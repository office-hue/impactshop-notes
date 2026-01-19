# 126. Beszélgetés összefoglaló: Shops.csv → Dognet feed + DRY_RUN=0 smoke

## Áttekintés
A kérés szerint le kellett húzni a valós `Shops.csv` (Dognet export) táblát, ezzel lecserélni a `fixtures/coupon-harvester/feeds/*.csv` mintákat, majd a friss feedekkel lefuttatni a whitelist generátort és egy `DRY_RUN=0` kupon-harvester smoke tesztet.

## Megfigyelések
- `curl -sSL https://docs.google.com/.../output=csv&gid=0` → `/tmp/impactshop_Shops.csv`, majd egy rövid Python helper 64 shop sorra szűkítette és kitöltötte a `fixtures/coupon-harvester/feeds/dognet_programs.csv` állományt (domain, slug, landing URL). A CJ export továbbra sem elérhető, ezért a `cj_programs.csv` most üres fejlécet tartalmaz.
- `scripts/generate_shops_whitelist.py --dognet-feed fixtures/.../dognet_programs.csv --cj-feed fixtures/.../cj_programs.csv` frissítette a `tools/shops_registry.json`-t (64 sor) és a `.codex/cron/coupon-harvester-config.json` whitelist + Gmail allowed domain listáját.
- `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` (2025-12-03 15:35 CET) → `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T143516.csv` + `shops_manual_draft-2025-12-03T143516.csv` készült, 24 kupon sorral; a logban új bejegyzés: `2025-12-03T143516 | coupons=24 | dry_run=False`.

## Következő lépések
1. Szerezz valódi CJ shop exportot (`tools/cj_shops.csv` vagy WP CLI `cj:sync-shops` dump), majd futtasd újra a whitelist generátort, hogy a `cj_programs.csv` se legyen üres.
2. A friss CSV-k manuális review-ja után töltsd fel a jóváhagyott sorokat a produkciós manual feedbe a runbook szerint, és ismételd meg a `DRY_RUN=0` smoke-ot validációként.
