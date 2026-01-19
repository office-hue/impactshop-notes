# PIN Error Codes Reference

| Code | HTTP | Leírás | User Message (HU) | Retry? |
| --- | --- | --- | --- | --- |
| `invalid_request` | 400 | Hibás `pseudo_id`/`context`/`channel` | Érvénytelen kérés | No |
| `rate_limited` | 429 | Rate limit túllépés | Túl sok próbálkozás, várj | Yes |
| `pseudo_not_found` | 404 | Nincs PIN a pseudo_id-hez | PIN nem található | No |
| `pin_locked` | 403 | 3 hibás próbálkozás után lock | PIN zárolva 15 percre | Yes |
| `pin_used` | 409 | PIN már felhasznált | PIN már használatban | No |
| `pin_expired` | 409 | PIN lejárt | PIN lejárt | Yes (új issue) |
| `pin_invalid` | 401 | Rossz PIN kód | Hibás PIN kód | Yes |
| `delivery_failed` | 500 | SMS/email kézbesítés hiba | Kézbesítési hiba | Yes |
| `server_error` | 500 | PIN tárolás hiba | Szerverhiba | Yes |
| `pin_disabled` | 403 | PIN rendszer kikapcsolva | PIN ideiglenesen nem elérhető | No |
