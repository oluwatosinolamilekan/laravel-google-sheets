<?php

namespace Olamilekan\GoogleSheets\Testing;

use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class FakeGoogleSheetsManager
{
    /** @var array<string, FakeSheet> */
    protected array $connections = [];

    public function __construct(array $sheets = [])
    {
        foreach ($sheets as $name => $rows) {
            $this->connections[$name] = new FakeSheet($rows);
        }
    }

    public function connection(?string $name = null): FakeSheet
    {
        $name = $name ?? 'default';

        return $this->connections[$name] ??= new FakeSheet();
    }

    public function make(string $spreadsheetId, string $sheetName = 'Sheet1'): FakeSheet
    {
        return new FakeSheet();
    }

    public function import(SheetImport $import, ?string $connection = null): mixed
    {
        return $this->connection($connection)->import($import);
    }

    public function export(SheetExport $export, ?string $connection = null): int
    {
        return $this->connection($connection)->export($export);
    }

    public function assertAppended(?string $connection, array $row): void
    {
        $this->connection($connection)->assertAppended($row);
    }

    public function assertUpdated(?string $connection, string $range, array $rows): void
    {
        $this->connection($connection)->assertUpdated($range, $rows);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
