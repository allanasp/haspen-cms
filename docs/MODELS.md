# Model Documentation

This document provides comprehensive documentation for all Eloquent models in the headless CMS, including usage examples, relationships, and best practices.

## Table of Contents

- [Space Model](#space-model)
- [User Model](#user-model)
- [Story Model](#story-model)
- [Component Model](#component-model)
- [Model Relationships](#model-relationships)
- [Common Patterns](#common-patterns)

---

## Space Model

**Multi-Tenant Container**

Represents a tenant workspace in the multi-tenant headless CMS. Each space provides complete data isolation, custom configuration, and independent content management. Spaces are the top-level organizational unit that contains stories, components, assets, and users with role-based access control.

### Key Features:
- **Complete Data Isolation**: All content is automatically scoped to the space
- **Custom Environments**: Configure different environments (dev, staging, production)
- **Multi-Language Support**: Define supported languages with default language
- **Plan-Based Limits**: Enforce story, asset, and API limits based on subscription plan
- **Domain Mapping**: Optional custom domain configuration
- **Trial Management**: Built-in trial period tracking and expiration
- **Suspension Control**: Ability to suspend/reactivate spaces

### Usage Examples:

#### Creating a New Space
```php
$space = Space::create([
    'name' => 'My Blog',
    'slug' => 'my-blog',
    'domain' => 'blog.example.com',
    'description' => 'A personal blog about technology',
    'plan' => Space::PLAN_PRO,
    'default_language' => 'en',
    'languages' => ['en', 'fr', 'es'],
    'environments' => [
        'development' => ['base_url' => 'http://localhost:3000'],
        'staging' => ['base_url' => 'https://staging.example.com'],
        'production' => ['base_url' => 'https://example.com']
    ],
    'settings' => [
        'theme' => 'default',
        'cache_ttl' => 3600,
        'enable_comments' => true
    ]
]);
```

#### Managing Space Users and Roles
```php
// Add user to space with specific role
$user = User::find(1);
$editorRole = Role::where('slug', 'editor')->first();

$space->users()->attach($user->id, [
    'role_id' => $editorRole->id,
    'custom_permissions' => ['publish_stories' => true]
]);

// Check user permissions
if ($user->hasPermissionInSpace($space, 'create_stories')) {
    // User can create stories in this space
}
```

#### Working with Space Limits
```php
// Check if space can create more content
if (!$space->hasReachedStoryLimit()) {
    $story = $space->stories()->create([
        'name' => 'New Article',
        'content' => [...],
        'status' => 'draft'
    ]);
}

// Monitor usage
$usage = [
    'stories' => $space->getStoriesCount(),
    'assets' => $space->getAssetsCount(),
    'storage_mb' => $space->getCached('assets_total_size', fn() => 
        $space->assets()->sum('file_size') / 1024 / 1024
    )
];
```

#### Environment Configuration
```php
// Get environment-specific settings
$prodConfig = $space->getEnvironmentConfig('production');
$baseUrl = $prodConfig['base_url'] ?? 'https://default.com';

// Check language support
if ($space->supportsLanguage('fr')) {
    // Create French content
}
```

#### Trial and Subscription Management
```php
// Check trial status
if ($space->isOnTrial()) {
    $daysLeft = $space->trial_ends_at->diffInDays(now());
    // Show trial expiration warning
}

// Handle trial expiration
if ($space->isTrialExpired()) {
    $space->suspend();
}

// Upgrade plan
$space->update([
    'plan' => Space::PLAN_ENTERPRISE,
    'story_limit' => null, // Unlimited
    'asset_limit' => 50000, // 50GB
    'trial_ends_at' => null
]);
```

### Properties:
- `uuid`: Public UUID for API exposure
- `name`: Human-readable space name
- `slug`: URL-friendly identifier (unique)
- `domain`: Optional custom domain
- `settings`: Custom space configuration
- `environments`: Environment-specific settings (dev, staging, prod)
- `languages`: Supported language codes
- `plan`: Subscription plan (free, pro, enterprise)
- `status`: Current status (active, suspended, deleted)

---

## User Model

**Multi-Space User Management**

Represents a user account in the multi-tenant headless CMS. Users can participate in multiple spaces with different roles and permissions, maintaining their own preferences, metadata, and authentication state across the entire system.

### Key Features:
- **Multi-Space Membership**: Users can belong to multiple spaces simultaneously
- **Role-Based Access**: Different roles and permissions per space
- **Custom Permissions**: Override role permissions with custom settings per space
- **Personal Preferences**: User-specific UI and behavior settings
- **Metadata Storage**: Extensible key-value storage for custom user data
- **Avatar Management**: Support for custom avatars with Gravatar fallback
- **Activity Tracking**: Last login tracking with IP address logging
- **Global Admin**: System-wide administrative privileges

### Usage Examples:

#### Creating and Managing Users
```php
$user = User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'password' => bcrypt('secure-password'),
    'timezone' => 'America/New_York',
    'language' => 'en',
    'bio' => 'Content creator and digital marketer',
    'preferences' => [
        'theme' => 'dark',
        'notifications' => ['email' => true, 'push' => false],
        'editor_mode' => 'advanced'
    ],
    'metadata' => [
        'department' => 'Marketing',
        'hire_date' => '2024-01-15',
        'employee_id' => 'EMP001'
    ]
]);
```

#### Managing Space Memberships and Roles
```php
// Add user to spaces with specific roles
$blogSpace = Space::where('slug', 'company-blog')->first();
$websiteSpace = Space::where('slug', 'main-website')->first();
$editorRole = Role::where('slug', 'editor')->first();
$authorRole = Role::where('slug', 'author')->first();

// Full editor access to blog
$user->spaces()->attach($blogSpace->id, [
    'role_id' => $editorRole->id,
    'custom_permissions' => [
        'publish_stories' => true,
        'manage_assets' => true
    ]
]);

// Limited author access to website
$user->spaces()->attach($websiteSpace->id, [
    'role_id' => $authorRole->id,
    'custom_permissions' => [
        'publish_stories' => false // Override role permission
    ]
]);

// Check user access
if ($user->belongsToSpace($blogSpace)) {
    $role = $user->getRoleInSpace($blogSpace);
    $canPublish = $user->hasPermissionInSpace($blogSpace, 'publish_stories');
}
```

#### Working with User Preferences
```php
// Set individual preferences
$user->setPreference('editor_mode', 'visual');
$user->setPreference('auto_save', true);

// Get preferences with defaults
$theme = $user->getPreference('theme', 'light');
$notifications = $user->getPreference('notifications', [
    'email' => true,
    'push' => false,
    'browser' => true
]);

// Bulk update preferences
$user->update([
    'preferences' => array_merge($user->preferences ?? [], [
        'dashboard_widgets' => ['stats', 'recent_stories', 'activity'],
        'timezone' => 'Europe/London'
    ])
]);
```

#### Managing User Metadata
```php
// Store custom business data
$user->setMetadata('last_training_date', '2024-03-15');
$user->setMetadata('certification_level', 'advanced');
$user->setMetadata('api_access_level', 'premium');

// Retrieve metadata
$certLevel = $user->getMetadata('certification_level', 'basic');
$hasAdvancedCert = $user->getMetadata('certification_level') === 'advanced';

// Use for business logic
if ($user->getMetadata('api_access_level') === 'premium') {
    // Grant premium API features
}
```

#### Avatar and Profile Management
```php
// Get avatar URL (custom or Gravatar fallback)
$avatarUrl = $user->getAvatarUrl();

// Update profile information
$user->update([
    'avatar_url' => 'https://cdn.example.com/avatars/jane-doe.jpg',
    'bio' => 'Senior Content Strategist with 5+ years experience',
    'timezone' => 'Pacific/Auckland'
]);

// Display name logic
$displayName = $user->getDisplayName(); // Uses name or falls back to email
```

#### Authentication and Activity Tracking
```php
// Update login information
$user->updateLastLogin($request->ip());

// Check user status
if (!$user->isActive()) {
    throw new AuthenticationException('Account is not active');
}

// Admin privilege checking
if ($user->isAdmin()) {
    // Grant system-wide access
}
```

### Properties:
- `uuid`: Public UUID for API exposure
- `name`: User's full name
- `email`: Unique email address
- `is_admin`: System-wide administrator flag
- `timezone`: User's timezone (default: UTC)
- `language`: Preferred language code (default: en)
- `status`: Account status (active, inactive, suspended)
- `preferences`: User interface and behavior preferences
- `metadata`: Custom business data storage

---

## Story Model

**Component-Based Content Management**

Represents content pages, posts, and folders in the headless CMS. Stories use a Storyblok-inspired component-based architecture where content is structured as reusable components with validated schemas. Supports hierarchical organization, multi-language content, publishing workflows, and comprehensive SEO management.

### Key Features:
- **Component-Based Content**: Structured content using reusable component blocks
- **Hierarchical Organization**: Parent-child relationships for complex site structures
- **Multi-Language Support**: Translation management with language variations
- **Publishing Workflow**: Draft → Review → Published → Scheduled states
- **SEO Optimization**: Meta titles, descriptions, robots directives, canonical URLs
- **Access Control**: Role-based content access restrictions
- **URL Management**: Automatic slug generation and full path resolution
- **Folder Support**: Organize content into folders for better structure

### Usage Examples:

#### Creating Content with Components
```php
$story = Story::create([
    'name' => 'About Our Company',
    'slug' => 'about-us',
    'language' => 'en',
    'status' => Story::STATUS_DRAFT,
    'content' => [
        'component' => 'page',
        'body' => [
            [
                '_uid' => (string) Str::uuid(),
                'component' => 'hero_section',
                'title' => 'Welcome to Our Company',
                'subtitle' => 'Building the future together',
                'image' => [
                    'id' => 123,
                    'filename' => 'hero-image.jpg',
                    'alt' => 'Team working together'
                ]
            ],
            [
                '_uid' => (string) Str::uuid(),
                'component' => 'text_block',
                'content' => 'Our company was founded in 2020...',
                'alignment' => 'left'
            ],
            [
                '_uid' => (string) Str::uuid(),
                'component' => 'team_grid',
                'members' => [
                    ['name' => 'John Doe', 'role' => 'CEO'],
                    ['name' => 'Jane Smith', 'role' => 'CTO']
                ]
            ]
        ]
    ],
    'meta_title' => 'About Us - Company Name',
    'meta_description' => 'Learn about our mission, values, and team.',
    'robots_meta' => ['index' => true, 'follow' => true]
]);
```

#### Working with Hierarchical Content
```php
// Create a main section folder
$blogFolder = Story::create([
    'name' => 'Blog',
    'slug' => 'blog',
    'is_folder' => true,
    'language' => 'en',
    'status' => Story::STATUS_PUBLISHED
]);

// Create category folders
$techCategory = Story::create([
    'name' => 'Technology',
    'slug' => 'technology',
    'parent_id' => $blogFolder->id,
    'is_folder' => true,
    'language' => 'en',
    'status' => Story::STATUS_PUBLISHED
]);

// Create a blog post
$blogPost = Story::create([
    'name' => 'Introduction to Laravel 11',
    'slug' => 'intro-to-laravel-11',
    'parent_id' => $techCategory->id,
    'language' => 'en',
    'content' => [...],
    'status' => Story::STATUS_DRAFT
]);

// Automatic path generation: /blog/technology/intro-to-laravel-11
echo $blogPost->path; // Auto-generated from hierarchy
print_r($blogPost->breadcrumbs); // Auto-generated navigation
```

#### Publishing Workflow
```php
// Create draft content
$story = Story::create(['status' => Story::STATUS_DRAFT, ...]);

// Move to review
$story->update(['status' => Story::STATUS_REVIEW]);

// Publish immediately
$story->publish($user->id);

// Schedule for future publishing
$story->schedule(
    scheduledAt: now()->addDays(3),
    publishedBy: $user->id
);

// Check status
if ($story->isPublished()) {
    echo "Story is live at: " . $story->getUrl();
}

// Query published content
$liveStories = Story::published()->get();
$scheduledStories = Story::scheduled()->get();
```

#### Multi-Language Content Management
```php
// Create English content
$englishStory = Story::create([
    'name' => 'Privacy Policy',
    'slug' => 'privacy-policy',
    'language' => 'en',
    'content' => [...],
    'status' => Story::STATUS_PUBLISHED
]);

// Create French translation
$frenchStory = Story::create([
    'name' => 'Politique de Confidentialité',
    'slug' => 'politique-de-confidentialite',
    'language' => 'fr',
    'translated_story_id' => $englishStory->id,
    'content' => [...],
    'status' => Story::STATUS_PUBLISHED
]);

// Update source story with translation info
$englishStory->update([
    'translated_languages' => ['fr', 'es', 'de']
]);

// Check translations
if ($englishStory->hasTranslation('fr')) {
    $translations = $englishStory->translations;
}
```

#### Working with Components
```php
// Find specific component types in content
$heroComponents = $story->getComponentsByType('hero_section');
$textBlocks = $story->getComponentsByType('text_block');

// Get specific content values
$pageTitle = $story->getContentComponent('title');
$seoSettings = $story->getContentComponent('seo');

// Validate content against component schemas
foreach ($story->getComponentsByType('form') as $formComponent) {
    $component = Component::where('technical_name', 'form')->first();
    $errors = $component->validateData($formComponent);
    if (!empty($errors)) {
        // Handle validation errors
    }
}
```

#### SEO and URL Management
```php
// Get SEO meta data
$seoMeta = $story->getSeoMeta();
// Returns: ['title' => '...', 'description' => '...', 'robots' => [...], 'canonical' => '...']

// Generate URLs for different environments
$devUrl = $story->getUrl('development');
$stagingUrl = $story->getUrl('staging');
$prodUrl = $story->getUrl('production');

// Access control
if ($story->canBeAccessedBy($currentUser)) {
    // User has permission to view this content
}

// Update SEO settings
$story->update([
    'meta_title' => 'Custom Page Title',
    'meta_description' => 'Engaging description for search engines',
    'robots_meta' => [
        'index' => true,
        'follow' => true,
        'noarchive' => false
    ]
]);
```

### Properties:
- `uuid`: Public UUID for API exposure
- `name`: Story title/name
- `slug`: URL-friendly identifier (auto-generated)
- `content`: Component-based content structure
- `language`: Language code (ISO 639-1)
- `status`: Publishing status (draft, review, published, scheduled, archived)
- `is_folder`: Whether this story is a folder (container)
- `path`: Full URL path (auto-generated from hierarchy)
- `breadcrumbs`: Navigation breadcrumb data (auto-generated)

---

## Component Model

**Content Block Schema Definition**

Represents reusable content block definitions in the headless CMS. Components define the structure, validation rules, and UI behavior for content blocks that can be used within stories. This follows a Storyblok-inspired architecture where content is built using composable, validated components with rich field type support.

### Key Features:
- **Schema Definition**: Define field types, validation rules, and UI configuration
- **Rich Field Types**: 20+ field types including text, images, nested components
- **Real-Time Validation**: Validate content data against component schemas
- **Version Management**: Track component schema versions for backwards compatibility
- **Access Control**: Role-based component usage restrictions
- **Nestable Components**: Support for nested component structures
- **Preview Templates**: Custom preview text generation from field data
- **UI Configuration**: Icons, colors, tabs, and display options

### Usage Examples:

#### Creating a Basic Text Component
```php
$textComponent = Component::create([
    'name' => 'Text Block',
    'technical_name' => 'text_block',
    'description' => 'Simple text content with alignment options',
    'icon' => 'text',
    'color' => '#4A90E2',
    'is_nestable' => true,
    'is_root' => false,
    'schema' => [
        [
            'key' => 'content',
            'type' => 'textarea',
            'display_name' => 'Content',
            'required' => true,
            'max_length' => 5000,
            'description' => 'The main text content'
        ],
        [
            'key' => 'alignment',
            'type' => 'select',
            'display_name' => 'Text Alignment',
            'required' => false,
            'default_value' => 'left',
            'options' => [
                ['label' => 'Left', 'value' => 'left'],
                ['label' => 'Center', 'value' => 'center'],
                ['label' => 'Right', 'value' => 'right']
            ]
        ]
    ],
    'preview_field' => [
        'template' => '{content} ({alignment})',
        'fields' => ['content', 'alignment']
    ]
]);
```

#### Creating a Complex Hero Component
```php
$heroComponent = Component::create([
    'name' => 'Hero Section',
    'technical_name' => 'hero_section',
    'description' => 'Full-width hero section with image and call-to-action',
    'icon' => 'hero',
    'color' => '#FF6B6B',
    'is_nestable' => false,
    'is_root' => true,
    'schema' => [
        [
            'key' => 'title',
            'type' => 'text',
            'display_name' => 'Hero Title',
            'required' => true,
            'max_length' => 100,
            'description' => 'Main heading text'
        ],
        [
            'key' => 'subtitle',
            'type' => 'textarea',
            'display_name' => 'Subtitle',
            'required' => false,
            'max_length' => 250
        ],
        [
            'key' => 'background_image',
            'type' => 'image',
            'display_name' => 'Background Image',
            'required' => true,
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_file_size' => 5242880 // 5MB
        ],
        [
            'key' => 'cta_button',
            'type' => 'link',
            'display_name' => 'Call to Action',
            'required' => false
        ],
        [
            'key' => 'height',
            'type' => 'select',
            'display_name' => 'Section Height',
            'default_value' => 'medium',
            'options' => [
                ['label' => 'Small (400px)', 'value' => 'small'],
                ['label' => 'Medium (600px)', 'value' => 'medium'],
                ['label' => 'Large (800px)', 'value' => 'large'],
                ['label' => 'Full Screen', 'value' => 'fullscreen']
            ]
        ]
    ],
    'tabs' => [
        ['name' => 'Content', 'fields' => ['title', 'subtitle', 'cta_button']],
        ['name' => 'Design', 'fields' => ['background_image', 'height']]
    ],
    'preview_field' => [
        'template' => 'Hero: {title}',
        'fields' => ['title']
    ]
]);
```

#### Validating Component Data
```php
// Content data from a story
$contentData = [
    'title' => 'Welcome to Our Site',
    'subtitle' => 'We build amazing digital experiences',
    'background_image' => [
        'id' => 123,
        'filename' => 'hero-bg.jpg',
        'alt' => 'Hero background'
    ],
    'height' => 'large'
];

// Validate against component schema
$errors = $heroComponent->validateData($contentData);

if (empty($errors)) {
    echo "Content is valid!";
} else {
    foreach ($errors as $field => $error) {
        echo "Error in {$field}: {$error}\n";
    }
}

// Get preview text
$preview = $heroComponent->getPreview($contentData);
// Returns: "Hero: Welcome to Our Site"
```

#### Working with Component Fields
```php
// Get specific field configuration
$titleField = $heroComponent->getField('title');
$requiredFields = $heroComponent->getRequiredFields();
$imageFields = $heroComponent->getFieldsByType('image');

// Check field properties
foreach ($requiredFields as $field) {
    echo "Required field: {$field['key']} ({$field['type']})\n";
}

// Get all text-based fields
$textFields = array_merge(
    $heroComponent->getFieldsByType('text'),
    $heroComponent->getFieldsByType('textarea'),
    $heroComponent->getFieldsByType('richtext')
);
```

### Supported Field Types:
- **Text Fields**: text, textarea, markdown, richtext
- **Data Types**: number, boolean, date, datetime, json
- **Selection**: select, multiselect
- **Media**: image, file, asset
- **Links**: link, email, url, story
- **Advanced**: color, table, blocks, component (nested)

### Properties:
- `uuid`: Public UUID for API exposure
- `name`: Human-readable component name
- `technical_name`: Technical identifier used in content
- `schema`: Field definitions and validation rules
- `status`: Component status (active, inactive, deprecated)
- `is_nestable`: Whether component can be nested inside others
- `is_root`: Whether component can be used as root content
- `version`: Schema version number (incremented on changes)

---

## Model Relationships

### Space Relationships
- `users()`: Many-to-many with pivot containing role and permissions
- `stories()`: One-to-many, all content automatically scoped to space
- `components()`: One-to-many, reusable content block definitions
- `assets()`: One-to-many, media files and documents
- `datasources()`: One-to-many, external data integrations

### User Relationships
- `spaces()`: Many-to-many with pivot containing role_id and custom_permissions
- `roles()`: Many-to-many through space_user pivot table
- `created_stories()`: One-to-many stories where user is creator
- `updated_stories()`: One-to-many stories where user made last update
- `published_stories()`: One-to-many stories where user published content

### Story Relationships
- `space()`: Belongs to a space (multi-tenant isolation)
- `parent()`: Belongs to another story (hierarchical structure)
- `children()`: Has many child stories (ordered by sort_order)
- `translatedStory()`: Belongs to original story for translations
- `translations()`: Has many translated versions
- `creator()`: Belongs to user who created the story
- `updater()`: Belongs to user who made last update
- `publisher()`: Belongs to user who published the story

### Component Relationships
- `space()`: Belongs to a space (multi-tenant isolation)
- `creator()`: Belongs to user who created the component
- `updater()`: Belongs to user who last updated the component
- `stories()`: Many-to-many through content usage (implicit)

---

## Common Patterns

### Multi-Tenant Data Access
```php
// Current space is automatically set by middleware
$stories = Story::all(); // Automatically scoped to current space

// Override scoping for cross-space operations
$allStories = Story::withoutGlobalScope('space')->get();

// Explicit space scoping
$spaceStories = Story::forSpace($specificSpace)->get();
```

### Caching Patterns
```php
// Model-level caching
$count = $space->getCached('stories_count', function () {
    return $this->stories()->count();
}, 3600);

// Query result caching
$popularStories = Story::cacheQuery('popular_stories', 
    Story::published()->orderBy('views', 'desc')->limit(10), 
    1800
);
```

### Validation Patterns
```php
// Component schema validation
$errors = $component->validateData($contentData);

// Story content validation
foreach ($story->getComponentsByType('form') as $formData) {
    $component = Component::where('technical_name', 'form')->first();
    $errors = $component->validateData($formData);
}
```

### Permission Checking
```php
// Space-level permissions
if ($user->hasPermissionInSpace($space, 'create_stories')) {
    // User can create stories
}

// Content access control
if ($story->canBeAccessedBy($user)) {
    // User can view this story
}

// Component usage permissions
if ($component->canBeUsedBy($user)) {
    // User can use this component
}
```