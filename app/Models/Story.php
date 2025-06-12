<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Story Model.
 *
 * Represents content pages/posts in the headless CMS.
 * Stories contain structured content using components (Storyblok-style).
 * @psalm-suppress PossiblyUnusedMethod
 *
 * @property int $id
 * @property string $uuid
 * @property int $space_id
 * @property int|null $parent_id
 * @property Story|null $parent
 * @property string $name
 * @property string $slug
 * @property string $full_slug
 * @property array<string, mixed> $content
 * @property string $language
 * @property int|null $translated_story_id
 * @property array<string> $translated_languages
 * @property string $status
 * @property bool $is_folder
 * @property bool $is_startpage
 * @property int $sort_order
 * @property string|null $path
 * @property array<string, mixed>|null $breadcrumbs
 * @property array<string, mixed>|null $meta_data
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array<string, mixed>|null $robots_meta
 * @property array<string>|null $allowed_roles
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $unpublished_at
 * @property \Carbon\Carbon|null $scheduled_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $published_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class Story extends Model
{
    /** @use HasFactory<\Database\Factories\StoryFactory> */
    use HasFactory;
    use HasUuid;
    use MultiTenant;
    use Sluggable;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'full_slug',
        'content',
        'language',
        'translated_story_id',
        'translated_languages',
        'status',
        'is_folder',
        'is_startpage',
        'sort_order',
        'path',
        'breadcrumbs',
        'meta_data',
        'meta_title',
        'meta_description',
        'robots_meta',
        'allowed_roles',
        'published_at',
        'unpublished_at',
        'scheduled_at',
        'created_by',
        'updated_by',
        'published_by',
        'locked_by',
        'locked_at',
        'lock_expires_at',
        'lock_session_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<array-key, mixed>
     */
    protected $casts = [
        'content' => Json::class,
        'translated_languages' => Json::class,
        'is_folder' => 'boolean',
        'is_startpage' => 'boolean',
        'breadcrumbs' => Json::class,
        'meta_data' => Json::class,
        'robots_meta' => Json::class,
        'allowed_roles' => Json::class,
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'locked_at' => 'datetime',
        'lock_expires_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<array-key, string>
     */
    protected $hidden = [
        'id',
        'space_id',
    ];

    /**
     * Sluggable configuration.
     */
    protected string $slugSourceField = 'name';

    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected int $cacheTtl = 3600;

    /**
     * Available story statuses.
     */
    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_REVIEW = 'review';
    public const string STATUS_PUBLISHED = 'published';
    public const string STATUS_SCHEDULED = 'scheduled';
    public const string STATUS_ARCHIVED = 'archived';

    /**
     * Get the parent story.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function parent(): BelongsTo
    {
        /** @var BelongsTo<Story, Story> $relation */
        $relation = $this->belongsTo(Story::class, 'parent_id');
        return $relation;
    }

    /**
     * Generate full slug based on parent hierarchy.
     */
    public function generateFullSlug(): string
    {
        $slugs = [$this->slug];
        $current = $this->parent_id !== null ? $this->parent : null;

        while ($current instanceof Story) {
            array_unshift($slugs, $current->slug);
            $current = $current->parent_id !== null ? $current->parent : null;
        }

        return implode('/', $slugs);
    }

    /**
     * Generate breadcrumbs array.
     *
     * @return array<int|string, mixed>
     */
    public function generateBreadcrumbs(): array
    {
        /** @var array<string, mixed> $breadcrumbs */
        $breadcrumbs = [];
        $current = $this;

        while ($current instanceof Story) {
            $breadcrumbs[] = [
                'uuid' => $current->uuid,
                'name' => $current->name,
                'slug' => $current->slug,
            ];
            $current = $current->parent_id !== null ? $current->parent : null;
        }

        return array_reverse($breadcrumbs);
    }

    /**
     * Get all versions of this story.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function versions(): HasMany
    {
        /** @var HasMany<StoryVersion> $relation */
        $relation = $this->hasMany(StoryVersion::class);
        return $relation;
    }

    /**
     * Get the user who created this story.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function creator(): BelongsTo
    {
        /** @var BelongsTo<User, Story> $relation */
        $relation = $this->belongsTo(User::class, 'created_by');
        return $relation;
    }

    /**
     * Get the user who last updated this story.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function updater(): BelongsTo
    {
        /** @var BelongsTo<User, Story> $relation */
        $relation = $this->belongsTo(User::class, 'updated_by');
        return $relation;
    }

    /**
     * Get child stories.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function children(): HasMany
    {
        /** @var HasMany<Story> $relation */
        $relation = $this->hasMany(Story::class, 'parent_id');
        return $relation;
    }

    /**
     * Get story translations.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function translations(): HasMany
    {
        /** @var HasMany<Story> $relation */
        $relation = $this->hasMany(Story::class, 'translated_story_id');
        return $relation;
    }

    /**
     * Get the user who has locked this story.
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function locker(): BelongsTo
    {
        /** @var BelongsTo<User, Story> $relation */
        $relation = $this->belongsTo(User::class, 'locked_by');
        return $relation;
    }

    /**
     * Create content template from current story.
     *
     * @param string $templateName
     * @param string|null $description
     * @return array<string, mixed>
     */
    public function createTemplate(string $templateName, ?string $description = null): array
    {
        $templateContent = $this->content;
        
        // Remove any unique identifiers and dynamic content
        $templateContent = $this->sanitizeContentForTemplate($templateContent);
        
        return [
            'name' => $templateName,
            'description' => $description ?? "Template created from story: {$this->name}",
            'content' => $templateContent,
            'meta_data' => $this->meta_data,
            'created_from_story_uuid' => $this->uuid,
            'created_at' => now(),
        ];
    }

    /**
     * Create story from template.
     *
     * @param array<string, mixed> $template
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createFromTemplate(array $template, array $overrides = []): array
    {
        $content = $template['content'] ?? [];
        $metaData = $template['meta_data'] ?? [];
        
        // Generate new UIDs for template content
        $content = self::regenerateContentUids($content);
        
        return array_merge([
            'content' => $content,
            'meta_data' => $metaData,
            'status' => self::STATUS_DRAFT,
            'created_from_template' => $template['name'] ?? 'Unknown',
        ], $overrides);
    }

    /**
     * Get available content templates for this space.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableTemplates(): array
    {
        $cacheKey = "story_templates:{$this->space_id}";
        
        return $this->cache($cacheKey, function () {
            // Check if templates are stored in database or config
            $configTemplates = config('cms.content_templates', []);
            $dbTemplates = $this->getCustomTemplatesFromDatabase();
            
            return array_merge($configTemplates, $dbTemplates);
        });
    }

    /**
     * Sanitize content for template creation.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private function sanitizeContentForTemplate(array $content): array
    {
        if (!is_array($content)) {
            return $content;
        }

        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = $this->sanitizeContentForTemplate($value);
            } elseif (is_string($value)) {
                // Remove specific content that shouldn't be in templates
                if ($key === '_uid') {
                    unset($content[$key]);
                } elseif (in_array($key, ['created_at', 'updated_at', 'id', 'uuid'])) {
                    unset($content[$key]);
                } elseif (str_contains($value, 'localhost') || str_contains($value, 'staging') || str_contains($value, 'production')) {
                    // Replace environment-specific URLs with placeholders
                    $content[$key] = '[PLACEHOLDER_URL]';
                }
            }
        }

        return $content;
    }

    /**
     * Regenerate UIDs for template content.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private static function regenerateContentUids(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = self::regenerateContentUids($value);
            } elseif ($key === '_uid') {
                // Generate new UUID for template instances
                $content[$key] = (string) \Illuminate\Support\Str::uuid();
            }
        }

        return $content;
    }

    /**
     * Get custom templates from database.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCustomTemplatesFromDatabase(): array
    {
        // This could be implemented with a separate templates table
        // For now, we'll check for stories marked as templates
        return Story::where('space_id', $this->space_id)
            ->whereJsonContains('meta_data->is_template', true)
            ->get(['uuid', 'name', 'content', 'meta_data'])
            ->map(function ($story) {
                return [
                    'name' => $story->name,
                    'description' => $story->meta_data['template_description'] ?? 'Custom template',
                    'content' => $story->content,
                    'meta_data' => $story->meta_data,
                    'uuid' => $story->uuid,
                    'type' => 'custom',
                ];
            })
            ->toArray();
    }

    /**
     * Lock story for editing.
     *
     * @param User $user
     * @param string|null $sessionId
     * @param int $lockDurationMinutes
     * @return bool
     */
    public function lock(User $user, ?string $sessionId = null, int $lockDurationMinutes = 30): bool
    {
        // Clean up expired locks first
        $this->cleanupExpiredLocks();

        // Check if already locked by someone else
        if ($this->isLockedByOther($user)) {
            return false;
        }

        $now = now();
        $this->update([
            'locked_by' => $user->id,
            'locked_at' => $now,
            'lock_expires_at' => $now->addMinutes($lockDurationMinutes),
            'lock_session_id' => $sessionId ?? session()->getId(),
        ]);

        return true;
    }

    /**
     * Unlock story.
     *
     * @param User|null $user
     * @param string|null $sessionId
     * @return bool
     */
    public function unlock(?User $user = null, ?string $sessionId = null): bool
    {
        // If no user provided, force unlock (admin action)
        if (!$user) {
            $this->update([
                'locked_by' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
                'lock_session_id' => null,
            ]);
            return true;
        }

        // Check if user can unlock (owner or same session)
        if (!$this->canUnlock($user, $sessionId)) {
            return false;
        }

        $this->update([
            'locked_by' => null,
            'locked_at' => null,
            'lock_expires_at' => null,
            'lock_session_id' => null,
        ]);

        return true;
    }

    /**
     * Extend lock duration.
     *
     * @param User $user
     * @param int $extendMinutes
     * @return bool
     */
    public function extendLock(User $user, int $extendMinutes = 30): bool
    {
        if (!$this->isLockedBy($user)) {
            return false;
        }

        $this->update([
            'lock_expires_at' => now()->addMinutes($extendMinutes),
        ]);

        return true;
    }

    /**
     * Check if story is locked.
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        $this->cleanupExpiredLocks();

        return $this->locked_by !== null &&
               $this->lock_expires_at !== null &&
               $this->lock_expires_at->isFuture();
    }

    /**
     * Check if story is locked by specific user.
     *
     * @param User $user
     * @return bool
     */
    public function isLockedBy(User $user): bool
    {
        return $this->isLocked() && $this->locked_by === $user->id;
    }

    /**
     * Check if story is locked by someone else.
     *
     * @param User $user
     * @return bool
     */
    public function isLockedByOther(User $user): bool
    {
        return $this->isLocked() && $this->locked_by !== $user->id;
    }

    /**
     * Check if user can unlock the story.
     *
     * @param User $user
     * @param string|null $sessionId
     * @return bool
     */
    public function canUnlock(User $user, ?string $sessionId = null): bool
    {
        if (!$this->isLocked()) {
            return true;
        }

        // Story owner can unlock
        if ($this->locked_by === $user->id) {
            return true;
        }

        // Same session can unlock
        if ($sessionId && $this->lock_session_id === $sessionId) {
            return true;
        }

        return false;
    }

    /**
     * Get lock information.
     *
     * @return array<string, mixed>|null
     */
    public function getLockInfo(): ?array
    {
        if (!$this->isLocked()) {
            return null;
        }

        return [
            'locked_by' => $this->locked_by,
            'locked_at' => $this->locked_at,
            'lock_expires_at' => $this->lock_expires_at,
            'session_id' => $this->lock_session_id,
            'locker' => $this->locker?->only(['id', 'name', 'email']),
            'time_remaining' => $this->lock_expires_at?->diffInMinutes(now()),
        ];
    }

    /**
     * Clean up expired locks for this story.
     */
    private function cleanupExpiredLocks(): void
    {
        if ($this->lock_expires_at && $this->lock_expires_at->isPast()) {
            $this->update([
                'locked_by' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
                'lock_session_id' => null,
            ]);
        }
    }

    /**
     * Clean up all expired locks across all stories.
     */
    public static function cleanupAllExpiredLocks(): int
    {
        return static::whereNotNull('lock_expires_at')
            ->where('lock_expires_at', '<', now())
            ->update([
                'locked_by' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
                'lock_session_id' => null,
            ]);
    }

    /**
     * Create translation for this story.
     *
     * @param string $targetLanguage
     * @param array<string, mixed> $translatedData
     * @param User $user
     * @return Story
     */
    public function createTranslation(string $targetLanguage, array $translatedData, User $user): Story
    {
        // Generate translation group ID if not exists
        $translationGroupId = $this->translation_group_id ?? $this->id;
        
        // Check if translation already exists
        $existingTranslation = Story::where('space_id', $this->space_id)
            ->where('translation_group_id', $translationGroupId)
            ->where('language', $targetLanguage)
            ->first();
            
        if ($existingTranslation) {
            throw new \InvalidArgumentException("Translation for language '{$targetLanguage}' already exists");
        }
        
        $translationData = array_merge([
            'space_id' => $this->space_id,
            'name' => $translatedData['name'] ?? $this->name . " ({$targetLanguage})",
            'slug' => $translatedData['slug'] ?? $this->slug . "-{$targetLanguage}",
            'content' => $translatedData['content'] ?? $this->content,
            'language' => $targetLanguage,
            'translation_group_id' => $translationGroupId,
            'status' => Story::STATUS_DRAFT,
            'parent_id' => $this->parent_id,
            'is_folder' => $this->is_folder,
            'meta_data' => array_merge($this->meta_data ?? [], $translatedData['meta_data'] ?? []),
            'created_by' => $user->id,
        ], $translatedData);
        
        $translation = Story::create($translationData);
        
        // Update original story with translation group ID if not set
        if (!$this->translation_group_id) {
            $this->update(['translation_group_id' => $translationGroupId]);
        }
        
        // Update translated languages list
        $this->updateTranslatedLanguages();
        
        return $translation;
    }

    /**
     * Sync translation content from another story.
     *
     * @param Story $sourceStory
     * @param array<string> $fieldsToSync
     * @return bool
     */
    public function syncTranslationContent(Story $sourceStory, array $fieldsToSync = ['content', 'meta_data']): bool
    {
        if (!$this->isTranslationOf($sourceStory)) {
            throw new \InvalidArgumentException('Stories are not in the same translation group');
        }
        
        $updateData = [];
        
        foreach ($fieldsToSync as $field) {
            if ($field === 'content') {
                $updateData['content'] = $this->syncContentStructure($sourceStory->content, $this->content);
            } elseif ($field === 'meta_data') {
                $updateData['meta_data'] = $this->syncMetaData($sourceStory->meta_data, $this->meta_data);
            } elseif (in_array($field, $this->fillable)) {
                $updateData[$field] = $sourceStory->$field;
            }
        }
        
        if (!empty($updateData)) {
            $this->update($updateData);
            return true;
        }
        
        return false;
    }

    /**
     * Check if this story is a translation of another story.
     *
     * @param Story $story
     * @return bool
     */
    public function isTranslationOf(Story $story): bool
    {
        return $this->translation_group_id !== null &&
               $this->translation_group_id === $story->translation_group_id &&
               $this->id !== $story->id;
    }

    /**
     * Get all translations of this story.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Story>
     */
    public function getAllTranslations(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->translation_group_id) {
            return collect([$this]);
        }
        
        return Story::where('translation_group_id', $this->translation_group_id)
            ->where('space_id', $this->space_id)
            ->orderBy('language')
            ->get();
    }

    /**
     * Get translation status for all languages.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTranslationStatus(): array
    {
        $translations = $this->getAllTranslations();
        $status = [];
        
        foreach ($translations as $translation) {
            $status[$translation->language] = [
                'uuid' => $translation->uuid,
                'status' => $translation->status,
                'last_updated' => $translation->updated_at,
                'word_count' => $this->getWordCount($translation->content),
                'completion_percentage' => $this->calculateTranslationCompletion($translation),
                'needs_sync' => $this->needsTranslationSync($translation),
            ];
        }
        
        return $status;
    }

    /**
     * Get untranslated content fields.
     *
     * @param Story $sourceStory
     * @return array<string, mixed>
     */
    public function getUntranslatedFields(Story $sourceStory): array
    {
        if (!$this->isTranslationOf($sourceStory)) {
            return [];
        }
        
        $untranslated = [];
        
        // Check content structure
        $untranslated['content'] = $this->findUntranslatedContent($sourceStory->content, $this->content);
        
        // Check meta fields
        $sourceMetaFields = ['meta_title', 'meta_description'];
        foreach ($sourceMetaFields as $field) {
            if (!empty($sourceStory->$field) && (empty($this->$field) || $this->$field === $sourceStory->$field)) {
                $untranslated['meta'][$field] = $sourceStory->$field;
            }
        }
        
        return array_filter($untranslated);
    }

    /**
     * Update translated languages list for all stories in translation group.
     */
    private function updateTranslatedLanguages(): void
    {
        if (!$this->translation_group_id) {
            return;
        }
        
        $languages = Story::where('translation_group_id', $this->translation_group_id)
            ->where('space_id', $this->space_id)
            ->pluck('language')
            ->toArray();
            
        Story::where('translation_group_id', $this->translation_group_id)
            ->where('space_id', $this->space_id)
            ->update(['translated_languages' => $languages]);
    }

    /**
     * Sync content structure between translations.
     *
     * @param array<string, mixed> $sourceContent
     * @param array<string, mixed> $targetContent
     * @return array<string, mixed>
     */
    private function syncContentStructure(array $sourceContent, array $targetContent): array
    {
        if (!is_array($sourceContent) || !is_array($targetContent)) {
            return $targetContent;
        }
        
        $syncedContent = $targetContent;
        
        // Sync component structure but preserve translated text
        foreach ($sourceContent as $key => $value) {
            if ($key === 'body' && is_array($value)) {
                $syncedContent[$key] = $this->syncContentBlocks($value, $targetContent[$key] ?? []);
            } elseif (is_array($value) && isset($targetContent[$key])) {
                $syncedContent[$key] = $this->syncContentStructure($value, $targetContent[$key]);
            } elseif (!isset($targetContent[$key]) && !$this->isTranslatableField($key)) {
                // Copy non-translatable fields
                $syncedContent[$key] = $value;
            }
        }
        
        return $syncedContent;
    }

    /**
     * Sync content blocks between translations.
     *
     * @param array<int, array<string, mixed>> $sourceBlocks
     * @param array<int, array<string, mixed>> $targetBlocks
     * @return array<int, array<string, mixed>>
     */
    private function syncContentBlocks(array $sourceBlocks, array $targetBlocks): array
    {
        $syncedBlocks = [];
        $targetBlocksByUid = collect($targetBlocks)->keyBy('_uid');
        
        foreach ($sourceBlocks as $sourceBlock) {
            $uid = $sourceBlock['_uid'] ?? null;
            $targetBlock = $uid ? $targetBlocksByUid->get($uid) : null;
            
            if ($targetBlock) {
                // Merge structure but keep translated content
                $syncedBlocks[] = $this->syncContentStructure($sourceBlock, $targetBlock);
            } else {
                // New block - copy structure, mark for translation
                $newBlock = $sourceBlock;
                $newBlock['_translation_needed'] = true;
                $syncedBlocks[] = $newBlock;
            }
        }
        
        return $syncedBlocks;
    }

    /**
     * Sync meta data between translations.
     *
     * @param array<string, mixed>|null $sourceMeta
     * @param array<string, mixed>|null $targetMeta
     * @return array<string, mixed>
     */
    private function syncMetaData(?array $sourceMeta, ?array $targetMeta): array
    {
        $sourceMeta = $sourceMeta ?? [];
        $targetMeta = $targetMeta ?? [];
        
        $nonTranslatableFields = ['canonical_url', 'robots', 'schema_markup'];
        
        foreach ($nonTranslatableFields as $field) {
            if (isset($sourceMeta[$field])) {
                $targetMeta[$field] = $sourceMeta[$field];
            }
        }
        
        return $targetMeta;
    }

    /**
     * Check if field contains translatable content.
     *
     * @param string $fieldName
     * @return bool
     */
    private function isTranslatableField(string $fieldName): bool
    {
        $translatableFields = [
            'text', 'title', 'description', 'content', 'body',
            'headline', 'subtitle', 'caption', 'alt_text',
            'meta_title', 'meta_description'
        ];
        
        foreach ($translatableFields as $pattern) {
            if (str_contains(strtolower($fieldName), $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Find untranslated content fields.
     *
     * @param array<string, mixed> $sourceContent
     * @param array<string, mixed> $targetContent
     * @return array<string, mixed>
     */
    private function findUntranslatedContent(array $sourceContent, array $targetContent): array
    {
        $untranslated = [];
        
        foreach ($sourceContent as $key => $value) {
            if (is_string($value) && $this->isTranslatableField($key)) {
                $targetValue = $targetContent[$key] ?? '';
                if (empty($targetValue) || $targetValue === $value) {
                    $untranslated[$key] = $value;
                }
            } elseif (is_array($value)) {
                $nestedUntranslated = $this->findUntranslatedContent($value, $targetContent[$key] ?? []);
                if (!empty($nestedUntranslated)) {
                    $untranslated[$key] = $nestedUntranslated;
                }
            }
        }
        
        return $untranslated;
    }

    /**
     * Calculate translation completion percentage.
     *
     * @param Story $translation
     * @return int
     */
    private function calculateTranslationCompletion(Story $translation): int
    {
        // This is a simplified calculation
        // In a real implementation, you might want more sophisticated logic
        $totalFields = $this->countTranslatableFields($this->content);
        $translatedFields = $this->countTranslatedFields($translation->content);
        
        if ($totalFields === 0) {
            return 100;
        }
        
        return min(100, intval(($translatedFields / $totalFields) * 100));
    }

    /**
     * Check if translation needs sync.
     *
     * @param Story $translation
     * @return bool
     */
    private function needsTranslationSync(Story $translation): bool
    {
        // Check if source was updated after translation
        return $this->updated_at > $translation->updated_at;
    }

    /**
     * Count translatable fields in content.
     *
     * @param array<string, mixed> $content
     * @return int
     */
    private function countTranslatableFields(array $content): int
    {
        $count = 0;
        
        foreach ($content as $key => $value) {
            if (is_string($value) && $this->isTranslatableField($key) && !empty($value)) {
                $count++;
            } elseif (is_array($value)) {
                $count += $this->countTranslatableFields($value);
            }
        }
        
        return $count;
    }

    /**
     * Count translated fields in content.
     *
     * @param array<string, mixed> $content
     * @return int
     */
    private function countTranslatedFields(array $content): int
    {
        $count = 0;
        
        foreach ($content as $key => $value) {
            if (is_string($value) && $this->isTranslatableField($key) && !empty($value)) {
                $count++;
            } elseif (is_array($value)) {
                $count += $this->countTranslatedFields($value);
            }
        }
        
        return $count;
    }

    /**
     * Get word count from content.
     *
     * @param array<string, mixed> $content
     * @return int
     */
    private function getWordCount(array $content): int
    {
        $text = $this->extractTextFromContent(['body' => $content]);
        return str_word_count(strip_tags($text));
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('content');
        $this->forgetCache('seo_meta');
        $this->forgetCache('url');
        $this->forgetCache("story_templates:{$this->space_id}");
    }

    /**
     * Boot the model.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Story $story) {
            // Auto-generate full slug and path
            if ($story->isDirty(['slug', 'parent_id'])) {
                $story->full_slug = $story->generateFullSlug();
                $story->path = '/' . $story->full_slug;
                /** @var array<string, mixed> $breadcrumbs */
                $breadcrumbs = $story->generateBreadcrumbs();
                $story->breadcrumbs = $breadcrumbs;
            }
        });
    }
}
