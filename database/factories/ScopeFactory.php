<?php

namespace Database\Factories;

use App\Models\Scope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scope>
 */
class ScopeFactory extends Factory
{
    protected $model = Scope::class;

    public function definition(): array
    {
        $resource = fake()->randomElement(['profile', 'users', 'clients', 'tokens']);
        $action = fake()->randomElement(['read', 'write', 'manage', 'issue']);

        return [
            'name' => str($resource)->replace('-', ' ')->title()->value().' '.str($action)->title()->value(),
            'code' => fake()->unique()->regexify("{$resource}\\.(?:{$action})[a-z]{2}"),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
