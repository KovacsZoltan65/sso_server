<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogService $auditLogService): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validated();
        $payload = $auditLogService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'event_type' => $validated['event_type'] ?? null,
                'category' => $validated['category'] ?? null,
                'severity' => $validated['severity'] ?? null,
                'actor_type' => $validated['actor_type'] ?? null,
                'subject_type' => $validated['subject_type'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ],
            perPage: (int) ($validated['per_page'] ?? 15),
            sortField: $validated['sort_field'] ?? 'occurred_at',
            sortOrder: isset($validated['sort_order']) ? (int) $validated['sort_order'] : -1,
            page: (int) ($validated['page'] ?? 1),
        );

        return $this->successResponse(
            message: 'Audit logs retrieved successfully.',
            data: $payload['rows'],
            meta: [
                'filters' => $payload['filters'],
                'sorting' => $payload['sorting'],
                'pagination' => $payload['pagination'],
                'filterOptions' => $payload['filterOptions'],
            ],
        );
    }

    public function show(AuditLog $auditLog, AuditLogService $auditLogService): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return $this->successResponse(
            message: 'Audit log retrieved successfully.',
            data: $auditLogService->getDetailPayload($auditLog),
        );
    }
}
