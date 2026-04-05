<?php

namespace App\Data;

use App\Models\UserClientConsent;
use Spatie\LaravelData\Data;

class UserClientConsentAdminSummaryData extends Data
{
    /**
     * @param array<int, string> $scopeCodes
     */
    public function __construct(
        public int $id,
        public int $userId,
        public string $userName,
        public string $userEmail,
        public int $clientId,
        public string $clientName,
        public string $clientPublicId,
        public array $scopeCodes,
        public string $trustTierSnapshot,
        public string $status,
        public string $grantedAt,
        public string $expiresAt,
        public ?string $revokedAt,
        public ?string $revocationReason,
        public bool $canRevoke,
    ) {
    }

    public static function fromModel(UserClientConsent $consent, bool $canRevoke = false): self
    {
        return new self(
            id: $consent->id,
            userId: $consent->user_id,
            userName: $consent->user->name,
            userEmail: $consent->user->email,
            clientId: $consent->client_id,
            clientName: $consent->client->name,
            clientPublicId: $consent->client->client_id,
            scopeCodes: $consent->granted_scope_codes ?? [],
            trustTierSnapshot: $consent->trust_tier_snapshot,
            status: $consent->currentStatus(),
            grantedAt: $consent->granted_at->toDateTimeString(),
            expiresAt: $consent->expires_at->toIso8601String(),
            revokedAt: $consent->revoked_at?->toIso8601String(),
            revocationReason: $consent->revocation_reason,
            canRevoke: $canRevoke && ! $consent->isRevoked(),
        );
    }
}
