<?php

namespace App\Services\OAuth;

use App\Models\Token;
use App\Models\User;

class OidcUserInfoService
{
    public function __construct(
        private readonly OidcSubjectService $subjectService,
        private readonly OidcClaimPolicyService $claimPolicyService,
    ) {
    }

    /**
     * @return array<string, bool|string>
     */
    public function claimsForToken(Token $token): array
    {
        /** @var User $user */
        $user = $token->user;

        return $this->claimsForUser($user, $token->scopes ?? []);
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, bool|string>
     */
    public function claimsForUser(User $user, array $scopes): array
    {
        return $this->claimPolicyService->userInfoClaimsForUser(
            user: $user,
            scopes: $scopes,
            subject: $this->subjectService->forUser($user),
        );
    }

    /**
     * @return array<int, string>
     */
    public function supportedClaims(): array
    {
        return $this->claimPolicyService->supportedClaims();
    }
}
