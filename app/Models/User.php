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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'space_user')
            ->withPivot(['role_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * Get all roles for this user across all spaces.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'space_user')
            ->withPivot(['space_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * Scope to active users only.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to admin users only.
     */
    public function scopeAdmins(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_admin', true);
    }

    /**
     * Scope users by status.
     */
    public function scopeStatus(\Illuminate\Database\Eloquent\Builder $query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Check if the user belongs to a specific space.
     */
    public function belongsToSpace(Space|int $space): bool
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        return $this->spaces()->where('space_id', $spaceId)->exists();
    }

    /**
     * Get the user's role in a specific space.
     */
    public function getRoleInSpace(Space|int $space): ?Role
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        $space = $this->spaces()->where('space_id', $spaceId)->first();
        
        if (!$space || !$space->pivot) {
            return null;
        }

        $roleId = $space->pivot->getAttribute('role_id');
        if (!$roleId) {
            return null;
        }

        /** @var Role|null $role */
        $role = Role::find($roleId);
        return $role;
    }

    /**
     * Get custom permissions for a specific space.
     */
    public function getCustomPermissionsInSpace(Space|int $space): array
    {
        $spaceId = $space instanceof Space ? $space->id : $space;

        $space = $this->spaces()->where('space_id', $spaceId)->first();

        if (!$space || !$space->pivot) {
            return [];
        }

        /** @var array<string, mixed> $permissions */
        $permissions = $space->pivot->getAttribute('custom_permissions') ?? [];
        return $permissions;
    }

    /**
     * Check if user has permission in a space.
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
     * @return mixed
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Set user preference.
     */
    public function setPreference(string $key, mixed $value): bool
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;

        return $this->update(['preferences' => $preferences]);
    }

    /**
     * Get user metadata by key.
     *
     * @return mixed
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set user metadata.
     */
    public function setMetadata(string $key, mixed $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;

        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Get the user's full name or email if name is not set.
     */
    public function getDisplayName(): string
    {
        return $this->name ?: $this->email;
    }

    /**
     * Get the user's avatar URL or generate a default one.
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
