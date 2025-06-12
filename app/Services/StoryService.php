<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Component;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Story management service handling business logic for content operations.
 */
class StoryService extends BaseService
{
    public function __construct(
        private ContentRenderer $contentRenderer,
        private SlugGenerator $slugGenerator,
        private VersionManager $versionManager
    ) {}

    /**
     * Get paginated stories with advanced filtering.
     *
     * @param Space $space
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<Story>
     */
    public function getPaginatedStories(Space $space, array $filters = []): LengthAwarePaginator
    {
        $query = Story::where('space_id', $space->id);

        // Apply filters
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $this->applyAdvancedSearch($query, $search, $filters);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['starts_with'])) {
            $query->where('slug', 'like', $filters['starts_with'] . '%');
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (isset($filters['updated_after'])) {
            $query->where('updated_at', '>=', $filters['updated_after']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Load relationships
        $query->with(['parent', 'creator', 'updater']);

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Create a new story with content validation.
     *
     * @param Space $space
     * @param array<string, mixed> $data
     * @param User $user
     * @return Story
     * @throws \InvalidArgumentException
     */
    public function createStory(Space $space, array $data, User $user): Story
    {
        // Validate content structure
        if (isset($data['content'])) {
            $this->validateContent($data['content'], $space);
        }

        // Generate unique slug
        $slug = $data['slug'] ?? $this->slugGenerator->generateFromTitle($data['name']);
        $data['slug'] = $this->slugGenerator->ensureUnique($slug, $space, Story::class);

        // Validate parent relationship
        if (isset($data['parent_id'])) {
            $this->validateParentStory($data['parent_id'], $space);
        }

        return DB::transaction(function () use ($space, $data, $user) {
            $story = new Story();
            $story->space_id = $space->id;
            $story->name = $data['name'];
            $story->slug = $data['slug'];
            $story->content = $data['content'] ?? [];
            $story->status = $data['status'] ?? 'draft';
            $story->parent_id = $data['parent_id'] ?? null;
            $story->language = $data['language'] ?? $space->default_language ?? 'en';
            $story->meta_title = $data['meta_title'] ?? null;
            $story->meta_description = $data['meta_description'] ?? null;
            $story->meta_keywords = $data['meta_keywords'] ?? null;
            $story->og_title = $data['og_title'] ?? null;
            $story->og_description = $data['og_description'] ?? null;
            $story->og_image = $data['og_image'] ?? null;
            $story->created_by = $user->id;
            $story->updated_by = $user->id;

            $story->save();

            // Create initial version
            $this->versionManager->createVersion($story, $user, 'Initial version');

            return $story;
        });
    }

    /**
     * Update an existing story.
     *
     * @param Story $story
     * @param array<string, mixed> $data
     * @param User $user
     * @return Story
     * @throws \InvalidArgumentException
     */
    public function updateStory(Story $story, array $data, User $user): Story
    {
        // Validate content if provided
        if (isset($data['content'])) {
            $this->validateContent($data['content'], $story->space);
        }

        // Handle slug changes
        if (isset($data['slug']) && $data['slug'] !== $story->slug) {
            $data['slug'] = $this->slugGenerator->ensureUnique(
                $data['slug'],
                $story->space,
                Story::class,
                $story->id
            );
        }

        // Validate parent relationship
        if (isset($data['parent_id']) && $data['parent_id'] !== $story->parent_id) {
            $this->validateParentStory($data['parent_id'], $story->space, $story->id);
        }

        return DB::transaction(function () use ($story, $data, $user) {
            // Create version before updating
            $this->versionManager->createVersion($story, $user, 'Before update');

            // Update story
            $story->fill($data);
            $story->updated_by = $user->id;

            // Handle publishing
            if (isset($data['status'])) {
                $this->handleStatusChange($story, $data['status'], $data);
            }

            $story->save();

            return $story;
        });
    }

    /**
     * Duplicate a story with optional modifications.
     *
     * @param Story $originalStory
     * @param array<string, mixed> $modifications
     * @param User $user
     * @return Story
     */
    public function duplicateStory(Story $originalStory, array $modifications, User $user): Story
    {
        $data = [
            'name' => $modifications['name'] ?? $originalStory->name . ' (Copy)',
            'slug' => $modifications['slug'] ?? null,
            'content' => $originalStory->content,
            'status' => $modifications['status'] ?? 'draft',
            'parent_id' => $modifications['parent_id'] ?? $originalStory->parent_id,
            'language' => $modifications['language'] ?? $originalStory->language,
            'meta_title' => $originalStory->meta_title,
            'meta_description' => $originalStory->meta_description,
            'meta_keywords' => $originalStory->meta_keywords,
            'og_title' => $originalStory->og_title,
            'og_description' => $originalStory->og_description,
            'og_image' => $originalStory->og_image,
        ];

        // Apply modifications
        foreach ($modifications as $key => $value) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        return $this->createStory($originalStory->space, $data, $user);
    }

    /**
     * Publish a story immediately or schedule for later.
     *
     * @param Story $story
     * @param User $user
     * @param Carbon|null $scheduledAt
     * @return Story
     */
    public function publishStory(Story $story, User $user, ?Carbon $scheduledAt = null): Story
    {
        if ($scheduledAt && $scheduledAt->isFuture()) {
            // Schedule for later
            $story->status = 'scheduled';
            $story->scheduled_at = $scheduledAt;
            $story->updated_by = $user->id;
            $story->save();

            // Dispatch scheduled publishing job
            \App\Jobs\PublishStoryJob::dispatch($story)->delay($scheduledAt);
        } else {
            // Publish immediately
            $story->status = 'published';
            $story->published_at = now();
            $story->scheduled_at = null;
            $story->updated_by = $user->id;
            $story->save();

            // Create version for publishing
            $this->versionManager->createVersion($story, $user, 'Published');
        }

        return $story;
    }

    /**
     * Unpublish a story.
     *
     * @param Story $story
     * @param User $user
     * @return Story
     */
    public function unpublishStory(Story $story, User $user): Story
    {
        $story->status = 'draft';
        $story->published_at = null;
        $story->scheduled_at = null;
        $story->updated_by = $user->id;
        $story->save();

        $this->versionManager->createVersion($story, $user, 'Unpublished');

        return $story;
    }

    /**
     * Bulk publish multiple stories.
     *
     * @param Collection<int, Story> $stories
     * @param User $user
     * @param Carbon|null $scheduledAt
     * @return int
     */
    public function bulkPublish(Collection $stories, User $user, ?Carbon $scheduledAt = null): int
    {
        $count = 0;

        foreach ($stories as $story) {
            try {
                $this->publishStory($story, $user, $scheduledAt);
                $count++;
            } catch (\Exception $e) {
                // Log error but continue with other stories
                \Log::warning('Failed to publish story', [
                    'story_id' => $story->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    /**
     * Bulk delete multiple stories.
     *
     * @param Collection<int, Story> $stories
     * @param User $user
     * @return int
     */
    public function bulkDelete(Collection $stories, User $user): int
    {
        $count = 0;

        DB::transaction(function () use ($stories, $user, &$count) {
            foreach ($stories as $story) {
                try {
                    $this->versionManager->createVersion($story, $user, 'Before deletion');
                    $story->delete();
                    $count++;
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete story', [
                        'story_id' => $story->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });

        return $count;
    }

    /**
     * Get story with rendered content.
     *
     * @param Story $story
     * @param bool $resolveAssets
     * @param bool $resolveDatasources
     * @return Story
     */
    public function getStoryWithRenderedContent(
        Story $story,
        bool $resolveAssets = true,
        bool $resolveDatasources = true
    ): Story {
        $story->content = $this->contentRenderer->render(
            $story->content,
            $story->space,
            $resolveAssets,
            $resolveDatasources
        );

        return $story;
    }

    /**
     * Validate story content structure and components.
     *
     * @param array<string, mixed> $content
     * @param Space $space
     * @throws \InvalidArgumentException
     */
    private function validateContent(array $content, Space $space): void
    {
        if (empty($content)) {
            return;
        }

        // Validate root structure
        if (!isset($content['body']) || !is_array($content['body'])) {
            throw new \InvalidArgumentException('Content must have a body array');
        }

        // Validate each component in the body
        $this->validateContentBlocks($content['body'], $space);
    }

    /**
     * Validate content blocks recursively.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param Space $space
     * @throws \InvalidArgumentException
     */
    private function validateContentBlocks(array $blocks, Space $space): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new \InvalidArgumentException('Each content block must be an array');
            }

            if (!isset($block['component'])) {
                throw new \InvalidArgumentException('Each content block must specify a component');
            }

            if (!isset($block['_uid']) || !is_string($block['_uid'])) {
                throw new \InvalidArgumentException('Each content block must have a unique _uid');
            }

            // Validate component exists
            $component = Component::where('internal_name', $block['component'])
                ->where('space_id', $space->id)
                ->first();

            if (!$component) {
                throw new \InvalidArgumentException("Component '{$block['component']}' not found");
            }

            // Validate against component schema
            $validationErrors = $component->validateData($block);
            if (!empty($validationErrors)) {
                $errorMessages = implode(', ', $validationErrors);
                throw new \InvalidArgumentException("Invalid data for component '{$block['component']}': {$errorMessages}");
            }

            // Recursively validate nested blocks
            if (isset($block['body']) && is_array($block['body'])) {
                $this->validateContentBlocks($block['body'], $space);
            }
        }
    }

    /**
     * Validate parent story relationship.
     *
     * @param string|null $parentId
     * @param Space $space
     * @param int|null $excludeId
     * @throws \InvalidArgumentException
     */
    private function validateParentStory(?string $parentId, Space $space, ?int $excludeId = null): void
    {
        if (!$parentId) {
            return;
        }

        $query = Story::where('uuid', $parentId)->where('space_id', $space->id);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $parent = $query->first();

        if (!$parent) {
            throw new \InvalidArgumentException('Parent story not found');
        }

        // Check for circular references (if excludeId is provided, we're updating)
        if ($excludeId) {
            $this->checkCircularReference($parent, $excludeId);
        }
    }

    /**
     * Check for circular parent-child references.
     *
     * @param Story $parent
     * @param int $childId
     * @throws \InvalidArgumentException
     */
    private function checkCircularReference(Story $parent, int $childId): void
    {
        $current = $parent;
        $visited = [];

        while ($current) {
            if ($current->id === $childId) {
                throw new \InvalidArgumentException('Circular parent-child reference detected');
            }

            if (in_array($current->id, $visited)) {
                break; // Already checked this branch
            }

            $visited[] = $current->id;
            $current = $current->parent;
        }
    }

    /**
     * Handle status changes with business logic.
     *
     * @param Story $story
     * @param string $newStatus
     * @param array<string, mixed> $data
     */
    private function handleStatusChange(Story $story, string $newStatus, array $data): void
    {
        $oldStatus = $story->status;

        if ($newStatus === 'published' && $oldStatus !== 'published') {
            $story->published_at = $data['published_at'] ?? now();
        } elseif ($newStatus !== 'published') {
            $story->published_at = null;
        }

        if ($newStatus === 'scheduled') {
            if (!isset($data['scheduled_at'])) {
                throw new \InvalidArgumentException('Scheduled date is required for scheduled status');
            }
            $story->scheduled_at = $data['scheduled_at'];
        } else {
            $story->scheduled_at = null;
        }

        $story->status = $newStatus;
    }

    /**
     * Apply advanced search to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @param array<string, mixed> $filters
     */
    private function applyAdvancedSearch($query, string $search, array $filters): void
    {
        $searchMode = $filters['search_mode'] ?? 'comprehensive';
        
        switch ($searchMode) {
            case 'exact':
                $this->applyExactSearch($query, $search);
                break;
            case 'fulltext':
                $this->applyFullTextSearch($query, $search);
                break;
            case 'content_only':
                $this->applyContentOnlySearch($query, $search);
                break;
            case 'metadata_only':
                $this->applyMetadataOnlySearch($query, $search);
                break;
            case 'comprehensive':
            default:
                $this->applyComprehensiveSearch($query, $search);
                break;
        }
        
        // Apply additional search filters
        if (isset($filters['search_components'])) {
            $this->applyComponentSearch($query, $filters['search_components']);
        }
        
        if (isset($filters['search_tags'])) {
            $this->applyTagSearch($query, $filters['search_tags']);
        }
    }

    /**
     * Apply exact match search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     */
    private function applyExactSearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', $search)
              ->orWhere('slug', $search)
              ->orWhere('meta_title', $search);
        });
    }

    /**
     * Apply PostgreSQL full-text search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     */
    private function applyFullTextSearch($query, string $search): void
    {
        // Prepare search terms for PostgreSQL full-text search
        $searchTerms = $this->prepareSearchTerms($search);
        
        $query->where(function ($q) use ($search, $searchTerms) {
            // Basic like search as fallback
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('slug', 'ilike', "%{$search}%")
              ->orWhere('meta_title', 'ilike', "%{$search}%")
              ->orWhere('meta_description', 'ilike', "%{$search}%");
              
            // PostgreSQL full-text search if available
            if (config('database.default') === 'pgsql') {
                $q->orWhereRaw(
                    "to_tsvector('english', name || ' ' || COALESCE(meta_title, '') || ' ' || COALESCE(meta_description, '')) @@ plainto_tsquery('english', ?)",
                    [$search]
                );
            }
        });
    }

    /**
     * Apply content-only search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     */
    private function applyContentOnlySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            // Search in JSON content using PostgreSQL operators
            if (config('database.default') === 'pgsql') {
                $q->whereRaw("content::text ilike ?", ["%{$search}%"]);
            } else {
                // Fallback for other databases
                $q->whereRaw("JSON_EXTRACT(content, '$') LIKE ?", ["%{$search}%"]);
            }
        });
    }

    /**
     * Apply metadata-only search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     */
    private function applyMetadataOnlySearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('slug', 'ilike', "%{$search}%")
              ->orWhere('meta_title', 'ilike', "%{$search}%")
              ->orWhere('meta_description', 'ilike', "%{$search}%");
              
            // Search in meta_data JSON field
            if (config('database.default') === 'pgsql') {
                $q->orWhereRaw("meta_data::text ilike ?", ["%{$search}%"]);
            } else {
                $q->orWhereRaw("JSON_EXTRACT(meta_data, '$') LIKE ?", ["%{$search}%"]);
            }
        });
    }

    /**
     * Apply comprehensive search across all fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     */
    private function applyComprehensiveSearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            // Text fields
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('slug', 'ilike', "%{$search}%")
              ->orWhere('meta_title', 'ilike', "%{$search}%")
              ->orWhere('meta_description', 'ilike', "%{$search}%");
              
            // JSON fields
            if (config('database.default') === 'pgsql') {
                $q->orWhereRaw("content::text ilike ?", ["%{$search}%"])
                  ->orWhereRaw("meta_data::text ilike ?", ["%{$search}%"]);
            } else {
                $q->orWhereRaw("JSON_EXTRACT(content, '$') LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_EXTRACT(meta_data, '$') LIKE ?", ["%{$search}%"]);
            }
        });
    }

    /**
     * Apply component-specific search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string> $components
     */
    private function applyComponentSearch($query, array $components): void
    {
        $query->where(function ($q) use ($components) {
            foreach ($components as $component) {
                if (config('database.default') === 'pgsql') {
                    $q->orWhereRaw("content @> ?", [json_encode([['component' => $component]])]);
                } else {
                    $q->orWhereRaw("JSON_SEARCH(content, 'one', ?) IS NOT NULL", [$component]);
                }
            }
        });
    }

    /**
     * Apply tag-based search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string> $tags
     */
    private function applyTagSearch($query, array $tags): void
    {
        $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                if (config('database.default') === 'pgsql') {
                    $q->orWhereRaw("meta_data @> ?", [json_encode(['tags' => [$tag]])]);
                } else {
                    $q->orWhereRaw("JSON_SEARCH(meta_data, 'one', ?) IS NOT NULL", [$tag]);
                }
            }
        });
    }

    /**
     * Prepare search terms for full-text search.
     *
     * @param string $search
     * @return string
     */
    private function prepareSearchTerms(string $search): string
    {
        // Remove special characters and split into words
        $terms = preg_split('/\s+/', trim($search));
        $terms = array_filter($terms, fn($term) => strlen($term) > 2);
        
        // Add wildcard operators for partial matching
        $terms = array_map(fn($term) => $term . ':*', $terms);
        
        return implode(' & ', $terms);
    }

    /**
     * Get search suggestions based on query.
     *
     * @param Space $space
     * @param string $query
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getSearchSuggestions(Space $space, string $query, int $limit = 10): array
    {
        $suggestions = [];
        
        // Name suggestions
        $nameMatches = Story::where('space_id', $space->id)
            ->where('name', 'ilike', "%{$query}%")
            ->limit($limit)
            ->pluck('name', 'uuid')
            ->map(fn($name, $uuid) => [
                'type' => 'story_name',
                'value' => $name,
                'uuid' => $uuid,
            ]);
        
        $suggestions = array_merge($suggestions, $nameMatches->toArray());
        
        // Tag suggestions from meta_data
        if (config('database.default') === 'pgsql') {
            $tagMatches = Story::where('space_id', $space->id)
                ->whereRaw("meta_data @> ?", [json_encode(['tags' => []])])
                ->get()
                ->pluck('meta_data')
                ->flatMap(fn($meta) => $meta['tags'] ?? [])
                ->filter(fn($tag) => stripos($tag, $query) !== false)
                ->unique()
                ->take($limit / 2)
                ->map(fn($tag) => [
                    'type' => 'tag',
                    'value' => $tag,
                ]);
                
            $suggestions = array_merge($suggestions, $tagMatches->toArray());
        }
        
        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Get search analytics/stats.
     *
     * @param Space $space
     * @return array<string, mixed>
     */
    public function getSearchStats(Space $space): array
    {
        $stats = [
            'total_stories' => Story::where('space_id', $space->id)->count(),
            'published_stories' => Story::where('space_id', $space->id)->where('status', 'published')->count(),
            'draft_stories' => Story::where('space_id', $space->id)->where('status', 'draft')->count(),
            'languages' => Story::where('space_id', $space->id)->distinct('language')->pluck('language'),
            'recent_stories' => Story::where('space_id', $space->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
        
        // Component usage stats
        if (config('database.default') === 'pgsql') {
            $componentStats = DB::select("
                SELECT 
                    jsonb_path_query(content, '$[*].component')::text as component,
                    COUNT(*) as usage_count
                FROM stories 
                WHERE space_id = ? AND content IS NOT NULL
                GROUP BY component
                ORDER BY usage_count DESC
                LIMIT 10
            ", [$space->id]);
            
            $stats['popular_components'] = collect($componentStats)->map(function ($item) {
                return [
                    'component' => trim($item->component, '"'),
                    'usage_count' => $item->usage_count,
                ];
            });
        }
        
        return $stats;
    }
}