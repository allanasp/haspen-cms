<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for API request and response logging.
 */
class ApiLogging
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = $this->generateRequestId();
        
        // Log request
        $this->logRequest($request, $requestId);
        
        // Process request
        $response = $next($request);
        
        // Log response
        $this->logResponse($request, $response, $requestId, $startTime);
        
        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);
        
        return $response;
    }

    /**
     * Generate unique request ID.
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Log incoming request.
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query' => $request->query->all(),
            'body' => $this->sanitizeBody($request->all()),
            'timestamp' => now()->toISOString(),
        ];

        // Add user context if authenticated
        if ($user = $request->user()) {
            $logData['user_id'] = $user->id;
            $logData['user_email'] = $user->email;
        }

        // Add space context if available
        if ($space = $request->get('current_space')) {
            $logData['space_id'] = $space->id;
            $logData['space_slug'] = $space->slug;
        }

        Log::channel('api')->info('API Request', $logData);
    }

    /**
     * Log response.
     */
    private function logResponse(Request $request, Response $response, string $requestId, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // ms

        $logData = [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'content_length' => strlen($response->getContent()),
            'timestamp' => now()->toISOString(),
        ];

        // Log error details for error responses
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            if ($content && $this->isJson($content)) {
                $logData['error_response'] = json_decode($content, true);
            }
        }

        $level = match (true) {
            $response->getStatusCode() >= 500 => 'error',
            $response->getStatusCode() >= 400 => 'warning',
            default => 'info'
        };

        Log::channel('api')->{$level}('API Response', $logData);
    }

    /**
     * Sanitize headers by removing sensitive information.
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
            'set-cookie'
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    /**
     * Sanitize request body by removing sensitive information.
     */
    private function sanitizeBody(array $body): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key'
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[REDACTED]';
            }
        }

        return $body;
    }

    /**
     * Check if content is JSON.
     */
    private function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }
}