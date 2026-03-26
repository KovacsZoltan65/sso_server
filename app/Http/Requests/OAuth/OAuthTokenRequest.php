<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grant_type' => ['required', 'string', Rule::in(['authorization_code', 'refresh_token'])],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'code' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:255'],
            'redirect_uri' => ['required_if:grant_type,authorization_code', 'nullable', 'url', 'max:4000'],
            'code_verifier' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:255'],
            'refresh_token' => ['required_if:grant_type,refresh_token', 'nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'grant_type' => trim((string) $this->input('grant_type')),
            'client_id' => trim((string) $this->input('client_id')),
            'client_secret' => trim((string) $this->input('client_secret')),
            'code' => trim((string) $this->input('code')),
            'redirect_uri' => trim((string) $this->input('redirect_uri')),
            'code_verifier' => trim((string) $this->input('code_verifier')),
            'refresh_token' => trim((string) $this->input('refresh_token')),
            'scope' => trim((string) $this->input('scope')),
        ]);
    }
}
