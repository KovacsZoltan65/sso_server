<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthIntrospectRequest;
use App\Services\OAuth\OAuthTokenService;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OAuthIntrospectController extends Controller
{
    public function __invoke(
        OAuthIntrospectRequest $request,
        OAuthTokenService $tokenService,
    ): JsonResponse {
        try {
            $result = $tokenService->introspectToken($request->validated());

            return $this->successResponse(Localization::translate('api.oauth.introspect.completed'), $result);
        } catch (ValidationException $exception) {
            $isClientAuthenticationFailure = $exception->status === 401;

            return $this->errorResponse(
                message: $isClientAuthenticationFailure
                    ? Localization::translate('api.oauth.invalid_client_credentials')
                    : Localization::translate('api.oauth.introspect.failed'),
                errors: $exception->errors(),
                status: $exception->status,
            );
        }
    }
}
