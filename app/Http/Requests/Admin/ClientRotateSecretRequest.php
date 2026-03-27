<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\ClientPermissions;
use Illuminate\Foundation\Http\FormRequest;

class ClientRotateSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can(ClientPermissions::ROTATE_SECRET)
            || $this->user()?->can(ClientPermissions::MANAGE_SECRETS)) ?? false;
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
