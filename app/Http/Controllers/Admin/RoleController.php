<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleBulkDestroyRequest;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
            private readonly RoleService $roleService
    ) {
        ;
    }
    
    /**
     * @param RoleIndexRequest $request
     * @return \Inertia\Response
     */
    public function index(RoleIndexRequest $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $validated = $request->validated();

        return Inertia::render('Roles/Index', $this->roleService->getIndexPayload(
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

    /**
     * @return \Inertia\Response
     */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Create', $this->roleService->getCreatePayload());
    }

    /**
     * @param RoleStoreRequest $request
     * @return RedirectResponse
     */
    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $this->roleService->createRole($request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('api.roles.created'));
    }

    /**
     * @param Role $role
     * @return \Inertia\Response
     */
    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Roles/Edit', $this->roleService->getEditPayload($role));
    }

    /**
     * @param RoleUpdateRequest $request
     * @param Role $role
     * @return RedirectResponse
     */
    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roleService->updateRole($role, $request->validated());

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('api.roles.updated'));
    }

    /**
     * @param Role $role
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->roleService->deleteRole($role);
        } catch (RuntimeException $exception) {
            if (request()->expectsJson()) {
                return $this->errorResponse(
                    message: $exception->getMessage(),
                    errors: ['role' => [$exception->getMessage()]],
                );
            }

            return redirect()
                ->route('admin.roles.index')
                ->with('error', $exception->getMessage());
        }

        if (request()->expectsJson()) {
            return $this->successResponse(
                message: __('api.roles.deleted'),
                data: ['id' => $role->id],
            );
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('api.roles.deleted'));
    }

    /**
     * @param RoleBulkDestroyRequest $request
     * @return JsonResponse
     */
    public function bulkDestroy(RoleBulkDestroyRequest $request): JsonResponse
    {
        $this->authorize('bulkDelete', Role::class);

        try {
            $deletedIds = $this->roleService->bulkDeleteRoles($request->validated('ids'));
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                errors: ['ids' => [$exception->getMessage()]],
            );
        }

        return $this->successResponse(
            message: __('api.roles.bulk_deleted'),
            data: ['ids' => $deletedIds],
            meta: ['deletedCount' => count($deletedIds)],
        );
    }
}
