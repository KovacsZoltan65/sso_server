<?php

namespace App\Repositories\Contracts;

use App\Models\ClientUserAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @phpstan-type ClientUserAccessFilters array{
 *     global?: string|null,
 *     client_id?: int|null,
 *     user_id?: int|null,
 *     status?: string|null
 * }
 * @phpstan-type ClientUserAccessAttributes array{
 *     client_id: int,
 *     user_id: int,
 *     is_active?: bool,
 *     allowed_from?: string|null,
 *     allowed_until?: string|null,
 *     notes?: string|null
 * }
 */
interface ClientUserAccessRepositoryInterface
{
    /**
     * @param ClientUserAccessFilters $filters
     */
    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    public function findById(int $id): ?ClientUserAccess;

    public function findAccessForClientAndUser(int $clientId, int $userId): ?ClientUserAccess;

    public function findActiveAccessForClientAndUser(int $clientId, int $userId): ?ClientUserAccess;

    public function clientHasAnyActiveRestrictions(int $clientId): bool;

    /**
     * @param ClientUserAccessAttributes $attributes
     */
    public function createAccess(array $attributes): ClientUserAccess;

    /**
     * @param ClientUserAccessAttributes $attributes
     */
    public function updateAccess(ClientUserAccess $access, array $attributes): ClientUserAccess;

    public function deleteAccess(ClientUserAccess $access): void;

    /**
     * @param array<int, int> $ids
     * @return Collection<int, ClientUserAccess>
     */
    public function getByIds(array $ids): Collection;

    /**
     * @param array<int, int> $ids
     */
    public function deleteByIds(array $ids): void;

    /**
     * @return Collection<int, ClientUserAccess>
     */
    public function listUsersForClient(int $clientId): Collection;

    /**
     * @return Collection<int, ClientUserAccess>
     */
    public function listClientsForUser(int $userId): Collection;

    /**
     * @return array<int, array{id: int, name: string, clientId: string}>
     */
    public function clientOptions(): array;

    /**
     * @return array<int, array{id: int, name: string, email: string, isActive: bool}>
     */
    public function userOptions(): array;
}
