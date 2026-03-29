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
 * @property int|null $authorization_code_id
 * @property int|null $parent_token_id
 * @property string $access_token_hash
 * @property string|null $refresh_token_hash
 * @property array<array-key, mixed>|null $scopes
 * @property \Illuminate\Support\Carbon $access_token_expires_at
 * @property \Illuminate\Support\Carbon|null $refresh_token_expires_at
 * @property \Illuminate\Support\Carbon|null $access_token_revoked_at
 * @property \Illuminate\Support\Carbon|null $refresh_token_revoked_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property string|null $issued_from_ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AuthorizationCode|null $authorizationCode
 * @property-read \App\Models\SsoClient $client
 * @property-read Token|null $parentToken
 * @property-read \App\Models\TokenPolicy|null $tokenPolicy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereAccessTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereAccessTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereAccessTokenRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereAuthorizationCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereIssuedFromIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereParentTokenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereRefreshTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereRefreshTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereRefreshTokenRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereScopes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereSsoClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereTokenPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Token whereUserId($value)
 * @mixin \Eloquent
 */
class Token extends Model
{
    use HasFactory;

    protected $fillable = [
        'sso_client_id',
        'user_id',
        'token_policy_id',
        'authorization_code_id',
        'parent_token_id',
        'access_token_hash',
        'refresh_token_hash',
        'scopes',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'access_token_revoked_at',
        'refresh_token_revoked_at',
        'last_used_at',
        'issued_from_ip',
        'user_agent',
    ];

    protected $casts = [
        'scopes' => 'array',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'access_token_revoked_at' => 'datetime',
        'refresh_token_revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
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

    public function authorizationCode(): BelongsTo
    {
        return $this->belongsTo(AuthorizationCode::class);
    }

    public function parentToken(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_token_id');
    }
}
