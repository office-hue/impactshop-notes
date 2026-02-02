# Partner admin permissions – role/capability mátrix

## Cél
Egységes jogosultsági keret a partner admin UI-hoz.

---

## Szerepkörök
- **partner_admin**: teljes hozzáférés a saját partnerhez
- **partner_ops**: olvasás + státusz/pause
- **partner_readonly**: csak olvasás
- **impact_admin**: globális hozzáférés (belső)

---

## Capability mátrix (javaslat)

| Capability | partner_admin | partner_ops | partner_readonly | impact_admin |
| --- | --- | --- | --- | --- |
| `partner_config_view` | ✅ | ✅ | ✅ | ✅ |
| `partner_config_edit` | ✅ | ⚠️ (limitált) | ❌ | ✅ |
| `partner_config_pause` | ✅ | ✅ | ❌ | ✅ |
| `partner_keys_rotate` | ✅ | ❌ | ❌ | ✅ |
| `partner_webhook_view` | ✅ | ✅ | ✅ | ✅ |
| `partner_dispute_view` | ✅ | ✅ | ✅ | ✅ |
| `partner_dispute_decide` | ✅ | ❌ | ❌ | ✅ |
| `partner_reconcile_export` | ✅ | ✅ | ❌ | ✅ |

---

## Limitált szerkesztés (partner_ops)
- `discount_*` mezők: ✅
- `webhook_url`: ❌
- `status`: ✅
