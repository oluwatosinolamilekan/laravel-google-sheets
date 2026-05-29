# Laravel Google Sheets

A fluent Laravel package for reading, writing, and managing Google Sheets with first-class support for multiple spreadsheet connections.

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13
- A Google Cloud project with the Sheets API enabled
- A service account JSON credentials file

## Installation

```bash
composer require olamilekan/laravel-google-sheets
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=google-sheets-config
```

## Configuration

### 1. Credentials

Place your service account JSON file somewhere secure (e.g. `storage/app/google/service-account.json`) and set the path in your `.env`:

```dotenv
GOOGLE_SHEETS_CREDENTIALS_PATH=/path/to/service-account.json
```

### 2. Spreadsheet Connections

Define as many named connections as you need in `config/google-sheets.php`:

```php
'sheets' => [

    'default' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'sheet' => 'Sheet1',
    ],

    'users' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_USERS_SPREADSHEET_ID'),
        'sheet' => 'Users',
    ],

    'reports' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_REPORTS_SPREADSHEET_ID'),
        'sheet' => 'Monthly',
    ],

],
```

Set the default connection:

```dotenv
GOOGLE_SHEETS_DEFAULT_CONNECTION=default
GOOGLE_SHEETS_SPREADSHEET_ID=your-spreadsheet-id-here
```

---

## Usage

### Using the Facade

```php
use Olamilekan\GoogleSheets\Facades\GoogleSheets;
```

### Reading Data

```php
// All rows from the default connection (first row treated as headers)
$rows = GoogleSheets::all();

// Specific range
$rows = GoogleSheets::range('A1:D10')->get();

// First row only
$row = GoogleSheets::first();

// Get column headers
$headers = GoogleSheets::headers();

// Without header mapping (raw arrays)
$rows = GoogleSheets::connection('users')->withoutHeaders()->get();
```

### Querying Data

```php
// Find rows where a column matches a value
$admins = GoogleSheets::find('role', 'admin');

// Where clause with operators
$highScores = GoogleSheets::where('score', '>=', 90);

// Partial text matching
$results = GoogleSheets::where('name', 'like', 'john');
```

### Writing Data

```php
// Append rows
GoogleSheets::append([
    ['Alice', 'alice@example.com', 'admin'],
    ['Bob', 'bob@example.com', 'user'],
]);

// Update a specific range
GoogleSheets::range('A2:C2')->update([
    ['Alice Updated', 'alice-new@example.com', 'superadmin'],
]);

// Batch update multiple ranges at once
GoogleSheets::batchUpdate([
    'A2:C2' => [['Alice', 'alice@example.com', 'admin']],
    'A3:C3' => [['Bob', 'bob@example.com', 'user']],
]);

// Clear a range
GoogleSheets::range('A2:C100')->clear();
```

### Associative Rows, Upserts, And Validation

```php
// Map associative arrays to the sheet's header row before appending
GoogleSheets::connection('users')->appendAssoc([
    ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'admin'],
]);

// Update existing rows by key column and append missing rows
GoogleSheets::connection('users')->upsert('email', [
    ['name' => 'Alice Updated', 'email' => 'alice@example.com', 'role' => 'owner'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'role' => 'user'],
]);

// Validate imported rows with Laravel validation rules
$validRows = GoogleSheets::connection('users')->validate([
    'name' => ['required', 'string'],
    'email' => ['required', 'email'],
]);

// Ensure required sheet headers exist
GoogleSheets::connection('users')->requireHeaders(['name', 'email', 'role']);
```

### Import Diff Preview

Preview the impact of an import before writing anything. The preview separates new, changed, deleted, invalid, and conflict rows.

```php
use App\Models\User;
use Olamilekan\GoogleSheets\Facades\GoogleSheets;

$preview = GoogleSheets::connection('users')
    ->diffAgainst(User::query(), key: 'email')
    ->rules([
        'name' => ['required', 'string'],
        'email' => ['required', 'email'],
    ])
    ->preview();

$preview->counts();
// ['new' => 1, 'changed' => 2, 'deleted' => 0, 'invalid' => 1, 'conflicts' => 0]

$preview->new;       // rows in the sheet that are not in the query
$preview->changed;   // rows where sheet values differ from existing query values
$preview->deleted;   // query rows missing from the sheet
$preview->invalid;   // rows failing validation or missing the key
$preview->conflicts; // duplicate key rows in the sheet or query
```

By default, changed rows compare sheet columns that also exist on the query/model row, excluding the key. You may narrow the comparison:

```php
$preview = GoogleSheets::connection('users')
    ->diffAgainst(User::query(), key: 'email')
    ->only(['name', 'role'])
    ->except(['updated_at'])
    ->preview();
```

### Import And Export Classes

```php
use App\Models\User;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class UsersImport extends SheetImport
{
    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    public function model(array $row): User
    {
        return User::updateOrCreate(
            ['email' => $row['email']],
            ['name' => $row['name']]
        );
    }
}

GoogleSheets::import(new UsersImport(), 'users');
```

```php
use App\Models\Report;
use Olamilekan\GoogleSheets\Exports\SheetExport;

class ReportsExport extends SheetExport
{
    public bool $replace = true;

    public function headings(): array
    {
        return ['Date', 'Name', 'Total'];
    }

    public function collection()
    {
        return Report::query()
            ->latest()
            ->get()
            ->map(fn (Report $report) => [
                $report->created_at->toDateString(),
                $report->name,
                $report->total,
            ]);
    }
}

GoogleSheets::export(new ReportsExport(), 'reports');
```

### Multiple Connections

```php
// Switch between configured connections
$users  = GoogleSheets::connection('users')->all();
$reports = GoogleSheets::connection('reports')->all();

// Create an ad-hoc connection to any spreadsheet
$data = GoogleSheets::make('some-spreadsheet-id', 'TabName')->all();
```

### Switching Sheets (Tabs) at Runtime

```php
$sheet = GoogleSheets::connection('default');

$sheet1Data = $sheet->sheet('Sheet1')->all();
$sheet2Data = $sheet->sheet('Sheet2')->all();
```

### Sheet / Tab Management

```php
// List all sheet tabs in a spreadsheet
$tabs = GoogleSheets::listSheets();   // ['Sheet1', 'Users', 'Reports']

// Check if a tab exists
GoogleSheets::sheetExists('Users');   // true

// Create a new tab
GoogleSheets::createSheet('Archive');

// Duplicate an existing tab
GoogleSheets::duplicateSheet('Sheet1', 'Sheet1 Copy');

// Delete a tab
GoogleSheets::deleteSheet('Archive');
```

### Caching

Enable caching in config or at runtime to reduce API calls:

```php
// In config/google-sheets.php
'cache' => [
    'enabled' => true,
    'store'   => 'redis',
    'ttl'     => 300,  // seconds
    'prefix'  => 'google_sheets_',
],

// At runtime
$rows = GoogleSheets::enableCache(600)->all();
$rows = GoogleSheets::disableCache()->all();
```

Write operations now clear remembered read cache keys for the active spreadsheet, so cached ranges are refreshed after updates.

### Chunked Processing

```php
GoogleSheets::chunk(100, function ($chunk) {
    foreach ($chunk as $row) {
        // process each row
    }
});

GoogleSheets::lazy(500)->each(function (array $row) {
    // process one row at a time
});
```

### Formatting, Formulas, And Named Ranges

```php
GoogleSheets::connection('reports')
    ->sheet('Monthly')
    ->boldHeader()
    ->freezeRows(1)
    ->autoResizeColumns(1, 4);

GoogleSheets::connection('reports')->append([
    ['Total', GoogleSheets::formula('SUM(C2:C100)')],
]);

$summaryRows = GoogleSheets::connection('reports')
    ->namedRange('MonthlySummary')
    ->get();
```

### Testing

```php
use Olamilekan\GoogleSheets\Facades\GoogleSheets;

$fake = GoogleSheets::fake([
    'users' => [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
    ],
]);

GoogleSheets::connection('users')->appendAssoc([
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);

$fake->assertAppended('users', ['name' => 'Bob', 'email' => 'bob@example.com']);
```

### Artisan Commands

```bash
php artisan google-sheets:list users
php artisan google-sheets:clear reports --sheet=Monthly --range=A2:D100
php artisan google-sheets:sync "App\\Imports\\UsersImport" users
php artisan google-sheets:sync "App\\Exports\\ReportsExport" reports
```

### Spreadsheet Metadata

```php
$title = GoogleSheets::getTitle();
$id    = GoogleSheets::getSpreadsheetId();
```

### Dependency Injection

```php
use Olamilekan\GoogleSheets\GoogleSheetsManager;

class UserImportService
{
    public function __construct(
        protected GoogleSheetsManager $sheets
    ) {}

    public function import(): void
    {
        $rows = $this->sheets->connection('users')->all();

        foreach ($rows as $row) {
            User::updateOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name']]
            );
        }
    }
}
```

---

## API Reference

### `GoogleSheetsManager`

| Method | Description |
|---|---|
| `connection(?string $name)` | Get a named connection (lazy-loaded & cached) |
| `make(string $spreadsheetId, string $sheet)` | Create an ad-hoc sheet instance |
| `getDefaultConnection()` | Get the default connection name |
| `purge(?string $name)` | Remove a resolved connection |
| `reconnect(?string $name)` | Purge and re-resolve a connection |

### `Sheet`

| Method | Returns | Description |
|---|---|---|
| `spreadsheet(string $id)` | `static` | Override the spreadsheet ID |
| `sheet(string $name)` | `static` | Switch to a different tab |
| `range(string $range)` | `static` | Set A1 range for the next operation |
| `get()` | `Collection` | Read rows (headers mapped) |
| `all()` | `Collection` | Read all rows from the sheet |
| `first()` | `?array` | First data row |
| `last()` | `?array` | Last data row |
| `headers()` | `array` | Column headers (row 1) |
| `find(col, val)` | `Collection` | Filter rows by column value |
| `where(col, op, val)` | `Collection` | Filter with comparison operators |
| `chunk(size, cb)` | `void` | Process rows in chunks |
| `append(array $rows)` | `int` | Append rows (returns row count) |
| `update(array $rows)` | `int` | Update range (returns row count) |
| `batchUpdate(array $data)` | `int` | Update multiple ranges |
| `clear()` | `bool` | Clear values in range |
| `appendAssoc(array)` | `int` | Append associative rows mapped to sheet headers |
| `updateAssoc(array)` | `int` | Update associative rows mapped to sheet headers |
| `upsert(key, rows)` | `int` | Update rows by key column and append missing rows |
| `validate(rules)` | `Collection` | Validate rows with Laravel validation rules |
| `requireHeaders(array)` | `static` | Ensure required headers exist |
| `lazy(size)` | `LazyCollection` | Iterate rows lazily from a collection-backed read |
| `createSheet(string)` | `static` | Add a new tab |
| `deleteSheet(string)` | `bool` | Remove a tab |
| `duplicateSheet(src, new)` | `static` | Copy a tab |
| `listSheets()` | `array` | List all tab names |
| `sheetExists(string)` | `bool` | Check if a tab exists |
| `namedRange(string)` | `static` | Set a named range for the next operation |
| `listNamedRanges()` | `array` | List named ranges |
| `formula(string)` | `string` | Create a formula cell value |
| `boldHeader()` | `static` | Bold the first row |
| `freezeRows(int)` | `static` | Freeze leading rows |
| `autoResizeColumns(start, end)` | `static` | Auto-resize columns |
| `formatRange(range, format)` | `static` | Apply cell formatting |
| `withHeaders()` | `static` | Map first row as keys (default) |
| `withoutHeaders()` | `static` | Return raw arrays |
| `enableCache(?int $ttl)` | `static` | Enable caching |
| `disableCache()` | `static` | Disable caching |

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `GOOGLE_SHEETS_CREDENTIALS_PATH` | `storage/app/google/service-account.json` | Path to credentials |
| `GOOGLE_SHEETS_DEFAULT_CONNECTION` | `default` | Default connection name |
| `GOOGLE_SHEETS_SPREADSHEET_ID` | — | Spreadsheet ID for default connection |
| `GOOGLE_SHEETS_APPLICATION_NAME` | `Laravel Google Sheets` | App name for API requests |
| `GOOGLE_SHEETS_CACHE_ENABLED` | `false` | Enable response caching |
| `GOOGLE_SHEETS_CACHE_STORE` | `null` (default driver) | Cache store to use |
| `GOOGLE_SHEETS_CACHE_TTL` | `300` | Cache lifetime in seconds |
| `GOOGLE_SHEETS_VALUE_RENDER` | `FORMATTED_VALUE` | Value render option |
| `GOOGLE_SHEETS_VALUE_INPUT` | `USER_ENTERED` | Value input option |

## License

MIT
