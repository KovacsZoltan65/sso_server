# Lokalizációs glossary

## Elsődleges elv
- A felhasználói felület elsődleges validációs nyelve magyar.
- Az angol nyelv teljes értékű fallback és másodlagos UI nyelv.
- Frontend és backend ugyanabból a `lang` könyvtárból dolgozik.

## Tudatosan angolul hagyott fogalmak
- `Token`
- `Access token`
- `Refresh token`
- `Scope`
- `PKCE`
- `Redirect URI`
- `Client ID`
- `Client secret`

## Magyarított fogalmak
- `Profile` → `Profil`
- `Save` → `Mentés`
- `Delete` → `Törlés`
- `Cancel` → `Mégse`
- `Audit Logs` → `Audit naplók`
- `Connection Health` → `Kapcsolat állapota`
- `Remembered Consents` → `Megjegyzett consentek`
- `Token Policies` → `Token szabályzatok`
- `Client Access` → `Klienshozzáférés`

## Kerülendő vegyes formák
- `Profile / Profil` ugyanazon nézeten belül
- `Client / Kliens` keverése ugyanabban a modulban
- `Delete Selected / Kijelöltek törlése`
- `Connection Health / Kapcsolat állapota`

## Kulcskonvenció
- Közös elemek: `common.*`
- Toolbar és shared UI: `toolbar.*`, `topbar.*`, `page_header.*`
- Navigáció: `navigation.*`
- Auth: `auth.*`
- Modulok: `clients.*`, `tokens.*`, `remembered_consents.*`, `profile.*`
