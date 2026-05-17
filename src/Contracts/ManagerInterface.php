<?php

namespace Olamilekan\GoogleSheets\Contracts;

use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\Imports\SheetImport;

interface ManagerInterface
{
    public function connection(?string $name = null): SheetInterface;

    public function getDefaultConnection(): string;

    public function getConnections(): array;

    public function import(SheetImport $import, ?string $connection = null): mixed;

    public function export(SheetExport $export, ?string $connection = null): int;
}
