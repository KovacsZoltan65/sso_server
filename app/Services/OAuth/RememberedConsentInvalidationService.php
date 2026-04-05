<?php

namespace App\Services\OAuth;

use App\Models\SsoClient;
use App\Models\UserClientConsent;
use App\Repositories\Contracts\UserClientConsentRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\OAuth\RememberedConsentRevocationReasons;
use Illuminate\Support\Arr;

class RememberedConsentInvalidationService
{
    public function __construct(
        private readonly UserClientConsentRepositoryInterface $consents,
        private readonly OAuthRememberedConsentService $rememberedConsentService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     * @return array{affected_count: int, reason: string|null, changed_fields: array<string, array{old: mixed, new: mixed}>}
     */
    public function invalidateForClientTrustChange(SsoClient $client, array $changedFields): array
    {
        $normalizedChanges = Arr::only($changedFields, ['trust_tier', 'consent_bypass_allowed']);

        if ($normalizedChanges === []) {
            return [
                'affected_count' => 0,
                'reason' => null,
                'changed_fields' => [],
            ];
        }

        $reason = array_key_exists('trust_tier', $normalizedChanges)
            ? RememberedConsentRevocationReasons::TRUST_TIER_CHANGED
            : RememberedConsentRevocationReasons::CONSENT_BYPASS_POLICY_CHANGED;

        $affectedCount = 0;

        foreach ($this->consents->activeForClient($client->id) as $consent) {
            $result = $this->invalidateConsentGrant($consent, $reason, [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'changed_attributes' => $this->formatChangedAttributes($normalizedChanges),
            ]);

            if ($result['invalidated']) {
                $affectedCount++;
            }
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.remembered_consent.bulk_invalidated_for_client',
            description: 'Remembered consents invalidated for client trust context change.',
            subject: $client,
            causer: auth()->user(),
            properties: [
                'client_id' => $client->id,
                'client_public_id' => $client->client_id,
                'actor_user_id' => auth()->id(),
                'affected_count' => $affectedCount,
                'reason' => $reason,
                'changed_attributes' => $this->formatChangedAttributes($normalizedChanges),
            ],
        );

        return [
            'affected_count' => $affectedCount,
            'reason' => $reason,
            'changed_fields' => $normalizedChanges,
        ];
    }

    /**
     * @return array{affected_count: int, reason: string, policy_version: string, previous_policy_version: string|null}
     */
    public function invalidateForPolicyVersionChange(string $newVersion, ?string $oldVersion = null): array
    {
        $newVersion = trim($newVersion);
        $oldVersion = $oldVersion !== null ? trim($oldVersion) : null;
        $affectedCount = 0;

        foreach ($this->consents->activeForPolicyVersionMismatch($newVersion, $oldVersion) as $consent) {
            $result = $this->invalidateConsentGrant(
                $consent,
                RememberedConsentRevocationReasons::CONSENT_POLICY_VERSION_CHANGED,
                [
                    'policy_version' => $newVersion,
                    'old_value' => $consent->consent_policy_version,
                    'new_value' => $newVersion,
                ],
            );

            if ($result['invalidated']) {
                $affectedCount++;
            }
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.remembered_consent.bulk_invalidated_for_policy_version',
            description: 'Remembered consents invalidated for consent policy version change.',
            subject: null,
            causer: auth()->user(),
            properties: [
                'actor_user_id' => auth()->id(),
                'affected_count' => $affectedCount,
                'reason' => RememberedConsentRevocationReasons::CONSENT_POLICY_VERSION_CHANGED,
                'old_value' => $oldVersion,
                'new_value' => $newVersion,
                'policy_version' => $newVersion,
            ],
        );

        return [
            'affected_count' => $affectedCount,
            'reason' => RememberedConsentRevocationReasons::CONSENT_POLICY_VERSION_CHANGED,
            'policy_version' => $newVersion,
            'previous_policy_version' => $oldVersion,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{consent: UserClientConsent, invalidated: bool, previous_status: string, new_status: string}
     */
    public function invalidateConsentGrant(UserClientConsent $consent, string $reason, array $meta = []): array
    {
        $previousStatus = $consent->currentStatus();

        if ($consent->isRevoked()) {
            return [
                'consent' => $consent->refresh(),
                'invalidated' => false,
                'previous_status' => $previousStatus,
                'new_status' => $consent->fresh()->currentStatus(),
            ];
        }

        $consent = $this->rememberedConsentService->revokeConsent($consent, $reason);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.remembered_consent.invalidated',
            description: 'Remembered consent invalidated automatically.',
            subject: $consent,
            causer: auth()->user(),
            properties: [
                'consent_id' => $consent->id,
                'client_id' => $consent->client_id,
                'client_public_id' => $consent->client->client_id,
                'target_user_id' => $consent->user_id,
                'actor_user_id' => auth()->id(),
                'reason' => $reason,
                'previous_status' => $previousStatus,
                'new_status' => $consent->currentStatus(),
                ...$meta,
            ],
        );

        return [
            'consent' => $consent,
            'invalidated' => true,
            'previous_status' => $previousStatus,
            'new_status' => $consent->currentStatus(),
        ];
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changedFields
     * @return array<string, string>
     */
    private function formatChangedAttributes(array $changedFields): array
    {
        return collect($changedFields)
            ->mapWithKeys(fn (array $change, string $field) => [
                $field => sprintf('%s -> %s', $this->stringifyValue($change['old']), $this->stringifyValue($change['new'])),
            ])
            ->all();
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return trim((string) $value);
    }
}
