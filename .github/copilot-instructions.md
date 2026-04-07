<!-- BEGIN CANONICAL ASSISTANT POLICY -->

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

<!-- END CANONICAL ASSISTANT POLICY -->

# Copilot Workflow Guardrails

When the task involves code changes, refactor, debugging, or PR review:

1. Call `dev_memory_brief` first with a short task description.
2. Use returned memory/file context before proposing edits.
3. If memory is stale or missing, call `dev_memory_stats` and note it explicitly.
4. Keep answers aligned with repository guardrails (`pr-policy`, hooks, safe audit).

If MCP tool calls fail, continue in fail-open mode, but clearly state that memory context was unavailable.

<!-- BEGIN AUTO DEV MEMORY CONTEXT -->

# Context Pack: codex/ic-bastion-fail-closed

Generated: 2026-04-06T18:07:20.112Z
Repo: /Users/bujdosoarnold/Developer/GitHub/impactshop-notes
Task: small refactor
Memory count: 2
File chunk count: 8

## Memory hits

- [session] SES-COMMIT-71edfeba42b6 :: feat(dev-memory): backlog tools refactor + cleanup-duplicates szkript
  - summary: Changed 6 file(s)
  - tags: git,history,docs,scripts,md,ts
  - source: commit:71edfeba42b68751b86f437927918206280b5329
- [session] SES-COMMIT-d492444d50f8 :: refactor(mcp): split monolith into 3 domain servers (#169)
  - summary: Changed 15 file(s)
  - tags: git,history,.vscode,agents.md,apps,conversation-summaries,docs,package.json,scripts,system-status-snapshot.md,json,md,ts,sh
  - source: commit:d492444d50f8f7989906b72d0ecfaff793ba04c4

## File chunks

- ai-agent/docs/memory-map.md:136-195 ⭐boost=3
  - │ ▼ [5] Persist - saveKBDocument() → SQLite documents tábla - saveChunks() → SQLite chunks tábla - appendCitationEdge() → SQLite citation_edges tábla - legalGraph.addLaw() / addCourtCase() → Neo4j/Graphiti vagy in-memory graph │ ▼ [6] Post-ingest: enrichAndChunkOne() [fire-and-forget] → liveIngestPipeline.ts ``` ### 3.2 Live Ingest Pipeline (`liveIngestPipeli
- ai-agent/docs/memory-map.md:181-240 ⭐boost=3
  - - text-embedding-3-small (1536-dim) - BLOBként SQLite chunks.embedding oszlopba - Hybrid search: BM25 + cosine → RRF fúzió ``` ### 3.3 Graphiti Migration (`scripts/graphiti-migrate.ts`) ``` Turso (cloud SQL) → Graphiti Knowledge Graph [1] Turso SELECT documents (limit, source filter, offset) [2] getGroupId(docType, sourceOrg) Mapping: email / gmail:* → legal_emai
- ai-agent/.github/copilot-instructions.md:181-224 ⭐boost=1.5
  - <!-- END CANONICAL ASSISTANT POLICY --> <!-- BEGIN AUTO DEV MEMORY CONTEXT --> # Context Pack: feat/document-visual-audit-failopen Generated: 2026-04-06T17:51:15.804Z Repo: /Users/bujdosoarnold/Developer/GitHub/ai-agent Task: small refactor Memory count: 2 File chunk count: 8 ## Memory hits - [session] SES-COMMIT-71edfeba42b6 :: feat(dev-memory): backlog tools refactor + cleanup-duplicates sz
- ai-agent/.github/copilot-instructions.md:136-195 ⭐boost=1.5
  - - `IMPACT_POLICY_ALLOW_MAIN_COMMIT=1` - `IMPACT_POLICY_ALLOW_MAIN_PUSH=1` - A bypass használatát PR-ben dokumentálni kell. ## 7. Védett műveletek és guardok - Teljes repo scan / tömeges rsync csak előzetes indoklással és Arnold jóváhagyásával. - Védett / bastion / jump szerverhez tartozó állományokhoz csak külön engedéllyel szabad nyúlni. - Ha védett fájlt kell módosítani vagy törölni, előtt
- ai-agent/docs/dev-mcp-audit-2026-04-01.md:991-1034 ⭐boost=1.5
  - 5. **#3 warnings tömb** — `DevMemoryBriefResult` bővítés 6. **#4 execFileSync** — script check + timeout + error logging 7. **#8 HTTP cleanup** — catch ágba `server.close()` 8. **#9a OCR** — `OcrResult` interface 9. **#10a magyar leírások** — 11 tool description fordítás ### Batch 3 — P2 sémák + perf (~2 óra) 10. **#5 Zod constraints** — numeric min/max, messages min(1) 11. **#6 describe()** — w
- ai-agent/docs/dev-mcp-audit-2026-04-01.md:946-1005 ⭐boost=1.5
  - | SEC-2 | Path traversal — `resolveDbPath` | **CRITICAL** | S | **P0** | | SEC-9 | Path traversal — `resolveStore` | **CRITICAL** | S | **P0** | | SEC-4 | SSRF — `fetchBuffer` | **CRITICAL** | S | **P0** | | 1 | Null propagation + N+1 → batch query | HIGH | M | **P1** | | 3 | Silent catch → warnings tömb | MEDIUM | S | **P1** | | 4 | execFileSync error logging + timeout | MEDIUM | S | **P1** | | 8
- ai-agent/docs/legal-agent-architecture-phase28.md:91-150 ⭐boost=1.5
  - Input: { type, description, parties?, specifics?, legalArea? } Output: { draft: string, sources: SearchResult[], model: string } ``` ### C) `legal_analysis` — Structured Analysis ``` 5 sections: Tényállás összefoglalása, Alkalmazandó jogszabályok, Jogszabályi elemzés, Kockázatok és lehetőségek, Javaslat Input: facts string + options { includePrecedents?: boolean } Output: { analysis: string,
- impactshop-notes-pr-cta/.github/copilot-instructions.md:1-11
  - # Copilot Workflow Guardrails When the task involves code changes, refactor, debugging, or PR review: 1. Call `dev_memory_brief` first with a short task description. 2. Use returned memory/file context before proposing edits. 3. If memory is stale or missing, call `dev_memory_stats` and note it explicitly. 4. Keep answers aligned with repository guardrails (`pr-policy`, hooks, safe audit). If M

<!-- END AUTO DEV MEMORY CONTEXT -->
