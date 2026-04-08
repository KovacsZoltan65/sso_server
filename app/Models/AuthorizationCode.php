<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sso_client_id
 * @property int $user_id
 * @property int|null $token_policy_id
 * @property string $code_hash
 * @property string $redirect_uri
 * @property string $redirect_uri_hash
 * @property string|null $nonce
 * @property string|null $oidc_sid
 * @property string|null $code_challenge
 * @property string|null $code_challenge_method
 * @property array<array-key, mixed>|null $scopes
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SsoClient $client
 * @property-read \App\Models\TokenPolicy|null $tokenPolicy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereCodeChallenge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereCodeChallengeMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereCodeHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereConsumedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereNonce($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereOidcSid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereRedirectUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereRedirectUriHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereScopes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereSsoClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereTokenPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationCode whereUserId($value)
 * @mixin \Eloquent
 */
class AuthorizationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'sso_client_id',
        'user_id',
        'token_policy_id',
        'code_hash',
        'redirect_uri',
        'redirect_uri_hash',
        'nonce',
        'oidc_sid',
        'code_challenge',
        'code_challenge_method',
        'scopes',
        'expires_at',
        'consumed_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(SsoClient::class, 'sso_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tokenPolicy(): BelongsTo
    {
        return $this->belongsTo(TokenPolicy::class);
    }

    public function identityResponseNonce(): ?string
    {
        $nonce = trim((string) ($this->nonce ?? ''));

        return $nonce !== '' ? $nonce : null;
    }

    public function oidcSessionIdentifier(): ?string
    {
        $sid = trim((string) ($this->oidc_sid ?? ''));

        return $sid !== '' ? $sid : null;
    }

    public function hasIdentityResponseNonce(): bool
    {
        return $this->identityResponseNonce() !== null;
    }

    public function requiresIdentityNonceValidation(): bool
    {
        return in_array('openid', $this->scopes ?? [], true);
    }

    /**
     * @return array{
     *     authorization_code_id: int,
     *     client_id: int,
     *     user_id: int,
     *     returned_nonce: string|null,
     *     oidc_sid: string|null,
     *     scope_contains_openid: bool
     * }
     */
    public function identityNonceContext(): array
    {
        return [
            'authorization_code_id' => $this->id,
            'client_id' => $this->sso_client_id,
            'user_id' => $this->user_id,
            'returned_nonce' => $this->identityResponseNonce(),
            'oidc_sid' => $this->oidcSessionIdentifier(),
            'scope_contains_openid' => $this->requiresIdentityNonceValidation(),
        ];
    }
}
