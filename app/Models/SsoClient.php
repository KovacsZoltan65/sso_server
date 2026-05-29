<?php

namespace App\Models;

use Database\Factories\SsoClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string $name
 * @property string $client_id
 * @property string $client_type
 * @property string $client_secret_hash
 * @property array<int, string>|null $redirect_uris
 * @property string|null $frontchannel_logout_uri
 * @property string|null $backchannel_logout_uri
 * @property bool $is_active
 * @property array<int, string>|null $scopes
 * @property int|null $token_policy_id
 * @property string $trust_tier
 * @property bool $is_first_party
 * @property bool $consent_bypass_allowed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RedirectUri> $redirectUris
 * @property-read Collection<int, Scope> $scopes
 * @property-read Collection<int, ClientSecret> $secrets
 * @property-read Collection<int, ClientSecret> $activeSecrets
 * @property-read Collection<int, ClientUserAccess> $userAccesses
 * @property-read Collection<int, UserClientConsent> $userConsents
 * @property-read Collection<int, AuthorizationCode> $authorizationCodes
 * @property-read Collection<int, Token> $tokens
 * @property-read TokenPolicy|null $tokenPolicy
 * @property-read int|null $active_secrets_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read int|null $authorization_codes_count
 * @property-read int|null $redirect_uris_count
 * @property-read int|null $scopes_count
 * @property-read int|null $secrets_count
 * @property-read int|null $tokens_count
 *
 * @method static \Database\Factories\SsoClientFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereClientType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereClientSecretHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereBackchannelLogoutUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereFrontchannelLogoutUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereRedirectUris($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereScopes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereTokenPolicyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereUpdatedAt($value)
 *
 * @property-read int|null $user_accesses_count
 * @property-read int|null $user_consents_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereConsentBypassAllowed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereIsFirstParty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SsoClient whereTrustTier($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'client_id',
    'client_type',
    'client_secret_hash',
    'redirect_uris',
    'frontchannel_logout_uri',
    'backchannel_logout_uri',
    'is_active',
    'scopes',
    'token_policy_id',
    'trust_tier',
    'is_first_party',
    'consent_bypass_allowed',
])]
#[Hidden(['client_secret_hash'])]
class SsoClient extends Model
{
    /** @use HasFactory<SsoClientFactory> */
    use HasFactory;

    public const CLIENT_TYPE_CONFIDENTIAL = 'confidential';

    public const CLIENT_TYPE_PUBLIC = 'public';

    public const TRUST_TIER_FIRST_PARTY_TRUSTED = 'first_party_trusted';

    public const TRUST_TIER_FIRST_PARTY_UNTRUSTED = 'first_party_untrusted';

    public const TRUST_TIER_THIRD_PARTY = 'third_party';

    public const TRUST_TIER_MACHINE_TO_MACHINE = 'machine_to_machine';

    protected $table = 'sso_clients';

    /**
     * Configure native casts for persisted scalar client attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'token_policy_id' => 'integer',
            'is_first_party' => 'boolean',
            'consent_bypass_allowed' => 'boolean',
        ];
    }

    /**
     * trust_tier is the primary trust field.
     * is_first_party is descriptive metadata.
     * consent_bypass_allowed is only a future precondition.
     *
     * @return array<int, string>
     */
    public static function supportedTrustTiers(): array
    {
        return [
            self::TRUST_TIER_FIRST_PARTY_TRUSTED,
            self::TRUST_TIER_FIRST_PARTY_UNTRUSTED,
            self::TRUST_TIER_THIRD_PARTY,
            self::TRUST_TIER_MACHINE_TO_MACHINE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedClientTypes(): array
    {
        return [
            self::CLIENT_TYPE_CONFIDENTIAL,
            self::CLIENT_TYPE_PUBLIC,
        ];
    }

    public function isConfidential(): bool
    {
        return $this->client_type === self::CLIENT_TYPE_CONFIDENTIAL;
    }

    public function isPublic(): bool
    {
        return $this->client_type === self::CLIENT_TYPE_PUBLIC;
    }

    public function hasActiveSecrets(): bool
    {
        return $this->activeSecrets()->exists();
    }

    /**
     * @return HasMany<RedirectUri, $this>
     */
    public function redirectUris(): HasMany
    {
        return $this->hasMany(RedirectUri::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Scope, $this>
     */
    public function scopes(): BelongsToMany
    {
        return $this->belongsToMany(Scope::class, 'client_scopes')
            ->withPivot('is_default')
            ->withTimestamps()
            ->orderBy('scopes.name');
    }

    /**
     * @return BelongsToMany<Scope, $this>
     */
    public function defaultScopes(): BelongsToMany
    {
        return $this->scopes()->wherePivot('is_default', true);
    }

    /**
     * @return HasMany<ClientSecret, $this>
     */
    public function secrets(): HasMany
    {
        return $this->hasMany(ClientSecret::class)->orderByDesc('is_active')->orderByDesc('id');
    }

    /**
     * @return HasMany<ClientSecret, $this>
     */
    public function activeSecrets(): HasMany
    {
        return $this->secrets()
            ->where('is_active', true)
            ->whereNull('revoked_at');
    }

    /**
     * @return HasMany<ClientUserAccess, $this>
     */
    public function userAccesses(): HasMany
    {
        return $this->hasMany(ClientUserAccess::class, 'client_id')->latest('id');
    }

    /**
     * @return HasMany<UserClientConsent, $this>
     */
    public function userConsents(): HasMany
    {
        return $this->hasMany(UserClientConsent::class, 'client_id')->latest('granted_at');
    }

    /**
     * @return BelongsTo<TokenPolicy, $this>
     */
    public function tokenPolicy(): BelongsTo
    {
        return $this->belongsTo(TokenPolicy::class);
    }

    /**
     * @return HasMany<AuthorizationCode, $this>
     */
    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(AuthorizationCode::class)->latest('id');
    }

    /**
     * @return HasMany<Token, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class)->latest('id');
    }

    /**
     * Return a stable list of redirect URIs from eager-loaded relations or the fallback attribute payload.
     *
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

    public function normalizedFrontChannelLogoutUri(): ?string
    {
        $uri = trim((string) ($this->frontchannel_logout_uri ?? ''));

        return $uri === '' ? null : $uri;
    }

    public function normalizedBackChannelLogoutUri(): ?string
    {
        $uri = trim((string) ($this->backchannel_logout_uri ?? ''));

        return $uri === '' ? null : $uri;
    }

    /**
     * Resolve the client's granted scope codes from the loaded relation or a fresh query.
     *
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

    /**
     * Resolve the client's explicit default scope codes.
     *
     * @return array<int, string>
     */
    public function defaultScopeCodes(): array
    {
        $scopes = $this->relationLoaded('scopes')
            ? $this->getRelation('scopes')
            : $this->scopes()->get();

        return $scopes
            ->filter(fn (Scope $scope): bool => (bool) ($scope->pivot?->is_default ?? false))
            ->pluck('code')
            ->map(static fn (string $code): string => trim($code))
            ->filter()
            ->values()
            ->all();
    }
}
