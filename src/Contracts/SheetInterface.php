<?php

namespace Olamilekan\GoogleSheets\Contracts;

use Illuminate\Support\Collection;
use Olamilekan\GoogleSheets\Imports\ImportDiff;
use Olamilekan\GoogleSheets\Sync\SyncReport;

interface SheetInterface
{
    public function spreadsheet(string $spreadsheetId): static;

    public function sheet(string $sheetName): static;

    public function range(string $range): static;

    public function get(): Collection;

    public function all(): Collection;

    public function first(): ?array;

    public function append(array $rows): int;

    public function update(array $rows): int;

    public function clear(): bool;

    public function find(string $column, mixed $value): Collection;

    public function headers(): array;

    public function diffAgainst(mixed $target, string $key): ImportDiff;

    public function syncRows(array|Collection $rows, string $keyColumn, array $options = []): SyncReport;

    public function syncFromModel(string $modelClass, string $keyColumn, array $options = []): SyncReport;

    public function syncToModel(string $modelClass, string $keyColumn, array $options = []): SyncReport;

    public function importCsv(string $path, string $keyColumn, array $options = []): SyncReport;

    public function exportCsv(string $path, array $options = []): SyncReport;

    public function syncFromApi(string $url, string $keyColumn, array $options = []): SyncReport;

    public function syncToApi(string $url, array $options = []): SyncReport;

    public function syncTwoWay(mixed $target, string $keyColumn, array $options = []): SyncReport;
}
