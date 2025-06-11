<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cdn;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Content Delivery Network API for Stories.
 * Public read-only access to published stories.
 */
#[OA\Tag(name: 'CDN - Stories', description: 'Public story content delivery')]
class StoryController extends Controller
{
    /**
     * Get a published story by slug.
     */
    #[OA\Get(
        path: '/api/v1/cdn/stories/{slug}',
        summary: 'Get published story by slug',
        description: 'Retrieve a published story by its slug. Returns story content with resolved components.',
        tags: ['CDN - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                description: 'The story slug',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'my-blog-post')
            ),
            new OA\Parameter(
                name: 'version',
                description: 'Specific version to retrieve (defaults to published)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['draft', 'published'], example: 'published')
            ),
            new OA\Parameter(
                name: 'resolve_links',
                description: 'Resolve story links in content',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', example: true)
            ),
            new OA\Parameter(
                name: 'resolve_relations',
                description: 'Include related stories and assets',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'author,tags')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'story' => new OA\Property(
                            properties: [
                                'id' => new OA\Property(type: 'string', example: 'uuid-here'),
                                'name' => new OA\Property(type: 'string', example: 'My Blog Post'),
                                'slug' => new OA\Property(type: 'string', example: 'my-blog-post'),
                                'content' => new OA\Property(type: 'object', example: ['_uid' => 'component-uuid', 'component' => 'hero', 'title' => 'Welcome']),
                                'published_at' => new OA\Property(type: 'string', format: 'date-time'),
                                'meta_title' => new OA\Property(type: 'string', nullable: true),
                                'meta_description' => new OA\Property(type: 'string', nullable: true)
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Story not found'),
            new OA\Response(response: 429, description: 'Rate limit exceeded')
        ]
    )]
    public function show(Request $request, string $slug): JsonResponse
    {
        $space = $request->get('current_space');
        
        $query = Story::where('slug', $slug)
            ->where('space_id', $space->id)
            ->where('published_at', '<=', now());

        // Handle version parameter
        $version = $request->query('version', 'published');
        if ($version === 'draft') {
            $query->where('status', 'draft');
        } else {
            $query->where('status', 'published');
        }

        $story = $query->with(['parent', 'children'])->first();

        if (!$story) {
            return response()->json([
                'error' => 'Story not found',
                'message' => 'The requested story could not be found or is not published'
            ], 404);
        }

        // Handle resolve parameters
        $resolveLinks = $request->boolean('resolve_links', false);
        $resolveRelations = $request->query('resolve_relations');

        if ($resolveLinks) {
            $story->content = $this->resolveContentLinks($story->content, $space);
        }

        if ($resolveRelations) {
            $relations = explode(',', $resolveRelations);
            $story = $this->resolveRelations($story, $relations);
        }

        return response()->json([
            'story' => new StoryResource($story)
        ]);
    }

    /**
     * List published stories with filtering and pagination.
     */
    #[OA\Get(
        path: '/api/v1/cdn/stories',
        summary: 'List published stories',
        description: 'Get a paginated list of published stories with optional filtering.',
        tags: ['CDN - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page (max 100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 25)
            ),
            new OA\Parameter(
                name: 'starts_with',
                description: 'Filter stories by slug prefix',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'blog/')
            ),
            new OA\Parameter(
                name: 'by_slugs',
                description: 'Filter by specific slugs (comma-separated)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'story-1,story-2')
            ),
            new OA\Parameter(
                name: 'excluding_slugs',
                description: 'Exclude specific slugs (comma-separated)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'draft-story')
            ),
            new OA\Parameter(
                name: 'sort_by',
                description: 'Sort field',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['created_at', 'published_at', 'name', 'position'], example: 'published_at')
            ),
            new OA\Parameter(
                name: 'sort_order',
                description: 'Sort direction',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], example: 'desc')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stories retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'stories' => new OA\Property(type: 'array', items: new OA\Items(ref: StoryResource::class)),
                        'meta' => new OA\Property(
                            properties: [
                                'current_page' => new OA\Property(type: 'integer'),
                                'per_page' => new OA\Property(type: 'integer'),
                                'total' => new OA\Property(type: 'integer'),
                                'last_page' => new OA\Property(type: 'integer')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 429, description: 'Rate limit exceeded')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $query = Story::where('space_id', $space->id)
            ->where('status', 'published')
            ->where('published_at', '<=', now());

        // Apply filters
        if ($startsWith = $request->query('starts_with')) {
            $query->where('slug', 'like', $startsWith . '%');
        }

        if ($bySlugs = $request->query('by_slugs')) {
            $slugs = explode(',', $bySlugs);
            $query->whereIn('slug', $slugs);
        }

        if ($excludingSlugs = $request->query('excluding_slugs')) {
            $slugs = explode(',', $excludingSlugs);
            $query->whereNotIn('slug', $slugs);
        }

        // Apply sorting
        $sortBy = $request->query('sort_by', 'published_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $stories = $query->paginate($perPage);

        return response()->json([
            'stories' => StoryResource::collection($stories->items()),
            'meta' => [
                'current_page' => $stories->currentPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
                'last_page' => $stories->lastPage(),
                'from' => $stories->firstItem(),
                'to' => $stories->lastItem()
            ]
        ]);
    }

    /**
     * Resolve content links in story content.
     */
    private function resolveContentLinks(array $content, $space): array
    {
        // This would recursively walk through content and resolve story links
        // Implementation depends on your content structure
        return $content;
    }

    /**
     * Resolve additional relations for story.
     */
    private function resolveRelations(Story $story, array $relations): Story
    {
        $allowedRelations = ['parent', 'children', 'translations'];
        $relations = array_intersect($relations, $allowedRelations);
        
        if (!empty($relations)) {
            $story->load($relations);
        }

        return $story;
    }
}