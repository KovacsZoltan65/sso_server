<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('wraps unexpected json exceptions in the standard api envelope', function (): void {
    Route::get('/__test/json-error', static function (): never {
        throw new RuntimeException('boom');
    });

    $this->getJson('/__test/json-error')
        ->assertStatus(500)
        ->assertExactJson([
            'message' => 'Server error.',
            'data' => [],
            'meta' => [],
            'errors' => [],
        ]);
});
