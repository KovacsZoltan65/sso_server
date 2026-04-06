<?php

namespace App\Services\OAuth;

use RuntimeException;

class OidcSigningKeyService
{
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
    public function publicJwk(): array
    {
        $details = $this->publicKeyDetails();
        $rsa = $details['rsa'] ?? null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            throw new RuntimeException('OIDC signing public key must be an RSA key.');
        }

        return [
            'kty' => 'RSA',
            'kid' => $this->kid(),
            'use' => 'sig',
            'alg' => $this->algorithm(),
            'n' => $this->base64UrlEncode($rsa['n']),
            'e' => $this->base64UrlEncode($rsa['e']),
        ];
    }

    public function algorithm(): string
    {
        $alg = trim((string) config('oidc.signing.alg', 'RS256'));

        if ($alg !== 'RS256') {
            throw new RuntimeException(sprintf('Unsupported OIDC signing algorithm [%s].', $alg));
        }

        return $alg;
    }

    public function kid(): string
    {
        $kid = trim((string) config('oidc.signing.kid', ''));

        if ($kid === '') {
            throw new RuntimeException('OIDC signing kid is missing.');
        }

        return $kid;
    }

    public function sign(string $input): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKeyPem());

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

    public function verify(string $input, string $signature): bool
    {
        $publicKey = $this->publicKeyResource();

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

    private function privateKeyPem(): string
    {
        $path = trim((string) config('oidc.signing.private_key_path', ''));

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('OIDC signing private key path is missing or invalid.');
        }

        $pem = file_get_contents($path);

        if (! is_string($pem) || trim($pem) === '') {
            throw new RuntimeException('OIDC signing private key file is unreadable.');
        }

        return $pem;
    }

    private function publicKeyDetails(): array
    {
        $publicKey = $this->publicKeyResource();

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

    private function publicKeyResource(): \OpenSSLAsymmetricKey
    {
        $path = trim((string) config('oidc.signing.public_key_path', ''));

        if ($path === '' || ! is_file($path)) {
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

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
