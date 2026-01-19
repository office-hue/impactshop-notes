# Guard Actions

## GitHub hitelesítés / PAT betöltése
- Az `impactall` és az `aiagentall` (alias: `./.codex/guards/ai-agent-guard.sh`) futások során GitHub klón/`git fetch` műveletek is történhetnek, ezért kötelező, hogy a macOS Keychain kezelje a PAT-et.
- Ellenőrzés: `git config --global credential.helper` → `osxkeychain`, majd `security find-internet-password -s github.com -g` (ha nincs, futtasd a `git credential-osxkeychain store` lépést).
- A PAT egyszeri tárolása után minden guard/impactall futás automatikusan a Keychainből olvassa ki a GitHub hozzáférést, így nem szakítja meg a pipeline-t jelszókéréssel.

## AI Agent Health Guard
1. Futtasd manuálisan: `./.codex/guards/ai-agent-guard.sh` (vagy várd meg a `*/15` cron futást).
2. Ha az összegzés WARN vagy FAIL, ellenőrizd a `.codex/logs/ai-agent.cron.log` fájlt és a legutóbbi guard eseményt a `.codex/logs/guard-events.log`-ban.
3. Állapítsd meg, mely feature flag hiányzik (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`), és javítsd a megfelelő szolgáltatást (cp40 `~/ai-agent-service.js`).
4. Ha a guard a harvester/whitelist hiánya miatt figyelmeztet, a Dognet/CJ Shops export útvonalait a `docs/coupon-harvester.md` „Shops export források” fejezete írja le (Shops Google Sheet + `wp impactshop cj:sync-shops`), innen tudsz gyorsan feedet frissíteni `fixtures/coupon-harvester/feeds/*.csv` → `scripts/generate_shops_whitelist.py` futással.
5. Javítás után futtasd újra a guardot, majd `~/bin/impactall`-t, hogy a Sprint S1 pre-flight is PASS-t jelezzen.
6. Dokumentáld a lépéseket a `notes.md`-ben és a kapcsolódó conversation summary-ben.

### AI Agent proxy / ping (cp40)
- Publikus proxy: `/ai-agent/*` → `http://127.0.0.1:4000/` (Apache `~/app/.htaccess`), ping alias: `/ai-agent/ping` → `/healthz`.
- Szolgáltatás Node 20.18.0-val fut: `~/node-v20/bin/node ~/ai-agent/scripts/ai-agent-service.cjs`.
- Keepalive: `~/ai-agent/scripts/ai-agent-keepalive.sh` (nohup, 60 mp-enként ellenőriz).
- Ping monitor: `~/ai-agent/scripts/ai-agent-ping-monitor.sh` (5 perc, log: `~/ai-agent/ping-monitor.log`, Discord webhook bekötve PING_FAIL esetén).
- Ha proxy/ping változik, frissítsd a runbookot és futtasd újra az `aiagentall` guardot deploy után.

## AI Agent Reliability Guard
1. A reliability score állapotot az `.codex/scripts/ai-agent-reliability-guard.sh` script futtatja (óránként a `guards.crontab` is hívja). Szükség esetén manuálisan is indítható: `./.codex/scripts/ai-agent-reliability-guard.sh`.
2. A guard a `../ai-agent/tmp/ingest/reliability-scores.json` fájlt figyeli; a logokat a `.codex/logs/ai-agent-reliability.log` és `.codex/logs/ai-agent-reliability.cron.log` fájlokban találod.
3. Ha ⚠️ jelzést kapsz (nőtt a `risky` kuponok száma vagy hiányzik a score fájl), futtasd `npm run ingest:normalize` az `ai-agent` repo-ban, hogy frissüljenek a score-ok.
4. Ellenőrizd az Impi ajánlásokban érintett shopokat (`tmp/ingest/gmail.json`, `tmp/ingest/arukereso.json`), és távolítsd el / jelöld manuális review-ra a hibás tételeket.
5. Guard PASS után rögzítsd az eseményt a `notes.md` naplóban, és szükség esetén frissítsd a conversation summary-t.
