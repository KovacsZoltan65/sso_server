<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sso_client_id
 * @property string|null $name
 * @property string $secret_hash
 * @property string|null $last_four
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SsoClient $client
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereLastFour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereSecretHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereSsoClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientSecret whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
