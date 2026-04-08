<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class OidcSessionService
{
    private const SESSION_KEY = 'oidc.session.sids_by_client';

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function issueSidForClientSession(SsoClient $client, ?User $user = null): string
    {
        $session = app('session.store');

        if (! $session instanceof Session) {
            return $this->newSid($client, $user);
        }

        $sids = $this->sids($session);
        $sid = trim((string) ($sids[$client->client_id] ?? ''));

        if ($sid !== '') {
            return $sid;
        }

        $sid = $this->newSid($client, $user);
        $sids[$client->client_id] = $sid;
        $session->put(self::SESSION_KEY, $sids);

        return $sid;
    }

    public function getCurrentSidForClient(SsoClient $client): ?string
    {
        $session = app('session.store');

        if (! $session instanceof Session) {
            return null;
        }

        $sid = trim((string) ($this->sids($session)[$client->client_id] ?? ''));

        return $sid !== '' ? $sid : null;
    }

    public function registerSidParticipation(SsoClient $client, string $sid, ?User $user = null): void
    {
        $normalizedSid = trim($sid);

        if ($normalizedSid === '') {
            return;
        }

        $session = app('session.store');

        if ($session instanceof Session) {
            $sids = $this->sids($session);
            $sids[$client->client_id] = $normalizedSid;
            $session->put(self::SESSION_KEY, $sids);
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.sid.bound_to_client_session',
            description: 'OIDC sid bound to the provider client session.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'target_user_id' => $user?->getKey(),
                'has_sid' => true,
                'status' => 'bound',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function sids(Session $session): array
    {
        $sids = $session->get(self::SESSION_KEY, []);

        return is_array($sids) ? $sids : [];
    }

    private function newSid(SsoClient $client, ?User $user): string
    {
        $sid = Str::random(64);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.sid.issued',
            description: 'OIDC sid issued for a provider client session.',
            subject: $client,
            causer: $user,
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'target_user_id' => $user?->getKey(),
                'has_sid' => true,
                'status' => 'issued',
            ],
        );

        return $sid;
    }
}
