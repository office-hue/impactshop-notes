# ChatGPT Beszélgetés - Dognet API Integráció

**Dátum**: 2025. október 1.  
**Téma**: Dognet hálózat és WordPress közötti API kapcsolat  
**Kimenet**: WordPress bővítmény Dognet PAP Publisher API-hoz  

## Beszélgetés Összefoglalása

### Kiindulási Pont
A felhasználó **API kapcsolatot szeretett volna** a Dognet affiliate hálózat és WordPress weboldal között, hivatkozva három dokumentációs linkre:
1. Dognet API access dokumentáció
2. QualityUnit banner API leírás  
3. Dognet tracking integráció útmutató

### Kulcsfontosságú Tisztázás: Merchant vs. Publisher

#### Dognet 2.0 Merchant API (NEM NEKÜNK)
- **Célcsoport**: Hirdetők (merchants)
- **Technológia**: REST API, Bearer token authentikáció
- **Funkciók**: Tranzakció kezelés, státusz frissítés, jutalék újraszámítás
- **URL**: `https://api.app.dognet.com/api/v1`
- **Megjegyzés**: A felhasználó által megosztott dokumentáció **merchant API volt**, nem publisher

#### PAP API (Publisher/Affiliate)
- **Célcsoport**: Kiadók (publishers/affiliates) - MI VAGYUNK EZ
- **Technológia**: Post Affiliate Pro (PAP) alacsony szintű API
- **Fájl**: `PapApi.class.php` - hivatalos QualityUnit kliens
- **URL**: `https://login.dognet.sk/scripts/server.php`
- **Funkciók**: Banner lekérés, link generálás, SubID paraméterezés

### Technikai Implementáció

#### WordPress Bővítmény: Dognet PAP Publisher Connector
```php
Plugin Name: Dognet PAP Publisher Connector
Description: Publisher integráció Dognet/PAP API-hoz
Version: 0.3.0
```

#### Főbb Funkciók
1. **PAP Session Management**
   - Automatikus bejelentkezés affiliate fiókkal
   - Session kezelés és hibakezelés
   - Token/session újrafelhasználás

2. **Banner Lekérés**
   ```php
   [dognet_banners types="I,T" limit="8" d1="NGO123" d2="AMB1" chan="FB2025" read_query="1"]
   ```

3. **Link Generálás**
   ```php
   [dognet_link text="Megnézem" url="https://merchant.hu/termek" d1="NGO123" d2="AMB1" chan="FB2025"]
   ```

4. **Automatikus Paraméter Beolvasás**
   - URL query paraméterekből: `?ngo=...&amb=...&chan=...`
   - Konfigurálandó paraméter nevek: `d1,data1,ngo,ngocode`

#### SubID és Channel Paraméterek
- **data1**: NGO kód (Impact Shop alapkövetelmény)
- **data2**: Ambassador/csatorna azonosító
- **chan**: Ad channel kód (Dognet 2.0 kötelező követelmény 2025. július 1-től)

### Dognet 2.0 Változások

#### Új Követelmények (2025. július 1.)
- **Csatorna használat kötelező**: Kifizetésekhez jutalékok ≥90%-ának "új ad channeles linkekből" kell származnia
- **Chan paraméter**: `chan=CHANNEL_CODE` kötelező minden linkben
- **Rate Limiting**: 240 req/min authenticated API hívások esetén

#### API Váltás Ütemezése
- **2025. október 1.**: Dognet 2.0 lesz az elsődleges admin
- **Publisher API**: PAP továbbra is támogatott és hivatalos
- **Merchant API**: REST-re váltás (Bearer token)

### Telepítési Kihívás: WordPress.com Limitációk

#### Probléma Azonosítása
- **Felhasználó helyzete**: `sharity.hu` WordPress.com-on fut, nincs saját tárhely
- **WordPress.com korlátozás**: Alap/prémium csomagokban nincs egyedi plugin telepítés
- **Fájlhozzáférés**: Nincs wp-content/plugins/ mappa elérés

#### Megoldási Opciók
1. **WordPress.com Business csomag**: Egyedi plugin telepítés engedélyezett
2. **Saját tárhely**: Átköltözés independent hosting szolgáltatóhoz
3. **Alternatív integráció**: WordPress.com engedélyezett pluginokkal

#### Technikai Blokkolók
- `PapApi.class.php` fájl nem másolható be `includes/` mappába
- ZIP feltöltés sikeres volt, de fájlrendszer hozzáférés nincs
- WordPress.com architectural limitations

### Impact Shop Integráció Kontextus

#### Paraméter Mapping
```php
// URL példa
?ngo=bator-tabor-alapitvany&amb=kovacs-anna&chan=FB2025

// Dognet link output
https://dognet.tracking.link/?data1=bator-tabor-alapitvany&data2=kovacs-anna&chan=FB2025
```

#### Kompatibilitás
- **Meglévő Fillout → WordPress → Dognet folyamat**: Változatlan
- **NGO kód továbbítás**: `d1` paraméter megmarad
- **Ambassador tracking**: `data2` paraméterrel bővül
- **Channel compliance**: `chan` paraméterrel Dognet 2.0 ready

### Kód Architektúra

#### Admin Interface
```php
// Beállítások oldal
add_options_page('Dognet PAP (Publisher)', 'Dognet PAP (Publisher)', 'manage_options', 'dognet-pap-publisher');

// Konfiguráció
'base_url' => 'https://login.dognet.sk'  // PAP szerver
'username' => ''  // Affiliate email
'password' => ''  // Affiliate jelszó
'q_data1'  => 'd1,data1,ngo,ngocode'    // Query param precedence
```

#### Cache és Optimalizálás
```php
// Transient cache bannerekhez
$cache_key = 'dognet_pap_banners_'.md5(json_encode($atts));
set_transient($cache_key, $output, HOUR_IN_SECONDS);
```

#### Hibaelhárítás
- PAP API fájl hiánya ellenőrzés
- Session login failure kezelés
- Cache fallback stratégia
- User capability validation

### Következő Lépések

#### Hosting Megoldás Szükséges
1. **WordPress.com Business upgrade** VAGY
2. **Saját tárhely szolgáltató** (RackForest, NetMasters, Tárhely.eu)
3. **WordPress migráció** független környezetbe

#### Plugin Aktiválás Utáni Teendők
1. `PapApi.class.php` bemásolása includes/ mappába
2. Affiliate belépési adatok konfigurálása
3. Ad channel kódok beállítása Dognet panelben
4. Shortcode tesztelés különböző paraméterekkel

#### Integráció Tesztelés
```php
// Teszt banner megjelenítés
[dognet_banners types="I,T" limit="6" chan="TEST2025" read_query="1"]

// URL tesztelés
https://sharity.hu/test-page?ngo=NGO123&amb=AMB1&chan=FB2025
```

## Technikai Jelentőség

### API Stratégia Pontosítás
Ez a beszélgetés **tisztázta az API integration irányát**:
- **NEM** Dognet 2.0 REST (merchant API)
- **IGEN** PAP Publisher API (affiliate funkcionalitáshoz)
- **Megtartjuk** a meglévő affiliate struktúrát
- **Bővítjük** channel tracking-gel (Dognet 2.0 compliance)

### WordPress.com Limitáció Felismerés
**Kritikus blocker azonosítás**:
- Saját plugin telepítés nem lehetséges alapcsomagban
- Fájlrendszer hozzáférés hiánya
- Business csomag vagy hosting váltás szükségessége

### Impact Shop Kompatibilitás
**Teljes backward compatibility**:
- Meglévő `/go/{shop}?d1={ngo}` linkek változatlanok
- Fillout űrlap integráció érintetlen  
- Dognet tracking továbbra is működik
- **Plusz**: Channel tracking readiness Dognet 2.0-hoz

Ez a beszélgetés **megalapozna** a teljes Dognet API integrációt, ha a hosting limitációk megoldódnának.