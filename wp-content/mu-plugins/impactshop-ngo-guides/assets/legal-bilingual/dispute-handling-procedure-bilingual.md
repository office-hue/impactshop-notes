<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

# Chargeback és Dispute Kezelési Eljárásrend

> **Verzió**: 1.2  
> **Dátum**: 2026-02-25  
> **Státusz**: HATÁLYOS  
> **Vonatkozik**: Impact Amplifier (szavazatvásárlás) — Stripe Checkout tranzakciók  
> **Kapcsolódó**: Stripe Felelősségmegosztási Mátrix, Impact Challenge szavazatvásárlási terv §4.5 D6

---

## 1. Áttekintés

Az Impact Amplifier szavazatvásárlás **adományjellegű** tranzakció, amelyre a D6 üzleti szabály vonatkozik:

> **D6**: „Adomány nem visszakövetelhető" — a tranzakció természete szerint adományozás, nem termékértékesítés.

Ennek ellenére a Stripe/kártyakibocsátó **chargeback** (visszaterhelés) mechanizmusa technikai szinten továbbra is elérhető a kártyatulajdonos számára. Ez az eljárásrend rögzíti a Sharity teendőit ilyen esetben.

---

## 2. Chargeback időkeretek

| Fázis | Időkeret | Ki intézi |
|-------|---------|-----------|
| **Kártyabirtokos chargeback bejelentés** | Tranzakciótól számított **120 nap** (Visa/MC) | Felhasználó → kibocsátó bank |
| **Stripe értesítés** | ~1-2 munkanap a bank bejelentése után | Stripe → webhook: `charge.dispute.created` |
| **Sharity válaszidő** | **7 naptári nap** a Stripe értesítéstől | Sharity admin |
| **Bank döntés** | 30–90 nap a bizonyíték benyújtástól | Kibocsátó bank |

---

## 3. Chargeback kezelési folyamat

### 3.1 Értesítés fogadása

1. **Stripe webhook** (`charge.dispute.created`) → a rendszer automatikusan naplózza:
   - `wp_impactshop_vote_purchases.status` → `disputed`
   - Guard alert → Discord webhook (üzemeltetési csatorna)
   - Email értesítés → Ops Squad

2. **Manuális észlelés** — ha a webhook feldolgozás bármely okból sikertelen:
   - Stripe Dashboard → Payments → Disputes ellenőrzése naponta
   - P2 szintű incident → ld. SLA §2

### 3.2 Vizsgálat (≤ 2 munkanap)

| Ellenőrzés | Hol | Mit keresünk |
|------------|-----|-------------|
| Tranzakció részletek | `wp_impactshop_vote_purchases` tábla | `stripe_session_id`, `amount`, `timestamp`, `pseudo_id` |
| Webhook log | Alkalmazás naplók | Sikeres checkout callback bejegyzés |
| Felhasználói adatok | DB + Stripe Dashboard | Email, pseudo_id, IP, Cloudflare Turnstile eredmény |
| Fraud jelek | Stripe Radar | Risk score, IP country, card fingerprint |
| Többszörös vásárlás | DB query | Ugyanazon pseudo_id/email/IP-ről jött-e több vásárlás |

### 3.3 Döntés

| Helyzet | Akció | Szavazat kezelés |
|---------|-------|-----------------|
| **Fraud** — hamis kártyahasználat (nem a valódi tulajdonos vásárolt) | Bizonyíték benyújtás → Stripe-on keresztül VAGY refund elfogadás | Szavazatok **visszavonása** |
| **Friendly fraud** — a tényleges vásárló vonja vissza | Bizonyíték benyújtás (checkout evidence) | Szavazatok **befagyasztása** a döntésig |
| **Jogos reklamáció** — tényleges rendszerhiba, dupla levonás | Refund elfogadás | Szavazatok korrekcióba — csak a dupla rész visszavonása |
| **Ismeretlen / bizonytalan** | Bizonyíték benyújtás, ha lehetséges | Szavazatok **befagyasztása** |

### 3.4 Bizonyíték összeállítás

Az alábbi elemeket kell csatolni a Stripe dispute response-hoz:

1. **Tranzakciós bizonyíték**
   - Checkout Session screenshot (Stripe Dashboard-ról)
   - Webhook delivery log (siker/timestamp)
   - Vásárlás utáni visszaigazolás (ha email küldés megtörtént)

2. **Felhasználói szándék bizonyítéka**
   - Cloudflare Turnstile verification = pass → nem bot
   - Adomány természetét megerősítő checkout szöveg: _„Adományozás az Impact Challenge-en keresztül"_
   - Terms & Conditions link a checkout oldalon

3. **Adomány-specifikus érvelés**
   - Az Impact Amplifier szavazatvásárlás **adományozási cselekmény**, nem termék/szolgáltatás vásárlás
   - A checkout oldalon egyértelmű tájékoztatás: _„Az összeg 50%-a a Közös Adományalapba kerül, amelyet a civil szervezetek között a szavazatok arányában (ÁSZF szerint negyedévente) osztunk szét; a felhasználó a kapott szavazatokat a kiválasztott civil szervezetre adja le."_
   - D6 szabály: az adományozó előzetes beleegyezésével történik

4. **Benyújtás**: Stripe Dashboard → Disputes → Submit Evidence

### 3.5 Eredmény kezelés

| Stripe döntés | Teendő |
|---------------|--------|
| **Won** (Sharity javára) | `status` → `completed` visszaállítás; szavazatok feloldása; záró naplóbejegyzés |
| **Won partially** (részleges Sharity javára) | `status` → `completed` a nem vitatott részre; a visszatérített összeg arányában `votes_granted` csökkentése; pool részleges korrekció (`refunded_amount × 0.5`); naplóbejegyzés mindkét részre |
| **Lost** (kártyabirtokos javára) | `status` → `refunded`; szavazatok végleges visszavonása; pool csökkentés |
| **Accepted** (Sharity nem vitatja) | `status` → `refunded`; szavazatok végleges visszavonása; pool csökkentés |

---

## 4. Szavazat visszavonási eljárás

### 4.1 Manuális visszavonás (WP-CLI)

```bash
wp impactshop vote-purchase void --order_id=<stripe_session_id>
```

Ez az alábbi műveleteket hajtja végre:
- `wp_impactshop_vote_purchases.status` → `voided` / `refunded`
- `wp_impactshop_challenges.total_votes` → csökkentés a jóváírt szavazatszámmal
- `wp_impactshop_challenges.pool_amount` → csökkentés az adomány részével (pool_share = amount × 50%)

### 4.2 Pool korrekció

- A pool összeg csökken az adományrész nagyságával (`amount × 0.5`)
- Ha a quarter még nem zárult le: a szavazat automatikusan eltűnik a szavazati arányból
- Ha a quarter **már lezárult** és kifizetés megtörtént: **manuális korrekciót** kell végrehajtani a következő negyedéves elosztásban (negatív tétel az érintett NGO-nál, **ha a szavazatok arányosan befolyásolták**)

### 4.3 Naplózás

Minden dispute-ot naplózni kell:

```
disputes.log:
2026-02-25T14:30:00Z | dispute_id=dp_xxx | session_id=cs_xxx | amount=2000 | decision=evidence_submitted | votes_frozen=2500 | admin=sharity_admin
```

---

## Kapcsolódó dokumentumok és guide-ok

- [Stripe felelősségmegosztás](./stripe-responsibility-matrix.md)
- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Negyedéves elosztási riport sablon](./quarterly-distribution-report-template.md)
- [SLA](./sla-policy.md)
- [Impact Challenge útmutató](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [Impact Amplifier (Rólunk)](https://app.sharity.hu/rolunk/)

---

## 5. Prevenció

### 5.1 Aktuális védelmi rétegek

| Réteg | Leírás | Beállítás |
|-------|--------|-----------|
| **Cloudflare Turnstile** | Bot/automation szűrés checkout indítás előtt | Checkout form előtt |
| **Rate limiting** | IP: max 10 checkout/óra; pseudo_id: max 5 checkout/óra | MU plugin + Cloudflare WAF |
| **Stripe Radar** | Beépített fraud scoring, kockázatos tranzakciók blokkolása | Stripe Dashboard → Radar Rules |
| **3D Secure** | EU-ban kötelező SCA (Strong Customer Authentication) | Stripe automatikus |
| **Egyértelmű tájékoztatás** | „Adomány" szó használata, nem „vásárlás" | Checkout UI szövegek |


---

## 6. Eskalációs mátrix

| Dispute összeg | Döntéshozó | Eskaláció |
|----------------|-----------|-----------|
| **≤ 5 000 Ft** | Ops Squad self-service | Nincs (standard eljárás) |
| **5 001 – 50 000 Ft** | Ops Squad + pénzügyi felelős | 48h-n belül közös döntés |
| **> 50 000 Ft** | Vezetőség + jogi tanácsadó | Azonnali eskaláció, jogi vélemény kérés |
| **Ismétlődő** (>3 dispute / hónap) | Vezetőség | Fraud pattern vizsgálat, Stripe support bevonás |

---

## 7. Felülvizsgálat

- **Gyakoriság**: negyedévente, vagy bármely dispute esemény után
- **Felelős**: Ops Squad
- **Metrika**: dispute rate < 0.1% (Stripe elvárás: < 0.75%)

---

## Változásnapló

| Verzió | Dátum | Változás |
|--------|-------|---------|
| 1.0 | 2026-02-25 | Kezdeti verzió — draft |
| 1.1 | 2026-02-25 | „Won partially" kimenet hozzáadása (§3.5) |
| 1.2 | 2026-02-25 | Véglegesítés: belső fájlhivatkozások eltávolítása, checkout-szöveg pontosítása (Közös Adományalap), audit megjegyzések beépítése |

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# Chargeback and Dispute Handling Procedure

> **Version**: 1.2  
> **Date**: 2026-02-25  
> **Status**: IN EFFECT  
> **Applies to**: Impact Amplifier (vote purchase) — Stripe Checkout transactions  
> **Related**: Stripe Responsibility Matrix, Impact Challenge vote purchase plan §4.5 D6

---

## 1. Overview

The Impact Amplifier vote purchase is a **donation-type** transaction, subject to business rule D6:

> **D6**: "Donations are non-refundable" — the transaction is by nature a donation, not a product sale.

Nevertheless, the Stripe/card issuer **chargeback** (reversal) mechanism remains technically available to the cardholder. This procedure documents Sharity's obligations in such cases.

---

## 2. Chargeback Timeframes

| Phase | Timeframe | Responsible party |
|-------|-----------|-------------------|
| **Cardholder chargeback filing** | **120 days** from the transaction date (Visa/MC) | User → issuing bank |
| **Stripe notification** | ~1–2 business days after the bank filing | Stripe → webhook: `charge.dispute.created` |
| **Sharity response window** | **7 calendar days** from Stripe notification | Sharity admin |
| **Bank decision** | 30–90 days from evidence submission | Issuing bank |

---

## 3. Chargeback Handling Process

### 3.1 Receiving Notification

1. **Stripe webhook** (`charge.dispute.created`) → the system automatically logs:
   - `wp_impactshop_vote_purchases.status` → `disputed`
   - Guard alert → Discord webhook (operations channel)
   - Email notification → Ops Squad

2. **Manual detection** — if webhook processing fails for any reason:
   - Stripe Dashboard → Payments → Disputes — check daily
   - P2-level incident → see SLA §2

### 3.2 Investigation (≤ 2 business days)

| Check | Where | What we look for |
|-------|-------|-----------------|
| Transaction details | `wp_impactshop_vote_purchases` table | `stripe_session_id`, `amount`, `timestamp`, `pseudo_id` |
| Webhook log | Application logs | Successful checkout callback entry |
| User data | DB + Stripe Dashboard | Email, pseudo_id, IP, Cloudflare Turnstile result |
| Fraud signals | Stripe Radar | Risk score, IP country, card fingerprint |
| Multiple purchases | DB query | Whether multiple purchases originated from the same pseudo_id/email/IP |

### 3.3 Decision

| Situation | Action | Vote handling |
|-----------|--------|--------------|
| **Fraud** — unauthorized card use (not the actual cardholder) | Submit evidence → via Stripe OR accept refund | Votes **revoked** |
| **Friendly fraud** — the actual buyer initiates reversal | Submit evidence (checkout evidence) | Votes **frozen** until decision |
| **Legitimate complaint** — actual system error, double charge | Accept refund | Votes corrected — only the duplicate portion revoked |
| **Unknown / uncertain** | Submit evidence if possible | Votes **frozen** |

### 3.4 Evidence Compilation

The following items must be attached to the Stripe dispute response:

1. **Transaction evidence**
   - Checkout Session screenshot (from Stripe Dashboard)
   - Webhook delivery log (success/timestamp)
   - Post-purchase confirmation (if email was sent)

2. **Evidence of user intent**
   - Cloudflare Turnstile verification = pass → not a bot
   - Checkout text confirming donation nature: _"Donation via Impact Challenge"_
   - Terms & Conditions link on the checkout page

3. **Donation-specific argumentation**
   - The Impact Amplifier vote purchase is a **donation act**, not a product/service purchase
   - Clear disclosure on the checkout page: _"50% of the amount goes to the Common Donation Pool, which is distributed among NGOs in proportion to votes (quarterly per Terms of Service); the user casts the received votes for the selected NGO."_
   - D6 rule: occurs with the donor's prior consent

4. **Submission**: Stripe Dashboard → Disputes → Submit Evidence

### 3.5 Outcome Handling

| Stripe decision | Action |
|-----------------|--------|
| **Won** (in Sharity's favor) | `status` → restore to `completed`; unfreeze votes; closing log entry |
| **Won partially** (partially in Sharity's favor) | `status` → `completed` for the undisputed portion; reduce `votes_granted` in proportion to the refunded amount; partial pool correction (`refunded_amount × 0.5`); log entry for both portions |
| **Lost** (in cardholder's favor) | `status` → `refunded`; permanent Vote Revocation; pool reduction |
| **Accepted** (Sharity does not contest) | `status` → `refunded`; permanent Vote Revocation; pool reduction |

---

## 4. Vote Revocation Procedure

### 4.1 Manual Revocation (WP-CLI)

```bash
wp impactshop vote-purchase void --order_id=<stripe_session_id>
```

This performs the following operations:
- `wp_impactshop_vote_purchases.status` → `voided` / `refunded`
- `wp_impactshop_challenges.total_votes` → decrease by the number of granted votes
- `wp_impactshop_challenges.pool_amount` → decrease by the donation portion (pool_share = amount × 50%)

### 4.2 Pool Correction

- The pool amount decreases by the donation portion (`amount × 0.5`)
- If the quarter has not yet closed: the votes automatically disappear from the vote ratio
- If the quarter **has already closed** and payout was made: a **manual correction** must be performed in the next Quarterly Distribution (negative line item for the affected NGO, **if the votes proportionally influenced the result**)

### 4.3 Logging

Every dispute must be logged:

```
disputes.log:
2026-02-25T14:30:00Z | dispute_id=dp_xxx | session_id=cs_xxx | amount=2000 | decision=evidence_submitted | votes_frozen=2500 | admin=sharity_admin
```

---

## Related Documents and Guides

- [Stripe Responsibility Matrix](./stripe-responsibility-matrix.md)
- [Terms of Service (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Quarterly Distribution Report Template](./quarterly-distribution-report-template.md)
- [SLA](./sla-policy.md)
- [Impact Challenge Guide](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [Impact Amplifier (About Us)](https://app.sharity.hu/rolunk/)

---

## 5. Prevention

### 5.1 Current Protection Layers

| Layer | Description | Configuration |
|-------|-------------|---------------|
| **Cloudflare Turnstile** | Bot/automation filtering before checkout initiation | Before checkout form |
| **Rate limiting** | IP: max 10 checkout/hour; pseudo_id: max 5 checkout/hour | MU plugin + Cloudflare WAF |
| **Stripe Radar** | Built-in fraud scoring, blocking risky transactions | Stripe Dashboard → Radar Rules |
| **3D Secure** | Mandatory SCA (Strong Customer Authentication) in the EU | Stripe automatic |
| **Clear disclosure** | Using the word "Donation" instead of "Purchase" | Checkout UI texts |


---

## 6. Escalation Matrix

| Dispute amount | Decision maker | Escalation |
|----------------|---------------|------------|
| **≤ HUF 5,000** | Ops Squad self-service | None (standard procedure) |
| **HUF 5,001 – 50,000** | Ops Squad + finance officer | Joint decision within 48h |
| **> HUF 50,000** | Management + legal counsel | Immediate escalation, legal opinion requested |
| **Recurring** (>3 disputes/month) | Management | Fraud pattern investigation, Stripe support involvement |

---

## 7. Review

- **Frequency**: quarterly, or after any dispute event
- **Responsible**: Ops Squad
- **Metric**: dispute rate < 0.1% (Stripe requirement: < 0.75%)

---

## Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-02-25 | Initial version — draft |
| 1.1 | 2026-02-25 | "Won partially" outcome added (§3.5) |
| 1.2 | 2026-02-25 | Finalization: internal file references removed, checkout text clarified (Common Donation Pool), audit notes incorporated |

</div>
