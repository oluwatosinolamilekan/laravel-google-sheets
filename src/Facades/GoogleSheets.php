<?php

namespace Olamilekan\GoogleSheets\Facades;

use Illuminate\Support\Facades\Facade;
use Olamilekan\GoogleSheets\GoogleSheetsManager;
use Olamilekan\GoogleSheets\Sheet;
use Olamilekan\GoogleSheets\Testing\FakeGoogleSheetsManager;

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
 * @method static int appendAssoc(array $rows)
 * @method static int updateAssoc(array $rows)
 * @method static int upsert(string $keyColumn, array $rows)
 * @method static \Illuminate\Support\LazyCollection lazy(int $chunkSize = 500)
 * @method static \Illuminate\Support\Collection validate(array $rules, array $messages = [], array $attributes = [])
 * @method static Sheet requireHeaders(array $headers)
 * @method static mixed import(\Olamilekan\GoogleSheets\Imports\SheetImport $import, ?string $connection = null)
 * @method static int export(\Olamilekan\GoogleSheets\Exports\SheetExport $export, ?string $connection = null)
 * @method static Sheet namedRange(string $name)
 * @method static array listNamedRanges()
 * @method static string formula(string $formula)
 * @method static Sheet boldHeader()
 * @method static Sheet freezeRows(int $rows = 1)
 * @method static Sheet autoResizeColumns(int $startColumn = 1, ?int $endColumn = null)
 * @method static Sheet formatRange(string $range, array $format, string $fields = 'userEnteredFormat')
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncRows(array|\Illuminate\Support\Collection $rows, string $keyColumn, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncFromModel(string $modelClass, string $keyColumn, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncToModel(string $modelClass, string $keyColumn, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport importCsv(string $path, string $keyColumn, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport exportCsv(string $path, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncFromApi(string $url, string $keyColumn, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncToApi(string $url, array $options = [])
 * @method static \Olamilekan\GoogleSheets\Sync\SyncReport syncTwoWay(mixed $target, string $keyColumn, array $options = [])
 * @method static \Illuminate\Bus\PendingDispatch queueSync(string $method, array $arguments = [], ?string $queue = null, ?string $connection = null)
 * @method static \Illuminate\Support\Collection syncAuditLog()
 *
 * @see \Olamilekan\GoogleSheets\GoogleSheetsManager
 */
class GoogleSheets extends Facade
{
    public static function fake(array $sheets = []): FakeGoogleSheetsManager
    {
        $fake = new FakeGoogleSheetsManager($sheets);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return GoogleSheetsManager::class;
    }
}
