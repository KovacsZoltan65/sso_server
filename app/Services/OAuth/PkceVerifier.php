<?php

namespace App\Services\OAuth;

use Illuminate\Validation\ValidationException;

class PkceVerifier
{
    public function verify(?string $challenge, ?string $method, string $verifier): void
    {
        if ($challenge === null || $challenge === '') {
            throw ValidationException::withMessages([
                'code_verifier' => 'A PKCE code challenge is required for authorization code exchange.',
            ]);
        }

        if ($method !== 'S256') {
            throw ValidationException::withMessages([
                'code_verifier' => 'Unsupported PKCE code challenge method.',
            ]);
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        if (! hash_equals($challenge, $computed)) {
            throw ValidationException::withMessages([
                'code_verifier' => 'The provided PKCE code verifier is invalid.',
            ]);
        }
    }
}
