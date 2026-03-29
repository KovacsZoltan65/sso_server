<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sso_client_id
 * @property string $uri
 * @property string $uri_hash
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SsoClient $client
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereSsoClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RedirectUri whereUriHash($value)
 * @mixin \Eloquent
 */
class RedirectUri extends Model
{
    protected $fillable = [
        'sso_client_id',
        'uri',
        'uri_hash',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sso_client_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(SsoClient::class, 'sso_client_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirectUri): void {
            $normalizedUri = trim((string) $redirectUri->uri);

            $redirectUri->uri = $normalizedUri;
            $redirectUri->uri_hash = hash('sha256', $normalizedUri);
        });
    }
}
