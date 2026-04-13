# UX Audit

Projektek: `sso_server`, `sso_client`  
Dátum: `2026-04-13`  
Módszer: statikus UI-, flow- és copy-audit a frontend/layout/kontroller rétegen

## Vezetői Összegzés

A két alkalmazás technikailag sok helyen tudatos és rendezett UX-alapokra épül: vannak megerősítő dialógusok, toast visszajelzések, üres állapotok, responsive shell-ek, és a biztonságkritikus pontokon jellemzően világos a rendszer szándéka.

A legnagyobb UX problémák nem annyira komponens-szinten, hanem rendszerélmény-szinten jelennek meg:

- a két alkalmazás között és néha ugyanazon oldalon belül is keveredik a nyelv és a terminológia,
- a `sso_client` belépési folyamata egy plusz kattintást kér ott, ahol a felhasználó inkább azonnali, irányított továbblépést várna,
- a `sso_server` profiloldala vizuálisan kilóg a többi admin shell-ből,
- az `sso_client` `SSO Status` oldala normál navigációs elemként erősen technikai és kevéssé akcióorientált,
- az `sso_server` consent képernyője korrekt, de még nem elég bizalomépítő egy valódi identity flow-hoz.

## Fő Megállapítások

### 1. Magas: nyelvi és terminológiai széttartás a két alkalmazás között

Érintett helyek:

- [resources/js/Pages/Auth/Login.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/Auth/Login.vue:37)
- [resources/js/Pages/Clients/Index.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/Clients/Index.vue:124)
- [resources/js/Pages/OAuth/Consent.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/OAuth/Consent.vue:43)
- [../sso_client/resources/js/Pages/Auth/Login.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Auth/Login.vue:25)
- [../sso_client/resources/js/Pages/Profile/Edit.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Profile/Edit.vue:130)
- [../sso_client/resources/js/Pages/Companies/Index.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Companies/Index.vue:173)
- [../sso_client/resources/js/Pages/Sso/Status.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Sso/Status.vue:25)

Megfigyelés:

- A `sso_server` login és consent oldalak angolok, miközben ugyanott a toastok egy része magyar.
- Az `sso_client` login képernyő magyar, a profiloldal és az SSO státuszoldal részben angol.
- A shell branding is eltér: `Central SSO control plane` vs `Connected SSO workspace`.

Felhasználói hatás:

- Bizalomvesztést okoz identity flow közben.
- A termék “egy rendszer” helyett két külön admin/UI világnak érződik.
- A felhasználó nehezebben különbözteti meg, mi rendszerüzenet, mi domainfogalom, és mi fejlesztői státuszszöveg.

Javaslat:

- Válasszatok egy elsődleges felhasználói nyelvet.
- Készítsetek közös copy glossary-t a két projektre.
- Egységesítsétek a shell, auth, profile, consent és CRUD oldalak szóhasználatát.

### 2. Közepes: a `sso_client` login flow egy felesleges plusz kattintást kér

Érintett helyek:

- [../sso_client/resources/js/Pages/Auth/Login.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Auth/Login.vue:14)
- [../sso_client/resources/js/Pages/Auth/Login.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Auth/Login.vue:40)
- [../sso_client/app/Http/Controllers/Auth/SsoAuthController.php](/c:/wamp64/www/sso/sso_client/app/Http/Controllers/Auth/SsoAuthController.php:28)

Megfigyelés:

- A login oldal lényegében csak egy átirányítás-előtti megálló.
- A felhasználónak külön rá kell kattintania a `Folytatas` gombra, miközben az oldal fő üzenete az, hogy most úgyis tovább fog menni a központi bejelentkezésre.

Felhasználói hatás:

- Lassítja a belépési folyamatot.
- Mobilon és gyakori SSO-használatnál felesleges súrlódást visz be.
- Olyan érzetet ad, mintha a rendszer még “félúton lenne”.

Javaslat:

- Alapértelmezésben induljon automatikus redirect rövid késleltetéssel.
- Maradjon egy jól látható “ha nem történt meg, kattints ide” fallback CTA.
- Hiba esetén ugyanitt lehet maradni kézi újrapróbálási lehetőséggel.

### 3. Közepes: a `sso_server` profiloldala vizuálisan és szerkezetileg kilóg a rendszerből

Érintett hely:

- [resources/js/Pages/Profile/Edit.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/Profile/Edit.vue:27)

Megfigyelés:

- A profiloldal még erősen Breeze-jellegű struktúrát használ (`gray`, `dark:*`, klasszikus `max-w-7xl`, egyszerű fehér kártyák), miközben a többi adminoldal már egy újabb, karakteresebb shell-re épül.

Felhasználói hatás:

- Törik a vizuális folytonosság.
- A profiloldal “másik alkalmazásnak” érződhet ugyanazon admin környezeten belül.
- Egy identity központnál ez különösen rossz, mert pont a fiók- és jelszófelületeknél lenne fontos a legnagyobb bizalomérzet.

Javaslat:

- Hozzátok át a profilt ugyanabba a page header + shell card + spacing rendszerbe, mint a többi admin oldalt.
- Takarítsátok ki a sötét mód maradványait, ha a designrendszer már nem ezt követi.
- A három szekció maradhat külön, de a környezeti vizuális nyelv legyen egységes.

### 4. Közepes: az `SSO Status` oldal túl technikai normál felhasználói navigációhoz

Érintett helyek:

- [../sso_client/resources/js/Composables/useNavigation.js](/c:/wamp64/www/sso/sso_client/resources/js/Composables/useNavigation.js:11)
- [../sso_client/resources/js/Pages/Sso/Status.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Sso/Status.vue:25)
- [../sso_client/resources/js/Pages/Sso/Status.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Sso/Status.vue:35)
- [../sso_client/resources/js/Pages/Sso/Status.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Sso/Status.vue:43)

Megfigyelés:

- Az oldal benne van a normál navigációban.
- A tartalma viszont erősen technikai: `Server base URL`, `Authorize endpoint`, `Token endpoint`, `Userinfo endpoint`, `Redirect URI`, `Scopes`, `Planned integration contract`.

Felhasználói hatás:

- Az oldal inkább implementációs vagy support nézet, mint napi felhasználói felület.
- Kevés magyarázatot ad arra, hogy a látott állapot mit jelent és mit tehet vele a felhasználó.
- A “status” oldal informatív, de kevéssé döntéstámogató.

Javaslat:

- Vagy tegyétek admin/debug oldallá,
- vagy fordítsátok át egy emberibb “Connection health” nézetté.
- A technikai mezők alá kerüljenek akciók és értelmező szövegek: mi rendben, mi hibás, mi a következő lépés.

### 5. Közepes: a `sso_server` consent oldal informatív, de még nem elég bizalomépítő

Érintett helyek:

- [resources/js/Pages/OAuth/Consent.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/OAuth/Consent.vue:45)
- [resources/js/Pages/OAuth/Consent.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/OAuth/Consent.vue:55)
- [resources/js/Pages/OAuth/Consent.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/OAuth/Consent.vue:62)

Megfigyelés:

- A képernyő jól listázza az alkalmazást és a scope-okat.
- Viszont kevés a bizalmi kapaszkodó: nem látszik például erősebben a cél domain, a kliens eredete, a környezet, vagy az, hogy a felhasználó pontosan “hová” fog visszakerülni.

Felhasználói hatás:

- A consent jogilag és technikailag rendben lehet, de kevésbé megnyugtató.
- Harmadik fél vagy több kliens esetén nehezebb gyorsan eldönteni, hogy ez valóban a várt alkalmazás-e.

Javaslat:

- Emeljétek be a kliens megbízhatósági jeleit.
- Jelenjen meg legalább a márkázott név mellett valamilyen eredet/domain kontextus.
- A CTA-k környezetében legyen rövid, emberközeli magyarázat arról, mi történik jóváhagyás vagy elutasítás után.

## Pozitív Megfigyelések

- A `sso_server` klienslistán a titok egyszeri megjelenítésére jó, világos figyelmeztetés van: [resources/js/Pages/Clients/Index.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/Clients/Index.vue:237).
- A `sso_server` admin listák használnak `Toast` és `ConfirmDialog` mintát, ami kiszámítható admin UX-et ad: [resources/js/Pages/Clients/Index.vue](/c:/wamp64/www/sso/sso_server/resources/js/Pages/Clients/Index.vue:222).
- A `sso_client` profiloldal rendelkezik dedikált loading állapottal és spinnerrel: [../sso_client/resources/js/Pages/Profile/Edit.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Profile/Edit.vue:214).
- A `sso_client` vállalatlista üres állapotot és megerősítő dialógust is használ: [../sso_client/resources/js/Pages/Companies/Index.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Companies/Index.vue:353), [../sso_client/resources/js/Pages/Companies/Index.vue](/c:/wamp64/www/sso/sso_client/resources/js/Pages/Companies/Index.vue:411).
- Mindkét projektben van külön auth shell, ami önmagában jó alap a bizalomépítéshez: [resources/js/Layouts/PublicAuthLayout.vue](/c:/wamp64/www/sso/sso_server/resources/js/Layouts/PublicAuthLayout.vue:24), [../sso_client/resources/js/Layouts/GuestLayout.vue](/c:/wamp64/www/sso/sso_client/resources/js/Layouts/GuestLayout.vue:12).

## Prioritási Javaslat

1. Nyelvi és terminológiai egységesítés a két rendszerben.
2. `sso_client` login flow egyszerűsítése automatikus redirecttel.
3. `sso_server` profiloldal UI-refaktor a jelenlegi shell-hez igazítva.
4. `sso_client` `SSO Status` oldal újrapozicionálása vagy emberibb átírása.
5. `sso_server` consent oldal bizalmi jeleinek bővítése.

## Audit Hatókör

Ez a jelentés statikus UX audit. Nem tartalmazott használhatósági tesztet valódi felhasználókkal, nem futott vizuális regressziós ellenőrzés, és nem történt frontend E2E verifikáció ebben a feladatban.
