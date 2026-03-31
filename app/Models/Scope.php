<?php

namespace App\Models;

use Database\Factories\ScopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SsoClient> $clients
 * @property-read int|null $clients_count
 * @method static \Database\Factories\ScopeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Scope whereUpdatedAt($value)
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'code',
    'description',
    'is_active',
])]
class Scope extends Model
{
    /** @use HasFactory<ScopeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }


    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(SsoClient::class, 'client_scopes')
            ->withTimestamps();
    }
}
