# Projekt napló

## 0. Rövid összefoglaló
- Platform: WordPress (ImpactShop)
- Fő téma: akciós kártyák linkjei → ne a shop főoldalra, hanem termékoldalra vigyenek.

## 1. Döntésnapló
## Döntési napló

# Impact Shop Development Notes

## Decision Log

### 2025-01-01: ChatGPT Conversation Integration
- **Context**: Starting systematic processing of ChatGPT conversation history for project context preservation
- **Decision**: Process conversations chronologically to build comprehensive technical documentation
- **Implementation**: Document conversation summaries, extract technical details, maintain decision log

### 2025-01-01: Complete Impact Shop System with Automated Deals Processing
- **Context**: 8th conversation covers comprehensive system implementation with automated deals feed processing
- **Decision**: Integrate complete PHP snippet with CSV management, automated deals processing, and API functionality
- **Implementation**: Complete WordPress snippet with shortcodes, redirects, admin tools, Apps Script automation, and Dognet API integration

### 2025-01-11: Apps Script Timeout Optimization and XML Parser Robustness
- **Context**: 10th conversation addresses Apps Script timeout issues and XML feed parsing failures for problematic merchants
- **Decision**: Implement patrol system with time-boxed execution, incremental processing, and dual-mode parsing (XML + regex fallback)
- **Implementation**: Enhanced Apps Script with SHOPS_PER_RUN=10 limit, 220s execution cap, preflight health checks, namespace-agnostic parsing, and base64 URL decoding fixes in WordPress

### 2025-01-02: XML Feed Parser Multi-Tier Architecture and Schema Recognition
- **Context**: 11th conversation develops sophisticated XML feed parsing system for diverse merchant formats
- **Decision**: Implement multi-tier parsing strategy with schema detection, case-insensitive field extraction, and deep DOM traversal
- **Implementation**: Three-tier parsing (DOM → CHUNK → HEURISTIC), dual schema support (Arukereso vs Google/RSS), ProductURL closure fixes, JAXP entity limit handling, Dognet preflight skipping, and progressive enhancement through v6.0 → v6.2

### 2025-01-11: Automatic Dognet API Authentication and Enhanced Banner System  
- **Context**: 9th conversation implements automatic Dognet API login and banner system improvements
- **Decision**: Implement auto-login with office@sharity.hu credentials, 20-hour token caching, and fallback banner generation
- **Implementation**: Consolidated snippet with automatic token management, enhanced banner highlighting, and robust error handling

### 2025-01-12: Advanced Dognet Backend Integration and Conversions Reporting
- **Context**: 12th conversation develops sophisticated backend integration for Dognet conversions/commissions data retrieval
- **Decision**: Implement robustized authentication with multiple endpoint fallbacks, comprehensive data aggregation system, and MU plugin architecture
- **Implementation**: Multi-endpoint authentication fallbacks, POST/GET method alternatives, shop×NGO data aggregation, REST API endpoints, HTML reporting shortcodes, comprehensive error handling, and MU plugin deployment approach

### 2025-01-12: Affiliate Hijacking Detection and Prevention System
- **Context**: 13th conversation addresses competitor plugin threats (Adjukössze) stealing affiliate commissions and compromising nyereményjáték integrity
- **Decision**: Implement multi-layered affiliate protection system with real-time detection and client-side blocking
- **Implementation**: WordPress plugin with Dognet Publisher API integration, timezone-aware click verification, ping diagnostics, and JavaScript-based anti-hijack protection

### Conversation Processing Status
- ✅ Conversation 7: WordPress plugin architecture optimization, enhanced code structure, UI improvements
- ✅ Conversation 8: Complete Impact Shop system with deals feed automation, Apps Script implementation, Dognet API integration
- ✅ Conversation 9: Automatic Dognet API authentication, token management, enhanced banner system with fallbacks
- ✅ Conversation 10: Apps Script timeout optimization, XML parser robustness, incremental processing with patrol system
- ✅ Conversation 11: XML feed parser multi-tier architecture, schema recognition, case-insensitive field extraction, deep DOM traversal
- ✅ Conversation 12: Advanced Dognet backend integration, conversions reporting system, robustized authentication, MU plugin architecture
- ✅ Conversation 13: Affiliate hijacking detection and prevention, anti-hijack protection system, Dognet click verification
- ⏳ Current: Documentation complete for processed conversations

## 2. Kódrészletek
- Lásd: `snippets/deals_shortcode_fixed.php`

## 3. Teendők
- [x] ChatGPT beszélgetés dokumentálása GitHub repository-ban
- [x] GitHub Copilot instructions készítése
- [x] WordPress Impact Shop továbbfejlesztése:
  - [x] Fillout NGO-választó implementálása (1 űrlap, dinamikus shop paraméter)
  - [x] WordPress Redirection linkek beállítása (shoponként külön szabály)
  - [x] Dognet d1 paraméter testing és működés ✅
- [x] 7 webshop beállítása: Árukereső, Decathlon, 4home, Allegro, Vision Express, REGIO Játék, Sparkl
- [ ] ## Aktuális feladatok

## Current Technical Status

### Impact Shop System
- **Current State**: Complete e-commerce affiliate platform with automatic Dognet API authentication, enhanced banner system, and robust XML feed processing
- **Architecture**: WordPress snippet + Google Sheets + Optimized Apps Script + Auto-login Dognet API integration  
- **Key Features**: Automatic token management, patrol-based feed processing, timeout protection, dual-mode XML parsing, shop/banner management, fallback banner generation, enhanced CSS highlighting, redirect handling, admin diagnostics
- **Apps Script Optimization**: Time-boxed execution (220s), incremental processing with cursors, preflight health checks, namespace-agnostic parsing, element budget limits
- **Feed Processing**: Robust XML parsing with fallback to regex-based parsing, handles malformed feeds (4home, Decathlon, Árukereső)
- **Authentication**: Automatic Dognet login (office@sharity.hu) with 20-hour token caching and 401 error retry
- **CSV Integration**: Dual system (Shops + Banners) with fallback banner generation when CSV is empty
- **API Integration**: Dognet Publisher API with automatic authentication and graceful fallback to legacy URLs
- **UI Components**: Highlighted banners (100px vs 60px shops), "AKCIÓ" badges, category-based fallback system

### Recent Implementations
- **Affiliate Hijacking Detection and Prevention**: Comprehensive security system protecting against competitor plugins (Adjukössze) with Dognet Publisher API integration for click verification, timezone-aware query handling (Europe/Bratislava), multi-layered detection (client-side JS + server-side verification), CHID parameter monitoring, real-time link protection, ping diagnostics for redirect chain analysis, WordPress plugin with both full and LITE versions, and anti-hijack protection with shortcode-based warning system
- **Advanced Dognet Backend Integration**: Sophisticated conversions reporting system with robustized authentication (multiple login endpoints), multi-format response parsing, shop×NGO data aggregation, REST API endpoints (/wp-json/impactshop/v1/totals), HTML reporting shortcodes, and MU plugin architecture for improved code management
- **Conversions Data Pipeline**: Complete financial data retrieval system with POST/GET method fallbacks, endpoint discovery handling 405/404 errors, response normalization across different API formats, campaign ID mapping from CSV, WordPress transient caching, and comprehensive diagnostic tools
- **MU Plugin Architecture**: Must-use plugin deployment approach solving code management issues, collision protection, automatic loading, and simplified deployment workflow
- **Robustized API Authentication**: Multiple Dognet login endpoint fallbacks (/auth/login, /publisher/login, /login), JSON and form-encoded payload support, HTTP header optimization for Cloudflare compatibility, automatic token refresh on 401 errors
- **XML Feed Parser Multi-Tier Architecture**: Sophisticated schema recognition with dual support (Arukereso vs Google/RSS formats), case-insensitive field extraction, deep DOM traversal, ProductURL closure fixes, JAXP entity limit handling, and progressive enhancement v6.0→v6.2
- **Advanced Feed Processing**: Three-tier parsing strategy (DOM → CHUNK → HEURISTIC), namespace-agnostic element selection, budget-limited node traversal, regex fallback for malformed feeds, and merchant-specific optimizations (4home deep search, Arukereso ProductURL normalization)
- **Apps Script Timeout Optimization**: Patrol system with time-boxed execution, incremental processing, and robust XML parsing with regex fallbacks
- **Feed Processing Robustness**: Enhanced handling of problematic merchants (4home, Decathlon, Árukereső) with namespace-agnostic parsing and element budget limits
- **WordPress Integration Fixes**: Base64 URL decoding fix in legacy Dognet fallback, pretty URL support (/go/{slug})
- **Automatic API Authentication**: Self-managing Dognet API integration with email/password login and token caching
- **Enhanced Banner System**: Visual differentiation, fallback generation, and robust CSV handling  
- **Consolidated Architecture**: Single snippet solution with all functionality integrated
- **Error Resilience**: 401 retry, timeout handling, fallback mechanisms at every level, preflight health checks
- **Performance Optimizations**: Smart caching, efficient banner injection, responsive design elements, time-boxed processing