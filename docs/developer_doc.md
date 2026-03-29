# SSO fejlesztői kézikönyv

Ez a dokumentum az `sso_server` és az `sso_client` projektekhez készült. A célja nem az, hogy bemutassa a rendszert, hanem hogy egy új fejlesztő 1 nap alatt fel tudja húzni lokálisan, megértse az auth boundary-t, és biztonságosan tudjon feature-t fejleszteni vagy hibát keresni.

## 1. Projekt áttekintés

### Mi a rendszer célja

- Az `sso_server` a központi identity és authorization szolgáltatás.
- Az `sso_client` egy vékony kliensalkalmazás, amely a bejelentkezést az SSO szerverre delegálja.
- A kliens nem lehet identity source: a felhasználói identitás, scope, token és auth döntések forrása mindig a szerver.

### SSO Server vs SSO Client

#### `sso_server`

- felhasználó hitelesítése
- OAuth-szerű authorization code flow kezelése
- access token és refresh token kibocsátása
- redirect URI validáció
- scope ellenőrzés
- token policy enforcement
- token revoke és introspection
- audit log írás minden kritikus auth eseménynél

#### `sso_client`

- redirect indítása az SSO szerver felé
- callback kezelése
- code -> token csere
- userinfo lekérdezése
- lokális Laravel session létrehozása
- védett oldalak kiszolgálása session alapon

### High-level flow

1. A user az `sso_client` felületén az SSO login gombra kattint.
2. A kliens átirányít a szerver `GET /oauth/authorize` végpontjára `client_id`, `redirect_uri`, `state`, `scope`, `code_challenge` paraméterekkel.
3. A user az `sso_server` oldalon bejelentkezik.
4. A szerver siker esetén visszairányít a kliens callback URL-jére `code` és `state` paraméterekkel.
5. A kliens a callbackben ellenőrzi a `state`-et és a PKCE verifier-t, majd meghívja a szerver `POST /api/oauth/token` végpontját.
6. A szerver access tokent és refresh tokent ad vissza.
7. A kliens meghívja a `GET /api/oauth/userinfo` végpontot, majd a kapott email alapján lokális usert létrehoz vagy bejelentkeztet.
8. Ettől kezdve a kliens saját sessionnel működik, de az auth forrás továbbra is az `sso_server`.

## 2. Architektúra

### Backend (`sso_server`)

#### Rétegezés

Az alap minta:

`Controller -> Service -> Repository`

- Controller: request fogadása, FormRequest validáció, service hívás, response
- Service: üzleti logika, tranzakciók, security döntések
- Repository: query, filter, pagination, relation loading
- Policy: authorization
- Data / Resource jellegű objektumok: frontend és API payload formázás

#### Mire figyelj kódszinten

- Ne tegyél business logikát Controllerbe.
- Ne tegyél query-heavy logikát Controllerbe.
- Validáció mindig `FormRequest` osztályban legyen.
- Jogosultság Policy-n menjen.
- Biztonsági és token logika Service-ben legyen.

#### Tipikus szerver oldali példák

- OAuth authorization code kiadás: `app/Services/OAuth/OAuthAuthorizationService.php`
- Token exchange / refresh / revoke / introspect / userinfo: `app/Services/OAuth/OAuthTokenService.php`
- Redirect URI strict match: `app/Services/OAuth/RedirectUriMatcher.php`
- PKCE verification: `app/Services/OAuth/PkceVerifier.php`
- Client business logika: `app/Services/ClientService.php`
- Client query és admin lista: `app/Repositories/ClientRepository.php`

#### FormRequest validáció

Jellemző helyek:

- `app/Http/Requests/OAuth/*`
- `app/Http/Requests/Admin/*`

Itt történik:

- input whitelist
- URI validáció
- enum / boolean / pagination / sort mezők ellenőrzése
- admin CRUD inputok ellenőrzése

#### Policy alapú authorization

Jellemző policy-k:

- `app/Policies/ClientPolicy.php`
- `app/Policies/ScopePolicy.php`
- `app/Policies/TokenPolicyPolicy.php`
- `app/Policies/AuditLogPolicy.php`

Alapszabály:

- minden védett admin resource policy alapon működjön
- bulk művelethez külön permission kell, pl. `resource.deleteAny`

#### Token kezelés

Az aktuális implementáció támogatja:

- authorization code -> token csere
- access token kibocsátás
- refresh token kibocsátás
- refresh flow
- token revoke
- token introspection
- userinfo endpoint

Megvalósítási részletek:

- a plain token nincs eltárolva, csak a hash
- access token és refresh token külön revocable
- TTL a `token_policies` alapján dől el
- refresh token rotáció policy-függő
- authorization code egyszer használható

#### Audit log rendszer

- audit események Spatie Activitylogon keresztül mennek
- a koncepcionális `audit_logs` felelősséget jelenleg az `activity_log` tábla és a hozzá kapcsolódó eventek fedik le
- auth, token, client secret, admin műveletek logolva vannak
- secret vagy token plain text nem kerülhet logba

### Client (`sso_client`)

#### Thin client szerep

Az `sso_client` nem implementál saját identity rendszert. A feladata:

- authorize redirect indítása
- callback validálása
- token exchange
- userinfo lekérés
- lokális session felépítése
- downstream UI / API oldalak védése

Kulcs implementáció:

- `app/Services/Sso/SsoClientService.php`

#### Auth flow a kliensben

- `GET /auth/sso/redirect` felépíti az authorize URL-t
- sessionbe elmenti a `state` és `pkce_verifier` értékeket
- callbackben ellenőrzi az `error`, `code`, `state`, `pkce_verifier` mezőket
- a token endpoint válaszból csak a `data.access_token` mezőt olvassa
- a userinfo válaszból csak a `data` envelope-ból dolgozik
- az email alapján lokális usert keres vagy hoz létre
- siker esetén `Auth::login()` + session regenerate

#### API hívások

- auth flow közben a kliens a szervert HTTP-n hívja
- self-service profile esetén a kliens közvetlenül a szerver `/api/profile*` végpontjait használja
- a kliens oldali validáció csak UX célú, a végső validáció a szerveren történik

## 3. Adatbázis struktúra

### Fő táblák és szerepük

#### `sso_clients`

- regisztrált kliensek
- fő mezők: `name`, `client_id`, `is_active`, `token_policy_id`
- kapcsolatok:
  - 1:N `redirect_uris`
  - N:M `scopes` a `client_scopes` pivoton át
  - 1:N `client_secrets`
  - N:1 `token_policies`
  - 1:N `authorization_codes`
  - 1:N `tokens`

Megjegyzés:

- a régebbi `client_secret_hash`, `redirect_uris`, `scopes` mezők kompatibilitási okból még jelen lehetnek a modellen, de az aktív normalizált tárolás a külön táblákban van

#### `redirect_uris`

- klienshez tartozó engedélyezett callback URI-k
- strict matchinghez használjuk
- `uri_hash` biztosítja az egyedi tárolást kliensen belül

#### `scopes`

- engedélyezhető scope katalógus
- tipikus példák: `openid`, `profile`, `email`
- a kliens csak az itt létező és hozzá rendelt scope-okat kérheti

#### `client_scopes`

- pivot tábla `sso_clients` és `scopes` között
- meghatározza, hogy egy kliens mely scope-okat kérheti

#### `client_secrets`

- kliens secret lifecycle tábla
- csak hash kerül tárolásra
- tartalmazhat több secretet is egy klienshez
- támogatja a rotate és revoke műveleteket
- a plain secret csak létrehozáskor vagy forgatáskor jelenhet meg egyszer

#### `token_policies`

- token TTL és security policy forrás
- fő mezők:
  - `access_token_ttl_minutes`
  - `refresh_token_ttl_minutes`
  - `refresh_token_rotation_enabled`
  - `pkce_required`
  - `reuse_refresh_token_forbidden`
  - `is_default`
  - `is_active`

#### `tokens`

- kiadott token párok életciklusának tárolása
- access és refresh token hash-eket tartalmaz
- lejárat, revoke, last used, parent-child refresh lánc is tárolódik

#### `audit_logs`

- koncepcionálisan az auth és admin események naplója
- jelenlegi implementációban ezt a Spatie `activity_log` tábla adja
- fontos eventek: login, token issue, token refresh, token revoke, client create/update/delete, secret rotate/revoke

### Kapcsolatok röviden

- Egy `sso_client` több `redirect_uri` rekorddal rendelkezik.
- Egy `sso_client` több `scope`-hoz kapcsolódhat a `client_scopes` pivoton keresztül.
- Egy `sso_client` több `client_secret` rekorddal rendelkezhet.
- Egy `sso_client` opcionálisan egy `token_policy` rekordhoz kötődik.
- Az `authorization_codes` és `tokens` rekordok mindig klienshez és userhez kötöttek.

## 4. Auth flow részletesen

### 1. Client -> `/oauth/authorize`

A kliens a következőket küldi:

```http
GET /oauth/authorize?response_type=code&client_id=portal-client&redirect_uri=http://sso-client.test/auth/sso/callback&scope=openid%20profile%20email&state=...&code_challenge=...&code_challenge_method=S256
```

Szerver oldali ellenőrzések:

- a kliens létezik és aktív
- a `redirect_uri` pontosan szerepel a kliens engedélyezett URI-i között
- a kért scope-ok a klienshez vannak rendelve
- ha a token policy előírja, a PKCE kötelező

### 2. User login

- ha a user nincs bejelentkezve a szerveren, a szerver login oldalra visz
- sikeres login után a szerver authorization code-ot hoz létre
- a code 10 percig él, egyszer használható, hash-elve tárolódik

### 3. Redirect back

A szerver redirectel a kliens `redirect_uri` címére:

```http
GET /auth/sso/callback?code=...&state=...
```

A kliens callbackben ellenőrzi:

- `error` mező van-e
- `code` jelen van-e
- `state` jelen van-e
- a `state` megegyezik-e a sessionben tárolttal
- a `pkce_verifier` megvan-e a sessionben

Ha bármelyik sérül, nem jön létre lokális session.

### 4. Code -> token csere

Kliens kérés:

```http
POST /api/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
client_id=portal-client
client_secret=...
redirect_uri=http://sso-client.test/auth/sso/callback
code=...
code_verifier=...
```

Szerver oldali ellenőrzések:

- kliens auth rendben van-e
- code létezik-e
- code még nem használt / nem revoked / nem expired
- code a klienshez tartozik-e
- `redirect_uri` egyezik-e az authorize fázissal
- PKCE verifier helyes-e

Sikeres válasz envelope:

```json
{
  "message": "OAuth token issued successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "refresh_token_expires_in": 86400,
    "scope": "openid profile email"
  },
  "meta": {},
  "errors": {}
}
```

### 5. Token használat

- a kliens a `Bearer access_token` értékkel meghívja a `/api/oauth/userinfo` végpontot
- a szerver csak aktív, nem revoked, nem expired access tokennel szolgál ki
- `openid` scope nélkül userinfo nem adható
- `profile` és `email` scope dönti el, mely claim-ek kerülnek vissza

### Redirect URI validáció

Kritikus szabály:

- nincs wildcard
- nincs prefix match
- nincs loose compare
- csak exact string match van

Ez a gyakorlatban a `RedirectUriMatcher` miatt jelenleg pontos `in_array(..., true)` egyezés.

### PKCE

- a kliens minden authorize redirectnél generál `code_verifier` értéket
- ebből készül `S256` challenge
- a szerver a token exchange során visszaellenőrzi
- ha a policy `pkce_required=true`, PKCE nélkül a flow elutasításra kerül

### Token TTL

- az access token TTL és a refresh token TTL a `token_policies` alapján számolódik
- egy kliensnek lehet saját policy-je
- ha nincs saját policy, az aktív default policy lép életbe

### Refresh flow

Kliens vagy más integráció használhatja a refresh grantet:

```http
POST /api/oauth/token
grant_type=refresh_token
client_id=...
client_secret=...
refresh_token=...
```

Szerver oldali viselkedés:

- refresh token ellenőrzése
- lejárat / revoke állapot ellenőrzése
- policy alapján refresh token rotáció
- új token pár kiadása

## 5. Lokális fejlesztési környezet

### Követelmények

- PHP: minimum `8.3`, ajánlott `8.4` (a CI is használ 8.4-et a szerveren)
- Node.js: ajánlott `22`
- Composer: `v2`
- npm: a lock file-ok miatt `npm ci` is használható
- adatbázis:
  - lokális fejlesztéshez elsősorban MySQL
  - tesztekhez és E2E-hez SQLite is használatban van

### Könyvtárstruktúra

Feltételezett lokális elrendezés:

```text
../sso_server
../sso_client
```

Az E2E és a cross-repo workflow is erre az együttélésre épít.

### Server setup (`sso_server`)

```bash
cd ../sso_server
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Ajánlott `.env` kiindulópontok:

```env
APP_NAME="SSO Server"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_DATABASE=sso_server
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
ACTIVITY_LOGGER_ENABLED=true
```

### Client setup (`sso_client`)

```bash
cd ../sso_client
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Ajánlott kliens `.env` mag:

```env
APP_NAME="SSO Client"
APP_URL=http://127.0.0.1:8001
DB_CONNECTION=mysql
DB_DATABASE=sso_client
DB_USERNAME=root
DB_PASSWORD=

SSO_SERVER_BASE_URL=http://127.0.0.1:8000
SSO_AUTHORIZE_ENDPOINT=/oauth/authorize
SSO_TOKEN_ENDPOINT=/api/oauth/token
SSO_USERINFO_ENDPOINT=/api/oauth/userinfo
SSO_CLIENT_ID=portal-client
SSO_CLIENT_SECRET=
SSO_REDIRECT_URI="${APP_URL}/auth/sso/callback"
SSO_SCOPES="openid profile email"
SSO_LOCAL_AUTH_ENABLED=false
```

### Lokális első indítás ellenőrzőlista

1. A szerver migrációi és seederei lefutottak.
2. Van legalább egy admin user a szerverben.
3. Van létező `sso_client` rekord a szerverben a kliens `SSO_CLIENT_ID` értékével.
4. A kliens `SSO_REDIRECT_URI` pontosan szerepel a szerver kliens redirect URI listájában.
5. A kliensnek engedélyezve vannak legalább az `openid` és `email` scope-ok.

## 6. E2E és tesztelés

### Playwright E2E auth flow

A browser auth E2E a `sso_client` repositoryban fut:

```bash
npm run e2e:auth
```

Mit tesztel:

- teljes browser redirect flow client -> server -> client
- authorize query paraméterek
- szerver oldali login
- callback és session felépítése
- reload utáni session megmaradása
- logout és guest mód
- protected route újra-auth
- invalid `state`
- missing `code`

Hasznos parancsok:

```bash
npm run e2e:auth:headed
npm run e2e:auth:prepare
```

Tipikus hibák:

- nincs meg a szomszédos `sso_server` repo
- hibás `SSO_E2E_SERVER_PATH`
- nincs telepítve Playwright Chromium
- a kliens secret nincs szinkronban az E2E setup után
- portütközés a `127.0.0.1:8010` vagy `127.0.0.1:8020` címeken

### Backend tesztek

Szerver:

```bash
php artisan test
composer test:security
```

Kliens:

```bash
php artisan test
composer test:security
```

### Frontend tesztek

Szerver:

```bash
npm run test
```

Kliens:

```bash
npm run test
npm run test:security
```

### Mit nézz először teszt hiba esetén

- melyik repo bukott el: `sso_server` vagy `sso_client`
- API envelope változott-e
- route név vagy URL változott-e
- scope / redirect URI / client secret drift van-e
- session vagy cookie driver változott-e

## 7. CI/CD (GitHub Actions)

### Browser Auth E2E workflow

A browser auth E2E workflow a `sso_client` repóban van:

- `.github/workflows/e2e-auth.yml`

Mit csinál:

1. checkoutolja a `sso_client` repót
2. checkoutolja a `sso_server` repót is
3. telepíti a PHP és Node függőségeket mindkét oldalon
4. telepíti a Playwright Chromiumot
5. lefuttatja az `npm run e2e:auth` parancsot a kliens oldalon
6. artifactként feltölti a reportokat és logokat

### Cross-repo checkout

A workflow a kliensből checkoutolja a szervert:

- tokennel: ha van `SSO_CROSS_REPO_TOKEN`
- token nélkül: ha publikus elérés elegendő

Szükséges secret:

- `SSO_CROSS_REPO_TOKEN`

### Artifactok

Fontos artifactok:

- `playwright-report`
- `client-logs`
- `server-logs`
- `e2e-runtime`

Megjegyzés:

- jelen állapot szerint külön `Laravel logs` artifact néven nincs feltöltés, hanem `client-logs` és `server-logs` néven mennek fel

### Szerver oldali security workflow

Az `sso_server` repóban külön security regression workflow fut:

- `.github/workflows/security-regression.yml`

Ez a `composer test:security` parancsot futtatja.

## 8. Biztonsági szabályok

Ez a projekt security-critical. Az alábbi szabályok nem ajánlások, hanem alapkövetelmények.

- Soha ne tárolj plain secretet.
- A kliens secret csak hash formában tárolható.
- A plain secret csak egyszer jelenhet meg létrehozáskor vagy rotate után.
- Redirect URI validáció csak strict exact match lehet.
- Token revoke kötelezően támogatott.
- Access token és refresh token naplózás plain értékkel tilos.
- Audit log kell minden fontos auth és token eseményről.
- A kliens nem lehet identity source.
- A userinfo és a profile API csak a szerver által engedélyezett mezőket adhatja vissza.
- Frontenden elrejtett gomb nem elég: a backendnek is védenie kell a műveletet.

## 9. Gyakori hibák és megoldások

### `Missing tenant context`

- Ez jelenleg nem az `sso_server` vagy az `sso_client` core flow standard hibája.
- Ha ilyen hiba egy downstream alkalmazásban jelenik meg, az jellemzően már a kliens fölötti üzleti réteg vagy multi-tenant boundary problémája.
- Ellenőrizd:
  - a callback után létrejött-e lokális session
  - a tenant kiválasztás sessionben vagy request contextben elérhető-e
  - nem veszett-e el session regenerate vagy domain mismatch miatt

### `No company selected`

- Ez szintén tipikusan downstream domain hiba, nem az alap SSO flow része.
- Ellenőrizd:
  - a bejelentkezett userhez van-e cég hozzárendelés
  - a kliens alkalmazás sessionje tartalmazza-e a kiválasztott company contextet
  - protected route guard után a szükséges kiválasztási lépés megtörtént-e

### `CSRF token mismatch`

Tipikus okok:

- eltérő `APP_URL`
- hibás session domain vagy same-site beállítás
- lejárt session
- kevert hostnevek, pl. `localhost` és `127.0.0.1`

Ellenőrzés:

- mindkét app ugyanazzal a host-stílussal fusson
- nézd meg a cookie-kat a böngészőben
- nézd meg, hogy a session driver és tábla rendben van-e

### `401` -> újra auth

Mit jelent:

- nincs érvényes kliens session
- vagy az upstream API access token már nem használható

Teendő:

- a kliens tervezetten visszavisz login / re-auth flow-ba
- ellenőrizd a callback sessiont, a token exchange-t és a userinfo választ

### `403` -> permission

Mit jelent:

- a user hitelesített, de nincs meg a szükséges policy / permission

Teendő:

- ellenőrizd a szerver oldali permission seedereket
- ellenőrizd a policy metódust
- nézd meg, hogy a permission név megfelel-e a `resource.action` szabványnak

## 10. Fejlesztési szabályok

### Backend

- Controller maradjon vékony.
- Service tartalmazza a business logicot.
- Repository csak query és filter legyen.
- Validáció mindig FormRequestben legyen.
- Authorization mindig Policy-n menjen.
- Response maradjon a közös envelope formátumban.
- Secretet, tokent, debug információt ne szivárogtass response-ban.

### Frontend

- PrimeVue only
- admin listákhoz DataTable standard
- destruktív művelethez ConfirmDialog kötelező
- visszajelzéshez Toast kötelező
- komplex admin formokat oldalas create/edit flow-ban tartsd
- ne duplikáld az SSO logikát a kliensben

## 11. Új feature fejlesztés folyamata

Ajánlott sorrend:

1. Migration
2. Model
3. Repository
4. Service
5. Controller
6. Policy
7. API / response contract
8. Vue oldal vagy admin UI
9. Teszt

### Gyakorlati checklist

1. Írd meg vagy bővítsd a migrációt.
2. Hozd létre a modell relationjeit és castjait.
3. Tedd a query logikát Repositoryba.
4. Tedd az üzleti szabályt Service-be.
5. Tartsd a Controllert 1-2 service hívás körül.
6. Írd meg a Policy szabályt és permission mappinget.
7. Tartsd meg az egységes JSON envelope-ot vagy Inertia payload formát.
8. Frontenden használd a meglévő shared komponenseket.
9. Írj backend és frontend tesztet.
10. Futtasd le a releváns suite-okat még merge előtt.

### Ha auth vagy security érintett

- mindig nézd meg az `OAuth*` teszteket
- mindig adj regression tesztet
- ellenőrizd, hogy a flow nem töri-e az integration contractot

## 12. Debug guide

### Laravel logok

Szerver:

- `sso_server/storage/logs/laravel.log`

Kliens:

- `sso_client/storage/logs/laravel.log`

Mit keress:

- token grant failed
- client authentication failed
- authorization denied
- userinfo hibák
- session és auth kivételek

### Playwright report

Helye tipikusan:

- `sso_client/playwright-report/`

Használat:

- nézd meg, melyik lépésnél tört meg a redirect
- ellenőrizd az authorize request query paramétereit
- nézd meg, visszajött-e a callback `code` és `state`

### Network trace

Ellenőrizd sorrendben:

1. `GET /auth/sso/redirect`
2. `GET /oauth/authorize`
3. `POST /login` a szerveren
4. `GET /auth/sso/callback`
5. `POST /api/oauth/token`
6. `GET /api/oauth/userinfo`

Ha itt bármi envelope vagy status eltérést látsz, az általában gyorsan megmutatja a hibát.

### Session / cookie problémák

Leggyakoribb okok:

- eltérő hostnév
- session domain mismatch
- same-site probléma
- session regenerate után elvesző state
- callback előtt vagy után törlődő session

Gyors ellenőrzés:

- van-e `state` a sessionben redirect után
- megmarad-e a `pkce_verifier`
- létrejön-e kliens session cookie a callback után
- logout után lecserélődik-e a session cookie

## Napi fejlesztői rövidlista

Ha gyorsan produktív akarsz lenni, ezt kövesd:

1. Húzd fel mindkét repót lokálisan.
2. Ellenőrizd, hogy a kliens `portal-client` rekordja létezik a szerveren.
3. Futtasd le az auth flow-t kézzel böngészőből.
4. Futtasd le az `npm run e2e:auth` parancsot a kliensben.
5. Új feature előtt keresd meg a meglévő Service, Repository, Policy és teszt mintát.
6. Auth boundary módosítás előtt mindig ellenőrizd a `docs/integration-contract.md` fájlt mindkét oldalon.

## Fontos fájlok

### Szerver

- `routes/web.php`
- `routes/api.php`
- `app/Services/OAuth/OAuthAuthorizationService.php`
- `app/Services/OAuth/OAuthTokenService.php`
- `app/Services/ClientService.php`
- `app/Repositories/ClientRepository.php`
- `tests/Feature/OAuth/*`
- `docs/integration-contract.md`

### Kliens

- `routes/auth.php`
- `app/Services/Sso/SsoClientService.php`
- `tests/Feature/Auth/SsoAuthenticationTest.php`
- `tests/e2e/auth-flow.spec.ts`
- `.github/workflows/e2e-auth.yml`
- `docs/integration-contract.md`

