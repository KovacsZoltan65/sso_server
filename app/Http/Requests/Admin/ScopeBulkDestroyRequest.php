<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\ScopePermissions;
use Illuminate\Foundation\Http\FormRequest;

class ScopeBulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ScopePermissions::DELETE_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:scopes,id'],
        ];
    }
}
