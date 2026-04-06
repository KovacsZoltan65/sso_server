<?php

namespace App\Services\OAuth;

use App\Repositories\Contracts\ScopeRepositoryInterface;

class OidcDiscoveryService
{
    public function __construct(
        private readonly ScopeRepositoryInterface $scopeRepository,
        private readonly OidcSigningKeyService $signingKeyService,
        private readonly OidcUserInfoService $oidcUserInfoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function providerMetadata(): array
    {
        return [
            'issuer' => $this->issuer(),
            'authorization_endpoint' => $this->absoluteUrl(route('oauth.authorize', absolute: false)),
            'token_endpoint' => $this->absoluteUrl(route('oauth.token', absolute: false)),
            'userinfo_endpoint' => $this->absoluteUrl(route('oauth.userinfo', absolute: false)),
            'end_session_endpoint' => $this->absoluteUrl(route('oidc.end_session', absolute: false)),
            'jwks_uri' => $this->absoluteUrl(route('oidc.jwks', absolute: false)),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => [$this->signingKeyService->algorithm()],
            'scopes_supported' => $this->scopeRepository->activeCodes(),
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => $this->oidcUserInfoService->supportedClaims(),
        ];
    }

    private function issuer(): string
    {
        return rtrim((string) config('oidc.issuer'), '/');
    }

    private function absoluteUrl(string $path): string
    {
        return $this->issuer().'/'.ltrim($path, '/');
    }
}
