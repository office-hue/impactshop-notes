# PR és deploy szabályok

Forrásmunkához közvetlen Git használható, dedikált feature/worktree ágon.
Engine, capsule, selector, taskpin, repin, modellazonosító és DEV-admission
nem feltétel. A hooktelepítés opcionális; alapból csak előkészítés, explicit
apply során saját backup/CAS/rollback védi. Kötelező commit-trailer nincs.

Commit előtt vizsgáld a pontos saját staged diffet és a valóban érintett
termék-/biztonsági teszteket. Idegen unstaged/untracked WIP-et őrizz meg.
A natív titokscan és product guard külön mérési eredmény; hiányzó opcionális
helper vagy nem futtatott check nem PASS és nem jogosultság.
A PR leírás célja, kockázata, valós ellenőrzése és rollbackje legyen érthető;
a szöveges checklist tanács, nem publikálási permit.

Közvetlen main/master commit/push továbbra sem engedett. Main fetch,
integration vagy merge előtt szűk quiet window szükséges, utána azonnali
elengedéssel. Érvényes külső felhatalmazás megmarad; új élő határt ne következtess.

## Impact Challenge védett útvonal szabály

- A teljes Impact Challenge perem bástyavédett és írásvédett területnek számít.
- Ide tartozik minden releváns funkció, útvonal, bekötés, kapcsolódási pont, nyilvántartás, adatírási mód, workflow és pipeline.
- Az Impact Challenge kanonikus baseline-ja: `docs/impact-challenge-canonical-baseline.md`. Impact Challenge PR / merge / deploy esetén ehhez kell mérni az eltérést.
- Külön beton protected perimeternek számít a teljes guide rendszer: `impactshop-ngo-guides.php` és `wp-content/mu-plugins/impactshop-ngo-guides/**`.
- Ezen belül a JYSK riport külön név szerint max-védett surface: `/jysk-riport/`, `/jysk-riport/?print=1`, `/jysk-riport.data.json`.
- Guide route / guide HTML / guide asset / guide PDF / guide fordítás módosítása csak explicit felhasználói engedéllyel mehet; sem PR, sem merge, sem deploy, sem automatika nem írhatja felül ezeket hallgatólagosan.
- JYSK riport touch esetén a protected smoke scope-nak ki kell terjednie a route renderre, a print nézetre és a JSON payloadra is; ezt nem lehet egyszerű guide-copyként vagy statikus assetcseréként kezelni.
- A default fejlesztési stratégia: `new code first`.
- Legacy módosítás csak külön, kifejezett jóváhagyással és csak akkor engedhető, ha nincs azonos minőségű additív megoldás.
- Protected file módosítás előtt kötelező a `docs/protected-file-change-checklist.md` szerinti koherencia és kockázati felmérés.
- Protected file módosítás után kötelező a funkció-ellenőrzési kör és a felhasználónak szóló UI checklist átadása.

## Valódi biztonsági és deploy-határ

- A protected-touch ellenőrzés és product commit-lane repo-local marad;
  nem kér DEV-controller állapotot. Idegen WIP-et nem stage-el vagy töröl.
- A `.deploy.production.env` és `.deploy.staging.env` párt együtt kezeld.
- Deploy csak merge-elt főágból, `bin/impactshop-guard-deploy.sh` útvonalon.
  A DEV-kivezetés nem deploy-engedély; backup, célpont/CAS, rollback és
  tényleges guard/smoke/read-only visszaállítás változatlanul szükséges.
- Guide/JYSK/Impact Challenge írásvédelem és explicit változtatási jogosultság
  megmarad. Közvetlen távoli írás nem helyettesíti a guarded deployt.
- Guard változásnál helyi/CI parity és érintett negatív teszt szükséges.
- Harmadik fél üres/stale inventory-válaszát ne tekintsd automatikusan valóságnak.
- Bizonytalan külső eredmény után előbb readback; nincs vak retry vagy fizetős próba.

A régi `Memory-ID`, checklist, task marker, engine/hook grant leírások
történetiek. A forrásmódosítás nem jelenti, hogy a telepített hosthookok,
régi worktree-k, futó chatek vagy GitHub protection már átálltak.
