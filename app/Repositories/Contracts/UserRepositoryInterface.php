<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginateForAdminIndex(?string $search, int $perPage = 10): LengthAwarePaginator;
}
