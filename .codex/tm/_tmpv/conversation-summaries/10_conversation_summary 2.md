# 10. Beszélgetés Összefoglaló - Apps Script Időtúllépés Optimalizáció

**Dátum:** 2024. (ChatGPT conversation)  
**Téma:** Impact Shop Apps Script robusztus újraírása időtúllépések és parser hibák kezelésére

## Fő Problémák és Megoldások

### 1. XML Feed Időtúllépések
**Probléma:** Több merchant (4home, Decathlon, Árukereső) feedje túl lassú vagy túl nagy, Apps Script 6 perc timeout.

**Megoldás - Patrol System (Őrjárat rendszer):**
- **SHOPS_PER_RUN = 10**: Maximum 10 bolt/futás
- **MAX_RUN_MS = 220000**: 220 másodperc/futás (6 perc alatt)
- **Incremental Processing**: PropertiesService kurzorral `PS_KEY_CURSOR`
- **Preflight Checks**: HEAD/Range kérések feed health ellenőrzésre

### 2. XML Parser Robusztusság
**Problémák:**
- Nagy CDATA blokkok (Decathlon)
- Namespace problémák
- DTD/Entity hibák
- Malformed XML struktúra

**Megoldás - Defensive Parsing:**
```javascript
// XML tisztítás + óriási szövegek visszavágása
function _sanitizeXml(xml) {
  xml = xml.replace(/<!DOCTYPE[\s\S]*?\[[\s\S]*?\]>/gi,'');
  xml = xml.replace(/<!ENTITY[\s\S]*?>/gi,'');
  return xml;
}

function _clampHugeText(xml) {
  // 50KB limit CDATA blokkokra
  xml = xml.replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, (m,body) => 
    body.length > 50000 ? ('<![CDATA[' + body.slice(0,50000) + ']]>') : m
  );
  return xml;
}
```

### 3. Fallback Parsing Strategy
**Két szintű parsing:**
1. **XML Service parsing** element budget-tel (MAX_ITEMS_SCAN = 1200)
2. **Regex-based fallback** (`_parseOneLenient`) namespace-agnostic

**Namespace-független keresés:**
```javascript
function _pickTextNSDeep(el, names, nodeBudget) {
  const targets = names.map(x => String(x).toLowerCase());
  const q = [el]; 
  let seen = 0;
  
  while(q.length && seen < nodeBudget) {
    const cur = q.shift(); 
    seen++;
    const local = String(cur.getName() || '').toLowerCase();
    if(targets.indexOf(local) !== -1) {
      const t = (cur.getText() || '').trim(); 
      if(t) return t;
    }
    // Continue deep traversal...
  }
}
```

### 4. WordPress Integration Fixes
**Base64 URL Decoding Fix:**
```php
// Legacy Dognet fallback javítás
$u_param = $_GET['u'] ?? '';
if ($u_param) {
    $product_url = base64_decode($u_param); // FIX: base64_decode hozzáadva
    if ($product_url && filter_var($product_url, FILTER_VALIDATE_URL)) {
        // Continue with Dognet API call...
    }
}
```

**Pretty URL Support:**
- `/go/{slug}` rewrite rules
- Enhanced URL handling for better UX

## Technikai Architektúra

### Apps Script Patrol Flow
1. **Cursor-based Iteration**: PropertiesService state management
2. **Time-boxed Execution**: Strict time limits per shop/run
3. **Preflight Health Checks**: HEAD requests before full download
4. **Graceful Degradation**: Fallback to regex parsing if XML fails
5. **Atomic Updates**: TMP sheet → main sheet transfer

### Performance Optimizations
- **PER_FEED_MS = 20000**: 20s max per feed processing
- **PREFLIGHT_MS = 6000**: 6s timeout for health checks
- **SLEEP_BETWEEN = 120ms**: Rate limiting between shops
- **Element Budget**: MAX_ITEMS_SCAN limit for deep XML traversal

### Error Handling Strategy
- **Timeout Protection**: Multiple timeout layers (preflight, per-feed, total run)
- **Malformed XML**: Sanitization + CDATA clamping
- **Missing Data**: Intelligent fallbacks for URL/image/title extraction
- **Network Issues**: Retry logic with Range requests

## WooCommerce vs Current Architecture Decision

**Evaluation Results:**
- **Current System Strengths**: 
  - CSV-based simplicity
  - Direct Google Sheets integration
  - Flexible feed processing
  - Quick iteration capability

- **WooCommerce Concerns:**
  - Complex product management overhead
  - Performance implications with large catalogs
  - Additional hosting/maintenance burden
  - Plugin dependency risks

**Decision: Stay with CSV + Apps Script approach** with enhanced robustness.

## Implementation Results

### Enhanced Feed Handling
- **Problematic Merchants**: 4home, Decathlon, Árukereső now handled
- **Success Rate**: Improved from frequent timeouts to reliable 1 product/shop
- **Banner Generation**: Enhanced with fallback URL generation
- **Error Recovery**: Graceful degradation with informative error messages

### System Robustness
- **Incremental Processing**: No more "all or nothing" runs
- **State Persistence**: Cursor-based resumable execution
- **Feed Health Monitoring**: Preflight checks prevent waste
- **Parse Flexibility**: Dual-mode parsing (XML + regex fallback)

### WordPress Integration
- **URL Decoding**: Fixed base64 decode in legacy Dognet fallback
- **Pretty URLs**: `/go/{slug}` support for better UX
- **API Integration**: Robust Fillout form integration
- **Cache Busting**: Version parameter for immediate updates

## Kód Struktúra

### Core Functions
- `impactshop巡_RUN()`: Main patrol execution
- `_parseOneFromXml()`: Robust XML parsing with fallbacks
- `_pickTextNSDeep()`: Namespace-agnostic deep search
- `_parseOneLenient()`: Regex-based fallback parser
- `_preflight()`: Health check implementation

### Configuration Constants
```javascript
const SHOPS_PER_RUN = 10;        // Shops per execution
const MAX_RUN_MS = 220000;       // Total execution time limit
const MAX_ITEMS_SCAN = 1200;     // XML element scan limit
const PER_FEED_MS = 20000;       // Per-feed processing time
```

### Tag Synonyms (Bővített)
- **TITLE_TAGS**: 7 variations for product names
- **URL_TAGS**: 7 variations for product URLs  
- **IMG_TAGS**: 10 variations for images
- **PRICE_TAGS**: 9 variations for pricing
- Plus category, availability, discount tag variations

## Következő Lépések

1. **Monitoring**: Feed success rate tracking
2. **Optimization**: Performance metrics collection  
3. **Expansion**: Additional merchant integration
4. **Maintenance**: Regular health check automation

## Tanulságok

1. **Defensive Programming**: XML feeds require multiple fallback strategies
2. **Time Management**: Strict time boxing prevents cascade failures
3. **State Management**: Incremental processing enables reliable large-scale operations
4. **Error Handling**: Graceful degradation better than complete failure
5. **Architecture Simplicity**: Sometimes simple CSV solutions outperform complex alternatives

---

**Státusz:** ✅ Implemented  
**Impact:** Jelentős stabilitás növekedés, reliable 1 product/shop extraction  
**Technical Debt:** Minimális, jól strukturált fallback rendszer