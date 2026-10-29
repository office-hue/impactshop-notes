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


## Optional signed donation points contract — 2026-10-29

A meglévő HMAC a teljes JSON törzset védi. Pontozáshoz donation_amount_huf (nemfloat, decimálisstring), donation_policy_version, original_purchase_id, original_purchase_at (UTC Z), revision és attribution_token (SAT1) kell. A rawpseudo és amount_gross/net nem donationauthority. Paid+teljescontract=award; unpaid=pendingbinding; refunded csak refund_scope=full esetén egyszeri teljesrefund. partial/hiányzóscope/hiányoscontract manual. Ugyanaz az eredetivásárlásazonosító és subject marad. A szerver által rögzített namespace impactshop:partner:<partner_code>.

Capture meglévőauth/config/idempotencyvalidáció után, azonosduplicateACK előtt; persistencehiba503, eltérő idempotencybody409 pontesemény nélkül. Optionalmezők hiánya nem töri a régi partnerledger API-t, de nem ad automatikuspontot. A SAT1 bearerérték nem kerül a legacy payload_json-ba.
