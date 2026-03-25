<?php

namespace Database\Seeders;

use App\Models\TokenPolicy;
use Illuminate\Database\Seeder;

class TokenPolicySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            TokenPolicy::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
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
            ],
            [
                'name' => 'Strict Public Client Policy',
                'code' => 'public.strict',
                'description' => 'Shorter TTLs with mandatory PKCE and refresh token rotation for public clients.',
                'access_token_ttl_minutes' => 30,
                'refresh_token_ttl_minutes' => 10080,
                'refresh_token_rotation_enabled' => true,
                'pkce_required' => true,
                'reuse_refresh_token_forbidden' => true,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Service Integration Policy',
                'code' => 'service.integration',
                'description' => 'Longer-lived tokens for server-to-server integrations with no PKCE requirement.',
                'access_token_ttl_minutes' => 120,
                'refresh_token_ttl_minutes' => 525600,
                'refresh_token_rotation_enabled' => false,
                'pkce_required' => false,
                'reuse_refresh_token_forbidden' => false,
                'is_default' => false,
                'is_active' => true,
            ],
        ];
    }
}
