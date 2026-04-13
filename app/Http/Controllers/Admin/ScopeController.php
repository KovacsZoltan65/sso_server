<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScopeBulkDestroyRequest;
use App\Http\Requests\Admin\ScopeIndexRequest;
use App\Http\Requests\Admin\ScopeStoreRequest;
use App\Http\Requests\Admin\ScopeUpdateRequest;
use App\Models\Scope;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ScopeController extends Controller
{
    public function __construct(
            private readonly ScopeService $scopeService
    ) {}
    
    /**
     * @param ScopeIndexRequest $request
     * @return \Inertia\Response
     */
    public function index(ScopeIndexRequest $request): Response
    {
        $this->authorize('viewAny', Scope::class);

        $validated = $request->validated();

        return Inertia::render('Scopes/Index', $this->scopeService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'name' => $validated['name'] ?? null,
                'code' => $validated['code'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    /**
     * @return \Inertia\Response
     */
    public function create(): Response
    {
        $this->authorize('create', Scope::class);

        return Inertia::render('Scopes/Create', $this->scopeService->getCreatePayload());
    }

    /**
     * @param ScopeStoreRequest $request
     * @return RedirectResponse
     */
    public function store(ScopeStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Scope::class);

        $this->scopeService->createScope($request->validated());

        return redirect()
            ->route('admin.scopes.index')
            ->with('success', 'Scope created successfully.');
    }

    /**
     * @param Scope $scope
     * @return \Inertia\Response
     */
    public function edit(Scope $scope): Response
    {
        $this->authorize('update', $scope);

        return Inertia::render('Scopes/Edit', $this->scopeService->getEditPayload($scope));
    }

    /**
     * @param ScopeUpdateRequest $request
     * @param Scope $scope
     * @return RedirectResponse
     */
    public function update(ScopeUpdateRequest $request, Scope $scope): RedirectResponse {
        $this->authorize('update', $scope);

        try {
            $this->scopeService->updateScope($scope, $request->validated());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.scopes.edit', $scope)
                ->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.scopes.index')
            ->with('success', 'Scope updated successfully.');
    }

    /**
     * @param Scope $scope
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Scope $scope): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $scope);

        try {
            $this->scopeService->deleteScope($scope);
        } catch (RuntimeException $exception) {
            if (request()->expectsJson()) {
                return $this->errorResponse(
                    message: $exception->getMessage(),
                    errors: ['scope' => [$exception->getMessage()]],
                );
            }

            return redirect()
                ->route('admin.scopes.index')
                ->with('error', $exception->getMessage());
        }

        if (request()->expectsJson()) {
            return $this->successResponse(
                message: 'Scope deleted successfully.',
                data: ['id' => $scope->id],
            );
        }

        return redirect()
            ->route('admin.scopes.index')
            ->with('success', 'Scope deleted successfully.');
    }

    /**
     * @param ScopeBulkDestroyRequest $request
     * @return JsonResponse
     */
    public function bulkDestroy(ScopeBulkDestroyRequest $request): JsonResponse
    {
        $this->authorize('bulkDelete', Scope::class);

        try {
            $deletedIds = $this->scopeService->bulkDeleteScopes($request->validated('ids'));
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                errors: ['ids' => [$exception->getMessage()]],
            );
        }

        return $this->successResponse(
            message: 'Selected scopes deleted successfully.',
            data: ['ids' => $deletedIds],
            meta: ['deletedCount' => count($deletedIds)],
        );
    }
}
