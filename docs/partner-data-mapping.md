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
