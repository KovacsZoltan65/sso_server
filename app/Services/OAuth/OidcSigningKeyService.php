<?php

namespace App\Services\OAuth;

use Illuminate\Support\Carbon;
use OpenSSLAsymmetricKey;
use RuntimeException;

class OidcSigningKeyService
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRING = 'retiring';
    public const STATUS_DISABLED = 'disabled';

    /**
     * @return array<int, string>
     */
    public static function lifecycleStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PUBLISHED,
            self::STATUS_RETIRING,
            self::STATUS_DISABLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function verificationStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PUBLISHED,
            self::STATUS_RETIRING,
        ];
    }

    /**
     * @return array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     status: string,
     *     activated_at: string|null,
     *     retiring_since: string|null,
     *     disabled_at: string|null
     * }
     */
    public function getActiveSigningKey(): array
    {
        $configuredKeys = $this->configuredKeys();

        $activeKeys = array_values(array_filter(
            $configuredKeys,
            static fn (array $key): bool => $key['status'] === self::STATUS_ACTIVE,
        ));

        if (count($activeKeys) !== 1) {
            throw new RuntimeException('OIDC signing key configuration must define exactly one active key.');
        }

        $activeKey = $activeKeys[0];
        $activeKid = $this->configuredActiveKid($configuredKeys);

        if ($activeKid !== '' && $activeKid !== $activeKey['kid']) {
            throw new RuntimeException(sprintf(
                'OIDC signing active_kid [%s] does not match lifecycle active key [%s].',
                $activeKid,
                $activeKey['kid'],
            ));
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
     *     published: bool,
     *     status: string,
     *     activated_at: string|null,
     *     retiring_since: string|null,
     *     disabled_at: string|null
     * }>
     */
    public function getPublishedVerificationKeys(): array
    {
        return array_values(array_filter(
            $this->configuredKeys(),
            static fn (array $key): bool => in_array($key['status'], self::verificationStatuses(), true),
        ));
    }

    /**
     * @return array<int, array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     status: string,
     *     activated_at: string|null,
     *     retiring_since: string|null,
     *     disabled_at: string|null
     * }>
     */
    public function getRetiringKeys(): array
    {
        return array_values(array_filter(
            $this->configuredKeys(),
            static fn (array $key): bool => $key['status'] === self::STATUS_RETIRING,
        ));
    }

    public function validateKeyConfiguration(): void
    {
        $this->configuredKeys();
        $this->getActiveSigningKey();
    }

    /**
     * @return array<int, array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     status: string,
     *     activated_at: string|null,
     *     retiring_since: string|null,
     *     disabled_at: string|null
     * }>
     */
    public function getConfiguredKeys(): array
    {
        return $this->configuredKeys();
    }

    public function canDisableKey(string $kid): bool
    {
        return (bool) $this->getDisableEligibility($kid)['eligible'];
    }

    /**
     * @return array{
     *     kid: string,
     *     status: string|null,
     *     eligible: bool,
     *     reason: string,
     *     retiring_since: string|null,
     *     grace_period_seconds: int,
     *     eligible_at: string|null
     * }
     */
    public function getDisableEligibility(string $kid): array
    {
        $normalizedKid = trim($kid);
        $gracePeriod = $this->retiringGracePeriodSeconds();

        if ($normalizedKid === '') {
            return $this->disableEligibility($normalizedKid, null, false, 'unknown_key', null, $gracePeriod, null);
        }

        $key = null;

        foreach ($this->configuredKeys() as $configuredKey) {
            if ($configuredKey['kid'] === $normalizedKid) {
                $key = $configuredKey;
                break;
            }
        }

        if ($key === null) {
            return $this->disableEligibility($normalizedKid, null, false, 'unknown_key', null, $gracePeriod, null);
        }

        $status = (string) $key['status'];
        $retiringSince = $this->getRetiringSince($normalizedKid);
        $eligibleAt = $retiringSince?->copy()->addSeconds($gracePeriod);

        if ($status === self::STATUS_ACTIVE) {
            return $this->disableEligibility($normalizedKid, $status, false, 'active_key_cannot_be_disabled', $retiringSince, $gracePeriod, $eligibleAt);
        }

        if ($status === self::STATUS_PUBLISHED) {
            return $this->disableEligibility($normalizedKid, $status, false, 'published_key_must_be_retired_first', $retiringSince, $gracePeriod, $eligibleAt);
        }

        if ($status === self::STATUS_DISABLED) {
            return $this->disableEligibility($normalizedKid, $status, true, 'key_already_disabled', $retiringSince, $gracePeriod, $eligibleAt);
        }

        if ($retiringSince === null || ! $this->isGracePeriodElapsed($normalizedKid)) {
            return $this->disableEligibility($normalizedKid, $status, false, 'grace_period_not_elapsed', $retiringSince, $gracePeriod, $eligibleAt);
        }

        return $this->disableEligibility($normalizedKid, $status, true, 'retiring_key_can_be_disabled', $retiringSince, $gracePeriod, $eligibleAt);
    }

    public function getRetiringSince(string $kid): ?Carbon
    {
        $normalizedKid = trim($kid);

        foreach ($this->configuredKeys() as $key) {
            if ($key['kid'] !== $normalizedKid) {
                continue;
            }

            $retiringSince = $key['retiring_since'] ?? null;

            if (! is_string($retiringSince) || trim($retiringSince) === '') {
                return null;
            }

            try {
                return Carbon::parse($retiringSince);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function isGracePeriodElapsed(string $kid): bool
    {
        $retiringSince = $this->getRetiringSince($kid);

        if ($retiringSince === null) {
            return false;
        }

        return $retiringSince->copy()->addSeconds($this->retiringGracePeriodSeconds())->lessThanOrEqualTo(now());
    }

    /**
     * @return array{
     *     kid: string,
     *     alg: string,
     *     private_key_path: string|null,
     *     public_key_path: string,
     *     published: bool,
     *     status: string,
     *     activated_at: string|null,
     *     retiring_since: string|null,
     *     disabled_at: string|null
     * }
     */
    public function findKeyByKid(string $kid): array
    {
        $normalizedKid = trim($kid);

        if ($normalizedKid === '') {
            throw new RuntimeException('OIDC signing kid is missing.');
        }

        foreach ($this->getPublishedVerificationKeys() as $key) {
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

        if (($signingKey['status'] ?? self::STATUS_ACTIVE) !== self::STATUS_ACTIVE) {
            throw new RuntimeException('OIDC signing can only use the active lifecycle key.');
        }

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
        $configured = $this->configuredRegistryKeys();

        if ((! is_array($configured) || $configured === [])
            && (config('oidc.signing.kid') !== null || config('oidc.signing.public_key_path') !== null)
        ) {
            $configured = [[
                'kid' => config('oidc.signing.kid'),
                'alg' => config('oidc.signing.alg', 'RS256'),
                'private_key_path' => config('oidc.signing.private_key_path'),
                'public_key_path' => config('oidc.signing.public_key_path'),
                'published' => true,
                'status' => self::STATUS_ACTIVE,
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
            $status = $this->normalizeStatus($key, $kid);

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
                'published' => in_array($status, self::verificationStatuses(), true),
                'active' => (bool) ($key['active'] ?? false),
                'status' => $status,
                'activated_at' => $this->normalizeTimestamp($key['activated_at'] ?? null),
                'retiring_since' => $this->normalizeTimestamp($key['retiring_since'] ?? null),
                'disabled_at' => $this->normalizeTimestamp($key['disabled_at'] ?? null),
            ];
            $seenKids[$kid] = true;
        }

        $activeKeys = array_values(array_filter(
            $keys,
            static fn (array $key): bool => $key['status'] === self::STATUS_ACTIVE,
        ));

        if (count($activeKeys) !== 1) {
            throw new RuntimeException('OIDC signing key configuration must define exactly one active key.');
        }

        $activeKid = $this->configuredActiveKid($keys);

        if ($activeKid !== '' && $activeKid !== $activeKeys[0]['kid']) {
            throw new RuntimeException(sprintf(
                'OIDC signing active_kid [%s] does not match lifecycle active key [%s].',
                $activeKid,
                $activeKeys[0]['kid'],
            ));
        }

        return $keys;
    }

    private function configuredRegistryKeys(): array
    {
        $registryPath = $this->normalizePath(config('oidc.signing.registry_path'));

        if ($this->usesExternalRegistry()) {
            $json = file_get_contents($registryPath);

            if (! is_string($json) || trim($json) === '') {
                throw new RuntimeException('OIDC signing key registry file is unreadable.');
            }

            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new RuntimeException('OIDC signing key registry file is invalid JSON.');
            }

            $keys = $decoded['keys'] ?? $decoded;

            if (! is_array($keys)) {
                throw new RuntimeException('OIDC signing key registry file does not contain a keys array.');
            }

            return $keys;
        }

        $configured = config('oidc.signing.keys', []);

        return is_array($configured) ? $configured : [];
    }

    private function normalizeStatus(array $key, string $kid): string
    {
        $status = trim((string) ($key['status'] ?? ''));

        if ($status === '') {
            $activeKid = trim((string) config('oidc.signing.active_kid', ''));
            $published = (bool) ($key['published'] ?? true);

            if ((bool) ($key['active'] ?? false) || ($activeKid !== '' && $activeKid === $kid)) {
                $status = self::STATUS_ACTIVE;
            } elseif (! $published) {
                $status = self::STATUS_DISABLED;
            } else {
                $status = self::STATUS_PUBLISHED;
            }
        }

        if (! in_array($status, self::lifecycleStatuses(), true)) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] has invalid lifecycle status [%s].', $kid, $status));
        }

        return $status;
    }

    private function retiringGracePeriodSeconds(): int
    {
        return max(60, (int) config('oidc.signing.retiring_grace_period_seconds', 86400));
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            throw new RuntimeException(sprintf('OIDC signing key lifecycle timestamp [%s] is invalid.', $value));
        }
    }

    private function disableEligibility(
        string $kid,
        ?string $status,
        bool $eligible,
        string $reason,
        ?Carbon $retiringSince,
        int $gracePeriodSeconds,
        ?Carbon $eligibleAt,
    ): array {
        return [
            'kid' => $kid,
            'status' => $status,
            'eligible' => $eligible,
            'reason' => $reason,
            'retiring_since' => $retiringSince?->toIso8601String(),
            'grace_period_seconds' => $gracePeriodSeconds,
            'eligible_at' => $eligibleAt?->toIso8601String(),
        ];
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
        if ($this->usesExternalRegistry()) {
            return '';
        }

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

    private function usesExternalRegistry(): bool
    {
        $registryPath = $this->normalizePath(config('oidc.signing.registry_path'));

        return is_string($registryPath) && is_file($registryPath);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
