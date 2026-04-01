<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator;

    public function findByIdWithRelations(int $id): ?AuditLog;

    /**
     * @return array<int, string>
     */
    public function categoryOptions(): array;

    /**
     * @return array<int, string>
     */
    public function actorTypeOptions(): array;

    /**
     * @return array<int, string>
     */
    public function subjectTypeOptions(): array;
}
