<?php

namespace Database\Seeders;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SsoClientSeeder extends Seeder
{
    public function run(): void
    {
        $plainSecret = null;

        $client_id = "portal-client";

        DB::transaction(function () use (&$plainSecret, $client_id): void {
            $redirectUris = [
                [
                    'uri' => trim('http://sso-client.test/auth/sso/callback'),
                    'is_primary' => true,
                ],
                [
                    'uri' => trim('http://sso-client.test/auth/logout/return'),
                    'is_primary' => false,
                ],
            ];
            $scopeDefinitions = $this->scopeDefinitions();
            $scopeCodes = collect($scopeDefinitions)->pluck('code')->values()->all();
            $existingClient = SsoClient::query()
                ->where('client_id', $client_id)
                ->with(['activeSecrets'])
                ->first();

            if ($existingClient === null || $existingClient->activeSecrets->isEmpty()) {
                $plainSecret = Str::random(64);
            }

            $client = SsoClient::query()->updateOrCreate(
                ['client_id' => $client_id],
                [
                    'name' => 'Portal Client',
                    'client_secret_hash' => $existingClient?->client_secret_hash ?: Hash::make($plainSecret ?? Str::random(64)),
                    'redirect_uris' => collect($redirectUris)->pluck('uri')->all(),
                    'frontchannel_logout_uri' => 'http://sso-client.test/auth/frontchannel-logout',
                    'backchannel_logout_uri' => 'http://sso-client.test/auth/backchannel-logout',
                    'is_active' => true,
                    'scopes' => $scopeCodes,
                    'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
                    'is_first_party' => true,
                    'consent_bypass_allowed' => false,
                ],
            );

            $this->syncRedirectUris($client, $redirectUris);
            $this->syncScopes($client, $scopeDefinitions);

            if (! $client->activeSecrets()->exists()) {
                $client->secrets()->create([
                    'name' => 'Local development secret',
                    'secret_hash' => Hash::make($plainSecret),
                    'last_four' => substr($plainSecret, -4),
                    'is_active' => true,
                ]);

                $client->forceFill([
                    'client_secret_hash' => Hash::make($plainSecret),
                ])->save();
            }
        });

        $this->command?->info('========================================');
        $this->command?->info('Portal client created.');
        $this->command?->info('========================================');
        $this->command?->warn("Client ID: {$client_id}");

        if ($plainSecret !== null) {
            $this->command?->warn("Client Secret: {$plainSecret}");
        }

        $this->seedCsharpAspNetDemoClient();
    }

    private function seedCsharpAspNetDemoClient(): void
    {
        $clientId = 'csharp-aspnet-demo';
        $redirectUri = 'http://localhost:5023/auth/callback';
        $plainSecret = null;
        $assignedScopes = [];
        $created = false;

        $existingClient = SsoClient::query()
            ->where('client_id', $clientId)
            ->with(['scopes'])
            ->first();

        if ($existingClient !== null) {
            $assignedScopes = $this->orderedDemoScopeCodes($existingClient->normalizedScopeCodes());

            $this->command?->info('C# ASP.NET Demo Client already exists.');
            $this->writeCsharpAspNetDemoClientSummary($existingClient, $redirectUri, $assignedScopes);

            return;
        }

        $tokenPolicy = $this->defaultConfidentialTokenPolicy();

        if ($tokenPolicy === null) {
            $this->command?->warn('No active default confidential token policy found for the C# ASP.NET Demo client.');
        }

        DB::transaction(function () use ($clientId, $redirectUri, $tokenPolicy, &$plainSecret, &$assignedScopes, &$created): void {
            $scopeIds = Scope::query()
                ->whereIn('code', ['openid', 'profile', 'email'])
                ->pluck('id', 'code');

            $assignedScopes = $this->orderedDemoScopeCodes($scopeIds->keys()->all());

            $plainSecret = Str::random(96);

            /*
             * Preconfigured confidential OAuth client for:
             * sso-dotnet-demos/csharp/SsoAspNetCSharpDemo
             *
             * Redirect URI: http://localhost:5023/auth/callback
             * Client type: confidential
             */
            $client = SsoClient::query()->create([
                'name' => 'C# ASP.NET Demo',
                'client_id' => $clientId,
                'client_type' => SsoClient::CLIENT_TYPE_CONFIDENTIAL,
                'client_secret_hash' => Hash::make($plainSecret),
                'redirect_uris' => [$redirectUri],
                'frontchannel_logout_uri' => null,
                'backchannel_logout_uri' => null,
                'is_active' => true,
                'scopes' => $assignedScopes,
                'token_policy_id' => $tokenPolicy?->getKey(),
                'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
                'is_first_party' => false,
                'consent_bypass_allowed' => false,
            ]);

            $client->redirectUris()->create([
                'uri' => $redirectUri,
                'uri_hash' => hash('sha256', $redirectUri),
                'is_primary' => true,
            ]);

            $client->scopes()->sync(
                $scopeIds->mapWithKeys(fn (int $scopeId, string $code): array => [
                    $scopeId => ['is_default' => \in_array($code, ['openid', 'profile'], true)],
                ])->all(),
            );

            $client->secrets()->create([
                'name' => 'C# ASP.NET Demo secret',
                'secret_hash' => Hash::make($plainSecret),
                'last_four' => substr($plainSecret, -4),
                'is_active' => true,
            ]);

            $created = true;
        });

        $client = SsoClient::query()
            ->where('client_id', $clientId)
            ->with(['scopes'])
            ->firstOrFail();

        if ($created) {
            $this->command?->line('========================================');
            $this->command?->info('C# ASP.NET Demo Client Created');
            $this->command?->line('========================================');
            $this->command?->line('');
            $this->command?->line('Client ID:');
            $this->command?->line($client->client_id);
            $this->command?->line('');
            $this->command?->line('Client Secret:');
            $this->command?->line((string) $plainSecret);
            $this->command?->line('');
            $this->command?->line('Redirect URI:');
            $this->command?->line($redirectUri);
            $this->command?->line('');
            $this->command?->line('========================================');
        }

        $this->writeCsharpAspNetDemoClientSummary($client, $redirectUri, $assignedScopes);
    }

    private function defaultConfidentialTokenPolicy(): ?TokenPolicy
    {
        return TokenPolicy::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->where('pkce_required', false)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, string>  $scopeCodes
     * @return array<int, string>
     */
    private function orderedDemoScopeCodes(array $scopeCodes): array
    {
        return collect(['openid', 'profile', 'email'])
            ->filter(fn (string $scopeCode): bool => \in_array($scopeCode, $scopeCodes, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $assignedScopes
     */
    private function writeCsharpAspNetDemoClientSummary(SsoClient $client, string $redirectUri, array $assignedScopes): void
    {
        $this->command?->line('C# ASP.NET Demo Client');
        $this->command?->line('Client ID: '.$client->client_id);
        $this->command?->line('Redirect URI: '.$redirectUri);
        $this->command?->line('Assigned scopes: '.(empty($assignedScopes) ? '(none)' : implode(', ', $assignedScopes)));
        $this->command?->line('Client type: '.$client->client_type);
    }

    /**
     * @param  array<int, array{uri: string, is_primary: bool}>  $redirectUris
     */
    private function syncRedirectUris(SsoClient $client, array $redirectUris): void
    {
        $hashes = [];

        foreach ($redirectUris as $redirectUri) {
            $uri = trim($redirectUri['uri']);
            $uriHash = hash('sha256', $uri);
            $hashes[] = $uriHash;

            $client->redirectUris()->updateOrCreate(
                ['uri_hash' => $uriHash],
                [
                    'uri' => $uri,
                    'is_primary' => $redirectUri['is_primary'],
                ],
            );
        }

        $client->redirectUris()
            ->whereNotIn('uri_hash', $hashes)
            ->delete();
    }

    /**
     * @param  array<int, array{name: string, code: string, description: string, is_active: bool}>  $scopeDefinitions
     */
    private function syncScopes(SsoClient $client, array $scopeDefinitions): void
    {
        $defaultScopeCodes = ['openid', 'profile'];

        $scopeIds = collect($scopeDefinitions)
            ->mapWithKeys(function (array $definition) use ($defaultScopeCodes): array {
                $scope = Scope::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    $definition,
                );

                return [
                    $scope->getKey() => [
                        'is_default' => \in_array($definition['code'], $defaultScopeCodes, true),
                    ],
                ];
            })
            ->all();

        $client->scopes()->sync($scopeIds);
    }

    /**
     * @return array<int, array{name: string, code: string, description: string, is_active: bool}>
     */
    private function scopeDefinitions(): array
    {
        return [
            [
                'name' => 'OpenID',
                'code' => 'openid',
                'description' => 'Authenticate the subject and issue an ID token.',
                'is_active' => true,
            ],
            [
                'name' => 'Profile',
                'code' => 'profile',
                'description' => 'Access standard profile claims for the subject.',
                'is_active' => true,
            ],
            [
                'name' => 'Email',
                'code' => 'email',
                'description' => 'Access verified email claims for the subject.',
                'is_active' => true,
            ],
        ];
    }
}
