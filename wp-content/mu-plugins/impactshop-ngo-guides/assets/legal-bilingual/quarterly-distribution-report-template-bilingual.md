<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

# Negyedéves Elosztási Riport — Sablon

> **Verzió**: 1.2  
> **Dátum**: 2026-02-25  
> **Státusz**: HATÁLYOS  
> **Vonatkozik**: Impact Shop közvetlen adomány + Impact Challenge/Amplifier Közös Adományalap — negyedéves NGO elosztás  
> **Adatforrás**: WordPress adatbázis (negyedéves elosztási táblák), Dognet ledger

---

## Sablon használati útmutató

Ez a sablon szolgál a negyedéves adományelosztási riport elkészítéséhez. Minden negyedév zárása után ki kell tölteni a megfelelő adatokkal, és az alábbi jóváhagyási folyamaton kell átesnie.

**Kitöltési lépések:**
1. Exportáld a negyedéves adatokat a WP adatbázisból (ld. §A1 — Lekérdezések)
2. Töltsd ki az alábbi sablont
3. Ellenőrizd az összesítő számokat
4. Küldd el jóváhagyásra
5. Véglegesítés után → NGO értesítés + payout indítás

---

# 📊 Negyedéves Elosztási Riport

## Alapadatok

| Mező | Érték |
|------|-------|
| **Riport azonosító** | `QDR-YYYY-QN` (pl. QDR-2026-Q1) |
| **Negyedév** | YYYY Q_ (pl. 2026 Q1) |
| **Időszak** | YYYY-MM-DD – YYYY-MM-DD |
| **Riport készítő** | [Név] |
| **Készítés dátuma** | YYYY-MM-DD |
| **Jóváhagyó** | [Név] |
| **Jóváhagyás dátuma** | YYYY-MM-DD |

---

## 1. Bevételi összesítő

### 1.1 Affiliate jutalék (Dognet, CJ stb.)

> **Megjegyzés**: A Direct Partner jutalék elosztása NGO szintfüggő (Base: 40%, Rising: 50%, Legend: 60%), ezért az itt feltüntetett összegek tranzakciónként eltérő arányokból adódnak össze. Az összesítő sorban az összes tranzakció után ténylegesen NGO-ra jutó összeg kerül.

| Tétel | Összeg (HUF) |
|-------|-------------|
| **Bruttó affiliate jutalék** (Dognet ledger) | _______ |
| **Platform díj** (fennmaradó rész — NGO szinttől függ: Base: 60%, Rising: 50%, Legend: 40%) | _______ |
| **NGO-knak közvetlenül jutó összeg** (szinttől függően 40–60% + user adomány-szorzó hatása, súlyozott átlag) | _______ |

> 💡 **Megjegyzés a riport kitöltőjének**: A Direct Partner tranzakcióknál az NGO szintjétől függően 40% (Base), 50% (Rising) vagy 60% (Legend) kerül adományba. Az affiliate hálózaton (Dognet) keresztül érkező tranzakcióknál az arány az affiliate megállapodás feltételeitől függ. Az összesítő sorba a ténylegesen NGO-ra jutó összeget kell beírni, a platform díj sorba a maradékot. *(Vö. ÁSZF §4.1.2(b) és §8.1.)*

### 1.2 Impact Amplifier (szavazatvásárlás)

| Tétel | Összeg (HUF) |
|-------|-------------|
| **Összes szavazatvásárlás bevétel** | _______ |
| **Platform díj** (50%) | _______ |
| **Adományalapba kerülő összeg** (50%) | _______ |

### 1.3 Összesített adománypool

| Forrás | Összeg (HUF) |
|--------|-------------|
| Affiliate jutalék NGO része | _______ |
| Impact Amplifier adomány része | _______ |
| **Összes elosztható pool** | **_______** |

---

## 2. Szavazati összesítő

| Tétel | Darab |
|-------|-------|
| **Összes leadott szavazat** (negyedévben) | _______ |
| Ebből affiliate-ból + Impact Challenge aktivitásból szerzett (ingyenes) | _______ |
| Ebből Impact Amplifier vásárlásból | _______ |
| **Érvényes szavazatok** (dispute-ok levonása után) | _______ |

### 2.1 Dispute korrekció

| Dispute típus | Darab | Érintett szavazat | Pool korrekció (HUF) |
|--------------|-------|-------------------|---------------------|
| Elfogadott chargeback | _______ | _______ | -_______ |
| Voided tranzakció | _______ | _______ | -_______ |
| **Összes korrekció** | _______ | _______ | **-_______** |

---

## 3. NGO elosztás — részletes táblázat

| # | NGO név | NGO slug | Impact Shop közvetlen adomány (HUF) | Impact Challenge/Amplifier szavazatok (db) | Szavazat arány (%) | Közös Alap elosztás (HUF) | Összes elosztott (HUF) |
|---|---------|----------|-----------------------------------|--------------------------------------------|-------------------|---------------------------|------------------------|
| 1 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 2 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 3 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 4 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 5 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| ... | ... | ... | ... | ... | ... | ... | ... |
| **Σ** | **ÖSSZESEN** | — | **_______** | **_______** | **100%** | **_______** | **_______** |

### 3.1 Elosztási képlet

**Impact Challenge/Amplifier Közös Alap elosztás:**
```
ngo_common_pool_share = (ngo_votes / total_valid_votes) × total_pool_amount
```

**Impact Shop közvetlen adomány:**
```
ngo_direct_donation = Σ (tranzakciós jutalék × NGO szint arány × adomány-szorzó)
```

**Teljes NGO elosztás:**
```
ngo_total = ngo_direct_donation + ngo_common_pool_share
```

Ahol:
- `ngo_votes` = az adott NGO-ra leadott érvényes szavazatok száma a negyedévben
- `total_valid_votes` = összes érvényes szavazat (dispute korrekció utáni)
- `total_pool_amount` = Közös Adományalap (Impact Amplifier 50% + Impact Challenge hirdetési bevétel egy része, korrekciók levonása után)
- `NGO szint arány` = Base: 40%, Rising: 50%, Legend: 60% *(ÁSZF §4.1.2(b))*
- `adomány-szorzó` = Basic ×1.00 – Legend ×1.25 *(ÁSZF §4.1.2(c))*

---

## 4. Payout státusz

| NGO | Összeg (HUF) | Payout módszer | Payout dátum | Státusz |
|-----|-------------|---------------|-------------|---------|
| ________________ | _______ | Banki átutalás | YYYY-MM-DD | ⬜ Függőben / ✅ Elküldve |
| ________________ | _______ | Banki átutalás | YYYY-MM-DD | ⬜ Függőben / ✅ Elküldve |
| ... | ... | ... | ... | ... |


---

## 5. Ellenőrzés és egyeztetés

### 5.1 Adatforrás egyeztetés

| Egyeztetés | Eredmény |
|-----------|---------|
| DB szavazatok összege = Stripe tranzakciók összege ± dispute korrekciók | ✅ / ❌ Eltérés: _______ |
| Dognet ledger egyenleg = DB affiliate bevétel | ✅ / ❌ Eltérés: _______ |
| NGO elosztás összege = Pool összeg | ✅ / ❌ Eltérés: _______ |

### 5.2 Anomáliák

| Anomália | Leírás | Akció |
|---------|--------|-------|
| (Ha van) | ________________ | ________________ |
| — | Nincs anomália | — |

---

## 6. Jóváhagyás

| Szerep | Név | Aláírás/Dátum |
|--------|-----|--------------|
| **Riport készítő** | ________________ | ________________ |
| **Pénzügyi ellenőr** | ________________ | ________________ |
| **Vezetőség** | ________________ | ________________ |

---

## Melléklet

### A1. Adatlekérdezések (WP-CLI / SQL)

**Negyedéves szavazat összesítő:**
```sql
SELECT 
    c.slug AS ngo_slug,
    c.name AS ngo_name,
    SUM(vp.votes_granted) AS total_votes,
    SUM(vp.amount) AS total_amount
FROM wp_impactshop_vote_purchases vp
JOIN wp_impactshop_challenges c ON vp.challenge_id = c.id
WHERE vp.status = 'completed'
  AND vp.created_at BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD'
GROUP BY c.slug, c.name
ORDER BY total_votes DESC;
```

**Affiliate bevétel negyedéves összesítő:**
```sql
SELECT 
    quarter_name,
    total_revenue,
    ngo_share,
    platform_share,
    status
FROM wp_impactshop_ads_quarters
WHERE quarter_name = 'YYYY-QN';
```

**Részletes NGO elosztás:**
```sql
SELECT 
    qr.ngo_slug,
    qr.ngo_name,
    qr.vote_count,
    qr.vote_percentage,
    qr.payout_amount,
    qr.payout_status
FROM wp_impactshop_ads_quarter_results qr
WHERE qr.quarter_id = (
    SELECT id FROM wp_impactshop_ads_quarters WHERE quarter_name = 'YYYY-QN'
)
ORDER BY qr.vote_count DESC;
```

### A2. Dispute lista (negyedév)

```sql
SELECT 
    vp.stripe_session_id,
    vp.amount,
    vp.votes_granted,
    vp.status,
    vp.updated_at
FROM wp_impactshop_vote_purchases vp
WHERE vp.status IN ('disputed', 'voided', 'refunded')
  AND vp.created_at BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD';
```

### A3. Riport exportálás

- **Markdown** → ez a dokumentum kitöltve
- **PDF** → Pandoc konverzió (markdown → PDF)
- **Archiválás** → negyedéves archívum mappába

---

## Felülvizsgálat

- **Gyakoriság**: minden negyedév zárása után (Q1: április 15-ig, Q2: július 15-ig, Q3: október 15-ig, Q4: január 15-ig)
- **Felelős**: Pénzügyi felelős + Ops Squad
- **Határidő**: negyedév záró napja + 15 munkanap

---

## Változásnapló

| Verzió | Dátum | Változás |
|--------|-------|---------|
| 1.0 | 2026-02-25 | Kezdeti sablon |
| 1.1 | 2026-02-25 | Jutalék felosztás (40/50/60%) pontosítás + minimum (5 000 Ft/csatorna) „elvész" szabály rögzítése |
| 1.2 | 2026-02-25 | Véglegesítés: 3-csatornás NGO elosztási tábla (Impact Shop közvetlen + Közös Alap szavazatarány), elosztási képlet kibővítése, belső fájlhivatkozások eltávolítva, audit megjegyzések beépítve, publikus guide koherencia rögzítve |

---

## Kapcsolódó dokumentumok és guide-ok

- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Vitakezelési eljárásrend](./dispute-handling-procedure.md)
- [SLA](./sla-policy.md)
- [Impact Shop útmutató](https://app.sharity.hu/ngo-guides/impact-shop/)
- [Impact Challenge útmutató](https://app.sharity.hu/ngo-guides/impact-challenge/)

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# Quarterly Distribution Report — Template

> **Version**: 1.2  
> **Date**: 2026-02-25  
> **Status**: IN EFFECT  
> **Applies to**: Impact Shop direct donation + Impact Challenge/Amplifier Common Donation Pool — quarterly NGO distribution  
> **Data source**: WordPress database (quarterly distribution tables), Dognet ledger

---

## Template Usage Guide

This template is used to prepare the Quarterly Distribution report. After the close of each quarter, it must be filled in with the relevant data and undergo the approval process described below.

**Steps to complete:**
1. Export the quarterly data from the WP database (see §A1 — Queries)
2. Fill in the template below
3. Verify the summary figures
4. Submit for approval
5. After finalization → NGO notification + payout initiation

---

# 📊 Quarterly Distribution Report

## Basic Information

| Field | Value |
|-------|-------|
| **Report identifier** | `QDR-YYYY-QN` (e.g. QDR-2026-Q1) |
| **Quarter** | YYYY Q_ (e.g. 2026 Q1) |
| **Period** | YYYY-MM-DD – YYYY-MM-DD |
| **Report prepared by** | [Name] |
| **Preparation date** | YYYY-MM-DD |
| **Approved by** | [Name] |
| **Approval date** | YYYY-MM-DD |

---

## 1. Revenue Summary

### 1.1 Affiliate Commission (Dognet, CJ, etc.)

> **Note**: Direct Partner commission distribution is NGO tier-dependent (Base: 40%, Rising: 50%, Legend: 60%); therefore the amounts shown here are the sum of varying ratios per transaction. The summary row contains the total amount actually allocated to NGOs across all transactions.

| Item | Amount (HUF) |
|------|-------------|
| **Gross affiliate commission** (Dognet ledger) | _______ |
| **Platform fee** (remaining portion — depends on NGO tier: Base: 60%, Rising: 50%, Legend: 40%) | _______ |
| **Amount directly allocated to NGOs** (40–60% depending on tier + user donation multiplier effect, weighted average) | _______ |

> 💡 **Note for the report preparer**: For Direct Partner transactions, 40% (Base), 50% (Rising), or 60% (Legend) goes to donation depending on the NGO tier. For transactions coming through the affiliate network (Dognet), the ratio depends on the affiliate agreement terms. The summary row should contain the amount actually allocated to NGOs; the platform fee row should contain the remainder. *(Cf. Terms of Service §4.1.2(b) and §8.1.)*

### 1.2 Impact Amplifier (vote purchase)

| Item | Amount (HUF) |
|------|-------------|
| **Total vote purchase revenue** | _______ |
| **Platform fee** (50%) | _______ |
| **Amount allocated to donation pool** (50%) | _______ |

### 1.3 Consolidated Donation Pool

| Source | Amount (HUF) |
|--------|-------------|
| Affiliate commission NGO portion | _______ |
| Impact Amplifier donation portion | _______ |
| **Total distributable pool** | **_______** |

---

## 2. Vote Summary

| Item | Count |
|------|-------|
| **Total votes cast** (in the quarter) | _______ |
| Of which earned from affiliate + Impact Challenge activity (free) | _______ |
| Of which from Impact Amplifier purchase | _______ |
| **Valid votes** (after dispute deductions) | _______ |

### 2.1 Dispute Correction

| Dispute type | Count | Affected votes | Pool correction (HUF) |
|-------------|-------|---------------|----------------------|
| Accepted chargeback | _______ | _______ | -_______ |
| Voided transaction | _______ | _______ | -_______ |
| **Total corrections** | _______ | _______ | **-_______** |

---

## 3. NGO Distribution — Detailed Table

| # | NGO name | NGO slug | Impact Shop direct donation (HUF) | Impact Challenge/Amplifier votes (count) | Vote share (%) | Common Pool distribution (HUF) | Total distributed (HUF) |
|---|----------|----------|----------------------------------|------------------------------------------|----------------|-------------------------------|------------------------|
| 1 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 2 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 3 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 4 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| 5 | ________________ | ________ | _______ | _______ | _____% | _______ | _______ |
| ... | ... | ... | ... | ... | ... | ... | ... |
| **Σ** | **TOTAL** | — | **_______** | **_______** | **100%** | **_______** | **_______** |

### 3.1 Distribution Formula

**Impact Challenge/Amplifier Common Donation Pool distribution:**
```
ngo_common_pool_share = (ngo_votes / total_valid_votes) × total_pool_amount
```

**Impact Shop direct donation:**
```
ngo_direct_donation = Σ (transaction_commission × NGO_tier_ratio × donation_multiplier)
```

**Total NGO distribution:**
```
ngo_total = ngo_direct_donation + ngo_common_pool_share
```

Where:
- `ngo_votes` = the number of valid votes cast for the given NGO in the quarter
- `total_valid_votes` = total valid votes (after dispute correction)
- `total_pool_amount` = Common Donation Pool (Impact Amplifier 50% + a portion of Impact Challenge advertising revenue, after corrections)
- `NGO tier ratio` = Base: 40%, Rising: 50%, Legend: 60% *(Terms of Service §4.1.2(b))*
- `donation multiplier` = Basic ×1.00 – Legend ×1.25 *(Terms of Service §4.1.2(c))*

---

## 4. Payout Status

| NGO | Amount (HUF) | Payout method | Payout date | Status |
|-----|-------------|--------------|------------|--------|
| ________________ | _______ | Bank transfer | YYYY-MM-DD | ⬜ Pending / ✅ Sent |
| ________________ | _______ | Bank transfer | YYYY-MM-DD | ⬜ Pending / ✅ Sent |
| ... | ... | ... | ... | ... |


---

## 5. Verification and Reconciliation

### 5.1 Data Source Reconciliation

| Reconciliation | Result |
|---------------|--------|
| DB vote totals = Stripe transaction totals ± dispute corrections | ✅ / ❌ Discrepancy: _______ |
| Dognet ledger balance = DB affiliate revenue | ✅ / ❌ Discrepancy: _______ |
| NGO distribution total = Pool total | ✅ / ❌ Discrepancy: _______ |

### 5.2 Anomalies

| Anomaly | Description | Action |
|---------|------------|--------|
| (If any) | ________________ | ________________ |
| — | No anomalies | — |

---

## 6. Approval

| Role | Name | Signature/Date |
|------|------|---------------|
| **Report preparer** | ________________ | ________________ |
| **Financial auditor** | ________________ | ________________ |
| **Management** | ________________ | ________________ |

---

## Appendix

### A1. Data Queries (WP-CLI / SQL)

**Quarterly vote summary:**
```sql
SELECT 
    c.slug AS ngo_slug,
    c.name AS ngo_name,
    SUM(vp.votes_granted) AS total_votes,
    SUM(vp.amount) AS total_amount
FROM wp_impactshop_vote_purchases vp
JOIN wp_impactshop_challenges c ON vp.challenge_id = c.id
WHERE vp.status = 'completed'
  AND vp.created_at BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD'
GROUP BY c.slug, c.name
ORDER BY total_votes DESC;
```

**Affiliate revenue quarterly summary:**
```sql
SELECT 
    quarter_name,
    total_revenue,
    ngo_share,
    platform_share,
    status
FROM wp_impactshop_ads_quarters
WHERE quarter_name = 'YYYY-QN';
```

**Detailed NGO distribution:**
```sql
SELECT 
    qr.ngo_slug,
    qr.ngo_name,
    qr.vote_count,
    qr.vote_percentage,
    qr.payout_amount,
    qr.payout_status
FROM wp_impactshop_ads_quarter_results qr
WHERE qr.quarter_id = (
    SELECT id FROM wp_impactshop_ads_quarters WHERE quarter_name = 'YYYY-QN'
)
ORDER BY qr.vote_count DESC;
```

### A2. Dispute List (quarter)

```sql
SELECT 
    vp.stripe_session_id,
    vp.amount,
    vp.votes_granted,
    vp.status,
    vp.updated_at
FROM wp_impactshop_vote_purchases vp
WHERE vp.status IN ('disputed', 'voided', 'refunded')
  AND vp.created_at BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD';
```

### A3. Report Export

- **Markdown** → this document filled in
- **PDF** → Pandoc conversion (markdown → PDF)
- **Archiving** → quarterly archive folder

---

## Review

- **Frequency**: after the close of each quarter (Q1: by April 15, Q2: by July 15, Q3: by October 15, Q4: by January 15)
- **Responsible**: Finance officer + Ops Squad
- **Deadline**: quarter end date + 15 business days

---

## Changelog

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-02-25 | Initial template |
| 1.1 | 2026-02-25 | Commission split (40/50/60%) clarification + minimum (HUF 5,000/channel) "forfeited" rule recorded |
| 1.2 | 2026-02-25 | Finalization: 3-channel NGO distribution table (Impact Shop direct + Common Pool vote ratio), distribution formula expanded, internal file references removed, audit notes incorporated, public guide coherence established |

---

## Related Documents and Guides

- [Terms of Service (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Dispute Handling Procedure](./dispute-handling-procedure.md)
- [SLA](./sla-policy.md)
- [Impact Shop Guide](https://app.sharity.hu/ngo-guides/impact-shop/)
- [Impact Challenge Guide](https://app.sharity.hu/ngo-guides/impact-challenge/)

</div>
