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
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string|null $description
 * @property array|null $settings
 * @property array $environments
 * @property string $default_language
 * @property array $languages
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
 */
class Space extends Model
{
    use HasFactory;
    use HasUuid;
    use Sluggable;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
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
     * @var array<string, string>
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
     * @var array<string>
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
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_DELETED = 'deleted';

    /**
     * Available plans.
     */
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Get all users associated with this space.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_user')
            ->withPivot(['role_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * Get all stories in this space.
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * Get all components in this space.
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Get all assets in this space.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get all datasources in this space.
     */
    public function datasources(): HasMany
    {
        return $this->hasMany(Datasource::class);
    }

    /**
     * Scope to active spaces only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to spaces by plan.
     */
    public function scopePlan($query, string $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Scope to spaces by domain.
     */
    public function scopeDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Check if the space is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the space is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the space is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the trial has expired.
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get space configuration for a specific environment.
     */
    public function getEnvironmentConfig(string $environment = 'production'): array
    {
        return $this->environments[$environment] ?? [];
    }

    /**
     * Check if a language is supported.
     */
    public function supportsLanguage(string $language): bool
    {
        return \in_array($language, $this->languages);
    }

    /**
     * Get the number of stories in this space.
     */
    public function getStoriesCount(): int
    {
        return $this->getCached('stories_count', function () {
            return $this->stories()->count();
        }, 3600);
    }

    /**
     * Get the number of assets in this space.
     */
    public function getAssetsCount(): int
    {
        return $this->getCached('assets_count', function () {
            return $this->assets()->count();
        }, 3600);
    }

    /**
     * Check if space has reached story limit.
     */
    public function hasReachedStoryLimit(): bool
    {
        if ($this->story_limit === null) {
            return false;
        }

        return $this->getStoriesCount() >= $this->story_limit;
    }

    /**
     * Check if space has reached asset limit.
     */
    public function hasReachedAssetLimit(): bool
    {
        if ($this->asset_limit === null) {
            return false;
        }

        $totalSize = $this->getCached('assets_total_size', function () {
            return $this->assets()->sum('file_size') / 1024 / 1024; // Convert to MB
        }, 3600);

        return $totalSize >= $this->asset_limit;
    }

    /**
     * Suspend the space.
     */
    public function suspend(): bool
    {
        return $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Reactivate the space.
     */
    public function reactivate(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'suspended_at' => null,
        ]);
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
