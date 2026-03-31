<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ClientSecret;
use App\Models\Scope;
use App\Models\SsoClient;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Prettus\Repository\Eloquent\Repository;

/**
 * @phpstan-type AdminClientFilters array{
 *     global?: string|null,
 *     status?: string|null
 * }
 * @phpstan-type ClientAttributes array{
 *     name: string,
 *     client_id?: string,
 *     client_secret_hash?: string,
 *     is_active: bool,
 *     token_policy_id?: int|null,
 *     redirect_uris?: array<int, string>,
 *     scopes?: array<int, string>
 * }
 * @phpstan-type ClientSecretAttributes array{
 *     name: string,
 *     secret_hash: string,
 *     last_four: string,
 *     is_active: bool
 * }
 */
class ClientRepository extends Repository implements ClientRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'name' => 'name',
        'client_id' => 'client_id',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    public function __construct(SsoClient $model)
    {
        parent::__construct($model);
    }

    /**
     * @return class-string<SsoClient>
     */
    public function model(): string
    {
        return SsoClient::class;
    }

    /**
     * @param AdminClientFilters $filters
     */
    public function paginateForAdminIndex(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = $this->model->newQuery()->with(['redirectUris', 'scopes', 'tokenPolicy']);

        $global = trim((string) ($filters['global'] ?? ''));
        $status = $filters['status'] ?? null;

        if ($global !== '') {
            $query->where(function ($innerQuery) use ($global): void {
                /** @var Builder<SsoClient> $innerQuery */
                $innerQuery
                    ->where('name', 'like', "%{$global}%")
                    ->orWhere('client_id', 'like', "%{$global}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOL));
        }

        $column = $this->sortableFields[$sortField ?? 'name'] ?? $this->sortableFields['name'];
        $direction = ($sortOrder ?? 1) === -1 ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param ClientAttributes $attributes
     */
    public function createClient(array $attributes): SsoClient
    {
        /** @var SsoClient $client */
        $client = $this->getModel()->newQuery()->create($attributes);

        return $client;
    }

    /**
     * @param ClientAttributes $attributes
     */
    public function updateClient(SsoClient $client, array $attributes): SsoClient
    {
        $client->fill($attributes);
        $client->save();

        return $client->refresh();
    }

    public function deleteClient(SsoClient $client): void
    {
        $client->delete();
    }

    public function syncRedirectUris(SsoClient $client, array $redirectUris): void
    {
        $normalized = collect($redirectUris)
            ->map(
                fn (string $uri): array => [
                    'uri' => trim($uri),
                    'uri_hash' => hash('sha256', trim($uri)),
                ]
            )
            ->unique('uri_hash')
            ->values();

        $hashes = $normalized->pluck('uri_hash')->all();

        $client->redirectUris()
            ->whereNotIn('uri_hash', $hashes)
            ->delete();

        foreach ($normalized as $item) {
            $client->redirectUris()->updateOrCreate(
                ['uri_hash' => $item['uri_hash']],
                ['uri' => $item['uri']],
            );
        }
    }

    public function syncScopes(SsoClient $client, array $scopeCodes): void
    {
        $scopeIds = Scope::query()
            ->whereIn('code', $scopeCodes)
            ->pluck('id')
            ->all();

        $client->scopes()->sync($scopeIds);
    }

    /**
     * @param ClientSecretAttributes $attributes
     */
    public function createSecret(SsoClient $client, array $attributes): void
    {
        $client->secrets()->create($attributes);
    }

    public function deactivateActiveSecrets(SsoClient $client): void
    {
        $client->secrets()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    public function revokeSecret(SsoClient $client, int $secretId): void
    {
        $client->secrets()
            ->whereKey($secretId)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    public function countUsableSecrets(SsoClient $client): int
    {
        return $client->secrets()
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();
    }
}
