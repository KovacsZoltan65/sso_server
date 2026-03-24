<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection;

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $roles
     */
    public function createWithRoles(array $attributes, array $roles = []): User;

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string> $roles
     */
    public function updateWithRoles(User $user, array $attributes, array $roles = []): User;
}
