<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Component;
use Illuminate\Support\Facades\Cache;

/**
 * Component Observer for handling model events.
 */
final class ComponentObserver
{
    /**
     * Handle the Component "creating" event.
     */
    public function creating(Component $component): void
    {
        // Slug generation is handled by the Sluggable trait
    }

    /**
     * Handle the Component "created" event.
     */
    public function created(Component $component): void
    {
        $this->clearCaches($component);
    }

    /**
     * Handle the Component "updating" event.
     */
    public function updating(Component $component): void
    {
        // Additional validation or processing can be added here
    }

    /**
     * Handle the Component "updated" event.
     */
    public function updated(Component $component): void
    {
        $this->clearCaches($component);
    }

    /**
     * Handle the Component "deleted" event.
     */
    public function deleted(Component $component): void
    {
        $this->clearCaches($component);
    }

    /**
     * Handle the Component "restored" event.
     */
    public function restored(Component $component): void
    {
        $this->clearCaches($component);
    }

    /**
     * Handle the Component "force deleted" event.
     */
    public function forceDeleted(Component $component): void
    {
        $this->clearCaches($component);
    }

    /**
     * Clear related caches when component changes.
     */
    private function clearCaches(Component $component): void
    {
        // Clear component-specific caches
        Cache::forget("component:{$component->uuid}");
        Cache::forget("component:slug:{$component->slug}");
        
        // Clear space-level caches
        Cache::forget("space:{$component->space_id}:components");
        Cache::forget("space:{$component->space_id}:component_library");
        
        // Clear schema validation caches
        Cache::forget("component:{$component->uuid}:schema");
        
        // Clear any story caches that might use this component
        Cache::tags(["component:{$component->uuid}"])->flush();
    }
}