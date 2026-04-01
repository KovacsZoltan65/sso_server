<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeTokenFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'family_id' => trim((string) $this->route('familyId', '')),
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'family_id' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
