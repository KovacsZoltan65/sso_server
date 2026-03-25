<?php

use App\Models\Scope;
use Database\Seeders\ScopeSeeder;

it('seeds default scopes idempotently', function () {
    $this->seed(ScopeSeeder::class);
    $this->seed(ScopeSeeder::class);

    expect(Scope::query()->where('code', 'openid')->exists())->toBeTrue();
    expect(Scope::query()->where('code', 'offline_access')->exists())->toBeTrue();
    expect(Scope::query()->where('code', 'clients.manage')->exists())->toBeTrue();
});
