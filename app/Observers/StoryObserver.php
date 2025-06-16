<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * Story Observer for handling model events.
 */
final class StoryObserver
{
    /**
     * Handle the Story "creating" event.
     */
    public function creating(Story $story): void
    {
        // Slug generation is handled by the Sluggable trait
        // but we can add additional logic here if needed
    }

    /**
     * Handle the Story "created" event.
     */
    public function created(Story $story): void
    {
        $this->clearCaches($story);
    }

    /**
     * Handle the Story "updating" event.
     */
    public function updating(Story $story): void
    {
        // Check if slug-related fields have changed
        if ($story->isDirty(['name', 'slug'])) {
            // Slug will be updated by the Sluggable trait
        }
    }

    /**
     * Handle the Story "updated" event.
     */
    public function updated(Story $story): void
    {
        $this->clearCaches($story);
    }

    /**
     * Handle the Story "deleted" event.
     */
    public function deleted(Story $story): void
    {
        $this->clearCaches($story);
    }

    /**
     * Handle the Story "restored" event.
     */
    public function restored(Story $story): void
    {
        $this->clearCaches($story);
    }

    /**
     * Handle the Story "force deleted" event.
     */
    public function forceDeleted(Story $story): void
    {
        $this->clearCaches($story);
    }

    /**
     * Clear related caches when story changes.
     */
    private function clearCaches(Story $story): void
    {
        // Clear story-specific caches
        Cache::forget("story:{$story->uuid}");
        Cache::forget("story:slug:{$story->slug}");
        
        // Clear space-level caches
        Cache::forget("space:{$story->space_id}:stories");
        Cache::forget("space:{$story->space_id}:published_stories");
        
        // Clear parent story caches if this is a child story
        if ($story->parent_id) {
            Cache::forget("story:{$story->parent_id}:children");
        }
        
        // Clear content delivery caches
        Cache::forget("cdn:story:{$story->uuid}");
        Cache::forget("cdn:story:slug:{$story->slug}");
    }
}