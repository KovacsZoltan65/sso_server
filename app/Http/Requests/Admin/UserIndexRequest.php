<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\UserPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(UserPermissions::VIEW_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:150'],
            'verified' => ['nullable', 'in:verified,pending'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:50'],
            'sortField' => ['nullable', 'in:name,email,createdAt,emailVerifiedAt'],
            'sortOrder' => ['nullable', 'integer', 'in:-1,1'],
        ];
    }
}
