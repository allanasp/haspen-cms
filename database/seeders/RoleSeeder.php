<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full administrative access to the space',
                'permissions' => [
                    'stories' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true, 'publish' => true],
                    'assets' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'components' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'datasources' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'users' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'space_settings' => ['read' => true, 'update' => true],
                ],
                'is_system_role' => true,
                'is_default' => false,
                'priority' => 100,
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can create and edit content',
                'permissions' => [
                    'stories' => [
                        'create' => true,
                        'read' => true,
                        'update' => true,
                        'delete' => false,
                        'publish' => false,
                        'restrictions' => ['own_content_only' => true]
                    ],
                    'assets' => [
                        'create' => true,
                        'read' => true,
                        'update' => true,
                        'delete' => false,
                        'restrictions' => ['max_file_size' => '10MB']
                    ],
                    'components' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'datasources' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'users' => ['create' => false, 'read' => false, 'update' => false, 'delete' => false],
                    'space_settings' => ['read' => false, 'update' => false],
                ],
                'is_system_role' => true,
                'is_default' => true,
                'priority' => 50,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to content',
                'permissions' => [
                    'stories' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false, 'publish' => false],
                    'assets' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'components' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'datasources' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'users' => ['create' => false, 'read' => false, 'update' => false, 'delete' => false],
                    'space_settings' => ['read' => false, 'update' => false],
                ],
                'is_system_role' => true,
                'is_default' => false,
                'priority' => 10,
            ],
            [
                'name' => 'Developer',
                'slug' => 'developer',
                'description' => 'Can manage components and technical aspects',
                'permissions' => [
                    'stories' => ['create' => true, 'read' => true, 'update' => true, 'delete' => false, 'publish' => true],
                    'assets' => ['create' => true, 'read' => true, 'update' => true, 'delete' => false],
                    'components' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'datasources' => ['create' => true, 'read' => true, 'update' => true, 'delete' => true],
                    'users' => ['create' => false, 'read' => true, 'update' => false, 'delete' => false],
                    'space_settings' => ['read' => true, 'update' => false],
                ],
                'is_system_role' => true,
                'is_default' => false,
                'priority' => 75,
            ],
        ];

        foreach ($roles as $roleData) {
            \App\Models\Role::create($roleData);
        }
    }
}
