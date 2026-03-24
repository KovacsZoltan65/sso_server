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
        return Inertia::render('Admin/Users/Index', $userService->getIndexPayload(
            search: $request->validated('search'),
            perPage: (int) ($request->validated('perPage') ?? 10),
        ));
    }
}
