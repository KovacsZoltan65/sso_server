<?php

namespace App\Services\OAuth;

use App\Models\AuthorizationCode;
use App\Models\User;

class OidcClaimPolicyService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const SCOPE_TO_CLAIMS = [
        'openid' => ['sub'],
        'profile' => ['name'],
        'email' => ['email', 'email_verified'],
    ];

    /**
     * @return array<int, string>
     */
    public function supportedClaims(): array
    {
        return collect(self::SCOPE_TO_CLAIMS)
            ->flatten()
            ->push('sid')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $scopes
     * @return array<int, string>
     */
    public function allowedClaimNamesForScopes(array $scopes, string $surface): array
    {
        $normalizedScopes = collect($scopes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();

        $claims = collect($normalizedScopes)
            ->flatMap(fn (string $scope): array => self::SCOPE_TO_CLAIMS[$scope] ?? [])
            ->unique()
            ->values();

        if ($surface === 'id_token') {
            return [];
        }

        if ($surface === 'userinfo') {
            return $claims->all();
        }

        return [];
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, bool|string>
     */
    public function userInfoClaimsForUser(User $user, array $scopes, string $subject): array
    {
        $claims = [];

        foreach ($this->allowedClaimNamesForScopes($scopes, 'userinfo') as $claim) {
            $value = $this->claimValue($claim, $user, $subject);

            if ($value !== null) {
                $claims[$claim] = $value;
            }
        }

        return $claims;
    }

    /**
     * @return array<string, bool|string>
     */
    public function idTokenIdentityClaimsForAuthorizationCode(AuthorizationCode $authorizationCode, string $subject): array
    {
        return [];
    }

    private function claimValue(string $claim, User $user, string $subject): bool|string|null
    {
        return match ($claim) {
            'sub' => $subject,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            default => null,
        };
    }
}
