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
        public string $createdAt,
    ) {
    }

    public static function fromModel(Permission $permission): self
    {
        return new self(
            id: $permission->id,
            name: $permission->name,
            guardName: $permission->guard_name,
            rolesCount: (int) ($permission->roles_count ?? 0),
            createdAt: $permission->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
        );
    }
}
