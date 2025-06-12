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
 * @psalm-suppress PossiblyUnusedMethod
 *
 * @property int $id
 * @property string $uuid
 * @property int $space_id
 * @property string $name
 * @property string $technical_name
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $display_name
 * @property array<string, mixed> $schema
 * @property string|null $icon
 * @property string|null $color
 * @property array<string, mixed>|null $preview_field
 * @property array<string, mixed>|null $tabs
 * @property string $status
 * @property bool $is_nestable
 * @property bool $is_root
 * @property int $version
 * @property array<string>|null $allowed_roles
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * 
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
final class Component extends Model
{
    /** @use HasFactory<\Database\Factories\ComponentFactory> */
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
        'name',
        'technical_name',
        'slug',
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
     * @var array<array-key, mixed>
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
     * Sluggable configuration.
     */
    protected string $slugSourceField = 'technical_name';
    protected bool $autoUpdateSlug = false;

    /**
     * Cache TTL in seconds (6 hours).
     */
    protected int $cacheTtl = 21600;

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
     * Available component statuses.
     */
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_INACTIVE = 'inactive';
    public const string STATUS_DEPRECATED = 'deprecated';

    /**
     * Available field types.
     */
    /** @var array<int, string> */
    public const array FIELD_TYPES = [
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
     * Validate string field.
     *
     * @param array<string, mixed> $field
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @param array<string, mixed> $field
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @param array<string, mixed> $field
     * @psalm-suppress PossiblyUnusedMethod
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
     *
     * @param array<string, mixed> $field
     * @psalm-suppress PossiblyUnusedMethod
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

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $item) {
            if (! \in_array($item, $validOptions)) {
                return 'All values must be from the allowed options';
            }
        }

        return null;
    }

    /**
     * Validate JSON field.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function validateJson(mixed $value): ?string
    {
        if (\is_array($value) || \is_object($value)) {
            return null; // Already decoded
        }

        if (! \is_string($value)) {
            return 'JSON value must be a string or array';
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value);
        unset($decoded); // Variable only used for validation

        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Value must be valid JSON';
        }

        return null;
    }

    /**
     * Validate data against component schema.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Validation errors (field_name => error_message)
     */
    public function validateData(array $data): array
    {
        $errors = [];
        
        if (!$this->schema || !is_array($this->schema)) {
            return $errors;
        }

        foreach ($this->schema as $fieldName => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                continue;
            }

            $value = $data[$fieldName] ?? null;
            $fieldType = $fieldConfig['type'] ?? 'text';
            $isRequired = $fieldConfig['required'] ?? false;

            // Check required fields
            if ($isRequired && ($value === null || $value === '')) {
                $errors[$fieldName] = "Field '{$fieldName}' is required";
                continue;
            }

            // Skip validation if field is not required and empty
            if (!$isRequired && ($value === null || $value === '')) {
                continue;
            }

            // Validate based on field type
            $error = $this->validateFieldByType($value, $fieldType, $fieldConfig);
            if ($error) {
                $errors[$fieldName] = $error;
            }
        }

        return $errors;
    }

    /**
     * Validate field by its type.
     *
     * @param mixed $value
     * @param string $fieldType
     * @param array<string, mixed> $fieldConfig
     */
    private function validateFieldByType(mixed $value, string $fieldType, array $fieldConfig): ?string
    {
        return match ($fieldType) {
            'text', 'textarea', 'markdown', 'richtext' => $this->validateString($value, $fieldConfig),
            'number' => $this->validateNumber($value, $fieldConfig),
            'boolean' => $this->validateBoolean($value),
            'email' => $this->validateEmail($value),
            'url' => $this->validateUrl($value),
            'date', 'datetime' => $this->validateDate($value),
            'select' => $this->validateSelect($value, $fieldConfig),
            'multiselect' => $this->validateMultiselect($value, $fieldConfig),
            'json' => $this->validateJson($value),
            'blocks' => $this->validateBlocks($value, $fieldConfig),
            'asset' => $this->validateAsset($value),
            'story' => $this->validateStory($value),
            'component' => $this->validateComponent($value),
            default => null,
        };
    }

    /**
     * Validate blocks field (nested components).
     *
     * @param mixed $value
     * @param array<string, mixed> $fieldConfig
     */
    private function validateBlocks(mixed $value, array $fieldConfig): ?string
    {
        if (!is_array($value)) {
            return 'Blocks must be an array';
        }

        $allowedComponents = $fieldConfig['component_whitelist'] ?? [];
        
        foreach ($value as $index => $block) {
            if (!is_array($block)) {
                return "Block at index {$index} must be an object";
            }

            $componentName = $block['component'] ?? null;
            if (!$componentName) {
                return "Block at index {$index} must have a component type";
            }

            // Check if component is allowed
            if (!empty($allowedComponents) && !in_array($componentName, $allowedComponents)) {
                return "Component '{$componentName}' is not allowed in this blocks field";
            }

            // Validate nested component data
            $component = Component::where('technical_name', $componentName)
                ->where('space_id', $this->space_id)
                ->first();

            if (!$component) {
                return "Component '{$componentName}' not found";
            }

            $blockData = $block;
            unset($blockData['component'], $blockData['_uid']);
            
            $blockErrors = $component->validateData($blockData);
            if (!empty($blockErrors)) {
                $errorMessages = implode(', ', $blockErrors);
                return "Block at index {$index} has validation errors: {$errorMessages}";
            }
        }

        return null;
    }

    /**
     * Validate asset field.
     *
     * @param mixed $value
     */
    private function validateAsset(mixed $value): ?string
    {
        if (!is_array($value) && !is_string($value) && !is_numeric($value)) {
            return 'Asset must be an ID, UUID, or asset object';
        }

        // If it's an asset object, validate it has required fields
        if (is_array($value)) {
            if (!isset($value['id']) && !isset($value['uuid'])) {
                return 'Asset object must have id or uuid field';
            }
            return null;
        }

        // For ID/UUID validation, we could check if asset exists but that might be expensive
        // The StoryService should handle asset existence validation
        return null;
    }

    /**
     * Validate story field.
     *
     * @param mixed $value
     */
    private function validateStory(mixed $value): ?string
    {
        if (!is_array($value) && !is_string($value) && !is_numeric($value)) {
            return 'Story must be an ID, UUID, or story object';
        }

        // If it's a story object, validate it has required fields
        if (is_array($value)) {
            if (!isset($value['id']) && !isset($value['uuid'])) {
                return 'Story object must have id or uuid field';
            }
            return null;
        }

        return null;
    }

    /**
     * Validate component field.
     *
     * @param mixed $value
     */
    private function validateComponent(mixed $value): ?string
    {
        if (!is_array($value)) {
            return 'Component must be an object';
        }

        $componentName = $value['component'] ?? null;
        if (!$componentName) {
            return 'Component must have a component type';
        }

        // Validate the component exists
        $component = Component::where('technical_name', $componentName)
            ->where('space_id', $this->space_id)
            ->first();

        if (!$component) {
            return "Component '{$componentName}' not found";
        }

        // Validate component data
        $componentData = $value;
        unset($componentData['component'], $componentData['_uid']);
        
        $errors = $component->validateData($componentData);
        if (!empty($errors)) {
            $errorMessages = implode(', ', $errors);
            return "Component validation errors: {$errorMessages}";
        }

        return null;
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
