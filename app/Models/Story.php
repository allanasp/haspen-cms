<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Story Model
 *
 * Represents content pages/posts in the headless CMS.
 * Stories contain structured content using components (Storyblok-style).
 *
 * @property int $id
 * @property string $uuid
 * @property int $space_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string $full_slug
 * @property array $content
 * @property string $language
 * @property int|null $translated_story_id
 * @property array $translated_languages
 * @property string $status
 * @property bool $is_folder
 * @property bool $is_startpage
 * @property int $sort_order
 * @property string|null $path
 * @property array|null $breadcrumbs
 * @property array|null $meta_data
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array|null $robots_meta
 * @property array|null $allowed_roles
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $unpublished_at
 * @property \Carbon\Carbon|null $scheduled_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Story extends Model
{
    use HasFactory, HasUuid, MultiTenant, Sluggable, SoftDeletes, Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'full_slug',
        'content',
        'language',
        'translated_story_id',
        'translated_languages',
        'status',
        'is_folder',
        'is_startpage',
        'sort_order',
        'path',
        'breadcrumbs',
        'meta_data',
        'meta_title',
        'meta_description',
        'robots_meta',
        'allowed_roles',
        'published_at',
        'unpublished_at',
        'scheduled_at',
        'created_by',
        'updated_by',
        'published_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'content' => Json::class,
        'translated_languages' => Json::class,
        'is_folder' => 'boolean',
        'is_startpage' => 'boolean',
        'breadcrumbs' => Json::class,
        'meta_data' => Json::class,
        'robots_meta' => Json::class,
        'allowed_roles' => Json::class,
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'id',
        'space_id',
    ];

    /**
     * Sluggable configuration
     */
    protected string $slugSourceField = 'name';
    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (1 hour)
     */
    protected int $cacheTtl = 3600;

    /**
     * Available story statuses
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get the parent story.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'parent_id');
    }

    /**
     * Get child stories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Story::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get the translated story.
     */
    public function translatedStory(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'translated_story_id');
    }

    /**
     * Get translations of this story.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Story::class, 'translated_story_id');
    }

    /**
     * Get the user who created this story.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this story.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who published this story.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Scope to published stories only.
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to draft stories only.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to scheduled stories.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '>', now());
    }

    /**
     * Scope stories by language.
     */
    public function scopeLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Scope to folder stories.
     */
    public function scopeFolders($query)
    {
        return $query->where('is_folder', true);
    }

    /**
     * Scope to content stories (not folders).
     */
    public function scopeContent($query)
    {
        return $query->where('is_folder', false);
    }

    /**
     * Scope to root level stories.
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope by parent.
     */
    public function scopeChildren($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Check if the story is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && 
               $this->published_at && 
               $this->published_at->isPast();
    }

    /**
     * Check if the story is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the story is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && 
               $this->scheduled_at && 
               $this->scheduled_at->isFuture();
    }

    /**
     * Check if the story is a folder.
     */
    public function isFolder(): bool
    {
        return $this->is_folder;
    }

    /**
     * Check if the story is the start page.
     */
    public function isStartpage(): bool
    {
        return $this->is_startpage;
    }

    /**
     * Publish the story.
     */
    public function publish(?int $publishedBy = null): bool
    {
        return $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $publishedBy,
        ]);
    }

    /**
     * Unpublish the story.
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => self::STATUS_DRAFT,
            'unpublished_at' => now(),
        ]);
    }

    /**
     * Schedule the story for publishing.
     */
    public function schedule(\Carbon\Carbon $scheduledAt, ?int $publishedBy = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'published_by' => $publishedBy,
        ]);
    }

    /**
     * Generate full slug based on parent hierarchy.
     */
    public function generateFullSlug(): string
    {
        $slugs = [$this->slug];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($slugs, $parent->slug);
            $parent = $parent->parent;
        }

        return implode('/', $slugs);
    }

    /**
     * Update full slug and path.
     */
    public function updateFullSlugAndPath(): void
    {
        $this->full_slug = $this->generateFullSlug();
        $this->path = '/' . $this->full_slug;
        $this->breadcrumbs = $this->generateBreadcrumbs();
        $this->save();
    }

    /**
     * Generate breadcrumbs array.
     */
    public function generateBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'uuid' => $current->uuid,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    /**
     * Get content component by key.
     */
    public function getContentComponent(string $key): ?array
    {
        return $this->content[$key] ?? null;
    }

    /**
     * Get all components of a specific type from content.
     */
    public function getComponentsByType(string $componentType): array
    {
        $components = [];
        
        if (isset($this->content['body']) && is_array($this->content['body'])) {
            foreach ($this->content['body'] as $component) {
                if (isset($component['component']) && $component['component'] === $componentType) {
                    $components[] = $component;
                }
            }
        }

        return $components;
    }

    /**
     * Check if story has translation in language.
     */
    public function hasTranslation(string $language): bool
    {
        return in_array($language, $this->translated_languages ?? []);
    }

    /**
     * Get URL for the story.
     */
    public function getUrl(string $environment = 'production'): string
    {
        $config = $this->space->getEnvironmentConfig($environment);
        $baseUrl = $config['base_url'] ?? '';
        
        return rtrim($baseUrl, '/') . $this->path;
    }

    /**
     * Check if user can access this story.
     */
    public function canBeAccessedBy(?User $user = null): bool
    {
        // Public stories (no role restrictions)
        if (empty($this->allowed_roles)) {
            return true;
        }

        // Require authentication
        if (!$user) {
            return false;
        }

        // Check user role in space
        $userRole = $user->getRoleInSpace($this->space_id);
        
        if (!$userRole) {
            return false;
        }

        return in_array($userRole->slug, $this->allowed_roles);
    }

    /**
     * Get SEO meta data.
     */
    public function getSeoMeta(): array
    {
        return [
            'title' => $this->meta_title ?: $this->name,
            'description' => $this->meta_description,
            'robots' => $this->robots_meta ?? ['index' => true, 'follow' => true],
            'canonical' => $this->getUrl(),
        ];
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('content');
        $this->forgetCache('seo_meta');
        $this->forgetCache('url');
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Story $story) {
            // Auto-generate full slug and path
            if ($story->isDirty(['slug', 'parent_id'])) {
                $story->full_slug = $story->generateFullSlug();
                $story->path = '/' . $story->full_slug;
                $story->breadcrumbs = $story->generateBreadcrumbs();
            }
        });
    }
}