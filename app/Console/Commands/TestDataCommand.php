<?php

namespace App\Console\Commands;

use App\Models\Space;
use App\Models\User;
use App\Models\Component;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestDataCommand extends Command
{
    protected $signature = 'test:create-data';
    protected $description = 'Create test data for Story Management System';

    public function handle()
    {
        $this->info('Creating test data...');

        // Create test space
        $space = Space::create([
            'name' => 'Test Space',
            'slug' => 'test-space',
            'uuid' => Str::uuid(),
            'settings' => [],
            'environments' => [],
            'languages' => ['en'],
        ]);

        // Create test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create test component
        $component = Component::create([
            'space_id' => $space->id,
            'name' => 'Test Component',
            'technical_name' => 'test_component',
            'uuid' => Str::uuid(),
            'schema' => [
                'title' => ['type' => 'text', 'required' => true],
                'content' => ['type' => 'textarea', 'required' => false],
                'featured' => ['type' => 'boolean', 'required' => false]
            ],
            'status' => 'active',
            'is_root' => true,
            'created_by' => $user->id,
        ]);

        // Create test story
        $story = Story::create([
            'space_id' => $space->id,
            'name' => 'Test Story',
            'slug' => 'test-story',
            'uuid' => Str::uuid(),
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'test_component',
                        'title' => 'Hello World',
                        'content' => 'This is test content',
                        'featured' => true
                    ]
                ]
            ],
            'language' => 'en',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->table(['Resource', 'UUID/ID'], [
            ['Space', $space->uuid],
            ['User', $user->id],
            ['Component', $component->uuid],
            ['Story', $story->uuid],
        ]);

        $this->info('Test data created successfully!');
        return 0;
    }
}