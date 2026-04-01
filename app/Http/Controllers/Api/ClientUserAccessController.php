<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientUserAccessBulkDestroyRequest;
use App\Http\Requests\Admin\ClientUserAccessIndexRequest;
use App\Http\Requests\Admin\StoreClientUserAccessRequest;
use App\Http\Requests\Admin\UpdateClientUserAccessRequest;
use App\Data\ClientUserAccessSummaryData;
use App\Models\ClientUserAccess;
use App\Models\SsoClient;
use App\Models\User;
use App\Services\ClientUserAccessService;
use Illuminate\Http\JsonResponse;

class ClientUserAccessController extends Controller
{
    public function index(
        ClientUserAccessIndexRequest $request,
        ClientUserAccessService $accessService,
    ): JsonResponse {
        $this->authorize('viewAny', ClientUserAccess::class);

        $validated = $request->validated();
        $payload = $accessService->getIndexPayload(
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
        );

        return $this->successResponse(
            message: 'Client user access records retrieved successfully.',
            data: [
                'rows' => $payload['rows'],
                'clientOptions' => $payload['clientOptions'],
                'userOptions' => $payload['userOptions'],
                'filters' => $payload['filters'],
                'sorting' => $payload['sorting'],
            ],
            meta: $payload['pagination'],
        );
    }

    public function store(
        StoreClientUserAccessRequest $request,
        ClientUserAccessService $accessService,
    ): JsonResponse {
        $this->authorize('create', ClientUserAccess::class);

        $access = $accessService->createAccess($request->validated());

        return $this->successResponse(
            message: 'Client user access created successfully.',
            data: ['access' => ClientUserAccessSummaryData::fromModel($access)],
            status: 201,
        );
    }

    public function update(
        UpdateClientUserAccessRequest $request,
        ClientUserAccess $clientUserAccess,
        ClientUserAccessService $accessService,
    ): JsonResponse {
        $this->authorize('update', $clientUserAccess);

        $updatedAccess = $accessService->updateAccess($clientUserAccess, $request->validated());

        return $this->successResponse(
            message: 'Client user access updated successfully.',
            data: ['id' => $updatedAccess->id],
        );
    }

    public function destroy(
        ClientUserAccess $clientUserAccess,
        ClientUserAccessService $accessService,
    ): JsonResponse {
        $this->authorize('delete', $clientUserAccess);

        $accessService->deleteAccess($clientUserAccess);

        return $this->successResponse(
            message: 'Client user access deleted successfully.',
            data: ['id' => $clientUserAccess->id],
        );
    }

    public function bulkDestroy(
        ClientUserAccessBulkDestroyRequest $request,
        ClientUserAccessService $accessService,
    ): JsonResponse {
        $this->authorize('bulkDelete', ClientUserAccess::class);

        $deletedIds = $accessService->bulkDelete($request->validated('ids'));

        return $this->successResponse(
            message: 'Selected client user access records deleted successfully.',
            data: ['ids' => $deletedIds],
            meta: ['deletedCount' => count($deletedIds)],
        );
    }

    public function clientAccesses(SsoClient $ssoClient, ClientUserAccessService $accessService): JsonResponse
    {
        $this->authorize('viewAny', ClientUserAccess::class);

        return $this->successResponse(
            message: 'Client access assignments retrieved successfully.',
            data: [
                'rows' => $accessService->listUsersForClient($ssoClient)->map(
                    fn (ClientUserAccess $access) => [
                        'id' => $access->id,
                        'user_id' => $access->user_id,
                        'user_name' => $access->user->name,
                        'user_email' => $access->user->email,
                        'is_active' => (bool) $access->is_active,
                        'allowed_from' => $access->allowed_from?->toIso8601String(),
                        'allowed_until' => $access->allowed_until?->toIso8601String(),
                    ]
                )->values()->all(),
            ],
        );
    }

    public function userAccesses(User $user, ClientUserAccessService $accessService): JsonResponse
    {
        $this->authorize('viewAny', ClientUserAccess::class);

        return $this->successResponse(
            message: 'User client access assignments retrieved successfully.',
            data: [
                'rows' => $accessService->listClientsForUser($user)->map(
                    fn (ClientUserAccess $access) => [
                        'id' => $access->id,
                        'client_id' => $access->client_id,
                        'client_name' => $access->client->name,
                        'client_public_id' => $access->client->client_id,
                        'is_active' => (bool) $access->is_active,
                        'allowed_from' => $access->allowed_from?->toIso8601String(),
                        'allowed_until' => $access->allowed_until?->toIso8601String(),
                    ]
                )->values()->all(),
            ],
        );
    }
}
