# Affiliate-en kívüli partnerek bevonása – megvalósítási terv

## Cél
Az Impact Shopba **affiliate-en kívüli** webshopok, offline vásárlások és szállásfoglalások bevonása úgy, hogy:
- **modern, de egyszerű** legyen az integráció,
- a **pseudo‑ID** és a **ledger** végig konzisztens maradjon,
- a partner minimális fejlesztéssel tudjon indulni,
- a rendszer később bővíthető legyen (QR/NFC, POS, API).

## Alapelv (legegyszerűbb modern megoldás)
1. **Webhooks + HMAC**: webshop → Impact Shop eseményküldés (order/booking). Nincs bonyolult SDK.
2. **Pseudo‑ID kényelmesen**: minden csatornán ugyanaz a kulcs (cookie, QR‑OTP, PIN).
3. **Idempotencia és dedupe**: minden esemény idempotens kulccsal érkezik.
4. **Token lifecycle + ledger**: minden nem‑affiliate tranzakció is a ledgerbe kerül.

---

## 1) Online webshop integráció (API-alap)
### 1.1 Minimum (MVP) – egyetlen webhook
**Endpoint**: `POST /impact/v1/partner/transaction`
- Auth: `Authorization: Bearer <partner_api_key>`
- HMAC: `X-Impact-Signature: sha256=...`
- Idempotency: `Idempotency-Key` header

**Payload (minimum)**:
```json
{
  "partner_code": "shopify-abc",
  "event_id": "order_123456",
  "event_type": "purchase",
  "pseudo_id": "ab12cd34ef56",
  "amount_gross": 19990,
  "currency": "HUF",
  "ngo_code": "bator-tabor",
  "timestamp": "2026-01-23T10:30:00Z",
  "payment_status": "paid"
}
```

**Miért ez a legegyszerűbb?**
- Egyetlen endpoint elég minden partnernek.
- Shopify/UNAS/Woo mind tud webhookot küldeni.

### 1.2 Shopify integráció (modern, egyszerű)
- Shopify **Order Created** webhook → `POST /impact/v1/partner/transaction`.
- Pseudo‑ID átadás **checkout attribute** vagy **cart attribute** mezőben.
- NGO kód: `ngo_code` query parameterekből vagy checkout note-ból.

**Legkisebb megoldás**:
- Theme snippet: pseudo‑ID + NGO kód ráírása order note-ra.
- Shopify admin webhook beállítás.

### 1.3 WooCommerce integráció
- Webhook: `order.created` vagy `order.completed`.
- Pseudo‑ID: `order_meta` → `impactshop_pseudo_id`.
- NGO: `order_meta` → `impactshop_ngo_code`.

### 1.4 UNAS integráció
- UNAS Webhook/Order export → saját kis „bridge” script,
  ami továbbküldi az Impact Shop endpointjára.

**Egyszerű bridge opció**:
- Cloudflare Worker / AWS Lambda (10–20 sor), csak továbbítás + HMAC.

---

## 2) Offline vásárlás / szállás / foglalás (QR/NFC)
### 2.1 Azonosítás
- **QR‑OTP**: 60–120s rövid token (nem pseudo‑ID).
- **PIN fallback**: ha offline vagy nincs QR, PIN‑es visszaállítás.

**Flow**:
1. User megnyitja QR-t (Impact Shop app / web).
2. POS/recepció beolvassa → `POST /impact/v1/identity/qr-otp`.
3. Sikeres validáció → pseudo‑ID visszajön, tranzakció rögzít.

### 2.2 Offline tranzakció rögzítés
**Endpoint**: `POST /impact/v1/retail/sale/redeem`
- Idempotency‑Key kötelező.
- Pseudo‑ID + összeg + partner + timestamp.

### 2.3 NFC (modern, de egyszerű)
**Opció A – Android NFC tag**
- NFC tag csak QR‑OTP URL‑t tartalmaz.
- POS/telefon NFC-vel olvas → ugyanaz a flow, mint QR.

**Opció B – Apple/Google Wallet pass**
- Wallet pass tartalmaz QR‑OTP refresh URL‑t.
- A wallet pass csak **URL**-t tárol, nem pseudo‑ID‑t.

---

## 3) Szálláshely / foglalás (hospitality flow)
**Kulcs elv**: foglalás = token lifecycle

**Flow**:
1. **Booking Created** → `/token/new` (Issued)
2. **Check‑in** → `/token/activate`
3. **Checkout / Confirm** → `/token/confirm` → ledger + pont

**Minden lépés** idempotens, Idempotency‑Key kötelező.

---

## 4) Ledger & elszámolás
Minden non‑affiliate esemény bekerül:
- `wp_impact_ledger` (source = `shop` vagy `retail`)
- `impact_points_log` (ha pont jár)

**Szabályok**:
- HUF alap, EUR esetén MNB napi árfolyam.
- Dedupe kulcs: `pseudo_id + event_id`.

### 4.1 Transzparencia és auditálhatóság (vásárló + partner)
**Cél**: minden tranzakció **egyértelműen azonosítható**, vitathatató és visszakereshető legyen.

**Kötelező mezők a tranzakcióban**:
- `event_id` (partner oldali egyedi ID)
- `partner_code`
- `pseudo_id` (hash-elhető, PII nélküli)
- `ngo_code`
- `amount_gross`, `currency`
- `timestamp`
- `idempotency_key`

**Ledger rögzítés**:
- `ledger_id` generálása minden bejegyzéshez.
- `event_id` + `partner_code` alapján dedupe.
- `source` mező: `shop|retail|hospitality`.

**Audit log**:
- `impact_audit_log` minden partner webhookhoz (`action=partner_tx_received`, `status=accepted|rejected|duplicate`).
- `audit_hash`: HMAC a bejövő payloadból, hogy a későbbi vitákban igazolható legyen a tartalom.

**Kétoldali transzparencia**:
- **Partner riport**: webhook visszajelzés `ledger_id`-val + státusz (accepted/duplicate/rejected).
- **Vásárlói riport**: pseudo‑ID alapján listázható tranzakciók (idő, összeg, NGO, státusz).

**Anti‑fraud / “nem létező tranzakció” védelem**:
- Kizárólag **partner által aláírt** webhook fogadható el.
- Idempotencia: ugyanaz az `event_id` csak egyszer könyvelhető.
- Státusz lifecycle: `pending → approved|declined` (nem “fizet” addig, amíg nem approved).

### 4.2 Tranzakció státuszok és bizonyíthatóság
**Státuszok**:
- `pending`: beérkezett, ellenőrzés alatt.
- `approved`: hitelesített, pont/adomány könyvelhető.
- `declined`: partner visszavonta (refund/chargeback).
- `void`: technikai törlés (dupe/hiba).

**Bizonyíték mezők (viták elkerülésére)**:
- `partner_ref` (pl. rendelési szám, foglalási ID)
- `payment_status` (paid/unpaid/refunded)
- `approved_at`, `declined_at`, `void_at`
- `proof_hash` (aláírt payload hash)

**Vásárlói transzparencia UI**:
- Tranzakció lista: státusz, összeg, NGO, kedvezmény, partner, dátum.
- “Miért pending?” magyarázó szöveg + várható idő.

### 4.3 Partner elszámolás és egyeztetés
**Cél**: minden könyvelés partnerrel is **egyezzen**, visszakereshetően.

**Napi/Heti egyeztetés**:
- Partner riport: `ledger_id`, `event_id`, `status`, `amount`, `discount_rate`.
- Eltérés esetén `reconcile_status=disputed` + ticket.

**Dispute flow**:
1. Partner jelzi: `POST /impact/v1/partner/dispute`.
2. Státusz: `disputed` → manuális review.
3. Döntés: `approved|declined` + audit log.

---

## 5) Biztonság (egyszerű, modern)
- HMAC aláírás minden partneri requesthez.
- Idempotency‑Key minden eseményhez.
- Partner API‑kulcsok rotációja (90 napos policy).
- Replay védelem QR‑OTP esetén.

---

## 6) Pontozási integráció (non‑affiliate tranzakciók)
**Cél**: a non‑affiliate tranzakciók is **pontot érjenek**, és ugyanazt a logikát kövessék,
mint az Impact Shop többi eseménye.

**Pont logika**:
- Minden `approved` tranzakció pontot generál.
- Dedupe: `pseudo_id + event_id`.
- Minden pont **auditálható** és visszavonható (`void`/`adjust`).

**Pont típusok (javaslat)**:
- `partner_purchase` – vásárlás / foglalás
- `partner_adjust` – részjóváírás
- `partner_void` – visszavonás (pont levonás)

---

## 7) Többszintű kedvezmény rendszer (Sharity ID)
**Cél**: a Sharity ID‑t használók aktivitás alapján egyre nagyobb kedvezményt kapnak.
Ez csak a non‑affiliate partnereknél lehetséges.

**Alapelv**:
- Minden partner megadja a **max kedvezményt** a legmagasabb szinten.
- Az alsó szintek **százalékos arányban** csökkennek ebből.
- A szintek **a meglévő pontszám‑besoroláshoz** igazodnak (percentilis alapú rendszer).

**Szint‑arányok (a pontrendszer szintjeivel azonosítva)**:

| Szint | Pontszám‑besorolás (spec) | Szorzó a max kedvezményhez | Példa max=20% esetén |
| --- | --- | --- | --- |
| **Legend** | Top 10% | 1.00× | 20.0% |
| **Platinum** | 10–20% | 0.90× | 18.0% |
| **Gold** | 20–40% | 0.80× | 16.0% |
| **Silver** | 40–70% | 0.70× | 14.0% |
| **Bronze** | 70–90% | 0.60× | 12.0% |
| **Basic** | Bottom 10% + új userek | 0.50× | 10.0% |

**Szabályok**:
- A kedvezmény mindig a partner által rögzített maxból számolódik.
- Kerekítés: 0.5% pontosság (pl. 17.5%).
- A besorolás a pontszám alapján automatikus.

**Transzparencia (partner + vásárló)**:
- **Partner**: a webhook válasz tartalmazza a `discount_tier`, `discount_rate`, `partner_max_discount` mezőket.
- **Vásárló**: UI-ban mindig látszik a szint, a kedvezmény és a max‑kedvezmény, hogy elkerülhető legyen a félreértés.

### 7.1 Kedvezmény számítási API (egyszerű, auditálható)
**Endpoint**: `POST /impact/v1/partner/discount/quote`

**Input**:
```json
{
  "partner_code": "shopify-abc",
  "pseudo_id": "ab12cd34ef56",
  "amount_gross": 19990,
  "currency": "HUF"
}
```

**Output**:
```json
{
  "tier": "gold",
  "partner_max_discount": 0.20,
  "discount_rate": 0.16,
  "discount_amount": 3198,
  "amount_net": 16792,
  "explain": "Gold szint → 80% a max kedvezményből"
}
```

### 7.2 Kedvezmény‑stacking szabályok
- **Nem halmozható** más kuponokkal, kivéve ha a partner engedélyezi.
- **Max cap**: partner oldalon fix (pl. 20% vagy 5 000 Ft).
- **Minimum kosár**: partner döntheti el (pl. 5 000 Ft felett).

### 7.3 Partner konfiguráció (egyszerű modell)
**Kulcsok**:
- `partner_max_discount`
- `discount_min_cart`
- `discount_cap_amount`
- `discount_stackable` (true/false)

**Transzparencia kötelező mezők**:
- `discount_tier`, `discount_rate`, `partner_max_discount`

---

## 8) MVP rollout javaslat
**Fázis 1 – Webshop API**
- Egy Shopify pilot partner
- Egy webhook endpoint

**Fázis 2 – Offline QR**
- QR‑OTP endpoint + 1 partner POS teszt

**Fázis 3 – Booking flow**
- Szálláshely partner, token lifecycle teszt

---

## 9) Hiányzó komponensek (javasolt új fejlesztések)
1. **Partner API gateway** (egy endpoint, HMAC + idempotencia)
2. **QR‑OTP validáció endpoint** (már tervben, implementáció kell)
3. **Minimal partner dashboard** (API kulcs, webhook állapot, last events)
4. **Bridge script minták** (Shopify/Woo/UNAS minimal file)

---

## 10) Következő lépés
- Döntés: első pilot partner (webshop vagy offline?)
- API kulcs generálás + webhook teszt
- Staging smoke (ledgerbe bekerül-e?)

### Kapcsolódó dokumentumok
- `docs/partner-summary.md`
- `docs/partner-transparency-dashboard.md`
- `docs/partner-webhook-sla.md`
- `docs/partner-api-openapi.yaml`
- `docs/partner-api-examples.md`
- `docs/partner-pilot-tests.md`
- `docs/partner-demo-scenario.md`
- `docs/partner-master-checklist.md`
- `docs/partner-db-migration-template.md`
- `docs/partner-db-schema.md`
- `docs/partner-config-storage.md`
- `docs/partner-auth-secrets.md`
- `docs/partner-reconciliation-job.md`
- `docs/partner-dashboard-wireframes.md`
- `docs/partner-webhook-test-env.md`
- `docs/partner-monitoring-kpi.md`
- `docs/partner-dispute-policy.md`
- `docs/partner-data-retention.md`
- `docs/partner-postman-collection.md`
- `docs/partner-postman-collection.json`
- `docs/partner-admin-ui-draft.md`
- `docs/partner-admin-ui-fields.csv`
- `docs/partner-admin-permissions.md`
- `docs/partner-reconcile-export-spec.md`
- `docs/partner-audit-event-list.md`
- `docs/partner-webhook-retry-spec.md`
- `docs/partner-sla-onepager.md`
- `docs/partner-config-validation.md`
- `docs/partner-api-error-catalog.md`
- `docs/partner-api-sample-responses.md`
- `docs/partner-staging-runbook.md`
- `docs/partner-webhook-security-checklist.md`
- `docs/partner-data-mapping.md`
- `docs/partner-onboarding-email-template.md`
- `docs/partner-release-checklist.md`
- `docs/partner-faq.md`
- `docs/partner-changelog.md`
- `docs/partner-webhook-sequence.md`
