<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
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
            'canManageUsers' => auth()->user()?->can('users.manage') ?? false,
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
        return $this->users->createWithRoles(
            attributes: Arr::only($payload, ['name', 'email', 'password']),
            roles: array_values($payload['roles'] ?? []),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateUser(User $user, array $payload): User
    {
        return $this->users->updateWithRoles(
            user: $user,
            attributes: Arr::only($payload, ['name', 'email']),
            roles: array_values($payload['roles'] ?? []),
        );
    }

    public function deleteUser(User $user, User $actingUser): void
    {
        $this->guardDeleteable($user, $actingUser);

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
