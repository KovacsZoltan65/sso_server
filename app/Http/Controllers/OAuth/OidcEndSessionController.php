<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\OAuth\OidcEndSessionRequest;
use App\Services\OAuth\OidcEndSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;

class OidcEndSessionController extends Controller
{
    public function __invoke(
        OidcEndSessionRequest $request,
        OidcEndSessionService $endSessionService,
    ): Response|RedirectResponse|View {
        return $endSessionService->handle($request, $request->validated());
    }
}
