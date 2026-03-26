<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthAuthorizeRequest;
use App\Services\OAuth\OAuthAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationController extends Controller
{
    public function __invoke(
        OAuthAuthorizeRequest $request,
        OAuthAuthorizationService $authorizationService,
    ): Response|RedirectResponse {
        $result = $authorizationService->approve($request->user(), $request->validated());

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }
}
