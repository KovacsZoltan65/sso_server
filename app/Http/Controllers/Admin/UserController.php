<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Services\UserService;
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
}
