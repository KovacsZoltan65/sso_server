<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthTokenRequest;
use App\Services\OAuth\OAuthTokenService;
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

            return $this->successResponse('OAuth token issued successfully.', $result);
        } catch (ValidationException $exception) {
            return $this->errorResponse(
                message: 'OAuth token request failed.',
                errors: $exception->errors(),
                status: 422,
            );
        }
    }
}
