<?php

namespace Olamilekan\GoogleSheets\Concerns;

use Illuminate\Support\Collection;

trait HasHeaders
{
    protected bool $firstRowAsHeader = true;

    public function withoutHeaders(): static
    {
        $this->firstRowAsHeader = false;

        return $this;
    }

    public function withHeaders(): static
    {
        $this->firstRowAsHeader = true;

        return $this;
    }

    protected function mapRowsToHeaders(array $rows): Collection
    {
        if (empty($rows)) {
            return collect();
        }

        if (! $this->firstRowAsHeader) {
            return collect($rows);
        }

        $headers = array_shift($rows);
        $headerCount = count($headers);

        return collect($rows)->map(function (array $row) use ($headers, $headerCount) {
            $row = array_pad($row, $headerCount, null);

            return array_combine($headers, array_slice($row, 0, $headerCount));
        })->values();
    }
}
