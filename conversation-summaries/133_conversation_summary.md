# 133. Beszélgetés összefoglaló: CJ shop feed + harvester smoke

## Áttekintés
Feladat a CJ shop feed beszerzése, a whitelist frissítése és egy teljes `DRY_RUN=0` kupon-harvester smoke futtatása volt, hogy a CJ partnerek is bekerüljenek a pipeline-ba.

## Megfigyelések
- `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && export CJ_* && wp impactshop cj:sync-shops --format=json"` lefutott (41 advertiser), majd a `wp option get impactshop_cj_shops --format=json` kimenetét `tools/cj_shops.json` + `tools/cj_shops.csv` formátumba mentettem.
- `scripts/generate_shops_whitelist.py --dognet-feed fixtures/coupon-harvester/feeds/dognet_programs.csv --cj-feed tools/cj_shops.csv` → `tools/shops_registry.json` 102 sorra nőtt, és a `.codex/cron/coupon-harvester-config.json` whitelistje tartalmazza a CJ doméneket is.
- `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` → manuális kupon CSV: `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T172443.csv`, shop export: `tmp/coupon-harvester/shops_manual_draft-2025-12-03T172443.csv`, log: `.codex/logs/coupon-harvester-smoke.log` (`2025-12-03T172443 | coupons=24 | dry_run=False`).

## Következő lépések
1. Végezdd el a manuális kupon review-t (a friss CSV alapján), majd frissítsd a manual feedet és futtasd az ingest pipeline-t (`npm run ingest:normalize && npm run ingest:sync`).
2. Ha a CJ feedben új shop jelenik meg, ismételd meg a `generate_shops_whitelist.py` + smoke lépéseket, hogy a whitelist naprakész maradjon.
