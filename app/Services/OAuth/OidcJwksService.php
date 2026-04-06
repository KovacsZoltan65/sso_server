<?php

namespace App\Services\OAuth;

class OidcJwksService
{
    public function __construct(
        private readonly OidcSigningKeyService $signingKeyService,
    ) {
    }

    /**
     * @return array{keys: array<int, array{kty: string, kid: string, use: string, alg: string, n: string, e: string}>}
     */
    public function currentJwkSet(): array
    {
        return [
            'keys' => [
                $this->signingKeyService->publicJwk(),
            ],
        ];
    }
}
