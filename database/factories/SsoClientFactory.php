<?php

namespace Database\Factories;

use App\Models\SsoClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<SsoClient>
 */
class SsoClientFactory extends Factory
{
    protected $model = SsoClient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'client_id' => 'client_'.Str::lower(Str::random(24)),
            'client_secret_hash' => Hash::make(Str::random(48)),
            'redirect_uris' => [
                fake()->url(),
            ],
            'frontchannel_logout_uri' => null,
            'is_active' => true,
            'scopes' => ['openid'],
            'token_policy_id' => null,
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
            'is_first_party' => false,
            'consent_bypass_allowed' => false,
        ];
    }
}
