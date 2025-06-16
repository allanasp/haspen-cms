<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use InvalidArgumentException;

/**
 * Field Type Registry Service.
 * Manages field types and allows for extensibility.
 */
class FieldTypeRegistry
{
    private static array $fieldTypes = [];
    private static array $validators = [];
    private static array $renderers = [];
    private static bool $initialized = false;

    /**
     * Initialize default field types.
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        // Register core field types
        self::registerCoreFieldTypes();
        self::$initialized = true;
    }

    /**
     * Register a new field type.
     */
    public static function register(string $type, array $definition): void
    {
        self::$fieldTypes[$type] = array_merge([
            'name' => $type,
            'label' => ucfirst(str_replace('_', ' ', $type)),
            'category' => 'custom',
            'properties' => [],
            'validation_rules' => [],
            'default_config' => [],
            'description' => ''
        ], $definition);
    }

    /**
     * Register a field type validator.
     */
    public static function registerValidator(string $type, Closure $validator): void
    {
        self::$validators[$type] = $validator;
    }

    /**
     * Register a field type renderer.
     */
    public static function registerRenderer(string $type, Closure $renderer): void
    {
        self::$renderers[$type] = $renderer;
    }

    /**
     * Get all registered field types.
     */
    public static function getFieldTypes(): array
    {
        self::initialize();
        return self::$fieldTypes;
    }

    /**
     * Get field types by category.
     */
    public static function getFieldTypesByCategory(string $category): array
    {
        self::initialize();
        return array_filter(self::$fieldTypes, fn($type) => $type['category'] === $category);
    }

    /**
     * Get a specific field type definition.
     */
    public static function getFieldType(string $type): ?array
    {
        self::initialize();
        return self::$fieldTypes[$type] ?? null;
    }

    /**
     * Check if a field type exists.
     */
    public static function hasFieldType(string $type): bool
    {
        self::initialize();
        return isset(self::$fieldTypes[$type]);
    }

    /**
     * Get supported field type names.
     */
    public static function getSupportedTypes(): array
    {
        self::initialize();
        return array_keys(self::$fieldTypes);
    }

    /**
     * Validate a value against a field type.
     */
    public static function validateValue(string $type, mixed $value, array $config = []): ?string
    {
        self::initialize();

        if (!self::hasFieldType($type)) {
            return "Unknown field type: {$type}";
        }

        // Use custom validator if available
        if (isset(self::$validators[$type])) {
            return self::$validators[$type]($value, $config);
        }

        // Use default validation logic
        return self::getDefaultValidator($type)($value, $config);
    }

    /**
     * Render a field value using registered renderer.
     */
    public static function renderValue(string $type, mixed $value, array $config = []): mixed
    {
        self::initialize();

        if (isset(self::$renderers[$type])) {
            return self::$renderers[$type]($value, $config);
        }

        // Return value as-is if no custom renderer
        return $value;
    }

    /**
     * Get field type schema for validation.
     */
    public static function getFieldTypeSchema(string $type): array
    {
        self::initialize();
        
        $fieldType = self::getFieldType($type);
        if (!$fieldType) {
            throw new InvalidArgumentException("Unknown field type: {$type}");
        }

        return [
            'type' => 'object',
            'properties' => array_merge(
                [
                    'type' => ['type' => 'string', 'enum' => [$type]],
                    'required' => ['type' => 'boolean'],
                    'translatable' => ['type' => 'boolean'],
                    'description' => ['type' => 'string'],
                    'default_value' => []
                ],
                $fieldType['properties']
            ),
            'required' => ['type'],
            'additionalProperties' => false
        ];
    }

    /**
     * Register core field types.
     */
    private static function registerCoreFieldTypes(): void
    {
        // Text input
        self::register('text', [
            'label' => 'Text',
            'category' => 'basic',
            'description' => 'Single line text input',
            'properties' => [
                'min_length' => ['type' => 'integer', 'minimum' => 0],
                'max_length' => ['type' => 'integer', 'minimum' => 1],
                'regex' => ['type' => 'string'],
                'placeholder' => ['type' => 'string']
            ]
        ]);

        // Textarea
        self::register('textarea', [
            'label' => 'Textarea',
            'category' => 'basic',
            'description' => 'Multi-line text input',
            'properties' => [
                'min_length' => ['type' => 'integer', 'minimum' => 0],
                'max_length' => ['type' => 'integer', 'minimum' => 1],
                'rows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                'placeholder' => ['type' => 'string']
            ]
        ]);

        // Rich text
        self::register('richtext', [
            'label' => 'Rich Text',
            'category' => 'advanced',
            'description' => 'WYSIWYG rich text editor',
            'properties' => [
                'toolbar_items' => ['type' => 'array'],
                'allow_target_blank' => ['type' => 'boolean'],
                'max_length' => ['type' => 'integer', 'minimum' => 1]
            ]
        ]);

        // Markdown
        self::register('markdown', [
            'label' => 'Markdown',
            'category' => 'advanced',
            'description' => 'Markdown text editor',
            'properties' => [
                'preview_mode' => ['type' => 'boolean'],
                'max_length' => ['type' => 'integer', 'minimum' => 1]
            ]
        ]);

        // Number
        self::register('number', [
            'label' => 'Number',
            'category' => 'basic',
            'description' => 'Numeric input with validation',
            'properties' => [
                'min' => ['type' => 'number'],
                'max' => ['type' => 'number'],
                'step' => ['type' => 'number'],
                'decimals' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10]
            ]
        ]);

        // Boolean
        self::register('boolean', [
            'label' => 'Boolean',
            'category' => 'basic',
            'description' => 'Checkbox or toggle',
            'properties' => [
                'display_as' => ['type' => 'string', 'enum' => ['checkbox', 'toggle']]
            ]
        ]);

        // Select
        self::register('select', [
            'label' => 'Select',
            'category' => 'choice',
            'description' => 'Dropdown selection',
            'properties' => [
                'options' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'value' => ['type' => 'string']
                        ],
                        'required' => ['name', 'value']
                    ]
                ],
                'allow_empty' => ['type' => 'boolean']
            ]
        ]);

        // Multi-select
        self::register('multiselect', [
            'label' => 'Multi-Select',
            'category' => 'choice',
            'description' => 'Multiple selection dropdown',
            'properties' => [
                'options' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'value' => ['type' => 'string']
                        ],
                        'required' => ['name', 'value']
                    ]
                ],
                'max_selections' => ['type' => 'integer', 'minimum' => 1]
            ]
        ]);

        // Email
        self::register('email', [
            'label' => 'Email',
            'category' => 'validation',
            'description' => 'Email address input with validation',
            'properties' => [
                'placeholder' => ['type' => 'string']
            ]
        ]);

        // URL
        self::register('url', [
            'label' => 'URL',
            'category' => 'validation',
            'description' => 'URL input with validation',
            'properties' => [
                'placeholder' => ['type' => 'string'],
                'protocols' => ['type' => 'array', 'items' => ['type' => 'string']]
            ]
        ]);

        // Date
        self::register('date', [
            'label' => 'Date',
            'category' => 'datetime',
            'description' => 'Date picker',
            'properties' => [
                'min_date' => ['type' => 'string', 'format' => 'date'],
                'max_date' => ['type' => 'string', 'format' => 'date'],
                'format' => ['type' => 'string']
            ]
        ]);

        // DateTime
        self::register('datetime', [
            'label' => 'Date Time',
            'category' => 'datetime',
            'description' => 'Date and time picker',
            'properties' => [
                'min_datetime' => ['type' => 'string', 'format' => 'date-time'],
                'max_datetime' => ['type' => 'string', 'format' => 'date-time'],
                'format' => ['type' => 'string']
            ]
        ]);

        // Asset
        self::register('asset', [
            'label' => 'Asset',
            'category' => 'media',
            'description' => 'File or image picker',
            'properties' => [
                'asset_folder' => ['type' => 'string'],
                'filetypes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'maximum_file_size' => ['type' => 'integer'],
                'image_dimensions' => [
                    'type' => 'object',
                    'properties' => [
                        'min_width' => ['type' => 'integer'],
                        'max_width' => ['type' => 'integer'],
                        'min_height' => ['type' => 'integer'],
                        'max_height' => ['type' => 'integer']
                    ]
                ]
            ]
        ]);

        // Blocks (nested components)
        self::register('blocks', [
            'label' => 'Blocks',
            'category' => 'structure',
            'description' => 'Nested component blocks',
            'properties' => [
                'restrict_type' => ['type' => 'string'],
                'component_whitelist' => ['type' => 'array', 'items' => ['type' => 'string']],
                'maximum' => ['type' => 'integer', 'minimum' => 1],
                'minimum' => ['type' => 'integer', 'minimum' => 0]
            ]
        ]);

        // Link
        self::register('link', [
            'label' => 'Link',
            'category' => 'structure',
            'description' => 'Internal or external link',
            'properties' => [
                'allow_external' => ['type' => 'boolean'],
                'allow_internal' => ['type' => 'boolean'],
                'allow_email' => ['type' => 'boolean'],
                'allow_anchor' => ['type' => 'boolean']
            ]
        ]);

        // Color
        self::register('color', [
            'label' => 'Color',
            'category' => 'design',
            'description' => 'Color picker',
            'properties' => [
                'format' => ['type' => 'string', 'enum' => ['hex', 'rgb', 'hsl']],
                'allow_transparency' => ['type' => 'boolean'],
                'preset_colors' => ['type' => 'array', 'items' => ['type' => 'string']]
            ]
        ]);

        // JSON
        self::register('json', [
            'label' => 'JSON',
            'category' => 'advanced',
            'description' => 'JSON data input',
            'properties' => [
                'schema' => ['type' => 'object'],
                'pretty_print' => ['type' => 'boolean']
            ]
        ]);

        // Table
        self::register('table', [
            'label' => 'Table',
            'category' => 'structure',
            'description' => 'Tabular data input',
            'properties' => [
                'columns' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'required' => ['type' => 'boolean']
                        ],
                        'required' => ['name', 'type']
                    ]
                ],
                'max_rows' => ['type' => 'integer', 'minimum' => 1]
            ]
        ]);
    }

    /**
     * Get default validator for a field type.
     */
    private static function getDefaultValidator(string $type): Closure
    {
        return match($type) {
            'text', 'textarea', 'richtext', 'markdown' => function($value, $config) {
                if (!is_string($value)) {
                    return 'Value must be a string';
                }
                
                if (isset($config['min_length']) && strlen($value) < $config['min_length']) {
                    return "Value must be at least {$config['min_length']} characters";
                }
                
                if (isset($config['max_length']) && strlen($value) > $config['max_length']) {
                    return "Value must not exceed {$config['max_length']} characters";
                }
                
                if (isset($config['regex']) && !preg_match($config['regex'], $value)) {
                    return 'Value does not match required pattern';
                }
                
                return null;
            },
            
            'number' => function($value, $config) {
                if (!is_numeric($value)) {
                    return 'Value must be numeric';
                }
                
                $numValue = (float) $value;
                if (isset($config['min']) && $numValue < $config['min']) {
                    return "Value must be at least {$config['min']}";
                }
                
                if (isset($config['max']) && $numValue > $config['max']) {
                    return "Value must not exceed {$config['max']}";
                }
                
                return null;
            },
            
            'boolean' => function($value, $config) {
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    return 'Value must be boolean';
                }
                return null;
            },
            
            'email' => function($value, $config) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Value must be a valid email address';
                }
                return null;
            },
            
            'url' => function($value, $config) {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return 'Value must be a valid URL';
                }
                return null;
            },
            
            default => function($value, $config) {
                return null; // No validation for unknown types
            }
        };
    }

    /**
     * Reset the registry (mainly for testing).
     */
    public static function reset(): void
    {
        self::$fieldTypes = [];
        self::$validators = [];
        self::$renderers = [];
        self::$initialized = false;
    }

    /**
     * Get field type categories.
     */
    public static function getCategories(): array
    {
        self::initialize();
        $categories = array_unique(array_column(self::$fieldTypes, 'category'));
        sort($categories);
        return $categories;
    }
}