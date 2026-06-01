<?php

namespace App\Http\Requests\OAuth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthTokenRequest extends FormRequest
{
    /**
     * A token endpoint hozzáférését nem a FormRequest dönti el.
     *
     * A kliens hitelesítése, grant jogosultság, authorization code,
     * refresh token és PKCE ellenőrzés az OAuth szolgáltatási réteg feladata.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Meghatározza a token endpoint protokollszintű bemeneti szabályait.
     *
     * Ez a réteg csak azt biztosítja, hogy a kérés szerkezete megfeleljen
     * a támogatott OAuth grant típusoknak:
     * - authorization_code grant
     * - refresh_token grant
     *
     * A tényleges tokenkiadás előtt külön domain ellenőrzés szükséges:
     * kliensazonosítás, secret-validáció, code/token állapot,
     * scope policy, redirect URI egyezés és PKCE ellenőrzés.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Csak a támogatott tokenkiadási folyamatok engedélyezettek.
            'grant_type' => ['required', 'string', Rule::in(['authorization_code', 'refresh_token'])],

            // A tokenkérést kezdeményező regisztrált OAuth kliens azonosítója.
            'client_id' => ['required', 'string', 'max:255'],

            // Confidential kliens esetén használható; public/PKCE kliensnél hiányozhat.
            'client_secret' => ['nullable', 'string', 'max:255'],

            // Authorization code grant esetén a korábban kiadott, egyszer használható code.
            'code' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:255'],

            // Authorization code grantnél a redirect URI-nak a code kiadásakor használt értékkel kell egyeznie.
            'redirect_uri' => ['required_if:grant_type,authorization_code', 'nullable', 'url', 'max:4000'],

            // PKCE esetén ebből ellenőrzi a rendszer, hogy a tokenkérő ugyanaz a fél-e,
            // amely az authorization requestet indította.
            'code_verifier' => ['nullable', 'string', 'max:255'],

            // Refresh token grant esetén ez képviseli a korábbi tokenkiadási jogosultság folytatását.
            'refresh_token' => ['required_if:grant_type,refresh_token', 'nullable', 'string', 'max:255'],

            // Token endpointon scope bővítés nem engedhető;
            // a szolgáltatási rétegnek meg kell akadályoznia a scope escalationt.
            'scope' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Normalizálja a token request szöveges paramétereit validáció előtt.
     *
     * A cél, hogy a tokenkiadási logika ne whitespace-eltérések miatt hozzon
     * eltérő döntést kliensazonosító, grant típus, code vagy token értékeknél.
     *
     * Fontos: a redirect URI-t nem trimeljük, mert az OAuth flow-ban
     * biztonsági jelentőségű a korábban rögzített URI-val való pontos egyezés.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'grant_type' => trim((string) $this->input('grant_type')),
            'client_id' => trim((string) $this->input('client_id')),
            'client_secret' => trim((string) $this->input('client_secret')),
            'code' => trim((string) $this->input('code')),
            'redirect_uri' => (string) $this->input('redirect_uri'),
            'code_verifier' => trim((string) $this->input('code_verifier')),
            'refresh_token' => trim((string) $this->input('refresh_token')),
            'scope' => trim((string) $this->input('scope')),
        ]);
    }
}
