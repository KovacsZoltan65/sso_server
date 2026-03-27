<?php

namespace App\Services\Profile;

use App\Data\SelfServiceProfileData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class SelfServiceProfileService
{
    /**
     * @var array<int, string>
     */
    private array $editableFields = ['name'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(User $user, Request $request, bool $logView = true): array
    {
        $freshUser = $this->users->refreshUser($user);

        if ($logView) {
            activity('account')
                ->causedBy($freshUser)
                ->performedOn($freshUser)
                ->event('profile.viewed')
                ->withProperties($this->requestContext($request))
                ->log('Self-service profile viewed.');
        }

        return SelfServiceProfileData::fromUser($freshUser)->toArray();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updateProfile(User $user, array $attributes, Request $request): array
    {
        $updatedUser = $this->users->updateProfile(
            $user,
            Arr::only($attributes, $this->editableFields),
        );

        activity('account')
            ->causedBy($updatedUser)
            ->performedOn($updatedUser)
            ->event('profile.updated')
            ->withProperties([
                'updated_fields' => array_values(array_keys(Arr::only($attributes, $this->editableFields))),
                ...$this->requestContext($request),
            ])
            ->log('Self-service profile updated.');

        return SelfServiceProfileData::fromUser($updatedUser)->toArray();
    }

    public function updatePassword(User $user, string $plainPassword, Request $request): void
    {
        $updatedUser = $this->users->updatePassword($user, Hash::make($plainPassword));

        activity('account')
            ->causedBy($updatedUser)
            ->performedOn($updatedUser)
            ->event('profile.password_changed')
            ->withProperties($this->requestContext($request))
            ->log('Self-service password changed.');
    }

    /**
     * @param array<int, string> $attemptedFields
     */
    public function logForbiddenMutationAttempt(User $user, array $attemptedFields, Request $request, string $endpoint): void
    {
        activity('security')
            ->causedBy($user)
            ->performedOn($user)
            ->event('profile.forbidden_mutation_attempt')
            ->withProperties([
                'attempted_fields' => array_values($attemptedFields),
                'endpoint' => $endpoint,
                ...$this->requestContext($request),
            ])
            ->log('Forbidden self-service mutation attempt detected.');
    }

    /**
     * @return array<int, string>
     */
    public function editableFields(): array
    {
        return $this->editableFields;
    }

    /**
     * @return array<int, string>
     */
    public function readOnlyFields(): array
    {
        return ['email', 'emailVerifiedAt'];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
        ];
    }
}
