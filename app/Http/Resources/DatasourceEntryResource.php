<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datasource Entry API Resource.
 * Transform datasource entry models into consistent JSON responses.
 */
class DatasourceEntryResource extends JsonResource
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
            'value' => $this->value,
            'dimensions' => $this->dimensions,
            'datasource' => $this->when(
                $this->relationLoaded('datasource'),
                fn() => [
                    'id' => $this->datasource->uuid,
                    'name' => $this->datasource->name,
                    'slug' => $this->datasource->slug,
                    'type' => $this->datasource->type
                ]
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }
}