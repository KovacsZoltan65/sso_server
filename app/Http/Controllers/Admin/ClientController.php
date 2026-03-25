<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientIndexRequest;
use App\Http\Requests\Admin\ClientStoreRequest;
use App\Http\Requests\Admin\ClientUpdateRequest;
use App\Models\SsoClient;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(ClientIndexRequest $request, ClientService $clientService): Response
    {
        $this->authorize('viewAny', SsoClient::class);

        $validated = $request->validated();

        return Inertia::render('Clients/Index', $clientService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'name' => $validated['name'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    public function create(ClientService $clientService): Response
    {
        $this->authorize('create', SsoClient::class);

        return Inertia::render('Clients/Create', $clientService->getCreatePayload());
    }

    public function store(ClientStoreRequest $request, ClientService $clientService): RedirectResponse
    {
        $this->authorize('create', SsoClient::class);

        $result = $clientService->createClient($request->validated());

        return redirect()
            ->route('admin.sso-clients.index')
            ->with('success', 'SSO client created successfully.')
            ->with('clientSecret', [
                'clientId' => $result['client']->client_id,
                'secret' => $result['plainSecret'],
            ]);
    }

    public function edit(SsoClient $ssoClient, ClientService $clientService): Response
    {
        $this->authorize('update', $ssoClient);

        return Inertia::render('Clients/Edit', $clientService->getEditPayload($ssoClient));
    }

    public function update(
        ClientUpdateRequest $request,
        SsoClient $ssoClient,
        ClientService $clientService,
    ): RedirectResponse {
        $this->authorize('update', $ssoClient);

        $clientService->updateClient($ssoClient, $request->validated());

        return redirect()
            ->route('admin.sso-clients.index')
            ->with('success', 'SSO client updated successfully.');
    }

    public function destroy(SsoClient $ssoClient, ClientService $clientService): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $ssoClient);

        $clientService->deleteClient($ssoClient);

        if (request()->expectsJson()) {
            return $this->successResponse(
                message: 'SSO client deleted successfully.',
                data: ['id' => $ssoClient->id],
            );
        }

        return redirect()
            ->route('admin.sso-clients.index')
            ->with('success', 'SSO client deleted successfully.');
    }
}
