<?php

namespace App\Http\Requests\Admin;

use App\Support\ClientOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('clients.update') || $this->user()?->can('sso-clients.manage')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url:http,https', 'max:2048'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(ClientOptions::scopeValues())],
            'is_active' => ['required', 'boolean'],
            'token_policy_id' => ['nullable', 'integer', 'min:1', 'exists:token_policies,id'],
        ];
    }
}
