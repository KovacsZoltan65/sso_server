<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame($originalEmail, $user->email);
});

test('email field cannot be updated through the self-service profile form', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'changed@example.com',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect('/profile');

    $this->assertSame($originalEmail, $user->refresh()->email);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
