<?php

namespace Olamilekan\GoogleSheets\Contracts;

use Illuminate\Support\Collection;
use Olamilekan\GoogleSheets\Imports\ImportDiff;

interface SheetInterface
{
    public function spreadsheet(string $spreadsheetId): static;

    public function sheet(string $sheetName): static;

    public function range(string $range): static;

    public function withRetries(?int $attempts = null, ?int $delay = null): static;

    public function withoutRetries(): static;

    public function get(): Collection;

    public function all(): Collection;

    public function first(): ?array;

    public function append(array $rows): int;

    public function update(array $rows): int;

    public function clear(): bool;

    public function find(string $column, mixed $value): Collection;

    public function headers(): array;

    public function diffAgainst(mixed $target, string $key): ImportDiff;
}
