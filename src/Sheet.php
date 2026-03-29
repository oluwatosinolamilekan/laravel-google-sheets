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
use Olamilekan\GoogleSheets\Concerns\HasCache;
use Olamilekan\GoogleSheets\Concerns\HasHeaders;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;
use Olamilekan\GoogleSheets\Exceptions\GoogleSheetsException;

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
        $range = $this->resolveRange();

        $this->forgetCache("get:{$this->spreadsheetId}:{$range}");
    }
}
