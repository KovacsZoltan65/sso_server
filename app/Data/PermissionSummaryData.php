<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;

class PermissionSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $guardName,
        public int $rolesCount,
        public int $usersCount,
        public string $createdAt,
        public bool $canDelete,
        public ?string $deleteBlockCode,
        public ?string $deleteBlockReason,
    ) {
    }

    public static function fromModel(
        Permission $permission,
        bool $canDelete = true,
        ?string $deleteBlockCode = null,
        ?string $deleteBlockReason = null,
    ): self
    {
        return new self(
            id: $permission->id,
            name: $permission->name,
            guardName: $permission->guard_name,
            rolesCount: (int) ($permission->roles_count ?? 0),
            usersCount: (int) ($permission->users_count ?? 0),
            createdAt: $permission->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            canDelete: $canDelete,
            deleteBlockCode: $deleteBlockCode,
            deleteBlockReason: $deleteBlockReason,
        );
    }
}
