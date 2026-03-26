<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthIntrospectRequest;
use App\Services\OAuth\OAuthTokenService;
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

            return $this->successResponse('Token introspection completed.', $result);
        } catch (ValidationException $exception) {
            return $this->errorResponse(
                message: 'Token introspection failed.',
                errors: $exception->errors(),
                status: 422,
            );
        }
    }
}
