<?php

use App\Enums\OAuth\OAuthTrustDecision;
use App\Models\SsoClient;
use App\Services\OAuth\OAuthTrustDecisionService;

function trustDecisionClient(array $attributes = []): SsoClient
{
    return new SsoClient(array_merge([
        'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
        'is_active' => true,
        'consent_bypass_allowed' => false,
        'is_first_party' => false,
    ], $attributes));
}

it('returns skip_consent for trusted first-party clients when bypass is allowed', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
            'is_first_party' => true,
            'consent_bypass_allowed' => true,
        ]),
        ['openid', 'profile'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::SkipConsent)
        ->and($decision->reason)->toBe('trusted_first_party_bypass_allowed');
});

it('returns show_consent for trusted first-party clients when bypass is disabled', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_TRUSTED,
            'is_first_party' => true,
            'consent_bypass_allowed' => false,
        ]),
        ['openid'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::ShowConsent)
        ->and($decision->reason)->toBe('consent_bypass_not_allowed');
});

it('returns show_consent for first-party untrusted clients', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => SsoClient::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
            'is_first_party' => true,
        ]),
        ['openid'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::ShowConsent)
        ->and($decision->reason)->toBe('first_party_untrusted_requires_consent');
});

it('returns show_consent for third-party clients', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => SsoClient::TRUST_TIER_THIRD_PARTY,
        ]),
        ['openid'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::ShowConsent)
        ->and($decision->reason)->toBe('third_party_requires_consent');
});

it('returns deny_authorization for machine-to-machine clients in interactive authorize flows', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => SsoClient::TRUST_TIER_MACHINE_TO_MACHINE,
        ]),
        ['openid'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::DenyAuthorization)
        ->and($decision->reason)->toBe('interactive_authorization_not_allowed');
});

it('falls back to show_consent for unknown trust data', function (): void {
    $decision = app(OAuthTrustDecisionService::class)->decideForAuthorization(
        trustDecisionClient([
            'trust_tier' => 'legacy_custom_tier',
        ]),
        ['openid'],
        'code',
    );

    expect($decision->decision)->toBe(OAuthTrustDecision::ShowConsent)
        ->and($decision->reason)->toBe('unknown_trust_tier');
});
