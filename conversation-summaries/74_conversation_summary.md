# 74. Beszélgetés összefoglaló: AI setup prompt finomhangolás

## Áttekintés
A batch QA eredményei alapján frissítettem az `apps/api-gateway/src/services/impi-openai.ts` rendszerpromptját, hogy Impi konzisztensen teljesítse a welcome/döntési/transzparencia előírásokat.

## Változások
- A system prompt most 10 szabályt tartalmaz: kötelező három opciós főmenü, 5 lépéses döntési mechanizmus részletezése, transzparencia-first fallback („nem akarok vásárolni” → Impact riport + Fillout) és kategória-kéréseknél min. 2 NGO + CTA.
- Explicit előírás, hogy fallbackkor is jelenjen meg a Fillout link, valamint mindig jelezze, hogy a deeplink használata rögzíti az adományt.
- A módosításra `npm run build` futott, így a dist kimenet és a szerverre szinkronizálandó csomag már az új prompttal készül.

## Következő lépések
1. Újra futtasd a batch QA scriptet; ellenőrizd, hogy a welcome menü és a döntési flow most már megfelel-e.
2. Ha szükséges, finomhangold a flow súlyokat/knowledge mappinget az állatvédős és átláthatósági use case-ekhez.
