<?php

declare(strict_types=1);

use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Illuminate\Support\Facades\Hash;

it('rate limits repeated oauth token requests with a standard json envelope', function (): void {
    $policy = TokenPolicy::factory()->create([
        'is_active' => true,
    ]);

    $client = SsoClient::factory()->create([
        'client_id' => 'portal-client',
        'is_active' => true,
        'token_policy_id' => $policy->id,
    ]);

    ClientSecret::query()->create([
        'sso_client_id' => $client->id,
        'name' => 'Initial secret',
        'secret_hash' => Hash::make('super-secret-value-123'),
        'last_four' => 'e123',
        'is_active' => true,
    ]);

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('oauth.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'client_secret' => 'super-secret-value-123',
            'code' => 'invalid-code',
            'redirect_uri' => 'https://portal.example.com/callback',
            'code_verifier' => 'plain-test-verifier-123456789',
        ])->assertStatus(422);
    }

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $client->client_id,
        'client_secret' => 'super-secret-value-123',
        'code' => 'invalid-code',
        'redirect_uri' => 'https://portal.example.com/callback',
        'code_verifier' => 'plain-test-verifier-123456789',
    ])
        ->assertStatus(429)
        ->assertExactJson([
            'message' => 'Too many attempts. Please retry later.',
            'data' => [],
            'meta' => [],
            'errors' => [],
        ]);
});
