<?php

namespace Olamilekan\GoogleSheets\Console;

use Illuminate\Console\Command;
use Olamilekan\GoogleSheets\GoogleSheetsManager;

class ClearSheetCommand extends Command
{
    protected $signature = 'google-sheets:clear {connection? : Configured sheet connection name} {--sheet= : Sheet tab name} {--range= : A1 range to clear}';

    protected $description = 'Clear values from a Google Sheet tab or range';

    public function handle(GoogleSheetsManager $sheets): int
    {
        $sheet = $sheets->connection($this->argument('connection'));

        if ($tab = $this->option('sheet')) {
            $sheet->sheet($tab);
        }

        if ($range = $this->option('range')) {
            $sheet->range($range);
        }

        $sheet->clear();
        $this->info('Google Sheet values cleared.');

        return self::SUCCESS;
    }
}
