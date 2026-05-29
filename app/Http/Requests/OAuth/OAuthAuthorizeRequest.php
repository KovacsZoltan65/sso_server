<?php

namespace App\Http\Requests\OAuth;

use App\Support\Localization;
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
            'nonce' => ['nullable', 'string', 'max:255'],
            'code_challenge' => ['nullable', 'string', 'max:255'],
            'code_challenge_method' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => trim((string) $this->input('client_id')),
            'redirect_uri' => trim((string) $this->input('redirect_uri')),
            'scope' => trim((string) $this->input('scope')),
            'state' => trim((string) $this->input('state')),
            'nonce' => trim((string) $this->input('nonce')),
            'code_challenge' => trim((string) $this->input('code_challenge')),
            'code_challenge_method' => trim((string) $this->input('code_challenge_method')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->scopeContainsOpenId() && trim((string) $this->input('nonce')) === '') {
                $validator->errors()->add('nonce', Localization::translate('validation.custom.nonce.required_for_openid_scope'));
            }
        });
    }

    private function scopeContainsOpenId(): bool
    {
        return collect(preg_split('/\s+/', trim((string) $this->input('scope'))) ?: [])
            ->map(static fn (string $scope): string => trim($scope))
            ->filter()
            ->contains('openid');
    }
}
