<?php

namespace Olamilekan\GoogleSheets\Console;

use Illuminate\Console\Command;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\GoogleSheetsManager;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class SyncSheetCommand extends Command
{
    protected $signature = 'google-sheets:sync {class : Import or export class name} {connection? : Configured sheet connection name}';

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
            $sheets->import($instance, $this->argument('connection'));
            $this->info('Google Sheets import completed.');

            return self::SUCCESS;
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
