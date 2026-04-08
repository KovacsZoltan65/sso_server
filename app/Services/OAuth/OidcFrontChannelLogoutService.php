<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Session\Session;

class OidcFrontChannelLogoutService
{
    private const SESSION_KEY = 'oidc.frontchannel.participating_clients';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly OidcSessionService $oidcSessionService,
    ) {
    }

    public function registerParticipatingClient(SsoClient $client, ?string $sid = null, ?\App\Models\User $user = null): string
    {
        $session = app('session.store');
        $sid = trim((string) ($sid ?? $this->oidcSessionService->issueSidForClientSession($client, $user)));

        if (! $session instanceof Session) {
            return $sid;
        }

        $this->oidcSessionService->registerSidParticipation($client, $sid, $user);

        $participants = $this->participatingClients($session);
        $participants[$client->client_id] = [
            'client_id' => $client->id,
            'client_public_id' => $client->client_id,
            'sid' => $sid,
            'frontchannel_logout_uri' => $client->normalizedFrontChannelLogoutUri(),
            'backchannel_logout_uri' => $client->normalizedBackChannelLogoutUri(),
            'registered_at' => now()->toIso8601String(),
        ];

        $session->put(self::SESSION_KEY, $participants);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.frontchannel_logout.registered_client',
            description: 'OIDC front-channel logout client registered for the provider session.',
            subject: $client,
            causer: auth()->user(),
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'has_frontchannel_logout_uri' => $client->normalizedFrontChannelLogoutUri() !== null,
                'has_backchannel_logout_uri' => $client->normalizedBackChannelLogoutUri() !== null,
                'has_sid' => true,
                'status' => 'registered',
            ],
        );

        return $sid;
    }

    /**
     * @return array<string, array{
     *     client_id: int,
     *     client_public_id: string,
     *     sid?: string,
     *     frontchannel_logout_uri: string|null,
     *     backchannel_logout_uri: string|null,
     *     registered_at: string
     * }>
     */
    public function participatingClients(Session $session): array
    {
        $participants = $session->get(self::SESSION_KEY, []);

        return is_array($participants) ? $participants : [];
    }

    /**
     * @return array<int, array{
     *     client_id: int,
     *     client_public_id: string,
     *     frontchannel_logout_uri: string,
     *     logout_url: string
     * }>
     */
    public function buildLogoutTargets(Session $session): array
    {
        $issuer = rtrim((string) config('oidc.issuer'), '/');

        return collect($this->participatingClients($session))
            ->map(function (array $participant) use ($issuer): ?array {
                $frontchannelLogoutUri = trim((string) ($participant['frontchannel_logout_uri'] ?? ''));
                $clientPublicId = trim((string) ($participant['client_public_id'] ?? ''));
                $sid = trim((string) ($participant['sid'] ?? ''));
                $clientId = (int) ($participant['client_id'] ?? 0);

                if ($frontchannelLogoutUri === '' || $clientPublicId === '' || $sid === '' || $clientId <= 0) {
                    return null;
                }

                return [
                    'client_id' => $clientId,
                    'client_public_id' => $clientPublicId,
                    'sid' => $sid,
                    'frontchannel_logout_uri' => $frontchannelLogoutUri,
                    'logout_url' => $this->appendQueryParameters($frontchannelLogoutUri, [
                        'iss' => $issuer,
                        'client_id' => $clientPublicId,
                        'sid' => $sid,
                    ]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function forgetParticipatingClients(Session $session, ?\App\Models\User $user = null): void
    {
        $count = count($this->participatingClients($session));

        $session->forget(self::SESSION_KEY);
        $this->oidcSessionService->clearSidParticipations($session, $user);

        if ($count > 0) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.sid.participation_cleared',
                description: 'OIDC provider session participation cleared.',
                causer: $user,
                properties: [
                    'affected_count' => $count,
                    'has_sid' => true,
                    'status' => 'cleared',
                ],
            );
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function appendQueryParameters(string $url, array $parameters): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($parameters, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
    }
}
