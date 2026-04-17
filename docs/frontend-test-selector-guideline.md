# Frontend Test Selector Guideline

Rovid iranyelv az `sso_server` frontend tesztjeihez.

## 1) Preferalt selector hierarchia

1. `semantic/role` vagy nativen stabil hook (`form`, `#id`, input `name`)  
2. Stabil attribútum selector (`data-toolbar-action`, `data-primary-row-action`)  
3. i18n-alapu assertion (`en[...]` / `hu[...]` kulcsbol)  
4. `data-testid` csak celzott shared pontokon

## 2) Mikor melyiket hasznaljuk

- `semantic/role`: form submit, input mezok, egyszeru strukturak.
- Stabil attribútum: shared komponensek mar meglevo publikus hookjai.
- i18n assertion: user-facing copy ellenorzese, hardcoded szoveg helyett.
- `data-testid`: ha a selector kulonben locale/copy/UI-library implementacio-fuggo lenne.

## 3) `data-testid` szabaly

- Nem tesszuk tele az oldalt `data-testid` attribútumokkal.
- Elonyben a shared/gyakran tesztelt komponensek:
  - `RowActionMenu`
  - `LanguageSwitcher`
  - `AdminTableToolbar` (ha uj action trigger kell)
- Oldalszintre csak akkor keruljon, ha nincs stabil semantic vagy shared attribútum.

## 4) Anti-pattern lista

- `findAll('button').find(button.text() === '...')` locale-fuggo flow-kban.
- `wrapper.text().toContain('<hardcoded copy>')` i18n kulcs helyett, ha nem kifejezett copy snapshot a cel.
- PrimeVue belso DOM/klassz struktura kozvetlen tesztelese, ha van stabilabb hook.
- Tobb, versengo selector ugyanarra az actionre.

## 5) PrimeVue/shared komponens minta

- Action trigger: elsodlegesen `data-*` hook.
- Popup/menu ellenorzes: szerkezeti allitas (pl. elem letezik, gombszam), ne labelszoveg.
- Copy ellenorzes: i18n kulcs alapu assertion.
