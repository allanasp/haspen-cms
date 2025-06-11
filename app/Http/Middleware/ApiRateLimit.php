<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * API Rate Limiting Middleware.
 * @psalm-suppress UnusedClass
 */
final class ApiRateLimit
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, string $limitType = 'default'): mixed
    {
        $key = $this->resolveRequestSignature($request, $limitType);
        $maxAttempts = $this->getLimit($limitType);
        $decayMinutes = $this->getWindow($limitType);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes);

        /** @var mixed $response */
        $response = $next($request);

        if ($response instanceof Response) {
            return $this->addHeaders(
                $response,
                $maxAttempts,
                $this->calculateRemainingAttempts($key, $maxAttempts)
            );
        }

        return $response;
    }

    /**
     * Get rate limit for the given type.
     */
    private function getLimit(string $limitType): int
    {
        return match ($limitType) {
            'cdn' => 60,        // CDN API: 60 requests per minute
            'management' => 120, // Management API: 120 requests per minute  
            'auth' => 10,       // Auth API: 10 requests per minute
            default => 60
        };
    }

    /**
     * Get rate limit window in seconds.
     */
    private function getWindow(string $limitType): int
    {
        return match ($limitType) {
            'cdn' => 60,        // 1 minute
            'management' => 60, // 1 minute
            'auth' => 60,       // 1 minute
            default => 60
        };
    }

    /**
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(Request $request, string $limitType = 'default'): string
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = $request->user();
        /** @var string|int $userId */
        $userId = $user?->getAuthIdentifier() ?? 'guest';
        $route = $request->route();
        $routeName = $route->getName();
        $routeIdentifier = $routeName ?? $request->path();

        $baseKey = \sprintf(
            'api_rate_limit:%s:%s:%s:%s',
            $limitType,
            (string) $userId,
            $request->ip() ?? 'unknown',
            $routeIdentifier
        );

        // Add space context for management API
        if ($limitType === 'management' && $space = $request->get('current_space')) {
            $baseKey .= ':space:' . $space->id;
        }

        return $baseKey;
    }

    /**
     * Create a rate limit response.
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts): \Illuminate\Http\JsonResponse
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], ResponseAlias::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        /** @var int $attempts */
        $attempts = $this->limiter->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }
}
