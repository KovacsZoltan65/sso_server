<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OAuthConsentDenyRequest;
use App\Services\OAuth\OAuthAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OAuthConsentDenyController extends Controller
{
    public function __invoke(
        OAuthConsentDenyRequest $request,
        OAuthAuthorizationService $authorizationService,
    ): Response|RedirectResponse {
        $result = $authorizationService->denyConsent($request->user(), (string) $request->validated('consent_token'));

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }
}
