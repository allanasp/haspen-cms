<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource class for consistent API responses.
 * @psalm-suppress UnusedClass
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
        /** @var object $resource */
        $resource = $this->resource;
        return [
            'id' => $resource->id ?? null,
            'created_at' => $this->when(
                isset($resource->created_at),
                fn () => $resource->created_at?->format('c')
            ),
            'updated_at' => $this->when(
                isset($resource->updated_at),
                fn () => $resource->updated_at?->format('c')
            ),
        ];
    }

    /**
     * Include timestamps in the response.
     */
    protected function withTimestamps(): array
    {
        /** @var object $resource */
        $resource = $this->resource;
        return [
            'created_at' => $resource->created_at?->format('c'),
            'updated_at' => $resource->updated_at?->format('c'),
        ];
    }

    /**
     * Include soft delete timestamp if available.
     */
    protected function withSoftDeleteTimestamp(): array
    {
        /** @var object $resource */
        $resource = $this->resource;
        return [
            'deleted_at' => $this->when(
                property_exists($resource, 'deleted_at') && isset($resource->deleted_at),
                fn () => $resource->deleted_at?->format('c')
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
