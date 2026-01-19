# 73. Beszélgetés összefoglaló: AI agent build + deploy

## Áttekintés
A batch QA után lefuttattam az `ai-agent` buildet, frissítettem a tudásbázist (beleértve az `AI-training-prompts.md`-t), majd rsync-kel deployoltam a cp40-es szolgáltatásra és újraindítottam az `ai-agent-service.js`-t.

## Fő lépések
- `npm run build` → `tsc` + `scripts/sync-knowledge-assets.js`, az új prompt fájl a `dist/Impi Tudásbázis` alatt elérhető.
- `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` + szerver oldali `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev`.
- Szolgáltatás restart: korábbi PID leállítva, új folyamat `nohup`-pal indult; `/healthz` kimenet `degraded` (Playwright hiány) – elvárt baseline.

## Következő lépések
1. Setup prompt frissítése a QA során feltárt hiányosságokra (welcome menü, 5 lépés, transzparencia fallback).
2. Újabb batch futtatás a módosítások után, hogy lássuk a javítás hatását.
