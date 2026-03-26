<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TokenPolicy extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'access_token_ttl_minutes',
        'refresh_token_ttl_minutes',
        'refresh_token_rotation_enabled',
        'pkce_required',
        'reuse_refresh_token_forbidden',
        'is_default',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'access_token_ttl_minutes' => 'integer',
        'refresh_token_ttl_minutes' => 'integer',
        'refresh_token_rotation_enabled' => 'boolean',
        'pkce_required' => 'boolean',
        'reuse_refresh_token_forbidden' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(AuthorizationCode::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'code',
                'description',
                'access_token_ttl_minutes',
                'refresh_token_ttl_minutes',
                'refresh_token_rotation_enabled',
                'pkce_required',
                'reuse_refresh_token_forbidden',
                'is_default',
                'is_active',
            ])
            ->logOnlyDirty();
    }
}
