<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cdn;

use App\Http\Controllers\Controller;
use App\Http\Resources\DatasourceEntryResource;
use App\Models\Datasource;
use App\Models\DatasourceEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Content Delivery Network API for Datasources.
 * Public read-only access to datasource entries.
 */
#[OA\Tag(name: 'CDN - Datasources', description: 'Public datasource content delivery')]
class DatasourceController extends Controller
{
    /**
     * Get datasource entries by datasource slug.
     */
    #[OA\Get(
        path: '/api/v1/cdn/datasources/{slug}',
        summary: 'Get datasource entries',
        description: 'Retrieve entries from a datasource by its slug.',
        tags: ['CDN - Datasources'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                description: 'The datasource slug',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'products')
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
                description: 'Items per page (max 100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 25)
            ),
            new OA\Parameter(
                name: 'dimension',
                description: 'Filter by dimension',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'category')
            ),
            new OA\Parameter(
                name: 'dimension_value',
                description: 'Filter by dimension value',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'electronics')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Search entries by name or value',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'laptop')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datasource entries retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'datasource_entries' => new OA\Property(
                            type: 'array',
                            items: new OA\Items(ref: DatasourceEntryResource::class)
                        ),
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
            new OA\Response(response: 404, description: 'Datasource not found'),
            new OA\Response(response: 429, description: 'Rate limit exceeded')
        ]
    )]
    public function show(Request $request, string $slug): JsonResponse
    {
        $space = $request->get('current_space');
        
        $datasource = Datasource::where('slug', $slug)
            ->where('space_id', $space->id)
            ->first();

        if (!$datasource) {
            return response()->json([
                'error' => 'Datasource not found',
                'message' => 'The requested datasource could not be found'
            ], 404);
        }

        $query = DatasourceEntry::where('datasource_id', $datasource->id);

        // Apply filters
        if ($dimension = $request->query('dimension')) {
            $dimensionValue = $request->query('dimension_value');
            
            if ($dimensionValue) {
                $query->whereJsonContains('dimensions', [$dimension => $dimensionValue]);
            } else {
                $query->whereJsonContains('dimensions', $dimension);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%");
            });
        }

        // Paginate
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $entries = $query->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'datasource_entries' => DatasourceEntryResource::collection($entries->items()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem()
            ]
        ]);
    }

    /**
     * List available datasources.
     */
    #[OA\Get(
        path: '/api/v1/cdn/datasources',
        summary: 'List available datasources',
        description: 'Get a list of all available datasources in the space.',
        tags: ['CDN - Datasources'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datasources retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'datasources' => new OA\Property(
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    'id' => new OA\Property(type: 'string'),
                                    'name' => new OA\Property(type: 'string'),
                                    'slug' => new OA\Property(type: 'string'),
                                    'type' => new OA\Property(type: 'string'),
                                    'entry_count' => new OA\Property(type: 'integer')
                                ],
                                type: 'object'
                            )
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
        
        $datasources = Datasource::where('space_id', $space->id)
            ->withCount('entries')
            ->orderBy('name')
            ->get();

        return response()->json([
            'datasources' => $datasources->map(function ($datasource) {
                return [
                    'id' => $datasource->uuid,
                    'name' => $datasource->name,
                    'slug' => $datasource->slug,
                    'type' => $datasource->type,
                    'entry_count' => $datasource->entries_count
                ];
            })
        ]);
    }
}