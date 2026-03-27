<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateSelfPasswordRequest;
use App\Services\Profile\SelfServiceProfileService;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly SelfServiceProfileService $profileService,
    ) {
    }

    /**
     * Update the user's password.
     */
    public function update(UpdateSelfPasswordRequest $request): RedirectResponse
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'), $request);

        return back();
    }
}
