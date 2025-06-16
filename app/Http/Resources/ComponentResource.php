<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Component API Resource.
 * Transform component models into consistent JSON responses.
 */
class ComponentResource extends JsonResource
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
            'name' => $this->name,
            'internal_name' => $this->internal_name,
            'schema' => $this->schema,
            'is_root' => $this->is_root,
            'is_nestable' => $this->is_nestable,
            'preview_field' => $this->preview_field,
            'preview_tmpl' => $this->preview_tmpl,
            'display' => [
                'icon' => $this->icon,
                'color' => $this->color,
                'tabs' => $this->tabs
            ],
            'usage_count' => $this->when(
                $request->query('include_usage'),
                fn() => $this->getUsageCount()
            ),
            'usage_statistics' => $this->when(
                $request->query('include_usage_stats'),
                fn() => $this->getUsageStatistics()
            ),
            'related_components' => $this->when(
                $request->query('include_related'),
                fn() => $this->getRelatedComponents()
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_by' => $this->when(
                $this->relationLoaded('creator'),
                fn() => [
                    'id' => $this->creator->uuid,
                    'name' => $this->creator->name
                ]
            )
        ];
    }

}