# Partner install guide – Shopify / WooCommerce / UNAS

## Shopify (minimal)
1. Adj hozzá egy theme snippetet, ami **pseudo‑ID + NGO kódot** beír a checkout note‑ba.
2. Hozz létre webhookot: **Order Created** → bridge endpoint.
3. Bridge továbbítja az Impact Shop endpointjára.

**Validáció**
- 1 teszt order → `accepted` + `ledger_id`.

---

## WooCommerce (minimal)
1. Telepítsd a `woocommerce-bridge.php` mintát egy custom pluginba.
2. Hozz létre webhookot WooCommerce-ben → `/impactshop/v1/woo-webhook`.
3. Ellenőrizd a HMAC + Idempotency‑Key küldést.

**Validáció**
- 1 teszt order → `accepted`.

---

## UNAS (minimal)
1. UNAS export / webhook beállítás.
2. Fut a `unas-bridge-node.js` (localhost vagy VPS).
3. Bridge továbbítja az Impact Shop endpointjára.

**Validáció**
- 1 teszt rendelés → `accepted`.

---

## Közös ellenőrzés
- HMAC signature érvényes
- Idempotency‑Key működik
- `ledger_id` visszatér a válaszban
