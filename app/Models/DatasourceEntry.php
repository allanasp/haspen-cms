<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DatasourceEntry model for storing individual data entries from datasources.
 *
 * @property int $id
 * @property string $uuid
 * @property int $datasource_id
 * @property string|null $external_id
 * @property array $data
 * @property int|null $position
 * @property string $status
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Datasource $datasource
 *
 * @method static \Illuminate\Database\Eloquent\Builder|DatasourceEntry byDatasource(int $datasourceId)
 * @method static \Illuminate\Database\Eloquent\Builder|DatasourceEntry byExternalId(string $externalId)
 * @method static \Illuminate\Database\Eloquent\Builder|DatasourceEntry published()
 * @method static \Illuminate\Database\Eloquent\Builder|DatasourceEntry draft()
 * @method static \Illuminate\Database\Eloquent\Builder|DatasourceEntry ordered()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @psalm-suppress UnusedClass
 */
final class DatasourceEntry extends Model
{
    use HasFactory;
    use HasUuid;

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * The table associated with the model.
     */
    protected $table = 'datasource_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'datasource_id',
        'external_id',
        'data',
        'position',
        'status',
        'metadata',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => Json::class,
        'position' => 'integer',
        'metadata' => Json::class,
        'published_at' => 'datetime',
        'datasource_id' => 'integer',
    ];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array<string>
     */
    protected $visible = [
        'id',
        'uuid',
        'external_id',
        'data',
        'position',
        'status',
        'metadata',
        'published_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The datasource this entry belongs to.
     */
    public function datasource(): BelongsTo
    {
        return $this->belongsTo(Datasource::class);
    }

    /**
     * Scope entries by datasource.
     */
    public function scopeByDatasource(\Illuminate\Database\Eloquent\Builder $query, int $datasourceId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('datasource_id', $datasourceId);
    }

    /**
     * Scope entries by external ID.
     */
    public function scopeByExternalId(\Illuminate\Database\Eloquent\Builder $query, string $externalId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('external_id', $externalId);
    }

    /**
     * Scope to published entries only.
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope to draft entries only.
     */
    public function scopeDraft(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to order entries by position.
     */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('position')->orderBy('created_at');
    }

    /**
     * Get a specific data field value.
     */
    public function getDataField(string $field, mixed $default = null): mixed
    {
        return data_get($this->data, $field, $default);
    }

    /**
     * Set a specific data field value.
     */
    public function setDataField(string $field, mixed $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $field, $value);
        $this->data = $data;
    }

    /**
     * Check if the entry is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if the entry is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Publish the entry.
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * Unpublish the entry (make it draft).
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * Archive the entry.
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => self::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Get the title/name of the entry.
     */
    public function getTitle(): string
    {
        // Try common title/name fields
        $titleFields = ['title', 'name', 'label', 'heading'];
        
        foreach ($titleFields as $field) {
            $value = $this->getDataField($field);
            if ($value && is_string($value)) {
                return $value;
            }
        }
        
        return "Entry #{$this->id}";
    }

    /**
     * Get a summary/excerpt of the entry.
     */
    public function getSummary(int $length = 150): string
    {
        // Try common content fields
        $contentFields = ['summary', 'excerpt', 'description', 'content', 'body'];
        
        foreach ($contentFields as $field) {
            $value = $this->getDataField($field);
            if ($value && is_string($value)) {
                return str($value)->limit($length);
            }
        }
        
        // If no content fields, try to create summary from other data
        $dataString = collect($this->data)
            ->filter(fn($value) => is_string($value))
            ->implode(' ');
            
        return str($dataString)->limit($length);
    }

    /**
     * Get validation rules for datasource entry creation/update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function rules(): array
    {
        return [
            'datasource_id' => ['required', 'integer', 'exists:datasources,id'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'array'],
            'position' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:' . self::STATUS_PUBLISHED . ',' . self::STATUS_DRAFT . ',' . self::STATUS_ARCHIVED],
            'metadata' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get validation rules for datasource entry creation.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function createRules(): array
    {
        return array_merge(self::rules(), [
            'datasource_id' => ['required', 'integer', 'exists:datasources,id'],
            'data' => ['required', 'array'],
            'status' => ['required', 'string', 'in:' . self::STATUS_PUBLISHED . ',' . self::STATUS_DRAFT . ',' . self::STATUS_ARCHIVED],
        ]);
    }

    /**
     * Get validation rules for datasource entry update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function updateRules(): array
    {
        $rules = self::rules();
        
        // Make datasource_id optional for updates (shouldn't change)
        $rules['datasource_id'] = ['nullable', 'integer', 'exists:datasources,id'];
        
        return $rules;
    }

    /**
     * Get validation rules for data field update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function dataUpdateRules(): array
    {
        return [
            'data' => ['required', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validation rules for status change.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function statusChangeRules(): array
    {
        return [
            'status' => ['required', 'string', 'in:' . self::STATUS_PUBLISHED . ',' . self::STATUS_DRAFT . ',' . self::STATUS_ARCHIVED],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Get validation rules for bulk operations.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function bulkRules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.datasource_id' => ['required', 'integer', 'exists:datasources,id'],
            'entries.*.external_id' => ['nullable', 'string', 'max:255'],
            'entries.*.data' => ['required', 'array'],
            'entries.*.position' => ['nullable', 'integer', 'min:0'],
            'entries.*.status' => ['required', 'string', 'in:' . self::STATUS_PUBLISHED . ',' . self::STATUS_DRAFT . ',' . self::STATUS_ARCHIVED],
            'entries.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validation rules for position reordering.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function reorderRules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.id' => ['required', 'integer', 'exists:datasource_entries,id'],
            'entries.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DatasourceEntry $entry) {
            if (!$entry->status) {
                $entry->status = self::STATUS_DRAFT;
            }
        });
    }
}
