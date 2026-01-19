# 317. beszélgetés összefoglaló

- A CJ shopoknál hiányzott a logó, a név helyett slug jelent meg, és a kategória sem volt helyes.
- A `tools/cj_shops.json` alapján prodon kitöltöttem az `impactshop_shops` option hiányzó mezőit (name, logo_url, category, domain), majd cache + fragment transients törlés.
- Ellenőrzés: CJ minták már név/logó/kategória mezőkkel jönnek (`Skytours US`, `GeekBuying`, `Jalbum`, `Answear.hu`, `inSPORTline.hu`).
