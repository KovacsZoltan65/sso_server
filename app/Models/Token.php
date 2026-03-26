<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
