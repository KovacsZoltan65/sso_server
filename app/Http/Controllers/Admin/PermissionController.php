<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionIndexRequest;
use App\Http\Requests\Admin\PermissionStoreRequest;
use App\Http\Requests\Admin\PermissionUpdateRequest;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(PermissionIndexRequest $request, PermissionService $permissionService): Response
    {
        $this->authorize('viewAny', Permission::class);

        $validated = $request->validated();

        return Inertia::render('Permissions/Index', $permissionService->getIndexPayload(
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

    public function create(PermissionService $permissionService): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('Permissions/Create', $permissionService->getCreatePayload());
    }

    public function store(PermissionStoreRequest $request, PermissionService $permissionService): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $permissionService->createPermission($request->validated());

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission, PermissionService $permissionService): Response
    {
        $this->authorize('update', $permission);

        return Inertia::render('Permissions/Edit', $permissionService->getEditPayload($permission));
    }

    public function update(
        PermissionUpdateRequest $request,
        Permission $permission,
        PermissionService $permissionService,
    ): RedirectResponse {
        $this->authorize('update', $permission);

        $permissionService->updatePermission($permission, $request->validated());

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission, PermissionService $permissionService): RedirectResponse
    {
        $this->authorize('delete', $permission);

        try {
            $permissionService->deletePermission($permission);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.permissions.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
