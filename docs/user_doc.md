# SSO felhasználói kézikönyv

## 1. Bevezetés

### Mi ez az alkalmazás?

Ez az alkalmazás egy központi bejelentkezési rendszert használó felület.

Segítségével:

- be tud jelentkezni az Ön számára elérhető alkalmazásokba
- el tudja érni a saját feladatait és adatait
- biztonságosan tud dolgozni egy központi azonosítással

### Mire használható?

Az alkalmazás típustól és jogosultságtól függően használható például:

- adatok megtekintésére
- listák kezelésére
- új rekordok létrehozására
- meglévő adatok szerkesztésére
- saját profil kezelésére

### Kinek készült?

Azoknak a felhasználóknak készült, akik:

- egy vagy több belső rendszerhez kapnak hozzáférést
- központi bejelentkezéssel dolgoznak
- biztonságosan és egyszerűen szeretnének belépni

### Mi az SSO egyszerűen?

Az SSO azt jelenti, hogy egy központi bejelentkezési oldalon azonosítja magát, és utána a kapcsolódó alkalmazások használhatják ezt a bejelentkezést.

Egyszerűbben:

- nem minden alkalmazás külön kezeli a jelszavát
- a bejelentkezést egy központi rendszer végzi
- Ön ezután visszakerül az eredeti alkalmazásba

## 2. Bejelentkezés

### Hogyan indul a bejelentkezés?

1. Nyissa meg az alkalmazást.
2. A kezdőoldalon vagy a bejelentkezési oldalon kattintson az SSO bejelentkezés gombra.
3. A rendszer átirányítja a központi bejelentkezési oldalra.
4. Adja meg a felhasználónevét vagy email címét és a jelszavát.
5. Sikeres belépés után automatikusan visszakerül az alkalmazásba.

### Miért történik átirányítás?

Ez teljesen normális.

Az átirányítás azért történik, mert:

- a bejelentkezést a központi SSO rendszer végzi
- az alkalmazás ellenőrzi, hogy valóban Ön jelentkezett be
- a sikeres azonosítás után visszaengedi Önt a saját felületre

### Mit fog látni a képernyőn?

Tipikusan ez a sorrend:

- alkalmazás kezdőoldala vagy login oldala
- SSO bejelentkezési oldal
- rövid várakozás
- visszatérés az alkalmazás dashboardjára vagy főoldalára

### Fontos tudnivaló

Ha a rendszer rövid időre másik oldalra viszi, majd visszahozza, az nem hiba. Ez a normál bejelentkezési folyamat része.

## 3. A fő felület áttekintése

### Menü

A bal oldali vagy felső menüben általában az alábbiakat találja:

- Dashboard vagy főoldal
- különböző kezelőoldalak
- listák
- saját profil
- kijelentkezés

### Dashboard

A Dashboard a belépés utáni fő áttekintő oldal.

Itt általában:

- gyors összefoglaló információkat lát
- fontos menüpontokat ér el
- innen indulhat tovább a napi munkához

### Navigáció

A navigáció célja, hogy gyorsan elérje a kívánt oldalt.

Jellemző elemek:

- menüpontok
- oldalcímek
- vissza gomb vagy navigációs útvonal
- oldal tetején akciógombok

### Alap UI elemek

#### Táblázatok

A listanézetekben gyakran táblázatot lát.

Itt tud:

- rekordokat megtekinteni
- keresni
- lapozni
- kijelölni
- szerkeszteni vagy törölni

#### Gombok

Gyakori gombok:

- `Új`
- `Mentés`
- `Mégse`
- `Szerkesztés`
- `Törlés`
- `Kijelentkezés`

#### Modál ablakok

Bizonyos műveleteknél felugró ablak jelenhet meg.

Ez általában arra szolgál, hogy:

- megerősítse a műveletet
- gyorsan kitöltsön egy űrlapot
- fontos figyelmeztetést lásson

## 4. Profil kezelés

### Saját adatok megtekintése

A profiloldalon meg tudja nézni a saját adatait.

Ilyenek lehetnek:

- név
- email cím
- fiókhoz tartozó alapadatok

### Mit lehet módosítani?

A rendszer általában csak bizonyos mezőket enged módosítani.

Jellemzően módosítható:

- név

### Mit nem lehet módosítani?

Bizonyos adatok biztonsági vagy üzleti okból nem szerkeszthetők.

Gyakori példa:

- email cím

Ha egy mező nem módosítható:

- szürkén jelenhet meg
- csak olvasható lehet
- vagy egyáltalán nem szerkeszthető mezőként jelenik meg

### Profil módosítása lépésről lépésre

1. Nyissa meg a `Profil` oldalt.
2. Keresse meg a módosítható mezőt.
3. Írja be az új értéket.
4. Kattintson a `Mentés` gombra.
5. Várja meg a sikeres visszajelzést.

## 5. Kijelentkezés

### Hogyan lehet kijelentkezni?

1. Keresse meg a `Kijelentkezés` gombot.
2. Kattintson rá.
3. A rendszer kilépteti, majd visszaviszi a kezdőoldalra vagy a login oldalra.

### Mi történik a háttérben?

Egyszerűen:

- az alkalmazás lezárja az aktuális munkamenetet
- a korábbi belépési állapot megszűnik
- a védett oldalak többé nem lesznek elérhetők új belépés nélkül

### Mikor fontos különösen kijelentkezni?

- nyilvános vagy közös gép használata után
- munkaidő végén
- ha más fogja használni ugyanazt az eszközt

## 6. Gyakori műveletek

### Lista nézet használata

A listanézetek segítenek sok adat áttekintésében.

Általában itt tud:

- sorokat átnézni
- oldalak között lapozni
- elemeket kiválasztani
- műveleteket indítani

### Keresés

1. Keresse meg a keresőmezőt.
2. Írjon be egy kulcsszót.
3. A lista szűkülni fog a találatokra.

Példák:

- név alapján keresés
- azonosító alapján keresés
- részleges szövegrész alapján keresés

### Szűrés

Egyes oldalakon külön szűrők is elérhetők.

Például:

- állapot szerint
- típus szerint
- dátum szerint

Használat:

1. Válassza ki a kívánt szűrőt.
2. Alkalmazza a feltételt.
3. Nézze meg a szűrt listát.

### Rekord létrehozása

1. Kattintson az `Új` vagy `Létrehozás` gombra.
2. Töltse ki a kötelező mezőket.
3. Ellenőrizze az adatokat.
4. Kattintson a `Mentés` gombra.

### Rekord szerkesztése

1. Nyissa meg a kívánt rekordot.
2. Kattintson a `Szerkesztés` gombra.
3. Módosítsa a szükséges mezőket.
4. Mentse el a változásokat.

### Rekord törlése

1. Keresse meg a `Törlés` műveletet.
2. Olvassa el a megerősítő üzenetet.
3. Csak akkor erősítse meg, ha biztos benne.

Fontos:

- a törlés sok esetben nem vonható vissza
- ha bizonytalan, kérjen segítséget

## 7. Hibák és megoldások

### Nem tudok belépni

Mi történt?

- hibás lehet a belépési adat
- a fiók nem elérhető
- átmeneti rendszerhiba is lehet

Mit tegyen?

- ellenőrizze újra az email címet vagy felhasználónevet
- ellenőrizze a jelszót
- próbálja meg újra
- ha továbbra sem működik, jelezze a támogatásnak

### Végtelen átirányítás

Mi történt?

- a rendszer újra és újra a login és az alkalmazás között mozog
- ez általában session vagy böngészőproblémára utal

Mit tegyen?

- frissítse az oldalt
- jelentkezzen ki, ha tud
- zárja be és nyissa meg újra a böngészőt
- próbálja meg privát ablakban
- ha a probléma megmarad, jelezze a támogatásnak

### 401 hiba

Mi történt?

- a rendszer úgy érzékeli, hogy Ön nincs érvényesen bejelentkezve
- a munkamenet lejárhatott

Mit tegyen?

- jelentkezzen be újra
- nyissa meg újra az alkalmazást
- ha ismétlődik, jelezze a hibát

### 403 hiba

Mi történt?

- be van jelentkezve, de nincs jogosultsága az adott műveletre vagy oldalra

Mit tegyen?

- ellenőrizze, hogy a megfelelő oldalon van-e
- próbáljon meg visszalépni egy korábbi oldalra
- kérjen jogosultság-ellenőrzést a rendszergazdától vagy támogatástól

### Lejárt session

Mi történt?

- túl sok ideig nem használta az alkalmazást
- a rendszer biztonsági okból lezárta a munkamenetet

Mit tegyen?

- jelentkezzen be újra
- hosszabb űrlapkitöltés előtt mentse el időben a munkát

## 8. Biztonsági tudnivalók

### Jelszókezelés

- soha ne ossza meg a jelszavát másokkal
- ne küldje el emailben vagy chatben
- ne írja fel jól látható helyre

### Nyilvános vagy közös gép használata

- mindig jelentkezzen ki a munka végén
- zárja be a böngészőablakot is
- ne mentse el a jelszót idegen eszközön

### Mi az a session egyszerűen?

A session az Ön aktuális bejelentkezett állapota.

Ez teszi lehetővé, hogy:

- ne kelljen minden kattintásnál újra belépni
- a rendszer tudja, hogy Ön dolgozik az alkalmazásban

### Mi az access token egyszerűen?

Ez egy háttérben használt belépési igazolás.

Önnek általában nincs vele teendője. A rendszer használja arra, hogy biztonságosan ellenőrizze az Ön hozzáférését.

Fontos:

- ezt nem kell másolnia vagy kezelnie
- ha lejár, a rendszer új belépést kérhet

## 9. Tippek és best practice-ek

### Hogyan használja hatékonyan?

- mindig a Dashboardról induljon
- használja a keresőt nagy listák esetén
- figyelje a visszajelző üzeneteket mentés után
- törlés előtt mindig ellenőrizze, mit töröl

### Gyakori hibák elkerülése

- ne nyisson meg túl sok párhuzamos lapot ugyanabból a műveletből
- hosszabb munka közben időnként mentse az adatokat
- ha valami szokatlanul viselkedik, frissítsen oldalt
- ugyanazt a böngészőt és ugyanazt a bejelentkezési módot használja

### Gyors navigáció

- használja a menüt a fő területek eléréséhez
- keresésnél rövid, pontos kulcsszavakat adjon meg
- ha elveszett, térjen vissza a Dashboardra

## 10. Kapcsolat és támogatás

### Hova forduljon hiba esetén?

Ha elakad, forduljon:

- a belső támogatási csapathoz
- a rendszergazdához
- az Ön szervezetén belüli kijelölt kapcsolattartóhoz

### Milyen információt adjon meg?

Hiba bejelentésekor hasznos, ha megadja:

- melyik oldalon történt a hiba
- mit szeretett volna csinálni
- pontosan mit látott a képernyőn
- volt-e hibaüzenet
- mikor történt a probléma
- lehetőleg képernyőképet is csatoljon

### Rövid mintaleírás hibabejelentéshez

- `Beléptem az alkalmazásba`
- `A Dashboardról a Profil oldalra mentem`
- `Mentés után 403 hibát kaptam`
- `A hiba 2026-03-29 10:15 körül történt`

## Gyors indulás 5 lépésben

1. Nyissa meg az alkalmazást.
2. Kattintson az SSO bejelentkezés gombra.
3. Jelentkezzen be a központi oldalon.
4. Várja meg, amíg visszairányítja a rendszer.
5. A Dashboardról indulva használja a menüt és a listákat.

