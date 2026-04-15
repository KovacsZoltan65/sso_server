<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeTokenFamilyRequest;
use App\Http\Requests\Admin\RevokeTokenRequest;
use App\Http\Requests\Admin\TokenIndexRequest;
use App\Models\Token;
use App\Services\TokenManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function __construct(
            private readonly TokenManagementService $tokenService
    ) {}
    
    /**
     * @param TokenIndexRequest $request
     * @return \Inertia\Response
     */
    public function index(TokenIndexRequest $request): Response
    {
        $this->authorize('viewAny', Token::class);

        $validated = $request->validated();
        
        return Inertia::render('Tokens/Index', $this->tokenService->getIndexPayload(
            filters: [
                'global' => $validated['global'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'token_type' => $validated['token_type'] ?? 'refresh_token',
                'state' => $validated['state'] ?? null,
            ],
            perPage: (int) ($validated['perPage'] ?? 10),
            sortField: $validated['sortField'] ?? 'createdAt',
            sortOrder: isset($validated['sortOrder']) ? (int) $validated['sortOrder'] : -1,
            page: (int) ($validated['page'] ?? 1),
        ));
    }

    /**
     * @param RevokeTokenRequest $request
     * @param Token $token
     * @return JsonResponse
     */
    public function revoke(RevokeTokenRequest $request, Token $token): JsonResponse
    {
        $this->authorize('revoke', $token);

        $token->loadMissing(['client', 'user']);
        $this->tokenService->revokeToken(
            token: $token,
            tokenType: (string) $request->validated('token_type'),
            reason: $request->validated('reason'),
        );

        return $this->successResponse(
            message: __('api.tokens.revoked'),
            data: ['id' => $token->id],
        );
    }

    /**
     * @param RevokeTokenFamilyRequest $request
     * @return JsonResponse
     */
    public function revokeFamily(RevokeTokenFamilyRequest $request): JsonResponse
    {
        $this->authorize('revokeFamily', Token::class);

        try {
            $result = $this->tokenService->revokeFamily(
                familyId: (string) $request->validated('family_id'),
                reason: $request->validated('reason'),
            );
        } catch (ModelNotFoundException) {
            return $this->errorResponse(
                message: __('api.tokens.family_not_found'),
                errors: ['family_id' => [__('api.tokens.family_not_found')]],
                status: 404,
            );
        }

        return $this->successResponse(
            message: $result['already_revoked']
                ? __('api.tokens.family_already_revoked')
                : __('api.tokens.family_revoked'),
            data: $result,
        );
    }
}
