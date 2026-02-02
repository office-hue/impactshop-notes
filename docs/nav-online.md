# NAV Online Számla – egységes összefoglaló

## Cél
Rövid, hivatkozható összegzés a NAV Online Számla integráció jelenlegi állapotáról, az aláírás számítás szabályairól és a kapcsolódó forrásokról.

## Források a repókban
- ImpactShop notes: `notes.md`
- Signature ellenőrző tool: `tools/nav-signature-verify.js`
- AI Agent core worker implementáció: `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent/apps/core-worker/src/nav-online-invoice.ts`

## Hivatalos hivatkozások
- Online Számla felhasználói kézikönyv (3.35):
  - https://onlineszamla.nav.gov.hu/files/container/download/Online%20Számla%20Felhasználói%20kézikönyv_3.35.pdf
- Fejlesztői repo (NAV):
  - https://github.com/nav-gov-hu/Online-Invoice
- Dokumentációs belépő:
  - https://onlineszamla.nav.gov.hu/dokumentaciok

## M2M API vs Online Számla
- A kliensprogram-regisztráció (Client ID/Secret + API Key) a NAV M2M API-hoz tartozik.
- Az Online Számla API külön technikai felhasználó + kulcsok alapján működik.

## Implementáció (ai-agent)
Fájl: `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent/apps/core-worker/src/nav-online-invoice.ts`

- Base URL: `https://api.onlineszamla.nav.gov.hu/invoiceService/v3`
- Token exchange: `POST /tokenExchange`
- Digest lekérdezés: `POST /queryInvoiceDigest`
- Invoice adat lekérdezés: `POST /queryInvoiceData`
- Kulcsok:
  - requestSignature minden NAV v3 kérésnél **signing key** alapján készül (exchange key csak a token titkosításhoz).
  - A signing key literális karaktersorozat, kötőjelekkel együtt.

### Konfigurációs env változók
- `NAV_ONLINE_INVOICE_USER` vagy `NAV_ONLINE_INVOICE_LOGIN`
- `NAV_ONLINE_INVOICE_PASSWORD`
- `NAV_ONLINE_INVOICE_SIGN_KEY`
- `NAV_ONLINE_INVOICE_EXCHANGE_KEY`
- `NAV_ONLINE_INVOICE_TAX_NUMBER` vagy `NAV_TAX_NUMBER`
- `NAV_ONLINE_INVOICE_BASE_URL` (opcionális)
- `NAV_ONLINE_INVOICE_TEST_BASE_URL` (opcionális, teszt környezet)
- `NAV_ONLINE_INVOICE_SOFTWARE_ID` (opcionális, 18 karakteres)

### Software blokk (XML)
- `softwareId` és a szoftver fejlesztői mezők fejlesztő által definiáltak.
- Nem a NAV UI-ban kiosztott kódok.
- Ha nincs `NAV_ONLINE_INVOICE_SOFTWARE_ID`, akkor fallback (ai-agentben): `HU<törzsszám> AIA00001` mintájú 18 karakteres ID.
- V3 XSD szerint a `softwareId` 18 karakter, csak `A-Z`, `0-9` vagy `-` lehet.

## requestSignature – helyes számítás
- Alap: `requestId + timestamp + signingKey`.
- Hash: SHA3-512, hex formátum, UPPERCASE.
- A signing key literális karaktersorozat, kötőjelekkel együtt. Nem szabad átkódolni vagy módosítani.
- V3 szerint a timestampet a signature‑hez `yyyyMMddHHmmss` formára kell maszkolni (az XML-ben marad ISO 8601).

## v3 XML séma (tokenExchange)
- Root elem: `TokenExchangeRequest` az OSA API namespace alatt.
- `requestVersion="3.0"` és `headerVersion="1.0"` a `common:header` blokkban szerepelnek.
- `header/user` elemek a `http://schemas.nav.gov.hu/NTCA/1.0/common` namespace alatt.
- A `software` blokk a default API namespace alatt van (prefix nélkül).
- A `requestSignature` elem a `common:user` blokkban szerepel a v3 sémában.

## queryInvoiceDigest – működési korlátok
- Időablak maximum 35 nap (különben `BAD_QUERY_PARAM_RANGE_EXCEEDED`).
- Pagináció: `currentPage` + `availablePage` mezők alapján.

## queryInvoiceData – felépítés
- Lekérdezés: `invoiceNumber` + `invoiceDirection` (+ `supplierTaxNumber` ha elérhető).
- Válaszban `invoiceData` base64 XML (dekompresszió nincs, ha `compressedContentIndicator=false`).

## NAV Online audit checklist (gyors)
- `NAV_ONLINE_INVOICE_BASE_URL` prod/test megfelelően beállítva.
- `NAV_ONLINE_INVOICE_TEST_BASE_URL` teszt környezethez (ha használod).
- `NAV_ONLINE_INVOICE_SOFTWARE_ID` 18 karakter, nagybetű, `A-Z0-9-`.
- requestSignature SHA3-512, UPPERCASE, `maskedTimestamp` (yyyyMMddHHmmss) használat.
- queryInvoiceDigest: max 35 nap + `availablePage` pagináció.
- queryInvoiceData: invoiceNumber + direction (+ supplierTaxNumber), válasz mentve.
- Exportok: lokális `data/nav-online-invoice/` + Drive mappa frissítve.

## Tipikus INVALID_REQUEST_SIGNATURE okok
- A signing key hex/base64-ként kezelése (átkódítás) – rossz hash.
- Rossz timestamp formátum (nem UTC / nem a NAV által elvárt formátum).
- Signing key vs exchange key felcserélése.
- Lejárt vagy visszavont kulcs.
- Óraeltérés a kliens oldalon.

## Javítási lépések
1. NAV portálon új XML aláíró kulcs + XML cserekulcs generálása.
2. Kulcsok pontos másolása (kötőjelek, nagybetuk, whitespace nélkül).
3. requestSignature SHA3-512 UPPERCASE beállítás ellenőrzése.
4. Klienskonyvtarban `signKeyHex=false` (ha van ilyen opcio).
5. Szerverido szinkron (UTC).

## Eszközök
- Signature ellenőrző tool: `tools/nav-signature-verify.js`
  - NAV test vector ellenőrzés
  - Kötőjel/timestamp hatás ellenőrzés
  - SHA3-512 library teszt

## Legutóbbi állapot (notes.md alapján)
- Token exchange működik (prod).
- Digest + invoiceData letöltés 2025-re elkészült.

## Exportok (2025)
- Lokális mentés (ai-agent): `data/nav-online-invoice/` + `download-summary.json`
- Drive mappa: `/Users/bujdosoarnold/Library/CloudStorage/GoogleDrive-bujdoso.arnold@bujdosoiroda.com/Megosztott meghajtók/AI Agent Core/NAV Online 2025/`

<!-- IMPACTALL: AUTOLOAD -->
### Impactall – NAV Online gyors infók (nem secret)
- Repo: `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent/apps/core-worker/src/nav-online-invoice.ts`
- Base URL: `https://api.onlineszamla.nav.gov.hu/invoiceService/v3`
- Központi env: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env`
- Kulcselv: requestSignature = `requestId + maskedTimestamp + signKey` (SHA3-512, UPPERCASE)
- Digest limit: max 35 nap + `availablePage` pagináció
- Exportok: `data/nav-online-invoice/` + Drive: `.../AI Agent Core/NAV Online 2025/`

### Impactall – Harvester/Auto‑banner gyors futtatás (SSH)
- SSH host: `sharityh@s59.tarhely.com` (prod shell)
- App path: `/home/sharityh/app`
- Sync + cleanup:
  - `wp impactshop auto-banner sync`
  - `wp impactshop auto-banner cleanup`
- Ellenőrzés:
  - `wp db query "SELECT COUNT(*) as count, status FROM wp_impactshop_auto_banners GROUP BY status;"`
  - `wp db query "SELECT id, shop_slug, title, status FROM wp_impactshop_auto_banners ORDER BY id DESC LIMIT 5;"`
- DTD/rossz URL takarítás:
  - `wp db query "DELETE FROM wp_impactshop_auto_banners WHERE banner_url LIKE '%w3.org%' OR banner_url LIKE '%/DTD/%' OR banner_url LIKE '%.dtd%';"`

## Kapcsolódó dokumentumok
- `notes.md` – 2026-01-10 és 2026-01-13 NAV blokkok
- `conversation-summaries/269_conversation_summary.md`
- `conversation-summaries/270_conversation_summary.md`
