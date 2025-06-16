<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS (Cross-Origin Resource Sharing) middleware.
 */
final class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS request.
     */
    private function handlePreflightRequest(Request $request): Response
    {
        $response = response('', 200);
        
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to response.
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigins = $this->getAllowedOrigins();

        // Check if origin is allowed
        if ($origin && $this->isOriginAllowed($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 
            'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-API-Key, X-Space-ID, X-Language'
        );
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours

        // Expose headers for client access
        $response->headers->set('Access-Control-Expose-Headers', 
            'X-RateLimit-Limit, X-RateLimit-Remaining, X-Total-Count, X-Page-Count, Link'
        );

        return $response;
    }

    /**
     * Get allowed origins from configuration.
     */
    private function getAllowedOrigins(): array
    {
        $origins = config('cors.allowed_origins', ['*']);
        
        if (!is_array($origins)) {
            return ['*'];
        }

        return $origins;
    }

    /**
     * Check if origin is allowed.
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Check for wildcard patterns
        foreach ($allowedOrigins as $allowedOrigin) {
            if (str_contains($allowedOrigin, '*')) {
                $pattern = str_replace('*', '.*', preg_quote($allowedOrigin, '/'));
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}