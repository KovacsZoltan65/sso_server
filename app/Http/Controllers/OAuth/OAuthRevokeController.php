<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthRevokeRequest;
use App\Services\OAuth\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OAuthRevokeController extends Controller
{
    public function __invoke(
        OAuthRevokeRequest $request,
        OAuthTokenService $tokenService,
    ): JsonResponse {
        try {
            $tokenService->revokeToken($request->validated());
        } catch (ValidationException $exception) {
            return $this->errorResponse(
                message: 'OAuth token revoke request failed.',
                errors: $exception->errors(),
                status: $exception->status,
            );
        }

        return $this->successResponse(
            message: 'Token revoked successfully.',
            data: null,
        );
    }
}
