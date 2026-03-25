<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\TokenPolicyPermissions;
use Illuminate\Foundation\Http\FormRequest;

class TokenPolicyBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can(TokenPolicyPermissions::DELETE_ANY)
            || $this->user()?->can('token-policies.manage')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:token_policies,id'],
        ];
    }
}
