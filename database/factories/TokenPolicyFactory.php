<?php

namespace Database\Factories;

use App\Models\TokenPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TokenPolicy>
 */
class TokenPolicyFactory extends Factory
{
    protected $model = TokenPolicy::class;

    public function definition(): array
    {
        $resource = fake()->randomElement(['default', 'public', 'mobile', 'service']);
        $suffix = fake()->unique()->lexify('??');

        return [
            'name' => str($resource)->title()->value().' Policy',
            'code' => "{$resource}.{$suffix}",
            'description' => fake()->sentence(),
            'access_token_ttl_minutes' => fake()->numberBetween(15, 120),
            'refresh_token_ttl_minutes' => fake()->numberBetween(240, 43200),
            'refresh_token_rotation_enabled' => fake()->boolean(),
            'pkce_required' => fake()->boolean(),
            'reuse_refresh_token_forbidden' => fake()->boolean(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
