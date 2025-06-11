<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource class for consistent API responses.
 */
final class BaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id ?? null,
            'created_at' => $this->when(
                isset($this->resource->created_at),
                fn () => $this->resource->created_at?->toISOString()
            ),
            'updated_at' => $this->when(
                isset($this->resource->updated_at),
                fn () => $this->resource->updated_at?->toISOString()
            ),
        ];
    }

    /**
     * Include timestamps in the response.
     */
    protected function withTimestamps(): array
    {
        return [
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Include soft delete timestamp if available.
     */
    protected function withSoftDeleteTimestamp(): array
    {
        return [
            'deleted_at' => $this->when(
                property_exists($this->resource, 'deleted_at') && isset($this->resource->deleted_at),
                fn () => $this->resource->deleted_at?->toISOString()
            ),
        ];
    }

    /**
     * Include pagination meta data.
     */
    public function withPaginationMeta(): array
    {
        // Check if resource has pagination methods
        if (\is_object($this->resource) && method_exists($this->resource, 'currentPage')) {
            return [
                'meta' => [
                    'current_page' => $this->resource->currentPage(),
                    'last_page' => $this->resource->lastPage(),
                    'per_page' => $this->resource->perPage(),
                    'total' => $this->resource->total(),
                    'from' => $this->resource->firstItem(),
                    'to' => $this->resource->lastItem(),
                ],
            ];
        }

        return ['meta' => []];
    }
}
