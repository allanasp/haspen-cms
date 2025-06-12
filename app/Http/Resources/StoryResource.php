<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Story',
    type: 'object',
    title: 'Story',
    description: 'A story content object',
    properties: [
        new OA\Property(property: 'id', type: 'string', description: 'Story UUID'),
        new OA\Property(property: 'name', type: 'string', description: 'Story name'),
        new OA\Property(property: 'slug', type: 'string', description: 'URL-friendly slug'),
        new OA\Property(property: 'content', type: 'object', description: 'JSON content structure'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'scheduled', 'archived']),
        new OA\Property(property: 'parent_id', type: 'string', nullable: true, description: 'Parent story UUID'),
        new OA\Property(property: 'language', type: 'string', description: 'Content language code'),
        new OA\Property(property: 'position', type: 'integer', description: 'Sort position'),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'meta_title', type: 'string', nullable: true),
        new OA\Property(property: 'meta_description', type: 'string', nullable: true),
        new OA\Property(property: 'meta_keywords', type: 'string', nullable: true),
        new OA\Property(property: 'og_title', type: 'string', nullable: true),
        new OA\Property(property: 'og_description', type: 'string', nullable: true),
        new OA\Property(property: 'og_image', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_by', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'updated_by', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'parent', ref: '#/components/schemas/Story', nullable: true),
        new OA\Property(property: 'children_count', type: 'integer', description: 'Number of child stories'),
        new OA\Property(property: 'space', ref: '#/components/schemas/Space'),
    ]
)]
/**
 * Story API Resource.
 * Transform story models into consistent JSON responses.
 */
class StoryResource extends JsonResource
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
            'slug' => $this->slug,
            'content' => $this->content,
            'status' => $this->status,
            'parent_id' => $this->parent?->uuid,
            'language' => $this->language,
            'position' => $this->position,
            'published_at' => $this->published_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (only include when loaded)
            'created_by' => $this->whenLoaded('creator', fn() => new UserResource($this->creator)),
            'updated_by' => $this->whenLoaded('updater', fn() => new UserResource($this->updater)),
            'parent' => $this->whenLoaded('parent', fn() => new StoryResource($this->parent)),
            'children' => $this->whenLoaded('children', fn() => StoryResource::collection($this->children)),
            'children_count' => $this->when(
                $this->relationLoaded('children'),
                fn() => $this->children->count(),
                fn() => $this->children_count ?? 0
            ),
            'space' => $this->whenLoaded('space', fn() => new SpaceResource($this->space)),

            // Additional metadata
            'translations' => $this->whenLoaded('translations', fn() => StoryResource::collection($this->translations)),
            'translation_group_id' => $this->when(
                isset($this->translation_group_id),
                $this->translation_group_id
            ),
            'breadcrumbs' => $this->when(
                $request->query('include_breadcrumbs'),
                fn() => $this->generateBreadcrumbs()
            ),
            'full_slug' => $this->generateFullSlug(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => '1.0',
                'last_modified' => $this->updated_at?->toISOString(),
            ],
        ];
    }

    /**
     * Generate breadcrumbs for the story.
     */
    private function generateBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this->resource;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->uuid,
                'name' => $current->name,
                'slug' => $current->slug
            ]);
            
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    /**
     * Generate full slug path for the story.
     */
    private function generateFullSlug(): string
    {
        $slugs = [];
        $current = $this->resource;

        while ($current) {
            array_unshift($slugs, $current->slug);
            $current = $current->parent;
        }

        return implode('/', $slugs);
    }
}