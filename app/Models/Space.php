<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Space Model.
 *
 * Represents a tenant in the multi-tenant headless CMS.
 * Each space provides complete data isolation and custom configuration.
 * @psalm-suppress PossiblyUnusedMethod
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed> $environments
 * @property string $default_language
 * @property array<string> $languages
 * @property string $plan
 * @property int|null $story_limit
 * @property int|null $asset_limit
 * @property int|null $api_limit
 * @property string $status
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon|null $suspended_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class Space extends Model
{
    /** @use HasFactory<\Database\Factories\SpaceFactory> */
    use HasFactory;
    use HasUuid;
    use Sluggable;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'description',
        'settings',
        'environments',
        'default_language',
        'languages',
        'plan',
        'story_limit',
        'asset_limit',
        'api_limit',
        'status',
        'trial_ends_at',
        'suspended_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<array-key, mixed>
     */
    protected $casts = [
        'settings' => Json::class,
        'environments' => Json::class,
        'languages' => Json::class,
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<array-key, string>
     */
    protected $hidden = [
        'id',
    ];

    /**
     * Sluggable configuration.
     */
    protected string $slugSourceField = 'name';

    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    /**
     * Available space statuses.
     */
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_SUSPENDED = 'suspended';
    public const string STATUS_DELETED = 'deleted';

    /**
     * Available plans.
     */
    public const string PLAN_FREE = 'free';
    public const string PLAN_PRO = 'pro';
    public const string PLAN_ENTERPRISE = 'enterprise';

    /**
     * Get all stories in this space.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function stories(): HasMany
    {
        /** @var HasMany<Story, Space> $relation */
        $relation = $this->hasMany(Story::class);
        return $relation;
    }

    /**
     * Get space configuration for a specific environment.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getEnvironmentConfig(string $environment = 'production'): array
    {
        /** @var mixed $environments */
        $environments = $this->environments;
        if (!is_array($environments)) {
            return [];
        }
        /** @var mixed $config */
        $config = $environments[$environment] ?? [];
        return is_array($config) ? $config : [];
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('stories_count');
        $this->forgetCache('assets_count');
        $this->forgetCache('assets_total_size');
    }
}
