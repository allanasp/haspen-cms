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
    protected array $fillable = [
        'name',
        'technical_name',
        'slug',
        'parent_component_id',
        'variant_group',
        'variant_name',
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
    protected array $casts = [
        'schema' => ComponentSchema::class,
        'preview_field' => Json::class,
        'tabs' => Json::class,
        'is_nestable' => 'boolean',
        'is_root' => 'boolean',
        'allow_inheritance' => 'boolean',
        'allowed_roles' => Json::class,
        'inherited_fields' => Json::class,
        'override_fields' => Json::class,
        'variant_config' => Json::class,
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
    protected array $hidden = [
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

            // Check conditional field display
            if (!$this->shouldDisplayField($fieldName, $fieldConfig, $data)) {
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
     * Check if a field should be displayed based on conditional rules.
     */
    protected function shouldDisplayField(string $fieldName, array $fieldConfig, array $data): bool
    {
        // If no conditions are defined, always display the field
        if (!isset($fieldConfig['conditions']) || !is_array($fieldConfig['conditions'])) {
            return true;
        }

        foreach ($fieldConfig['conditions'] as $condition) {
            if (!$this->evaluateCondition($condition, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        $fieldValue = $data[$field] ?? null;

        return match($operator) {
            'equals', '==' => $fieldValue == $value,
            'not_equals', '!=' => $fieldValue != $value,
            'contains' => is_string($fieldValue) && str_contains($fieldValue, $value),
            'not_contains' => is_string($fieldValue) && !str_contains($fieldValue, $value),
            'in' => is_array($value) && in_array($fieldValue, $value),
            'not_in' => is_array($value) && !in_array($fieldValue, $value),
            'greater_than', '>' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue > $value,
            'less_than', '<' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue < $value,
            'greater_equal', '>=' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue >= $value,
            'less_equal', '<=' => is_numeric($fieldValue) && is_numeric($value) && $fieldValue <= $value,
            'empty' => empty($fieldValue),
            'not_empty' => !empty($fieldValue),
            'is_true' => $fieldValue === true || $fieldValue === 'true' || $fieldValue === 1 || $fieldValue === '1',
            'is_false' => $fieldValue === false || $fieldValue === 'false' || $fieldValue === 0 || $fieldValue === '0',
            default => true
        };
    }

    /**
     * Get visible fields based on current data and conditions.
     */
    public function getVisibleFields(array $data = []): array
    {
        if (!$this->schema || !is_array($this->schema)) {
            return [];
        }

        $visibleFields = [];

        foreach ($this->schema as $fieldName => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                continue;
            }

            if ($this->shouldDisplayField($fieldName, $fieldConfig, $data)) {
                $visibleFields[$fieldName] = $fieldConfig;
            }
        }

        return $visibleFields;
    }

    /**
     * Get component usage count across all stories in the same space.
     */
    public function getUsageCount(): int
    {
        return $this->cache('usage_count', function () {
            return Story::where('space_id', $this->space_id)
                ->whereJsonContains('content->body', ['component' => $this->technical_name])
                ->count();
        }, 300); // Cache for 5 minutes
    }

    /**
     * Get stories that use this component.
     */
    public function getUsedInStories()
    {
        return Story::where('space_id', $this->space_id)
            ->whereJsonContains('content->body', ['component' => $this->technical_name])
            ->select('uuid', 'name', 'slug', 'status', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Check if component is being used in any stories.
     */
    public function isInUse(): bool
    {
        return $this->getUsageCount() > 0;
    }

    /**
     * Get detailed usage statistics for this component.
     */
    public function getUsageStatistics(): array
    {
        $stories = Story::where('space_id', $this->space_id)
            ->whereJsonContains('content->body', ['component' => $this->technical_name])
            ->get(['status', 'content']);

        $stats = [
            'total_usage' => 0,
            'by_status' => [
                'draft' => 0,
                'in_review' => 0,
                'published' => 0,
                'scheduled' => 0,
                'archived' => 0,
            ],
            'usage_depth' => [], // Track how deep the component is nested
        ];

        foreach ($stories as $story) {
            $usageCount = $this->countComponentUsageInContent($story->content, $this->technical_name);
            $stats['total_usage'] += $usageCount;
            $stats['by_status'][$story->status] += $usageCount;
        }

        return $stats;
    }

    /**
     * Count how many times a component is used in content (including nested).
     */
    private function countComponentUsageInContent(array $content, string $componentName, int $depth = 0): int
    {
        $count = 0;
        
        if (!isset($content['body']) || !is_array($content['body'])) {
            return $count;
        }

        foreach ($content['body'] as $block) {
            if (isset($block['component']) && $block['component'] === $componentName) {
                $count++;
            }

            // Check nested content
            foreach ($block as $key => $value) {
                if (is_array($value) && isset($value['body']) && is_array($value['body'])) {
                    $count += $this->countComponentUsageInContent($value, $componentName, $depth + 1);
                }
            }
        }

        return $count;
    }

    /**
     * Get components that are frequently used together with this component.
     */
    public function getRelatedComponents(int $limit = 5): array
    {
        $stories = Story::where('space_id', $this->space_id)
            ->whereJsonContains('content->body', ['component' => $this->technical_name])
            ->get(['content']);

        $componentCounts = [];

        foreach ($stories as $story) {
            $usedComponents = $this->extractComponentsFromContent($story->content);
            foreach ($usedComponents as $componentName) {
                if ($componentName !== $this->technical_name) {
                    $componentCounts[$componentName] = ($componentCounts[$componentName] ?? 0) + 1;
                }
            }
        }

        arsort($componentCounts);
        return array_slice($componentCounts, 0, $limit, true);
    }

    /**
     * Extract all component names from content structure.
     */
    private function extractComponentsFromContent(array $content): array
    {
        $components = [];
        
        if (!isset($content['body']) || !is_array($content['body'])) {
            return $components;
        }

        foreach ($content['body'] as $block) {
            if (isset($block['component'])) {
                $components[] = $block['component'];
            }

            // Check nested content
            foreach ($block as $value) {
                if (is_array($value) && isset($value['body'])) {
                    $components = array_merge($components, $this->extractComponentsFromContent($value));
                }
            }
        }

        return array_unique($components);
    }

    /**
     * Parent component relationship (for inheritance).
     */
    public function parentComponent()
    {
        return $this->belongsTo(Component::class, 'parent_component_id');
    }

    /**
     * Child components relationship (inheritance children).
     */
    public function childComponents()
    {
        return $this->hasMany(Component::class, 'parent_component_id');
    }

    /**
     * Variant siblings (components in the same variant group).
     */
    public function variantSiblings()
    {
        return $this->where('variant_group', $this->variant_group)
                   ->where('space_id', $this->space_id)
                   ->where('id', '!=', $this->id);
    }

    /**
     * Get the complete schema including inherited fields.
     */
    public function getCompleteSchema(): array
    {
        return $this->cache('complete_schema', function () {
            $schema = $this->schema ?? [];
            
            if ($this->parent_component_id && $this->parentComponent) {
                $parentSchema = $this->parentComponent->getCompleteSchema();
                $overrides = $this->override_fields ?? [];
                
                // Merge parent schema with overrides
                foreach ($parentSchema as $fieldName => $fieldConfig) {
                    if (!isset($schema[$fieldName])) {
                        $schema[$fieldName] = $fieldConfig;
                    }
                }
                
                // Apply overrides
                foreach ($overrides as $fieldName => $override) {
                    if (isset($schema[$fieldName])) {
                        $schema[$fieldName] = array_merge($schema[$fieldName], $override);
                    }
                }
            }
            
            return $schema;
        }, 600); // Cache for 10 minutes
    }

    /**
     * Create a child component that inherits from this component.
     */
    public function createChild(array $childData): Component
    {
        if (!$this->allow_inheritance) {
            throw new \InvalidArgumentException('This component does not allow inheritance');
        }

        $child = new Component();
        $child->space_id = $this->space_id;
        $child->parent_component_id = $this->id;
        $child->name = $childData['name'];
        $child->technical_name = $childData['technical_name'];
        $child->description = $childData['description'] ?? "Extends {$this->name}";
        $child->type = $this->type;
        $child->is_nestable = $this->is_nestable;
        $child->is_root = $this->is_root;
        $child->allow_inheritance = $childData['allow_inheritance'] ?? true;
        
        // Set inherited fields
        $child->inherited_fields = $this->getCompleteSchema();
        
        // Apply child-specific schema and overrides
        $child->schema = $childData['schema'] ?? [];
        $child->override_fields = $childData['override_fields'] ?? [];
        
        // Copy visual properties unless overridden
        $child->icon = $childData['icon'] ?? $this->icon;
        $child->color = $childData['color'] ?? $this->color;
        $child->tabs = $childData['tabs'] ?? $this->tabs;
        
        $child->created_by = auth()->id();
        $child->save();

        return $child;
    }

    /**
     * Create a variant of this component.
     */
    public function createVariant(array $variantData): Component
    {
        $variant = new Component();
        $variant->space_id = $this->space_id;
        $variant->variant_group = $this->variant_group ?: $this->technical_name;
        $variant->variant_name = $variantData['variant_name'];
        $variant->name = $variantData['name'] ?? "{$this->name} ({$variantData['variant_name']})";
        $variant->technical_name = $variantData['technical_name'] ?? "{$this->technical_name}_{$variantData['variant_name']}";
        $variant->description = $variantData['description'] ?? "Variant of {$this->name}";
        
        // Copy base properties
        $variant->type = $this->type;
        $variant->schema = $this->schema;
        $variant->is_nestable = $this->is_nestable;
        $variant->is_root = $this->is_root;
        $variant->allow_inheritance = $this->allow_inheritance;
        $variant->icon = $this->icon;
        $variant->color = $this->color;
        $variant->tabs = $this->tabs;
        
        // Apply variant configuration
        $variant->variant_config = $variantData['variant_config'] ?? [];
        
        // Apply schema overrides
        if (isset($variantData['schema_overrides'])) {
            $schema = $variant->schema;
            foreach ($variantData['schema_overrides'] as $fieldName => $override) {
                if (isset($schema[$fieldName])) {
                    $schema[$fieldName] = array_merge($schema[$fieldName], $override);
                } else {
                    $schema[$fieldName] = $override;
                }
            }
            $variant->schema = $schema;
        }
        
        $variant->created_by = auth()->id();
        $variant->save();

        // Update original component's variant group if not set
        if (!$this->variant_group) {
            $this->variant_group = $this->technical_name;
            $this->variant_name = 'default';
            $this->save();
        }

        return $variant;
    }

    /**
     * Check if this component inherits from another.
     */
    public function inheritsFrom(Component $component): bool
    {
        if ($this->parent_component_id === $component->id) {
            return true;
        }
        
        if ($this->parentComponent) {
            return $this->parentComponent->inheritsFrom($component);
        }
        
        return false;
    }

    /**
     * Get all ancestor components.
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parentComponent;
        
        while ($current) {
            $ancestors->push($current);
            $current = $current->parentComponent;
        }
        
        return $ancestors;
    }

    /**
     * Get all descendant components.
     */
    public function getDescendants(): Collection
    {
        $descendants = collect();
        
        foreach ($this->childComponents as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        
        return $descendants;
    }

    /**
     * Check if this component is a variant.
     */
    public function isVariant(): bool
    {
        return !empty($this->variant_group) && !empty($this->variant_name);
    }

    /**
     * Get the base component for this variant group.
     */
    public function getBaseVariant(): ?Component
    {
        if (!$this->variant_group) {
            return null;
        }
        
        return Component::where('space_id', $this->space_id)
            ->where('variant_group', $this->variant_group)
            ->where('variant_name', 'default')
            ->first();
    }

    /**
     * Get all variants in the same group.
     */
    public function getAllVariants()
    {
        if (!$this->variant_group) {
            return collect([$this]);
        }
        
        return Component::where('space_id', $this->space_id)
            ->where('variant_group', $this->variant_group)
            ->orderBy('variant_name')
            ->get();
    }

    /**
     * Override validateData to use complete schema.
     */
    public function validateData(array $data): array
    {
        $errors = [];
        $schema = $this->getCompleteSchema();
        
        if (!$schema || !is_array($schema)) {
            return $errors;
        }

        foreach ($schema as $fieldName => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                continue;
            }

            // Check conditional field display
            if (!$this->shouldDisplayField($fieldName, $fieldConfig, $data)) {
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
     * Clear model-specific cache.
     */
    protected function clearModelSpecificCache(): void
    {
        $this->forgetCache('schema');
        $this->forgetCache('fields');
        $this->forgetCache('validation_rules');
        $this->forgetCache('usage_count');
        $this->forgetCache('complete_schema');
    }
}
