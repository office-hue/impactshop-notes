# 294. Beszélgetés összefoglaló: Impi deploy prod

## Áttekintés
Az Impi kommunikációs javításokat production környezetbe deployoltam az ai-agent service-re.

## Megoldás
- `npm run build` → `rsync dist/` a `s59.tarhely.com:/home/sharityh/ai-agent/dist/` célra.
- `bash ~/ai-agent/scripts/ai-agent-keepalive.sh` restart.
