<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthAuthorizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_type' => ['required', 'string', Rule::in(['code'])],
            'client_id' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:4000'],
            'scope' => ['nullable', 'string', 'max:1000'],
            'state' => ['nullable', 'string', 'max:1000'],
            'code_challenge' => ['nullable', 'string', 'max:255'],
            'code_challenge_method' => ['nullable', 'string', Rule::in(['S256'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => trim((string) $this->input('client_id')),
            'redirect_uri' => trim((string) $this->input('redirect_uri')),
            'scope' => trim((string) $this->input('scope')),
            'state' => trim((string) $this->input('state')),
            'code_challenge' => trim((string) $this->input('code_challenge')),
            'code_challenge_method' => trim((string) $this->input('code_challenge_method')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $challenge = $this->input('code_challenge');
            $method = $this->input('code_challenge_method');

            if ($challenge !== '' && $challenge !== null && ($method === '' || $method === null)) {
                $validator->errors()->add('code_challenge_method', 'The code challenge method field is required when code challenge is present.');
            }

            if (($method !== '' && $method !== null) && ($challenge === '' || $challenge === null)) {
                $validator->errors()->add('code_challenge', 'The code challenge field is required when code challenge method is present.');
            }
        });
    }
}
