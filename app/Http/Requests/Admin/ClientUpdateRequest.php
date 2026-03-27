<?php

namespace App\Http\Requests\Admin;

use App\Models\Scope;
use App\Support\Permissions\ClientPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ClientPermissions::UPDATE) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $redirectUris = collect($this->input('redirect_uris', []))
            ->map(static fn (mixed $uri): string => trim((string) $uri))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $scopes = collect($this->input('scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url:http,https', 'max:2048', 'distinct:strict'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'distinct:strict', Rule::exists(Scope::class, 'code')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
            'token_policy_id' => ['nullable', 'integer', 'min:1', 'exists:token_policies,id'],
        ];
    }
}
