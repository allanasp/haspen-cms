<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Component;
use App\Models\Datasource;
use App\Models\DatasourceEntry;
use App\Models\Role;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comprehensive seeder for initial headless CMS data.
 * Creates a complete demo environment with realistic content.
 */
final class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@headlesscms.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'timezone' => 'UTC',
            'language' => 'en',
            'status' => User::STATUS_ACTIVE,
            'preferences' => [
                'theme' => 'dark',
                'notifications' => [
                    'email' => true,
                    'push' => true,
                ],
                'editor' => [
                    'auto_save' => true,
                    'spell_check' => true,
                ],
            ],
            'metadata' => [
                'signup_source' => 'manual',
                'onboarding_completed' => true,
            ],
        ]);

        // Create demo space
        $demoSpace = Space::create([
            'name' => 'Demo Website',
            'slug' => 'demo-website',
            'description' => 'A demonstration website showcasing the headless CMS capabilities',
            'domain' => 'demo.headlesscms.com',
            'plan' => 'professional',
            'status' => Space::STATUS_ACTIVE,
            'settings' => [
                'default_language' => 'en',
                'available_languages' => ['en', 'es', 'fr', 'de'],
                'timezone' => 'UTC',
                'content_versioning' => true,
                'auto_publish' => false,
                'seo' => [
                    'meta_title_template' => '{title} | {site_name}',
                    'meta_description_template' => '{excerpt}',
                    'og_image_default' => '/assets/default-og-image.jpg',
                ],
                'api' => [
                    'rate_limit' => 1000,
                    'cache_ttl' => 3600,
                    'allowed_origins' => ['*'],
                ],
                'assets' => [
                    'max_file_size' => 10485760, // 10MB
                    'allowed_types' => ['image/*', 'video/*', 'application/pdf'],
                    'auto_optimize' => true,
                    'generate_thumbnails' => true,
                ],
            ],
            'limits' => [
                'stories' => 10000,
                'assets' => 50000,
                'api_calls_per_month' => 1000000,
                'bandwidth_gb_per_month' => 100,
                'users' => 50,
            ],
            'metadata' => [
                'created_via' => 'seeder',
                'demo_data' => true,
            ],
        ]);

        // Create roles for the demo space
        $adminRole = Role::create([
            'space_id' => $demoSpace->id,
            'name' => 'Administrator',
            'slug' => 'administrator',
            'description' => 'Full access to all features and content',
            'permissions' => [
                'stories' => ['create', 'read', 'update', 'delete', 'publish'],
                'components' => ['create', 'read', 'update', 'delete'],
                'assets' => ['create', 'read', 'update', 'delete'],
                'datasources' => ['create', 'read', 'update', 'delete'],
                'users' => ['create', 'read', 'update', 'delete'],
                'settings' => ['read', 'update'],
            ],
            'is_system_role' => true,
        ]);

        $editorRole = Role::create([
            'space_id' => $demoSpace->id,
            'name' => 'Editor',
            'slug' => 'editor',
            'description' => 'Can create and edit content but not manage system settings',
            'permissions' => [
                'stories' => ['create', 'read', 'update', 'publish'],
                'components' => ['read'],
                'assets' => ['create', 'read', 'update'],
                'datasources' => ['read'],
            ],
            'is_system_role' => true,
        ]);

        // Attach admin user to demo space
        $demoSpace->users()->attach($adminUser->id, [
            'role_id' => $adminRole->id,
            'custom_permissions' => null,
            'last_accessed_at' => now(),
        ]);

        // Create demo components
        $heroComponent = Component::create([
            'space_id' => $demoSpace->id,
            'name' => 'Hero Section',
            'slug' => 'hero-section',
            'description' => 'A large hero section with background image, title, and call-to-action',
            'category' => 'layout',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'title' => 'Hero Title',
                        'description' => 'Main headline for the hero section',
                        'maxLength' => 100,
                    ],
                    'subtitle' => [
                        'type' => 'string',
                        'title' => 'Subtitle',
                        'description' => 'Supporting text below the main title',
                        'maxLength' => 200,
                    ],
                    'background_image' => [
                        'type' => 'string',
                        'title' => 'Background Image',
                        'format' => 'asset',
                        'description' => 'Background image for the hero section',
                    ],
                    'cta_text' => [
                        'type' => 'string',
                        'title' => 'Call to Action Text',
                        'description' => 'Text for the primary button',
                        'maxLength' => 50,
                    ],
                    'cta_link' => [
                        'type' => 'string',
                        'title' => 'Call to Action Link',
                        'format' => 'uri',
                        'description' => 'URL for the primary button',
                    ],
                ],
                'required' => ['title'],
            ],
            'config' => [
                'preview_image' => '/assets/components/hero-section-preview.jpg',
                'tags' => ['hero', 'banner', 'landing'],
                'responsive' => true,
                'accessibility' => [
                    'requires_alt_text' => true,
                    'heading_level' => 1,
                ],
            ],
            'status' => Component::STATUS_PUBLISHED,
            'created_by' => $adminUser->id,
        ]);

        $textComponent = Component::create([
            'space_id' => $demoSpace->id,
            'name' => 'Rich Text Block',
            'slug' => 'rich-text-block',
            'description' => 'A flexible rich text editor for content',
            'category' => 'content',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type' => 'string',
                        'title' => 'Content',
                        'format' => 'richtext',
                        'description' => 'Rich text content with formatting',
                    ],
                    'text_size' => [
                        'type' => 'string',
                        'title' => 'Text Size',
                        'enum' => ['small', 'medium', 'large'],
                        'default' => 'medium',
                        'description' => 'Size of the text content',
                    ],
                    'text_align' => [
                        'type' => 'string',
                        'title' => 'Text Alignment',
                        'enum' => ['left', 'center', 'right', 'justify'],
                        'default' => 'left',
                        'description' => 'Alignment of the text content',
                    ],
                ],
                'required' => ['content'],
            ],
            'config' => [
                'preview_image' => '/assets/components/rich-text-preview.jpg',
                'tags' => ['text', 'content', 'editor'],
                'responsive' => true,
            ],
            'status' => Component::STATUS_PUBLISHED,
            'created_by' => $adminUser->id,
        ]);

        // Create demo datasource
        $blogDataSource = Datasource::create([
            'space_id' => $demoSpace->id,
            'name' => 'Blog Posts API',
            'slug' => 'blog-posts-api',
            'description' => 'External API for fetching blog post data',
            'type' => Datasource::TYPE_API,
            'config' => [
                'url' => 'https://jsonplaceholder.typicode.com/posts',
                'method' => 'GET',
                'timeout' => 30,
                'data_path' => null,
            ],
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                        'userId' => ['type' => 'integer'],
                    ],
                ],
            ],
            'mapping' => [
                'id' => 'id',
                'title' => 'title',
                'content' => 'body',
                'author_id' => 'userId',
            ],
            'cache_duration' => 3600,
            'auto_sync' => true,
            'sync_frequency' => 'hourly',
            'max_entries' => 100,
            'status' => Datasource::STATUS_ACTIVE,
            'created_by' => $adminUser->id,
        ]);

        // Create some demo datasource entries
        for ($i = 1; $i <= 5; $i++) {
            DatasourceEntry::create([
                'datasource_id' => $blogDataSource->id,
                'external_id' => (string) $i,
                'data' => [
                    'id' => $i,
                    'title' => "Sample Blog Post {$i}",
                    'content' => "This is the content for sample blog post number {$i}. It contains interesting information about various topics.",
                    'author_id' => 1,
                    'published_at' => now()->subDays(rand(1, 30)),
                ],
                'position' => $i,
                'status' => DatasourceEntry::STATUS_PUBLISHED,
                'metadata' => [
                    'synced_at' => now(),
                    'source' => 'demo',
                ],
            ]);
        }

        // Create demo stories
        $homePage = Story::create([
            'space_id' => $demoSpace->id,
            'name' => 'Home Page',
            'slug' => 'home',
            'full_slug' => 'home',
            'content' => [
                [
                    'component' => 'hero-section',
                    'content' => [
                        'title' => 'Welcome to Our Demo Site',
                        'subtitle' => 'Experience the power of headless CMS',
                        'cta_text' => 'Get Started',
                        'cta_link' => '/about',
                    ],
                ],
                [
                    'component' => 'rich-text-block',
                    'content' => [
                        'content' => '<h2>About Our Platform</h2><p>This demo showcases the capabilities of our headless CMS. You can create rich, dynamic content using our flexible component system.</p>',
                        'text_size' => 'medium',
                        'text_align' => 'left',
                    ],
                ],
            ],
            'status' => Story::STATUS_PUBLISHED,
            'language' => 'en',
            'position' => 0,
            'meta_title' => 'Home - Demo Website',
            'meta_description' => 'Welcome to our demo website showcasing headless CMS capabilities',
            'published_at' => now(),
            'created_by' => $adminUser->id,
        ]);

        $aboutPage = Story::create([
            'space_id' => $demoSpace->id,
            'name' => 'About Us',
            'slug' => 'about',
            'full_slug' => 'about',
            'content' => [
                [
                    'component' => 'rich-text-block',
                    'content' => [
                        'content' => '<h1>About Us</h1><p>We are a team passionate about creating powerful, flexible content management solutions. Our headless CMS empowers developers and content creators to build amazing digital experiences.</p><h2>Our Mission</h2><p>To provide the most developer-friendly and content-creator-friendly headless CMS platform.</p>',
                        'text_size' => 'medium',
                        'text_align' => 'left',
                    ],
                ],
            ],
            'status' => Story::STATUS_PUBLISHED,
            'language' => 'en',
            'position' => 1,
            'meta_title' => 'About Us - Demo Website',
            'meta_description' => 'Learn more about our team and mission',
            'published_at' => now(),
            'created_by' => $adminUser->id,
        ]);

        // Create blog posts using the datasource
        $blogIndex = Story::create([
            'space_id' => $demoSpace->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'full_slug' => 'blog',
            'content' => [
                [
                    'component' => 'rich-text-block',
                    'content' => [
                        'content' => '<h1>Our Blog</h1><p>Stay updated with the latest news and insights from our team.</p>',
                        'text_size' => 'large',
                        'text_align' => 'center',
                    ],
                ],
                [
                    'component' => 'datasource',
                    'datasource_id' => $blogDataSource->id,
                    'content' => [
                        'limit' => 10,
                        'template' => 'blog-list',
                    ],
                ],
            ],
            'status' => Story::STATUS_PUBLISHED,
            'language' => 'en',
            'position' => 2,
            'meta_title' => 'Blog - Demo Website',
            'meta_description' => 'Read our latest blog posts and insights',
            'published_at' => now(),
            'created_by' => $adminUser->id,
        ]);

        $this->command->info('Initial demo data has been created successfully!');
        $this->command->info("Demo space: {$demoSpace->name} (ID: {$demoSpace->id})");
        $this->command->info("Admin user: {$adminUser->email}");
        $this->command->info('Password: password');
    }
}