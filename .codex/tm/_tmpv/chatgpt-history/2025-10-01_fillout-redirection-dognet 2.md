# ChatGPT beszélgetés - Fillout + WordPress Redirection + Dognet integráció
**Dátum**: 2025-10-01 (folytatás)
**Cél**: Komplett affiliate webshop rendszer technikai implementációja
**Status**: Sikeres - működő megoldás több webshopra

## Probléma leírása
A korábbi Impact Shop fejlesztés folytatása:
- Fillout űrlap → NGO választás → WordPress átirányítás → Dognet affiliate linkek
- Több webshop egységes kezelése (Árukereső, Decathlon, 4home, Allegro, Vision Express, REGIO Játék, Sparkl)
- Data1 paraméter átadása NGO azonosításához

## ChatGPT megoldása

### Működő technikai flow:
1. **Webshop logó** → Fillout űrlap (shop paraméterrel)
2. **NGO választás** → Fillout redirect vissza Sharity /go endpointra
3. **WordPress Redirection** → Shopnként külön szabály → Dognet affiliate link
4. **Dognet tracking** → d1 paraméterben NGO kód

### Fillout beállítás (1 űrlap minden shophoz):
- **URL**: `https://form.fillout.com/t/eM61RLkz6jus?shop={shop_név}`
- **Hidden mező**: shop (URL paraméterből)
- **NGO mező**: Custom values (pl. bator-tabor-alapitvany)
- **Redirect**: `https://app.sharity.hu/go` (paraméter mappeléssel)

### WordPress Redirection (shoponként 1 szabály):
```
Source: ^/go\?shop={shop}&d1=([^&]+)$
Target: https://go.dognet.com/?cid={CID}&chid=KVirfJde&refid=67246ee77827f&url={ENCODED_URL}&d1=$1
```

### Sikeres megvalósítás - webshopok:

| Shop | CID | Logó Link | Redirection Target |
|------|-----|-----------|-------------------|
| Árukereső | 897 | `?shop=arukereso` | `url=https%3A%2F%2Fwww.arukereso.hu%2F` |
| Decathlon | 5191 | `?shop=decathlon` | `url=https%3A%2F%2Fwww.decathlon.hu%2F` |
| 4home | 4319 | `?shop=4home` | `url=https%3A%2F%2Fwww.4home.hu%2F` |
| Allegro | 5385 | `?shop=allegro` | `url=https%3A%2F%2Fallegro.hu%2F` |
| Vision Express | 223 | `?shop=visionexpress` | `url=https%3A%2F%2Fwww.visionexpress.hu%2F` |
| REGIO Játék | 357 | `?shop=regiojatek` | `url=https%3A%2F%2Fwww.regiojatek.hu%2F` |
| Sparkl | 249 | `?shop=sparkl` | `url=https%3A%2F%2Fwww.sparkl.hu%2F` |

## Tesztelés eredménye
✅ **Működik**:
- 1 Fillout űrlap → dinamikus shop paraméterrel
- WordPress Redirection shopnként külön szabály
- Dognet d1 paraméter → NGO kód átadása
- Kattintás tracking Dognet riportokban

❌ **Problémák voltak**:
- Kezdetben `data1` helyett `d1` paramétert kellett használni
- Fillout paraméter duplázás (inline + mappelés együtt)
- Shop hidden mező URL paraméter binding
- Regex beállítások WordPress Redirectionben

## Következő lépések
1. További webshopok hozzáadása ugyanezzel a sablonnal
2. NGO kódok egységesítése (URL-barát nevek)
3. Analytics és konverzió mérés beállítása
4. Skálázás több partner bevonásával

## Kapcsolódó fájlok
- [x] Fillout űrlap: `eM61RLkz6jus` (dinamikus shop paraméterrel)
- [x] WordPress Redirection szabályok (7 webshop)
- [x] Dognet kampány linkek (CID-kkal, d1 paraméterrel)

## GitHub Copilot notes
- **Sikeresen megoldott**: komplex multi-platform integráció
- **Kulcs technika**: 1 űrlap + shop paraméter + shoponként regex szabály
- **Mérés**: Dognet d1 paraméterben NGO kód riportáláshoz
- **Skálázható**: új webshop = új CID + URL + Redirection szabály
- **Következő**: további partnerek bevonása, konverzió tracking