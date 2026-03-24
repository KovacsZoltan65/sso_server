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
        public array $roles,
        public ?string $emailVerifiedAt,
        public string $createdAt,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            roles: $user->roleNames(),
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            createdAt: $user->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
        );
    }
}
