# Custom Casts Documentation

This document provides comprehensive documentation for all custom Eloquent casts in the headless CMS, including usage examples, validation details, and implementation patterns.

## Table of Contents

- [Json Cast](#json-cast)
- [ComponentSchema Cast](#componentschema-cast)
- [Usage Patterns](#usage-patterns)
- [Best Practices](#best-practices)

---

## Json Cast

**Enhanced JSON Cast with Validation and Error Handling**

A robust JSON casting implementation that provides safe encoding/decoding of JSON data with comprehensive error handling and validation. This cast is used throughout the CMS for storing flexible data structures like user preferences, space settings, story metadata, and other dynamic JSON content in JSONB database columns.

### Key Features:
- **Safe JSON Encoding/Decoding**: Handles malformed JSON gracefully
- **Error Reporting**: Descriptive error messages with field context
- **Unicode Support**: Proper handling of international characters
- **Performance Optimized**: Efficient encoding with minimal overhead
- **Type Safety**: Maintains data type integrity during cast operations

### Usage Examples:

#### In Model Definitions
```php
class User extends Model
{
    protected $casts = [
        'preferences' => Json::class,
        'metadata' => Json::class,
    ];
}

class Space extends Model
{
    protected $casts = [
        'settings' => Json::class,
        'environments' => Json::class,
        'languages' => Json::class,
    ];
}
```

#### Working with JSON Data
```php
// Setting complex data structures
$user->preferences = [
    'theme' => 'dark',
    'notifications' => [
        'email' => true,
        'push' => false,
        'browser' => true
    ],
    'dashboard' => [
        'widgets' => ['stats', 'recent_activity', 'quick_actions'],
        'layout' => 'grid'
    ]
];

// Accessing nested data
$theme = $user->preferences['theme'];
$emailNotifications = $user->preferences['notifications']['email'];

// Modifying JSON data
$prefs = $user->preferences;
$prefs['theme'] = 'light';
$prefs['notifications']['push'] = true;
$user->preferences = $prefs;
$user->save();
```

#### Space Configuration Example
```php
$space->settings = [
    'cache_ttl' => 3600,
    'maintenance_mode' => false,
    'features' => [
        'comments' => true,
        'search' => true,
        'analytics' => false
    ],
    'integrations' => [
        'google_analytics' => ['tracking_id' => 'GA-XXXXX'],
        'stripe' => ['public_key' => 'pk_test_...']
    ]
];

// Environment-specific configurations
$space->environments = [
    'development' => [
        'base_url' => 'http://localhost:3000',
        'debug' => true,
        'cache_enabled' => false
    ],
    'staging' => [
        'base_url' => 'https://staging.example.com',
        'debug' => false,
        'cache_enabled' => true
    ],
    'production' => [
        'base_url' => 'https://example.com',
        'debug' => false,
        'cache_enabled' => true,
        'cdn_url' => 'https://cdn.example.com'
    ]
];
```

#### Error Handling
```php
try {
    // This will work fine
    $user->metadata = ['key' => 'value', 'number' => 123];
    $user->save();
} catch (\InvalidArgumentException $e) {
    // Handle JSON encoding errors
    echo "JSON Error: " . $e->getMessage();
}

// When reading from database with corrupted JSON
try {
    $preferences = $user->preferences;
} catch (\InvalidArgumentException $e) {
    // Handle malformed JSON in database
    \Log::error("Corrupted JSON in user preferences", [
        'user_id' => $user->id,
        'error' => $e->getMessage()
    ]);
    
    // Set default values
    $user->preferences = ['theme' => 'light'];
    $user->save();
}
```

### Technical Details:

#### Encoding Configuration
The cast uses specific JSON encoding flags for optimal storage:
- `JSON_UNESCAPED_UNICODE`: Preserves international characters
- `JSON_UNESCAPED_SLASHES`: Keeps URLs readable in database

#### Database Storage
Data is stored as JSONB in PostgreSQL, enabling:
- Fast queries using GIN indexes
- Efficient storage with compression
- Native JSON operations in SQL

#### Performance Considerations
- Automatic encoding/decoding happens on model access
- Large JSON structures should be paginated or cached
- Use specific field access rather than loading entire JSON when possible

### Common Use Cases
- **User Preferences**: UI settings, notification preferences, customizations
- **Space Settings**: Feature flags, integrations, configuration options
- **Story Metadata**: SEO data, custom fields, publishing information
- **Asset Processing**: Image variants, transformation parameters
- **Flexible Configuration**: Any dynamic key-value data storage

---

## ComponentSchema Cast

**Component Schema Cast with Comprehensive Validation**

A specialized JSON cast designed specifically for component schema validation and storage. This cast ensures that component schemas conform to the expected structure, validates field types, and maintains data integrity for the component-based content system. It provides real-time validation of component definitions to prevent invalid schemas from being stored in the database.

### Key Features:
- **Schema Structure Validation**: Ensures proper component schema format
- **Field Type Validation**: Validates against supported field types (20+ types)
- **Required Field Checking**: Validates that required schema properties are present
- **Type Safety**: Maintains strict typing for schema definitions
- **Error Reporting**: Detailed validation error messages for debugging
- **Performance Optimized**: Fast validation with minimal overhead

### Supported Field Types:

#### Text and Content Fields
- `text`: Single-line text input
- `textarea`: Multi-line text input
- `markdown`: Markdown editor with preview
- `richtext`: WYSIWYG rich text editor

#### Data Types
- `number`: Numeric input with validation
- `boolean`: True/false toggle
- `date`: Date picker
- `datetime`: Date and time picker
- `json`: Raw JSON data input

#### Selection Fields
- `select`: Single selection dropdown
- `multiselect`: Multiple selection field

#### Media and Assets
- `image`: Image upload and selection
- `file`: File upload and selection
- `asset`: Asset browser integration

#### Links and References
- `link`: URL or internal link builder
- `email`: Email address input
- `url`: URL input with validation
- `story`: Story reference picker

#### Advanced Fields
- `color`: Color picker
- `table`: Tabular data editor
- `blocks`: Nested component blocks

### Usage Examples:

#### Basic Component Schema
```php
class Component extends Model
{
    protected $casts = [
        'schema' => ComponentSchema::class,
    ];
}

// Creating a simple text component
$component = Component::create([
    'name' => 'Headline',
    'technical_name' => 'headline',
    'schema' => [
        [
            'key' => 'text',
            'type' => 'text',
            'display_name' => 'Headline Text',
            'required' => true,
            'max_length' => 100
        ],
        [
            'key' => 'level',
            'type' => 'select',
            'display_name' => 'Heading Level',
            'required' => false,
            'default_value' => 'h2',
            'options' => [
                ['label' => 'H1', 'value' => 'h1'],
                ['label' => 'H2', 'value' => 'h2'],
                ['label' => 'H3', 'value' => 'h3']
            ]
        ]
    ]
]);
```

#### Complex Form Component Schema
```php
$formComponent = Component::create([
    'name' => 'Contact Form',
    'technical_name' => 'contact_form',
    'schema' => [
        [
            'key' => 'title',
            'type' => 'text',
            'display_name' => 'Form Title',
            'required' => true,
            'max_length' => 50
        ],
        [
            'key' => 'fields',
            'type' => 'json',
            'display_name' => 'Form Fields Configuration',
            'required' => true,
            'description' => 'JSON array defining form fields'
        ],
        [
            'key' => 'submit_text',
            'type' => 'text',
            'display_name' => 'Submit Button Text',
            'required' => false,
            'default_value' => 'Send Message',
            'max_length' => 20
        ],
        [
            'key' => 'success_message',
            'type' => 'textarea',
            'display_name' => 'Success Message',
            'required' => true,
            'max_length' => 500
        ],
        [
            'key' => 'email_notification',
            'type' => 'boolean',
            'display_name' => 'Send Email Notification',
            'required' => false,
            'default_value' => true
        ]
    ]
]);
```

#### Media-Rich Component Schema
```php
$galleryComponent = Component::create([
    'name' => 'Image Gallery',
    'technical_name' => 'image_gallery',
    'schema' => [
        [
            'key' => 'title',
            'type' => 'text',
            'display_name' => 'Gallery Title',
            'required' => false,
            'max_length' => 100
        ],
        [
            'key' => 'images',
            'type' => 'json',
            'display_name' => 'Gallery Images',
            'required' => true,
            'description' => 'Array of image objects with metadata'
        ],
        [
            'key' => 'layout',
            'type' => 'select',
            'display_name' => 'Gallery Layout',
            'required' => false,
            'default_value' => 'grid',
            'options' => [
                ['label' => 'Grid', 'value' => 'grid'],
                ['label' => 'Masonry', 'value' => 'masonry'],
                ['label' => 'Carousel', 'value' => 'carousel']
            ]
        ],
        [
            'key' => 'thumbnail_size',
            'type' => 'number',
            'display_name' => 'Thumbnail Size (px)',
            'required' => false,
            'default_value' => 200,
            'min' => 100,
            'max' => 500
        ]
    ]
]);
```

### Validation Process:

#### Schema Structure Validation
The cast validates that each schema entry contains:
```php
[
    'key' => 'field_name',        // Required: field identifier
    'type' => 'text',             // Required: must be valid field type
    'display_name' => 'Label',    // Optional: human-readable label
    'required' => true,           // Optional: field requirement
    'default_value' => 'default', // Optional: default value
    // Additional type-specific properties...
]
```

#### Error Handling
```php
try {
    $component = Component::create([
        'schema' => [
            [
                'key' => 'title',
                'type' => 'invalid_type' // This will trigger validation error
            ]
        ]
    ]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage(); // "Invalid field type: invalid_type"
}

// Missing required properties
try {
    $component = Component::create([
        'schema' => [
            [
                'key' => 'title'
                // Missing 'type' property
            ]
        ]
    ]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage(); // "Component schema field must have a type"
}
```

### Field Type Validation Examples:

#### Text Field Validation
```php
[
    'key' => 'title',
    'type' => 'text',
    'required' => true,
    'min_length' => 5,
    'max_length' => 100,
    'pattern' => '/^[A-Za-z0-9\s]+$/', // Optional regex pattern
    'placeholder' => 'Enter title...'
]
```

#### Select Field Validation
```php
[
    'key' => 'category',
    'type' => 'select',
    'required' => true,
    'options' => [
        ['label' => 'Technology', 'value' => 'tech'],
        ['label' => 'Business', 'value' => 'business'],
        ['label' => 'Lifestyle', 'value' => 'lifestyle']
    ],
    'default_value' => 'tech'
]
```

#### Number Field Validation
```php
[
    'key' => 'price',
    'type' => 'number',
    'required' => true,
    'min' => 0,
    'max' => 9999.99,
    'step' => 0.01,
    'prefix' => '$',
    'suffix' => ' USD'
]
```

#### Image Field Validation
```php
[
    'key' => 'featured_image',
    'type' => 'image',
    'required' => false,
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_file_size' => 5242880, // 5MB in bytes
    'dimensions' => [
        'min_width' => 400,
        'min_height' => 300,
        'max_width' => 1920,
        'max_height' => 1080
    ]
]
```

### Technical Implementation:

#### Validation Rules
- All schema fields must be arrays
- Each field must have a 'type' property
- Field types must be from the allowed list
- Schema structure must be valid JSON when serialized

#### Performance Considerations
- Validation happens only during write operations
- Schema structure is cached after successful validation
- Large schemas are validated efficiently using fast array operations

#### Database Storage
- Schemas are stored as JSONB in PostgreSQL
- GIN indexes enable fast schema querying
- Compressed storage reduces database size

---

## Usage Patterns

### Model Configuration Patterns

#### Standard JSON Fields
```php
class User extends Model
{
    protected $casts = [
        'preferences' => Json::class,
        'metadata' => Json::class,
        'settings' => Json::class,
    ];
    
    // Helper methods for JSON access
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }
    
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
    }
}
```

#### Component Schema Fields
```php
class Component extends Model
{
    protected $casts = [
        'schema' => ComponentSchema::class,
        'preview_field' => Json::class,
        'tabs' => Json::class,
    ];
    
    // Schema validation helpers
    public function validateContentData(array $data): array
    {
        $errors = [];
        
        foreach ($this->schema as $field) {
            $key = $field['key'];
            $value = $data[$key] ?? null;
            
            // Validation logic using schema definition
            if ($field['required'] ?? false && empty($value)) {
                $errors[$key] = "Field '{$key}' is required";
            }
        }
        
        return $errors;
    }
}
```

### Database Migration Patterns

#### JSONB Column Setup
```php
// Migration for JSON fields
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->jsonb('preferences')->nullable();
    $table->jsonb('metadata')->nullable();
    $table->timestamps();
    
    // GIN indexes for fast JSON queries
    $table->index('preferences', 'users_preferences_gin', 'gin');
    $table->index('metadata', 'users_metadata_gin', 'gin');
});

// Or add to existing table
Schema::table('components', function (Blueprint $table) {
    $table->jsonb('schema');
    $table->jsonb('preview_field')->nullable();
    
    DB::statement('CREATE INDEX components_schema_gin_idx ON components USING GIN (schema)');
});
```

### Query Patterns

#### JSON Field Queries
```php
// Query JSON fields in PostgreSQL
$users = User::whereJsonContains('preferences->notifications->email', true)->get();

// Query nested JSON structures
$spaces = Space::whereJsonPath('settings', '$.features.comments', true)->get();

// Use raw queries for complex JSON operations
$results = DB::table('components')
    ->whereRaw("schema @> '[{\"type\": \"image\"}]'")
    ->get();
```

#### Component Schema Queries
```php
// Find components with specific field types
$imageComponents = Component::whereJsonContains('schema', [['type' => 'image']])->get();

// Find components with required fields
$requiredFieldComponents = Component::whereRaw(
    "EXISTS (SELECT 1 FROM jsonb_array_elements(schema) AS elem WHERE elem->>'required' = 'true')"
)->get();
```

### Validation Patterns

#### Custom JSON Validation
```php
class CustomJsonCast extends Json
{
    protected function validateJson(mixed $value): mixed
    {
        $decoded = parent::validateJson($value);
        
        // Custom validation logic
        if (isset($decoded['email']) && !filter_var($decoded['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format in JSON data');
        }
        
        return $decoded;
    }
}
```

#### Schema Validation Extensions
```php
class ExtendedComponentSchema extends ComponentSchema
{
    protected function validateSchema(mixed $schema): array
    {
        $validated = parent::validateSchema($schema);
        
        // Additional validation rules
        foreach ($validated as $field) {
            if ($field['type'] === 'custom_type' && !$this->validateCustomType($field)) {
                throw new \InvalidArgumentException("Invalid custom type configuration");
            }
        }
        
        return $validated;
    }
    
    private function validateCustomType(array $field): bool
    {
        // Custom type validation logic
        return isset($field['custom_config']) && is_array($field['custom_config']);
    }
}
```

---

## Best Practices

### Performance Optimization

1. **Use GIN Indexes for JSON Queries**
   ```php
   // Migration
   DB::statement('CREATE INDEX table_json_gin_idx ON table_name USING GIN (json_column)');
   
   // Query efficiently
   $results = Model::whereJsonContains('json_field', ['key' => 'value'])->get();
   ```

2. **Cache Parsed JSON for Frequently Accessed Data**
   ```php
   class User extends Model
   {
       use Cacheable;
       
       public function getCachedPreferences(): array
       {
           return $this->getCached('parsed_preferences', function () {
               return $this->preferences ?? [];
           }, 3600);
       }
   }
   ```

3. **Minimize JSON Field Size**
   ```php
   // ✅ Good: Store only necessary data
   $user->preferences = [
       'theme' => 'dark',
       'language' => 'en'
   ];
   
   // ❌ Avoid: Large nested structures
   $user->preferences = [
       'theme' => 'dark',
       'cached_data' => [...], // This should be cached separately
       'temporary_data' => [...] // This shouldn't be stored
   ];
   ```

### Error Handling

1. **Graceful JSON Error Handling**
   ```php
   try {
       $preferences = $user->preferences;
   } catch (\InvalidArgumentException $e) {
       // Log error and use defaults
       \Log::warning('JSON decode error', [
           'model' => get_class($user),
           'id' => $user->id,
           'field' => 'preferences',
           'error' => $e->getMessage()
       ]);
       
       $preferences = $this->getDefaultPreferences();
   }
   ```

2. **Schema Validation Error Handling**
   ```php
   try {
       $component = Component::create(['schema' => $schemaData]);
   } catch (\InvalidArgumentException $e) {
       return response()->json([
           'error' => 'Invalid component schema',
           'details' => $e->getMessage()
       ], 422);
   }
   ```

### Security Considerations

1. **Validate JSON Input**
   ```php
   // Controller validation
   public function store(Request $request)
   {
       $validated = $request->validate([
           'preferences' => 'required|array',
           'preferences.theme' => 'required|string|in:light,dark',
           'preferences.language' => 'required|string|size:2',
       ]);
       
       $user->update(['preferences' => $validated['preferences']]);
   }
   ```

2. **Sanitize Component Schemas**
   ```php
   class ComponentSchema extends \App\Casts\ComponentSchema
   {
       protected function validateSchema(mixed $schema): array
       {
           $validated = parent::validateSchema($schema);
           
           // Sanitize field keys (prevent injection)
           foreach ($validated as &$field) {
               $field['key'] = preg_replace('/[^a-zA-Z0-9_]/', '', $field['key']);
           }
           
           return $validated;
       }
   }
   ```

### Testing Patterns

1. **Test JSON Cast Functionality**
   ```php
   public function test_json_cast_encoding_decoding()
   {
       $data = ['key' => 'value', 'nested' => ['data' => 123]];
       
       $user = User::create(['preferences' => $data]);
       
       $this->assertEquals($data, $user->fresh()->preferences);
   }
   
   public function test_json_cast_error_handling()
   {
       $this->expectException(\InvalidArgumentException::class);
       
       // Manually insert invalid JSON
       DB::table('users')->insert([
           'email' => 'test@example.com',
           'preferences' => 'invalid json{'
       ]);
       
       User::first()->preferences;
   }
   ```

2. **Test Component Schema Validation**
   ```php
   public function test_component_schema_validation()
   {
       $validSchema = [
           ['key' => 'title', 'type' => 'text', 'required' => true]
       ];
       
       $component = Component::create(['schema' => $validSchema]);
       
       $this->assertEquals($validSchema, $component->schema);
   }
   
   public function test_invalid_component_schema_throws_exception()
   {
       $this->expectException(\InvalidArgumentException::class);
       
       Component::create([
           'schema' => [
               ['key' => 'title', 'type' => 'invalid_type']
           ]
       ]);
   }
   ```

### Migration Strategies

1. **Adding JSON Fields to Existing Tables**
   ```php
   // Migration
   public function up()
   {
       Schema::table('users', function (Blueprint $table) {
           $table->jsonb('preferences')->nullable();
       });
       
       // Migrate existing data
       User::chunk(100, function ($users) {
           foreach ($users as $user) {
               $user->update([
                   'preferences' => [
                       'theme' => 'light', // Default values
                       'language' => 'en'
                   ]
               ]);
           }
       });
       
       // Add index after data migration
       DB::statement('CREATE INDEX users_preferences_gin_idx ON users USING GIN (preferences)');
   }
   ```

2. **Migrating Schema Changes**
   ```php
   // When component schema structure changes
   public function up()
   {
       Component::chunk(50, function ($components) {
           foreach ($components as $component) {
               $schema = $component->schema;
               
               // Transform old schema format to new format
               foreach ($schema as &$field) {
                   if (isset($field['validation'])) {
                       // Move validation rules to top level
                       $field = array_merge($field, $field['validation']);
                       unset($field['validation']);
                   }
               }
               
               $component->update(['schema' => $schema]);
           }
       });
   }
   ```