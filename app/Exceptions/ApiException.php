<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base API exception class for consistent error responses.
 */
class ApiException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected array $errorData;

    public function __construct(
        string $message = 'An error occurred',
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        string $errorCode = 'INTERNAL_ERROR',
        array $errorData = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional error data.
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];

        if (!empty($this->errorData)) {
            $response['error']['data'] = $this->errorData;
        }

        if (config('app.debug')) {
            $response['debug'] = [
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => $this->getTrace(),
            ];
        }

        return response()->json($response, $this->statusCode);
    }

    /**
     * Static factory methods for common API exceptions.
     */
    public static function notFound(string $resource = 'Resource'): static
    {
        return new static(
            message: "{$resource} not found",
            statusCode: Response::HTTP_NOT_FOUND,
            errorCode: 'NOT_FOUND'
        );
    }

    public static function unauthorized(string $message = 'Unauthorized'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_UNAUTHORIZED,
            errorCode: 'UNAUTHORIZED'
        );
    }

    public static function forbidden(string $message = 'Access denied'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_FORBIDDEN,
            errorCode: 'FORBIDDEN'
        );
    }

    public static function validationFailed(array $errors): static
    {
        return new static(
            message: 'Validation failed',
            statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
            errorCode: 'VALIDATION_FAILED',
            errorData: ['validation_errors' => $errors]
        );
    }

    public static function conflict(string $message = 'Resource conflict'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_CONFLICT,
            errorCode: 'CONFLICT'
        );
    }

    public static function badRequest(string $message = 'Bad request'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_BAD_REQUEST,
            errorCode: 'BAD_REQUEST'
        );
    }

    public static function tooManyRequests(string $message = 'Too many requests'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_TOO_MANY_REQUESTS,
            errorCode: 'TOO_MANY_REQUESTS'
        );
    }

    public static function serverError(string $message = 'Internal server error'): static
    {
        return new static(
            message: $message,
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            errorCode: 'INTERNAL_ERROR'
        );
    }
}