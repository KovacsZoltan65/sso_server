PROFI GIT BACKUP PROMPT v3 (Codex)

Feladat: Készíts biztonságos, idempotens Git mentést a jelenlegi projektről, majd pushold a jelenlegi branch-re az origin remote-ra. A parancsokat ténylegesen futtasd. Ne commitolj üresen. A folyamat legyen óvatos, “AI-proof”, és álljon meg veszély esetén.

SZABÁLYOK

- A parancsokat ténylegesen futtasd.
- Ne commitolj, ha nincs változás.
- Ne pusholj, ha nincs origin remote.
- Ne pusholj detached HEAD állapotból.
- Ne használj force push-t.
- Ne squasholj.
- Ne módosíts history-t.
- Ha veszélyes, érzékeny, túl nagy vagy gyanús fájl kerülne a commitba, állj meg.

LÉPÉSEK

1. Repo ellenőrzés

- Futtasd:
  git rev-parse --is-inside-work-tree
- Ha nem Git repo:
  git init

2. Remote ellenőrzés

- Futtasd:
  git remote -v
- Ha nincs `origin`:
  írd ki:
  "Nincs origin remote beállítva, push nem lehetséges."
  és állj meg.

3. Branch ellenőrzés

- Futtasd:
  git branch --show-current
- Ha üres:
  írd ki:
  "Detached HEAD állapot, nem biztonságos push."
  és állj meg.

4. Sync ellenőrzés

- Futtasd:
  git fetch origin
  git status -sb
- Ha a branch behind állapotban van:
  írd ki:
  "A lokális branch le van maradva a remote mögött, automatikus push leállítva."
  és állj meg.

5. Working tree ellenőrzés

- Futtasd:
  git status --porcelain
- Ha nincs output:
  írd ki:
  "Nincs commitolandó változás."
  és állj meg.

6. Rövid áttekintés

- Futtasd:
  git status
  git diff --stat

7. STOP-lista ellenőrzés

- Vizsgáld a módosult / új / törölt fájlokat ezekre:
    - .env, .env._, _.key, id_rsa, _.pfx, _.p12
    - node_modules/, vendor/
    - storage/logs/, storage/_.sql, _.dump, _.zip, _.tar, \*.gz
    - integrity-\*.json
    - public/build/, dist/, coverage/, playwright-report/, test-results/, .e2e-runtime/
    - nested .git vagy submodule jellegű gyanús mappák
- Ha találsz ilyet:
    - írd ki listában
    - javasolj `.gitignore` vagy eltávolítási lépést
    - állj meg, és ne commitolj automatikusan

8. Stage

- Futtasd:
  git add -A

9. Commit message generálás

- A commit üzenet magyar nyelvű legyen.
- Legyen rövid, max ~90 karakter.
- Formátum:
  [AUTO] Backup: <tömör összefoglaló>
- Preferált prefixek összefoglalóban:
    - frontend: ...
    - backend: ...
    - tests: ...
    - ci: ...
    - workflow: ...
- Ha nem állapítható meg jól:
  [AUTO] Backup: project snapshot

10. Commit

- Futtasd:
  git commit -m "<GENERÁLT ÜZENET>"
- Ha nincs mit commitolni:
  írd ki:
  "Nincs commitolandó változás (staging üres)."
  és állj meg.

11. Upstream ellenőrzés

- Futtasd:
  git branch -vv
- Ha az aktuális branch nem trackel origin-t:
  git push -u origin <branch>
- Ha trackeli:
  git push origin HEAD

12. Végső ellenőrzés

- Futtasd:
  git log --oneline -1
  git status
  git remote -v

KIMENET

A végén írd ki:

- Branch: <branch>
- Commit: <hash> <subject>
- Changed files: <N files changed, +insertions, -deletions>
- File summary: <new / modified / deleted counts ha elérhető>
- Push: sikeres / sikertelen
- Remote: origin (<url>)

MEGJEGYZÉS

- Ne találj ki remote-ot.
- Ne próbálj konfliktust automatikusan megoldani.
- Ha a remote ahead/behind állapot vagy veszélyes fájl miatt megállt a folyamat, ezt világosan írd ki.
