# 63. Beszélgetés összefoglaló: Impi tudásbázis fallback fix

## Áttekintés
A cp40-en futó `ai-agent` szolgáltatás nem találta az Impi tudásbázis fájlokat, ezért a GPT fallback minden kérdésre ugyanazt az általános választ adta. A build pipeline most már bepakolja a `Impi Tudásbázis` könyvtárat a `dist/` alá, a service loader explicit feloldja az `IMPI_*` környezeti változókat, és a távoli `.env` + `.deploy.*.env` fájlok is megkapták az abszolút útvonalakat. Új build + rsync után a cp40-es `ai-agent-service.js` ismét tudja tölteni a tudás- és beszélgetés térképet.

## Fő lépések
- Új `scripts/sync-knowledge-assets.js` + `package.json` build módosítás: a `tsc` után automatikusan átmásolja az `Impi Tudásbázis` mappát (PDF-ek, JSON, `Tudásbázis-imői.md`) a `dist` könyvtárba.
- `scripts/ai-agent-service.js` most `resolveFirstExisting` segítségével beállítja az `IMPI_KNOWLEDGE_DIR/FILE`, `IMPI_KNOWLEDGE_ALIAS_FILE` és `IMPI_CONVERSATION_MAP` értékeket (repo + dist útvonalak), így az ENOENT hibák megszűntek.
- `.deploy.{staging,production}.env` és a cp40-es `~/ai-agent/.env` kiegészült az abszolút `IMPI_*` path-okkal; `npm run build` + `rsync -az --delete` után újraindítottam a szolgáltatást (`nohup $HOME/node-v18/bin/node ~/ai-agent-service.js`).
- Ellenőrzés: `curl -sS http://127.0.0.1:4000/healthz` már visszaadja a knowledge flag-eket, az `ai-agent` guard 21:28-kor PASS lett (`staging: 7ms / production: 7ms`, guard log frissült).

## Következő lépések
- Ha Playwright scraper adat is beérkezik, frissítsd a `manual/arukereso` ingest állományokat, hogy a healthz `status` mezője `ok`-ra váltson.
- Tartsd karban a `tools/Tudásbázis-imői.md` fájlt, mert a build step automatikusan ezt csomagolja a `dist/Impi Tudásbázis` mappába.
