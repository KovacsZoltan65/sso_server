<?php

namespace App\Http\Requests\Admin;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientUpdateRequest extends FormRequest
{
    /**
     * Az admin jogosultság ellenőrzése policy/controller szinten történik.
     *
     * A FormRequest feladata itt kizárólag az input normalizálása
     * és a klienskonfiguráció adatminőségének ellenőrzése.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizálja az OAuth kliens tömbös konfigurációs mezőit validáció előtt.
     *
     * Ezzel biztosítjuk, hogy a validáció és a későbbi mentési logika már
     * egységesített adatszerkezettel dolgozzon:
     * - üres értékek nélkül
     * - trimelt stringekkel
     * - scope-oknál duplikációmentes listákkal
     */
    protected function prepareForValidation(): void
    {
        $redirectUris = collect($this->input('redirect_uris', []))
            ->map(static fn (mixed $uri): string => trim((string) $uri))
            ->filter()
            ->values()
            ->all();

        $scopes = collect($this->input('scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $defaultScopes = collect($this->input('default_scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'default_scopes' => $defaultScopes,
            'client_type' => trim((string) $this->input('client_type', SsoClient::CLIENT_TYPE_CONFIDENTIAL)),
        ]);
    }

    /**
     * Meghatározza az OAuth kliens módosításához szükséges validációs szabályokat.
     *
     * Biztonsági célok:
     * - csak megbízható redirect URI konfiguráció legyen menthető
     * - csak aktív scope-ok legyenek hozzárendelhetők a klienshez
     * - a token policy kizárólag létező szabálycsomagra hivatkozhasson
     * - a trust tier csak a rendszer által támogatott érték lehet
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Az admin felületen azonosítható, ember által olvasható kliensnév.
            'name' => ['required', 'string', 'max:255'],

            // OAuth kliens típusa: confidential vagy public.
            'client_type' => ['required', 'string', Rule::in(SsoClient::supportedClientTypes())],

            // Authorization code flow esetén legalább egy visszatérési cél szükséges.
            'redirect_uris' => ['required', 'array', 'min:1'],

            // A redirect URI lista nem tartalmazhat duplikált vagy nem HTTP(S) célokat.
            'redirect_uris.*' => ['required', 'url:http,https', 'max:2048', 'distinct:strict'],

            // A klienshez rendelhető engedélykészlet.
            'scopes' => ['nullable', 'array'],

            // Csak aktív, ismert scope adható ki a kliens számára.
            'scopes.*' => ['string', 'distinct:strict', Rule::exists(Scope::class, 'code')->where('is_active', true)],

            // Scope-ok, amelyek explicit scope kérés hiányában automatikusan használhatók.
            'default_scopes' => ['nullable', 'array'],

            // Alapértelmezettként is csak aktív scope használható.
            'default_scopes.*' => ['string', 'distinct:strict', Rule::exists(Scope::class, 'code')->where('is_active', true)],

            // Inaktív kliens nem vehet részt OAuth folyamatban.
            'is_active' => ['required', 'boolean'],

            // Opcionális token-élettartam és biztonsági szabálycsomag.
            'token_policy_id' => ['nullable', 'integer', 'min:1', 'exists:token_policies,id'],

            // A kliens bizalmi besorolása csak kontrollált értékkészletből választható.
            'trust_tier' => ['required', 'string', Rule::in(SsoClient::supportedTrustTiers())],

            // First-party kliensnél eltérő consent és biztonsági szabályok alkalmazhatók.
            'is_first_party' => ['required', 'boolean'],

            // Consent megkerülés csak kifejezetten engedélyezett kliensnél történhet.
            'consent_bypass_allowed' => ['required', 'boolean'],
        ];
    }

    /**
     * Lefuttatja a több mezőt együtt értelmező üzleti validációkat.
     *
     * Ezek nem egyszerű mezőszintű szabályok:
     * - a default scope-oknak a kliens scope-készletén belül kell maradniuk
     * - a redirect URI nem tartalmazhat fragmentet, mert az OAuth redirect során
     *   a fragment kliensoldali rész, és nem megbízható szerveroldali egyezési alap
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $assignedScopes = collect($this->input('scopes', []));

            // A default scope nem jelenthet implicit jogosultságbővítést.
            $invalidDefaultScopes = collect($this->input('default_scopes', []))
                ->reject(fn (string $scope): bool => $assignedScopes->contains($scope));

            if ($invalidDefaultScopes->isNotEmpty()) {
                $validator->errors()->add('default_scopes', 'Default scopes must be assigned to the client first.');
            }

            // Fragmentes redirect URI-t nem engedünk, mert az nem része a szerverhez
            // érkező callback URL-nek, így OAuth egyezésnél és auditnál félrevezető lenne.
            foreach ($this->input('redirect_uris', []) as $index => $redirectUri) {
                if (\is_string($redirectUri) && parse_url($redirectUri, PHP_URL_FRAGMENT) !== null) {
                    $validator->errors()->add("redirect_uris.{$index}", 'Redirect URIs must not contain a fragment.');
                }
            }

            if ($this->input('client_type') === SsoClient::CLIENT_TYPE_PUBLIC) {
                $policyId = $this->input('token_policy_id');

                $pkceRequired = TokenPolicy::query()
                    ->when($policyId !== null && $policyId !== '', fn ($query) => $query->whereKey((int) $policyId))
                    ->when($policyId === null || $policyId === '', fn ($query) => $query->where('is_default', true))
                    ->where('is_active', true)
                    ->where('pkce_required', true)
                    ->exists();

                if (! $pkceRequired) {
                    $validator->errors()->add(
                        'token_policy_id',
                        'Public clients must use a token policy where PKCE is required.'
                    );
                }
            }
        });
    }
}
