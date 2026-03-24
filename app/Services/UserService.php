<?php

namespace App\Services;

use App\Data\UserSummaryData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getIndexPayload(?string $search, int $perPage = 10): array
    {
        $paginator = $this->users->paginateForAdminIndex($search, $perPage);

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (User $user) => UserSummaryData::fromModel($user))
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
