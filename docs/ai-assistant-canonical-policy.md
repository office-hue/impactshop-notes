# AI Assistant Canonical Policy

Ez a dokumentum az `impactshop-notes` repo helyi, kanonikus agent policy összefoglalója. A workspace-szintű szabályokkal együtt értelmezendő.

## Elsődleges szabályforrások

- Workspace globál policy: `/Users/bujdosoarnold/AGENTS.md`
- Közös assistant policy: `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/ai-assistant-canonical-policy.md`
- Repo-specifikus szabályok: `AGENTS.md`
- PR / merge / deploy policy: `docs/pr-policy.md`
- Impact Challenge baseline: `docs/impact-challenge-canonical-baseline.md`

Ha eltérés van a lokális asszisztens-config és a repo szabályok között, a fenti sorrend az elsődleges.

## Session workflow

### Session elején

```bash
cd /Users/bujdosoarnold/Developer/GitHub/ai-agent
npm run memory:pre-task -- --task "<rövid feladatleírás>"
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
- Meglévő Impact Challenge kód, route, bekötés, pontszámítási út, adatmodell vagy workflow csak külön, explicit jóváhagyással módosítható, és csak akkor, ha nincs azonosan jó additív megoldás.
- A guide rendszer teljes köre (`impactshop-ngo-guides.php` + `wp-content/mu-plugins/impactshop-ngo-guides/**`) külön beton protected perimeter: route, HTML guide, fordítás, jogi asset, PDF és renderelt output egyaránt csak explicit felhasználói engedéllyel módosítható.
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

## Védett műveletek

- Teljes repo scan / tömeges rsync csak külön jóváhagyással.
- Védett / bastion állományhoz csak backup + rollback tervvel szabad nyúlni.
- Védett fájl backup retention maximum 2 nap, kivéve ha külön dokumentált eltérés van.
- Fizikai írásvédettség célállapota: a production Impact Challenge MU-plugin állományok read-only (`0444`) jogosultság alatt maradnak, és csak célzott deploy idejére tehetők írhatóvá.
- A guide-rendszer fizikai célállapota: lokálban és productionön a guide fájlok `0444`, a guide könyvtárak `0555`, és ez az állapot minden deploy után kötelezően visszaállítandó.

## Nyelv

- Minden stakeholder-facing válasz és dokumentáció magyarul készüljön, kivéve ha a feladat kifejezetten mást kér.
