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
}
