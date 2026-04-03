# OIDC-Grade Roadmap For Nonce, Logout, And Session Boundary Evolution

## 1. Cél és scope

Ez a dokumentum a `sso_server` és a kapcsolódó `sso_client` OIDC-érettebb fejlődési útját rögzíti az alábbi területeken:

- `nonce` támogatás
- logout modell
- provider és kliens session boundary
- cross-app session viselkedés
- fázisolt roadmap a jelenlegi authorization code + PKCE flow továbbfejlesztéséhez

Nem scope:

- route implementáció
- `nonce` runtime bevezetése
- ID token implementáció
- front-channel vagy back-channel logout implementáció
- session engine refactor

## 2. Current state summary

### 2.1. `nonce`

Jelenleg nincs explicit `nonce` támogatás.

Most mi van:

- a `sso_client` `state`-et és `code_verifier`-t generál
- az authorize request tartalmaz `state`, `code_challenge`, `code_challenge_method`
- a callback oldalon `state` validáció történik
- nincs `nonce` generálás, tárolás vagy validáció

Következmény:

- a rendszer jelenlegi OAuth authorization code + PKCE flow-ra alkalmas
- de OIDC-érettebb browser flow-hoz még hiányzik a `nonce`

### 2.2. Logout

`sso_server`

- saját web session logouttal rendelkezik
- a provider logout jelenleg csak a szerver saját sessionjét zárja

`sso_client`

- csak lokális logoutot hajt végre
- törli a saját sessiont, a `state` és `pkce_verifier` ideiglenes állapotot
- nincs provider logout redirect vagy handshake

Következmény:

- a kliensből kijelentkezés nem szünteti meg a provider sessiont
- a provider session megszűnése sem propagálódik a kliensek felé

### 2.3. Session boundary

Jelenleg implicit, de nem teljesen formalizált boundary létezik:

- a provider session a `sso_server` bejelentkezett állapota
- a client session a `sso_client` lokális Laravel web sessionje
- a kliens authorize callback után építi fel a saját sessionjét
- a kettő nem ugyanaz, de ez még nincs külön OIDC-grade roadmapként dokumentálva

Mi hiányzik:

- explicit reauth szabály
- explicit provider vs client session lifecycle modell
- explicit logout maturity roadmap

## 3. Nonce roadmap

### 3.1. Miért kell

A `nonce` jövőbeli OIDC-érettséghez szükséges, mert:

- replay elleni védelmet ad az interactive auth válasz kontextusában
- előkészíti az ID token alapú response integritást
- segít formalizálni az authorize request és a későbbi identity response kapcsolatát

### 3.2. Mikor kell

Javasolt végső irány:

- `openid` scope jelenléte esetén kötelező

Indok:

- az OIDC-jellegű interactive identity flow-knál van igazi jelentősége
- nem kell minden tisztán OAuth-jellegű flow-ra ráerőltetni az első iterációban

### 3.3. Hol keletkezik

Javasolt modell:

- a `sso_client` generálja
- legalább a `state`-hez hasonló erősségű, magas entrópiájú véletlen érték legyen
- sessionben tárolódjon ugyanúgy, mint a `state` és a `pkce_verifier`

### 3.4. Hol megy át

- authorize request query paramétereként: `nonce=<value>`
- a `sso_server` validált authorize kontextus részeként továbbviszi
- a consent contextbe is bekerül, ha consent flow közbeékelődik

### 3.5. Hol validálódik

Első OIDC-kompatibilis iterációban:

- még nem a callback queryn validálódik
- hanem a későbbi ID token vagy OIDC-grade identity response ellenőrzésnél

Átmeneti roadmap szabály:

- már a korai iterációban gyűjtsük és tároljuk a `nonce`-ot
- de a tényleges cryptographic validation csak az ID token bevezetésével váljon kötelezővé

### 3.6. Első iteráció javaslata

Konkrét baseline:

- `openid` scope jelenléte esetén a `sso_client` már generáljon `nonce`-ot
- a `sso_server` fogadja és a consent / authorize kontextus részeként tárolja
- a callback contract még ne változzon emiatt
- a teljes validációt a későbbi ID token / OIDC response story fogja kötelezővé tenni

Ez nem full OIDC implementáció, de nem hagyja a `nonce` témát homályos “majd egyszer” állapotban.

## 4. Logout model

### 4.1. Local client logout

Jelentés:

- a `sso_client` saját sessionjének lezárása
- lokális auth state törlése
- ideiglenes SSO state törlése

Jelenlegi állapot:

- már létezik
- ez a jelenlegi egyetlen teljesen működő logout típus a kliens oldalon

### 4.2. Provider logout

Jelentés:

- a `sso_server` saját provider sessionjének lezárása
- a központi auth cookie / session megszüntetése

Jelenlegi állapot:

- a provider saját session logouttal rendelkezik
- de nincs dokumentált kliens felől elérhető provider logout redirect contract

### 4.3. Cross-app logout / SSO logout

Jelentés:

- provider logout vagy egy központi logout esemény hatása több kliensre

Jelenlegi állapot:

- nincs
- sem front-channel, sem back-channel propagation nincs

Következmény:

- a user kijelentkezhet az egyik kliensből úgy, hogy a provider session még él
- a user kijelentkezhet a providerből úgy, hogy más kliensek lokális sessionje még ideiglenesen él

## 5. Session boundary model

### 5.1. Provider session

Mit jelent:

- a user authentikált a `sso_server` oldalon
- a provider web session él
- a provider képes új authorize kérést új login nélkül folytatni

### 5.2. Client session

Mit jelent:

- a `sso_client` lokálisan felépítette a saját sessionjét
- ez a callback, token exchange és userinfo után jön létre
- a kliens saját guardon és cookie-n keresztül működik tovább

### 5.3. Boundary szabály

Kötelező mentális modell:

- provider session != client session
- provider session megléte nem jelenti, hogy minden kliensben aktív session van
- client session megszűnése nem jelenti, hogy a provider session megszűnt
- provider session megszűnése nem jelenti automatikusan, hogy minden kliens azonnal kijelentkezett

### 5.4. Reauthentication szabály

Javasolt baseline:

- ha a kliens lokális sessionje él, nem kell új authorize flow
- ha a kliens lokális sessionje megszűnt, a kliens újra a providerhez irányít
- ha a provider session még él, a user gyorsan visszakerülhet a kliensbe
- ha a provider session is megszűnt, új login szükséges

Forced reauth események jövőbeli baseline-ja:

- security incident
- password reset vagy account recovery
- admin forced reauth
- trust/policy által megkövetelt fresh login

## 6. Logout roadmap phases

### Phase 1 – Clear boundary and terminology

Cél:

- explicit dokumentáció a local client logout és provider session közti különbségről
- explicit UX baseline
- nincs cross-app logout elvárás

Mit tartalmaz:

- current local logout mint supported baseline
- provider session külön kezelése
- session boundary szerződés

### Phase 2 – Provider logout contract

Cél:

- szabályozott provider logout endpoint vagy redirect szerződés
- világos döntés arról, hogy a kliens csak lokálisan vagy központilag is kijelentkeztet

Mit tartalmaz:

- provider logout return URL policy
- kliensoldali “local logout” vs “logout everywhere” jellegű szerződés
- open redirect guardrail-ek

Még nem tartalmazza:

- teljes multi-client propagation

### Phase 3 – OIDC-grade logout propagation

Cél:

- front-channel logout
- back-channel logout
- több kliens sessionállapotának tudatos propagációja

Mit tartalmazhat később:

- logout correlation state
- session-state propagation
- kliensek közti szinkronizált kijelentkeztetési modell

## 7. `sso_client` UX and contract impact

### 7.1. `nonce`

A kliens jövőbeli felelőssége:

- `nonce` generálása
- sessionben tárolása
- authorize requestben továbbítása

Az első iterációban a kliensnek még nem kell új callback branch-et kezelnie emiatt.

### 7.2. Logout típusok

Jövőbeli baseline:

- `local logout`
- `provider logout`
- később `global / cross-app logout`

Kliensoldali kommunikáció:

- egyértelmű legyen, ha a user csak ebből az appból jelentkezett ki
- és külön, ha a központi szolgáltatásból is kijelentkezik

### 7.3. Session boundary UX

A user tapasztalhatja, hogy:

- ebből az appból kijelentkezett, de a központi szolgáltatásban még él a session
- a központi szolgáltatásból kijelentkezett, de más kliens sessionje még ideiglenesen él

Ezt az első iterációban dokumentálni kell, nem eltüntetni.

## 8. Security guardrails

### 8.1. `nonce`

- ne legyen újrahasznosítható flow-kontextusokon át
- magas entrópiájú legyen
- a kliens sessionen kívül ne legyen lazán kezelve
- ha a flow később `openid`-ot használ, a `nonce` ne maradjon opcionális

### 8.2. Logout

- logout return URL ne nyisson open redirect kockázatot
- a local és provider logout ne legyen összekeverve
- a részleges state-törlés ne maradjon dokumentálatlan
- provider logout ne tekintse automatikusan lezártnak az összes kliens sessionjét, ha nincs propagation implementálva

### 8.3. Session boundary

- ne legyen feltételezve, hogy provider session = client session
- security event esetén legyen erősebb reauth irány
- a kliens csak a saját sessionjét birtokolja, a provider a sajátját

## 9. Data / contract impact

Későbbi javasolt új adatok:

- `nonce`
- `logout_return_to` vagy ennek policy-alapú megfelelője
- session / logout correlation identifier
- provider session awareness vagy future `session_state` jellegű mező

Mi nem kell még az első iterációban:

- ID token
- full OIDC discovery
- front-channel logout event propagation
- back-channel logout token
- session engine újraírás

## 10. Audit / observability

### 10.1. `sso_server`

Javasolt események:

- `oauth.nonce.issued`
- `oauth.nonce.validation_failed`
- `auth.logout.provider_initiated`
- `auth.logout.provider_completed`
- `auth.session.reauth_required`

Minimum payload:

- `client_id` ha ismert
- `client_public_id` ha ismert
- `target_user_id`
- `reason`
- `decision`
- request context mezők

### 10.2. `sso_client`

Javasolt események:

- `client_auth.logout.local_completed`
- `client_auth.logout.provider_redirected`
- `client_auth.session.reauth_required`

Minimum payload:

- `target_local_user_id` ha ismert
- `reason`
- `redirect_target` ha van
- request context mezők

### 10.3. Mit nem logolunk

- access token
- refresh token
- authorization code
- PKCE verifier
- client secret
- session identifier
- nyers `nonce`

## 11. Test plan

### 11.1. `nonce`

- kliens generál `nonce`-ot
- authorize request továbbítja
- szerver authorize kontextusba teszi
- ha később required és hiányzik -> fail
- ha később mismatch -> fail

### 11.2. Logout

- local logout csak a kliens sessiont zárja
- provider logout a provider sessiont is zárja
- logout contract nem enged open redirectet
- cross-app logout hiánya explicit és tesztelt baseline marad Phase 1-ben

### 11.3. Session boundary

- provider session él, kliens session nincs
- kliens session él, provider session megszűnt
- reauth requirement felismerhető

