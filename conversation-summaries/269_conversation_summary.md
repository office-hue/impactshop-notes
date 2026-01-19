# 269. Beszélgetés összefoglaló: Rekonstrukció – NAV Online Számla + impactall (2026-01-10)

## Áttekintés
A hiányzó history log miatt a 2026-01-10-es eseményeket a megmaradt dokumentumblokkokból és fájlnyomokból állítottam össze.

## Megoldás
- Billingo Drive célmappa frissítés: Shared Drive célmappa ID + env beállítások rögzítve, új sheet link elmentve.
- AI Agent core elérés tisztázva: nincs publikus reverse proxy, lokális `http://127.0.0.1:4000`; keepalive csak egyszeri restartot végez.
- NAV Online Számla hivatkozások + Software mezők magyarázata rögzítve (M2M vs Online Számla különbségek).
- `impactall` futás: 13/13 PASS, staging/prod HTTP 200, status snapshot frissült.
- Élő NAV token-exchange teszt: `INVALID_REQUEST_SIGNATURE (400)`; sign key formátum gyanús.
- Új ellenőrző eszköz: `tools/nav-signature-verify.js` a NAV test vector és SHA3-512 ellenőrzéshez.

## Következő lépések
1. NAV oldalon új aláírókulcs generálása/ellenőrzése, majd token-exchange újrapróba.
