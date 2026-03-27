<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\TokenPolicyPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TokenPolicyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(TokenPolicyPermissions::CREATE) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9._-]+$/', Rule::unique('token_policies', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
            'access_token_ttl_minutes' => ['required', 'integer', 'min:1'],
            'refresh_token_ttl_minutes' => ['required', 'integer', 'min:1'],
            'refresh_token_rotation_enabled' => ['required', 'boolean'],
            'pkce_required' => ['required', 'boolean'],
            'reuse_refresh_token_forbidden' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $accessTtl = (int) $this->input('access_token_ttl_minutes', 0);
            $refreshTtl = (int) $this->input('refresh_token_ttl_minutes', 0);

            if ($refreshTtl < $accessTtl) {
                $validator->errors()->add('refresh_token_ttl_minutes', 'Refresh token TTL must be greater than or equal to access token TTL.');
            }

            if ($this->boolean('reuse_refresh_token_forbidden') && ! $this->boolean('refresh_token_rotation_enabled')) {
                $validator->errors()->add('reuse_refresh_token_forbidden', 'Refresh token reuse can only be forbidden when rotation is enabled.');
            }

            if ($this->boolean('is_default') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'The default token policy must remain active.');
            }
        });
    }
}
