<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function wp_parse_url(string $url, int $component = -1)
{
    return parse_url($url, $component);
}
function home_url(string $path = '/'): string
{
    global $test_home_path;
    return 'https://app.sharity.hu' . $test_home_path . ltrim($path, '/');
}
function untrailingslashit(string $value): string
{
    return rtrim($value, '/');
}

require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php';

function assert_same(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " expected=" . $expected . " actual=" . $actual);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$test_home_path = '';
assert_same('/profil', impactshop_identity_profile_route_path('/profil/?tab=account'), 'root profile route');
assert_true(impactshop_identity_is_profile_route('/profil/?tab=account'), 'root profile route must be classified');
assert_same('/profil/belepesi-kod', impactshop_identity_profile_route_path('/profil/belepesi-kod/?once=1'), 'root reveal route');
assert_same('/profil/belepesi-kod/', impactshop_identity_profile_reveal_cookie_path(), 'root reveal cookie path');
assert_true(!impactshop_identity_is_profile_route('/profilish/?tab=account'), 'root near miss must not be classified');
assert_true(!impactshop_identity_is_profile_route('/foo/profil/?tab=account'), 'nested foreign route must not be classified');

$test_home_path = '/impactshop-staging';
assert_same('/profil', impactshop_identity_profile_route_path('/impactshop-staging/profil/?tab=account'), 'subdirectory profile route');
assert_true(impactshop_identity_is_profile_route('/impactshop-staging/profil/?tab=account'), 'subdirectory profile route must be classified');
assert_same('/profil/belepesi-kod', impactshop_identity_profile_route_path('/impactshop-staging/profil/belepesi-kod/?once=1'), 'subdirectory reveal route');
assert_same('/impactshop-staging/profil/belepesi-kod/', impactshop_identity_profile_reveal_cookie_path(), 'subdirectory reveal cookie path');
assert_true(!impactshop_identity_is_profile_route('/impactshop-stagingx/profil/?tab=account'), 'subdirectory prefix near miss must not be classified');
assert_true(!impactshop_identity_is_profile_route('/other/impactshop-staging/profil/?tab=account'), 'foreign nested subdirectory must not be classified');

echo "impactshop profile route path behavior: PASS\n";
