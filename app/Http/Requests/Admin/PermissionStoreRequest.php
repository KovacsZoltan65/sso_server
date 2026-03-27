<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\PermissionPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionPermissions::CREATE) ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
