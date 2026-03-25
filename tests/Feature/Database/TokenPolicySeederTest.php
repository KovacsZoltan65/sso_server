<?php

use App\Models\TokenPolicy;
use Database\Seeders\TokenPolicySeeder;

it('seeds default token policies idempotently', function () {
    $this->seed(TokenPolicySeeder::class);
    $this->seed(TokenPolicySeeder::class);

    expect(TokenPolicy::where('code', 'default.web')->exists())->toBeTrue();
    expect(TokenPolicy::where('code', 'public.strict')->exists())->toBeTrue();
    expect(TokenPolicy::where('code', 'service.integration')->exists())->toBeTrue();
    expect(TokenPolicy::where('is_default', true)->count())->toBe(1);
});
