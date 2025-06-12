<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use App\Services\VersionManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Management - Stories', description: 'Story management operations')]
class StoryController extends Controller
{
    public function __construct(
        private StoryService $storyService,
        private VersionManager $versionManager
    ) {}

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories',
        summary: 'List stories with advanced filtering',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'scheduled', 'archived'])),
            new OA\Parameter(name: 'starts_with', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'parent_id', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'language', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'created_after', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'updated_after', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at', 'name', 'published_at'])),
            new OA\Parameter(name: 'sort_order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stories retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'data' => new OA\Property(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Story')
                        ),
                        'meta' => new OA\Property(
                            type: 'object',
                            properties: [
                                'current_page' => new OA\Property(type: 'integer'),
                                'total' => new OA\Property(type: 'integer'),
                                'per_page' => new OA\Property(type: 'integer'),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function index(Request $request, Space $space): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'starts_with' => 'nullable|string|max:100',
            'parent_id' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'created_after' => 'nullable|date',
            'updated_after' => 'nullable|date',
            'sort_by' => ['nullable', Rule::in(['created_at', 'updated_at', 'name', 'published_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $stories = $this->storyService->getPaginatedStories($space, $filters);

        return StoryResource::collection($stories);
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories',
        summary: 'Create a new story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    'name' => new OA\Property(type: 'string', maxLength: 255),
                    'slug' => new OA\Property(type: 'string', maxLength: 255),
                    'content' => new OA\Property(type: 'object'),
                    'status' => new OA\Property(type: 'string', enum: ['draft', 'published', 'scheduled', 'archived']),
                    'parent_id' => new OA\Property(type: 'string'),
                    'language' => new OA\Property(type: 'string', maxLength: 10),
                    'meta_title' => new OA\Property(type: 'string', maxLength: 255),
                    'meta_description' => new OA\Property(type: 'string', maxLength: 500),
                    'meta_keywords' => new OA\Property(type: 'string', maxLength: 255),
                    'og_title' => new OA\Property(type: 'string', maxLength: 255),
                    'og_description' => new OA\Property(type: 'string', maxLength: 500),
                    'og_image' => new OA\Property(type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Story created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'parent_id' => 'nullable|string|exists:stories,uuid',
            'language' => 'nullable|string|max:10',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $user = Auth::user();
        $story = $this->storyService->createStory($space, $data, $user);

        return $this->successResponse(
            new StoryResource($story),
            'Story created successfully',
            201
        );
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}',
        summary: 'Get a specific story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'resolve_assets', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'resolve_datasources', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story retrieved successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            ),
            new OA\Response(response: 404, description: 'Story not found')
        ]
    )]
    public function show(Request $request, Space $space, Story $story): JsonResponse
    {
        $resolveAssets = $request->boolean('resolve_assets', true);
        $resolveDatasources = $request->boolean('resolve_datasources', true);

        if ($resolveAssets || $resolveDatasources) {
            $story = $this->storyService->getStoryWithRenderedContent(
                $story,
                $resolveAssets,
                $resolveDatasources
            );
        }

        return $this->successResponse(
            new StoryResource($story),
            'Story retrieved successfully'
        );
    }

    #[OA\Put(
        path: '/api/v1/spaces/{space_id}/stories/{story}',
        summary: 'Update a story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'name' => new OA\Property(type: 'string', maxLength: 255),
                    'slug' => new OA\Property(type: 'string', maxLength: 255),
                    'content' => new OA\Property(type: 'object'),
                    'status' => new OA\Property(type: 'string', enum: ['draft', 'published', 'scheduled', 'archived']),
                    'parent_id' => new OA\Property(type: 'string'),
                    'language' => new OA\Property(type: 'string', maxLength: 10),
                    'meta_title' => new OA\Property(type: 'string', maxLength: 255),
                    'meta_description' => new OA\Property(type: 'string', maxLength: 500),
                    'meta_keywords' => new OA\Property(type: 'string', maxLength: 255),
                    'og_title' => new OA\Property(type: 'string', maxLength: 255),
                    'og_description' => new OA\Property(type: 'string', maxLength: 500),
                    'og_image' => new OA\Property(type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Story not found')
        ]
    )]
    public function update(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'content' => 'sometimes|array',
            'status' => ['sometimes', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'parent_id' => 'sometimes|nullable|string|exists:stories,uuid',
            'language' => 'sometimes|string|max:10',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:500',
            'meta_keywords' => 'sometimes|nullable|string|max:255',
            'og_title' => 'sometimes|nullable|string|max:255',
            'og_description' => 'sometimes|nullable|string|max:500',
            'og_image' => 'sometimes|nullable|string|max:500',
            'scheduled_at' => 'sometimes|nullable|date|after:now',
            'published_at' => 'sometimes|nullable|date',
        ]);

        $user = Auth::user();
        $story = $this->storyService->updateStory($story, $data, $user);

        return $this->successResponse(
            new StoryResource($story),
            'Story updated successfully'
        );
    }

    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/stories/{story}',
        summary: 'Delete a story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Story deleted successfully'),
            new OA\Response(response: 404, description: 'Story not found')
        ]
    )]
    public function destroy(Space $space, Story $story): JsonResponse
    {
        $user = Auth::user();
        
        // Create version before deletion
        $this->versionManager->createVersion($story, $user, 'Before deletion');
        
        $story->delete();

        return $this->successResponse(null, 'Story deleted successfully', 204);
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/duplicate',
        summary: 'Duplicate a story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'name' => new OA\Property(type: 'string', maxLength: 255),
                    'slug' => new OA\Property(type: 'string', maxLength: 255),
                    'status' => new OA\Property(type: 'string', enum: ['draft', 'published', 'scheduled', 'archived']),
                    'parent_id' => new OA\Property(type: 'string'),
                    'language' => new OA\Property(type: 'string', maxLength: 10),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Story duplicated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            )
        ]
    )]
    public function duplicate(Request $request, Space $space, Story $story): JsonResponse
    {
        $modifications = $request->validate([
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'parent_id' => 'nullable|string|exists:stories,uuid',
            'language' => 'nullable|string|max:10',
        ]);

        $user = Auth::user();
        $duplicatedStory = $this->storyService->duplicateStory($story, $modifications, $user);

        return $this->successResponse(
            new StoryResource($duplicatedStory),
            'Story duplicated successfully',
            201
        );
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/publish',
        summary: 'Publish a story immediately or schedule for later',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'scheduled_at' => new OA\Property(type: 'string', format: 'date-time', description: 'Schedule for future publication')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story published successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            )
        ]
    )]
    public function publish(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $user = Auth::user();
        $scheduledAt = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
        
        $story = $this->storyService->publishStory($story, $user, $scheduledAt);

        $message = $scheduledAt && $scheduledAt->isFuture() 
            ? "Story scheduled for publication at {$scheduledAt->toDateTimeString()}"
            : 'Story published successfully';

        return $this->successResponse(
            new StoryResource($story),
            $message
        );
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/unpublish',
        summary: 'Unpublish a story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story unpublished successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            )
        ]
    )]
    public function unpublish(Space $space, Story $story): JsonResponse
    {
        $user = Auth::user();
        $story = $this->storyService->unpublishStory($story, $user);

        return $this->successResponse(
            new StoryResource($story),
            'Story unpublished successfully'
        );
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/bulk/publish',
        summary: 'Bulk publish multiple stories',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['story_ids'],
                properties: [
                    'story_ids' => new OA\Property(
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Array of story UUIDs'
                    ),
                    'scheduled_at' => new OA\Property(type: 'string', format: 'date-time')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stories published successfully',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(type: 'string'),
                        'published_count' => new OA\Property(type: 'integer'),
                        'total_count' => new OA\Property(type: 'integer'),
                    ]
                )
            )
        ]
    )]
    public function bulkPublish(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'story_ids' => 'required|array|min:1',
            'story_ids.*' => 'required|string|exists:stories,uuid',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $stories = Story::whereIn('uuid', $data['story_ids'])
            ->where('space_id', $space->id)
            ->get();

        $user = Auth::user();
        $scheduledAt = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
        
        $publishedCount = $this->storyService->bulkPublish($stories, $user, $scheduledAt);

        $message = $scheduledAt && $scheduledAt->isFuture()
            ? "Scheduled {$publishedCount} of {$stories->count()} stories for publication"
            : "Published {$publishedCount} of {$stories->count()} stories";

        return $this->successResponse([
            'published_count' => $publishedCount,
            'total_count' => $stories->count(),
        ], $message);
    }

    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/stories/bulk/delete',
        summary: 'Bulk delete multiple stories',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['story_ids'],
                properties: [
                    'story_ids' => new OA\Property(
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        description: 'Array of story UUIDs'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stories deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(type: 'string'),
                        'deleted_count' => new OA\Property(type: 'integer'),
                        'total_count' => new OA\Property(type: 'integer'),
                    ]
                )
            )
        ]
    )]
    public function bulkDelete(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'story_ids' => 'required|array|min:1',
            'story_ids.*' => 'required|string|exists:stories,uuid',
        ]);

        $stories = Story::whereIn('uuid', $data['story_ids'])
            ->where('space_id', $space->id)
            ->get();

        $user = Auth::user();
        $deletedCount = $this->storyService->bulkDelete($stories, $user);

        return $this->successResponse([
            'deleted_count' => $deletedCount,
            'total_count' => $stories->count(),
        ], "Deleted {$deletedCount} of {$stories->count()} stories");
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/versions',
        summary: 'Get story versions',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story versions retrieved successfully'
            )
        ]
    )]
    public function versions(Request $request, Space $space, Story $story): JsonResponse
    {
        $perPage = $request->integer('per_page', 20);
        $versions = $this->versionManager->getVersions($story, $perPage);

        return $this->successResponse($versions, 'Story versions retrieved successfully');
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/versions/{version}/restore',
        summary: 'Restore story to a specific version',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'version', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story restored successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Story')
            )
        ]
    )]
    public function restoreVersion(Space $space, Story $story, int $version): JsonResponse
    {
        $user = Auth::user();
        $restoredStory = $this->versionManager->restoreToVersion($story, $version, $user);

        return $this->successResponse(
            new StoryResource($restoredStory),
            "Story restored to version {$version}"
        );
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/versions/compare',
        summary: 'Compare two story versions',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'version_a', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'version_b', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Version comparison retrieved successfully'
            )
        ]
    )]
    public function compareVersions(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'version_a' => 'required|integer|min:1',
            'version_b' => 'required|integer|min:1',
        ]);

        $comparison = $this->versionManager->compareVersions(
            $story,
            $data['version_a'],
            $data['version_b']
        );

        return $this->successResponse($comparison, 'Version comparison retrieved successfully');
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/stats',
        summary: 'Get story statistics and metrics',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story statistics retrieved successfully'
            )
        ]
    )]
    public function stats(Space $space, Story $story): JsonResponse
    {
        $versionStats = $this->versionManager->getVersionStats($story);

        $stats = [
            'versions' => $versionStats,
            'content_metrics' => [
                'content_blocks' => $this->countContentBlocks($story->content),
                'content_size' => strlen(json_encode($story->content)),
                'last_modified' => $story->updated_at->toDateTimeString(),
                'word_count' => $this->estimateWordCount($story->content),
            ],
            'publication_history' => [
                'status' => $story->status,
                'published_at' => $story->published_at?->toDateTimeString(),
                'scheduled_at' => $story->scheduled_at?->toDateTimeString(),
                'created_at' => $story->created_at->toDateTimeString(),
            ]
        ];

        return $this->successResponse($stats, 'Story statistics retrieved successfully');
    }

    private function countContentBlocks(array $content): int
    {
        if (!isset($content['body']) || !is_array($content['body'])) {
            return 0;
        }

        $count = count($content['body']);
        
        foreach ($content['body'] as $block) {
            if (isset($block['body']) && is_array($block['body'])) {
                $count += $this->countContentBlocks(['body' => $block['body']]);
            }
        }

        return $count;
    }

    private function estimateWordCount(array $content): int
    {
        $text = $this->extractTextFromContent($content);
        return str_word_count(strip_tags($text));
    }

    private function extractTextFromContent(array $content): string
    {
        $text = '';
        
        if (isset($content['body']) && is_array($content['body'])) {
            foreach ($content['body'] as $block) {
                if (is_array($block)) {
                    foreach ($block as $key => $value) {
                        if (is_string($value) && !in_array($key, ['component', '_uid', 'asset'])) {
                            $text .= ' ' . $value;
                        } elseif (is_array($value)) {
                            $text .= ' ' . $this->extractTextFromContent(['body' => [$value]]);
                        }
                    }
                }
            }
        }

        return $text;
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/templates',
        summary: 'Get available content templates',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Content templates retrieved successfully'
            )
        ]
    )]
    public function getTemplates(Space $space): JsonResponse
    {
        $story = new Story(['space_id' => $space->id]);
        $templates = $story->getAvailableTemplates();

        return $this->successResponse($templates, 'Content templates retrieved successfully');
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/create-template',
        summary: 'Create template from story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Template name'),
                    new OA\Property(property: 'description', type: 'string', description: 'Template description'),
                    new OA\Property(property: 'save_to_database', type: 'boolean', description: 'Save as custom template'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Template created successfully'
            )
        ]
    )]
    public function createTemplate(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'save_to_database' => 'boolean',
        ]);

        $template = $story->createTemplate($data['name'], $data['description'] ?? null);

        // Optionally save as custom template in database
        if ($data['save_to_database'] ?? false) {
            $templateStory = new Story([
                'space_id' => $space->id,
                'name' => $template['name'],
                'slug' => Str::slug($template['name']),
                'content' => $template['content'],
                'meta_data' => array_merge($template['meta_data'] ?? [], [
                    'is_template' => true,
                    'template_description' => $template['description'],
                    'created_from_story_uuid' => $template['created_from_story_uuid'],
                ]),
                'status' => Story::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            
            $templateStory->save();
            $template['uuid'] = $templateStory->uuid;
        }

        return $this->successResponse($template, 'Template created successfully');
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/from-template',
        summary: 'Create story from template',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'template_uuid', type: 'string', description: 'Template UUID (for custom templates)'),
                    new OA\Property(property: 'template_name', type: 'string', description: 'Template name (for config templates)'),
                    new OA\Property(property: 'story_name', type: 'string', description: 'New story name'),
                    new OA\Property(property: 'story_slug', type: 'string', description: 'New story slug'),
                    new OA\Property(property: 'parent_id', type: 'string', description: 'Parent story UUID'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Story created from template successfully'
            )
        ]
    )]
    public function createFromTemplate(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'template_uuid' => 'nullable|string|exists:stories,uuid',
            'template_name' => 'nullable|string',
            'story_name' => 'required|string|max:255',
            'story_slug' => 'nullable|string|max:255',
            'parent_id' => 'nullable|string|exists:stories,uuid',
        ]);

        // Get template
        $template = null;
        
        if ($data['template_uuid'] ?? null) {
            // Custom template from database
            $templateStory = Story::where('uuid', $data['template_uuid'])
                ->where('space_id', $space->id)
                ->whereJsonContains('meta_data->is_template', true)
                ->first();
                
            if (!$templateStory) {
                return $this->errorResponse('Template not found');
            }
            
            $template = [
                'name' => $templateStory->name,
                'content' => $templateStory->content,
                'meta_data' => $templateStory->meta_data,
            ];
        } elseif ($data['template_name'] ?? null) {
            // Config template
            $configTemplates = config('cms.content_templates', []);
            $template = collect($configTemplates)->firstWhere('name', $data['template_name']);
            
            if (!$template) {
                return $this->errorResponse('Template not found');
            }
        } else {
            return $this->errorResponse('Either template_uuid or template_name is required');
        }

        // Create story from template
        $storyData = Story::createFromTemplate($template, [
            'name' => $data['story_name'],
            'slug' => $data['story_slug'] ?? Str::slug($data['story_name']),
            'space_id' => $space->id,
            'created_by' => Auth::id(),
        ]);

        if ($data['parent_id'] ?? null) {
            $parent = Story::where('uuid', $data['parent_id'])
                ->where('space_id', $space->id)
                ->first();
            
            if ($parent) {
                $storyData['parent_id'] = $parent->id;
            }
        }

        $story = $this->storyService->createStory($space, $storyData, Auth::user());

        return $this->successResponse(
            new StoryResource($story),
            'Story created from template successfully',
            201
        );
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/lock',
        summary: 'Lock story for editing',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'duration_minutes', type: 'integer', description: 'Lock duration in minutes (default: 30)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story locked successfully'
            )
        ]
    )]
    public function lock(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'duration_minutes' => 'integer|min:1|max:1440', // Max 24 hours
        ]);

        $user = Auth::user();
        $duration = $data['duration_minutes'] ?? 30;
        $sessionId = $request->header('X-Session-ID') ?? session()->getId();

        if ($story->isLockedByOther($user)) {
            $lockInfo = $story->getLockInfo();
            return $this->errorResponse(
                'Story is currently locked by another user',
                409,
                ['lock_info' => $lockInfo]
            );
        }

        $success = $story->lock($user, $sessionId, $duration);

        if (!$success) {
            return $this->errorResponse('Failed to lock story');
        }

        return $this->successResponse(
            ['lock_info' => $story->getLockInfo()],
            'Story locked successfully'
        );
    }

    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/stories/{story}/lock',
        summary: 'Unlock story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story unlocked successfully'
            )
        ]
    )]
    public function unlock(Request $request, Space $space, Story $story): JsonResponse
    {
        $user = Auth::user();
        $sessionId = $request->header('X-Session-ID') ?? session()->getId();

        if (!$story->canUnlock($user, $sessionId)) {
            return $this->errorResponse(
                'You do not have permission to unlock this story',
                403
            );
        }

        $success = $story->unlock($user, $sessionId);

        if (!$success) {
            return $this->errorResponse('Failed to unlock story');
        }

        return $this->successResponse(null, 'Story unlocked successfully');
    }

    #[OA\Put(
        path: '/api/v1/spaces/{space_id}/stories/{story}/lock',
        summary: 'Extend story lock',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'extend_minutes', type: 'integer', description: 'Minutes to extend lock (default: 30)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story lock extended successfully'
            )
        ]
    )]
    public function extendLock(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'extend_minutes' => 'integer|min:1|max:1440',
        ]);

        $user = Auth::user();
        $extendMinutes = $data['extend_minutes'] ?? 30;

        if (!$story->isLockedBy($user)) {
            return $this->errorResponse(
                'You do not have an active lock on this story',
                403
            );
        }

        $success = $story->extendLock($user, $extendMinutes);

        if (!$success) {
            return $this->errorResponse('Failed to extend lock');
        }

        return $this->successResponse(
            ['lock_info' => $story->getLockInfo()],
            'Story lock extended successfully'
        );
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/lock',
        summary: 'Get story lock status',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Story lock status retrieved successfully'
            )
        ]
    )]
    public function getLockStatus(Space $space, Story $story): JsonResponse
    {
        $lockInfo = $story->getLockInfo();

        return $this->successResponse([
            'is_locked' => $story->isLocked(),
            'lock_info' => $lockInfo,
        ], 'Story lock status retrieved successfully');
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/search/suggestions',
        summary: 'Get search suggestions',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search suggestions retrieved successfully'
            )
        ]
    )]
    public function getSearchSuggestions(Request $request, Space $space): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'integer|min:1|max:50',
        ]);

        $suggestions = $this->storyService->getSearchSuggestions(
            $space,
            $data['q'],
            $data['limit'] ?? 10
        );

        return $this->successResponse($suggestions, 'Search suggestions retrieved successfully');
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/search/stats',
        summary: 'Get search statistics',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search statistics retrieved successfully'
            )
        ]
    )]
    public function getSearchStats(Space $space): JsonResponse
    {
        $stats = $this->storyService->getSearchStats($space);

        return $this->successResponse($stats, 'Search statistics retrieved successfully');
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/translations',
        summary: 'Create translation for story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'language', type: 'string', description: 'Target language code'),
                    new OA\Property(property: 'name', type: 'string', description: 'Translated story name'),
                    new OA\Property(property: 'slug', type: 'string', description: 'Translated story slug'),
                    new OA\Property(property: 'content', type: 'object', description: 'Translated content'),
                    new OA\Property(property: 'meta_data', type: 'object', description: 'Translated metadata'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Translation created successfully'
            )
        ]
    )]
    public function createTranslation(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'language' => 'required|string|max:10',
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'meta_data' => 'nullable|array',
        ]);

        $user = Auth::user();

        try {
            $translation = $story->createTranslation($data['language'], $data, $user);
            
            return $this->successResponse(
                new StoryResource($translation),
                'Translation created successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/translations',
        summary: 'Get all translations for story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Translations retrieved successfully'
            )
        ]
    )]
    public function getTranslations(Space $space, Story $story): JsonResponse
    {
        $translations = $story->getAllTranslations();

        return $this->successResponse(
            StoryResource::collection($translations),
            'Translations retrieved successfully'
        );
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/translation-status',
        summary: 'Get translation status for all languages',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Translation status retrieved successfully'
            )
        ]
    )]
    public function getTranslationStatus(Space $space, Story $story): JsonResponse
    {
        $status = $story->getTranslationStatus();

        return $this->successResponse($status, 'Translation status retrieved successfully');
    }

    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/stories/{story}/sync-translation',
        summary: 'Sync translation with source story',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'source_story_uuid', type: 'string', description: 'Source story UUID'),
                    new OA\Property(property: 'fields_to_sync', type: 'array', description: 'Fields to sync', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Translation synced successfully'
            )
        ]
    )]
    public function syncTranslation(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'source_story_uuid' => 'required|string|exists:stories,uuid',
            'fields_to_sync' => 'array',
            'fields_to_sync.*' => 'string|in:content,meta_data,name,meta_title,meta_description',
        ]);

        $sourceStory = Story::where('uuid', $data['source_story_uuid'])
            ->where('space_id', $space->id)
            ->first();

        if (!$sourceStory) {
            return $this->errorResponse('Source story not found');
        }

        $fieldsToSync = $data['fields_to_sync'] ?? ['content', 'meta_data'];

        try {
            $synced = $story->syncTranslationContent($sourceStory, $fieldsToSync);
            
            if ($synced) {
                return $this->successResponse(
                    new StoryResource($story->fresh()),
                    'Translation synced successfully'
                );
            } else {
                return $this->successResponse(null, 'No changes were needed');
            }
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/stories/{story}/untranslated-fields',
        summary: 'Get untranslated fields for translation',
        tags: ['Management - Stories'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'space_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'story', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'source_story_uuid', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Untranslated fields retrieved successfully'
            )
        ]
    )]
    public function getUntranslatedFields(Request $request, Space $space, Story $story): JsonResponse
    {
        $data = $request->validate([
            'source_story_uuid' => 'required|string|exists:stories,uuid',
        ]);

        $sourceStory = Story::where('uuid', $data['source_story_uuid'])
            ->where('space_id', $space->id)
            ->first();

        if (!$sourceStory) {
            return $this->errorResponse('Source story not found');
        }

        $untranslatedFields = $story->getUntranslatedFields($sourceStory);

        return $this->successResponse(
            $untranslatedFields,
            'Untranslated fields retrieved successfully'
        );
    }
}