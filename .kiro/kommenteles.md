# SSO SERVER – PHPDoc + Larastan kompatibilis kommentelési audit és javítás

Projekt:
Laravel 13 + PHP 8.4 + SSO SERVER

Feladat:
Auditáld és javítsd a backend kód kommentelését úgy, hogy:

- PHPDoc kompatibilis legyen
- Larastan (PHPStan) kompatibilis legyen
- IDE támogatás (autocomplete, type inference) javuljon
- a kód olvashatósága nőjön
- ne legyen komment spam
- Magyar nyelvű kommenteket használj

FONTOS:

- a cél nem a mennyiség, hanem a minőség
- csak ott kommentelj, ahol értelme van
- a kommentek legyenek pontosak és típushelyesek
- a kód maradjon tiszta, ne legyen túlmagyarázva

---

## FŐ CÉLOK

1. PHPDoc kompatibilis típusleírások
2. Larastan static analysis támogatás
3. IDE autocomplete javítása
4. Service / Repository / DTO réteg típusbiztossá tétele

---

## KOMMENTELÉSI SZABÁLYOK

### 1) Kötelező PHPDoc helyek

Adj PHPDoc-ot minden:

- Controller metódushoz
- Service metódushoz
- Repository metódushoz
- Policy metódushoz
- DTO / Data class-hoz
- komplex Model metódushoz
- helper / utility metódushoz

---

### 2) Metódus PHPDoc minta

```php
/**
 * Új token-szabályzat létrehozása.
 *
 * @param array<string, mixed> $data
 * @return \App\Models\TokenPolicy
 */
```
