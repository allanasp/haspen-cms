<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Asset;
use App\Models\Component;
use App\Models\Datasource;
use App\Models\Story;
use App\Observers\AssetObserver;
use App\Observers\ComponentObserver;
use App\Observers\DatasourceObserver;
use App\Observers\StoryObserver;
use Illuminate\Support\ServiceProvider;

/**
 * @psalm-suppress UnusedClass
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Story::observe(StoryObserver::class);
        Component::observe(ComponentObserver::class);
        Asset::observe(AssetObserver::class);
        Datasource::observe(DatasourceObserver::class);
    }
}
