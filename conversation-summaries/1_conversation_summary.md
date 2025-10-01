# 1. Beszélgetés Összefoglaló - Impact Shop Alapozás

**Dátum**: Induló beszélgetés  
**Téma**: Sharity Impact Shop technikai tervezés és no-code megvalósítás  
**Státusz**: ✅ Befejezett

## 📋 Főbb témák

### 1. Impact Shopping koncepció kidolgozása
- **Cél**: Affiliate marketing webshop integráció civil szervezetek támogatásával
- **Mechanizmus**: Vásárlás → jutalék → 80% adomány NGO-nak
- **Platformok**: WordPress/Elementor alapú megoldás

### 2. Technikai architektúra kialakítása

#### A) Alapvető rendszer elemei:
- **Sharity WordPress oldal**: partner ikonok megjelenítése
- **Tally űrlap**: NGO választó felület
- **WordPress Redirection plugin**: URL átirányítás kezelés
- **Dognet affiliate hálózat**: partner tracking és jutalék

#### B) Adatfolyam:
1. User kattint webshop ikonra → Tally űrlap
2. NGO kiválasztás → átirányítás /go/{shop} linkre
3. Redirection plugin → Dognet affiliate link
4. Webshop → vásárlás → jutalék → NGO adomány

### 3. No-code vs fejlesztési megközelítés
- **Döntés**: No-code eszközök használata (gyorsaság, karbantarthatóság)
- **Indoklás**: Gyors MVP, egyszerű módosíthatóság, költséghatékonyság

### 4. Paraméter kezelési rendszer
- **NGO kód**: Minden szervezethez egyedi azonosító (pl. mbe, adamremenye)
- **Nagykövet kód**: amb paraméter követési célokra
- **UTM tracking**: Részletes analytics és forgalom követés

## 🔧 Technikai megvalósítás

### WordPress Redirection Plugin Setup
- **Forrás URL**: /go/{shop} (pl. /go/emag)
- **Cél URL**: Dognet deeplink formátum
- **Beállítások**:
  - ✅ Paraméterek továbbadása
  - ❌ Paraméterek figyelmen kívül hagyása
  - 302 átirányítási kód (affiliate linkekhez ajánlott)

### Tally űrlap konfiguráció
- **Kérdés**: "Melyik ügyet szeretnéd támogatni?"
- **Conditional logic**: Minden NGO → egyedi kód hozzárendelés
- **Rejtett mezők**: shop, amb (URL paraméterekből)
- **Redirect**: https://app.sharity.hu/go/@shop?d1=@ngo_code&amb=@amb...

### 20+ Partner integráció
Redirection szabályok JSON importálással:
- Árukereső, Decathlon, 4home, Pepita, EMAG
- Electronic Star, Klarstein, Zooplus, Nike
- AliExpress, Wolt, és további partnerek

## 🔄 Folyamat fejlődése

### Kezdeti Tally megoldás problémái:
- Kézi NGO hozzáadás minden új szervezetnél
- Sok száz NGO esetén nehézkes karbantartás
- Skálázhatósági korlátok

### Fillout-ra váltás (beszélgetés vége):
- **Előnyök**: 
  - Google Sheets/Airtable integráció
  - Custom values funkció
  - Dinamikus opció betöltés
  - URL paraméter pre-fill
- **Beállítás**:
  - NGO dropdown: Custom values (név + kód)
  - Rejtett shop mező: URL paraméterből
  - Redirect: /go/{shop}?d1={ngo_code}...

### CSV adatkezelés
- **TablePress WordPress plugin**: CSV import és megjelenítés
- **Excel képletek**: Automatikus összegzések
- **Visszajelzés**: Sikeres import üzenetek
- **Frissítés**: Rendszeres CSV feltöltés a jutalék adatokkal

## 📊 UX/UI fejlesztési tervek

### Kategorizálás szükségessége:
- Webshop ikonok rendszerezése kategóriákba
- Elektronika, Sport, Egészség, Otthon, Divat stb.
- Keresőmező és szűrők
- Mobil responsive design

### Felhasználói élmény javítása:
- Átlátható CTA gombok
- Folyamat indikátor
- Élő adomány számlálók
- Kategóriánkénti teljesítmény megjelenítés

## 🎯 Eredmények és következő lépések

### Működő alaprendszer:
✅ Redirection plugin konfigurálva 35+ partnerrel  
✅ Dognet integráció paraméter továbbítással  
✅ Fillout űrlap NGO kód kezeléssel  
✅ CSV import rendszer TablePress-szel  

### Jövőbeli fejlesztések:
- 🔄 Kategóriás webshop rendszerezés
- 📊 Dinamikus adomány statisztikák  
- 🎨 UX/UI fejlesztések
- 📈 Automatizált riportolás
- 🤝 Több száz NGO integráció

## 💡 Kulcs tanulságok

1. **No-code előnyei**: Gyors MVP, könnyen módosítható
2. **Paraméteres tracking**: Átlátható jutalék követés
3. **Plugin választás**: Redirection vs Pretty Links
4. **Škálázhatóság**: Tally → Fillout váltás szükségessége
5. **Adatkezelés**: CSV alapú frissítési rendszer

## 🔗 Kapcsolódó fájlok
- `redirection_rules.json` - WordPress import fájl
- `ngo_codes.csv` - Egységes NGO azonosítók
- `sharity_ngo_master.xlsx` - Szervezetek adatbázis

---

**Összefoglalás készítés dátuma**: 2025. január 30.  
**Feldolgozott beszélgetés hossza**: 4536 sor  
**Következő lépés**: 2. beszélgetés feldolgozása