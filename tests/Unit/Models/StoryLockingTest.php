<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Story;
use App\Models\Space;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @group story-locking
 * @group story-management
 */
class StoryLockingTest extends TestCase
{
    use RefreshDatabase;

    private Story $story;
    private User $user1;
    private User $user2;
    private Space $space;
    private string $sessionId1;
    private string $sessionId2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->space = Space::factory()->create();
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->story = Story::factory()->for($this->space)->create();
        
        $this->sessionId1 = Str::uuid()->toString();
        $this->sessionId2 = Str::uuid()->toString();
    }

    public function test_story_can_be_locked_by_user(): void
    {
        $duration = 30; // minutes
        
        $result = $this->story->lock($this->user1, $this->sessionId1, $duration);
        
        $this->assertTrue($result, 'Story should be successfully locked');
        $this->assertTrue($this->story->isLocked(), 'Story should be marked as locked');
        $this->assertEquals($this->user1->id, $this->story->locked_by);
        $this->assertEquals($this->sessionId1, $this->story->lock_session_id);
        $this->assertNotNull($this->story->locked_at);
        $this->assertNotNull($this->story->lock_expires_at);
        
        // Check expiration time is approximately correct (within 1 minute tolerance)
        $expectedExpiration = now()->addMinutes($duration);
        $this->assertTrue(
            abs($this->story->lock_expires_at->diffInSeconds($expectedExpiration)) < 60,
            'Lock expiration should be set correctly'
        );
    }

    public function test_locked_story_prevents_lock_by_other_user(): void
    {
        // User 1 locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // User 2 tries to lock the same story
        $result = $this->story->lock($this->user2, $this->sessionId2, 30);
        
        $this->assertFalse($result, 'Second user should not be able to lock already locked story');
        $this->assertEquals($this->user1->id, $this->story->locked_by, 'Lock should remain with first user');
        $this->assertEquals($this->sessionId1, $this->story->lock_session_id);
    }

    public function test_same_user_can_extend_their_lock(): void
    {
        // User locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $originalExpiration = $this->story->lock_expires_at;
        
        // Same user extends the lock
        $result = $this->story->extendLock($this->user1, 15);
        
        $this->assertTrue($result, 'User should be able to extend their own lock');
        $this->assertTrue($this->story->lock_expires_at->greaterThan($originalExpiration), 'Lock expiration should be extended');
        
        // Check extension time is approximately correct
        $expectedNewExpiration = $originalExpiration->addMinutes(15);
        $this->assertTrue(
            abs($this->story->lock_expires_at->diffInSeconds($expectedNewExpiration)) < 60,
            'Lock extension should be set correctly'
        );
    }

    public function test_different_user_cannot_extend_lock(): void
    {
        // User 1 locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $originalExpiration = $this->story->lock_expires_at;
        
        // User 2 tries to extend the lock
        $result = $this->story->extendLock($this->user2, 15);
        
        $this->assertFalse($result, 'Different user should not be able to extend lock');
        $this->assertEquals($originalExpiration->timestamp, $this->story->lock_expires_at->timestamp, 'Lock expiration should not change');
    }

    public function test_story_can_be_unlocked_by_lock_owner(): void
    {
        // Lock the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $this->assertTrue($this->story->isLocked());
        
        // Unlock by the same user
        $result = $this->story->unlock($this->user1, $this->sessionId1);
        
        $this->assertTrue($result, 'Lock owner should be able to unlock');
        $this->assertFalse($this->story->isLocked(), 'Story should no longer be locked');
        $this->assertNull($this->story->locked_by);
        $this->assertNull($this->story->locked_at);
        $this->assertNull($this->story->lock_expires_at);
        $this->assertNull($this->story->lock_session_id);
    }

    public function test_story_cannot_be_unlocked_by_different_user(): void
    {
        // User 1 locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // User 2 tries to unlock
        $result = $this->story->unlock($this->user2, $this->sessionId2);
        
        $this->assertFalse($result, 'Different user should not be able to unlock');
        $this->assertTrue($this->story->isLocked(), 'Story should remain locked');
        $this->assertEquals($this->user1->id, $this->story->locked_by);
    }

    public function test_story_cannot_be_unlocked_with_wrong_session(): void
    {
        // Lock the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // Try to unlock with same user but different session
        $result = $this->story->unlock($this->user1, $this->sessionId2);
        
        $this->assertFalse($result, 'Wrong session should not be able to unlock');
        $this->assertTrue($this->story->isLocked(), 'Story should remain locked');
    }

    public function test_expired_lock_is_automatically_detected(): void
    {
        // Lock the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // Manually set expiration to past
        $this->story->update(['lock_expires_at' => now()->subMinutes(5)]);
        
        $this->assertFalse($this->story->isLocked(), 'Expired lock should be considered unlocked');
    }

    public function test_expired_lock_can_be_taken_by_another_user(): void
    {
        // User 1 locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // Manually expire the lock
        $this->story->update(['lock_expires_at' => now()->subMinutes(5)]);
        
        // User 2 should be able to lock it now
        $result = $this->story->lock($this->user2, $this->sessionId2, 30);
        
        $this->assertTrue($result, 'New user should be able to lock expired lock');
        $this->assertEquals($this->user2->id, $this->story->locked_by);
        $this->assertEquals($this->sessionId2, $this->story->lock_session_id);
    }

    public function test_is_locked_by_other_method(): void
    {
        // Story is not locked
        $this->assertFalse($this->story->isLockedByOther($this->user1), 'Unlocked story should not be locked by other');
        
        // User 1 locks the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // Check from different users' perspectives
        $this->assertFalse($this->story->isLockedByOther($this->user1), 'Story should not be locked by other for the lock owner');
        $this->assertTrue($this->story->isLockedByOther($this->user2), 'Story should be locked by other for different user');
        
        // Test with expired lock
        $this->story->update(['lock_expires_at' => now()->subMinutes(5)]);
        $this->assertFalse($this->story->isLockedByOther($this->user2), 'Expired lock should not be locked by other');
    }

    public function test_get_lock_info_returns_correct_data(): void
    {
        // Test unlocked story
        $lockInfo = $this->story->getLockInfo();
        $this->assertNull($lockInfo, 'Unlocked story should return null lock info');
        
        // Lock the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        $lockInfo = $this->story->getLockInfo();
        
        $this->assertIsArray($lockInfo, 'Lock info should be an array');
        $this->assertEquals($this->user1->id, $lockInfo['locked_by']);
        $this->assertEquals($this->sessionId1, $lockInfo['session_id']);
        $this->assertArrayHasKey('locker', $lockInfo);
        $this->assertEquals($this->user1->name, $lockInfo['locker']['name']);
        $this->assertEquals($this->user1->email, $lockInfo['locker']['email']);
        $this->assertArrayHasKey('time_remaining', $lockInfo);
        $this->assertIsInt($lockInfo['time_remaining']);
        $this->assertGreaterThan(0, $lockInfo['time_remaining']);
        $this->assertLessThanOrEqual(30, $lockInfo['time_remaining']);
    }

    public function test_get_lock_info_with_expired_lock(): void
    {
        // Lock and then expire
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $this->story->update(['lock_expires_at' => now()->subMinutes(5)]);
        
        $lockInfo = $this->story->getLockInfo();
        $this->assertNull($lockInfo, 'Expired lock should return null lock info');
    }

    public function test_cleanup_expired_locks_static_method(): void
    {
        // Create multiple stories with expired locks
        $story2 = Story::factory()->for($this->space)->create();
        $story3 = Story::factory()->for($this->space)->create();
        
        // Lock all stories
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $story2->lock($this->user1, $this->sessionId1, 30);
        $story3->lock($this->user2, $this->sessionId2, 30);
        
        // Expire some locks
        $this->story->update(['lock_expires_at' => now()->subMinutes(5)]);
        $story2->update(['lock_expires_at' => now()->subMinutes(10)]);
        // story3 remains valid
        
        $cleanupCount = Story::cleanupAllExpiredLocks();
        
        $this->assertEquals(2, $cleanupCount, 'Should cleanup 2 expired locks');
        
        // Refresh models and check
        $this->story->refresh();
        $story2->refresh();
        $story3->refresh();
        
        $this->assertFalse($this->story->isLocked(), 'Expired lock should be cleaned up');
        $this->assertFalse($story2->isLocked(), 'Expired lock should be cleaned up');
        $this->assertTrue($story3->isLocked(), 'Valid lock should remain');
    }

    public function test_lock_with_default_duration(): void
    {
        // Test with default duration (should be 30 minutes)
        $result = $this->story->lock($this->user1, $this->sessionId1);
        
        $this->assertTrue($result);
        
        $expectedExpiration = now()->addMinutes(30);
        $this->assertTrue(
            abs($this->story->lock_expires_at->diffInSeconds($expectedExpiration)) < 60,
            'Default lock duration should be 30 minutes'
        );
    }

    public function test_lock_duration_boundaries(): void
    {
        // Test minimum duration
        $result = $this->story->lock($this->user1, $this->sessionId1, 1);
        $this->assertTrue($result);
        
        $this->story->unlock($this->user1, $this->sessionId1);
        
        // Test maximum reasonable duration
        $result = $this->story->lock($this->user1, $this->sessionId1, 480); // 8 hours
        $this->assertTrue($result);
        
        $expectedExpiration = now()->addMinutes(480);
        $this->assertTrue(
            abs($this->story->lock_expires_at->diffInSeconds($expectedExpiration)) < 60,
            'Long lock duration should work'
        );
    }

    public function test_concurrent_lock_attempts(): void
    {
        // Simulate race condition - both users try to lock at the same time
        $this->story->lock($this->user1, $this->sessionId1, 30);
        
        // Immediate second attempt should fail
        $result = $this->story->lock($this->user2, $this->sessionId2, 30);
        $this->assertFalse($result);
        
        // Only first user should have the lock
        $this->assertEquals($this->user1->id, $this->story->locked_by);
        $this->assertEquals($this->sessionId1, $this->story->lock_session_id);
    }

    public function test_lock_refresh_updates_expiration(): void
    {
        // Lock the story
        $this->story->lock($this->user1, $this->sessionId1, 30);
        $originalExpiration = $this->story->lock_expires_at;
        
        // Wait a moment and refresh lock
        Carbon::setTestNow(now()->addMinutes(5));
        
        $result = $this->story->lock($this->user1, $this->sessionId1, 30);
        $this->assertTrue($result, 'User should be able to refresh their own lock');
        
        $this->assertTrue(
            $this->story->lock_expires_at->greaterThan($originalExpiration),
            'Lock refresh should extend expiration'
        );
        
        Carbon::setTestNow(); // Reset time
    }
}