# 127. Beszélgetés összefoglaló: Manual coupons review + DRY_RUN=0 smoke

## Áttekintés
A `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T143516.csv` állományt átnéztem, kiválasztottam a valódi kuponkódokat, betöltöttem az éles manual feedbe (`../ai-agent/tmp/ingest/raw/manual_coupons.csv`), majd újra lefuttattam a `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` validációt.

## Megfigyelések
- A draft 24 sora közül csak a Decathlon `WINTER20` és a Notino `ILLAT15` tűnt érvényesnek; a többi HTML/body töredék volt, ezért eldobtam.
- A két kódot Python helperrel deduplikálva hozzáadtam a manual feedhez (`source_type=harvester`, `validation_note="2025-12-03 manual review"`).
- `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` ismét lefutott (CSV: `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T144339.csv`, log: `2025-12-03T144339 | coupons=24 | dry_run=False`).

## Következő lépések
1. Szerezd be a hiányzó CJ shop exportot (`wp impactshop cj:sync-shops --format=json`), futtasd újra a whitelist generátort, majd a smoke-ot.
2. Az ingest pipeline (`npm run ingest:normalize && npm run ingest:sync`) következő lefuttatásakor már tartalmazni fogja a két új kupon sort – indítsd el, ha az AI agent feedjét is frissíteni kell.
