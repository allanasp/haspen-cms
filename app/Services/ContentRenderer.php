<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Component;
use App\Models\DatasourceEntry;
use App\Models\Space;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * Content rendering service for processing JSON content structures.
 */
class ContentRenderer
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Render content with asset and datasource resolution.
     *
     * @param array<string, mixed> $content
     * @param Space $space
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return array<string, mixed>
     */
    public function render(
        array $content,
        Space $space,
        bool $resolveAssets = true,
        bool $resolveDatasources = true
    ): array {
        if (empty($content) || !isset($content['body'])) {
            return $content;
        }

        $rendered = $content;
        $rendered['body'] = $this->renderBlocks(
            $content['body'],
            $space,
            $resolveAssets,
            $resolveDatasources
        );

        return $rendered;
    }

    /**
     * Render content blocks recursively.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param Space $space
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return array<int, array<string, mixed>>
     */
    private function renderBlocks(
        array $blocks,
        Space $space,
        bool $resolveAssets,
        bool $resolveDatasources
    ): array {
        $rendered = [];

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['component'])) {
                $rendered[] = $block;
                continue;
            }

            $renderedBlock = $this->renderBlock($block, $space, $resolveAssets, $resolveDatasources);
            $rendered[] = $renderedBlock;
        }

        return $rendered;
    }

    /**
     * Render a single content block.
     *
     * @param array<string, mixed> $block
     * @param Space $space
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return array<string, mixed>
     */
    private function renderBlock(
        array $block,
        Space $space,
        bool $resolveAssets,
        bool $resolveDatasources
    ): array {
        $rendered = $block;

        // Get component definition for schema-based processing
        $component = $this->getComponent($block['component'], $space);

        if ($component) {
            $rendered = $this->processBlockFields($rendered, $component, $space, $resolveAssets, $resolveDatasources);
        }

        // Process nested blocks
        if (isset($block['body']) && is_array($block['body'])) {
            $rendered['body'] = $this->renderBlocks(
                $block['body'],
                $space,
                $resolveAssets,
                $resolveDatasources
            );
        }

        return $rendered;
    }

    /**
     * Process block fields based on component schema.
     *
     * @param array<string, mixed> $block
     * @param Component $component
     * @param Space $space
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return array<string, mixed>
     */
    private function processBlockFields(
        array $block,
        Component $component,
        Space $space,
        bool $resolveAssets,
        bool $resolveDatasources
    ): array {
        $schema = $component->schema ?? [];

        foreach ($schema as $fieldName => $fieldConfig) {
            if (!isset($block[$fieldName])) {
                continue;
            }

            $fieldType = $fieldConfig['type'] ?? 'text';
            $fieldValue = $block[$fieldName];

            $block[$fieldName] = $this->processFieldValue(
                $fieldValue,
                $fieldType,
                $space,
                $resolveAssets,
                $resolveDatasources
            );
        }

        return $block;
    }

    /**
     * Process field value based on field type.
     *
     * @param mixed $value
     * @param string $type
     * @param Space $space
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return mixed
     */
    private function processFieldValue(
        mixed $value,
        string $type,
        Space $space,
        bool $resolveAssets,
        bool $resolveDatasources
    ): mixed {
        return match ($type) {
            'asset' => $resolveAssets ? $this->resolveAsset($value, $space) : $value,
            'assets' => $resolveAssets ? $this->resolveAssets($value, $space) : $value,
            'datasource' => $resolveDatasources ? $this->resolveDatasource($value, $space) : $value,
            'story_link' => $this->resolveStoryLink($value, $space),
            'richtext' => $this->processRichText($value, $space, $resolveAssets),
            'blocks' => is_array($value) ? $this->renderBlocks($value, $space, $resolveAssets, $resolveDatasources) : $value,
            default => $value
        };
    }

    /**
     * Resolve asset reference to full asset data.
     *
     * @param mixed $value
     * @param Space $space
     * @return array<string, mixed>|null
     */
    private function resolveAsset(mixed $value, Space $space): ?array
    {
        if (!is_string($value) && !is_array($value)) {
            return null;
        }

        $assetId = is_array($value) ? ($value['id'] ?? null) : $value;

        if (!$assetId) {
            return null;
        }

        $cacheKey = "asset.{$space->id}.{$assetId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($assetId, $space) {
            $asset = Asset::where('space_id', $space->id)
                ->where(function ($query) use ($assetId) {
                    $query->where('uuid', $assetId)
                          ->orWhere('filename', $assetId);
                })
                ->first();

            if (!$asset) {
                return null;
            }

            return [
                'id' => $asset->uuid,
                'filename' => $asset->filename,
                'title' => $asset->title,
                'alt' => $asset->alt,
                'content_type' => $asset->content_type,
                'file_size' => $asset->file_size,
                'url' => $this->generateAssetUrl($asset),
                'metadata' => $asset->metadata,
                'transformations' => $this->generateAssetTransformations($asset)
            ];
        });
    }

    /**
     * Resolve multiple asset references.
     *
     * @param mixed $value
     * @param Space $space
     * @return array<int, array<string, mixed>>
     */
    private function resolveAssets(mixed $value, Space $space): array
    {
        if (!is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $assetValue) {
            $asset = $this->resolveAsset($assetValue, $space);
            if ($asset) {
                $resolved[] = $asset;
            }
        }

        return $resolved;
    }

    /**
     * Resolve datasource reference to actual data.
     *
     * @param mixed $value
     * @param Space $space
     * @return array<string, mixed>|null
     */
    private function resolveDatasource(mixed $value, Space $space): ?array
    {
        if (!is_array($value) || !isset($value['datasource']) || !isset($value['entry'])) {
            return null;
        }

        $datasourceSlug = $value['datasource'];
        $entryIdentifier = $value['entry'];

        $cacheKey = "datasource.{$space->id}.{$datasourceSlug}.{$entryIdentifier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($datasourceSlug, $entryIdentifier, $space) {
            $entry = DatasourceEntry::whereHas('datasource', function ($query) use ($datasourceSlug, $space) {
                $query->where('slug', $datasourceSlug)
                      ->where('space_id', $space->id);
            })
            ->where(function ($query) use ($entryIdentifier) {
                $query->where('uuid', $entryIdentifier)
                      ->orWhere('name', $entryIdentifier);
            })
            ->first();

            if (!$entry) {
                return null;
            }

            return [
                'id' => $entry->uuid,
                'name' => $entry->name,
                'value' => $entry->value,
                'data' => $entry->data,
                'dimensions' => $entry->dimensions
            ];
        });
    }

    /**
     * Resolve story link reference.
     *
     * @param mixed $value
     * @param Space $space
     * @return array<string, mixed>|null
     */
    private function resolveStoryLink(mixed $value, Space $space): ?array
    {
        if (!is_array($value) || !isset($value['story'])) {
            return null;
        }

        $storyIdentifier = $value['story'];

        $cacheKey = "story_link.{$space->id}.{$storyIdentifier}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($storyIdentifier, $space) {
            $story = Story::where('space_id', $space->id)
                ->where(function ($query) use ($storyIdentifier) {
                    $query->where('uuid', $storyIdentifier)
                          ->orWhere('slug', $storyIdentifier);
                })
                ->where('status', 'published')
                ->first();

            if (!$story) {
                return null;
            }

            return [
                'id' => $story->uuid,
                'name' => $story->name,
                'slug' => $story->slug,
                'url' => $this->generateStoryUrl($story),
                'meta_title' => $story->meta_title,
                'meta_description' => $story->meta_description
            ];
        });
    }

    /**
     * Process rich text content to resolve embedded assets and links.
     *
     * @param mixed $value
     * @param Space $space
     * @param bool $resolveAssets
     * @return string
     */
    private function processRichText(mixed $value, Space $space, bool $resolveAssets): string
    {
        if (!is_string($value)) {
            return '';
        }

        $processed = $value;

        if ($resolveAssets) {
            // Replace asset references in rich text
            $processed = preg_replace_callback(
                '/\[asset:([^\]]+)\]/',
                function ($matches) use ($space) {
                    $asset = $this->resolveAsset($matches[1], $space);
                    return $asset ? $asset['url'] : $matches[0];
                },
                $processed
            );
        }

        // Replace story links
        $processed = preg_replace_callback(
            '/\[story:([^\]]+)\]/',
            function ($matches) use ($space) {
                $link = $this->resolveStoryLink(['story' => $matches[1]], $space);
                return $link ? $link['url'] : $matches[0];
            },
            $processed
        );

        return $processed;
    }

    /**
     * Get component definition with caching.
     *
     * @param string $componentName
     * @param Space $space
     * @return Component|null
     */
    private function getComponent(string $componentName, Space $space): ?Component
    {
        $cacheKey = "component.{$space->id}.{$componentName}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($componentName, $space) {
            return Component::where('internal_name', $componentName)
                ->where('space_id', $space->id)
                ->first();
        });
    }

    /**
     * Generate asset URL.
     *
     * @param Asset $asset
     * @return string
     */
    private function generateAssetUrl(Asset $asset): string
    {
        // This would generate the proper asset URL based on storage configuration
        return url("/api/v1/cdn/assets/{$asset->filename}");
    }

    /**
     * Generate asset transformation URLs.
     *
     * @param Asset $asset
     * @return array<string, string>
     */
    private function generateAssetTransformations(Asset $asset): array
    {
        if (!str_starts_with($asset->content_type, 'image/')) {
            return [];
        }

        $baseUrl = $this->generateAssetUrl($asset);

        return [
            'thumbnail' => $baseUrl . '?w=150&h=150&fit=crop',
            'small' => $baseUrl . '?w=300&h=300&fit=crop',
            'medium' => $baseUrl . '?w=600&h=600&fit=crop',
            'large' => $baseUrl . '?w=1200&h=1200&fit=crop',
            'webp' => $baseUrl . '?format=webp&quality=80'
        ];
    }

    /**
     * Generate story URL.
     *
     * @param Story $story
     * @return string
     */
    private function generateStoryUrl(Story $story): string
    {
        // This would generate the proper story URL based on frontend configuration
        return url("/stories/{$story->slug}");
    }

    /**
     * Clear cache for a specific space.
     *
     * @param Space $space
     */
    public function clearCache(Space $space): void
    {
        $patterns = [
            "asset.{$space->id}.*",
            "datasource.{$space->id}.*",
            "story_link.{$space->id}.*",
            "component.{$space->id}.*"
        ];

        foreach ($patterns as $pattern) {
            Cache::tags(['content_renderer', "space.{$space->id}"])->flush();
        }
    }
}