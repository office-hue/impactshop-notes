# Partner admin UI – draft

## Cél
Minimalista admin felület a partner konfiguráció kezelésére és állapotkövetésre.

---

## UX flow (rövid)
1. **Partner lista** (kártyák + státusz)
2. **Partner részletek** (konfig + webhook health)
3. **Mentés** → audit log bejegyzés

---

## Partner lista nézet
- Keresés (partner_code)
- Szűrők: `active|paused|disabled`
- KPI mini: last webhook, success rate

---

## Partner részletek (mezőlista)
### Alapadatok
- `partner_code` (readonly)
- `status` (select: active/paused/disabled)
- `webhook_mode` (test/live)

### Kedvezmény szabályok
- `partner_max_discount` (percent)
- `discount_cap_amount` (HUF)
- `discount_min_cart` (HUF)
- `discount_stackable` (boolean)

### Webhook beállítások
- `webhook_url`
- `allowed_event_types` (multi-select)
- `idempotency_ttl_sec`

### Biztonság (read-only)
- `key_id` (aktuális)
- `last_rotation_at`

---

## Gombok / akciók
- **Mentés**
- **Státusz kapcsoló** (pause/disable)
- **Kulcs rotáció indítása** (ha engedélyezett)

---

## Validáció (minimum)
- `partner_max_discount` $\le 0.5$
- `discount_min_cart` $\ge 0$
- `discount_cap_amount` $\ge 0$
- `webhook_url` https-only
