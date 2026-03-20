# Unified Display Plan – Véglegesített terv

> Verzió: 2026-01-30 | Státusz: FINAL | Tulaj: Sharity / ImpactShop

---

## 1. Cél és scope

Egy **egységes kijelző komponens** (Unified Display), amely többféle tartalomtípust rotál **backend‑vezérelt logikával**. Cél: egységes UX, egységes mérés, és bővíthetőség új tartalomtípusokkal.

**Tartalomtípusok (kanonikus enum):**

| content_type | Forrás | CTA pont | Megjegyzés |
|---|---|---:|---|
| `auto_banner` | Harvester → DB | +1 | Affiliate ajánlatok automatikus bannerei |
| `ads` | IMA SDK (Google, stb.) | +1 | Programmatic video hirdetések |
| `youtube_sponsor` | YouTube IFrame API | +5 | Szponzor/brand videók |
| `youtube_edu` | YouTube IFrame API | +1 | Edukációs videók |
| `external_embed` | Jövőbeli integráció | +1 | Harmadik fél embed |

**Legacy kompatibilitás:**
- `ad → ads`
- `sponsor → youtube_sponsor`
- `education → youtube_edu`

---

## 2. Véglegesített döntések (a korábbi javaslatokból)

1. **Egységes content_type enum:** a kanonikus típusokkal, régi típusok mappinggel támogatva.
2. **CTA pont forrása:** **`cta.points` a kanonikus mező**. A `reward_points` átmeneti (visszafelé kompatibilis) mező, később kivezethető.
3. **Kép fallback:** auto‑banner esetén logo‑registry + placeholder. Fájlrendszer check **nem** fut requestenként; cache‑elt mapping lesz.
4. **Ár/discount fallback:** regex‑parse csak **importkor** fut, nem runtime. 0% esetek logolása finomhangoláshoz.
5. **Batch prefetch:** a kliens kérhet `batch_size=3`‑at, de a default `1` marad.
6. **Completion definíciók típusonként:** ads: IMA COMPLETE, youtube: 75% watch, auto_banner: min. 5s láthatóság.
7. **Dedupe + rate limit:** napi dedupe kulcs + per‑óra limit (pseudo_id + IP).

---

## 3. Végleges response séma

```
GET /wp-json/impact/v1/ads-watch/next
```

```json
{
  "content_type": "auto_banner|ads|youtube_sponsor|youtube_edu|external_embed",
  "content_id": "banner_12345",
  "title": "Nike Air Max – 40% kedvezmény",
  "media": {
    "type": "image|video|youtube",
    "url": "https://...",
    "youtube_id": "dQw4w9WgXcQ"
  },
  "cta": {
    "url": "https://shop.example.com/product?ref=impact",
    "label": "Megnézem",
    "points": 5
  },
  "brand": {
    "name": "Nike",
    "logo_url": "https://..."
  },
  "tracking": {
    "dedupe_key": "cta_click:banner_12345:{pseudo_id}:2026-01-30"
  },
  "ttl_seconds": 1800,
  "reward_points": 5
}
```

**Megjegyzés:** `reward_points` csak átmeneti. A kliens **elsődlegesen** `cta.points`‑ot használ.

---

## 4. Tartalom kiválasztás (backend)

**Alapelv:** rotáció + dedupe + availability.

- **Rotáció súlyok (alap):**
  - ads: 60%
  - auto_banner: 20%
  - youtube_sponsor: 15%
  - youtube_edu: 5%

- **Dedupe:** ugyanaz a content_id **4 órán belül** nem ismétlődik.
- **Fallback:** ha nincs elérhető tartalom, „house” content (ImpactShop edukáció).

**Implementációs vázlat:**
```
selectNext(pseudo_id):
  seen = cache_get(pseudo_id, ttl=4h)
  available = loadAvailableContent(exclude=seen)
  weightedPick(available, weights)
```

---

## 5. CTA pont logika

**Szabályok:**
- `youtube_sponsor` CTA: +5 pont
- minden más CTA: +1 pont

**Dedupe:** napi kulcs
`cta_click:{content_id}:{pseudo_id}:{YYYY-MM-DD}`

**Rate limit:**
- pseudo_id: max 30 CTA / óra
- IP: max 120 CTA / óra

---

## 6. Megjelenítés (frontend)

**Egységes UI követelmények:**
- Egyetlen frame, de **3 renderer**:
  - IMA (ads)
  - HTML5 video / image (auto_banner)
  - YouTube IFrame API (youtube_*)
- **CTA mindig látható**, a videó alatt.
- **Progress bar és %** (JYSK‑szerű, smooth növekedés).
- **Miért kell végignézni?** info ikon és magyarázat.

---

## 7. Kép‑ és árfallback (A/B)

### 7.1 Kép fallback
1) banner.image_url
2) shop logo registry (cache‑elt mapping)
3) default placeholder

### 7.2 Ár / kedvezmény fallback
- Ha nincs strukturált ár, **importkor** regex‑parse:
  - “50% kedvezmény”
  - “10.000 Ft helyett 5.000 Ft”
- 0% esetek logolása finomhangoláshoz.

---

## 8. Mérés és GA4

**Eventek:**
- `content_impression`
- `content_view_complete`
- `cta_click`

**Completion threshold:**
- ads: IMA COMPLETE
- youtube: 75% watch
- auto_banner: 5s view

---

## 9. Implementációs roadmap

| Fázis | Tartalom |
|---|---|
| 0.5 | DB + cache előkészítés (auto_banner image/price fallback) |
| 1 | Endpoint séma egységesítés + mapping + CTA pont | 
| 2 | UI: unified player + progress + CTA always visible |
| 3 | YouTube IFrame API + state tracking |
| 4 | GA4 + monitoring | 

---

## 10. Koherencia vizsgálat (meglévő rendszerrel)

**Kompatibilis elemek:**
- `/ads-watch/next` endpoint továbbra is a fő belépő.
- Auto‑banner táblák és harvest logika változatlanok.
- Pseudo‑ID logika nem változik (cookie + backend).

**Potenciális ütközések és megoldásuk:**
- **Régi content_type enum** → explicit mapping (ad/sponsor/education).
- **Kliens `reward_points` mezőre támaszkodás** → `cta.points` a fő, de `reward_points` még visszafelé támogatott.
- **File‑I/O runtime** → cache + import‑time fallback, runtime nem olvas fájlrendszert.

**Eredmény:** nincs ütközés a működő rendszerrel; a terv kompatibilis és fokozatosan bevezethető.

---

## 11. Elfogadási kritériumok

- Unified player egyetlen frame‑ben rotálja a 4+ content typet.
- CTA pontok helyesen számolódnak (+1 / +5) dedupe + rate limit mellett.
- Progress bar és “Miért kell végignézni?” megjelenik.
- GA4 események érkeznek.
- Fallback kép/ár működik és nem lassítja a runtime‑ot.

