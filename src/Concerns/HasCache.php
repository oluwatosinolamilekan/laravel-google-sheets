<?php

namespace Olamilekan\GoogleSheets\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasCache
{
    protected array $cacheConfig = [];

    public function setCacheConfig(array $config): static
    {
        $this->cacheConfig = $config;

        return $this;
    }

    public function disableCache(): static
    {
        $this->cacheConfig['enabled'] = false;

        return $this;
    }

    public function enableCache(?int $ttl = null): static
    {
        $this->cacheConfig['enabled'] = true;

        if ($ttl !== null) {
            $this->cacheConfig['ttl'] = $ttl;
        }

        return $this;
    }

    protected function isCacheEnabled(): bool
    {
        return ($this->cacheConfig['enabled'] ?? false) === true;
    }

    protected function getCacheKey(string $suffix): string
    {
        $prefix = $this->cacheConfig['prefix'] ?? 'google_sheets_';

        return $prefix . md5($suffix);
    }

    protected function remember(string $key, callable $callback): mixed
    {
        if (! $this->isCacheEnabled()) {
            return $callback();
        }

        $store = $this->cacheConfig['store'] ?? null;
        $ttl = $this->cacheConfig['ttl'] ?? 300;

        return Cache::store($store)->remember(
            $this->getCacheKey($key),
            $ttl,
            $callback
        );
    }

    protected function forgetCache(string $key): void
    {
        if (! $this->isCacheEnabled()) {
            return;
        }

        $store = $this->cacheConfig['store'] ?? null;

        Cache::store($store)->forget($this->getCacheKey($key));
    }
}
