<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Space Model - Multi-tenant isolation
 * 
 * Represents a tenant space in the headless CMS system.
 * Each space contains isolated content, users, and configuration.
 */
class Space extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
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

    protected $casts = [
        'settings' => 'array',
        'environments' => 'array',
        'languages' => 'array',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Space $space) {
            if (empty($space->uuid)) {
                $space->uuid = (string) Str::uuid();
            }
            if (empty($space->slug)) {
                $space->slug = Str::slug($space->name);
            }
        });
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope query to active spaces
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to spaces by plan
     */
    public function scopeByPlan($query, string $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Check if space is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if space is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if space trial has expired
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get users belonging to this space
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_id', 'status', 'joined_at', 'last_accessed_at', 'invited_by', 'custom_permissions'])
            ->withTimestamps();
    }

    /**
     * Get stories in this space
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * Get published stories in this space
     */
    public function publishedStories(): HasMany
    {
        return $this->hasMany(Story::class)->where('is_published', true);
    }

    /**
     * Get components in this space
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Get active components in this space
     */
    public function activeComponents(): HasMany
    {
        return $this->hasMany(Component::class)->where('status', 'active');
    }

    /**
     * Get assets in this space
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get datasources in this space
     */
    public function datasources(): HasMany
    {
        return $this->hasMany(Datasource::class);
    }

    /**
     * Check if language is supported
     */
    public function supportsLanguage(string $language): bool
    {
        return in_array($language, $this->languages ?? ['en']);
    }

    /**
     * Get space settings value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set space settings value
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    /**
     * Get environment-specific configuration
     */
    public function getEnvironmentConfig(string $environment, string $key = null, mixed $default = null): mixed
    {
        $config = data_get($this->environments, $environment, []);
        
        if ($key === null) {
            return $config;
        }
        
        return data_get($config, $key, $default);
    }

    /**
     * Check if space has reached story limit
     */
    public function hasReachedStoryLimit(): bool
    {
        if ($this->story_limit === null) {
            return false;
        }

        return $this->stories()->count() >= $this->story_limit;
    }

    /**
     * Check if space has reached asset limit
     */
    public function hasReachedAssetLimit(): bool
    {
        if ($this->asset_limit === null) {
            return false;
        }

        $totalSize = $this->assets()->sum('file_size');
        return $totalSize >= ($this->asset_limit * 1024 * 1024); // Convert MB to bytes
    }
}
