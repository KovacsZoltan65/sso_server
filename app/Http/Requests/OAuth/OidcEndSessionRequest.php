<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;

class OidcEndSessionRequest extends FormRequest
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
            'id_token_hint' => ['nullable', 'string'],
            'post_logout_redirect_uri' => ['nullable', 'string', 'max:2048'],
            'state' => ['nullable', 'string', 'max:512'],
        ];
    }
}
