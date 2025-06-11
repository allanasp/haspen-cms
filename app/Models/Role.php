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
 * @property array<array-key, string> $permissions
 * @property bool $is_system_role
 * @property bool $is_default
 * @property int $priority
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class Role extends Model
{
    use HasFactory;
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
     * @var array<array-key, mixed>
     */
    protected $casts = [
        'permissions' => Json::class,
        'is_system_role' => 'boolean',
        'is_default' => 'boolean',
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
     * Cache TTL in seconds (6 hours).
     */
    protected int $cacheTtl = 21600;

    /**
     * Available permissions.
     */
    /** @var array<int, string> */
    public const array PERMISSIONS = [
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
    public const string ROLE_ADMIN = 'admin';
    public const string ROLE_EDITOR = 'editor';
    public const string ROLE_AUTHOR = 'author';
    public const string ROLE_VIEWER = 'viewer';

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return \in_array($permission, $this->permissions);
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
