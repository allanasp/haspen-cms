# Trait Documentation

This document provides comprehensive documentation for all reusable traits in the headless CMS, including usage examples, implementation details, and best practices.

## Table of Contents

- [HasUuid Trait](#hasuuid-trait)
- [MultiTenant Trait](#multitenant-trait)
- [Sluggable Trait](#sluggable-trait)
- [Cacheable Trait](#cacheable-trait)
- [Usage Patterns](#usage-patterns)
- [Best Practices](#best-practices)

---

## HasUuid Trait

**UUID Functionality for Public API Exposure**

Provides comprehensive UUID functionality for Eloquent models, enabling secure public API exposure while keeping internal database IDs hidden. This trait automatically generates UUIDs for new models and provides helper methods for UUID-based operations.

### Key Features:
- **Automatic UUID Generation**: UUIDs are generated automatically when models are created
- **Route Model Binding**: Enable UUID-based route model binding for clean API URLs
- **Helper Methods**: Convenient methods for finding models by UUID
- **Security**: Internal database IDs remain hidden from public APIs
- **Performance**: Indexed UUID column for fast lookups
- **Standard Compliance**: Uses Laravel's Str::uuid() for RFC 4122 compliance

### Usage Examples:

#### Basic Model Setup
```php
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasUuid;
    
    // UUID column will be automatically populated
    // Route model binding will work with UUID
}
```

#### Route Model Binding
```php
// routes/api.php
Route::get('/stories/{story:uuid}', [StoryController::class, 'show']);

// Controller method
public function show(Story $story)
{
    // $story is automatically resolved by UUID
    return response()->json($story);
}

// API URL examples:
// GET /api/stories/123e4567-e89b-12d3-a456-426614174000
```

#### Finding Models by UUID
```php
// Find by UUID (returns model or null)
$story = Story::findByUuid('123e4567-e89b-12d3-a456-426614174000');

// Find by UUID or fail (throws ModelNotFoundException)
$story = Story::findByUuidOrFail('123e4567-e89b-12d3-a456-426614174000');

// Check if UUID exists
if (Story::whereUuid($uuid)->exists()) {
    // UUID exists in database
}
```

#### Manual UUID Generation
```php
// Normally UUIDs are auto-generated, but you can set manually
$story = new Story();
$story->uuid = (string) Str::uuid(); // Custom UUID
$story->name = 'My Story';
$story->save();

// Or during creation
$story = Story::create([
    'uuid' => (string) Str::uuid(),
    'name' => 'My Story',
    // ... other fields
]);
```

#### API Resource Integration
```php
// API Resource using UUID
class StoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->uuid, // Expose UUID as 'id' in API
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            // Internal 'id' field is never exposed
        ];
    }
}
```

### Implementation Details:

#### Database Migration
```php
// Add UUID column to your migration
Schema::table('stories', function (Blueprint $table) {
    $table->uuid('uuid')->unique()->after('id');
    $table->index('uuid'); // For performance
});
```

#### Configuration Options
```php
class Story extends Model
{
    use HasUuid;
    
    // Customize UUID column name (optional)
    protected string $uuidColumn = 'uuid'; // Default
    
    // Hide internal ID from JSON serialization
    protected $hidden = ['id'];
}
```

---

## MultiTenant Trait

**Automatic Space-Based Data Scoping**

Provides automatic multi-tenant data isolation by scoping all database queries to the current space. This trait ensures complete data separation between tenants while providing helper methods for cross-space operations when needed.

### Key Features:
- **Automatic Query Scoping**: All queries automatically filtered by current space
- **Global Scope Management**: Uses Eloquent global scopes for consistent behavior
- **Cross-Space Operations**: Helper methods for admin operations across spaces
- **Space Relationship**: Automatic space relationship and helper methods
- **Middleware Integration**: Works seamlessly with space resolution middleware
- **Data Isolation**: Ensures complete tenant data separation

### Usage Examples:

#### Basic Model Setup
```php
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use MultiTenant;
    
    // All queries will be automatically scoped to current space
    // Space relationship is automatically available
}
```

#### Automatic Query Scoping
```php
// Current space is set by middleware
app()->instance('current.space', $currentSpace);

// All queries are automatically scoped
$stories = Story::all(); // WHERE space_id = current_space_id
$publishedStories = Story::where('status', 'published')->get(); // WHERE space_id = X AND status = 'published'

// Relationships are also scoped
$user->stories; // Only stories in current space
```

#### Cross-Space Operations
```php
// Remove space scoping for admin operations
$allStories = Story::withoutGlobalScope('space')->get();

// Query specific space
$spaceStories = Story::forSpace($otherSpace)->get();

// Multiple spaces (admin operations)
$multiSpaceStories = Story::withoutGlobalScope('space')
    ->whereIn('space_id', [1, 2, 3])
    ->get();
```

#### Space Context Management
```php
// Set current space context
app()->instance('current.space', $space);

// Check current space
$currentSpace = app('current.space');

// Temporarily switch space context
$originalSpace = app('current.space');
app()->instance('current.space', $otherSpace);

// ... do work in other space context ...

// Restore original context
app()->instance('current.space', $originalSpace);
```

#### Creating Multi-Tenant Records
```php
// Space ID is automatically set from current context
$story = Story::create([
    'name' => 'My Story',
    'content' => [...],
    // space_id is automatically set
]);

// Explicit space assignment (overrides current context)
$story = Story::create([
    'name' => 'My Story',
    'space_id' => $specificSpace->id,
    'content' => [...],
]);
```

#### Middleware Integration
```php
// Middleware to set current space
class SetCurrentSpace
{
    public function handle($request, Closure $next)
    {
        // Resolve space from subdomain, header, or route parameter
        $space = $this->resolveSpace($request);
        
        // Set current space context
        app()->instance('current.space', $space);
        
        return $next($request);
    }
}
```

### Implementation Details:

#### Database Schema
```php
// Migration: Add space_id to multi-tenant tables
Schema::table('stories', function (Blueprint $table) {
    $table->foreignId('space_id')->constrained()->onDelete('cascade');
    $table->index(['space_id', 'status']); // Composite indexes for performance
});
```

#### Global Scope Implementation
The trait automatically adds a global scope that filters all queries:
```php
// Automatic scope added by trait
static::addGlobalScope('space', function (Builder $builder): void {
    $currentSpace = app('current.space');
    
    if ($currentSpace instanceof Space) {
        $builder->where('space_id', $currentSpace->id);
    }
});
```

---

## Sluggable Trait

**URL-Friendly Slug Generation**

Provides automatic generation of URL-friendly slugs from source fields with uniqueness validation and conflict resolution. This trait handles slug creation, updates, and ensures URL uniqueness within the appropriate scope.

### Key Features:
- **Automatic Slug Generation**: Slugs generated from configurable source fields
- **Uniqueness Validation**: Ensures slugs are unique within defined scope
- **Conflict Resolution**: Automatic numbering for duplicate slugs
- **Manual Control**: Option to disable auto-updates for manual slug management
- **Multi-Scope Support**: Different uniqueness scopes (global, space-based, parent-based)
- **URL Safety**: Handles special characters, Unicode, and reserved words

### Usage Examples:

#### Basic Configuration
```php
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use Sluggable;
    
    // Configure slug generation
    protected string $slugSourceField = 'name'; // Source field for slug
    protected bool $autoUpdateSlug = false; // Manual control
    
    // Slug will be generated from 'name' field
}
```

#### Advanced Configuration
```php
class Story extends Model
{
    use Sluggable;
    
    protected string $slugSourceField = 'title';
    protected bool $autoUpdateSlug = true; // Auto-update on title changes
    protected string $slugColumn = 'slug'; // Custom slug column name
    protected int $slugMaxLength = 100; // Maximum slug length
    
    // Custom scope for uniqueness validation
    protected function getSlugUniqueScope(Builder $query): Builder
    {
        // Ensure uniqueness within same parent and language
        return $query->where('parent_id', $this->parent_id)
                    ->where('language', $this->language);
    }
}
```

#### Slug Generation Examples
```php
// Automatic slug generation
$story = Story::create([
    'name' => 'My Awesome Blog Post',
    // slug will be auto-generated as 'my-awesome-blog-post'
]);

// Manual slug setting
$story = Story::create([
    'name' => 'My Story',
    'slug' => 'custom-url-slug',
]);

// Slug conflict resolution
$story1 = Story::create(['name' => 'Same Title']); // slug: 'same-title'
$story2 = Story::create(['name' => 'Same Title']); // slug: 'same-title-2'
$story3 = Story::create(['name' => 'Same Title']); // slug: 'same-title-3'
```

#### Working with Hierarchical Slugs
```php
// Parent story
$blogFolder = Story::create([
    'name' => 'Blog',
    'is_folder' => true,
]); // slug: 'blog'

// Child story
$blogPost = Story::create([
    'name' => 'My First Post',
    'parent_id' => $blogFolder->id,
]); // slug: 'my-first-post'

// Full path generation (handled by Story model)
echo $blogPost->full_slug; // 'blog/my-first-post'
echo $blogPost->path; // '/blog/my-first-post'
```

#### Finding by Slug
```php
// Find by slug
$story = Story::findBySlug('my-awesome-post');

// Find by slug or fail
$story = Story::findBySlugOrFail('my-awesome-post');

// Route model binding with slug
Route::get('/stories/{story:slug}', [StoryController::class, 'show']);
```

#### Manual Slug Management
```php
// Regenerate slug from source field
$story->generateSlugIfEmpty();

// Force regenerate slug
$story->slug = null;
$story->generateSlugIfEmpty(); // Will regenerate

// Update slug when source changes (if autoUpdateSlug is false)
$story->name = 'New Title';
$story->updateSlugIfNeeded();
$story->save();
```

#### Unicode and Special Character Handling
```php
// Unicode support
$story = Story::create([
    'name' => 'Café & Restaurant París',
]); // slug: 'cafe-restaurant-paris'

// Special characters
$story = Story::create([
    'name' => 'C++ Programming & More!!!',
]); // slug: 'c-programming-more'

// Reserved word handling
$story = Story::create([
    'name' => 'API',
]); // slug: 'api' (or 'api-1' if 'api' exists)
```

### Implementation Details:

#### Database Migration
```php
Schema::table('stories', function (Blueprint $table) {
    $table->string('slug')->unique(); // Basic uniqueness
    
    // Or composite uniqueness within space
    $table->string('slug');
    $table->unique(['space_id', 'slug']);
    
    // Or within parent for hierarchical content
    $table->unique(['parent_id', 'slug']);
});
```

#### Customization Options
```php
class Story extends Model
{
    use Sluggable;
    
    // Custom slug transformation
    protected function transformSlugSource(string $source): string
    {
        // Custom transformation logic
        return strtolower(trim($source));
    }
    
    // Custom uniqueness validation
    protected function validateSlugUniqueness(string $slug): bool
    {
        return !$this->getSlugUniqueScope($this->newQuery())
                    ->where('slug', $slug)
                    ->where('id', '!=', $this->id ?? 0)
                    ->exists();
    }
}
```

---

## Cacheable Trait

**Model-Level Caching with Automatic Invalidation**

Provides intelligent caching capabilities for Eloquent models with automatic cache invalidation, configurable TTL, and model-specific cache management. This trait enables significant performance improvements for frequently accessed data.

### Key Features:
- **Model-Level Caching**: Cache expensive operations and frequently accessed data
- **Automatic Invalidation**: Cache is cleared when models are updated or deleted
- **Configurable TTL**: Set different cache durations per model and operation
- **Query Result Caching**: Cache query results with automatic invalidation
- **Tagged Caching**: Use cache tags for efficient bulk invalidation
- **Multiple Cache Drivers**: Support for Redis, Memcached, and other Laravel cache drivers

### Usage Examples:

#### Basic Configuration
```php
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use Cacheable;
    
    // Configure default cache TTL (in seconds)
    protected int $cacheTtl = 3600; // 1 hour
    
    // Cache will be automatically managed
}
```

#### Caching Expensive Operations
```php
// Cache expensive calculations
$storiesCount = $space->getCached('stories_count', function () {
    return $this->stories()->count();
}, 3600); // Cache for 1 hour

// Cache database queries
$popularStories = $space->getCached('popular_stories', function () {
    return $this->stories()
        ->published()
        ->orderBy('views', 'desc')
        ->limit(10)
        ->get();
}, 1800); // Cache for 30 minutes

// Cache API responses
$externalData = $space->getCached('external_api_data', function () {
    return Http::get('https://api.example.com/data')->json();
}, 7200); // Cache for 2 hours
```

#### Query Result Caching
```php
// Cache query results with automatic invalidation
$publishedStories = Story::cacheQuery('published_stories', 
    Story::published()->latest(), 
    1800 // 30 minutes
);

// Cache with custom key generation
$userStories = Story::cacheQuery("user_{$user->id}_stories",
    Story::where('created_by', $user->id)->published(),
    3600
);

// Cache complex queries
$stats = Story::cacheQuery('story_stats', 
    Story::selectRaw('
        COUNT(*) as total,
        COUNT(CASE WHEN status = "published" THEN 1 END) as published,
        COUNT(CASE WHEN status = "draft" THEN 1 END) as drafts
    '),
    1800
);
```

#### Model-Specific Caching
```php
class Space extends Model
{
    use Cacheable;
    
    protected int $cacheTtl = 86400; // 24 hours
    
    // Cache model methods
    public function getStoriesCount(): int
    {
        return $this->getCached('stories_count', function () {
            return $this->stories()->count();
        });
    }
    
    public function getAssetsSize(): int
    {
        return $this->getCached('assets_size', function () {
            return $this->assets()->sum('file_size');
        }, 7200); // 2 hours
    }
    
    // Custom cache invalidation
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('stories_count');
        $this->forgetCache('assets_size');
        $this->forgetCache('popular_stories');
    }
}
```

#### Manual Cache Management
```php
// Clear specific cache
$space->forgetCache('stories_count');

// Clear all model caches
$space->clearCache();

// Clear cache for all models of same type
Space::clearModelCache();

// Refresh cache (clear and recalculate)
$space->refreshCache('stories_count', function () {
    return $this->stories()->count();
});
```

#### Tagged Cache Management
```php
// Cache with tags for bulk invalidation
$space->getCachedWithTags(['space', 'stories'], 'story_data', function () {
    return $this->stories()->with('creator')->get();
}, 3600);

// Clear all caches with specific tags
Cache::tags(['space', 'stories'])->flush();

// Clear space-specific caches
Cache::tags(['space', "space_{$space->id}"])->flush();
```

#### Event-Based Cache Invalidation
```php
class Story extends Model
{
    use Cacheable;
    
    protected static function boot()
    {
        parent::boot();
        
        // Clear related caches when story is updated
        static::saved(function (Story $story) {
            $story->space->forgetCache('stories_count');
            $story->space->forgetCache('popular_stories');
            
            // Clear user-specific caches
            if ($story->created_by) {
                Cache::forget("user_{$story->created_by}_stories");
            }
        });
        
        // Clear caches when story is deleted
        static::deleted(function (Story $story) {
            $story->space->forgetCache('stories_count');
        });
    }
}
```

#### Performance Monitoring
```php
// Cache hit/miss tracking
$space->getCached('stories_count', function () {
    event(new CacheMiss('space_stories_count'));
    return $this->stories()->count();
}, 3600, function () {
    event(new CacheHit('space_stories_count'));
});

// Cache performance metrics
$cacheStats = [
    'hits' => Cache::get('cache_hits_count', 0),
    'misses' => Cache::get('cache_misses_count', 0),
    'hit_ratio' => $hits / ($hits + $misses) * 100,
];
```

### Implementation Details:

#### Cache Key Generation
```php
// Automatic cache key generation
protected function getCacheKey(string $key): string
{
    return sprintf(
        '%s:%s:%s',
        $this->getTable(),
        $this->getKey(),
        $key
    );
}

// Custom cache key generation
protected function getCacheKey(string $key): string
{
    return "space_{$this->id}_{$key}";
}
```

#### Configuration Options
```php
class Story extends Model
{
    use Cacheable;
    
    // Custom cache TTL per operation
    protected array $cacheTtls = [
        'content' => 7200,    // 2 hours
        'metadata' => 3600,   // 1 hour
        'statistics' => 1800, // 30 minutes
    ];
    
    // Cache driver selection
    protected string $cacheDriver = 'redis';
    
    // Disable caching in certain environments
    protected function shouldCache(): bool
    {
        return !app()->environment('testing');
    }
}
```

---

## Usage Patterns

### Combining Traits
```php
class Story extends Model
{
    use HasFactory, HasUuid, MultiTenant, Sluggable, Cacheable, SoftDeletes;
    
    // Configure traits
    protected string $slugSourceField = 'name';
    protected bool $autoUpdateSlug = false;
    protected int $cacheTtl = 3600;
    
    // Traits work together seamlessly
    // - HasFactory for test data generation
    // - HasUuid for API exposure and public identification
    // - MultiTenant for automatic space-based data isolation
    // - Sluggable for URL-friendly slug generation
    // - Cacheable for intelligent performance optimization
    // - SoftDeletes for data safety and recovery
}

class User extends Model
{
    use HasFactory, HasApiTokens, HasUuid, Cacheable, SoftDeletes, Notifiable;
    
    protected int $cacheTtl = 3600;
    
    // User-specific trait combination
    // - HasApiTokens for Sanctum authentication
    // - HasUuid for secure public identification
    // - Cacheable for user data and permissions caching
    // - Notifiable for email and push notifications
}

class Space extends Model
{
    use HasFactory, HasUuid, Cacheable;
    
    protected int $cacheTtl = 86400; // 24 hours
    
    // Space-specific traits for tenant management
    // - HasUuid for secure tenant identification
    // - Cacheable for space settings and configuration caching
}

class Component extends Model
{
    use HasFactory, HasUuid, MultiTenant, Cacheable;
    
    protected int $cacheTtl = 7200; // 2 hours
    
    // Component management with validation caching
}
```

### API Development Pattern
```php
// Model with full trait suite for API
class ApiModel extends Model
{
    use HasUuid, MultiTenant, Cacheable;
    
    protected $hidden = ['id', 'space_id'];
    
    // API-friendly methods
    public function toApiArray(): array
    {
        return $this->getCached('api_data', function () {
            return [
                'id' => $this->uuid,
                'name' => $this->name,
                'created_at' => $this->created_at->toISOString(),
            ];
        }, 1800);
    }
}
```

### Performance Optimization Pattern
```php
class OptimizedModel extends Model
{
    use Cacheable, MultiTenant;
    
    // Cache expensive relationships
    public function getCachedRelation(string $relation): Collection
    {
        return $this->getCached("relation_{$relation}", function () use ($relation) {
            return $this->$relation;
        }, 3600);
    }
    
    // Cache computed properties
    public function getComputedAttribute(): mixed
    {
        return $this->getCached('computed_attribute', function () {
            // Expensive computation here
            return $this->performExpensiveCalculation();
        });
    }
}
```

---

## Best Practices

### Trait Usage Guidelines

1. **Use HasUuid for all API-exposed models**
   ```php
   // ✅ Good: API models use UUID
   class Story extends Model
   {
       use HasUuid;
       protected $hidden = ['id'];
   }
   ```

2. **Apply MultiTenant to all tenant-scoped models**
   ```php
   // ✅ Good: Consistent multi-tenant scoping
   class Story extends Model
   {
       use MultiTenant;
   }
   ```

3. **Use Sluggable for user-facing URLs**
   ```php
   // ✅ Good: SEO-friendly URLs
   class Story extends Model
   {
       use Sluggable;
       protected string $slugSourceField = 'title';
   }
   ```

4. **Apply Cacheable to performance-critical models**
   ```php
   // ✅ Good: Cache expensive operations
   class Space extends Model
   {
       use Cacheable;
       
       public function getMetrics(): array
       {
           return $this->getCached('metrics', function () {
               return $this->calculateExpensiveMetrics();
           }, 3600);
       }
   }
   ```

### Performance Considerations

1. **Configure appropriate cache TTLs**
   ```php
   // Different TTLs for different data types
   protected array $cacheTtls = [
       'stats' => 3600,      // 1 hour - changes infrequently
       'user_data' => 1800,  // 30 min - changes moderately
       'live_data' => 300,   // 5 min - changes frequently
   ];
   ```

2. **Use cache tags for efficient invalidation**
   ```php
   // Group related caches with tags
   $this->getCachedWithTags(['user', 'preferences'], 'user_prefs', $callback);
   ```

3. **Implement custom cache invalidation**
   ```php
   protected function clearModelSpecificCache(): void
   {
       // Clear only relevant caches
       $this->forgetCache('expensive_calculation');
       $this->forgetCache('related_data');
   }
   ```

### Security Considerations

1. **Always hide internal IDs when using HasUuid**
   ```php
   protected $hidden = ['id', 'space_id'];
   ```

2. **Validate UUID input in controllers**
   ```php
   if (!Str::isUuid($uuid)) {
       abort(400, 'Invalid UUID format');
   }
   ```

3. **Use MultiTenant trait for all tenant-scoped data**
   ```php
   // Prevents cross-tenant data access
   $stories = Story::all(); // Automatically scoped
   ```

### Testing Patterns

1. **Test trait functionality in isolation**
   ```php
   public function test_uuid_generation()
   {
       $model = new TestModel();
       $model->save();
       
       $this->assertNotNull($model->uuid);
       $this->assertTrue(Str::isUuid($model->uuid));
   }
   ```

2. **Test multi-tenant isolation**
   ```php
   public function test_multi_tenant_scoping()
   {
       $space1 = Space::factory()->create();
       $space2 = Space::factory()->create();
       
       app()->instance('current.space', $space1);
       
       $story1 = Story::factory()->create();
       
       app()->instance('current.space', $space2);
       
       $this->assertCount(0, Story::all()); // Should not see space1's story
   }
   ```

3. **Test cache invalidation**
   ```php
   public function test_cache_invalidation()
   {
       $model = TestModel::factory()->create();
       
       // Prime cache
       $result1 = $model->getCached('test', fn() => 'cached_value');
       
       // Update model (should clear cache)
       $model->update(['name' => 'new name']);
       
       // Cache should be cleared
       $this->assertNull(Cache::get($model->getCacheKey('test')));
   }
   ```