<?php

namespace Olamilekan\GoogleSheets\Tests;

use Illuminate\Support\Collection;
use Olamilekan\GoogleSheets\Console\SyncSheetCommand;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\GoogleSheetsManager;
use Olamilekan\GoogleSheets\GoogleSheetsServiceProvider;
use Olamilekan\GoogleSheets\Imports\SheetImport;
use Olamilekan\GoogleSheets\Testing\FakeSheet;
use Orchestra\Testbench\TestCase;

class SyncSheetCommandDryRunTest extends TestCase
{
    protected DryRunCommandGoogleSheetsManager $sheets;

    protected function getPackageProviders($app): array
    {
        return [GoogleSheetsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sheets = new DryRunCommandGoogleSheetsManager([
            'users' => [
                ['email' => 'new@example.com', 'name' => 'New User', 'role' => 'user'],
                ['email' => 'changed@example.com', 'name' => 'Changed User', 'role' => 'owner'],
                ['email' => 'invalid-email', 'name' => 'Invalid User', 'role' => 'user'],
            ],
        ]);

        $this->app->instance(GoogleSheetsManager::class, $this->sheets);
    }

    public function test_it_previews_import_diff_without_running_the_import(): void
    {
        $this->artisan(SyncSheetCommand::class, [
            'class' => DryRunUsersImport::class,
            'connection' => 'users',
            '--dry-run' => true,
        ])
            ->expectsOutput('Google Sheets import dry-run completed. No rows were written.')
            ->expectsTable(
                ['New', 'Changed', 'Deleted', 'Invalid', 'Conflicts'],
                [[1, 1, 1, 1, 0]]
            )
            ->assertSuccessful();

        $this->assertFalse($this->sheets->imported);
        $this->assertCount(0, $this->sheets->connection('users')->appends);
        $this->assertCount(0, $this->sheets->connection('users')->updates);
    }

    public function test_it_rejects_dry_run_for_exports(): void
    {
        $this->artisan(SyncSheetCommand::class, [
            'class' => DryRunReportsExport::class,
            'connection' => 'users',
            '--dry-run' => true,
        ])
            ->expectsOutput('The --dry-run option is only supported for SheetImport classes.')
            ->assertFailed();
    }
}

class DryRunCommandGoogleSheetsManager extends GoogleSheetsManager
{
    public bool $imported = false;

    /** @var array<string, FakeSheet> */
    protected array $fakeConnections = [];

    public function __construct(array $sheets = [])
    {
        parent::__construct([]);

        foreach ($sheets as $name => $rows) {
            $this->fakeConnections[$name] = new FakeSheet($rows);
        }
    }

    public function connection(?string $name = null): FakeSheet
    {
        $name = $name ?? 'default';

        return $this->fakeConnections[$name] ??= new FakeSheet();
    }

    public function import(SheetImport $import, ?string $connection = null): mixed
    {
        $this->imported = true;

        return parent::import($import, $connection);
    }
}

class DryRunUsersImport extends SheetImport
{
    public function target(): Collection
    {
        return collect([
            ['email' => 'changed@example.com', 'name' => 'Changed User', 'role' => 'user'],
            ['email' => 'deleted@example.com', 'name' => 'Deleted User', 'role' => 'user'],
        ]);
    }

    public function key(): string
    {
        return 'email';
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    public function model(array $row): mixed
    {
        throw new \RuntimeException('Dry-run should not import rows.');
    }
}

class DryRunReportsExport extends SheetExport
{
    public function array(): array
    {
        return [['Date', 'Total']];
    }
}
