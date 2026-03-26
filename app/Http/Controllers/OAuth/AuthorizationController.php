<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthAuthorizeRequest;
use App\Services\OAuth\OAuthAuthorizationService;
use Illuminate\Http\RedirectResponse;

class AuthorizationController extends Controller
{
    public function __invoke(OAuthAuthorizeRequest $request, OAuthAuthorizationService $authorizationService): RedirectResponse
    {
        $result = $authorizationService->approve($request->user(), $request->validated());

        return redirect()->away($result['redirect_url']);
    }
}
