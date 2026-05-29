<?php

namespace Olamilekan\GoogleSheets\Imports;

use Illuminate\Support\Collection;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;

abstract class SheetImport
{
    public function handle(SheetInterface $sheet): mixed
    {
        $rows = $sheet->all();

        if (method_exists($this, 'rules')) {
            $sheet->validate($this->rules(), errorSheetName: $this->validationErrorSheet());
        }

        if (method_exists($this, 'collection')) {
            return $this->collection($rows);
        }

        return $rows->map(fn (array $row) => $this->model($row));
    }

    public function model(array $row): mixed
    {
        return $row;
    }

    protected function validationErrorSheet(): ?string
    {
        if (method_exists($this, 'errorSheet')) {
            return $this->errorSheet();
        }

        return $this->errorSheet ?? null;
    }
}
