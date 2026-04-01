<?php

namespace App\Services;

use App\Data\Audit\AuditLogDetailData;
use App\Data\Audit\AuditLogSummaryData;
use App\Models\AuditLog;
use App\Models\SsoClient;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'password',
        'password_hash',
        'token',
        'access_token',
        'refresh_token',
        'client_secret',
        'secret',
        'authorization',
        'authorization_header',
        'cookie',
        'code_verifier',
        'private_key',
        'raw_credential',
        'credential',
        'bearer_token',
        'session',
        'session_id',
    ];

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 15,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $paginator = $this->auditLogs->paginateForAdmin($filters, $sortField, $sortOrder, $perPage, $page);
        $rows = $this->buildRows($paginator);

        return [
            'rows' => $rows,
            'filters' => [
                'global' => $filters['global'] ?? null,
                'event_type' => $filters['event_type'] ?? null,
                'category' => $filters['category'] ?? null,
                'severity' => $filters['severity'] ?? null,
                'actor_type' => $filters['actor_type'] ?? null,
                'subject_type' => $filters['subject_type'] ?? null,
                'client_id' => $filters['client_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'occurred_at',
                'order' => $sortOrder ?? -1,
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
            'filterOptions' => [
                'categories' => $this->toLabelValueOptions($this->auditLogs->categoryOptions(), fn (string $value) => $value),
                'severities' => $this->toLabelValueOptions(['info', 'warning', 'error', 'critical'], fn (string $value) => Str::headline($value)),
                'actorTypes' => $this->toLabelValueOptions($this->auditLogs->actorTypeOptions(), fn (string $value) => class_basename($value)),
                'subjectTypes' => $this->toLabelValueOptions($this->auditLogs->subjectTypeOptions(), fn (string $value) => class_basename($value)),
                'clients' => SsoClient::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (SsoClient $client) => [
                        'label' => sprintf('%s (%s)', $client->name, $client->client_id),
                        'value' => $client->id,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailPayload(AuditLog $auditLog): array
    {
        $auditLog->loadMissing(['causer', 'subject']);
        $clients = $this->resolveClients(collect([$auditLog]));

        return $this->toDetailData($auditLog, $clients)->toArray();
    }

    /**
     * @param LengthAwarePaginator<int, AuditLog> $paginator
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(LengthAwarePaginator $paginator): array
    {
        $items = Collection::make($paginator->items());
        $clients = $this->resolveClients($items);

        return $items
            ->map(fn (AuditLog $auditLog) => $this->toSummaryData($auditLog, $clients)->toArray())
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, AuditLog> $logs
     * @return Collection<int, SsoClient>
     */
    private function resolveClients(Collection $logs): Collection
    {
        $clientIds = $logs
            ->flatMap(function (AuditLog $auditLog): array {
                $properties = $this->properties($auditLog);
                $ids = [];

                if ($auditLog->subject instanceof SsoClient) {
                    $ids[] = $auditLog->subject->id;
                }

                if (isset($properties['client_id']) && is_numeric($properties['client_id'])) {
                    $ids[] = (int) $properties['client_id'];
                }

                return $ids;
            })
            ->unique()
            ->values();

        if ($clientIds->isEmpty()) {
            return collect();
        }

        /** @var Collection<int, SsoClient> $clients */
        $clients = SsoClient::query()
            ->whereIn('id', $clientIds->all())
            ->get()
            ->keyBy('id');

        return $clients;
    }

    /**
     * @param Collection<int, SsoClient> $clients
     */
    private function toSummaryData(AuditLog $auditLog, Collection $clients): AuditLogSummaryData
    {
        $properties = $this->properties($auditLog);

        return new AuditLogSummaryData(
            id: $auditLog->id,
            eventType: (string) ($auditLog->event ?? 'activity.logged'),
            category: (string) ($auditLog->log_name ?? 'general'),
            severity: $this->severityFor($auditLog, $properties),
            actor: $this->actorPayload($auditLog->causer, $auditLog->causer_type, $auditLog->causer_id),
            subject: $this->subjectPayload($auditLog->subject, $auditLog->subject_type, $auditLog->subject_id),
            client: $this->clientPayload($auditLog, $properties, $clients),
            ipAddress: $this->safeString($properties['ip_address'] ?? null),
            occurredAt: $auditLog->created_at?->toDateTimeString() ?? '',
            summary: (string) $auditLog->description,
        );
    }

    /**
     * @param Collection<int, SsoClient> $clients
     */
    private function toDetailData(AuditLog $auditLog, Collection $clients): AuditLogDetailData
    {
        $properties = $this->properties($auditLog);
        $sanitizedMeta = $this->sanitizeForDisplay($properties);

        return new AuditLogDetailData(
            id: $auditLog->id,
            eventType: (string) ($auditLog->event ?? 'activity.logged'),
            category: (string) ($auditLog->log_name ?? 'general'),
            severity: $this->severityFor($auditLog, $properties),
            actor: $this->actorPayload($auditLog->causer, $auditLog->causer_type, $auditLog->causer_id),
            subject: $this->subjectPayload($auditLog->subject, $auditLog->subject_type, $auditLog->subject_id),
            client: $this->clientPayload($auditLog, $properties, $clients),
            ipAddress: $this->safeString($properties['ip_address'] ?? null),
            userAgent: $this->safeString($properties['user_agent'] ?? null),
            requestId: $this->safeString($properties['request_id'] ?? $auditLog->batch_uuid),
            occurredAt: $auditLog->created_at?->toDateTimeString() ?? '',
            summary: (string) $auditLog->description,
            meta: is_array($sanitizedMeta) ? $sanitizedMeta : [],
            tags: $this->extractTags($properties),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(AuditLog $auditLog): array
    {
        return is_array($auditLog->properties) ? $auditLog->properties : [];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function severityFor(AuditLog $auditLog, array $properties): string
    {
        $event = strtolower((string) ($auditLog->event ?? ''));
        $result = strtolower((string) ($properties['result'] ?? ''));
        $category = strtolower((string) ($auditLog->log_name ?? ''));

        if ($category === 'security' && (str_contains($event, 'incident') || str_contains($event, 'reuse') || str_contains($event, 'denied'))) {
            return 'critical';
        }

        if ($category === 'security' || $result === 'failure') {
            return 'error';
        }

        if (str_contains($event, 'revoke')) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actorPayload(?Model $causer, ?string $causerType, ?int $causerId): ?array
    {
        if ($causer === null && $causerType === null && $causerId === null) {
            return null;
        }

        if ($causer instanceof User) {
            return [
                'type' => class_basename($causer::class),
                'id' => $causer->id,
                'display' => sprintf('%s (%s)', $causer->name, $causer->email),
            ];
        }

        return [
            'type' => class_basename((string) $causerType),
            'id' => $causerId,
            'display' => $this->modelDisplay($causer) ?? $this->fallbackDisplay($causerType, $causerId),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subjectPayload(?Model $subject, ?string $subjectType, ?int $subjectId): ?array
    {
        if ($subject === null && $subjectType === null && $subjectId === null) {
            return null;
        }

        $resolvedType = $subject instanceof Model
            ? $subject::class
            : $subjectType;

        return [
            'type' => class_basename((string) $resolvedType),
            'id' => $subject?->getKey() ?? $subjectId,
            'display' => $this->modelDisplay($subject) ?? $this->fallbackDisplay($subjectType, $subjectId),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     * @param Collection<int, SsoClient> $clients
     * @return array<string, mixed>|null
     */
    private function clientPayload(AuditLog $auditLog, array $properties, Collection $clients): ?array
    {
        $client = null;

        if ($auditLog->subject instanceof SsoClient) {
            $client = $auditLog->subject;
        } elseif (isset($properties['client_id']) && is_numeric($properties['client_id'])) {
            $client = $clients->get((int) $properties['client_id']);
        }

        if (! $client instanceof SsoClient) {
            return null;
        }

        return [
            'id' => $client->id,
            'display' => $client->name,
            'clientId' => $client->client_id,
        ];
    }

    private function modelDisplay(?Model $model): ?string
    {
        if ($model instanceof User) {
            return sprintf('%s (%s)', $model->name, $model->email);
        }

        if ($model instanceof SsoClient) {
            return sprintf('%s (%s)', $model->name, $model->client_id);
        }

        if ($model instanceof Model) {
            foreach (['name', 'code', 'email'] as $attribute) {
                $value = $model->getAttribute($attribute);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            return sprintf('%s #%s', class_basename($model::class), $model->getKey());
        }

        return null;
    }

    private function fallbackDisplay(?string $type, ?int $id): string
    {
        return trim(sprintf('%s #%s', class_basename((string) $type), $id ?? 'n/a'));
    }

    /**
     * @param array<int, string> $values
     * @return array<int, array{label: string, value: string}>
     */
    private function toLabelValueOptions(array $values, callable $labelResolver): array
    {
        return collect($values)
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->map(fn (string $value) => [
                'label' => (string) $labelResolver($value),
                'value' => $value,
            ])
            ->all();
    }

    private function safeString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<int, string>
     */
    private function extractTags(array $properties): array
    {
        return collect($properties['scope_codes'] ?? [])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    private function sanitizeForDisplay(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeForDisplay(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                );
            }

            return $sanitized;
        }

        if (is_string($value) && preg_match('/^(Bearer|Basic)\s+/i', $value) === 1) {
            return '[REDACTED]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::of($key)
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->toString();

        if (Str::contains($normalized, ['password', 'secret', 'authorization', 'cookie', 'privatekey', 'codeverifier', 'credential', 'session'])) {
            return true;
        }

        if (Str::contains($normalized, 'token') && ! Str::endsWith($normalized, ['id', 'ttl', 'kind', 'type'])) {
            return true;
        }

        return collect($this->sensitiveKeys)
            ->map(fn (string $item) => Str::of($item)->replaceMatches('/[^a-z0-9]/', '')->toString())
            ->contains(fn (string $item): bool => $item === $normalized);
    }
}
