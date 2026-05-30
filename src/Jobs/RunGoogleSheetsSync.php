<?php

namespace Olamilekan\GoogleSheets\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Olamilekan\GoogleSheets\GoogleSheetsManager;

class RunGoogleSheetsSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $connection,
        public string $method,
        public array $arguments = [],
    ) {
    }

    public function handle(GoogleSheetsManager $sheets): mixed
    {
        return $sheets->connection($this->connection)->{$this->method}(...$this->arguments);
    }
}
