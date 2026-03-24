PROFI GIT BACKUP PROMPT v2 (Codex)

Feladat: Készíts biztonságos, “AI-proof” Git mentést a jelenlegi projektről, majd pushold a jelenlegi branch-re az origin remote-ra. A folyamat legyen idempotens, ne commitoljon üresen, és adjon részletes összefoglalót.

SZABÁLYOK

- A parancsokat TÉNYLEGESEN futtasd, ne csak felsorold.
- Ne commitolj, ha nincs változás.
- Figyelmeztess, ha érzékeny vagy túl nagy fájlok kerülnek a commitba (.env, storage dumps, node_modules, vendor, integrity json, large logs).
- A végén adj rövid summary-t: branch, commit hash, fájlszám, insertions/deletions, push státusz.

LÉPÉSEK

1. Repo ellenőrzés

- Ha nincs Git repo:
  git init

2. Remote ellenőrzés (kötelező)

- Listázd:
  git remote -v
- Ha nincs `origin`:
  ÁLLJ MEG és írd ki:
  "Nincs origin remote beállítva, push nem lehetséges."
  (Ne próbálj meg remote-ot kitalálni.)

3. Aktuális branch meghatározása

- Futtasd:
  git branch --show-current
- Ha üres:
  írd ki és állj meg:
  "Detached HEAD állapot, nem biztonságos push."

4. Working tree ellenőrzés

- Futtasd:
  git status --porcelain
- Ha nincs output:
  írd ki:
  "Nincs commitolandó változás."
  és állj meg.

5. Változások áttekintése (rövid, de informatív)

- Futtasd:
  git status
  git diff --stat

6. Védő ellenőrzések (STOP list)

- Keress a változások között veszélyes mintákat:
    - .env, .env._, _.key, id*rsa, *.pfx, \_.p12
    - node_modules/, vendor/
    - storage/logs/, storage/_.sql, _.dump, _.zip, _.tar, \*.gz (ha backup)
    - integrity-\*.json (ha nagy report)
- Ha ilyet találsz:
    - Írd ki listában
    - Javasolj gitignore-t / eltávolítást
    - ÁLLJ MEG, és ne commitolj automatikusan.

7. Stage

- Add hozzá a változásokat:
  git add -A

8. Commit message generálás (diff alapján)

- Gyűjts rövid leírást a módosított fájlokból:
    - frontend (resources/js): “frontend: ...”
    - backend (app/\*): “backend: ...”
    - routes/tests: “tests/routes: ...”
- Készíts 1 soros, max ~90 karakter commit üzenetet:
  Formátum:
  [AUTO] Backup: <tömör összefoglaló>
- Ha nem tudsz jó összefoglalót:
  [AUTO] Backup: project snapshot

9. Commit

- Futtasd:
  git commit -m "<GENERÁLT ÜZENET>"
- Ha a commit azt mondja, hogy nincs mit commitolni:
  írd ki:
  "Nincs commitolandó változás (staging üres)."
  és állj meg.

10. Upstream ellenőrzés

- Futtasd:
  git branch -vv
- Ha az aktuális branch nem trackel origin-t:
  állítsd be:
  git push -u origin <branch>
  (Ekkor ez a push egyben fel is tolja.)

11. Push (kötelező, ha van origin)

- Ha van upstream:
  git push origin HEAD

12. Végső ellenőrzés

- Futtasd:
  git log --oneline -1
  git status

KIMENET (a futás végén írd ki)

- Branch: <branch>
- Commit: <hash> <subject>
- Changed files (stat alapján): <N files changed, insertions/deletions ha elérhető>
- Push: sikeres / sikertelen (hibaüzenettel)
- Remote: origin (url)

MEGJEGYZÉS

- Ne futtass force push-t.
- Ne squasholj.
- Ne módosíts history-t.
