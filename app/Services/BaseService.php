<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Base service class providing common functionality for all services
 */
abstract class BaseService
{
    /**
     * Log an info message with service context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge(['service' => static::class], $context));
    }

    /**
     * Log an error message with service context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge(['service' => static::class], $context));
    }

    /**
     * Log a warning message with service context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge(['service' => static::class], $context));
    }

    /**
     * Log a debug message with service context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Log::debug($message, array_merge(['service' => static::class], $context));
    }
}