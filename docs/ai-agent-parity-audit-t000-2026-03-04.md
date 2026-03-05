# T-000 AI Agent Parity Audit (kanonikus vs top-level)

> Dátum: 2026-03-04  
> Kanonikus döntés: `impact_hub/ai-agent` a source of truth  
> Összevetett fák:  
> - Kanonikus: `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent`  
> - Top-level: `/Users/bujdosoarnold/Developer/GitHub/ai-agent`

## 1) Módszer

A parity-audit a kanonikus repo git-tracked állományaira épült, majd source-scope szűréssel újrafuttatva.

Source-scope (tisztított):
1. Útvonalak: `apps/`, `tests/`, `scripts/`, `tools/`, `config/`, `services/`, `package*.json`, `tsconfig*.json`, `README*`, `.env.example`
2. Kizárások: `.git`, `node_modules`, `dist`, `coverage`, `tmp`, `.next`, `.turbo`, `.cache`, `out`, `build`, `neo4j/data`
3. Kiterjesztés-szűrés: `.ts`, `.tsx`, `.js`, `.mjs`, `.cjs`, `.json`, `.md`, `.sh`, `.yaml`, `.yml`, `.sql`, `.txt`

## 2) Eredmény (source-scope, tisztított)

1. Kanonikus forrásfájlok: **116**
2. Top-level forrásfájlok: **192**
3. Kanonikusból hiányzik top-levelből: **4**
4. Top-level-only (kanonikusban nincs): **80**
5. Közös fájl: **112**
6. Tartalmilag eltérő közös fájl: **33**

## 3) Legfontosabb drift területek

## 3.1 Top-level-only, magas prioritású (migráció-jelölt)

1. API gateway security réteg:
   - `apps/api-gateway/src/bootstrap/security.ts`
   - `apps/api-gateway/src/middleware/auth.ts`
2. Legal/tax capability + legal pipeline:
   - `apps/core-agent-graph/src/capabilities/legalLegislationLookup.ts`
   - `apps/core-agent-graph/src/capabilities/taxChecklist.ts`
   - `apps/core-agent-graph/src/legal/*` (teljes legal modulcsomag)
3. MCP legal tooling:
   - `apps/mcp-wrapper/src/tools/legal-tools.ts`
   - `apps/mcp-wrapper/src/tools/ads-tools.ts`
4. Legal regressziós teszt:
   - `tests/legal-agent.test.ts`
5. Legal operációs script-ek:
   - `scripts/kuria-baseline.ts`
   - `scripts/legal-daily-cron.sh`
   - `scripts/legal-daily-sync.mjs`

## 3.2 Top-level-only, NEM migráció-jelölt (biztonsági/üzemeltetési ok)

1. Lokális secret fájlok:
   - `config/drive-service-account.json`
   - `tools/secrets/gmail/promotions-credentials.json`
   - `tools/secrets/gmail/promotions-token.json`
2. Lokális operációs mellékfájlok, amiket nem célszerű kanonikus repóba emelni.

## 3.3 Közös, de eltérő (33 fájl)

Fő érintett csoportok:
1. `apps/api-gateway/src/index.ts` és több `services/*`
2. `apps/core-agent-graph/src/*` capability és node fájlok
3. `apps/core-worker/src/*`
4. `package.json`, `package-lock.json`, `.env.example`

## 4) Kockázati értékelés

1. **Magas kockázat**: top-level-only biztonsági és legal modulok miatt a dokumentált Phase állapot és a kanonikus repo eltér.
2. **Magas kockázat**: közös, de eltérő kritikus fájlok (API gateway, graph, worker) miatt regresszióveszély magas.
3. **Közepes kockázat**: top-levelben maradt secret fájlok miatt operációs keveredés és accidental commit veszély.

## 5) Javasolt rendezési sorrend (implementációs tervhez input)

1. Security-first parity csomag:
   - `bootstrap/security.ts`, `middleware/auth.ts`, `api-gateway/src/index.ts` eltérések rendezése.
2. Legal core parity csomag:
   - `legalLegislationLookup.ts`, `taxChecklist.ts`, `legal/*`, `legal-tools.ts`, `tests/legal-agent.test.ts`.
3. Worker/script parity csomag:
   - legal/nav/kúria script-ek kontrollált átemelése.
4. Secret hygiene csomag:
   - top-level secret állományok explicit kizárása és dokumentált kezelése.

## 6) Audit artifactek (nyers listák)

A részletes listák itt vannak:
- `impactshop-notes/docs/.audit/canonical-source-clean.txt`
- `impactshop-notes/docs/.audit/top-source-clean.txt`
- `impactshop-notes/docs/.audit/missing-in-top-source-clean.txt`
- `impactshop-notes/docs/.audit/extra-in-top-source-clean.txt`
- `impactshop-notes/docs/.audit/content-diff-source-clean.txt`

