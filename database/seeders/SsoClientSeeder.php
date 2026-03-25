<?php

namespace Database\Seeders;

use App\Models\SsoClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SsoClientSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->clients() as $client) {
            SsoClient::query()->updateOrCreate(
                ['client_id' => $client['client_id']],
                [
                    'name' => $client['name'],
                    'client_secret_hash' => Hash::make($client['plain_secret']),
                    'redirect_uris' => $client['redirect_uris'],
                    'is_active' => $client['is_active'],
                    'scopes' => $client['scopes'],
                    'token_policy_id' => $client['token_policy_id'],
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clients(): array
    {
        return [
            [
                'name' => 'SSO Admin Console',
                'client_id' => 'client_admin_console',
                'plain_secret' => 'dev-admin-console-secret',
                'redirect_uris' => [
                    'https://admin.sso.test/auth/callback',
                    'https://admin.sso.test/auth/silent-renew',
                ],
                'is_active' => true,
                'scopes' => ['openid', 'profile', 'email'],
                'token_policy_id' => null,
            ],
            [
                'name' => 'Customer Portal',
                'client_id' => 'client_customer_portal',
                'plain_secret' => 'dev-customer-portal-secret',
                'redirect_uris' => [
                    'https://portal.sso.test/oidc/callback',
                ],
                'is_active' => true,
                'scopes' => ['openid', 'profile', 'email', 'offline_access'],
                'token_policy_id' => null,
            ],
            [
                'name' => 'Operations Dashboard',
                'client_id' => 'client_ops_dashboard',
                'plain_secret' => 'dev-ops-dashboard-secret',
                'redirect_uris' => [
                    'https://ops.sso.test/login/callback',
                    'https://ops.sso.test/auth/callback',
                ],
                'is_active' => false,
                'scopes' => ['openid', 'profile'],
                'token_policy_id' => null,
            ],
        ];
    }
}
