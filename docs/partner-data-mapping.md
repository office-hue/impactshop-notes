# Partner → Impact data mapping

## Cél
Egységes mező‑mapping táblázat a partner rendszerekhez.

| Partner mező | Impact mező | Kötelező | Megjegyzés |
| --- | --- | --- | --- |
| `partner_code` | `partner_code` | ✅ | partner azonosító |
| `order_id` | `event_id` | ✅ | partner order/booking ID |
| `event_type` | `event_type` | ✅ | purchase/booking/retail |
| `customer_pseudo_id` | `pseudo_id` | ✅ | Sharity pseudo‑ID |
| `ngo_code` | `ngo_code` | ✅ | NGO slug |
| `amount_gross` | `amount_gross` | ✅ | bruttó összeg |
| `currency` | `currency` | ✅ | HUF/EUR/USD |
| `timestamp` | `timestamp` | ✅ | ISO8601 |
| `payment_status` | `payment_status` | ✅ | paid/unpaid/refunded |
| `discount_tier` | `discount_tier` | ❌ | basic/bronze/silver/gold/platinum/legend |
| `discount_rate` | `discount_rate` | ❌ | 0.00–0.50 |
| `partner_max_discount` | `partner_max_discount` | ❌ | 0.00–0.50 |
| `meta` | `meta` | ❌ | extra adatok |

---

## Megjegyzés
- Ha partner oldalon más a mezőnév, ezt a mappinget kell alkalmazni.


## Aláírt adomány-pontozási szerződés — közvetlen lane OFF

A fogadó technikai ellenőrzése nem jelent valódi kereskedői bekötést. A `partner-prod` korábbi tesztazonosító; nem aktiválható ennek a munkának a részeként. A Shopify/WooCommerce/UNAS hidak integrációs minták.

| Pontozási mező | Szerződés |
| --- | --- |
| `original_purchase_id` | Stabil, partneren belül egyedi vásárlásazonosító; korrekciónál változatlan. 1–128 ASCII betű/szám vagy `._:-`. |
| `original_purchase_at` | Eredeti UTC-idő, pontos `YYYY-MM-DDTHH:mm:ssZ`; nem a webhook érkezése. |
| `donation_amount_huf` | Pontos, nem negatív decimális string, vezető nullák nélkül (a nulla kivétel), legfeljebb 6 tizedessel és 64 karakterrel; végleges adomány HUF-ban, egy half-up kerekítéssel. Nem kosárösszeg, becsült jutalék vagy szintszorzó. |
| `donation_policy_version` | A valódi kereskedővel elfogadott adományszabály verziója, 1–96 ASCII betű/szám vagy `._:-`. A fogadó formátumot ellenőriz, szerződést nem helyettesít. |
| `revision` | Stabil eseményrevízió, 1–64 ASCII betű/szám vagy `._:-`; azonos revízió és tartalom ismétlése hatástalan. |
| `attribution_token` | Hiteles SAT1; az első teljes pending esemény tartósan rögzíti a szerveroldali subjectet. A raw `pseudo_id` nem jogosít pontozásra. |
| `refund_scope` | `full` az egyszeri teljes visszavonáshoz; részleges/hiányos/ismételt korrekció kézi eset. |

A namespace `impactshop:partner:<partner_code>`. Már az `unpaid` esemény teljes azonosítót, időt, policyt, revíziót és decimális összeget igényel (a még nem végleges összeg lehet `"0"`, nem kerül jóváírásra). Hiányos pending kézi ügyet nyithat, amely később sem kapcsol vissza automatikusan. A `paid` csak igazoltan végleges adomány esetén küldhető; teljes contract mellett egyszeri award. A teljes refund a meglévő jóváírás és aktuális egyenleg minimumát vonja le, a maradék végleg elengedett.

A HMAC a nyers JSON-törzs küldőjét/tartalmát hitelesíti; az adományszabályt külön a partner szerződéséhez kell kötni. Az affiliate 50% szabály nem alkalmazható automatikusan. Ezért a közvetlen lane most OFF marad. OFF mellett a beérkezett pontesemény megmarad, feldolgozáskor `manual / lane_not_ready`, automatikus pont nélkül.

A fogadó a meglévő auth/config/idempotency ellenőrzés után tartósan rögzíti a ponteseményt, még a duplicate ACK előtt. A HTTP 200 és `accepted/duplicate` a fogadást jelzi, nem a pontjóváírást. Persistencehiba 503; a tárolt idempotenciakulcs TTL-jén belüli eltérő body 409, új pontesemény nélkül. Új státusz/revízió új transportkulcsot kap; ugyanazon kérés megismétlése az eredeti kulcsot/bodyt használja. A tartós gazdasági idempotencia a transportkulcstól külön működik. Az opcionális mezők hiánya nem töri a régi ledger API-t, de nem ad automatikus pontot. SAT1 nem kerül a legacy `payload_json` mezőbe.

## Fogadóhiba célzott javítása — 2026-10-29

Koherencia: kizárólag a transaction REST callback meglévő insertjének formátumlistája érintett; a 17 adatmezőhöz 18 formátum tartozott, ezért az idempotenciakulcs `%d` átalakítással `0` lett. A helyi, teljes aláírt kéréses SQL-teszt ezt reprodukálta: eltérő body mellett 200 duplicate érkezett 409 helyett.

A javítás a mezők sorrendjéhez igazítja a formátumokat. Az auth, pontszabály, állapotgép és ütemezés változatlan. Kockázat: új transaction sorok tárolási típusa; ellenőrzés: tényleges kulcs/összegek visszaolvasása, hibás auth, pending/final/replay/refund és OFF lane SQL-teszt. Mentés: saját `tmp/partner-finish/impactshop-partner-api.before.php`, továbbá a rögzített Git-alap. Élesítéskor a meglévő exact-file távoli backup/rollback kell; korábbi hibás sorokat nem írunk át és nem törlünk. A partnerpontozás OFF marad.

Kézi UI-checklist telepítés után: a pont/szint nézet elérhető; normál vásárlási redirect működik; közvetlen partnerből nincs automatikus új pont. REST-ellenőrzés stagingen szintetikus adattal, éles tesztvásárlás nélkül.

Érintett fájlok: `impactshop-partner-api.php`, `impactshop-guard-hashes.json`, `impactshop-guard-hashes.sha256`, `bastion-guard-status.md`. Smoke scope: signed partner callback / local SQL contract / lane OFF. A runtime egyetlen formátumsora változik, az éles backup/rollback a meglévő exact-file kiadási út feladata.
