<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $access_token_ttl_minutes
 * @property int $refresh_token_ttl_minutes
 * @property bool $refresh_token_rotation_enabled
 * @property bool $pkce_required
 * @property bool $reuse_refresh_token_forbidden
 * @property bool $is_default
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuthorizationCode> $authorizationCodes
 * @property-read int|null $authorization_codes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Token> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\TokenPolicyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereAccessTokenTtlMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy wherePkceRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereRefreshTokenRotationEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereRefreshTokenTtlMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereReuseRefreshTokenForbidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TokenPolicy whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TokenPolicy extends Model
{
    use HasFactory;

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

}
