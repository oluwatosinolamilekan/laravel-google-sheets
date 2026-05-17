<?php

namespace Olamilekan\GoogleSheets\Console;

use Illuminate\Console\Command;
use Olamilekan\GoogleSheets\GoogleSheetsManager;

class ListSheetsCommand extends Command
{
    protected $signature = 'google-sheets:list {connection? : Configured sheet connection name}';

    protected $description = 'List tabs for a configured Google Sheets connection';

    public function handle(GoogleSheetsManager $sheets): int
    {
        $tabs = $sheets->connection($this->argument('connection'))->listSheets();

        foreach ($tabs as $tab) {
            $this->line($tab);
        }

        return self::SUCCESS;
    }
}
