<?php

namespace App\Data;

use App\Models\TokenPolicy;
use Spatie\LaravelData\Data;

class TokenPolicySummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $description,
        public int $accessTokenTtlMinutes,
        public int $refreshTokenTtlMinutes,
        public bool $refreshTokenRotationEnabled,
        public bool $pkceRequired,
        public bool $reuseRefreshTokenForbidden,
        public bool $isDefault,
        public bool $isActive,
        public string $createdAt,
        public int $clientsCount,
        public bool $canDelete,
        public ?string $deleteBlockCode,
        public ?string $deleteBlockReason,
    ) {
    }

    public static function fromModel(
        TokenPolicy $tokenPolicy,
        int $clientsCount = 0,
        bool $canDelete = true,
        ?string $deleteBlockCode = null,
        ?string $deleteBlockReason = null,
    ): self {
        return new self(
            id: $tokenPolicy->id,
            name: $tokenPolicy->name,
            code: $tokenPolicy->code,
            description: $tokenPolicy->description,
            accessTokenTtlMinutes: (int) $tokenPolicy->access_token_ttl_minutes,
            refreshTokenTtlMinutes: (int) $tokenPolicy->refresh_token_ttl_minutes,
            refreshTokenRotationEnabled: (bool) $tokenPolicy->refresh_token_rotation_enabled,
            pkceRequired: (bool) $tokenPolicy->pkce_required,
            reuseRefreshTokenForbidden: (bool) $tokenPolicy->reuse_refresh_token_forbidden,
            isDefault: (bool) $tokenPolicy->is_default,
            isActive: (bool) $tokenPolicy->is_active,
            createdAt: $tokenPolicy->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            clientsCount: $clientsCount,
            canDelete: $canDelete,
            deleteBlockCode: $deleteBlockCode,
            deleteBlockReason: $deleteBlockReason,
        );
    }
}
