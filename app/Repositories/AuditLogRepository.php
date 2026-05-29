<?php

namespace App\Repositories;

use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\Repository;
use Spatie\Activitylog\Models\Activity;

class AuditLogRepository extends Repository implements AuditLogRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $sortableFields = [
        'created_at' => 'activity_log.created_at',
        'event' => 'activity_log.event',
        'severity' => 'activity_log.log_name',
        'actor_id' => 'activity_log.causer_id',
        'client_id' => 'activity_log.subject_id',
    ];

    public function __construct(Activity $model)
    {
        parent::__construct($model);
    }

    public function model(): string
    {
        return Activity::class;
    }

    public function paginateForAdmin(
        array $filters,
        ?string $sortField,
        string|int|null $sortOrder,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        $search = trim((string) ($filters['search'] ?? ''));
        $event = trim((string) ($filters['event'] ?? ''));
        $actorId = $filters['actor_id'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $severity = trim((string) ($filters['severity'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $column = $this->sortableFields[$sortField ?? ''] ?? $this->sortableFields['created_at'];
        $direction = \in_array($sortOrder, ['asc', 1, '1'], true) ? 'asc' : 'desc';

        return $this->getModel()
            ->newQuery()
            ->select('activity_log.*')
            ->with(['causer', 'subject'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('activity_log.event', 'like', "%{$search}%")
                        ->orWhere('activity_log.log_name', 'like', "%{$search}%")
                        ->orWhere('activity_log.description', 'like', "%{$search}%")
                        ->orWhere('activity_log.properties->ip_address', 'like', "%{$search}%")
                        ->orWhere('activity_log.properties->user_agent', 'like', "%{$search}%")
                        ->orWhereHasMorph('causer', [User::class], function ($causerQuery) use ($search): void {
                            $causerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHasMorph('subject', [SsoClient::class], function ($subjectQuery) use ($search): void {
                            $subjectQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('client_id', 'like', "%{$search}%");
                        })
                        ->orWhere('activity_log.properties->client_public_id', 'like', "%{$search}%");
                });
            })
            ->when($event !== '', fn ($query) => $query->where('activity_log.event', $event))
            ->when($actorId !== null && $actorId !== '', function ($query) use ($actorId): void {
                $query
                    ->where('activity_log.causer_type', User::class)
                    ->where('activity_log.causer_id', (int) $actorId);
            })
            ->when($clientId !== null && $clientId !== '', function ($query) use ($clientId): void {
                $query->where(function ($innerQuery) use ($clientId): void {
                    $innerQuery
                        ->where(function ($subjectQuery) use ($clientId): void {
                            $subjectQuery
                                ->where('activity_log.subject_type', SsoClient::class)
                                ->where('activity_log.subject_id', (int) $clientId);
                        })
                        ->orWhere('activity_log.properties->client_id', (int) $clientId);
                });
            })
            ->when($severity !== '', fn ($query) => $this->applySeverityFilter($query, $severity))
            ->when($dateFrom !== null && $dateFrom !== '', fn ($query) => $query->where('activity_log.created_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo !== null && $dateTo !== '', fn ($query) => $query->where('activity_log.created_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->orderBy($column, $direction)
            ->orderByDesc('activity_log.id')
            ->paginate($perPage, ['activity_log.*'], 'page', $page)
            ->withQueryString();
    }

    public function eventOptions(): Collection
    {
        /** @var Collection<int, string> $events */
        $events = $this->getModel()
            ->newQuery()
            ->whereNotNull('event')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->filter()
            ->values();

        return $events;
    }

    private function applySeverityFilter(mixed $query, string $severity): void
    {
        $normalized = strtolower($severity);

        $query->where(function ($innerQuery) use ($normalized): void {
            $innerQuery->where('activity_log.properties->severity', $normalized);

            match ($normalized) {
                'error' => $innerQuery
                    ->orWhere('activity_log.properties->result', 'failure')
                    ->orWhere('activity_log.log_name', 'security')
                    ->orWhere('activity_log.event', 'like', '%.failed')
                    ->orWhere('activity_log.event', 'like', '%.denied'),
                'warning' => $innerQuery
                    ->orWhere('activity_log.event', 'like', '%.revoked')
                    ->orWhere('activity_log.event', 'like', '%.invalidated')
                    ->orWhere('activity_log.event', 'like', '%.mismatch'),
                'info' => $innerQuery
                    ->orWhere('activity_log.properties->result', 'success')
                    ->orWhereNotIn('activity_log.log_name', ['security']),
                default => null,
            };
        });
    }
}
