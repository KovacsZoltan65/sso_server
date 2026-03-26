<?php

namespace App\Services\OAuth;

use Illuminate\Validation\ValidationException;

class PkceVerifier
{
    public function verify(?string $challenge, ?string $method, string $verifier): void
    {
        if ($challenge === null || $challenge === '') {
            return;
        }

        $computed = match ($method) {
            null, '', 'plain' => $verifier,
            'S256' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            default => throw ValidationException::withMessages([
                'code_verifier' => 'Unsupported PKCE code challenge method.',
            ]),
        };

        if (! hash_equals($challenge, $computed)) {
            throw ValidationException::withMessages([
                'code_verifier' => 'The provided PKCE code verifier is invalid.',
            ]);
        }
    }
}
