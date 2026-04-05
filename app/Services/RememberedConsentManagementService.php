<?php

namespace App\Services;

use App\Data\UserClientConsentAdminSummaryData;
use App\Http\Requests\Admin\RevokeUserClientConsentRequest;
use App\Models\UserClientConsent;
use App\Repositories\Contracts\UserClientConsentRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\OAuth\OAuthRememberedConsentService;
use App\Support\Permissions\RememberedConsentPermissions;
use Illuminate\Support\Collection;

class RememberedConsentManagementService
{
    public function __construct(
        private readonly UserClientConsentRepositoryInterface $consents,
        private readonly OAuthRememberedConsentService $rememberedConsentService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getIndexPayload(
        array $filters,
        int $perPage = 10,
        ?string $sortField = null,
        ?int $sortOrder = null,
        int $page = 1,
    ): array {
        $paginator = $this->consents->paginateForAdmin($filters, $sortField, $sortOrder, $perPage, $page);
        $canRevoke = auth()->user()?->can(RememberedConsentPermissions::REVOKE) || false;

        return [
            'rows' => Collection::make($paginator->items())
                ->map(fn (UserClientConsent $consent) => UserClientConsentAdminSummaryData::fromModel($consent, $canRevoke))
                ->values()
                ->all(),
            'filters' => [
                'global' => $filters['global'] ?? null,
                'client_id' => $filters['client_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'sorting' => [
                'field' => $sortField ?? 'grantedAt',
                'order' => $sortOrder ?? -1,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'first' => ($paginator->currentPage() - 1) * $paginator->perPage(),
            ],
            'clientOptions' => $this->consents->clientOptionsForAdmin()->all(),
            'userOptions' => $this->consents->userOptionsForAdmin()->all(),
            'revocationReasonOptions' => collect(RevokeUserClientConsentRequest::supportedReasons())
                ->map(fn (string $reason) => [
                    'label' => str($reason)->replace('_', ' ')->headline()->toString(),
                    'value' => $reason,
                ])
                ->values()
                ->all(),
            'canManageRememberedConsents' => $canRevoke,
        ];
    }

    /**
     * @return array{consent: UserClientConsent, already_revoked: bool, previous_status: string, new_status: string}
     */
    public function revokeConsent(UserClientConsent $consent, string $reason): array
    {
        $previousStatus = $consent->currentStatus();
        $alreadyRevoked = $consent->isRevoked();

        if (! $alreadyRevoked) {
            $consent = $this->rememberedConsentService->revokeConsent($consent, $reason);
        } else {
            $consent->refresh();
        }

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_OAUTH,
            event: 'oauth.remembered_consent.revoked',
            description: 'OAuth remembered consent revoked by admin.',
            subject: $consent,
            causer: auth()->user(),
            properties: [
                'consent_id' => $consent->id,
                'client_id' => $consent->client_id,
                'client_public_id' => $consent->client->client_id,
                'target_user_id' => $consent->user_id,
                'actor_user_id' => auth()->id(),
                'revocation_reason' => $reason,
                'previous_status' => $previousStatus,
                'new_status' => $consent->currentStatus(),
            ],
        );

        return [
            'consent' => $consent,
            'already_revoked' => $alreadyRevoked,
            'previous_status' => $previousStatus,
            'new_status' => $consent->currentStatus(),
        ];
    }
}
