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
        $jwks = $jwksService->currentJwkSet();

        $auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.jwks.served_multikey',
            description: 'OIDC multi-key JWKS served.',
            properties: [
                'key_count' => count($jwks['keys'] ?? []),
                'status' => 'served',
            ],
        );

        return response()->json($jwks);
    }
}
