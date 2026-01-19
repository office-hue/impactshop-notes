# 331. Beszélgetés összefoglaló: PIN kézbesítés + DB cleanup

Kérés: valós kézbesítés integrálása (email/SMS/QR) és DB purge + cron cleanup.

- Email kézbesítés: `wp_mail` beépítve (delivery.channel=email).
- SMS/QR: hookok `impactshop_identity_pin_sms`, `impactshop_identity_pin_qr_payload`.
- Delivery log pin_hash + target_hash mezőkkel.
- Napi cron cleanup: `impactshop_pin_cleanup` → 30 napnál régebbi használt/lejárt PIN törlés.
- Új MU plugin: `wp-content/mu-plugins/impactshop-identity-pin-cron.php`.
- OpenAPI frissítve a delivery mezőkkel.
- SMS provider bekötve (Vonage), QR payload QuickChart URL.
