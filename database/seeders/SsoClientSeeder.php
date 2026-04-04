<?php

namespace Database\Seeders;

use App\Models\Scope;
use App\Models\SsoClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SsoClientSeeder extends Seeder
{
    public function run(): void
    {
        $plainSecret = null;

        DB::transaction(function () use (&$plainSecret): void {
            $redirectUri = trim('http://sso-client.test/auth/sso/callback');
            $scopeDefinitions = $this->scopeDefinitions();
            $scopeCodes = collect($scopeDefinitions)->pluck('code')->values()->all();
            $existingClient = SsoClient::query()
                ->where('client_id', 'portal-client')
                ->with('activeSecrets')
                ->first();

            if ($existingClient === null || $existingClient->activeSecrets->isEmpty()) {
                $plainSecret = Str::random(64);
            }

            $client = SsoClient::query()->updateOrCreate(
                ['client_id' => 'portal-client'],
                [
                    'name' => 'Portal Client',
                    'client_secret_hash' => $existingClient?->client_secret_hash ?: Hash::make($plainSecret ?? Str::random(64)),
                    'redirect_uris' => [$redirectUri],
                    'is_active' => true,
                    'scopes' => $scopeCodes,
                    'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
                    'is_first_party' => true,
                    'consent_bypass_allowed' => false,
                ],
            );

            $this->syncRedirectUri($client, $redirectUri);
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

        $this->command?->info('Portal client created.');
        $this->command?->warn('Client ID: portal-client');

        if ($plainSecret !== null) {
            $this->command?->warn('Client Secret: '.$plainSecret);
        }
    }

    private function syncRedirectUri(SsoClient $client, string $redirectUri): void
    {
        $redirectUriHash = hash('sha256', $redirectUri);

        $client->redirectUris()->updateOrCreate(
            ['uri_hash' => $redirectUriHash],
            [
                'uri' => $redirectUri,
                'is_primary' => true,
            ],
        );

        $client->redirectUris()
            ->where('uri_hash', '!=', $redirectUriHash)
            ->delete();
    }

    /**
     * @param array<int, array{name: string, code: string, description: string, is_active: bool}> $scopeDefinitions
     */
    private function syncScopes(SsoClient $client, array $scopeDefinitions): void
    {
        $scopeIds = collect($scopeDefinitions)
            ->map(function (array $definition): int {
                return Scope::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    $definition,
                )->getKey();
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
