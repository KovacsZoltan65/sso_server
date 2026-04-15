<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shares the default locale with inertia responses', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', config('app.locale'))
            ->where('locale.fallback', config('app.fallback_locale'))
            ->where('locale.available', config('app.available_locales')));
});

it('stores the selected locale in session and applies it on the next request', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'en',
        ])
        ->assertRedirect(route('dashboard'));

    expect(session('locale'))->toBe('en');

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en'));
});

it('rejects unsupported locale values', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'de',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('locale');
});
