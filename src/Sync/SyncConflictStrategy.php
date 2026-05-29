<?php

namespace Olamilekan\GoogleSheets\Sync;

final class SyncConflictStrategy
{
    public const APP_WINS = 'app_wins';

    public const SHEET_WINS = 'sheet_wins';

    public const SKIP = 'skip';

    public const FAIL = 'fail';

    public static function normalize(string $strategy): string
    {
        return match ($strategy) {
            self::APP_WINS, 'incoming_wins' => self::APP_WINS,
            self::SHEET_WINS, 'existing_wins' => self::SHEET_WINS,
            self::SKIP => self::SKIP,
            self::FAIL, 'manual' => self::FAIL,
            default => self::APP_WINS,
        };
    }
}
