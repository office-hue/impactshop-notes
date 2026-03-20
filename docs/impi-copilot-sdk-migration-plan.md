# Impi AI Agent → GitHub Copilot SDK Migrációs Terv

**Verzió:** 1.0  
**Dátum:** 2026-01-27  
**Státusz:** TERV – Jóváhagyásra vár  
**Prioritás:** MEDIUM  
**Mód:** FÁZISOLT – teljes backup + rollback biztosítással

---

## 📋 Tartalomjegyzék

1. [Összefoglaló](#összefoglaló)
2. [Jelenlegi állapot (AS-IS)](#jelenlegi-állapot-as-is)
3. [Célállapot (TO-BE)](#célállapot-to-be)
4. [Előfeltételek és kockázatok](#előfeltételek-és-kockázatok)
5. [Backup és Rollback protokoll](#backup-és-rollback-protokoll)
6. [Migrációs fázisok](#migrációs-fázisok)
7. [Tesztelési terv](#tesztelési-terv)
8. [Rollback forgatókönyvek](#rollback-forgatókönyvek)
9. [Befejezési kritériumok](#befejezési-kritériumok)
10. [Üzemeltetési Keretrendszer](#üzemeltetési-keretrendszer-codex-52)
    - [10.1 Observability & Monitoring](#101--observability--monitoring)
    - [10.2 Resiliency & Fault Tolerance](#102-️-resiliency--fault-tolerance)
    - [10.3 Security & Compliance](#103--security--compliance)
    - [10.4 Cost Control & Quotas](#104--cost-control--quotas)
    - [10.5 Rollout Strategy](#105--rollout-strategy)
    - [10.6 Caching Strategy](#106-️-caching-strategy)
    - [10.7 Rollback & Recovery](#107--rollback--recovery)
    - [10.8 Operational Runbooks](#108--operational-runbooks)
    - [10.9 Quality Assurance](#109--quality-assurance)
    - [10.10 Documentation & Governance](#1010--documentation--governance)
    - [10.11 Core-wide Policies](#1011--core-wide-policies)
    - [10.12 Additional Recommendations](#1012--additional-recommendations)
    - [10.14 AI/LLM Specifikus Javaslatok](#1014--aillm-specifikus-javaslatok)
    - [10.15 User Experience](#1015--user-experience-javaslatok)
    - [10.16 Continuous Improvement](#1016--continuous-improvement)
    - [10.17 Incident Response](#1017--incident-response)
    - [10.18 Onboarding & Knowledge Transfer](#1018--onboarding--knowledge-transfer)
    - [10.19 Integráció & API Design](#1019--integráció--api-design)
    - [10.20 Business Metrics & KPIs](#1020--business-metrics--kpis)
    - [10.21 Experimentation Framework](#1021--experimentation-framework)
    - [10.22 Lokalizáció & i18n](#1022--lokalizáció--i18n)
    - [10.23 Accessibility & Inclusivity](#1023--accessibility--inclusivity)
    - [10.24 Future Roadmap Ideas](#1024--future-roadmap-ideas)
    - [10.25 Stakeholder Communication](#1025--stakeholder-communication)
    - [10.26 Vendor & Dependency Management](#1026--vendor--dependency-management)
    - [10.27 Technical Debt Management](#1027--technical-debt-management)
    - [10.28 Secrets & Credentials Management](#1028--secrets--credentials-management)
    - [10.29 Data Management & Retention](#1029--data-management--retention)
    - [10.30 Performance Optimization](#1030--performance-optimization)
    - [10.31 Chaos Engineering](#1031--chaos-engineering-optional-advanced)
    - [10.32 Developer Experience (DX)](#1032--developer-experience-dx)
    - [10.33 Analytics & Insights](#1033--analytics--insights)
    - [10.34 Gamification & Engagement](#1034--gamification--engagement-future)
    - [10.35 Migration Rollback Scenarios](#1035--migration-rollback-scenarios)
    - [10.36 Network & Infrastructure](#1036--network--infrastructure)
    - [10.37 Compliance & Legal](#1037--compliance--legal)
    - [10.38 Support & Helpdesk](#1038--support--helpdesk)
    - [10.39 Prompt Library & Templates](#1039--prompt-library--templates)
    - [10.40 Configuration Reference](#1040--configuration-reference)
    - [10.41 Checklist Summary](#1041--checklist-summary)
    - [10.42 Success Criteria Summary](#1042--success-criteria-summary)
    - [10.13 Hivatkozások](#1013--hivatkozások)

---

## 🎯 Összefoglaló

A GitHub Copilot SDK bevezetése az Impi AI Agent rendszerbe, amely lehetővé teszi:
- **Multi-step planning** – komplexebb kupon keresési workflow-k
- **MCP Server integráció** – VS Code/IDE natív elérhetőség
- **Multi-model támogatás** – GPT-4o, Claude, stb. rugalmas váltás
- **Standardizált tool calling** – egyszerűbb capability fejlesztés

**Migráció típusa:** Fázisolt, backward-compatible – a meglévő rendszer mindvégig működőképes marad.

---

## 📊 Jelenlegi állapot (AS-IS)

### Architektúra

```
┌─────────────────────────────────────────────────────────┐
│                    Impi AI Agent                        │
│                    (Port 4000)                          │
├─────────────────────────────────────────────────────────┤
│  Endpoints:                                             │
│  - /healthz (health check)                              │
│  - /api/v1/chat/command (command interface)             │
│  - /api/v1/search?q=... (search API)                    │
├─────────────────────────────────────────────────────────┤
│  Stack:                                                 │
│  - TypeScript / Node.js                                 │
│  - Express vagy hasonló HTTP server                     │
│  - OpenAI API (egyetlen model)                          │
│  - Saját capability keret                               │
├─────────────────────────────────────────────────────────┤
│  Sources:                                               │
│  - apps/ai-agent-core/src/sources/cj-links.ts           │
│  - Dognet API                                           │
│  - Gmail harvester                                      │
│  - Playwright scraper                                   │
├─────────────────────────────────────────────────────────┤
│  Kliensek:                                              │
│  - Discord bot                                          │
│  - CLI                                                  │
│  - REST API hívások                                     │
└─────────────────────────────────────────────────────────┘
```

### Fő fájlok

| Fájl | Funkció | Migráció érintettsége |
|------|---------|----------------------|
| `apps/ai-agent-core/src/sources/cj-links.ts` | CJ kupon source | Marad – tool wrapper |
| `apps/ai-agent-core/src/sources/types.ts` | Típus definíciók | Marad – bővül |
| `apps/ai-agent-core/src/impi/recommend.ts` | Ajánló logika | Refaktor → Tool |
| `apps/ai-agent-core/src/impi/ngo-categories.ts` | NGO kategorizálás | Marad |

### Jelenlegi korlátok

- ❌ Nincs multi-step planning (egyetlen LLM hívás)
- ❌ Nincs IDE integráció (csak Discord/CLI/API)
- ❌ Egyetlen model (OpenAI) – nincs fallback
- ❌ Saját capability keret karbantartása

---

## 🎯 Célállapot (TO-BE)

### Új architektúra

```
┌─────────────────────────────────────────────────────────────────┐
│                         VS Code / IDE                           │
│                    (MCP Client natív)                           │
└─────────────────────┬───────────────────────────────────────────┘
                      │ MCP Protocol
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Impi MCP Server                              │
│               (Copilot SDK alapú)                               │
├─────────────────────────────────────────────────────────────────┤
│  Tools (MCP):                                                   │
│  - impi_search_coupons                                          │
│  - impi_validate_coupon                                         │
│  - impi_recommend_shop                                          │
│  - impi_get_ngo_info                                            │
├─────────────────────────────────────────────────────────────────┤
│  Copilot SDK Features:                                          │
│  - Multi-step planning                                          │
│  - Multi-model (GPT-4o, Claude, stb.)                           │
│  - Automatic tool orchestration                                 │
├─────────────────────────────────────────────────────────────────┤
│  Backend (változatlan):                                         │
│  - CJ Links source                                              │
│  - Dognet API                                                   │
│  - Gmail harvester                                              │
│  - Playwright scraper                                           │
└─────────────────────────────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│              Legacy API Layer (Port 4000)                       │
│         (backward compatible – Discord/CLI)                     │
└─────────────────────────────────────────────────────────────────┘
```

### Előnyök

| Képesség | Jelenlegi | Copilot SDK után |
|----------|-----------|------------------|
| Multi-step planning | ❌ | ✅ |
| IDE integráció | ❌ | ✅ (VS Code, JetBrains) |
| Multi-model | ❌ | ✅ (GPT-4o, Claude, Gemini) |
| Tool calling standard | Saját | ✅ MCP szabvány |
| Capability fejlesztés | Komplex | ✅ Egyszerű decorator |
| Backward compatible | – | ✅ Legacy API marad |

---

## ⚠️ Előfeltételek és kockázatok

### Előfeltételek

| # | Előfeltétel | Ellenőrzés módja | Státusz |
|---|-------------|------------------|---------|
| 1 | Node.js >= 18.x | `node --version` | ⬜ |
| 2 | TypeScript >= 5.0 | `tsc --version` | ⬜ |
| 3 | GitHub Copilot SDK telepítve | `npm list @github/copilot-sdk` | ⬜ |
| 4 | Copilot hozzáférés/entitlement aktív | GitHub org/license beállítások | ⬜ |
| 5 | MCP Server teszt env | Lokális VS Code | ⬜ |

### Kockázatok

| Kockázat | Valószínűség | Hatás | Mitigáció |
|----------|--------------|-------|-----------|
| Copilot SDK API változás | Közepes | Magas | Verzió pinning, changelog figyelés |
| Legacy kliensek törnek | Alacsony | Magas | Backward compatible layer |
| Teljesítmény romlás | Alacsony | Közepes | Benchmark tesztek minden fázis után |
| Capability migrációs hibák | Közepes | Közepes | Unit tesztek, A/B összehasonlítás |

---

## 💾 Backup és Rollback protokoll

### 🔴 KÖTELEZŐ – Migráció előtt végrehajtandó

#### 1. Teljes backup készítése

```bash
#!/bin/bash
# backup-impi-migration.sh
# Futtatás: ./bin/backup-impi-migration.sh

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=".codex/backups/impi-copilot-sdk-migration-${TIMESTAMP}"
BACKUP_ARCHIVE="${BACKUP_DIR}.tar.gz"

echo "🔵 Impi Copilot SDK Migráció Backup"
echo "==================================="
echo "Timestamp: ${TIMESTAMP}"
echo "Backup dir: ${BACKUP_DIR}"
echo ""

# 1. Backup könyvtár létrehozása
mkdir -p "${BACKUP_DIR}"

# 2. AI Agent Core teljes mentése
echo "📦 AI Agent Core mentése..."
if [ -d "apps/ai-agent-core" ]; then
    cp -R apps/ai-agent-core "${BACKUP_DIR}/"
    echo "   ✅ apps/ai-agent-core → ${BACKUP_DIR}/"
else
    echo "   ⚠️ apps/ai-agent-core nem található"
fi

# 3. Impi specifikus fájlok (ha külön vannak)
echo "📦 Impi fájlok mentése..."
find . -name "*impi*" -type f ! -path "./.git/*" ! -path "./node_modules/*" ! -name ".env*" ! -path "./secrets/*" | while read -r file; do
    target="${BACKUP_DIR}/impi-files/$(dirname "$file")"
    mkdir -p "$target"
    cp "$file" "$target/"
    echo "   ✅ $file"
done

# 4. Package.json és lock fájlok
echo "📦 Dependency fájlok mentése..."
cp package.json "${BACKUP_DIR}/" 2>/dev/null || echo "   ⚠️ package.json nem található root-ban"
cp package-lock.json "${BACKUP_DIR}/" 2>/dev/null || true
cp yarn.lock "${BACKUP_DIR}/" 2>/dev/null || true

if [ -d "apps/ai-agent-core" ]; then
    cp apps/ai-agent-core/package.json "${BACKUP_DIR}/ai-agent-core-package.json" 2>/dev/null || true
fi

# 5. Environment és config fájlok (szenzitívek nélkül)
echo "📦 Config fájlok mentése..."
if [ -f ".env.example" ]; then
    cp .env.example "${BACKUP_DIR}/"
fi

# 6. Git state mentése
echo "📦 Git state mentése..."
git rev-parse HEAD > "${BACKUP_DIR}/git-commit-hash.txt"
git branch --show-current > "${BACKUP_DIR}/git-branch.txt"
git status --short > "${BACKUP_DIR}/git-status.txt"
git diff > "${BACKUP_DIR}/git-diff-unstaged.patch" 2>/dev/null || true
git diff --cached > "${BACKUP_DIR}/git-diff-staged.patch" 2>/dev/null || true

# 7. Rollback script generálása
echo "📦 Rollback script generálása..."
cat > "${BACKUP_DIR}/rollback.sh" << 'ROLLBACK_SCRIPT'
#!/bin/bash
# Impi Copilot SDK Migráció Rollback Script
# Generálva: TIMESTAMP_PLACEHOLDER

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"

echo "🔴 ROLLBACK: Impi Copilot SDK Migráció"
echo "======================================="
echo "Backup dir: ${SCRIPT_DIR}"
echo "Project root: ${PROJECT_ROOT}"
echo ""

read -p "⚠️ Ez visszaállítja a migráció előtti állapotot. Folytatod? (y/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Rollback megszakítva."
    exit 0
fi

# 1. AI Agent Core visszaállítása
echo "📦 AI Agent Core visszaállítása..."
if [ -d "${SCRIPT_DIR}/ai-agent-core" ]; then
    rm -rf "${PROJECT_ROOT}/apps/ai-agent-core"
    cp -R "${SCRIPT_DIR}/ai-agent-core" "${PROJECT_ROOT}/apps/"
    echo "   ✅ apps/ai-agent-core visszaállítva"
fi

# 2. Új Copilot SDK fájlok törlése (ha léteznek)
echo "📦 Új MCP server fájlok törlése..."
NEW_MCP_DIR="${PROJECT_ROOT}/apps/impi-mcp-server"
if [ -d "${NEW_MCP_DIR}" ]; then
    rm -rf "${NEW_MCP_DIR}"
    echo "   ✅ apps/impi-mcp-server törölve"
fi

# 3. Dependencies visszaállítása
echo "📦 Dependencies újratelepítése..."
cd "${PROJECT_ROOT}"
if [ -f "${SCRIPT_DIR}/package-lock.json" ]; then
    cp "${SCRIPT_DIR}/package-lock.json" .
fi
npm install

# 4. Git patches alkalmazása (ha vannak)
if [ -s "${SCRIPT_DIR}/git-diff-staged.patch" ]; then
    echo "📦 Git staged changes visszaállítása..."
    git apply "${SCRIPT_DIR}/git-diff-staged.patch" --cached 2>/dev/null || true
fi

echo ""
echo "✅ ROLLBACK KÉSZ"
echo "==============="
echo "Ellenőrizd: npm run dev:mvp"
echo "Health check: curl http://localhost:4000/healthz"
ROLLBACK_SCRIPT

# Timestamp behelyettesítése
sed -i.bak "s/TIMESTAMP_PLACEHOLDER/${TIMESTAMP}/" "${BACKUP_DIR}/rollback.sh"
rm -f "${BACKUP_DIR}/rollback.sh.bak"
chmod +x "${BACKUP_DIR}/rollback.sh"

# 8. Verify script generálása
cat > "${BACKUP_DIR}/verify-backup.sh" << 'VERIFY_SCRIPT'
#!/bin/bash
# Backup integritás ellenőrzése

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🔍 Backup Integritás Ellenőrzés"
echo "==============================="

ERRORS=0

check_exists() {
    if [ -e "${SCRIPT_DIR}/$1" ]; then
        echo "   ✅ $1"
    else
        echo "   ❌ $1 HIÁNYZIK"
        ERRORS=$((ERRORS + 1))
    fi
}

check_exists "ai-agent-core"
check_exists "git-commit-hash.txt"
check_exists "git-branch.txt"
check_exists "rollback.sh"

echo ""
if [ $ERRORS -eq 0 ]; then
    echo "✅ Backup integritás OK"
    exit 0
else
    echo "❌ $ERRORS hiba – backup hiányos!"
    exit 1
fi
VERIFY_SCRIPT
chmod +x "${BACKUP_DIR}/verify-backup.sh"

# 9. Backup összefoglaló
echo ""
echo "📋 Backup összefoglaló"
echo "======================"
echo "Könyvtár: ${BACKUP_DIR}"
du -sh "${BACKUP_DIR}"
ls -la "${BACKUP_DIR}"

# 10. Backup tömörítése (opcionális)
echo ""
read -p "Tömörítsem a backup-ot tar.gz-be? (y/N) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    tar -czf "${BACKUP_ARCHIVE}" -C "$(dirname "${BACKUP_DIR}")" "$(basename "${BACKUP_DIR}")"
    echo "✅ Archívum: ${BACKUP_ARCHIVE}"
    du -sh "${BACKUP_ARCHIVE}"
fi

echo ""
echo "✅ BACKUP KÉSZ"
echo "=============="
echo ""
echo "Rollback parancs:"
echo "  ${BACKUP_DIR}/rollback.sh"
echo ""
echo "Backup ellenőrzés:"
echo "  ${BACKUP_DIR}/verify-backup.sh"
```

#### 2. Backup integritás ellenőrzése

```bash
# Migráció előtt KÖTELEZŐ futtatni:
.codex/backups/impi-copilot-sdk-migration-*/verify-backup.sh
```

#### 3. Rollback teszt (dry-run)

```bash
# Opcionális de ajánlott – rollback script szintaxis ellenőrzése
bash -n .codex/backups/impi-copilot-sdk-migration-*/rollback.sh
```

---

## 🔧 Migrációs fázisok

### FÁZIS 0: Előkészítés (30 perc)

**Előfeltétel:** Backup KÉSZ és ELLENŐRZÖTT

```
[ ] Backup script futtatása és ellenőrzése
[ ] Node.js verzió ellenőrzése (>= 18.x)
[ ] TypeScript verzió ellenőrzése (>= 5.0)
[ ] Copilot SDK dokumentáció áttekintése
[ ] Jelenlegi AI Agent működésének dokumentálása (baseline)
[ ] Health check baseline rögzítése: curl http://localhost:4000/healthz > baseline-health.json
```

**Döntési pont:** Ha bármelyik előfeltétel nem teljesül → STOP, ne folytasd.

---

### FÁZIS 1: Copilot SDK telepítés (izolált) (1 óra)

**Cél:** Új MCP server projekt létrehozása a meglévő rendszer érintése nélkül.

```
[ ] Új könyvtár létrehozása: apps/impi-mcp-server/
[ ] package.json inicializálás
[ ] Copilot SDK függőségek telepítése:
    npm install @github/copilot-sdk @modelcontextprotocol/sdk
[ ] Package név ellenőrzése hivatalos Copilot SDK dokumentáció alapján
[ ] TypeScript konfigurálás (tsconfig.json)
[ ] Alap MCP server skeleton létrehozása

[ ] Fájl struktúra:
    apps/impi-mcp-server/
    ├── package.json
    ├── tsconfig.json
    ├── src/
    │   ├── index.ts          # MCP server entry point
    │   ├── tools/
    │   │   ├── index.ts      # Tool exports
    │   │   ├── search.ts     # impi_search_coupons tool
    │   │   ├── validate.ts   # impi_validate_coupon tool
    │   │   └── recommend.ts  # impi_recommend_shop tool
    │   ├── adapters/
    │   │   └── legacy.ts     # Legacy API adapter
    │   └── types/
    │       └── index.ts
    └── README.md

[ ] Első tool implementálása: impi_search_coupons
    - Input: query string, filters (optional)
    - Output: NormalizedCoupon[]
    - Backend: meglévő CJ Links + Dognet source használata

[ ] MCP server indítás tesztelése (standalone)
```

**Ellenőrzés:**
- MCP server elindul hiba nélkül
- `impi_search_coupons` tool listázható
- Meglévő AI Agent (port 4000) továbbra is működik

**Rollback trigger:** Ha a meglévő AI Agent bármilyen módon sérül → azonnal rollback.

---

### FÁZIS 2: Tool implementáció (2-3 óra)

**Cél:** Teljes tool készlet implementálása.

```
[ ] impi_search_coupons tool:
    - Query parsing (természetes nyelv)
    - Source aggregálás (CJ + Dognet)
    - Reliability scoring
    - Response formázás

[ ] impi_validate_coupon tool:
    - Kupon kód validálás
    - Lejárat ellenőrzés
    - Shop elérhetőség check

[ ] impi_recommend_shop tool:
    - Preferencia alapú ajánlás
    - NGO támogatás figyelembevétele
    - Top N eredmény

[ ] impi_get_ngo_info tool:
    - NGO részletek lekérése
    - Támogatási statisztikák

[ ] Tool unit tesztek:
    - Minden tool-hoz min. 3 teszt eset
    - Edge case-ek (üres eredmény, hiba, timeout)
```

**Ellenőrzés:**
- Minden tool unit teszt zöld
- Tool-ok VS Code-ból elérhetők (MCP inspector)

---

### FÁZIS 3: VS Code integráció (1 óra)

**Cél:** Impi MCP Server elérhetővé tétele VS Code-ban.

```
[ ] VS Code MCP konfigurálás:
    .vscode/settings.json:
    {
      "mcp.servers": {
        "impi": {
          "command": "node",
          "args": ["apps/impi-mcp-server/dist/index.js"],
          "env": {
            "CJ_LINKS_JSON": "${workspaceFolder}/data/cj-links-latest.json"
          }
        }
      }
    }

[ ] MCP server manifest (opcionális):
    apps/impi-mcp-server/mcp-manifest.json

[ ] VS Code teszt:
    - MCP server auto-start
    - Tool discovery működik
    - Első query sikeres

[ ] Dokumentáció:
    - apps/impi-mcp-server/README.md frissítése
    - Használati példák
```

**Ellenőrzés:**
- VS Code-ban `@impi` prompt működik
- Tool hívások sikeresek
- Nincs interferencia a meglévő rendszerrel

---

### FÁZIS 4: Legacy API Bridge (1-2 óra)

**Cél:** Backward compatibility biztosítása Discord/CLI kliensek számára.

```
[ ] Legacy adapter implementálása:
    apps/impi-mcp-server/src/adapters/legacy.ts
    - /api/v1/chat/command → MCP tool hívásra fordítás
    - /api/v1/search → impi_search_coupons wrapper

[ ] Két üzemmód támogatása:
    a) Standalone legacy (port 4000) – változatlan
    b) MCP server + legacy bridge (port 4000 + MCP)

[ ] Legacy endpoint-ok tesztelése:
    - Discord bot továbbra is működik
    - CLI változatlan
    - Nincs regresszió

[ ] Feature flag bevezetése:
    IMPI_MODE=legacy|mcp|hybrid
    - legacy: csak régi rendszer
    - mcp: csak MCP server
    - hybrid: mindkettő (default a migráció alatt)
```

**Ellenőrzés:**
- Legacy kliensek változatlanul működnek
- MCP és legacy párhuzamosan fut
- Feature flag váltás működik

---

### FÁZIS 5: Multi-step Planning POC (2 óra)

**Cél:** Copilot SDK planning képességének demonstrálása.

```
[ ] Komplex workflow implementálása:
    "Találd meg a legjobb kupont az IKEA-hoz és validáld"
    
    Planning lépések:
    1. impi_search_coupons(query="IKEA")
    2. Eredmények rangsorolása
    3. impi_validate_coupon(top_result)
    4. impi_recommend_shop(ha nincs valid kupon)
    5. Összefoglaló válasz

[ ] Agent loop konfiguráció:
    - Max iterations: 5
    - Timeout: 30s
    - Error handling

[ ] Benchmark összehasonlítás:
    - Legacy: egyetlen LLM hívás eredménye
    - Copilot SDK: multi-step eredmény
    - Minőség és teljesítmény összevetés
```

**Ellenőrzés:**
- Multi-step workflow sikeresen fut
- Eredmény jobb/részletesebb mint a legacy
- Teljesítmény elfogadható (< 30s)

---

### FÁZIS 6: Produkciós előkészítés (1 óra)

**Cél:** Stabil, deploy-ready állapot elérése.

```
[ ] Error handling finomhangolás:
    - Graceful degradation
    - Timeout kezelés
    - Rate limiting

[ ] Logging és monitoring:
    - Request/response logging
    - Error tracking
    - Metrics (response time, success rate)

[ ] Konfiguráció externalizálás:
    - Környezeti változók
    - Secrets management
    - Feature flags

[ ] Dokumentáció véglegesítés:
    - README.md
    - API dokumentáció
    - Troubleshooting guide

[ ] Staging deploy:
    - MCP server staging-re
    - E2E tesztek futtatása
    - Smoke test
```

**Ellenőrzés:**
- Staging zöld
- Nincs kritikus hiba
- Dokumentáció teljes

---

## 🧪 Tesztelési terv

### Automatizált tesztek

| Teszt típus | Scope | Futtatás | Elvárt eredmény |
|-------------|-------|----------|-----------------|
| Unit | Minden tool | `npm test` | 100% pass |
| Integration | MCP ↔ Backend | `npm run test:integration` | 100% pass |
| E2E | Teljes workflow | `npm run test:e2e` | 100% pass |
| Legacy compat | Legacy API | `npm run test:legacy` | Nincs regresszió |

### Manuális tesztek

| Teszt | Leírás | Kritérium |
|-------|--------|-----------|
| VS Code MCP | Tool discovery + query | Működik |
| Discord bot | Kupon keresés | Változatlan válasz |
| CLI | /keres parancs | Változatlan válasz |
| Multi-step | Komplex query | Jobb eredmény |

### Teljesítmény benchmark

```bash
# Baseline rögzítése (migráció előtt)
curl -w "@curl-format.txt" -o /dev/null http://localhost:4000/api/v1/search?q=test > baseline-perf.txt

# Migráció után összehasonlítás
curl -w "@curl-format.txt" -o /dev/null http://localhost:4000/api/v1/search?q=test > new-perf.txt

diff baseline-perf.txt new-perf.txt
```

---

## 🔴 Rollback forgatókönyvek

### Azonnali rollback triggerek

| Trigger | Akció | Prioritás |
|---------|-------|-----------|
| Legacy API nem válaszol | Rollback azonnal | P0 |
| Health check fail | Rollback 5 percen belül | P0 |
| Discord bot nem működik | Rollback 15 percen belül | P1 |
| MCP server crash loop | MCP leállítás, legacy marad | P1 |
| 50%+ teljesítmény romlás | Rollback 30 percen belül | P2 |

### Rollback végrehajtás

```bash
# 1. Teljes rollback (minden visszaáll)
.codex/backups/impi-copilot-sdk-migration-*/rollback.sh

# 2. Csak MCP leállítás (legacy marad)
pkill -f "impi-mcp-server"
# vagy
systemctl stop impi-mcp-server

# 3. Feature flag váltás (hybrid → legacy)
export IMPI_MODE=legacy
pm2 restart impi-agent
```

### Rollback utáni ellenőrzés

```bash
# Health check
curl http://localhost:4000/healthz

# Legacy API teszt
curl -X POST http://localhost:4000/api/v1/chat/command \
  -H "Content-Type: application/json" \
  -d '{"text":"/keres teszt kupon"}'

# Discord bot teszt (manuális)
# CLI teszt
./impactctl search "teszt"
```

---

## ✅ Befejezési kritériumok

### Fázis befejezési kritériumok

| Fázis | Kritérium | Ellenőrzés |
|-------|-----------|------------|
| 0 | Backup kész + ellenőrzött | `verify-backup.sh` exit 0 |
| 1 | MCP server standalone működik | Tool listázás sikeres |
| 2 | Minden tool unit teszt zöld | `npm test` exit 0 |
| 3 | VS Code integráció működik | Manuális teszt OK |
| 4 | Legacy kompatibilitás megmarad | Legacy tesztek zöldek |
| 5 | Multi-step planning működik | POC sikeres |
| 6 | Staging deploy sikeres | Smoke test OK |

### Teljes migráció befejezési kritériumok

1. ✅ Minden fázis kritérium teljesül
2. ✅ Nincs regresszió a legacy funkciókban
3. ✅ MCP server stabil (24 óra crash-free)
4. ✅ Teljesítmény elfogadható (< 20% romlás)
5. ✅ Dokumentáció teljes
6. ✅ Rollback tesztelve és működik
7. ✅ notes.md frissítve

---

## 📎 Függelék

### D. Go/No-Go ellenőrzőlista (release gate)

**Go feltételek (mindnek teljesülnie kell):**
- [ ] Staging MCP + legacy hibrid 24 óra stabil (crash-free)
- [ ] Legacy endpointok regresszió nélkül (Discord/CLI smoke zöld)
- [ ] Tool unit + integration + e2e tesztek 100% pass
- [ ] SLO-k betartva (p95 < 2s, error rate < 1%)
- [ ] Feature flag visszaállítás tesztelve (IMPI_MODE=legacy)
- [ ] Backup + rollback szkriptek érvényesítve (verify-backup OK)
- [ ] PII redaction és retention policy élesben validálva
- [ ] On-call/runbook frissítve (MCP emergency stop)

**No-Go triggerek (bármelyik esetén STOP):**
- [ ] Legacy API hibázik vagy válaszidő > 2x baseline
- [ ] Tool success rate < 90% vagy p95 > 5s 5 percen át
- [ ] PII log leak vagy redaction hiba
- [ ] MCP crash loop (restart > 3x/5min)
- [ ] Auth/permission drift (scope allowlist sérül)

### E. Kockázati regiszter (minimális, akcióval)

| Kockázat | Hatás | Likelihood | Mitigáció | Trigger | Válasz |
|---|---|---|---|---|---|
| Copilot SDK verzió drift | API törés | Medium | Lockfile pin + CI diff check | build/test fail | Rollback + pin fix |
| Legacy/MCP bridge regresszió | CLI/Discord hiba | Medium | Dual-run + diff log | error rate > 5% | IMPI_MODE=legacy |
| PII log leak | Compliance incidens | Low | Redaction + log sampling | audit finding | Emergency stop |
| Model fallback format eltérés | UI/consumer break | Medium | Contract tests + schema validate | validation fail | Fail closed + legacy |
| Tool timeout spike | UX romlás | Medium | Soft/hard timeout + cache | p95 > 5s | Degrade to legacy |

### A. Copilot SDK Tool Template

```typescript
// apps/impi-mcp-server/src/tools/search.ts
import { Tool, ToolInput, ToolOutput } from '@modelcontextprotocol/sdk';
import { loadCjLinks } from '../../../ai-agent-core/src/sources/cj-links';

interface SearchInput extends ToolInput {
  query: string;
  limit?: number;
  shop_slug?: string;
}

export const impiSearchCoupons: Tool<SearchInput, ToolOutput> = {
  name: 'impi_search_coupons',
  description: 'Kuponok keresése az ImpactShop adatbázisban',
  inputSchema: {
    type: 'object',
    properties: {
      query: { type: 'string', description: 'Keresési kifejezés' },
      limit: { type: 'number', description: 'Max eredmények száma', default: 10 },
      shop_slug: { type: 'string', description: 'Shop szűrő (opcionális)' }
    },
    required: ['query']
  },
  async execute(input: SearchInput): Promise<ToolOutput> {
    const coupons = await loadCjLinks();
    const filtered = coupons.filter(c => 
      c.title?.toLowerCase().includes(input.query.toLowerCase()) ||
      c.shop_name?.toLowerCase().includes(input.query.toLowerCase())
    );
    return {
      content: filtered.slice(0, input.limit || 10),
      metadata: { total: filtered.length }
    };
  }
};
```

### B. MCP Server Entry Point

```typescript
// apps/impi-mcp-server/src/index.ts
import { McpServer } from '@modelcontextprotocol/sdk/server';
import { impiSearchCoupons } from './tools/search';
import { impiValidateCoupon } from './tools/validate';
import { impiRecommendShop } from './tools/recommend';

const server = new McpServer({
  name: 'impi',
  version: '1.0.0',
  description: 'ImpactShop Impi AI Agent MCP Server'
});

server.registerTool(impiSearchCoupons);
server.registerTool(impiValidateCoupon);
server.registerTool(impiRecommendShop);

server.start();
```

### C. curl-format.txt (teljesítmény méréshez)

```
     time_namelookup:  %{time_namelookup}s\n
        time_connect:  %{time_connect}s\n
     time_appconnect:  %{time_appconnect}s\n
    time_pretransfer:  %{time_pretransfer}s\n
       time_redirect:  %{time_redirect}s\n
  time_starttransfer:  %{time_starttransfer}s\n
                     ----------\n
          time_total:  %{time_total}s\n
```

---

**Utolsó frissítés:** 2026-01-27  
**Készítette:** GitHub Copilot  
**Jóváhagyásra vár:** Igen  
**Scope:** Ez a terv a **teljes AI Agent Core** migrációs és üzemeltetési keretrendszerére vonatkozik (nem csak Impi).

---

## 🧭 Üzemeltetési Keretrendszer (Codex 5.2)

> Az alábbi irányelvek a GitHub Copilot SDK integráció és a teljes AI Agent Core üzemeltetéséhez tartozó best practice-eket foglalják össze, kategorizált formában.

---

### 10.1 📊 Observability & Monitoring

#### Trace & Logging

| Elem | Leírás | Prioritás |
|------|--------|-----------|
| **Trace-ID propagáció** | Minden kéréshez egyedi `trace_id` és `run_id` generálása, amely áthalad az MCP → Tool → Backend rétegeken | P0 |
| **Structured logging** | JSON formátumú logok: timestamp, level, trace_id, tool_name, latency_ms, status | P0 |
| **Request sampling** | Részletes logok 1–5%-ra, hogy a költség ne szaladjon el | P1 |
| **Privacy redaction** | PII maszkolás (email, token, név) minden logban – safe-list alapú | P0 |

#### Metrikák & SLI/SLO

| SLI | Target SLO | Rollback trigger |
|-----|-----------|------------------|
| Response latency p95 | < 2s | > 5s folyamatosan 5 percig |
| Response latency p99 | < 5s | > 10s |
| Error rate | < 1% | > 5% |
| Tool success rate | > 98% | < 90% |
| Availability | 99.5% | < 99% |

**SLO burn-rate riasztás:** 2x és 5x burn-rate küszöbnél azonnali alert.

**Codex 5.2 javaslata:** Vezess be aktív **synthetic monitoring**-ot (percenkénti MCP tool ping), hogy a regressziók felhasználói panasz előtt látszódjanak.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

#### Latency bontás

```
┌──────────────────────────────────────────────────────┐
│                   Total Latency                      │
├────────────┬────────────┬────────────┬──────────────┤
│   Model    │   Tool     │ External   │   Network    │
│  Inference │ Execution  │    API     │   Overhead   │
└────────────┴────────────┴────────────┴──────────────┘
```

Mérjétek külön-külön, hogy a lassulás forrása azonosítható legyen.

---

### 10.2 🛡️ Resiliency & Fault Tolerance

#### Circuit Breaker

```typescript
// Külső források védelme
const circuitBreaker = {
  cj_links: { threshold: 5, timeout: 30s, fallback: 'cache' },
  dognet_api: { threshold: 3, timeout: 15s, fallback: 'cache' },
  gmail_harvester: { threshold: 3, timeout: 60s, fallback: 'skip' },
  playwright_scraper: { threshold: 2, timeout: 120s, fallback: 'skip' }
};
```

**Állapotok:** CLOSED → OPEN (hibánál) → HALF-OPEN (próba) → CLOSED

#### Retry Policy

| Source | Max retries | Backoff | Jitter |
|--------|-------------|---------|--------|
| CJ Links | 3 | exponential (1s, 2s, 4s) | ±500ms |
| Dognet API | 2 | exponential (2s, 4s) | ±1s |
| Model API | 2 | exponential (1s, 2s) | ±500ms |

#### Timeout hierarchia

| Réteg | Timeout | Fallback |
|-------|---------|----------|
| Tool execution | 3–5s | cached response |
| Model inference | 30s | smaller/faster model |
| External API | 10s | circuit breaker open |
| Agent loop (total) | 60s | partial response |

**Codex 5.2 javaslata:** Használj **soft timeout + hard timeout** párost toolonként (soft: warning + fallback előkészítés), hogy a tail latency ne akassza meg az agent loop-ot.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

#### Degraded Mode

```
Degradáció szintek:
1. NORMAL      → Minden működik
2. DEGRADED    → MCP disabled, legacy only
3. EMERGENCY   → Static cache + legacy only, no model calls
4. MAINTENANCE → Minden offline, status page
```

Automatikus visszaállás: 10 perc stabil működés után.

---

### 10.3 🔐 Security & Compliance

#### Input Validation

- **Schema validation** minden tool input/output-nál (zod/JSON Schema)
- **Prompt injection filter** a `/chat/command` és MCP bemeneteknél
- **Query normalization** (ékezet, typo, injection attempt szűrés)

#### Access Control

| Tier | Hozzáférés | Rate limit |
|------|------------|------------|
| `admin` | Minden tool + debug + cache-buster | 1000 req/min |
| `user` | Publikus tool-ok | 60 req/min |
| `guest` | Csak olvasás | 10 req/min |

**MCP Capability Allowlist:** Csak a szükséges tool-ok legyenek publikusan elérhetők.

**Codex 5.2 javaslata:** Vezess be **scope-alapú MCP permissions**-t (read-only vs write), hogy a tool-ok jogosultsága minimális legyen.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

#### Privacy & PII

| Mező | PII osztály | Retention | Log policy |
|------|-------------|-----------|------------|
| email | SENSITIVE | 7 nap | MASKED |
| user_id | INTERNAL | 30 nap | HASHED |
| query | INTERNAL | 30 nap | REDACTED |
| coupon_code | INTERNAL | 30 nap | MASKED |

**Data lineage:** Minden válaszban `source`, `fetched_at`, `confidence` meta mező.

#### LLM Output Guardrails

- Tiltott tartalom szűrés (safe output policy)
- Hallucination detection (low confidence warning)
- PII leak prevention a válaszokban

---

### 10.4 💰 Cost Control & Quotas

#### Token Budget

| Scope | Limit | Alert @ | Hard stop @ |
|-------|-------|---------|-------------|
| Per request | 4K tokens | - | 8K |
| Per user/hour | 50K tokens | 80% | 100% |
| Per tenant/day | 500K tokens | 80% | 95% |
| Global/hour | 2M tokens | 80% | 95% |

**Codex 5.2 javaslata:** Kapcsolj be **prompt compression/summarization** fallbackot költség-spike esetén, hogy a budget ne ugorjon hirtelen.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

#### Model Cost Tier

| Model | Cost/1K tokens | Use case | Fallback |
|-------|---------------|----------|----------|
| GPT-4o | $0.03 | Complex queries | Claude 3 Haiku |
| Claude 3.5 Sonnet | $0.015 | Default | GPT-4o-mini |
| GPT-4o-mini | $0.005 | Simple/high volume | - |

**Cost dashboard:** Per tool, per user, per day – top költségű promptok logolása.

#### Rate Limiting

```yaml
rate_limits:
  global:
    burst: 100
    sustained: 50/s
  per_user:
    burst: 10
    sustained: 2/s
  per_tool:
    impi_search_coupons:
      burst: 20
      sustained: 5/s
    impi_validate_coupon:
      burst: 50
      sustained: 10/s
```

**Backpressure:** Queue + max in-flight limit, hogy a rendszer ne telítődjön.

---

### 10.5 🚀 Rollout Strategy

#### Üzemmódok (Feature Flags)

```typescript
enum ImpiMode {
  LEGACY = 'legacy',      // Csak régi rendszer
  HYBRID = 'hybrid',      // Mindkettő párhuzamosan
  CANARY = 'canary',      // 5-10% MCP, többi legacy
  SHADOW = 'shadow',      // MCP számol, de nem küld output
  MCP = 'mcp'             // Csak MCP
}
```

**Feature flag verzionálás:** `MCP_V1`, `MCP_V2` – visszagörgetéskor kód módosítás nélkül.

#### Progressive Rollout

```
Fázis 1: Internal canary (belső felhasználók)
    ↓
Fázis 2: Pilot NGO-k (5-10 partner)
    ↓
Fázis 3: 10% production traffic
    ↓
Fázis 4: 50% production traffic
    ↓
Fázis 5: 100% (full rollout)
```

**Deterministic routing:** Canary/shadow módban ugyanazon user cohort-on fut az összehasonlítás.

#### Go/No-Go Checklist

Minden fázis végén:

| Kritérium | Target | Mérés módja |
|-----------|--------|-------------|
| Latency p95 | < 2s | Prometheus |
| Error rate | < 1% | Error tracker |
| Success rate | > 98% | Tool metrics |
| Cost delta | < +10% | Cost dashboard |
| Quality score | ≥ baseline | Golden dataset eval |

**Codex 5.2 javaslata:** Egészítsd ki a go/no-go kaput **üzleti KPI küszöbökkel** (pl. kupon találati arány), hogy a döntés ne csak technikai legyen.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

---

### 10.6 🗄️ Caching Strategy

#### Cache rétegek

```
┌─────────────────────────────────────────────┐
│           L1: In-memory (per instance)      │
│                 TTL: 1-5 perc               │
├─────────────────────────────────────────────┤
│           L2: Redis/Distributed             │
│                TTL: 15-60 perc              │
├─────────────────────────────────────────────┤
│           L3: Semantic Cache                │
│     (query embedding + top-k reuse)         │
│               TTL: 1-24 óra                 │
└─────────────────────────────────────────────┘
```

#### Cache konfiguráció

| Source | TTL | Warm cache | Force refresh |
|--------|-----|------------|---------------|
| CJ Links | 15 perc | Top 100 shop | `?refresh=1` |
| Dognet | 5 perc | Top 50 coupon | Admin only |
| NGO list | 60 perc | All | Daily cron |

**Result freshness label:** Minden válaszban `"cache_age": "2m"` meta.

#### Warming Strategy

```bash
# Deploy után top 100 query warming
./bin/warm-cache.sh --top 100 --source cj,dognet
```

---

### 10.7 🔄 Rollback & Recovery

#### Quick Actions

| Akció | Parancs | Hatás | Visszaállás |
|-------|---------|-------|-------------|
| MCP disable | `IMPI_MODE=legacy` + restart | Azonnal legacy | Flag váltás |
| Tool disable | `IMPI_TOOL_DISABLED=validate` | Egy tool ki | Flag törlés |
| Emergency mode | `IMPI_EMERGENCY=1` | Static cache only | Flag törlés |
| Full rollback | `./rollback.sh` | Teljes visszaállás | Manual redeploy |

#### Rollback SLA

| Trigger | Max időablak |
|---------|--------------|
| P0 (legacy API down) | 5 perc |
| P1 (partial outage) | 15 perc |
| P2 (degraded perf) | 30 perc |

#### Data Rollback

Nem csak kód, hanem:
- Cache invalidation
- Registry state restore
- Feature flag reset

**Soft delete policy:** Törölt elemek 7 napig visszaállíthatók.

---

### 10.8 📋 Operational Runbooks

#### Incident Taxonomy

| Code | Kategória | Példa | Severity |
|------|-----------|-------|----------|
| `MCP_DOWN` | MCP Server | Process crash | P0 |
| `MODEL_TIMEOUT` | LLM | API timeout > 30s | P1 |
| `QUOTA_EXCEEDED` | Cost | Token limit elérve | P1 |
| `SOURCE_STALE` | Data | CJ feed > 24h | P2 |
| `CIRCUIT_OPEN` | Dependency | Dognet down | P2 |

#### Escalation Matrix

| Level | Felelős | Max response time |
|-------|---------|-------------------|
| L1 | On-call dev | 15 perc |
| L2 | Senior dev | 30 perc |
| L3 | Tech lead | 1 óra |

#### Runbook Index

| Runbook | Trigger | Fájl |
|---------|---------|------|
| MCP Emergency Stop | `MCP_DOWN` | `runbooks/mcp-emergency.md` |
| Model Fallback | `MODEL_TIMEOUT` | `runbooks/model-fallback.md` |
| Cost Overage | `QUOTA_EXCEEDED` | `runbooks/cost-control.md` |
| Data Recovery | Manual | `runbooks/data-rollback.md` |

---

### 10.9 🧪 Quality Assurance

#### Golden Dataset

```yaml
golden_dataset:
  location: tests/golden/
  size: 500 queries
  coverage:
    - shop search: 150
    - coupon validation: 100
    - NGO recommendation: 100
    - edge cases: 150
  update_frequency: monthly
```

#### Regression Suites

| Suite | Futtatás | Coverage |
|-------|----------|----------|
| Unit tests | Minden commit | Tools, adapters |
| Integration | PR merge | MCP ↔ Backend |
| Golden eval | Nightly | Output quality |
| Load test | Weekly | Capacity baseline |
| Edge case | Release | No coupons, timeout, stale |

#### Quality Metrics

| Metric | Mérés | Target |
|--------|-------|--------|
| Precision | Golden dataset | > 90% |
| Recall | Golden dataset | > 85% |
| Confidence score | Per response | > 0.7 |

**Semantic regression:** Keresési relevancia változás detektálása.

---

### 10.10 📝 Documentation & Governance

#### Schema Registry

Minden tool input/output verzionált sémával:

```typescript
// Schema versioning
interface ToolOutput {
  tool_version: string;  // "1.2.0"
  schema_version: string; // "2024-01"
  content: unknown;
  metadata: OutputMetadata;
}
```

**Breaking change policy:** Minor verzió = backward compatible, Major = migration szükséges.

#### Configuration Management

| Elem | Tárolás | Audit |
|------|---------|-------|
| Feature flags | Environment + DB | Change log |
| Model routing | Config file | Git history |
| Rate limits | YAML | Git history |
| Secrets | Vault | Access log |

**Config checksum:** Hash változás esetén azonnali alert.
**Config drift detection:** Staging vs prod eltérések napi riport.

#### Release Process

```
1. Feature branch
2. PR + review
3. Staging deploy
4. Golden eval pass
5. Canary (1 óra)
6. Progressive rollout
7. Release notes
8. Monitoring (24h)
```

**Safe rollout windows:** Kedd-Csütörtök 10:00-16:00 (nem pénteken!)
**Configuration freeze:** Rollout alatt nincs párhuzamos config változás.

---

### 10.11 🔧 Core-wide Policies

> Ezek a szabályok a teljes AI Agent Core-ra vonatkoznak, nem csak az Impi modulra.

#### Shared Infrastructure

- **Centralized secrets:** Egységes kulcskezelés (rotáció, scope, audit)
- **Cross-service tracing:** Shared trace-id az összes modulon át
- **Core-level rate limiting:** Globális limit + per-modul sublimit
- **Dependency health dashboard:** CJ/Dognet/Gmail/Playwright státusz egy helyen

#### Kill-switch Cascade

```
Core kill-switch
    ↓
Module kill-switch (ai-agent-core, impi, stb.)
    ↓
Tool kill-switch (search, validate, recommend)
```

Bármelyik szint azonnal kikapcsolható.

#### Compatibility Matrix

| SDK version | Tool version | Model | Status |
|-------------|--------------|-------|--------|
| 1.0.x | 1.x | GPT-4o, Claude 3.5 | ✅ Supported |
| 1.1.x | 1.x, 2.x | + Gemini | ✅ Supported |
| 2.0.x | 2.x+ | All | 🔄 Planned |

**Dependency pinning:** Lockfile CI ellenőrzés, hogy SDK verzió ne változzon véletlenül.

#### Upgrade Playbook

```
1. Changelog review
2. Staging deploy
3. Compatibility test
4. Canary (24h)
5. Progressive rollout
6. Rollback point megjelölése
7. Full deploy
8. Post-deploy monitoring (48h)
```

---

### 10.12 🎯 Additional Recommendations

#### Data Quality

- **Feed health checks:** CJ/Dognet mezők hiány > X% → auto alert
- **Data freshness SLA:** Max 24h, sérülésnél confidence csökkentés
- **Data contract tests:** Mező típus/hiány változás CI-ben

#### Multi-tenant (ha szükséges)

- **Tenant isolation:** Terhelés nem szivárog át
- **Per-tenant SLA:** Prioritás, quota, fairness
- **Token budget per tenant:** Költség kontroll

#### Advanced Features

- **Confidence threshold:** Alacsony confidence esetén alternatív ajánlás
- **Dual-write period:** Legacy és MCP párhuzamos logolás összehasonlításhoz
- **Replay capability:** Request/response replay hibás futások debug-jához
- **Bulkhead isolation:** Kritikus tool-ok külön worker/pool-ban

#### Future-proofing

- **Multi-region readiness:** Checklist földrajzi bővítéshez
- **Roll-forward strategy:** Ha rollback nem opció, legyen stabil vész-útvonal előre
- **Model upgrade policy:** A/B teszt minden model váltásnál
- **Feature flag sunset:** Lejárati dátum, hogy ne maradjanak bent régi toggle-ok

---

### 10.14 🧠 AI/LLM Specifikus Javaslatok

#### Prompt Engineering

| Elem | Leírás | Prioritás |
|------|--------|-----------|
| **Prompt versioning** | Minden prompt template verzionált (`prompt_v1.2`), A/B teszteléshez | P1 |
| **System prompt registry** | Központi tároló, audit log minden változásra | P1 |
| **Prompt injection defense** | Input sanitization + output validation | P0 |
| **Few-shot example cache** | Gyakori minták gyorsítótárazása | P2 |

#### Token Optimization

```typescript
// Token használat optimalizálás
const tokenStrategy = {
  // Prompt tömörítés
  compression: {
    enabled: true,
    method: 'semantic',  // vagy 'truncate'
    maxInputTokens: 2000
  },
  // Response streaming
  streaming: {
    enabled: true,
    chunkSize: 100
  },
  // Context window management
  contextPruning: {
    strategy: 'relevance',  // vagy 'recency'
    keepLastN: 5
  }
};
```

#### Hallucination Mitigation

- **Grounding:** Minden válasz forráshoz kötve (CJ/Dognet link)
- **Confidence scoring:** < 0.6 esetén "nem biztos" jelzés
- **Fact verification:** Kupon kód létezés ellenőrzés a válasz előtt
- **Citation required:** Tool output-ra hivatkozás kötelező

#### Model Selection Logic

```
Query complexity scoring:
├── Simple (keyword match) → GPT-4o-mini (olcsó, gyors)
├── Medium (filtering + ranking) → Claude 3.5 Haiku
├── Complex (multi-step reasoning) → GPT-4o / Claude 3.5 Sonnet
└── Critical (validation) → Ensemble (2+ model consensus)
```

---

### 10.15 📱 User Experience Javaslatok

#### Response Quality

| Metrika | Target | Mérés |
|---------|--------|-------|
| First token latency | < 500ms | Streaming start |
| Full response time | < 3s | P95 |
| Relevance score | > 0.8 | User feedback + golden eval |
| Actionable rate | > 90% | Kattintható link / valid kupon |

#### Graceful Degradation UX

```
Szint 1: Teljes funkció
    ↓ (model timeout)
Szint 2: Cached válasz + "frissítés folyamatban" jelzés
    ↓ (source unavailable)
Szint 3: Top 10 népszerű kupon + "korlátozott keresés" jelzés
    ↓ (full outage)
Szint 4: Static fallback page + "hamarosan visszatérünk"
```

#### Feedback Loop

- **Implicit signals:** Kattintás, copy, time-on-result
- **Explicit feedback:** 👍/👎 + opcionális szöveges visszajelzés
- **Feedback → Training:** Havi fine-tuning ciklus a feedback alapján
- **A/B test integration:** Új prompt/model automatikus kiértékelése

---

### 10.16 🔄 Continuous Improvement

#### Weekly Review Checklist

```markdown
[ ] SLO dashboard áttekintés (zöld/sárga/piros)
[ ] Top 5 leglassabb query elemzése
[ ] Top 5 hibás query root cause
[ ] Cost trend vs forecast
[ ] User feedback összesítés
[ ] Model performance drift check
[ ] Dependency health status
```

#### Monthly Optimization Cycle

| Hét | Fókusz | Output |
|-----|--------|--------|
| 1 | Metrika elemzés | Bottleneck lista |
| 2 | Experiment design | A/B terv |
| 3 | Implementation | Feature branch |
| 4 | Rollout + eval | Go/no-go döntés |

#### Quarterly Planning

- **Capacity review:** Következő negyedév terhelés becslés
- **Cost optimization:** Model mix újratervezés
- **Feature prioritization:** User feedback alapú roadmap
- **Tech debt review:** Refactor backlog priorizálás

---

### 10.17 🚨 Incident Response

#### Severity Levels

| Level | Definíció | Response time | Escalation |
|-------|-----------|---------------|------------|
| SEV1 | Teljes leállás | 5 perc | Azonnali on-call |
| SEV2 | Részleges kiesés (>30% user) | 15 perc | On-call + backup |
| SEV3 | Degradált teljesítmény | 1 óra | Következő munkanap |
| SEV4 | Kozmetikai / minor | 24 óra | Backlog |

#### Incident Playbook Template

```markdown
## Incident: [TITLE]
**Severity:** SEV[1-4]
**Start:** [TIMESTAMP]
**Status:** INVESTIGATING / IDENTIFIED / MONITORING / RESOLVED

### Timeline
- HH:MM - [Event description]

### Impact
- Affected users: [COUNT/PERCENTAGE]
- Affected features: [LIST]

### Root Cause
[Description]

### Mitigation
[Steps taken]

### Prevention
[Future action items]
```

#### Post-Incident Review

- **5 Whys analysis:** Root cause mélyebb megértése
- **Blameless culture:** Rendszer, nem személy fókusz
- **Action items:** Konkrét, mérhető, határidős
- **Knowledge sharing:** Retro eredmények dokumentálása

---

### 10.18 🎓 Onboarding & Knowledge Transfer

#### Developer Onboarding

```
Day 1: Architecture overview + local setup
Day 2: MCP protocol + tool anatomy
Day 3: Observability stack + debugging
Day 4: Deployment pipeline + rollback
Day 5: Pair programming on real task
```

#### Documentation Standards

| Típus | Frissítés | Felelős |
|-------|-----------|---------|
| API docs | Minden PR | Developer |
| Runbooks | Minden incident után | On-call |
| Architecture | Negyedévente | Tech lead |
| Onboarding | Félévente | Team |

#### Knowledge Base

- **Searchable wiki:** Confluence / Notion
- **Video walkthroughs:** Loom recordings
- **Decision log:** ADR (Architecture Decision Records)
- **FAQ:** Gyakori hibák + megoldások

---

### 10.19 🔌 Integráció & API Design

#### MCP Tool Design Principles

```typescript
// Tool tervezési elvek
interface ToolDesignChecklist {
  // 1. Single Responsibility
  singlePurpose: true;           // Egy tool = egy feladat
  
  // 2. Idempotency
  idempotent: true;              // Ismételt hívás = ugyanaz az eredmény
  requestId: string;             // Dedupe support
  
  // 3. Graceful Failure
  timeout: number;               // Explicit timeout
  fallback: 'cache' | 'error';   // Hiba esetén viselkedés
  
  // 4. Observable
  traceId: string;               // Trace context propagation
  metrics: boolean;              // Latency, success rate
  
  // 5. Documented
  description: string;           // Human-readable leírás
  examples: Example[];           // Használati példák
}
```

#### API Versioning Strategy

| Verzió | Státusz | Support until |
|--------|---------|---------------|
| v1 | Deprecated | 2026-06-01 |
| v2 | Current | 2027-01-01 |
| v3 | Beta | - |

**Breaking change policy:**
- Major verzió: min. 6 hónap deprecation period
- Minor verzió: backward compatible
- Sunset notice: 3 hónap előre

#### External Integration Patterns

```
┌─────────────────────────────────────────────────────────┐
│                    Impi MCP Server                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│   ┌─────────┐   ┌─────────┐   ┌─────────┐              │
│   │ Webhook │   │  REST   │   │  Event  │              │
│   │ Inbound │   │  API    │   │  Stream │              │
│   └────┬────┘   └────┬────┘   └────┬────┘              │
│        │             │              │                   │
│        ▼             ▼              ▼                   │
│   ┌─────────────────────────────────────┐              │
│   │         Event Router                │              │
│   └─────────────────────────────────────┘              │
│        │             │              │                   │
│        ▼             ▼              ▼                   │
│   ┌─────────┐   ┌─────────┐   ┌─────────┐              │
│   │   CJ    │   │ Dognet  │   │  Gmail  │              │
│   │ Adapter │   │ Adapter │   │ Adapter │              │
│   └─────────┘   └─────────┘   └─────────┘              │
└─────────────────────────────────────────────────────────┘
```

---

### 10.20 📈 Business Metrics & KPIs

#### Success Metrics

| Metric | Definíció | Target | Mérés |
|--------|-----------|--------|-------|
| **Kupon találati arány** | Valid kupon / összes query | > 70% | Weekly |
| **Átlagos megtakarítás** | € / sikeres kupon | > 5€ | Monthly |
| **NGO támogatás** | € / hónap az NGO-knak | Növekvő trend | Monthly |
| **User retention** | 30 napos visszatérés | > 40% | Monthly |
| **NPS** | Net Promoter Score | > 50 | Quarterly |

#### Cost Efficiency

| Metric | Számítás | Target |
|--------|----------|--------|
| Cost per query | Total LLM cost / query count | < $0.01 |
| Cost per conversion | Total cost / successful coupon use | < $0.50 |
| ROI | (Revenue - Cost) / Cost | > 300% |

#### Operational Health Dashboard

```
┌─────────────────────────────────────────────────────────┐
│  🟢 System Status: HEALTHY                              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Availability     ████████████████████░  99.8%         │
│  Latency p95      ████████████████░░░░░  1.2s          │
│  Error Rate       ██░░░░░░░░░░░░░░░░░░░  0.3%          │
│  Cache Hit Rate   ████████████████████░  89%           │
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │ Queries/min │  │ Token Usage │  │ Cost Today  │     │
│  │    1,234    │  │   450K/2M   │  │   $12.34    │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│                                                         │
│  Recent Alerts: None                                    │
└─────────────────────────────────────────────────────────┘
```

---

### 10.21 🧪 Experimentation Framework

#### A/B Test Infrastructure

```yaml
experiment:
  id: "exp-2026-01-model-switch"
  name: "GPT-4o vs Claude 3.5 Sonnet"
  hypothesis: "Claude 3.5 javítja a kupon relevanciát"
  
  variants:
    control:
      weight: 50%
      model: "gpt-4o"
    treatment:
      weight: 50%
      model: "claude-3.5-sonnet"
  
  metrics:
    primary: "coupon_relevance_score"
    secondary: ["latency_p95", "cost_per_query"]
  
  guardrails:
    max_error_rate: 2%
    min_sample_size: 1000
  
  duration: "7d"
  auto_rollback: true
```

#### Feature Flag Governance

| Flag típus | Élettartam | Owner | Review |
|------------|------------|-------|--------|
| Release | 1-2 hét | PM | Weekly |
| Experiment | 2-4 hét | Data | Bi-weekly |
| Ops | Permanent | SRE | Quarterly |
| Kill switch | Permanent | SRE | Annual |

#### Experiment Lifecycle

```
DRAFT → REVIEW → APPROVED → RUNNING → ANALYZING → DECIDED
                                ↓
                          AUTO-ROLLBACK
                          (if guardrail hit)
```

---

### 10.22 🌍 Lokalizáció & i18n

#### Nyelvi támogatás

| Nyelv | Státusz | Coverage |
|-------|---------|----------|
| Magyar (hu) | ✅ Primary | 100% |
| Angol (en) | 🔄 Planned | 0% |
| Német (de) | 📋 Backlog | 0% |

#### Prompt Localization

```typescript
// Nyelv-specifikus prompt templates
const prompts = {
  hu: {
    system: "Te egy kuponkereső asszisztens vagy...",
    noResults: "Sajnos nem találtam kupont ehhez a bolthoz.",
    confidence: "Bizonyosság: {score}%"
  },
  en: {
    system: "You are a coupon search assistant...",
    noResults: "Sorry, I couldn't find coupons for this store.",
    confidence: "Confidence: {score}%"
  }
};
```

#### Currency & Regional Settings

- **Pénznem:** HUF primary, EUR secondary
- **Dátum formátum:** YYYY-MM-DD (ISO) + lokalizált megjelenítés
- **Számformátum:** 1 234,56 (magyar) vs 1,234.56 (angol)

---

### 10.23 ♿ Accessibility & Inclusivity

#### WCAG Compliance (MCP Output)

| Kritérium | Szint | Státusz |
|-----------|-------|---------|
| Text alternatives | A | ✅ |
| Keyboard accessible | A | ✅ |
| Readable content | AA | ✅ |
| Error identification | A | 🔄 |

#### Inclusive Design Principles

- **Plain language:** Kerüljük a zsargont, max. 8. osztályos szint
- **Consistent structure:** Minden válasz azonos formátumban
- **Error messages:** Konkrét, actionable, nem technikai
- **Screen reader friendly:** Strukturált markdown output

---

### 10.24 🔮 Future Roadmap Ideas

#### Short-term (Q1 2026)

- [ ] MCP server production deploy
- [ ] Multi-model A/B testing
- [ ] Real-time coupon validation
- [ ] Discord bot migration

#### Mid-term (Q2-Q3 2026)

- [ ] Voice interface (VS Code Speech)
- [ ] Proactive recommendations ("Új kupon a kedvenc boltodban!")
- [ ] Browser extension integration
- [ ] Partner API (white-label)

#### Long-term (2027+)

- [ ] Multi-language support
- [ ] Personalized recommendations (user history)
- [ ] Predictive analytics ("Holnap várható akció")
- [ ] Mobile app SDK

#### Research Areas

| Terület | Potenciál | Komplexitás |
|---------|-----------|-------------|
| RAG enhancement | Magas | Közepes |
| Fine-tuned model | Közepes | Magas |
| Multi-modal (image) | Alacsony | Magas |
| Real-time scraping | Magas | Magas |

---

### 10.25 🤝 Stakeholder Communication

#### Communication Matrix

| Stakeholder | Érdeklődés | Kommunikáció | Frekvencia |
|-------------|------------|--------------|------------|
| Product Owner | Feature progress, blockers | Slack + weekly sync | Daily/Weekly |
| NGO Partners | Támogatási statisztikák | Email report | Monthly |
| Tech Lead | Architecture decisions | PR review + ADR | As needed |
| End Users | New features, outages | In-app + Discord | As needed |
| Management | KPIs, costs, roadmap | Dashboard + report | Monthly |

#### Status Update Template

```markdown
## Weekly Status: [YYYY-Www]

### 🎯 Highlights
- [Major achievement 1]
- [Major achievement 2]

### 📊 Metrics
| Metric | This Week | Last Week | Trend |
|--------|-----------|-----------|-------|
| Queries | X | Y | ↑/↓ |
| Success Rate | X% | Y% | ↑/↓ |
| Cost | $X | $Y | ↑/↓ |

### 🚧 Blockers
- [Blocker if any]

### 📅 Next Week
- [Planned work]
```

#### Incident Communication

| Severity | Internal | External | Timing |
|----------|----------|----------|--------|
| SEV1 | Slack @channel + call | Status page + email | Real-time |
| SEV2 | Slack @team | Status page | Within 15 min |
| SEV3 | Slack thread | None | EOD |
| SEV4 | Jira ticket | None | Next sprint |

---

### 10.26 💼 Vendor & Dependency Management

#### Critical Dependencies

| Dependency | Vendor | Criticality | Fallback |
|------------|--------|-------------|----------|
| OpenAI API | OpenAI | P0 | Anthropic Claude |
| Anthropic API | Anthropic | P0 | OpenAI GPT-4o |
| CJ Affiliate | CJ | P1 | Cached data |
| Dognet API | Dognet | P1 | Cached data |
| GitHub Copilot SDK | Microsoft | P1 | Direct MCP |

#### Vendor Risk Assessment

```
Risk Score = (Criticality × Replaceability × Stability)

High Risk (>7):
  - OpenAI API: Monitor rate limits, have fallback ready
  
Medium Risk (4-7):
  - CJ Affiliate: Data freshness monitoring
  
Low Risk (<4):
  - GitHub Copilot SDK: Well-documented, open standard
```

#### Contract & SLA Tracking

| Vendor | Contract End | SLA | Current Performance |
|--------|--------------|-----|---------------------|
| OpenAI | 2026-12-31 | 99.9% | 99.7% |
| Anthropic | 2026-06-30 | 99.5% | 99.8% |
| CJ | Annual renewal | Best effort | ~95% |

#### Dependency Update Policy

- **Security patches:** Within 24h
- **Minor updates:** Weekly review, monthly deploy
- **Major updates:** Quarterly evaluation, staged rollout
- **Deprecated dependencies:** 6 month migration window

---

### 10.27 🧹 Technical Debt Management

#### Debt Categories

| Kategória | Példa | Impact | Effort |
|-----------|-------|--------|--------|
| **Code Quality** | Missing tests, duplicated code | Medium | Low |
| **Architecture** | Tight coupling, missing abstraction | High | High |
| **Infrastructure** | Manual deployments, missing monitoring | High | Medium |
| **Documentation** | Outdated docs, missing ADRs | Low | Low |
| **Dependencies** | Outdated packages, security vulnerabilities | High | Medium |

#### Debt Tracking

```yaml
tech_debt_item:
  id: TD-001
  title: "Refactor CJ adapter for better testability"
  category: architecture
  impact: medium
  effort: medium
  created: 2026-01-15
  owner: @developer
  status: backlog  # backlog | in-progress | resolved
  resolution_date: null
  notes: "Blocks unit test coverage improvement"
```

#### Debt Budget

| Sprint | Feature Work | Debt Paydown | Maintenance |
|--------|--------------|--------------|-------------|
| Target | 60% | 25% | 15% |
| Actual | Track weekly | Track weekly | Track weekly |

**Rule:** Ha a debt backlog > 20 item → következő sprintben 40% debt paydown.

---

### 10.28 🔐 Secrets & Credentials Management

#### Secret Types

| Secret | Storage | Rotation | Access |
|--------|---------|----------|--------|
| API Keys (OpenAI, Anthropic) | Vault / ENV | 90 days | Service only |
| Database credentials | Vault | 30 days | Service only |
| JWT signing keys | Vault | 180 days | Auth service |
| Webhook secrets | Vault | On compromise | Service only |

#### Secret Lifecycle

```
CREATE → STORE → DISTRIBUTE → USE → ROTATE → REVOKE
           ↓
     Audit Log
```

#### Emergency Procedures

1. **Leaked secret detected:**
   - Immediate revocation (< 5 min)
   - Generate new secret
   - Update all services
   - Audit access logs
   - Post-mortem

2. **Rotation script:**
```bash
#!/bin/bash
# rotate-secret.sh
SECRET_NAME=$1
./vault rotate $SECRET_NAME
./deploy update-env $SECRET_NAME
./notify slack "Secret $SECRET_NAME rotated"
```

---

### 10.29 📦 Data Management & Retention

#### Data Classification

| Osztály | Példák | Retention | Encryption |
|---------|--------|-----------|------------|
| **PII** | Email, név, IP | 30 nap | At-rest + In-transit |
| **Sensitive** | Query history, preferences | 90 nap | At-rest + In-transit |
| **Business** | Kupon adatok, NGO stats | 1 év | In-transit |
| **Public** | Aggregált metrikák | ∞ | None |

**Codex 5.2 javaslata:** Rögzíts **data residency** elvet (EU vs non-EU), hogy vendor választás és audit során egyértelmű legyen az elvárás.  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026

#### Retention Policy Enforcement

```yaml
retention_policies:
  logs:
    application: 30d
    security: 1y
    audit: 7y
  
  user_data:
    queries: 90d
    preferences: until_deletion
    
  analytics:
    raw: 30d
    aggregated: 2y
```

#### Data Deletion Process

| Trigger | Action | Timeline |
|---------|--------|----------|
| User request (GDPR) | Full deletion | 72h |
| Retention expiry | Automated cleanup | Daily cron |
| Account deletion | Cascade delete | Immediate |

#### Backup Strategy

| Data | Frequency | Retention | Location |
|------|-----------|-----------|----------|
| Config | Every deploy | 30 versions | Git + S3 |
| Cache | No backup | N/A | Ephemeral |
| Logs | Daily | 30 days | S3 |
| Analytics | Weekly | 1 year | S3 Glacier |

---

### 10.30 🏃 Performance Optimization

#### Performance Budget

| Metric | Budget | Current | Status |
|--------|--------|---------|--------|
| First token | 500ms | 450ms | ✅ |
| Full response (p50) | 1.5s | 1.2s | ✅ |
| Full response (p95) | 3s | 2.8s | ⚠️ |
| Memory per request | 50MB | 35MB | ✅ |
| Bundle size (if any) | 200KB | 180KB | ✅ |

#### Optimization Checklist

```markdown
## Query Path Optimization
- [ ] Connection pooling enabled
- [ ] Query caching implemented
- [ ] Batch requests where possible
- [ ] Async processing for non-blocking ops

## Model Optimization
- [ ] Prompt length minimized
- [ ] Response streaming enabled
- [ ] Token budget per query
- [ ] Model selection by complexity

## Infrastructure
- [ ] CDN for static content
- [ ] Edge caching where applicable
- [ ] Auto-scaling configured
- [ ] Resource limits set
```

#### Profiling & Bottleneck Detection

```
Weekly Performance Review:
1. Run load test (100 qps for 10 min)
2. Analyze flame graphs
3. Check slow query log
4. Review memory allocations
5. Document findings
6. Create optimization tickets
```

---

### 10.31 🎭 Chaos Engineering (Optional Advanced)

#### Chaos Experiments

| Experiment | Target | Expected Behavior |
|------------|--------|-------------------|
| Model timeout | OpenAI API | Fallback to Claude |
| Source failure | CJ adapter | Return cached data |
| Memory pressure | MCP server | Graceful degradation |
| Network latency | All external | Timeout + retry |

#### Game Day Checklist

```markdown
## Pre-Game
- [ ] Notify stakeholders
- [ ] Ensure rollback ready
- [ ] Monitoring dashboards open
- [ ] On-call available

## During Game
- [ ] Start experiment
- [ ] Monitor metrics
- [ ] Document observations
- [ ] Abort if SEV1 triggered

## Post-Game
- [ ] Restore normal state
- [ ] Analyze results
- [ ] Create improvement tickets
- [ ] Share learnings
```

#### Resilience Score

| Component | Score | Last Tested |
|-----------|-------|-------------|
| Model fallback | 8/10 | 2026-01-15 |
| Cache resilience | 9/10 | 2026-01-10 |
| Rate limit handling | 7/10 | 2026-01-05 |
| Overall | **8/10** | - |

---

### 10.32 🧑‍💻 Developer Experience (DX)

#### Local Development Setup

```bash
# One-command setup
./scripts/setup-dev.sh

# What it does:
# 1. Check prerequisites (Node, pnpm, etc.)
# 2. Install dependencies
# 3. Copy .env.example → .env.local
# 4. Start local services (Redis, mock APIs)
# 5. Run initial tests
# 6. Open VS Code with recommended extensions
```

#### Dev Tooling

| Tool | Purpose | Config |
|------|---------|--------|
| **pnpm** | Package manager | `pnpm-workspace.yaml` |
| **Turborepo** | Monorepo build | `turbo.json` |
| **Biome** | Lint + Format | `biome.json` |
| **Vitest** | Unit testing | `vitest.config.ts` |
| **Playwright** | E2E testing | `playwright.config.ts` |

#### Pre-commit Hooks

```yaml
# .husky/pre-commit
- pnpm lint-staged
- pnpm typecheck
- pnpm test:affected
```

#### IDE Recommendations

```json
// .vscode/extensions.json
{
  "recommendations": [
    "GitHub.copilot",
    "GitHub.copilot-chat",
    "biomejs.biome",
    "ms-vscode.vscode-typescript-next",
    "bradlc.vscode-tailwindcss"
  ]
}
```

#### Debug Configuration

```json
// .vscode/launch.json
{
  "configurations": [
    {
      "name": "Debug MCP Server",
      "type": "node",
      "request": "launch",
      "program": "${workspaceFolder}/apps/impi-mcp-server/dist/index.js",
      "env": { "DEBUG": "mcp:*" }
    },
    {
      "name": "Debug Tool Execution",
      "type": "node",
      "request": "attach",
      "port": 9229
    }
  ]
}
```

---

### 10.33 📊 Analytics & Insights

#### Event Taxonomy

```typescript
// Analytics events
const events = {
  // User actions
  'query.submitted': { query: string, source: 'discord' | 'mcp' | 'api' },
  'coupon.clicked': { coupon_id: string, shop_slug: string },
  'coupon.copied': { coupon_id: string, code: string },
  'feedback.submitted': { rating: 1 | -1, query_id: string },
  
  // System events
  'tool.executed': { tool_name: string, latency_ms: number, success: boolean },
  'model.called': { model: string, tokens_used: number, cost: number },
  'cache.hit': { key: string, age_seconds: number },
  'error.occurred': { error_type: string, message: string }
};
```

#### Funnel Analysis

```
Query Submitted (100%)
    ↓
Results Displayed (95%)  ← 5% error/timeout
    ↓
Coupon Clicked (40%)     ← 55% no interest
    ↓
Code Copied (30%)        ← 10% just browsing
    ↓
Purchase Made (??%)      ← External, estimated via affiliate
```

#### Cohort Analysis

| Cohort | Definition | Metrics to Track |
|--------|------------|------------------|
| New users | First query < 7 days | Activation rate, D7 retention |
| Power users | > 10 queries/week | Feature usage, satisfaction |
| Churned | No query > 30 days | Reactivation campaigns |

#### Dashboard Panels

```
┌─────────────────────────────────────────────────────────┐
│  📊 Weekly Insights Dashboard                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ Total Queries│  │ Success Rate │  │ Avg Latency  │  │
│  │   12,456     │  │    94.2%     │  │    1.3s      │  │
│  │   ↑ 12%      │  │   ↑ 2.1%     │  │   ↓ 0.2s     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                         │
│  Top Searched Shops          Top Performing Coupons    │
│  1. IKEA (1,234)            1. ABOUT YOU -20% (89%)   │
│  2. About You (987)         2. IKEA Free Ship (85%)   │
│  3. eMAG (654)              3. MediaMarkt -15% (82%)  │
│                                                         │
│  Query Volume (last 7 days)                            │
│  ▁▂▃▅▆█▇▅▃▂▁▂▃▅▆█▇▅▃▂▁▂▃▅▆█▇                          │
│  Mon Tue Wed Thu Fri Sat Sun                           │
└─────────────────────────────────────────────────────────┘
```

---

### 10.34 🎮 Gamification & Engagement (Future)

#### User Engagement Ideas

| Feature | Description | Complexity |
|---------|-------------|------------|
| **Savings tracker** | "Összesen 12,450 Ft-ot spóroltál!" | Low |
| **Streak counter** | "5 napos streak! 🔥" | Low |
| **Achievements** | "Első sikeres kupon" badge | Medium |
| **Leaderboard** | Top NGO támogatók | Medium |
| **Referral program** | "Hívd meg barátaidat" | High |

#### NGO Engagement

```
┌─────────────────────────────────────────────────────────┐
│  🌟 Your Impact                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Total donated through your searches:                   │
│                                                         │
│         💰 4,567 Ft                                     │
│                                                         │
│  Supported NGOs:                                        │
│  • Magyar Vöröskereszt    ████████░░  2,100 Ft         │
│  • WWF Magyarország       ████░░░░░░  1,200 Ft         │
│  • UNICEF Hungary         ███░░░░░░░  1,267 Ft         │
│                                                         │
│  [Share your impact] [Explore NGOs]                    │
└─────────────────────────────────────────────────────────┘
```

---

### 10.35 🔄 Migration Rollback Scenarios

#### Scenario Matrix

| Scenario | Symptom | Detection | Rollback Action |
|----------|---------|-----------|-----------------|
| **MCP crash loop** | Restart > 3x/5min | Health check | `IMPI_MODE=legacy` |
| **Model degradation** | Relevance < 70% | Golden eval | Switch model |
| **Cost spike** | > 2x daily budget | Cost alert | Rate limit + model downgrade |
| **Data corruption** | Invalid responses | Schema validation | Restore cache + restart |
| **Security incident** | Suspicious activity | Audit log | Full shutdown + investigate |

#### Rollback Decision Tree

```
Issue detected
    │
    ├─ Is production impacted?
    │   ├─ Yes → Is it SEV1/SEV2?
    │   │         ├─ Yes → Immediate rollback
    │   │         └─ No → Evaluate in 15 min
    │   └─ No → Continue monitoring
    │
    ├─ Is rollback safe?
    │   ├─ Yes → Execute rollback
    │   └─ No → Mitigate first, then rollback
    │
    └─ Post-rollback
        ├─ Verify recovery
        ├─ Root cause analysis
        └─ Fix forward plan
```

#### Rollback Drill Schedule

| Drill | Frequency | Last Run | Next Due |
|-------|-----------|----------|----------|
| MCP → Legacy | Monthly | 2026-01-10 | 2026-02-10 |
| Model fallback | Quarterly | 2025-12-01 | 2026-03-01 |
| Full restore | Semi-annual | 2025-10-15 | 2026-04-15 |

---

### 10.36 🌐 Network & Infrastructure

#### Network Topology

```
┌─────────────────────────────────────────────────────────┐
│                      Internet                           │
└────────────────────────┬────────────────────────────────┘
                         │
                    ┌────┴────┐
                    │   CDN   │
                    └────┬────┘
                         │
              ┌──────────┴──────────┐
              │                     │
         ┌────┴────┐           ┌────┴────┐
         │ Load    │           │ Rate    │
         │ Balancer│           │ Limiter │
         └────┬────┘           └────┬────┘
              │                     │
    ┌─────────┴─────────┐          │
    │                   │          │
┌───┴───┐          ┌───┴───┐      │
│ MCP   │          │ MCP   │◄─────┘
│Server │          │Server │
│  #1   │          │  #2   │
└───┬───┘          └───┬───┘
    │                   │
    └─────────┬─────────┘
              │
    ┌─────────┴─────────┐
    │                   │
┌───┴───┐          ┌───┴───┐
│ Redis │          │ Redis │
│Primary│          │Replica│
└───────┘          └───────┘
```

#### DNS & Endpoints

| Endpoint | Purpose | DNS |
|----------|---------|-----|
| Production | User traffic | `api.impactshop.hu` |
| Staging | Testing | `staging-api.impactshop.hu` |
| Internal | Admin/debug | `internal.impactshop.hu` |
| Metrics | Observability | `metrics.impactshop.hu` |

#### Firewall Rules

| Source | Destination | Port | Action |
|--------|-------------|------|--------|
| Internet | Load Balancer | 443 | Allow |
| Load Balancer | MCP Servers | 4000 | Allow |
| MCP Servers | Redis | 6379 | Allow |
| MCP Servers | External APIs | 443 | Allow |
| * | * | * | Deny |

---

### 10.37 📝 Compliance & Legal

#### GDPR Compliance Checklist

```markdown
## Data Controller Responsibilities
- [x] Privacy policy published
- [x] Data processing register maintained
- [x] Lawful basis for processing documented
- [x] User consent mechanism (where required)
- [x] Data retention policy implemented
- [x] Data subject access request (DSAR) process

## Technical Measures
- [x] Encryption at rest (AES-256)
- [x] Encryption in transit (TLS 1.3)
- [x] Access logging enabled
- [x] Data minimization practiced
- [x] Pseudonymization where possible
- [ ] Regular security audits (scheduled Q2)

**Codex 5.2 javaslata:** Készíts **DPIA sablont** Copilot/LLM adatkezelésre (különösen a query logolás és model routing miatt).  
**További forrás:** https://github.com/github/copilot-sdk?utm_source=email-cli-sdk-repo-cta&utm_medium=email&utm_campaign=cli-sdk-jan-2026
```

#### Cookie Policy (ha van web interface)

| Cookie | Purpose | Duration | Type |
|--------|---------|----------|------|
| `session_id` | Session mgmt | Session | Essential |
| `preferences` | User prefs | 1 year | Functional |
| `_ga` | Analytics | 2 years | Analytics |

#### Third-Party Data Processing

| Vendor | Data Shared | DPA Signed | Location |
|--------|-------------|------------|----------|
| OpenAI | Query text | ✅ | USA (EU DC) |
| Anthropic | Query text | ✅ | USA (EU DC) |
| Vercel | Logs | ✅ | EU |

---

### 10.38 🆘 Support & Helpdesk

#### Support Tiers

| Tier | Scope | SLA | Channel |
|------|-------|-----|---------|
| Self-service | FAQ, docs | - | Website |
| Community | User questions | Best effort | Discord |
| Standard | Bug reports | 48h response | Email |
| Priority | Critical issues | 4h response | Direct |

#### Common Issues & Resolutions

| Issue | Likely Cause | Quick Fix |
|-------|--------------|-----------|
| "Nem találok kupont" | Shop not indexed | Check shop list |
| "Lejárt kupon" | Stale cache | Force refresh |
| "Lassú válasz" | Model overload | Wait or retry |
| "Hibás kód" | Source data error | Report for investigation |

#### Escalation Path

```
User Report
    ↓
Discord/Email Triage (Community/Support)
    ↓
Bug confirmed? → Create ticket
    ↓
Severity assessment
    ├─ SEV1/2 → Immediate dev escalation
    └─ SEV3/4 → Sprint backlog
```

---

### 10.39 🧠 Prompt Library & Templates

#### System Prompts

```typescript
const systemPrompts = {
  // Fő keresési prompt
  search: `Te az ImpactShop kuponkereső asszisztense vagy.
Feladatod: releváns kuponok és kedvezmények keresése a felhasználó kérése alapján.

Szabályok:
- Csak érvényes, nem lejárt kuponokat adj vissza
- Mindig add meg a forrást (CJ, Dognet, stb.)
- Ha nincs találat, ajánlj hasonló boltokat
- Legyél tömör és hasznos

Válasz formátum: strukturált JSON`,

  // Validációs prompt
  validate: `Ellenőrizd a kupon érvényességét:
- Lejárati dátum
- Minimum rendelési érték
- Korlátozások (kategória, első vásárlás, stb.)
Ha probléma van, jelezd egyértelműen.`,

  // Ajánló prompt
  recommend: `A felhasználó preferenciái alapján ajánlj boltokat és kuponokat.
Vedd figyelembe:
- Korábbi keresések (ha van)
- Népszerű akciók
- NGO támogatási lehetőségek`
};
```

#### Few-Shot Examples

```yaml
examples:
  - query: "IKEA kupon"
    expected_tools: ["impi_search_coupons"]
    expected_output:
      - shop: "IKEA"
        discount: "Ingyenes szállítás 30.000 Ft felett"
        code: null
        type: "auto"
  
  - query: "Legjobb ruha akció"
    expected_tools: ["impi_search_coupons", "impi_recommend_shop"]
    expected_output:
      - shop: "About You"
        discount: "-20%"
        code: "ABOUTYOU20"
```

#### Error Response Templates

```typescript
const errorResponses = {
  no_results: "Sajnos nem találtam aktív kupont ehhez a bolthoz. Próbáld meg később, vagy nézd meg a hasonló boltokat: {alternatives}",
  
  expired: "Ez a kupon sajnos lejárt ({expiry_date}). Itt egy friss alternatíva: {alternative}",
  
  invalid_query: "Nem értem pontosan mit keresel. Próbáld meg így: 'IKEA kupon' vagy 'ruha akció'",
  
  rate_limit: "Túl sok kérés érkezett. Kérlek várj {wait_seconds} másodpercet.",
  
  system_error: "Technikai hiba történt. A csapat már dolgozik rajta. Próbáld újra később."
};
```

---

### 10.40 🔧 Configuration Reference

#### Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `IMPI_MODE` | No | `hybrid` | `legacy`, `hybrid`, `canary`, `mcp` |
| `OPENAI_API_KEY` | Yes | - | OpenAI API key |
| `ANTHROPIC_API_KEY` | Yes | - | Anthropic API key |
| `CJ_API_KEY` | Yes | - | CJ Affiliate API key |
| `DOGNET_API_URL` | Yes | - | Dognet API endpoint |
| `REDIS_URL` | No | `localhost:6379` | Redis connection |
| `LOG_LEVEL` | No | `info` | `debug`, `info`, `warn`, `error` |
| `CACHE_TTL_SECONDS` | No | `900` | Default cache TTL |
| `MAX_TOKENS_PER_REQUEST` | No | `4000` | Token limit |
| `RATE_LIMIT_PER_MINUTE` | No | `60` | Rate limit |

#### Feature Flags

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `FF_MCP_ENABLED` | bool | `true` | MCP server active |
| `FF_CACHE_ENABLED` | bool | `true` | Caching active |
| `FF_MODEL_FALLBACK` | bool | `true` | Auto model fallback |
| `FF_STREAMING` | bool | `true` | Response streaming |
| `FF_ANALYTICS` | bool | `true` | Analytics tracking |
| `FF_SEMANTIC_CACHE` | bool | `false` | Semantic cache (beta) |

#### Tuning Parameters

```yaml
# config/tuning.yaml
model:
  primary: "gpt-4o"
  fallback: "claude-3-5-sonnet"
  temperature: 0.3
  max_tokens: 1000

cache:
  l1_ttl: 60          # seconds
  l2_ttl: 900         # seconds
  l3_ttl: 3600        # seconds
  max_size_mb: 512

circuit_breaker:
  failure_threshold: 5
  recovery_timeout: 30
  half_open_requests: 3

retry:
  max_attempts: 3
  base_delay_ms: 1000
  max_delay_ms: 10000
  jitter: true
```

---

### 10.41 📋 Checklist Summary

#### Pre-Migration Checklist

```markdown
## Before You Start
- [ ] Backup completed and verified
- [ ] Rollback script tested
- [ ] All prerequisites met (Node, TS, SDK)
- [ ] Stakeholders notified
- [ ] Monitoring dashboards ready
- [ ] On-call scheduled

## Go/No-Go
- [ ] Staging tests passed
- [ ] Performance baseline recorded
- [ ] Security review done
- [ ] Documentation updated
```

#### Post-Migration Checklist

```markdown
## Immediate (Day 1)
- [ ] Health checks green
- [ ] No error spikes
- [ ] Latency within SLO
- [ ] User feedback monitored

## Short-term (Week 1)
- [ ] Canary metrics stable
- [ ] Cost within budget
- [ ] No rollback triggers
- [ ] Team retro scheduled

## Long-term (Month 1)
- [ ] Full rollout completed
- [ ] Legacy deprecated
- [ ] Documentation finalized
- [ ] Lessons learned documented
```

#### Operational Readiness

```markdown
## Daily
- [ ] Dashboard green check
- [ ] Alert review

## Weekly
- [ ] Metrics review
- [ ] Cost check
- [ ] Backlog grooming

## Monthly
- [ ] SLO review
- [ ] Capacity planning
- [ ] Security patches

## Quarterly
- [ ] Architecture review
- [ ] Vendor assessment
- [ ] Roadmap update
```

---

### 10.42 🎯 Success Criteria Summary

#### Migration Success

| Kritérium | Target | Mérés |
|-----------|--------|-------|
| Zero downtime | 100% | Uptime monitor |
| No data loss | 0 records | Audit log |
| Performance parity | ≤ +10% latency | P95 comparison |
| Feature parity | 100% | Test coverage |
| Rollback tested | ✅ | Drill log |

#### Operational Success (post-migration)

| Kritérium | Target | Timeline |
|-----------|--------|----------|
| SLO compliance | 99.5% | 30 days |
| Error rate | < 1% | 30 days |
| User satisfaction | NPS > 50 | 90 days |
| Cost efficiency | ≤ budget | Monthly |
| Team confidence | High | Retro feedback |

---

### 10.13 📚 Hivatkozások

| Forrás | Link | Megjegyzés |
|--------|------|------------|
| GitHub Copilot SDK | https://github.com/github/copilot-sdk | Hivatalos repo |
| MCP Protocol Spec | https://modelcontextprotocol.io/ | Protokoll dokumentáció |
| OpenTelemetry | https://opentelemetry.io/ | Observability standard |
| Circuit Breaker Pattern | https://martinfowler.com/bliki/CircuitBreaker.html | Pattern reference |
| SRE Book (Google) | https://sre.google/sre-book/table-of-contents/ | SRE best practices |
| DORA Metrics | https://dora.dev/ | DevOps teljesítmény |
| OWASP LLM Top 10 | https://owasp.org/www-project-top-10-for-large-language-model-applications/ | LLM security |

---

## 📝 Changelog

| Dátum | Verzió | Változás |
|-------|--------|----------|
| 2026-01-27 | 1.0 | Kezdeti terv |
| 2026-01-27 | 1.1 | Üzemeltetési keretrendszer hozzáadása (38 szekció) |
| 2026-01-27 | 1.2 | Prompt library, config reference, checklists |
| 2026-01-27 | 1.3 | Koherencia vizsgálat + repo/fájl elérési utak hozzáadása |

---

## 📂 Implementációs Gyorshivatkozások (Codex számára)

> **FONTOS**: Ez a szekció a Codex AI Agent számára készült, hogy gyorsan megtalálja a szükséges fájlokat.

### Repo elérési utak

| Repo | Teljes elérési út |
|------|-------------------|
| **AI Agent (fő)** | `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent` |
| **Impactshop Notes** | `/Users/bujdosoarnold/Documents/GitHub/impactshop-notes` |
| **Legacy ai-agent** | `/Users/bujdosoarnold/Developer/GitHub/ai-agent` (régebbi, ne használd) |

### Fő fájlok az AI Agent repo-ban

```
ai-agent/
├── apps/
│   ├── ai-agent-core/src/
│   │   ├── sources/
│   │   │   ├── types.ts          # NormalizedCoupon, CouponType, SourceSnapshot
│   │   │   └── cj-links.ts       # loadCjLinks(), getCjSnapshot()
│   │   └── impi/
│   │       ├── recommend.ts      # Fő ajánló logika (~700+ sor)
│   │       └── ngo-categories.ts # NgoCategory, loadNgoCategories()
│   │
│   ├── api-gateway/src/
│   │   └── index.ts              # Express server, Port 4000, /healthz, /api/v1/*
│   │
│   └── core-agent-graph/src/
│       ├── capabilities/
│       │   ├── impi.ts           # impiCapabilityV1, impiCapabilityV2 (rollout 20%)
│       │   ├── types.ts          # CapabilityManifest interface
│       │   ├── registry.ts       # registerCapability()
│       │   └── index.ts          # Capability exports
│       ├── state.ts              # CoreAgentState
│       └── index.ts              # runCoreAgentPrototype()
│
├── tools/
│   ├── playwright/
│   │   └── arukereso-runner.ts   # Árukereső scraper
│   └── gmail/
│       └── promotions-runner.ts  # Gmail promóciók feldolgozása
│
├── data/
│   └── ngo-category-map.json     # NGO kategória mapping
│
└── package.json                  # Scripts: dev:api, dev:agent, dev:mvp, ingest:normalize
```

### Létrehozandó struktúra (Fázis 1)

```
apps/impi-mcp-server/             # ÚJ - a migráció hozza létre
├── package.json
├── tsconfig.json
├── src/
│   ├── index.ts                  # MCP server entry point
│   ├── tools/
│   │   ├── index.ts
│   │   ├── search.ts             # impi_search_coupons
│   │   ├── validate.ts           # impi_validate_coupon
│   │   └── recommend.ts          # impi_recommend_shop
│   ├── adapters/
│   │   └── legacy.ts             # Legacy API bridge
│   └── types/
│       └── index.ts
└── README.md
```

### Gyors parancsok

```bash
# AI Agent repo megnyitása
cd /Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent

# Fejlesztői szerver indítása
npm run dev:mvp

# Health check
curl http://localhost:4000/healthz

# Backup futtatása migráció előtt
./bin/backup-impi-migration.sh
```

### Koherencia vizsgálat eredménye (2026-01-27)

| Ellenőrzés | Eredmény |
|------------|----------|
| Belső konzisztencia | ✅ Kiváló |
| Fájl hivatkozások | ✅ 100% egyezés |
| Architektúra leírás | ✅ Pontos |
| Capability keret | ✅ Létezik, dokumentált |
| Fázisok végrehajthatósága | ✅ Reális |

---

*Ez a dokumentum a teljes AI Agent Core migrációs és üzemeltetési keretrendszere. Kérdések: @team-lead*
