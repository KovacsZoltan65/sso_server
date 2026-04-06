<?php

namespace App\Services\OAuth;

use OpenSSLAsymmetricKey;
use RuntimeException;

class OidcSigningKeyService
{
    /**
     * @return array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool
     * }
     */
    public function getActiveSigningKey(): array
    {
        $configuredKeys = $this->configuredKeys();
        $activeKid = $this->configuredActiveKid($configuredKeys);

        if ($activeKid !== '') {
            $activeKey = $this->findKeyByKid($activeKid);
        } else {
            $activeKeys = array_values(array_filter(
                $configuredKeys,
                static fn (array $key): bool => (bool) ($key['active'] ?? false),
            ));

            if (count($activeKeys) !== 1) {
                throw new RuntimeException('OIDC signing key configuration must define exactly one active key.');
            }

            $activeKey = $activeKeys[0];
        }

        if (! $activeKey['published']) {
            throw new RuntimeException('OIDC active signing key must also be published for verification.');
        }

        return $activeKey;
    }

    /**
     * @return array<int, array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool
     * }>
     */
    public function getPublishedVerificationKeys(): array
    {
        return array_values(array_filter(
            $this->configuredKeys(),
            static fn (array $key): bool => $key['published'],
        ));
    }

    /**
     * @return array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool
     * }
     */
    public function findKeyByKid(string $kid): array
    {
        $normalizedKid = trim($kid);

        if ($normalizedKid === '') {
            throw new RuntimeException('OIDC signing kid is missing.');
        }

        foreach ($this->configuredKeys() as $key) {
            if ($key['kid'] === $normalizedKid) {
                return $key;
            }
        }

        throw new RuntimeException(sprintf('OIDC signing key [%s] is not configured.', $normalizedKid));
    }

    /**
     * @return array{
     *     kty: string,
     *     kid: string,
     *     use: string,
     *     alg: string,
     *     n: string,
     *     e: string
     * }
     */
    public function publicJwk(?array $key = null): array
    {
        $signingKey = $key ?? $this->getActiveSigningKey();
        $details = $this->publicKeyDetails($signingKey);
        $rsa = $details['rsa'] ?? null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            throw new RuntimeException('OIDC signing public key must be an RSA key.');
        }

        return [
            'kty' => 'RSA',
            'kid' => $signingKey['kid'],
            'use' => 'sig',
            'alg' => $signingKey['alg'],
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    public function algorithm(): string
    {
        return $this->getActiveSigningKey()['alg'];
    }

    public function kid(): string
    {
        return $this->getActiveSigningKey()['kid'];
    }

    /**
     * @return array<int, string>
     */
    public function supportedAlgorithms(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $key): string => $key['alg'],
            $this->getPublishedVerificationKeys(),
        )));
    }

    public function sign(string $input, ?array $key = null): string
    {
        $signingKey = $key ?? $this->getActiveSigningKey();
        $privateKey = openssl_pkey_get_private($this->privateKeyPem($signingKey));

        if ($privateKey === false) {
            throw new RuntimeException('OIDC signing private key could not be loaded.');
        }

        try {
            $signature = '';
            $signed = openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            if ($signed !== true) {
                throw new RuntimeException('OIDC signing failed.');
            }

            return $signature;
        } finally {
            openssl_free_key($privateKey);
        }
    }

    public function verify(string $input, string $signature, ?string $kid = null): bool
    {
        $verificationKey = $kid !== null
            ? $this->findKeyByKid($kid)
            : $this->getActiveSigningKey();
        $publicKey = $this->publicKeyResource($verificationKey);

        try {
            $verified = openssl_verify($input, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified === 1) {
                return true;
            }

            if ($verified === 0) {
                return false;
            }

            throw new RuntimeException('OIDC signature verification failed.');
        } finally {
            openssl_free_key($publicKey);
        }
    }

    /**
     * @return array<int, array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     active?: bool
     * }>
     */
    private function configuredKeys(): array
    {
        $configured = config('oidc.signing.keys', []);

        if ((! is_array($configured) || $configured === [])
            && (config('oidc.signing.kid') !== null || config('oidc.signing.public_key_path') !== null)
        ) {
            $configured = [[
                'kid' => config('oidc.signing.kid'),
                'alg' => config('oidc.signing.alg', 'RS256'),
                'private_key_path' => config('oidc.signing.private_key_path'),
                'public_key_path' => config('oidc.signing.public_key_path'),
                'published' => true,
            ]];
        }

        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException('OIDC signing key configuration is missing.');
        }

        $keys = [];
        $seenKids = [];

        foreach ($configured as $index => $key) {
            if (! is_array($key)) {
                throw new RuntimeException(sprintf('OIDC signing key definition at index [%d] is invalid.', $index));
            }

            $kid = trim((string) ($key['kid'] ?? ''));
            $alg = trim((string) ($key['alg'] ?? 'RS256'));
            $publicKeyPath = $this->normalizePath($key['public_key_path'] ?? null);
            $privateKeyPath = $this->normalizePath($key['private_key_path'] ?? null);

            if ($kid === '') {
                throw new RuntimeException(sprintf('OIDC signing key definition at index [%d] is missing kid.', $index));
            }

            if (isset($seenKids[$kid])) {
                throw new RuntimeException(sprintf('OIDC signing kid [%s] is duplicated.', $kid));
            }

            if ($alg !== 'RS256') {
                throw new RuntimeException(sprintf('Unsupported OIDC signing algorithm [%s].', $alg));
            }

            if ($publicKeyPath === null) {
                throw new RuntimeException(sprintf('OIDC signing key [%s] is missing a public key path.', $kid));
            }

            $keys[] = [
                'kid' => $kid,
                'alg' => $alg,
                'private_key_path' => $privateKeyPath,
                'public_key_path' => $publicKeyPath,
                'published' => (bool) ($key['published'] ?? true),
                'active' => (bool) ($key['active'] ?? false),
            ];
            $seenKids[$kid] = true;
        }

        return $keys;
    }

    private function privateKeyPem(array $key): string
    {
        $path = $key['private_key_path'];

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException('OIDC signing private key path is missing or invalid.');
        }

        $pem = file_get_contents($path);

        if (! is_string($pem) || trim($pem) === '') {
            throw new RuntimeException('OIDC signing private key file is unreadable.');
        }

        return $pem;
    }

    private function publicKeyDetails(array $key): array
    {
        $publicKey = $this->publicKeyResource($key);

        try {
            $details = openssl_pkey_get_details($publicKey);

            if (! is_array($details)) {
                throw new RuntimeException('OIDC signing public key details are unavailable.');
            }

            return $details;
        } finally {
            openssl_free_key($publicKey);
        }
    }

    private function publicKeyResource(array $key): OpenSSLAsymmetricKey
    {
        $path = $key['public_key_path'];

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException('OIDC signing public key path is missing or invalid.');
        }

        $pem = file_get_contents($path);

        if (! is_string($pem) || trim($pem) === '') {
            throw new RuntimeException('OIDC signing public key file is unreadable.');
        }

        $publicKey = openssl_pkey_get_public($pem);

        if (! $publicKey instanceof \OpenSSLAsymmetricKey) {
            throw new RuntimeException('OIDC signing public key could not be loaded.');
        }

        return $publicKey;
    }

    private function normalizePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $normalized = trim($path);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<int, array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     active?: bool
     * }> $configuredKeys
     */
    private function configuredActiveKid(array $configuredKeys): string
    {
        $configuredRegistry = config('oidc.signing.keys', []);
        $activeKid = trim((string) config('oidc.signing.active_kid', ''));

        if ((! is_array($configuredRegistry) || $configuredRegistry === []) && config('oidc.signing.kid') !== null) {
            return trim((string) config('oidc.signing.kid', ''));
        }

        if ($activeKid === '') {
            return '';
        }

        foreach ($configuredKeys as $key) {
            if ($key['kid'] === $activeKid) {
                return $activeKid;
            }
        }

        return $activeKid;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
