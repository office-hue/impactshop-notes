# 293. Beszélgetés összefoglaló: Impi kommunikáció javítás

## Áttekintés
Az Impi kommunikációt egységesítettük rövid, bekezdéses stílusra, és kikapcsoltuk a bullet‑listás utófeldolgozást. A fallback summaryk is rövid, CTA‑val záró formára kerültek.

## Megoldás
- `apps/api-gateway/src/services/impi-openai.ts`: prompt 2–4 mondat, bullet nélkül; ellentmondó instrukciók eltávolítva.
- `apps/api-gateway/src/index.ts`: low‑effort és confidence template rövid bekezdéses; autolink kikapcsolva; per‑thousand szöveg bekezdésben.
- `apps/ai-agent-core/src/impi/recommend.ts`: fallback summaryk rövid CTA‑val, kevésbé technikai stílus.
