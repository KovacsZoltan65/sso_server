<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionBulkDestroyRequest;
use App\Http\Requests\Admin\PermissionIndexRequest;
use App\Http\Requests\Admin\PermissionStoreRequest;
use App\Http\Requests\Admin\PermissionUpdateRequest;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
            private readonly PermissionService $permissionService
    ) {}
    
    public function index(PermissionIndexRequest $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        $validated = $request->validated();

        return Inertia::render('Permissions/Index', $this->permissionService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'name' => $validated['name'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) ($validated['sortOrder']) : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    public function create(): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('Permissions/Create', $this->permissionService->getCreatePayload());
    }

    public function store(PermissionStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $this->permissionService->createPermission($request->validated());

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission): Response
    {
        $this->authorize('update', $permission);

        return Inertia::render('Permissions/Edit', $this->permissionService->getEditPayload($permission));
    }

    public function update(
        PermissionUpdateRequest $request,
        Permission $permission
    ): RedirectResponse {
        $this->authorize('update', $permission);

        $this->permissionService->updatePermission($permission, $request->validated());

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $permission);

        try {
            $this->permissionService->deletePermission($permission);
        } catch (RuntimeException $exception) {
            if (request()->expectsJson()) {
                return $this->errorResponse(
                    message: $exception->getMessage(),
                    errors: ['permission' => [$exception->getMessage()]],
                );
            }

            return redirect()
                ->route('admin.permissions.index')
                ->with('error', $exception->getMessage());
        }

        if (request()->expectsJson()) {
            return $this->successResponse(
                message: 'Permission deleted successfully.',
                data: ['id' => $permission->id],
            );
        }

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }

    public function bulkDestroy(
        PermissionBulkDestroyRequest $request
    ): JsonResponse {
        $this->authorize('bulkDelete', Permission::class);

        try {
            $deletedIds = $this->permissionService->bulkDeletePermissions($request->validated('ids'));
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                errors: ['ids' => [$exception->getMessage()]],
            );
        }

        return $this->successResponse(
            message: 'Selected permissions deleted successfully.',
            data: ['ids' => $deletedIds],
            meta: ['deletedCount' => count($deletedIds)],
        );
    }
}
