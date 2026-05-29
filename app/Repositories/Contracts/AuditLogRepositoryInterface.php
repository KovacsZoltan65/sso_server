<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        string|int|null $sortOrder,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, string>
     */
    public function eventOptions(): Collection;
}
