<?php

namespace App\Http\Requests\Admin;

use App\Models\Scope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScopeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('scopes.update') || $this->user()?->can('scopes.manage')) ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Scope $scope */
        $scope = $this->route('scope');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('scopes', 'code')->ignore($scope->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
