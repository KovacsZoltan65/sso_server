<?php

namespace App\Services\Audit;

use App\Data\Audit\AuditLogData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @phpstan-type AuditPropertyValue scalar|array<int|string, scalar|array<int|string, scalar>|null>|null
 * @phpstan-type AuditProperties array<string, AuditPropertyValue>
 */
class AuditLogService
{
    public const LOG_AUTH = 'auth';
    public const LOG_ACCOUNT = 'account';
    public const LOG_OAUTH = 'oauth';
    public const LOG_SECURITY = 'security';
    public const LOG_ADMIN_CLIENT = 'admin.client';
    public const LOG_ADMIN_CLIENT_ACCESS = 'admin.client_access';
    public const LOG_ADMIN_SCOPE = 'admin.scope';
    public const LOG_ADMIN_TOKEN_POLICY = 'admin.token_policy';
    public const LOG_ADMIN_USER = 'admin.user';
    public const LOG_ADMIN_ROLE = 'admin.role';
    public const LOG_ADMIN_PERMISSION = 'admin.permission';

    /**
     * @var array<int, string>
     */
    private array $allowedLogNames = [
        self::LOG_AUTH,
        self::LOG_ACCOUNT,
        self::LOG_OAUTH,
        self::LOG_SECURITY,
        self::LOG_ADMIN_CLIENT,
        self::LOG_ADMIN_CLIENT_ACCESS,
        self::LOG_ADMIN_SCOPE,
        self::LOG_ADMIN_TOKEN_POLICY,
        self::LOG_ADMIN_USER,
        self::LOG_ADMIN_ROLE,
        self::LOG_ADMIN_PERMISSION,
    ];

    /**
     * @var array<int, string>
     */
    private array $allowedPropertyKeys = [
        'reason',
        'ip_address',
        'user_agent',
        'route',
        'client_id',
        'client_public_id',
        'consent_id',
        'scope_codes',
        'grant_type',
        'token_kind',
        'updated_fields',
        'changed_attributes',
        'secret_last_four',
        'target_user_id',
        'actor_user_id',
        'affected_count',
        'target_role_id',
        'target_permission_id',
        'target_scope_id',
        'user_id',
        'redirect_uri',
        'redirect_uri_count',
        'granted_scope_fingerprint',
        'policy_id',
        'consent_policy_version',
        'policy_version',
        'trust_tier',
        'is_first_party',
        'consent_bypass_allowed',
        'status',
        'previous_status',
        'new_status',
        'old_value',
        'new_value',
        'decision',
        'revocation_reason',
        'result',
        'has_nonce',
        'scope_contains_openid',
        'has_frontchannel_logout_uri',
        'frontchannel_target_count',
        'kid',
        'key_count',
        'deleted_count',
        'client_access_id',
        'allowed_from',
        'allowed_until',
        'token_id',
        'family_id',
        'parent_token_id',
        'replaced_by_token_id',
        'revoked_reason',
        'revoked_count',
        'already_revoked',
        'trigger',
        'incident_detected_at',
    ];

    /**
     * @var array<int, string>
     */
    private array $sensitivePropertyKeys = [
        'password',
        'password_hash',
        'secret',
        'client_secret',
        'access_token',
        'refresh_token',
        'authorization_code',
        'cookie',
        'session',
        'session_id',
        'bearer_token',
    ];

    /**
     * @param AuditProperties $properties
     */
    public function log(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $entry = new AuditLogData(
            logName: $this->normalizeLogName($logName),
            event: $this->normalizeEvent($event),
            description: trim($description),
            subject: $subject,
            causer: $causer,
            properties: $this->sanitizeProperties($properties),
        );

        $activity = activity($entry->logName)->event($entry->event);

        if ($entry->subject instanceof Model) {
            $activity->performedOn($entry->subject);
        }

        if ($entry->causer instanceof Model) {
            $activity->causedBy($entry->causer);
        }

        if ($entry->properties !== []) {
            $activity->withProperties($entry->properties);
        }

        $activity->log($entry->description);
    }

    /**
     * @param AuditProperties $properties
     */
    public function logSuccess(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: [
                ...$properties,
                'result' => 'success',
            ],
        );
    }

    /**
     * @param AuditProperties $properties
     */
    public function logFailure(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: [
                ...$properties,
                'result' => 'failure',
            ],
        );
    }

    /**
     * @param AuditProperties $properties
     */
    public function logSecurityEvent(
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->log(
            logName: self::LOG_SECURITY,
            event: $event,
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: $properties,
        );
    }

    /**
     * @param AuditProperties $properties
     */
    public function logAdminCrud(
        string $resource,
        string $action,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $logName = match ($resource) {
            'client', 'client_secret', 'redirect_uri', 'client_scope' => self::LOG_ADMIN_CLIENT,
            'client_user_access' => self::LOG_ADMIN_CLIENT_ACCESS,
            'scope' => self::LOG_ADMIN_SCOPE,
            'token_policy' => self::LOG_ADMIN_TOKEN_POLICY,
            'user' => self::LOG_ADMIN_USER,
            'role' => self::LOG_ADMIN_ROLE,
            'permission' => self::LOG_ADMIN_PERMISSION,
            default => throw new InvalidArgumentException(sprintf('Unsupported admin audit resource [%s].', $resource)),
        };

        $this->log(
            logName: $logName,
            event: sprintf('admin.%s.%s', $resource, $action),
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: $properties,
        );
    }

    /**
     * @return AuditProperties
     */
    public function requestContext(Request $request): array
    {
        return array_filter([
            'ip_address' => $request->ip(),
            'user_agent' => $this->normalizeString($request->userAgent()),
            'route' => $request->route()?->getName(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function normalizeLogName(string $logName): string
    {
        $normalized = trim($logName);

        if (! in_array($normalized, $this->allowedLogNames, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported audit log name [%s].', $logName));
        }

        return $normalized;
    }

    private function normalizeEvent(string $event): string
    {
        $normalized = trim($event);

        if (! preg_match('/^[a-z]+(?:\.[a-z0-9_]+){2,}$/', $normalized)) {
            throw new InvalidArgumentException(sprintf('Invalid audit event [%s].', $event));
        }

        return $normalized;
    }

    /**
     * @param AuditProperties $properties
     * @return AuditProperties
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, $this->sensitivePropertyKeys, true)) {
                throw new InvalidArgumentException(sprintf('Sensitive audit property [%s] is not allowed.', $key));
            }

            if (! in_array($key, $this->allowedPropertyKeys, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported audit property [%s].', $key));
            }

            $normalizedValue = $this->normalizePropertyValue($key, $value);

            if ($normalizedValue === null) {
                continue;
            }

            $sanitized[$key] = $normalizedValue;
        }

        return $sanitized;
    }

    private function normalizePropertyValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'client_id', 'consent_id', 'policy_id', 'target_user_id', 'actor_user_id', 'affected_count', 'target_role_id', 'target_permission_id', 'target_scope_id', 'user_id', 'redirect_uri_count', 'deleted_count', 'token_id', 'parent_token_id', 'replaced_by_token_id', 'client_access_id' => $this->normalizeInteger($value),
            'scope_codes', 'updated_fields' => $this->normalizeStringList($value),
            'changed_attributes' => $this->normalizeAssociativeArray($value),
            default => $this->normalizeScalar($value),
        };
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeStringList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = collect($value)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<string, array<int, string>|int|string|bool|null>|null
     */
    private function normalizeAssociativeArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($item)) {
                $normalizedList = $this->normalizeStringList($item);

                if ($normalizedList !== null) {
                    $normalized[$key] = $normalizedList;
                }

                continue;
            }

            $normalized[$key] = $this->normalizeScalar($item);
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeScalar(mixed $value): string|int|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        return $this->normalizeString($value);
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, 1024, '');
    }
}
