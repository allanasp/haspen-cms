<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Management API for Assets.
 * Admin operations for asset upload and management with cloud storage.
 */
#[OA\Tag(name: 'Management - Assets', description: 'Asset management operations')]
class AssetController extends Controller
{
    /**
     * List assets with filtering.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/assets',
        summary: 'List assets',
        description: 'Get a paginated list of assets with filtering.',
        tags: ['Management - Assets'],
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
                description: 'Search in asset filename and title',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'hero')
            ),
            new OA\Parameter(
                name: 'content_type',
                description: 'Filter by content type',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'image/jpeg')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Assets retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'assets' => new OA\Property(type: 'array', items: new OA\Items(ref: AssetResource::class)),
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
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $space = $request->get('current_space');
        
        $query = Asset::where('space_id', $space->id);

        // Apply search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($contentType = $request->query('content_type')) {
            $query->where('content_type', 'like', $contentType . '%');
        }

        // Order by creation date (newest first)
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $assets = $query->paginate($perPage);

        return response()->json([
            'assets' => AssetResource::collection($assets->items()),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage()
            ]
        ]);
    }

    /**
     * Upload a new asset.
     */
    #[OA\Post(
        path: '/api/v1/spaces/{space_id}/assets',
        summary: 'Upload asset',
        description: 'Upload a new asset file.',
        tags: ['Management - Assets'],
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
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        'file' => new OA\Property(
                            type: 'string',
                            format: 'binary',
                            description: 'File to upload'
                        ),
                        'title' => new OA\Property(
                            type: 'string',
                            description: 'Asset title',
                            example: 'Hero Image'
                        ),
                        'alt' => new OA\Property(
                            type: 'string',
                            description: 'Alt text for images',
                            example: 'Beautiful hero image'
                        ),
                        'folder' => new OA\Property(
                            type: 'string',
                            description: 'Folder path',
                            example: 'images/heroes'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Asset uploaded successfully',
                content: new OA\JsonContent(
                    properties: [
                        'asset' => new OA\Property(ref: AssetResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(StoreAssetRequest $request): JsonResponse
    {
        $space = $request->get('current_space');
        $file = $request->file('file');

        // Validate file
        $this->validateAssetFile($file, $space);

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = $this->generateUniqueFilename($originalName, $extension, $space);

        // Determine folder path
        $folder = $request->input('folder', 'uploads');
        $filePath = "{$space->id}/{$folder}/{$filename}";

        // Store file
        $path = $file->storeAs(
            dirname($filePath),
            basename($filePath),
            ['disk' => 'public']
        );

        // Generate file hash for deduplication
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Extract metadata
        $metadata = $this->extractFileMetadata($file);

        // Create asset record
        $asset = new Asset();
        $asset->space_id = $space->id;
        $asset->filename = $filename;
        $asset->original_filename = $originalName;
        $asset->title = $request->input('title', pathinfo($originalName, PATHINFO_FILENAME));
        $asset->alt = $request->input('alt');
        $asset->content_type = $file->getMimeType();
        $asset->file_size = $file->getSize();
        $asset->file_path = $path;
        $asset->file_hash = $fileHash;
        $asset->metadata = $metadata;
        $asset->uploaded_by = $request->user()->id;

        $asset->save();

        return response()->json([
            'asset' => new AssetResource($asset)
        ], 201);
    }

    /**
     * Get a specific asset.
     */
    #[OA\Get(
        path: '/api/v1/spaces/{space_id}/assets/{asset_id}',
        summary: 'Get asset',
        description: 'Retrieve a specific asset by ID.',
        tags: ['Management - Assets'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'asset_id',
                description: 'Asset UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Asset retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'asset' => new OA\Property(ref: AssetResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Asset not found')
        ]
    )]
    public function show(Request $request, string $assetId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $asset = Asset::where('uuid', $assetId)
            ->where('space_id', $space->id)
            ->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Asset not found',
                'message' => 'The requested asset could not be found'
            ], 404);
        }

        return response()->json([
            'asset' => new AssetResource($asset)
        ]);
    }

    /**
     * Update asset metadata.
     */
    #[OA\Put(
        path: '/api/v1/spaces/{space_id}/assets/{asset_id}',
        summary: 'Update asset',
        description: 'Update asset metadata.',
        tags: ['Management - Assets'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'asset_id',
                description: 'Asset UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'title' => new OA\Property(type: 'string', example: 'Updated Title'),
                    'alt' => new OA\Property(type: 'string', example: 'Updated alt text'),
                    'filename' => new OA\Property(type: 'string', example: 'new-filename.jpg')
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Asset updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        'asset' => new OA\Property(ref: AssetResource::class)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Asset not found')
        ]
    )]
    public function update(UpdateAssetRequest $request, string $assetId): JsonResponse
    {
        $space = $request->get('current_space');
        $data = $request->validated();
        
        $asset = Asset::where('uuid', $assetId)
            ->where('space_id', $space->id)
            ->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Asset not found',
                'message' => 'The requested asset could not be found'
            ], 404);
        }

        // Update fields
        $asset->fill($data);
        $asset->save();

        return response()->json([
            'asset' => new AssetResource($asset)
        ]);
    }

    /**
     * Delete an asset.
     */
    #[OA\Delete(
        path: '/api/v1/spaces/{space_id}/assets/{asset_id}',
        summary: 'Delete asset',
        description: 'Delete an asset and its file.',
        tags: ['Management - Assets'],
        parameters: [
            new OA\Parameter(
                name: 'space_id',
                description: 'Space UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'asset_id',
                description: 'Asset UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Asset deleted successfully'),
            new OA\Response(response: 404, description: 'Asset not found')
        ]
    )]
    public function destroy(Request $request, string $assetId): JsonResponse
    {
        $space = $request->get('current_space');
        
        $asset = Asset::where('uuid', $assetId)
            ->where('space_id', $space->id)
            ->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Asset not found',
                'message' => 'The requested asset could not be found'
            ], 404);
        }

        // Delete file from storage
        if (Storage::exists($asset->file_path)) {
            Storage::delete($asset->file_path);
        }

        // Delete asset record
        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate uploaded asset file.
     */
    private function validateAssetFile(UploadedFile $file, $space): void
    {
        // Check file size limits based on space plan
        $maxSize = $space->getResourceLimit('max_asset_size', 10 * 1024 * 1024); // 10MB default
        
        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException("File size exceeds limit of " . ($maxSize / 1024 / 1024) . "MB");
        }

        // Check asset count limits
        $assetCount = Asset::where('space_id', $space->id)->count();
        $assetLimit = $space->getResourceLimit('asset_limit', 1000);
        
        if ($assetCount >= $assetLimit) {
            throw new \InvalidArgumentException("Asset limit of {$assetLimit} reached");
        }
    }

    /**
     * Generate unique filename to avoid conflicts.
     */
    private function generateUniqueFilename(string $originalName, string $extension, $space): string
    {
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $basename);
        $basename = trim($basename, '-_');
        
        if (empty($basename)) {
            $basename = 'file';
        }

        $filename = $basename . '.' . $extension;
        $counter = 1;

        while (Asset::where('space_id', $space->id)->where('filename', $filename)->exists()) {
            $filename = $basename . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $filename;
    }

    /**
     * Extract metadata from uploaded file.
     */
    private function extractFileMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType()
        ];

        // Extract image-specific metadata
        if (str_starts_with($file->getMimeType(), 'image/')) {
            try {
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo !== false) {
                    $metadata['width'] = $imageInfo[0];
                    $metadata['height'] = $imageInfo[1];
                    $metadata['type'] = $imageInfo[2];
                }
            } catch (\Exception $e) {
                // Ignore metadata extraction errors
            }
        }

        return $metadata;
    }
}