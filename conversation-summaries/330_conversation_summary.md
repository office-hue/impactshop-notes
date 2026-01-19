# 330. Beszélgetés összefoglaló: PIN DB tároló + kézbesítés/cookie

Kérés: perzisztens DB tároló + migrációs tábla, PIN kézbesítés és cookie
beállítás a REST stubhoz.

- Új migrációs MU plugin: `wp-content/mu-plugins/impactshop-identity-pin-migration.php`
  → `wp_impact_pin_tokens` tábla.
- PIN tárolás DB-ben (hash, expiry, attempts, lockout, used_at).
- Kézbesítés stub: `impactshop_identity_pin_deliver` hook + delivery log.
- Cookie beállítás: `impactshop_pseudo_id` 365 napra.
