# 77. Beszélgetés összefoglaló: setup prompt deploy + extra QA

## Áttekintés
A GPT-ből származó teljes setup instrukciót beépítettem az ai-agent rendszerpromptjába, deployoltam a cp40-es szolgáltatásra, majd lefuttattam az „Extra teszt promptok” 8-as batchét és értékeltem a válaszokat a kritikus-barát checklist szerint.

## Lépesek
- `apps/api-gateway/src/services/impi-openai.ts` új system promptot kapott (teljes 5 lépéses mérlegelési blokk, flow hivatkozások). `npm run build` → `rsync` → szerver oldali `npm install --omit=dev` → `~/ai-agent-service.js` restart (PID 584435).
- Batch teszt: `ssh sharityh@cp40.ezit.hu curl ...` lefuttatva a 8 extra prompttal (telefontok, videós támogatás, átláthatóság, Bátor Tábor kupon, gyerek vs. állat, videós riport késés, max NGO hatás, döntési logika).

## Megfigyelések
- Válaszok több esetben visszacsúsztak a generikus shop fallbackre (videós / átláthatósági kérdéseknél is Mobilfox/Online Márkaboltok jött elő).
- Nem történt 5 lépéses mérlegelés vagy NGO listázás (pl. gyerek vs. állat, telefontok + állatvédelem).
- Átláthatósági kérésekhez nincs REST/Impact link, hibakezelésnél shop CTA érkezett.

## Következő lépések
1. Tovább kell finomhangolni a flow-súlyokat / knowledge mappinget, hogy bizonyos kulcsszavak (videós támogatás, átláthatóság, gyerek vs. állat) ne essenek vissza a shop fallbackre.
2. Újabb QA run a javítások után, a „kritikus barát” checklist pontozásával.
