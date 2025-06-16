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
    protected array $fillable = [
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
    protected array $casts = [
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
    protected array $hidden = [
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
     * Get validation rules for space creation/update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'environments' => ['required', 'array'],
            'default_language' => ['required', 'string', 'max:10'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['required', 'string', 'max:10'],
            'plan' => ['required', 'string', 'in:' . self::PLAN_FREE . ',' . self::PLAN_PRO . ',' . self::PLAN_ENTERPRISE],
            'story_limit' => ['nullable', 'integer', 'min:0'],
            'asset_limit' => ['nullable', 'integer', 'min:0'],
            'api_limit' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:' . self::STATUS_ACTIVE . ',' . self::STATUS_SUSPENDED . ',' . self::STATUS_DELETED],
            'trial_ends_at' => ['nullable', 'date', 'after:now'],
            'suspended_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get validation rules for space creation.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function createRules(): array
    {
        return array_merge(self::rules(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:spaces,slug', 'regex:/^[a-z0-9-]+$/'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:spaces,domain', 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'],
            'default_language' => ['required', 'string', 'max:10'],
            'languages' => ['required', 'array', 'min:1'],
            'plan' => ['required', 'string', 'in:' . self::PLAN_FREE . ',' . self::PLAN_PRO . ',' . self::PLAN_ENTERPRISE],
            'environments' => ['required', 'array'],
        ]);
    }

    /**
     * Get validation rules for space update.
     *
     * @param int|null $spaceId
     * @return array<string, array<int, string>|string>
     */
    public static function updateRules(?int $spaceId = null): array
    {
        $rules = self::rules();
        
        // Update unique rules to exclude current space
        if ($spaceId) {
            $rules['slug'] = ['nullable', 'string', 'max:255', "unique:spaces,slug,{$spaceId}", 'regex:/^[a-z0-9-]+$/'];
            $rules['domain'] = ['nullable', 'string', 'max:255', "unique:spaces,domain,{$spaceId}", 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'];
        } else {
            $rules['slug'] = ['nullable', 'string', 'max:255', 'unique:spaces,slug', 'regex:/^[a-z0-9-]+$/'];
            $rules['domain'] = ['nullable', 'string', 'max:255', 'unique:spaces,domain', 'regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'];
        }
        
        return $rules;
    }

    /**
     * Get validation rules for space settings update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function settingsUpdateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'default_language' => ['required', 'string', 'max:10'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * Get validation rules for plan change.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function planChangeRules(): array
    {
        return [
            'plan' => ['required', 'string', 'in:' . self::PLAN_FREE . ',' . self::PLAN_PRO . ',' . self::PLAN_ENTERPRISE],
            'story_limit' => ['nullable', 'integer', 'min:0'],
            'asset_limit' => ['nullable', 'integer', 'min:0'],
            'api_limit' => ['nullable', 'integer', 'min:0'],
        ];
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
