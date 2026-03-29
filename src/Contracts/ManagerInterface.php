<?php

namespace Olamilekan\GoogleSheets\Contracts;

interface ManagerInterface
{
    public function connection(?string $name = null): SheetInterface;

    public function getDefaultConnection(): string;

    public function getConnections(): array;
}
