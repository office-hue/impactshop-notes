# 128. Beszélgetés összefoglaló: CJ shop export blokk + ingest

## Áttekintés
A feladat a CJ shop lista exportálása (`wp impactshop cj:sync-shops --format=json → tools/cj_shops.csv`), majd a whitelist generátor + smoke + ingest pipeline lefuttatása lett volna. A CJ API hitelesítés azonban továbbra sem működik a rendelkezésre álló kulcsokkal, így csak az ingest lépést tudtam végrehajtani.

## Megfigyelések
- `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && wp impactshop cj:sync-shops --format=json"` hibával állt meg: `CJ credentials missing` (nincs `CJ_PUBLISHER_PAT`). A `CJ_DEVELOPER_KEY=NaNVErg7XUFUhFeGZOD5mHJdBg` + `CJ_PUBLISHER_ID=7318997` párost exportálva ugyan lefut a parancs, de 0 shopot ad vissza.
- Közvetlen API hívás (`curl https://advertiser-lookup.api.cj.com/v3/...`) 401 „Not Authenticated” választ ad a fenti developer key-re, így tényleges CJ shop feed nem tölthető le. Következmény: `tools/cj_shops.csv` és a `fixtures/coupon-harvester/feeds/cj_programs.csv` továbbra is üresek, ezért a whitelist + smoke nem változna.
- Az AI agent ingest pipeline lefutott a friss manual kuponokkal: `npm run ingest:normalize` → 99 manual / 43 Árukereső rekord, majd `npm run ingest:sync` frissítette a `tmp/ingest/raw/*.json|csv` fájlokat.

## Következő lépések
1. Adj használható CJ credentialt (PAT vagy működő developer key + publisher id), majd újra futtasd a `wp impactshop cj:sync-shops` parancsot és frissítsd a `tools/cj_shops.csv` / whitelist fájlokat.
2. Amint a CJ feed elérhető, futtasd a `scripts/generate_shops_whitelist.py --cj-feed tools/cj_shops.csv ...` + `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` lépéseket, hogy a whitelist és a guard is tartalmazza a CJ doméneket.
