# 325. Beszélgetés összefoglaló: PIN paraméterek rögzítése

Kérés: PIN formátum + érvényesség + rate limit paraméterek rögzítése.

- Rögzítve: 6 számjegyű, egyszer használatos PIN; 1 aktív PIN / pseudo‑ID.
- Érvényesség: 15 perc; újragenerálás max 3 / 24 óra / pseudo‑ID.
- Rate limit: 5/óra/IP + 10/nap/pseudo‑ID; 3 hibás próbálkozás után 15 perc lockout.
- Audit: `identity_pin_verify` event mezőkkel (pseudo_hash, ip_hash, status, attempt_count).
- Dokumentáció: `impact-hub-system-v1.3.md` 4.1 PIN szekció frissítve.
