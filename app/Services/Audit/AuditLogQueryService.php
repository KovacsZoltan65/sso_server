<?php

namespace App\Services\Audit;

use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AuditLogQueryService
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'secret',
        'client_secret',
        'token',
        'access_token',
        'refresh_token',
        'authorization_code',
        'password',
    ];

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getIndexPayload(array $filters): array
    {
        $sortOrder = $filters['sort_order'] ?? -1;
        $paginator = $this->auditLogs->paginateForAdmin(
            filters: $filters,
            sortField: $filters['sort_field'] ?? 'created_at',
            sortOrder: $sortOrder,
            perPage: (int) ($filters['per_page'] ?? 15),
            page: (int) ($filters['page'] ?? 1),
        );

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (Activity $activity): array => $this->mapActivity($activity))
                ->values()
                ->all(),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'event' => $filters['event'] ?? null,
                'actor_id' => $filters['actor_id'] ?? null,
                'client_id' => $filters['client_id'] ?? null,
                'severity' => $filters['severity'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'sorting' => [
                'field' => $filters['sort_field'] ?? 'created_at',
                'order' => $this->normalizeSortOrder($sortOrder),
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
            'eventOptions' => $this->auditLogs->eventOptions()
                ->map(fn (string $event): array => ['label' => $event, 'value' => $event])
                ->all(),
            'severityOptions' => [
                ['label' => 'Info', 'value' => 'info'],
                ['label' => 'Warning', 'value' => 'warning'],
                ['label' => 'Error', 'value' => 'error'],
            ],
        ];
    }

    private function mapActivity(Activity $activity): array
    {
        $properties = $this->propertiesToArray($activity->properties);
        $maskedProperties = $this->maskSensitivePayload($properties);
        $actor = $this->mapActor($activity);
        $client = $this->mapClient($activity, $properties);
        $userAgent = (string) ($properties['user_agent'] ?? '');

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'logName' => $activity->log_name,
            'description' => $activity->description,
            'severity' => $this->resolveSeverity($activity, $properties),
            'actor' => $actor,
            'client' => $client,
            'ipAddress' => $properties['ip_address'] ?? null,
            'userAgent' => $userAgent !== '' ? $userAgent : null,
            'userAgentShort' => $userAgent !== '' ? str($userAgent)->limit(80)->toString() : null,
            'createdAt' => $activity->created_at?->toDateTimeString(),
            'properties' => $maskedProperties,
            'context' => [
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type,
                'causer_id' => $activity->causer_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function propertiesToArray(mixed $properties): array
    {
        if ($properties instanceof Collection) {
            return $properties->all();
        }

        return \is_array($properties) ? $properties : [];
    }

    private function mapActor(Activity $activity): array
    {
        $causer = $activity->causer;

        if ($causer instanceof User) {
            return [
                'id' => $causer->id,
                'name' => $causer->name,
                'email' => $causer->email,
                'label' => "{$causer->name} ({$causer->email})",
            ];
        }

        return [
            'id' => $activity->causer_id,
            'name' => null,
            'email' => null,
            'label' => $activity->causer_id !== null ? "{$activity->causer_type} #{$activity->causer_id}" : null,
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mapClient(Activity $activity, array $properties): array
    {
        $subject = $activity->subject;

        if ($subject instanceof SsoClient) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'clientId' => $subject->client_id,
                'label' => "{$subject->name} ({$subject->client_id})",
            ];
        }

        $clientId = $properties['client_id'] ?? null;
        $clientPublicId = $properties['client_public_id'] ?? null;

        return [
            'id' => \is_numeric($clientId) ? (int) $clientId : null,
            'name' => null,
            'clientId' => $clientPublicId,
            'label' => $clientPublicId ?: ($clientId !== null ? "Client #{$clientId}" : null),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolveSeverity(Activity $activity, array $properties): string
    {
        $explicit = strtolower((string) ($properties['severity'] ?? ''));

        if (\in_array($explicit, ['info', 'warning', 'error'], true)) {
            return $explicit;
        }

        $event = (string) $activity->event;

        if (($properties['result'] ?? null) === 'failure' || $activity->log_name === 'security' || str_contains($event, '.failed') || str_contains($event, '.denied')) {
            return 'error';
        }

        if (str_contains($event, '.revoked') || str_contains($event, '.invalidated') || str_contains($event, '.mismatch')) {
            return 'warning';
        }

        return 'info';
    }

    private function normalizeSortOrder(mixed $sortOrder): int
    {
        return \in_array($sortOrder, ['asc', 1, '1'], true) ? 1 : -1;
    }

    private function maskSensitivePayload(mixed $payload): mixed
    {
        if (! \is_array($payload)) {
            return $payload;
        }

        $masked = [];

        foreach ($payload as $key => $value) {
            $keyString = (string) $key;

            $masked[$key] = $this->isSensitiveKey($keyString)
                ? '[masked]'
                : $this->maskSensitivePayload($value);
        }

        return $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return collect($this->sensitiveKeys)
            ->contains(fn (string $sensitiveKey): bool => $normalized === $sensitiveKey || str_contains($normalized, $sensitiveKey));
    }
}
