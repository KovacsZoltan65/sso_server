<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeTokenRequest extends FormRequest
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
            'token_type' => ['required', 'in:access_token,refresh_token'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
