# NGO Card – Útmutató szervezeteknek

> Beágyazható widget a saját oldaladra – mutasd meg élőben, mennyi támogatást gyűjtöttél!

---

## Mi az NGO Card?

Az NGO Card egy **beágyazható kártya/widget**, amit a saját weboldaladon helyezhetsz el. Automatikusan mutatja:

- A szervezeted nevét és logóját
- Az eddig gyűjtött adomány összegét (élőben frissül!)
- Egy CTA gombot, ami az Impact Shop-ba visz

```
┌────────────────────────────────────────┐
│  🖼️ Logo                               │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Szervezeted neve                      │
│                                        │
│  💰 Eddig gyűjtött: 127.450 Ft        │
│                                        │
│  [Vásárolok és támogatok]             │
└────────────────────────────────────────┘
```

---

## 🎯 Miért jó ez neked?

### ✅ Láthatóság
A látogatóid rögtön látják, hogy a Sharity-n keresztül támogathatnak

### ✅ Transzparencia
Élő adat – a támogatóid látják, mennyi gyűlt össze

### ✅ Motiváció
A növekvő szám további adományozásra ösztönöz

### ✅ Egyszerűség
Egyetlen kódrészlet beillesztése – semmi több!

---

## 🛠️ Beágyazás – Lépésről lépésre

### 1. Kód beszerzése

Másold be ezt a kódot a weboldaladra:

```html
<div class="sharity-ngo-card-embed"
     data-ngo="YOURSLUG"
     data-label="Szervezeted Neve"
     data-img="https://yoursite.hu/logo.png"
     data-text="Támogass vásárlással – neked nem kerül többe!"
     data-href="https://app.sharity.hu/impactshop"
     data-accent="#7c3aed"
     data-currency="HUF"
     data-rate-huf="392.5"
     data-from="2025-01-01"
     data-to=""
     data-status="all"
     data-cta="Vásárolok és támogatok"
></div>
<script src="https://app.sharity.hu/embed/ngo-card.js"></script>
```

### 2. Paraméterek testreszabása

| Paraméter | Leírás | Példa |
|-----------|--------|-------|
| `data-ngo` | A te egyedi slug-od | `bator-tabor` |
| `data-label` | Megjelenített név | `Bátor Tábor Alapítvány` |
| `data-img` | Logo URL | `https://example.hu/logo.png` |
| `data-text` | Rövid leírás | `Támogass vásárlással!` |
| `data-href` | Céloldal (hova vigyen a gomb) | `https://app.sharity.hu/impactshop` |
| `data-accent` | Színkód (hex) | `#7c3aed` |
| `data-cta` | Gomb felirata | `Támogatom` |
| `data-from` | Kezdő dátum az összesítéshez | `2025-01-01` |
| `data-to` | Záró dátum (üres = ma) | `` |
| `data-status` | Státusz szűrő | `all`, `approved`, `pending` |

### 3. Beillesztés

A kódot a HTML-be kell beillesztened oda, ahol meg szeretnéd jeleníteni a kártyát:
- WordPress: HTML blokk vagy shortcode
- Wix/Squarespace: Egyedi HTML elem
- Bármilyen weboldal: `<body>` tag-en belül

---

## 🎨 Dizájn variációk

### Sötét téma
```html
data-accent="#1a1a2e"
```

### Narancssárga akcentus
```html
data-accent="#ff6b35"
```

### Zöld (környezetvédelem)
```html
data-accent="#2ecc71"
```

---

## 📊 WordPress Shortcode

Ha WordPress oldalad van, használhatsz shortcode-ot is:

```
[impact_ngo_card 
  ngo="bator-tabor" 
  label="Bátor Tábor Alapítvány"  
  from="2025-01-01" 
  to="" 
  status="all" 
  rate_huf="392.5" 
  currency="HUF" 
  accent="#7c3aed"
]
```

### Shortcode paraméterek

| Paraméter | Leírás | Alapértelmezett |
|-----------|--------|-----------------|
| `ngo` | Slug (kötelező) | - |
| `label` | Megjelenített név | slug alapján |
| `from` | Kezdő dátum | hónap eleje |
| `to` | Záró dátum | ma |
| `status` | approved/pending/all | approved |
| `rate_huf` | EUR→HUF árfolyam | 392 |
| `currency` | HUF vagy EUR | HUF |
| `accent` | Szín | #7c3aed |
| `refresh` | Frissítési idő (mp) | 45 |

---

## 📱 Reszponzív megjelenés

A kártya automatikusan alkalmazkodik a képernyőmérethez:

| Szélesség | Megjelenés |
|-----------|------------|
| 320px+ | Kompakt mobil nézet |
| 768px+ | Tablet nézet |
| 1024px+ | Teljes desktop nézet |

---

## 🔄 Adatfrissítés

A kártya **automatikusan frissíti** az adatokat:
- Alapértelmezés: 45 másodpercenként
- Az élő összeg azonnal látszik, ha új adomány érkezik

---

## 🎫 Apple Wallet Pass

Az NGO Card **Apple Wallet kártyaként** is elérhető!

### Mit tartalmaz?

- Szervezeted logója és neve
- QR kód az Impact Shop linkkel
- Hátlap: összesített adomány, Sharity hírek

### Hogyan szerezd meg?

1. Kérd el tőlünk az egyedi Wallet pass-t
2. Küldd el a támogatóidnak
3. Ők hozzáadják a Wallet apphoz
4. A kártyáról indított vásárlások automatikusan téged támogatnak

---

## 🔗 Személyre szabott linkek

### Alap Impact Shop link
```
https://app.sharity.hu/impactshop/?d1=YOURSLUG&ngo=YOURSLUG
```

### UTM paraméterekkel (követéshez)
```
https://app.sharity.hu/impactshop/?d1=YOURSLUG&ngo=YOURSLUG&utm_source=website&utm_medium=ngo-card&utm_campaign=2026-februar
```

### Konkrét bolthoz
```
https://app.sharity.hu/impactshop/?d1=YOURSLUG&ngo=YOURSLUG&shop=alza
```

---

## ❓ Gyakori kérdések

### Honnan tudom a slug-omat?
A Sharity regisztrációkor kapod meg, vagy kérdezd tőlünk: office@sharity.hu

### A kártya lassítja az oldalamat?
Minimálisan – a script aszinkron töltődik és optimalizált.

### Mi van, ha nincs még adomány?
A kártya „0 Ft" értéket mutat – de ez nem baj, a lényeg, hogy látható!

### Hogyan változtatom a színt?
A `data-accent` paraméterben adj meg bármilyen hex színkódot.

### Működik mobil nézetben?
Igen, teljesen reszponzív.

### Több kártyát is tehetek egy oldalra?
Igen, de mindegyiknek egyedi `data-ngo` értéke legyen.

---

## 🎯 Best Practices

### DO ✅
- Tedd jól látható helyre (főoldal, adomány oldal)
- Használj kontrasztos színeket
- Adj hozzá rövid, CTA-jellegű szöveget
- Oszd meg social mediában is

### DON'T ❌
- Ne rejtsd el láblécbe – senki nem fogja észrevenni
- Ne használj túl világos színeket sötét háttéren
- Ne hagyj ékezettelen/hibás nevet

---

## 📞 Kapcsolat és Támogatás

Ha segítség kell a beágyazáshoz:

- **Email**: office@sharity.hu
- **Válaszidő**: 24 órán belül
- **Technikai konzultáció**: igény esetén videóhívás

---

## 🔗 Kapcsolódó útmutatók

- [Impact Activity útmutató](./impact-activity-guide.md)
- [Impact Shop útmutató](./impact-shop-guide.md)

---

*Verzió: 1.0 | Utolsó frissítés: 2026. február*
