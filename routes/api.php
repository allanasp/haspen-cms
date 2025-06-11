<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Cdn\StoryController as CdnStoryController;
use App\Http\Controllers\Api\V1\Cdn\DatasourceController as CdnDatasourceController;
use App\Http\Controllers\Api\V1\Cdn\AssetController as CdnAssetController;
use App\Http\Controllers\Api\V1\Management\StoryController;
use App\Http\Controllers\Api\V1\Management\ComponentController;
use App\Http\Controllers\Api\V1\Management\AssetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API v1 routes
Route::prefix('v1')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Authentication API (/api/v1/auth/)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->middleware([
        'api.rate_limit:auth',
        'api.logging'
    ])->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
        
        // Authenticated routes
        Route::middleware('api.auth')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Content Delivery API (/api/v1/cdn/)
    |--------------------------------------------------------------------------
    | Public content access with subdomain/space resolution
    */
    Route::prefix('cdn')->middleware([
        'tenant.isolation',
        'api.rate_limit:cdn',
        'api.logging'
    ])->group(function () {
        
        // Stories
        Route::get('stories', [CdnStoryController::class, 'index'])->name('cdn.stories.index');
        Route::get('stories/{slug}', [CdnStoryController::class, 'show'])->name('cdn.stories.show');
        
        // Datasources
        Route::get('datasources', [CdnDatasourceController::class, 'index'])->name('cdn.datasources.index');
        Route::get('datasources/{slug}', [CdnDatasourceController::class, 'show'])->name('cdn.datasources.show');
        
        // Assets
        Route::get('assets/{filename}', [CdnAssetController::class, 'show'])->name('cdn.assets.show');
        Route::get('assets/{filename}/info', [CdnAssetController::class, 'info'])->name('cdn.assets.info');
    });

    /*
    |--------------------------------------------------------------------------
    | Management API (/api/v1/spaces/{space_id}/)
    |--------------------------------------------------------------------------
    | Admin operations requiring authentication and space access
    */
    Route::prefix('spaces/{space_id}')->middleware([
        'tenant.isolation',
        'api.auth:space',
        'api.rate_limit:management',
        'api.logging'
    ])->group(function () {
        
        // Stories Management
        Route::apiResource('stories', StoryController::class, [
            'parameters' => ['stories' => 'storyId']
        ])->names([
            'index' => 'management.stories.index',
            'store' => 'management.stories.store',
            'show' => 'management.stories.show',
            'update' => 'management.stories.update',
            'destroy' => 'management.stories.destroy'
        ]);
        
        // Components Management
        Route::apiResource('components', ComponentController::class, [
            'parameters' => ['components' => 'componentId']
        ])->names([
            'index' => 'management.components.index',
            'store' => 'management.components.store',
            'show' => 'management.components.show',
            'update' => 'management.components.update',
            'destroy' => 'management.components.destroy'
        ]);
        
        // Assets Management
        Route::apiResource('assets', AssetController::class, [
            'parameters' => ['assets' => 'assetId']
        ])->names([
            'index' => 'management.assets.index',
            'store' => 'management.assets.store',
            'show' => 'management.assets.show',
            'update' => 'management.assets.update',
            'destroy' => 'management.assets.destroy'
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0')
    ]);
})->name('api.health');
