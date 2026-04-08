<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $client_id
 * @property array<int, string> $granted_scope_codes
 * @property string $granted_scope_fingerprint
 * @property string $redirect_uri_hash
 * @property string $trust_tier_snapshot
 * @property bool $consent_bypass_allowed_snapshot
 * @property string $consent_policy_version
 * @property \Illuminate\Support\Carbon $granted_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property string|null $revocation_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read SsoClient $client
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereConsentBypassAllowedSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereConsentPolicyVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereGrantedScopeCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereGrantedScopeFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereRedirectUriHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereRevocationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereTrustTierSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserClientConsent whereUserId($value)
 * @mixin \Eloquent
 */
class UserClientConsent extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'granted_scope_codes',
        'granted_scope_fingerprint',
        'redirect_uri_hash',
        'trust_tier_snapshot',
        'consent_bypass_allowed_snapshot',
        'consent_policy_version',
        'granted_at',
        'expires_at',
        'revoked_at',
        'revocation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_scope_codes' => 'array',
            'consent_bypass_allowed_snapshot' => 'boolean',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SsoClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(SsoClient::class, 'client_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function currentStatus(): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'active';
    }
}
