<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class UserService
{
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

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (User $user) => UserSummaryData::fromModel($user))
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
}
