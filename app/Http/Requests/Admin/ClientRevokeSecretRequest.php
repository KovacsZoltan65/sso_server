<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ClientRevokeSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('clients.revokeSecret')
            || $this->user()?->can('clients.manageSecrets')
            || $this->user()?->can('sso-clients.manage')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
