<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Services\OAuth\OidcDiscoveryService;
use Illuminate\Http\JsonResponse;

class OidcDiscoveryController extends Controller
{
    public function __invoke(OidcDiscoveryService $discoveryService, AuditLogService $auditLogService): JsonResponse
    {
        $auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.discovery.served',
            description: 'OIDC discovery metadata served.',
            properties: [
                'status' => 'served',
            ],
        );

        return response()->json($discoveryService->providerMetadata());
    }
}
