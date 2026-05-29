<?php

namespace Olamilekan\GoogleSheets\Imports;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

class ImportDiffPreview implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly Collection $new,
        public readonly Collection $changed,
        public readonly Collection $deleted,
        public readonly Collection $invalid,
        public readonly Collection $conflicts,
    ) {
    }

    public function counts(): array
    {
        return [
            'new' => $this->new->count(),
            'changed' => $this->changed->count(),
            'deleted' => $this->deleted->count(),
            'invalid' => $this->invalid->count(),
            'conflicts' => $this->conflicts->count(),
        ];
    }

    public function hasChanges(): bool
    {
        return collect($this->counts())->sum() > 0;
    }

    public function toArray(): array
    {
        return [
            'new' => $this->new->values()->all(),
            'changed' => $this->changed->values()->all(),
            'deleted' => $this->deleted->values()->all(),
            'invalid' => $this->invalid->values()->all(),
            'conflicts' => $this->conflicts->values()->all(),
            'counts' => $this->counts(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
