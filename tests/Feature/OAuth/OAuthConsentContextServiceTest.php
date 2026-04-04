<?php

use App\Exceptions\OAuth\OAuthConsentContextNotFoundException;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use App\Models\User;
use App\Services\OAuth\OAuthConsentContextService;

beforeEach(function (): void {
    $this->withoutVite();

    Scope::factory()->create(['name' => 'OpenID', 'code' => 'openid', 'is_active' => true]);
    Scope::factory()->create(['name' => 'Profile', 'code' => 'profile', 'is_active' => true]);
});

function consentClient(array $policyOverrides = [], array $clientOverrides = []): SsoClient
{
    $policy = TokenPolicy::query()->create(array_merge([
        'name' => 'Consent Policy',
        'code' => 'consent.policy.'.fake()->unique()->numerify('###'),
        'description' => 'Consent context policy.',
        'access_token_ttl_minutes' => 60,
        'refresh_token_ttl_minutes' => 1440,
        'refresh_token_rotation_enabled' => true,
        'pkce_required' => true,
        'reuse_refresh_token_forbidden' => true,
        'is_default' => false,
        'is_active' => true,
    ], $policyOverrides));

    $client = SsoClient::factory()->create(array_merge([
        'name' => 'Portal Client',
        'client_id' => 'client_portal_'.fake()->unique()->numerify('###'),
        'token_policy_id' => $policy->id,
        'is_active' => true,
    ], $clientOverrides));

    $client->redirectUris()->create([
        'uri' => 'https://portal.example.com/callback',
        'uri_hash' => hash('sha256', 'https://portal.example.com/callback'),
        'is_primary' => true,
    ]);

    $client->scopes()->sync(Scope::query()->whereIn('code', ['openid', 'profile'])->pluck('id')->all());

    return $client->fresh(['redirectUris', 'scopes', 'tokenPolicy']);
}

function consentPayload(SsoClient $client, array $overrides = []): array
{
    return array_merge([
        'response_type' => 'code',
        'client_id' => $client->client_id,
        'redirect_uri' => 'https://portal.example.com/callback',
        'scope' => 'openid profile',
        'state' => 'consent-state',
        'code_challenge' => 'test-code-challenge',
        'code_challenge_method' => 'S256',
    ], $overrides);
}

it('creates and stores a consent context in the current session', function (): void {
    $user = User::factory()->create();
    $client = consentClient();
    $service = app(OAuthConsentContextService::class);

    $context = $service->createContext($user, consentPayload($client));

    expect($context->consentToken)->toMatch('/^[a-f0-9]{64}$/')
        ->and($context->clientId)->toBe($client->client_id)
        ->and($context->clientDbId)->toBe($client->id)
        ->and($context->clientDisplayName)->toBe('Portal Client')
        ->and($context->clientDescription)->toBeNull()
        ->and($context->redirectUri)->toBe('https://portal.example.com/callback')
        ->and($context->requestedScopes)->toBe(['openid', 'profile'])
        ->and($context->state)->toBe('consent-state')
        ->and($context->responseType)->toBe('code')
        ->and($context->codeChallenge)->toBe('test-code-challenge')
        ->and($context->codeChallengeMethod)->toBe('S256')
        ->and($context->userId)->toBe($user->id);

    $storedContexts = session('oauth.consent_contexts', []);

    expect($storedContexts)->toHaveKey($context->consentToken)
        ->and($storedContexts[$context->consentToken]['client_id'] ?? null)->toBe($client->client_id)
        ->and($storedContexts[$context->consentToken]['user_id'] ?? null)->toBe($user->id)
        ->and($storedContexts[$context->consentToken]['requested_scopes'] ?? null)->toBe(['openid', 'profile']);
});

it('retrieves a stored consent context by token with consistent data', function (): void {
    $user = User::factory()->create();
    $client = consentClient();
    $service = app(OAuthConsentContextService::class);
    $created = $service->createContext($user, consentPayload($client, ['state' => 'return-state']));

    $retrieved = $service->getContextByToken($created->consentToken);

    expect($retrieved->consentToken)->toBe($created->consentToken)
        ->and($retrieved->clientId)->toBe($created->clientId)
        ->and($retrieved->clientDbId)->toBe($created->clientDbId)
        ->and($retrieved->redirectUri)->toBe($created->redirectUri)
        ->and($retrieved->requestedScopes)->toBe($created->requestedScopes)
        ->and($retrieved->state)->toBe('return-state')
        ->and($retrieved->userId)->toBe($user->id);
});

it('fails explicitly when the consent token is missing from the session', function (): void {
    $service = app(OAuthConsentContextService::class);

    expect(fn () => $service->getContextByToken('missing-token'))
        ->toThrow(OAuthConsentContextNotFoundException::class, 'The consent context is missing, expired, or no longer available.');
});

it('rejects expired consent contexts and cleans them up from the session', function (): void {
    $user = User::factory()->create();
    $client = consentClient();
    $service = app(OAuthConsentContextService::class);
    $context = $service->createContext($user, consentPayload($client));

    $storedContexts = session('oauth.consent_contexts', []);
    $storedContexts[$context->consentToken]['expires_at'] = now()->subMinute()->toIso8601String();
    session()->put('oauth.consent_contexts', $storedContexts);

    expect(fn () => $service->getContextByToken($context->consentToken))
        ->toThrow(OAuthConsentContextNotFoundException::class);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($context->consentToken)
        ->and($service->hasValidContext($context->consentToken))->toBeFalse();
});

it('invalidates a stored consent context explicitly', function (): void {
    $user = User::factory()->create();
    $client = consentClient();
    $service = app(OAuthConsentContextService::class);
    $context = $service->createContext($user, consentPayload($client));

    expect($service->hasValidContext($context->consentToken))->toBeTrue();

    $service->invalidateContext($context->consentToken);

    expect(session('oauth.consent_contexts', []))->not->toHaveKey($context->consentToken)
        ->and($service->hasValidContext($context->consentToken))->toBeFalse();
});

it('stores multiple consent contexts in the same session without overwriting each other', function (): void {
    $user = User::factory()->create();
    $client = consentClient();
    $service = app(OAuthConsentContextService::class);

    $first = $service->createContext($user, consentPayload($client, ['state' => 'first-state']));
    $second = $service->createContext($user, consentPayload($client, ['state' => 'second-state']));

    $storedContexts = session('oauth.consent_contexts', []);

    expect($first->consentToken)->not->toBe($second->consentToken)
        ->and($storedContexts)->toHaveCount(2)
        ->and($service->getContextByToken($first->consentToken)->state)->toBe('first-state')
        ->and($service->getContextByToken($second->consentToken)->state)->toBe('second-state');
});
