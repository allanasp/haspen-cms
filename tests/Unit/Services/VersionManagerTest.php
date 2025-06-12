<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Space;
use App\Models\Story;
use App\Models\StoryVersion;
use App\Models\User;
use App\Services\VersionManager;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * @group version-manager
 * @group story-management
 */
class VersionManagerTest extends TestCase
{
    use RefreshDatabase;

    private VersionManager $versionManager;
    private Story $story;
    private User $user;
    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->versionManager = app(VersionManager::class);
        $this->space = Space::factory()->create();
        $this->user = User::factory()->create();
        $this->story = Story::factory()->for($this->space)->create([
            'name' => 'Test Story',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Original Title',
                        'subtitle' => 'Original Subtitle'
                    ]
                ]
            ],
            'meta_title' => 'Original Meta Title',
            'meta_description' => 'Original meta description',
            'meta_data' => [
                'keywords' => ['original', 'test'],
                'author' => 'Original Author'
            ]
        ]);
    }

    public function test_create_version_creates_snapshot(): void
    {
        $reason = 'Initial version creation';
        
        $version = $this->versionManager->createVersion($this->story, $this->user, $reason);
        
        $this->assertInstanceOf(StoryVersion::class, $version);
        $this->assertEquals($this->story->id, $version->story_id);
        $this->assertEquals($this->user->id, $version->created_by);
        $this->assertEquals($reason, $version->reason);
        $this->assertEquals(1, $version->version_number);
        
        // Check content snapshot
        $this->assertNotNull($version->content_snapshot);
        $this->assertEquals($this->story->content, $version->content_snapshot);
        
        // Check meta snapshot
        $this->assertNotNull($version->meta_snapshot);
        $this->assertEquals($this->story->meta_title, $version->meta_snapshot['meta_title']);
        $this->assertEquals($this->story->meta_description, $version->meta_snapshot['meta_description']);
        $this->assertEquals($this->story->meta_data, $version->meta_snapshot['meta_data']);
    }

    public function test_create_version_increments_version_numbers(): void
    {
        // Create first version
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'First version');
        $this->assertEquals(1, $version1->version_number);
        
        // Update story content
        $this->story->update([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Updated Title',
                        'subtitle' => 'Updated Subtitle'
                    ]
                ]
            ]
        ]);
        
        // Create second version
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Second version');
        $this->assertEquals(2, $version2->version_number);
        
        // Create third version
        $version3 = $this->versionManager->createVersion($this->story, $this->user, 'Third version');
        $this->assertEquals(3, $version3->version_number);
    }

    public function test_create_version_handles_default_reason(): void
    {
        $version = $this->versionManager->createVersion($this->story, $this->user);
        
        $this->assertNotNull($version->reason);
        $this->assertStringContainsString('Content updated', $version->reason);
    }

    public function test_compare_versions_shows_differences(): void
    {
        // Create initial version
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'Initial');
        
        // Update story
        $this->story->update([
            'name' => 'Updated Story Name',
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Updated Title',
                        'subtitle' => 'Updated Subtitle'
                    ],
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'New text block content'
                    ]
                ]
            ],
            'meta_title' => 'Updated Meta Title'
        ]);
        
        // Create second version
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Updated');
        
        $comparison = $this->versionManager->compareVersions($this->story, $version1->id, $version2->id);
        
        $this->assertArrayHasKey('version1', $comparison);
        $this->assertArrayHasKey('version2', $comparison);
        $this->assertArrayHasKey('changes', $comparison);
        
        // Check version info
        $this->assertEquals($version1->id, $comparison['version1']['id']);
        $this->assertEquals($version2->id, $comparison['version2']['id']);
        
        // Check changes detection
        $changes = $comparison['changes'];
        $this->assertArrayHasKey('content', $changes);
        $this->assertArrayHasKey('meta', $changes);
        
        // Content should show differences
        $this->assertNotEmpty($changes['content']);
        
        // Meta should show differences
        $this->assertNotEmpty($changes['meta']);
        $this->assertArrayHasKey('meta_title', $changes['meta']);
    }

    public function test_compare_versions_handles_identical_versions(): void
    {
        // Create two versions with same content
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'First');
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Second');
        
        $comparison = $this->versionManager->compareVersions($this->story, $version1->id, $version2->id);
        
        $this->assertArrayHasKey('changes', $comparison);
        $changes = $comparison['changes'];
        
        // Should show no changes or minimal changes
        $this->assertTrue(
            empty($changes['content']) || count($changes['content']) === 0,
            'Identical content should show no content changes'
        );
    }

    public function test_restore_from_version_restores_content(): void
    {
        // Create initial version
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'Initial');
        $originalContent = $this->story->content;
        $originalMetaTitle = $this->story->meta_title;
        
        // Update story
        $this->story->update([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Changed Title',
                        'subtitle' => 'Changed Subtitle'
                    ]
                ]
            ],
            'meta_title' => 'Changed Meta Title'
        ]);
        
        // Create version with changed content
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Changed');
        
        // Restore from first version
        $result = $this->versionManager->restoreFromVersion(
            $this->story, 
            $version1->id, 
            $this->user, 
            'Restored from version 1'
        );
        
        $this->assertTrue($result, 'Restore should succeed');
        
        // Refresh story
        $this->story->refresh();
        
        // Content should be restored
        $this->assertEquals($originalContent, $this->story->content);
        $this->assertEquals($originalMetaTitle, $this->story->meta_title);
        
        // Should create a new version for the restore
        $latestVersion = $this->story->versions()->latest()->first();
        $this->assertStringContainsString('Restored from version 1', $latestVersion->reason);
    }

    public function test_restore_from_version_with_invalid_version_fails(): void
    {
        $result = $this->versionManager->restoreFromVersion(
            $this->story, 
            99999, // Non-existent version ID
            $this->user, 
            'Invalid restore'
        );
        
        $this->assertFalse($result, 'Restore should fail for invalid version');
    }

    public function test_restore_from_version_with_wrong_story_fails(): void
    {
        // Create version for original story
        $version = $this->versionManager->createVersion($this->story, $this->user, 'Original');
        
        // Create different story
        $otherStory = Story::factory()->for($this->space)->create();
        
        // Try to restore other story from original story's version
        $result = $this->versionManager->restoreFromVersion(
            $otherStory, 
            $version->id, 
            $this->user, 
            'Wrong story restore'
        );
        
        $this->assertFalse($result, 'Restore should fail for wrong story');
    }

    public function test_get_version_stats_returns_analytics(): void
    {
        // Create multiple versions
        $this->versionManager->createVersion($this->story, $this->user, 'Version 1');
        
        $this->story->update(['name' => 'Updated Name 1']);
        $this->versionManager->createVersion($this->story, $this->user, 'Version 2');
        
        $this->story->update(['name' => 'Updated Name 2']);
        $this->versionManager->createVersion($this->story, $this->user, 'Version 3');
        
        $stats = $this->versionManager->getVersionStats($this->story);
        
        $this->assertArrayHasKey('total_versions', $stats);
        $this->assertArrayHasKey('avg_versions_per_month', $stats);
        $this->assertArrayHasKey('most_active_user', $stats);
        $this->assertArrayHasKey('latest_version', $stats);
        $this->assertArrayHasKey('first_version', $stats);
        
        $this->assertEquals(3, $stats['total_versions']);
        $this->assertIsFloat($stats['avg_versions_per_month']);
        $this->assertEquals($this->user->id, $stats['most_active_user']['id']);
        $this->assertEquals($this->user->name, $stats['most_active_user']['name']);
    }

    public function test_version_creation_preserves_all_metadata(): void
    {
        $this->story->update([
            'meta_data' => [
                'seo_keywords' => ['test', 'version', 'management'],
                'social_image' => 'social.jpg',
                'custom_fields' => [
                    'field1' => 'value1',
                    'field2' => 'value2'
                ],
                'settings' => [
                    'comments_enabled' => true,
                    'sharing_enabled' => false
                ]
            ]
        ]);
        
        $version = $this->versionManager->createVersion($this->story, $this->user, 'Complex metadata');
        
        $metaSnapshot = $version->meta_snapshot;
        
        $this->assertArrayHasKey('meta_data', $metaSnapshot);
        $this->assertEquals($this->story->meta_data, $metaSnapshot['meta_data']);
        
        // Check nested structures are preserved
        $this->assertArrayHasKey('custom_fields', $metaSnapshot['meta_data']);
        $this->assertEquals('value1', $metaSnapshot['meta_data']['custom_fields']['field1']);
        $this->assertTrue($metaSnapshot['meta_data']['settings']['comments_enabled']);
    }

    public function test_version_content_snapshot_preserves_structure(): void
    {
        $complexContent = [
            'body' => [
                [
                    '_uid' => Str::uuid(),
                    'component' => 'hero',
                    'title' => 'Main Hero',
                    'nested_components' => [
                        [
                            '_uid' => Str::uuid(),
                            'component' => 'button',
                            'text' => 'Click Me',
                            'style' => 'primary'
                        ]
                    ]
                ],
                [
                    '_uid' => Str::uuid(),
                    'component' => 'grid',
                    'columns' => [
                        [
                            '_uid' => Str::uuid(),
                            'component' => 'card',
                            'title' => 'Card 1',
                            'content' => 'Card content 1'
                        ],
                        [
                            '_uid' => Str::uuid(),
                            'component' => 'card',
                            'title' => 'Card 2',
                            'content' => 'Card content 2'
                        ]
                    ]
                ]
            ]
        ];
        
        $this->story->update(['content' => $complexContent]);
        
        $version = $this->versionManager->createVersion($this->story, $this->user, 'Complex content');
        
        $this->assertEquals($complexContent, $version->content_snapshot);
        
        // Verify nested structure preservation
        $heroComponent = $version->content_snapshot['body'][0];
        $this->assertArrayHasKey('nested_components', $heroComponent);
        $this->assertCount(1, $heroComponent['nested_components']);
        
        $gridComponent = $version->content_snapshot['body'][1];
        $this->assertArrayHasKey('columns', $gridComponent);
        $this->assertCount(2, $gridComponent['columns']);
    }

    public function test_compare_versions_detects_content_additions(): void
    {
        // Create initial version with one component
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'Initial');
        
        // Add new component
        $this->story->update([
            'content' => [
                'body' => [
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'hero',
                        'title' => 'Original Title',
                        'subtitle' => 'Original Subtitle'
                    ],
                    [
                        '_uid' => Str::uuid(),
                        'component' => 'text_block',
                        'content' => 'New content block'
                    ]
                ]
            ]
        ]);
        
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Added component');
        
        $comparison = $this->versionManager->compareVersions($this->story, $version1->id, $version2->id);
        
        $this->assertArrayHasKey('changes', $comparison);
        $this->assertArrayHasKey('content', $comparison['changes']);
        
        // Should detect the addition
        $contentChanges = $comparison['changes']['content'];
        $this->assertNotEmpty($contentChanges);
    }

    public function test_version_statistics_with_multiple_users(): void
    {
        $user2 = User::factory()->create();
        
        // User 1 creates versions
        $this->versionManager->createVersion($this->story, $this->user, 'User 1 - Version 1');
        $this->versionManager->createVersion($this->story, $this->user, 'User 1 - Version 2');
        
        // User 2 creates version
        $this->versionManager->createVersion($this->story, $user2, 'User 2 - Version 1');
        
        $stats = $this->versionManager->getVersionStats($this->story);
        
        $this->assertEquals(3, $stats['total_versions']);
        
        // Most active user should be user 1 (2 versions vs 1)
        $this->assertEquals($this->user->id, $stats['most_active_user']['id']);
    }

    public function test_restore_creates_restoration_version(): void
    {
        // Create initial version
        $version1 = $this->versionManager->createVersion($this->story, $this->user, 'Initial');
        
        // Update story
        $this->story->update(['name' => 'Changed Name']);
        $version2 = $this->versionManager->createVersion($this->story, $this->user, 'Changed');
        
        // Count versions before restore
        $versionCountBefore = $this->story->versions()->count();
        
        // Restore
        $this->versionManager->restoreFromVersion(
            $this->story, 
            $version1->id, 
            $this->user, 
            'Restored to initial state'
        );
        
        // Should have one more version
        $versionCountAfter = $this->story->versions()->count();
        $this->assertEquals($versionCountBefore + 1, $versionCountAfter);
        
        // Latest version should be the restoration
        $latestVersion = $this->story->versions()->latest()->first();
        $this->assertStringContainsString('Restored to initial state', $latestVersion->reason);
    }
}