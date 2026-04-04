<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthConsentApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consent_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'consent_token' => trim((string) $this->input('consent_token')),
        ]);
    }
}
