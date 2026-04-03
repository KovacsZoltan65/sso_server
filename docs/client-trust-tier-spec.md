# Client Trust Tier And Consent Bypass Specification

## 1. Cél és scope

Ez a dokumentum a `sso_server` kliens trust tier modelljét és a consent bypass szabályait definiálja.

Scope:

- explicit trust tier taxonómia
- consent requirement szabályrendszer
- consent bypass guardrail-ek
- scope-változás hatása a consentre
- adatmodell javaslat
- audit minimum
- kapcsolat a STORY-10 consent flow-val

Nem scope:

- adatbázis migráció
- route vagy service implementáció
- remembered consent implementáció
- admin UI kidolgozása
- OIDC specializáció

## 2. Current state

Jelenleg nincs explicit trust modell.

Mit jelent ez most:

- a `sso_clients` adatmodell nem tartalmaz trust tier mezőt
- nincs külön `consent_required` vagy `consent_bypass_allowed` fogalom
- a jelenlegi authorize flow minden klienst alapvetően egyformán kezel
- a meglévő eltérés csak policy és access restriction szinten jelenik meg

Következmény:

- first-party és third-party kliens között nincs formális döntési különbség
- a jövőbeli consent flow nem tud jelenleg trust-alapú döntést hozni
- a bypass szabályok most csak implicit, dokumentálatlan formában lennének bevezethetők, ami nem elfogadható

## 3. Trust tier model

### 3.1. Kötelező kategóriák

#### `first_party_trusted`

Jelentés:

- a rendszer tulajdonosa által üzemeltetett, teljes kontroll alatt álló kliens
- stabil, jóváhagyott first-party alkalmazás
- az alkalmazás redirect URI-jai, scope-jai és ownershipje kontrollált

Tipikus példa:

- hivatalos belső portál
- elsődleges saját webalkalmazás

#### `first_party_untrusted`

Jelentés:

- first-party ownershipű, de nem teljesen megbízható kliens
- kísérleti, fejlesztés alatt álló, partnercsapat által kezelt vagy fokozottabb review-t igénylő saját app

Tipikus példa:

- beta belső alkalmazás
- új kliens, amely még nem kapott trusted besorolást

#### `third_party`

Jelentés:

- külső alkalmazás
- nem a provider teljes kontrollja alatt áll
- user-facing consent mindig explicit kell legyen

Tipikus példa:

- partnerintegráció
- külső SaaS vagy ügyfélalkalmazás

#### `machine_to_machine`

Jelentés:

- nincs interactive end-user authorize flow
- user consent nem értelmezett
- tipikusan `client_credentials` jellegű kliens

Megjegyzés:

- ez a tier nem használja a user-consent képernyőt
- interactive `/oauth/authorize` flow-ra nem alkalmas baseline

### 3.2. Modell alapelve

A trust tier nem ugyanaz, mint:

- `confidential` vs `public`
- `pkce_required`
- token policy

Ezek külön dimenziók.

A trust tier csak azt mondja meg:

- mennyire bízhat a provider a kliens user-facing authorize viselkedésében
- mikor engedhető consent bypass

## 4. Consent rules

### 4.1. Consent requirement matrix

| trust tier | consent required | megjegyzés |
| --- | --- | --- |
| `first_party_trusted` | default szerint nem, de csak szigorú bypass guardrail-ek mellett | bypass csak akkor, ha minden biztonsági feltétel teljesül |
| `first_party_untrusted` | igen | saját kliens, de trusted bypass nem engedélyezett |
| `third_party` | mindig igen | explicit user consent kötelező |
| `machine_to_machine` | nincs user consent | interactive authorize flow helyett külön machine flow |

### 4.2. Döntési sorrend

Consent decision csak a STORY-10 szerinti valid authorize kontextus után történhet:

1. kliens valid
2. redirect valid
3. authorize request valid
4. user hitelesített
5. provider-side policy refusal nincs
6. trust tier alapján consent required vagy bypass döntés születik

## 5. Bypass rules

### 5.1. Mikor engedhető meg a bypass

Consent bypass csak akkor engedhető, ha az alábbiak egyszerre teljesülnek:

- `trust_tier = first_party_trusted`
- a kliens aktív
- a redirect URI a kliens előzetesen regisztrált, validált redirect URI-ja
- a kért scope-ok a kliens számára engedélyezett scope-ok
- a kért scope-halmazon nincs olyan bővülés, amely új user-facing jóváhagyást igényel
- a user hitelesített
- provider-side access restriction nem tiltja a klienshez való hozzáférést

### 5.2. Mikor tilos a bypass

Consent bypass tilos, ha bármelyik igaz:

- `trust_tier = third_party`
- `trust_tier = first_party_untrusted`
- `trust_tier` hiányzik vagy ismeretlen
- a kliens inaktív
- a redirect URI nem valid
- a request szerkezetileg hibás
- új vagy bővített scope jelenik meg a korábban jóváhagyotthoz képest
- a kliens machine-to-machine tierben van, de interactive authorize flow-t próbál használni

### 5.3. Policy kimenetek

A szerver consent policy döntésének ajánlott normalizált kimenete:

- `show_consent`
- `skip_consent`
- `deny_authorization`

Ez a háromállapotú eredmény tisztább, mint egy sima boolean.

## 6. Scope handling

### 6.1. Scope baseline

A trust tier önmagában nem írhatja felül a scope kontrollt.

Mindig kötelező:

- csak a klienshez rendelt scope kérhető
- invalid scope esetén provider-side hiba keletkezik

### 6.2. Scope change szabály

Ha a kliens az eddigiekhez képest új scope-ot kér:

- consent újra kötelező

Ha a kliens a már korábban jóváhagyott scope-halmazon belül marad:

- `first_party_trusted` kliensnél bypass lehetséges
- `first_party_untrusted` és `third_party` kliensnél ettől még explicit consent szükséges az első implementáció baseline-ja szerint

### 6.3. Scope bővülés

Scope bővülésnek számít:

- bármely új scope hozzáadása a korábban jóváhagyott halmazhoz

Nem számít scope bővülésnek:

- ha kevesebb scope-ot kér a kliens
- ha ugyanazt a scope-halmazon belül marad

Következmény:

- új scope -> consent kötelező
- változatlan vagy szűkebb scope -> trust tier alapú döntés engedhető

## 7. Remembered consent kapcsolat

Ez a story nem implementál remembered consentet, de előkészíti.

Jövőbeli baseline:

- `first_party_trusted`: alkalmas remembered consent vagy implicit consent skip szabályra
- `first_party_untrusted`: remembered consent csak külön, szigorú review után
- `third_party`: explicit remembered consent modell nélkül minden új sessionben consent jelenhet meg

Fontos:

- a trust tier nem egyenlő a remembered consenttel
- a remembered consent később külön decision layer lesz

## 8. Security guardrail-ek

### 8.1. Tilos

- `third_party` kliensnél consent bypass
- redirect URI lazítása trust tier alapján
- scope ellenőrzés kihagyása trusted kliens miatt
- provider-side access restriction megkerülése trusted kliens miatt
- ismeretlen vagy hiányzó trust tier automatikus trustedként kezelése

### 8.2. Kötelező

- trust tier policy minden authorize requestnél lefusson
- consent skip esetén audit event keletkezzen
- scope bővülés esetén consent requirement kényszerüljön ki
- consent bypass csak validált authorize kontextuson történhessen
- bypass soha ne írja felül a STORY-07 authorize error contractot

### 8.3. Default deny szemlélet

Ha a trust-related adat hiányos vagy ellentmondásos:

- a rendszer `show_consent` vagy `deny_authorization` irányba menjen
- soha ne `skip_consent` legyen az implicit fallback

## 9. Data model proposal

### 9.1. Javasolt mezők a `sso_clients` táblához

Minimum javaslat:

- `trust_tier` enum
- `is_first_party` boolean
- `consent_bypass_allowed` boolean

### 9.2. Javasolt enum értékek

`trust_tier`:

- `first_party_trusted`
- `first_party_untrusted`
- `third_party`
- `machine_to_machine`

### 9.3. Mezőszintű jelentés

`trust_tier`

- a kliens alap trust kategóriája
- ez a fő döntési mező

`is_first_party`

- gyors szűrésre és admin UX-re hasznos segédmező
- nem helyettesíti a `trust_tier`-t

`consent_bypass_allowed`

- csak plusz engedélyező zászló
- önmagában nem elég bypasshoz
- `third_party` esetén akkor sem eredményezhet skip-et, ha tévesen `true`

### 9.4. Javasolt normalizált policy

A végső bypass döntés ajánlott képlete:

- `trust_tier`
- `consent_bypass_allowed`
- scope change státusz
- authorize kontextus validitása
- access restriction eredménye

Vagyis a bypass ne legyen egyetlen booleannel vezérelve.

## 10. Integration contract expectations (`sso_client`)

A kliens felől a trust tier közvetlenül nem kell, hogy látható legyen, de a következménye igen.

### 10.1. Várható UX utak

#### Consent skip

Ha a szerver trusted bypass döntést hoz:

- a kliens a mostani success authorize flow-t látja
- nincs külön consent képernyő
- a callback normál success callback lesz

#### Consent required

Ha a szerver explicit consentet kér:

- a user a provider consent képernyőjét látja
- approve után normál success callback jön
- deny után refusal callback jön

### 10.2. Kliens elvárás

A kliensnek nem kell külön tudnia a trust tier konkrét értékét.

A kliensnek ezt kell stabilan kezelnie:

- success callback
- refusal callback
- provider-side authorize error

Vagyis a kliens a trust modell hatását transport és UX szinten érzékeli, nem nyers policyadatként.

## 11. Audit specification

Minimum szerveroldali események:

- `oauth.consent.shown`
- `oauth.consent.skipped`
- `oauth.consent.required_due_to_scope_change`

Ajánlott minimum payload:

- `client_id`
- `client_public_id`
- `target_user_id`
- `trust_tier`
- `scope_codes`
- `redirect_uri`
- `decision`

Nem logolható:

- authorization code
- access token
- refresh token
- PKCE verifier
- client secret

## 12. Test plan

### 12.1. `sso_server`

Későbbi implementációs tesztek:

- `first_party_trusted` kliens + változatlan scope -> consent skip
- `first_party_trusted` kliens + új scope -> consent kötelező
- `first_party_untrusted` kliens -> consent kötelező
- `third_party` kliens -> consent kötelező
- `machine_to_machine` kliens interactive authorize flow-ban -> fail
- hiányzó trust tier -> ne legyen bypass
- `consent_bypass_allowed=false` -> ne legyen bypass még trusted tier mellett sem

### 12.2. `sso_client`

Későbbi implementációs tesztek:

- skip esetén normál authorize success callback
- consent esetén provider consent page után approve success callback
- deny esetén refusal callback branch
- refusal ne essen vissza generikus belső hibára

## 13. Kapcsolat a STORY-10-zel

A STORY-10 a consent flow mechanikáját írja le.

A STORY-12 ezt a döntési réteget adja hozzá:

- kell-e egyáltalán consent
- vagy trusted bypass miatt a flow közvetlenül approve-success irányba mehet

Ajánlott integrációs pont:

- a STORY-10 `GET /oauth/authorize` decision ága a valid authorize kontextus után először trust policy döntést futtat
- eredmény:
  - `deny_authorization`
  - `show_consent`
  - `skip_consent`

Ez legyen a jövőbeli consent engine első döntési pontja.
