<?php

namespace App\Models;

use Database\Factories\SsoClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'name',
    'client_id',
    'client_secret_hash',
    'redirect_uris',
    'is_active',
    'scopes',
    'token_policy_id',
])]
#[Hidden(['client_secret_hash'])]
class SsoClient extends Model
{
    /** @use HasFactory<SsoClientFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'sso_clients';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'token_policy_id' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sso_clients')
            ->logOnly(['name', 'client_id', 'is_active', 'redirect_uris', 'scopes', 'token_policy_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
