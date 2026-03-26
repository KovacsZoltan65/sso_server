<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sso_client_id',
    'name',
    'secret_hash',
    'last_four',
    'is_active',
    'revoked_at',
    'expires_at',
])]
#[Hidden(['secret_hash'])]
class ClientSecret extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sso_client_id' => 'integer',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(SsoClient::class, 'sso_client_id');
    }
}
