<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateSelfProfileRequest;
use App\Services\Profile\SelfServiceProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SelfServiceProfileService $profileService,
    ) {
    }

    /**
     * Display the user's profile form.
     * @param Request $request
     * @return \Inertia\Response
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     * @param UpdateSelfProfileRequest $request
     * @return RedirectResponse
     */
    public function update(UpdateSelfProfileRequest $request): RedirectResponse
    {
        $this->profileService->updateProfile($request->user(), $request->validated(), $request);

        return Redirect::route('profile.edit')->with('success', __('profile.updated_summary'));
    }

    /**
     * Delete the user's account.
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $this->profileService->deleteProfile($user, $request);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
