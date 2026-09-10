<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('YEAR_IN_SECONDS', 31536000);

function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_rewrite_rule(...$args): void {}
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function is_ssl(): bool { return true; }

require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-boot.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_COOKIE = [];
$_GET = [];
$_SERVER['REQUEST_URI'] = '/subsite/profil/';
assert_true(impactshop_identity_should_touch_cookie() === true, 'ordinary subdirectory HTML must touch cookies');

$_SERVER['REQUEST_URI'] = '/subsite/wp-json/impact/v1/identity/profile';
assert_true(impactshop_identity_should_touch_cookie() === false, 'pretty REST under a subdirectory must be excluded');

$_SERVER['REQUEST_URI'] = '/subsite/profil/';
$_GET = ['rest_route' => '/impact/v1/identity/profile'];
assert_true(impactshop_identity_should_touch_cookie() === false, 'non-empty scalar rest_route must be excluded');

$_GET = ['rest_route' => ''];
assert_true(impactshop_identity_should_touch_cookie() === true, 'empty scalar rest_route is not a REST signal');

$_GET = ['rest_route' => ['malformed']];
assert_true(impactshop_identity_should_touch_cookie() === true, 'array rest_route must not be trusted');

// The boot callback checks impact_pseudo_id before the shared predicate, so a
// legacy/query compatibility override remains intact even beside rest_route.
$_GET = [
    'rest_route' => '/impact/v1/identity/profile',
    'impact_pseudo_id' => 'ABC123456789',
];
impactshop_identity_ensure_pseudo_cookie();
assert_true(
    ($_COOKIE['impactshop_pseudo_id'] ?? '') === 'ABC123456789',
    'impact_pseudo_id query compatibility must remain intact'
);

echo "impactshop identity cookie-touch behavior: PASS\n";
