<?php

namespace Olamilekan\GoogleSheets\Imports;

use Illuminate\Support\Collection;
use LogicException;
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

    public function dryRun(SheetInterface $sheet): ImportDiffPreview
    {
        if (! method_exists($this, 'target') || ! method_exists($this, 'key')) {
            throw new LogicException('Dry-run imports must define target() and key() methods, or override dryRun().');
        }

        $diff = $sheet->diffAgainst($this->target(), $this->key());

        if (method_exists($this, 'rules')) {
            $diff->rules($this->rules());
        }

        if (method_exists($this, 'dryRunOnly')) {
            $diff->only($this->dryRunOnly());
        }

        if (method_exists($this, 'dryRunExcept')) {
            $diff->except($this->dryRunExcept());
        }

        return $diff->preview();
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
