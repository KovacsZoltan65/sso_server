<?php

use App\Models\SsoClient;
use Database\Seeders\SsoClientSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the default sso clients with expected identifiers and scopes', function () {
    $this->seed(SsoClientSeeder::class);

    $adminConsole = SsoClient::query()->where('client_id', 'client_admin_console')->first();
    $customerPortal = SsoClient::query()->where('client_id', 'client_customer_portal')->first();
    $opsDashboard = SsoClient::query()->where('client_id', 'client_ops_dashboard')->first();

    expect($adminConsole)->not->toBeNull();
    expect($customerPortal)->not->toBeNull();
    expect($opsDashboard)->not->toBeNull();

    expect($adminConsole->name)->toBe('SSO Admin Console');
    expect($adminConsole->redirect_uris)->toBe([
        'https://admin.sso.test/auth/callback',
        'https://admin.sso.test/auth/silent-renew',
    ]);
    expect($adminConsole->scopes)->toBe(['openid', 'profile', 'email']);
    expect($adminConsole->is_active)->toBeTrue();

    expect($customerPortal->scopes)->toBe(['openid', 'profile', 'email', 'offline_access']);
    expect($opsDashboard->is_active)->toBeFalse();
});

it('stores seeded client secrets as hashes', function () {
    $this->seed(SsoClientSeeder::class);

    $adminConsole = SsoClient::query()->where('client_id', 'client_admin_console')->firstOrFail();

    expect($adminConsole->client_secret_hash)->not->toBe('dev-admin-console-secret');
    expect(Hash::check('dev-admin-console-secret', $adminConsole->client_secret_hash))->toBeTrue();
});
