<?php

namespace Olamilekan\GoogleSheets\Tests;

use Illuminate\Support\Facades\Http;
use Olamilekan\GoogleSheets\Sync\SyncAudit;
use Olamilekan\GoogleSheets\Testing\FakeSheet;
use Orchestra\Testbench\TestCase;

class SyncMethodsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SyncAudit::clear();
    }

    public function test_it_syncs_rows_to_a_sheet_with_conflict_reporting(): void
    {
        $sheet = new FakeSheet([
            ['email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'user'],
        ]);

        $report = $sheet->syncRows([
            ['email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'owner'],
            ['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'user'],
        ], 'email', ['conflict' => 'sheet_wins']);

        $this->assertSame(1, $report->created());
        $this->assertSame(0, $report->updated());
        $this->assertSame(1, $report->skipped());
        $this->assertSame(1, $report->conflictCount());
        $this->assertCount(1, $sheet->appends);
        $this->assertCount(0, $sheet->updates);
    }

    public function test_it_imports_and_exports_csv(): void
    {
        $importPath = sys_get_temp_dir() . '/google-sheets-users-import.csv';
        $exportPath = sys_get_temp_dir() . '/google-sheets-users-export.csv';

        file_put_contents($importPath, "email,name,role\nalice@example.com,Alice,user\nbob@example.com,Bob,owner\n");

        $sheet = new FakeSheet([
            ['email' => 'alice@example.com', 'name' => 'Alice Old', 'role' => 'user'],
        ]);

        $import = $sheet->importCsv($importPath, 'email');
        $export = $sheet->exportCsv($exportPath);

        $this->assertSame(1, $import->created());
        $this->assertSame(1, $import->updated());
        $this->assertSame(2, $export->created());
        $this->assertStringContainsString('alice@example.com', file_get_contents($exportPath));
        $this->assertStringContainsString('bob@example.com', file_get_contents($exportPath));
    }

    public function test_it_syncs_from_and_to_an_api(): void
    {
        Http::fake([
            'https://example.test/users' => Http::response([
                'data' => [
                    ['email' => 'alice@example.com', 'name' => 'Alice'],
                ],
            ]),
            'https://example.test/push' => Http::response(['ok' => true]),
        ]);

        $sheet = new FakeSheet([
            ['email' => 'existing@example.com', 'name' => 'Existing'],
        ]);

        $fromApi = $sheet->syncFromApi('https://example.test/users', 'email', ['data_key' => 'data']);
        $toApi = $sheet->syncToApi('https://example.test/push');

        $this->assertSame(1, $fromApi->created());
        $this->assertSame(2, $toApi->created());

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/push'
            && count($request['rows']) === 2);
    }

    public function test_it_reports_failed_csv_and_api_sources(): void
    {
        Http::fake([
            'https://example.test/failing' => Http::response(['message' => 'Nope'], 500),
        ]);

        $sheet = new FakeSheet([
            ['email' => 'existing@example.com', 'name' => 'Existing'],
        ]);

        $csv = $sheet->importCsv(sys_get_temp_dir() . '/missing-google-sheets-users.csv', 'email');
        $api = $sheet->syncFromApi('https://example.test/failing', 'email');

        $this->assertSame(1, $csv->failed());
        $this->assertSame(1, $api->failed());
    }

    public function test_it_records_audit_logs_and_sends_callback_notifications(): void
    {
        $notified = null;
        $sheet = new FakeSheet([
            ['email' => 'alice@example.com', 'name' => 'Alice'],
        ]);

        $report = $sheet->syncRows([
            ['email' => 'bob@example.com', 'name' => 'Bob'],
        ], 'email', [
            'notify' => [
                'callback' => function ($report) use (&$notified) {
                    $notified = $report->toArray();
                },
            ],
        ]);

        $this->assertSame(1, $report->created());
        $this->assertSame('rows_to_sheet', $notified['operation']);
        $this->assertSame(1, $sheet->syncAuditLog()->count());
    }
}
