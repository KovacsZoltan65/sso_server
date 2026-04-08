<?php

namespace App\Models;

use Database\Factories\ClientUserAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property int $user_id
 * @property bool $is_active
 * @property string|null $allowed_from
 * @property string|null $allowed_until
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SsoClient $client
 * @property-read User $user
 * @method static \Database\Factories\ClientUserAccessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereAllowedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereAllowedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientUserAccess whereUserId($value)
 * @mixin \Eloquent
 */
#[Fillable([
    'client_id',
    'user_id',
    'is_active',
    'allowed_from',
    'allowed_until',
    'notes',
])]
class ClientUserAccess extends Model
{
    /** @use HasFactory<ClientUserAccessFactory> */
    use HasFactory;

    protected $table = 'client_user_access';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'user_id' => 'integer',
            'is_active' => 'boolean',
            'allowed_from' => 'datetime',
            'allowed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SsoClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(SsoClient::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
