# 89. Beszélgetés összefoglaló: Story shopping step2 fix

## Áttekintés
A story guard WARN oka az volt, hogy a shopping follow-up üzeneteknél az AI válasz `intent` mezője üres maradt, így nem jött létre `story_shopping_step2` esemény. Az API logikáját módosítottam, hogy a kategória intent megmaradjon, majd újra lefuttattam a lokális QA-t és a `guard:story` riportot.

## Módosítások
- `apps/api-gateway/src/index.ts`: bevezettem egy `normalizedIntent` változót; ha `isShoppingFollowUp` igaz, a `recommendation.intent` hiánya esetén is `category`-t írunk vissza (response payload, session snapshot, guard log). A `computeStoryEvent` most ezt az értéket használja, így a második lépés `story_shopping_step2`-t generál.

## Teszt
1. `node dist/apps/api-gateway/src/index.js` → `curl` (`session_id=storyfix`): első üzenet kategória ajánlat, második üzenet konkrét linket kér 20k Ft körül – a logban a második válasz már `intent=category` lett.
2. `npm run guard:story` → `.codex/logs/story-guard.log` most mind az öt lépést lefedettnek jelzi (`story_shopping_step2` = 1 találat, session storyfix), így a guard WARN megszűnt.
