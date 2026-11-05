# Direct HUF kézi egyeztetés — 2026-11-05

## Jóváhagyott határ és koherencia

N d0b5a8fc saját ág. Érintett: donation finance-ledger.php, finance-read.php,
meglévő target/CLI/HTTP/worker tesztek. Belépő az új finance-close CLI;
fogyasztó a meglévő owner-status V2 és webhook finance-target dry/apply.
A változás a felhasználó által jóváhagyott meglévő fájlmódosítás.
Nincs új adatbázistábla, API, Stripe-hívás, rendelés-, pont-, szavazat-
vagy pénzmozgatás. A bank_receipt bizonyíték hiányában null marad.

## Konkrét kockázat és ellenőrzés

Dupla jóváhagyás, elveszett ACK, megváltozott bizonyítékra látszó régi
nettó, worker által elvesztett zárás, sérült target. Meglévő egyedi kulcs,
pinned tranzakció, teljes nyers target összehasonlítás és valódi MariaDB
verseny-/visszaolvasási teszt védi. A jóváhagyás csak aktuális egyeztetett
nettó: nem bizonyít teljes felelősséget, kifizetést vagy banki beérkezést.
Account-igazolás az új fizetés aktiválásánál ellenőrzött, nem történeti
nettókimutatást eltüntető új feltétel.

## Kiadás és visszaállítás

Célzott teszt és független forrásreview után saját termékcommit, majd csak
a két érintett guardhash frissítése. Mainművelethez friss csendablak kell.
Exact-file staging, azonos byte production, saját friss hash/mentés/readback.
Új rendelés OFF az accountfeltételek igazolásáig. Rollback új indítás OFF;
a már lezárt adatot olvasni tudó backend marad, nincs pénzügyi DB-visszatöltés.

## Célzott felületi ellenőrzés kiadás után

Saját adományrendelés: fizetés és egyeztetett nettó külön; új refund után
régi jóváhagyott összeg nem aktuális; kifizetés és banki beérkezés külön.
Idegen rendelés nem olvasható. Ehhez a csomaghoz nincs új arculat.

## CLI és állapot

A `--evidence` egy JSON referenciaobjektum, `ref` és `sha256` mezőkkel;
a meglévő privát bizonylat azonosítóját és hashét adja, nem a banki adatot.
Példa szintetikus bemenettel:

```text
wp sharity donations finance-close --order=<do_id> --expect=<target_sha256> --evidence='{"ref":"pilot_evidence","sha256":"<64_hex>"}'
```

Csak az előnézet `proposal_digest` értékével adott `--apply` ír.
`persisted`, `already_closed`, `current_applicable`, `evidence_recorded`
külön jelzés: történeti siker újranyitás után sem jelent aktuális nettót;
más referencia nem lesz elmentettnek feltüntetve.

9169 valódi MariaDB assertion, ebből86 új: párhuzamos close, elveszett ACK,
commit előtti hiba, RR-metadata-verseny, status-only újranyitás, teljes
refund díjhiánya, sérült meta, V1/V2 és változatlan worker.356HTTP,17runtime,
CLI/lint/diff sikeres. Régi negatív vote-fixture warning változatlan.
Független forrásreview két lelete javítva és lezárva; nem éles audit.

Forrás ellenőrzött; commit/push/merge/deploy/aktiválás külön igazolandó.
Az élő Stripe account-, díj- és felelősségfeltételek rendezetlenek.
Idegen protected-record/output WIP érintetlen, csendablak nincs.

## Direct HUF kiadási kivágás

Elfogadott mainalap9fcafb3b; donor2e4fe8c9. A teljes meglévő donation
belépő és tíz almodul/manifest/lock-fájl, valamint30szintetikus teszt
kerül át. A korábbi finance-close kétfájlos változása ebben a már
együtt tesztelt függőségkörben adható ki. Az inaktív Amplifier-kódot
nem írjuk újra; account-bind továbbra kizárólag Directet enged.

Auth a meglévő Sharity sessionből; SDK izolált stripe22.1.0 artifact,
production Composer nélkül. Root loader utolsóként, majd explicit meglévő
quote/order/webhook/webhook-finance/webhook-account/finance/payout sémák.
HTTP nem migrál. Új fizetés OFF a sandbox és élő account/díj/felelősség
ellenőrzéséig. Új séma a finance-close miatt nincs.

Meglévő drain ötpercenként, poll tizenöt percenként; csak ténylegesen
létező five_minutes/sharity_quarter_hour intervallummal, egy-egy saját
esemény és runtime-readback. Új scheduler/daemon nincs.

NGO/mód/HUF engedély külön; a történeti owner status és webhook az új
fizetés OFF állapotában is megmarad. Direct-net100: minden tényleges
fizetési díj utáni összeg NGO, nincs platformrész/szavazat. A pénzügyi
elfogadás a valódi fizetéstől a privát banki bizonylatig külön szükséges.

Az új release-gyökérben is9169DB,461CLI,356HTTP,17runtime és PHP lint/diff
sikeres. A pontos régi SDK-artifact env-paramétere a runtime-suite-ban
SHARITY_DONATION_SDK_VENDOR; nincs közös vendor-módosítás. Kizárólag
a11új donation-forrás kerül a meglévő guardleltárba; a guardlogika és
minden régi védett bejegyzés változatlan. Független integrációs review
lezárva: kiválasztott41fájl donorbyte-azonos, nincs aktiválási rövidítés.

## Protected files touched és smoke

A külön védelmi commitban csak az impactshop-guard-config.json és saját
checksumja, az impactshop-guard-hashes.json és saját checksumja, valamint
bastion-guard-status.md változik. A meglévő bejegyzések megmaradnak; csak
a 11 új donation fájl regisztrációja és a config saját digestje frissül.
Smoke: donation:direct-huf, finance:manual-close, guard:inventory.
Rollback: csak e saját kiadáshoz tartozó fájl/pointer állítható vissza;
új rendelés OFF, korábbi státusz/webhook/egyeztetés megmarad.
