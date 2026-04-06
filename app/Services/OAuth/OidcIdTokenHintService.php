<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;

class OidcIdTokenHintService
{
    public function __construct(
        private readonly OidcSigningKeyService $signingKeyService,
    ) {
    }

    public function resolveClientFromHint(?string $idTokenHint): ?SsoClient
    {
        $claims = $this->validatedClaims($idTokenHint);

        if (! is_array($claims)) {
            return null;
        }

        $audience = trim((string) ($claims['aud'] ?? ''));

        if ($audience === '') {
            return null;
        }

        return SsoClient::query()
            ->where('client_id', $audience)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatedClaims(?string $idTokenHint): ?array
    {
        $segments = $this->jwtSegments($idTokenHint);

        if ($segments === null) {
            return null;
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $segments;
        $header = $this->decodeJsonSegment($headerSegment);
        $claims = $this->decodeJsonSegment($payloadSegment);
        $signature = $this->decodeBase64Url($signatureSegment);

        if (! is_array($header) || ! is_array($claims) || $signature === null) {
            return null;
        }

        $alg = trim((string) ($header['alg'] ?? ''));
        $kid = trim((string) ($header['kid'] ?? ''));
        $issuer = trim((string) ($claims['iss'] ?? ''));

        if (
            $alg !== $this->signingKeyService->algorithm()
            || $kid === ''
            || $issuer !== rtrim((string) config('oidc.issuer'), '/')
        ) {
            return null;
        }

        $signingInput = $headerSegment.'.'.$payloadSegment;

        try {
            if (! $this->signingKeyService->verify($signingInput, $signature, $kid)) {
                return null;
            }
        } catch (\RuntimeException) {
            return null;
        }

        return $claims;
    }

    /**
     * @return array<int, string>|null
     */
    private function jwtSegments(?string $jwt): ?array
    {
        $normalized = trim((string) $jwt);

        if ($normalized === '') {
            return null;
        }

        $segments = explode('.', $normalized);

        return count($segments) === 3 ? array_values($segments) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonSegment(string $segment): ?array
    {
        $decoded = $this->decodeBase64Url($segment);

        if ($decoded === null) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }
}
