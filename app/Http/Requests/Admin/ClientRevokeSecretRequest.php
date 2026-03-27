<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions\ClientPermissions;
use Illuminate\Foundation\Http\FormRequest;

class ClientRevokeSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can(ClientPermissions::REVOKE_SECRET)
            || $this->user()?->can(ClientPermissions::MANAGE_SECRETS)) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
