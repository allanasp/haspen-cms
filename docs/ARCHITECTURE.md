# Architecture Documentation

This document provides a detailed technical overview of the headless CMS architecture, designed to help developers understand the system's design principles and implementation details.

## Table of Contents

- [System Overview](#system-overview)
- [API Architecture](#api-architecture)
- [Database Architecture](#database-architecture)
- [Multi-Tenant Design](#multi-tenant-design)
- [Security Model](#security-model)
- [Performance Strategy](#performance-strategy)
- [Component System](#component-system)
- [Data Flow](#data-flow)

## System Overview

The headless CMS is built on Laravel 11.x with a focus on:

- **Three-Tier API**: Content Delivery, Management, and Authentication APIs
- **Multi-tenancy**: Complete data isolation between spaces (tenants)
- **Component-based content**: Storyblok-style content management with JSON schemas and validation
- **Content Management**: Advanced features including locking, templates, and version management
- **Translation Workflow**: Smart translation sync with completion tracking
- **Advanced Search**: Full-text search with component filtering and analytics
- **Modern PHP**: PHP 8.3+ with strict typing and advanced features
- **Performance**: Redis caching, PostgreSQL with JSONB, and optimized queries
- **Developer Experience**: Comprehensive traits, OpenAPI documentation, and type safety

### High-Level Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │  Admin Panel    │    │  Mobile App     │
│                 │    │                 │    │                 │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          │ CDN API              │ Management API       │ CDN API
          │                      │                      │
┌─────────▼──────────────────────▼──────────────────────▼───────┐
│                        API Gateway                            │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐   │
│  │   CDN API   │  │ Management  │  │  Authentication     │   │
│  │ (Public)    │  │ API (Auth)  │  │  API                │   │
│  └─────────────┘  └─────────────┘  └─────────────────────┘   │
└─────────┬──────────────────────────────────────────────────┘
          │
┌─────────▼───────┐    ┌──────────────┐    ┌─────────────────┐
│   Application   │    │  PostgreSQL  │    │     Redis       │
│   Layer         │◄──►│   Database   │    │   (Cache/       │
│  ┌─────────────┐ │    │              │    │   Sessions/     │
│  │  Services   │ │    │              │    │   Locks)        │
│  │  Layer      │ │    │              │    │                 │
│  └─────────────┘ │    │              │    │                 │
└─────────────────┘    └──────────────┘    └─────────────────┘
```

## API Architecture

### Three-Tier API Design

The API is organized into three distinct tiers, each with specific purposes and security requirements:

#### 1. Content Delivery API (`/api/v1/cdn/`)

**Purpose**: Public content access for frontend applications

```
┌─────────────────────────────────────┐
│           CDN API                   │
├─────────────────────────────────────┤
│ • No authentication required       │
│ • High performance caching          │
│ • Rate limit: 60 req/min           │
│ • Public content only              │
│ • Image transformations            │
└─────────────────────────────────────┘
```

**Endpoints:**
- `GET /stories` - List published stories
- `GET /stories/{slug}` - Get story by slug
- `GET /datasources/{slug}` - Get datasource entries
- `GET /assets/{filename}` - Asset delivery with transformations

#### 2. Management API (`/api/v1/spaces/{space_id}/`)

**Purpose**: Authenticated admin operations for content management

```
┌─────────────────────────────────────┐
│         Management API              │
├─────────────────────────────────────┤
│ • JWT authentication required      │
│ • Space-scoped access              │
│ • Rate limit: 120 req/min          │
│ • Full CRUD operations             │
│ • Content validation               │
│ • Advanced content features        │
└─────────────────────────────────────┘
```

**Core Endpoints:**
- Stories: Create, read, update, delete, publish
- Components: Schema management and validation
- Assets: Upload, transform, organize
- Users: Invite, permissions, roles

**Advanced Features:**
- Content Locking: Prevent concurrent editing conflicts
- Content Templates: Reusable story structures
- Advanced Search: Full-text with component filtering
- Translation Management: Smart sync and completion tracking
- Version Management: Complete version history with restore

#### 3. Authentication API (`/api/v1/auth/`)

**Purpose**: User authentication and token management

```
┌─────────────────────────────────────┐
│        Authentication API           │
├─────────────────────────────────────┤
│ • JWT token generation             │
│ • User registration/login          │
│ • Rate limit: 10 req/min           │
│ • Password management              │
│ • Multi-space access               │
└─────────────────────────────────────┘
```

### Middleware Stack

```php
┌─────────────────────┐
│  Request Logging    │ ← API monitoring and debugging
├─────────────────────┤
│  Rate Limiting      │ ← Tiered rate limits by API type
├─────────────────────┤
│  CORS Handling      │ ← Cross-origin request management
├─────────────────────┤
│  Tenant Isolation  │ ← Space-based data scoping
├─────────────────────┤
│  Authentication     │ ← JWT token validation
├─────────────────────┤
│  Authorization      │ ← Permission checking
└─────────────────────┘
```

## Database Architecture

### Core Tables

```sql
-- Multi-tenant isolation
spaces (
    id, uuid, name, slug, settings, environments, 
    languages, plan, status, trial_ends_at, 
    story_limit, asset_limit, api_limit, ...
)

-- User management with multi-tenant support  
users (
    id, uuid, name, email, preferences, metadata, 
    status, timezone, language, two_factor_secret, ...
)

roles (
    id, name, slug, permissions, is_system_role, 
    priority, description, created_at, ...
)

space_user (
    space_id, user_id, role_id, custom_permissions,
    invitation_token, invitation_status, joined_at, ...
)

-- Content management (Storyblok-style)
components (
    id, space_id, uuid, name, internal_name, schema,
    preview_field, preview_tmpl, is_root, is_nestable,
    icon, color, tabs, status, version, ...
)

stories (
    id, space_id, uuid, name, slug, content, parent_id,
    language, translation_group_id, status, position,
    published_at, scheduled_at, meta_title, meta_description,
    locked_by, locked_at, lock_expires_at, lock_session_id, ...
)

-- Version management
story_versions (
    id, story_id, version_number, content_snapshot,
    reason, created_by, created_at, meta_snapshot, ...
)

-- Asset management
assets (
    id, space_id, uuid, filename, original_filename,
    content_type, file_size, file_path, file_hash,
    title, alt, metadata, variants, uploaded_by, ...
)

-- External data integration
datasources (
    id, space_id, uuid, name, slug, type, config,
    schema, auth_config, sync_frequency, last_sync, ...
)

datasource_entries (
    id, datasource_id, uuid, name, value, data,
    dimensions, computed_fields, status, ...
)
```

### JSONB Usage and Indexing

The system extensively uses PostgreSQL's JSONB columns with GIN indexes for optimal performance:

```sql
-- Component schemas with field definitions
CREATE INDEX idx_components_schema_gin ON components USING gin(schema);

-- Story content with component data
CREATE INDEX idx_stories_content_gin ON stories USING gin(content);

-- Space settings and configurations
CREATE INDEX idx_spaces_settings_gin ON spaces USING gin(settings);

-- User preferences and metadata
CREATE INDEX idx_users_preferences_gin ON users USING gin(preferences);

-- Asset metadata and variants
CREATE INDEX idx_assets_metadata_gin ON assets USING gin(metadata);

-- Datasource configurations
CREATE INDEX idx_datasources_config_gin ON datasources USING gin(config);

-- Entry dimensions for filtering
CREATE INDEX idx_entries_dimensions_gin ON datasource_entries USING gin(dimensions);
```

### Row Level Security (RLS) Ready

The database schema is designed to support PostgreSQL's Row Level Security for enhanced tenant isolation:

```sql
-- Enable RLS on tenant-scoped tables
ALTER TABLE stories ENABLE ROW LEVEL SECURITY;
ALTER TABLE components ENABLE ROW LEVEL SECURITY;
ALTER TABLE assets ENABLE ROW LEVEL SECURITY;

-- Create policies for space-based access
CREATE POLICY space_isolation_stories ON stories
FOR ALL TO application_role
USING (space_id = current_setting('app.current_space_id')::uuid);

CREATE POLICY space_isolation_components ON components
FOR ALL TO application_role
USING (space_id = current_setting('app.current_space_id')::uuid);
```

## Multi-Tenant Design

### Space-Based Isolation

Every piece of content is scoped to a specific space (tenant):

```php
// Automatic space scoping in models
class Story extends Model
{
    use MultiTenant;
    
    protected static function booted(): void
    {
        static::addGlobalScope(new SpaceScope);
    }
}

// All queries automatically include space_id
Story::all(); // SELECT * FROM stories WHERE space_id = ?
```

### Space Resolution

The system supports multiple methods for determining the current space context:

```php
class TenantIsolation
{
    private function resolveSpaceIdentifier(Request $request): ?string
    {
        // 1. Route parameter (Management API)
        if ($request->route('space_id')) {
            return $request->route('space_id');
        }
        
        // 2. Subdomain (CDN API)
        $host = $request->getHost();
        if (str_contains($host, '.')) {
            return explode('.', $host)[0];
        }
        
        // 3. Header-based
        return $request->header('X-Space-ID');
    }
}
```

### Resource Limits

Each space has configurable resource limits:

```php
class Space extends Model
{
    public function checkResourceLimit(string $resource, int $current): bool
    {
        $limit = $this->getResourceLimit($resource);
        return $current < $limit;
    }
    
    public function getResourceLimit(string $resource, int $default = 0): int
    {
        return match($resource) {
            'story_limit' => $this->story_limit,
            'asset_limit' => $this->asset_limit,
            'api_limit' => $this->api_limit,
            default => $default
        };
    }
}
```

## Security Model

### JWT Authentication

```php
class JwtService
{
    public function generateToken(User $user, bool $remember = false): string
    {
        $payload = [
            'sub' => $user->id,
            'iss' => config('app.url'),
            'aud' => config('app.url'),
            'iat' => time(),
            'exp' => time() + ($remember ? 20160 : 60) * 60, // 2 weeks or 1 hour
            'spaces' => $user->spaces->pluck('uuid')->toArray()
        ];
        
        return $this->encodeToken($payload);
    }
}
```

### Permission System

```php
class Role extends Model
{
    protected $casts = [
        'permissions' => Json::class
    ];
    
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        
        // Check exact permission
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // Check wildcard permissions
        foreach ($permissions as $perm) {
            if (str_ends_with($perm, '*') && 
                str_starts_with($permission, rtrim($perm, '*'))) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Rate Limiting Strategy

```php
class ApiRateLimit
{
    private function getLimit(string $limitType): int
    {
        return match ($limitType) {
            'cdn' => 60,        // Public content access
            'management' => 120, // Authenticated operations
            'auth' => 10,       // Security-sensitive operations
            default => 60
        };
    }
}
```

## Performance Strategy

### Caching Layers

```
┌─────────────────────┐
│   Application Cache │ ← Model-level caching with TTL
├─────────────────────┤
│   Query Cache       │ ← Database query result caching
├─────────────────────┤
│   Session Cache     │ ← User session and JWT storage
├─────────────────────┤
│   Rate Limit Cache  │ ← Request counting and throttling
└─────────────────────┘
```

### Model Caching

```php
trait Cacheable
{
    public static function cached(string $key, \Closure $callback, int $ttl = 3600): mixed
    {
        return Cache::remember(
            static::getCacheKey($key),
            $ttl,
            $callback
        );
    }
    
    public function invalidateCache(): void
    {
        $pattern = static::getCacheKey('*');
        Cache::tags([static::class])->flush();
    }
}
```

### Query Optimization

```php
// Eager loading relationships
$stories = Story::with(['space', 'creator', 'translations'])
    ->published()
    ->orderBy('published_at', 'desc')
    ->get();

// JSONB queries with indexes
$components = Component::whereJsonContains('schema->title->required', true)
    ->where('space_id', $spaceId)
    ->get();

// Pagination with counting optimization
$stories = Story::simplePaginate(25); // Skip total count for performance
```

## Component System

### Schema-Based Validation

```php
class Component extends Model
{
    public function validateData(array $data): array
    {
        $errors = [];
        
        foreach ($this->schema as $fieldName => $fieldConfig) {
            $value = $data[$fieldName] ?? null;
            $isRequired = $fieldConfig['required'] ?? false;
            
            // Check required fields
            if ($isRequired && ($value === null || $value === '')) {
                $errors[$fieldName] = "Field '{$fieldName}' is required";
                continue;
            }
            
            // Skip validation if field is not required and empty
            if (!$isRequired && ($value === null || $value === '')) {
                continue;
            }
            
            // Validate based on field type
            $error = $this->validateFieldByType($value, $fieldConfig['type'], $fieldConfig);
            if ($error) {
                $errors[$fieldName] = $error;
            }
        }
        
        return $errors;
    }
}
```

### Advanced Field Validation

```php
private function validateFieldByType(mixed $value, string $fieldType, array $fieldConfig): ?string
{
    return match ($fieldType) {
        'text', 'textarea', 'markdown', 'richtext' => $this->validateString($value, $fieldConfig),
        'number' => $this->validateNumber($value, $fieldConfig),
        'boolean' => $this->validateBoolean($value),
        'email' => $this->validateEmail($value),
        'url' => $this->validateUrl($value),
        'date', 'datetime' => $this->validateDate($value),
        'select' => $this->validateSelect($value, $fieldConfig),
        'asset' => $this->validateAsset($value),
        'blocks' => $this->validateBlocks($value),
        'component' => $this->validateComponent($value),
        default => null,
    };
}

protected function validateString(mixed $value, array $field): ?string
{
    if (!is_string($value)) {
        return 'Value must be a string';
    }

    if (isset($field['min_length']) && strlen($value) < $field['min_length']) {
        return "Value must be at least {$field['min_length']} characters";
    }

    if (isset($field['max_length']) && strlen($value) > $field['max_length']) {
        return "Value must not exceed {$field['max_length']} characters";
    }

    return null;
}
```

## Service Architecture

### Core Services

```php
// Content Management Services
StoryService::class          // Advanced story operations and search
VersionManager::class        // Version history and restoration
ContentRenderer::class       // Content processing and rendering
SlugGenerator::class         // URL-friendly slug generation

// Infrastructure Services  
JwtService::class           // JWT token management
BaseService::class          // Common service functionality
BaseRepository::class       // Data access abstraction
```

### StoryService - Advanced Content Operations

```php
class StoryService extends BaseService
{
    // Advanced search with multiple modes
    public function getPaginatedStories(Space $space, array $filters = []): LengthAwarePaginator
    
    // Search suggestions for auto-complete
    public function getSearchSuggestions(Space $space, string $query, int $limit = 10): array
    
    // Search analytics and statistics
    public function getSearchStats(Space $space): array
    
    // Content validation with detailed errors
    protected function validateStoryContent(array $content): array
}
```

### VersionManager - Content History

```php
class VersionManager
{
    // Create version with automatic change detection
    public function createVersion(Story $story, User $user, string $reason = null): StoryVersion
    
    // Compare two versions with detailed diff
    public function compareVersions(Story $story, int $version1Id, int $version2Id): array
    
    // Restore story from specific version
    public function restoreFromVersion(Story $story, int $versionId, User $user, string $reason = null): bool
    
    // Get version statistics
    public function getVersionStats(Story $story): array
}
```

## Data Flow

### Enhanced Content Creation Flow

```
1. Component Definition
   ├─ Admin creates component schema
   ├─ Advanced schema validation (20+ field types)
   ├─ Version tracking for schema changes
   └─ Cache invalidation

2. Story Creation with Locking
   ├─ Content locking check (prevent conflicts)
   ├─ Content validation against schema
   ├─ Multi-tenant scoping
   ├─ Slug uniqueness check
   ├─ Version creation (automatic)
   └─ Draft storage

3. Template System
   ├─ Template creation from existing stories
   ├─ Template storage and categorization
   ├─ Template instantiation with customization
   └─ Template-based content generation

4. Publishing Flow
   ├─ Final content validation
   ├─ Lock verification and release
   ├─ Status change to 'published'
   ├─ Version snapshot creation
   ├─ Search index update
   └─ CDN cache warming

5. Translation Workflow
   ├─ Translation creation with structure sync
   ├─ Completion percentage calculation
   ├─ Change detection and sync alerts
   └─ Translation status tracking

6. Content Delivery
   ├─ CDN API request
   ├─ Multi-layer cache check
   ├─ Database query (if cache miss)
   ├─ Content rendering and transformation
   └─ Response with optimized assets
```

### Asset Processing Flow

```
1. Upload
   ├─ File validation (type, size)
   ├─ Virus scanning (if configured)
   ├─ Storage (local/S3)
   └─ Metadata extraction

2. Processing
   ├─ Image optimization
   ├─ Variant generation
   ├─ CDN distribution
   └─ Database record creation

3. Delivery
   ├─ Transformation parameters
   ├─ Cache lookup
   ├─ On-demand processing
   └─ Optimized delivery
```

### Enhanced Multi-Tenant Request Flow

```
1. Request Routing
   ├─ Space identification (subdomain/parameter/header)
   ├─ Space validation and status check
   ├─ Resource limit verification
   └─ Context setting

2. Authentication (if required)
   ├─ JWT token validation
   ├─ User space access verification
   ├─ Role-based permission checking
   └─ Session management

3. Content Locking (for editing operations)
   ├─ Lock status verification
   ├─ Conflict detection
   ├─ Lock acquisition/extension
   └─ Session-based lock management

4. Data Access with Advanced Features
   ├─ Automatic space scoping
   ├─ Advanced search processing
   ├─ Content validation
   ├─ Version management
   └─ Result filtering and transformation

5. Response
   ├─ Resource transformation
   ├─ Translation metadata
   ├─ Lock information
   ├─ Rate limit headers
   └─ Comprehensive logging and monitoring
```

### Search and Analytics Flow

```
1. Search Request Processing
   ├─ Query parsing and sanitization
   ├─ Search mode determination (exact, fulltext, comprehensive)
   ├─ Component and tag filtering
   └─ Permission-based result filtering

2. Search Execution
   ├─ Database full-text search (PostgreSQL)
   ├─ JSONB content indexing
   ├─ Relevance scoring
   └─ Result ranking and pagination

3. Search Analytics
   ├─ Query statistics collection
   ├─ Popular component tracking
   ├─ Search suggestion generation
   └─ Performance metrics

4. Response Enhancement
   ├─ Result highlighting
   ├─ Suggestion recommendations
   ├─ Search statistics
   └─ Cached response optimization
```

---

For implementation details, see the [Development Guide](DEVELOPMENT.md) and [API Documentation](API.md).