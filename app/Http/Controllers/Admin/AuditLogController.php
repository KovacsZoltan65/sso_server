<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogs\IndexAuditLogRequest;
use App\Services\Audit\AuditLogQueryService;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogQueryService $auditLogQueryService,
    ) {}

    public function index(IndexAuditLogRequest $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        return Inertia::render('Admin/AuditLogs/Index', $this->auditLogQueryService->getIndexPayload($request->validated()));
    }
}
