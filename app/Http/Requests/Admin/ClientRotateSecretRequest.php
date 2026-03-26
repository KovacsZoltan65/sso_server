<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ClientRotateSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('clients.rotateSecret')
            || $this->user()?->can('clients.manageSecrets')
            || $this->user()?->can('sso-clients.manage')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
