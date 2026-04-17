<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\OAuth\OAuthTokenService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OAuthUserInfoController extends Controller
{
    public function __invoke(
        Request $request,
        OAuthTokenService $tokenService,
    ): JsonResponse {
        try {
            $result = $tokenService->getUserInfo($request->bearerToken(), $request->ip(), $request->userAgent());

            return $this->successResponse(__('api.oauth.userinfo.retrieved'), $result);
        } catch (AuthenticationException $exception) {
            return $this->errorResponse(
                message: __('api.oauth.authentication_failed'),
                errors: ['token' => [$exception->getMessage()]],
                status: 401,
            );
        } catch (AuthorizationException $exception) {
            return $this->errorResponse(
                message: __('messages.forbidden'),
                errors: ['scope' => [$exception->getMessage()]],
                status: 403,
            );
        }
    }
}
