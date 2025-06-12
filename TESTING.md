# Story Management System - Testing Guide

This document provides comprehensive information about the test suite for the Story Management System features implemented in the headless CMS.

## Test Suite Overview

The Story Management System includes 200+ individual tests covering all major features:

### 🧪 Unit Tests (6 Test Classes)

#### Component Validation Tests (`ComponentValidationTest`)
- **30+ tests** covering field type validation
- Tests: text, email, number, boolean, URL, date, select, multiselect
- Validation error handling and edge cases
- Schema validation with nested structures

#### Story Locking Tests (`StoryLockingTest`) 
- **25+ tests** covering concurrent editing prevention
- Lock acquisition, expiration, extension
- Session-based lock management
- Multi-user lock conflict resolution

#### Content Templates Tests (`ContentTemplatesTest`)
- **20+ tests** covering template system
- Template creation from existing stories
- Template instantiation with customization
- Complex nested component preservation

#### Translation Workflow Tests (`TranslationWorkflowTest`)
- **25+ tests** covering translation management
- Translation creation and linking
- Completion percentage calculation
- Content sync and structure matching

#### Story Service Tests (`StoryServiceTest`)
- **20+ tests** covering advanced search and operations
- Multiple search modes (exact, fulltext, comprehensive)
- Component filtering and search suggestions
- Content validation and analytics

#### Version Manager Tests (`VersionManagerTest`)
- **20+ tests** covering version history
- Version creation and comparison
- Content restoration from versions
- Version statistics and analytics

### 🌐 Feature Tests (2 Test Classes)

#### Story Management API Tests (`StoryManagementTest`)
- **40+ tests** covering all management endpoints
- CRUD operations with validation
- Content locking API endpoints
- Template management API
- Translation workflow API
- Advanced search API
- Bulk operations

#### CDN API Tests (`ContentDeliveryTest`)
- **20+ tests** covering public content delivery
- Published content access
- Filtering and pagination
- Rate limiting and security

## Quick Start

### Run All Story Management Tests

```bash
# Run the comprehensive test script
php run-story-management-tests.php

# Run using PHPUnit groups
./vendor/bin/phpunit --group=story-management

# Run with detailed output
./vendor/bin/phpunit --group=story-management --testdox
```

### Run Individual Test Suites

```bash
# Component validation
./vendor/bin/phpunit tests/Unit/Models/ComponentValidationTest.php

# Story locking
./vendor/bin/phpunit tests/Unit/Models/StoryLockingTest.php

# Content templates
./vendor/bin/phpunit tests/Unit/Models/ContentTemplatesTest.php

# Translation workflow
./vendor/bin/phpunit tests/Unit/Models/TranslationWorkflowTest.php

# Story service
./vendor/bin/phpunit tests/Unit/Services/StoryServiceTest.php

# Version management
./vendor/bin/phpunit tests/Unit/Services/VersionManagerTest.php

# API endpoints
./vendor/bin/phpunit tests/Feature/Api/Management/StoryManagementTest.php
./vendor/bin/phpunit tests/Feature/Api/Cdn/ContentDeliveryTest.php
```

### Run with Coverage

```bash
# Generate HTML coverage report
./vendor/bin/phpunit --group=story-management --coverage-html coverage

# View coverage report
open coverage/index.html
```

## Test Categories

### 🔧 Core Functionality Tests

#### Component Schema Validation
- ✅ Required field validation
- ✅ Field type validation (text, email, number, boolean, URL, date)
- ✅ Length constraints and boundaries
- ✅ Select/multiselect option validation
- ✅ Nested component validation
- ✅ Error message formatting

#### Content Locking System
- ✅ Lock acquisition and release
- ✅ Concurrent editing prevention
- ✅ Session-based lock management
- ✅ Lock expiration and cleanup
- ✅ Lock extension functionality
- ✅ Multi-user conflict resolution

#### Content Templates
- ✅ Template creation from stories
- ✅ Template instantiation with overrides
- ✅ Structure preservation
- ✅ Metadata handling
- ✅ Database and config templates

#### Translation Workflow
- ✅ Translation creation and linking
- ✅ Bidirectional relationships
- ✅ Content structure synchronization
- ✅ Completion percentage calculation
- ✅ Change detection and sync alerts
- ✅ Multi-language support

### 🔍 Advanced Search Tests

#### Search Modes
- ✅ Exact match search
- ✅ Full-text search
- ✅ Content-only search
- ✅ Metadata-only search
- ✅ Comprehensive search

#### Search Features
- ✅ Component filtering
- ✅ Tag filtering
- ✅ Language filtering
- ✅ Status filtering
- ✅ Date range filtering
- ✅ Search suggestions
- ✅ Search analytics

### 📚 Version Management Tests

#### Version Operations
- ✅ Version creation with snapshots
- ✅ Version comparison and diff
- ✅ Content restoration
- ✅ Version statistics
- ✅ Automatic version numbering

#### Content Preservation
- ✅ Content snapshot integrity
- ✅ Metadata preservation
- ✅ Nested structure handling
- ✅ Change detection

### 🌐 API Endpoint Tests

#### Management API
- ✅ Story CRUD operations
- ✅ Content validation
- ✅ Authentication and authorization
- ✅ Content locking endpoints
- ✅ Template management endpoints
- ✅ Translation endpoints
- ✅ Search endpoints
- ✅ Bulk operations

#### CDN API
- ✅ Public content delivery
- ✅ Published content filtering
- ✅ Pagination and sorting
- ✅ Rate limiting
- ✅ Error handling

## Test Data and Factories

The test suite uses Laravel factories to create realistic test data:

### Factory Examples

```php
// Story with complex content
$story = Story::factory()->for($space)->create([
    'content' => [
        'body' => [
            [
                '_uid' => Str::uuid(),
                'component' => 'hero',
                'title' => 'Hero Title',
                'nested_components' => [...]
            ]
        ]
    ]
]);

// Story with lock
$lockedStory = Story::factory()->for($space)->withLock($user)->create();

// Template story
$template = Story::factory()->for($space)->asTemplate()->create();

// Component with validation schema
$component = Component::factory()->for($space)->create([
    'schema' => [
        'title' => ['type' => 'text', 'required' => true],
        'email' => ['type' => 'email', 'required' => false]
    ]
]);
```

## Test Environment Setup

### Prerequisites

```bash
# Install dependencies
composer install

# Set up test database
php artisan migrate --database=testing

# Run database verification
php verify-database.php
```

### Environment Configuration

```env
# Test database
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Or for persistent test database
DB_CONNECTION=sqlite
DB_DATABASE=database/testing.sqlite

# Test-specific settings
APP_ENV=testing
LOG_LEVEL=emergency
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Story Management Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: sqlite3, pdo_sqlite
        
    - name: Install dependencies
      run: composer install
      
    - name: Run Story Management Tests
      run: ./vendor/bin/phpunit --group=story-management
      
    - name: Run Test Coverage
      run: ./vendor/bin/phpunit --group=story-management --coverage-clover coverage.xml
```

## Performance Testing

### Test Execution Times

The test suite is optimized for speed:

- Unit tests: ~30 seconds
- Feature tests: ~60 seconds
- Full suite: ~90 seconds

### Parallel Testing

```bash
# Install parallel testing
composer require --dev brianium/paratest

# Run tests in parallel
./vendor/bin/paratest --group=story-management --processes=4
```

## Debugging Tests

### Common Issues and Solutions

#### Database Issues
```bash
# Clear test database
php artisan migrate:fresh --database=testing

# Verify database structure
php verify-database.php
```

#### Factory Issues
```bash
# Debug factory creation
./vendor/bin/phpunit --debug tests/Unit/Models/ComponentValidationTest.php
```

#### Mock Issues
```bash
# Clear application cache
php artisan cache:clear
php artisan config:clear
```

### Debugging Tools

```php
// In tests, use these for debugging
dump($variable);           // Dump variable without stopping
dd($variable);            // Dump and die
$this->assertEmpty($errors, json_encode($errors)); // Show validation errors
```

## Coverage Goals

### Current Coverage Targets

- **Unit Tests**: 95%+ line coverage
- **Feature Tests**: 90%+ endpoint coverage
- **Integration**: All major workflows covered

### Coverage Reports

```bash
# Generate detailed coverage
./vendor/bin/phpunit --group=story-management --coverage-html coverage --coverage-filter app/

# View coverage
open coverage/index.html
```

## Contributing to Tests

### Adding New Tests

1. **Create test file** in appropriate directory
2. **Follow naming conventions**: `FeatureNameTest.php`
3. **Add test groups**: `@group feature-name`
4. **Use factories** for test data
5. **Document complex tests** with comments

### Test Best Practices

- ✅ One assertion per test method
- ✅ Descriptive test method names
- ✅ Use factories for consistent data
- ✅ Clean up after tests (RefreshDatabase)
- ✅ Test both success and failure cases
- ✅ Include edge cases and boundary conditions

### Example Test Structure

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group feature-name
 * @group story-management
 */
class FeatureTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Setup test data
    }
    
    public function test_specific_behavior_works_correctly(): void
    {
        // Arrange
        $data = $this->createTestData();
        
        // Act
        $result = $this->performAction($data);
        
        // Assert
        $this->assertTrue($result);
    }
}
```

## Test Results Interpretation

### Success Indicators
- ✅ All tests pass
- ✅ Coverage above thresholds
- ✅ No memory leaks
- ✅ Execution time within limits

### Failure Analysis
- ❌ Review error messages
- ❌ Check test data setup
- ❌ Verify database state
- ❌ Confirm environment configuration

## Maintenance

### Regular Maintenance Tasks

1. **Update test data** when schemas change
2. **Add tests** for new features
3. **Review coverage** regularly
4. **Update documentation** when needed
5. **Optimize slow tests** periodically

### Test Suite Health Monitoring

```bash
# Run daily health check
php run-story-management-tests.php

# Monitor test execution time
./vendor/bin/phpunit --group=story-management --log-junit results.xml
```

---

This comprehensive test suite ensures the Story Management System is robust, reliable, and maintainable. For questions or issues, refer to the test files or run the test script for detailed diagnostics.