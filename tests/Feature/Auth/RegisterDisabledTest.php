<?php

use App\Models\User;

test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('registration submission is not available', function () {
    $initialUserCount = User::count();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    expect(User::count())->toBe($initialUserCount);
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});
