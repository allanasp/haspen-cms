<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Management;

use App\Models\Component;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * @group api-story-management
 * @group story-management
 */
class StoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Space $space;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
        
        // Add user to space
        $this->space->users()->attach($this->user->id, [
            'role_id' => 1, // Assume admin role
            'custom_permissions' => []
        ]);
        
        $this->baseUrl = "/api/v1/spaces/{$this->space->uuid}";
        
        // Create test components
        Component::factory()->for($this->space)->create([
            'technical_name' => 'hero',
            'schema' => [
                'title' => ['type' => 'text', 'required' => true],
                'subtitle' => ['type' => 'text', 'required' => false]
            ]
        ]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_stories_with_pagination(): void
    {
        Story::factory()->for($this->space)->count(15)->create([
            'status' => 'published'
        ]);
        
        $response = $this->getJson("{$this->baseUrl}/stories");
        
        $response->assertOk()
            ->assertJsonStructure([
                'stories' => [
                    '*' => ['id', 'name', 'slug', 'status', 'created_at', 'updated_at']
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ])
            ->assertJsonPath('meta.total', 15);
    }

    public function test_can_search_stories_by_name(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Blog Post About Technology',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Marketing Article',
            'status' => 'published'
        ]);
        
        $response = $this->getJson("{$this->baseUrl}/stories?search=blog");
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('stories.0.name', 'Blog Post About Technology');
    }

    public function test_can_search_stories_with_advanced_filters(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Tech Blog',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Technology Solutions'
                    ]
                ]
            ],
            'status' => 'published',
            'language' => 'en'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Marketing Page',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Marketing content'
                    ]
                ]
            ],
            'status' => 'published',
            'language' => 'en'
        ]);
        
        $response = $this->getJson("{$this->baseUrl}/stories?" . http_build_query([
            'search' => 'technology',
            'search_mode' => 'comprehensive',
            'search_components' => ['hero'],
            'language' => 'en'
        ]));
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('stories.0.name', 'Tech Blog');
    }

    public function test_can_create_story(): void
    {
        $storyData = [
            'story' => [
                'name' => 'New Test Story',
                'slug' => 'new-test-story',
                'content' => [
                    'body' => [
                        [
                            '_uid' => Str::uuid(),
                            'component' => 'hero',
                            'title' => 'Hero Title',
                            'subtitle' => 'Hero Subtitle'
                        ]
                    ]
                ],
                'status' => 'draft',
                'language' => 'en',
                'meta_title' => 'Test Meta Title',
                'meta_description' => 'Test meta description'
            ]
        ];
        
        $response = $this->postJson("{$this->baseUrl}/stories", $storyData);
        
        $response->assertCreated()
            ->assertJsonPath('story.name', 'New Test Story')
            ->assertJsonPath('story.slug', 'new-test-story')
            ->assertJsonPath('story.status', 'draft');
        
        $this->assertDatabaseHas('stories', [
            'name' => 'New Test Story',
            'slug' => 'new-test-story',
            'space_id' => $this->space->id
        ]);
    }

    public function test_create_story_validates_content(): void
    {
        $storyData = [
            'story' => [
                'name' => 'Invalid Story',
                'content' => [
                    'body' => [
                        [
                            '_uid' => Str::uuid(),
                            'component' => 'hero',
                            // Missing required 'title' field
                            'subtitle' => 'Subtitle only'
                        ]
                    ]
                ],
                'status' => 'draft'
            ]
        ];
        
        $response = $this->postJson("{$this->baseUrl}/stories", $storyData);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['story.content']);
    }

    public function test_can_update_story(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'name' => 'Original Name',
            'status' => 'draft'
        ]);
        
        $updateData = [
            'story' => [
                'name' => 'Updated Name',
                'status' => 'published',
                'meta_title' => 'Updated Meta Title'
            ]
        ];
        
        $response = $this->putJson("{$this->baseUrl}/stories/{$story->uuid}", $updateData);
        
        $response->assertOk()
            ->assertJsonPath('story.name', 'Updated Name')
            ->assertJsonPath('story.status', 'published');
        
        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'name' => 'Updated Name',
            'status' => 'published'
        ]);
    }

    public function test_can_delete_story(): void
    {
        $story = Story::factory()->for($this->space)->create();
        
        $response = $this->deleteJson("{$this->baseUrl}/stories/{$story->uuid}");
        
        $response->assertNoContent();
        
        $this->assertSoftDeleted('stories', ['id' => $story->id]);
    }

    // Content Locking Tests
    
    public function test_can_lock_story(): void
    {
        $story = Story::factory()->for($this->space)->create();
        
        $response = $this->postJson(
            "{$this->baseUrl}/stories/{$story->uuid}/lock",
            ['duration_minutes' => 30],
            ['X-Session-ID' => 'test-session-123']
        );
        
        $response->assertOk()
            ->assertJsonStructure([
                'lock_info' => [
                    'locked_by',
                    'locked_at',
                    'lock_expires_at',
                    'session_id',
                    'locker' => ['id', 'name', 'email'],
                    'time_remaining'
                ]
            ])
            ->assertJsonPath('lock_info.locked_by', $this->user->id)
            ->assertJsonPath('lock_info.session_id', 'test-session-123');
        
        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'locked_by' => $this->user->id,
            'lock_session_id' => 'test-session-123'
        ]);
    }

    public function test_cannot_lock_already_locked_story(): void
    {
        $otherUser = User::factory()->create();
        $story = Story::factory()->for($this->space)->create();
        
        // First user locks the story
        $story->lock($otherUser, 'other-session', 30);
        
        // Current user tries to lock
        $response = $this->postJson(
            "{$this->baseUrl}/stories/{$story->uuid}/lock",
            ['duration_minutes' => 30],
            ['X-Session-ID' => 'test-session-123']
        );
        
        $response->assertStatus(409)
            ->assertJsonPath('error', 'Story is already locked')
            ->assertJsonStructure([
                'lock_info' => [
                    'locked_by',
                    'locker' => ['name', 'email'],
                    'time_remaining'
                ]
            ]);
    }

    public function test_can_unlock_own_story(): void
    {
        $story = Story::factory()->for($this->space)->create();
        
        // Lock the story first
        $story->lock($this->user, 'test-session-123', 30);
        
        $response = $this->deleteJson(
            "{$this->baseUrl}/stories/{$story->uuid}/lock",
            [],
            ['X-Session-ID' => 'test-session-123']
        );
        
        $response->assertOk()
            ->assertJsonPath('success', true);
        
        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'locked_by' => null,
            'lock_session_id' => null
        ]);
    }

    public function test_can_extend_lock(): void
    {
        $story = Story::factory()->for($this->space)->create();
        
        // Lock the story first
        $story->lock($this->user, 'test-session-123', 30);
        $originalExpiration = $story->lock_expires_at;
        
        $response = $this->putJson(
            "{$this->baseUrl}/stories/{$story->uuid}/lock",
            ['extend_minutes' => 15],
            ['X-Session-ID' => 'test-session-123']
        );
        
        $response->assertOk()
            ->assertJsonStructure(['lock_info']);
        
        $story->refresh();
        $this->assertTrue($story->lock_expires_at->greaterThan($originalExpiration));
    }

    public function test_can_get_lock_status(): void
    {
        $story = Story::factory()->for($this->space)->create();
        
        // Test unlocked story
        $response = $this->getJson("{$this->baseUrl}/stories/{$story->uuid}/lock");
        
        $response->assertOk()
            ->assertJsonPath('is_locked', false)
            ->assertJsonPath('lock_info', null);
        
        // Lock the story
        $story->lock($this->user, 'test-session-123', 30);
        
        // Test locked story
        $response = $this->getJson("{$this->baseUrl}/stories/{$story->uuid}/lock");
        
        $response->assertOk()
            ->assertJsonPath('is_locked', true)
            ->assertJsonStructure([
                'lock_info' => [
                    'locked_by',
                    'time_remaining',
                    'locker' => ['name']
                ]
            ]);
    }

    // Content Templates Tests
    
    public function test_can_get_available_templates(): void
    {
        // Create a template story in database
        Story::factory()->for($this->space)->create([
            'name' => 'Custom Template',
            'content' => ['body' => []],
            'meta_data' => [
                'is_template' => true,
                'template_description' => 'Custom template description'
            ]
        ]);
        
        $response = $this->getJson("{$this->baseUrl}/stories/templates");
        
        $response->assertOk()
            ->assertJsonStructure([
                'templates' => [
                    '*' => [
                        'name',
                        'description',
                        'type',
                        'content'
                    ]
                ]
            ]);
        
        $templates = $response->json('templates');
        $this->assertGreaterThan(0, count($templates));
        
        // Should include both config and database templates
        $templateTypes = array_column($templates, 'type');
        $this->assertContains('config', $templateTypes);
        $this->assertContains('custom', $templateTypes);
    }

    public function test_can_create_template_from_story(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Template Hero',
                        'subtitle' => 'Template Subtitle'
                    ]
                ]
            ]
        ]);
        
        $response = $this->postJson(
            "{$this->baseUrl}/stories/{$story->uuid}/create-template",
            [
                'name' => 'My Custom Template',
                'description' => 'Template for landing pages',
                'save_to_database' => true
            ]
        );
        
        $response->assertCreated()
            ->assertJsonPath('template.name', 'My Custom Template')
            ->assertJsonPath('template.description', 'Template for landing pages')
            ->assertJsonPath('template.type', 'custom')
            ->assertJsonStructure([
                'template' => [
                    'name',
                    'description',
                    'type',
                    'content',
                    'uuid'
                ]
            ]);
        
        // Should create a template story in database
        $this->assertDatabaseHas('stories', [
            'name' => 'My Custom Template',
            'space_id' => $this->space->id
        ]);
    }

    public function test_can_create_story_from_template(): void
    {
        // Create a template story
        $templateStory = Story::factory()->for($this->space)->create([
            'name' => 'Blog Template',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'template-hero-uid',
                        'component' => 'hero',
                        'title' => 'Template Title',
                        'subtitle' => 'Template Subtitle'
                    ]
                ]
            ],
            'meta_data' => [
                'is_template' => true,
                'template_description' => 'Blog post template'
            ]
        ]);
        
        $response = $this->postJson(
            "{$this->baseUrl}/stories/from-template",
            [
                'template_uuid' => $templateStory->uuid,
                'story_name' => 'My New Blog Post',
                'story_slug' => 'my-new-blog-post'
            ]
        );
        
        $response->assertCreated()
            ->assertJsonPath('story.name', 'My New Blog Post')
            ->assertJsonPath('story.slug', 'my-new-blog-post')
            ->assertJsonStructure([
                'story' => [
                    'id', 'name', 'slug', 'content', 'status'
                ]
            ]);
        
        $this->assertDatabaseHas('stories', [
            'name' => 'My New Blog Post',
            'slug' => 'my-new-blog-post',
            'space_id' => $this->space->id
        ]);
        
        // Verify content structure is copied
        $newStory = Story::where('slug', 'my-new-blog-post')->first();
        $this->assertNotNull($newStory);
        $this->assertEquals('template-hero-uid', $newStory->content['body'][0]['_uid']);
    }

    // Search Features Tests
    
    public function test_can_get_search_suggestions(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Technology Blog Post',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Technical Documentation',
            'status' => 'published'
        ]);
        
        $response = $this->getJson("{$this->baseUrl}/stories/search/suggestions?q=tech&limit=5");
        
        $response->assertOk()
            ->assertJsonStructure([
                'suggestions' => [
                    '*' => [
                        'type',
                        'value'
                    ]
                ]
            ]);
        
        $suggestions = $response->json('suggestions');
        $this->assertLessThanOrEqual(5, count($suggestions));
        
        // All suggestions should contain the search term
        foreach ($suggestions as $suggestion) {
            $this->assertStringContainsStringIgnoringCase('tech', $suggestion['value']);
        }
    }

    public function test_can_get_search_stats(): void
    {
        Story::factory()->for($this->space)->count(5)->create(['status' => 'published']);
        Story::factory()->for($this->space)->count(3)->create(['status' => 'draft']);
        
        $response = $this->getJson("{$this->baseUrl}/stories/search/stats");
        
        $response->assertOk()
            ->assertJsonStructure([
                'total_stories',
                'published_stories',
                'draft_stories',
                'languages',
                'recent_stories',
                'popular_components'
            ])
            ->assertJsonPath('published_stories', 5)
            ->assertJsonPath('draft_stories', 3);
    }

    // Translation Tests
    
    public function test_can_create_translation(): void
    {
        $originalStory = Story::factory()->for($this->space)->create([
            'name' => 'English Story',
            'language' => 'en',
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-123',
                        'component' => 'hero',
                        'title' => 'English Title',
                        'subtitle' => 'English Subtitle'
                    ]
                ]
            ]
        ]);
        
        $response = $this->postJson(
            "{$this->baseUrl}/stories/{$originalStory->uuid}/translations",
            [
                'language' => 'es',
                'name' => 'Historia en Español',
                'slug' => 'historia-espanol',
                'content' => [
                    'body' => [
                        [
                            '_uid' => 'hero-123', // Same UID
                            'component' => 'hero',
                            'title' => 'Título en Español',
                            'subtitle' => 'Subtítulo en Español'
                        ]
                    ]
                ]
            ]
        );
        
        $response->assertCreated()
            ->assertJsonPath('translation.language', 'es')
            ->assertJsonPath('translation.name', 'Historia en Español')
            ->assertJsonStructure([
                'translation' => [
                    'id', 'name', 'slug', 'language', 'content'
                ]
            ]);
        
        $this->assertDatabaseHas('stories', [
            'name' => 'Historia en Español',
            'language' => 'es',
            'translation_group_id' => $originalStory->id,
            'space_id' => $this->space->id
        ]);
    }

    public function test_can_get_all_translations(): void
    {
        $originalStory = Story::factory()->for($this->space)->create([
            'language' => 'en'
        ]);
        
        $spanishStory = $originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => ['body' => []]
        ], $this->user);
        
        $response = $this->getJson("{$this->baseUrl}/stories/{$originalStory->uuid}/translations");
        
        $response->assertOk()
            ->assertJsonStructure([
                'translations' => [
                    '*' => [
                        'id', 'language', 'name', 'status'
                    ]
                ]
            ]);
        
        $translations = $response->json('translations');
        $this->assertCount(2, $translations); // Original + Spanish
        
        $languages = array_column($translations, 'language');
        $this->assertContains('en', $languages);
        $this->assertContains('es', $languages);
    }

    public function test_can_get_translation_status(): void
    {
        $originalStory = Story::factory()->for($this->space)->create([
            'language' => 'en'
        ]);
        
        $spanishStory = $originalStory->createTranslation('es', [
            'name' => 'Spanish Story',
            'slug' => 'spanish-story',
            'content' => ['body' => []]
        ], $this->user);
        
        $response = $this->getJson("{$this->baseUrl}/stories/{$originalStory->uuid}/translation-status");
        
        $response->assertOk()
            ->assertJsonStructure([
                'en' => [
                    'uuid',
                    'status',
                    'last_updated',
                    'word_count',
                    'completion_percentage',
                    'needs_sync'
                ],
                'es' => [
                    'uuid',
                    'status', 
                    'last_updated',
                    'word_count',
                    'completion_percentage',
                    'needs_sync'
                ]
            ]);
        
        $status = $response->json();
        $this->assertEquals(100, $status['en']['completion_percentage']);
        $this->assertIsInt($status['es']['completion_percentage']);
    }

    // Bulk Operations Tests
    
    public function test_can_bulk_publish_stories(): void
    {
        $stories = Story::factory()->for($this->space)->count(3)->create([
            'status' => 'draft'
        ]);
        
        $storyIds = $stories->pluck('uuid')->toArray();
        
        $response = $this->postJson(
            "{$this->baseUrl}/stories/bulk-publish",
            [
                'story_ids' => $storyIds,
                'published_at' => now()->toISOString()
            ]
        );
        
        $response->assertOk()
            ->assertJsonPath('published_count', 3);
        
        foreach ($stories as $story) {
            $this->assertDatabaseHas('stories', [
                'id' => $story->id,
                'status' => 'published'
            ]);
        }
    }

    public function test_can_bulk_delete_stories(): void
    {
        $stories = Story::factory()->for($this->space)->count(3)->create();
        $storyIds = $stories->pluck('uuid')->toArray();
        
        $response = $this->deleteJson(
            "{$this->baseUrl}/stories/bulk-delete",
            ['story_ids' => $storyIds]
        );
        
        $response->assertOk()
            ->assertJsonPath('deleted_count', 3);
        
        foreach ($stories as $story) {
            $this->assertSoftDeleted('stories', ['id' => $story->id]);
        }
    }

    public function test_unauthorized_user_cannot_access_management_api(): void
    {
        Sanctum::actingAs(User::factory()->create()); // Different user, not in space
        
        $response = $this->getJson("{$this->baseUrl}/stories");
        
        $response->assertForbidden();
    }
}