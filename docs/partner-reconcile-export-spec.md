# Partner pénzügyi kimutatás — pontos bemeneti szerződés

A signed B parancs `operation=partner-statement`, `data` mezője az alábbi
kimutatás. A partner privát policyjában a `rounding=per_transaction_floor_minor` kötelező,
szerződéssel jóváhagyott érték; hiánya nem helyettesíthető implicit kerekítéssel.
A privát `plan` és aláírás a teljes tartalmat köti össze a célrendszerrel,
jogi személlyel, policyvel és előállapottal. Az általános CSV vagy partner API
`paid` mezője nem helyettesíti ezt a jóváhagyást vagy a banki egyeztetést.

| Mező | Szerződés |
| --- | --- |
| `partner_id`, `statement_id` | Stabil azonosítók; ASCII betű/szám, `._:-`, legfeljebb 128 karakter. |
| `revision`, `previous_revision` | Egész, egymást követő revízió; első alkalommal `1` és `0`. |
| `policy_revision` | A privát partnerpolicy pontos jóváhagyott verziója. |
| `currency` | Kizárólag `HUF` vagy `EUR`, átváltás nélkül. |
| `net_minor` | Nem negatív egész, skála 100; pontosan a sorok összege. A B jelenlegi pénzkorlátja is alkalmazandó. |
| `cash`, `part_id` | B bankbeérkezés és a pontosan egyező `impact_shop` rész. |
| `evidence_ref` | Ellenőrzött bizonylat hivatkozása; 1–191 ASCII betű/szám vagy `_:. /-` szóköz nélkül, nem a bizonylat tartalma. |
| `source_digest` | Az eredeti forrás SHA-256 lenyomata, 64 kisbetűs hex karakter. |
| `lines` | 1–1000 egyedi providertranzakció, alábbi formában. |

A `cash` a meglévő közös szerződést követi:

```json
{
  "bank_source": "bank-export",
  "account_fingerprint": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "bank_tx_id": "original-bank-id",
  "currency": "EUR",
  "net_minor": 10001,
  "evidence_ref": "bank-proof:2026-11",
  "parts": [{"part_id": "affiliate", "classification": "impact_shop", "net_minor": 10001}]
}
```

Ez szintetikus formai példa, nem élő számlakötés. A valódi alias a privát
policyben pontosan egy kanonikus számlára és jogi személyre mutat. A banktétel
összes eredeti része megmarad; azokat más csatorna vagy revízió nem teremti újra.

Minden sor mezői pontosan `provider_tx_id`, `net_minor`, `attribution`. A sorok
sorrendje nem módosítja az azonosságot. Az attribúció alapforrását a privát policy
adja; ismeretlen extra mező elutasítást okoz.

| Forrás | Attribúciós mezők |
| --- | --- |
| `partner_tx` | `{}`; a kulcs a sor `provider_tx_id` értéke. Opcionális `ngo_id` csak egyezésellenőrzés. |
| `dognet_intent` | `provider_token_hash` SHA-256, `provider_scope_ref`; opcionális `ngo_id` csak egyezésellenőrzés. |
| `operator_evidence` | `ngo_id`, `evidence_ref`, `source_digest`; a digest a kimutatáséval egyezik. Kifejezett policyengedély kell. |

Bármely alapforrás mellett opcionális `source_kind`; eltérő értékként kizárólag
az explicit engedélyezett `operator_evidence` fogadható el. Hiányzó mappingnél a
sor félretett (`held`) eredményt kap; nem lesz belőle más NGO-ra osztott összeg.

Korrekcióban a teljes eredeti tranzakciókészlet szerepel, a cash, bankrész,
deviza és partnerpolicy változatlan. A sorösszeg legfeljebb az eredeti bankrész,
a sor összege legfeljebb annak eredeti kapacitása. A már rögzített NGO nem
cserélhető le. Az összeg kumulatív aktuális nettó, a könyvelt esemény a korábbi
NGO-összeghez képest számított különbség. Az eredeti dokumentum és minden
kanonikus revízió megőrzendő.

Az `apply` privát eredménye soronként `provider_tx_id`, `net_minor`, `ngo_id`,
`ngo_minor`, `held`, `reason`; továbbá kimutatásazonosító és revízió. Nincs új
nyilvános CSV-export vagy banki DTO. A tényleges utalási CSV a meglévő B
`bank-payout export` parancsból, jóváhagyott batch alapján készül, privát fájlba.
A banktitkot, címzett IBAN-ját és bizonylattartalmat nem szabad DOCSYNC-be másolni.

A régi analitikai export `partner_code,event_id,status,reconcile_status,amount_gross`
mezői olvasási segédletként megtarthatók. A pénzügyi elfogadásban nincs ±1%
tolerancia, implicit HUF, kijelzési FX vagy `paid → approved` banki bizonyíték.
