<?php

use App\Models\SsoClient;
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
    expect($client->redirect_uris)->toBe(['https://portal.example.com/callback']);
    expect($client->scopes)->toBe(['openid', 'profile', 'email']);
    expect($client->redirectUris)->toHaveCount(1);
    expect($client->redirectUris->first()?->uri)->toBe('https://portal.example.com/callback');
    expect($client->redirectUris->first()?->is_primary)->toBeTrue();
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

    expect($client->redirectUris)->toHaveCount(1);
    expect($client->scopes)->toHaveCount(3);
    expect($client->activeSecrets)->toHaveCount(1);
    expect($client->activeSecrets->first()?->is($secret))->toBeTrue();
});

it('stores only the hashed client secret', function () {
    $messages = [];

    $command = \Mockery::mock(\Illuminate\Console\Command::class);
    $command->shouldReceive('info')
        ->once()
        ->with('Portal client created.');
    $command->shouldReceive('warn')
        ->once()
        ->with('Client ID: portal-client');
    $command->shouldReceive('warn')
        ->once()
        ->withArgs(function (string $message) use (&$messages): bool {
            $messages[] = $message;

            return str_starts_with($message, 'Client Secret: ');
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
