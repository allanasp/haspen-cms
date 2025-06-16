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
        
        // Additional Story routes
        Route::prefix('stories')->name('management.stories.')->group(function () {
            Route::get('templates', [StoryController::class, 'getTemplates'])->name('templates');
            Route::post('from-template', [StoryController::class, 'createFromTemplate'])->name('create-from-template');
            Route::post('{storyId}/create-template', [StoryController::class, 'createTemplate'])->name('create-template');
            Route::post('{storyId}/publish', [StoryController::class, 'publish'])->name('publish');
            Route::post('{storyId}/unpublish', [StoryController::class, 'unpublish'])->name('unpublish');
            Route::post('{storyId}/duplicate', [StoryController::class, 'duplicate'])->name('duplicate');
            Route::post('bulk-publish', [StoryController::class, 'bulkPublish'])->name('bulk-publish');
            Route::delete('bulk-delete', [StoryController::class, 'bulkDelete'])->name('bulk-delete');
            Route::get('{storyId}/versions', [StoryController::class, 'versions'])->name('versions');
            
            // Content locking routes
            Route::post('{storyId}/lock', [StoryController::class, 'lock'])->name('lock');
            Route::delete('{storyId}/lock', [StoryController::class, 'unlock'])->name('unlock');
            Route::put('{storyId}/lock', [StoryController::class, 'extendLock'])->name('extend-lock');
            Route::get('{storyId}/lock', [StoryController::class, 'getLockStatus'])->name('lock-status');
            
            // Search routes
            Route::get('search/suggestions', [StoryController::class, 'getSearchSuggestions'])->name('search-suggestions');
            Route::get('search/stats', [StoryController::class, 'getSearchStats'])->name('search-stats');
            
            // Translation routes
            Route::post('{storyId}/translations', [StoryController::class, 'createTranslation'])->name('create-translation');
            Route::get('{storyId}/translations', [StoryController::class, 'getTranslations'])->name('translations');
            Route::get('{storyId}/translation-status', [StoryController::class, 'getTranslationStatus'])->name('translation-status');
            Route::post('{storyId}/sync-translation', [StoryController::class, 'syncTranslation'])->name('sync-translation');
            Route::get('{storyId}/untranslated-fields', [StoryController::class, 'getUntranslatedFields'])->name('untranslated-fields');
        });
        
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

        // Datasources Management
        Route::apiResource('datasources', \App\Http\Controllers\Api\V1\Management\DatasourceController::class, [
            'parameters' => ['datasources' => 'datasourceId']
        ])->names([
            'index' => 'management.datasources.index',
            'store' => 'management.datasources.store',
            'show' => 'management.datasources.show',
            'update' => 'management.datasources.update',
            'destroy' => 'management.datasources.destroy'
        ]);

        // Additional Datasource routes
        Route::prefix('datasources')->name('management.datasources.')->group(function () {
            Route::post('{datasourceId}/sync', [\App\Http\Controllers\Api\V1\Management\DatasourceController::class, 'sync'])->name('sync');
            Route::post('{datasourceId}/test', [\App\Http\Controllers\Api\V1\Management\DatasourceController::class, 'test'])->name('test');
            Route::get('{datasourceId}/entries', [\App\Http\Controllers\Api\V1\Management\DatasourceController::class, 'entries'])->name('entries');
            Route::post('{datasourceId}/health-check', [\App\Http\Controllers\Api\V1\Management\DatasourceController::class, 'healthCheck'])->name('health-check');
        });

        // Users Management
        Route::apiResource('users', \App\Http\Controllers\Api\V1\Management\UserController::class, [
            'parameters' => ['users' => 'userId']
        ])->names([
            'index' => 'management.users.index',
            'store' => 'management.users.store',
            'show' => 'management.users.show',
            'update' => 'management.users.update',
            'destroy' => 'management.users.destroy'
        ]);

        // Additional User routes
        Route::prefix('users')->name('management.users.')->group(function () {
            Route::post('{userId}/assign-role', [\App\Http\Controllers\Api\V1\Management\UserController::class, 'assignRole'])->name('assign-role');
            Route::delete('{userId}/remove-role', [\App\Http\Controllers\Api\V1\Management\UserController::class, 'removeRole'])->name('remove-role');
            Route::get('{userId}/permissions', [\App\Http\Controllers\Api\V1\Management\UserController::class, 'permissions'])->name('permissions');
            Route::post('{userId}/invite', [\App\Http\Controllers\Api\V1\Management\UserController::class, 'invite'])->name('invite');
        });

        // Roles Management
        Route::apiResource('roles', \App\Http\Controllers\Api\V1\Management\RoleController::class, [
            'parameters' => ['roles' => 'roleId']
        ])->names([
            'index' => 'management.roles.index',
            'store' => 'management.roles.store',
            'show' => 'management.roles.show',
            'update' => 'management.roles.update',
            'destroy' => 'management.roles.destroy'
        ]);

        // Space Settings
        Route::prefix('settings')->name('management.settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Management\SettingsController::class, 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\Api\V1\Management\SettingsController::class, 'update'])->name('update');
            Route::get('api-keys', [\App\Http\Controllers\Api\V1\Management\SettingsController::class, 'apiKeys'])->name('api-keys');
            Route::post('api-keys', [\App\Http\Controllers\Api\V1\Management\SettingsController::class, 'createApiKey'])->name('create-api-key');
            Route::delete('api-keys/{keyId}', [\App\Http\Controllers\Api\V1\Management\SettingsController::class, 'deleteApiKey'])->name('delete-api-key');
        });
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
