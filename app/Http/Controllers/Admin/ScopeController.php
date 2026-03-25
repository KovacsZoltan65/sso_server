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
    public function index(ScopeIndexRequest $request, ScopeService $scopeService): Response
    {
        $this->authorize('viewAny', Scope::class);

        $validated = $request->validated();

        return Inertia::render('Scopes/Index', $scopeService->getIndexPayload(
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

    public function create(ScopeService $scopeService): Response
    {
        $this->authorize('create', Scope::class);

        return Inertia::render('Scopes/Create', $scopeService->getCreatePayload());
    }

    public function store(ScopeStoreRequest $request, ScopeService $scopeService): RedirectResponse
    {
        $this->authorize('create', Scope::class);

        $scopeService->createScope($request->validated());

        return redirect()
            ->route('admin.scopes.index')
            ->with('success', 'Scope created successfully.');
    }

    public function edit(Scope $scope, ScopeService $scopeService): Response
    {
        $this->authorize('update', $scope);

        return Inertia::render('Scopes/Edit', $scopeService->getEditPayload($scope));
    }

    public function update(
        ScopeUpdateRequest $request,
        Scope $scope,
        ScopeService $scopeService,
    ): RedirectResponse {
        $this->authorize('update', $scope);

        try {
            $scopeService->updateScope($scope, $request->validated());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.scopes.edit', $scope)
                ->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.scopes.index')
            ->with('success', 'Scope updated successfully.');
    }

    public function destroy(Scope $scope, ScopeService $scopeService): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $scope);

        try {
            $scopeService->deleteScope($scope);
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

    public function bulkDestroy(
        ScopeBulkDestroyRequest $request,
        ScopeService $scopeService,
    ): JsonResponse {
        $this->authorize('bulkDelete', Scope::class);

        try {
            $deletedIds = $scopeService->bulkDeleteScopes($request->validated('ids'));
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
