<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait HasUuid.
 *
 * Provides UUID functionality for models that need public UUID identifiers.
 * UUIDs are automatically generated on model creation and exposed in API responses.
 */
trait HasUuid
{
    /**
     * Boot the HasUuid trait for a model.
     */
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key name for Laravel model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Find a model by UUID.
     *
     * @param string $uuid
     *
     * @return static|null
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find a model by UUID or fail.
     *
     * @param string $uuid
     *
     * @return static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Scope a query to find by UUID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $uuid
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
