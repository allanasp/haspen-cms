# Development Guide

Advanced development workflows, testing strategies, and debugging techniques for the Headless CMS. This guide includes comprehensive information about the new Story Management System features including content locking, templates, advanced search, and translation workflows.

## Table of Contents

- [Development Environment](#development-environment)
- [Code Quality](#code-quality)
- [Testing](#testing)
- [Database Management](#database-management)
- [API Development](#api-development)
- [Debugging](#debugging)
- [Performance Optimization](#performance-optimization)
- [Best Practices](#best-practices)

## Development Environment

### Prerequisites

- PHP 8.3+
- PostgreSQL 16+
- Redis 7+
- Docker & Docker Compose
- Node.js 18+ (for frontend assets)

### Docker Development Setup

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Access application container
docker-compose exec app bash

# Access database
docker-compose exec db psql -U headless_cms -d headless_cms

# Access Redis CLI
docker-compose exec redis redis-cli
```

### Local Development Setup

```bash
# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
createdb headless_cms
php artisan migrate --seed

# Start development server
php artisan serve --host=0.0.0.0 --port=8000
```

### Environment Configuration

Key environment variables for development:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=headless_cms
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# JWT Configuration
JWT_SECRET=your-jwt-secret-key
JWT_TTL=60  # Token lifetime in minutes

# API Configuration
API_RATE_LIMIT_CDN=60
API_RATE_LIMIT_MANAGEMENT=120
API_RATE_LIMIT_AUTH=10

# Development
APP_DEBUG=true
LOG_LEVEL=debug
LOG_CHANNEL=stack

# Asset Storage
FILESYSTEM_DISK=local
```

## Code Quality

### Static Analysis

```bash
# PHPStan (Level 8)
composer analyse
./vendor/bin/phpstan analyse

# Psalm
composer psalm
./vendor/bin/psalm

# PHP CS Fixer
composer format
./vendor/bin/php-cs-fixer fix
```

### Pre-commit Hooks

Set up Git hooks for automatic code quality checks:

```bash
# Install pre-commit hooks
cp .githooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

# Manual hook execution
.githooks/pre-commit
```

### Code Standards

- **PSR-12** coding standard
- **Strict typing** on all PHP files
- **PHPDoc** for all public methods
- **Type hints** for all parameters and return types

Example:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Story management service.
 */
final class StoryService
{
    /**
     * Get paginated stories for a space.
     *
     * @param string $spaceId
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<Story>
     */
    public function getPaginatedStories(string $spaceId, array $filters = []): LengthAwarePaginator
    {
        // Implementation
    }
}
```

## Testing

### Test Structure

```
tests/
├── Feature/           # Integration tests
│   ├── Api/
│   │   ├── Auth/      # Authentication tests
│   │   ├── Cdn/       # CDN API tests
│   │   └── Management/ # Management API tests
│       │   ├── StoryLockingTest.php
│       │   ├── ContentTemplatesTest.php
│       │   ├── AdvancedSearchTest.php
│       │   └── TranslationWorkflowTest.php
├── Unit/              # Unit tests
│   ├── Models/        # Model tests
│   │   ├── ComponentValidationTest.php
│   │   └── StoryFeaturesTest.php
│   ├── Services/      # Service tests
│   │   ├── StoryServiceTest.php
│   │   ├── VersionManagerTest.php
│   │   └── ContentRendererTest.php
│   └── Middleware/    # Middleware tests
└── TestCase.php       # Base test class
```

### Running Tests

```bash
# Run all tests
composer test
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite=Feature
./vendor/bin/phpunit --testsuite=Unit

# Run specific feature tests
./vendor/bin/phpunit tests/Feature/Api/Management/StoryLockingTest.php
./vendor/bin/phpunit tests/Feature/Api/Management/ContentTemplatesTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Test story management features
./vendor/bin/phpunit --group=story-management

# Parallel testing
./vendor/bin/paratest

# Quick validation tests (custom scripts)
php verify-database.php
php test-story-features.php
```

### Test Database

```bash
# Create test database
createdb headless_cms_test

# Run migrations for testing
php artisan migrate --database=testing

# Reset test database
php artisan migrate:fresh --database=testing --seed
```

### Writing Tests

#### API Test Example

```php
<?php

namespace Tests\Feature\Api\Cdn;

use App\Models\Space;
use App\Models\Story;
use Tests\TestCase;

class StoryControllerTest extends TestCase
{
    public function test_can_list_published_stories(): void
    {
        $space = Space::factory()->create();
        $stories = Story::factory()
            ->for($space)
            ->published()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/cdn/stories', [
            'X-Space-ID' => $space->uuid
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'stories' => [
                    '*' => ['id', 'name', 'slug', 'content', 'published_at']
                ],
                'meta' => ['current_page', 'per_page', 'total']
            ])
            ->assertJsonCount(3, 'stories');
    }

    public function test_can_get_story_by_slug(): void
    {
        $space = Space::factory()->create();
        $story = Story::factory()
            ->for($space)
            ->published()
            ->create(['slug' => 'test-story']);

        $response = $this->getJson('/api/v1/cdn/stories/test-story', [
            'X-Space-ID' => $space->uuid
        ]);

        $response->assertOk()
            ->assertJsonPath('story.slug', 'test-story')
            ->assertJsonPath('story.name', $story->name);
    }
}
```

#### Component Validation Test Example

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Component;
use Tests\TestCase;

class ComponentValidationTest extends TestCase
{
    public function test_validates_required_fields(): void
    {
        $component = Component::factory()->create([
            'schema' => [
                'title' => ['type' => 'text', 'required' => true],
                'description' => ['type' => 'textarea', 'required' => false]
            ]
        ]);

        $validData = ['title' => 'Test Title', 'description' => 'Test description'];
        $errors = $component->validateData($validData);
        $this->assertEmpty($errors);

        $invalidData = ['description' => 'Missing required title'];
        $errors = $component->validateData($invalidData);
        $this->assertArrayHasKey('title', $errors);
        $this->assertStringContains('required', $errors['title']);
    }
    
    public function test_validates_field_types(): void
    {
        $component = Component::factory()->create([
            'schema' => [
                'email' => ['type' => 'email', 'required' => true],
                'count' => ['type' => 'number', 'min' => 0, 'max' => 100],
                'featured' => ['type' => 'boolean', 'required' => false]
            ]
        ]);

        $invalidData = [
            'email' => 'invalid-email',
            'count' => 150, // over max
            'featured' => 'not_boolean'
        ];
        
        $errors = $component->validateData($invalidData);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('count', $errors);
        $this->assertArrayHasKey('featured', $errors);
    }
}
```

### Factories

Create realistic test data using model factories:

```php
<?php

namespace Database\Factories;

use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => $this->faker->sentence(4),
            'slug' => $this->faker->slug,
            'content' => [
                'body' => [
                    [
                        '_uid' => $this->faker->uuid,
                        'component' => 'hero',
                        'title' => $this->faker->sentence,
                        'description' => $this->faker->paragraph
                    ]
                ]
            ],
            'status' => 'draft',
            'language' => 'en'
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'published_at' => now()
        ]);
    }
}
```

## Database Management

### Migrations

```bash
# Create migration
php artisan make:migration create_example_table

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration with seeds
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status
```

### Seeds and Factories

```bash
# Create seeder
php artisan make:seeder ExampleSeeder

# Run specific seeder
php artisan db:seed --class=ExampleSeeder

# Create factory
php artisan make:factory ExampleFactory
```

### Database Queries

```bash
# Database tinker
php artisan tinker

# Query examples
>>> App\Models\Story::where('status', 'published')->count()
>>> App\Models\Space::with('stories')->find('uuid')
>>> App\Models\Component::whereJsonContains('schema->title->required', true)->get()
```

## API Development

### Creating New Endpoints

1. **Create Controller**

```bash
php artisan make:controller Api/V1/Management/ExampleController
```

2. **Add Routes**

```php
// routes/api.php
Route::prefix('v1/spaces/{space_id}')->group(function () {
    Route::apiResource('examples', ExampleController::class);
    
    // Story management features
    Route::prefix('stories/{story}')->group(function () {
        Route::post('lock', [StoryController::class, 'lock']);
        Route::delete('lock', [StoryController::class, 'unlock']);
        Route::put('lock', [StoryController::class, 'extendLock']);
        Route::get('lock', [StoryController::class, 'getLockStatus']);
        
        Route::post('create-template', [StoryController::class, 'createTemplate']);
        Route::post('translations', [StoryController::class, 'createTranslation']);
        Route::get('translations', [StoryController::class, 'getTranslations']);
        Route::post('sync-translation', [StoryController::class, 'syncTranslation']);
    });
    
    Route::get('stories/templates', [StoryController::class, 'getTemplates']);
    Route::post('stories/from-template', [StoryController::class, 'createFromTemplate']);
    Route::get('stories/search/suggestions', [StoryController::class, 'getSearchSuggestions']);
    Route::get('stories/search/stats', [StoryController::class, 'getSearchStats']);
});
```

3. **Create Request Validation**

```bash
php artisan make:request StoreExampleRequest
php artisan make:request UpdateExampleRequest
```

4. **Create API Resource**

```bash
php artisan make:resource ExampleResource
```

### Middleware Development

```bash
# Create middleware
php artisan make:middleware ExampleMiddleware

# Register in bootstrap/app.php
$middleware->alias([
    'example' => \App\Http\Middleware\ExampleMiddleware::class,
]);
```

### OpenAPI Documentation

Add OpenAPI annotations to controllers:

```php
/**
 * @OA\Get(
 *     path="/api/v1/spaces/{space_id}/examples",
 *     summary="List examples",
 *     tags={"Management - Examples"},
 *     @OA\Parameter(
 *         name="space_id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Examples retrieved successfully"
 *     )
 * )
 */
public function index(Request $request): JsonResponse
{
    // Implementation
}
```

## Debugging

### Logging

```php
// Different log levels
Log::debug('Debug information', ['context' => $data]);
Log::info('Informational message');
Log::warning('Warning message');
Log::error('Error occurred', ['exception' => $e]);

// API-specific logging
Log::channel('api')->info('API request processed', [
    'request_id' => $requestId,
    'user_id' => auth()->id(),
    'space_id' => $space->id
]);
```

### Debugging Tools

```bash
# Laravel Tinker
php artisan tinker

# Database queries
DB::enableQueryLog();
// ... perform operations
dd(DB::getQueryLog());

# Dump and die
dd($variable);
dump($variable);

# Ray debugging (if installed)
ray($variable);
```

### Performance Profiling

```php
// Measure execution time
$start = microtime(true);
// ... code to measure
$executionTime = microtime(true) - $start;
Log::debug("Operation took {$executionTime} seconds");

// Memory usage
$memoryStart = memory_get_usage();
// ... code to measure  
$memoryUsed = memory_get_usage() - $memoryStart;
Log::debug("Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . " MB");
```

## Performance Optimization

### Database Optimization

```php
// Eager loading
$stories = Story::with(['space', 'creator', 'translations'])->get();

// Lazy loading prevention
Model::preventLazyLoading(! app()->isProduction());

// Query optimization
$stories = Story::select(['id', 'name', 'slug'])
    ->where('status', 'published')
    ->orderBy('published_at', 'desc')
    ->limit(10)
    ->get();

// Index usage
Story::whereJsonContains('content->body', ['component' => 'hero'])->get();
```

### Caching Strategies

```php
// Model caching
$stories = Cache::remember(
    "space.{$spaceId}.stories.published",
    now()->addMinutes(30),
    fn () => Story::published()->where('space_id', $spaceId)->get()
);

// Query caching
$components = Cache::tags(['components', "space.{$spaceId}"])
    ->remember(
        "space.{$spaceId}.components",
        now()->addHour(),
        fn () => Component::where('space_id', $spaceId)->get()
    );

// Cache invalidation
Cache::tags(['components', "space.{$spaceId}"])->flush();
```

### Queue Optimization

```php
// Background jobs
dispatch(new ProcessAssetJob($asset));

// Batch jobs
Bus::batch([
    new ProcessImageJob($image1),
    new ProcessImageJob($image2),
    new ProcessImageJob($image3),
])->dispatch();

// Queue monitoring
php artisan queue:work --timeout=60 --memory=128
```

## Best Practices

### API Development

1. **Consistent Response Format**

```php
// Success response
return response()->json([
    'data' => $resource,
    'meta' => $metadata
]);

// Error response
return response()->json([
    'error' => 'Error type',
    'message' => 'Human readable message',
    'errors' => $validationErrors
], 422);
```

2. **Input Validation**

```php
// Form requests for validation
class StoreStoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'story.name' => 'required|string|max:255',
            'story.content' => 'required|array',
            'story.status' => 'sometimes|in:draft,published'
        ];
    }
}
```

3. **Resource Transformation**

```php
// API resources for consistent output
class StoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'content' => $this->content,
            'published_at' => $this->published_at?->toISOString(),
            'meta' => $this->when($this->meta_title, [
                'title' => $this->meta_title,
                'description' => $this->meta_description
            ])
        ];
    }
}
```

### Security

1. **Input Sanitization**

```php
// Sanitize HTML content
$cleanContent = strip_tags($input, '<p><br><strong><em>');

// Validate UUIDs
if (!Str::isUuid($id)) {
    abort(400, 'Invalid UUID format');
}
```

2. **Rate Limiting**

```php
// Custom rate limiting
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

3. **Permission Checking**

```php
// Check user permissions
if (!$user->can('publish', $story)) {
    abort(403, 'Insufficient permissions');
}
```

### Code Organization

1. **Service Classes**

```php
// Advanced story management service
class StoryService extends BaseService
{
    public function __construct(
        private VersionManager $versionManager,
        private ContentRenderer $contentRenderer
    ) {}
    
    public function publishStory(Story $story, ?Carbon $scheduledAt = null): Story
    {
        // Validate content before publishing
        $errors = $this->validateStoryContent($story->content);
        if (!empty($errors)) {
            throw new ValidationException('Story content validation failed', $errors);
        }
        
        $story->status = $scheduledAt ? 'scheduled' : 'published';
        $story->published_at = $scheduledAt ?? now();
        $story->save();
        
        // Create version snapshot
        $this->versionManager->createVersion($story, auth()->user(), 'Published story');
        
        event(new StoryPublished($story));
        
        return $story;
    }
    
    public function getPaginatedStories(Space $space, array $filters = []): LengthAwarePaginator
    {
        $query = $space->stories();
        
        // Apply advanced search if provided
        if (isset($filters['search'])) {
            $query = $this->applyAdvancedSearch($query, $filters);
        }
        
        return $query->paginate($filters['per_page'] ?? 25);
    }
}
```

2. **Repository Pattern**

```php
// Data access layer
class StoryRepository extends BaseRepository
{
    protected function model(): string
    {
        return Story::class;
    }

    public function findPublishedBySlug(string $spaceId, string $slug): ?Story
    {
        return $this->query()
            ->where('space_id', $spaceId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }
}
```

### Testing Strategy

1. **Test Pyramid**
   - Unit tests: 70%
   - Integration tests: 20%  
   - E2E tests: 10%

2. **Story Management Feature Testing**

```php
// Content locking tests
public function test_user_can_lock_story_for_editing(): void
public function test_locked_story_prevents_concurrent_edits(): void
public function test_lock_expires_automatically(): void

// Template system tests
public function test_can_create_template_from_story(): void
public function test_can_create_story_from_template(): void
public function test_template_preserves_structure(): void

// Advanced search tests
public function test_can_search_stories_by_content(): void
public function test_can_filter_by_component_type(): void
public function test_search_suggestions_work(): void

// Translation workflow tests
public function test_can_create_translation(): void
public function test_translation_sync_detects_changes(): void
public function test_translation_completion_calculated(): void

// Component validation tests
public function test_component_validates_required_fields(): void
public function test_component_validates_field_types(): void
public function test_nested_component_validation(): void
```

3. **Test Data Setup**

```php
// Enhanced test factories for new features
class StoryFactory extends Factory
{
    public function withLock(User $user = null): static
    {
        return $this->state([
            'locked_by' => $user?->id ?? User::factory(),
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(30),
            'lock_session_id' => Str::uuid()
        ]);
    }
    
    public function asTemplate(): static
    {
        return $this->state([
            'meta_data' => [
                'is_template' => true,
                'template_category' => 'blog'
            ]
        ]);
    }
}
```

3. **Database Testing**

```php
// Use transactions for faster tests
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;
    
    // Test implementation
}
```

### Story Management Feature Development

#### Testing New Features

1. **Database Verification**

```bash
# Run database structure verification
php verify-database.php

# Expected output:
# ✅ stories - Stories table with locking fields
# ✅ story_versions - Story versions for version management
# ✅ components - Components for schema validation
# ✅ Content locking fields present
```

2. **Component Validation Testing**

```bash
# Test component validation directly
php test-story-features.php

# Expected output:
# ✅ PASS: Valid data validation passed
# ✅ PASS: Required field validation works
# ✅ PASS: Type validation works
```

3. **API Endpoint Testing**

```bash
# Start development server
php artisan serve

# Test story locking
curl -X POST http://localhost:8000/api/v1/spaces/{space_id}/stories/{story_id}/lock \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "X-Session-ID: $SESSION_ID" \
  -d '{"duration_minutes": 30}'

# Test template creation
curl -X POST http://localhost:8000/api/v1/spaces/{space_id}/stories/{story_id}/create-template \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -d '{"name": "Blog Template", "description": "Standard blog post template"}'

# Test advanced search
curl "http://localhost:8000/api/v1/spaces/{space_id}/stories?search=blog&search_mode=comprehensive" \
  -H "Authorization: Bearer $JWT_TOKEN"
```

#### Key Development Endpoints

| Feature | Endpoint | Method | Description |
|---------|----------|---------|-------------|
| Content Locking | `/stories/{id}/lock` | POST | Lock story for editing |
| Content Locking | `/stories/{id}/lock` | DELETE | Unlock story |
| Content Templates | `/stories/templates` | GET | List available templates |
| Content Templates | `/stories/{id}/create-template` | POST | Create template from story |
| Advanced Search | `/stories/search/suggestions` | GET | Get search suggestions |
| Translation | `/stories/{id}/translations` | POST | Create translation |
| Translation | `/stories/{id}/sync-translation` | POST | Sync translation structure |

#### Development Workflow

1. **Feature Development Cycle**
   - Update models with new functionality
   - Add database migrations if needed
   - Implement service layer logic
   - Create API endpoints
   - Add comprehensive tests
   - Update API documentation

2. **Quality Assurance**
   - Run static analysis: `composer analyse`
   - Run all tests: `composer test`
   - Verify database: `php verify-database.php`
   - Test core features: `php test-story-features.php`
   - Manual API testing with Postman/curl

---

For deployment instructions, see the [Deployment Guide](DEPLOYMENT.md).