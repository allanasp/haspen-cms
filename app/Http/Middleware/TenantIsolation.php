<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Space;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Middleware to handle tenant isolation and space resolution.
 */
class TenantIsolation
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return BaseResponse
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        // Extract space identifier from route parameter or subdomain
        $spaceId = $this->resolveSpaceIdentifier($request);
        
        if (!$spaceId) {
            return response()->json([
                'error' => 'Space identifier required',
                'message' => 'Please provide a valid space ID in the URL or subdomain'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Resolve space and set tenant context
        $space = $this->resolveSpace($spaceId);
        
        if (!$space) {
            return response()->json([
                'error' => 'Space not found',
                'message' => 'The specified space does not exist or is not accessible'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check space status
        if ($space->status !== 'active') {
            return response()->json([
                'error' => 'Space unavailable', 
                'message' => 'This space is currently ' . $space->status
            ], Response::HTTP_FORBIDDEN);
        }

        // Set current space in request for downstream usage
        $request->merge(['current_space' => $space]);
        app()->instance('current_space', $space);

        return $next($request);
    }

    /**
     * Resolve space identifier from request.
     */
    private function resolveSpaceIdentifier(Request $request): ?string
    {
        // First try route parameter
        if ($request->route('space_id')) {
            return $request->route('space_id');
        }

        // Then try subdomain
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) > 2) {
            return $parts[0]; // First subdomain part
        }

        // Finally try header
        return $request->header('X-Space-ID');
    }

    /**
     * Resolve space by identifier (UUID or slug).
     */
    private function resolveSpace(string $identifier): ?Space
    {
        // Try UUID format first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return Space::where('uuid', $identifier)->first();
        }

        // Try slug format
        return Space::where('slug', $identifier)->first();
    }
}