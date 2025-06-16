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
        $tokenString = $this->extractToken($request);

        if (!$tokenString) {
            return $this->unauthorizedResponse('Authentication token required');
        }

        try {
            $token = $this->jwtService->parseToken($tokenString);
            
            if (!$this->jwtService->validateToken($token)) {
                return $this->unauthorizedResponse('Invalid token');
            }

            if ($this->jwtService->isExpired($token)) {
                return $this->unauthorizedResponse('Token expired');
            }

            $userId = $this->jwtService->getUserId($token);
            $user = $this->resolveUser($userId);

            if (!$user) {
                return $this->unauthorizedResponse('Invalid user');
            }

            // Check if user has access to current space
            if ($guard === 'space' && !$this->userHasSpaceAccess($user, $request)) {
                return $this->forbiddenResponse('Access denied to this space');
            }

            // Set authenticated user and JWT claims
            $request->setUserResolver(fn() => $user);
            $request->attributes->set('jwt_token', $token);
            $request->attributes->set('jwt_claims', $token->claims()->all());
            auth()->setUser($user);

        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid or expired token: ' . $e->getMessage());
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
     * Resolve user from JWT user ID.
     */
    private function resolveUser(string $userId): ?\App\Models\User
    {
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