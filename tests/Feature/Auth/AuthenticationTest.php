<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'auth',
        'event' => 'auth.login.failed',
        'description' => 'User login failed.',
    ]);
});

test('login attempts are locked out after too many failed requests', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'auth',
        'event' => 'auth.lockout.triggered',
        'description' => 'User login rate limited.',
    ]);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'auth',
        'event' => 'auth.logout.succeeded',
        'description' => 'User logged out.',
        'causer_id' => $user->id,
        'causer_type' => User::class,
    ]);
});
