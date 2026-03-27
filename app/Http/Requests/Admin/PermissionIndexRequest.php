<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\PermissionPermissions;
use Illuminate\Foundation\Http\FormRequest;

class PermissionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionPermissions::VIEW_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:50'],
            'sortField' => ['nullable', 'in:name,createdAt'],
            'sortOrder' => ['nullable', 'integer', 'in:-1,1'],
        ];
    }
}
