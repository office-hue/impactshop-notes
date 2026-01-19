# 146. Beszélgetés összefoglaló: Impi reliability warning

## Áttekintés
A reliability guard/profil kész után az Impi ajánlómotorját is bővítettem: minden ajánlathoz `reliability_label` kerül, és a válasz most `warnings` + `cleanup_candidates` mezőket is visszaad, ha alacsony megbízhatóságú kupon kerül a top listába.

## Megfigyelések
- `apps/ai-agent-core/src/impi/recommend.ts` importálja a reliability label típust, hozzárendeli a `reliability_label` mezőt az ajánlatokhoz, és `cleanup_candidates` listát gyárt a `risky` tételekből.
- Az API válasza (Impi chat + `/api/v1/coupons`) új `warnings` tömböt tartalmaz; kliens oldalon így könnyen lehet figyelmeztetést megjeleníteni vagy manuális review-t kérni.
- A meglévő high-impact és kategória fallback ajánlatok is kaptak `reliability_label='super'` defaultot, hogy megfeleljenek az új típusnak.
- `npm run lint` sikeres; a változás kizárólag a TypeScript réteget érinti, backend deploy előtt elegendő az `aiagentall` guard futtatása.

## Következő lépések
1. A kliens oldalon jelenítsd meg a `warnings` üzenetet (pl. Impi chat UI-ban sárga bannerként).
2. Használd a `cleanup_candidates` listát arra, hogy manuális review feladatot nyiss a kockázatos kuponokra (pl. guard → summary).
