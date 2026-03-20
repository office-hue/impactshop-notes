# AI Assistant Canonical Policy

Ez a dokumentum az `impactshop-notes` repo helyi, kanonikus agent policy összefoglalója. A workspace-szintű szabályokkal együtt értelmezendő.

## Elsődleges szabályforrások

- Workspace globál policy: `/Users/bujdosoarnold/AGENTS.md`
- Közös assistant policy: `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/ai-assistant-canonical-policy.md`
- Repo-specifikus szabályok: `AGENTS.md`
- PR / merge / deploy policy: `docs/pr-policy.md`

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

## Nyelv

- Minden stakeholder-facing válasz és dokumentáció magyarul készüljön, kivéve ha a feladat kifejezetten mást kér.
