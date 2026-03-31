<?php

namespace App\Data\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-type AuditProperties array<string, scalar|array<int|string, scalar|array<int|string, scalar>|null>|null>
 */
final readonly class AuditLogData
{
    /**
     * @param AuditProperties $properties
     */
    public function __construct(
        public string $logName,
        public string $event,
        public string $description,
        public ?Model $subject = null,
        public ?Model $causer = null,
        public array $properties = [],
    ) {
    }
}
