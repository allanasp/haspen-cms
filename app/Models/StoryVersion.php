<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Story Version Model - Tracks content history and changes.
 * 
 * @property int $id
 * @property string $uuid
 * @property int $story_id
 * @property int $version_number
 * @property string $name
 * @property string $slug
 * @property array $content
 * @property string $status
 * @property string $language
 * @property int $position
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $scheduled_at
 * @property string|null $reason
 * @property int $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * 
 * @property-read Story $story
 * @property-read User $creator
 */
class StoryVersion extends Model
{
    use HasUuid;
    use MultiTenant;

    protected array $fillable = [
        'story_id',
        'version_number', 
        'name',
        'slug',
        'content',
        'status',
        'language',
        'position',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'published_at',
        'scheduled_at',
        'reason',
        'created_by',
    ];

    protected array $casts = [
        'content' => Json::class,
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'position' => 'integer',
    ];

    protected array $attributes = [
        'content' => '[]',
        'status' => 'draft',
        'language' => 'en',
        'position' => 0,
    ];

    /**
     * Get the story this version belongs to.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the user who created this version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get versions for a specific story.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $storyId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForStory($query, int $storyId)
    {
        return $query->where('story_id', $storyId);
    }

    /**
     * Scope to order by version number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByVersion($query, string $direction = 'desc')
    {
        return $query->orderBy('version_number', $direction);
    }

    /**
     * Get the version display name.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return "v{$this->version_number} - {$this->name}";
    }

    /**
     * Check if this is the latest version for the story.
     *
     * @return bool
     */
    public function isLatest(): bool
    {
        $latestVersion = static::forStory($this->story_id)
            ->max('version_number');
            
        return $this->version_number === $latestVersion;
    }

    /**
     * Get the size of the content in bytes.
     *
     * @return int
     */
    public function getContentSize(): int
    {
        return strlen(json_encode($this->content));
    }
}
