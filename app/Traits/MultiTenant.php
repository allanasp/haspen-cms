<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Space;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait MultiTenant
 *
 * Provides multi-tenant functionality by automatically scoping queries to the current space.
 * Ensures data isolation between different tenants (spaces).
 */
trait MultiTenant
{
    /**
     * Boot the MultiTenant trait for a model.
     */
    public static function bootMultiTenant(): void
    {
        // Automatically scope queries to current space if one is set
        static::addGlobalScope('space', function (Builder $builder): void {
            $currentSpace = app('current.space');
            
            if ($currentSpace instanceof Space) {
                $builder->where('space_id', $currentSpace->id);
            }
        });

        // Automatically set space_id when creating models
        static::creating(function (Model $model): void {
            if (empty($model->space_id)) {
                $currentSpace = app('current.space');
                
                if ($currentSpace instanceof Space) {
                    $model->space_id = $currentSpace->id;
                }
            }
        });
    }

    /**
     * Get the space that owns this model.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Scope a query to a specific space.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  Space|int  $space
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSpace($query, Space|int $space)
    {
        $spaceId = $space instanceof Space ? $space->id : $space;
        
        return $query->where('space_id', $spaceId);
    }

    /**
     * Scope a query without the space constraint.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutSpaceScope($query)
    {
        return $query->withoutGlobalScope('space');
    }

    /**
     * Check if the model belongs to the given space.
     *
     * @param  Space|int  $space
     * @return bool
     */
    public function belongsToSpace(Space|int $space): bool
    {
        $spaceId = $space instanceof Space ? $space->id : $space;
        
        return $this->space_id === $spaceId;
    }

    /**
     * Check if the model belongs to the current space.
     *
     * @return bool
     */
    public function belongsToCurrentSpace(): bool
    {
        $currentSpace = app('current.space');
        
        if (!$currentSpace instanceof Space) {
            return false;
        }
        
        return $this->belongsToSpace($currentSpace);
    }
}