# SSH Bridge Doctor

Ez a feladat ellenőrzi az SSH-hidat, a staging WordPress CLI elérhetőségét és a staging HTTP/REST állapotát.

## Futtatás
1. Töltsd fel a GitHub Secrets értékeket (`SSH_HOST`, `SSH_USER`, `SSH_PORT`, `SSH_KEY`).
2. Másold a `tasks/current-task.doctor.json` tartalmát a `.codex/bridge/current-task.json`-ba és push-old, **vagy** futtasd lokálisan:
   ```bash
   make doctor
   ```
3. Nézd meg a kimenetet:
   ```bash
   make show-last
   make show-usage
   ```

Elvárt eredmény: `hostname`, `whoami`, `wp --info` lefut; a staging `https://app.sharity.hu/impactshop-staging/` és `.../wp-json/impact/v1/total` végpontok 200-as választ adnak (vagy 301 → 200 lánc), a `last-run.json` és `usage.json` naplózza a részleteket.

