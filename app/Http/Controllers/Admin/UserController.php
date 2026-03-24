<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
}
