<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UserClientConsent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserClientConsentRepositoryInterface
{
    /**
     * @return Collection<int, UserClientConsent>
     */
    public function activeForClient(int $clientId): Collection;

    /**
     * @return Collection<int, UserClientConsent>
     */
    public function activeForPolicyVersionMismatch(string $currentVersion, ?string $oldVersion = null): Collection;

    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, array{id: int, name: string, email: string}>
     */
    public function userOptionsForAdmin(): Collection;

    /**
     * @return Collection<int, array{id: int, name: string, clientId: string}>
     */
    public function clientOptionsForAdmin(): Collection;

    public function updateConsent(UserClientConsent $consent, array $attributes): UserClientConsent;
}
