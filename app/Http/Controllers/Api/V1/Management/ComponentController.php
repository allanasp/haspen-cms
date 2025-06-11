<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponentRequest;
use App\Http\Requests\UpdateComponentRequest;
use App\Http\Resources\ComponentResource;
use App\Models\Component;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Management API for Components.
 * Admin operations for component CRUD with schema validation.
 */
#[OA\Tag(name: 'Management - Components', description: 'Component management operations')]
class ComponentController extends Controller
{
    /**
     * List components.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components',
        summary: 'List components',
        description: 'Get a paginated list of components.',
        tags: ['Management - Components'],
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
                name: 'search',
                description: 'Search in component name',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'hero')
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Filter by component type',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['root', 'nestable'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Components retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'components' => new OA\Property(type: 'array', items: new OA\Items(ref: ComponentResource::class))
                    ],
                    type: 'object'
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $query = Component::where('space_id', $space->id);

        // Apply search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('internal_name', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($type = $request->query('type')) {
            $query->where('is_root', $type === 'root');
        }

        $components = $query->orderBy('name')->get();

        return response()->json([
            'components' => ComponentResource::collection($components)
        ]);
    }

    /**
     * Create a new component.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components',
        summary: 'Create component',
        description: 'Create a new component with schema definition.',
        tags: ['Management - Components'],
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
                required: ['component'],
                properties: [
                    'component' => new OA\Property(
                        properties: [
                            'name' => new OA\Property(type: 'string', example: 'Hero Section'),
                            'internal_name' => new OA\Property(type: 'string', example: 'hero'),
                            'schema' => new OA\Property(
                                type: 'object',
                                example: [
                                    'title' => ['type' => 'text', 'required' => true],
                                    'description' => ['type' => 'textarea', 'required' => false]
                                ]
                            ),
                            'is_root' => new OA\Property(type: 'boolean', example: true),
                            'is_nestable' => new OA\Property(type: 'boolean', example: false),
                            'preview_field' => new OA\Property(type: 'string', nullable: true, example: 'title'),
                            'icon' => new OA\Property(type: 'string', nullable: true, example: 'hero'),
                            'color' => new OA\Property(type: 'string', nullable: true, example: '#3b82f6')
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
                description: 'Component created successfully',
                content: new OA\JsonContent(
                    properties: [
                        'component' => new OA\Property(ref: ComponentResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(StoreComponentRequest $request): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated()['component'];

        // Validate component schema
        $this->validateComponentSchema($data['schema']);

        $component = new Component();
        $component->space_id = $space->id;
        $component->name = $data['name'];
        $component->internal_name = $data['internal_name'];
        $component->schema = $data['schema'];
        $component->is_root = $data['is_root'] ?? false;
        $component->is_nestable = $data['is_nestable'] ?? true;
        $component->preview_field = $data['preview_field'] ?? null;
        $component->icon = $data['icon'] ?? null;
        $component->color = $data['color'] ?? null;
        $component->created_by = $request->user()->id;

        $component->save();

        return response()->json([
            'component' => new ComponentResource($component)
        ], 201);
    }

    /**
     * Get a specific component.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/{component_id}',
        summary: 'Get component',
        description: 'Retrieve a specific component by ID.',
        tags: ['Management - Components'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'component_id',
                description: 'Component UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Component retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'component' => new OA\Property(ref: ComponentResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Component not found')
        ]
    )]
    public function show(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json([
                'error' => 'Component not found',
                'message' => 'The requested component could not be found'
            ], 404);
        }

        return response()->json([
            'component' => new ComponentResource($component)
        ]);
    }

    /**
     * Update a component.
     */
    #[OA\Put(
        path: '/api/v1/spaces/{space_id}/components/{component_id}',
        summary: 'Update component',
        description: 'Update an existing component.',
        tags: ['Management - Components'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'component_id',
                description: 'Component UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['component'],
                properties: [
                    'component' => new OA\Property(
                        properties: [
                            'name' => new OA\Property(type: 'string'),
                            'schema' => new OA\Property(type: 'object'),
                            'is_root' => new OA\Property(type: 'boolean'),
                            'is_nestable' => new OA\Property(type: 'boolean'),
                            'preview_field' => new OA\Property(type: 'string', nullable: true),
                            'icon' => new OA\Property(type: 'string', nullable: true),
                            'color' => new OA\Property(type: 'string', nullable: true)
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
                description: 'Component updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        'component' => new OA\Property(ref: ComponentResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Component not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(UpdateComponentRequest $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated()['component'];
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json([
                'error' => 'Component not found',
                'message' => 'The requested component could not be found'
            ], 404);
        }

        // Validate schema if provided
        if (isset($data['schema'])) {
            $this->validateComponentSchema($data['schema']);
        }

        // Update fields
        $component->fill($data);
        $component->updated_by = $request->user()->id;
        $component->save();

        return response()->json([
            'component' => new ComponentResource($component)
        ]);
    }

    /**
     * Delete a component.
     */
    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/components/{component_id}',
        summary: 'Delete component',
        description: 'Delete a component (soft delete).',
        tags: ['Management - Components'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'component_id',
                description: 'Component UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Component deleted successfully'),
            new OA\Response(response: 404, description: 'Component not found')
        ]
    )]
    public function destroy(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json([
                'error' => 'Component not found',
                'message' => 'The requested component could not be found'
            ], 404);
        }

        $component->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate component schema structure.
     */
    private function validateComponentSchema(array $schema): void
    {
        if (empty($schema)) {
            throw new \InvalidArgumentException('Component schema cannot be empty');
        }

        $allowedTypes = [
            'text', 'textarea', 'markdown', 'richtext', 'number', 'boolean', 
            'datetime', 'asset', 'option', 'options', 'blocks', 'link', 'email', 'url'
        ];

        foreach ($schema as $fieldName => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                throw new \InvalidArgumentException("Field '{$fieldName}' configuration must be an array");
            }

            if (!isset($fieldConfig['type'])) {
                throw new \InvalidArgumentException("Field '{$fieldName}' must specify a type");
            }

            if (!in_array($fieldConfig['type'], $allowedTypes)) {
                throw new \InvalidArgumentException("Field '{$fieldName}' has invalid type '{$fieldConfig['type']}'");
            }

            // Additional validation based on field type
            if ($fieldConfig['type'] === 'option' && !isset($fieldConfig['options'])) {
                throw new \InvalidArgumentException("Field '{$fieldName}' of type 'option' must specify options");
            }
        }
    }
}