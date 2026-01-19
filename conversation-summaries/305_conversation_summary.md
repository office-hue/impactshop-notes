# 305. Beszélgetés összefoglaló: CJ SID log ellenőrzés

- A prod `impactshop-go-clicks.log` grep alapján 2025-12-30 körül találtam CJ click sorokat.
- A `cj-5619548` shopnál volt `sid`‑es sor (`teszt-ngo~<pseudo>`), de két CJ sorban `sid` üres.
- Következtetés: a `sid` param nem minden CJ linknél kerül rá, ezért az NGO visszakötés nem garantált.
