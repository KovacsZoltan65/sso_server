<?php

namespace App\Data\Audit;

final readonly class AuditLogSummaryData
{
    /**
     * @param array<string, mixed>|null $actor
     * @param array<string, mixed>|null $subject
     * @param array<string, mixed>|null $client
     */
    public function __construct(
        public int $id,
        public string $eventType,
        public string $category,
        public string $severity,
        public ?array $actor,
        public ?array $subject,
        public ?array $client,
        public ?string $ipAddress,
        public string $occurredAt,
        public string $summary,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'eventType' => $this->eventType,
            'category' => $this->category,
            'severity' => $this->severity,
            'actor' => $this->actor,
            'subject' => $this->subject,
            'client' => $this->client,
            'ipAddress' => $this->ipAddress,
            'occurredAt' => $this->occurredAt,
            'summary' => $this->summary,
        ];
    }
}
