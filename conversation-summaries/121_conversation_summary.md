# 121. Beszélgetés összefoglaló: kupon-harvester pipeline bekötése

## Áttekintés
A feladat a dummy sorokat generáló smoke script kiváltása valódi (whitelist + fixture alapú) pipeline-nal, és annak ütemezése volt, hogy az impactall header figyelmeztetés hosszú távon se térjen vissza.

## Megfigyelések
- Új `scripts/coupon_harvester_pipeline.py` készült: configból betöltött whitelist + Gmail/HTML fixture tartalmakat regexeli, deduplikál, majd CSV-be (`tmp/coupon-harvester/manual_coupons_draft-<ts>.csv`, `.../shops_manual_draft-<ts>.csv`) ír, miközben JSON summary-t ad vissza.
- A `scripts/coupon-harvester-smoke.sh` most ezt a pipeline-t hívja (`DRY_RUN=1` mellett is), automatikusan létrehozza/karbantartja a `.codex/cron/coupon-harvester-config.json`-t, és a kimenetet a `.codex/logs/coupon-harvester-smoke.log` fájlba jegyzi fel.
- A cron wrapper (`.codex/cron/coupon-harvester-smoke.sh`) bekerült a `crontab`-ba (`5 8 * * * … # coupon-harvester-smoke`), így minden reggel lefut a pipeline, és naprakészek maradnak a logok / CSV-k.

## Következő lépések
1. Ha megjönnek a Gmail API credentials és a whitelist API feedek, frissítsd a configot (gmail_fixture_dir → valódi Gmail, html_sources → élő URL-ek) és kapcsold be a DRY_RUN=0 módot.
2. Bővítsd a pipeline loggolását error/duplikátum statusszal, majd építs rá guardot, ami 0 kupon esetén figyelmeztet.
