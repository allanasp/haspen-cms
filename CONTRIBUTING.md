# Contributing to Headless CMS

Thank you for your interest in contributing to this open source headless CMS! This document provides guidelines and information for contributors.

## 🚀 Getting Started

### Prerequisites

- PHP 8.3 or higher
- PostgreSQL 16+
- Redis 7+
- Composer 2.x
- Docker & Docker Compose (for development)

### Development Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd headless-cms
   ```

2. **Start the development environment**
   ```bash
   docker compose up -d
   ```

3. **Install dependencies**
   ```bash
   docker compose exec app composer install
   ```

4. **Setup environment**
   ```bash
   docker compose exec app cp .env.example .env
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

## 📐 Architecture Overview

This project follows modern Laravel best practices with a focus on:

### 🏗️ **Multi-Tenant Architecture**
- **Spaces**: Each space is a separate tenant with isolated data
- **Automatic Scoping**: Models automatically scope to the current space
- **Role-Based Access**: Users have different roles in different spaces

### 📊 **Model Structure**
- **Traits**: Reusable functionality (HasUuid, MultiTenant, Sluggable, Cacheable)
- **Custom Casts**: JSON validation and transformation
- **Relationships**: Comprehensive model relationships with proper eager loading
- **Scopes**: Common query patterns as reusable scopes

### 🎨 **Storyblok-Style Content**
- **Components**: Define reusable content blocks with schemas
- **Stories**: Hierarchical content using components
- **Validation**: Real-time content validation against component schemas

## 🛠️ Development Guidelines

### Code Style

We follow PSR-12 coding standards with some Laravel-specific conventions:

```bash
# Format code automatically
./vendor/bin/php-cs-fixer fix

# Run static analysis
./vendor/bin/phpstan analyse

# Run psalm analysis
./vendor/bin/psalm
```

### Model Development

When creating or modifying models, follow these patterns:

#### 1. Use Appropriate Traits
```php
class MyModel extends Model
{
    use HasFactory, HasUuid, MultiTenant, Sluggable, Cacheable;
    
    // Trait configuration
    protected string $slugSourceField = 'name';
    protected bool $autoUpdateSlug = false;
    protected int $cacheTtl = 3600;
}
```

#### 2. Comprehensive Documentation
```php
/**
 * MyModel Model
 *
 * Brief description of what this model represents.
 * Explain the business logic and relationships.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * // ... document all properties
 */
class MyModel extends Model
{
    // Implementation
}
```

#### 3. Type Hints and Return Types
```php
public function getRelatedItems(): Collection
{
    return $this->getCached('related_items', function () {
        return $this->relatedItems()->get();
    });
}
```

### Component Schema Development

When working with component schemas, follow these patterns:

```php
// Component schema validation
$schema = [
    [
        'key' => 'title',
        'type' => 'text',
        'required' => true,
        'max_length' => 255,
    ],
    [
        'key' => 'content',
        'type' => 'textarea',
        'required' => false,
    ],
    // More fields...
];

// Validate data against schema
$errors = $component->validateData($contentData);
```

### Database Migrations

When creating migrations:

1. **Add Comments**: Explain the purpose of each table and column
2. **Use Indexes**: Add appropriate indexes for performance
3. **GIN Indexes**: Use GIN indexes for JSONB columns

```php
Schema::create('my_table', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique()->comment('Public UUID for API exposure');
    $table->string('name')->comment('Human-readable name');
    $table->jsonb('metadata')->nullable()->comment('Additional metadata');
    
    // Indexes
    $table->index('name');
});

// GIN index for JSONB
DB::statement('CREATE INDEX my_table_metadata_gin_idx ON my_table USING GIN (metadata)');
```

## 🧪 Testing

### Writing Tests

We use PHPUnit for testing. Test structure:

```php
<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    public function test_example_functionality(): void
    {
        // Arrange
        $space = Space::factory()->create();
        $user = User::factory()->create();
        
        // Act
        $response = $this->actingAs($user)
            ->postJson("/api/spaces/{$space->uuid}/stories", [
                'name' => 'Test Story',
            ]);
        
        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('stories', [
            'name' => 'Test Story',
            'space_id' => $space->id,
        ]);
    }
}
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

## 📝 Contribution Process

### 1. Issue First

For significant changes, please create an issue first to discuss:
- New features
- Breaking changes
- Major refactoring

### 2. Fork and Branch

```bash
# Fork the repository on GitHub
git clone https://github.com/your-username/headless-cms.git
cd headless-cms

# Create a feature branch
git checkout -b feature/amazing-feature
```

### 3. Make Changes

- Follow the coding standards
- Write tests for new functionality
- Update documentation if needed
- Ensure all tests pass

### 4. Commit Guidelines

Use conventional commits:

```bash
# Feature
git commit -m "feat: add user role management API"

# Bug fix
git commit -m "fix: resolve component validation issue"

# Documentation
git commit -m "docs: update API documentation"

# Refactor
git commit -m "refactor: improve query performance"
```

### 5. Pull Request

1. Push your branch to your fork
2. Create a pull request against the main branch
3. Provide a clear description of changes
4. Link any related issues

## 🐛 Bug Reports

When reporting bugs, please include:

1. **Environment**: PHP version, OS, browser (if applicable)
2. **Steps to Reproduce**: Clear, numbered steps
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Error Messages**: Any error messages or logs
6. **Code Samples**: Minimal code that reproduces the issue

## 💡 Feature Requests

For feature requests, please provide:

1. **Use Case**: Why is this feature needed?
2. **Proposed Solution**: How should it work?
3. **Alternatives**: Any alternative solutions considered?
4. **Examples**: Similar features in other tools?

## 📚 Key Files to Understand

### Models
- `app/Models/Space.php` - Multi-tenant spaces
- `app/Models/User.php` - User management with roles
- `app/Models/Story.php` - Content management
- `app/Models/Component.php` - Content block definitions
- `app/Models/Role.php` - Permission management

### Traits
- `app/Traits/HasUuid.php` - UUID functionality
- `app/Traits/MultiTenant.php` - Multi-tenant scoping
- `app/Traits/Sluggable.php` - URL-friendly slugs
- `app/Traits/Cacheable.php` - Model caching

### Casts
- `app/Casts/Json.php` - Enhanced JSON casting
- `app/Casts/ComponentSchema.php` - Component validation

## 🔧 Useful Commands

```bash
# Code formatting
./vendor/bin/php-cs-fixer fix

# Static analysis
./vendor/bin/phpstan analyse

# Clear all caches
php artisan optimize:clear

# Generate IDE helper files
php artisan ide-helper:generate
php artisan ide-helper:models

# Database operations
php artisan migrate:fresh --seed
php artisan tinker
```

## 📞 Getting Help

- **GitHub Issues**: For bug reports and feature requests
- **GitHub Discussions**: For questions and community discussion
- **Documentation**: Check the README and inline documentation

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

Thank you for contributing! 🎉