<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateSelfPasswordRequest;
use App\Http\Requests\Profile\UpdateSelfProfileRequest;
use App\Services\Profile\SelfServiceProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceProfileController extends Controller
{
    public function __construct(
        private readonly SelfServiceProfileService $profileService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewSelf', $request->user());

        return $this->successResponse(
            'Profile retrieved successfully.',
            $this->profileService->profilePayload($request->user(), $request),
            $this->responseMeta($request),
        );
    }

    public function update(UpdateSelfProfileRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Profile updated successfully.',
            $this->profileService->updateProfile($request->user(), $request->validated(), $request),
            $this->responseMeta($request),
        );
    }

    public function updatePassword(UpdateSelfPasswordRequest $request): JsonResponse
    {
        $this->profileService->updatePassword($request->user(), $request->validated('password'), $request);

        return $this->successResponse(
            'Password updated successfully.',
            [],
            $this->responseMeta($request),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseMeta(Request $request): array
    {
        return [
            'editable_fields' => $this->profileService->editableFields(),
            'read_only_fields' => $this->profileService->readOnlyFields(),
            'csrf_token' => $request->session()->token(),
        ];
    }
}
