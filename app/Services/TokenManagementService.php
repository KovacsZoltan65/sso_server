<?php

namespace App\Services;

use App\Data\TokenAdminSummaryData;
use App\Models\Token;
use App\Repositories\Contracts\TokenRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Permissions\TokenPermissions;
use Illuminate\Support\Collection;

class TokenManagementService
{
    public function __construct(
        private readonly TokenRepositoryInterface $tokens,
        private readonly AuditLogService $auditLogService,
        private readonly TokenFamilyService $tokenFamilyService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $tokenType = (string) ($filters['token_type'] ?? 'refresh_token');
        $paginator = $this->tokens->paginateForAdmin($filters, $sortField, $sortOrder, $perPage, $page);
        $canRevoke = auth()->user()?->can(TokenPermissions::REVOKE) || false;
        $canRevokeFamily = auth()->user()?->can(TokenPermissions::REVOKE_FAMILY) || false;

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (Token $token) => TokenAdminSummaryData::fromModel($token, $tokenType, $canRevoke, $canRevokeFamily))
                ->values()
                ->all(),
            'filters' => [
                'global' => $filters['global'] ?? null,
                'client_id' => $filters['client_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'token_type' => $tokenType,
                'state' => $filters['state'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'createdAt',
                'order' => $sortOrder ?? -1,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
            'clientOptions' => $this->clientOptions(),
            'userOptions' => $this->userOptions(),
            'canManageTokens' => $canRevoke,
            'canManageTokenFamilies' => $canRevokeFamily,
        ];
    }

    public function revokeToken(Token $token, string $tokenType, ?string $reason = null): void
    {
        if ($tokenType === 'access_token') {
            if ($token->access_token_revoked_at !== null) {
                return;
            }

            $this->tokens->revokeAccessToken($token, $reason ?: 'admin_revoked');
        } else {
            if ($token->refresh_token_hash === null || $token->refresh_token_revoked_at !== null || $token->replaced_by_token_id !== null) {
                return;
            }

            $this->tokens->revokeRefreshToken($token, $reason ?: 'admin_revoked');
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.token.revoked',
            description: 'OAuth token revoked.',
            subject: $token->client,
            causer: auth()->user(),
            properties: [
                'client_id' => $token->sso_client_id,
                'client_public_id' => $token->client->client_id,
                'target_user_id' => $token->user_id,
                'token_id' => $token->id,
                'token_kind' => $tokenType,
                'family_id' => $token->family_id,
                'parent_token_id' => $token->parent_token_id,
                'replaced_by_token_id' => $token->replaced_by_token_id,
                'revoked_reason' => $reason ?: 'admin_revoked',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeFamily(string $familyId, ?string $reason = null): array
    {
        return $this->tokenFamilyService->revokeFamily(
            familyId: $familyId,
            reason: $reason ?: 'admin_family_revoked',
            actor: auth()->user(),
            context: [
                'trigger' => 'admin_action',
                'incident_related' => false,
            ],
        );
    }

    /**
     * @return array<int, array{id: int, name: string, clientId: string}>
     */
    private function clientOptions(): array
    {
        return $this->tokens->clientOptionsForAdmin()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(): array
    {
        return $this->tokens->userOptionsForAdmin()
            ->all();
    }
}
