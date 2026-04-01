<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TokenIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string', 'max:150'],
            'client_id' => ['nullable', 'integer', 'exists:sso_clients,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'token_type' => ['nullable', 'in:access_token,refresh_token'],
            'state' => ['nullable', 'in:active,expired,revoked,rotated'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:50'],
            'sortField' => ['nullable', 'in:createdAt,expiresAt,client,user'],
            'sortOrder' => ['nullable', 'integer', 'in:-1,1'],
        ];
    }
}
