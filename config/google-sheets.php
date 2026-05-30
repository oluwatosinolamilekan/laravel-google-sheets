<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The default spreadsheet connection to use when no connection is
    | explicitly specified. This should match one of the keys in the
    | "sheets" configuration array below.
    |
    */

    'default' => env('GOOGLE_SHEETS_DEFAULT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Google API Credentials
    |--------------------------------------------------------------------------
    |
    | Authentication method for the Google Sheets API. Supports service
    | account credentials via a JSON key file path, or inline credentials
    | passed as an array.
    |
    */

    'credentials' => [
        'type' => env('GOOGLE_SHEETS_AUTH_TYPE', 'service_account'),
        'file' => env('GOOGLE_SHEETS_CREDENTIALS_PATH', storage_path('app/google/service-account.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | The application name sent with API requests. This appears in Google's
    | API console usage reports.
    |
    */

    'application_name' => env('GOOGLE_SHEETS_APPLICATION_NAME', 'Laravel Google Sheets'),

    /*
    |--------------------------------------------------------------------------
    | API Scopes
    |--------------------------------------------------------------------------
    |
    | OAuth scopes requested when authenticating. The default scope grants
    | full read/write access. Use the readonly scope if write access is
    | not required.
    |
    */

    'scopes' => [
        \Google\Service\Sheets::SPREADSHEETS,
        // \Google\Service\Sheets::SPREADSHEETS_READONLY,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spreadsheet Connections
    |--------------------------------------------------------------------------
    |
    | Define named connections, each pointing to a different Google
    | Spreadsheet. Each connection must have a spreadsheet_id. You can
    | optionally override the default sheet (tab) name per connection.
    |
    | You can add as many connections as needed and switch between them
    | at runtime using GoogleSheets::connection('name').
    |
    */

    'sheets' => [

        'default' => [
            'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
            'sheet' => env('GOOGLE_SHEETS_DEFAULT_SHEET', 'Sheet1'),
        ],

        // 'users' => [
        //     'spreadsheet_id' => env('GOOGLE_SHEETS_USERS_SPREADSHEET_ID'),
        //     'sheet' => 'Users',
        // ],

        // 'reports' => [
        //     'spreadsheet_id' => env('GOOGLE_SHEETS_REPORTS_SPREADSHEET_ID'),
        //     'sheet' => 'Monthly',
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache configuration for API responses. Enable to reduce API calls
    | for frequently read data. Uses Laravel's cache system.
    |
    */

    'cache' => [
        'enabled' => env('GOOGLE_SHEETS_CACHE_ENABLED', false),
        'store' => env('GOOGLE_SHEETS_CACHE_STORE', null),
        'ttl' => env('GOOGLE_SHEETS_CACHE_TTL', 300),
        'prefix' => 'google_sheets_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry & Backoff
    |--------------------------------------------------------------------------
    |
    | Retry transient Google Sheets API failures such as rate limits, quota
    | throttling, and backend errors. Delays are in milliseconds and use
    | exponential backoff with jitter between attempts.
    |
    */

    'retry' => [
        'enabled' => env('GOOGLE_SHEETS_RETRY_ENABLED', true),
        'attempts' => env('GOOGLE_SHEETS_RETRY_ATTEMPTS', 3),
        'delay' => env('GOOGLE_SHEETS_RETRY_DELAY', 250),
        'max_delay' => env('GOOGLE_SHEETS_RETRY_MAX_DELAY', 5000),
        'status_codes' => [429, 500, 502, 503, 504],
        'reasons' => [
            'rateLimitExceeded',
            'userRateLimitExceeded',
            'quotaExceeded',
            'backendError',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Value Render & Input Options
    |--------------------------------------------------------------------------
    |
    | Default options for how values are rendered when reading and how
    | input is interpreted when writing.
    |
    | Render options: FORMATTED_VALUE, UNFORMATTED_VALUE, FORMULA
    | Input options:  RAW, USER_ENTERED
    |
    */

    'value_render_option' => env('GOOGLE_SHEETS_VALUE_RENDER', 'FORMATTED_VALUE'),

    'value_input_option' => env('GOOGLE_SHEETS_VALUE_INPUT', 'USER_ENTERED'),

    /*
    |--------------------------------------------------------------------------
    | Date/Time Render Option
    |--------------------------------------------------------------------------
    |
    | How dates, times, and durations are represented in the output.
    | Options: SERIAL_NUMBER, FORMATTED_STRING
    |
    */

    'date_time_render_option' => env('GOOGLE_SHEETS_DATETIME_RENDER', 'FORMATTED_STRING'),

];
