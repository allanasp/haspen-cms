<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use App\Models\StoryVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Version management service for story content history.
 */
class VersionManager
{
    private const MAX_VERSIONS_PER_STORY = 50;

    /**
     * Create a new version of a story.
     *
     * @param Story $story
     * @param User $user
     * @param string $reason
     * @return StoryVersion
     */
    public function createVersion(Story $story, User $user, string $reason = 'Manual save'): StoryVersion
    {
        return DB::transaction(function () use ($story, $user, $reason) {
            // Get the next version number
            $versionNumber = $this->getNextVersionNumber($story);

            // Create the version
            $version = new StoryVersion();
            $version->story_id = $story->id;
            $version->version_number = $versionNumber;
            $version->name = $story->name;
            $version->slug = $story->slug;
            $version->content = $story->content;
            $version->status = $story->status;
            $version->meta_title = $story->meta_title;
            $version->meta_description = $story->meta_description;
            $version->meta_keywords = $story->meta_keywords;
            $version->og_title = $story->og_title;
            $version->og_description = $story->og_description;
            $version->og_image = $story->og_image;
            $version->published_at = $story->published_at;
            $version->scheduled_at = $story->scheduled_at;
            $version->language = $story->language;
            $version->reason = $reason;
            $version->created_by = $user->id;

            $version->save();

            // Clean up old versions if we exceed the limit
            $this->cleanupOldVersions($story);

            return $version;
        });
    }

    /**
     * Get paginated versions for a story.
     *
     * @param Story $story
     * @param int $perPage
     * @return LengthAwarePaginator<StoryVersion>
     */
    public function getVersions(Story $story, int $perPage = 20): LengthAwarePaginator
    {
        return StoryVersion::where('story_id', $story->id)
            ->with('creator')
            ->orderBy('version_number', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a specific version by version number.
     *
     * @param Story $story
     * @param int $versionNumber
     * @return StoryVersion|null
     */
    public function getVersion(Story $story, int $versionNumber): ?StoryVersion
    {
        return StoryVersion::where('story_id', $story->id)
            ->where('version_number', $versionNumber)
            ->with('creator')
            ->first();
    }

    /**
     * Get the latest version.
     *
     * @param Story $story
     * @return StoryVersion|null
     */
    public function getLatestVersion(Story $story): ?StoryVersion
    {
        return StoryVersion::where('story_id', $story->id)
            ->orderBy('version_number', 'desc')
            ->with('creator')
            ->first();
    }

    /**
     * Restore a story to a specific version.
     *
     * @param Story $story
     * @param int $versionNumber
     * @param User $user
     * @return Story
     * @throws \InvalidArgumentException
     */
    public function restoreToVersion(Story $story, int $versionNumber, User $user): Story
    {
        $version = $this->getVersion($story, $versionNumber);

        if (!$version) {
            throw new \InvalidArgumentException("Version {$versionNumber} not found");
        }

        return DB::transaction(function () use ($story, $version, $user) {
            // Create a version of the current state before restoring
            $this->createVersion($story, $user, "Before restoring to version {$version->version_number}");

            // Restore the story to the version state
            $story->name = $version->name;
            $story->slug = $version->slug;
            $story->content = $version->content;
            $story->status = $version->status;
            $story->meta_title = $version->meta_title;
            $story->meta_description = $version->meta_description;
            $story->meta_keywords = $version->meta_keywords;
            $story->og_title = $version->og_title;
            $story->og_description = $version->og_description;
            $story->og_image = $version->og_image;
            $story->language = $version->language;
            $story->updated_by = $user->id;

            // Don't restore published_at or scheduled_at to prevent accidental publishing
            
            $story->save();

            // Create a version for the restoration
            $this->createVersion($story, $user, "Restored to version {$version->version_number}");

            return $story;
        });
    }

    /**
     * Compare two versions and return differences.
     *
     * @param Story $story
     * @param int $versionA
     * @param int $versionB
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    public function compareVersions(Story $story, int $versionA, int $versionB): array
    {
        $vA = $this->getVersion($story, $versionA);
        $vB = $this->getVersion($story, $versionB);

        if (!$vA || !$vB) {
            throw new \InvalidArgumentException('One or both versions not found');
        }

        $differences = [];

        // Compare fields
        $fields = [
            'name', 'slug', 'content', 'status', 'meta_title', 'meta_description',
            'meta_keywords', 'og_title', 'og_description', 'og_image', 'language'
        ];

        foreach ($fields as $field) {
            $valueA = $vA->{$field};
            $valueB = $vB->{$field};

            if ($valueA !== $valueB) {
                $differences[$field] = [
                    'version_' . $versionA => $valueA,
                    'version_' . $versionB => $valueB,
                    'changed' => true
                ];
            } else {
                $differences[$field] = [
                    'value' => $valueA,
                    'changed' => false
                ];
            }
        }

        // Add metadata
        $differences['_metadata'] = [
            'version_a' => [
                'number' => $vA->version_number,
                'created_at' => $vA->created_at,
                'created_by' => $vA->creator?->name,
                'reason' => $vA->reason
            ],
            'version_b' => [
                'number' => $vB->version_number,
                'created_at' => $vB->created_at,
                'created_by' => $vB->creator?->name,
                'reason' => $vB->reason
            ]
        ];

        return $differences;
    }

    /**
     * Delete a specific version.
     *
     * @param Story $story
     * @param int $versionNumber
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function deleteVersion(Story $story, int $versionNumber): bool
    {
        // Prevent deletion of the only version
        $versionCount = StoryVersion::where('story_id', $story->id)->count();
        
        if ($versionCount <= 1) {
            throw new \InvalidArgumentException('Cannot delete the only version');
        }

        $version = $this->getVersion($story, $versionNumber);

        if (!$version) {
            throw new \InvalidArgumentException("Version {$versionNumber} not found");
        }

        return $version->delete();
    }

    /**
     * Get version statistics for a story.
     *
     * @param Story $story
     * @return array<string, mixed>
     */
    public function getVersionStats(Story $story): array
    {
        $versions = StoryVersion::where('story_id', $story->id)
            ->with('creator')
            ->orderBy('version_number')
            ->get();

        if ($versions->isEmpty()) {
            return [
                'total_versions' => 0,
                'first_version' => null,
                'latest_version' => null,
                'contributors' => [],
                'version_frequency' => []
            ];
        }

        $contributors = $versions->groupBy('created_by')
            ->map(function ($userVersions) {
                $user = $userVersions->first()->creator;
                return [
                    'user' => $user ? $user->name : 'Unknown',
                    'version_count' => $userVersions->count(),
                    'latest_version' => $userVersions->max('version_number')
                ];
            })
            ->sortByDesc('version_count')
            ->values();

        // Calculate version frequency (versions per day)
        $firstVersion = $versions->first();
        $latestVersion = $versions->last();
        $daysDiff = $firstVersion->created_at->diffInDays($latestVersion->created_at) ?: 1;
        $frequency = round($versions->count() / $daysDiff, 2);

        return [
            'total_versions' => $versions->count(),
            'first_version' => [
                'number' => $firstVersion->version_number,
                'created_at' => $firstVersion->created_at,
                'created_by' => $firstVersion->creator?->name
            ],
            'latest_version' => [
                'number' => $latestVersion->version_number,
                'created_at' => $latestVersion->created_at,
                'created_by' => $latestVersion->creator?->name
            ],
            'contributors' => $contributors->toArray(),
            'version_frequency' => $frequency
        ];
    }

    /**
     * Create versions for multiple stories (bulk operation).
     *
     * @param Collection<int, Story> $stories
     * @param User $user
     * @param string $reason
     * @return int
     */
    public function createBulkVersions(Collection $stories, User $user, string $reason): int
    {
        $count = 0;

        DB::transaction(function () use ($stories, $user, $reason, &$count) {
            foreach ($stories as $story) {
                try {
                    $this->createVersion($story, $user, $reason);
                    $count++;
                } catch (\Exception $e) {
                    \Log::warning('Failed to create version for story', [
                        'story_id' => $story->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });

        return $count;
    }

    /**
     * Clean up versions older than specified days.
     *
     * @param Story $story
     * @param int $days
     * @return int Number of deleted versions
     */
    public function cleanupOldVersions(Story $story, int $days = 90): int
    {
        // Always keep the latest 10 versions regardless of age
        $keepVersions = StoryVersion::where('story_id', $story->id)
            ->orderBy('version_number', 'desc')
            ->limit(10)
            ->pluck('id');

        $deleted = StoryVersion::where('story_id', $story->id)
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('id', $keepVersions)
            ->delete();

        return $deleted;
    }

    /**
     * Get the next version number for a story.
     *
     * @param Story $story
     * @return int
     */
    private function getNextVersionNumber(Story $story): int
    {
        $lastVersion = StoryVersion::where('story_id', $story->id)
            ->max('version_number');

        return ($lastVersion ?? 0) + 1;
    }

    /**
     * Clean up excess versions, keeping only the latest ones.
     *
     * @param Story $story
     */
    private function cleanupExcessVersions(Story $story): void
    {
        $versionCount = StoryVersion::where('story_id', $story->id)->count();

        if ($versionCount > self::MAX_VERSIONS_PER_STORY) {
            $versionsToDelete = $versionCount - self::MAX_VERSIONS_PER_STORY;

            StoryVersion::where('story_id', $story->id)
                ->orderBy('version_number')
                ->limit($versionsToDelete)
                ->delete();
        }
    }
}