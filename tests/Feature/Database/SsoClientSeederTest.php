<?php

use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Database\Seeders\SsoClientSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the portal client with the expected redirect uri and scopes', function () {
    $this->seed(SsoClientSeeder::class);

    $client = SsoClient::query()
        ->where('client_id', 'portal-client')
        ->with(['redirectUris', 'scopes', 'activeSecrets'])
        ->first();

    expect($client)->not->toBeNull();
    expect($client->name)->toBe('Portal Client');
    expect($client->is_active)->toBeTrue();
    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED);
    expect($client->is_first_party)->toBeTrue();
    expect($client->consent_bypass_allowed)->toBeFalse();
    expect($client->redirect_uris)->toBe([
        'http://sso-client.test/auth/sso/callback',
        'http://sso-client.test/auth/logout/return',
    ]);
    expect($client->frontchannel_logout_uri)->toBe('http://sso-client.test/auth/frontchannel-logout');
    expect($client->backchannel_logout_uri)->toBe('http://sso-client.test/auth/backchannel-logout');
    expect($client->scopes)->toBe(['openid', 'profile', 'email']);
    expect($client->redirectUris)->toHaveCount(2);
    expect($client->redirectUris->pluck('uri')->all())->toBe([
        'http://sso-client.test/auth/sso/callback',
        'http://sso-client.test/auth/logout/return',
    ]);
    expect($client->redirectUris->firstWhere('uri', 'http://sso-client.test/auth/sso/callback')?->is_primary)->toBeTrue();
    expect($client->redirectUris->firstWhere('uri', 'http://sso-client.test/auth/logout/return')?->is_primary)->toBeFalse();
    expect($client->getRelation('scopes')->pluck('code')->all())->toBe(['email', 'openid', 'profile']);
    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->activeSecrets->first()?->is_active)->toBeTrue();
});

it('is idempotent and does not create a second active secret automatically', function () {
    $this->seed(SsoClientSeeder::class);

    $client = SsoClient::query()->where('client_id', 'portal-client')->firstOrFail();
    $secret = $client->activeSecrets()->firstOrFail();

    $this->seed(SsoClientSeeder::class);

    $client->refresh()->load(['redirectUris', 'scopes', 'activeSecrets']);

    expect($client->redirectUris)->toHaveCount(2);
    expect($client->scopes)->toHaveCount(3);
    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->trust_tier)->toBe(SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED);
    expect($client->is_first_party)->toBeTrue();
    expect($client->consent_bypass_allowed)->toBeFalse();
    expect($client->frontchannel_logout_uri)->toBe('http://sso-client.test/auth/frontchannel-logout');
    expect($client->backchannel_logout_uri)->toBe('http://sso-client.test/auth/backchannel-logout');
    expect($client->activeSecrets->first()?->is($secret))->toBeTrue();
});

it('seeds the csharp aspnet demo client without duplicating it', function () {
    $policy = TokenPolicy::query()->create([
        'name' => 'Default Web Policy',
        'code' => 'default.web',
        'description' => 'Balanced defaults for standard confidential and first-party web clients.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 43200,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => false,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->seed(SsoClientSeeder::class);

    $client = SsoClient::query()
        ->where('client_id', 'csharp-aspnet-demo')
        ->with(['redirectUris', 'scopes', 'activeSecrets'])
        ->first();

    expect($client)->not->toBeNull();
    expect($client->name)->toBe('C# ASP.NET Demo');
    expect($client->client_type)->toBe(SsoClient::CLIENT_TYPE_CONFIDENTIAL);
    expect($client->is_active)->toBeTrue();
    expect($client->redirect_uris)->toBe(['http://localhost:5023/auth/callback']);
    expect($client->token_policy_id)->toBe($policy->id);
    expect($client->redirectUris)->toHaveCount(1);
    expect($client->redirectUris->first()?->uri)->toBe('http://localhost:5023/auth/callback');
    expect($client->redirectUris->first()?->uri_hash)->toBe(hash('sha256', 'http://localhost:5023/auth/callback'));
    expect($client->redirectUris->first()?->is_primary)->toBeTrue();
    expect($client->getRelation('scopes')->pluck('code')->sort()->values()->all())->toBe(['email', 'openid', 'profile']);
    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->activeSecrets->first()?->secret_hash)->not->toBeNull();

    $secret = $client->activeSecrets->first();

    $this->seed(SsoClientSeeder::class);

    $client->refresh()->load(['redirectUris', 'scopes', 'activeSecrets']);

    expect(SsoClient::query()->where('client_id', 'csharp-aspnet-demo')->count())->toBe(1);
    expect($client->redirectUris)->toHaveCount(1);
    expect($client->scopes)->toHaveCount(3);
    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->activeSecrets->first()?->is($secret))->toBeTrue();
});

it('does not create a csharp aspnet demo secret again when the client exists', function () {
    TokenPolicy::query()->create([
        'name' => 'Default Web Policy',
        'code' => 'default.web',
        'description' => 'Balanced defaults for standard confidential and first-party web clients.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 43200,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => false,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->seed(SsoClientSeeder::class);

    $client = SsoClient::query()
        ->where('client_id', 'csharp-aspnet-demo')
        ->with(['activeSecrets'])
        ->firstOrFail();

    $secret = $client->activeSecrets->first();

    $this->seed(SsoClientSeeder::class);

    $client->refresh()->load('activeSecrets');

    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->activeSecrets->first()?->is($secret))->toBeTrue();
});

it('still seeds the csharp aspnet demo client when no default token policy exists', function () {
    $this->seed(SsoClientSeeder::class);

    $client = SsoClient::query()
        ->where('client_id', 'csharp-aspnet-demo')
        ->with(['redirectUris', 'scopes', 'activeSecrets'])
        ->first();

    expect($client)->not->toBeNull();
    expect($client->token_policy_id)->toBeNull();
    expect($client->redirectUris)->toHaveCount(1);
    expect($client->scopes)->toHaveCount(3);
    expect($client->activeSecrets)->toHaveCount(1);
});

it('stores only the hashed client secret', function () {
    $messages = [];

    $command = \Mockery::mock(\Illuminate\Console\Command::class);
    $command->shouldReceive('line')->zeroOrMoreTimes();
    $command->shouldReceive('info')
        ->zeroOrMoreTimes();
    $command->shouldReceive('warn')
        ->zeroOrMoreTimes()
        ->withArgs(function (string $message) use (&$messages): bool {
            if (str_starts_with($message, 'Client Secret: ')) {
                $messages[] = $message;
            }

            return true;
        });

    $seeder = new SsoClientSeeder();
    $seeder->setContainer(app());
    $seeder->setCommand($command);
    $seeder->run();

    $client = SsoClient::query()->where('client_id', 'portal-client')->firstOrFail();
    $secret = $client->activeSecrets()->firstOrFail();

    $plainSecret = str($messages[0] ?? '')
        ->after('Client Secret: ')
        ->toString();

    expect($plainSecret)->not->toBe('');
    expect($secret->secret_hash)->not->toBe($plainSecret);
    expect(Hash::check($plainSecret, $secret->secret_hash))->toBeTrue();
    expect(Hash::check($plainSecret, $client->client_secret_hash))->toBeTrue();
});
