<?php

namespace Olamilekan\GoogleSheets\Concerns;

use Google\Service\Exception as GoogleServiceException;
use Throwable;

trait HandlesRateLimits
{
    protected array $retryConfig = [];

    public function setRetryConfig(array $config): static
    {
        $this->retryConfig = $config;

        return $this;
    }

    public function withoutRetries(): static
    {
        $this->retryConfig['enabled'] = false;

        return $this;
    }

    public function withRetries(?int $attempts = null, ?int $delay = null): static
    {
        $this->retryConfig['enabled'] = true;

        if ($attempts !== null) {
            $this->retryConfig['attempts'] = $attempts;
        }

        if ($delay !== null) {
            $this->retryConfig['delay'] = $delay;
        }

        return $this;
    }

    protected function withRateLimitRetries(callable $callback): mixed
    {
        $attempts = max(1, (int) ($this->retryConfig['attempts'] ?? 3));
        $delay = max(0, (int) ($this->retryConfig['delay'] ?? 250));
        $maxDelay = max($delay, (int) ($this->retryConfig['max_delay'] ?? 5000));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $exception) {
                if (! $this->shouldRetryGoogleSheetsRequest($exception) || $attempt >= $attempts) {
                    throw $exception;
                }

                $this->sleepBeforeRetry($attempt, $delay, $maxDelay);
            }
        }
    }

    protected function shouldRetryGoogleSheetsRequest(Throwable $exception): bool
    {
        if (($this->retryConfig['enabled'] ?? true) !== true) {
            return false;
        }

        $retryableStatusCodes = $this->retryConfig['status_codes'] ?? [429, 500, 502, 503, 504];

        if (in_array((int) $exception->getCode(), $retryableStatusCodes, true)) {
            return true;
        }

        if (! $exception instanceof GoogleServiceException) {
            return false;
        }

        $retryableReasons = $this->retryConfig['reasons'] ?? [
            'rateLimitExceeded',
            'userRateLimitExceeded',
            'quotaExceeded',
            'backendError',
        ];

        foreach ($exception->getErrors() as $error) {
            if (in_array($error['reason'] ?? null, $retryableReasons, true)) {
                return true;
            }
        }

        return false;
    }

    protected function sleepBeforeRetry(int $attempt, int $delay, int $maxDelay): void
    {
        if ($delay === 0) {
            return;
        }

        $backoff = min($maxDelay, $delay * (2 ** ($attempt - 1)));
        $jitter = $backoff > 1 ? random_int(0, (int) floor($backoff / 2)) : 0;

        usleep(($backoff + $jitter) * 1000);
    }
}
