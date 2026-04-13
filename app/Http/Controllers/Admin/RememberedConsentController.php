<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RememberedConsentIndexRequest;
use App\Http\Requests\Admin\RevokeUserClientConsentRequest;
use App\Models\UserClientConsent;
use App\Services\RememberedConsentManagementService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RememberedConsentController extends Controller
{
    /**
     * @param RememberedConsentIndexRequest $request
     * @param RememberedConsentManagementService $service
     * @return \Inertia\Response
     */
    public function index(
        RememberedConsentIndexRequest $request,
        RememberedConsentManagementService $service,
    ): Response {
        $this->authorize('viewAny', UserClientConsent::class);

        $validated = $request->validated();

        return Inertia::render('RememberedConsents/Index', $service->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'grantedAt',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : -1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    /**
     * @param RevokeUserClientConsentRequest $request
     * @param UserClientConsent $consent
     * @param RememberedConsentManagementService $service
     * @return JsonResponse
     */
    public function revoke(
        RevokeUserClientConsentRequest $request,
        UserClientConsent $consent,
        RememberedConsentManagementService $service,
    ): JsonResponse {
        $this->authorize('revoke', $consent);

        $consent->loadMissing(['client', 'user']);
        $result = $service->revokeConsent($consent, (string) $request->validated('revocation_reason'));

        return $this->successResponse(
            message: $result['already_revoked']
                ? 'Remembered consent was already revoked.'
                : 'Remembered consent revoked successfully.',
            data: [
                'id' => $consent->id,
                'already_revoked' => $result['already_revoked'],
                'status' => $result['new_status'],
            ],
        );
    }
}
