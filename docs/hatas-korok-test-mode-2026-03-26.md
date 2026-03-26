# Hatás Körök test mode — 2026-03-26

## Cél

Fejlesztői és QA teszteléshez a Hatás Körök oldal kapott explicit teszt üzemmódot, ahol pseudo ID alapján gyorsan lehet identitást váltani, NGO admin nézetet nyitni, és tagsági korlát nélkül posztolni.

## Módosítások

- Backend flag: `IMPACT_COMMUNITY_TEST_MODE` vagy `PIN_TEST_MODE`, továbbá query/header/cookie alapon is aktiválható `ic_test_mode=1`.
- Pseudo override: `impact_pseudo_id` query/header/request param beolvasható, és a session ezt használja a normál cookie helyett.
- NGO override: `impact_ngo_slug` query/header/request param alapján teszt módban pseudo-alapú NGO token generálódik.
- Teszt módban a következő korlátok lazulnak:
  - max kör limit
  - körtagsági követelmény posztoláshoz és szavazáshoz
  - post rate limit
  - timeout/trust/link/toxicity blokkok
- A `/impact/v1/auth/status` most visszaadja a `test_mode`, `pseudo_id`, `ngo_slug`, `ngo_admin_url` mezőket is.
- A Hatás Körök SPA kapott egy felső teszt panelt pseudo/NGO váltáshoz.
- Az NGO admin shortcode oldal teszt módban automatikus pseudo-alapú loginra képes.

## Validáció

- `php -l wp-content/mu-plugins/impact-community.php`
- `php -l wp-content/mu-plugins/impact-community-app.php`
- production deploy lefutott
- `GET /wp-json/impact/v1/auth/status` teszt módban:
  - `authenticated=true`
  - `test_mode=true`
  - `pseudo_id=TESTUSER01`
- production roundtrip:
  - teszt pseudo ID-val poszt létrehozás
  - azonnali törlés ugyanazzal a pseudo ID-val

## Teszt URL minta

```text
/hatas-korok/?ic_test_mode=1&impact_pseudo_id=TESTUSER01&impact_ngo_slug=bator-tabor-alapitvany
```

```text
/impact-challenge/ngo-admin/?ic_test_mode=1&impact_pseudo_id=TESTUSER01&impact_ngo_slug=bator-tabor-alapitvany
```
