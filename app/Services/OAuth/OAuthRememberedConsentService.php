<?php

namespace App\Services\OAuth;

use App\Data\OAuth\OAuthRememberedConsentDecisionResult;
use App\Models\SsoClient;
use App\Models\User;
use App\Models\UserClientConsent;
use Illuminate\Support\Carbon;

class OAuthRememberedConsentService
{
    public const CONSENT_POLICY_VERSION = 'remembered-consent-v1';

    /**
     * Storage foundation only:
     * this service persists explicit consent grants, but does not decide authorize-time reuse yet.
     *
     * @param array<int, string> $scopeCodes
     */
    public function storeApprovedConsent(
        User $user,
        SsoClient $client,
        array $scopeCodes,
        string $redirectUri,
    ): UserClientConsent {
        $grantedAt = now();

        return UserClientConsent::query()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'granted_scope_codes' => $this->normalizeScopeCodes($scopeCodes),
            'granted_scope_fingerprint' => $this->scopeFingerprint($scopeCodes),
            'redirect_uri_hash' => $this->redirectUriFingerprint($redirectUri),
            'trust_tier_snapshot' => $client->trust_tier,
            'consent_bypass_allowed_snapshot' => (bool) $client->consent_bypass_allowed,
            'consent_policy_version' => $this->consentPolicyVersion(),
            'granted_at' => $grantedAt,
            'expires_at' => $grantedAt->copy()->addDays($this->rememberedConsentTtlDays()),
        ]);
    }

    /**
     * @param array<int, string> $scopeCodes
     */
    public function findActiveConsent(
        User $user,
        SsoClient $client,
        array $scopeCodes,
        ?string $redirectUri = null,
    ): ?UserClientConsent {
        $query = UserClientConsent::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->where('granted_scope_fingerprint', $this->scopeFingerprint($scopeCodes))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('granted_at');

        if ($redirectUri !== null && trim($redirectUri) !== '') {
            $query->where('redirect_uri_hash', $this->redirectUriFingerprint($redirectUri));
        }

        /** @var UserClientConsent|null $consent */
        $consent = $query->first();

        return $consent;
    }

    /**
     * First iteration remembered consent reuse:
     * exact scope, exact redirect, exact trust snapshot, exact policy version.
     *
     * @param array<int, string> $scopeCodes
     */
    public function evaluateReusableConsent(
        User $user,
        SsoClient $client,
        array $scopeCodes,
        string $redirectUri,
    ): OAuthRememberedConsentDecisionResult {
        $scopeFingerprint = $this->scopeFingerprint($scopeCodes);
        $redirectFingerprint = $this->redirectUriFingerprint($redirectUri);

        /** @var \Illuminate\Database\Eloquent\Collection<int, UserClientConsent> $exactScopeCandidates */
        $exactScopeCandidates = UserClientConsent::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->where('granted_scope_fingerprint', $scopeFingerprint)
            ->latest('granted_at')
            ->get();

        foreach ($exactScopeCandidates as $candidate) {
            if ($candidate->isRevoked() || $candidate->isExpired()) {
                continue;
            }

            if ($candidate->redirect_uri_hash !== $redirectFingerprint) {
                continue;
            }

            if ($candidate->trust_tier_snapshot !== $client->trust_tier) {
                continue;
            }

            if ($candidate->consent_bypass_allowed_snapshot !== (bool) $client->consent_bypass_allowed) {
                continue;
            }

            if ($candidate->consent_policy_version !== $this->consentPolicyVersion()) {
                continue;
            }

            return new OAuthRememberedConsentDecisionResult(
                shouldReuse: true,
                reason: 'remembered_consent_match',
                consent: $candidate,
            );
        }

        $candidate = $exactScopeCandidates->first();

        if ($candidate instanceof UserClientConsent) {
            if ($candidate->isRevoked()) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_revoked',
                    consent: $candidate,
                );
            }

            if ($candidate->isExpired()) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_expired',
                    consent: $candidate,
                );
            }

            if ($candidate->redirect_uri_hash !== $redirectFingerprint) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_redirect_mismatch',
                    consent: $candidate,
                );
            }

            if ($candidate->trust_tier_snapshot !== $client->trust_tier) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_trust_mismatch',
                    consent: $candidate,
                );
            }

            if ($candidate->consent_bypass_allowed_snapshot !== (bool) $client->consent_bypass_allowed) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_bypass_mismatch',
                    consent: $candidate,
                );
            }

            if ($candidate->consent_policy_version !== $this->consentPolicyVersion()) {
                return new OAuthRememberedConsentDecisionResult(
                    shouldReuse: false,
                    reason: 'remembered_consent_policy_mismatch',
                    consent: $candidate,
                );
            }
        }

        /** @var UserClientConsent|null $candidate */
        $candidate = UserClientConsent::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->latest('granted_at')
            ->first();

        if (! $candidate instanceof UserClientConsent) {
            return new OAuthRememberedConsentDecisionResult(
                shouldReuse: false,
                reason: 'remembered_consent_missing',
            );
        }

        if ($candidate->granted_scope_fingerprint !== $scopeFingerprint) {
            return new OAuthRememberedConsentDecisionResult(
                shouldReuse: false,
                reason: 'remembered_consent_scope_mismatch',
                consent: $candidate,
            );
        }

        return new OAuthRememberedConsentDecisionResult(
            shouldReuse: false,
            reason: 'remembered_consent_missing',
        );
    }

    public function revokeConsent(UserClientConsent $consent, ?string $reason = null): UserClientConsent
    {
        if ($consent->isRevoked()) {
            return $consent;
        }

        $consent->forceFill([
            'revoked_at' => now(),
            'revocation_reason' => $this->normalizeNullableString($reason),
        ])->save();

        return $consent->refresh();
    }

    public function isConsentUsable(?UserClientConsent $consent): bool
    {
        return $consent instanceof UserClientConsent && $consent->isActive();
    }

    /**
     * @param array<int, string> $scopeCodes
     * @return array<int, string>
     */
    public function normalizeScopeCodes(array $scopeCodes): array
    {
        return collect($scopeCodes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter(static fn (string $scope): bool => $scope !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $scopeCodes
     */
    public function scopeFingerprint(array $scopeCodes): string
    {
        return hash('sha256', json_encode($this->normalizeScopeCodes($scopeCodes), JSON_THROW_ON_ERROR));
    }

    public function redirectUriFingerprint(string $redirectUri): string
    {
        return hash('sha256', trim($redirectUri));
    }

    public function consentPolicyVersion(): string
    {
        return trim((string) config('services.oauth.consent_policy_version', self::CONSENT_POLICY_VERSION))
            ?: self::CONSENT_POLICY_VERSION;
    }

    private function rememberedConsentTtlDays(): int
    {
        return max(1, (int) config('services.oauth.remembered_consent_ttl_days', 30));
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
