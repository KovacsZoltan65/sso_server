<?php

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

Schedule::command('activitylog:clean')->daily();
