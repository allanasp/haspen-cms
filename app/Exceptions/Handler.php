<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, Request $request) {
            // Only handle API requests
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->renderApiException($e, $request);
            }

            return null;
        });
    }

    /**
     * Render API exceptions with consistent JSON format.
     */
    protected function renderApiException(Throwable $e, Request $request): JsonResponse
    {
        // Handle custom API exceptions
        if ($e instanceof ApiException) {
            return $e->render();
        }

        // Handle Laravel validation exceptions
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'data' => [
                        'validation_errors' => $e->errors(),
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Handle authentication exceptions
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Handle model not found exceptions
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => "{$model} not found.",
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        // Handle 404 exceptions
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        // Handle HTTP exceptions
        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'HTTP_ERROR',
                    'message' => $e->getMessage() ?: 'HTTP error occurred.',
                ],
            ], $e->getStatusCode());
        }

        // Handle generic exceptions
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.';

        $response = [
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => $message,
            ],
        ];

        // Add debug information in debug mode
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}