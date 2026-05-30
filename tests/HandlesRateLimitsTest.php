<?php

namespace Olamilekan\GoogleSheets\Tests;

use Google\Service\Exception as GoogleServiceException;
use Olamilekan\GoogleSheets\Concerns\HandlesRateLimits;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class HandlesRateLimitsTest extends TestCase
{
    public function test_it_retries_retryable_status_codes_with_backoff(): void
    {
        $handler = $this->handler(['attempts' => 3, 'delay' => 100, 'max_delay' => 500]);
        $attempts = 0;

        $result = $handler->run(function () use (&$attempts) {
            $attempts++;

            if ($attempts < 3) {
                throw new RuntimeException('Too many requests', 429);
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(3, $attempts);
        $this->assertSame([
            ['attempt' => 1, 'delay' => 100, 'maxDelay' => 500],
            ['attempt' => 2, 'delay' => 100, 'maxDelay' => 500],
        ], $handler->sleeps);
    }

    public function test_it_retries_google_service_rate_limit_reasons(): void
    {
        $handler = $this->handler(['attempts' => 2, 'delay' => 0]);
        $attempts = 0;

        $result = $handler->run(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new GoogleServiceException('Quota exceeded', 403, null, [
                    ['reason' => 'userRateLimitExceeded'],
                ]);
            }

            return 'retried';
        });

        $this->assertSame('retried', $result);
        $this->assertSame(2, $attempts);
    }

    public function test_it_does_not_retry_when_retries_are_disabled(): void
    {
        $handler = $this->handler(['enabled' => false, 'attempts' => 3]);
        $attempts = 0;

        $this->expectException(RuntimeException::class);

        try {
            $handler->run(function () use (&$attempts) {
                $attempts++;

                throw new RuntimeException('Too many requests', 429);
            });
        } finally {
            $this->assertSame(1, $attempts);
            $this->assertSame([], $handler->sleeps);
        }
    }

    private function handler(array $config): object
    {
        return new class ($config) {
            use HandlesRateLimits;

            public array $sleeps = [];

            public function __construct(array $config)
            {
                $this->setRetryConfig($config);
            }

            public function run(callable $callback): mixed
            {
                return $this->withRateLimitRetries($callback);
            }

            protected function sleepBeforeRetry(int $attempt, int $delay, int $maxDelay): void
            {
                $this->sleeps[] = compact('attempt', 'delay', 'maxDelay');
            }
        };
    }
}
