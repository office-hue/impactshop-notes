<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

# Stripe Felelősségmegosztási Mátrix

> **Verzió**: 1.1  
> **Dátum**: 2026-02-25  
> **Státusz**: HATÁLYOS  
> **Vonatkozik**: Impact Amplifier (szavazatvásárlás) — Stripe Checkout integráció  
> **Kapcsolódó**: Vitakezelési eljárásrend (dispute-handling-procedure)

---

## 1. Felek

| Fél | Jogi entitás | Szerep |
|-----|-------------|--------|
| **Sharity** | Sharity Adományszervező Alapítvány + Sharity Mobile Application Zrt. | Kereskedő (merchant) — a Stripe fióktulajdonos |
| **Stripe** | Stripe Payments Europe, Limited (Dublin, Írország) | Fizetési szolgáltató (payment processor) |
| **Felhasználó** | Magánszemély vagy cég | Adományozó — Stripe Checkout-on keresztül fizet |

---

## 2. Felelősségi mátrix

### 2.1 Fizetési folyamat

| Tevékenység | Sharity | Stripe | Megjegyzés |
|-------------|:-------:|:------:|-----------|
| **Checkout Session létrehozása** (REST → Stripe API) | ✅ | — | Szerveroldali API-hívás |
| **Fizetési oldal megjelenítése** (hosted checkout) | — | ✅ | Stripe hosted — kártyaadat nem érinti a Sharity szerverét |
| **Kártyaadatok kezelése** (PCI-DSS) | — | ✅ | Sharity scope: PCI SAQ-A (legkisebb) |
| **3D Secure / SCA** (erős ügyfél-hitelesítés) | — | ✅ | Stripe automatikusan kezeli EU tranzakcióknál |
| **Fraud detection** (Stripe Radar) | — | ✅ | Beépített, automatikus — nincs extra fejlesztés |
| **Webhook fogadása és Signature ellenőrzés** | ✅ | — | `Stripe-Signature` header + webhook secret |
| **Szavazat jóváírás** sikeres fizetés után | ✅ | — | Idempotens webhook handler |
| **Pool növelés** (adományalap) | ✅ | — | Atomic DB increment |
| **Visszaigazolás emailben** | ✅ | — | Sharity email küldés (msmtp / WP Mail) |
| **Adományigazolás** (céges adományozóknak) | ✅ | — | Sharity Alapítvány állítja ki |
| **Többvalutás konverzió** | — | ✅ | Stripe kezeli a settlement-et (settlement currency: HUF) |
| **Payout** (kifizetés Sharity bankszámlára) | — | ✅ | Stripe → Sharity bankszámla (automatikus ütemezés) |

### 2.2 Biztonság

| Terület | Sharity | Stripe | Megjegyzés |
|---------|:-------:|:------:|-----------|
| **API kulcsok biztonságos tárolása** | ✅ | — | Környezeti változókban, verziókezelésből kizárva |
| **Webhook secret rotálás** | ✅ | ✅ | Sharity felelős a rotálás kezdeményezéséért |
| **SSL/TLS** (kommunikáció titkosítás) | ✅ (szerver) | ✅ (API) | HTTPS mindkét oldalon kötelező |
| **PCI-DSS compliance** | ✅ (SAQ-A) | ✅ (Level 1) | Hosted checkout → Sharity scope minimális |
| **Rate limiting** (bot védelem) | ✅ | — | Cloudflare WAF + pseudo_id cap |
| **Cloudflare Turnstile** (CAPTCHA) | ✅ | — | Checkout indítás előtt |
| **IP fraud monitoring** | — | ✅ | Stripe Radar |
| **Account takeover protection** | — | ✅ | Stripe fiók szintű védelem |

### 2.3 Vitás ügyek és visszatérítés

| Tevékenység | Sharity | Stripe | Megjegyzés |
|-------------|:-------:|:------:|-----------|
| **Chargeback értesítés** | — | ✅ | Stripe webhook: `charge.dispute.created` |
| **Chargeback bizonyíték összeállítás** | ✅ | — | Ld. Vitakezelési eljárásrend |
| **Chargeback bizonyíték benyújtás** | ✅ | ✅ | Sharity feltölti → Stripe továbbítja a banknak |
| **Refund kezdeményezés** | ✅ | — | Admin döntés (kivételes, D6: adomány nem visszakövetelhető) |
| **Refund végrehajtás** | — | ✅ | Stripe API: `Refund::create()` |
| **Szavazat visszavonás** refund/void esetén | ✅ | — | Ld. Vitakezelési eljárásrend §4 |

**Visszatérítési kivételek:** Az „adomány nem visszakövetelhető" főszabály (D6) alól kivételt képez a dupla levonás és a bizonyított rendszerhiba. Ezekben az esetekben a Sharity kezdeményezi a visszatérítést a Stripe API-n keresztül. A checkout és az ÁSZF szövegezése egyértelműen tartalmazza a visszatérítési feltételeket, hogy a chargeback-bizonyíték ne tartalmazzon félreérthető állításokat.

### 2.4 Megfelelőség és adatvédelem

| Terület | Sharity | Stripe | Megjegyzés |
|---------|:-------:|:------:|-----------|
| **GDPR — adatkezelő** | ✅ | — | Sharity az adatkezelő |
| **GDPR — adatfeldolgozó** | — | ✅ | Stripe mint adatfeldolgozó (ld. GDPR Adatfeldolgozói nyilvántartás) |
| **DPA (Data Processing Agreement)** | ✅ (aláírás) | ✅ (biztosítás) | Stripe DPA: https://stripe.com/legal/dpa |
| **Tranzakciós adatok megőrzése** | ✅ (DB) | ✅ (Dashboard) | Mindkét fél megőrzi — számviteli kötelezettség: 8 év |
| **Felhasználói adattörlés** (GDPR jog) | ✅ | — | Sharity: soft-delete céges adatoknál; Stripe: retain for compliance |
| **AML / KYC** (pénzmosás elleni ellenőrzés) | — | ✅ | Stripe végzi (Sharity nem kezel kártyaadatot) |

---

## 3. Stripe szerződési feltételek hivatkozások

| Dokumentum | URL | Vonatkozik |
|-----------|-----|-----------|
| **Stripe Services Agreement** | https://stripe.com/legal/ssa | Általános szolgáltatási feltételek |
| **Stripe Privacy Policy** | https://stripe.com/privacy | Adatvédelmi szabályzat |
| **Stripe Data Processing Agreement (DPA)** | https://stripe.com/legal/dpa | GDPR adatfeldolgozói megállapodás |
| **Stripe Checkout Documentation** | https://docs.stripe.com/payments/checkout | Checkout Session API referencia |
| **Stripe Disputes & Fraud** | https://docs.stripe.com/disputes | Chargeback kezelés dokumentáció |
| **Stripe PCI Compliance** | https://stripe.com/guides/pci-compliance | PCI-DSS szintek és SAQ-A |
| **Stripe Zero-Decimal Currencies** | https://docs.stripe.com/currencies#zero-decimal | HUF/CZK kezelés |

---

## 4. Kapcsolattartás

| Ügy | Ki intézi | Hogyan |
|-----|-----------|--------|
| Stripe fiók beállítások | Sharity admin | Stripe Dashboard → Settings |
| Webhook konfiguráció | Sharity fejlesztő | Stripe Dashboard → Developers → Webhooks |
| Stripe support (technikai) | Sharity admin | Stripe Dashboard → Support (email/chat) |
| Chargeback válasz | Sharity admin | Stripe Dashboard → Payments → Disputes |
| Payout beállítás | Sharity pénzügy | Stripe Dashboard → Balance → Payouts |

---

## 5. Felülvizsgálat

- **Gyakoriság**: félévente, vagy Stripe feltételek változásakor
- **Felelős**: Ops Squad + pénzügyi vezető
- **Mikor kötelező frissíteni**: Stripe SSA frissítésekor, új valuták hozzáadásakor, PCI audit utáni megállapítás

---

## Változásnapló

| Verzió | Dátum | Változás |
|--------|-------|---------|
| 1.0 | 2026-02-25 | Kezdeti verzió — draft |
| 1.1 | 2026-02-25 | Véglegesítés: belső fájlhivatkozások eltávolítása, audit megjegyzés beépítése, visszatérítési kivételek rögzítése |

---

## Kapcsolódó dokumentumok és guide-ok

- [Vitakezelési eljárásrend](./dispute-handling-procedure.md)
- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [GDPR adatfeldolgozók](./gdpr-data-processors.md)
- [Hozzáférés-kezelési mátrix](./access-control-matrix.md)
- [Impact Challenge útmutató](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [Impact Amplifier (Rólunk)](https://app.sharity.hu/rolunk/)

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# Stripe Responsibility Matrix

> **Version**: 1.1  
> **Date**: 2026-02-25  
> **Status**: IN EFFECT  
> **Applies to**: Impact Amplifier (vote purchase) — Stripe Checkout integration  
> **Related**: Dispute Handling Procedure (dispute-handling-procedure)

---

## 1. Parties

| Party | Legal entity | Role |
|-------|-------------|------|
| **Sharity** | Sharity Donation Organizing Foundation + Sharity Mobile Application Zrt. | Merchant — the Stripe account owner |
| **Stripe** | Stripe Payments Europe, Limited (Dublin, Ireland) | Payment processor |
| **User** | Natural person or company | Donor — pays via Stripe Checkout |

---

## 2. Responsibility Matrix

### 2.1 Payment Flow

| Activity | Sharity | Stripe | Notes |
|----------|:-------:|:------:|-------|
| **Checkout Session creation** (REST → Stripe API) | ✅ | — | Server-side API call |
| **Payment page rendering** (hosted checkout) | — | ✅ | Stripe hosted — card data does not touch the Sharity server |
| **Card data handling** (PCI-DSS) | — | ✅ | Sharity scope: PCI SAQ-A (minimum) |
| **3D Secure / SCA** (Strong Customer Authentication) | — | ✅ | Stripe handles automatically for EU transactions |
| **Fraud detection** (Stripe Radar) | — | ✅ | Built-in, automatic — no extra development required |
| **Webhook reception and Signature verification** | ✅ | — | `Stripe-Signature` header + webhook secret |
| **Vote crediting** after successful payment | ✅ | — | Idempotent webhook handler |
| **Pool increment** (donation pool) | ✅ | — | Atomic DB increment |
| **Confirmation email** | ✅ | — | Sharity email sending (msmtp / WP Mail) |
| **Donation certificate** (for corporate donors) | ✅ | — | Issued by the Sharity Foundation |
| **Multi-currency conversion** | — | ✅ | Stripe handles settlement (settlement currency: HUF) |
| **Payout** (disbursement to Sharity bank account) | — | ✅ | Stripe → Sharity bank account (automatic schedule) |

### 2.2 Security

| Area | Sharity | Stripe | Notes |
|------|:-------:|:------:|-------|
| **Secure storage of API keys** | ✅ | — | In environment variables, excluded from version control |
| **Webhook secret rotation** | ✅ | ✅ | Sharity is responsible for initiating rotation |
| **SSL/TLS** (communication encryption) | ✅ (server) | ✅ (API) | HTTPS mandatory on both sides |
| **PCI-DSS compliance** | ✅ (SAQ-A) | ✅ (Level 1) | Hosted checkout → Sharity scope is minimal |
| **Rate limiting** (bot protection) | ✅ | — | Cloudflare WAF + pseudo_id cap |
| **Cloudflare Turnstile** (CAPTCHA) | ✅ | — | Before checkout initiation |
| **IP fraud monitoring** | — | ✅ | Stripe Radar |
| **Account takeover protection** | — | ✅ | Stripe account-level protection |

### 2.3 Disputes and Refunds

| Activity | Sharity | Stripe | Notes |
|----------|:-------:|:------:|-------|
| **Chargeback notification** | — | ✅ | Stripe webhook: `charge.dispute.created` |
| **Chargeback evidence compilation** | ✅ | — | See Dispute Handling Procedure |
| **Chargeback evidence submission** | ✅ | ✅ | Sharity uploads → Stripe forwards to the bank |
| **Refund initiation** | ✅ | — | Admin decision (exceptional, D6: donations are non-refundable) |
| **Refund execution** | — | ✅ | Stripe API: `Refund::create()` |
| **Vote revocation** on refund/void | ✅ | — | See Dispute Handling Procedure §4 |

**Refund exceptions:** The general rule of "donations are non-refundable" (D6) is subject to exceptions in the case of double charges and verified system errors. In such cases, Sharity initiates the refund via the Stripe API. The checkout and GTC wording clearly set out the refund conditions so that the chargeback evidence does not contain ambiguous statements.

### 2.4 Compliance and Data Protection

| Area | Sharity | Stripe | Notes |
|------|:-------:|:------:|-------|
| **GDPR — data controller** | ✅ | — | Sharity is the data controller |
| **GDPR — data processor** | — | ✅ | Stripe as data processor (see GDPR Data Processor Registry) |
| **DPA (Data Processing Agreement)** | ✅ (signing) | ✅ (provision) | Stripe DPA: https://stripe.com/legal/dpa |
| **Transaction data retention** | ✅ (DB) | ✅ (Dashboard) | Both parties retain — accounting obligation: 8 years |
| **User data deletion** (GDPR right) | ✅ | — | Sharity: soft-delete for corporate data; Stripe: retain for compliance |
| **AML / KYC** (anti-money laundering checks) | — | ✅ | Stripe performs (Sharity does not handle card data) |

---

## 3. Stripe Contractual Terms References

| Document | URL | Applies to |
|----------|-----|-----------|
| **Stripe Services Agreement** | https://stripe.com/legal/ssa | General service terms |
| **Stripe Privacy Policy** | https://stripe.com/privacy | Privacy policy |
| **Stripe Data Processing Agreement (DPA)** | https://stripe.com/legal/dpa | GDPR data processing agreement |
| **Stripe Checkout Documentation** | https://docs.stripe.com/payments/checkout | Checkout Session API reference |
| **Stripe Disputes & Fraud** | https://docs.stripe.com/disputes | Chargeback handling documentation |
| **Stripe PCI Compliance** | https://stripe.com/guides/pci-compliance | PCI-DSS levels and SAQ-A |
| **Stripe Zero-Decimal Currencies** | https://docs.stripe.com/currencies#zero-decimal | HUF/CZK handling |

---

## 4. Contact Information

| Matter | Handled by | How |
|--------|-----------|-----|
| Stripe account settings | Sharity admin | Stripe Dashboard → Settings |
| Webhook configuration | Sharity developer | Stripe Dashboard → Developers → Webhooks |
| Stripe support (technical) | Sharity admin | Stripe Dashboard → Support (email/chat) |
| Chargeback response | Sharity admin | Stripe Dashboard → Payments → Disputes |
| Payout settings | Sharity finance | Stripe Dashboard → Balance → Payouts |

---

## 5. Review

- **Frequency**: semi-annually, or upon changes to Stripe terms
- **Responsible**: Ops Squad + CFO
- **Mandatory update triggers**: Stripe SSA update, addition of new currencies, post-PCI audit findings

---

## Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-02-25 | Initial version — draft |
| 1.1 | 2026-02-25 | Finalization: removal of internal file references, audit note integration, refund exception documentation |

---

## Related Documents and Guides

- [Dispute Handling Procedure](./dispute-handling-procedure.md)
- [General Terms and Conditions (GTC)](../ÁSZF/Sharity_ASZF_2026.md)
- [GDPR Data Processors](./gdpr-data-processors.md)
- [Access Control Matrix](./access-control-matrix.md)
- [Impact Challenge Guide](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [Impact Amplifier (About Us)](https://app.sharity.hu/rolunk/)

</div>
