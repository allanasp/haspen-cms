<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom JSON cast with validation support.
 */
class Json implements CastsAttributes
{
    /**
     * Cast the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    #[\Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON in {$key}: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    #[\Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Cannot encode JSON for {$key}: " . json_last_error_msg());
        }

        return $encoded;
    }
}
