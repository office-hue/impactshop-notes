# Langfuse ellenőrzési enablement

Ez a rövid útmutató összefoglalja, hogyan használjuk a Langfuse-t az AI Agent (core task + Impi
chat) megfigyelésére, és hogyan dokumentáljuk release előtt a telemetria állapotát.

## Előfeltételek

- `LANGFUSE_SERVER_URL`, `LANGFUSE_SERVER_API_KEY`, `LANGFUSE_PUBLIC_KEY` változók szerepelnek a
  `.codex/.env.local`, `.staging_env`, `.production_env` fájlokban.
- Az API gateway és a core worker már küld `core_task_created` és `impi_chat_response` eseményeket
  (`trackLangfuseEvent()` helper).
- Van hozzáférés a `https://cloud.langfuse.com` projekthez és a Discord/Slack webhookhoz, ahová az
  alert érkezik.

## Dashboard + alert beállítása

1. Nyisd meg a Langfuse UI-t, válaszd ki az ImpactShop projektet.
2. Hozz létre egy „Core tasks” panelt:
   - Forrás: `Events`
   - Szűrő: `name = core_task_created`
   - Aggregáció: napi darabszám (count) workspace/feladat típus bontással.
3. Hozz létre egy „Impi responses” panelt:
   - Szűrő: `name = impi_chat_response`
   - Mutatók: átlagos `metadata.processing_ms`, `error_ratio` (error / total) és napi darabszám.
4. Alert szabályok:
   - `core_task_created`: absence ≥15 perc → Discord/Slack webhook.
   - `impi_chat_response`: absence ≥15 perc **vagy** `error_ratio > 0.1` → ugyanarra a webhookra.
   - Adj címkét (`ai-agent`, `langfuse`) és rövid leírást, hogy a guard logban is egyértelmű legyen a
     forrás.

## Release előtti checklist

Minden produkciós release (vagy guard kézi PASS kérés) előtt futtasd az alábbi blokkot. A végén a
képernyőmentést a `image/langfuse/` mappába mentsd `langfuse-YYYYMMDD-HHMM.png` néven, majd hivatkozd
be a `notes.md` aktuális naplójába.

1. Lépj be a Langfuse UI-ba, frissítsd a dashboardot.
2. Ellenőrizd, hogy **mindkét panelen** van esemény az utolsó 15 percben (tooltip dátum/idő).
3. Nézd meg az alert feedet: nincs „Triggered” esemény az utolsó 24 órából.
4. Készíts egy képernyőmentést, amelyen látszik a két panel és a dátum (browser tab + menüsor), majd
   nevezd el az előírt formátumban.
5. Jegyezd fel a `notes.md`-ben: *„Langfuse check OK – core_task_created X, impi_chat_response Y,
   screenshot: image/langfuse/langfuse-YYYYMMDD-HHMM.png”.*
6. Amennyiben alert riasztott (például absence), kommentáld a Slack/Discord threadben és ne folytasd a
   release-t, amíg manuális guard/worker vizsgálat nem igazolta, hogy helyreállt a szolgáltatás.

## Hibakeresés

- **Esemény hiányzik**: futtasd a `./.codex/guards/ai-agent-guard.sh` és `~/bin/impactall`
  parancsokat. Ha ezek PASS-t adnak, nézd meg az API gateway logját (`api/logs/core-worker.log`), hogy
  a `trackLangfuseEvent` hívások ténylegesen sikeresek-e.
- **Alert állandóan riaszt**: ellenőrizd, hogy a Discord/Slack webhook titok egyezik-e a
  `~/.impact-secrets/env.d/langfuse-alert.env` fájlban lévővel, illetve hogy a Langfuse UI-ban nem lett
  véletlenül módosítva a threshold.
- **Screenshot hiányzik**: release előtt kötelező rögzíteni. Ha elfelejtetted, készíts pótlólagos
  képet és linkeld a `notes.md`-ben, majd jelöld a releasenél, hogy „post release screenshot”.

A fenti lépésekkel a Langfuse megfigyelés ugyanúgy auditálható lesz, mint a hagyományos guard és REST
ellenőrzések.
