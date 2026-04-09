<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientUserAccessIndexRequest;
use App\Http\Requests\Admin\StoreClientUserAccessRequest;
use App\Http\Requests\Admin\UpdateClientUserAccessRequest;
use App\Models\ClientUserAccess;
use App\Services\ClientUserAccessService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientUserAccessPageController extends Controller
{
    public function __construct(
        private readonly ClientUserAccessService $accessService,
    ) {
    }

    public function index(ClientUserAccessIndexRequest $request): Response
    {
        $this->authorize('viewAny', ClientUserAccess::class);

        $validated = $request->validated();

        return Inertia::render('ClientUserAccess/Index', $this->accessService->getIndexPayload(
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

    public function create(): Response
    {
        $this->authorize('create', ClientUserAccess::class);

        return Inertia::render('ClientUserAccess/Create', $this->accessService->getCreatePayload());
    }

    public function store(StoreClientUserAccessRequest $request): RedirectResponse
    {
        $this->authorize('create', ClientUserAccess::class);

        $this->accessService->createAccess($request->validated());

        return redirect()
            ->route('admin.client-user-access.index')
            ->with('success', 'Client user access created successfully.');
    }

    public function edit(ClientUserAccess $clientUserAccess): Response
    {
        $this->authorize('update', $clientUserAccess);

        return Inertia::render('ClientUserAccess/Edit', $this->accessService->getEditPayload($clientUserAccess));
    }

    public function update(
        UpdateClientUserAccessRequest $request,
        ClientUserAccess $clientUserAccess,
    ): RedirectResponse {
        $this->authorize('update', $clientUserAccess);

        $this->accessService->updateAccess($clientUserAccess, $request->validated());

        return redirect()
            ->route('admin.client-user-access.index')
            ->with('success', 'Client user access updated successfully.');
    }
}
