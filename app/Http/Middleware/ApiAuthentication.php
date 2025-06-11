<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Middleware to handle API authentication via JWT tokens.
 */
class ApiAuthentication
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $guard Optional guard specification
     * @return BaseResponse
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): BaseResponse
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('Authentication token required');
        }

        try {
            $payload = $this->jwtService->validateToken($token);
            $user = $this->resolveUser($payload);

            if (!$user) {
                return $this->unauthorizedResponse('Invalid user');
            }

            // Check if user has access to current space
            if ($guard === 'space' && !$this->userHasSpaceAccess($user, $request)) {
                return $this->forbiddenResponse('Access denied to this space');
            }

            // Set authenticated user
            $request->setUserResolver(fn() => $user);
            auth()->setUser($user);

        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        return $next($request);
    }

    /**
     * Extract JWT token from request.
     */
    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        
        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->query('token');
    }

    /**
     * Resolve user from JWT payload.
     */
    private function resolveUser(array $payload): ?\App\Models\User
    {
        $userId = $payload['sub'] ?? null;
        
        if (!$userId) {
            return null;
        }

        return \App\Models\User::find($userId);
    }

    /**
     * Check if user has access to current space.
     */
    private function userHasSpaceAccess(\App\Models\User $user, Request $request): bool
    {
        $space = $request->get('current_space');
        
        if (!$space) {
            return true; // No space context required
        }

        return $user->spaces()->where('space_id', $space->id)->exists();
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorizedResponse(string $message): BaseResponse
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return forbidden response.
     */
    private function forbiddenResponse(string $message): BaseResponse
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => $message
        ], Response::HTTP_FORBIDDEN);
    }
}