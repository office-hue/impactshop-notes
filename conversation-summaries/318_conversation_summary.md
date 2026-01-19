# 318 Conversation Summary

- CJ tranzakciok beletoltese a ticker/leaderboard/activity logikaba megtortent az `wp-content/mu-plugins/impactshop-metrics-ngo.php` fajlban.
- Uj CJ GraphQL fetcher keszult (PAT + publisher id + website id), Dognet + CJ sorok merge-jevel.
- Mapping: `AUTOMATED`/`PENDING` -> pending, `ACCEPTED` -> approved, `REJECTED/DECLINED/REVERSED` kiesik.
- NGO slug feloldas: `sid` tamogatas, `d1~pseudo` esetben `d1` kerul felhasznalasra.
- Prod+staging deploy, cache flush + metrics transiensek torolve; REST ellenorzesek OK.
