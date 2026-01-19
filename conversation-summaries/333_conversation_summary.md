# 333. Beszélgetés összefoglaló: PIN MU deploy + staging smoke

Kérés: PIN MU pluginok deployolása stagingre és smoke teszt.

- Deploy: MU pluginek rsync-kelve `/home/sharityh/app-staging/wp-content/mu-plugins`.
- Rewrite flush: `/usr/local/bin/wp --path=/home/sharityh/app-staging rewrite flush --hard`.
- Smoke: `POST /impact/v1/identity/pin/issue` (sms) → `status=ok`, `delivery.status=queued`.
- Megjegyzés: PHP-FPM reload nem futott (sudo nincs), endpoint mégis elérhető.
