<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\PermissionPermissions;
use Illuminate\Foundation\Http\FormRequest;

class PermissionBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionPermissions::DELETE_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
