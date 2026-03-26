<?php

namespace App\Models;

use Database\Factories\SsoClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function redirectUris(): HasMany
    {
        return $this->hasMany(RedirectUri::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function scopes(): BelongsToMany
    {
        return $this->belongsToMany(Scope::class, 'client_scopes')
            ->withTimestamps()
            ->orderBy('scopes.name');
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(ClientSecret::class)->orderByDesc('is_active')->orderByDesc('id');
    }

    public function activeSecrets(): HasMany
    {
        return $this->secrets()
            ->where('is_active', true)
            ->whereNull('revoked_at');
    }

    public function tokenPolicy(): BelongsTo
    {
        return $this->belongsTo(TokenPolicy::class);
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(AuthorizationCode::class)->latest('id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class)->latest('id');
    }

    /**
     * @return array<int, string>
     */
    public function normalizedRedirectUris(): array
    {
        if ($this->relationLoaded('redirectUris') || $this->redirectUris()->exists()) {
            return $this->redirectUris
                ->pluck('uri')
                ->map(static fn (string $uri): string => trim($uri))
                ->filter()
                ->values()
                ->all();
        }

        return collect($this->redirect_uris ?? [])
            ->map(static fn (mixed $uri): string => trim((string) $uri))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function normalizedScopeCodes(): array
    {
        $scopes = $this->relationLoaded('scopes')
            ? $this->getRelation('scopes')
            : $this->scopes()->get();

        return $scopes
            ->pluck('code')
            ->map(static fn (string $code): string => trim($code))
            ->filter()
            ->values()
            ->all();
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
