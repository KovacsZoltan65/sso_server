<?php

namespace App\Services\OAuth;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Str;
use RuntimeException;

class OidcKeyRotationService
{
    public function __construct(
        private readonly OidcSigningKeyService $signingKeyService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listKeys(): array
    {
        return $this->signingKeyService->getConfiguredKeys();
    }

    /**
     * @return array<string, mixed>
     */
    public function createNewKey(?string $kid = null, ?string $directory = null): array
    {
        $kid = $this->normalizeKid($kid ?: 'oidc-signing-'.now()->format('YmdHis').'-'.strtolower(Str::random(8)));
        $keys = $this->readRegistryForMutation();

        if ($this->findKeyIndex($keys, $kid) !== null) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] already exists.', $kid));
        }

        $directory = $this->normalizeDirectory($directory ?: config('oidc.signing.key_directory'));
        $this->ensureDirectoryExists($directory);

        $opensslOptions = array_filter([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $this->opensslConfigPath(),
        ], static fn (mixed $value): bool => $value !== null);

        $resource = openssl_pkey_new($opensslOptions);

        if ($resource === false) {
            throw new RuntimeException('OIDC signing key generation failed.');
        }

        if (! openssl_pkey_export($resource, $privatePem, null, $opensslOptions)) {
            throw new RuntimeException('OIDC signing private key export failed.');
        }

        $details = openssl_pkey_get_details($resource);

        if (! is_array($details) || ! is_string($details['key'] ?? null)) {
            throw new RuntimeException('OIDC signing public key export failed.');
        }

        $privateKeyPath = $directory.DIRECTORY_SEPARATOR.$kid.'.private.pem';
        $publicKeyPath = $directory.DIRECTORY_SEPARATOR.$kid.'.public.pem';

        file_put_contents($privateKeyPath, $privatePem);
        file_put_contents($publicKeyPath, $details['key']);
        @chmod($privateKeyPath, 0600);
        @chmod($publicKeyPath, 0644);

        $key = [
            'kid' => $kid,
            'status' => OidcSigningKeyService::STATUS_PUBLISHED,
            'alg' => 'RS256',
            'private_key_path' => $privateKeyPath,
            'public_key_path' => $publicKeyPath,
            'activated_at' => null,
            'retiring_since' => null,
            'disabled_at' => null,
        ];

        $keys[] = $key;
        $this->writeRegistry($keys);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.keys.created',
            description: 'OIDC signing key created in published state.',
            properties: [
                'kid' => $kid,
                'status' => OidcSigningKeyService::STATUS_PUBLISHED,
            ],
        );

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function activateKey(string $kid): array
    {
        $kid = $this->normalizeKid($kid);
        $keys = $this->readRegistryForMutation();
        $targetIndex = $this->findKeyIndex($keys, $kid);

        if ($targetIndex === null) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] is not configured.', $kid));
        }

        $target = $keys[$targetIndex];

        if (($target['status'] ?? null) === OidcSigningKeyService::STATUS_DISABLED) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] is disabled and cannot be activated.', $kid));
        }

        if (trim((string) ($target['private_key_path'] ?? '')) === '') {
            throw new RuntimeException(sprintf('OIDC signing key [%s] cannot be activated without a private key path.', $kid));
        }

        $previousActiveKid = null;
        $now = now()->toIso8601String();

        foreach ($keys as $index => $key) {
            if (($key['status'] ?? null) === OidcSigningKeyService::STATUS_ACTIVE && $key['kid'] !== $kid) {
                $previousActiveKid = (string) $key['kid'];
                $keys[$index]['status'] = OidcSigningKeyService::STATUS_RETIRING;
                $keys[$index]['retiring_since'] = $now;
                $keys[$index]['disabled_at'] = null;
                unset($keys[$index]['active']);
                unset($keys[$index]['published']);

                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.keys.retiring_started',
                    description: 'OIDC signing key retiring grace period started.',
                    properties: [
                        'kid' => $previousActiveKid,
                        'status' => OidcSigningKeyService::STATUS_RETIRING,
                        'retiring_since' => $now,
                        'grace_period_seconds' => $this->signingKeyService->getDisableEligibility($previousActiveKid)['grace_period_seconds'],
                    ],
                );
            }
        }

        $keys[$targetIndex]['status'] = OidcSigningKeyService::STATUS_ACTIVE;
        $keys[$targetIndex]['activated_at'] = $now;
        $keys[$targetIndex]['retiring_since'] = null;
        $keys[$targetIndex]['disabled_at'] = null;
        unset($keys[$targetIndex]['active']);
        unset($keys[$targetIndex]['published']);

        $this->writeRegistry($keys);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.keys.activated',
            description: 'OIDC signing key activated.',
            properties: [
                'kid' => $kid,
                'old_value' => $previousActiveKid,
                'new_value' => $kid,
                'status' => OidcSigningKeyService::STATUS_ACTIVE,
            ],
        );

        return $keys[$targetIndex];
    }

    /**
     * @return array<string, mixed>
     */
    public function retireKey(string $kid): array
    {
        return $this->transitionKey($kid, OidcSigningKeyService::STATUS_PUBLISHED, OidcSigningKeyService::STATUS_RETIRING, 'oauth.keys.retiring_started', 'OIDC signing key retiring grace period started.');
    }

    /**
     * @return array<string, mixed>
     */
    public function disableKey(string $kid): array
    {
        $eligibility = $this->signingKeyService->getDisableEligibility($kid);

        if (! $eligibility['eligible']) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.keys.disable_blocked',
                description: 'OIDC signing key disable blocked by lifecycle guard.',
                properties: [
                    'kid' => $eligibility['kid'],
                    'status' => $eligibility['status'],
                    'disable_eligible' => false,
                    'disable_reason' => $eligibility['reason'],
                    'retiring_since' => $eligibility['retiring_since'],
                    'grace_period_seconds' => $eligibility['grace_period_seconds'],
                    'eligible_at' => $eligibility['eligible_at'],
                ],
            );

            throw new RuntimeException(sprintf('OIDC signing key [%s] cannot be disabled: %s.', $kid, $eligibility['reason']));
        }

        return $this->transitionKey($kid, OidcSigningKeyService::STATUS_RETIRING, OidcSigningKeyService::STATUS_DISABLED, 'oauth.keys.disabled_after_grace', 'OIDC signing key disabled after grace period.');
    }

    /**
     * @return array{new_key: array<string, mixed>, previous_active_kid: string|null}
     */
    public function rotate(?string $kid = null): array
    {
        $previousActiveKid = (string) $this->signingKeyService->getActiveSigningKey()['kid'];
        $newKey = $this->createNewKey($kid);
        $activatedKey = $this->activateKey((string) $newKey['kid']);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.keys.rotation_executed',
            description: 'OIDC signing key rotation workflow executed.',
            properties: [
                'kid' => $activatedKey['kid'],
                'old_value' => $previousActiveKid,
                'new_value' => $activatedKey['kid'],
                'status' => OidcSigningKeyService::STATUS_ACTIVE,
            ],
        );

        return [
            'new_key' => $activatedKey,
            'previous_active_kid' => $previousActiveKid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionKey(string $kid, string $fromStatus, string $toStatus, string $event, string $description): array
    {
        $kid = $this->normalizeKid($kid);
        $keys = $this->readRegistryForMutation();
        $targetIndex = $this->findKeyIndex($keys, $kid);

        if ($targetIndex === null) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] is not configured.', $kid));
        }

        $currentStatus = (string) ($keys[$targetIndex]['status'] ?? '');

        if ($currentStatus !== $fromStatus && $currentStatus !== $toStatus) {
            throw new RuntimeException(sprintf('OIDC signing key [%s] cannot transition from [%s] to [%s].', $kid, $currentStatus, $toStatus));
        }

        $keys[$targetIndex]['status'] = $toStatus;

        if ($toStatus === OidcSigningKeyService::STATUS_RETIRING) {
            $keys[$targetIndex]['retiring_since'] = now()->toIso8601String();
            $keys[$targetIndex]['disabled_at'] = null;
        }

        if ($toStatus === OidcSigningKeyService::STATUS_DISABLED) {
            $keys[$targetIndex]['disabled_at'] = now()->toIso8601String();
        }

        unset($keys[$targetIndex]['active']);
        unset($keys[$targetIndex]['published']);
        $this->writeRegistry($keys);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: $event,
            description: $description,
            properties: [
                'kid' => $kid,
                'old_value' => $currentStatus,
                'new_value' => $toStatus,
                'status' => $toStatus,
                'retiring_since' => $keys[$targetIndex]['retiring_since'] ?? null,
                'disabled_at' => $keys[$targetIndex]['disabled_at'] ?? null,
            ],
        );

        return $keys[$targetIndex];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRegistryForMutation(): array
    {
        return array_map(
            fn (array $key): array => $this->normalizeMutableKey($key),
            $this->signingKeyService->getConfiguredKeys(),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     */
    private function writeRegistry(array $keys): void
    {
        $registryPath = $this->normalizePath(config('oidc.signing.registry_path'));
        $directory = dirname($registryPath);
        $this->ensureDirectoryExists($directory);

        $payload = json_encode(['keys' => array_values($keys)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            throw new RuntimeException('OIDC signing key registry encoding failed.');
        }

        file_put_contents($registryPath, $payload.PHP_EOL);
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     */
    private function findKeyIndex(array $keys, string $kid): ?int
    {
        foreach ($keys as $index => $key) {
            if (($key['kid'] ?? null) === $kid) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $key
     * @return array<string, mixed>
     */
    private function normalizeMutableKey(array $key): array
    {
        return [
            'kid' => (string) $key['kid'],
            'status' => (string) $key['status'],
            'alg' => (string) $key['alg'],
            'private_key_path' => $key['private_key_path'] ?? null,
            'public_key_path' => (string) $key['public_key_path'],
            'activated_at' => $key['activated_at'] ?? null,
            'retiring_since' => $key['retiring_since'] ?? null,
            'disabled_at' => $key['disabled_at'] ?? null,
        ];
    }

    private function normalizeKid(string $kid): string
    {
        $kid = trim($kid);

        if ($kid === '') {
            throw new RuntimeException('OIDC signing kid is missing.');
        }

        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $kid)) {
            throw new RuntimeException('OIDC signing kid contains unsupported characters.');
        }

        return $kid;
    }

    private function normalizeDirectory(mixed $directory): string
    {
        $directory = trim((string) $directory);

        if ($directory === '') {
            throw new RuntimeException('OIDC signing key directory is missing.');
        }

        return $directory;
    }

    private function normalizePath(mixed $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            throw new RuntimeException('OIDC signing key registry path is missing.');
        }

        return $path;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('OIDC signing key directory [%s] could not be created.', $directory));
        }
    }

    private function opensslConfigPath(): ?string
    {
        $configured = $this->normalizeOptionalPath(config('oidc.signing.openssl_config_path'));

        if ($configured !== null && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            getenv('OPENSSL_CONF') ?: null,
            'C:\\wamp64\\bin\\php\\php'.PHP_VERSION.'\\extras\\ssl\\openssl.cnf',
            'C:\\wamp64\\bin\\apache\\apache2.4.65\\conf\\openssl.cnf',
            'C:\\Program Files\\Common Files\\SSL\\openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            $path = $this->normalizeOptionalPath($candidate);

            if ($path !== null && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeOptionalPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        return $path !== '' ? $path : null;
    }
}
