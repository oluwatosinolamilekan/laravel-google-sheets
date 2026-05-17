<?php

namespace Olamilekan\GoogleSheets;

use Google\Service\Sheets as GoogleSheetsService;
use Olamilekan\GoogleSheets\Contracts\ManagerInterface;
use Olamilekan\GoogleSheets\Contracts\SheetInterface;
use Olamilekan\GoogleSheets\Exports\SheetExport;
use Olamilekan\GoogleSheets\Exceptions\InvalidConnectionException;
use Olamilekan\GoogleSheets\Imports\SheetImport;

class GoogleSheetsManager implements ManagerInterface
{
    protected array $config;

    protected ?GoogleSheetsService $service = null;

    /** @var array<string, Sheet> */
    protected array $connections = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(?string $name = null): SheetInterface
    {
        $name = $name ?? $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $this->connections[$name] = $this->resolve($name);

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'default';
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Dynamically create a connection on-the-fly without prior configuration.
     */
    public function make(string $spreadsheetId, string $sheetName = 'Sheet1'): Sheet
    {
        $service = $this->getService();

        $sheet = new Sheet($service, $spreadsheetId, $sheetName, $this->getSheetOptions());

        if (! empty($this->config['cache'])) {
            $sheet->setCacheConfig($this->config['cache']);
        }

        return $sheet;
    }

    /**
     * Purge a resolved connection so it will be re-created next time.
     */
    public function purge(?string $name = null): static
    {
        $name = $name ?? $this->getDefaultConnection();
        unset($this->connections[$name]);

        return $this;
    }

    /**
     * Reconnect to a given connection.
     */
    public function reconnect(?string $name = null): SheetInterface
    {
        $this->purge($name);

        return $this->connection($name);
    }

    public function import(SheetImport $import, ?string $connection = null): mixed
    {
        return $this->connection($connection)->import($import);
    }

    public function export(SheetExport $export, ?string $connection = null): int
    {
        return $this->connection($connection)->export($export);
    }

    /**
     * Forward calls to the default connection for convenience.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }

    // -------------------------------------------------------------------------
    //  Internal
    // -------------------------------------------------------------------------

    protected function resolve(string $name): Sheet
    {
        $config = $this->getConnectionConfig($name);

        $spreadsheetId = $config['spreadsheet_id'] ?? null;

        if (! $spreadsheetId) {
            throw InvalidConnectionException::missingSpreadsheetId($name);
        }

        $sheetName = $config['sheet'] ?? 'Sheet1';
        $service = $this->getService();

        $sheet = new Sheet($service, $spreadsheetId, $sheetName, $this->getSheetOptions());

        if (! empty($this->config['cache'])) {
            $sheet->setCacheConfig($this->config['cache']);
        }

        return $sheet;
    }

    protected function getConnectionConfig(string $name): array
    {
        $sheets = $this->config['sheets'] ?? [];

        if (! isset($sheets[$name])) {
            throw InvalidConnectionException::connectionNotFound($name);
        }

        return $sheets[$name];
    }

    protected function getService(): GoogleSheetsService
    {
        if ($this->service === null) {
            $client = GoogleClientFactory::make($this->config);
            $this->service = new GoogleSheetsService($client);
        }

        return $this->service;
    }

    protected function getSheetOptions(): array
    {
        return [
            'value_render_option' => $this->config['value_render_option'] ?? 'FORMATTED_VALUE',
            'value_input_option' => $this->config['value_input_option'] ?? 'USER_ENTERED',
            'date_time_render_option' => $this->config['date_time_render_option'] ?? 'FORMATTED_STRING',
        ];
    }
}
