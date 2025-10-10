# 13. Beszélgetés - Összefoglaló

**Dátum:** 2025 szeptember 21.
**Témák:** Affiliate hijacking detektálás és védelem, Dognet Publisher API integráció, WordPress plugin fejlesztés

## Főbb témák és megoldások

### 1. Affiliate Hijacking Problémafeltárás
- **Kontextus:** Az Adjukössze plugin és hasonló kiegészítők felülírják a Sharity affiliate linkeket
- **Problémák:**
  - Az NGO kódok (data1 paraméter) elvesznek vagy lecserélődnek
  - A nyereményjáték részvétel meghiúsul
  - A bizalmi problémák a felhasználókkal
- **Cél:** Automatikus detektálás és védelem implementálása

### 2. Dognet Publisher API Integráció

#### Főbb végpontok és működés:
- **Authentication:** `/auth/login` 
  - 24 órás token generálás (használattal hosszabbodik)
  - Dognet timezone megkapása (pl. Europe/Bratislava)
  - Bearer token használata minden további híváshoz

- **Link generálás:** `/campaigns/links/generate`
  - Deeplink próbálkozás először
  - Base link fallback ha deeplink nem engedett
  - data1 paraméter (NGO kód) beágyazása

- **Kattintás ellenőrzés:** `/clicks/filter`
  - Numerikus ad_channel_id alapú szűrés
  - Dognet időzóna szerinti időablak használata
  - data1 paraméter megléte/tartalma vizsgálata

### 3. WordPress Plugin Fejlesztés

#### Első verzió - Teljes funkciójú plugin:
- AJAX-alapú admin felület nem-blokkoló működéssel
- Token cache rendszer (20 óra TTL)
- Polling mechanizmus kattintás ellenőrzéshez
- Frontend shortcode figyelmeztetéshez
- Deeplink → base fallback logika

#### LITE verzió:
- Egyszerű, fagyásbiztos megközelítés
- Manuális lépésenkénti tesztelés
- Azonnali állapot visszajelzés
- Időzóna-kezelés javítása

#### Ping diagnosztika:
- Redirect lánc végigkövetése
- HTTP státusz kódok monitorozása
- WAF/AdBlock detektálás
- Szerveroldali vs. böngészős viselkedés elemzése

### 4. Anti-Hijack Védelem

#### Kliens oldali védelem:
- JavaScript-alapú real-time link monitoring
- CHID értékek ellenőrzése
- MutationObserver DOM változások figyelésére
- Kattintás blokkolása gyanús esetekben

#### Működési elvek:
- Deeplink nem szükséges a védelemhez
- Base linkek ugyanúgy működnek
- go.dognet.com host és CHID paraméter alapú detektálás
- Automatikus figyelmeztető sáv megjelenítése

## Technikai Implementáció

### Backend WordPress Plugin Struktúra:
```php
class Sharity_Aff_Check_Lite_TZ {
    // Token cache és auth kezelés
    private function auth(&$o) // 24h token + timezone
    
    // Link generálás deeplink/base fallback
    private function gen_link($token, $ad_channel_id, $campaign_id, $url, $data1)
    
    // Kattintások szűrése Dognet TZ-ben
    private function filter_clicks($token, $ad_channel_id, $from, $to, $per)
    
    // Ping diagnosztika redirect követéssel
    public function handle_ping()
}
```

### Frontend Anti-Hijack JavaScript:
```javascript
// CHID ellenőrzés kattintáskor
document.addEventListener("click", function(e){
    var a = e.target.closest("a[href]");
    if (!a) return;
    checkAnchor(a, e); // Dognet link és CHID validáció
}, true);

// DOM változások figyelése
var obs = new MutationObserver(function(muts){
    // href attribútum változások detektálása
});
```

## Fő Eredmények és Tanulságok

### Időzóna Kezelés:
- Dognet API Europe/Bratislava időzónát használ
- Auth válaszból kinyert timezone használata kötelező
- UTC helyett Dognet TZ alkalmazása a clicks/filter lekérdezésekhez

### WAF és Blokkolás Kezelés:
- Szerveroldali lekérdezések gyakran 403-at kapnak
- Ping diagnosztika != valódi böngészős kattintás
- AdBlock/Tracking Protection kikapcsolása szükséges teszthez

### Approved Kampányok:
- `/campaigns/mine/filter` végpont használata
- ad_channel_in_campaign_status = 1 szűrő
- Campaign approval ellenőrzése kötelező

### Védelem Stratégia:
- Kliens oldali védelem azonnali
- Szerveroldali verifikáció bizonyítékokkal
- Deeplink nem szükséges a hijack detektáláshoz
- Base linkek teljes funkcionalitással

## Gyakorlati Alkalmazás

### Teszt Forgatókönyv:
1. **Happy Path:** Saját CHID → nincs figyelmeztetés
2. **Hijack Szimuláció:** CHID módosítás konzolból → figyelmeztetés
3. **DOM Változás:** Késleltetett átírás → MutationObserver detektálás
4. **Nem Dognet:** Host csere → azonnali blokkolás

### Üzembe Helyezés:
- Anti-hijack plugin aktiválása
- CHID beállítása admin felületen
- `[sharity_aff_guard]` shortcode elhelyezése
- Kattintás blokkolás opcionális bekapcsolása

## Következő Lépések
- Automatikus szerveroldali riasztási rendszer
- Modal/Toast figyelmeztető opciók
- Approved kampányok listázó funkció
- A/B teszt deeplink vs. base teljesítményre

---

**Technikai referencia:** Dognet Publisher API dokumentáció szerint működik minden végpont (auth/login, campaigns/links/generate, clicks/filter) Bearer token hitelesítéssel és 24 órás token érvényességgel.