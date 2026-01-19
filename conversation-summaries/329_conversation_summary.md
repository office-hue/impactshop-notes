# 329. Beszélgetés összefoglaló: PIN REST controller stub (WP)

Kérés: WordPress REST controller stub előkészítése a PIN kiadás/ellenőrzéshez.

- Új MU plugin: `wp-content/mu-plugins/impactshop-identity-pin.php`.
- Végpontok: `/impact/v1/identity/pin/issue` és `/impact/v1/identity/pin/verify`.
- Alap logika: validáció, rate limit, lockout, audit hook + fájl log.
- Transient alapú tárolás (stub), élesítéshez DB + kézbesítés szükséges.
