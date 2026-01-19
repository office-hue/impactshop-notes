# 299. Beszélgetés összefoglaló: staging QA hibák vizsgálata

- Az `access-guard.sh` script nem található a gépen (nem a `~/impact-tools` alatt, sem a GitHub/Developer repókban), ezért a `doctor` nem futtatható.
- A `/go?u=...` és `/go-deal?u=...` staging végpontok 500-asai a válasz alapján hiányzó `shop` paraméter miatt vannak („Hiányzó paraméter (shop)”).
- A `/go/<slug>` és `/go-deal/<slug>` 403-as státusza a végső Dognet célon jön (Cloudflare challenge), WordPress oldalról 307 redirect rendben.
- Stagingen az `Impact_Safety` osztály elérhető (MU plugin `impact-safety-loader.php` jelen), viszont az `impact_disable_link_guard` opció nem létezik, így a `link_guard flag` teszt FAIL.
