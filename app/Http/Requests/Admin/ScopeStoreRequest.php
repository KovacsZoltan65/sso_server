<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\ScopePermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScopeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ScopePermissions::CREATE) ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('scopes', 'code'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
