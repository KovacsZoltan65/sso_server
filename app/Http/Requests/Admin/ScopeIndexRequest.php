<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScopeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('scopes.viewAny') || $this->user()?->can('scopes.view')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:50'],
            'sortField' => ['nullable', 'in:name,code,createdAt'],
            'sortOrder' => ['nullable', 'integer', 'in:-1,1'],
        ];
    }
}
