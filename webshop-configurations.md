# Webshop Partner Konfigurációk

## Fillout Űrlap
**Form ID**: `eM61RLkz6jus`
**Alap URL**: `https://form.fillout.com/t/eM61RLkz6jus?shop={shop_név}`

## WordPress Redirection Template
```
Source URL (Regex ON): ^/go\?shop={shop}&d1=([^&]+)$
Target URL: https://go.dognet.com/?cid={CID}&chid=KVirfJde&refid=67246ee77827f&url={ENCODED_URL}&d1=$1
HTTP kód: 302 Temporary
Query Parameters: Exact
```

## Webshop Konfigurációk

### Árukereső
- **CID**: 897
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso`
- **Source**: `^/go\?shop=arukereso&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&d1=$1`

### Decathlon
- **CID**: 5191
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon`
- **Source**: `^/go\?shop=decathlon&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=5191&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.decathlon.hu%2F&d1=$1`

### 4home
- **CID**: 4319
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=4home`
- **Source**: `^/go\?shop=4home&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=4319&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.4home.hu%2F&d1=$1`

### Allegro
- **CID**: 5385
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=allegro`
- **Source**: `^/go\?shop=allegro&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=5385&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fallegro.hu%2F&d1=$1`

### Vision Express
- **CID**: 223
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=visionexpress`
- **Source**: `^/go\?shop=visionexpress&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=223&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.visionexpress.hu%2F&d1=$1`

### REGIO Játék
- **CID**: 357
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=regiojatek`
- **Source**: `^/go\?shop=regiojatek&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=357&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.regiojatek.hu%2F&d1=$1`

### Sparkl
- **CID**: 249
- **Logó link**: `https://form.fillout.com/t/eM61RLkz6jus?shop=sparkl`
- **Source**: `^/go\?shop=sparkl&d1=([^&]+)$`
- **Target**: `https://go.dognet.com/?cid=249&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.sparkl.hu%2F&d1=$1`

## Új Webshop Hozzáadása

1. **Dognet kampány link** megszerzése (CID, URL)
2. **WordPress Redirection** új szabály:
   - Source: `^/go\?shop={új_shop}&d1=([^&]+)$`
   - Target: Dognet link template kitöltése
3. **Weboldal logó** linkjének beállítása: `?shop={új_shop}`

## Tesztelési Checklist

- [ ] Logó kattintás → Fillout form megnyílik helyes shop paraméterrel
- [ ] NGO választás → redirect `/go?shop=...&d1=...` formátumban
- [ ] WordPress Redirection → megfelelő Dognet linkre irányít
- [ ] Dognet riportban → d1 paraméterben NGO kód megjelenik