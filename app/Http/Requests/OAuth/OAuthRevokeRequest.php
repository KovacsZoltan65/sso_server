<?php

declare(strict_types=1);

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthRevokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'token' => trim((string) $this->input('token', '')),
            'token_type_hint' => trim((string) $this->input('token_type_hint', '')),
            'client_id' => trim((string) $this->input('client_id', '')),
            'client_secret' => trim((string) $this->input('client_secret', '')),
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:20'],
            'token_type_hint' => ['nullable', 'string', 'in:access_token,refresh_token'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
