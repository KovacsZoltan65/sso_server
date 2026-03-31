<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Permissions\UserPermissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

class UserService
{
    /**
     * @var array<int, string>
     */
    private array $protectedUserEmails = [
        'superadmin@sso.test',
        'admin@sso.test',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array
    {
        $paginator = $this->users->paginateForAdminIndex($filters, $sortField, $sortOrder, $perPage, $page);
        /** @var User|null $actingUser */
        $actingUser = auth()->user();

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (User $user) => UserSummaryData::fromModel(
                    user: $user,
                    canDelete: $this->canDeleteUser($user, $actingUser),
                    deleteBlockCode: $this->deleteBlockCode($user, $actingUser),
                    deleteBlockReason: $this->deleteBlockReason($user, $actingUser),
                ))
                ->values()
                ->all(),
            'roleOptions' => $this->roleOptions(),
            'canManageUsers' => auth()->user()?->can(UserPermissions::CREATE)
                || auth()->user()?->can(UserPermissions::UPDATE)
                || auth()->user()?->can(UserPermissions::DELETE)
                || auth()->user()?->can(UserPermissions::DELETE_ANY)
                || false,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'name' => $filters['name'] ?? null,
                'email' => $filters['email'] ?? null,
                'verified' => $filters['verified'] ?? null,
                'perPage' => $perPage,
            ],
            'sorting' => [
                'field' => $sortField ?? 'name',
                'order' => $sortOrder ?? 1,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function roleOptions(): array
    {
        return $this->users->getRoleNames()
            ->map(fn (string $role) => [
                'label' => ucfirst($role),
                'value' => $role,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createUser(array $payload): User
    {
        $user = $this->users->createWithRoles(
            attributes: Arr::only($payload, ['name', 'email', 'password']),
            roles: array_values($payload['roles'] ?? []),
        );

        $this->auditLogService->logAdminCrud(
            resource: 'user',
            action: 'created',
            description: 'Admin user created.',
            subject: $user,
            causer: auth()->user(),
            properties: [
                'target_user_id' => $user->id,
                'updated_fields' => ['name', 'email', 'roles'],
                'changed_attributes' => [
                    'roles' => $user->roleNames(),
                ],
                'status' => $user->email_verified_at === null ? 'unverified' : 'verified',
            ],
        );

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateUser(User $user, array $payload): User
    {
        $previousRoles = $user->roleNames();
        $updatedUser = $this->users->updateWithRoles(
            user: $user,
            attributes: Arr::only($payload, ['name', 'email']),
            roles: array_values($payload['roles'] ?? []),
        );

        $currentRoles = $updatedUser->roleNames();

        $this->auditLogService->logAdminCrud(
            resource: 'user',
            action: 'updated',
            description: 'Admin user updated.',
            subject: $updatedUser,
            causer: auth()->user(),
            properties: [
                'target_user_id' => $updatedUser->id,
                'updated_fields' => array_values(array_keys(Arr::only($payload, ['name', 'email', 'roles']))),
                'changed_attributes' => [
                    'attached_roles' => array_values(array_diff($currentRoles, $previousRoles)),
                    'detached_roles' => array_values(array_diff($previousRoles, $currentRoles)),
                ],
                'status' => $updatedUser->email_verified_at === null ? 'unverified' : 'verified',
            ],
        );

        return $updatedUser;
    }

    public function deleteUser(User $user, User $actingUser): void
    {
        $this->guardDeleteable($user, $actingUser);

        $this->auditLogService->logAdminCrud(
            resource: 'user',
            action: 'deleted',
            description: 'Admin user deleted.',
            subject: $user,
            causer: $actingUser,
            properties: [
                'target_user_id' => $user->id,
            ],
        );

        $this->users->deleteUser($user);
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    public function bulkDeleteUsers(array $ids, User $actingUser): array
    {
        $users = $this->users->getByIds($ids);

        if ($users->count() !== count($ids)) {
            throw new RuntimeException('One or more selected users could not be found.');
        }

        foreach ($users as $user) {
            $this->guardDeleteable($user, $actingUser);
        }

        foreach ($users as $user) {
            $this->auditLogService->logAdminCrud(
                resource: 'user',
                action: 'deleted',
                description: 'Admin user deleted.',
                subject: $user,
                causer: $actingUser,
                properties: [
                    'target_user_id' => $user->id,
                ],
            );
        }

        $deletedIds = $users->pluck('id')->values()->all();

        $this->users->deleteByIds($deletedIds);

        return $deletedIds;
    }

    public function canDeleteUser(User $user, ?User $actingUser): bool
    {
        return $this->deleteBlockCode($user, $actingUser) === null;
    }

    public function isProtectedUser(User $user): bool
    {
        return in_array($user->email, $this->protectedUserEmails, true)
            || $user->hasRole('superadmin');
    }

    private function guardDeleteable(User $user, User $actingUser): void
    {
        $reason = $this->deleteBlockReason($user, $actingUser);

        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
    }

    private function deleteBlockCode(User $user, ?User $actingUser): ?string
    {
        if ($actingUser?->is($user)) {
            return 'current_user';
        }

        if ($this->isProtectedUser($user)) {
            return 'protected_user';
        }

        return null;
    }

    private function deleteBlockReason(User $user, ?User $actingUser): ?string
    {
        return match ($this->deleteBlockCode($user, $actingUser)) {
            'current_user' => 'The currently signed-in user cannot be deleted.',
            'protected_user' => 'This protected system user cannot be deleted.',
            default => null,
        };
    }
}
