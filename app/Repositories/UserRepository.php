<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Permission\Models\Role;

class UserRepository extends Repository implements UserRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'email' => 'email',
        'createdAt' => 'created_at',
        'emailVerifiedAt' => 'email_verified_at',
    ];

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator
    {
        $query = $this->getModel()
            ->newQuery()
            ->with('roles');

        $global = trim((string) ($filters['global'] ?? ''));
        $name = trim((string) ($filters['name'] ?? ''));
        $email = trim((string) ($filters['email'] ?? ''));
        $verified = $filters['verified'] ?? null;

        if ($global !== '') {
            $query->where(function ($innerQuery) use ($global): void {
                $innerQuery
                    ->where('name', 'like', "%{$global}%")
                    ->orWhere('email', 'like', "%{$global}%");
            });
        }

        if ($name !== '') {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($email !== '') {
            $query->where('email', 'like', "%{$email}%");
        }

        if ($verified === 'verified') {
            $query->whereNotNull('email_verified_at');
        }

        if ($verified === 'pending') {
            $query->whereNull('email_verified_at');
        }

        $column = $this->sortableFields[$sortField ?? ''] ?? 'name';
        $direction = $sortOrder === -1 ? 'desc' : 'asc';

        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function getRoleNames(): Collection
    {
        /** @var Collection<int, string> $roles */
        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name');

        return $roles;
    }

    public function createWithRoles(array $attributes, array $roles = []): User
    {
        /** @var User $user */
        $user = $this->getModel()->newQuery()->create($attributes);
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    public function updateWithRoles(User $user, array $attributes, array $roles = []): User
    {
        $user->fill($attributes);
        $user->save();
        $user->syncRoles($roles);

        return $user->load('roles');
    }

    public function getByIds(array $ids): Collection
    {
        /** @var Collection<int, User> $users */
        $users = $this->getModel()
            ->newQuery()
            ->with('roles')
            ->whereIn('id', $ids)
            ->get();

        return $users;
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    public function deleteByIds(array $ids): void
    {
        $this->getModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }
}
