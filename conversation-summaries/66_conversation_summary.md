# 66. Beszélgetés összefoglaló: Impi AI training pack

## Áttekintés
A kérés szerint nem elég, hogy Impi betölti a tudásbázist: konkrét tréningcsomagra volt szükség, amely a w_pacc79.pdf módszertanát alkalmazza Sharity környezetben. Létrehoztam egy részletes, lépésről lépésre használható dokumentumot, beillesztettem a tudásindexbe, majd deployoltam a frissítést a cp40-es AI szolgáltatásra.

## Fő lépések
- Új `Impi Tudásbázis/AI-training-pack.md` fájl: célok, tudásforrások, prompt/setup sablon, gyakorló szkriptek, QA checklist és iterációs ciklus – mind a Sharity döntési mechanizmusaira szabva.
- `knowledge-aliases.json` → `knowledge_files` listába bekerült a training pack, új `tudasbazis-impi-training-pack` topic jött létre, majd `npm run build` biztosította, hogy a `dist/` mappa is tartalmazza.
- `rsync -az --delete ./ sharityh@cp40.ezit.hu:~/ai-agent` + `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` újraindította az API-t, így a szerveren is elérhető az új tréninganyag.

## Következő lépések
- A training packban szereplő gyakorló promptokat futtasd végig (ranglista, videós támogatás, döntési mechanizmus, átláthatóság). Jegyezd fel a tapasztalatokat, és ha szükséges, bővítsd a dokumentumot új szabályokkal vagy példákkal.
- Szerezz Playwright scraper adatot, hogy a `/healthz` riportból kikerüljön a hiányzó feature, és a tréning során valós, friss ajánlatok álljanak rendelkezésre.
