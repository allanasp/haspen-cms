<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Component;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Component Schema Validator Service.
 * Validates component schemas and data against schemas.
 */
class ComponentSchemaValidator
{
    /**
     * Supported field types and their validation rules.
     */
    private const FIELD_TYPES = [
        'text', 'textarea', 'richtext', 'markdown',
        'number', 'boolean', 'asset', 'blocks',
        'select', 'multiselect', 'email', 'url',
        'date', 'datetime', 'link', 'color',
        'json', 'table', 'story', 'component'
    ];

    /**
     * Validate a component schema definition.
     */
    public function validateSchema(array $schema): array
    {
        $errors = [];

        if (empty($schema)) {
            return ['Schema cannot be empty'];
        }

        foreach ($schema as $fieldName => $fieldConfig) {
            $fieldErrors = $this->validateFieldSchema($fieldName, $fieldConfig);
            if (!empty($fieldErrors)) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate a single field schema definition.
     */
    public function validateFieldSchema(string $fieldName, mixed $fieldConfig): array
    {
        $errors = [];

        // Field config must be an array
        if (!is_array($fieldConfig)) {
            return ['Field configuration must be an array'];
        }

        // Validate required properties
        if (!isset($fieldConfig['type'])) {
            $errors[] = 'Field type is required';
        } elseif (!in_array($fieldConfig['type'], self::FIELD_TYPES)) {
            $errors[] = "Invalid field type '{$fieldConfig['type']}'";
        }

        // Validate field-specific properties
        $fieldType = $fieldConfig['type'] ?? null;
        if ($fieldType) {
            $typeErrors = $this->validateFieldTypeSpecificProperties($fieldType, $fieldConfig);
            $errors = array_merge($errors, $typeErrors);
        }

        // Validate common properties
        $commonErrors = $this->validateCommonProperties($fieldConfig);
        $errors = array_merge($errors, $commonErrors);

        // Validate conditional logic
        if (isset($fieldConfig['conditions'])) {
            $conditionErrors = $this->validateConditions($fieldConfig['conditions']);
            $errors = array_merge($errors, $conditionErrors);
        }

        return $errors;
    }

    /**
     * Validate field type specific properties.
     */
    private function validateFieldTypeSpecificProperties(string $fieldType, array $fieldConfig): array
    {
        $errors = [];

        switch ($fieldType) {
            case 'text':
            case 'textarea':
            case 'richtext':
            case 'markdown':
                if (isset($fieldConfig['min_length']) && !is_numeric($fieldConfig['min_length'])) {
                    $errors[] = 'min_length must be numeric';
                }
                if (isset($fieldConfig['max_length']) && !is_numeric($fieldConfig['max_length'])) {
                    $errors[] = 'max_length must be numeric';
                }
                if (isset($fieldConfig['regex']) && !$this->isValidRegex($fieldConfig['regex'])) {
                    $errors[] = 'regex pattern is invalid';
                }
                break;

            case 'number':
                if (isset($fieldConfig['min']) && !is_numeric($fieldConfig['min'])) {
                    $errors[] = 'min must be numeric';
                }
                if (isset($fieldConfig['max']) && !is_numeric($fieldConfig['max'])) {
                    $errors[] = 'max must be numeric';
                }
                if (isset($fieldConfig['step']) && !is_numeric($fieldConfig['step'])) {
                    $errors[] = 'step must be numeric';
                }
                break;

            case 'select':
            case 'multiselect':
                if (!isset($fieldConfig['options']) || !is_array($fieldConfig['options'])) {
                    $errors[] = 'options array is required for select fields';
                } elseif (empty($fieldConfig['options'])) {
                    $errors[] = 'options array cannot be empty';
                } else {
                    foreach ($fieldConfig['options'] as $option) {
                        if (!is_array($option) || !isset($option['name']) || !isset($option['value'])) {
                            $errors[] = 'Each option must have name and value properties';
                            break;
                        }
                    }
                }
                break;

            case 'asset':
                if (isset($fieldConfig['asset_folder']) && !is_string($fieldConfig['asset_folder'])) {
                    $errors[] = 'asset_folder must be a string';
                }
                if (isset($fieldConfig['filetypes']) && !is_array($fieldConfig['filetypes'])) {
                    $errors[] = 'filetypes must be an array';
                }
                break;

            case 'blocks':
                if (isset($fieldConfig['restrict_type']) && !is_string($fieldConfig['restrict_type'])) {
                    $errors[] = 'restrict_type must be a string';
                }
                if (isset($fieldConfig['component_whitelist']) && !is_array($fieldConfig['component_whitelist'])) {
                    $errors[] = 'component_whitelist must be an array';
                }
                if (isset($fieldConfig['maximum']) && !is_numeric($fieldConfig['maximum'])) {
                    $errors[] = 'maximum must be numeric';
                }
                break;

            case 'table':
                if (!isset($fieldConfig['columns']) || !is_array($fieldConfig['columns'])) {
                    $errors[] = 'columns array is required for table fields';
                }
                break;
        }

        return $errors;
    }

    /**
     * Validate common field properties.
     */
    private function validateCommonProperties(array $fieldConfig): array
    {
        $errors = [];

        // Validate required property
        if (isset($fieldConfig['required']) && !is_bool($fieldConfig['required'])) {
            $errors[] = 'required must be a boolean';
        }

        // Validate translatable property
        if (isset($fieldConfig['translatable']) && !is_bool($fieldConfig['translatable'])) {
            $errors[] = 'translatable must be a boolean';
        }

        // Validate description
        if (isset($fieldConfig['description']) && !is_string($fieldConfig['description'])) {
            $errors[] = 'description must be a string';
        }

        // Validate default_value
        if (isset($fieldConfig['default_value'])) {
            // Default value validation depends on field type - could be expanded
        }

        return $errors;
    }

    /**
     * Validate conditional display rules.
     */
    private function validateConditions(mixed $conditions): array
    {
        $errors = [];

        if (!is_array($conditions)) {
            return ['conditions must be an array'];
        }

        foreach ($conditions as $index => $condition) {
            if (!is_array($condition)) {
                $errors[] = "Condition {$index} must be an array";
                continue;
            }

            if (!isset($condition['field'])) {
                $errors[] = "Condition {$index} must have a field property";
            }

            if (!isset($condition['operator'])) {
                $errors[] = "Condition {$index} must have an operator property";
            } else {
                $validOperators = [
                    'equals', '==', 'not_equals', '!=',
                    'contains', 'not_contains', 'in', 'not_in',
                    'greater_than', '>', 'less_than', '<',
                    'greater_equal', '>=', 'less_equal', '<=',
                    'empty', 'not_empty', 'is_true', 'is_false'
                ];

                if (!in_array($condition['operator'], $validOperators)) {
                    $errors[] = "Condition {$index} has invalid operator '{$condition['operator']}'";
                }
            }

            // Value is optional for some operators (empty, not_empty, is_true, is_false)
            $operatorsWithoutValue = ['empty', 'not_empty', 'is_true', 'is_false'];
            if (!in_array($condition['operator'] ?? '', $operatorsWithoutValue) && !isset($condition['value'])) {
                $errors[] = "Condition {$index} must have a value property";
            }
        }

        return $errors;
    }

    /**
     * Validate component data against its schema.
     */
    public function validateData(Component $component, array $data): array
    {
        return $component->validateData($data);
    }

    /**
     * Validate data against a specific schema.
     */
    public function validateDataAgainstSchema(array $schema, array $data): array
    {
        $errors = [];

        // Get visible fields based on conditional logic
        $visibleFields = $this->getVisibleFields($schema, $data);

        foreach ($visibleFields as $fieldName => $fieldConfig) {
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
            $error = $this->validateFieldValue($value, $fieldType, $fieldConfig);
            if ($error) {
                $errors[$fieldName] = $error;
            }
        }

        return $errors;
    }

    /**
     * Get visible fields based on conditional logic.
     */
    private function getVisibleFields(array $schema, array $data): array
    {
        $visibleFields = [];

        foreach ($schema as $fieldName => $fieldConfig) {
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
     * Check if a field should be displayed based on conditional rules.
     */
    private function shouldDisplayField(string $fieldName, array $fieldConfig, array $data): bool
    {
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
    private function evaluateCondition(array $condition, array $data): bool
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
     * Validate a field value based on its type and configuration.
     */
    private function validateFieldValue(mixed $value, string $fieldType, array $fieldConfig): ?string
    {
        // This would mirror the validation logic from Component model
        // For brevity, implementing key validations
        
        switch ($fieldType) {
            case 'text':
            case 'textarea':
            case 'richtext':
            case 'markdown':
                if (!is_string($value)) {
                    return 'Value must be a string';
                }
                
                if (isset($fieldConfig['min_length']) && strlen($value) < $fieldConfig['min_length']) {
                    return "Value must be at least {$fieldConfig['min_length']} characters";
                }
                
                if (isset($fieldConfig['max_length']) && strlen($value) > $fieldConfig['max_length']) {
                    return "Value must not exceed {$fieldConfig['max_length']} characters";
                }
                
                break;

            case 'number':
                if (!is_numeric($value)) {
                    return 'Value must be numeric';
                }
                
                $numValue = (float) $value;
                if (isset($fieldConfig['min']) && $numValue < $fieldConfig['min']) {
                    return "Value must be at least {$fieldConfig['min']}";
                }
                
                if (isset($fieldConfig['max']) && $numValue > $fieldConfig['max']) {
                    return "Value must not exceed {$fieldConfig['max']}";
                }
                
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'])) {
                    return 'Value must be boolean';
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Value must be a valid email address';
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return 'Value must be a valid URL';
                }
                break;
        }

        return null;
    }

    /**
     * Check if a regex pattern is valid.
     */
    private function isValidRegex(string $pattern): bool
    {
        return @preg_match($pattern, '') !== false;
    }

    /**
     * Get supported field types.
     */
    public function getSupportedFieldTypes(): array
    {
        return self::FIELD_TYPES;
    }

    /**
     * Get field type definition with validation rules.
     */
    public function getFieldTypeDefinition(string $fieldType): ?array
    {
        $definitions = [
            'text' => [
                'properties' => ['min_length', 'max_length', 'regex', 'placeholder'],
                'description' => 'Single line text input'
            ],
            'textarea' => [
                'properties' => ['min_length', 'max_length', 'rows', 'placeholder'],
                'description' => 'Multi-line text input'
            ],
            'richtext' => [
                'properties' => ['toolbar_items', 'allow_target_blank'],
                'description' => 'WYSIWYG rich text editor'
            ],
            'number' => [
                'properties' => ['min', 'max', 'step', 'decimals'],
                'description' => 'Numeric input with validation'
            ],
            'boolean' => [
                'properties' => ['default_value'],
                'description' => 'Checkbox or toggle'
            ],
            'select' => [
                'properties' => ['options', 'allow_empty'],
                'description' => 'Dropdown selection'
            ],
            'asset' => [
                'properties' => ['asset_folder', 'filetypes'],
                'description' => 'File or image picker'
            ],
            'blocks' => [
                'properties' => ['restrict_type', 'component_whitelist', 'maximum'],
                'description' => 'Nested component blocks'
            ]
        ];

        return $definitions[$fieldType] ?? null;
    }
}