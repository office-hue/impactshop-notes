# 311. beszélgetés összefoglaló

- Lefuttattam a friss /go hívást CJ shopra: 307 redirect `www.jateknet.hu`-ra; a logban új sor jelent meg `sid` nélkül.
- A prod `impactshop-go-clicks.log` bejegyzésnél `target_host=www.jateknet.hu`, `sid` üres, `ngo=teszt-ngo`, `shop=cj-5619548`, `pseudo` beállt.
- A `impactshop_shops` opcióban a CJ shopnál van `cj_click_url` (`https://www.jdoqocy.com/click-101589464-14448006`), tehát a /go most nem ezt használta.
- Következtetés: a CJ linképítés útvonala valószínűleg nem preferálja a `cj_click_url`-t; ezt kell javítani, hogy `sid` rákerüljön.
