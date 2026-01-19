# 345. Beszélgetés összefoglaló: WP test bootstrap + PHPUnit retry

Kérés: WP test bootstrap beállítása és PHPUnit újrafuttatás.

- Bootstrap: `bin/install-wp-tests.sh` + `tests/bootstrap.php`.
- Retry: `WP_TESTS_DIR=tests/wordpress-tests-lib vendor/bin/phpunit tests/test-impactshop-identity-pin.php`.
- Hiba: nincs MySQL kapcsolat (mysqli_real_connect / DB nincs).
