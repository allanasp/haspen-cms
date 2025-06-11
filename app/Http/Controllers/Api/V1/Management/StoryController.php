<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoryRequest;
use App\Http\Requests\UpdateStoryRequest;
use App\Http\Resources\StoryResource;
use App\Models\Component;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Management API for Stories.
 * Admin operations for story CRUD with versioning.
 */
#[OA\Tag(name: 'Management - Stories', description: 'Story management operations')]
class StoryController extends Controller
{
    /**
     * List stories with advanced filtering.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories',
        summary: 'List stories',
        description: 'Get a paginated list of stories with filtering and search.',
        tags: ['Management - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 25)
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Search in story name and slug',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'blog post')
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['draft', 'in_review', 'published', 'scheduled', 'archived'])
            ),
            new OA\Parameter(
                name: 'starts_with',
                description: 'Filter by slug prefix',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'blog/')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stories retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'data' => new OA\Property(type: 'array', items: new OA\Items(ref: StoryResource::class)),
                        'meta' => new OA\Property(
                            properties: [
                                'current_page' => new OA\Property(type: 'integer'),
                                'per_page' => new OA\Property(type: 'integer'),
                                'total' => new OA\Property(type: 'integer')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $query = Story::where('space_id', $space->id);

        // Apply search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($startsWith = $request->query('starts_with')) {
            $query->where('slug', 'like', $startsWith . '%');
        }

        // Order by creation date (newest first)
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $stories = $query->with(['parent', 'translations'])
            ->paginate($perPage);

        return response()->json([
            'data' => StoryResource::collection($stories->items()),
            'meta' => [
                'current_page' => $stories->currentPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
                'last_page' => $stories->lastPage()
            ]
        ]);
    }

    /**
     * Create a new story.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories',
        summary: 'Create story',
        description: 'Create a new story with content validation.',
        tags: ['Management - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['story'],
                properties: [
                    'story' => new OA\Property(
                        properties: [
                            'name' => new OA\Property(type: 'string', example: 'My Blog Post'),
                            'slug' => new OA\Property(type: 'string', example: 'my-blog-post'),
                            'content' => new OA\Property(type: 'object', example: ['_uid' => 'component-uuid', 'component' => 'hero']),
                            'status' => new OA\Property(type: 'string', enum: ['draft', 'published'], example: 'draft'),
                            'parent_id' => new OA\Property(type: 'string', nullable: true),
                            'meta_title' => new OA\Property(type: 'string', nullable: true),
                            'meta_description' => new OA\Property(type: 'string', nullable: true)
                        ],
                        type: 'object'
                    )
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Story created successfully',
                content: new OA\JsonContent(
                    properties: [
                        'story' => new OA\Property(ref: StoryResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(StoreStoryRequest $request): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated()['story'];

        // Validate content against component schemas
        if (isset($data['content']) && !empty($data['content'])) {
            $this->validateStoryContent($data['content'], $space);
        }

        $story = new Story();
        $story->space_id = $space->id;
        $story->name = $data['name'];
        $story->slug = $data['slug'] ?? str($data['name'])->slug();
        $story->content = $data['content'] ?? [];
        $story->status = $data['status'] ?? 'draft';
        $story->parent_id = $data['parent_id'] ?? null;
        $story->meta_title = $data['meta_title'] ?? null;
        $story->meta_description = $data['meta_description'] ?? null;
        $story->created_by = $request->user()->id;

        $story->save();

        return response()->json([
            'story' => new StoryResource($story)
        ], 201);
    }

    /**
     * Get a specific story.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story_id}',
        summary: 'Get story',
        description: 'Retrieve a specific story by ID.',
        tags: ['Management - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'story_id',
                description: 'Story UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'story' => new OA\Property(ref: StoryResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Story not found')
        ]
    )]
    public function show(Request $request, string $storyId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $story = Story::where('uuid', $storyId)
            ->where('space_id', $space->id)
            ->with(['parent', 'children', 'translations'])
            ->first();

        if (!$story) {
            return response()->json([
                'error' => 'Story not found',
                'message' => 'The requested story could not be found'
            ], 404);
        }

        return response()->json([
            'story' => new StoryResource($story)
        ]);
    }

    /**
     * Update a story.
     */
    #[OA\Put(
        path: '/api/v1/spaces/{space_id}/stories/{story_id}',
        summary: 'Update story',
        description: 'Update an existing story.',
        tags: ['Management - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'story_id',
                description: 'Story UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['story'],
                properties: [
                    'story' => new OA\Property(
                        properties: [
                            'name' => new OA\Property(type: 'string'),
                            'slug' => new OA\Property(type: 'string'),
                            'content' => new OA\Property(type: 'object'),
                            'status' => new OA\Property(type: 'string', enum: ['draft', 'in_review', 'published', 'scheduled', 'archived']),
                            'publish_at' => new OA\Property(type: 'string', format: 'date-time', nullable: true),
                            'meta_title' => new OA\Property(type: 'string', nullable: true),
                            'meta_description' => new OA\Property(type: 'string', nullable: true)
                        ],
                        type: 'object'
                    )
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        'story' => new OA\Property(ref: StoryResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Story not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(UpdateStoryRequest $request, string $storyId): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated()['story'];
        
        $story = Story::where('uuid', $storyId)
            ->where('space_id', $space->id)
            ->first();

        if (!$story) {
            return response()->json([
                'error' => 'Story not found',
                'message' => 'The requested story could not be found'
            ], 404);
        }

        // Validate content if provided
        if (isset($data['content'])) {
            $this->validateStoryContent($data['content'], $space);
        }

        // Update fields
        $story->fill($data);
        $story->updated_by = $request->user()->id;

        // Handle publishing
        if (isset($data['status']) && $data['status'] === 'published' && $story->status !== 'published') {
            $story->published_at = $data['publish_at'] ?? now();
        }

        $story->save();

        return response()->json([
            'story' => new StoryResource($story)
        ]);
    }

    /**
     * Delete a story.
     */
    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/stories/{story_id}',
        summary: 'Delete story',
        description: 'Delete a story (soft delete).',
        tags: ['Management - Stories'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'story_id',
                description: 'Story UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Story deleted successfully'),
            new OA\Response(response: 404, description: 'Story not found')
        ]
    )]
    public function destroy(Request $request, string $storyId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $story = Story::where('uuid', $storyId)
            ->where('space_id', $space->id)
            ->first();

        if (!$story) {
            return response()->json([
                'error' => 'Story not found',
                'message' => 'The requested story could not be found'
            ], 404);
        }

        $story->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate story content against component schemas.
     */
    private function validateStoryContent(array $content, $space): void
    {
        // Basic validation - ensure content structure is valid
        if (!isset($content['body']) || !is_array($content['body'])) {
            throw new \InvalidArgumentException('Story content must have a body array');
        }

        // Validate each component in the body
        foreach ($content['body'] as $item) {
            if (!isset($item['component'])) {
                throw new \InvalidArgumentException('Each content item must specify a component');
            }

            $componentName = $item['component'];
            $component = Component::where('internal_name', $componentName)
                ->where('space_id', $space->id)
                ->first();

            if (!$component) {
                throw new \InvalidArgumentException("Component '{$componentName}' not found");
            }

            // Additional schema validation would go here
            // This would validate the item data against the component's schema
        }
    }
}