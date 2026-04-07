# Public Pages Canonical Baseline

Last updated: 2026-04-01

## Purpose

Ez a dokumentum a Sharity publikus információs és guide-oldalainak kanonikus baseline-ja. Ha a live viselkedés, route-szerep, forráshely vagy védelmi állapot ettől jóváhagyás nélkül eltér, azt regressziónak kell tekinteni.

## Canonical routes

- `/rolunk/` = brand-first oldal
- `/cegeknek/` = elsődleges partner / sales belépő
- `/befektetoknek/` = befektetői landing page
- `/partner-api/` = developer / integration landing
- `/ngo-guides/` = NGO guide hub
- `/ngo-guides/ngo-card/` = NGO Card guide
- `/ngo-guides/impact-shop/` = shopping / affiliate donation guide
- `/ngo-guides/impact-challenge/` = videó + Offerwall + voting guide
- `/ngo-guides/jogi-dokumentumok/` = trust / document hub

## Canonical URL policy

- A kanonikus publikus URL-forma minden érintett route-on perjeles.
- A `canonical` és `og:url` mezőknek is ezt a perjeles formát kell követniük.
- A HU/EN párok `?lang=en` váltóval élnek, és `hreflang` / `alternate` kapcsolattal vannak összekötve.

## Canonical source of truth

### Guard-enforced guide system

Ezek a repo-n belüli, guardolt források:

- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `wp-content/mu-plugins/impactshop-ngo-guides/ngo-guides-summary.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/ngo-guides-summary-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/impact-shop-ngo.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/impact-shop-ngo-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/impact-activity-ngo.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/impact-activity-ngo-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/ngo-card.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/ngo-card-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/cegeknek.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/cegeknek-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/befektetoknek.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/befektetoknek-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/rolunk.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/rolunk-en.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok.html`
- `wp-content/mu-plugins/impactshop-ngo-guides/jogi-dokumentumok-en.html`

### Repo-external but canonical sources

Ezek jelenleg nem a `impactshop-notes` repo guard-configján belül élnek, ezért külön fizikai kontroll kell rájuk:

- `/Users/bujdosoarnold/Developer/GitHub/partner-docs.html`
- `/Users/bujdosoarnold/Developer/GitHub/partner-docs-en.html`

## Required protection model

### Guide system

- Kötelező guard-protected perimeter
- Kötelező backup + rollback
- Kötelező deploy utáni read-only visszazárás
- A `befektetoknek` oldal jelenlegi kanonikus tartalmi rétege: üzleti modell + moat + AI operating layer + proof / KPI / diligence framing HU és EN változatban.

### Partner API canonical source

- Kötelező külön backup bundle minden módosítás előtt
- Kötelező rollback leírás
- Kötelező fizikai read-only célállapot (`0444`)
- Nem szabad úgy hivatkozni rá, mintha az `impactshop-notes` repo machine-enforced protected köre volna

## Discovery / indexing baseline

- A kanonikus publikus route-oknak szerepelniük kell a `sitemap_index.xml` alatti custom static sitemapben.
- A dedikált sitemap jelenlegi útvonala: `/impactshop-static-sitemap.xml`
- A feednek tartalmaznia kell a fenti route-ok HU és EN párjait.

## Operational rule

Ha a publikus route-készlet új oldallal bővül, vagy a serving/source-of-truth útvonal változik, ezt a dokumentumot, a policy-fájlokat és szükség esetén a machine-readable protected modellt ugyanabban a change setben frissíteni kell.
