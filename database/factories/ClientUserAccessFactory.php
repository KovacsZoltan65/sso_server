<?php

namespace Database\Factories;

use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientUserAccess>
 */
class ClientUserAccessFactory extends Factory
{
    protected $model = ClientUserAccess::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => SsoClient::factory(),
            'user_id' => User::factory(),
            'is_active' => true,
            'allowed_from' => null,
            'allowed_until' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
