# BabaMama Expo portálátirányítás

Arnold 2026. november 19-én mindkét meglévő QR/NFC-portálcímet pontosan a
`https://sharity.hu/babamama-expo` oldalra kérte irányítani.

Protected files touched: `wp-content/mu-plugins/impactshop-sharity-public-portal-redirects.php`,
`docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`.

A meglévő `template_redirect` hook két pontos app.sharity.hu portálútvonal
GET/HEAD kérésére válaszol. A 302 státusz, a gyorsítótárazás tiltása,
az állandó Location, a query eldobása és az admin/REST/AJAX kizárása megmarad.
A cél már éles. A hibás Location mindkét nyomtatott bejáratot érintené.
A guide-kezelő, fizetési oldal, adatírás és közös WordPress-útvonalak változatlanok.

Rollback: exact-file production release egyetlen pluginra, az ellenőrzött
`b525a44e6efc7b00e915be5f06b16bdddb8585545f70567e8ac390ba5f82c7b9`
élő SHA-256-ról, beépített távoli mentéssel. Hiba esetén a kiadás receiptjében
szereplő release ID és telepített SHA alapján futtatandó a guard rollback.
Az előző 302 célja `https://sharity.hu/`.

Smoke: route:adomany-automata-portal-1, route:adomany-automata-portal-2,
destination:sharity-public-home (független kontroll), destination:babamama-expo,
route:ngo-guides, route:jysk-riport, route:impact-challenge, route:profil,
deploy:guard-preflight, deploy:checksum-verify.
Kézi próba: olvasd be mindkét táblát, és ellenőrizd a böngésző címsorában a
pontos Expo-címet, az oldal fejlécét és az adománygomb megjelenését.
