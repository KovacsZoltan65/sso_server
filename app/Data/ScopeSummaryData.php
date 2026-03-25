<?php

namespace App\Data;

use App\Models\Scope;
use Spatie\LaravelData\Data;

class ScopeSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
        public ?string $description,
        public bool $isActive,
        public string $createdAt,
        public int $clientsCount,
        public bool $canDelete,
        public ?string $deleteBlockCode,
        public ?string $deleteBlockReason,
    ) {
    }

    public static function fromModel(
        Scope $scope,
        int $clientsCount = 0,
        bool $canDelete = true,
        ?string $deleteBlockCode = null,
        ?string $deleteBlockReason = null,
    ): self {
        return new self(
            id: $scope->id,
            name: $scope->name,
            code: $scope->code,
            description: $scope->description,
            isActive: (bool) $scope->is_active,
            createdAt: $scope->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
            clientsCount: $clientsCount,
            canDelete: $canDelete,
            deleteBlockCode: $deleteBlockCode,
            deleteBlockReason: $deleteBlockReason,
        );
    }
}
