# 45. Beszélgetés összefoglaló: AI healthz + ár normalizer

## Áttekintés
Frissítettem az AI agent Node szolgáltatást, hogy a `/healthz` válasz explicit feature flag listát és státuszt adjon vissza, valamint kibővítettem a normalizer pipeline-t termékár adatokkal (Shops.csv + deals feed), így Impi pontosabban számol adomány összeget.

## Fő lépések
- `apps/api-gateway/src/index.ts`: új `REQUIRED_FEATURES` lista, `feature_status` + `missing_features` mezők, a guard most már a JSON-ból tudja kiolvasni a `playwright/gmail/harvester_bridge/openai_bridge` flageket.
- `tools/ingest/normalizer.ts` + `apps/ai-agent-core/src/sources/types.ts`: ár-map betöltés (Shops.csv + `https://app.sharity.hu/wp-json/impactshop/v1/deals`) → `price_huf` mező kerül a normalizált kuponokra, amit az Impi ajánlómotor fogyaszt.
- `impactshop-impi-chat.php/.js`: a front-end már az új mezőkre számítja a „minden 1 000 Ft után ...” vagy konkrét termékáras adomány szöveget (glass UI változatlan).
- Guard futtatás: `.codex/guards/ai-agent-guard.sh` még WARN státuszt jelez, mert a távoli 127.0.0.1:4000 szolgáltatáson a régi healthz fut; az új build lokálisan már a teljes feature listát adja.

## Következő lépések
- Másold át az új `ai-agent` buildet a cp40 szerverre (`~/ai-agent-service.js`/`ai-agent-data`) és indítsd újra a szolgáltatást, hogy a produkciós `/healthz` is megkapja a feature flag mezőket, így a guard PASS lesz.
- Opcionálisan töltsd fel a legfrissebb `Shops.csv`-t a `tmp/ingest/raw/` alá, hogy a price map teljes legyen (Dognet + CJ exportból).
