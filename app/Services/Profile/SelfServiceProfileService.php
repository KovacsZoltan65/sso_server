<?php

namespace App\Services\Profile;

use App\Data\SelfServiceProfileData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

/**
 * Server-side authority for self-service profile reads and mutations.
 *
 * The client renders the profile UX, but this service decides which fields are
 * editable, when an audit event should be emitted, and how no-op updates are
 * handled so the account audit trail stays trustworthy.
 */
class SelfServiceProfileService
{
    /**
     * @var array<int, string>
     */
    private array $editableFields = ['name'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * Builds the canonical self-service payload for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function profilePayload(User $user, Request $request, bool $logView = true): array
    {
        $freshUser = $this->users->refreshUser($user);

        if ($logView) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_ACCOUNT,
                event: 'account.profile.viewed',
                description: 'Self-service profile viewed.',
                subject: $freshUser,
                causer: $freshUser,
                properties: $this->auditLogService->requestContext($request),
            );
        }

        return SelfServiceProfileData::fromUser($freshUser)->toArray();
    }

    /**
     * Updates only whitelisted self-service profile fields.
     *
     * No-op submissions intentionally return a fresh payload without writing an
     * "updated" audit success event. That keeps the API UX stable while
     * avoiding misleading account activity entries.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updateProfile(User $user, array $attributes, Request $request): array
    {
        $editableAttributes = Arr::only($attributes, $this->editableFields);
        $updatedFields = array_values(array_keys(array_filter(
            $editableAttributes,
            fn (mixed $value, string $field): bool => $user->getAttribute($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        )));

        if ($updatedFields === []) {
            return SelfServiceProfileData::fromUser($this->users->refreshUser($user))->toArray();
        }

        $updatedUser = $this->users->updateProfile($user, $editableAttributes);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_ACCOUNT,
            event: 'account.profile.updated',
            description: 'Self-service profile updated.',
            subject: $updatedUser,
            causer: $updatedUser,
            properties: [
                'updated_fields' => $updatedFields,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return SelfServiceProfileData::fromUser($updatedUser)->toArray();
    }

    public function updatePassword(User $user, string $plainPassword, Request $request): void
    {
        $updatedUser = $this->users->updatePassword($user, Hash::make($plainPassword));

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_ACCOUNT,
            event: 'account.password.changed',
            description: 'Self-service password changed.',
            subject: $updatedUser,
            causer: $updatedUser,
            properties: $this->auditLogService->requestContext($request),
        );
    }

    public function deleteProfile(User $user, Request $request): void
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_ACCOUNT,
            event: 'account.profile.deleted',
            description: 'Self-service profile deleted.',
            subject: $user,
            causer: $user,
            properties: $this->auditLogService->requestContext($request),
        );

        $this->users->deleteUser($user);
    }

    /**
     * @param array<int, string> $attemptedFields
     */
    public function logForbiddenMutationAttempt(User $user, array $attemptedFields, Request $request, string $endpoint): void
    {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_SECURITY,
            event: 'security.profile_mutation.denied',
            description: 'Forbidden self-service mutation attempt detected.',
            subject: $user,
            causer: $user,
            properties: [
                'updated_fields' => array_values($attemptedFields),
                'reason' => $endpoint,
                ...$this->auditLogService->requestContext($request),
            ],
        );
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
}
