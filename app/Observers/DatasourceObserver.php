<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Datasource;
use Illuminate\Support\Facades\Cache;

/**
 * Datasource Observer for handling model events.
 */
final class DatasourceObserver
{
    /**
     * Handle the Datasource "creating" event.
     */
    public function creating(Datasource $datasource): void
    {
        // Slug generation is handled by the Sluggable trait
    }

    /**
     * Handle the Datasource "created" event.
     */
    public function created(Datasource $datasource): void
    {
        $this->clearCaches($datasource);
    }

    /**
     * Handle the Datasource "updating" event.
     */
    public function updating(Datasource $datasource): void
    {
        // If configuration changes, clear data cache
        if ($datasource->isDirty(['config', 'auth_config', 'headers', 'mapping', 'transformations', 'filters'])) {
            $datasource->clearCache();
        }
    }

    /**
     * Handle the Datasource "updated" event.
     */
    public function updated(Datasource $datasource): void
    {
        $this->clearCaches($datasource);
    }

    /**
     * Handle the Datasource "deleted" event.
     */
    public function deleted(Datasource $datasource): void
    {
        $this->clearCaches($datasource);
    }

    /**
     * Handle the Datasource "restored" event.
     */
    public function restored(Datasource $datasource): void
    {
        $this->clearCaches($datasource);
    }

    /**
     * Handle the Datasource "force deleted" event.
     */
    public function forceDeleted(Datasource $datasource): void
    {
        $this->clearCaches($datasource);
        
        // Clean up all related entries
        $datasource->entries()->forceDelete();
    }

    /**
     * Clear related caches when datasource changes.
     */
    private function clearCaches(Datasource $datasource): void
    {
        // Clear datasource-specific caches
        Cache::forget("datasource:{$datasource->uuid}");
        Cache::forget("datasource:{$datasource->uuid}:data");
        Cache::forget("datasource:slug:{$datasource->slug}");
        
        // Clear space-level caches
        Cache::forget("space:{$datasource->space_id}:datasources");
        Cache::forget("space:{$datasource->space_id}:datasource_library");
        
        // Clear type-specific caches
        Cache::forget("space:{$datasource->space_id}:datasources:{$datasource->type}");
        
        // Clear the datasource's own cache
        $datasource->clearCache();
    }
}