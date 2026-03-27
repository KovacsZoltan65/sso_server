<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $errors
     */
    protected function successResponse(
        string $message,
        mixed $data = [],
        array $meta = [],
        array $errors = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $errors
     */
    protected function errorResponse(
        string $message,
        array $data = [],
        array $meta = [],
        array $errors = [],
        int $status = 422,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }
}
