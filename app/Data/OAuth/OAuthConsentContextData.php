<?php

namespace App\Data\OAuth;

use Carbon\CarbonImmutable;

/**
 * @phpstan-type OAuthConsentContextPayload array{
 *     consent_token: string,
 *     client_id: string,
 *     client_db_id: int,
 *     client_display_name: string,
 *     client_description: string|null,
 *     redirect_uri: string,
 *     requested_scopes: array<int, string>,
 *     state: string|null,
 *     response_type: string,
 *     code_challenge: string|null,
 *     code_challenge_method: string|null,
 *     user_id: int,
 *     created_at: string,
 *     expires_at: string
 * }
 */
final class OAuthConsentContextData
{
    /**
     * @param array<int, string> $requestedScopes
     */
    public function __construct(
        public readonly string $consentToken,
        public readonly string $clientId,
        public readonly int $clientDbId,
        public readonly string $clientDisplayName,
        public readonly ?string $clientDescription,
        public readonly string $redirectUri,
        public readonly array $requestedScopes,
        public readonly ?string $state,
        public readonly string $responseType,
        public readonly ?string $codeChallenge,
        public readonly ?string $codeChallengeMethod,
        public readonly int $userId,
        public readonly string $createdAt,
        public readonly string $expiresAt,
    ) {
    }

    /**
     * @param OAuthConsentContextPayload $payload
     */
    public static function fromSessionPayload(array $payload): self
    {
        return new self(
            consentToken: (string) $payload['consent_token'],
            clientId: (string) $payload['client_id'],
            clientDbId: (int) $payload['client_db_id'],
            clientDisplayName: (string) $payload['client_display_name'],
            clientDescription: isset($payload['client_description']) ? (string) $payload['client_description'] : null,
            redirectUri: (string) $payload['redirect_uri'],
            requestedScopes: array_values(array_map(static fn (mixed $scope): string => trim((string) $scope), $payload['requested_scopes'])),
            state: isset($payload['state']) ? (string) $payload['state'] : null,
            responseType: (string) $payload['response_type'],
            codeChallenge: isset($payload['code_challenge']) ? (string) $payload['code_challenge'] : null,
            codeChallengeMethod: isset($payload['code_challenge_method']) ? (string) $payload['code_challenge_method'] : null,
            userId: (int) $payload['user_id'],
            createdAt: (string) $payload['created_at'],
            expiresAt: (string) $payload['expires_at'],
        );
    }

    /**
     * @return OAuthConsentContextPayload
     */
    public function toSessionPayload(): array
    {
        return [
            'consent_token' => $this->consentToken,
            'client_id' => $this->clientId,
            'client_db_id' => $this->clientDbId,
            'client_display_name' => $this->clientDisplayName,
            'client_description' => $this->clientDescription,
            'redirect_uri' => $this->redirectUri,
            'requested_scopes' => $this->requestedScopes,
            'state' => $this->state,
            'response_type' => $this->responseType,
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => $this->codeChallengeMethod,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    public function createdAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->createdAt);
    }

    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->expiresAt);
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        return $this->expiresAt()->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }
}
