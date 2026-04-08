<?php

use App\Services\OAuth\OidcKeyRotationService;
use App\Services\OAuth\RememberedConsentInvalidationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('oauth:invalidate-remembered-consents {--policy-version=} {--old-version=}', function () {
    $newVersion = trim((string) ($this->option('policy-version') ?: config('services.oauth.consent_policy_version')));
    $oldVersion = $this->option('old-version');

    if ($newVersion === '') {
        $this->error('A valid policy version is required.');

        return 1;
    }

    $result = app(RememberedConsentInvalidationService::class)->invalidateForPolicyVersionChange(
        $newVersion,
        $oldVersion !== null ? trim((string) $oldVersion) : null,
    );

    $this->info(sprintf(
        'Invalidated %d remembered consent grant(s) for policy version [%s].',
        $result['affected_count'],
        $result['policy_version'],
    ));

    return 0;
})->purpose('Invalidate remembered consent grants for a consent policy version change');

Artisan::command('oidc:keys:list', function () {
    $rotationService = app(OidcKeyRotationService::class);
    $keys = $rotationService->listKeys();

    if ($keys === []) {
        $this->warn('No OIDC signing keys are configured.');

        return 0;
    }

    $this->table(
        ['kid', 'status', 'alg', 'activated_at', 'retiring_since', 'disable_eligible', 'disable_reason', 'public_key_path'],
        array_map(
            static function (array $key): array {
                $eligibility = app(\App\Services\OAuth\OidcSigningKeyService::class)
                    ->getDisableEligibility((string) ($key['kid'] ?? ''));

                return [
                    $key['kid'] ?? '',
                    $key['status'] ?? '',
                    $key['alg'] ?? '',
                    $key['activated_at'] ?? '',
                    $key['retiring_since'] ?? '',
                    $eligibility['eligible'] ? 'yes' : 'no',
                    $eligibility['reason'],
                    $key['public_key_path'] ?? '',
                ];
            },
            $keys,
        ),
    );

    return 0;
})->purpose('List configured OIDC signing keys and lifecycle states');

Artisan::command('oidc:keys:rotate {kid?} {--activate=} {--disable=}', function () {
    $rotationService = app(OidcKeyRotationService::class);
    $activateKid = trim((string) ($this->option('activate') ?? ''));
    $disableKid = trim((string) ($this->option('disable') ?? ''));

    if ($activateKid !== '' && $disableKid !== '') {
        $this->error('Use only one key lifecycle action at a time.');

        return 1;
    }

    if ($activateKid !== '') {
        $key = $rotationService->activateKey($activateKid);
        $this->info(sprintf('Activated OIDC signing key [%s].', $key['kid']));

        return 0;
    }

    if ($disableKid !== '') {
        try {
            $key = $rotationService->disableKey($disableKid);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->info(sprintf('Disabled OIDC signing key [%s].', $key['kid']));

        return 0;
    }

    $result = $rotationService->rotate(trim((string) ($this->argument('kid') ?? '')) ?: null);
    $this->info(sprintf(
        'Rotated OIDC signing key from [%s] to [%s].',
        $result['previous_active_kid'] ?? 'none',
        $result['new_key']['kid'] ?? 'unknown',
    ));

    return 0;
})->purpose('Rotate OIDC signing keys or perform a controlled key lifecycle action');

Schedule::command('activitylog:clean')->daily();
