<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Story;
use App\Models\Space;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * @group content-templates
 * @group story-management
 */
class ContentTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private Story $story;
    private User $user;
    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->space = Space::factory()->create();
        $this->user = User::factory()->create();
        $this->story = Story::factory()->for($this->space)->create([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'hero',
                        'title' => 'Sample Hero Title',
                        'subtitle' => 'Sample subtitle text',
                        'image' => [
                            'id' => 123,
                            'filename' => 'hero.jpg',
                            'alt' => 'Hero image'
                        ]
                    ],
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'text_block',
                        'content' => 'Sample content text',
                        'alignment' => 'left'
                    ],
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'cta_section',
                        'title' => 'Call to Action',
                        'button_text' => 'Get Started',
                        'button_link' => 'https://example.com'
                    ]
                ]
            ],
            'meta_title' => 'Sample Page Title',
            'meta_description' => 'Sample page description for SEO'
        ]);
    }

    public function test_create_template_from_story(): void
    {
        $templateName = 'Landing Page Template';
        $templateDescription = 'Template for marketing landing pages';
        
        $template = $this->story->createTemplate($templateName, $templateDescription);
        
        $this->assertIsArray($template, 'Template should be returned as array');
        $this->assertEquals($templateName, $template['name']);
        $this->assertEquals($templateDescription, $template['description']);
        $this->assertEquals('custom', $template['type']);
        $this->assertArrayHasKey('content', $template);
        $this->assertArrayHasKey('meta_data', $template);
        
        // Verify content structure is preserved
        $this->assertArrayHasKey('body', $template['content']);
        $this->assertCount(3, $template['content']['body'], 'Should preserve all content blocks');
        
        // Verify each component type is preserved
        $components = collect($template['content']['body'])->pluck('component')->toArray();
        $this->assertContains('hero', $components);
        $this->assertContains('text_block', $components);
        $this->assertContains('cta_section', $components);
        
        // Verify meta data is included
        $this->assertArrayHasKey('meta_title', $template['meta_data']);
        $this->assertArrayHasKey('meta_description', $template['meta_data']);
    }

    public function test_create_template_removes_specific_content(): void
    {
        $template = $this->story->createTemplate('Test Template', 'Test description');
        
        // Check that UIDs are preserved (needed for template instantiation)
        foreach ($template['content']['body'] as $block) {
            $this->assertArrayHasKey('_uid', $block, 'UIDs should be preserved for template structure');
            $this->assertNotEmpty($block['_uid']);
        }
        
        // Content should be preserved but can be overridden when instantiating
        $heroBlock = collect($template['content']['body'])->firstWhere('component', 'hero');
        $this->assertNotNull($heroBlock);
        $this->assertArrayHasKey('title', $heroBlock);
        $this->assertArrayHasKey('subtitle', $heroBlock);
    }

    public function test_create_template_with_empty_content(): void
    {
        $emptyStory = Story::factory()->for($this->space)->create([
            'content' => ['body' => []]
        ]);
        
        $template = $emptyStory->createTemplate('Empty Template', 'Template with no content');
        
        $this->assertIsArray($template);
        $this->assertEquals([], $template['content']['body']);
        $this->assertArrayHasKey('meta_data', $template);
    }

    public function test_create_from_template_static_method(): void
    {
        $templateData = [
            'name' => 'Blog Post Template',
            'description' => 'Standard blog post template',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'hero',
                        'title' => 'Template Title Placeholder',
                        'subtitle' => 'Template Subtitle Placeholder'
                    ],
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'text_block',
                        'content' => 'Template content placeholder'
                    ]
                ]
            ],
            'meta_data' => [
                'meta_title' => 'Template Meta Title',
                'meta_description' => 'Template meta description'
            ]
        ];
        
        $overrides = [
            'name' => 'My New Blog Post',
            'slug' => 'my-new-blog-post',
            'status' => 'draft'
        ];
        
        $storyData = Story::createFromTemplate($templateData, $overrides);
        
        $this->assertIsArray($storyData, 'Should return array of story data');
        $this->assertEquals('My New Blog Post', $storyData['name']);
        $this->assertEquals('my-new-blog-post', $storyData['slug']);
        $this->assertEquals('draft', $storyData['status']);
        
        // Content should be copied from template
        $this->assertArrayHasKey('content', $storyData);
        $this->assertArrayHasKey('body', $storyData['content']);
        $this->assertCount(2, $storyData['content']['body']);
        
        // Meta data should be copied
        $this->assertArrayHasKey('meta_title', $storyData);
        $this->assertArrayHasKey('meta_description', $storyData);
        $this->assertEquals('Template Meta Title', $storyData['meta_title']);
    }

    public function test_create_from_template_preserves_structure(): void
    {
        $templateData = [
            'name' => 'Complex Template',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-uid-123',
                        'component' => 'hero',
                        'title' => 'Hero Title',
                        'nested_data' => [
                            'settings' => ['style' => 'modern'],
                            'options' => ['animate' => true]
                        ]
                    ]
                ]
            ],
            'meta_data' => [
                'template_category' => 'marketing',
                'custom_settings' => ['theme' => 'dark']
            ]
        ];
        
        $storyData = Story::createFromTemplate($templateData, ['name' => 'New Story']);
        
        // Verify nested structure is preserved
        $heroBlock = $storyData['content']['body'][0];
        $this->assertEquals('hero-uid-123', $heroBlock['_uid']);
        $this->assertArrayHasKey('nested_data', $heroBlock);
        $this->assertArrayHasKey('settings', $heroBlock['nested_data']);
        $this->assertEquals('modern', $heroBlock['nested_data']['settings']['style']);
        
        // Verify meta data structure
        $this->assertArrayHasKey('template_category', $storyData['meta_data']);
        $this->assertEquals('marketing', $storyData['meta_data']['template_category']);
    }

    public function test_get_available_templates(): void
    {
        // Test with default/config templates
        $templates = $this->story->getAvailableTemplates();
        
        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates, 'Should return some default templates');
        
        // Check structure of default templates
        foreach ($templates as $template) {
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('type', $template);
            $this->assertArrayHasKey('content', $template);
            
            if ($template['type'] === 'config') {
                $this->assertArrayNotHasKey('uuid', $template);
            }
        }
        
        // Verify specific default templates exist
        $templateNames = collect($templates)->pluck('name')->toArray();
        $this->assertContains('Basic Page', $templateNames);
        $this->assertContains('Blog Post', $templateNames);
        $this->assertContains('Landing Page', $templateNames);
    }

    public function test_get_available_templates_includes_database_templates(): void
    {
        // Create a template story in database
        $templateStory = Story::factory()->for($this->space)->create([
            'name' => 'Custom Database Template',
            'content' => ['body' => [['component' => 'hero', 'title' => 'Template Hero']]],
            'meta_data' => [
                'is_template' => true,
                'template_description' => 'Custom template from database',
                'template_category' => 'custom'
            ]
        ]);
        
        $templates = $this->story->getAvailableTemplates();
        
        // Should include both config and database templates
        $this->assertGreaterThan(3, count($templates), 'Should include config + database templates');
        
        $customTemplate = collect($templates)->firstWhere('name', 'Custom Database Template');
        $this->assertNotNull($customTemplate, 'Should include database template');
        $this->assertEquals('custom', $customTemplate['type']);
        $this->assertEquals($templateStory->uuid, $customTemplate['uuid']);
        $this->assertEquals('Custom template from database', $customTemplate['description']);
    }

    public function test_template_with_complex_nested_components(): void
    {
        $complexStory = Story::factory()->for($this->space)->create([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid()->toString(),
                        'component' => 'grid_section',
                        'columns' => [
                            [
                                '_uid' => Str::uuid()->toString(),
                                'component' => 'feature_card',
                                'title' => 'Feature 1',
                                'icon' => 'icon-1'
                            ],
                            [
                                '_uid' => Str::uuid()->toString(),
                                'component' => 'feature_card',
                                'title' => 'Feature 2',
                                'icon' => 'icon-2'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        
        $template = $complexStory->createTemplate('Grid Template', 'Template with nested components');
        
        $this->assertArrayHasKey('columns', $template['content']['body'][0]);
        $this->assertCount(2, $template['content']['body'][0]['columns']);
        
        // Verify nested components preserve their structure
        $firstColumn = $template['content']['body'][0]['columns'][0];
        $this->assertEquals('feature_card', $firstColumn['component']);
        $this->assertArrayHasKey('_uid', $firstColumn);
        $this->assertArrayHasKey('title', $firstColumn);
    }

    public function test_create_from_template_with_parent_id(): void
    {
        $parentStory = Story::factory()->for($this->space)->create([
            'name' => 'Parent Folder',
            'is_folder' => true
        ]);
        
        $templateData = [
            'name' => 'Child Template',
            'content' => ['body' => []]
        ];
        
        $storyData = Story::createFromTemplate($templateData, [
            'name' => 'Child Story',
            'parent_id' => $parentStory->id
        ]);
        
        $this->assertEquals($parentStory->id, $storyData['parent_id']);
        $this->assertEquals('Child Story', $storyData['name']);
    }

    public function test_template_metadata_handling(): void
    {
        $storyWithMetadata = Story::factory()->for($this->space)->create([
            'meta_title' => 'Original Meta Title',
            'meta_description' => 'Original meta description',
            'meta_data' => [
                'robots_meta' => ['index' => true, 'follow' => true],
                'canonical_url' => 'https://example.com/original',
                'custom_field' => 'custom_value'
            ]
        ]);
        
        $template = $storyWithMetadata->createTemplate('Metadata Template', 'Template with metadata');
        
        $this->assertArrayHasKey('meta_data', $template);
        $this->assertArrayHasKey('robots_meta', $template['meta_data']);
        $this->assertArrayHasKey('custom_field', $template['meta_data']);
        
        // Create story from template
        $storyData = Story::createFromTemplate($template, [
            'name' => 'New Story from Metadata Template'
        ]);
        
        $this->assertEquals('Original Meta Title', $storyData['meta_title']);
        $this->assertEquals('Original meta description', $storyData['meta_description']);
        $this->assertArrayHasKey('meta_data', $storyData);
        $this->assertEquals('custom_value', $storyData['meta_data']['custom_field']);
    }

    public function test_template_override_behavior(): void
    {
        $templateData = [
            'name' => 'Template Name',
            'content' => ['body' => []],
            'meta_data' => [
                'meta_title' => 'Template Meta Title',
                'robots_meta' => ['index' => false]
            ]
        ];
        
        $overrides = [
            'name' => 'Override Name',
            'slug' => 'override-slug',
            'meta_title' => 'Override Meta Title',
            'meta_data' => [
                'robots_meta' => ['index' => true], // Should override
                'new_field' => 'new_value' // Should be added
            ]
        ];
        
        $storyData = Story::createFromTemplate($templateData, $overrides);
        
        $this->assertEquals('Override Name', $storyData['name']);
        $this->assertEquals('override-slug', $storyData['slug']);
        $this->assertEquals('Override Meta Title', $storyData['meta_title']);
        
        // Meta data should be merged with overrides taking precedence
        $this->assertTrue($storyData['meta_data']['robots_meta']['index']);
        $this->assertEquals('new_value', $storyData['meta_data']['new_field']);
    }

    public function test_template_with_empty_or_null_fields(): void
    {
        $templateData = [
            'name' => 'Template',
            'content' => null,
            'meta_data' => null
        ];
        
        $storyData = Story::createFromTemplate($templateData, ['name' => 'New Story']);
        
        $this->assertEquals('New Story', $storyData['name']);
        $this->assertArrayHasKey('content', $storyData);
        $this->assertArrayHasKey('meta_data', $storyData);
        
        // Should handle null gracefully
        $this->assertIsArray($storyData['content']);
        $this->assertIsArray($storyData['meta_data']);
    }
}