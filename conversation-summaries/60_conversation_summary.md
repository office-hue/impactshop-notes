# 60. Beszélgetés összefoglaló: AI agent build + deploy

## Áttekintés
A keyword-score fix után kimentettem a friss `ai-agent` buildet, telepítettem a cp40 szerverre és újraindítottam a Node szolgáltatást. A `.deploy.*.env` fájlokat kiegészítettem az `AI_AGENT_HEALTH_URL` értékkel, majd lefuttattam az `aiagentall` guardot.

## Fő lépések
- `npm run build` a lokális `ai-agent` repo-ban, majd `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent`.
- SSH-n: `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev`, a régi `~/ai-agent-service.js` processz leállítva (`kill $(cat ~/ai-agent-service.pid)`), majd `nohup node ~/ai-agent-service.js &` új PID/LOG rögzítéssel.
- `.deploy.staging.env` és `.deploy.production.env` most tartalmazza az `AI_AGENT_HEALTH_URL="http://127.0.0.1:4000/healthz"` sort, így a guard paraméterezhető.
- `.codex/guards/ai-agent-guard.sh` futása mindkét env-re HTTP 200 / 7–8 ms eredményt adott (OK státusz a logban).

## Következő lépések
- Ha a wp-cli `wp impactshop ai-agent ping` JSON-ját is ellenőrizni kell, futtasd staging/prod környezetben, de a guard most már zöld. Új buildnél ugyanez a `rsync + npm install + service restart` folyamat követendő.
