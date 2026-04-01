<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Prettus\Repository\Eloquent\Repository;

class AuditLogRepository extends Repository implements AuditLogRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'id' => 'activity_log.id',
        'occurred_at' => 'activity_log.created_at',
        'event_type' => 'activity_log.event',
        'category' => 'activity_log.log_name',
    ];

    public function __construct(AuditLog $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return AuditLog::class;
    }

    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        ?int $sortOrder,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        $table = $this->getModel()->getTable();
        $column = $this->sortableFields[$sortField ?? ''] ?? $this->sortableFields['occurred_at'];
        $direction = $sortOrder === 1 ? 'asc' : 'desc';
        $severitySql = $this->severitySql($table);

        $query = $this->buildAdminQuery($filters)
            ->with(['causer', 'subject']);

        if (($sortField ?? null) === 'severity') {
            $query->orderByRaw(sprintf('%s %s', $severitySql, $direction));
        } else {
            $query->orderBy($column, $direction);
        }

        return $query
            ->paginate($perPage, ["{$table}.*"], 'page', $page)
            ->withQueryString();
    }

    public function findByIdWithRelations(int $id): ?AuditLog
    {
        /** @var AuditLog|null $auditLog */
        $auditLog = $this->getModel()
            ->newQuery()
            ->with(['causer', 'subject'])
            ->find($id);

        return $auditLog;
    }

    public function categoryOptions(): array
    {
        return $this->getModel()
            ->newQuery()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->filter()
            ->values()
            ->all();
    }

    public function actorTypeOptions(): array
    {
        return $this->getModel()
            ->newQuery()
            ->whereNotNull('causer_type')
            ->distinct()
            ->orderBy('causer_type')
            ->pluck('causer_type')
            ->filter()
            ->values()
            ->all();
    }

    public function subjectTypeOptions(): array
    {
        return $this->getModel()
            ->newQuery()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildAdminQuery(array $filters): Builder
    {
        $table = $this->getModel()->getTable();
        $global = trim((string) ($filters['global'] ?? ''));
        $category = $filters['category'] ?? null;
        $eventType = trim((string) ($filters['event_type'] ?? ''));
        $severity = $filters['severity'] ?? null;
        $actorType = $filters['actor_type'] ?? null;
        $subjectType = $filters['subject_type'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $resultExpression = $this->jsonExtractExpression("{$table}.properties", '$.result');
        $clientPublicIdExpression = $this->jsonExtractExpression("{$table}.properties", '$.client_public_id');
        $ipAddressExpression = $this->jsonExtractExpression("{$table}.properties", '$.ip_address');
        $clientIdExpression = $this->jsonExtractExpression("{$table}.properties", '$.client_id');
        $severitySql = $this->severitySql($table);

        return $this->getModel()
            ->newQuery()
            ->select("{$table}.*")
            ->leftJoin('users as actor_users', function ($join) use ($table): void {
                $join->on('actor_users.id', '=', "{$table}.causer_id")
                    ->where("{$table}.causer_type", '=', User::class);
            })
            ->when($global !== '', function (Builder $query) use (
                $global,
                $table,
                $clientPublicIdExpression,
                $ipAddressExpression,
                $resultExpression
            ): void {
                $like = '%'.$global.'%';

                $query->where(function (Builder $innerQuery) use (
                    $global,
                    $like,
                    $table,
                    $clientPublicIdExpression,
                    $ipAddressExpression,
                    $resultExpression
                ): void {
                    if (ctype_digit($global)) {
                        $innerQuery->orWhere("{$table}.id", (int) $global);
                    }

                    $innerQuery
                        ->orWhere("{$table}.log_name", 'like', $like)
                        ->orWhere("{$table}.event", 'like', $like)
                        ->orWhere("{$table}.description", 'like', $like)
                        ->orWhere('actor_users.name', 'like', $like)
                        ->orWhere('actor_users.email', 'like', $like)
                        ->orWhereRaw("{$clientPublicIdExpression} like ?", [$like])
                        ->orWhereRaw("{$ipAddressExpression} like ?", [$like])
                        ->orWhereRaw("{$resultExpression} like ?", [$like]);
                });
            })
            ->when($category !== null && $category !== '', fn (Builder $query) => $query->where("{$table}.log_name", $category))
            ->when($eventType !== '', fn (Builder $query) => $query->where("{$table}.event", 'like', '%'.$eventType.'%'))
            ->when($severity !== null && $severity !== '', fn (Builder $query) => $query->whereRaw("{$severitySql} = ?", [$severity]))
            ->when($actorType !== null && $actorType !== '', fn (Builder $query) => $query->where("{$table}.causer_type", $actorType))
            ->when($subjectType !== null && $subjectType !== '', fn (Builder $query) => $query->where("{$table}.subject_type", $subjectType))
            ->when($clientId !== null && $clientId !== '', function (Builder $query) use ($table, $clientId, $clientIdExpression): void {
                $query->where(function (Builder $innerQuery) use ($table, $clientId, $clientIdExpression): void {
                    $innerQuery
                        ->where(function (Builder $subjectQuery) use ($table, $clientId): void {
                            $subjectQuery
                                ->where("{$table}.subject_type", SsoClient::class)
                                ->where("{$table}.subject_id", (int) $clientId);
                        })
                        ->orWhereRaw("CAST({$clientIdExpression} AS INTEGER) = ?", [(int) $clientId]);
                });
            })
            ->when($dateFrom !== null && $dateFrom !== '', function (Builder $query) use ($table, $dateFrom): void {
                $query->where("{$table}.created_at", '>=', Carbon::parse((string) $dateFrom)->startOfDay());
            })
            ->when($dateTo !== null && $dateTo !== '', function (Builder $query) use ($table, $dateTo): void {
                $query->where("{$table}.created_at", '<=', Carbon::parse((string) $dateTo)->endOfDay());
            });
    }

    private function severitySql(string $table): string
    {
        $resultExpression = $this->jsonExtractExpression("{$table}.properties", '$.result');

        return <<<SQL
CASE
    WHEN {$table}.log_name = 'security'
        AND (
            {$table}.event LIKE '%incident%'
            OR {$table}.event LIKE '%reuse%'
            OR {$table}.event LIKE '%denied%'
        )
        THEN 'critical'
    WHEN {$table}.log_name = 'security' THEN 'error'
    WHEN {$resultExpression} = 'failure' THEN 'error'
    WHEN {$table}.event LIKE '%revoke%' OR {$table}.event LIKE '%revoked%' THEN 'warning'
    ELSE 'info'
END
SQL;
    }

    private function jsonExtractExpression(string $column, string $path): string
    {
        $driver = $this->getModel()->getConnection()->getDriverName();

        return match ($driver) {
            'sqlite' => "json_extract({$column}, '{$path}')",
            default => "json_unquote(json_extract({$column}, '{$path}'))",
        };
    }
}
