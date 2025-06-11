<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Trait Cacheable.
 *
 * Provides automatic model caching functionality with cache invalidation
 * on model updates, creation, and deletion.
 */
trait Cacheable
{
    /**
     * Boot the Cacheable trait for a model.
     */
    public static function bootCacheable(): void
    {
        static::saved(function (Model $model): void {
            if (method_exists($model, 'clearCache')) {
                $model->clearCache();
            }
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'clearCache')) {
                $model->clearCache();
            }
        });
    }

    /**
     * Get a cached version of the model by key.
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl Time to live in seconds
     *
     * @return mixed
     */
    public function getCached(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cacheKey = $this->getCacheKey($key);
        $ttl = $ttl ?? $this->getCacheTtl();

        return Cache::remember($cacheKey, $ttl, \Closure::fromCallable($callback));
    }

    /**
     * Cache a value with automatic model-based key generation.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     *
     * @return bool
     */
    public function putCache(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cacheKey = $this->getCacheKey($key);
        $ttl = $ttl ?? $this->getCacheTtl();

        return Cache::put($cacheKey, $value, $ttl);
    }

    /**
     * Remove a specific cache key for this model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function forgetCache(string $key): bool
    {
        $cacheKey = $this->getCacheKey($key);

        return Cache::forget($cacheKey);
    }

    /**
     * Clear all cached data for this model instance.
     */
    public function clearCache(): void
    {
        $pattern = $this->getCacheKeyPrefix() . '*';

        // Get all cache keys matching the pattern
        $keys = $this->getCacheKeys($pattern);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Clear any additional model-specific cache
        $this->clearModelSpecificCache();
    }

    /**
     * Generate a cache key for this model.
     *
     * @param string $suffix
     *
     * @return string
     */
    protected function getCacheKey(string $suffix): string
    {
        return $this->getCacheKeyPrefix() . $suffix;
    }

    /**
     * Get the cache key prefix for this model.
     *
     * @return string
     */
    protected function getCacheKeyPrefix(): string
    {
        $modelClass = class_basename($this);
        /** @var mixed $keyValue */
        $keyValue = $this->exists ? $this->getKey() : 'new';
        $identifier = (string) $keyValue;

        return strtolower($modelClass) . ':' . $identifier . ':';
    }

    /**
     * Get the default cache TTL in seconds.
     *
     * @return int
     */
    protected function getCacheTtl(): int
    {
        return property_exists($this, 'cacheTtl') ? $this->cacheTtl : 3600;
    }

    /**
     * Get cache keys matching a pattern.
     *
     * @param string $pattern
     *
     * @return array<string>
     */
    protected function getCacheKeys(string $pattern): array
    {
        // This implementation depends on the cache driver
        // Redis supports KEYS command, for other drivers we maintain a key registry
        $driver = Cache::getStore();

        if (method_exists($driver, 'keys')) {
            return $driver->keys($pattern);
        }

        // Fallback: maintain a registry of cache keys
        return $this->getCacheKeysFromRegistry($pattern);
    }

    /**
     * Get cache keys from a maintained registry.
     *
     * @param string $pattern
     *
     * @return array<string>
     */
    protected function getCacheKeysFromRegistry(string $pattern): array
    {
        $registryKey = 'cache_registry:' . class_basename($this);
        /** @var array<string> $registry */
        $registry = Cache::get($registryKey, []);

        $patternRegex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';

        return array_filter($registry, function (string $key) use ($patternRegex): bool {
            return (bool) preg_match($patternRegex, $key);
        });
    }

    /**
     * Add a cache key to the registry.
     *
     * @param string $key
     */
    protected function addToRegistry(string $key): void
    {
        $registryKey = 'cache_registry:' . class_basename($this);
        /** @var array<string> $registry */
        $registry = Cache::get($registryKey, []);

        if (! \in_array($key, $registry, true)) {
            $registry[] = $key;
            Cache::put($registryKey, $registry, 86400); // 24 hours
        }
    }

    /**
     * Clear model-specific cache.
     * Override this method in models to clear additional cache.
     */
    protected function clearModelSpecificCache(): void
    {
        // Override in models to clear additional cache
    }

    /**
     * Cache a query result.
     *
     * @param string $key
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $ttl
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function cacheQuery(string $key, $query, ?int $ttl = null)
    {
        $className = class_basename(static::class);
        $cacheKey = 'query:' . $className . ':' . $key;
        $ttl = $ttl ?? 3600;

        return Cache::remember($cacheKey, $ttl, function () use ($query) {
            return $query->get();
        });
    }

    /**
     * Clear query cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function forgetQueryCache(string $key): bool
    {
        $className = class_basename(static::class);
        $cacheKey = 'query:' . $className . ':' . $key;

        return Cache::forget($cacheKey);
    }
}
