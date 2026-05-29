<?php

namespace Olamilekan\GoogleSheets\Console;

use Illuminate\Console\Command;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\GoogleSheetsManager;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class SyncSheetCommand extends Command
{
    protected $signature = 'google-sheets:sync
        {class : Import or export class name}
        {connection? : Configured sheet connection name}
        {--dry-run : Preview an import without writing data}';

    protected $description = 'Run a Google Sheets import or export class';

    public function handle(GoogleSheetsManager $sheets): int
    {
        $class = $this->argument('class');

        if (! class_exists($class)) {
            $this->error("Class [{$class}] was not found.");

            return self::FAILURE;
        }

        $instance = app($class);

        if ($instance instanceof SheetImport) {
            if ($this->option('dry-run')) {
                $preview = $instance->dryRun($sheets->connection($this->argument('connection')));
                $counts = $preview->counts();

                $this->info('Google Sheets import dry-run completed. No rows were written.');
                $this->table(
                    ['New', 'Changed', 'Deleted', 'Invalid', 'Conflicts'],
                    [[
                        $counts['new'],
                        $counts['changed'],
                        $counts['deleted'],
                        $counts['invalid'],
                        $counts['conflicts'],
                    ]]
                );

                return self::SUCCESS;
            }

            $sheets->import($instance, $this->argument('connection'));
            $this->info('Google Sheets import completed.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->error('The --dry-run option is only supported for SheetImport classes.');

            return self::FAILURE;
        }

        if ($instance instanceof SheetExport) {
            $count = $sheets->export($instance, $this->argument('connection'));
            $this->info("Google Sheets export completed. {$count} rows written.");

            return self::SUCCESS;
        }

        $this->error("Class [{$class}] must extend SheetImport or SheetExport.");

        return self::FAILURE;
    }
}
