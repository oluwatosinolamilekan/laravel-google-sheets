<?php

namespace Olamilekan\GoogleSheets;

use Google\Service\Sheets as GoogleSheetsService;
use Google\Service\Sheets\AppendValuesResponse;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\Request as GoogleRequest;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\UpdateValuesResponse;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Olamilekan\GoogleSheets\Concerns\HasCache;
use Olamilekan\GoogleSheets\Concerns\HasHeaders;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\Exceptions\GoogleSheetsException;
use Olamilekan\GoogleSheets\Imports\ImportDiff;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class Sheet implements SheetInterface
{
    use HasCache;
    use HasHeaders;

    protected GoogleSheetsService $service;

    protected string $spreadsheetId;

    protected string $sheetName;

    protected ?string $currentRange = null;

    protected string $valueRenderOption;

    protected string $valueInputOption;

    protected string $dateTimeRenderOption;

    public function __construct(
        GoogleSheetsService $service,
        string $spreadsheetId,
        string $sheetName = 'Sheet1',
        array $options = []
    ) {
        $this->service = $service;
        $this->spreadsheetId = $spreadsheetId;
        $this->sheetName = $sheetName;
        $this->valueRenderOption = $options['value_render_option'] ?? 'FORMATTED_VALUE';
        $this->valueInputOption = $options['value_input_option'] ?? 'USER_ENTERED';
        $this->dateTimeRenderOption = $options['date_time_render_option'] ?? 'FORMATTED_STRING';
    }

    public function spreadsheet(string $spreadsheetId): static
    {
        $this->spreadsheetId = $spreadsheetId;

        return $this;
    }

    public function sheet(string $sheetName): static
    {
        $this->sheetName = $sheetName;
        $this->currentRange = null;

        return $this;
    }

    public function range(string $range): static
    {
        $this->currentRange = $range;

        return $this;
    }

    // -------------------------------------------------------------------------
    //  Read Operations
    // -------------------------------------------------------------------------

    public function get(): Collection
    {
        $range = $this->resolveRange();

        $rows = $this->remember("get:{$this->spreadsheetId}:{$range}", function () use ($range) {
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId,
                $range,
                [
                    'valueRenderOption' => $this->valueRenderOption,
                    'dateTimeRenderOption' => $this->dateTimeRenderOption,
                ]
            );

            return $response->getValues() ?? [];
        });

        return $this->mapRowsToHeaders($rows);
    }

    public function all(): Collection
    {
        $this->currentRange = null;

        return $this->get();
    }

    public function first(): ?array
    {
        return $this->get()->first();
    }

    public function last(): ?array
    {
        return $this->get()->last();
    }

    public function count(): int
    {
        return $this->withoutHeaders()->get()->count() - ($this->firstRowAsHeader ? 0 : 0);
    }

    public function headers(): array
    {
        $range = "{$this->sheetName}!1:1";

        $response = $this->service->spreadsheets_values->get(
            $this->spreadsheetId,
            $range,
            ['valueRenderOption' => $this->valueRenderOption]
        );

        $values = $response->getValues() ?? [];

        return $values[0] ?? [];
    }

    public function find(string $column, mixed $value): Collection
    {
        return $this->get()->filter(function (array $row) use ($column, $value) {
            return ($row[$column] ?? null) == $value;
        })->values();
    }

    public function where(string $column, mixed $operator, mixed $value = null): Collection
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->get()->filter(function (array $row) use ($column, $operator, $value) {
            $cell = $row[$column] ?? null;

            return match ($operator) {
                '=', '==' => $cell == $value,
                '===' => $cell === $value,
                '!=' => $cell != $value,
                '>' => $cell > $value,
                '>=' => $cell >= $value,
                '<' => $cell < $value,
                '<=' => $cell <= $value,
                'like' => str_contains(strtolower((string) $cell), strtolower((string) $value)),
                default => $cell == $value,
            };
        })->values();
    }

    public function chunk(int $size, callable $callback): void
    {
        $this->get()->chunk($size)->each($callback);
    }

    public function lazy(int $chunkSize = 500): LazyCollection
    {
        return LazyCollection::make(function () use ($chunkSize) {
            foreach ($this->get()->chunk($chunkSize) as $chunk) {
                foreach ($chunk as $row) {
                    yield $row;
                }
            }
        });
    }

    public function validate(
        array $rules,
        array $messages = [],
        array $attributes = [],
        ?string $errorSheetName = null
    ): Collection
    {
        if ($errorSheetName !== null) {
            return $this->validateRowsAndWriteErrors($rules, $messages, $attributes, $errorSheetName);
        }

        return $this->get()->map(function (array $row, int $index) use ($rules, $messages, $attributes) {
            return Validator::make($row, $rules, $messages, $attributes)
                ->validateWithBag('googleSheetsRow' . ($index + 1));
        });
    }

    public function validateWithErrorSheet(
        array $rules,
        string $errorSheetName = 'Import Errors',
        array $messages = [],
        array $attributes = []
    ): Collection {
        return $this->validate($rules, $messages, $attributes, $errorSheetName);
    }

    public function requireHeaders(array $headers): static
    {
        $missing = array_values(array_diff($headers, $this->headers()));

        if ($missing !== []) {
            throw new GoogleSheetsException('Missing required Google Sheet headers: ' . implode(', ', $missing));
        }

        return $this;
    }

    public function import(SheetImport $import): mixed
    {
        return $import->handle($this);
    }

    public function diffAgainst(mixed $target, string $key): ImportDiff
    {
        return new ImportDiff($this, $target, $key);
    }

    public function export(SheetExport $export): int
    {
        return $export->handle($this);
    }

    // -------------------------------------------------------------------------
    //  Write Operations
    // -------------------------------------------------------------------------

    public function append(array $rows): int
    {
        $rows = $this->normalizeRows($rows);
        $range = $this->resolveRange();

        $body = new ValueRange(['values' => $rows]);

        $response = $this->service->spreadsheets_values->append(
            $this->spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => $this->valueInputOption]
        );

        $this->invalidateReadCache();

        return $response->getUpdates()?->getUpdatedRows() ?? 0;
    }

    public function appendAssoc(array $rows): int
    {
        return $this->append($this->mapAssociativeRowsToHeaders($rows));
    }

    public function update(array $rows): int
    {
        $rows = $this->normalizeRows($rows);
        $range = $this->resolveRange();

        $body = new ValueRange(['values' => $rows]);

        $response = $this->service->spreadsheets_values->update(
            $this->spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => $this->valueInputOption]
        );

        $this->invalidateReadCache();

        return $response->getUpdatedRows() ?? 0;
    }

    public function updateAssoc(array $rows): int
    {
        return $this->update($this->mapAssociativeRowsToHeaders($rows));
    }

    public function upsert(string $keyColumn, array $rows): int
    {
        $this->requireHeaders([$keyColumn]);

        $headers = $this->headers();
        $existing = $this->all();
        $updates = [];
        $appends = [];

        foreach ($rows as $row) {
            $key = $row[$keyColumn] ?? null;

            if ($key === null || $key === '') {
                $appends[] = $row;
                continue;
            }

            $rowIndex = $existing->search(fn (array $existingRow) => (string) ($existingRow[$keyColumn] ?? '') === (string) $key);

            if ($rowIndex === false) {
                $appends[] = $row;
                continue;
            }

            $sheetRowNumber = $this->firstRowAsHeader ? $rowIndex + 2 : $rowIndex + 1;
            $updates[$this->rowRange($sheetRowNumber, count($headers))] = [
                $this->mapAssociativeRowToHeaders($row, $headers),
            ];
        }

        $updated = $updates === [] ? 0 : $this->batchUpdate($updates);
        $appended = $appends === [] ? 0 : $this->appendAssoc($appends);

        return $updated + $appended;
    }

    public function batchUpdate(array $data): int
    {
        $valueRanges = [];

        foreach ($data as $range => $rows) {
            $fullRange = str_contains($range, '!') ? $range : "{$this->sheetName}!{$range}";
            $valueRanges[] = new ValueRange([
                'range' => $fullRange,
                'values' => $this->normalizeRows($rows),
            ]);
        }

        $body = new \Google\Service\Sheets\BatchUpdateValuesRequest([
            'valueInputOption' => $this->valueInputOption,
            'data' => $valueRanges,
        ]);

        $response = $this->service->spreadsheets_values->batchUpdate(
            $this->spreadsheetId,
            $body
        );

        $this->invalidateReadCache();

        return $response->getTotalUpdatedRows() ?? 0;
    }

    public function clear(): bool
    {
        $range = $this->resolveRange();

        $this->service->spreadsheets_values->clear(
            $this->spreadsheetId,
            $range,
            new ClearValuesRequest()
        );

        $this->invalidateReadCache();

        return true;
    }

    // -------------------------------------------------------------------------
    //  Sheet / Tab Management
    // -------------------------------------------------------------------------

    public function createSheet(string $title): static
    {
        $requests = [
            new GoogleRequest([
                'addSheet' => [
                    'properties' => ['title' => $title],
                ],
            ]),
        ];

        $this->executeBatchRequest($requests);

        return $this->sheet($title);
    }

    public function deleteSheet(string $title): bool
    {
        $sheetId = $this->getSheetIdByTitle($title);

        if ($sheetId === null) {
            throw new GoogleSheetsException("Sheet tab [{$title}] not found in spreadsheet.");
        }

        $requests = [
            new GoogleRequest([
                'deleteSheet' => ['sheetId' => $sheetId],
            ]),
        ];

        $this->executeBatchRequest($requests);

        return true;
    }

    public function duplicateSheet(string $sourceTitle, string $newTitle): static
    {
        $sheetId = $this->getSheetIdByTitle($sourceTitle);

        if ($sheetId === null) {
            throw new GoogleSheetsException("Sheet tab [{$sourceTitle}] not found.");
        }

        $requests = [
            new GoogleRequest([
                'duplicateSheet' => [
                    'sourceSheetId' => $sheetId,
                    'newSheetName' => $newTitle,
                ],
            ]),
        ];

        $this->executeBatchRequest($requests);

        return $this->sheet($newTitle);
    }

    public function listSheets(): array
    {
        $spreadsheet = $this->getSpreadsheet();

        return collect($spreadsheet->getSheets())
            ->map(fn ($sheet) => $sheet->getProperties()->getTitle())
            ->all();
    }

    public function sheetExists(string $title): bool
    {
        return in_array($title, $this->listSheets(), true);
    }

    public function namedRange(string $name): static
    {
        return $this->range($name);
    }

    public function listNamedRanges(): array
    {
        return collect($this->getSpreadsheet()->getNamedRanges() ?? [])
            ->map(fn ($range) => $range->getName())
            ->all();
    }

    public function formula(string $formula): string
    {
        return str_starts_with($formula, '=') ? $formula : "={$formula}";
    }

    public function boldHeader(): static
    {
        return $this->formatRange('1:1', [
            'textFormat' => ['bold' => true],
        ], 'userEnteredFormat.textFormat.bold');
    }

    public function freezeRows(int $rows = 1): static
    {
        $sheetId = $this->getSheetIdByTitle($this->sheetName);

        if ($sheetId === null) {
            throw new GoogleSheetsException("Sheet tab [{$this->sheetName}] not found.");
        }

        $this->executeBatchRequest([
            new GoogleRequest([
                'updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $sheetId,
                        'gridProperties' => ['frozenRowCount' => $rows],
                    ],
                    'fields' => 'gridProperties.frozenRowCount',
                ],
            ]),
        ]);

        return $this;
    }

    public function autoResizeColumns(int $startColumn = 1, ?int $endColumn = null): static
    {
        $sheetId = $this->getSheetIdByTitle($this->sheetName);

        if ($sheetId === null) {
            throw new GoogleSheetsException("Sheet tab [{$this->sheetName}] not found.");
        }

        $dimensions = [
            'sheetId' => $sheetId,
            'dimension' => 'COLUMNS',
            'startIndex' => max(0, $startColumn - 1),
        ];

        if ($endColumn !== null) {
            $dimensions['endIndex'] = $endColumn;
        }

        $this->executeBatchRequest([
            new GoogleRequest([
                'autoResizeDimensions' => [
                    'dimensions' => $dimensions,
                ],
            ]),
        ]);

        return $this;
    }

    public function formatRange(string $range, array $format, string $fields = 'userEnteredFormat'): static
    {
        $this->executeBatchRequest([
            new GoogleRequest([
                'repeatCell' => [
                    'range' => $this->gridRange($range),
                    'cell' => [
                        'userEnteredFormat' => $format,
                    ],
                    'fields' => $fields,
                ],
            ]),
        ]);

        return $this;
    }

    // -------------------------------------------------------------------------
    //  Spreadsheet Metadata
    // -------------------------------------------------------------------------

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->service->spreadsheets->get($this->spreadsheetId);
    }

    public function getTitle(): string
    {
        return $this->getSpreadsheet()->getProperties()->getTitle();
    }

    public function getSpreadsheetId(): string
    {
        return $this->spreadsheetId;
    }

    public function getSheetName(): string
    {
        return $this->sheetName;
    }

    public function getService(): GoogleSheetsService
    {
        return $this->service;
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    protected function resolveRange(): string
    {
        if ($this->currentRange) {
            return str_contains($this->currentRange, '!')
                ? $this->currentRange
                : "{$this->sheetName}!{$this->currentRange}";
        }

        return $this->sheetName;
    }

    protected function normalizeRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        if (! is_array(reset($rows))) {
            return [$rows];
        }

        return array_values($rows);
    }

    protected function mapAssociativeRowsToHeaders(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = $this->headers();

        return collect($rows)
            ->map(fn (array $row) => $this->mapAssociativeRowToHeaders($row, $headers))
            ->all();
    }

    protected function mapAssociativeRowToHeaders(array $row, array $headers): array
    {
        return collect($headers)
            ->map(fn (string $header) => $row[$header] ?? null)
            ->all();
    }

    protected function rowRange(int $rowNumber, int $columnCount): string
    {
        return 'A' . $rowNumber . ':' . $this->columnName($columnCount) . $rowNumber;
    }

    protected function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    protected function columnIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    protected function gridRange(string $range): array
    {
        $sheetId = $this->getSheetIdByTitle($this->sheetName);

        if ($sheetId === null) {
            throw new GoogleSheetsException("Sheet tab [{$this->sheetName}] not found.");
        }

        $range = str_contains($range, '!') ? explode('!', $range, 2)[1] : $range;
        $gridRange = ['sheetId' => $sheetId];

        if (preg_match('/^([A-Z]+)?(\d+)?(?::([A-Z]+)?(\d+)?)?$/i', $range, $matches) !== 1) {
            throw new GoogleSheetsException("Invalid A1 range [{$range}].");
        }

        [, $startColumn, $startRow, $endColumn, $endRow] = array_pad($matches, 5, null);

        if ($startColumn) {
            $gridRange['startColumnIndex'] = $this->columnIndex($startColumn);
        }

        if ($startRow) {
            $gridRange['startRowIndex'] = ((int) $startRow) - 1;
        }

        if ($endColumn) {
            $gridRange['endColumnIndex'] = $this->columnIndex($endColumn) + 1;
        } elseif ($startColumn && ! $endRow) {
            $gridRange['endColumnIndex'] = $this->columnIndex($startColumn) + 1;
        }

        if ($endRow) {
            $gridRange['endRowIndex'] = (int) $endRow;
        } elseif ($startRow && ! $endColumn) {
            $gridRange['endRowIndex'] = (int) $startRow;
        }

        return $gridRange;
    }

    protected function validateRowsAndWriteErrors(
        array $rules,
        array $messages,
        array $attributes,
        string $errorSheetName
    ): Collection {
        $validRows = collect();
        $errors = [];

        foreach ($this->get() as $index => $row) {
            $validator = Validator::make($row, $rules, $messages, $attributes);

            if ($validator->fails()) {
                $sheetRowNumber = $this->firstRowAsHeader ? $index + 2 : $index + 1;

                foreach ($validator->errors()->toArray() as $field => $fieldMessages) {
                    foreach ($fieldMessages as $message) {
                        $errors[] = [
                            'row' => $sheetRowNumber,
                            'field' => $field,
                            'message' => $message,
                        ];
                    }
                }

                continue;
            }

            $validRows->push($validator->validated());
        }

        if ($errors !== []) {
            $this->writeValidationErrors($errors, $errorSheetName);

            throw ValidationException::withMessages(
                collect($errors)
                    ->groupBy(fn (array $error) => 'row_' . $error['row'] . '.' . $error['field'])
                    ->map(fn (Collection $group) => $group->pluck('message')->all())
                    ->all()
            );
        }

        return $validRows;
    }

    protected function writeValidationErrors(array $errors, string $errorSheetName): void
    {
        $sheetName = $this->sheetName;
        $currentRange = $this->currentRange;

        try {
            if (! $this->sheetExists($errorSheetName)) {
                $this->createSheet($errorSheetName);
            }

            $rows = collect($errors)
                ->map(fn (array $error) => [$error['row'], $error['field'], $error['message']])
                ->prepend(['Row', 'Field', 'Message'])
                ->all();

            $this->sheet($errorSheetName)->clear();
            $this->range('A1:C' . count($rows))->update($rows);
            $this->boldHeader();
            $this->autoResizeColumns(1, 3);
        } finally {
            $this->sheetName = $sheetName;
            $this->currentRange = $currentRange;
        }
    }

    protected function getSheetIdByTitle(string $title): ?int
    {
        $spreadsheet = $this->getSpreadsheet();

        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === $title) {
                return $sheet->getProperties()->getSheetId();
            }
        }

        return null;
    }

    protected function executeBatchRequest(array $requests): void
    {
        $body = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);

        $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $body);
    }

    protected function invalidateReadCache(): void
    {
        $this->flushRememberedCacheKeys();
    }
}
