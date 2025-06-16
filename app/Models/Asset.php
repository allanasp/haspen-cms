<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Asset model for file management with CDN support and image processing.
 *
 * @property int $id
 * @property int $space_id
 * @property string $uuid
 * @property string $filename
 * @property string|null $name
 * @property string|null $description
 * @property string|null $alt_text
 * @property string $content_type
 * @property int $file_size
 * @property string $file_hash
 * @property string $extension
 * @property string $storage_disk
 * @property string $storage_path
 * @property string|null $public_url
 * @property string|null $cdn_url
 * @property int|null $width
 * @property int|null $height
 * @property float|null $aspect_ratio
 * @property string|null $dominant_color
 * @property bool $has_transparency
 * @property array|null $processing_data
 * @property array $variants
 * @property bool $is_processed
 * @property \Carbon\Carbon|null $processed_at
 * @property int|null $folder_id
 * @property array $tags
 * @property array $custom_fields
 * @property int $download_count
 * @property \Carbon\Carbon|null $last_accessed_at
 * @property array $usage_stats
 * @property string|null $external_id
 * @property array|null $external_data
 * @property bool $is_public
 * @property array|null $allowed_roles
 * @property \Carbon\Carbon|null $expires_at
 * @property int $uploaded_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Space $space
 * @property-read User $uploader
 * @property-read User|null $updater
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Asset whereSpaceId(int $spaceId)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset byContentType(string $contentType)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset images()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset videos()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset documents()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset public()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset private()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset processed()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset unprocessed()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset withinSizeRange(int $minSize, int $maxSize)
 * @method static \Illuminate\Database\Eloquent\Builder|Asset recentlyAccessed()
 * @method static \Illuminate\Database\Eloquent\Builder|Asset popular()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @psalm-suppress UnusedClass
 */
final class Asset extends Model
{
    use Cacheable;
    use HasFactory;
    use HasUuid;
    use MultiTenant;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected string $table = 'assets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected array $fillable = [
        'filename',
        'name',
        'description',
        'alt_text',
        'content_type',
        'file_size',
        'file_hash',
        'extension',
        'storage_disk',
        'storage_path',
        'public_url',
        'cdn_url',
        'width',
        'height',
        'aspect_ratio',
        'dominant_color',
        'has_transparency',
        'processing_data',
        'variants',
        'is_processed',
        'processed_at',
        'folder_id',
        'tags',
        'custom_fields',
        'external_id',
        'external_data',
        'is_public',
        'allowed_roles',
        'expires_at',
        'uploaded_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'aspect_ratio' => 'decimal:4',
        'has_transparency' => 'boolean',
        'processing_data' => Json::class,
        'variants' => Json::class,
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'folder_id' => 'integer',
        'tags' => Json::class,
        'custom_fields' => Json::class,
        'download_count' => 'integer',
        'last_accessed_at' => 'datetime',
        'usage_stats' => Json::class,
        'external_data' => Json::class,
        'is_public' => 'boolean',
        'allowed_roles' => Json::class,
        'expires_at' => 'datetime',
        'uploaded_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected array $hidden = [
        'storage_path',
        'file_hash',
        'processing_data',
        'external_data',
        'allowed_roles',
    ];

    /**
     * The space this asset belongs to.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * The user who uploaded this asset.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The user who last updated this asset.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get assets by content type.
     */
    public function scopeByContentType(\Illuminate\Database\Eloquent\Builder $query, string $contentType): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Get only image assets.
     */
    public function scopeImages(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('content_type', 'like', 'image/%');
    }

    /**
     * Get only video assets.
     */
    public function scopeVideos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('content_type', 'like', 'video/%');
    }

    /**
     * Get only document assets.
     */
    public function scopeDocuments(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('content_type', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
    }

    /**
     * Get only public assets.
     */
    public function scopePublic(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get only private assets.
     */
    public function scopePrivate(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_public', false);
    }

    /**
     * Get only processed assets.
     */
    public function scopeProcessed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_processed', true);
    }

    /**
     * Get only unprocessed assets.
     */
    public function scopeUnprocessed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_processed', false);
    }

    /**
     * Get assets within a specific size range.
     */
    public function scopeWithinSizeRange(\Illuminate\Database\Eloquent\Builder $query, int $minSize, int $maxSize): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereBetween('file_size', [$minSize, $maxSize]);
    }

    /**
     * Get recently accessed assets.
     */
    public function scopeRecentlyAccessed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('last_accessed_at')
            ->orderBy('last_accessed_at', 'desc');
    }

    /**
     * Get popular assets by download count.
     */
    public function scopePopular(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('download_count', '>', 0)
            ->orderBy('download_count', 'desc');
    }

    /**
     * Check if the asset is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    /**
     * Check if the asset is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->content_type, 'video/');
    }

    /**
     * Check if the asset is a document.
     */
    public function isDocument(): bool
    {
        return in_array($this->content_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ]);
    }

    /**
     * Get the primary URL for this asset (CDN if available, otherwise public URL).
     */
    public function getUrl(): string
    {
        return $this->cdn_url ?? $this->public_url ?? $this->generateUrl();
    }

    /**
     * Generate a URL for the asset.
     */
    public function generateUrl(): string
    {
        if ($this->public_url) {
            return $this->public_url;
        }

        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    /**
     * Get a CDN URL with optional transformations.
     *
     * @param array<string, mixed> $transformations
     */
    public function getCdnUrl(array $transformations = []): string
    {
        $baseUrl = $this->cdn_url ?? $this->getUrl();

        if (empty($transformations) || !$this->isImage()) {
            return $baseUrl;
        }

        // Build transformation query string
        $params = [];
        
        if (isset($transformations['width'])) {
            $params['w'] = (int) $transformations['width'];
        }
        
        if (isset($transformations['height'])) {
            $params['h'] = (int) $transformations['height'];
        }
        
        if (isset($transformations['quality'])) {
            $params['q'] = max(1, min(100, (int) $transformations['quality']));
        }
        
        if (isset($transformations['format'])) {
            $params['f'] = $transformations['format'];
        }
        
        if (isset($transformations['fit'])) {
            $params['fit'] = $transformations['fit'];
        }

        if (!empty($params)) {
            $baseUrl .= '?' . http_build_query($params);
        }

        return $baseUrl;
    }

    /**
     * Get a specific variant of the asset.
     */
    public function getVariant(string $variantName): ?string
    {
        $variants = $this->variants ?? [];
        
        return $variants[$variantName] ?? null;
    }

    /**
     * Get all available variants.
     *
     * @return array<string, string>
     */
    public function getVariants(): array
    {
        return $this->variants ?? [];
    }

    /**
     * Add a new variant to the asset.
     */
    public function addVariant(string $name, string $url): void
    {
        $variants = $this->variants ?? [];
        $variants[$name] = $url;
        $this->variants = $variants;
        $this->save();
    }

    /**
     * Get the human-readable file size.
     */
    public function getHumanReadableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->file_size;
        
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the asset has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the user can access this asset.
     */
    public function canBeAccessedBy(?User $user = null): bool
    {
        // Check if asset has expired
        if ($this->hasExpired()) {
            return false;
        }

        // Public assets can be accessed by anyone
        if ($this->is_public) {
            return true;
        }

        // Private assets require a user
        if (!$user) {
            return false;
        }

        // Check role-based access
        if ($this->allowed_roles && !empty($this->allowed_roles)) {
            $userRoles = $user->roles->pluck('name')->toArray();
            return !empty(array_intersect($userRoles, $this->allowed_roles));
        }

        // Default: user must belong to the same space
        return $user->spaces->contains($this->space_id);
    }

    /**
     * Track asset access.
     */
    public function trackAccess(): void
    {
        $this->increment('download_count');
        $this->update(['last_accessed_at' => now()]);

        // Update usage stats
        $stats = $this->usage_stats ?? [];
        $today = now()->format('Y-m-d');
        
        if (!isset($stats['daily'])) {
            $stats['daily'] = [];
        }
        
        if (!isset($stats['daily'][$today])) {
            $stats['daily'][$today] = 0;
        }
        
        $stats['daily'][$today]++;
        
        // Keep only last 30 days
        $stats['daily'] = array_slice($stats['daily'], -30, null, true);
        
        $this->update(['usage_stats' => $stats]);
    }

    /**
     * Get usage statistics for a specific period.
     *
     * @return array<string, mixed>
     */
    public function getUsageStats(int $days = 30): array
    {
        $stats = $this->usage_stats ?? [];
        $dailyStats = $stats['daily'] ?? [];
        
        $period = now()->subDays($days)->format('Y-m-d');
        $filteredStats = array_filter($dailyStats, fn($key) => $key >= $period, ARRAY_FILTER_USE_KEY);
        
        return [
            'total_downloads' => $this->download_count,
            'period_downloads' => array_sum($filteredStats),
            'daily_breakdown' => $filteredStats,
            'average_daily' => count($filteredStats) > 0 ? array_sum($filteredStats) / count($filteredStats) : 0,
        ];
    }

    /**
     * Get the display name for the asset.
     */
    public function getDisplayName(): string
    {
        return $this->name ?? pathinfo($this->filename, PATHINFO_FILENAME);
    }

    /**
     * Generate a unique filename for storage.
     */
    public static function generateStoragePath(string $filename, int $spaceId): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = Str::uuid()->toString();
        
        return sprintf('spaces/%d/assets/%s.%s', $spaceId, $basename, $extension);
    }

    /**
     * Get validation rules for asset creation/update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'filename' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:1', 'max:104857600'], // 100MB max
            'file_hash' => ['required', 'string', 'max:255'],
            'extension' => ['required', 'string', 'max:10'],
            'storage_disk' => ['required', 'string', 'max:50'],
            'storage_path' => ['required', 'string', 'max:500'],
            'public_url' => ['nullable', 'string', 'url', 'max:500'],
            'cdn_url' => ['nullable', 'string', 'url', 'max:500'],
            'width' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'aspect_ratio' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'dominant_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'has_transparency' => ['boolean'],
            'processing_data' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'is_processed' => ['boolean'],
            'processed_at' => ['nullable', 'date'],
            'folder_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'external_data' => ['nullable', 'array'],
            'is_public' => ['boolean'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'uploaded_by' => ['required', 'integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get validation rules for asset creation.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function createRules(): array
    {
        return array_merge(self::rules(), [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:1', 'max:104857600'],
            'file_hash' => ['required', 'string', 'max:255'],
            'extension' => ['required', 'string', 'max:10'],
            'storage_disk' => ['required', 'string', 'max:50'],
            'storage_path' => ['required', 'string', 'max:500'],
            'uploaded_by' => ['required', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * Get validation rules for asset update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function updateRules(): array
    {
        $rules = self::rules();
        
        // Make certain fields optional for updates
        $rules['file_size'] = ['nullable', 'integer', 'min:1', 'max:104857600'];
        $rules['file_hash'] = ['nullable', 'string', 'max:255'];
        $rules['storage_disk'] = ['nullable', 'string', 'max:50'];
        $rules['storage_path'] = ['nullable', 'string', 'max:500'];
        $rules['uploaded_by'] = ['nullable', 'integer', 'exists:users,id'];
        
        return $rules;
    }

    /**
     * Get validation rules for metadata update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function metadataUpdateRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
            'is_public' => ['boolean'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Get validation rules for file upload.
     *
     * @param int $maxFileSize Max file size in bytes
     * @return array<string, array<int, string>|string>
     */
    public static function uploadRules(int $maxFileSize = 104857600): array
    {
        return [
            'file' => [
                'required',
                'file',
                "max:{$maxFileSize}",
                'mimes:jpg,jpeg,png,gif,svg,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp4,mov,avi,wmv,flv,webm,mp3,wav,aac,ogg'
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'folder_id' => ['nullable', 'integer'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * Get validation rules for image processing.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function processingRules(): array
    {
        return [
            'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:100'],
            'format' => ['nullable', 'string', 'in:jpg,jpeg,png,webp,gif'],
            'fit' => ['nullable', 'string', 'in:contain,cover,fill,inside,outside'],
            'variants' => ['nullable', 'array'],
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Asset $asset) {
            // Calculate aspect ratio for images
            if ($asset->isImage() && $asset->width && $asset->height) {
                $asset->aspect_ratio = $asset->width / $asset->height;
            }
        });
    }
}