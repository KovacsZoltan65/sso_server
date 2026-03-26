# Postman OAuth Használati Útmutató

Ez a mappa a jelenlegi SSO Server OAuth flow kézi teszteléséhez szükséges Postman fájlokat tartalmazza.

Elérhető fájlok:

- `postman/sso_server.json`
- `postman/environment.json`

A collection a jelenlegi implementált endpointokra épül:

- `GET /oauth/authorize`
- `POST /api/oauth/token`
- `POST /api/oauth/revoke`
- `POST /api/oauth/introspect`
- `GET /api/oauth/userinfo` (opcionális, mert jelenleg már implementálva van)

## Előfeltételek

A használathoz szükséges:

- futó Laravel alkalmazás
- működő adatbázis
- létező OAuth kliens
- ismert `client_id`
- ismert `client_secret`
- a klienshez regisztrált `redirect_uri`
- egy olyan felhasználó, akivel böngészőben be lehet jelentkezni

## Importálás

1. Nyisd meg a Postmant.
2. Importáld a `postman/sso_server.json` collection fájlt.
3. Importáld a `postman/environment.json` environment fájlt.
4. Válaszd ki az environmentet a Postman jobb felső sarkában.

## Fontos megjegyzés az authorize lépéshez

A `GET /oauth/authorize` végpont a jelenlegi flow alapján böngészős sessiont és login állapotot igényelhet.

Ez azt jelenti, hogy a teljes Authorization Code + PKCE flow sok esetben nem fut végig tisztán csak Postmanből.

Ajánlott gyakorlat:

1. Postmanben generáld le a PKCE értékeket.
2. A collection authorize requestjéből másold ki vagy nyisd meg az authorize URL-t böngészőben.
3. Jelentkezz be a Laravel alkalmazásba.
4. A redirectelt callback URL-ből másold ki a `code` query paraméter értékét.
5. Írd be ezt a Postman environment `authorization_code` változójába.
6. A token cserét már Postmanből futtasd.

Ez nem hiba, hanem a flow természetes része.

## Environment változók

A collection ezeket a változókat használja:

- `base_url`
- `client_id`
- `client_secret`
- `redirect_uri`
- `scope`
- `auth_user_email`
- `code_verifier`
- `code_challenge`
- `authorization_code`
- `access_token`
- `refresh_token`
- `introspection_active`
- `last_location`

Állítsd be legalább ezeket indulás előtt:

- `base_url`
- `client_id`
- `client_secret`
- `redirect_uri`
- `scope`

Megjegyzések:

- a `base_url` ne tartalmazzon `/api` utótagot
- a `redirect_uri` pontosan egyezzen a klienshez regisztrált URI-val
- a `scope` tipikusan lehet például `openid profile email`

## Collection felépítése

### 1. OAuth Setup

- `Generate PKCE Values`
- `Open Authorize URL Helper`

Itt történik a `code_verifier` és `code_challenge` generálása.

### 2. Authorization Code Flow

- `Authorization Request`
- `Token Exchange`

Itt készül el az authorization code, majd a token csere.

### 3. Refresh Flow

- `Refresh Token Exchange`

Itt tesztelhető a refresh token alapú új access token kiadás.

### 4. Token Management

- `Introspect Access Token`
- `Introspect Refresh Token`
- `Revoke Access Token`
- `Revoke Refresh Token`
- `Introspect Access Token After Revoke`

Itt ellenőrizhető a token állapota és a revoke működése.

### 5. Optional

- `UserInfo`

Ez opcionális request, mert a projektben jelenleg már létezik a végpont.

## Ajánlott futtatási sorrend

1. Ellenőrizd az environment változókat.
2. Futtasd a `Generate PKCE Values` requestet.
3. Futtasd az `Open Authorize URL Helper` vagy `Authorization Request` requestet.
4. Ha szükséges, végezd el a login folyamatot böngészőben.
5. Mentsd el az `authorization_code` értéket.
6. Futtasd a `Token Exchange` requestet.
7. Ellenőrizd, hogy az `access_token` és `refresh_token` elmentődött.
8. Futtasd az `Introspect Access Token` requestet.
9. Futtasd a `Refresh Token Exchange` requestet.
10. Futtasd újra az introspection requesteket.
11. Futtasd a `Revoke Access Token` vagy `Revoke Refresh Token` requestet.
12. Futtasd az `Introspect Access Token After Revoke` requestet.
13. Opcionálisan futtasd a `UserInfo` requestet.

## Mit csinálnak automatikusan a script-ek

### Pre-request script-ek

- PKCE értékek generálása
- `code_verifier` mentése environment változóba
- `code_challenge` mentése environment változóba

### Tests script-ek

- státuszkód ellenőrzés
- tokenek automatikus mentése
- introspection `active` érték mentése
- redirect `Location` header mentése, ha elérhető
- authorization code automatikus kinyerése, ha a redirect közvetlenül látható

## Gyakori hibák

### Rossz `redirect_uri`

Ha a redirect URI nem egyezik pontosan a klienshez regisztrált értékkel, a token csere vagy authorize kérés elbukik.

### Rossz `client_secret`

Az API token, revoke és introspection végpontok ilyenkor hibát adnak.

### Régi vagy már felhasznált authorization code

Az authorization code egyszer használható. Minden új próbához új code kell.

### Hibás PKCE páros

Ha a `code_verifier` nem ugyanahhoz a `code_challenge`-hez tartozik, a token csere sikertelen lesz.

### Postman nem látja a redirectet

Ilyenkor böngészőből kell kinyerni a `code` értéket kézzel.

### Introspection `active: false`

Ez szándékos lehet több esetben is:

- token nem létezik
- token lejárt
- token revoke-olva lett
- token más klienshez tartozik

A backend ezt biztonsági okból egységesen kezeli.

### UserInfo hibák

- `401`: hibás, lejárt vagy revoke-olt Bearer access token
- `403`: hiányzik az `openid` scope

## Rövid ellenőrzőlista

- helyes `base_url`
- helyes `client_id`
- helyes `client_secret`
- helyes `redirect_uri`
- PKCE legenerálva
- új `authorization_code`
- sikeres token csere
- elmentett `access_token`
- elmentett `refresh_token`
- sikeres introspection
- sikeres refresh
- revoke után `active: false`

