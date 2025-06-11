<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'published_at' => $this->when(
                $this->published_at,
                fn() => $this->published_at?->toISOString()
            ),
            'scheduled_at' => $this->when(
                $this->scheduled_at,
                fn() => $this->scheduled_at?->toISOString()
            ),
            'meta' => $this->when(
                $this->meta_title || $this->meta_description,
                [
                    'title' => $this->meta_title,
                    'description' => $this->meta_description
                ]
            ),
            'parent' => $this->when(
                $this->relationLoaded('parent') && $this->parent,
                fn() => [
                    'id' => $this->parent->uuid,
                    'name' => $this->parent->name,
                    'slug' => $this->parent->slug
                ]
            ),
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn() => $this->children->map(fn($child) => [
                    'id' => $child->uuid,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'status' => $child->status
                ])
            ),
            'translations' => $this->when(
                $this->relationLoaded('translations'),
                fn() => $this->translations->map(fn($translation) => [
                    'id' => $translation->uuid,
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'language' => $translation->language
                ])
            ),
            'breadcrumbs' => $this->when(
                $request->query('include_breadcrumbs'),
                fn() => $this->generateBreadcrumbs()
            ),
            'full_slug' => $this->generateFullSlug(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'created_by' => $this->when(
                $this->relationLoaded('creator'),
                fn() => [
                    'id' => $this->creator->uuid,
                    'name' => $this->creator->name
                ]
            ),
            'updated_by' => $this->when(
                $this->relationLoaded('updater'),
                fn() => [
                    'id' => $this->updater->uuid,
                    'name' => $this->updater->name
                ]
            )
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