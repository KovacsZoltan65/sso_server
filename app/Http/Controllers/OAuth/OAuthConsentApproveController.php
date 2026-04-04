<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthConsentApproveRequest;
use App\Services\OAuth\OAuthAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OAuthConsentApproveController extends Controller
{
    public function __invoke(
        OAuthConsentApproveRequest $request,
        OAuthAuthorizationService $authorizationService,
    ): Response|RedirectResponse {
        $result = $authorizationService->approveConsent($request->user(), (string) $request->validated('consent_token'));

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }
}
