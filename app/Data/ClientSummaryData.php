<?php

namespace App\Data;

use App\Models\SsoClient;
use Spatie\LaravelData\Data;

/**
 * @phpstan-type ClientSummaryPayload array{
 *     id: int,
 *     name: string,
 *     clientId: string,
 *     redirectUris: array<int, string>,
 *     redirectUriCount: int,
 *     isActive: bool,
 *     scopes: array<int, string>,
 *     scopesCount: int,
 *     tokenPolicyId: int|null,
 *     createdAt: string,
 *     canDelete: bool
 * }
 */
class ClientSummaryData extends Data
{
    /**
     * @param array<int, string> $redirectUris
     * @param array<int, string> $scopes
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $clientId,
        public array $redirectUris,
        public int $redirectUriCount,
        public bool $isActive,
        public array $scopes,
        public int $scopesCount,
        public ?int $tokenPolicyId,
        public string $createdAt,
        public bool $canDelete,
    ) {
    }

    /**
     * Hozza létre az adminisztrátori táblák és szelektorok által felhasznált 
     * ügyfél-összefoglaló hasznos adatot.
     *
     * @return self
     */
    public static function fromModel(SsoClient $client, bool $canDelete = true): self
    {
        $redirectUris = $client->normalizedRedirectUris();
        $scopes = $client->normalizedScopeCodes();

        return new self(
            id: $client->id,
            name: $client->name,
            clientId: $client->client_id,
            redirectUris: $redirectUris,
            redirectUriCount: count($redirectUris),
            isActive: (bool) $client->is_active,
            scopes: $scopes,
            scopesCount: count($scopes),
            tokenPolicyId: $client->token_policy_id,
            createdAt: $client->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            canDelete: $canDelete,
        );
    }
}
