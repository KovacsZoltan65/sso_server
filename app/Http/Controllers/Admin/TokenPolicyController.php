<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TokenPolicyBulkDestroyRequest;
use App\Http\Requests\Admin\TokenPolicyIndexRequest;
use App\Http\Requests\Admin\TokenPolicyStoreRequest;
use App\Http\Requests\Admin\TokenPolicyUpdateRequest;
use App\Models\TokenPolicy;
use App\Services\TokenPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TokenPolicyController extends Controller
{
    public function __construct(
            private readonly TokenPolicyService $tokenPolicyService
    ) {}
    
    public function index(TokenPolicyIndexRequest $request): Response
    {
        $this->authorize('viewAny', TokenPolicy::class);

        $validated = $request->validated();

        return Inertia::render('TokenPolicies/Index', $this->tokenPolicyService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    public function create(): Response
    {
        $this->authorize('create', TokenPolicy::class);

        return Inertia::render('TokenPolicies/Create', $this->tokenPolicyService->getCreatePayload());
    }

    public function store(TokenPolicyStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', TokenPolicy::class);

        $this->tokenPolicyService->createTokenPolicy($request->validated());

        return redirect()
            ->route('admin.token-policies.index')
            ->with('success', 'Token policy created successfully.');
    }

    public function edit(TokenPolicy $tokenPolicy): Response
    {
        $this->authorize('update', $tokenPolicy);

        return Inertia::render('TokenPolicies/Edit', $this->tokenPolicyService->getEditPayload($tokenPolicy));
    }

    public function update(TokenPolicyUpdateRequest $request, TokenPolicy $tokenPolicy): RedirectResponse
    {
        $this->authorize('update', $tokenPolicy);

        $this->tokenPolicyService->updateTokenPolicy($tokenPolicy, $request->validated());

        return redirect()
            ->route('admin.token-policies.index')
            ->with('success', 'Token policy updated successfully.');
    }

    public function destroy(TokenPolicy $tokenPolicy): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $tokenPolicy);

        try {
            $this->tokenPolicyService->deleteTokenPolicy($tokenPolicy);
        } catch (RuntimeException $exception) {
            if (request()->expectsJson()) {
                return $this->errorResponse($exception->getMessage());
            }

            return redirect()
                ->route('admin.token-policies.index')
                ->with('error', $exception->getMessage());
        }

        if (request()->expectsJson()) {
            return $this->successResponse('Token policy deleted successfully.');
        }

        return redirect()
            ->route('admin.token-policies.index')
            ->with('success', 'Token policy deleted successfully.');
    }

    public function bulkDestroy(TokenPolicyBulkDestroyRequest $request): JsonResponse
    {
        $this->authorize('bulkDelete', TokenPolicy::class);

        try {
            $result = $this->tokenPolicyService->bulkDeleteTokenPolicies($request->validated('ids', []));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->successResponse(
            message: 'Selected token policies deleted successfully.',
            meta: $result['meta'],
        );
    }
}
