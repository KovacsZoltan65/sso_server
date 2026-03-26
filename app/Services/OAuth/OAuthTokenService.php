<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\Token;
use App\Models\TokenPolicy;
use App\Models\SsoClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthTokenService
{
    public function __construct(
        private readonly RedirectUriMatcher $redirectUriMatcher,
        private readonly PkceVerifier $pkceVerifier,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $plainCode = (string) $payload['code'];
        $authorizationCode = AuthorizationCode::query()
            ->with(['client', 'tokenPolicy', 'user'])
            ->where('code_hash', hash('sha256', $plainCode))
            ->first();

        if ($authorizationCode === null) {
            throw ValidationException::withMessages(['code' => 'The provided authorization code is invalid.']);
        }

        if ($authorizationCode->sso_client_id !== $client->id) {
            throw ValidationException::withMessages(['code' => 'The authorization code does not belong to this client.']);
        }

        if ($authorizationCode->consumed_at !== null || $authorizationCode->revoked_at !== null || $authorizationCode->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'The authorization code is expired, revoked, or already used.']);
        }

        $redirectUri = trim((string) $payload['redirect_uri']);
        if (! $this->redirectUriMatcher->matches($client, $redirectUri) || ! hash_equals($authorizationCode->redirect_uri, $redirectUri)) {
            throw ValidationException::withMessages(['redirect_uri' => 'The redirect URI does not match the authorization request.']);
        }

        $verifier = trim((string) ($payload['code_verifier'] ?? ''));
        if ($verifier === '') {
            throw ValidationException::withMessages(['code_verifier' => 'The code verifier field is required.']);
        }

        $this->pkceVerifier->verify($authorizationCode->code_challenge, $authorizationCode->code_challenge_method, $verifier);
        $policy = $this->resolvePolicy($client, $authorizationCode->tokenPolicy);

        return DB::transaction(function () use ($authorizationCode, $client, $policy, $ipAddress, $userAgent): array {
            $authorizationCode->forceFill(['consumed_at' => now()])->save();

            $issued = $this->issueTokenPair(
                client: $client,
                userId: $authorizationCode->user_id,
                policy: $policy,
                scopes: $authorizationCode->scopes ?? [],
                authorizationCodeId: $authorizationCode->id,
                parentTokenId: null,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            activity('oauth')
                ->performedOn($client)
                ->causedBy($authorizationCode->user)
                ->event('oauth.token.issued')
                ->withProperties(['grant_type' => 'authorization_code'])
                ->log('OAuth token pair issued from authorization code.');

            return $issued;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function refreshAccessToken(array $payload, ?string $ipAddress, ?string $userAgent): array
    {
        $client = $this->resolveClient((string) $payload['client_id']);
        $this->assertClientAuthentication($client, (string) ($payload['client_secret'] ?? ''));

        $plainRefreshToken = (string) $payload['refresh_token'];
        $token = Token::query()
            ->with('tokenPolicy')
            ->where('refresh_token_hash', hash('sha256', $plainRefreshToken))
            ->first();

        if ($token === null || $token->sso_client_id !== $client->id) {
            throw ValidationException::withMessages(['refresh_token' => 'The provided refresh token is invalid.']);
        }

        if ($token->refresh_token_revoked_at !== null || $token->refresh_token_expires_at === null || $token->refresh_token_expires_at->isPast()) {
            throw ValidationException::withMessages(['refresh_token' => 'The refresh token is expired or revoked.']);
        }

        $policy = $this->resolvePolicy($client, $token->tokenPolicy);

        return DB::transaction(function () use ($token, $client, $policy, $ipAddress, $userAgent): array {
            if ($policy->refresh_token_rotation_enabled || $policy->reuse_refresh_token_forbidden) {
                $token->forceFill([
                    'refresh_token_revoked_at' => now(),
                ])->save();
            }

            $issued = $this->issueTokenPair(
                client: $client,
                userId: $token->user_id,
                policy: $policy,
                scopes: $token->scopes ?? [],
                authorizationCodeId: null,
                parentTokenId: $token->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            activity('oauth')
                ->performedOn($client)
                ->causedBy($token->user)
                ->event('oauth.token.refreshed')
                ->withProperties(['parent_token_id' => $token->id])
                ->log('OAuth access token refreshed.');

            return $issued;
        });
    }

    private function resolveClient(string $clientId): SsoClient
    {
        /** @var SsoClient|null $client */
        $client = SsoClient::query()->with(['activeSecrets', 'tokenPolicy'])->where('client_id', $clientId)->where('is_active', true)->first();

        if ($client === null) {
            throw ValidationException::withMessages(['client_id' => 'The provided client is invalid or inactive.']);
        }

        return $client;
    }

    private function assertClientAuthentication(SsoClient $client, string $clientSecret): void
    {
        $activeSecrets = $client->activeSecrets()->get();

        if ($activeSecrets->isEmpty()) {
            return;
        }

        foreach ($activeSecrets as $secret) {
            if (Hash::check($clientSecret, $secret->secret_hash)) {
                return;
            }
        }

        throw ValidationException::withMessages(['client_secret' => 'The provided client secret is invalid.']);
    }

    private function resolvePolicy(SsoClient $client, ?TokenPolicy $policy = null): TokenPolicy
    {
        if ($policy instanceof TokenPolicy && $policy->is_active) {
            return $policy;
        }

        $resolved = TokenPolicy::query()
            ->when($client->token_policy_id !== null, fn ($query) => $query->whereKey($client->token_policy_id))
            ->when($client->token_policy_id === null, fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();

        if ($resolved === null) {
            throw ValidationException::withMessages(['client_id' => 'No active token policy is available for this client.']);
        }

        return $resolved;
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, mixed>
     */
    private function issueTokenPair(
        SsoClient $client,
        int $userId,
        TokenPolicy $policy,
        array $scopes,
        ?int $authorizationCodeId,
        ?int $parentTokenId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $plainAccessToken = Str::random(80);
        $plainRefreshToken = Str::random(80);

        $accessExpiresAt = now()->addMinutes($policy->access_token_ttl_minutes);
        $refreshExpiresAt = now()->addMinutes($policy->refresh_token_ttl_minutes);

        Token::query()->create([
            'sso_client_id' => $client->id,
            'user_id' => $userId,
            'token_policy_id' => $policy->id,
            'authorization_code_id' => $authorizationCodeId,
            'parent_token_id' => $parentTokenId,
            'access_token_hash' => hash('sha256', $plainAccessToken),
            'refresh_token_hash' => hash('sha256', $plainRefreshToken),
            'scopes' => array_values($scopes),
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'issued_from_ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $plainAccessToken,
            'refresh_token' => $plainRefreshToken,
            'expires_in' => now()->diffInSeconds($accessExpiresAt),
            'refresh_token_expires_in' => now()->diffInSeconds($refreshExpiresAt),
            'scope' => implode(' ', $scopes),
        ];
    }
}
