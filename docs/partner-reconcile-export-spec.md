# Partner pénzügyi kimutatás — pontos bemeneti szerződés

A V2 signed B parancs `operation=partner-statement`, `data` mezője az alábbi
kimutatás. A partner privát policyjában a `rounding=per_transaction_floor_minor` kötelező,
szerződéssel jóváhagyott érték; hiánya nem helyettesíthető implicit kerekítéssel.
A privát `plan` és aláírás a teljes tartalmat köti össze a célrendszerrel,
jogi személlyel, policyvel és előállapottal. Az általános CSV vagy partner API
`paid` mezője nem helyettesíti ezt a jóváhagyást vagy a banki egyeztetést.

| Mező | Szerződés |
| --- | --- |
| `partner_id`, `statement_id` | Stabil azonosítók; ASCII betű/szám, `._:-`, legfeljebb 128 karakter. |
| `revision`, `previous_revision` | Egész, egymást követő revízió; első alkalommal `1` és `0`. |
| `expected_revision`, `original_statement_key` | Első kiadásnál `0` és `null`; korrekcióban az aktuális revízió és az első eredményben visszaadott 64 hex kulcs. A `previous_revision` az `expected_revision` értékével egyezik. |
| `bank_account_id` | A B policy kanonikus 64 hex bankszámla-azonosítója, a partnerforrásszabállyal egyezően. Nem provider-scope és nem banki alias. |
| `financial_scope` | Pontosan `{provider,provider_scope_ref,source_policy_sha256}`; a kiválasztott szabállyal és a boríték `grant_id` engedélyével egyezik. |
| `policy_revision` | A privát partnerpolicy pontos jóváhagyott verziója. |
| `currency` | Kizárólag `HUF` vagy `EUR`, átváltás nélkül. |
| `net_minor` | Nem negatív egész, skála 100; pontosan a sorok összege. A B jelenlegi pénzkorlátja is alkalmazandó. |
| `cash`, `part_id` | B bankbeérkezés és a pontosan egyező `impact_shop` rész. |
| `evidence_ref` | Ellenőrzött bizonylat hivatkozása; 1–191 ASCII betű/szám vagy `_:. /-` szóköz nélkül, nem a bizonylat tartalma. |
| `source_digest` | Az eredeti forrás SHA-256 lenyomata, 64 kisbetűs hex karakter. |
| `lines` | 1–1000 egyedi providertranzakció, alábbi formában. |

A `source_policy_sha256` a kiválasztott partnerforrásszabály kanonikus JSON-jának
SHA-256 lenyomata. A kiválasztott mezők pontosan `revision`, `provider`,
`bank_account_id`, `provider_scope_ref`, `source_kind`, `ngo_rate_bps`, `rounding`,
`allow_operator_evidence` (alapértéke hamis). Az objektumkulcsok rekurzívan
rendezettek, JSON slash/unicode escape nélkül. Nem a teljes B policy hash-e;
azt a parancsboríték külön `policy_sha256` mezője köti.

Az `approver` kulcsnak a pontos `partner-statement` granthez tartozó jog kell;
collector kulccsal NGO-jogosultság nem írható. A B V2 aláírási tartományát a
`signing_domain(plan)` adja, nem a régi V1 konstans.

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

A `provider_tx_id` a `financial_scope.provider` és `provider_scope_ref` saját
névterében stabil azonosító. A tulajdonosi kulcs a provider, provider-scope,
jogi személy, test/live mód és provider-tranzakció alapján képződik; a fogadó
bank vagy policyverzió változása nem enged új elszámolást. Külön bizonyított
forrásscope-ban az azonos helyi ID megengedett. Korábbi, bizonyíthatatlan
scope-ú azonos ID mellett az új import megáll; más bank nem bizonyít új pénzt.
A szerver a korrekció tényleges sorhivatkozását megőrzi, új ID-t nem kér az
operátortól és történeti sort nem kulcsol át.

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

Korrekcióban az eredeti kulcs és `expected_revision` alapján zárolt, szerveren
fagyasztott szabály és foglalás az alap. Mai partnerpolicy/alias alapján nincs
újrafoglalás; az eredeti forráspolicy hash-e és engedélyezett grantje kell.
Bizonyíték nélküli legacy sort a legújabb policy nem jogosíthat fel.
A teljes eredeti tranzakciókészlet szerepel, a cash, bankrész,
deviza és partnerpolicy változatlan. A sorösszeg legfeljebb az eredeti bankrész,
a sor összege legfeljebb annak eredeti kapacitása. A már rögzített NGO nem
cserélhető le. Az összeg kumulatív aktuális nettó, a könyvelt esemény a korábbi
NGO-összeghez képest számított különbség. Az eredeti dokumentum és minden
kanonikus revízió megőrzendő.

Az `apply` privát eredménye soronként `provider_tx_id`, `net_minor`, `ngo_id`,
`ngo_minor`, `held`, `reason`; továbbá `original_statement_key`, kimutatásazonosító és revízió. Nincs új
nyilvános CSV-export vagy banki DTO. A tényleges utalási CSV a meglévő B
`bank-payout export` parancsból, jóváhagyott batch alapján készül, privát fájlba.
A banktitkot, címzett IBAN-ját és bizonylattartalmat nem szabad DOCSYNC-be másolni.

### Történeti Dognet-kimutatás

A történeti csomag aláírt `partner-statement` adatai a meglévő mezők mellett
`historical_cash` objektumot tartalmaznak. A `rule_ref` a kanonikus szabályzat
pontos Git-revíziójához és szabálysorához rendelt belső hivatkozás. Az
`evidence_sha256` a változatlan privát csomag lenyomata. A csomag tartalmazza
a dokumentumlenyomatot, hatályt, partnerpolicy-lenyomatot, eredeti Dognet-exportot,
bankbizonylatot és a tételes egyezést. A `source_digest` és a banki
`evidence_ref` megőrzi saját szerepét. A pénzügyi azonosító és a bizonylat
privát marad.

A `rows` a teljes eredeti bankbeérkezés forrástételeit tartalmazza.
Minden sor mezője `provider_tx_id`, `part_id`, `net_minor`, `disposition`,
`ngo_id`, `source_policy_sha256`, `priority`. A Dognet eredeti `ID` mezője
a providerazonosító. A HUF scale-2 kapacitás összege rész és teljes banktétel
szintjén pontosan egyezik. Első revízióban minden kiválasztott tétel a saját
teljes eredeti banki részértékével szerepel. Korrekcióban a tételazonosság és
eredeti felső határ megmarad.

- `eligible`: igazolt NGO és szabálylenyomat. Azonos szabályú sorok közös
  bankrészt használhatnak. Az elsőbbség az aláírt sorbesorolás része.
- `held`: saját, változatlan bankrész. Már igazolt numerikus NGO rögzíthető,
  a szabálylenyomat itt null. Új feldolgozáskor az eredeti sorhoz és összeghez
  pontos partnergrant és új tételes bizonyíték szükséges.
- `excluded`: jelölő nélkül végleg kizárt, NGO és szabálylenyomat null.
  A kizárt sorok közös `unassigned` bankrészbe kerülhetnek.

A teljes felosztás az első jogosultságkönyveléssel azonos tranzakcióban
rögzül. Minden későbbi rész és revízió ugyanazt a teljes banki felosztást
és történeti jegyzéket hordozza. A már rögzített historikus NGO-jogosultság
külön elsőbbséget élvez a következő HUF csomagban.

A régi analitikai export `partner_code,event_id,status,reconcile_status,amount_gross`
mezői olvasási segédletként megtarthatók. A pénzügyi elfogadásban nincs ±1%
tolerancia, implicit HUF, kijelzési FX vagy `paid → approved` banki bizonyíték.
