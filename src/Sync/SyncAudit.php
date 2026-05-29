<?php

namespace Olamilekan\GoogleSheets\Sync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncAudit
{
    protected static array $records = [];

    public static function record(SyncReport $report): void
    {
        static::$records[] = $report->toArray();

        Log::info('Google Sheets sync completed.', $report->toArray());
    }

    public static function records(): Collection
    {
        return collect(static::$records);
    }

    public static function clear(): void
    {
        static::$records = [];
    }
}
