# Bástya védelem kiegészítés – végleges terv

## Cél
A guard/deploy folyamatot úgy erősítjük meg, hogy:
- ne történhessen “rossz repo / rossz útvonal” miatti téves hiányzás,
- a védett fájlok forrása (source of truth) mindig egyértelmű legyen,
- a guard hibák gyorsan visszafejthetők legyenek,
- párhuzamos futások és config‑drift ne okozzanak káoszt.

## Hatókör
- Guard konfiguráció és deploy folyamatok: `docs/impactshop-guard-config.json`, `bin/impactshop-guard-deploy.sh`.
- Repo‑azonosítás és útvonal‑lock a guard előtt.
- Védett fájlok meta‑adata (owner_repo/root/branch) a guard listában.
- Snapshot meta és integritás ellenőrzés.
- Opcionális: `impactall` preflight script bevezetése (guard futások előtt hívható).
- Árukereső autobanner/ads-watch flow védelme (deeplink engedélyezett útvonal + kliens‑oldali intercept tiltás).

## Kiinduló probléma (tanulság)
- A guard “hiányzó védett fájl” hibát dobott, mert a lista olyan fájlokat tartalmazott, amelyek **nem ebben a repo‑ban** voltak.
- A guard log másik útvonalat írt (Developer vs Documents), így **másik repo példányból** dolgozott.
- Nem volt rögzítve, hogy a védett fájl **melyik repo** “source of truth”.

## Végleges biztosítékok

### 1) Repo‑útvonal lock + repo‑azonosítás (hard fail)
**Cél:** azonnal megállítani a guardot, ha rossz repo példányból fut.
- A config tartalmazza:
  - `repo.root` (abszolút, kanonizált útvonal)
  - `repo.remote` (origin URL)
  - `repo.branch` (branch név)
- Guard induláskor ellenőrzi:
  - `realpath(ROOT_DIR)` == `repo.root`
  - `git remote get-url origin` == `repo.remote`
  - `git rev-parse --abbrev-ref HEAD` == `repo.branch`
- Eltérés → **hard fail**, részletes hibaüzenettel.

### 2) Védett fájlok source‑of‑truth meta
**Cél:** ne legyen többé “nem itt van” félreértés.
- `protected_files` lista objektumokra vált:
  - `path`
  - `owner_repo`
  - `owner_root`
  - `owner_branch`
- Ha egy fájl hiányzik:
  - ha owner eltér → “wrong repo” hiba + mutatja, hol kell lennie
  - ha owner egyezik → “missing protected file” hiba

### 3) Repo‑sanity preflight blokk
**Cél:** még a guard előtt kiszűrni a futtatási elcsúszásokat.
- Új script: `bin/impactshop-guard-preflight.sh`
- Ellenőrzi:
  - repo root / remote / branch
  - protected fájlok létezése és path canonicalization
  - opcionális: `git status` figyelmeztetés
- Guard deploy ezt **mindig** lefuttatja.
- `impactall` előtt is hívható (nem kötelező, de javasolt).

### 4) Snapshot meta + integritás
**Cél:** visszanézéskor pontos forrás és tartalom.
- Snapshot mellé `snapshot.meta.json`:
  - repo root/remote/branch
  - timestamp
  - protected fájlok listája + SHA256 hash
- Visszaállításkor nem csak létezés, hanem integritás is ellenőrizhető.

### 5) Lockfile / párhuzamos futások ellen
**Cél:** race condition elkerülése.
- `.codex/guard-events/.guard.lock` lockfile
- Stale lock cleanup (PID/mtime ellenőrzés)
- Guard csak lock acquire után fut tovább.

### 6) Config checksum + validáció
**Cél:** config‑drift és hibás JSON kiszűrése.
- `docs/impactshop-guard-config.sha256` fájl
- Guard induláskor ellenőrzi a checksumot (mismatch → hard fail)
- Minimális séma‑validáció a szükséges mezőkre

### 7) Symlink/path canonicalization
**Cél:** symlinkes áttolás megakadályozása.
- Guard realpath‑tel ellenőrzi, hogy a protected fájl **a repo root alatt** van.
- Ha nem, hard fail.

### 8) Hibaüzenetek és runbook rövidítés
**Cél:** gyors döntés és javítás.
- Hibák tartalmazzák:
  - melyik fájl hiányzik
  - melyik repo/útvonal az elvárt
- Rövid runbook blokk a `notes.md`‑ben (fix utasításokkal)

## Koherencia és biztonsági ellenőrzés
**Megállapítások:**
1. A “missing file” és “wrong repo” kategória korábban össze volt mosva → külön kezeljük.
2. Az útvonal‑lock hiánya volt a fő kockázat → hard lock beépítve.
3. A védett fájlok forrása nem volt rögzítve → owner meta bevezetve.
4. Párhuzamos futás és config‑drift kockázat → lockfile + config checksum.

**Átvezetett javítások:**
- Repo root/remote/branch lock (1. pont)
- Owner meta + wrong‑repo hiba (2. pont)
- Repo sanity preflight (3. pont)
- Snapshot meta + hash (4. pont)
- Lockfile (5. pont)
- Config checksum + minimal schema validation (6. pont)
- Path canonicalization (7. pont)

## Implementációs lépések
1. `docs/impactshop-guard-config.json` meta mezők hozzáadása (repo + owner meta).
2. `docs/impactshop-guard-config.sha256` létrehozása (checksum lock).
3. `bin/impactshop-guard-preflight.sh` létrehozása (repo sanity).
4. `bin/impactshop-guard-deploy.sh` frissítése:
   - preflight futtatás
   - repo lock + config checksum
   - owner mismatch kezelése
   - snapshot meta + hash
   - lockfile
5. `notes.md` rövid runbook és változásnapló frissítése.

## Rögzített védett fájllista (source of truth)
- A végleges, aktuális védett fájllista **egy helyen** van rögzítve: `docs/impactshop-guard-config.json`.
- A bástya védelem ezt a listát tekinti **kanonikusnak**; minden változtatás itt indul és itt kerül auditálásra.
- Ha új, végleges Impact Shop fájl kerül be vagy kivezetésre kerül, kizárólag a guard config frissíthető (indok + notes.md bejegyzés kötelező).

## Impact Challenge védelmi perem
- A védelem kiterjed az Impact Challenge teljes működési körére: ads-watch, auto-banner, offerwall, NGO kiválasztás és NGO-card, leaderboard, identity, pont- és szavazatmotor, szavazatvásárlás, quarter-close, redirect/go/go-deal bekötések, kapcsolódó event donation widgetek, PWA shell/push, valamint az ezekhez kapcsolódó guard/deploy konfiguráció.
- A route, bekötési pont, adatrögzítés, workflow és pipeline szintű védelem kanonikus listája a `docs/impactshop-guard-config.json` `protected_files` tömbje.
- A célállapot nem csak logikai, hanem fizikai védelem is: productionön ezek a fájlok alapállapotban read-only (`0444`) jogosultsággal maradnak.
- Új fejlesztési igény elsődleges megoldási útja additív, új kód legyen (új modul, új wrapper, új hook, új különálló integrációs réteg).
- Meglévő Impact Challenge kód módosítása csak külön, explicit jóváhagyással engedett, és csak akkor, ha nincs azonosan jó additív megoldás.
- Protected-file módosítás előtt kötelező a koherencia vizsgálat, kockázatelemzés és érintett funkciólista.
- Protected-file módosítás után kötelező a post-merge/deploy ellenőrzési lista és a felhasználónak szóló kézi UI checklist.
- A kötelező eljárás külön dokumentuma: `docs/protected-file-change-checklist.md`

## Árukereső autobanner véglegesített flow
- **Állapot:** Fillout → `/go-deal` → Dognet deeplink → Árukereső termékoldal.
- **Kliens tiltás:** `impact-arukereso-deeplink-fix.php` **OFF** (interceptor nem futhat).
- **Deeplink átengedés:** `impact-arukereso-guard.php`, `impact-cid-arukereso-fix.php`, `sharity-impact-compat.php`.
- **Dognet guard:** `impact-combat-pack.php` nem blokkolhat Árukeresőt.
- **Bástya elv:** a fenti fájlok mind védettek; módosítás csak guard + indok mellett.

## JYSK szavazás véglegesített flow
- **Állapot:** mobil CTA/tally/UI fixek + egymezős NGO keresés + ID panel eltávolítva (csak mini widget marad).
- **Video:** kampány `video_url` MP4-re állítva (Komárom/Mezőkövesd).
- **Esély/sorsolás:** 3 nyereményes odds képlet + sorsolás max 3 nyertes (pseudo csak egyszer).
- **Bástya elv:** `impactshop-vote-jysk.php` + `impactshop-vote-jysk.js` védett; módosítás csak guard + indok mellett.

## Cégjelző API + hírlevél szolgáltatás (véglegesített)
- **Cégjelző API:** `impactshop-cegjelzo.php` védett; REST export `impact/v1/cegjelzo/ngo-registry`, heti sync cron.
- **Hírlevél (Brevo):** API kulcs + sender env fájlok csak secrets-ben (`~/.impact-secrets/env.d/capi.env`, `~/.impact-secrets/env.d/ai-agent.env`); nem kerülhet repo-ba.
- **Bástya elv:** a fenti komponensek módosítása csak guard + indok mellett.

## ID widget + ID panel (véglegesített)
- **ID panel:** `impactshop-identity-panel.php` + `impactshop-identity-panel.js` védett; JYSK vote modulból kivéve, csak dedikált profil oldalon marad.
- **ID widget:** kis widget továbbra is marad (szavazás/ads-watch oldalakhoz).
- **Bástya elv:** az ID panel + widget módosítása csak guard + indok mellett.

## Elfogadási kritérium
- Guard rossz repo esetén **hard fail** és pontos hibaüzenet.
- Guard hiányzó fájlnál **külön jelzi**, ha másik repo a tulajdonos.
- Guard snapshot meta tartalmazza a hash‑eket.
- Lockfile megakadályozza a párhuzamos futást.
- Config checksum mismatch esetén guard nem fut tovább.
