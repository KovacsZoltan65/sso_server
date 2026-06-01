<?php

namespace App\Http\Requests\OAuth;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthAuthorizeRequest extends FormRequest
{
    /**
     * Az OAuth authorize endpoint elérhetőségét nem a FormRequest korlátozza.
     *
     * A kliens, redirect URI, scope és PKCE szabályok ellenőrzése
     * a validációs és domain szolgáltatási rétegben történik.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Meghatározza az OAuth authorization request alapvető formai szabályait.
     *
     * Ez a réteg csak a protokollszintű bemenet szerkezetét védi:
     * - kizárólag authorization code flow engedélyezett
     * - kötelező kliensazonosító és redirect URI szükséges
     * - opcionális OIDC / PKCE / state paraméterek kontrollált mérettel érkezhetnek
     *
     * A tényleges kliensjogosultság, redirect URI egyezés, scope kiadhatóság
     * és PKCE üzleti ellenőrzés külön OAuth szolgáltatási réteg feladata.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Csak authorization code flow támogatott; implicit/password irányt nem engedünk.
            'response_type' => ['required', 'string', Rule::in(['code'])],

            // A kliens azonosítója alapján keressük meg a regisztrált OAuth klienst.
            'client_id' => ['required', 'string', 'max:255'],

            // A redirect URI-t itt még csak formai bemenetként kezeljük;
            // a pontos egyezést a regisztrált kliens URI-listájával későbbi domain validáció végzi.
            'redirect_uri' => ['required', 'string', 'max:4000'],

            // Space-delimited scope lista; üres érték esetén a kliens default scope logikája érvényesülhet.
            'scope' => ['nullable', 'string', 'max:1000'],

            // CSRF/session-kötési célú opaque érték, amelyet változatlanul vissza kell adni a kliensnek.
            'state' => ['nullable', 'string', 'max:1000'],

            // OIDC esetén replay protection célú érték; openid scope mellett kötelező.
            'nonce' => ['nullable', 'string', 'max:255'],

            // PKCE challenge public kliens vagy extra hardening esetén.
            'code_challenge' => ['nullable', 'string', 'max:255'],

            // PKCE algoritmus jelölése; a támogatott értékek részletes ellenőrzése domain szinten történhet.
            'code_challenge_method' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Normalizálja az authorization request szöveges paramétereit validáció előtt.
     *
     * A cél, hogy a későbbi validáció és OAuth szolgáltatási logika
     * ne whitespace-eltérések vagy null értékek miatt hozzon eltérő döntést.
     *
     * Fontos: a redirect URI-t nem trimeljük, mert az URI pontos egyezése
     * biztonsági jelentőségű lehet a regisztrált klienskonfigurációhoz képest.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => trim((string) $this->input('client_id')),
            'redirect_uri' => (string) $this->input('redirect_uri'),
            'scope' => trim((string) $this->input('scope')),
            'state' => trim((string) $this->input('state')),
            'nonce' => trim((string) $this->input('nonce')),
            'code_challenge' => trim((string) $this->input('code_challenge')),
            'code_challenge_method' => trim((string) $this->input('code_challenge_method')),
        ]);
    }

    /**
     * Lefuttatja azokat a protokollszintű kiegészítő ellenőrzéseket,
     * amelyek több request paraméter együttes értelmezését igénylik.
     *
     * OIDC esetén az `openid` scope jelenléte nonce használatot követel meg,
     * hogy a tokenválasz jobban védhető legyen replay támadásokkal szemben.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->scopeContainsOpenId() && trim((string) $this->input('nonce')) === '') {
                $validator->errors()->add(
                    'nonce',
                    Localization::translate('validation.custom.nonce.required_for_openid_scope')
                );
            }
        });
    }

    /**
     * Eldönti, hogy az authorization request OIDC folyamatot kezdeményez-e.
     *
     * Az OAuth/OIDC scope paraméter szóközzel tagolt lista, ezért nem egyszerű
     * részszöveg-keresést használunk: az `openid_profile` például nem jelent
     * valódi `openid` scope-ot.
     */
    private function scopeContainsOpenId(): bool
    {
        return collect(preg_split('/\s+/', trim((string) $this->input('scope'))) ?: [])
            ->map(static fn (string $scope): string => trim($scope))
            ->filter()
            ->contains('openid');
    }
}
