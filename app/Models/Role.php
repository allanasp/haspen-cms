<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Role Model.
 *
 * Represents user roles with permissions in the headless CMS.
 * Roles define what users can do within a space.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array $permissions
 * @property bool $is_system_role
 * @property bool $is_default
 * @property int $priority
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Role extends Model
{
    use HasFactory;
    use HasUuid;
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
        'description',
        'permissions',
        'is_system_role',
        'is_default',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => Json::class,
        'is_system_role' => 'boolean',
        'is_default' => 'boolean',
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
     * Cache TTL in seconds (6 hours).
     */
    protected int $cacheTtl = 21600;

    /**
     * Available permissions.
     */
    public const PERMISSIONS = [
        // Space management
        'space.view',
        'space.edit',
        'space.delete',
        'space.manage_users',

        // Story management
        'story.view',
        'story.create',
        'story.edit',
        'story.delete',
        'story.publish',
        'story.unpublish',

        // Component management
        'component.view',
        'component.create',
        'component.edit',
        'component.delete',

        // Asset management
        'asset.view',
        'asset.upload',
        'asset.edit',
        'asset.delete',

        // Datasource management
        'datasource.view',
        'datasource.create',
        'datasource.edit',
        'datasource.delete',
        'datasource.sync',

        // User management
        'user.view',
        'user.invite',
        'user.edit',
        'user.remove',
        'user.manage_roles',

        // System permissions
        'admin.access',
        'admin.settings',
        'admin.analytics',
    ];

    /**
     * System roles.
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_AUTHOR = 'author';
    public const ROLE_VIEWER = 'viewer';

    /**
     * Get all users with this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_user')
            ->withPivot(['space_id', 'custom_permissions', 'last_accessed_at'])
            ->withTimestamps();
    }

    /**
     * Scope to system roles only.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_role', true);
    }

    /**
     * Scope to custom roles only.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system_role', false);
    }

    /**
     * Scope to default roles.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope roles by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->permissions ?? []);
    }

    /**
     * Check if the role has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return ! empty(array_intersect($permissions, $this->permissions ?? []));
    }

    /**
     * Check if the role has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return empty(array_diff($permissions, $this->permissions ?? []));
    }

    /**
     * Add a permission to the role.
     */
    public function addPermission(string $permission): bool
    {
        if (! $this->hasPermission($permission)) {
            $permissions = $this->permissions ?? [];
            $permissions[] = $permission;

            return $this->update(['permissions' => $permissions]);
        }

        return true;
    }

    /**
     * Remove a permission from the role.
     */
    public function removePermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_values(array_filter($permissions, fn ($p) => $p !== $permission));

        return $this->update(['permissions' => $permissions]);
    }

    /**
     * Set multiple permissions for the role.
     */
    public function setPermissions(array $permissions): bool
    {
        // Validate permissions
        $validPermissions = array_intersect($permissions, self::PERMISSIONS);

        return $this->update(['permissions' => $validPermissions]);
    }

    /**
     * Check if the role is a system role.
     */
    public function isSystemRole(): bool
    {
        return $this->is_system_role;
    }

    /**
     * Check if the role is the default role.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Get the role's permission level (based on priority).
     */
    public function getPermissionLevel(): string
    {
        return match ($this->priority) {
            100 => 'admin',
            75 => 'editor',
            50 => 'author',
            25 => 'viewer',
            default => 'custom',
        };
    }

    /**
     * Check if this role can manage another role.
     */
    public function canManageRole(Role $role): bool
    {
        // System roles can't be managed by non-system roles
        if ($role->isSystemRole() && ! $this->isSystemRole()) {
            return false;
        }

        // Can only manage roles with lower priority
        return $this->priority > $role->priority;
    }

    /**
     * Get default permissions for a role type.
     */
    public static function getDefaultPermissions(string $roleType): array
    {
        return match ($roleType) {
            self::ROLE_ADMIN => [
                'space.view', 'space.edit', 'space.manage_users',
                'story.view', 'story.create', 'story.edit', 'story.delete', 'story.publish', 'story.unpublish',
                'component.view', 'component.create', 'component.edit', 'component.delete',
                'asset.view', 'asset.upload', 'asset.edit', 'asset.delete',
                'datasource.view', 'datasource.create', 'datasource.edit', 'datasource.delete', 'datasource.sync',
                'user.view', 'user.invite', 'user.edit', 'user.remove', 'user.manage_roles',
                'admin.access', 'admin.settings', 'admin.analytics',
            ],
            self::ROLE_EDITOR => [
                'space.view',
                'story.view', 'story.create', 'story.edit', 'story.delete', 'story.publish', 'story.unpublish',
                'component.view', 'component.create', 'component.edit', 'component.delete',
                'asset.view', 'asset.upload', 'asset.edit', 'asset.delete',
                'datasource.view', 'datasource.sync',
                'user.view',
            ],
            self::ROLE_AUTHOR => [
                'space.view',
                'story.view', 'story.create', 'story.edit',
                'component.view',
                'asset.view', 'asset.upload', 'asset.edit',
                'datasource.view',
            ],
            self::ROLE_VIEWER => [
                'space.view',
                'story.view',
                'component.view',
                'asset.view',
                'datasource.view',
            ],
            default => [],
        };
    }

    /**
     * Create a system role.
     */
    public static function createSystemRole(string $name, string $slug, array $permissions, int $priority = 50): self
    {
        return static::create([
            'name' => $name,
            'slug' => $slug,
            'permissions' => $permissions,
            'is_system_role' => true,
            'priority' => $priority,
        ]);
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('permissions');
        $this->forgetCache('users');
    }
}
