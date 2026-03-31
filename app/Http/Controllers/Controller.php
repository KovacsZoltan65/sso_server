<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Audit\AuditLogService;

abstract class Controller
{
    use AuthorizesRequests {
        authorize as protected baseAuthorize;
    }

    /**
     * @param  mixed  $ability
     * @param  mixed|array<int, mixed>  $arguments
     * @return \Illuminate\Auth\Access\Response
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
                app(AuditLogService::class)->logSecurityEvent(
                    event: 'security.authorization.denied',
                    description: 'Authorization denied.',
                    causer: $request->user(),
                    properties: [
                        'route' => $request->route()?->getName(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                );
            }

            throw $exception;
        }
    }

    /**
     * Hozz létre egy sikeres API-választ a projekt szabványos JSON-borítékának használatával.
     *
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
     * Hozz létre egy hiba API választ a projekt szabványos JSON borítékának használatával.
     *
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
