<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * API Rate Limiting Middleware.
 */
final class ApiRateLimit
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): mixed
    {
        $key = $this->resolveRequestSignature($request);

        $maxAttempts = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

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
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        /** @var string|int $userId */
        $userId = $user?->id ?? 'guest';
        $route = $request->route();
        $routeName = $route !== null ? $route->getName() : null;
        $routeIdentifier = $routeName ?? $request->path();

        return \sprintf(
            'api_rate_limit:%s:%s:%s',
            (string) $userId,
            $request->ip() ?? 'unknown',
            $routeIdentifier
        );
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
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }
}
