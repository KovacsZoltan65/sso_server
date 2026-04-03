# Remembered Consent Policy And Storage Specification

## 1. Cél és scope

Ez a dokumentum a `sso_server` remembered consent policy-ját és storage modelljét definiálja.

Scope:

- remembered consent támogatási szabályok
- kliens-kategória alapú alkalmazhatóság
- consent identity modell
- scope és redirect szabályok
- lejárat és invalidation
- storage modell javaslat
- audit minimum
- decision tree
- kapcsolat a STORY-10 és STORY-12 anyagokkal

Nem scope:

- adatbázis migráció
- runtime implementáció
- admin UI
- user-visible checkbox
- teljes consent history rendszer

## 2. Current state summary

Jelenleg nincs remembered consent.

Mit jelent ez most:

- nincs consent rekord entitás
- nincs per-user/per-client/per-scope consent tárolás
- nincs remembered consent döntési ág az authorize flow-ban
- a jelenlegi automatikus approve nem remembered consent, hanem a consent flow hiánya

Mi hiányzik hozzá:

- explicit policy, hogy mely klienseknél engedhető
- explicit identity modell, hogy mire vonatkozik egy grant
- explicit invalidation szabályok
- tárolási modell
- audit események

## 3. Policy decision

### 3.1. Támogatott-e

Igen, a remembered consent támogatott legyen, de konzervatív feltételekkel.

### 3.2. Kliens-kategória szabály

`first_party_trusted`

- támogatott
- ez az első iteráció elsődleges remembered consent célcsoportja

`first_party_untrusted`

- első iterációban nem támogatott
- explicit consent szükséges minden alkalommal

`third_party`

- első iterációban nem támogatott
- explicit consent szükséges minden alkalommal

`machine_to_machine`

- interactive user consent szempontból nem releváns

### 3.3. Miért ez a baseline

Mit nyerünk:

- csökkentjük a felesleges consentet a legmegbízhatóbb first-party klienseknél
- nem nyitunk túl korán kockázatos harmadik feles bypass felületet
- a későbbi kiterjesztéshez megmarad a policy hook

Mit kerülünk el:

- third-party csendes jogosultságfelhalmozás
- first-party, de bizonytalan minőségű kliensek túl korai felmentése

## 4. Consent identity model

### 4.1. Javasolt identity kulcs

A remembered consent rekord identity-je:

- `user_id`
- `client_id`
- `redirect_uri_fingerprint`
- `granted_scope_fingerprint`
- `policy_version`

Ez logikai értelemben a consent egysége.

### 4.2. Miért ez a kötés

`user_id`

- a consent mindig konkrét user döntése

`client_id`

- a grant mindig konkrét klienshez tartozik

`redirect_uri_fingerprint`

- az első iterációban a remembered consent legyen konkrét redirect URI-hoz kötött
- ez konzervatívabb és biztonságosabb több redirect URI-s kliensnél

`granted_scope_fingerprint`

- a pontos scope-halmazt azonosítja
- megakadályozza, hogy scope-bővítés csendben átmenjen

`policy_version`

- lehetővé teszi a consent policy vagy trust szabályok jövőbeli változás miatti invalidálást

### 4.3. Mit nyerünk ezzel

- nincs cross-user újrahasznosítás
- nincs cross-client újrahasznosítás
- nincs scope-expansion reuse
- nincs több redirect URI között implicit újrahasznosítás
- a policy változás technikailag kényszeríthet új consentet

## 5. Scope rules

### 5.1. Azonos scope-halmaz

Ha a kliens ugyanazt a scope-halmazt kéri ugyanarra a redirect URI-ra:

- remembered consent használható, ha a rekord nem lejárt és nem revokált

### 5.2. Scope bővülés

Ha bármely új scope megjelenik:

- remembered consent nem használható
- új consent kötelező

Ez kemény szabály.

### 5.3. Scope szűkülés

Ha a kliens kevesebb scope-ot kér, mint amit korábban a user már jóváhagyott:

- az első iterációban remembered consent használható
- indok: a korábbi grant szuperszettje a most kérteknek

Megjegyzés:

- a kliens továbbra is csak a ténylegesen kért scope-okra kap auth code-ból származó grantet
- a remembered consent csak azt dönti el, kell-e új consent képernyő

### 5.4. Scope átnevezés vagy meta változás

Ha a scope technikai `code` változik:

- új consent szükséges

Ha csak a display név vagy leírás változik, de a technikai `code` ugyanaz:

- ez önmagában ne törje meg a remembered consentet az első iterációban

Indok:

- a technikai code a kanonikus azonosító
- pusztán copy-változás miatt nem érdemes automatikusan invalidálni

## 6. Redirect rules

### 6.1. Redirect kötés

Az első iteráció javaslata:

- a remembered consent konkrét `redirect_uri`-hoz legyen kötve

### 6.2. Miért ez a biztonságosabb baseline

Biztonságosabb, mert:

- több redirect URI-s kliensnél nem reuse-olunk automatikusan más callback célra
- világosabb az audit és az invalidation
- kisebb a kockázata annak, hogy egy új redirect cél csendben hozzáférést kapjon

### 6.3. Gyakorlati következmény

Ugyanazon kliens több redirect URI-ja esetén:

- az egyikre adott remembered consent a másikra nem használható újra

Redirect módosítás után:

- a korábbi redirecthez tartozó remembered consent nem használható az új redirectre

## 7. Expiry and invalidation

### 7.1. TTL döntés

Javasolt első baseline:

- fix TTL: `90 nap`

Indok:

- nem túl rövid, tehát valódi UX-előnyt ad
- nem korlátlan, tehát policy és biztonsági változások mellett sem marad örökké érvényben

### 7.2. Kötelező invalidation események

Egy remembered consent érvénytelen, ha:

- scope bővülés történt
- a kliens inaktívvá válik
- a kliens `trust_tier` értéke megváltozik
- a kliens `consent_bypass_allowed` policy-je megváltozik
- a consent policy `policy_version` értéke megváltozik
- a redirect URI már nem egyezik vagy kikerül a kliensből
- a user manuálisan visszavonja
- admin visszavonja
- account recovery / security incident / forced reauth policy explicit invalidálást kér
- a rekord lejárt

### 7.3. Forced security invalidation

Az első iteráció specifikációja szerint remembered consent invalidálható legyen legalább ezeknél:

- jelszó-visszaállítás utáni biztonsági helyzet
- kompromittálódás gyanúja
- admin által kezdeményezett forced reauth vagy consent reset

## 8. Storage model

### 8.1. Javasolt entitás

Javasolt tábla / entitás:

- `user_client_consents`

### 8.2. Minimum mezők

- `id`
- `user_id`
- `client_id`
- `redirect_uri`
- `redirect_uri_fingerprint`
- `granted_scope_codes`
- `granted_scope_fingerprint`
- `trust_tier_snapshot`
- `policy_version`
- `granted_at`
- `last_used_at`
- `expires_at`
- `revoked_at`
- `revocation_reason`

### 8.3. Egyediség

Javasolt logikai egyediség:

- egy aktív remembered consent rekord ugyanarra a kombinációra:
  - `user_id`
  - `client_id`
  - `redirect_uri_fingerprint`
  - `granted_scope_fingerprint`
  - `policy_version`

Gyakorlati cél:

- ugyanarra a consent identity-re ne legyen több párhuzamos aktív rekord

### 8.4. Mit nem tárolunk

- access token
- refresh token
- authorization code
- PKCE verifier
- client secret
- session identifier

### 8.5. Snapshot mezők

Tároljunk snapshotot ezekből:

- `trust_tier_snapshot`
- `policy_version`

Ne tároljunk szükségtelen UI snapshotot első iterációban:

- kliens display név
- scope display nevek

Indok:

- a technikai döntéshez nem szükséges
- csökkenti a snapshot driftet és a felesleges tárolást

## 9. Decision tree

A remembered consent kiértékelés javasolt sorrendje:

1. kliens valid?
2. redirect URI valid?
3. authorize request valid?
4. user hitelesített?
5. trust tier engedi a remembered consentet?
6. a kliens `consent_bypass_allowed` szerint eligible?
7. létezik aktív, nem revokált, nem lejárt consent rekord?
8. a redirect URI fingerprint egyezik?
9. a kért scope-halmaz kompatibilis?
10. a `policy_version` egyezik?
11. a `trust_tier_snapshot` még kompatibilis?
12. nincs olyan security invalidation esemény, ami felülírná?
13. ha igen -> `skip_consent`
14. ha nem -> `show_consent`

Kompatibilis scope-halmaz az első iterációban:

- pontos egyezés
- vagy a kért halmaz a korábban jóváhagyott halmaz részhalmaza

Nem kompatibilis:

- bővített scope-halmaz

## 10. Client contract impact

### 10.1. Mit érzékel a kliens

Ha remembered consent miatt skip történik:

- a kliens normál success callbacket kap
- nincs külön “remembered consent used” protokoll

Ha remembered consent nem használható:

- normál consent flow jelenik meg
- approve után success callback
- deny után refusal callback

### 10.2. Mit nem kell tudnia a kliensnek

A kliensnek nem kell tudnia:

- friss consentből jött-e a döntés
- remembered consentből történt-e a skip
- mi volt a policy version
- mi volt az invalidation oka

Ez szerveroldali policy kérdés marad.

## 11. Audit

### 11.1. Minimum szerveroldali események

- `oauth.consent.remembered_used`
- `oauth.consent.remembered_not_eligible`
- `oauth.consent.remembered_invalidated`
- `oauth.consent.remembered_revoked`

### 11.2. Minimum payload

- `client_id`
- `client_public_id`
- `target_user_id`
- `scope_codes`
- `trust_tier`
- `policy_version`
- `reason`
- `decision`

### 11.3. Mit ne logoljunk

- access token
- refresh token
- authorization code
- PKCE verifier
- client secret
- session identifier

### 11.4. `sso_client`

A kliens oldalon nincs szükség külön remembered-consent-specifikus protokollra vagy új callback branchre.

## 12. UX policy note

Első iterációban:

- ne legyen user-visible “Remember this approval” checkbox

Indok:

- a remembered consent az első iterációban szerveroldali policy kérdés maradjon
- ne keverjük össze a usert explicit consent UI-val és policy-driven consent skippel
- a checkbox külön storyként, külön UX/security döntéssel vezethető be később

## 13. Test plan

### 13.1. `sso_server`

- eligible remembered consent -> consent skip
- no consent record -> consent shown
- expired consent -> consent shown
- revoked consent -> consent shown
- scope expansion -> consent shown
- same scope set -> skip, ha minden policy feltétel teljesül
- narrowed scope set -> skip, ha minden policy feltétel teljesül
- trusted kliens -> skip csak kompatibilis policy mellett
- `first_party_untrusted` -> no skip
- `third_party` -> no skip
- trust tier changed -> previous remembered consent invalid
- policy version changed -> previous remembered consent invalid
- redirect changed -> previous remembered consent invalid

### 13.2. `sso_client`

- skip esetén normál success callback
- consent szükség esetén változatlan approve/deny flow
- refusal továbbra is külön ág

## 14. Kapcsolat a STORY-10 és STORY-12 specifikációval

Kapcsolat a STORY-10-zel:

- a consent mechanika ugyanaz marad
- remembered consent csak azt dönti el, kell-e a consent screen

Kapcsolat a STORY-12-vel:

- remembered consent eligibility a trust tier policyból indul
- első iterációban csak `first_party_trusted` kliensnél engedhető
