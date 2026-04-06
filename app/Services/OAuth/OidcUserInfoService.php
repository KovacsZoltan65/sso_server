<?php

namespace App\Services\OAuth;

use App\Models\Token;
use App\Models\User;

class OidcUserInfoService
{
    public function __construct(
        private readonly OidcSubjectService $subjectService,
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
        $claims = [
            'sub' => $this->subjectService->forUser($user),
        ];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return $claims;
    }

    /**
     * @return array<int, string>
     */
    public function supportedClaims(): array
    {
        return ['sub', 'name', 'email', 'email_verified'];
    }
}
