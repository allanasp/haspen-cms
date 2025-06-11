<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cdn;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

/**
 * Content Delivery Network API for Assets.
 * Public access to assets with transformation support.
 */
#[OA\Tag(name: 'CDN - Assets', description: 'Public asset delivery with transformations')]
class AssetController extends Controller
{
    /**
     * Get asset with optional transformations.
     */
    #[OA\Get(
        path: '/api/v1/cdn/assets/{filename}',
        summary: 'Get asset with transformations',
        description: 'Retrieve an asset file with optional image transformations.',
        tags: ['CDN - Assets'],
        parameters: [
            new OA\Parameter(
                name: 'filename',
                description: 'The asset filename',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'hero-image.jpg')
            ),
            new OA\Parameter(
                name: 'w',
                description: 'Width for image resize',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 4000, example: 800)
            ),
            new OA\Parameter(
                name: 'h',
                description: 'Height for image resize',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 4000, example: 600)
            ),
            new OA\Parameter(
                name: 'fit',
                description: 'Resize fit mode',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['crop', 'clip', 'scale'], example: 'crop')
            ),
            new OA\Parameter(
                name: 'format',
                description: 'Output format',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['webp', 'jpg', 'png'], example: 'webp')
            ),
            new OA\Parameter(
                name: 'quality',
                description: 'Image quality (1-100)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 80)
            ),
            new OA\Parameter(
                name: 'focal',
                description: 'Focal point for cropping (x,y coordinates 0-1)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', pattern: '^[0-1](\.[0-9]+)?,[0-1](\.[0-9]+)?$', example: '0.5,0.3')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Asset retrieved successfully',
                content: new OA\MediaType(
                    mediaType: 'image/*',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 404, description: 'Asset not found'),
            new OA\Response(response: 429, description: 'Rate limit exceeded')
        ]
    )]
    public function show(Request $request, string $filename): Response|JsonResponse
    {
        $space = $request->get('current_space');
        
        $asset = Asset::where('filename', $filename)
            ->where('space_id', $space->id)
            ->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Asset not found',
                'message' => 'The requested asset could not be found'
            ], 404);
        }

        // Check if file exists in storage
        if (!Storage::exists($asset->file_path)) {
            return response()->json([
                'error' => 'Asset file not found',
                'message' => 'The asset file could not be located in storage'
            ], 404);
        }

        // Get transformation parameters
        $transformations = $this->getTransformationParams($request);

        // Generate cache key for transformed asset
        $cacheKey = $this->generateCacheKey($asset, $transformations);
        
        // Check if transformed version exists in cache
        $cachedPath = "cache/assets/{$cacheKey}";
        
        if (!Storage::exists($cachedPath) && !empty($transformations)) {
            // Apply transformations and cache result
            $transformedContent = $this->applyTransformations($asset, $transformations);
            Storage::put($cachedPath, $transformedContent);
        }

        // Serve the file
        $filePath = !empty($transformations) && Storage::exists($cachedPath) 
            ? $cachedPath 
            : $asset->file_path;

        $content = Storage::get($filePath);
        $mimeType = $this->getMimeType($asset, $transformations);

        return response($content)
            ->header('Content-Type', $mimeType)
            ->header('Content-Length', strlen($content))
            ->header('Cache-Control', 'public, max-age=31536000') // 1 year
            ->header('ETag', md5($content))
            ->header('X-Asset-ID', $asset->uuid);
    }

    /**
     * Get asset metadata and information.
     */
    #[OA\Get(
        path: '/api/v1/cdn/assets/{filename}/info',
        summary: 'Get asset metadata',
        description: 'Retrieve metadata and information about an asset.',
        tags: ['CDN - Assets'],
        parameters: [
            new OA\Parameter(
                name: 'filename',
                description: 'The asset filename',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'hero-image.jpg')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Asset metadata retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'asset' => new OA\Property(
                            properties: [
                                'id' => new OA\Property(type: 'string'),
                                'filename' => new OA\Property(type: 'string'),
                                'title' => new OA\Property(type: 'string'),
                                'alt' => new OA\Property(type: 'string'),
                                'content_type' => new OA\Property(type: 'string'),
                                'file_size' => new OA\Property(type: 'integer'),
                                'metadata' => new OA\Property(type: 'object'),
                                'created_at' => new OA\Property(type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Asset not found')
        ]
    )]
    public function info(Request $request, string $filename): JsonResponse
    {
        $space = $request->get('current_space');
        
        $asset = Asset::where('filename', $filename)
            ->where('space_id', $space->id)
            ->first();

        if (!$asset) {
            return response()->json([
                'error' => 'Asset not found',
                'message' => 'The requested asset could not be found'
            ], 404);
        }

        return response()->json([
            'asset' => [
                'id' => $asset->uuid,
                'filename' => $asset->filename,
                'title' => $asset->title,
                'alt' => $asset->alt,
                'content_type' => $asset->content_type,
                'file_size' => $asset->file_size,
                'metadata' => $asset->metadata,
                'created_at' => $asset->created_at->toISOString()
            ]
        ]);
    }

    /**
     * Extract transformation parameters from request.
     */
    private function getTransformationParams(Request $request): array
    {
        $params = [];

        if ($request->has('w')) {
            $params['width'] = max(1, min(4000, (int) $request->query('w')));
        }

        if ($request->has('h')) {
            $params['height'] = max(1, min(4000, (int) $request->query('h')));
        }

        if ($request->has('fit')) {
            $params['fit'] = $request->query('fit');
        }

        if ($request->has('format')) {
            $params['format'] = $request->query('format');
        }

        if ($request->has('quality')) {
            $params['quality'] = max(1, min(100, (int) $request->query('quality')));
        }

        if ($request->has('focal')) {
            $params['focal'] = $request->query('focal');
        }

        return $params;
    }

    /**
     * Generate cache key for transformed asset.
     */
    private function generateCacheKey(Asset $asset, array $transformations): string
    {
        $key = $asset->id . '_' . $asset->updated_at->timestamp;
        
        if (!empty($transformations)) {
            ksort($transformations);
            $key .= '_' . md5(serialize($transformations));
        }

        return $key;
    }

    /**
     * Apply image transformations.
     */
    private function applyTransformations(Asset $asset, array $transformations): string
    {
        // This is a placeholder for actual image transformation logic
        // You would integrate with a service like Intervention Image, Imagick, or cloud service
        
        $originalContent = Storage::get($asset->file_path);
        
        // For now, return original content
        // In a real implementation, you'd apply the transformations here
        return $originalContent;
    }

    /**
     * Get appropriate MIME type for asset.
     */
    private function getMimeType(Asset $asset, array $transformations): string
    {
        if (isset($transformations['format'])) {
            return match ($transformations['format']) {
                'webp' => 'image/webp',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                default => $asset->content_type
            };
        }

        return $asset->content_type;
    }
}