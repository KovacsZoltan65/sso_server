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
                message: __('api.oauth.revoke.request_failed'),
                errors: $exception->errors(),
                status: $exception->status,
            );
        }

        return $this->successResponse(
            message: __('api.tokens.revoked'),
            data: null,
        );
    }
}
