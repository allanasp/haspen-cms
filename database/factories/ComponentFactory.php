<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Component>
 * @psalm-suppress UnusedClass
 */
final class ComponentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Component>
     */
    protected $model = Component::class;

    /**
     * Predefined component schemas for realistic CMS components.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $componentSchemas = [
        'hero_section' => [
            'name' => 'Hero Section',
            'description' => 'Main hero section with headline, description, and call-to-action',
            'icon' => 'hero',
            'color' => '#3b82f6',
            'is_root' => true,
            'schema' => [
                'headline' => [
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 100,
                    'label' => 'Headline',
                    'description' => 'Main hero headline text',
                ],
                'subheadline' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 300,
                    'label' => 'Subheadline',
                    'description' => 'Supporting text for the hero section',
                ],
                'background_image' => [
                    'type' => 'asset',
                    'required' => false,
                    'label' => 'Background Image',
                    'asset_types' => ['image'],
                ],
                'cta_text' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 50,
                    'label' => 'CTA Button Text',
                ],
                'cta_link' => [
                    'type' => 'link',
                    'required' => false,
                    'label' => 'CTA Link',
                ],
                'layout' => [
                    'type' => 'select',
                    'required' => true,
                    'label' => 'Layout Style',
                    'options' => [
                        ['label' => 'Centered', 'value' => 'centered'],
                        ['label' => 'Left Aligned', 'value' => 'left'],
                        ['label' => 'Right Aligned', 'value' => 'right'],
                    ],
                    'default' => 'centered',
                ],
            ],
        ],
        'text_block' => [
            'name' => 'Text Block',
            'description' => 'Rich text content block',
            'icon' => 'text',
            'color' => '#10b981',
            'is_nestable' => true,
            'schema' => [
                'content' => [
                    'type' => 'richtext',
                    'required' => true,
                    'label' => 'Content',
                    'description' => 'Rich text content',
                ],
                'text_align' => [
                    'type' => 'select',
                    'required' => false,
                    'label' => 'Text Alignment',
                    'options' => [
                        ['label' => 'Left', 'value' => 'left'],
                        ['label' => 'Center', 'value' => 'center'],
                        ['label' => 'Right', 'value' => 'right'],
                        ['label' => 'Justify', 'value' => 'justify'],
                    ],
                    'default' => 'left',
                ],
                'max_width' => [
                    'type' => 'select',
                    'required' => false,
                    'label' => 'Maximum Width',
                    'options' => [
                        ['label' => 'Small', 'value' => 'sm'],
                        ['label' => 'Medium', 'value' => 'md'],
                        ['label' => 'Large', 'value' => 'lg'],
                        ['label' => 'Full Width', 'value' => 'full'],
                    ],
                    'default' => 'md',
                ],
            ],
        ],
        'image_gallery' => [
            'name' => 'Image Gallery',
            'description' => 'Responsive image gallery with various layout options',
            'icon' => 'gallery',
            'color' => '#f59e0b',
            'is_nestable' => true,
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Gallery Title',
                ],
                'images' => [
                    'type' => 'asset',
                    'required' => true,
                    'multiple' => true,
                    'label' => 'Images',
                    'asset_types' => ['image'],
                    'min_items' => 1,
                    'max_items' => 20,
                ],
                'layout' => [
                    'type' => 'select',
                    'required' => true,
                    'label' => 'Gallery Layout',
                    'options' => [
                        ['label' => 'Grid', 'value' => 'grid'],
                        ['label' => 'Masonry', 'value' => 'masonry'],
                        ['label' => 'Carousel', 'value' => 'carousel'],
                        ['label' => 'Slideshow', 'value' => 'slideshow'],
                    ],
                    'default' => 'grid',
                ],
                'columns' => [
                    'type' => 'number',
                    'required' => false,
                    'min' => 1,
                    'max' => 6,
                    'default' => 3,
                    'label' => 'Columns',
                    'description' => 'Number of columns in grid layout',
                ],
                'show_captions' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'label' => 'Show Image Captions',
                ],
            ],
        ],
        'card_grid' => [
            'name' => 'Card Grid',
            'description' => 'Grid of content cards',
            'icon' => 'grid',
            'color' => '#8b5cf6',
            'is_nestable' => true,
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Section Title',
                ],
                'cards' => [
                    'type' => 'blocks',
                    'required' => true,
                    'label' => 'Cards',
                    'component_whitelist' => ['card'],
                    'min_items' => 1,
                    'max_items' => 12,
                ],
                'columns' => [
                    'type' => 'select',
                    'required' => true,
                    'label' => 'Columns per Row',
                    'options' => [
                        ['label' => '1 Column', 'value' => '1'],
                        ['label' => '2 Columns', 'value' => '2'],
                        ['label' => '3 Columns', 'value' => '3'],
                        ['label' => '4 Columns', 'value' => '4'],
                    ],
                    'default' => '3',
                ],
                'gap' => [
                    'type' => 'select',
                    'required' => false,
                    'label' => 'Card Spacing',
                    'options' => [
                        ['label' => 'Small', 'value' => 'sm'],
                        ['label' => 'Medium', 'value' => 'md'],
                        ['label' => 'Large', 'value' => 'lg'],
                    ],
                    'default' => 'md',
                ],
            ],
        ],
        'card' => [
            'name' => 'Card',
            'description' => 'Individual content card component',
            'icon' => 'card',
            'color' => '#ef4444',
            'is_nestable' => true,
            'schema' => [
                'image' => [
                    'type' => 'asset',
                    'required' => false,
                    'label' => 'Card Image',
                    'asset_types' => ['image'],
                ],
                'title' => [
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 100,
                    'label' => 'Card Title',
                ],
                'description' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 500,
                    'label' => 'Card Description',
                ],
                'link' => [
                    'type' => 'link',
                    'required' => false,
                    'label' => 'Card Link',
                ],
                'badge' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 30,
                    'label' => 'Badge Text',
                ],
                'badge_color' => [
                    'type' => 'color',
                    'required' => false,
                    'label' => 'Badge Color',
                    'default' => '#3b82f6',
                ],
            ],
        ],
        'video_player' => [
            'name' => 'Video Player',
            'description' => 'Responsive video player component',
            'icon' => 'video',
            'color' => '#dc2626',
            'is_nestable' => true,
            'schema' => [
                'video_source' => [
                    'type' => 'select',
                    'required' => true,
                    'label' => 'Video Source',
                    'options' => [
                        ['label' => 'Upload', 'value' => 'upload'],
                        ['label' => 'YouTube', 'value' => 'youtube'],
                        ['label' => 'Vimeo', 'value' => 'vimeo'],
                        ['label' => 'External URL', 'value' => 'url'],
                    ],
                ],
                'video_file' => [
                    'type' => 'asset',
                    'required' => false,
                    'label' => 'Video File',
                    'asset_types' => ['video'],
                    'conditional' => ['video_source', '=', 'upload'],
                ],
                'video_url' => [
                    'type' => 'url',
                    'required' => false,
                    'label' => 'Video URL',
                    'conditional' => ['video_source', 'in', ['youtube', 'vimeo', 'url']],
                ],
                'poster_image' => [
                    'type' => 'asset',
                    'required' => false,
                    'label' => 'Poster Image',
                    'asset_types' => ['image'],
                ],
                'autoplay' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'label' => 'Autoplay',
                ],
                'controls' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'label' => 'Show Controls',
                ],
                'loop' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'label' => 'Loop Video',
                ],
            ],
        ],
        'contact_form' => [
            'name' => 'Contact Form',
            'description' => 'Customizable contact form',
            'icon' => 'form',
            'color' => '#059669',
            'is_nestable' => true,
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Form Title',
                ],
                'description' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 500,
                    'label' => 'Form Description',
                ],
                'fields' => [
                    'type' => 'json',
                    'required' => true,
                    'label' => 'Form Fields',
                    'description' => 'JSON configuration for form fields',
                ],
                'submit_text' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 30,
                    'default' => 'Send Message',
                    'label' => 'Submit Button Text',
                ],
                'success_message' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 200,
                    'default' => 'Thank you for your message! We\'ll get back to you soon.',
                    'label' => 'Success Message',
                ],
                'recipient_email' => [
                    'type' => 'email',
                    'required' => true,
                    'label' => 'Recipient Email',
                ],
            ],
        ],
        'testimonial' => [
            'name' => 'Testimonial',
            'description' => 'Customer testimonial with quote and author',
            'icon' => 'quote',
            'color' => '#7c3aed',
            'is_nestable' => true,
            'schema' => [
                'quote' => [
                    'type' => 'textarea',
                    'required' => true,
                    'max_length' => 500,
                    'label' => 'Testimonial Quote',
                ],
                'author_name' => [
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 100,
                    'label' => 'Author Name',
                ],
                'author_title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Author Title/Position',
                ],
                'author_company' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Author Company',
                ],
                'author_image' => [
                    'type' => 'asset',
                    'required' => false,
                    'label' => 'Author Photo',
                    'asset_types' => ['image'],
                ],
                'rating' => [
                    'type' => 'number',
                    'required' => false,
                    'min' => 1,
                    'max' => 5,
                    'label' => 'Rating (1-5 stars)',
                ],
                'layout' => [
                    'type' => 'select',
                    'required' => false,
                    'label' => 'Layout Style',
                    'options' => [
                        ['label' => 'Card', 'value' => 'card'],
                        ['label' => 'Minimal', 'value' => 'minimal'],
                        ['label' => 'Featured', 'value' => 'featured'],
                    ],
                    'default' => 'card',
                ],
            ],
        ],
        'pricing_table' => [
            'name' => 'Pricing Table',
            'description' => 'Pricing plans comparison table',
            'icon' => 'pricing',
            'color' => '#0891b2',
            'is_nestable' => true,
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Section Title',
                ],
                'subtitle' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 300,
                    'label' => 'Section Subtitle',
                ],
                'plans' => [
                    'type' => 'blocks',
                    'required' => true,
                    'label' => 'Pricing Plans',
                    'component_whitelist' => ['pricing_plan'],
                    'min_items' => 1,
                    'max_items' => 6,
                ],
                'billing_toggle' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                    'label' => 'Show Monthly/Yearly Toggle',
                ],
            ],
        ],
        'faq_section' => [
            'name' => 'FAQ Section',
            'description' => 'Frequently asked questions with expandable answers',
            'icon' => 'faq',
            'color' => '#ea580c',
            'is_nestable' => true,
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 100,
                    'label' => 'Section Title',
                ],
                'questions' => [
                    'type' => 'blocks',
                    'required' => true,
                    'label' => 'FAQ Items',
                    'component_whitelist' => ['faq_item'],
                    'min_items' => 1,
                ],
                'layout' => [
                    'type' => 'select',
                    'required' => false,
                    'label' => 'Layout Style',
                    'options' => [
                        ['label' => 'Accordion', 'value' => 'accordion'],
                        ['label' => 'Card Grid', 'value' => 'grid'],
                        ['label' => 'Simple List', 'value' => 'list'],
                    ],
                    'default' => 'accordion',
                ],
            ],
        ],
    ];

    /**
     * Available icons for components.
     *
     * @var array<string>
     */
    private static array $icons = [
        'component', 'hero', 'text', 'image', 'gallery', 'video', 'audio',
        'form', 'button', 'card', 'grid', 'list', 'table', 'chart',
        'map', 'calendar', 'clock', 'user', 'quote', 'star', 'heart',
        'shopping-cart', 'tag', 'folder', 'file', 'link', 'settings'
    ];

    /**
     * Available colors for components.
     *
     * @var array<string>
     */
    private static array $colors = [
        '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e',
        '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1',
        '#8b5cf6', '#a855f7', '#c084fc', '#d946ef', '#ec4899', '#f43f5e'
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        // Randomly select a component schema or generate a simple one
        if ($this->faker->boolean(70)) {
            $componentKey = $this->faker->randomElement(array_keys(self::$componentSchemas));
            $component = self::$componentSchemas[$componentKey];
            
            return [
                'name' => $component['name'],
                'technical_name' => strtolower(str_replace(' ', '_', $component['name'])),
                'description' => $component['description'],
                'display_name' => $component['name'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_root' => $component['is_root'] ?? false,
                'is_nestable' => $component['is_nestable'] ?? true,
                'status' => $this->faker->randomElement([
                    Component::STATUS_ACTIVE,
                    Component::STATUS_ACTIVE,
                    Component::STATUS_ACTIVE, // Weight towards active
                    Component::STATUS_INACTIVE,
                    Component::STATUS_DEPRECATED,
                ]),
                'version' => $this->faker->numberBetween(1, 5),
                'allowed_roles' => $this->faker->optional(0.3)->randomElements(['admin', 'editor', 'author'], rand(1, 2)),
                'preview_field' => $this->generatePreviewField($component['schema']),
                'tabs' => $this->generateTabs($component['schema']),
            ];
        }
        
        // Generate a custom component
        return $this->generateCustomComponent();
    }

    /**
     * Generate a custom component with random schema.
     *
     * @return array<string, mixed>
     */
    private function generateCustomComponent(): array
    {
        $componentTypes = [
            'content' => ['Text Block', 'Content Section', 'Article Block'],
            'media' => ['Media Gallery', 'Image Slider', 'Video Block'],
            'layout' => ['Container', 'Column', 'Spacer'],
            'interactive' => ['Button Group', 'Tab Panel', 'Accordion'],
            'data' => ['Data Table', 'Chart', 'Statistics'],
        ];
        
        $type = $this->faker->randomElement(array_keys($componentTypes));
        $name = $this->faker->randomElement($componentTypes[$type]);
        $technicalName = strtolower(str_replace(' ', '_', $name));
        
        $schema = $this->generateRandomSchema();
        
        return [
            'name' => $name,
            'technical_name' => $technicalName,
            'description' => $this->faker->sentence(),
            'display_name' => $name,
            'schema' => $schema,
            'icon' => $this->faker->randomElement(self::$icons),
            'color' => $this->faker->randomElement(self::$colors),
            'is_root' => $this->faker->boolean(20),
            'is_nestable' => $this->faker->boolean(80),
            'status' => $this->faker->randomElement([
                Component::STATUS_ACTIVE,
                Component::STATUS_ACTIVE,
                Component::STATUS_ACTIVE, // Weight towards active
                Component::STATUS_INACTIVE,
                Component::STATUS_DEPRECATED,
            ]),
            'version' => $this->faker->numberBetween(1, 3),
            'allowed_roles' => $this->faker->optional(0.2)->randomElements(['admin', 'editor'], rand(1, 2)),
            'preview_field' => $this->generatePreviewField($schema),
            'tabs' => $this->faker->optional(0.4)->randomElements([
                ['name' => 'Content', 'fields' => ['title', 'content']],
                ['name' => 'Settings', 'fields' => ['layout', 'style']],
                ['name' => 'Advanced', 'fields' => ['custom_css', 'custom_js']],
            ], rand(1, 2)),
        ];
    }

    /**
     * Generate a random schema for custom components.
     *
     * @return array<string, array<string, mixed>>
     */
    private function generateRandomSchema(): array
    {
        $schema = [];
        $fieldCount = $this->faker->numberBetween(2, 6);
        $fieldTypes = ['text', 'textarea', 'richtext', 'number', 'boolean', 'select', 'asset', 'link'];
        
        $commonFields = ['title', 'content', 'description', 'image', 'link', 'enabled', 'style', 'layout'];
        
        for ($i = 0; $i < $fieldCount; $i++) {
            $fieldName = $this->faker->randomElement($commonFields) . ($i > 0 ? '_' . $i : '');
            $fieldType = $this->faker->randomElement($fieldTypes);
            
            $field = [
                'type' => $fieldType,
                'required' => $this->faker->boolean(30),
                'label' => ucwords(str_replace('_', ' ', $fieldName)),
            ];
            
            // Add type-specific properties
            switch ($fieldType) {
                case 'text':
                    $field['max_length'] = $this->faker->randomElement([50, 100, 255]);
                    break;
                    
                case 'textarea':
                    $field['max_length'] = $this->faker->randomElement([500, 1000, 2000]);
                    break;
                    
                case 'number':
                    $field['min'] = 0;
                    $field['max'] = $this->faker->numberBetween(10, 1000);
                    break;
                    
                case 'select':
                    $field['options'] = $this->generateSelectOptions();
                    break;
                    
                case 'asset':
                    $field['asset_types'] = $this->faker->randomElements(['image', 'video', 'document'], rand(1, 2));
                    break;
            }
            
            $schema[$fieldName] = $field;
        }
        
        return $schema;
    }

    /**
     * Generate select field options.
     *
     * @return array<int, array<string, string>>
     */
    private function generateSelectOptions(): array
    {
        $options = [];
        $optionCount = $this->faker->numberBetween(2, 5);
        
        for ($i = 0; $i < $optionCount; $i++) {
            $label = ucfirst($this->faker->word());
            $value = strtolower($label);
            
            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        }
        
        return $options;
    }

    /**
     * Generate preview field configuration.
     *
     * @param array<string, array<string, mixed>> $schema
     * @return array<string, string>|null
     */
    private function generatePreviewField(array $schema): ?array
    {
        // Find the most appropriate field for preview
        $preferredFields = ['title', 'name', 'headline', 'label'];
        
        foreach ($preferredFields as $fieldName) {
            if (isset($schema[$fieldName])) {
                return [
                    'field' => $fieldName,
                    'fallback' => 'Untitled Component',
                ];
            }
        }
        
        // Fall back to first text field
        foreach ($schema as $fieldName => $fieldConfig) {
            if (in_array($fieldConfig['type'], ['text', 'textarea'])) {
                return [
                    'field' => $fieldName,
                    'fallback' => 'Untitled Component',
                ];
            }
        }
        
        return null;
    }

    /**
     * Generate tabs configuration.
     *
     * @param array<string, array<string, mixed>> $schema
     * @return array<int, array<string, mixed>>|null
     */
    private function generateTabs(array $schema): ?array
    {
        $fieldCount = count($schema);
        
        // Only generate tabs if there are enough fields
        if ($fieldCount < 4) {
            return null;
        }
        
        $tabs = [
            [
                'name' => 'Content',
                'fields' => [],
            ],
            [
                'name' => 'Settings',
                'fields' => [],
            ],
        ];
        
        // Distribute fields across tabs
        foreach (array_keys($schema) as $fieldName) {
            if (in_array($fieldName, ['title', 'content', 'description', 'text'])) {
                $tabs[0]['fields'][] = $fieldName;
            } else {
                $tabs[1]['fields'][] = $fieldName;
            }
        }
        
        // Remove empty tabs
        $tabs = array_filter($tabs, fn($tab) => !empty($tab['fields']));
        
        return count($tabs) > 1 ? array_values($tabs) : null;
    }

    /**
     * State for root components (page level).
     */
    public function root(): static
    {
        return $this->state([
            'is_root' => true,
            'is_nestable' => false,
            'status' => Component::STATUS_ACTIVE,
        ]);
    }

    /**
     * State for nestable components.
     */
    public function nestable(): static
    {
        return $this->state([
            'is_root' => false,
            'is_nestable' => true,
        ]);
    }

    /**
     * State for active components.
     */
    public function active(): static
    {
        return $this->state([
            'status' => Component::STATUS_ACTIVE,
        ]);
    }

    /**
     * State for inactive components.
     */
    public function inactive(): static
    {
        return $this->state([
            'status' => Component::STATUS_INACTIVE,
        ]);
    }

    /**
     * State for deprecated components.
     */
    public function deprecated(): static
    {
        return $this->state([
            'status' => Component::STATUS_DEPRECATED,
            'version' => $this->faker->numberBetween(1, 2),
        ]);
    }

    /**
     * State for hero section component.
     */
    public function heroSection(): static
    {
        return $this->state(function () {
            $component = self::$componentSchemas['hero_section'];
            
            return [
                'name' => $component['name'],
                'technical_name' => 'hero_section',
                'description' => $component['description'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_root' => true,
                'is_nestable' => false,
            ];
        });
    }

    /**
     * State for text block component.
     */
    public function textBlock(): static
    {
        return $this->state(function () {
            $component = self::$componentSchemas['text_block'];
            
            return [
                'name' => $component['name'],
                'technical_name' => 'text_block',
                'description' => $component['description'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_nestable' => true,
            ];
        });
    }

    /**
     * State for image gallery component.
     */
    public function imageGallery(): static
    {
        return $this->state(function () {
            $component = self::$componentSchemas['image_gallery'];
            
            return [
                'name' => $component['name'],
                'technical_name' => 'image_gallery',
                'description' => $component['description'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_nestable' => true,
            ];
        });
    }

    /**
     * State for contact form component.
     */
    public function contactForm(): static
    {
        return $this->state(function () {
            $component = self::$componentSchemas['contact_form'];
            
            return [
                'name' => $component['name'],
                'technical_name' => 'contact_form',
                'description' => $component['description'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_nestable' => true,
            ];
        });
    }

    /**
     * State for components with rich schemas.
     */
    public function richSchema(): static
    {
        return $this->state(function () {
            $componentKey = $this->faker->randomElement(['hero_section', 'image_gallery', 'contact_form', 'pricing_table']);
            $component = self::$componentSchemas[$componentKey];
            
            return [
                'name' => $component['name'],
                'technical_name' => strtolower(str_replace(' ', '_', $component['name'])),
                'description' => $component['description'],
                'schema' => $component['schema'],
                'icon' => $component['icon'],
                'color' => $component['color'],
                'is_root' => $component['is_root'] ?? false,
                'is_nestable' => $component['is_nestable'] ?? true,
                'tabs' => $this->generateTabs($component['schema']),
                'preview_field' => $this->generatePreviewField($component['schema']),
            ];
        });
    }

    /**
     * State for simple components with minimal schemas.
     */
    public function simple(): static
    {
        return $this->state([
            'schema' => [
                'title' => [
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 100,
                    'label' => 'Title',
                ],
                'content' => [
                    'type' => 'textarea',
                    'required' => false,
                    'max_length' => 500,
                    'label' => 'Content',
                ],
            ],
            'tabs' => null,
            'preview_field' => [
                'field' => 'title',
                'fallback' => 'Untitled Component',
            ],
        ]);
    }
}
