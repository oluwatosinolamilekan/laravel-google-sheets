<?php

namespace Olamilekan\GoogleSheets\Exports;

use Illuminate\Support\Collection;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;

abstract class SheetExport
{
    public bool $withHeadings = true;

    public bool $replace = false;

    public string $range = 'A1';

    public string $clearRange = 'A:Z';

    public function handle(SheetInterface $sheet): int
    {
        $rows = $this->rows();

        if ($this->withHeadings && method_exists($this, 'headings')) {
            $rows = $rows->prepend($this->headings());
        }

        if ($this->replace) {
            $sheet->range($this->clearRange)->clear();

            return $sheet->range($this->range)->update($rows->values()->all());
        }

        return $sheet->append($rows->values()->all());
    }

    protected function rows(): Collection
    {
        if (method_exists($this, 'collection')) {
            return collect($this->collection());
        }

        return collect($this->array());
    }

    public function array(): array
    {
        return [];
    }
}
