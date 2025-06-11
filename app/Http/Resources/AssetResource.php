<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Asset API Resource.
 * Transform asset models into consistent JSON responses.
 */
class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'title' => $this->title,
            'alt' => $this->alt,
            'content_type' => $this->content_type,
            'file_size' => $this->file_size,
            'file_url' => $this->getFileUrl(),
            'metadata' => $this->metadata,
            'dimensions' => $this->when(
                $this->isImage(),
                fn() => [
                    'width' => $this->metadata['width'] ?? null,
                    'height' => $this->metadata['height'] ?? null
                ]
            ),
            'transformations' => $this->when(
                $this->isImage(),
                fn() => $this->getAvailableTransformations()
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'uploaded_by' => $this->when(
                $this->relationLoaded('uploader'),
                fn() => [
                    'id' => $this->uploader->uuid,
                    'name' => $this->uploader->name
                ]
            )
        ];
    }

    /**
     * Get the public URL for the asset.
     */
    private function getFileUrl(): string
    {
        // This would generate the public URL for the asset
        // Could be CDN URL, S3 URL, or local storage URL
        return url("/api/v1/cdn/assets/{$this->filename}");
    }

    /**
     * Check if the asset is an image.
     */
    private function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    /**
     * Get available image transformations.
     */
    private function getAvailableTransformations(): array
    {
        return [
            'thumbnail' => $this->getFileUrl() . '?w=150&h=150&fit=crop',
            'small' => $this->getFileUrl() . '?w=300&h=300&fit=crop',
            'medium' => $this->getFileUrl() . '?w=600&h=600&fit=crop',
            'large' => $this->getFileUrl() . '?w=1200&h=1200&fit=crop',
            'webp' => $this->getFileUrl() . '?format=webp&quality=80'
        ];
    }
}