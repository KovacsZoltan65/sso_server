<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Services\OAuth\OidcJwksService;
use Illuminate\Http\JsonResponse;

class OidcJwksController extends Controller
{
    public function __invoke(OidcJwksService $jwksService, AuditLogService $auditLogService): JsonResponse
    {
        $auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.jwks.served',
            description: 'OIDC JWKS served.',
            properties: [
                'status' => 'served',
            ],
        );

        return response()->json($jwksService->currentJwkSet());
    }
}
