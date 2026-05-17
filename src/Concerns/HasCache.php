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
        $cacheKey = $this->getCacheKey($key);

        $this->rememberCacheKey($cacheKey);

        return Cache::store($store)->remember($cacheKey, $ttl, $callback);
    }

    protected function forgetCache(string $key): void
    {
        if (! $this->isCacheEnabled()) {
            return;
        }

        $store = $this->cacheConfig['store'] ?? null;

        Cache::store($store)->forget($this->getCacheKey($key));
    }

    protected function forgetCachedKeys(array $keys): void
    {
        if (! $this->isCacheEnabled()) {
            return;
        }

        $store = $this->cacheConfig['store'] ?? null;

        foreach ($keys as $key) {
            Cache::store($store)->forget($key);
        }
    }

    protected function rememberCacheKey(string $cacheKey): void
    {
        if (! method_exists($this, 'getSpreadsheetId')) {
            return;
        }

        $store = $this->cacheConfig['store'] ?? null;
        $ttl = $this->cacheConfig['ttl'] ?? 300;
        $indexKey = $this->cacheIndexKey();
        $keys = Cache::store($store)->get($indexKey, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::store($store)->put($indexKey, $keys, $ttl);
        }
    }

    protected function flushRememberedCacheKeys(): void
    {
        if (! method_exists($this, 'getSpreadsheetId')) {
            return;
        }

        $store = $this->cacheConfig['store'] ?? null;
        $indexKey = $this->cacheIndexKey();
        $keys = Cache::store($store)->get($indexKey, []);

        $this->forgetCachedKeys($keys);
        Cache::store($store)->forget($indexKey);
    }

    protected function cacheIndexKey(): string
    {
        return $this->getCacheKey('index:' . $this->getSpreadsheetId());
    }
}
