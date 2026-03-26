<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
