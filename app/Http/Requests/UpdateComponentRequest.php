<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update component form request validation.
 */
class UpdateComponentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'component' => 'required|array',
            'component.name' => 'sometimes|string|max:255',
            'component.schema' => 'sometimes|array|min:1',
            'component.is_root' => 'sometimes|boolean',
            'component.is_nestable' => 'sometimes|boolean',
            'component.preview_field' => 'sometimes|nullable|string',
            'component.preview_tmpl' => 'sometimes|nullable|string',
            'component.icon' => 'sometimes|nullable|string|max:50',
            'component.color' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'component.tabs' => 'sometimes|nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'component.required' => 'Component data is required',
            'component.name.max' => 'Component name cannot exceed 255 characters',
            'component.schema.min' => 'Schema must contain at least one field',
            'component.color.regex' => 'Color must be a valid hex color code (e.g., #3b82f6)'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate schema structure if provided
            if ($this->has('component.schema')) {
                $this->validateComponentSchema($validator, $this->input('component.schema'));
            }

            // Validate preview field exists in schema if both are provided
            if ($this->has('component.preview_field') && $this->has('component.schema')) {
                $previewField = $this->input('component.preview_field');
                $schema = $this->input('component.schema');
                
                if ($previewField && !array_key_exists($previewField, $schema)) {
                    $validator->errors()->add('component.preview_field', 'Preview field must exist in the component schema');
                }
            }
        });
    }

    /**
     * Validate component schema structure.
     */
    private function validateComponentSchema($validator, array $schema): void
    {
        $allowedTypes = [
            'text', 'textarea', 'markdown', 'richtext', 'number', 'boolean', 
            'datetime', 'asset', 'option', 'options', 'blocks', 'link', 'email', 'url'
        ];

        foreach ($schema as $fieldName => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' configuration must be an array");
                continue;
            }

            if (!isset($fieldConfig['type'])) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' must specify a type");
                continue;
            }

            if (!in_array($fieldConfig['type'], $allowedTypes)) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' has invalid type '{$fieldConfig['type']}'");
                continue;
            }

            // Type-specific validation
            if ($fieldConfig['type'] === 'option' && !isset($fieldConfig['options'])) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' of type 'option' must specify options");
            }

            if ($fieldConfig['type'] === 'options' && !isset($fieldConfig['options'])) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' of type 'options' must specify options");
            }

            if (isset($fieldConfig['options']) && !is_array($fieldConfig['options'])) {
                $validator->errors()->add('component.schema', "Field '{$fieldName}' options must be an array");
            }
        }
    }
}