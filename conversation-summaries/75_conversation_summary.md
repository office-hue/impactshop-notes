# 75. Beszélgetés összefoglaló: GPT-s scenárió bank integráció

## Áttekintés
Beépítettem a GPT által adott „Impi gyakorló pálya” promptcsomagot az `Impi Tudásbázis/AI-training-pack.md` dokumentumba, hogy egységes helyen legyen minden QA-szcenárió.

## Fő változások
- Új „Szenárió bank” szekció (S1–S9 + metapromptok), amely minden promptnál tartalmazza a felhasználói példát és az elvárt viselkedés checklistjét.
- A gyűjtemény lefedi az alap köszönést, vásárlás+NGO flow-kat, transzparencia/leaderboard/feedback témákat, valamint multi-turn és meta QA teszteket.
- A jövőbeli batch QA futások innentől erre a táblára hivatkozhatnak, biztosítva, hogy a setup prompt módosításai ténylegesen megfelelnek a könyv szerinti követelményeknek.

## Következő lépések
1. Válassz ki néhány S- és M-szcenáriót a legközelebbi batch futtatáshoz.
2. Ha bármelyik checklist sérül, módosítsd a setup promptot vagy a flow súlyokat (conversation-map / knowledge aliasok).
