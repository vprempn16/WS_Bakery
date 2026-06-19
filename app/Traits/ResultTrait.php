<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ResultTrait
{
    protected string $genericErrorMessage = 'An unexpected error occurred. Please contact support.';

    /**
     * All API responses use HTTP 200 by default (or custom status code).
     */
    protected function success($data = null, $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Paginated response for list endpoints.
     */
    protected function paginated($paginator, $fields = null, $message = 'Success', int $status = 200): JsonResponse
    {
        $data = [
            'list' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        if ($fields !== null) {
            $data['fields'] = $fields;
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Error response.
     */
    protected function error($message = 'Error', $errorId = null, $errors = null, $errorCode = null, int $status = 200): JsonResponse
    {
        $safeMessage = $this->sanitizeUserMessage((string) $message);

        $payload = [
            'status'  => false,
            'message' => $safeMessage,
        ];

        if ($errorId !== null) {
            $payload['error_id'] = $errorId;
        }

        $payload['error_code'] = $errorCode ?? $errorId;

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Prevent leaking SQL/table names/file paths/internal details to frontend.
     */
    protected function sanitizeUserMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return $this->genericErrorMessage;
        }

        $looksSensitive =
            str_contains($message, 'SQLSTATE[') ||
            str_contains($message, ' SQL:') ||
            preg_match('#\\/[a-zA-Z0-9\\/_-]+\\.php#', $message) ||
            preg_match('#\\b[a-zA-Z]:\\\\\\\\[^\\s]+#', $message);

        return $looksSensitive ? $this->genericErrorMessage : $message;
    }
}
