<?php

namespace App\Data\Audit;

final readonly class AuditLogDetailData
{
    /**
     * @param array<string, mixed>|null $actor
     * @param array<string, mixed>|null $subject
     * @param array<string, mixed>|null $client
     * @param array<string, mixed> $meta
     * @param array<int, string> $tags
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
        public ?string $userAgent,
        public ?string $requestId,
        public string $occurredAt,
        public string $summary,
        public array $meta,
        public array $tags,
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
            'userAgent' => $this->userAgent,
            'requestId' => $this->requestId,
            'occurredAt' => $this->occurredAt,
            'summary' => $this->summary,
            'meta' => $this->meta,
            'tags' => $this->tags,
        ];
    }
}
