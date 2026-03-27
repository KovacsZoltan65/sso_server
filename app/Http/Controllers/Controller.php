<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests {
        authorize as protected baseAuthorize;
    }

    /**
     * @param  mixed  $ability
     * @param  mixed|array<int, mixed>  $arguments
     *
     * @throws AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        try {
            return $this->baseAuthorize($ability, $arguments);
        } catch (AuthorizationException $exception) {
            $request = request();

            if ($request instanceof Request && str_starts_with($request->path(), 'admin/')) {
                $activity = activity('security')
                    ->event('authorization.denied')
                    ->withProperties([
                        'route' => $request->route()?->getName(),
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'ip_address' => $request->ip(),
                    ]);

                if ($request->user() !== null) {
                    $activity->causedBy($request->user());
                }

                $activity->log('Authorization denied.');
            }

            throw $exception;
        }
    }

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
