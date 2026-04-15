<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use App\Services\Profile\SelfServiceProfileService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSelfProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('updateSelf', $user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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

                $allowedKeys = ['name'];
                $unexpectedKeys = collect(array_keys($this->all()))
                    ->diff($allowedKeys)
                    ->values();

                if ($unexpectedKeys->isEmpty()) {
                    return;
                }

                foreach ($unexpectedKeys as $key) {
                    $validator->errors()->add($key, __('validation.custom.self_service_profile.forbidden_field'));
                }

                app(SelfServiceProfileService::class)->logForbiddenMutationAttempt(
                    $user,
                    $unexpectedKeys->all(),
                    $this,
                    'profile.update',
                );
            },
        ];
    }
}
