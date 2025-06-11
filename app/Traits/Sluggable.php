<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait Sluggable.
 *
 * Automatically generates URL-friendly slugs from a source field.
 * Ensures uniqueness within the model scope and handles updates.
 */
trait Sluggable
{
    /**
     * Boot the Sluggable trait for a model.
     */
    public static function bootSluggable(): void
    {
        static::creating(function (Model $model): void {
            if (method_exists($model, 'generateSlugIfEmpty')) {
                $model->generateSlugIfEmpty();
            }
        });

        static::updating(function (Model $model): void {
            if (method_exists($model, 'updateSlugIfNeeded')) {
                $model->updateSlugIfNeeded();
            }
        });
    }

    /**
     * Generate a slug if the current slug is empty.
     */
    public function generateSlugIfEmpty(): void
    {
        if ($this->slug === null || $this->slug === '') {
            $this->slug = $this->generateUniqueSlug();
        }
    }

    /**
     * Update slug if the source field has changed and slug should be auto-updated.
     */
    public function updateSlugIfNeeded(): void
    {
        $sourceField = $this->getSlugSourceField();

        if ($this->isDirty($sourceField) && $this->shouldAutoUpdateSlug()) {
            $this->slug = $this->generateUniqueSlug();
        }
    }

    /**
     * Generate a unique slug based on the source field.
     *
     * @param string|null $value
     *
     * @return string
     */
    public function generateUniqueSlug(?string $value = null): string
    {
        $value = $value !== null && $value !== '' ? $value : $this->getSlugSourceValue();
        $baseSlug = Str::slug($value);

        if ($baseSlug === null || $baseSlug === '') {
            $baseSlug = 'item';
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug already exists.
     *
     * @param string $slug
     *
     * @return bool
     */
    protected function slugExists(string $slug): bool
    {
        $query = static::where('slug', $slug);

        // Exclude current model when updating
        if ($this->exists) {
            $query->where($this->getKeyName(), '!=', $this->getKey());
        }

        // Apply tenant scoping if model uses multi-tenancy
        if ($this->getAttribute('space_id')) {
            $query->where('space_id', $this->getAttribute('space_id'));
        }

        return $query->exists();
    }

    /**
     * Get the field name that should be used as the source for the slug.
     *
     * @return string
     */
    protected function getSlugSourceField(): string
    {
        return isset($this->slugSourceField) ? $this->slugSourceField : 'name';
    }

    /**
     * Get the value from the source field for slug generation.
     *
     * @return string
     */
    protected function getSlugSourceValue(): string
    {
        $field = $this->getSlugSourceField();

        return (string) $this->getAttribute($field);
    }

    /**
     * Determine if the slug should be automatically updated when the source field changes.
     *
     * @return bool
     */
    protected function shouldAutoUpdateSlug(): bool
    {
        return isset($this->autoUpdateSlug) ? $this->autoUpdateSlug : false;
    }

    /**
     * Scope a query to find by slug.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $slug
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Find a model by slug.
     *
     * @param string $slug
     *
     * @return static|null
     */
    public static function findBySlug(string $slug): ?static
    {
        /** @var static|null */
        return static::where('slug', $slug)->first();
    }

    /**
     * Find a model by slug or fail.
     *
     * @param string $slug
     *
     * @return static
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findBySlugOrFail(string $slug): static
    {
        /** @var static */
        return static::where('slug', $slug)->firstOrFail();
    }

    /**
     * Set the slug manually.
     *
     * @param string $slug
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $this->generateUniqueSlug($slug);
    }
}
