<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OidcEndSessionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly OidcIdTokenHintService $idTokenHintService,
        private readonly OidcFrontChannelLogoutService $frontChannelLogoutService,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function handle(Request $request, array $validated): Response|RedirectResponse|View
    {
        $idTokenHint = $this->normalizeString($validated['id_token_hint'] ?? null);
        $requestedRedirectUri = $this->normalizeString($validated['post_logout_redirect_uri'] ?? null);
        $state = $this->normalizeString($validated['state'] ?? null);
        $client = $this->idTokenHintService->resolveClientFromHint($idTokenHint);
        $redirectUri = $this->validatedRedirectUri($request, $client, $requestedRedirectUri);
        $frontChannelTargets = $this->frontChannelLogoutService->buildLogoutTargets($request->session());
        $finalRedirectUrl = $redirectUri !== null
            ? $this->appendState($redirectUri, $state)
            : route('login');

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.end_session.requested',
            description: 'OIDC end session requested.',
            subject: $client,
            causer: $request->user(),
            properties: [
                'client_id' => $client?->id,
                'client_public_id' => $client?->client_id,
                'redirect_uri' => $requestedRedirectUri,
                'status' => 'requested',
            ],
        );

        Auth::guard('web')->logout();
        $this->frontChannelLogoutService->forgetParticipatingClients($request->session());
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.end_session.completed',
            description: 'OIDC end session completed.',
            subject: $client,
            causer: null,
            properties: [
                'client_id' => $client?->id,
                'client_public_id' => $client?->client_id,
                'redirect_uri' => $redirectUri,
                'frontchannel_target_count' => count($frontChannelTargets),
                'status' => 'completed',
            ],
        );

        if ($frontChannelTargets !== []) {
            foreach ($frontChannelTargets as $target) {
                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.frontchannel_logout.dispatched',
                    description: 'OIDC front-channel logout dispatched.',
                    subject: $client,
                    causer: null,
                    properties: [
                        'client_id' => $target['client_id'],
                        'client_public_id' => $target['client_public_id'],
                        'status' => 'dispatched',
                    ],
                );
            }

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.frontchannel_logout.completed_provider_side',
                description: 'OIDC front-channel logout completed provider side.',
                subject: $client,
                causer: null,
                properties: [
                    'frontchannel_target_count' => count($frontChannelTargets),
                    'status' => 'completed_provider_side',
                ],
            );

            return response()->view('oidc.frontchannel-logout', [
                'frontChannelTargets' => $frontChannelTargets,
                'finalRedirectUrl' => $finalRedirectUrl,
                'hasPostLogoutRedirect' => $redirectUri !== null,
            ]);
        }

        if ($redirectUri !== null) {
            return redirect()->away($finalRedirectUrl);
        }

        return redirect()->route('login')
            ->with('status', 'Sikeres kijelentkezes.');
    }

    private function validatedRedirectUri(Request $request, ?SsoClient $client, ?string $requestedRedirectUri): ?string
    {
        if ($requestedRedirectUri === null) {
            return null;
        }

        if ($client instanceof SsoClient && $this->redirectUriMatcher->matches($client, $requestedRedirectUri)) {
            return $requestedRedirectUri;
        }

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.end_session.redirect_denied',
            description: 'OIDC end session redirect denied.',
            subject: $client,
            causer: $request->user(),
            properties: [
                'client_id' => $client?->id,
                'client_public_id' => $client?->client_id,
                'redirect_uri' => $requestedRedirectUri,
                'reason' => $client instanceof SsoClient ? 'redirect_not_registered' : 'missing_client_context',
                'status' => 'denied',
            ],
        );

        return null;
    }

    private function appendState(string $redirectUri, ?string $state): string
    {
        $normalizedState = $this->normalizeString($state);

        if ($normalizedState === null) {
            return $redirectUri;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.http_build_query([
            'state' => $normalizedState,
        ], arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
