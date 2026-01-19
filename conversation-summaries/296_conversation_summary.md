# 296. Beszélgetés összefoglaló: Impi videós CTA javítás + deploy

## Áttekintés
Javítottam a videós támogatás linket, hogy az ImpactShop NGO CTA‑t használja (ne a régi adomany.sharity.hu linket), majd productionre deployoltam.

## Megoldás
- Code: `apps/api-gateway/src/services/impi-openai.ts`, `apps/api-gateway/src/index.ts`, `apps/ai-agent-core/src/impi/recommend.ts`.
- Commit: `9fa19e3` a branchen.
- PR frissítve: https://github.com/office-hue/impact_hub/pull/18
- Deploy: build + rsync dist + keepalive restart.
- Prod restart után a service nem indult a hiányzó `dist/data/*` miatt, ezért `data/*` átmásolva a szerverre: `/home/sharityh/ai-agent/dist/data/`.
- Verifikáció: `curl http://127.0.0.1:4000/api/v1/chat/impi` → ImpactShop NGO link, nincs „Nincs kuponkód...” mondat.
