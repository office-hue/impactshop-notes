# AI Agent integráció: kupon harvester + Árukereső vadász

## 1. Cél
A kupon harvester (Gmail + whitelistelt shop oldalak) és az Árukereső Playwright vadász külön-külön képes draft CSV-ket/JSON-t előállítani. A cél, hogy ezek az adatok strukturáltan bekerüljenek az `ai-agent` szolgáltatásba, ahol:
- Egyetlen belső DTO és adatforrás-réteg szolgálja ki a chat/CLI/Discord felületeket.
- Automatikus reliability score, deduplikáció és archívum készül.
- Visszajelzés menjen vissza a harvester/vadász felé (pl. reject ok, hiányzó mező).

## 2. Forrás komponensek
- `docs/coupon-harvester.md` → `tools/coupon-harvester.ts` / `.py`: Gmail + whitelist scrape → `out/manual_coupons_draft-YYYY-MM-DD.csv`.
- `docs/Árukereso kupon vadász.md` → `ai-agent/tools/playwright/arukereso-runner.ts`: Playwright JSON → `tools/out/arukereso-promotions.json`.
- Shops registry (`shops_registry.json`), Dognet/CJ CSV-k: slug/domain/default CTA összerendelések.

## 3. Adatfolyam javaslat
1. **Raw export** (meglévő állapot):
   - Harvester → `out/manual_coupons_draft-<ts>.csv` + `manual_coupons_draft-latest.csv` symlink.
   - Vadász → `tools/out/arukereso-promotions.json` + timestamp.
2. **Normalizer** (új script, pl. `ai-agent/tools/ingest/normalizer.ts`):
   - CSV/JSON bemeneteket betölti (Drive path vagy rsync az `impactshop-notes/.codex/cache/coupons/` mappába).
   - Egységes DTO-t készít: `source`, `shop_slug`, `shop_name`, `coupon_code`, `discount`, `type`, `starts_at`, `expires_at`, `cta_url`, `reliability_seed`.
   - Feloldja a slug/domain mappinget (shops_registry + Dognet/CJ feed). Ha nincs találat → `needs_mapping` flag.
   - Output: `ai-agent/tmp/ingest/manual-coupons.json` + `ai-agent/tmp/ingest/arukereso.json`.
3. **AI Agent ingest pipeline** (`apps/ai-agent-core/src/sources/*.ts`):
   - Új modulok: `manualCoupons.ts`, `arukeresoPlaywright.ts` → mindkettő ugyanarra a DTO-ra épül.
   - A modulok adatokat írnak a `pg` adatbázisba vagy lokális JSON store-ba (`data/coupons.sqlite`).
   - Reliability worker (`apps/ai-agent-core/src/workers/reliability.ts`) számolja a score-t.
4. **Agent szolgáltatás**:
   - `/api/v1/coupons` REST + `/api/v1/chat/command` kimenet a normalizált adatból válogat.
   - `features` mező a `/healthz` válaszban: `playwright`, `gmail`, `harvester_bridge` → guard ellenőrizni tudja.
5. **Visszajelzés**:
   - Sikertelen rekordok (`needs_mapping`, `lejárt`, `hiányzó CTA`) → `tmp/gmail-promotions-invalid.json` + Slack/Discord.
   - A reliability pipeline visszatöltése: ha a kupon `risk` státuszt kap, automatikus jelzés a manual CSV-be (pl. `status` oszlop).

## 4. Integrációs feladatok
### Rövid táv (S2 backlog T-2.8..T-2.10)
1. **Közös DTO + converter**
   - Készíts `packages/coupon-schema/src/index.ts` modul-t (`zod` definícióval).
   - Exportálj `parseManualCsv(path)`, `parseArukeresoJson(path)` helper függvényeket.
2. **Sync script**
   - `ai-agent/tools/ingest/sync-from-impactshop.ts` → bemenet: `IMPACTSHOP_NOTES_ROOT` + `IMPACTSHOP_REPO_ROOT`.
   - Feladata: kimásolja a legfrissebb draftokat `ai-agent/tmp/ingest/` alá, majd meghívja a normalizert.
3. **AI Agent source modulok**
   - `apps/ai-agent-core/src/sources/manual-coupons.ts` (CSV) és `arukereso.ts` (Playwright JSON).
   - Mindkettő implementálja az `SourceAdapter` interfészt (fetch, dedup, reliability seed, lastRun timestamp).
4. **Reliability pipeline**
   - `manual_coupons_stats.json` + AI visszajelzések → `reliability_score`.
   - Score > 0.7 → `label: "super"`, <0.3 → `label: "risky"` (AI agent javaslat).
5. **Guard / health jelzés**
   - `AI_AGENT_HEALTH_URL` JSON `features` tömbjét bővítsd: `"playwright"`, `"gmail"`, `"reliability"`, `"harvester_bridge"`.

### Közép táv
- **Cronok**: `.codex/cron/arukereso-playwright.sh` és `.codex/cron/coupon-harvester.sh` → lefutás után `guard_result` + ingest trigger.
- **Backfill**: régi `manual_coupons_draft-*.csv` fájlok betöltése az AI agent adatbázisba (history chart / reliability baseline).
- **Feedback loop**: AI agent `/api/v1/chat/command` "archive" utasítás → jelölje `status=archived` a manual CSV-ben vagy dedikált store-ban.

## 5. Könyvtárstruktúra javaslat
```
ai-agent/
  tools/
    ingest/
      normalizer.ts
      sync-from-impactshop.ts
      README.md (runbook)
  apps/
    ai-agent-core/
      src/
        sources/
          manual-coupons.ts
          arukereso.ts
        workers/reliability.ts
```
Az `impactshop-notes` oldalon a draft fájlok továbbra is version control alatt maradnak, de a sync script csak read-only módban fér hozzájuk.

## 6. Biztonsági megfontolások
- Gmail token + Dognet/CJ API kulcsok továbbra is csak lokálisan legyenek tárolva (`.codex/.env.local`, `secrets/`).
- A sync script kizárólag az ImpactShop gépről fut, nem push-olja vissza az adatokat.
- Az AI agent API csak aggregált adatot szolgáltat ki (coupon + shop), e-mail metaadatok nem kerülnek bele.

## 7. Következő lépések
1. `docs/coupon-harvester.md` és `docs/Árukereso kupon vadász.md` alapján készíts pipeline backlog-taskokat (`.codex/sprint-tasks/S2.md`).
2. Implementáld a normalizer + source modulokat, majd frissítsd a guardot (`ai-agent-guard.sh`), hogy a `/healthz` feature flag-et ellenőrizze.
3. Validálj egy end-to-end futást: harvester → normalizer → AI agent → `/api/v1/chat/command` javaslat.
