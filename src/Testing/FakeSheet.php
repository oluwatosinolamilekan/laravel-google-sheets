<?php

namespace Olamilekan\GoogleSheets\Testing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\Exceptions\GoogleSheetsException;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class FakeSheet implements SheetInterface
{
    protected array $rows;

    protected string $sheetName = 'Sheet1';

    protected ?string $range = null;

    public array $appends = [];

    public array $updates = [];

    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    public function sheet(string $sheetName): static
    {
        $this->sheetName = $sheetName;

        return $this;
    }

    public function spreadsheet(string $spreadsheetId): static
    {
        return $this;
    }

    public function range(string $range): static
    {
        $this->range = $range;

        return $this;
    }

    public function all(): Collection
    {
        return Collection::make($this->rows);
    }

    public function get(): Collection
    {
        return $this->all();
    }

    public function first(): ?array
    {
        return $this->all()->first();
    }

    public function headers(): array
    {
        $first = $this->first();

        return $first ? array_keys($first) : [];
    }

    public function append(array $rows): int
    {
        $rows = $this->normalizeRows($rows);
        $this->appends[] = $rows;
        array_push($this->rows, ...$rows);

        return count($rows);
    }

    public function appendAssoc(array $rows): int
    {
        return $this->append($rows);
    }

    public function update(array $rows): int
    {
        $rows = $this->normalizeRows($rows);
        $this->updates[] = ['range' => $this->range, 'rows' => $rows];

        return count($rows);
    }

    public function updateAssoc(array $rows): int
    {
        return $this->update($rows);
    }

    public function clear(): bool
    {
        $this->rows = [];

        return true;
    }

    public function find(string $column, mixed $value): Collection
    {
        return $this->all()
            ->filter(fn (array $row) => ($row[$column] ?? null) == $value)
            ->values();
    }

    public function lazy(int $chunkSize = 500): LazyCollection
    {
        return LazyCollection::make($this->rows);
    }

    public function validate(array $rules, array $messages = [], array $attributes = []): Collection
    {
        return $this->all()->map(fn (array $row) => Validator::make($row, $rules, $messages, $attributes)->validate());
    }

    public function requireHeaders(array $headers): static
    {
        $missing = array_diff($headers, $this->headers());

        if ($missing !== []) {
            throw new GoogleSheetsException('Missing required Google Sheet headers: ' . implode(', ', $missing));
        }

        return $this;
    }

    public function import(SheetImport $import): mixed
    {
        return $import->handle($this);
    }

    public function export(SheetExport $export): int
    {
        return $export->handle($this);
    }

    public function assertAppended(array $row): void
    {
        $flattened = Collection::make($this->appends)->flatten(1)->all();

        if (! in_array($row, $flattened, true)) {
            throw new \RuntimeException('Expected row was not appended to Google Sheets.');
        }
    }

    public function assertUpdated(?string $range, array $rows): void
    {
        if (! in_array(['range' => $range, 'rows' => $rows], $this->updates, true)) {
            throw new \RuntimeException('Expected range was not updated in Google Sheets.');
        }
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this;
    }

    protected function normalizeRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return is_array(reset($rows)) ? array_values($rows) : [$rows];
    }
}
