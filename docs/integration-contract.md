# SSO Server <-> SSO Client integrációs szerződés

## Hatókör

Ez a dokumentum az explicit, tesztekkel védett integrációs szerződést írja le az alábbiak között:

- `sso_server`
- `sso_client`

Csak a jelenlegi, ténylegesen implementált működést írja le.

## 1. Authorize request szerződés

A kliens a bejelentkezést ide irányítással indítja:

- `GET {SSO_SERVER_BASE_URL}{SSO_AUTHORIZE_ENDPOINT}`
- alapértelmezés szerint: `GET /oauth/authorize`

Az `sso_client` által küldött kötelező query paraméterek:

- `response_type=code`
- `client_id`
- `redirect_uri`
- `scope` szóközzel elválasztva, `SSO_SCOPES` alapján
- `state` véletlen 64 karakter, sessionben tárolva
- `code_challenge`
- `code_challenge_method=S256`

Szerver oldali viselkedés:

- érvényes kérés és hitelesített user esetén `302` redirect a `redirect_uri` címre `code` és visszaechozott `state` paraméterrel
- érvénytelen kliens / redirect / scope esetén a jelenlegi szerver oldali működés validation hiba az authorize route-on (`302` session validation hibákkal), nem callback redirect

## 2. Callback szerződés (`sso_client`)

A kliens callback végpontja:

- `GET /auth/sso/callback`

A kliens elvárása:

- sikeres ág: `code` és `state`
- hibaág: opcionális OAuth-stílusú `error`

A kliens oldali validáció szabályai:

- hiányzó `code` -> hiba
- hiányzó `state` -> hiba
- eltérő `state` vagy hiányzó várt session state -> hiba
- hiányzó PKCE verifier a sessionből -> hiba
- callback queryben jelen lévő `error` -> hiba

## 3. Token response szerződés

Token végpont:

- `POST {SSO_SERVER_BASE_URL}{SSO_TOKEN_ENDPOINT}`
- alapértelmezés szerint: `POST /api/oauth/token`

A kliens által használt grant:

- `grant_type=authorization_code`
- `client_id`
- `client_secret` ha konfigurálva van
- `redirect_uri`
- `code`
- `code_verifier`

Sikeres válasz hiteles formátuma:

```json
{
  "message": "OAuth token issued successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "refresh_token_expires_in": 86400,
    "scope": "openid profile email",
    "id_token": "eyJ..."
  },
  "meta": {},
  "errors": {}
}
```

Szerződés szabály:

- az `id_token` csak akkor jelenik meg, ha a kiadott scope-ok között szerepel az `openid`
- az `id_token` RS256 algoritmussal, aszimmetrikusan alairt JWT
- a JWT header legalabb ezeket tartalmazza: `alg`, `typ`, `kid`
- az `id_token` minimális claim készlete: `iss`, `sub`, `aud`, `iat`, `exp`
- a `nonce` claim csak akkor kerül bele, ha az authorize/code flow hordozott nonce-ot
- az `sso_client` továbbra is kizárólag a `data` envelope-ból dolgozik, nincs top-level fallback

JWKS endpoint:

- `GET /.well-known/jwks.json`
- szabvanyos JWK Set valaszt ad: `{"keys":[...]}`
- az aktiv publikus alairasi kulcs legalabb ezeket tartalmazza: `kty`, `kid`, `use`, `alg`, `n`, `e`
- private key anyag soha nem jelenhet meg a valaszban

OpenID Provider discovery endpoint:

- `GET /.well-known/openid-configuration`
- szabvanyos OpenID Provider Metadata dokumentumot ad
- jelenleg csak a tenylegesen implementalt baseline-t publikálja:
  - `issuer`
  - `authorization_endpoint`
  - `token_endpoint`
  - `userinfo_endpoint`
  - `jwks_uri`
  - `response_types_supported`
- `grant_types_supported`
- `subject_types_supported`
- `id_token_signing_alg_values_supported`
- `scopes_supported`
- `code_challenge_methods_supported`
- `claims_supported`
- `frontchannel_logout_supported`
- `end_session_endpoint`
- a `claims_supported` jelenleg pontosan ezeket a claim-eket publikalja: `sub`, `name`, `email`, `email_verified`
- tudatosan nincs benne peldaul:
  - `registration_endpoint`
- a metadata URL-jei az `issuer` baseline-hoz igazodnak
- a `jwks_uri` a mar mukodo `/.well-known/jwks.json` vegpontra mutat
- a `userinfo_endpoint` a mar mukodo bearer tokennel vedett `/api/oauth/userinfo` vegpontra mutat
- az `end_session_endpoint` a mar mukodo `GET /oidc/logout` provider logout vegpontra mutat
- a discovery `claims_supported` mar a kozponti OIDC claim policybol epul
- a `frontchannel_logout_supported` azt jelzi, hogy a provider oldalon elerheto a front-channel logout foundation

Hibás válasz formátuma:

```json
{
  "message": "OAuth token request failed.",
  "data": {},
  "meta": {},
  "errors": {
    "field": ["reason"]
  }
}
```

## 4. UserInfo response szerződés

Userinfo végpont:

- `GET {SSO_SERVER_BASE_URL}{SSO_USERINFO_ENDPOINT}`
- alapértelmezés szerint: `GET /api/oauth/userinfo`
- authorizáció: `Bearer {access_token}`

Sikeres válasz:

```json
{
  "message": "User info retrieved successfully.",
  "data": {
    "sub": "123",
    "name": "Example User",
    "email": "user@example.test",
    "email_verified": true
  },
  "meta": {},
  "errors": {}
}
```

Claim szerződés:

- `openid` scope eseten garantalt: `data.sub`
- `profile` scope eseten opcionális: `data.name`
- `email` scope eseten opcionális: `data.email`, `data.email_verified`
- a `data.sub` ugyanazt a stabil identity subjectet használja, mint az `id_token.sub`
- a scope -> claim mapping kozponti OIDC claim policyban van rogzitve, nem kulon userinfo-specifikus stringlistakban

ID token claim szerződés:

- az `id_token` minimalis marad
- garantalt protokoll es identity baseline: `iss`, `sub`, `aud`, `iat`, `exp`
- `nonce` csak akkor szerepel benne, ha a flow-ban volt nonce
- `name`, `email`, `email_verified` claim-ek nem kerulnek automatikusan az `id_token`-ba
- a reszletesebb identity claim-ek elsodleges helye tovabbra is a `userinfo`

Kliens oldali szerződés:

- a userinfo választ csak a `data` mezőből olvassa
- a kliens a `userinfo.sub` erteket kontrollaltan osszevetheti az `id_token.sub` claimmel
- a lokális user session felépítéséhez jelenleg szükséges a `data.email`

## 5. Logout szerződés

Provider logout végpont:

- `GET /oidc/logout`
- discoveryben: `end_session_endpoint`

Fogadott paraméterek:

- `id_token_hint` opcionális
- `post_logout_redirect_uri` opcionális
- `state` opcionális

Validációs szabály:

- `post_logout_redirect_uri` csak akkor fogadhato el, ha a szerver megbizhato klienskontextust tud feloldani a sajat alairasú `id_token_hint`-bol
- a redirect celjanak pontosan egyeznie kell az adott kliens egyik regisztralt redirect URI-javal
- ervenytelen vagy nem ellenorizheto redirect soha nem okozhat open redirectet

Logout viselkedés:

- a provider session tenylegesen lezarul
- valid `post_logout_redirect_uri` eseten a szerver oda redirectel
- ha `state` erkezett es a redirect ervenyes, a szerver visszaechozza a redirect URL queryjeben
- ha nincs ervenyes redirect, a szerver a sajat login oldalra ter vissza status uzenettel

Front-channel logout foundation:

- a provider session alatt a szerver explicit RP participation listat tarol
- a regisztracio csak sikeres authorization code kiadas utan tortenik meg
- kliensenkent a tarolt minimum adatok:
  - `client_id`
  - `frontchannel_logout_uri`
- logoutkor a szerver a resztvevo RP-khez front-channel logout cel URL-eket epit
- ha van ilyen cel, a provider egy relay oldalt ad vissza, amely betolti ezeket a front-channel logout URL-eket, majd tovabblep a vegso redirect vagy fallback oldal fele
- a kliens fele a jelenlegi foundation ezeket a query parametereket kuldi:
  - `iss`
  - `client_id`

Tudatosan nincs benne meg:

- back-channel logout
- global multi-client session kill
- teljes garantalt distributed single logout

## 6. Self-service profile szerződés

Profile végpontok:

- `GET /api/profile`
- `PATCH /api/profile`
- `PATCH /api/profile/password`

Authentikációs modell:

- a böngésző közvetlenül a szerver profile végpontjait hívja
- a szerver session marad a hitelesítés forrása
- a kliens credentialdel küldi a kéréseket, és csak az explicit JSON envelope-ot használja

Szerkeszthető mezők:

- `name`

Csak olvasható mezők:

- `email`
- `emailVerifiedAt`

Self-service-en keresztül tiltott mezők:

- `roles`
- `permissions`
- `email_verified_at`
- admin / status / security mezők
- minden olyan váratlan mező, amely nincs rajta az explicit whitelistán

Sikeres profile lekérés:

```json
{
  "message": "Profile retrieved successfully.",
  "data": {
    "id": 123,
    "name": "Example User",
    "email": "user@example.test",
    "emailVerifiedAt": "2026-03-27T15:00:00+00:00"
  },
  "meta": {
    "editable_fields": ["name"],
    "read_only_fields": ["email", "emailVerifiedAt"],
    "csrf_token": "..."
  },
  "errors": {}
}
```

Sikeres jelszófrissítés:

```json
{
  "message": "Password updated successfully.",
  "data": {},
  "meta": {
    "editable_fields": ["name"],
    "read_only_fields": ["email", "emailVerifiedAt"],
    "csrf_token": "..."
  },
  "errors": {}
}
```

Validation hiba esetén a formátum marad a standard envelope:

```json
{
  "message": "Validation failed.",
  "data": [],
  "meta": [],
  "errors": {
    "field": ["reason"]
  }
}
```

## 7. Session/Auth state szerződés (kliens)

Lokális hitelesített állapot csak az alábbiak után jön létre:

1. érvényes callback validáció
2. sikeres token exchange
3. sikeres userinfo lekérés használható `email` mezővel
4. lokális user feloldás email alapján
5. Laravel web login + session regenerate

A kliens guest állapotba kerül, ha:

- lokális logout történik, vagy
- a user védett route-ot ér el érvényes session nélkül (`401` JSON API jellegű kérésnél, login redirect böngészőnél)

## 8. Hiba szerződés mátrix

| Eset | Szerver státusz/body | Transport | Kliens viselkedés |
|---|---|---|---|
| invalid client (authorize) | 302 + validation session hibák (`client_id`) | szerver oldali redirect | nem callback-alapú, a user a szerver flow-ban marad |
| inactive client (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik, a kliens login hibát ad |
| redirect mismatch (authorize/token) | 302 validation (authorize) / 422 JSON (token) | redirect vagy JSON | a token fázis elbukik |
| disallowed scope (authorize) | 302 + validation session hibák (`scope`) | szerver oldali redirect | nem callback-alapú |
| missing state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| invalid state (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| missing code (callback) | n/a, kliens oldali callback validáció | query a kliens callbackre | a kliens elutasítja |
| invalid/expired/reused code (token) | 422 JSON envelope `errors.code` mezővel | JSON | a kliens elutasítja a token exchange-et |
| token endpoint failure/network | n/a | transport hiba | a kliens elutasítja a token exchange-et |
| userinfo unauthorized | 401 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| userinfo forbidden | 403 JSON envelope | JSON | a kliens elutasítja a userinfo fázist |
| forbidden self-service profile field | 422 JSON envelope mezőszintű hibákkal | JSON | a kliens mező- vagy domain hibákat jelenít meg |
| unauthorized protected route (client app) | 302 login redirect (HTML) / 401 JSON (`reauth_to`) | redirect vagy JSON | explicit re-auth viselkedés |

## 9. Konfigurációs szerződés

A kliens konfigurációjának tartalmaznia kell:

- `SSO_SERVER_BASE_URL`
- `SSO_AUTHORIZE_ENDPOINT`
- `SSO_TOKEN_ENDPOINT`
- `SSO_USERINFO_ENDPOINT`
- `SSO_CLIENT_ID`
- `SSO_CLIENT_SECRET` ha confidential client auth szükséges
- `SSO_REDIRECT_URI`
- `SSO_SCOPES`, és kötelezően tartalmaznia kell az `email` scope-ot, mert a kliens session mappinghez szükséges a `userinfo.email`

A szerver konfigurációjának / adatainak ehhez igazodnia kell:

- létezik OAuth kliens ugyanazzal a `client_id` értékkel
- az engedélyezett `redirect_uri` pontosan tartalmazza a `SSO_REDIRECT_URI` értéket
- az engedélyezett scope-ok tartalmazzák a kliens által kért scope-okat
- a token policy PKCE beállításai kompatibilisek a kliens kérésével
- a `CORS_ALLOWED_ORIGINS` tartalmazza a pontos `sso_client` browser origint a közvetlen self-service profile hívásokhoz

## 10. Szerződést védő tesztlefedettség

Szerver:

- `tests/Feature/OAuth/OAuthAuthorizationCodeFlowTest.php`
- `tests/Feature/OAuth/OAuthUserInfoTest.php`
- `tests/Feature/Api/SelfServiceProfileApiTest.php`

Kliens:

- `tests/Feature/Auth/SsoAuthenticationTest.php`
- `tests/Feature/ProfileTest.php`

Ezek a tesztek adják ennek a szerződésnek a regressziós védelmét.
