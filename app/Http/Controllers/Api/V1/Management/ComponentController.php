<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComponentRequest;
use App\Http\Requests\UpdateComponentRequest;
use App\Http\Resources\ComponentResource;
use App\Models\Component;
use App\Services\ComponentLibraryManager;
use App\Services\ComponentSchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * Component Management API.
 * CRUD operations and advanced component features.
 */
#[OA\Tag(name: 'Management - Components', description: 'Component management operations')]
class ComponentController extends Controller
{
    public function __construct(
        private ComponentLibraryManager $libraryManager,
        private ComponentSchemaValidator $schemaValidator
    ) {}

    /**
     * List components with filtering and search.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components',
        summary: 'List components',
        description: 'Get paginated list of components with filtering, search, and inheritance info.',
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
                name: 'search',
                description: 'Search in component name and description',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Filter by component type',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['content_type', 'nestable', 'universal'])
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['draft', 'active', 'deprecated'])
            ),
            new OA\Parameter(
                name: 'include_usage',
                description: 'Include usage statistics',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Components retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'data' => new OA\Property(type: 'array', items: new OA\Items(ref: ComponentResource::class)),
                        'meta' => new OA\Property(type: 'object')
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
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('technical_name', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Order by name
        $query->orderBy('name');

        // Paginate
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $components = $query->with(['parentComponent', 'childComponents'])->paginate($perPage);

        return response()->json([
            'data' => ComponentResource::collection($components->items()),
            'meta' => [
                'current_page' => $components->currentPage(),
                'per_page' => $components->perPage(),
                'total' => $components->total(),
                'last_page' => $components->lastPage()
            ]
        ]);
    }

    /**
     * Create a new component.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components',
        summary: 'Create component',
        description: 'Create a new component with schema validation.',
        tags: ['Management - Components'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['component'],
                properties: [
                    'component' => new OA\Property(
                        properties: [
                            'name' => new OA\Property(type: 'string'),
                            'technical_name' => new OA\Property(type: 'string'),
                            'description' => new OA\Property(type: 'string'),
                            'type' => new OA\Property(type: 'string', enum: ['content_type', 'nestable', 'universal']),
                            'schema' => new OA\Property(type: 'object'),
                            'is_root' => new OA\Property(type: 'boolean'),
                            'is_nestable' => new OA\Property(type: 'boolean'),
                            'allow_inheritance' => new OA\Property(type: 'boolean')
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
            )
        ]
    )]
    public function store(StoreComponentRequest $request): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated()['component'];

        // Validate schema
        $schemaErrors = $this->schemaValidator->validateSchema($data['schema'] ?? []);
        if (!empty($schemaErrors)) {
            return response()->json([
                'error' => 'Invalid component schema',
                'details' => $schemaErrors
            ], 422);
        }

        $component = DB::transaction(function () use ($space, $data, $request) {
            $component = new Component();
            $component->space_id = $space->id;
            $component->name = $data['name'];
            $component->technical_name = $data['technical_name'];
            $component->description = $data['description'] ?? null;
            $component->type = $data['type'] ?? 'content_type';
            $component->schema = $data['schema'] ?? [];
            $component->preview_field = $data['preview_field'] ?? null;
            $component->preview_template = $data['preview_template'] ?? null;
            $component->icon = $data['icon'] ?? null;
            $component->color = $data['color'] ?? null;
            $component->tabs = $data['tabs'] ?? null;
            $component->is_root = $data['is_root'] ?? false;
            $component->is_nestable = $data['is_nestable'] ?? true;
            $component->allow_inheritance = $data['allow_inheritance'] ?? true;
            $component->allowed_roles = $data['allowed_roles'] ?? null;
            $component->max_instances = $data['max_instances'] ?? null;
            $component->status = $data['status'] ?? 'draft';
            $component->created_by = $request->user()->id;

            $component->save();

            return $component;
        });

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
        description: 'Retrieve a specific component with relationships.',
        tags: ['Management - Components'],
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
            )
        ]
    )]
    public function show(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->with(['parentComponent', 'childComponents'])
            ->first();

        if (!$component) {
            return response()->json([
                'error' => 'Component not found'
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
        responses: [
            new OA\Response(
                response: 200,
                description: 'Component updated successfully'
            )
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
                'error' => 'Component not found'
            ], 404);
        }

        // Validate schema if provided
        if (isset($data['schema'])) {
            $schemaErrors = $this->schemaValidator->validateSchema($data['schema']);
            if (!empty($schemaErrors)) {
                return response()->json([
                    'error' => 'Invalid component schema',
                    'details' => $schemaErrors
                ], 422);
            }
        }

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
        responses: [
            new OA\Response(response: 204, description: 'Component deleted successfully')
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
                'error' => 'Component not found'
            ], 404);
        }

        // Check if component is in use
        if ($component->isInUse()) {
            return response()->json([
                'error' => 'Component is currently in use and cannot be deleted',
                'usage_count' => $component->getUsageCount()
            ], 422);
        }

        $component->delete();

        return response()->json(null, 204);
    }

    /**
     * Create a child component (inheritance).
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/inherit',
        summary: 'Create child component',
        description: 'Create a component that inherits from this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 201, description: 'Child component created successfully')
        ]
    )]
    public function createChild(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $parent = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$parent) {
            return response()->json(['error' => 'Parent component not found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'technical_name' => 'required|string|max:255|unique:components,technical_name,NULL,id,space_id,' . $space->id,
            'description' => 'nullable|string',
            'schema' => 'nullable|array',
            'override_fields' => 'nullable|array'
        ]);

        try {
            $child = $parent->createChild($request->all());
            
            return response()->json([
                'component' => new ComponentResource($child)
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a component variant.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/variant',
        summary: 'Create component variant',
        description: 'Create a variant of this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 201, description: 'Variant created successfully')
        ]
    )]
    public function createVariant(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        $request->validate([
            'variant_name' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'technical_name' => 'nullable|string|max:255|unique:components,technical_name,NULL,id,space_id,' . $space->id,
            'description' => 'nullable|string',
            'variant_config' => 'nullable|array',
            'schema_overrides' => 'nullable|array'
        ]);

        $variant = $component->createVariant($request->all());
        
        return response()->json([
            'component' => new ComponentResource($variant)
        ], 201);
    }

    /**
     * Get component children.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/children',
        summary: 'Get component children',
        description: 'Get all components that inherit from this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Children retrieved successfully')
        ]
    )]
    public function getChildren(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        $children = $component->getDescendants();
        
        return response()->json([
            'children' => ComponentResource::collection($children),
            'count' => $children->count()
        ]);
    }

    /**
     * Get component variants.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/variants',
        summary: 'Get component variants',
        description: 'Get all variants of this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Variants retrieved successfully')
        ]
    )]
    public function getVariants(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        $variants = $component->getAllVariants();
        
        return response()->json([
            'variants' => ComponentResource::collection($variants),
            'variant_group' => $component->variant_group,
            'count' => $variants->count()
        ]);
    }

    /**
     * Get component ancestors.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/ancestors',
        summary: 'Get component ancestors',
        description: 'Get inheritance chain for this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Ancestors retrieved successfully')
        ]
    )]
    public function getAncestors(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        $ancestors = $component->getAncestors();
        
        return response()->json([
            'ancestors' => ComponentResource::collection($ancestors),
            'inheritance_depth' => $ancestors->count()
        ]);
    }

    /**
     * Get component usage statistics.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/{component_id}/usage',
        summary: 'Get component usage',
        description: 'Get detailed usage statistics for this component.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Usage statistics retrieved successfully')
        ]
    )]
    public function getUsage(Request $request, string $componentId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $component = Component::where('uuid', $componentId)
            ->where('space_id', $space->id)
            ->first();

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        return response()->json([
            'usage_statistics' => $component->getUsageStatistics(),
            'used_in_stories' => $component->getUsedInStories(),
            'related_components' => $component->getRelatedComponents()
        ]);
    }

    /**
     * Import components.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components/import',
        summary: 'Import components',
        description: 'Import components from exported data.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Components imported successfully')
        ]
    )]
    public function import(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $request->validate([
            'import_data' => 'required|array',
            'options' => 'nullable|array'
        ]);

        $importData = $request->input('import_data');
        $options = $request->input('options', []);

        try {
            $results = $this->libraryManager->importComponents($space, $importData, $options);
            
            return response()->json([
                'message' => 'Import completed successfully',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Import failed',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Export components.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components/export',
        summary: 'Export components',
        description: 'Export components for backup or transfer.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Components exported successfully')
        ]
    )]
    public function export(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $request->validate([
            'component_ids' => 'nullable|array',
            'component_ids.*' => 'string|exists:components,uuid'
        ]);

        $componentIds = $request->input('component_ids', []);
        $exportData = $this->libraryManager->exportComponents($space, $componentIds);

        return response()->json([
            'export_data' => $exportData,
            'filename' => "components-export-{$space->slug}-" . now()->format('Y-m-d-H-i-s') . '.json'
        ]);
    }

    /**
     * Get component templates.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/components/templates',
        summary: 'Get component templates',
        description: 'Get available component templates and presets.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 200, description: 'Templates retrieved successfully')
        ]
    )]
    public function getTemplates(Request $request): JsonResponse
    {
        $templates = $this->libraryManager->getBuiltInTemplates();
        
        return response()->json([
            'templates' => $templates,
            'categories' => $templates->pluck('template_info.category')->unique()->values()
        ]);
    }

    /**
     * Create component from template.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/components/from-template',
        summary: 'Create from template',
        description: 'Create a component from a template.',
        tags: ['Management - Components'],
        responses: [
            new OA\Response(response: 201, description: 'Component created from template successfully')
        ]
    )]
    public function createFromTemplate(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $request->validate([
            'template' => 'required|array',
            'overrides' => 'nullable|array'
        ]);

        $template = $request->input('template');
        $overrides = $request->input('overrides', []);

        try {
            $component = $this->libraryManager->createFromTemplate($space, $template, $overrides);
            
            return response()->json([
                'component' => new ComponentResource($component)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create component from template',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}