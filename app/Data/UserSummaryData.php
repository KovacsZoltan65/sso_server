<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class UserSummaryData extends Data
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $isActive,
        public array $roles,
        public ?string $emailVerifiedAt,
        public string $createdAt,
        public bool $canDelete,
        public ?string $deleteBlockCode,
        public ?string $deleteBlockReason,
    ) {
    }

    public static function fromModel(
        User $user,
        bool $canDelete = true,
        ?string $deleteBlockCode = null,
        ?string $deleteBlockReason = null,
    ): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            isActive: (bool) $user->is_active,
            roles: $user->roleNames(),
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            createdAt: $user->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            canDelete: $canDelete,
            deleteBlockCode: $deleteBlockCode,
            deleteBlockReason: $deleteBlockReason,
        );
    }
}
