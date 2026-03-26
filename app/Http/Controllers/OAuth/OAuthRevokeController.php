<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthRevokeRequest;
use App\Services\OAuth\OAuthTokenService;
use Illuminate\Http\JsonResponse;

class OAuthRevokeController extends Controller
{
    public function __invoke(
        OAuthRevokeRequest $request,
        OAuthTokenService $tokenService,
    ): JsonResponse {
        $tokenService->revokeToken($request->validated());

        return response()->json([
            'message' => 'Token revoked successfully.',
            'data' => null,
            'meta' => [],
            'errors' => [],
        ]);
    }
}