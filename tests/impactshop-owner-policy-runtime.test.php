<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$test_options = [];
$debug_enabled = false;

class WP_REST_Request {}
class WP_REST_Response
{
    public function header(string $name, string $value): void {}
}

function add_filter(...$args): void {}
function add_action(...$args): void {}
function current_user_can(string $capability): bool { return false; }
function get_option(string $key, $default = false) { global $test_options; return $test_options[$key] ?? $default; }
function update_option(string $key, $value, bool $autoload = false): bool { global $test_options; $test_options[$key] = $value; return true; }
function impactshop_ads_watch_debug_enabled(): bool { global $debug_enabled; return $debug_enabled; }

require dirname(__DIR__) . '/wp-content/mu-plugins/000-impactshop-owner-policy.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class ImpactshopPolicyTestServer
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function get_routes(): array
    {
        return $this->routes;
    }
}

function build_routes(bool $include_debug): array
{
    $routes = [];
    foreach (impactshop_owner_policy_registry() as $pattern => $entry) {
        [$method, $route] = explode(' ', $pattern, 2);
        if (!$include_debug && $pattern === 'GET /impact/v1/ads-watch/debug-rotation') {
            continue;
        }
        $routes[$route][] = [
            'methods' => $method,
            'callback' => $entry['callback'],
        ];
    }
    return $routes;
}

$wp_rest_server = new ImpactshopPolicyTestServer(build_routes(false));
$debug_enabled = false;
test_assert(impactshop_owner_policy_runtime_self_test() === true, 'disabled debug route must be optional');
test_assert(impactshop_owner_policy_safe_disabled() === false, 'optional debug route must not safe-disable');

$debug_enabled = true;
test_assert(impactshop_owner_policy_runtime_self_test() === false, 'enabled missing debug route must fail');
test_assert(impactshop_owner_policy_safe_disabled() === true, 'enabled missing debug route must safe-disable');

$test_options[IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE_OPTION] = 0;
$wp_rest_server = new ImpactshopPolicyTestServer(build_routes(true));
test_assert(impactshop_owner_policy_runtime_self_test() === true, 'enabled registered debug route must pass');
test_assert(impactshop_owner_policy_safe_disabled() === false, 'valid debug route must remain enabled');

echo "impactshop owner-policy conditional runtime: PASS\n";
