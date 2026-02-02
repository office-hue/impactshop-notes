# Partner konfiguráció tárolás

## Cél
Partnerenként tárolni a **kedvezmény‑szabályokat** és az integrációs beállításokat úgy, hogy:
- gyorsan lekérdezhető legyen a webhook feldolgozás során,
- támogatott legyen a staging/prod elkülönítés,
- auditálható legyen a módosítás.

---

## Javasolt tárolási modell
### 1) Konfigurációs tábla: `wp_impact_partner_config`
**Kulcs mezők**
- `partner_code` (varchar(64), unique)
- `status` (varchar(16)) – `active|paused|disabled`
- `webhook_url` (varchar(255))
- `webhook_mode` (varchar(16)) – `test|live`
- `partner_max_discount` (decimal(5,4))
- `discount_cap_amount` (int)
- `discount_min_cart` (int)
- `discount_stackable` (tinyint)
- `currency` (char(3))
- `allowed_event_types` (varchar(255)) – pl. `purchase,booking`
- `idempotency_ttl_sec` (int) – alap: 86400
- `created_at`, `updated_at` (datetime)

**Indexek**
- Unique: `partner_code`
- Index: `status`, `webhook_mode`

### 2) Konfig cache (opcionális)
- `transient: impact_partner_config_{partner_code}`
- TTL: 5–10 perc
- Fallback: DB

---

## Módosítási audit
- `wp_impact_partner_config_log` tábla (opcionális)
  - `partner_code`, `changed_by`, `diff_json`, `created_at`
- Alternatíva: `impact_audit_log` bővítés `action=partner_config_update`

---

## Admin felület / API javaslat
- **Admin UI**: partner konfiguráció szerkesztése + státusz kapcsoló
- **API (belső)**: `POST /impact/v1/admin/partner/config`
- Validáció: max kedvezmény $\le 0.5$, min kosár $\ge 0$

---

## Különbség a secrets tárolástól
- A konfiguráció **nem** tartalmaz titkos kulcsot.
- A titkos kulcsok külön (lásd `docs/partner-auth-secrets.md`).
