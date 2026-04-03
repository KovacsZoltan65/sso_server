# OAuth Consent Flow Specification

## 1. Cél és scope

Ez a dokumentum a `sso_server` jövőbeli valódi consent flow-jának implementálható specifikációja.

Scope:

- `GET /oauth/authorize` utáni consent decision pont
- `GET` consent képernyő
- `POST` approve
- `POST` deny
- success callback és refusal callback viselkedés
- route, controller, request, service felelősségek
- consent context integritás
- minimum audit event lista
- tesztterv

Nem scope:

- tényleges route vagy controller implementáció
- consent UI komponensek megépítése
- adatbázis migráció
- remembered consent
- trust tier / first-party bypass modell
- OIDC bővítés

Kapcsolódó specifikáció:

- `docs/client-trust-tier-spec.md` definiálja, hogy a valid authorize kontextus után a rendszer `show_consent`, `skip_consent` vagy `deny_authorization` irányba mehet-e
- `docs/remembered-consent-spec.md` definiálja, hogy a trust-tier által egyébként engedhető bypass mikor használhat meglévő remembered consent rekordot

## 2. Current state summary

Jelenleg a szerver authorize flow-ja a következőképpen működik:

1. A kliens `GET /oauth/authorize` kérést küld `client_id`, `redirect_uri`, `scope`, `state`, `code_challenge`, `code_challenge_method` paraméterekkel.
2. Az `OAuthAuthorizeRequest` validálja a request szerkezetét.
3. Az `AuthorizationController` azonnal az `OAuthAuthorizationService::approve()` metódust hívja.
4. A service:
   - validálja a klienst
   - validálja a redirect URI-t
   - feloldja a scope-okat
   - ellenőrzi a PKCE szabályokat
   - ellenőrzi a kliens-hozzáférési korlátozásokat
   - siker esetén azonnal authorization code-ot állít ki
   - majd `302` redirectet épít a kliens `redirect_uri` címére `code` és `state` paraméterekkel
5. Bizonyos validált refusal esetek már most is callback hibaformában mennek vissza, például `access_denied`.

Következmény:

- a tényleges user consent jelenleg hiányzik
- a rendszer valid authorize kontextus esetén automatikusan approve-ol
- nincs külön decision context
- nincs GET consent oldal
- nincs POST approve/deny boundary

## 3. Target consent flow

### 3.1. High-level célállapot

A jövőbeli authorize flow csak akkor állíthat ki authorization code-ot, ha:

1. a kliens valid
2. a redirect URI valid
3. a request valid
4. a user hitelesített
5. a user hozzáférhet a klienshez
6. a user explicit approve döntést hoz a consent képernyőn

### 3.2. Lépésről lépésre

#### Step 1. Authorize request beérkezik

Endpoint:

- `GET /oauth/authorize`

Feltételek:

- `client_id` valid
- `redirect_uri` valid
- `response_type=code`
- scope lista valid
- PKCE paraméterek validak
- user session hitelesített

Ha ezek közül bármelyik nem teljesül:

- a hiba provider oldalon marad
- nem jelenik meg consent képernyő
- nem épül callback redirect kétes célra

#### Step 2. Access restriction check

Még a consent előtt le kell futnia minden olyan ellenőrzésnek, amely nem user döntés, hanem provider policy:

- inaktív kliens
- invalid redirect URI
- disallowed scope
- PKCE violation
- user access restriction a klienshez

Ha a kliens és a redirect már valid, de a user hozzáférése policy szerint tiltott:

- a flow callback refusal ágra mehet vissza a STORY-07 szerződés szerint
- `error=access_denied`
- opcionális `error_description`
- `state` visszaadható

Ez nem consent deny, hanem provider oldali authorization refusal.

#### Step 3. Consent context létrejön

Ez a lépés csak akkor történik meg, ha a trust-tier döntés eredménye `show_consent`.

Ha az authorize request valid és a user is hitelesített, a szerver létrehoz egy szerveroldali consent contextet.

Ez tartalmazza:

- kliens azonosító és megjelenítési adat
- validált redirect URI
- validált scope lista
- eredeti `state`
- user azonosító
- PKCE-hez tartozó authorize inputok
- lejárati idő
- egyszer használatos consent token

#### Step 4. GET consent screen render

Ez a képernyő nem minden kliensnél jelenik meg.

Megjelenítési feltétel:

- a trust-tier policy döntés `show_consent`

A szerver a validált authorize kontextusból egy consent oldalt renderel.

A user látja:

- melyik alkalmazás kér hozzáférést
- mi a kliens megjelenítési neve
- rövid kliensleírás vagy fallback azonosító
- mely scope-ok kértek hozzáférést
- milyen döntést hozhat: approve vagy deny

A képernyő user-facing, nem admin UI.

#### Step 5. POST approve

Ez a lépés csak olyan flow-ban értelmezett, ahol a rendszer előzőleg consent képernyőt renderelt.

A user elküldi az approve döntést.

A szerver:

1. betölti és validálja a consent contextet
2. ellenőrzi, hogy a context ugyanahhoz a sessionhöz és userhez tartozik
3. ellenőrzi, hogy még nem járt le és nem lett felhasználva
4. újra a szerveroldali contextből dolgozik, nem a böngésző által visszaküldött kliensadatokból
5. kiállítja az authorization code-ot
6. success callback redirectet épít a validált `redirect_uri` címre

Success callback:

- `code=<authorization_code>`
- `state=<eredeti state>` ha eredetileg jelen volt

#### Step 6. POST deny

Ez a lépés csak olyan flow-ban értelmezett, ahol a rendszer előzőleg consent képernyőt renderelt.

A user elküldi a deny döntést.

A szerver:

1. betölti és validálja a consent contextet
2. ellenőrzi, hogy a context sessionhöz és userhez kötött
3. ellenőrzi, hogy a redirect cél a korábban validált redirect URI
4. refusal callback redirectet épít

Refusal callback:

- `error=access_denied`
- `error_description` opcionális, standardizált rövid értékkel
- `state=<eredeti state>` ha eredetileg jelen volt

## 4. Route / action specification

### 4.1. `GET /oauth/authorize`

Cél:

- authorize request validálása
- kliens és redirect validálása
- access restriction ellenőrzése
- hitelesítetlen user login flow-ra küldése
- valid esetben consent context létrehozása és consent render

Input:

- query paraméterek az aktuális authorize requestből

Előfeltételek:

- érvényes web session vagy a meglévő login continuation mechanizmus

Response viselkedés:

- invalid client / invalid redirect / invalid request -> provider-side validation hiba
- valid client + valid redirect + policy refusal -> callback refusal a meglévő authorize error contract szerint
- trust-tier policy `skip_consent` -> közvetlen approve-success ág, opcionálisan remembered consent evaluation után
- trust-tier policy `show_consent` -> consent page render

Felelős rétegek:

- `OAuthAuthorizeRequest`: szerkezeti input validáció
- `AuthorizationController`: vékony orchestration
- új `OAuthAuthorizationService` decision ág: authorize requestből trust-tier és consent preparation
- auth guard / middleware: user session biztosítása

### 4.2. `POST /oauth/authorize/approve`

Cél:

- explicit user approve döntés feldolgozása
- authorization code kiállítása
- success callback redirect

Input:

- `consent_token`

Nem megbízható input:

- `client_id`
- `redirect_uri`
- `scope`
- `state`
- PKCE mezők

Előfeltételek:

- hitelesített user session
- érvényes consent context
- context a jelenlegi userhez és sessionhöz kötött

Response:

- success -> redirect a validált callback URI-ra `code` és `state` paraméterrel
- invalid/expired context -> provider-side hiba, nincs callback redirect

Felelős rétegek:

- új `OAuthConsentDecisionRequest`: POST payload validáció
- új `OAuthConsentController@approve`: vékony controller
- új `OAuthConsentService@approve`: business logic

### 4.3. `POST /oauth/authorize/deny`

Cél:

- explicit user deny döntés feldolgozása
- refusal callback redirect

Input:

- `consent_token`

Előfeltételek:

- hitelesített user session
- érvényes consent context
- validált redirect URI már a contextben rögzítve

Response:

- success -> redirect a validált callback URI-ra `error=access_denied` paraméterekkel
- invalid/expired context -> provider-side hiba, nincs callback redirect

Felelős rétegek:

- új `OAuthConsentDecisionRequest`
- új `OAuthConsentController@deny`
- új `OAuthConsentService@deny`

## 5. Consent context specification

### 5.1. Kötelező adatok

A consent context minimum mezői:

- `consent_token`
- `client_id`
- `client_db_id`
- `client_display_name`
- `client_description`
- `redirect_uri`
- `requested_scopes`
- `state`
- `response_type`
- `code_challenge`
- `code_challenge_method`
- `user_id`
- `created_at`
- `expires_at`

### 5.2. Források

- `client_id`, `redirect_uri`, `state`, PKCE mezők: az eredeti authorize requestből, validálás után
- `client_db_id`, `client_display_name`, `client_description`: szerveroldali kliensrekordból
- `requested_scopes`: szerveroldalon validált scope listából
- `user_id`: hitelesített user sessionből
- `consent_token`, időbélyegek: szerver generálja

### 5.3. Mit szabad a formon visszaküldeni

Csak ezt:

- `consent_token`

Opcionálisan:

- CSRF token a szokásos Laravel mechanizmus szerint

### 5.4. Mit nem szabad a böngészőre bízni

Nem szabad a POST hidden inputokra támaszkodni az alábbiaknál:

- `client_id`
- `redirect_uri`
- `scope`
- `state`
- `user_id`
- `code_challenge`
- `code_challenge_method`

Ezeket minden approve/deny döntésnél a szerveroldali consent contextből kell visszaolvasni.

## 6. Context integrity modell

### 6.1. Javasolt megoldás

Első implementációhoz javasolt:

- sessionben tárolt szerveroldali consent context
- plusz egyszer használatos `consent_token` azonosítóval

Indoklás:

- illeszkedik a meglévő Laravel web session alapú login flow-hoz
- nem igényel új adatbázis-táblát az első implementációhoz
- csökkenti annak kockázatát, hogy a böngésző által visszaküldött hidden input legyen az igazságforrás
- egyszerűen invalidálható approve vagy deny után

### 6.2. Javasolt session shape

Példa szerkezet:

```php
oauth.consent_contexts.<consent_token> = [
    'user_id' => 123,
    'client_id' => 'portal-client',
    'client_db_id' => 7,
    'redirect_uri' => 'https://client.example.com/callback',
    'requested_scopes' => ['openid', 'profile', 'email'],
    'state' => '...',
    'response_type' => 'code',
    'code_challenge' => '...',
    'code_challenge_method' => 'S256',
    'created_at' => '...',
    'expires_at' => '...',
]
```

### 6.3. Érvényességi szabályok

A context:

- sessionhöz kötött
- userhez kötött
- egyszer használatos
- rövid lejáratú

Javasolt első baseline:

- 5 perc érvényesség

Lejárat vagy felhasználás után:

- context törlendő vagy invalidnak tekintendő
- új approve/deny nem használhatja fel

## 7. Security rules

### 7.1. Approve integritás

- approve csak hitelesített user sessionnel történhet
- approve csak a szerver által kiadott consent contextre vonatkozhat
- a POST nem írhatja felül a kliens-, redirect-, scope- vagy state-adatot
- authorization code csak a contextből származó validált authorize inputok alapján jöhet létre

### 7.2. Deny integritás

- deny csak érvényes consent context alapján történhet
- refusal redirect csak a korábban validált redirect URI-ra mehet
- `error=access_denied` legyen az alap refusal kód
- `state` csak akkor menjen vissza, ha az eredeti authorize requestből validált érték volt

### 7.3. Redirect és state szabályok

- callback redirect kizárólag előzetesen validált redirect URI-ra épülhet
- approve és deny sem számíthat POST hidden inputban küldött redirect URI-ra
- a success és refusal callback is az eredeti, validált `state` értéket tükrözi vissza
- ha nincs valid redirect kontextus, nincs callback redirect

### 7.4. Expired / invalid context kezelés

Ha a context:

- hiányzik
- lejárt
- másik userhez tartozik
- már felhasznált
- sessionből nem oldható fel

akkor:

- provider-side hiba keletkezik
- nincs callback redirect
- auth code nem jöhet létre
- refusal callback sem mehet ki

## 8. Callback contract

### 8.1. Success callback

Approve után a kliens felé visszamenő callback szerződés:

- `code` kötelező
- `state` kötelező, ha eredetileg szerepelt az authorize requestben

Példa:

```text
GET /auth/sso/callback?code=<authorization_code>&state=<original_state>
```

### 8.2. Refusal callback

Deny után a kliens felé visszamenő callback szerződés:

- `error=access_denied`
- `error_description` opcionális, de ajánlott egy rövid standard érték
- `state` kötelező, ha az eredeti request tartalmazta

Példa:

```text
GET /auth/sso/callback?error=access_denied&error_description=The+user+denied+the+request.&state=<original_state>
```

### 8.3. Kapcsolódás a STORY-07-hez

A consent deny ugyanabba a callback authorize error keretbe illeszkedik, mint a STORY-07 refusal szerződés:

- provider oldalon maradnak a nem biztonságosan redirectelhető hibák
- callbacken csak már valid kliens + valid redirect után keletkező refusal vagy authorize error mehet vissza

Különbség:

- `access_denied` policy refusal esetén provider-originated refusal lehet
- `access_denied` consent deny esetén user-originated refusal

A kliens UX-ben mindkettő refusal típusú authorize callback hiba, de a szerver auditban meg kell különböztetni őket.

## 9. sso_client expected behavior

Approve után:

1. normál callback validáció
2. state ellenőrzés
3. token exchange
4. userinfo
5. lokális session felépítés

Deny után:

- a kliens authorize refusal callbackként kezeli
- rövid, nem technikai üzenetet mutat
- ne generikus belső hibát mutasson
- a user értse, hogy az alkalmazás-hozzáférést utasította el

Provider authorize error callback után:

- továbbra is különüljön el a belső klienshibától
- maradjon összhangban a STORY-07 error contracttal

Consent skip esetén:

- a kliens számára nincs külön szerződésbeli eltérés az approve-success ághoz képest
- a kliens normál success callbacket kap
- a trust-tier bypass ténye nem lehet feltétele a kliens helyes működésének
- ugyanez igaz remembered consent alapú skipre is

## 10. Audit specification

### 10.1. sso_server események

#### `oauth.consent.rendered`

Amikor a consent képernyő sikeresen renderelődik.

Minimum payload:

- `client_id`
- `client_public_id`
- `target_user_id`
- `scope_codes`
- `redirect_uri`
- `consent_token`

#### `oauth.consent.approved`

Amikor a user explicit approve döntést hoz.

Minimum payload:

- `client_id`
- `client_public_id`
- `target_user_id`
- `scope_codes`
- `redirect_uri`
- `consent_token`

#### `oauth.consent.denied`

Amikor a user explicit deny döntést hoz.

Minimum payload:

- `client_id`
- `client_public_id`
- `target_user_id`
- `scope_codes`
- `redirect_uri`
- `consent_token`
- `error` = `access_denied`

#### `oauth.consent.approve_failed`

Approve kérés érkezett, de a döntés nem hajtható végre.

Minimum payload:

- `reason`
- `consent_token`
- `target_user_id` ha feloldható
- `client_public_id` ha feloldható

#### `oauth.consent.context_invalid`

Hiányzó, lejárt, újrahasznált vagy sessionből fel nem oldható context.

Minimum payload:

- `reason`
- `consent_token`
- `target_user_id` ha feloldható

### 10.2. sso_client események

#### `client_auth.authorize_refusal.received`

Amikor a kliens `access_denied` callbacket kap.

Minimum payload:

- `provider_error`
- `provider_error_description`
- `callback_result` = `failure`
- request context mezők

#### `client_auth.authorize_error.received`

Amikor a kliens más authorize callback hibát kap a providertől.

Minimum payload:

- `provider_error`
- `provider_error_description`
- `callback_result` = `failure`
- request context mezők

### 10.3. Mit ne logoljon egyik oldal sem

Nem kerülhet auditba:

- access token
- refresh token
- authorization code nyers értéke
- PKCE verifier
- client secret
- session identifier
- teljes query string érzékeny mezőkkel együtt

## 11. Test plan

### 11.1. sso_server

Kötelező implementációs tesztek:

- valid authorize request -> consent page render
- invalid client -> provider-side validation failure
- invalid redirect URI -> provider-side validation failure
- approve -> authorization code redirect
- deny -> `access_denied` refusal redirect
- expired consent context -> provider-side failure
- missing consent context -> provider-side failure
- reused consent context -> provider-side failure
- POST approve with tampered `client_id` or `redirect_uri` hidden input -> ignored / rejected
- access-restricted client -> refusal callback vagy provider-side refusal a meglévő policy szerint, consent nélkül

### 11.2. sso_client

Kötelező implementációs tesztek:

- approve success callback -> normál session build flow
- deny callback -> külön refusal ág
- refusal üzenet ne essen vissza generikus belső hibára
- provider authorize error callback továbbra is külön ág
- state kezelés approve és deny után is konzisztens

## 12. Ticketing breakdown javaslat

A specifikáció alapján a következő implementációs ticketek választhatók le:

1. `STORY-11` – Consent context preparation on `GET /oauth/authorize`
2. `STORY-12` – Consent page render and public auth surface integration
3. `STORY-13` – `POST /oauth/authorize/approve` implementation
4. `STORY-14` – `POST /oauth/authorize/deny` implementation
5. `STORY-15` – Consent audit events and regression coverage

Trust-tier függő előfeltétel:

- a consent engine implementációja előtt szükséges a `docs/client-trust-tier-spec.md` szerinti explicit trust policy decision layer

Remembered-consent függő előfeltétel:

- a remembered consent evaluation csak a `docs/remembered-consent-spec.md` szerinti identity, TTL és invalidation szabályokkal vezethető be
