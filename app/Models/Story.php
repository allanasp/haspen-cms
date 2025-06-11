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
 * Story Model.
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
 * @property array<string, mixed> $content
 * @property string $language
 * @property int|null $translated_story_id
 * @property array<string> $translated_languages
 * @property string $status
 * @property bool $is_folder
 * @property bool $is_startpage
 * @property int $sort_order
 * @property string|null $path
 * @property array<string, mixed>|null $breadcrumbs
 * @property array<string, mixed>|null $meta_data
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array<string, mixed>|null $robots_meta
 * @property array<string>|null $allowed_roles
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $unpublished_at
 * @property \Carbon\Carbon|null $scheduled_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;
    use HasUuid;
    use MultiTenant;
    use Sluggable;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     * @var array<array-key, mixed>
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
     * @var array<array-key, string>
     */
    protected $hidden = [
        'id',
        'space_id',
    ];

    /**
     * Sluggable configuration.
     */
    protected string $slugSourceField = 'name';

    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected int $cacheTtl = 3600;

    /**
     * Available story statuses.
     */
    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_REVIEW = 'review';
    public const string STATUS_PUBLISHED = 'published';
    public const string STATUS_SCHEDULED = 'scheduled';
    public const string STATUS_ARCHIVED = 'archived';

    /**
     * Get the parent story.
     */
    public function parent(): BelongsTo
    {
        /** @var BelongsTo<Story, Story> $relation */
        $relation = $this->belongsTo(Story::class, 'parent_id');
        return $relation;
    }

    /**
     * Generate full slug based on parent hierarchy.
     */
    public function generateFullSlug(): string
    {
        $slugs = [$this->slug];
        /** @var Story|null $current */
        $current = $this->parent_id !== null ? $this->parent : null;

        while ($current instanceof Story) {
            array_unshift($slugs, $current->slug);
            /** @var Story|null $current */
            $current = $current->parent_id !== null ? $current->parent : null;
        }

        return implode('/', $slugs);
    }

    /**
     * Generate breadcrumbs array.
     *
     * @return array<int|string, mixed>
     */
    public function generateBreadcrumbs(): array
    {
        /** @var array<string, mixed> $breadcrumbs */
        $breadcrumbs = [];
        $current = $this;

        while ($current instanceof Story) {
            $breadcrumbs[] = [
                'uuid' => $current->uuid,
                'name' => $current->name,
                'slug' => $current->slug,
            ];
            /** @var Story|null $current */
            $current = $current->parent_id !== null ? $current->parent : null;
        }

        return array_reverse($breadcrumbs);
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
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Story $story) {
            // Auto-generate full slug and path
            if ($story->isDirty(['slug', 'parent_id'])) {
                $story->full_slug = $story->generateFullSlug();
                $story->path = '/' . $story->full_slug;
                /** @var array<string, mixed> $breadcrumbs */
                $breadcrumbs = $story->generateBreadcrumbs();
                $story->breadcrumbs = $breadcrumbs;
            }
        });
    }
}
