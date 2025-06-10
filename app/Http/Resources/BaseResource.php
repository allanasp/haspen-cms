<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base resource class for consistent API responses
 */
class BaseResource extends JsonResource
{
    /**
     * Transform the resource into an array
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->when($this->created_at, fn() => $this->created_at?->toISOString()),
            'updated_at' => $this->when($this->updated_at, fn() => $this->updated_at?->toISOString()),
        ];
    }

    /**
     * Include timestamps in the response
     */
    protected function withTimestamps(): array
    {
        return [
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Include soft delete timestamp if available
     */
    protected function withSoftDeleteTimestamp(): array
    {
        return [
            'deleted_at' => $this->when(
                property_exists($this->resource, 'deleted_at') && $this->deleted_at,
                fn() => $this->deleted_at?->toISOString()
            ),
        ];
    }

    /**
     * Include pagination meta data
     */
    public function withPaginationMeta(): array
    {
        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }
}