feat(impactshop): <RÖVID LEÍRÁS> — pl. "Dognet totals REST + cache finomhangolás"

Kiadási jegyzet:
- Újdonságok:
  - ...
- Változások:
  - ...
- Migráció / teendő:
  - ...

Ellenőrző lista (staging → prod):
[ ] Repo tiszta (git status üres)
[ ] .deploy.staging.env MAPPINGS naprakész
[ ] ./bin/staging-qa-suite.sh zöld
[ ] ./bin/deploy-wpcontent-map.sh lefutott → app-staging friss
[ ] Staging UI/shortcode/REST manuál tesztelve
[ ] Cron guard él (percenként/5 perc)

Go/No-Go:
[ ] Nincs kritikus regresszió
[ ] Cache TTL-ek rendben (ticker 180s, activity 120s, leaderboard 300s, report 15m)
[ ] GA4/UTM események a helyükön

Deploy to PROD:
  ./bin/deploy-wpcontent-map.sh --production

Post-deploy prod ellenőrzés:
[ ] SSH ls -l plugins / mu-plugins
[ ] wp plugin list (aktív állapot)
[ ] Rövidkódok renderelnek
[ ] /wp-json/impactshop/v1/totals válaszol
[ ] GA4 eventek lőnek

Rollback:
- git revert a kiadási commitra
- újra deploy
- rewrite flush: ?impactshop_refresh=1
