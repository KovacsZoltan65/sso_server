<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class SelfServiceProfileData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $emailVerifiedAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
        );
    }
}
