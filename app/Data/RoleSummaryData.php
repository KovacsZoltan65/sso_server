<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Role;

class RoleSummaryData extends Data
{
    /**
     * @phpstan-type RoleSummaryPayload array{
     *     id: int,
     *     name: string,
     *     guardName: string,
     *     permissions: array<int, string>,
     *     permissionCount: int,
     *     usersCount: int,
     *     createAt: string,
     *     canDelete: bool,
     *     deleteBlockCode: string,
     *     deleteBlockReason: string
     * }
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $guardName,
        public array $permissions,
        public int $permissionsCount,
        public int $usersCount,
        public string $createdAt,
        public bool $canDelete,
        public ?string $deleteBlockCode,
        public ?string $deleteBlockReason,
    ) {
    }

    public static function fromModel(
        Role $role,
        bool $canDelete = true,
        ?string $deleteBlockCode = null,
        ?string $deleteBlockReason = null,
    ): self
    {
        $permissions = $role->relationLoaded('permissions')
            ? $role->permissions->pluck('name')->values()->all()
            : $role->permissions()->pluck('name')->values()->all();

        return new self(
            id: $role->id,
            name: $role->name,
            guardName: $role->guard_name,
            permissions: $permissions,
            permissionsCount: (int) ($role->permissions_count ?? count($permissions)),
            usersCount: (int) ($role->users_count ?? 0),
            createdAt: $role->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            canDelete: $canDelete,
            deleteBlockCode: $deleteBlockCode,
            deleteBlockReason: $deleteBlockReason,
        );
    }
}
