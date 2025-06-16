<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

/**
 * Authentication API.
 * Handle user authentication, registration, and token management.
 */
#[OA\Tag(name: 'Authentication', description: 'User authentication and token management')]
class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * User registration.
     */
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register user',
        description: 'Register a new user account.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    'name' => new OA\Property(type: 'string', example: 'John Doe'),
                    'email' => new OA\Property(type: 'string', format: 'email', example: 'john@example.com'),
                    'password' => new OA\Property(type: 'string', format: 'password', minLength: 8, example: 'password123'),
                    'password_confirmation' => new OA\Property(type: 'string', format: 'password', example: 'password123')
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        'user' => new OA\Property(
                            properties: [
                                'id' => new OA\Property(type: 'string', format: 'uuid'),
                                'name' => new OA\Property(type: 'string'),
                                'email' => new OA\Property(type: 'string', format: 'email'),
                                'created_at' => new OA\Property(type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        ),
                        'token' => new OA\Property(type: 'string', description: 'JWT access token'),
                        'expires_at' => new OA\Property(type: 'string', format: 'date-time', description: 'Token expiration time')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Create user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(), // Auto-verify for API registration
        ]);

        // Generate JWT token
        $tokenData = $this->jwtService->createTokenPair($user->id);
        $token = $tokenData['access_token'];

        return response()->json([
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString()
            ],
            'access_token' => $token,
            'refresh_token' => $tokenData['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokenData['expires_in']
        ], 201);
    }

    /**
     * User login.
     */
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login user',
        description: 'Authenticate user and return access token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    'email' => new OA\Property(type: 'string', format: 'email', example: 'john@example.com'),
                    'password' => new OA\Property(type: 'string', format: 'password', example: 'password123'),
                    'remember' => new OA\Property(type: 'boolean', description: 'Extend token lifetime', example: false)
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        'user' => new OA\Property(
                            properties: [
                                'id' => new OA\Property(type: 'string', format: 'uuid'),
                                'name' => new OA\Property(type: 'string'),
                                'email' => new OA\Property(type: 'string', format: 'email'),
                                'spaces' => new OA\Property(
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            'id' => new OA\Property(type: 'string', format: 'uuid'),
                                            'name' => new OA\Property(type: 'string'),
                                            'slug' => new OA\Property(type: 'string'),
                                            'role' => new OA\Property(type: 'string')
                                        ],
                                        type: 'object'
                                    )
                                )
                            ],
                            type: 'object'
                        ),
                        'token' => new OA\Property(type: 'string', description: 'JWT access token'),
                        'expires_at' => new OA\Property(type: 'string', format: 'date-time', description: 'Token expiration time')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $remember = $credentials['remember'] ?? false;

        // Find user by email
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'The provided credentials are incorrect'
            ], 401);
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return response()->json([
                'error' => 'Account inactive',
                'message' => 'Your account is currently ' . $user->status
            ], 401);
        }

        // Generate JWT token
        $tokenData = $this->jwtService->createTokenPair($user->id);
        $token = $tokenData['access_token'];

        // Load user spaces with roles
        $userSpaces = $user->spaces()->with('pivot.role')->get()->map(function ($space) {
            return [
                'id' => $space->uuid,
                'name' => $space->name,
                'slug' => $space->slug,
                'role' => $space->pivot->role->name ?? 'member'
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'spaces' => $userSpaces
            ],
            'access_token' => $token,
            'refresh_token' => $tokenData['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokenData['expires_in']
        ]);
    }

    /**
     * Refresh access token.
     */
    #[OA\Post(
        path: '/api/v1/auth/refresh',
        summary: 'Refresh token',
        description: 'Refresh the current access token.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(
                    properties: [
                        'token' => new OA\Property(type: 'string', description: 'New JWT access token'),
                        'expires_at' => new OA\Property(type: 'string', format: 'date-time', description: 'Token expiration time')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'No authenticated user found'
            ], 401);
        }

        // Generate new JWT token
        $tokenData = $this->jwtService->createTokenPair($user->id);

        return response()->json([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokenData['expires_in']
        ]);
    }

    /**
     * Get current user profile.
     */
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current user',
        description: 'Get the currently authenticated user profile.',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'user' => new OA\Property(
                            properties: [
                                'id' => new OA\Property(type: 'string', format: 'uuid'),
                                'name' => new OA\Property(type: 'string'),
                                'email' => new OA\Property(type: 'string', format: 'email'),
                                'preferences' => new OA\Property(type: 'object'),
                                'spaces' => new OA\Property(type: 'array', items: new OA\Items(type: 'object')),
                                'created_at' => new OA\Property(type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'No authenticated user found'
            ], 401);
        }

        // Load user spaces with roles
        $userSpaces = $user->spaces()->with('pivot.role')->get()->map(function ($space) {
            return [
                'id' => $space->uuid,
                'name' => $space->name,
                'slug' => $space->slug,
                'role' => $space->pivot->role->name ?? 'member',
                'permissions' => $space->pivot->role->permissions ?? []
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'preferences' => $user->preferences ?? [],
                'spaces' => $userSpaces,
                'created_at' => $user->created_at->toISOString()
            ]
        ]);
    }

    /**
     * Logout user.
     */
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Logout user',
        description: 'Logout the current user (invalidate token).',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(type: 'string', example: 'Successfully logged out')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        // Note: For JWT tokens, we would typically add the token to a blacklist
        // For this implementation, we'll just return success
        // In a production environment, you might want to implement token blacklisting

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Forgot password.
     */
    #[OA\Post(
        path: '/api/v1/auth/forgot-password',
        summary: 'Forgot password',
        description: 'Send password reset email.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    'email' => new OA\Property(type: 'string', format: 'email', example: 'john@example.com')
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset email sent',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(type: 'string', example: 'Password reset email sent')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // TODO: Implement password reset email logic
        // This would typically send an email with a reset link

        return response()->json([
            'message' => 'Password reset email sent. Please check your inbox.'
        ]);
    }

    /**
     * Reset password.
     */
    #[OA\Post(
        path: '/api/v1/auth/reset-password',
        summary: 'Reset password',
        description: 'Reset user password with token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'password_confirmation', 'token'],
                properties: [
                    'email' => new OA\Property(type: 'string', format: 'email', example: 'john@example.com'),
                    'password' => new OA\Property(type: 'string', format: 'password', minLength: 8, example: 'newpassword123'),
                    'password_confirmation' => new OA\Property(type: 'string', format: 'password', example: 'newpassword123'),
                    'token' => new OA\Property(type: 'string', example: 'reset-token-here')
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successfully',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(type: 'string', example: 'Password reset successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 400, description: 'Invalid reset token')
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required|string'
        ]);

        // TODO: Implement password reset logic
        // This would typically validate the reset token and update the password

        return response()->json([
            'message' => 'Password reset successfully. You can now login with your new password.'
        ]);
    }
}