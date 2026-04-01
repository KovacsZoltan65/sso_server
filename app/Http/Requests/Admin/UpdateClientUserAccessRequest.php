<?php

namespace App\Http\Requests\Admin;

use App\Models\ClientUserAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var ClientUserAccess $access */
        $access = $this->route('clientUserAccess');

        return [
            'client_id' => ['required', 'integer', Rule::exists('sso_clients', 'id')],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('client_user_access', 'user_id')
                    ->ignore($access->id)
                    ->where(fn ($query) => $query->where('client_id', $this->integer('client_id'))),
            ],
            'is_active' => ['nullable', 'boolean'],
            'allowed_from' => ['nullable', 'date'],
            'allowed_until' => ['nullable', 'date', 'after_or_equal:allowed_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
