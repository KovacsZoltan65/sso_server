<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(RoleIndexRequest $request, RoleService $roleService): Response
    {
        $this->authorize('viewAny', Role::class);

        $validated = $request->validated();

        return Inertia::render('Roles/Index', $roleService->getIndexPayload(
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

    public function create(RoleService $roleService): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Create', $roleService->getCreatePayload());
    }

    public function store(RoleStoreRequest $request, RoleService $roleService): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $roleService->createRole($request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role, RoleService $roleService): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Roles/Edit', $roleService->getEditPayload($role));
    }

    public function update(RoleUpdateRequest $request, Role $role, RoleService $roleService): RedirectResponse
    {
        $this->authorize('update', $role);

        $roleService->updateRole($role, $request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role, RoleService $roleService): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $roleService->deleteRole($role);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
