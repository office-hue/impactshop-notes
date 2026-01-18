# Coupon Harvester – Script váz és leírás

Cél: Gmail + whitelistelt (Dognet/CJ) partner oldalak publikus kuponjainak heti 2× begyűjtése sandbox CSV-kbe. Éles adatba nem ír, manuális review kötelező.

## Fő elvek
- Források: saját Gmail (kupon/“kedvezmény” levelek), publikus partner-oldalak (whitelist domenek).
- Kimenet: draft CSV-k `out/manual_coupons_draft-YYYY-MM-DD.csv` és `out/shops_manual_draft-YYYY-MM-DD.csv`.
- Whitelist-only crawl, no-login, no form-post; csak publikus/partner által küldött kódok.
- Data minimization: levélből csak kivonat (from, subject, kuponkód, kedvezmény, expiry), teljes body nem tárolódik.

### Shops export források
- **Dognet shops**: a „Shops” Google Sheet publikált CSV-je → `https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv`.  
  - Lekérés: `curl -sSL <url> -o /tmp/impactshop_Shops.csv`.  
  - Normalizálás: a repo gyökeréből futtasd a helper scriptet (`python3 scripts/generate_shops_whitelist.py --dognet-feed fixtures/coupon-harvester/feeds/dognet_programs.csv ...`), vagy egyszerű CSV szűréssel töltsd fel a `fixtures/coupon-harvester/feeds/dognet_programs.csv` fájlt (mezők: domain, slug, default_d1, landing_url).  
  - A generátor automatikusan frissíti a `tools/shops_registry.json`-t + `.codex/cron/coupon-harvester-config.json` whitelistjét.
- **CJ shops**: SSH cp40 → `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && wp impactshop cj:sync-shops --format=json"` majd a JSON-t mentsd le `tools/cj_shops.json`/`.csv` néven (pl. `jq -r`-rel `domain,slug,program_id`), végül add meg a generátornak: `scripts/generate_shops_whitelist.py --cj-feed tools/cj_shops.csv`.
- Mindkét feedet ugyanazzal a parancssal lehet egyszerre beolvasni; ha nincs elérhető CJ export, a `cj_programs.csv` üresen is hagyható, de dokumentáld a hiányt a `notes.md`-ben.

## Adatmodellek
- Shops draft: `slug, name, logo_url, default_d1 (NGO slug), cta_url(optional), category(optional)`
- Manual coupons draft: `shop_slug, shop_name, logo_url, coupon_code, discount_label, title(optional), cta_url(optional), starts_at, expires_at, coupon_type, priority`
- Dedup kulcs: `(shop_slug, coupon_code, expires_at|discount_label)`

## Whitelist (kezdeti példa, bővíthető Dognet/CJ feedből)
```
decathlon.hu      -> decathlon
yves-rocher.hu    -> yves_rocher
aboutyou.hu       -> about_you
otto.hu           -> otto
c-and-a.com       -> c_and_a
notino.hu         -> notino
emag.hu           -> emag
alza.hu           -> alza
```
Ajánlott: Dognet/CJ program feedből automatikus slug/domain/program-id táblázat generálása.

## Futási folyamat (heti 2×, pl. kedd/péntek 09:00)
1) Gmail search: `(subject:kupon OR subject:coupon OR "kuponkód" OR "kedvezmény") newer_than:14d`, szűrés partnerdoménekre.  
2) Feldolgozás: kód, kedvezmény, lejárat, partner domain → slug mapping.  
3) Web scrape: whitelistelt kupon/akció oldalak (pl. `/kupon`, `/coupon`, “promóció”). Regex a kódra, kedvezményre.  
4) Dedup + expiry check; ha nincs slug-match → `needs_mapping` és felvitel `shops_manual_draft`-ba.  
5) Kimenet írása timestampelt CSV-kbe, `latest` szimbolikus linkkel.  
6) Összefoglaló log: találatok, reject okok (lejárt, hiányos mező).  
7) Manuális review: jóváhagyott sorokat másolod át az éles Shops.csv-be és a publikus manual_coupons feedbe (Sheets→CSV).

## Node.js script váz (tools/coupon-harvester.ts)
```ts
import fs from 'fs/promises';
import fetch from 'node-fetch';
import {google} from 'googleapis';
import {parse} from 'node-html-parser';
import {stringify} from 'csv-stringify/sync';

const WHITELIST = [
  {slug: 'decathlon', domain: 'decathlon.hu'},
  {slug: 'yves_rocher', domain: 'yves-rocher.hu'},
  {slug: 'about_you', domain: 'aboutyou.hu'},
  {slug: 'otto', domain: 'otto.hu'},
];

type Coupon = {
  shop_slug: string;
  shop_name: string;
  logo_url?: string;
  coupon_code: string;
  discount_label: string;
  title?: string;
  cta_url?: string;
  starts_at?: string;
  expires_at?: string;
  coupon_type?: string;
  priority?: number;
};

function mapDomainToShop(domain: string) {
  const hit = WHITELIST.find(w => domain.includes(w.domain));
  if (hit) return {slug: hit.slug, name: hit.slug.replace(/_/g, ' ')};
  return {slug: domain.split('.')[0], name: domain};
}

function extractFromHtml(html: string, subject: string, from: string): Coupon | null {
  const root = parse(html);
  const text = root.text.toLowerCase();
  const code = (text.match(/([a-z0-9]{4,12})/i) || [])[1];
  const discount = (text.match(/(-\\s?\\d{1,2}%|\\d{3,5}\\s?ft)/i) || [])[1];
  if (!code || !discount) return null;
  const domain = (from.match(/@([^> ]+)/) || [])[1] || '';
  const shop = mapDomainToShop(domain);
  return {
    shop_slug: shop.slug,
    shop_name: shop.name,
    coupon_code: code.toUpperCase(),
    discount_label: discount.replace(/\\s+/g, ''),
    title: subject.slice(0, 120),
  };
}

// TODO: Gmail auth (OAuth/Service Account), rate-limit/backoff, scan-state dedup

function dedup(list: Coupon[]): Coupon[] {
  const seen = new Set<string>();
  return list.filter(c => {
    const key = `${c.shop_slug}|${c.coupon_code}|${c.expires_at || ''}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

async function writeCsv(coupons: Coupon[]) {
  const csv = stringify(coupons, {
    header: true,
    columns: [
      'shop_slug','shop_name','logo_url','coupon_code','discount_label','title',
      'cta_url','starts_at','expires_at','coupon_type','priority'
    ]
  });
  const ts = new Date().toISOString().slice(0,10);
  await fs.writeFile(`out/manual_coupons_draft-${ts}.csv`, csv, 'utf8');
}
```

## Python script váz (tools/coupon_harvester.py)
```python
import csv, re, requests
from bs4 import BeautifulSoup
from datetime import datetime

WHITELIST = [
    {"slug": "decathlon", "domain": "decathlon.hu"},
    {"slug": "yves_rocher", "domain": "yves-rocher.hu"},
    {"slug": "about_you", "domain": "aboutyou.hu"},
]

def scrape(url, slug):
    r = requests.get(url, timeout=10)
    soup = BeautifulSoup(r.text, "html.parser")
    text = soup.get_text(" ", strip=True)
    codes = re.findall(r"kuponkód[:\\s]+([A-Z0-9]{4,12})", text, flags=re.I)
    discount = re.search(r"(-\\s?\\d{1,2}%|\\d{3,5}\\s?ft)", text, flags=re.I)
    out = []
    for code in codes:
        out.append({
            "shop_slug": slug,
            "shop_name": slug,
            "coupon_code": code.upper(),
            "discount_label": discount.group(1) if discount else "",
            "title": "Kupon",
        })
    return out

def write_csv(rows, fname):
    cols = ["shop_slug","shop_name","logo_url","coupon_code","discount_label","title",
            "cta_url","starts_at","expires_at","coupon_type","priority"]
    with open(fname, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        for r in rows:
            w.writerow(r)

def main():
    rows = []
    # rows += scrape("https://www.decathlon.hu/kupon", "decathlon")
    rows = dedup(rows)
    fname = f"out/manual_coupons_draft-{datetime.now().date()}.csv"
    write_csv(rows, fname)
    print("Írva:", fname, len(rows), "sor")

def dedup(rows):
    seen = set()
    out = []
    for r in rows:
        key = (r["shop_slug"], r["coupon_code"], r.get("expires_at",""))
        if key in seen: continue
        seen.add(key)
        out.append(r)
    return out

if __name__ == "__main__":
    main()
```

## Kiegészítő TODO-k
- Gmail OAuth/Service Account auth + token cache; rate-limit/backoff.
- Dognet/CJ feedből whitelist generator (slug/domain/program-id) + scrape-URL registry.
- Scan-state hash, lejárt szűrés, „expiry_unknown” flag, `needs_mapping` jelölés új shopokra.
- Cron heti 2×; smoke tesztek mock Gmail/HTML-lel.
- Log: forrás (msg-id/URL), találat, reject ok; dedup statisztika.

## Review-alapú bővítések (GPT-5.1 + Opus 4.1)

### Biztonság és privacy
- Sandbox/output: mindig külön OUT_DIR (pl. `out/sandbox`), soha nem ír éles TablePress/Sheets feedbe.
- Gmail: csak `gmail.readonly` scope, saját fiók; teljes body ne kerüljön logba, csak msg-id, from domain, subject, találati statisztika.
- HTTP scrape: whitelist-only, no login, no cookie/form-post; max fetch darabszám.

### Konfiguráció
- Konstansok konfigurációs fájlban (.json/.yaml): WHITELIST (domain→slug), OUT_DIR, NEWER_THAN_DAYS, Gmail label/sender szűrők, scrape-URL registry.
- Domain→slug: pontos domain match (endsWith), ha nincs találat → `shop_slug="NEEDS_MAPPING"` + domain a shops_manual_draft-ba; ne találj ki slugot automatikusan.
- Javasolt registry: `shops_registry.(json|csv)` Dognet/CJ feedből generálva (slug, domain, program_id, default_d1, category, default_cta_url, logo_url).

### Adatmodell finomítás
- Opcionális extra oszlopok a draftba: `source_type (gmail|web)`, `source_ref (msg-id|URL)`, `language`, `currency`, `expiry_unknown (bool)`.
- Kötelező mezők validálása: shop_slug, coupon_code, discount_label; lejárt kupon eldobása.

### Gmail integráció
- Keresés finomítása: from:whitelist OR label:Promotions, paging (nextPageToken) kötelező.
- Incremental feldolgozás: historyId/msg-id checkpoint, ne mindig 14 napot nézzen.
- Rate limit/backoff: 429/5xx esetén exponential backoff (max 3–5 retry).

### Regex / extract javítás
- Kupon kulcsszavak környezetében keress kódot: `kuponkód|kedvezménykód|coupon|promo`.
- Kód minta: `([A-Z0-9-]{4,16})` a kulcsszó ±100 karakteres ablakában.
- Kedvezmény minta bővítve: `%`, `Ft`, `EUR`, „ingyenes szállítás”, „+ ajándék”.
- Lejárat minta: dátum formátumok (YYYY-MM-DD, YYYY.MM.DD, DD.MM.YYYY), „érvényes …-ig”.
- Ha nincs expiry: `expires_at=""` + `expiry_unknown=true`.

### Dedup, minőség, score
- Dedup kulcs kiegészítve fuzzy-val (O/0 tévesztés): opcionális Levenshtein 0.9 felett ugyanazon shopnál.
- Validator/score: missing_code, missing_shop, invalid_code_format, expired, no_discount → score csökkentés; reject, ha expired vagy code hiányzik.

### Logging és metrikák
- Minden run JSON log: total_emails_checked, matched, coupons_found, written, rejected (lejárt/hiányos/duplikált), errors (Gmail/HTTP/parsing), new_shops.
- Alert jelzés: 0 új kupon; >50% duplikátum; >10 error.

### Tesztek
- Fixtures: `fixtures/gmail/*.json`, `fixtures/html/*.html` (anonimizált).
- Unit: extractFromHtml (több formátum), domain→slug mapping (hit/no-hit), validator.
- Smoke: DRY_RUN=true, nem ír file-t, csak logol.

### Tooling döntés
- Elsődleges implementáció: javasolt a Node/TS (típusosság, illeszkedés az ImpactShop ökoszisztémához). A Python váz maradhat mint POC vagy törölhető később, hogy ne duplikáljon logikát.

### Közép távú integráció (opcionális)
- `tools/coupon-approve.ts`: interaktív CLI/web admin a draft CSV-k jóváhagyására (slug, dátum, címke módosítás), majd export az éles Shops.csv/manual_coupons feedekbe.

## Implementált fájlok (váz)
- `tools/coupon-harvester.config.json` – sandbox config (OUT_DIR, newerThanDays, whitelist, Gmail query, scrape target példa).
- `tools/coupon-harvester.ts` – TS alapú futtatható váz:
  - Config betöltés, DRY_RUN támogatás.
  - Gmail kivonat (auth váz, gmail.readonly scope), whitelist alapú domain→slug mapping.
  - Whitelistelt web scrape (no login), alap regex extract, dedup, expiry_unknown jelzés.
  - Draft CSV írás (`manual_coupons_draft-YYYY-MM-DD.csv` + latest) az outDir-be.
  - JSON log összefoglaló (gmail/web count, dry_run flag).
- `tools/gmail-auth.ts` – Gmail OAuth/Service Account helper (credentialsPath, tokenPath, delegatedUser), gmail.readonly scope.
- `tools/whitelist-generator.ts` – Dognet/CJ program CSV → slug/domain JSON whitelist generátor (slugify, domain normalizálás).

## Gmail auth flow (részletes)
- **Installed App OAuth (me):**
  1) Szerezz `credentials.json`-t (Google Cloud console, OAuth client ID – Desktop).
  2) `GMAIL_CREDENTIALS=credentials.json node ts-node tools/gmail-auth.ts --init` (vagy egyszerűen futtasd a harvester-t, és írd ki a consent URL-t), majd a kapott kódot cseréld tokenre, írd `token.json`-ba.
  3) Futtatás: `GMAIL_CREDENTIALS=credentials.json GMAIL_TOKEN=token.json ts-node tools/coupon-harvester.ts`
- **Service Account (delegation):**
  1) Szerezz service account JSON-t (type=service_account), domain-wide delegation engedélyezve.
  2) Állítsd `GMAIL_USER=user@domain`-t a delegált mailboxhoz.
  3) Futtatás: `GMAIL_CREDENTIALS=sa.json GMAIL_USER=user@domain ts-node tools/coupon-harvester.ts`
- Scopes: mindig `https://www.googleapis.com/auth/gmail.readonly`.
- Rate limit/backoff: a harvesterben implementált backoff javasolt; 429/5xx esetén retry limit 3–5.
- Incremental feldolgozás: javasolt historyId/msg-id checkpoint (TODO).

## Teszt/fixture minták (javaslat)
- `fixtures/gmail/*.json`: Gmail API message list/get válaszok (anonimizálva), különböző kupon-formátumokkal.
- `fixtures/html/*.html`: publikus kuponoldal minták (decathlon, yves-rocher, stb., anonimizált), eltérő kód- és kedvezményformákkal.
- Smoke teszt:
  - DRY_RUN=1, fixture-ből betöltött HTML/JSON (mockolt fetch/Gmail), elvárt kimenet 1–2 kupon.
  - Validáld a dedupot és a kötelező mezők meglétét (shop_slug, coupon_code, discount_label).

## Playwright HTML snapshot runner
Az `impactshop-notes` repo most hordoz egy valós Playwright crawlert, amely HTML snapshotokat készít
a whitelistelt partneroldalakról. A snapshotokat felhasználhatod a `scripts/coupon_harvester_pipeline.py`
`html_sources` blokkjában, így DRY_RUN=1 módban is valós tartalmat dolgozol fel.

### Telepítés
```
npm install
npm run playwright:install
```

### Konfiguráció
- Mintafájl: `tools/playwright/harvester-config.sample.json` – másold `harvester-config.json` néven, majd
  töltsd ki a `pages` listát (slug, URL, opcionális selector + kimeneti fájlnév).
- `outDir`: hova mentse a HTML-t (pl. `fixtures/coupon-harvester/html`).
- `summaryPath`: opcionális JSON, amely felsorolja a létrehozott snapshotokat.
- Oldalszintű opciók: `waitForSelector`, `waitAfterLoadMs`, `saveAs` (fájlnév).

### Futás
```
npm run playwright:harvest:config
# vagy
npx tsx tools/playwright/harvester-runner.ts --config tools/playwright/harvester-config.json
```

A futás `tmp/coupon-harvester/playwright-summary.json` fájlba írja az összegzést, a HTML pedig
alapértelmezés szerint a `fixtures/coupon-harvester/html` könyvtárba kerül. A `scripts/coupon-harvester-smoke.sh`
configjában a `html_sources` mezőt frissítsd az új fájlokra, így a smoke teszt már ezekből dolgozik.
