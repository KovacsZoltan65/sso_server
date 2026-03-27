# Postman scriptek szerepe az OAuth tesztelésben

## Áttekintés

A Postman scriptek célja, hogy automatizálják az OAuth tesztelési folyamatot, csökkentsék a manuális hibákat, és egységesen kezeljék az adatáramlást a requestek között.

Két fő típusuk van:

- Pre-request Script
- Tests Script

---

## 1. Pre-request Script feladata

A Pre-request Script minden kérés ELŐTT fut le.

### Fő célok:

- dinamikus adatok generálása
- environment változók előkészítése
- request paraméterek automatikus beállítása

### OAuth flow-ban konkrét szerep:

#### PKCE generálás

- `code_verifier` generálása
- `code_challenge` kiszámítása (SHA256 + base64url)
- environment változókba mentés

#### Automatikus fallback logika

- ha nincs PKCE érték → generál
- biztosítja, hogy a Token Exchange mindig működő értékeket kapjon

### Miért fontos?

- elkerülöd a kézi másolási hibákat
- garantálod a PKCE konzisztenciát
- gyorsabb tesztelés

---

## 2. Tests Script feladata

A Tests Script minden kérés UTÁN fut le.

### Fő célok:

- válasz validálása
- adatok kinyerése
- environment frissítése
- automatizált ellenőrzések

---

## 3. OAuth flow-ban konkrét feladatok

### Authorization Request

- redirect URL (`Location` header) kiolvasása
- `authorization_code` kinyerése (ha lehetséges)
- fallback: manuális másolás támogatása

---

### Token Exchange

- HTTP státusz ellenőrzése (200)
- válasz struktúra validálása
- `access_token` mentése
- `refresh_token` mentése
- `scope` mentése

Ez a legfontosabb lépés, mert innen indul minden további request.

---

### Refresh Token Exchange

- új `access_token` mentése
- opcionálisan új `refresh_token` mentése (rotáció miatt)

---

### Introspection

- `active` mező ellenőrzése
- environment változó frissítése (`introspection_active`)

---

### Revoke

- státusz ellenőrzése
- sikeres művelet validálása

---

### Introspection After Revoke

- ellenőrzés, hogy a token:

    active = false

Ez igazolja a revoke működését.

---

### UserInfo

- bearer token validálása
- user payload ellenőrzése
- `sub` mező kötelező ellenőrzése
- opcionálisan email validálás

---

## 4. Environment változók szerepe

A Postman scriptek ezekkel dolgoznak:

- `code_verifier`
- `code_challenge`
- `authorization_code`
- `access_token`
- `refresh_token`
- `scope`
- `introspection_active`

### Mi történik a háttérben?

1. Pre-request generál adatot
2. Request elküldésre kerül
3. Tests script:
    - validál
    - adatot kinyer
    - elment

4. Következő request már ezeket használja

Ez egy láncolt állapotkezelés.

---

## 5. Miért kritikus ez egy SSO rendszerben?

Az OAuth flow több lépésből áll, ahol:

- minden lépés függ az előzőtől
- több token és állapot mozog egyszerre
- hibák könnyen rejtve maradnak manuális tesztelésnél

A scriptek:

- biztosítják a folytonosságot
- automatizálják az állapotkezelést
- reprodukálhatóvá teszik a teszteket
- csökkentik a hibalehetőséget

---

## 6. Rövid összefoglaló

Pre-request Script:
→ adat előkészítés

Tests Script:
→ validáció + adatmentés

Environment:
→ állapot tárolás

Együtt:
→ teljes OAuth flow automatizált tesztelése Postmanben

---

## 7. Mentális modell

Úgy gondolj rá, mint egy mini state machine-re:

- PKCE generálás
- authorization_code megszerzése
- token kiadás
- token használat
- token frissítés
- token visszavonás

A Postman script ezt a teljes ciklust kezeli.

---

## 8. Fejlesztői előny

- gyors debug
- reprodukálható bugok
- API contract validáció
- backend regressziók azonnali észlelése

---

## Végszó

Ha a Postman scripted jól van megírva, akkor:

nem te teszteled az OAuth-ot
hanem az OAuth teszteli saját magát

🙂
