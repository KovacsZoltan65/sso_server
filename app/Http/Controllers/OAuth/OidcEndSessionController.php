<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OidcEndSessionRequest;
use App\Services\OAuth\OidcEndSessionService;
use Illuminate\Http\RedirectResponse;

class OidcEndSessionController extends Controller
{
    public function __invoke(
        OidcEndSessionRequest $request,
        OidcEndSessionService $endSessionService,
    ): RedirectResponse {
        return $endSessionService->handle($request, $request->validated());
    }
}
