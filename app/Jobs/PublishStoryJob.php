<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Story;
use App\Services\VersionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to handle scheduled story publishing.
 */
class PublishStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Story $story
    ) {}

    /**
     * Execute the job.
     */
    public function handle(VersionManager $versionManager): void
    {
        try {
            // Reload the story to get the latest state
            $this->story->refresh();

            // Only publish if the story is still scheduled
            if ($this->story->status !== 'scheduled') {
                Log::info('Story publishing skipped - status changed', [
                    'story_id' => $this->story->id,
                    'current_status' => $this->story->status
                ]);
                return;
            }

            // Check if scheduled time has passed
            if ($this->story->scheduled_at && $this->story->scheduled_at->isFuture()) {
                Log::warning('Story publishing skipped - scheduled time is still in the future', [
                    'story_id' => $this->story->id,
                    'scheduled_at' => $this->story->scheduled_at->toDateTimeString()
                ]);
                
                // Reschedule the job
                self::dispatch($this->story)->delay($this->story->scheduled_at);
                return;
            }

            // Create version before publishing
            $systemUser = $this->story->creator ?? $this->story->updater;
            if ($systemUser) {
                $versionManager->createVersion($this->story, $systemUser, 'Scheduled publication');
            }

            // Update story status
            $this->story->status = 'published';
            $this->story->published_at = now();
            $this->story->scheduled_at = null;
            $this->story->save();

            Log::info('Story published successfully via scheduled job', [
                'story_id' => $this->story->id,
                'story_name' => $this->story->name,
                'published_at' => $this->story->published_at->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to publish scheduled story', [
                'story_id' => $this->story->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If this was the last attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $this->story->update([
                    'status' => 'draft',
                    'scheduled_at' => null
                ]);

                Log::error('Story publishing failed after maximum attempts - status reset to draft', [
                    'story_id' => $this->story->id,
                    'attempts' => $this->attempts()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PublishStoryJob failed permanently', [
            'story_id' => $this->story->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Reset story to draft status
        try {
            $this->story->update([
                'status' => 'draft',
                'scheduled_at' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset story status after job failure', [
                'story_id' => $this->story->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'story:' . $this->story->id,
            'space:' . $this->story->space_id,
            'publish'
        ];
    }
}