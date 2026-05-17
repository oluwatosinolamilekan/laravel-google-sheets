<?php

namespace Olamilekan\GoogleSheets;

use Illuminate\Support\ServiceProvider;
use Olamilekan\GoogleSheets\Console\ClearSheetCommand;
use Olamilekan\GoogleSheets\Console\ListSheetsCommand;
use Olamilekan\GoogleSheets\Console\SyncSheetCommand;
use Olamilekan\GoogleSheets\Contracts\ManagerInterface;

class GoogleSheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/google-sheets.php', 'google-sheets');

        $this->app->singleton(GoogleSheetsManager::class, function ($app) {
            return new GoogleSheetsManager($app['config']['google-sheets']);
        });

        $this->app->alias(GoogleSheetsManager::class, 'google-sheets');
        $this->app->alias(GoogleSheetsManager::class, ManagerInterface::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/google-sheets.php' => config_path('google-sheets.php'),
            ], 'google-sheets-config');

            $this->commands([
                ClearSheetCommand::class,
                ListSheetsCommand::class,
                SyncSheetCommand::class,
            ]);
        }
    }
}
