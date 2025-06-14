<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model.
 *
 * Represents a user in the multi-tenant headless CMS.
 * Users can belong to multiple spaces with different roles.
 * @psalm-suppress PossiblyUnusedMethod
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $avatar_url
 * @property string|null $bio
 * @property bool $is_admin
 * @property string $timezone
 * @property string $language
 * @property string $status
 * @property array<string, mixed>|null $preferences
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    /** @use HasApiTokens<\Laravel\Sanctum\PersonalAccessToken> */
    use HasApiTokens;
    use HasUuid;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'bio',
        'timezone',
        'language',
        'preferences',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<array-key, string>
     */
    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<array-key, mixed>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'preferences' => Json::class,
        'metadata' => Json::class,
        'last_login_at' => 'datetime',
    ];

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected int $cacheTtl = 3600;

    /**
     * Available user statuses.
     */
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_INACTIVE = 'inactive';
    public const string STATUS_SUSPENDED = 'suspended';

    /**
     * Get all spaces this user belongs to.
     *
     * @return BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        /** @psalm-suppress MixedMethodCall, MixedAssignment, MixedReturnStatement */
        $relation = $this->belongsToMany(Space::class, 'space_user')
            ->withPivot(['role_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
        return $relation;
    }

    /**
     * Get all roles for this user across all spaces.
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        /** @psalm-suppress MixedMethodCall, MixedAssignment, MixedReturnStatement */
        $relation = $this->belongsToMany(Role::class, 'space_user')
            ->withPivot(['space_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
        return $relation;
    }

    /**
     * Scope to active users only.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to admin users only.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function scopeAdmins(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope users by status.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function scopeStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the user is active.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the user is an admin.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Check if the user belongs to a specific space.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function belongsToSpace(Space|int $space): bool
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        /** @psalm-suppress MixedReturnStatement, MixedMethodCall */
        $spacesQuery = $this->spaces();
        return $spacesQuery->where('space_id', $spaceId)->exists();
    }

    /**
     * Get the user's role in a specific space.
     */
    public function getRoleInSpace(Space|int $space): ?Role
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        $spacesQuery = $this->spaces();
        /** @var Space|null $space */
        $space = $spacesQuery->where('space_id', $spaceId)->first();
        
        if (!$space) {
            return null;
        }

        /** @var object $pivot */
        $pivot = $space->pivot;

        /** @psalm-suppress MixedMethodCall */
        /** @var int|null $roleId */
        $roleId = $pivot->getAttribute('role_id');
        if ($roleId === null) {
            return null;
        }

        /** @var Role|null $role */
        $role = Role::find($roleId);
        return $role;
    }

    /**
     * Get custom permissions for a specific space.
     *
     * @return array<string, mixed>
     */
    public function getCustomPermissionsInSpace(Space|int $space): array
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        $spacesQuery = $this->spaces();
        /** @var Space|null $space */
        $space = $spacesQuery->where('space_id', $spaceId)->first();

        if (!$space) {
            return [];
        }

        /** @var object $pivot */
        $pivot = $space->pivot;

        /** @psalm-suppress MixedMethodCall */
        $permissionsData = $pivot->getAttribute('custom_permissions');
        if (!is_array($permissionsData)) {
            return [];
        }
        /** @var array<string, mixed> $permissions */
        $permissions = $permissionsData;
        return $permissions;
    }

    /**
     * Check if user has permission in a space.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function hasPermissionInSpace(Space|int $space, string $permission): bool
    {
        $role = $this->getRoleInSpace($space);
        $customPermissions = $this->getCustomPermissionsInSpace($space);

        // Check custom permissions first
        if (isset($customPermissions[$permission])) {
            return (bool) $customPermissions[$permission];
        }

        // Check role permissions
        if ($role) {
            return $role->hasPermission($permission);
        }

        return false;
    }

    /**
     * Update last login information.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function updateLastLogin(?string $ip = null): bool
    {
        return $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Get user preference by key.
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @return mixed
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->preferences;
        if (!is_array($preferences)) {
            return $default;
        }
        return $preferences[$key] ?? $default;
    }

    /**
     * Set user preference.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setPreference(string $key, mixed $value): bool
    {
        $preferences = $this->preferences;
        if (!is_array($preferences)) {
            $preferences = [];
        }
        /** @psalm-suppress MixedAssignment */
        $preferences[$key] = $value;

        return $this->update(['preferences' => $preferences]);
    }

    /**
     * Get user metadata by key.
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @return mixed
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        $metadata = $this->metadata;
        if (!is_array($metadata)) {
            return $default;
        }
        return $metadata[$key] ?? $default;
    }

    /**
     * Set user metadata.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setMetadata(string $key, mixed $value): bool
    {
        $metadata = $this->metadata;
        if (!is_array($metadata)) {
            $metadata = [];
        }
        /** @psalm-suppress MixedAssignment */
        $metadata[$key] = $value;

        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Get the user's full name or email if name is not set.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getDisplayName(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Get the user's avatar URL or generate a default one.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getAvatarUrl(): string
    {
        return $this->avatar_url !== null && $this->avatar_url !== '' ? $this->avatar_url : $this->generateDefaultAvatar();
    }

    /**
     * Generate a default avatar URL using Gravatar.
     */
    protected function generateDefaultAvatar(): string
    {
        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        // Clear any user-specific cached data
        $this->forgetCache('spaces');
        $this->forgetCache('permissions');
    }
}
