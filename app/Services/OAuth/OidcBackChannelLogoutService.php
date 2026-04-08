<?php

namespace App\Services\OAuth;

use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OidcBackChannelLogoutService
{
    private const LOGOUT_EVENT_CLAIM = 'http://schemas.openid.net/event/backchannel-logout';

    public function __construct(
        private readonly OidcSigningKeyService $signingKeyService,
        private readonly OidcSubjectService $subjectService,
        private readonly OidcFrontChannelLogoutService $frontChannelLogoutService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<int, array{
     *     client_id: int,
     *     client_public_id: string,
     *     backchannel_logout_uri: string,
     *     logout_token: string
     * }>
     */
    public function buildDispatchTargets(Session $session, int $userId): array
    {
        $subject = $this->subjectService->forUserId($userId);

        return collect($this->frontChannelLogoutService->participatingClients($session))
            ->map(function (array $participant) use ($subject): ?array {
                $backChannelLogoutUri = trim((string) ($participant['backchannel_logout_uri'] ?? ''));
                $clientPublicId = trim((string) ($participant['client_public_id'] ?? ''));
                $sid = trim((string) ($participant['sid'] ?? ''));
                $clientId = (int) ($participant['client_id'] ?? 0);

                if ($backChannelLogoutUri === '' || $clientPublicId === '' || $sid === '' || $clientId <= 0) {
                    return null;
                }

                return [
                    'client_id' => $clientId,
                    'client_public_id' => $clientPublicId,
                    'sid' => $sid,
                    'backchannel_logout_uri' => $backChannelLogoutUri,
                    'logout_token' => $this->issueLogoutToken($clientPublicId, $subject, $sid),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function dispatch(array $targets): void
    {
        foreach ($targets as $target) {
            $response = null;

            try {
                $response = Http::asForm()
                    ->timeout(max(1, (int) config('services.http.timeout', 10)))
                    ->post($target['backchannel_logout_uri'], [
                        'logout_token' => $target['logout_token'],
                    ]);

                if (! $response->successful()) {
                    throw new RuntimeException('backchannel_logout_http_failure');
                }

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.backchannel_logout.dispatched',
                    description: 'OIDC back-channel logout dispatched.',
                    properties: [
                        'client_id' => $target['client_id'],
                        'client_public_id' => $target['client_public_id'],
                        'has_sid' => true,
                        'status' => 'dispatched',
                        'http_status' => $response->status(),
                    ],
                );

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.backchannel_logout.dispatched_with_sid',
                    description: 'OIDC back-channel logout dispatched with sid correlation.',
                    properties: [
                        'client_id' => $target['client_id'],
                        'client_public_id' => $target['client_public_id'],
                        'has_sid' => true,
                        'status' => 'dispatched',
                        'http_status' => $response->status(),
                    ],
                );
            } catch (\Throwable $exception) {
                $this->auditLogService->logFailure(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.backchannel_logout.dispatch_failed',
                    description: 'OIDC back-channel logout dispatch failed.',
                    properties: [
                        'client_id' => $target['client_id'],
                        'client_public_id' => $target['client_public_id'],
                        'status' => 'dispatch_failed',
                        'reason' => $exception->getMessage(),
                        'http_status' => $response?->status(),
                    ],
                );
            }
        }
    }

    public function issueLogoutToken(string $audience, string $subject, ?string $sid = null, ?Carbon $issuedAt = null): string
    {
        $issuedAt ??= now();
        $signingKey = $this->signingKeyService->getActiveSigningKey();
        $payload = $this->logoutTokenClaims($audience, $subject, $sid, $issuedAt);

        $token = $this->encodeJwt(
            header: [
                'alg' => $signingKey['alg'],
                'typ' => 'JWT',
                'kid' => $signingKey['kid'],
            ],
            payload: $payload,
            signingKey: $signingKey,
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.backchannel_logout.token_issued',
            description: 'OIDC back-channel logout token issued.',
            properties: [
                'client_public_id' => $audience,
                'kid' => $signingKey['kid'],
                'has_sid' => trim((string) ($sid ?? '')) !== '',
                'has_jti' => true,
                'has_exp' => true,
                'ttl_seconds' => $this->ttlSeconds(),
                'status' => 'issued',
            ],
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.backchannel_logout.token_issued_with_exp',
            description: 'OIDC back-channel logout token issued with expiration.',
            properties: [
                'client_public_id' => $audience,
                'kid' => $signingKey['kid'],
                'has_sid' => trim((string) ($sid ?? '')) !== '',
                'has_jti' => true,
                'has_exp' => true,
                'ttl_seconds' => $this->ttlSeconds(),
                'status' => 'issued',
            ],
        );

        return $token;
    }

    /**
     * @return array<string, int|string|object>
     */
    public function logoutTokenClaims(string $audience, string $subject, ?string $sid = null, ?Carbon $issuedAt = null): array
    {
        $issuedAt ??= now();
        $expiresAt = $issuedAt->copy()->addSeconds($this->ttlSeconds());

        $claims = [
            'iss' => $this->issuer(),
            'aud' => trim($audience),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'jti' => Str::random(64),
            'sub' => $subject,
            'events' => [
                self::LOGOUT_EVENT_CLAIM => new \stdClass(),
            ],
        ];

        $sid = trim((string) ($sid ?? ''));

        if ($sid !== '') {
            $claims['sid'] = $sid;
        }

        return $claims;
    }

    /**
     * @param array<string, int|string> $header
     * @param array<string, int|string|object> $payload
     * @param array{kid: string, alg: string, private_key_path: string|null, public_key_path: string, published: bool} $signingKey
     */
    private function encodeJwt(array $header, array $payload, array $signingKey): string
    {
        $headerSegment = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadSegment = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = $headerSegment.'.'.$payloadSegment;
        $signature = $this->signingKeyService->sign($signingInput, $signingKey);

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function issuer(): string
    {
        $issuer = trim((string) config('oidc.issuer'));

        if ($issuer === '') {
            throw new RuntimeException('OIDC issuer configuration is missing.');
        }

        return rtrim($issuer, '/');
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('oidc.backchannel_logout_token_ttl_seconds', 300));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
