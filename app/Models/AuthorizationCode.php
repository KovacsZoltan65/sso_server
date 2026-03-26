<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
