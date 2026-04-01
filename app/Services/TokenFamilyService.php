<?php

namespace App\Services;

use App\Models\Token;
use App\Models\User;
use App\Repositories\Contracts\TokenRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type FamilyRevokeResult array{
 *     family_id: string,
 *     revoked_count: int,
 *     already_revoked: bool,
 *     incident_related: bool,
 *     trigger: string,
 *     reason: string
 * }
 */
class TokenFamilyService
{
    public function __construct(
        private readonly TokenRepositoryInterface $tokens,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return FamilyRevokeResult
     */
    public function revokeFamily(string $familyId, string $reason, ?User $actor = null, array $context = []): array
    {
        $tokens = $this->tokens->findFamilyTokens($familyId);

        if ($tokens->isEmpty()) {
            throw (new ModelNotFoundException())->setModel(Token::class, [$familyId]);
        }

        /** @var Token $representative */
        $representative = $tokens->first();
        $alreadyRevoked = ! $this->tokens->familyHasActiveTokens($familyId);
        $trigger = (string) ($context['trigger'] ?? 'admin_action');
        $incidentRelated = (bool) ($context['incident_related'] ?? false);
        $incidentDetectedAt = $context['incident_detected_at'] ?? null;
        $incidentReason = $context['incident_reason'] ?? null;

        return DB::transaction(function () use (
            $familyId,
            $reason,
            $actor,
            $representative,
            $alreadyRevoked,
            $trigger,
            $incidentRelated,
            $incidentDetectedAt,
            $incidentReason
        ): array {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.token.family_revoke_started',
                description: 'OAuth token family revoke started.',
                subject: $representative->client,
                causer: $actor,
                properties: [
                    'client_id' => $representative->sso_client_id,
                    'client_public_id' => $representative->client->client_id,
                    'user_id' => $representative->user_id,
                    'token_id' => $representative->id,
                    'family_id' => $familyId,
                    'reason' => $reason,
                    'decision' => 'started',
                    'trigger' => $trigger,
                    'incident_detected_at' => $incidentDetectedAt,
                    'already_revoked' => $alreadyRevoked,
                ],
            );

            $revokedCount = $alreadyRevoked
                ? 0
                : $this->tokens->revokeFamilyTokens(
                    familyId: $familyId,
                    reason: $reason,
                    trigger: $trigger,
                    incidentDetectedAt: is_string($incidentDetectedAt) ? $incidentDetectedAt : null,
                    incidentReason: is_string($incidentReason) ? $incidentReason : null,
                );

            $properties = [
                'client_id' => $representative->sso_client_id,
                'client_public_id' => $representative->client->client_id,
                'user_id' => $representative->user_id,
                'token_id' => $representative->id,
                'family_id' => $familyId,
                'reason' => $reason,
                'decision' => $alreadyRevoked ? 'noop' : 'family_revoked',
                'trigger' => $trigger,
                'revoked_count' => $revokedCount,
                'already_revoked' => $alreadyRevoked,
                'incident_detected_at' => $incidentDetectedAt,
            ];

            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_OAUTH,
                event: 'oauth.token.family_revoke_completed',
                description: 'OAuth token family revoke completed.',
                subject: $representative->client,
                causer: $actor,
                properties: $properties,
            );

            if ($trigger === 'admin_action') {
                $this->auditLogService->logSuccess(
                    logName: AuditLogService::LOG_OAUTH,
                    event: 'oauth.token.family_revoked_by_admin',
                    description: 'OAuth token family revoked by admin.',
                    subject: $representative->client,
                    causer: $actor,
                    properties: $properties,
                );
            }

            return [
                'family_id' => $familyId,
                'revoked_count' => $revokedCount,
                'already_revoked' => $alreadyRevoked,
                'incident_related' => $incidentRelated,
                'trigger' => $trigger,
                'reason' => $reason,
            ];
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function handleSuspiciousRefreshReuse(Token $refreshToken, array $context = []): void
    {
        $incidentDetectedAt = now()->toIso8601String();
        $incidentReason = (string) ($context['incident_reason'] ?? 'refresh_reuse_detected');

        $reusedToken = $this->tokens->markRefreshReuseDetected(
            token: $refreshToken,
            reason: $incidentReason,
            incidentDetectedAt: $incidentDetectedAt,
        );

        $incidentContext = $this->buildFamilyIncidentContext($reusedToken, [
            ...$context,
            'trigger' => 'automatic_reuse_response',
            'incident_related' => true,
            'incident_detected_at' => $incidentDetectedAt,
            'incident_reason' => $incidentReason,
        ]);

        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.refresh_token.suspicious_reuse_detected',
            description: 'OAuth suspicious refresh token reuse detected.',
            subject: $refreshToken->client,
            causer: $refreshToken->user,
            properties: [
                ...$incidentContext,
                'decision' => 'incident_detected',
            ],
        );

        $this->revokeFamily(
            familyId: (string) $refreshToken->family_id,
            reason: 'family_revoked_due_to_reuse',
            actor: null,
            context: $incidentContext,
        );
    }

    public function isFamilyRevoked(string $familyId): bool
    {
        return ! $this->tokens->familyHasActiveTokens($familyId);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildFamilyIncidentContext(Token $token, array $context = []): array
    {
        return [
            'client_id' => $token->sso_client_id,
            'client_public_id' => $token->client->client_id,
            'user_id' => $token->user_id,
            'token_id' => $token->id,
            'family_id' => $token->family_id,
            'reason' => $context['incident_reason'] ?? 'refresh_reuse_detected',
            'trigger' => $context['trigger'] ?? 'automatic_reuse_response',
            'incident_detected_at' => $context['incident_detected_at'] ?? null,
        ];
    }
}
