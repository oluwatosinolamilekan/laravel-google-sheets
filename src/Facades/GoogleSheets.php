<?php

namespace Olamilekan\GoogleSheets\Facades;

use Illuminate\Support\Facades\Facade;
use Olamilekan\GoogleSheets\GoogleSheetsManager;
use Olamilekan\GoogleSheets\Sheet;

/**
 * @method static Sheet connection(?string $name = null)
 * @method static Sheet make(string $spreadsheetId, string $sheetName = 'Sheet1')
 * @method static string getDefaultConnection()
 * @method static array getConnections()
 * @method static GoogleSheetsManager purge(?string $name = null)
 * @method static Sheet reconnect(?string $name = null)
 * @method static Sheet spreadsheet(string $spreadsheetId)
 * @method static Sheet sheet(string $sheetName)
 * @method static Sheet range(string $range)
 * @method static \Illuminate\Support\Collection get()
 * @method static \Illuminate\Support\Collection all()
 * @method static array|null first()
 * @method static int append(array $rows)
 * @method static int update(array $rows)
 * @method static bool clear()
 * @method static \Illuminate\Support\Collection find(string $column, mixed $value)
 * @method static \Illuminate\Support\Collection where(string $column, mixed $operator, mixed $value = null)
 * @method static array headers()
 * @method static Sheet createSheet(string $title)
 * @method static bool deleteSheet(string $title)
 * @method static Sheet duplicateSheet(string $sourceTitle, string $newTitle)
 * @method static array listSheets()
 * @method static bool sheetExists(string $title)
 *
 * @see \Olamilekan\GoogleSheets\GoogleSheetsManager
 */
class GoogleSheets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoogleSheetsManager::class;
    }
}
