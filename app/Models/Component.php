<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ComponentSchema;
use App\Casts\Json;
use App\Traits\Cacheable;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Component Model.
 *
 * Represents reusable content block definitions in the headless CMS.
 * Components define the structure and fields for content blocks (Storyblok-style).
 *
 * @property int $id
 * @property string $uuid
 * @property int $space_id
 * @property string $name
 * @property string $technical_name
 * @property string|null $description
 * @property string|null $display_name
 * @property array $schema
 * @property string|null $icon
 * @property string|null $color
 * @property array|null $preview_field
 * @property array|null $tabs
 * @property string $status
 * @property bool $is_nestable
 * @property bool $is_root
 * @property int $version
 * @property array|null $allowed_roles
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Component extends Model
{
    use HasFactory;
    use HasUuid;
    use MultiTenant;
    use Sluggable;
    use SoftDeletes;
    use Cacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'technical_name',
        'description',
        'display_name',
        'schema',
        'icon',
        'color',
        'preview_field',
        'tabs',
        'status',
        'is_nestable',
        'is_root',
        'version',
        'allowed_roles',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'schema' => ComponentSchema::class,
        'preview_field' => Json::class,
        'tabs' => Json::class,
        'is_nestable' => 'boolean',
        'is_root' => 'boolean',
        'allowed_roles' => Json::class,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'id',
        'space_id',
    ];

    /**
     * Sluggable configuration.
     */
    protected string $slugSourceField = 'technical_name';

    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (6 hours).
     */
    protected int $cacheTtl = 21600;

    /**
     * Available component statuses.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DEPRECATED = 'deprecated';

    /**
     * Available field types.
     */
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'markdown',
        'richtext',
        'number',
        'boolean',
        'date',
        'datetime',
        'select',
        'multiselect',
        'image',
        'file',
        'link',
        'email',
        'url',
        'color',
        'json',
        'table',
        'blocks',
        'asset',
        'story',
        'component',
    ];

    /**
     * Get the user who created this component.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this component.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to active components only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to inactive components.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to nestable components.
     */
    public function scopeNestable($query)
    {
        return $query->where('is_nestable', true);
    }

    /**
     * Scope to root components.
     */
    public function scopeRoot($query)
    {
        return $query->where('is_root', true);
    }

    /**
     * Scope components by version.
     */
    public function scopeVersion($query, int $version)
    {
        return $query->where('version', $version);
    }

    /**
     * Check if the component is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the component is nestable.
     */
    public function isNestable(): bool
    {
        return $this->is_nestable;
    }

    /**
     * Check if the component can be used as root.
     */
    public function isRoot(): bool
    {
        return $this->is_root;
    }

    /**
     * Check if the component is deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->status === self::STATUS_DEPRECATED;
    }

    /**
     * Get field by key from schema.
     */
    public function getField(string $key): ?array
    {
        foreach ($this->schema ?? [] as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get all fields of a specific type.
     */
    public function getFieldsByType(string $type): array
    {
        $fields = [];

        foreach ($this->schema ?? [] as $field) {
            if ($field['type'] === $type) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Get required fields.
     */
    public function getRequiredFields(): array
    {
        $fields = [];

        foreach ($this->schema ?? [] as $field) {
            if ($field['required'] ?? false) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Validate component data against schema.
     */
    public function validateData(array $data): array
    {
        $errors = [];

        foreach ($this->schema ?? [] as $field) {
            $key = $field['key'];
            $value = $data[$key] ?? null;

            // Check required fields
            if (($field['required'] ?? false) && ($value === null || $value === '')) {
                $errors[$key] = "Field '{$key}' is required";

                continue;
            }

            // Skip validation if field is not present and not required
            if ($value === null) {
                continue;
            }

            // Type-specific validation
            $validationError = $this->validateFieldValue($field, $value);
            if ($validationError) {
                $errors[$key] = $validationError;
            }
        }

        return $errors;
    }

    /**
     * Validate a single field value.
     */
    protected function validateFieldValue(array $field, mixed $value): ?string
    {
        $type = $field['type'];

        return match ($type) {
            'text', 'textarea' => $this->validateString($value, $field),
            'number' => $this->validateNumber($value, $field),
            'boolean' => $this->validateBoolean($value),
            'email' => $this->validateEmail($value),
            'url' => $this->validateUrl($value),
            'date', 'datetime' => $this->validateDate($value),
            'select' => $this->validateSelect($value, $field),
            'multiselect' => $this->validateMultiselect($value, $field),
            'json' => $this->validateJson($value),
            default => null,
        };
    }

    /**
     * Validate string field.
     */
    protected function validateString(mixed $value, array $field): ?string
    {
        if (! \is_string($value)) {
            return 'Value must be a string';
        }

        if (isset($field['min_length']) && \strlen($value) < $field['min_length']) {
            return "Value must be at least {$field['min_length']} characters";
        }

        if (isset($field['max_length']) && \strlen($value) > $field['max_length']) {
            return "Value must not exceed {$field['max_length']} characters";
        }

        return null;
    }

    /**
     * Validate number field.
     */
    protected function validateNumber(mixed $value, array $field): ?string
    {
        if (! is_numeric($value)) {
            return 'Value must be a number';
        }

        $number = (float) $value;

        if (isset($field['min']) && $number < $field['min']) {
            return "Value must be at least {$field['min']}";
        }

        if (isset($field['max']) && $number > $field['max']) {
            return "Value must not exceed {$field['max']}";
        }

        return null;
    }

    /**
     * Validate boolean field.
     */
    protected function validateBoolean(mixed $value): ?string
    {
        if (! \is_bool($value) && ! \in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
            return 'Value must be a boolean';
        }

        return null;
    }

    /**
     * Validate email field.
     */
    protected function validateEmail(mixed $value): ?string
    {
        if (! \is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Value must be a valid email address';
        }

        return null;
    }

    /**
     * Validate URL field.
     */
    protected function validateUrl(mixed $value): ?string
    {
        if (! \is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return 'Value must be a valid URL';
        }

        return null;
    }

    /**
     * Validate date field.
     */
    protected function validateDate(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return 'Date must be a string';
        }

        try {
            new \DateTime($value);
        } catch (\Exception $e) {
            return 'Value must be a valid date';
        }

        return null;
    }

    /**
     * Validate select field.
     */
    protected function validateSelect(mixed $value, array $field): ?string
    {
        if (! isset($field['options']) || ! \is_array($field['options'])) {
            return null;
        }

        $validOptions = array_column($field['options'], 'value');

        if (! \in_array($value, $validOptions)) {
            return 'Value must be one of the allowed options';
        }

        return null;
    }

    /**
     * Validate multiselect field.
     */
    protected function validateMultiselect(mixed $value, array $field): ?string
    {
        if (! \is_array($value)) {
            return 'Value must be an array';
        }

        if (! isset($field['options']) || ! \is_array($field['options'])) {
            return null;
        }

        $validOptions = array_column($field['options'], 'value');

        foreach ($value as $item) {
            if (! \in_array($item, $validOptions)) {
                return 'All values must be from the allowed options';
            }
        }

        return null;
    }

    /**
     * Validate JSON field.
     */
    protected function validateJson(mixed $value): ?string
    {
        if (\is_array($value) || \is_object($value)) {
            return null; // Already decoded
        }

        if (! \is_string($value)) {
            return 'JSON value must be a string or array';
        }

        json_decode($value);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Value must be valid JSON';
        }

        return null;
    }

    /**
     * Get component preview based on preview field configuration.
     */
    public function getPreview(array $data): string
    {
        if (! $this->preview_field) {
            return $this->name;
        }

        $template = $this->preview_field['template'] ?? '{name}';
        $fields = $this->preview_field['fields'] ?? ['name'];

        // Simple template replacement
        foreach ($fields as $field) {
            $value = $data[$field] ?? '';
            $template = str_replace("{{$field}}", (string) $value, $template);
        }

        return $template ?: $this->name;
    }

    /**
     * Increment version.
     */
    public function incrementVersion(): bool
    {
        return $this->update(['version' => $this->version + 1]);
    }

    /**
     * Deprecate the component.
     */
    public function deprecate(): bool
    {
        return $this->update(['status' => self::STATUS_DEPRECATED]);
    }

    /**
     * Check if user can use this component.
     */
    public function canBeUsedBy(?User $user = null): bool
    {
        // Public components (no role restrictions)
        if (empty($this->allowed_roles)) {
            return true;
        }

        // Require authentication
        if (! $user) {
            return false;
        }

        // Check user role in space
        $userRole = $user->getRoleInSpace($this->space_id);

        if (! $userRole) {
            return false;
        }

        return \in_array($userRole->slug, $this->allowed_roles);
    }

    /**
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('schema');
        $this->forgetCache('fields');
        $this->forgetCache('validation_rules');
    }
}
