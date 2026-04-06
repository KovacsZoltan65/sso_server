<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Build and sign a minimal OIDC ID token for authorization-code flows.
 */
class OidcIdTokenService
{
    public function __construct(
        private readonly OidcSigningKeyService $signingKeyService,
        private readonly OidcSubjectService $subjectService,
    ) {
    }

    public function issueForAuthorizationCode(AuthorizationCode $authorizationCode): string
    {
        $issuedAt = now();
        $claims = $this->claimsForAuthorizationCode($authorizationCode, $issuedAt);

        return $this->encodeJwt(
            header: [
                'alg' => $this->signingKeyService->algorithm(),
                'typ' => 'JWT',
                'kid' => $this->signingKeyService->kid(),
            ],
            payload: $claims,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function claimsForAuthorizationCode(AuthorizationCode $authorizationCode, ?Carbon $issuedAt = null): array
    {
        $authorizationCode->loadMissing(['client', 'user']);

        $issuedAt ??= now();
        $expiresAt = $issuedAt->copy()->addSeconds($this->ttlSeconds());

        $claims = [
            'iss' => $this->issuer(),
            'sub' => $this->subjectService->forUserId($authorizationCode->user_id),
            'aud' => (string) $authorizationCode->client->client_id,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ];

        $nonce = $authorizationCode->identityResponseNonce();

        if ($nonce !== null) {
            $claims['nonce'] = $nonce;
        }

        return $claims;
    }

    /**
     * @param array<string, int|string> $header
     * @param array<string, int|string> $payload
     */
    private function encodeJwt(array $header, array $payload): string
    {
        $headerSegment = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadSegment = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingInput = $headerSegment.'.'.$payloadSegment;
        $signature = $this->signingKeyService->sign($signingInput);

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
        return max(60, (int) config('oidc.id_token_ttl_seconds', 300));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
