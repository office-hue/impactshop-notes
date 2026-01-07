# AI Agent Core Console – felhasználói kézikönyv

A Core Console az AI Agent feladataihoz (dokumentum-ingest, memória szinkron, watchdog) biztosít egy
webes felületet és CLI segédeszközt. Ez a kézikönyv bemutatja, hogyan éred el a felületet, milyen
kártyákat látsz rajta, hogyan indíthatsz új feladatokat vagy guardokat, és miként diagnosztizáld a
leggyakoribb problémákat.

## 1. Elérés és azonosítás

1. **Hosts + proxy** – a lokális nginx proxy a `setup-core-ai-proxy.sh` szkripttel állítható be. A
   script létrehozza a `/opt/homebrew/etc/nginx/servers/core-ai.conf` fájlt és hozzáadja az
   `/etc/hosts`-hoz az alábbi sort:
   ```
   127.0.0.1 core-ai.sharity.hu
   ```
   Ezt követően a `brew services restart nginx` parancs újratölti a proxyt.
2. **URL** – a felület a böngészőben a
   `http://core-ai.sharity.hu/admin/core-console?key=<API_KEY>` címen érhető el. A `key` query param
   kerül az `x-api-key` headerbe, így csak a megfelelő kulccsal (például
   `sk_aiagent_core_console_20251206`) nyílik meg az oldal.
3. **Környezet változók** – az `AI_AGENT_API_URL` és `AI_AGENT_API_KEY` értékek szerepelnek a
   `~/.impact-secrets/env.d/ai-agent.env` fájlban, így a CLI script és a guardok is ugyanebből olvasnak.

> Tipp: ha 403-as hibát kapsz, ellenőrizd, hogy az API kulcs egyezik-e az aktuális
> `AI_AGENT_API_KEY`-vel, illetve hogy a hosts/proxy beállítás érvényes-e.

## 2. Navigációs áttekintés

A Core Console fő oldala három részre oszlik:

1. **Státuszkártyák** – modulonként (Playwright, Gmail, Harvester bridge, OpenAI bridge,
   Reliability, Memory sync) látszik a legutóbbi futás időpontja, a feldolgozott elemek száma és egy
   `STALE` jelző, ha 24 óránál régebbi a log (`.codex/logs/*.log` bejegyzései alapján).
2. **Feladatkezelő** – listázza az aktuális és korábbi Core feladatokat (workspace, jobType,
   státusz), valamint tartalmaz egy űrlapot új feladat indításához.
3. **Dokumentum-ingest szekció** – structured kártyák mutatják a legutóbbi dokumentum/JSON kimenetet,
   a táblázatok számát, figyelmeztetéseket, valamint „Download JSON” és „Guard újrafuttatása” gombot.

## 3. Új feladat indítása

A „New Task” űrlapon az alábbi mezők érhetők el:

- **Workspace** – Impact Shop, Finance/Könyvelés vagy Operations/Assistant (a
  `config/core-workspaces.json` fájl alapján jelenik meg).
- **Job type** – jelenleg `document_ingest` vagy `memory_sync`. A választás határozza meg, hogy a core
  worker a dokumentum ingest modult vagy a Graphiti memória szinkront futtatja.
- **Job params** – JSON formátum, például:
  ```json
  {
    "source": "drive",
    "ingestPath": "Impi Tudásbázis/2025-12-05/beszamolo.xlsx",
    "labels": ["impactshop", "crm"]
  }
  ```
- **Attachments** – opcionális Drive path vagy lokális fájl hivatkozás, amelyet a worker a
  `attachments[*].ingestPath` mezőbe másol.

A form elküldése a `/core/tasks` végpontot hívja; siker esetén új sor jelenik meg a feladatlistában,
majd a queue (BullMQ) feldolgozza és „done” státuszba állítja. Hiba esetén a piros alert sávban látod
az API üzenetét.

### CLI alternatíva

A `bin/impactctl-core-task.sh` ugyanazokat az endpointokat hívja. Példa dokumentum ingest indítására:

```
AI_AGENT_API_KEY=... \
AI_AGENT_API_URL=http://core-ai.sharity.hu/core \
  ./bin/impactctl-core-task.sh \
  --workspace impactshop \
  --job-type document_ingest \
  --params '{"ingestPath":"Impi Tudásbázis/2025-12-06/report.xlsx"}'
```

A script automatikusan hozzáadja az `x-api-key` headert és prettified JSON választ ír a terminálra.

## 4. Dokumentum és guard műveletek

- **Preview / JSON letöltés** – a kártyán lévő „Download JSON” link a
  `/core/documents/<slug>.json` végpontot hívja, amely a worker által előállított teljes struktúrát
  (sheet/tables, warnings, summary) tartalmazza.
- **Guard újrafuttatása** – a „Re-run guard” gomb POST-olja a `/core/guard/document-ingest` végpontot,
  amely a `.codex/guards/document-ingest.sh` scriptet futtatja. A futás eredménye a státuszkártyán és a
  `document-ingest.log`-ban is megjelenik.

## 5. Watchdog és logok

| Funkció            | Log fájl                              | Megjegyzés                              |
|--------------------|---------------------------------------|-----------------------------------------|
| Playwright cron    | `.codex/logs/arukereso-playwright.cron.log` | `/healthz` `feature_status.playwright` |
| Gmail ingest       | `.codex/logs/gmail-promotions.cron.log`     | `feature_status.gmail`                 |
| Harvester smoke    | `.codex/logs/coupon-harvester-smoke.log`    | STALE esetén Core UI figyelmeztet     |
| OpenAI bridge      | `../ai-agent/tmp/logs/impi-chat.log`        | Watchdog Discord értesítést küld      |
| Memory sync        | `.codex/logs/graphiti-ingest.cron.log`      | `feature_status.memory_sync`          |

A `.codex/scripts/ai-agent-log-watchdog.sh` script óránként ellenőrzi a fenti logokat. Ha valamelyik
24 órán túl frissült, `STALE` jelzést kap a státuszkártya, és opcionálisan Discord értesítés megy a
`AI_AGENT_WATCHDOG_WEBHOOK` címre.

## 6. Hibaelhárítás

| Probléma | Teendő |
| --- | --- |
| **A Core Console nem tölt be** | Ellenőrizd a hosts bejegyzést és hogy az nginx proxy fut-e (`brew services restart nginx`). Győződj meg róla, hogy a `key` query param friss API kulcsot tartalmaz. |
| **403 Unauthorized** | A query paramban lévő kulcs nem egyezik az aktuális `AI_AGENT_API_KEY`-vel, vagy nincs engedélyed a workspace-hez. Frissítsd az `.env.local`-t / secrets fájlt, majd töltsd újra az oldalt. |
| **Guard gomb nem csinál semmit** | Nézd meg a browser konzolt (network hiba?) és a `.codex/logs/document-ingest.log`-ot. Ha a log nem frissül, futtasd kézzel: `./.codex/guards/document-ingest.sh`. |
| **Státuszkártya STALE** | Nyisd meg a hozzá tartozó logot (ld. táblázat), futtasd kézzel a megfelelő cron/guard scriptet (pl. `scripts/coupon-harvester-smoke.sh` DRY_RUN=1), majd frissítsd az oldalt. |
| **Új feladat nem jelenik meg** | Ellenőrizd, hogy a `tmp/state/core-tasks.json` írható-e, illetve fut-e a queue/worker (`apps/core-worker`). Szükség esetén `npm run core:worker` az ai-agent repóban. |
| **Drive/attachments hiány** | A jobParams `ingestPath` mezője pontatlan. Győződj meg róla, hogy létező Drive/mappa slugot adsz meg, vagy használj teljes útvonalat (`Impi Tudásbázis/...`). |

## 7. Kapcsolódó eszközök

- `bin/impactctl-core-task.sh` – terminálból indíthatsz feladatokat.
- `setup-core-ai-proxy.sh` – beállítja a `core-ai.sharity.hu` proxyt.
- `.codex/guards/ai-agent-guard.sh` – AI Agent egészség-ellenőrzés (Playwright/Gmail/Harvester/OpenAI/Reliability).
- `.codex/scripts/ai-agent-log-watchdog.sh` – log frissesség monitorozása és opcionális webhook jelzés.

További részletekért lásd a `notes.md` vonatkozó szakaszait (2025-12-05–06 Core Console bejegyzések),
valamint a `conversation-summaries/211`, `224`, `228` dokumentumokat.
