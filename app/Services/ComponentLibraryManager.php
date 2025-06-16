<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Component;
use App\Models\Space;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Component Library Manager Service.
 * Manages component libraries, import/export, and templates.
 */
class ComponentLibraryManager
{
    public function __construct(
        private ComponentSchemaValidator $schemaValidator
    ) {}

    /**
     * Export components from a space.
     */
    public function exportComponents(Space $space, array $componentIds = []): array
    {
        $query = Component::where('space_id', $space->id);
        
        if (!empty($componentIds)) {
            $query->whereIn('uuid', $componentIds);
        }
        
        $components = $query->with('creator')->get();
        
        $exportData = [
            'export_info' => [
                'version' => '1.0',
                'created_at' => now()->toISOString(),
                'space_name' => $space->name,
                'space_uuid' => $space->uuid,
                'component_count' => $components->count()
            ],
            'components' => []
        ];

        foreach ($components as $component) {
            $exportData['components'][] = $this->serializeComponent($component);
        }

        return $exportData;
    }

    /**
     * Import components into a space.
     */
    public function importComponents(Space $space, array $importData, array $options = []): array
    {
        $options = array_merge([
            'skip_duplicates' => false,
            'update_existing' => false,
            'preserve_ids' => false,
            'name_prefix' => '',
            'dry_run' => false
        ], $options);

        // Validate import data structure
        $this->validateImportData($importData);

        $results = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'components' => []
        ];

        DB::transaction(function () use ($space, $importData, $options, &$results) {
            foreach ($importData['components'] as $componentData) {
                try {
                    $result = $this->importSingleComponent($space, $componentData, $options);
                    
                    if ($result['status'] === 'imported') {
                        $results['imported']++;
                        $results['components'][] = $result['component'];
                    } else {
                        $results['skipped']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'component' => $componentData['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Create a component template/preset.
     */
    public function createTemplate(string $name, array $componentData, array $metadata = []): array
    {
        // Validate component data
        $errors = $this->schemaValidator->validateSchema($componentData['schema'] ?? []);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Invalid component schema: ' . json_encode($errors));
        }

        $template = [
            'template_info' => [
                'name' => $name,
                'description' => $metadata['description'] ?? '',
                'category' => $metadata['category'] ?? 'custom',
                'tags' => $metadata['tags'] ?? [],
                'version' => $metadata['version'] ?? '1.0',
                'created_at' => now()->toISOString(),
                'author' => $metadata['author'] ?? auth()->user()?->name
            ],
            'component' => $this->sanitizeComponentForTemplate($componentData)
        ];

        return $template;
    }

    /**
     * Create component from template.
     */
    public function createFromTemplate(Space $space, array $template, array $overrides = []): Component
    {
        $componentData = $template['component'];
        
        // Apply overrides
        $componentData = array_merge($componentData, $overrides);
        
        // Generate new UUID and technical name if needed
        if (!isset($componentData['uuid']) || empty($componentData['uuid'])) {
            $componentData['uuid'] = Str::uuid()->toString();
        }
        
        if (!isset($componentData['technical_name']) || empty($componentData['technical_name'])) {
            $componentData['technical_name'] = Str::slug($componentData['name']);
        }

        // Ensure technical name is unique in the space
        $componentData['technical_name'] = $this->ensureUniqueTechnicalName(
            $space, 
            $componentData['technical_name']
        );

        // Create the component
        $component = new Component();
        $component->space_id = $space->id;
        $component->uuid = $componentData['uuid'];
        $component->name = $componentData['name'];
        $component->technical_name = $componentData['technical_name'];
        $component->description = $componentData['description'] ?? null;
        $component->type = $componentData['type'] ?? 'content_type';
        $component->schema = $componentData['schema'];
        $component->preview_field = $componentData['preview_field'] ?? null;
        $component->preview_template = $componentData['preview_template'] ?? null;
        $component->icon = $componentData['icon'] ?? null;
        $component->color = $componentData['color'] ?? null;
        $component->tabs = $componentData['tabs'] ?? null;
        $component->is_root = $componentData['is_root'] ?? false;
        $component->is_nestable = $componentData['is_nestable'] ?? true;
        $component->allowed_roles = $componentData['allowed_roles'] ?? null;
        $component->max_instances = $componentData['max_instances'] ?? null;
        $component->status = $componentData['status'] ?? 'draft';
        $component->created_by = auth()->id();
        
        $component->save();

        return $component;
    }

    /**
     * Get built-in component templates.
     */
    public function getBuiltInTemplates(): Collection
    {
        return collect([
            [
                'template_info' => [
                    'name' => 'Hero Section',
                    'description' => 'Large banner with headline, subheadline, and call-to-action',
                    'category' => 'marketing',
                    'tags' => ['hero', 'banner', 'cta'],
                    'version' => '1.0'
                ],
                'component' => [
                    'name' => 'Hero',
                    'technical_name' => 'hero',
                    'description' => 'Hero section with customizable content',
                    'type' => 'content_type',
                    'is_root' => true,
                    'icon' => 'rectangle-stack',
                    'color' => '#3B82F6',
                    'schema' => [
                        'headline' => [
                            'type' => 'text',
                            'required' => true,
                            'description' => 'Main headline text',
                            'max_length' => 100
                        ],
                        'subheadline' => [
                            'type' => 'textarea',
                            'description' => 'Supporting text below headline',
                            'max_length' => 200
                        ],
                        'background_image' => [
                            'type' => 'asset',
                            'description' => 'Background image',
                            'filetypes' => ['jpg', 'png', 'webp']
                        ],
                        'cta_text' => [
                            'type' => 'text',
                            'description' => 'Call-to-action button text',
                            'max_length' => 50
                        ],
                        'cta_url' => [
                            'type' => 'link',
                            'description' => 'Call-to-action link destination'
                        ]
                    ]
                ]
            ],
            [
                'template_info' => [
                    'name' => 'Text Block',
                    'description' => 'Simple rich text content block',
                    'category' => 'content',
                    'tags' => ['text', 'content', 'basic'],
                    'version' => '1.0'
                ],
                'component' => [
                    'name' => 'Text Block',
                    'technical_name' => 'text_block',
                    'description' => 'Rich text content block',
                    'type' => 'content_type',
                    'is_nestable' => true,
                    'icon' => 'document-text',
                    'color' => '#6B7280',
                    'schema' => [
                        'content' => [
                            'type' => 'richtext',
                            'required' => true,
                            'description' => 'Rich text content'
                        ]
                    ]
                ]
            ],
            [
                'template_info' => [
                    'name' => 'Image Gallery',
                    'description' => 'Responsive image gallery with captions',
                    'category' => 'media',
                    'tags' => ['images', 'gallery', 'media'],
                    'version' => '1.0'
                ],
                'component' => [
                    'name' => 'Image Gallery',
                    'technical_name' => 'image_gallery',
                    'description' => 'Image gallery with customizable layout',
                    'type' => 'content_type',
                    'icon' => 'photo',
                    'color' => '#10B981',
                    'schema' => [
                        'title' => [
                            'type' => 'text',
                            'description' => 'Gallery title',
                            'max_length' => 100
                        ],
                        'images' => [
                            'type' => 'table',
                            'required' => true,
                            'description' => 'Gallery images with captions',
                            'columns' => [
                                [
                                    'name' => 'image',
                                    'type' => 'asset',
                                    'required' => true
                                ],
                                [
                                    'name' => 'caption',
                                    'type' => 'text',
                                    'required' => false
                                ],
                                [
                                    'name' => 'alt_text',
                                    'type' => 'text',
                                    'required' => true
                                ]
                            ]
                        ],
                        'layout' => [
                            'type' => 'select',
                            'description' => 'Gallery layout style',
                            'options' => [
                                ['name' => 'Grid', 'value' => 'grid'],
                                ['name' => 'Masonry', 'value' => 'masonry'],
                                ['name' => 'Carousel', 'value' => 'carousel']
                            ],
                            'default_value' => 'grid'
                        ]
                    ]
                ]
            ],
            [
                'template_info' => [
                    'name' => 'Card Grid',
                    'description' => 'Grid of cards with image, title, and description',
                    'category' => 'layout',
                    'tags' => ['cards', 'grid', 'layout'],
                    'version' => '1.0'
                ],
                'component' => [
                    'name' => 'Card Grid',
                    'technical_name' => 'card_grid',
                    'description' => 'Responsive grid of content cards',
                    'type' => 'content_type',
                    'icon' => 'squares-2x2',
                    'color' => '#8B5CF6',
                    'schema' => [
                        'title' => [
                            'type' => 'text',
                            'description' => 'Section title',
                            'max_length' => 100
                        ],
                        'cards' => [
                            'type' => 'table',
                            'required' => true,
                            'description' => 'Card content',
                            'columns' => [
                                [
                                    'name' => 'image',
                                    'type' => 'asset',
                                    'required' => false
                                ],
                                [
                                    'name' => 'title',
                                    'type' => 'text',
                                    'required' => true
                                ],
                                [
                                    'name' => 'description',
                                    'type' => 'textarea',
                                    'required' => false
                                ],
                                [
                                    'name' => 'link',
                                    'type' => 'link',
                                    'required' => false
                                ]
                            ]
                        ],
                        'columns' => [
                            'type' => 'select',
                            'description' => 'Number of columns',
                            'options' => [
                                ['name' => '2 Columns', 'value' => '2'],
                                ['name' => '3 Columns', 'value' => '3'],
                                ['name' => '4 Columns', 'value' => '4']
                            ],
                            'default_value' => '3'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get component statistics for a space.
     */
    public function getComponentStatistics(Space $space): array
    {
        $components = Component::where('space_id', $space->id)->get();
        
        $stats = [
            'total_components' => $components->count(),
            'by_type' => $components->countBy('type'),
            'by_status' => $components->countBy('status'),
            'usage_statistics' => [],
            'most_used' => [],
            'unused' => []
        ];

        // Calculate usage statistics
        foreach ($components as $component) {
            $usageCount = $component->getUsageCount();
            $stats['usage_statistics'][$component->technical_name] = $usageCount;
            
            if ($usageCount > 0) {
                $stats['most_used'][] = [
                    'name' => $component->name,
                    'technical_name' => $component->technical_name,
                    'usage_count' => $usageCount
                ];
            } else {
                $stats['unused'][] = [
                    'name' => $component->name,
                    'technical_name' => $component->technical_name
                ];
            }
        }

        // Sort most used by usage count
        usort($stats['most_used'], fn($a, $b) => $b['usage_count'] <=> $a['usage_count']);
        $stats['most_used'] = array_slice($stats['most_used'], 0, 10);

        return $stats;
    }

    /**
     * Serialize a component for export.
     */
    private function serializeComponent(Component $component): array
    {
        return [
            'uuid' => $component->uuid,
            'name' => $component->name,
            'technical_name' => $component->technical_name,
            'description' => $component->description,
            'type' => $component->type,
            'schema' => $component->schema,
            'preview_field' => $component->preview_field,
            'preview_template' => $component->preview_template,
            'icon' => $component->icon,
            'color' => $component->color,
            'tabs' => $component->tabs,
            'is_root' => $component->is_root,
            'is_nestable' => $component->is_nestable,
            'allowed_roles' => $component->allowed_roles,
            'max_instances' => $component->max_instances,
            'version' => $component->version,
            'status' => $component->status,
            'created_by' => $component->creator?->name,
            'created_at' => $component->created_at->toISOString(),
            'updated_at' => $component->updated_at->toISOString()
        ];
    }

    /**
     * Import a single component.
     */
    private function importSingleComponent(Space $space, array $componentData, array $options): array
    {
        // Check if component already exists
        $existingComponent = Component::where('space_id', $space->id)
            ->where('technical_name', $componentData['technical_name'])
            ->first();

        if ($existingComponent) {
            if ($options['skip_duplicates']) {
                return ['status' => 'skipped', 'reason' => 'duplicate'];
            }
            
            if (!$options['update_existing']) {
                throw new InvalidArgumentException("Component '{$componentData['technical_name']}' already exists");
            }
        }

        // Validate component schema
        $errors = $this->schemaValidator->validateSchema($componentData['schema']);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Invalid component schema: ' . json_encode($errors));
        }

        if ($options['dry_run']) {
            return ['status' => 'would_import'];
        }

        // Create or update component
        $component = $existingComponent ?: new Component();
        
        if (!$options['preserve_ids'] || !$existingComponent) {
            $componentData['uuid'] = Str::uuid()->toString();
        }

        if ($options['name_prefix']) {
            $componentData['name'] = $options['name_prefix'] . $componentData['name'];
            $componentData['technical_name'] = Str::slug($componentData['name']);
        }

        // Ensure unique technical name
        if (!$existingComponent) {
            $componentData['technical_name'] = $this->ensureUniqueTechnicalName(
                $space, 
                $componentData['technical_name']
            );
        }

        $component->fill([
            'space_id' => $space->id,
            'uuid' => $componentData['uuid'],
            'name' => $componentData['name'],
            'technical_name' => $componentData['technical_name'],
            'description' => $componentData['description'] ?? null,
            'type' => $componentData['type'] ?? 'content_type',
            'schema' => $componentData['schema'],
            'preview_field' => $componentData['preview_field'] ?? null,
            'preview_template' => $componentData['preview_template'] ?? null,
            'icon' => $componentData['icon'] ?? null,
            'color' => $componentData['color'] ?? null,
            'tabs' => $componentData['tabs'] ?? null,
            'is_root' => $componentData['is_root'] ?? false,
            'is_nestable' => $componentData['is_nestable'] ?? true,
            'allowed_roles' => $componentData['allowed_roles'] ?? null,
            'max_instances' => $componentData['max_instances'] ?? null,
            'status' => $componentData['status'] ?? 'draft',
            'created_by' => auth()->id(),
        ]);

        $component->save();

        return [
            'status' => 'imported',
            'component' => $component
        ];
    }

    /**
     * Validate import data structure.
     */
    private function validateImportData(array $importData): void
    {
        $validator = Validator::make($importData, [
            'export_info' => 'required|array',
            'export_info.version' => 'required|string',
            'components' => 'required|array|min:1',
            'components.*.name' => 'required|string',
            'components.*.technical_name' => 'required|string',
            'components.*.schema' => 'required|array'
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid import data: ' . $validator->errors()->first());
        }
    }

    /**
     * Sanitize component data for template.
     */
    private function sanitizeComponentForTemplate(array $componentData): array
    {
        // Remove space-specific and instance-specific data
        unset($componentData['id'], $componentData['space_id'], $componentData['created_by'], 
              $componentData['updated_by'], $componentData['created_at'], $componentData['updated_at']);

        return $componentData;
    }

    /**
     * Ensure technical name is unique in the space.
     */
    private function ensureUniqueTechnicalName(Space $space, string $technicalName): string
    {
        $originalName = $technicalName;
        $counter = 1;

        while (Component::where('space_id', $space->id)
                       ->where('technical_name', $technicalName)
                       ->exists()) {
            $technicalName = $originalName . '_' . $counter;
            $counter++;
        }

        return $technicalName;
    }
}