<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Component schema cast with validation.
 */
class ComponentSchema implements CastsAttributes
{
    /**
     * Cast the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON in component schema: ' . json_last_error_msg());
        }

        return $this->validateSchema($decoded);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $validated = $this->validateSchema($value);

        $encoded = json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Cannot encode component schema: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Validate component schema structure.
     *
     * @param mixed $schema
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function validateSchema(mixed $schema): array
    {
        if (! \is_array($schema)) {
            throw new \InvalidArgumentException('Component schema must be an array');
        }

        foreach ($schema as $field) {
            if (! \is_array($field)) {
                throw new \InvalidArgumentException('Component schema fields must be arrays');
            }

            if (! isset($field['type'])) {
                throw new \InvalidArgumentException('Component schema field must have a type');
            }

            $allowedTypes = [
                'text', 'textarea', 'markdown', 'number', 'boolean', 'date', 'datetime',
                'select', 'multiselect', 'image', 'file', 'link', 'email', 'url',
                'color', 'json', 'table', 'blocks', 'asset', 'story'
            ];

            if (! \in_array($field['type'], $allowedTypes)) {
                throw new \InvalidArgumentException("Invalid field type: {$field['type']}");
            }
        }

        return $schema;
    }
}
