<?php

namespace App\Http\Requests\Admin;

use App\Models\Scope;
use App\Models\SsoClient;
use App\Models\TokenPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A validáció lefutása előtt normalizálja a tömbként érkező mezőket.
     *
     * Célok:
     * - eltávolítani a felesleges szóközöket
     * - kiszűrni az üres elemeket
     * - biztosítani a folyamatos indexelést
     * - scope listáknál megszüntetni a duplikációkat
     * 
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Redirect URI lista normalizálása:
        // - stringgé alakítás
        // - trim()
        // - üres elemek eltávolítása
        // - indexek újragenerálása
        $redirectUris = collect($this->input('redirect_uris', []))
            ->map(static fn (mixed $uri): string => trim((string) $uri))
            ->filter()
            ->values()
            ->all();

        // Engedélyezett scope-ok normalizálása:
        // - stringgé alakítás
        // - trim()
        // - üres elemek eltávolítása
        // - duplikált scope-ok eltávolítása
        // - indexek újragenerálása
        $scopes = collect($this->input('scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Alapértelmezett scope-ok normalizálása:
        // - stringgé alakítás
        // - trim()
        // - üres elemek eltávolítása
        // - duplikált scope-ok eltávolítása
        // - indexek újragenerálása
        $defaultScopes = collect($this->input('default_scopes', []))
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // A megtisztított adatok visszaírása a request-be,
        // így a validáció már a normalizált adatokkal dolgozik.
        $this->merge([
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'default_scopes' => $defaultScopes,
            'client_type' => trim((string) $this->input('client_type', SsoClient::CLIENT_TYPE_CONFIDENTIAL)),
        ]);
    }

    /**
     * Meghatározza az OAuth kliens létrehozásához szükséges validációs szabályokat.
     *
     * Biztonsági célok:
     * - csak érvényes és egyedi redirect URI-k regisztrálhatók
     * - csak aktív, a rendszerben létező scope-ok rendelhetők klienshez
     * - a token kiadási szabályok kizárólag létező token policy-ra hivatkozhatnak
     * - a trust tier kizárólag a rendszer által támogatott bizalmi szintek egyike lehet
     *
     * A scope és default scope listák tényleges kapcsolatát
     * (pl. a default scope-ok szerepelnek-e az engedélyezett scope-ok között)
     * külön üzleti validáció ellenőrzi.
     * 
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Az admin által megadott, ember által olvasható kliensnév.
            'name' => ['required', 'string', 'max:255'],

            // OAuth kliens típusa: confidential kliensek secretet kapnak,
            // public kliensek csak PKCE-vel használhatók.
            'client_type' => ['required', 'string', Rule::in(SsoClient::supportedClientTypes())],

            // Legalább egy visszairányítási végpont kötelező,
            // mert az authorization code flow erre tér vissza sikeres hitelesítés után.
            'redirect_uris' => ['required', 'array', 'min:1'],

            // Minden redirect URI:
            // - legyen teljes HTTP/HTTPS URL
            // - ne szerepeljen többször a kliens konfigurációjában
            'redirect_uris.*' => [
                'required',
                'url:http,https',
                'max:2048',
                'distinct:strict',
            ],

            // A kliens által kérhető scope-ok listája.
            'scopes' => ['nullable', 'array'],

            // Kizárólag aktív scope rendelhető klienshez.
            // Inaktív vagy törölt scope-ra nem épülhet új jogosultság.
            'scopes.*' => [
                'string',
                'distinct:strict',
                Rule::exists(Scope::class, 'code')
                    ->where('is_active', true),
            ],

            // Azok a scope-ok, amelyek automatikusan használhatók,
            // ha az authorization kérés nem küld explicit scope paramétert.
            'default_scopes' => ['nullable', 'array'],

            // Csak aktív scope lehet alapértelmezett scope.
            'default_scopes.*' => [
                'string',
                'distinct:strict',
                Rule::exists(Scope::class, 'code')
                    ->where('is_active', true),
            ],

            // A kliens globális engedélyezési állapota.
            // Inaktív kliens nem kezdeményezhet OAuth folyamatot.
            'is_active' => ['required', 'boolean'],

            // Opcionális token policy kapcsolat.
            // Meghatározhatja többek között a tokenek élettartamát
            // és egyéb biztonsági paramétereit.
            'token_policy_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:token_policies,id',
            ],

            // A kliens bizalmi besorolása.
            // A rendszer különböző biztonsági szabályokat alkalmazhat
            // a trust tier alapján.
            'trust_tier' => [
                'required',
                'string',
                Rule::in(SsoClient::supportedTrustTiers()),
            ],

            // Jelzi, hogy a kliens a szervezet saját alkalmazása-e.
            // A first-party és third-party kliensek eltérő OAuth szabályokkal működhetnek.
            'is_first_party' => ['required', 'boolean'],

            // Meghatározza, hogy a kliens jogosult-e
            // a felhasználói consent képernyő megkerülésére.
            'consent_bypass_allowed' => ['required', 'boolean'],
        ];
    }

    /**
     * Lefuttatja azokat az üzleti validációkat, amelyek több mező együttes
     * értelmezését igénylik, ezért nem írhatók le tisztán mezőszintű szabályokkal.
     *
     * Ellenőrzések:
     * - alapértelmezett scope csak olyan scope lehet, amely ténylegesen hozzá van rendelve a klienshez
     * - redirect URI nem tartalmazhat fragment részt, mert azt a szerver nem kapja meg OAuth redirect során
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $assignedScopes = collect($this->input('scopes', []));

            // A default scope nem adhat a kliensnek rejtett vagy implicit többletjogosultságot.
            // Csak olyan scope lehet alapértelmezett, amely a kliens engedélyezett scope-listájában is szerepel.
            $invalidDefaultScopes = collect($this->input('default_scopes', []))
                ->reject(fn (string $scope): bool => $assignedScopes->contains($scope));

            if ($invalidDefaultScopes->isNotEmpty()) {
                $validator->errors()->add(
                    'default_scopes',
                    'Default scopes must be assigned to the client first.'
                );
            }

            // OAuth redirect URI-ban a fragment kliensoldali rész:
            // a böngésző nem küldi el a szervernek, ezért biztonsági és egyezési
            // szempontból nem lehet megbízható redirect cél része.
            foreach ($this->input('redirect_uris', []) as $index => $redirectUri) {
                if (\is_string($redirectUri) && parse_url($redirectUri, PHP_URL_FRAGMENT) !== null) {
                    $validator->errors()->add(
                        "redirect_uris.{$index}",
                        'Redirect URIs must not contain a fragment.'
                    );
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
