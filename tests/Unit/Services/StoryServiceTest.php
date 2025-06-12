<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Component;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * @group story-service
 * @group story-management
 */
class StoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private StoryService $storyService;
    private Space $space;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->storyService = app(StoryService::class);
        $this->space = Space::factory()->create();
        $this->user = User::factory()->create();
        
        // Create some test components
        Component::factory()->for($this->space)->create([
            'technical_name' => 'hero',
            'schema' => [
                'title' => ['type' => 'text', 'required' => true],
                'subtitle' => ['type' => 'text', 'required' => false]
            ]
        ]);
        
        Component::factory()->for($this->space)->create([
            'technical_name' => 'text_block',
            'schema' => [
                'content' => ['type' => 'textarea', 'required' => true]
            ]
        ]);
    }

    public function test_get_paginated_stories_returns_paginator(): void
    {
        // Create test stories
        Story::factory()->for($this->space)->count(15)->create([
            'status' => 'published'
        ]);
        
        $result = $this->storyService->getPaginatedStories($this->space);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(15, $result->total());
        $this->assertLessThanOrEqual(25, $result->count()); // Default per_page
    }

    public function test_get_paginated_stories_with_basic_search(): void
    {
        // Create stories with specific names
        Story::factory()->for($this->space)->create([
            'name' => 'Blog Post About Technology',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Product Launch Announcement',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Another Blog Post',
            'status' => 'published'
        ]);
        
        $filters = ['search' => 'blog'];
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(2, $result->total());
        
        // Check that returned stories contain the search term
        foreach ($result->items() as $story) {
            $this->assertStringContainsStringIgnoringCase('blog', $story->name);
        }
    }

    public function test_get_paginated_stories_with_exact_search_mode(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Exact Match Test',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Exact Test Match',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Different Content',
            'status' => 'published'
        ]);
        
        $filters = [
            'search' => 'Exact Match',
            'search_mode' => 'exact'
        ];
        
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Exact Match Test', $result->first()->name);
    }

    public function test_get_paginated_stories_with_content_search(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Story 1',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Welcome to our platform',
                        'subtitle' => 'Best technology solutions'
                    ]
                ]
            ],
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Story 2',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Learn about our marketing services'
                    ]
                ]
            ],
            'status' => 'published'
        ]);
        
        $filters = [
            'search' => 'technology',
            'search_mode' => 'content_only'
        ];
        
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Story 1', $result->first()->name);
    }

    public function test_get_paginated_stories_with_component_filter(): void
    {
        Story::factory()->for($this->space)->create([
            'name' => 'Hero Story',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Hero Title'
                    ]
                ]
            ],
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Text Story',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Text content'
                    ]
                ]
            ],
            'status' => 'published'
        ]);
        
        $filters = ['search_components' => ['hero']];
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals('Hero Story', $result->first()->name);
    }

    public function test_get_paginated_stories_with_multiple_filters(): void
    {
        $heroStory = Story::factory()->for($this->space)->create([
            'name' => 'Marketing Hero Page',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Marketing Solutions'
                    ]
                ]
            ],
            'status' => 'published',
            'language' => 'en'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Tech Blog Post',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Technology content'
                    ]
                ]
            ],
            'status' => 'published',
            'language' => 'en'
        ]);
        
        $filters = [
            'search' => 'marketing',
            'search_components' => ['hero'],
            'language' => 'en',
            'status' => 'published'
        ];
        
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals($heroStory->id, $result->first()->id);
    }

    public function test_get_search_suggestions_returns_relevant_suggestions(): void
    {
        // Create stories with various names and content
        Story::factory()->for($this->space)->create([
            'name' => 'Blog Post About Technology',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Technology Newsletter',
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'name' => 'Marketing Blog',
            'status' => 'published'
        ]);
        
        $suggestions = $this->storyService->getSearchSuggestions($this->space, 'tech', 5);
        
        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual(5, count($suggestions));
        
        // Check suggestion structure
        foreach ($suggestions as $suggestion) {
            $this->assertArrayHasKey('type', $suggestion);
            $this->assertArrayHasKey('value', $suggestion);
            $this->assertContains($suggestion['type'], ['story_name', 'story_slug', 'tag', 'component']);
            $this->assertStringContainsStringIgnoringCase('tech', $suggestion['value']);
        }
    }

    public function test_get_search_suggestions_includes_component_suggestions(): void
    {
        // Create story with specific components
        Story::factory()->for($this->space)->create([
            'name' => 'Test Story',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Hero section'
                    ],
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'Text content'
                    ]
                ]
            ],
            'status' => 'published'
        ]);
        
        $suggestions = $this->storyService->getSearchSuggestions($this->space, 'her', 10);
        
        $componentSuggestions = array_filter($suggestions, fn($s) => $s['type'] === 'component');
        
        $this->assertNotEmpty($componentSuggestions);
        
        $componentValues = array_column($componentSuggestions, 'value');
        $this->assertContains('hero', $componentValues);
    }

    public function test_get_search_stats_returns_comprehensive_analytics(): void
    {
        // Create test data
        Story::factory()->for($this->space)->count(5)->create(['status' => 'published']);
        Story::factory()->for($this->space)->count(3)->create(['status' => 'draft']);
        Story::factory()->for($this->space)->count(2)->create(['status' => 'archived']);
        
        // Create stories with specific components
        Story::factory()->for($this->space)->create([
            'content' => [
                'body' => [
                    ['_uid' => Str::uuid(), 'component' => 'hero'],
                    ['_uid' => Str::uuid(), 'component' => 'text_block']
                ]
            ],
            'status' => 'published'
        ]);
        
        Story::factory()->for($this->space)->create([
            'content' => [
                'body' => [
                    ['_uid' => Str::uuid(), 'component' => 'hero'],
                    ['_uid' => Str::uuid(), 'component' => 'hero'] // Duplicate
                ]
            ],
            'status' => 'published'
        ]);
        
        $stats = $this->storyService->getSearchStats($this->space);
        
        $this->assertArrayHasKey('total_stories', $stats);
        $this->assertArrayHasKey('published_stories', $stats);
        $this->assertArrayHasKey('draft_stories', $stats);
        $this->assertArrayHasKey('languages', $stats);
        $this->assertArrayHasKey('recent_stories', $stats);
        $this->assertArrayHasKey('popular_components', $stats);
        
        $this->assertEquals(12, $stats['total_stories']); // Including setUp stories
        $this->assertEquals(7, $stats['published_stories']);
        $this->assertEquals(3, $stats['draft_stories']);
        
        // Check popular components
        $this->assertIsArray($stats['popular_components']);
        
        $heroUsage = collect($stats['popular_components'])->firstWhere('component', 'hero');
        $this->assertNotNull($heroUsage);
        $this->assertGreaterThanOrEqual(3, $heroUsage['usage_count']); // 3 hero components
    }

    public function test_validate_story_content_validates_against_components(): void
    {
        $content = [
            'body' => [
                [
                    '_uid' => Str::uuid(),
                    'component' => 'hero',
                    'title' => 'Valid Title',
                    'subtitle' => 'Valid Subtitle'
                ],
                [
                    '_uid' => Str::uuid(),
                    'component' => 'text_block',
                    'content' => 'Valid content'
                ]
            ]
        ];
        
        $errors = $this->storyService->validateStoryContent($content);
        $this->assertEmpty($errors, 'Valid content should pass validation');
    }

    public function test_validate_story_content_returns_validation_errors(): void
    {
        $content = [
            'body' => [
                [
                    '_uid' => Str::uuid(),
                    'component' => 'hero',
                    // Missing required 'title' field
                    'subtitle' => 'Valid Subtitle'
                ],
                [
                    '_uid' => Str::uuid(),
                    'component' => 'text_block',
                    // Missing required 'content' field
                ]
            ]
        ];
        
        $errors = $this->storyService->validateStoryContent($content);
        
        $this->assertNotEmpty($errors, 'Invalid content should return errors');
        $this->assertIsArray($errors);
        
        // Check that errors are properly structured
        foreach ($errors as $componentIndex => $componentErrors) {
            $this->assertIsArray($componentErrors);
        }
    }

    public function test_validate_story_content_handles_unknown_components(): void
    {
        $content = [
            'body' => [
                [
                    '_uid' => Str::uuid(),
                    'component' => 'unknown_component',
                    'some_field' => 'some_value'
                ]
            ]
        ];
        
        $errors = $this->storyService->validateStoryContent($content);
        
        // Should handle unknown components gracefully (no validation errors for unknown components)
        $this->assertEmpty($errors, 'Unknown components should not cause validation errors');
    }

    public function test_pagination_with_custom_per_page(): void
    {
        Story::factory()->for($this->space)->count(30)->create(['status' => 'published']);
        
        $filters = ['per_page' => 10];
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(30, $result->total());
        $this->assertEquals(3, $result->lastPage());
    }

    public function test_search_with_status_filter(): void
    {
        Story::factory()->for($this->space)->count(3)->create(['status' => 'published']);
        Story::factory()->for($this->space)->count(2)->create(['status' => 'draft']);
        Story::factory()->for($this->space)->count(1)->create(['status' => 'archived']);
        
        $filters = ['status' => 'draft'];
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(2, $result->total());
        
        foreach ($result->items() as $story) {
            $this->assertEquals('draft', $story->status);
        }
    }

    public function test_search_with_date_filters(): void
    {
        $recentStory = Story::factory()->for($this->space)->create([
            'created_at' => now()->subDays(1),
            'status' => 'published'
        ]);
        
        $oldStory = Story::factory()->for($this->space)->create([
            'created_at' => now()->subDays(10),
            'status' => 'published'
        ]);
        
        $filters = ['created_after' => now()->subDays(5)->toISOString()];
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals($recentStory->id, $result->first()->id);
    }

    public function test_comprehensive_search_mode(): void
    {
        $story = Story::factory()->for($this->space)->create([
            'name' => 'Technology Blog Post',
            'slug' => 'tech-blog-post',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Innovation in Software Development'
                    ]
                ]
            ],
            'meta_title' => 'Software Tech Article',
            'status' => 'published'
        ]);
        
        // Search should find the story through multiple fields
        $filters = [
            'search' => 'software',
            'search_mode' => 'comprehensive'
        ];
        
        $result = $this->storyService->getPaginatedStories($this->space, $filters);
        
        $this->assertEquals(1, $result->total());
        $this->assertEquals($story->id, $result->first()->id);
    }
}