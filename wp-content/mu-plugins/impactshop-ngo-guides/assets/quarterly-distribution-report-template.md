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
- `adomány-szorzó` = Basic ×1,00 – Legend ×1,25 *(ÁSZF §4.1.2(c))*

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
