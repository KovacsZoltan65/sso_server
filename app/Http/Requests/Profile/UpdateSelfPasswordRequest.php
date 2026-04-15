<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use App\Services\Profile\SelfServiceProfileService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateSelfPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('updateOwnPassword', $user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User|null $user */
                $user = $this->user();

                if ($user === null) {
                    return;
                }

                $allowedKeys = ['current_password', 'password', 'password_confirmation'];
                $unexpectedKeys = collect(array_keys($this->all()))
                    ->diff($allowedKeys)
                    ->values();

                if ($unexpectedKeys->isEmpty()) {
                    return;
                }

                foreach ($unexpectedKeys as $key) {
                    $validator->errors()->add($key, __('validation.custom.self_service_password.forbidden_field'));
                }

                app(SelfServiceProfileService::class)->logForbiddenMutationAttempt(
                    $user,
                    $unexpectedKeys->all(),
                    $this,
                    'profile.password.update',
                );
            },
        ];
    }
}
