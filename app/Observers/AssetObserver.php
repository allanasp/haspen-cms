<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Asset Observer for handling model events.
 */
final class AssetObserver
{
    /**
     * Handle the Asset "creating" event.
     */
    public function creating(Asset $asset): void
    {
        // Additional processing can be added here
    }

    /**
     * Handle the Asset "created" event.
     */
    public function created(Asset $asset): void
    {
        $this->clearCaches($asset);
    }

    /**
     * Handle the Asset "updating" event.
     */
    public function updating(Asset $asset): void
    {
        // Track changes for cache invalidation
    }

    /**
     * Handle the Asset "updated" event.
     */
    public function updated(Asset $asset): void
    {
        $this->clearCaches($asset);
    }

    /**
     * Handle the Asset "deleted" event.
     */
    public function deleted(Asset $asset): void
    {
        $this->clearCaches($asset);
    }

    /**
     * Handle the Asset "restored" event.
     */
    public function restored(Asset $asset): void
    {
        $this->clearCaches($asset);
    }

    /**
     * Handle the Asset "force deleted" event.
     */
    public function forceDeleted(Asset $asset): void
    {
        $this->clearCaches($asset);
        
        // Clean up physical files
        if ($asset->path && Storage::exists($asset->path)) {
            Storage::delete($asset->path);
        }
        
        // Clean up thumbnails and processed versions
        if ($asset->thumbnails) {
            foreach ($asset->thumbnails as $thumbnail) {
                if (isset($thumbnail['path']) && Storage::exists($thumbnail['path'])) {
                    Storage::delete($thumbnail['path']);
                }
            }
        }
    }

    /**
     * Clear related caches when asset changes.
     */
    private function clearCaches(Asset $asset): void
    {
        // Clear asset-specific caches
        Cache::forget("asset:{$asset->uuid}");
        Cache::forget("asset:{$asset->uuid}:url");
        Cache::forget("asset:{$asset->uuid}:thumbnails");
        
        // Clear space-level caches
        Cache::forget("space:{$asset->space_id}:assets");
        Cache::forget("space:{$asset->space_id}:asset_library");
        
        // Clear CDN caches
        Cache::forget("cdn:asset:{$asset->uuid}");
        
        // Clear content type specific caches
        Cache::forget("space:{$asset->space_id}:assets:{$asset->content_type}");
        
        // Clear file hash cache (for deduplication)
        Cache::forget("asset:hash:{$asset->file_hash}");
    }
}