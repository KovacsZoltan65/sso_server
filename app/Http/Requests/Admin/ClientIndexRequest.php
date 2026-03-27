<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\ClientPermissions;
use Illuminate\Foundation\Http\FormRequest;

class ClientIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ClientPermissions::VIEW_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:50'],
            'sortField' => ['nullable', 'in:name,clientId,createdAt'],
            'sortOrder' => ['nullable', 'integer', 'in:-1,1'],
        ];
    }
}
