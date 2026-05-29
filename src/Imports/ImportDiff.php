<?php

namespace Olamilekan\GoogleSheets\Imports;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;

class ImportDiff
{
    protected array $rules = [];

    protected array $only = [];

    protected array $except = [];

    public function __construct(
        protected SheetInterface $sheet,
        protected mixed $target,
        protected string $key,
    ) {
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function only(array $columns): static
    {
        $this->only = $columns;

        return $this;
    }

    public function except(array $columns): static
    {
        $this->except = $columns;

        return $this;
    }

    public function preview(): ImportDiffPreview
    {
        $sheetRows = $this->sheet->all()->values();
        $targetRows = $this->collectTargetRows($this->target)->values();

        $sheetDuplicates = $this->duplicateKeys($sheetRows);
        $targetDuplicates = $this->duplicateKeys($targetRows);
        $duplicateKeys = $sheetDuplicates->keys()->merge($targetDuplicates->keys())->unique()->values();

        $invalid = collect();
        $conflicts = collect();
        $validSheetRows = collect();

        $sheetRows->each(function (array $row, int $index) use ($duplicateKeys, $invalid, $conflicts, $validSheetRows) {
            $key = $this->rowKey($row);
            $rowNumber = $index + 2;

            if ($key === null) {
                $invalid->push([
                    'row_number' => $rowNumber,
                    'row' => $row,
                    'errors' => [$this->key => ['The import key is missing.']],
                ]);

                return;
            }

            $validator = Validator::make($row, $this->rules);

            if ($validator->fails()) {
                $invalid->push([
                    'row_number' => $rowNumber,
                    'key' => $key,
                    'row' => $row,
                    'errors' => $validator->errors()->toArray(),
                ]);

                return;
            }

            if ($duplicateKeys->contains($this->keyFingerprint($key))) {
                $conflicts->push([
                    'row_number' => $rowNumber,
                    'key' => $key,
                    'row' => $row,
                    'reason' => 'Duplicate key found in sheet rows or target rows.',
                ]);

                return;
            }

            $validSheetRows->push($row);
        });

        $targetRows->each(function (array $row, int $index) use ($targetDuplicates, $conflicts) {
            $key = $this->rowKey($row);

            if ($key !== null && $targetDuplicates->has($this->keyFingerprint($key))) {
                $conflicts->push([
                    'row_number' => $index + 1,
                    'key' => $key,
                    'row' => $row,
                    'source' => 'target',
                    'reason' => 'Duplicate key found in target rows.',
                ]);
            }
        });

        $targetByKey = $targetRows
            ->filter(fn (array $row) => $this->rowKey($row) !== null && ! $targetDuplicates->has($this->keyFingerprint($this->rowKey($row))))
            ->keyBy(fn (array $row) => $this->keyFingerprint($this->rowKey($row)));

        $sheetByKey = $validSheetRows->keyBy(fn (array $row) => $this->keyFingerprint($this->rowKey($row)));

        $new = collect();
        $changed = collect();

        $validSheetRows->each(function (array $row) use ($targetByKey, $new, $changed) {
            $key = $this->rowKey($row);
            $targetRow = $targetByKey->get($this->keyFingerprint($key));

            if ($targetRow === null) {
                $new->push($row);

                return;
            }

            $changes = $this->changes($row, $targetRow);

            if ($changes !== []) {
                $changed->push([
                    'key' => $key,
                    'row' => $row,
                    'existing' => $targetRow,
                    'changes' => $changes,
                ]);
            }
        });

        $deleted = $targetByKey
            ->reject(fn (array $row, string $key) => $sheetByKey->has($key))
            ->values();

        return new ImportDiffPreview(
            new: $new->values(),
            changed: $changed->values(),
            deleted: $deleted->values(),
            invalid: $invalid->values(),
            conflicts: $conflicts->values(),
        );
    }

    protected function collectTargetRows(mixed $target): Collection
    {
        if (is_callable($target) && ! is_array($target)) {
            $target = $target();
        }

        if ($target instanceof Collection) {
            return $target->map(fn (mixed $row) => $this->normalizeRow($row))->values();
        }

        if ($target instanceof Arrayable) {
            $target = $target->toArray();
        }

        if (is_object($target) && method_exists($target, 'get')) {
            return collect($target->get())->map(fn (mixed $row) => $this->normalizeRow($row))->values();
        }

        if (is_object($target) && method_exists($target, 'cursor')) {
            return collect($target->cursor())->map(fn (mixed $row) => $this->normalizeRow($row))->values();
        }

        return $this->collectArrayRows($target);
    }

    protected function collectArrayRows(mixed $rows): Collection
    {
        if (! is_array($rows)) {
            return collect($rows)->map(fn (mixed $row) => $this->normalizeRow($row))->values();
        }

        if ($rows === []) {
            return collect();
        }

        if (! array_is_list($rows)) {
            return collect([$this->normalizeRow($rows)]);
        }

        return collect($rows)->map(fn (mixed $row) => $this->normalizeRow($row))->values();
    }

    protected function normalizeRow(mixed $row): array
    {
        if ($row instanceof Arrayable) {
            return $row->toArray();
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        if (is_object($row)) {
            return get_object_vars($row);
        }

        return (array) $row;
    }

    protected function duplicateKeys(Collection $rows): Collection
    {
        return $rows
            ->map(fn (array $row) => $this->rowKey($row))
            ->filter(fn (?string $key) => $key !== null)
            ->map(fn (string $key) => $this->keyFingerprint($key))
            ->countBy()
            ->filter(fn (int $count) => $count > 1);
    }

    protected function rowKey(array $row): ?string
    {
        $value = $row[$this->key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function keyFingerprint(?string $key): string
    {
        return 'key:' . $key;
    }

    protected function changes(array $sheetRow, array $targetRow): array
    {
        $columns = $this->comparableColumns($sheetRow, $targetRow);
        $changes = [];

        foreach ($columns as $column) {
            $incoming = $sheetRow[$column] ?? null;
            $existing = $targetRow[$column] ?? null;

            if ($this->normalizeValue($incoming) !== $this->normalizeValue($existing)) {
                $changes[$column] = [
                    'from' => $existing,
                    'to' => $incoming,
                ];
            }
        }

        return $changes;
    }

    protected function comparableColumns(array $sheetRow, array $targetRow): array
    {
        $columns = $this->only !== []
            ? $this->only
            : array_values(array_intersect(array_keys($sheetRow), array_keys($targetRow)));

        return array_values(array_diff($columns, array_merge([$this->key], $this->except)));
    }

    protected function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value) ?: '';
        }

        return trim((string) $value);
    }
}
