<?php

namespace Olamilekan\GoogleSheets\Sync;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use JsonSerializable;

class SyncReport implements Arrayable, JsonSerializable
{
    protected array $counts = [
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'conflicts' => 0,
        'failed' => 0,
    ];

    protected array $errors = [];

    protected array $conflicts = [];

    protected Carbon $startedAt;

    protected ?Carbon $finishedAt = null;

    public function __construct(
        protected string $operation,
        protected string $connection = 'default',
        protected array $metadata = [],
    ) {
        $this->startedAt = Carbon::now();
    }

    public static function for(string $operation, ?string $connection = null, array $metadata = []): static
    {
        return new static($operation, $connection ?? 'default', $metadata);
    }

    public function increment(string $key, int $amount = 1): static
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $amount;

        return $this;
    }

    public function addError(string $message, array $context = []): static
    {
        $this->errors[] = ['message' => $message, 'context' => $context];
        $this->increment('failed');

        return $this;
    }

    public function addConflict(mixed $key, array $incoming = [], array $existing = [], string $reason = 'Conflict detected.'): static
    {
        $this->conflicts[] = compact('key', 'incoming', 'existing', 'reason');
        $this->increment('conflicts');

        return $this;
    }

    public function merge(SyncReport $report): static
    {
        foreach ($report->counts() as $key => $count) {
            $this->increment($key, $count);
        }

        foreach ($report->errors() as $error) {
            $this->errors[] = $error;
        }

        foreach ($report->conflicts() as $conflict) {
            $this->conflicts[] = $conflict;
        }

        return $this;
    }

    public function finish(): static
    {
        $this->finishedAt = Carbon::now();

        return $this;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function connection(): string
    {
        return $this->connection;
    }

    public function counts(): array
    {
        return $this->counts;
    }

    public function created(): int
    {
        return $this->counts['created'];
    }

    public function updated(): int
    {
        return $this->counts['updated'];
    }

    public function deleted(): int
    {
        return $this->counts['deleted'];
    }

    public function skipped(): int
    {
        return $this->counts['skipped'];
    }

    public function failed(): int
    {
        return $this->counts['failed'];
    }

    public function conflictCount(): int
    {
        return $this->counts['conflicts'];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function conflicts(): array
    {
        return $this->conflicts;
    }

    public function successful(): bool
    {
        return $this->failed() === 0 && $this->conflictCount() === 0;
    }

    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'connection' => $this->connection,
            'status' => $this->successful() ? 'success' : 'attention_required',
            'counts' => $this->counts,
            'errors' => $this->errors,
            'conflicts' => $this->conflicts,
            'metadata' => $this->metadata,
            'started_at' => $this->startedAt->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
