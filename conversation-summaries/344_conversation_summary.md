# 344. Beszélgetés összefoglaló: PHPUnit smoke (PIN)

Kérés: PHPUnit teszt futtatása.

- Parancs: `vendor/bin/phpunit tests/test-impactshop-identity-pin.php`.
- Hiba: `WP_UnitTestCase` hiányzik (WP tesztkörnyezet nem inicializált).
- Következő: WP test bootstrap beállítása után újra futtatni.
