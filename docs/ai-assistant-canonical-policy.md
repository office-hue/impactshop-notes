# AI Assistant Canonical Policy

Ez a dokumentum az `impactshop-notes` repo helyi, kanonikus agent policy összefoglalója. A workspace-szintű szabályokkal együtt értelmezendő.

## Elsődleges szabályforrások

- Workspace globál policy: `/Users/bujdosoarnold/AGENTS.md`
- Közös assistant policy: `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/ai-assistant-canonical-policy.md`
- Repo-specifikus szabályok: `AGENTS.md`
- PR / merge / deploy policy: `docs/pr-policy.md`
- Impact Challenge baseline: `docs/impact-challenge-canonical-baseline.md`
- Public pages baseline: `docs/public-pages-canonical-baseline.md`

Ha eltérés van a lokális asszisztens-config és a repo szabályok között, a fenti sorrend az elsődleges.

## Session workflow

### Session elején

```bash
cd /Users/bujdosoarnold/Developer/GitHub/ai-agent
npm run memory:pre-task -- --task "<rövid feladatleírás>"
```

Ha a feladat a guardrendszert, protected lane-t, Impact Challenge / Impact Shop surface-t vagy offerwall protected runtime-ot érinti, ezt a célzott betöltőt kell előnyben részesíteni:

```bash
cd /Users/bujdosoarnold/Developer/GitHub/ai-agent
npm run memory:impact-challenge-load -- --task "<rövid feladatleírás>"
```

Ha a kontextus stale vagy hiányos:

```bash
cd /Users/bujdosoarnold/Developer/GitHub/ai-agent
npm run memory:full-sync -- --task "<rövid feladatleírás>"
```

### Session végén

```bash
cd /Users/bujdosoarnold/Developer/GitHub/ai-agent
npm run memory:v2:session-save -- --summary "<mi történt>" --tags "impactshop-notes,tag1"
npm run memory:full-sync -- --task "<lezáró összefoglaló>"
```

## Repo-specifikus működés

- Session elején és végén `notes.md` frissítése kötelező.
- A `conversation-summaries/` és kapcsolódó operatív dokumentáció naprakészen tartandó.
- Bastion guardrail mindig kötelező; ha nem egyértelmű, meg kell állni és jóváhagyást kérni.
- Az Impact Challenge teljes védett köre különösen érzékeny rendszernek minősül: elsődleges fejlesztési irány csak új, additív kóddal megengedett.
- Az `Impact Shop` és a `Profil / Üzenetek / Pontok` surface-ek ugyanilyen max-védett kategóriába tartoznak; ezekhez is csak additív-first megoldás vagy explicit protected override út engedett.
- Meglévő Impact Challenge kód, route, bekötés, pontszámítási út, adatmodell vagy workflow csak külön, explicit jóváhagyással módosítható, és csak akkor, ha nincs azonosan jó additív megoldás.
- A max-védett surface-ek authoritative leírása:
  - `docs/critical-surface-inventory.md`
  - `docs/impact-shop-profile-protected-perimeter.md`
  - `docs/impact-challenge-protected-perimeter.md`
- A guide rendszer teljes köre (`impactshop-ngo-guides.php` + `wp-content/mu-plugins/impactshop-ngo-guides/**`) külön beton protected perimeter: route, HTML guide, fordítás, jogi asset, PDF és renderelt output egyaránt csak explicit felhasználói engedéllyel módosítható.
- A publikus információs IA kanonikus baseline-ja a `docs/public-pages-canonical-baseline.md`. Ez lefedi a `/rolunk/`, `/cegeknek/`, `/befektetoknek/`, `/partner-api/`, `/ngo-guides/`, `/ngo-guides/ngo-card/`, `/ngo-guides/impact-shop/`, `/ngo-guides/impact-challenge/` és `/ngo-guides/jogi-dokumentumok/` route-okat.
- A `partner-api` forrásai jelenleg repo-határon kívül élnek (`/Users/bujdosoarnold/Developer/GitHub/partner-docs.html`, `/Users/bujdosoarnold/Developer/GitHub/partner-docs-en.html`), ezért itt kétlépcsős védelem az előírt: kötelező backup + rollback, valamint fizikai read-only célállapot. Ezt nem szabad repo-szintű guard enforcementként félrekommunikálni.
- A JYSK riport külön név szerint is max-védett guide-surface: `/jysk-riport/`, `/jysk-riport/?print=1` és `/jysk-riport.data.json` ugyanennek a protected perimeternek a része, így tartalmi, route-, print- vagy adatpayload-változás csak explicit engedéllyel, protected change recorddal és guide smoke scope-pal vihető át.
- Nem megengedett olyan automatika, deploy-lépés vagy szinkron, amely guide tartalmat felülírhat, átnevezhet, lecserélhet vagy más forrásból visszaállíthat explicit engedély nélkül.
- Az Impact Challenge teljes kanonikus állapotát a `docs/impact-challenge-canonical-baseline.md` rögzíti; ettől eltérő viselkedés, route, tartalom vagy fizikai jogosultsági állapot regressziónak számít, ha nincs rá külön jóváhagyás.
- Védett fájl módosítása előtt kötelező a koherencia vizsgálat, a kockázatelemzés és az érintett funkciók listája.
- Védett fájl módosítása után kötelező külön manuális UI checklistet adni a felhasználónak, hogy mit ellenőrizzen végig a weboldalon.
- A kötelező részletes ellenőrzési rend: `docs/protected-file-change-checklist.md`
- Guard- vagy protected-modell változtatásnál kötelező külön parity ellenőrzést végezni a lokális wrapper/hook és a GitHub oldali CI guard között.
- Protected env párokat (`.deploy.production.env` + `.deploy.staging.env`) ugyanannak a lane-nek kell tekinteni; külön staging vagy külön review nem megengedett.
- Review komment javítása után kötelező újraellenőrizni a teljes guard-kört és a nyitott review threadeket, mielőtt merge vagy deploy történik.
- Harmadik fél inventory/cache integrációnál az üres válasz nem tekinthető automatikusan stabil valóságnak; stale empty cache gyanú esetén forced refresh / retry / rövid TTL szükséges.

## Git / PR / merge / deploy

- Közvetlen `main/master` commit és push tiltott.
- Új munka feature/worktree ágról indul.
- Kötelező ellenőrzés push előtt:

```bash
bash scripts/git-health-check.sh
bash scripts/safe-repo-audit.sh --strict --mode push
```

- Deploy csak guardolt útvonalon és csak merge-elt főágból mehet.
- Preferált deploy útvonal: `bin/impactshop-guard-deploy.sh`.
- Deploy után guard és smoke ellenőrzés kötelező, eredménnyel együtt dokumentálva.
- Ha a kanonikus guard deploy útvonal maga hibás root/branch kötés, hiányzó script vagy más guard-infra hiba miatt nem használható, ezt explicit nem-kanonikus kivételként kell kimondani; ilyenkor csak szűk, auditált helyreállítás engedett pontos fájllistával, előzetes backuppal, távoli rollback scripttel, cache/rewrite flush-sel, utólagos live verifikációval és külön follow-up feladattal a kanonikus deploy path javítására.

## Védett műveletek

- Teljes repo scan / tömeges rsync csak külön jóváhagyással.
- Közvetlen production remote write (`scp`, `rsync`, `ssh cp`, kézi chmod+copy`) tiltott; ha kivétel kell, csak guardolt emergency wrapperrel és auditált indokkal mehet.
- Ha a guardolt emergency wrapper dokumentáltan hiányzik vagy hibás, ezt nem szabad csendben megkerülni: a kivételes kézi helyreállítás csak ideiglenes incidensútvonal lehet, és külön dokumentálni kell, hogy nem kanonikus deploy történt.
- Védett / bastion állományhoz csak backup + rollback tervvel szabad nyúlni.
- Védett fájl backup retention maximum 2 nap, kivéve ha külön dokumentált eltérés van.
- Fizikai írásvédettség célállapota: a production Impact Challenge MU-plugin állományok read-only (`0444`) jogosultság alatt maradnak, és csak célzott deploy idejére tehetők írhatóvá.
- A guide-rendszer fizikai célállapota: lokálban és productionön a guide fájlok `0444`, a guide könyvtárak `0555`, és ez az állapot minden deploy után kötelezően visszaállítandó.
- A JYSK riporthoz tartozó guide fájlok és adatfájlok ugyanebbe a read-only célállapotba tartoznak; a `jysk-riport` route, print nézet és JSON payload nem kezelhető “könnyű statikus oldalként”.

## Nyelv

- Minden stakeholder-facing válasz és dokumentáció magyarul készüljön, kivéve ha a feladat kifejezetten mást kér.
