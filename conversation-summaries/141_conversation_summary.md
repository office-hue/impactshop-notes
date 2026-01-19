# 141. Beszélgetés összefoglaló: Gmail ingest + guard integráció

## Áttekintés
A shop registry után a Gmail adatfolyam tényleges megvalósítása következett: elkészült az auth/ingest toolchain, a normalizer mostantól `gmail_structured` rekordokat is összeállít, az AI Agent snapshot és az API pedig felismeri a `gmail` feature flaget.

## Megfigyelések
- Új scripts: `tools/gmail/auth.ts` (OAuth kód → token) és `tools/gmail/promotions-runner.ts` (Gmail API → `tmp/ingest/raw/gmail-promotions.json`, shop/domain felismeréssel). `package.json` kapott `gmail:auth` / `gmail:promotions` parancsokat.
- A `tools/ingest/normalizer.ts` immár három forrást dolgoz fel (`manual_csv`, `arukereso_playwright`, `gmail_structured`), `tmp/ingest/gmail.json` fájlba írja az eredményt, és a reliability statisztika is tartalmazza az új sorokat.
- AI Agent core: új `sources/gmail-promotions.ts` + snapshot integráció → `/healthz` most a `gmail` feature-t is jelzi; az API `/gmail/promotions` végpontja már ezt az adatot szolgálja ki.
- `npm run lint` zöld állapotot adott; a shop registry diagnosztika továbbra is használható a Playwright flag ellenőrzésére.

## Következő lépések
1. Töltsd fel a Gmail credentials/token fájlokat (`tools/secrets/gmail`) és futtasd a `npm run gmail:promotions` parancsot, hogy valós rekordok kerüljenek a normalizerbe.
2. Kapcsolódó guard frissítés: a `aiagentall` futtatása után ellenőrizd, hogy a `/healthz` feature listában a `gmail` zászló aktív maradjon.
