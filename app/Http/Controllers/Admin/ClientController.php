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
    public function __construct(
            private readonly ClientService $clientService
    ) {}
    /**
     * Rendereld a kliens indexoldalát az aktuális szűrő-, rendezési és tördelési hasznos adattartalommal.
     *
     * @return Response
     */
    public function index(ClientIndexRequest $request): Response
    {
        $this->authorize('viewAny', SsoClient::class);

        $validated = $request->validated();

        return Inertia::render('Clients/Index', $this->clientService->getIndexPayload(
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
     * Rendereld a létrehozási űrlap hasznos adatait egy új OAuth klienshez.
     *
     * @return Response
     */
    public function create(): Response
    {
        $this->authorize('create', SsoClient::class);

        return Inertia::render('Clients/Create', $this->clientService->getCreatePayload());
    }

    /**
     * Tárolj egy újonnan létrehozott OAuth klienst, és flasheld az egyszer látható titkát.
     *
     * @return RedirectResponse
     */
    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', SsoClient::class);

        $result = $this->clientService->createClient($request->validated());

        return redirect()
            ->route('admin.sso-clients.index')
            ->with('success', 'SSO client created successfully.')
            ->with('clientSecret', [
                'clientId' => $result['client']->client_id,
                'secret' => $result['plainSecret'],
            ]);
    }

    /**
     * Rendereld az ügyfél szerkesztési oldalát és a titkoskezelési metaadatait.
     *
     * @return Response
     */
    public function edit(SsoClient $ssoClient): Response
    {
        $this->authorize('update', $ssoClient);

        return Inertia::render('Clients/Edit', $this->clientService->getEditPayload($ssoClient));
    }

    /**
     * Meglévő OAuth-kliens frissítése egy érvényesített adminisztrátori adatcsomagból.
     *
     * @return RedirectResponse
     */
    public function update(
        ClientUpdateRequest $request,
        SsoClient $ssoClient
    ): RedirectResponse {
        $this->authorize('update', $ssoClient);

        $this->clientService->updateClient($ssoClient, $request->validated());

        return redirect()
            ->route('admin.sso-clients.index')
            ->with('success', 'SSO client updated successfully.');
    }

    /**
     * Változtasd meg az aktív kliens titkos kódot, és villogtasd egyszer az új sima titkos kódot.
     *
     * @return RedirectResponse
     */
    public function rotateSecret(
        ClientRotateSecretRequest $request,
        SsoClient $ssoClient
    ): RedirectResponse {
        $this->authorize('rotateSecret', $ssoClient);

        $result = $this->clientService->rotateSecret($ssoClient, $request->validated());

        return redirect()
            ->route('admin.sso-clients.edit', $ssoClient)
            ->with('success', 'Client secret rotated successfully.')
            ->with('clientSecret', [
                'clientId' => $result['client']->client_id,
                'secret' => $result['plainSecret'],
            ]);
    }

    /**
     * Visszavon egy adott ügyféltitkot, és JSON-t vagy átirányítást ad vissza a kérés típusa alapján.
     *
     * @return RedirectResponse|JsonResponse
     */
    public function revokeSecret(
        ClientRevokeSecretRequest $request,
        SsoClient $ssoClient,
        ClientSecret $clientSecret
    ): RedirectResponse|JsonResponse {
        $this->authorize('revokeSecret', [$ssoClient, $clientSecret]);

        $this->clientService->revokeSecret($ssoClient, $clientSecret);

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
     * Töröljön egy OAuth klienst, és adja vissza a hívó által várt válaszformátumot.
     *
     * @return RedirectResponse|JsonResponse
     */
    public function destroy(SsoClient $ssoClient): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $ssoClient);

        $this->clientService->deleteClient($ssoClient);

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
