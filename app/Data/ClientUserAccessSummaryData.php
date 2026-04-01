<?php

namespace App\Data;

use App\Models\ClientUserAccess;
use Spatie\LaravelData\Data;

class ClientUserAccessSummaryData extends Data
{
    public function __construct(
        public int $id,
        public int $clientId,
        public string $clientName,
        public string $clientPublicId,
        public int $userId,
        public string $userName,
        public string $userEmail,
        public bool $isActive,
        public ?string $allowedFrom,
        public ?string $allowedUntil,
        public ?string $notes,
        public string $createdAt,
        public bool $canDelete,
    ) {
    }

    public static function fromModel(ClientUserAccess $access, bool $canDelete = true): self
    {
        return new self(
            id: $access->id,
            clientId: $access->client_id,
            clientName: $access->client->name,
            clientPublicId: $access->client->client_id,
            userId: $access->user_id,
            userName: $access->user->name,
            userEmail: $access->user->email,
            isActive: (bool) $access->is_active,
            allowedFrom: $access->allowed_from?->toIso8601String(),
            allowedUntil: $access->allowed_until?->toIso8601String(),
            notes: $access->notes,
            createdAt: $access->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            canDelete: $canDelete,
        );
    }
}
