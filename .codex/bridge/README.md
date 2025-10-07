# Impact Bridge használat

## Előkészítés

1. Állítsd be a GitHub Secrets értékeket:
   - `SSH_HOST` = `cp40.ezit.hu`
   - `SSH_USER` = `sharityh`
   - `SSH_PORT` = `22` (ha eltér, írd át)
   - `SSH_KEY`  = az `id_ed25519` privát kulcs **egysorossá alakítva**
2. Győződj meg róla, hogy a kulcs jogosult a staging/prod szerverre.

## Működés

1. Szerkeszd a `.codex/bridge/current-task.json` fájlt.
2. `git add` + `git commit` + `git push`.
3. A push triggereli a **Codex Bridge Executor** workflow-t.
4. A workflow lefuttatja a `.codex/bridge/execute.sh` scriptet, ami SSH-n végrehajtja a parancsokat, majd a kimenetet a `.codex/bridge/last-run.json` fájlba menti.
5. A workflow a frissített `last-run.json`-t visszacommitolja a repo-ba.

## Lokális futtatás

```bash
make run-task
```

Ez ugyanazt a szkriptet hívja, így a fejlesztői gépről is végrehajtható a feladat (feltéve, hogy az SSH konfiguráció be van állítva).

Az utolsó futás eredménye megtekinthető:

```bash
make show-last
```

## Kvóta / erőforrás mérés

- A `last-run.json` minden parancshoz rögzíti az `elapsed_sec` értéket (ha elérhető `/usr/bin/time`), illetve az egyes parancsok `stdout`/`stderr` kimenetét.
- A `usage.json` gördülő összesítést tart fenn: `runs`, `commands`, összesített CPU-idő (`cpu_elapsed_sec`), teljes futási idő (`wallclock_sec`), kimenet mérete (`stdout_bytes`).
- GitHub Actions-ben futva a workflow külön hozzáadja az `actions_wallclock_sec` mezőt is (teljes job-idő).
- Hasznos parancsok:
  - `make show-last` – az utolsó run részletes eredménye
  - `make show-usage` – kumulált használati statisztikák

> Ez a mérés nem kapcsolódik a ChatGPT tokenekhez; kizárólag az automatikus végrehajtás erőforrás-fogyasztását követi.

## Bridge Doctor (önellenőrzés)

- Minta feladat: `.codex/bridge/tasks/current-task.doctor.json`
- Futás menete:
  - **GitHub Actions**: másold a mintát `.codex/bridge/current-task.json`-ba és `git push`.
  - **Lokálisan**: futtasd a `make doctor` parancsot.
- Teszteli:
  - SSH kapcsolat (hostname, whoami, uptime)
  - Staging WP-CLI elérhetőség (`wp cli info`, `wp core is-installed`)
  - HOME/SITEURL értékek
  - Írás/olvasás jog a home könyvtárban
  - HTTP front és REST endpoint (APP hoston)

Sikerkritérium: REST 200 (vagy 301→200), minden parancs lefut, a részletek a `last-run.json`/`usage.json` fájlokban jelennek meg.
