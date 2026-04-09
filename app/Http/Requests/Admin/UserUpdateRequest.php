<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return $user instanceof User
            ? (bool) $this->user()?->can('update', $user)
            : false;
    }

    /**
     * @throws AuthorizationException
     */
    protected function failedAuthorization(): void
    {
        app(AuditLogService::class)->logSecurityEvent(
            event: 'security.authorization.denied',
            description: 'Authorization denied.',
            causer: $this->user(),
            properties: [
                'route' => $this->route()?->getName(),
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ],
        );

        throw new AuthorizationException('This action is unauthorized.');
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
