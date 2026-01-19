# 312. beszélgetés összefoglaló

- CJ /go ág javítva: CJ shopoknál a CJ click URL elsődleges, a `sid` most `d1~pseudo` formában épül (cookie: `impactshop_pseudo_id`).
- Érintett fájlok: `wp-content/mu-plugins/impactshop-boot.php`, `wp-content/mu-plugins/impactshop-go-bridge.php`.
- Deploy: fájlok rsync prod+stagingre, cache flush mindkét környezeten.
- Teszt: /go hívás `impactshop_pseudo_id=TESTPSEUDO123` cookie-val → 307 redirect CJ click URL-re, `sid=teszt-ngo~TESTPSEUDO123`.
- Log ellenőrzés: `impactshop-go-clicks.log` nem frissült az új tesztre, ezért a log‑oldali verifikáció hiányzik.
