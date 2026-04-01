<?php

namespace App\Data;

use App\Models\Token;
use Spatie\LaravelData\Data;

class TokenAdminSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $tokenType,
        public int $userId,
        public string $userName,
        public string $userEmail,
        public int $clientId,
        public string $clientName,
        public string $clientPublicId,
        public string $status,
        public ?string $familyId,
        public ?int $parentTokenId,
        public ?int $replacedByTokenId,
        public string $issuedAt,
        public ?string $expiresAt,
        public ?string $revokedAt,
        public bool $canRevoke,
    ) {
    }

    public static function fromModel(Token $token, string $tokenType, bool $canRevoke = false): self
    {
        $isAccess = $tokenType === 'access_token';
        $expiresAt = $isAccess ? $token->access_token_expires_at : $token->refresh_token_expires_at;
        $revokedAt = $isAccess ? $token->access_token_revoked_at : $token->refresh_token_revoked_at;

        $status = 'active';

        if (! $isAccess && $token->replaced_by_token_id !== null) {
            $status = 'rotated';
        } elseif ($revokedAt !== null) {
            $status = 'revoked';
        } elseif ($expiresAt !== null && $expiresAt->isPast()) {
            $status = 'expired';
        }

        return new self(
            id: $token->id,
            tokenType: $tokenType,
            userId: $token->user_id,
            userName: $token->user->name,
            userEmail: $token->user->email,
            clientId: $token->sso_client_id,
            clientName: $token->client->name,
            clientPublicId: $token->client->client_id,
            status: $status,
            familyId: $token->family_id,
            parentTokenId: $token->parent_token_id,
            replacedByTokenId: $token->replaced_by_token_id,
            issuedAt: $token->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            expiresAt: $expiresAt?->toIso8601String(),
            revokedAt: $revokedAt?->toIso8601String(),
            canRevoke: $canRevoke && $status === 'active',
        );
    }
}
