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
    public function index(UserIndexRequest $request, UserService $userService): Response
    {
        $validated = $request->validated();

        return Inertia::render('Admin/Users/Index', $userService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'verified' => $validated['verified'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'name',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : 1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    public function store(UserStoreRequest $request, UserService $userService): RedirectResponse
    {
        $userService->createUser($request->validated());

        return back()->with('success', 'User created successfully.');
    }

    public function update(UserUpdateRequest $request, User $user, UserService $userService): RedirectResponse
    {
        $userService->updateUser($user, $request->validated());

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user, UserService $userService): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $userService->deleteUser($user, request()->user());
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

    public function bulkDestroy(UserBulkDestroyRequest $request, UserService $userService): JsonResponse
    {
        $this->authorize('bulkDelete', User::class);

        try {
            $deletedIds = $userService->bulkDeleteUsers(
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
