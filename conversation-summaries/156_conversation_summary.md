# 156. Beszélgetés összefoglaló: Gmail személyes kupon filter

## Áttekintés
Beépítettem a Gmail Promotions ingest pipeline-ba egy guardot, ami kiszűri az egyszer használatos, csak a tulajdonosnak küldött kuponokat, és frissítettem a dokumentációt/naplókat.

## Megfigyelések
- `tools/gmail/promotions-runner.ts` most a `GMAIL_PERSONAL_RECIPIENTS` (default: `bujdoso.arnold@bujdosoiroda.com`) env alapján ellenőrzi a `To`/`Delivered-To` fejeket; ha egyezés van, a rekord nem kerül be a feedbe, a logban `🔒` jelölést kap.
- A Gmail diagnostics/normalizer modulok importjai, valamint a tsconfig `NodeNext` beállítása gondoskodik róla, hogy ts-node loaderrel is működjön a pipeline.
- `docs/ai-agent-strategy.md` T-2.9 részében szerepel a személyes kupon guard, `notes.md`-ben pedig rögzítettem a változtatást.

## Következő lépések
1. Ha több személyes cím is érintett, add hozzá őket a `GMAIL_PERSONAL_RECIPIENTS` változóhoz (vesszővel elválasztva), majd futtasd újra a cronokat.
2. Figyeld a Gmail ingest logot: ha a `🔒` sorok száma nő, ellenőrizd, hogy nem tévesen blokkol-e publikus promókat.
