# Impact Shop – Útmutató NGO-knak

> Vásárlásból adomány – Minden vásárlás egy jó ügyet erősít

---

## Mi az Impact Shop?

Az Impact Shop egy **jótékonysági vásárlási platform**, ahol a felhasználók a megszokott webshopokban vásárolnak, de az affiliate jutalékból **automatikusan adomány** képződik a választott civil szervezetnek.

```
🛒 Vásárlás a webshopban → 💰 Affiliate jutalék → ❤️ Adomány az NGO-nak
```

**A lényeg:** A vásárlónak nem kerül többe – a kereskedő affiliate jutalékából jön a támogatás.

---

## 🎯 Hogyan működik?

### A felhasználó szemszögéből

1. **Impact Shop megnyitása** – app.sharity.hu/impactshop/
2. **NGO kiválasztása** – „Kit szeretnék támogatni ezzel a vásárlással?"
3. **Bolt kiválasztása** – Több mint 100+ partnerwebshop (Alza, eMAG, Vision Express, stb.)
4. **Vásárlás** – Normál módon, a megszokott árakon
5. **Adomány generálódik** – Automatikusan, háttérben

### Az NGO szemszögéből

- A te támogatóid **innen indítják** a vásárlást
- Minden ilyen vásárlás után **te kapod az adományt**
- **Valós időben** láthatod a beérkező összegeket

---

## 💰 Honnan jön a pénz?

### Az affiliate modell

```
Vásárló → Impact Shop link → Webshop → Vásárlás 
                                         ↓
                              Affiliate jutalék (pl. 5%)
                                         ↓
                              50% Sharity fenntartás
                              50% Adomány az NGO-nak
```

### Példa kalkuláció

| Tétel | Összeg |
|-------|--------|
| Vásárlás értéke | 20.000 Ft |
| Affiliate jutalék (5%) | 1.000 Ft |
| Adomány az NGO-nak (50%) | **500 Ft** |

**Fontos:** A vásárlónak ez 0 Ft-ba kerül – ugyanannyiért vásárol, mint egyébként!

---

## 🏪 Partnerwebshopok

### Kategóriák

| Kategória | Példa boltok |
|-----------|--------------|
| Elektronika | Alza, eMAG, MediaMarkt |
| Divat | About You, H&M, Reserved |
| Egészség | Vision Express, dm, Rossmann |
| Otthon | JYSK, IKEA, Mömax |
| Élelmiszer | Tesco, Auchan, Kifli |
| Sport | Decathlon, Intersport |
| Játék | Játéksziget, Regio Játék |

### Kedvezmények és kuponok

Az Impact Shop-ban **kuponok és akciós ajánlatok** is elérhetők – így a támogatóid kedvezményesen vásárolhatnak, miközben téged támogatnak!

---

## 📊 Mit látsz a dashboardon?

### Valós idejű adatok

- **„Kik támogattak mostanában?"** – Legutóbbi tranzakciók (anonim)
- **Összegyűjtött adomány** – Aktuális hónap + összes idők
- **Webshop statisztikák** – Melyik boltból jön a legtöbb

### Példa megjelenítés

```
ARNI támogatta a(z) [Te szervezeted] ügyet 
42 Ft-tal a(z) Glami vásárlással.
2026-01-27 | PENDING
```

**Státuszok:**
- `PENDING` – Várakozik jóváhagyásra (30-90 nap)
- `APPROVED` – Jóváhagyva, kifizetésre vár
- `REJECTED` – Visszáruzott/törölt rendelés

---

## 🚀 Hogyan szerezz több adományt?

### 1. Személyre szabott link

Minden NGO-nak van egyedi linkje:
```
https://app.sharity.hu/impactshop/?d1=YOURSLUG&ngo=YOURSLUG
```

Ha erről a linkről érkeznek, **automatikusan te vagy kiválasztva** támogatottként!

### 2. Tedd ki az oldaladra

```html
<a href="https://app.sharity.hu/impactshop/?d1=yourslug&ngo=yourslug">
  💚 Vásárolj és támogass – 0 Ft-ból!
</a>
```

### 3. Hírlevél kampány

Ötlet szezonális kampányokhoz:
- „Karácsonyi ajándékod dupla öröm – vásárolj az Impact Shop-ból!"
- „Black Friday: 0 Ft-ból támogatás"
- „Iskolakezdés – vásárolj és segíts!"

### 4. NGO Card beágyazás

Dinamikus kártya, ami mutatja az aktuális adományodat:
```
[impact_ngo_card ngo="yourslug" label="Szervezeted neve"]
```

---

## ⏱️ Kifizetési folyamat

### Miért van várakozási idő?

Az affiliate jutalékok **30-90 nap késéssel** kerülnek jóváhagyásra:
- Visszáru esetén a jutalék törlődik
- Meg kell várni a végső tranzakció megerősítését

### Időszalag

```
Vásárlás → (30-90 nap) → Jóváhagyás → Összesítés → Kifizetés
   ↓                         ↓              ↓           ↓
PENDING                 APPROVED       Hónap vége   Átutalás
```

### Kifizetési ciklus

- **Havi összesítés** minden hónap végén
- **Átutalás** a következő hónap 15-ig
- **Minimum**: nincs minimum – már 1 Ft-tól kifizetjük

---

## 📈 Ranglisták

### Webshop toplista

Melyik bolt generálja a legtöbb adományt?

### NGO toplista

Láthatóság: A top adománygyűjtő szervezetek külön szekciót kapnak.

---

## ❓ Gyakori kérdések

### A támogatóimnak regisztrálniuk kell?
Nem szükséges. De ha van Sharity azonosítójuk:
- Pontokat gyűjtenek
- Szinteket érnek el
- Magasabb szinteken több kedvezményt kapnak

### Mi van, ha a vásárló visszaküldi a terméket?
A visszáruzott rendelés jutaléka törlődik – ebből nem lesz adomány.

### Hogyan követhetem az adományokat?
- Valós időben a dashboardon
- Havi összesítő emailben

### Van minimum vásárlási összeg?
Nincs. Már 1 Ft értékű vásárlástól is képződik adomány (ha van affiliate jutalék).

### Miért van, hogy néhány boltnál nincs adomány?
Nem minden webshop affiliate partner. Csak a rendszerben szereplő boltokból van jutalék.

---

## 🎁 Bónusz: Kuponok és kedvezmények

A támogatóid **extra kedvezményeket** kaphatnak:

| Szint | Kedvezmény |
|-------|------------|
| Basic | 0% |
| Bronze | 2% |
| Silver | 3% |
| Gold | 4% |
| Platinum | 5% |
| Legend | 6% |

**Win-win:** A támogatód olcsóbban vásárol, te több adományt kapsz!

---

## 📞 Kapcsolat és Támogatás

Ha kérdésed van:

- **Email**: office@sharity.hu
- **Válaszidő**: 24 órán belül

---

## 🔗 Hasznos linkek

- [Impact Shop főoldal](https://app.sharity.hu/impactshop/)
- [Impact Activity útmutató](./impact-activity-guide.md)
- [NGO Card beágyazás útmutató](./ngo-card-guide.md)

---

*Verzió: 1.0 | Utolsó frissítés: 2026. február*
