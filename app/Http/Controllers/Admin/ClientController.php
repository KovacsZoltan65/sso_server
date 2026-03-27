<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientIndexRequest;
use App\Http\Requests\Admin\ClientRevokeSecretRequest;
use App\Http\Requests\Admin\ClientRotateSecretRequest;
use App\Http\Requests\Admin\ClientStoreRequest;
use App\Http\Requests\Admin\ClientUpdateRequest;
use App\Models\ClientSecret;
use App\Models\SsoClient;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Render the client index page with the current filter, sorting, and pagination payload.
     */
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

    /**
     * Render the create form payload for a new OAuth client.
     */
    public function create(ClientService $clientService): Response
    {
        $this->authorize('create', SsoClient::class);

        return Inertia::render('Clients/Create', $clientService->getCreatePayload());
    }

    /**
     * Store a newly created OAuth client and flash its one-time visible secret.
     */
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

    /**
     * Render the client edit page and its secret-management metadata.
     */
    public function edit(SsoClient $ssoClient, ClientService $clientService): Response
    {
        $this->authorize('update', $ssoClient);

        return Inertia::render('Clients/Edit', $clientService->getEditPayload($ssoClient));
    }

    /**
     * Update an existing OAuth client from a validated admin payload.
     */
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

    /**
     * Rotate the active client secret and flash the new plain secret once.
     */
    public function rotateSecret(
        ClientRotateSecretRequest $request,
        SsoClient $ssoClient,
        ClientService $clientService,
    ): RedirectResponse {
        $this->authorize('rotateSecret', $ssoClient);

        $result = $clientService->rotateSecret($ssoClient, $request->validated());

        return redirect()
            ->route('admin.sso-clients.edit', $ssoClient)
            ->with('success', 'Client secret rotated successfully.')
            ->with('clientSecret', [
                'clientId' => $result['client']->client_id,
                'secret' => $result['plainSecret'],
            ]);
    }

    /**
     * Revoke a specific client secret and return JSON or redirect based on the request type.
     */
    public function revokeSecret(
        ClientRevokeSecretRequest $request,
        SsoClient $ssoClient,
        ClientSecret $clientSecret,
        ClientService $clientService,
    ): RedirectResponse|JsonResponse {
        $this->authorize('revokeSecret', [$ssoClient, $clientSecret]);

        $clientService->revokeSecret($ssoClient, $clientSecret);

        if ($request->expectsJson()) {
            return $this->successResponse(
                message: 'Client secret revoked successfully.',
                data: ['id' => $clientSecret->id],
            );
        }

        return redirect()
            ->route('admin.sso-clients.edit', $ssoClient)
            ->with('success', 'Client secret revoked successfully.');
    }

    /**
     * Delete an OAuth client and return the response format expected by the caller.
     */
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
