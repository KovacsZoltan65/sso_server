<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeTokenRequest;
use App\Http\Requests\Admin\TokenIndexRequest;
use App\Models\Token;
use App\Services\TokenManagementService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TokenController extends Controller
{
    public function index(TokenIndexRequest $request, TokenManagementService $tokenService): Response
    {
        $this->authorize('viewAny', Token::class);

        $validated = $request->validated();
        
        return Inertia::render('Tokens/Index', $tokenService->getIndexPayload(
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

    public function revoke(RevokeTokenRequest $request, Token $token, TokenManagementService $tokenService): JsonResponse
    {
        $this->authorize('revoke', $token);

        $token->loadMissing(['client', 'user']);
        $tokenService->revokeToken(
            token: $token,
            tokenType: (string) $request->validated('token_type'),
            reason: $request->validated('reason'),
        );

        return $this->successResponse(
            message: 'Token revoked successfully.',
            data: ['id' => $token->id],
        );
    }
}
