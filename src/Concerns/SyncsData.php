<?php

namespace Olamilekan\GoogleSheets\Concerns;

use Illuminate\Bus\PendingDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Olamilekan\GoogleSheets\Jobs\RunGoogleSheetsSync;
use Olamilekan\GoogleSheets\Sync\SyncAudit;
use Olamilekan\GoogleSheets\Sync\SyncConflictStrategy;
use Olamilekan\GoogleSheets\Sync\SyncNotifier;
use Olamilekan\GoogleSheets\Sync\SyncReport;

trait SyncsData
{
    public function syncRows(array|Collection $rows, string $keyColumn, array $options = []): SyncReport
    {
        $report = SyncReport::for($options['operation'] ?? 'rows_to_sheet', $this->getSyncConnectionName(), [
            'key' => $keyColumn,
            'source' => $options['source'] ?? 'rows',
        ]);

        $rows = $this->normalizeSyncRows($rows);
        $strategy = SyncConflictStrategy::normalize($options['conflict'] ?? SyncConflictStrategy::APP_WINS);
        $headers = $this->headers();

        if ($headers !== [] && ! in_array($keyColumn, $headers, true)) {
            return $this->completeSyncReport(
                $report->addError("Missing required Google Sheet header [{$keyColumn}]."),
                $options
            );
        }

        $existingRows = $this->all()->values();
        $updates = [];
        $appends = [];

        foreach ($rows as $row) {
            $key = $row[$keyColumn] ?? null;

            if ($key === null || $key === '') {
                $report->addError('Sync row is missing the key column.', compact('keyColumn', 'row'));
                continue;
            }

            $rowIndex = $existingRows->search(fn (array $existingRow) => (string) ($existingRow[$keyColumn] ?? '') === (string) $key);

            if ($rowIndex === false) {
                $appends[] = $row;
                $report->increment('created');
                continue;
            }

            $existingRow = $existingRows->get($rowIndex);

            if (! $this->syncRowChanged($row, $existingRow, $keyColumn)) {
                $report->increment('skipped');
                continue;
            }

            if ($strategy !== SyncConflictStrategy::APP_WINS) {
                $report->addConflict($key, $row, $existingRow, "Conflict handled with [{$strategy}] strategy.");
                $report->increment('skipped');
                continue;
            }

            $headers = $headers === [] ? array_keys($row) : $headers;
            $sheetRowNumber = $rowIndex + 2;
            $updates[$this->syncRowRange($sheetRowNumber, count($headers))] = [
                $this->syncMapRowToHeaders($row, $headers),
            ];
            $report->increment('updated');
        }

        if ($updates !== []) {
            $this->batchUpdate($updates);
        }

        if ($appends !== []) {
            if ($headers === []) {
                $headers = array_keys($appends[0]);
                $this->append(array_merge([$headers], $this->syncRowsForHeaders($appends, $headers)));
            } else {
                $this->appendAssoc($appends);
            }
        }

        return $this->completeSyncReport($report, $options);
    }

    public function syncFromModel(string $modelClass, string $keyColumn, array $options = []): SyncReport
    {
        $rows = $this->collectSyncModelRows($modelClass, $options['columns'] ?? null);

        return $this->syncRows($rows, $keyColumn, array_merge($options, [
            'operation' => 'model_to_sheet',
            'source' => $modelClass,
        ]));
    }

    public function syncToModel(string $modelClass, string $keyColumn, array $options = []): SyncReport
    {
        $report = SyncReport::for('sheet_to_model', $this->getSyncConnectionName(), [
            'key' => $keyColumn,
            'target' => $modelClass,
        ]);

        foreach ($this->all() as $row) {
            $key = $row[$keyColumn] ?? null;

            if ($key === null || $key === '') {
                $report->addError('Sheet row is missing the key column.', compact('keyColumn', 'row'));
                continue;
            }

            $model = $modelClass::query()->where($keyColumn, $key)->first();
            $values = $this->syncOnlyColumns($row, $options['columns'] ?? null);

            if ($model === null) {
                $modelClass::query()->create($values);
                $report->increment('created');
                continue;
            }

            $model->fill($values);

            if (method_exists($model, 'isDirty') && ! $model->isDirty()) {
                $report->increment('skipped');
                continue;
            }

            $model->save();
            $report->increment('updated');
        }

        return $this->completeSyncReport($report, $options);
    }

    public function importCsv(string $path, string $keyColumn, array $options = []): SyncReport
    {
        if (! is_readable($path)) {
            $report = SyncReport::for('csv_to_sheet', $this->getSyncConnectionName(), ['source' => $path]);

            return $this->completeSyncReport($report->addError("Unable to read CSV file [{$path}]."), $options);
        }

        $rows = $this->readSyncCsv($path);

        return $this->syncRows($rows, $keyColumn, array_merge($options, [
            'operation' => 'csv_to_sheet',
            'source' => $path,
        ]));
    }

    public function exportCsv(string $path, array $options = []): SyncReport
    {
        $report = SyncReport::for('sheet_to_csv', $this->getSyncConnectionName(), ['target' => $path]);
        $rows = $this->all()->values();
        $headers = $rows->isEmpty() ? $this->headers() : array_keys($rows->first());

        $handle = fopen($path, 'w');

        if ($handle === false) {
            return $this->completeSyncReport($report->addError("Unable to open CSV file [{$path}] for writing."), $options);
        }

        if ($headers !== []) {
            fputcsv($handle, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($handle, $this->syncMapRowToHeaders($row, $headers));
            $report->increment('created');
        }

        fclose($handle);

        return $this->completeSyncReport($report, $options);
    }

    public function syncFromApi(string $url, string $keyColumn, array $options = []): SyncReport
    {
        $response = Http::withHeaders($options['headers'] ?? [])->get($url, $options['query'] ?? []);

        if ($response->failed()) {
            $report = SyncReport::for('api_to_sheet', $this->getSyncConnectionName(), ['source' => $url]);

            return $this->completeSyncReport($report->addError('API sync failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]), $options);
        }

        $data = $response->json();

        if (isset($options['data_key'])) {
            $data = data_get($data, $options['data_key'], []);
        }

        return $this->syncRows($data ?? [], $keyColumn, array_merge($options, [
            'operation' => 'api_to_sheet',
            'source' => $url,
        ]));
    }

    public function syncToApi(string $url, array $options = []): SyncReport
    {
        $report = SyncReport::for('sheet_to_api', $this->getSyncConnectionName(), ['target' => $url]);
        $rows = $this->all()->values()->all();
        $payloadKey = $options['payload_key'] ?? 'rows';
        $response = Http::withHeaders($options['headers'] ?? [])->post($url, [$payloadKey => $rows]);

        if ($response->failed()) {
            $report->addError('API sync failed.', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } else {
            $report->increment('created', count($rows));
        }

        return $this->completeSyncReport($report, $options);
    }

    public function syncTwoWay(mixed $target, string $keyColumn, array $options = []): SyncReport
    {
        $report = SyncReport::for('two_way_sync', $this->getSyncConnectionName(), [
            'key' => $keyColumn,
            'target' => is_string($target) ? $target : get_debug_type($target),
        ]);

        $preview = $this->diffAgainst($target, $keyColumn)->preview();
        $strategy = SyncConflictStrategy::normalize($options['conflict'] ?? SyncConflictStrategy::FAIL);

        foreach ($preview->conflicts as $conflict) {
            $report->addConflict($conflict['key'] ?? null, $conflict['row'] ?? [], [], $conflict['reason'] ?? 'Conflict detected.');
        }

        if ($strategy === SyncConflictStrategy::FAIL && $report->conflictCount() > 0) {
            return $this->completeSyncReport($report, $options);
        }

        if (is_string($target) && class_exists($target)) {
            $modelReport = $this->syncToModel($target, $keyColumn, array_merge($options, [
                'audit' => false,
                'notify' => [],
            ]));
            $report->merge($modelReport);
        }

        if (($options['push_target_to_sheet'] ?? true) === true) {
            $sheetReport = $this->syncRows($this->collectSyncTargetRows($target), $keyColumn, array_merge($options, [
                'operation' => 'two_way_target_to_sheet',
                'audit' => false,
                'notify' => [],
            ]));
            $report->merge($sheetReport);
        }

        return $this->completeSyncReport($report, $options);
    }

    public function queueSync(string $method, array $arguments = [], ?string $queue = null, ?string $connection = null): PendingDispatch
    {
        $job = new RunGoogleSheetsSync($this->getSyncConnectionName(), $method, $arguments);

        if ($queue !== null) {
            $job->onQueue($queue);
        }

        if ($connection !== null) {
            $job->onConnection($connection);
        }

        return dispatch($job);
    }

    public function syncAuditLog(): Collection
    {
        return SyncAudit::records();
    }

    protected function completeSyncReport(SyncReport $report, array $options): SyncReport
    {
        $report->finish();

        if (($options['audit'] ?? true) === true) {
            SyncAudit::record($report);
        }

        if (! empty($options['notify'])) {
            SyncNotifier::send($report, $options['notify']);
        }

        return $report;
    }

    protected function normalizeSyncRows(array|Collection $rows): Collection
    {
        return collect($rows)->map(fn (mixed $row) => $this->normalizeSyncRow($row))->values();
    }

    protected function normalizeSyncRow(mixed $row): array
    {
        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        if (is_object($row)) {
            return get_object_vars($row);
        }

        return (array) $row;
    }

    protected function collectSyncModelRows(string $modelClass, ?array $columns = null): Collection
    {
        return $modelClass::query()
            ->get()
            ->map(fn (mixed $model) => $this->syncOnlyColumns($this->normalizeSyncRow($model), $columns))
            ->values();
    }

    protected function collectSyncTargetRows(mixed $target): Collection
    {
        if (is_string($target) && class_exists($target) && method_exists($target, 'query')) {
            return $this->collectSyncModelRows($target);
        }

        if (is_callable($target) && ! is_array($target)) {
            $target = $target();
        }

        if (is_object($target) && method_exists($target, 'get')) {
            $target = $target->get();
        }

        return $this->normalizeSyncRows($target instanceof Collection ? $target : (array) $target);
    }

    protected function syncOnlyColumns(array $row, ?array $columns): array
    {
        if ($columns === null || $columns === []) {
            return $row;
        }

        return array_intersect_key($row, array_flip($columns));
    }

    protected function readSyncCsv(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return collect();
        }

        $headers = fgetcsv($handle) ?: [];
        $rows = collect();

        while (($values = fgetcsv($handle)) !== false) {
            $values = array_pad($values, count($headers), null);
            $rows->push(array_combine($headers, array_slice($values, 0, count($headers))));
        }

        fclose($handle);

        return $rows;
    }

    protected function syncRowChanged(array $incoming, array $existing, string $keyColumn): bool
    {
        foreach ($incoming as $column => $value) {
            if ($column === $keyColumn) {
                continue;
            }

            if ((string) ($existing[$column] ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }

    protected function syncRowsForHeaders(array $rows, array $headers): array
    {
        return collect($rows)
            ->map(fn (array $row) => $this->syncMapRowToHeaders($row, $headers))
            ->all();
    }

    protected function syncMapRowToHeaders(array $row, array $headers): array
    {
        return collect($headers)
            ->map(fn (string $header) => $row[$header] ?? null)
            ->all();
    }

    protected function syncRowRange(int $rowNumber, int $columnCount): string
    {
        return 'A' . $rowNumber . ':' . $this->syncColumnName($columnCount) . $rowNumber;
    }

    protected function syncColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    protected function getSyncConnectionName(): string
    {
        return method_exists($this, 'getSheetName') ? $this->getSheetName() : 'default';
    }
}
