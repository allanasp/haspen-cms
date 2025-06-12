<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Cdn;

use App\Models\Space;
use App\Models\Story;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * @group cdn-api
 * @group story-management
 */
class ContentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->space = Space::factory()->create([
            'slug' => 'test-space'
        ]);
    }

    public function test_can_list_published_stories(): void
    {
        // Create published stories
        Story::factory()->for($this->space)->count(5)->create([
            'status' => 'published',
            'published_at' => now()
        ]);
        
        // Create draft stories (should not appear)
        Story::factory()->for($this->space)->count(3)->create([
            'status' => 'draft'
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonStructure([
                'stories' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'content',
                        'published_at',
                        'full_slug'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ])
            ->assertJsonPath('meta.total', 5); // Only published stories
        
        // Verify all returned stories are published
        $stories = $response->json('stories');
        foreach ($stories as $story) {
            $this->assertNotNull($story['published_at']);
        }
    }

    public function test_can_get_story_by_slug(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'name' => 'Test Story',
            'slug' => 'test-story',
            'status' => 'published',
            'published_at' => now(),
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Hero Title',
                        'subtitle' => 'Hero Subtitle'
                    ],
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Some text content'
                    ]
                ]
            ],
            'meta_title' => 'Test Meta Title',
            'meta_description' => 'Test meta description'
        ]);
        
        $response = $this->getJson("/api/v1/cdn/stories/{$story->slug}", [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('story.name', 'Test Story')
            ->assertJsonPath('story.slug', 'test-story')
            ->assertJsonPath('story.meta_title', 'Test Meta Title')
            ->assertJsonStructure([
                'story' => [
                    'id',
                    'name',
                    'slug',
                    'content' => [
                        'body' => [
                            '*' => [
                                '_uid',
                                'component'
                            ]
                        ]
                    ],
                    'published_at',
                    'meta_title',
                    'meta_description'
                ]
            ]);
        
        // Verify content structure
        $content = $response->json('story.content');
        $this->assertCount(2, $content['body']);
        $this->assertEquals('hero', $content['body'][0]['component']);
        $this->assertEquals('text_block', $content['body'][1]['component']);
    }

    public function test_cannot_get_draft_story_via_cdn(): void
    {
        $draftStory = Story::factory()->for($this->space)->create([
            'slug' => 'draft-story',
            'status' => 'draft'
        ]);
        
        $response = $this->getJson("/api/v1/cdn/stories/{$draftStory->slug}", [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertNotFound();
    }

    public function test_can_filter_stories_by_slug_prefix(): void
    {
        // Create stories with different slug patterns
        Story::factory()->for($this->space)->create([
            'slug' => 'blog/post-1',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'blog/post-2', 
            'status' => 'published',
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'page/about',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories?starts_with=blog/', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
        
        $stories = $response->json('stories');
        foreach ($stories as $story) {
            $this->assertStringStartsWith('blog/', $story['slug']);
        }
    }

    public function test_can_filter_stories_by_specific_slugs(): void
    {
        Story::factory()->for($this->space)->create([
            'slug' => 'story-1',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'story-2',
            'status' => 'published', 
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'story-3',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories?by_slugs=story-1,story-3', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
        
        $returnedSlugs = array_column($response->json('stories'), 'slug');
        $this->assertContains('story-1', $returnedSlugs);
        $this->assertContains('story-3', $returnedSlugs);
        $this->assertNotContains('story-2', $returnedSlugs);
    }

    public function test_can_exclude_stories_by_slugs(): void
    {
        Story::factory()->for($this->space)->create([
            'slug' => 'include-1',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'exclude-1',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        Story::factory()->for($this->space)->create([
            'slug' => 'include-2',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories?excluding_slugs=exclude-1', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
        
        $returnedSlugs = array_column($response->json('stories'), 'slug');
        $this->assertContains('include-1', $returnedSlugs);
        $this->assertContains('include-2', $returnedSlugs);
        $this->assertNotContains('exclude-1', $returnedSlugs);
    }

    public function test_can_sort_stories(): void
    {
        $story1 = Story::factory()->for($this->space)->create([
            'name' => 'A Story',
            'status' => 'published',
            'published_at' => now()->subDays(2)
        ]);
        
        $story2 = Story::factory()->for($this->space)->create([
            'name' => 'B Story',
            'status' => 'published',
            'published_at' => now()->subDays(1)
        ]);
        
        $story3 = Story::factory()->for($this->space)->create([
            'name' => 'C Story',
            'status' => 'published',
            'published_at' => now()
        ]);
        
        // Test sort by published_at desc (most recent first)
        $response = $this->getJson('/api/v1/cdn/stories?sort_by=published_at&sort_order=desc', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk();
        
        $stories = $response->json('stories');
        $this->assertEquals($story3->id, $stories[0]['id']);
        $this->assertEquals($story2->id, $stories[1]['id']);
        $this->assertEquals($story1->id, $stories[2]['id']);
        
        // Test sort by name asc
        $response = $this->getJson('/api/v1/cdn/stories?sort_by=name&sort_order=asc', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk();
        
        $stories = $response->json('stories');
        $this->assertEquals('A Story', $stories[0]['name']);
        $this->assertEquals('B Story', $stories[1]['name']);
        $this->assertEquals('C Story', $stories[2]['name']);
    }

    public function test_pagination_works_correctly(): void
    {
        Story::factory()->for($this->space)->count(30)->create([
            'status' => 'published',
            'published_at' => now()
        ]);
        
        // Test first page
        $response = $this->getJson('/api/v1/cdn/stories?page=1&per_page=10', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 3);
        
        $this->assertCount(10, $response->json('stories'));
        
        // Test last page
        $response = $this->getJson('/api/v1/cdn/stories?page=3&per_page=10', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.current_page', 3);
        
        $this->assertCount(10, $response->json('stories'));
    }

    public function test_respects_per_page_limits(): void
    {
        Story::factory()->for($this->space)->count(50)->create([
            'status' => 'published',
            'published_at' => now()
        ]);
        
        // Test max per_page limit (should be capped at 100)
        $response = $this->getJson('/api/v1/cdn/stories?per_page=150', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100); // Should be capped
        
        // Test reasonable per_page
        $response = $this->getJson('/api/v1/cdn/stories?per_page=5', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('meta.per_page', 5);
        
        $this->assertCount(5, $response->json('stories'));
    }

    public function test_story_contains_full_slug_path(): void
    {
        // Create hierarchical stories
        $parentFolder = Story::factory()->for($this->space)->create([
            'name' => 'Blog',
            'slug' => 'blog',
            'is_folder' => true,
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $childStory = Story::factory()->for($this->space)->create([
            'name' => 'My Post',
            'slug' => 'my-post',
            'parent_id' => $parentFolder->id,
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk();
        
        $stories = $response->json('stories');
        $childStoryData = collect($stories)->firstWhere('slug', 'my-post');
        
        $this->assertNotNull($childStoryData);
        $this->assertEquals('blog/my-post', $childStoryData['full_slug']);
    }

    public function test_stories_include_proper_metadata(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'status' => 'published',
            'published_at' => now(),
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'language' => 'en'
        ]);
        
        $response = $this->getJson("/api/v1/cdn/stories/{$story->slug}", [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertJsonPath('story.meta_title', 'SEO Title')
            ->assertJsonPath('story.meta_description', 'SEO Description')
            ->assertJsonPath('story.language', 'en')
            ->assertJsonStructure([
                'story' => [
                    'id',
                    'name',
                    'slug',
                    'content',
                    'language',
                    'published_at',
                    'meta_title',
                    'meta_description'
                ]
            ]);
    }

    public function test_invalid_space_returns_error(): void
    {
        $response = $this->getJson('/api/v1/cdn/stories', [
            'X-Space-ID' => 'invalid-space-id'
        ]);
        
        $response->assertStatus(404);
    }

    public function test_missing_space_header_returns_error(): void
    {
        $response = $this->getJson('/api/v1/cdn/stories');
        
        $response->assertStatus(400)
            ->assertJsonPath('error', 'Space ID is required');
    }

    public function test_content_is_properly_structured(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'status' => 'published',
            'published_at' => now(),
            'content' => [
                'body' => [
                    [
                        '_uid' => 'hero-123',
                        'component' => 'hero',
                        'title' => 'Main Title',
                        'subtitle' => 'Subtitle text',
                        'settings' => [
                            'background_color' => '#ffffff',
                            'text_align' => 'center'
                        ]
                    ],
                    [
                        '_uid' => 'text-456',
                        'component' => 'text_block',
                        'content' => 'Rich text content here',
                        'formatting' => [
                            'font_size' => 'large',
                            'color' => '#333333'
                        ]
                    ]
                ]
            ]
        ]);
        
        $response = $this->getJson("/api/v1/cdn/stories/{$story->slug}", [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk();
        
        $content = $response->json('story.content');
        
        // Verify content structure is preserved
        $this->assertArrayHasKey('body', $content);
        $this->assertCount(2, $content['body']);
        
        $heroComponent = $content['body'][0];
        $this->assertEquals('hero-123', $heroComponent['_uid']);
        $this->assertEquals('hero', $heroComponent['component']);
        $this->assertEquals('Main Title', $heroComponent['title']);
        $this->assertArrayHasKey('settings', $heroComponent);
        $this->assertEquals('#ffffff', $heroComponent['settings']['background_color']);
        
        $textComponent = $content['body'][1];
        $this->assertEquals('text-456', $textComponent['_uid']);
        $this->assertEquals('text_block', $textComponent['component']);
        $this->assertArrayHasKey('formatting', $textComponent);
    }

    public function test_cdn_api_rate_limiting_headers(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'status' => 'published',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/cdn/stories', [
            'X-Space-ID' => $this->space->uuid
        ]);
        
        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');
        
        // CDN API should have 60 requests per minute limit
        $this->assertEquals('60', $response->headers->get('X-RateLimit-Limit'));
    }
}