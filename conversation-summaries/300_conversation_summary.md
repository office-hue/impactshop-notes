# 300. Beszélgetés összefoglaló: staging QA suite javítás

- A staging QA suite redirect tesztjei most `shop=` paraméterrel futnak és nem követik a külső redirecteket (curl `-sI`), így nem futnak bele a Dognet Cloudflare 403-ba.
- Az `Impact_Safety exists` teszt SSH-n fut, nem lokálisan.
- A frissített QA kör 21/21 PASS lett (log: `staging-qa-20260115-104802.log`).
