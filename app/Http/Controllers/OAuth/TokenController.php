<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthTokenRequest;
use App\Services\OAuth\OAuthTokenService;
use App\Support\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function __invoke(OAuthTokenRequest $request, OAuthTokenService $tokenService): JsonResponse
    {
        try {
            $payload = $request->validated();
            $result = ($payload['grant_type'] ?? null) === 'refresh_token'
                ? $tokenService->refreshAccessToken($payload, $request->ip(), $request->userAgent())
                : $tokenService->exchangeAuthorizationCode($payload, $request->ip(), $request->userAgent());

            return $this->successResponse(Localization::translate('api.oauth.token.issued'), $result);
        } catch (ValidationException $exception) {
            $isClientAuthenticationFailure = $exception->status === 401;

            return $this->errorResponse(
                message: $isClientAuthenticationFailure
                    ? Localization::translate('api.oauth.invalid_client_credentials')
                    : Localization::translate('api.oauth.token.request_failed'),
                errors: $exception->errors(),
                status: $exception->status,
            );
        }
    }
}
