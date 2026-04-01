<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientUserAccessIndexRequest;
use App\Models\ClientUserAccess;
use App\Services\ClientUserAccessService;
use Inertia\Inertia;
use Inertia\Response;

class ClientUserAccessPageController extends Controller
{
    public function __invoke(
        ClientUserAccessIndexRequest $request,
        ClientUserAccessService $accessService,
    ): Response {
        $this->authorize('viewAny', ClientUserAccess::class);

        $validated = $request->validated();

        return Inertia::render('ClientUserAccess/Index', $accessService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'createdAt',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : -1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }
}
