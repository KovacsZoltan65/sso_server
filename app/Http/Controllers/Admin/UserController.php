<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserBulkDestroyRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class UserController extends Controller
{
    public function __construct(
            private readonly UserService $userService
    ) {}
    
    /**
     * @param UserIndexRequest $request
     * @return \Inertia\Response
     */
    public function index(UserIndexRequest $request): Response
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validated();

        return Inertia::render('Admin/Users/Index', $this->userService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'status' => $validated['status'] ?? null,
                'verified' => $validated['verified'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    /**
     * @param UserStoreRequest $request
     * @return RedirectResponse
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->userService->createUser($request->validated());

        return back()->with('success', 'User created successfully.');
    }

    /**
     * @param UserUpdateRequest $request
     * @param User $user
     * @return RedirectResponse
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userService->updateUser($user, $request->validated());

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->deleteUser($user, request()->user());
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                errors: ['user' => [$exception->getMessage()]],
            );
        }

        return $this->successResponse(
            message: 'User deleted successfully.',
            data: ['id' => $user->id],
        );
    }

    /**
     * @param UserBulkDestroyRequest $request
     * @return JsonResponse
     */
    public function bulkDestroy(UserBulkDestroyRequest $request): JsonResponse
    {
        $this->authorize('bulkDelete', User::class);

        try {
            $deletedIds = $this->userService->bulkDeleteUsers(
                ids: $request->validated('ids'),
                actingUser: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                errors: ['ids' => [$exception->getMessage()]],
            );
        }

        return $this->successResponse(
            message: 'Selected users deleted successfully.',
            data: ['ids' => $deletedIds],
            meta: ['deletedCount' => count($deletedIds)],
        );
    }
}
