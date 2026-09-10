<?php
/**
 * Structural owner-policy inventory. This intentionally uses token_get_all()
 * rather than grep so registration syntax changes cannot silently evade CI.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$policy = $root . '/wp-content/mu-plugins/000-impactshop-owner-policy.php';
$source = (string) file_get_contents($policy);
$expected_owner = [
    'POST /impact/v1/identity/profile',
    'POST /impact/v1/identity/restore',
    'POST /impact/v1/identity/code/generate',
    'POST /impact/v1/ngo-selector/set',
    'POST /impact/v1/saved-offers/save',
    'POST /impact/v1/push/subscribe',
    'POST /impact/v1/push/unsubscribe',
    'POST /impact/v1/tracking/cta-click',
    'POST /impact/v1/ads-watch/view',
    'POST /impact/v1/ads-watch/education',
    'POST /impact/v1/ads-watch/allocate',
    'POST /impact/v1/ads-watch/set-ngo',
    'POST /impact/v1/ads-watch/set-auto-vote',
    'POST /impact/v1/vote/view',
    'POST /impact/v1/vote/cast',
    'POST /sharity/v1/pseudo/points/earn',
    'POST /sharity/v1/pseudo/vacation',
    'POST /sharity/v1/pseudo/vacation/end',
    'POST /sharity/v1/pseudo/last-ngo',
    'POST /sharity/v1/pseudo/feedback',
    'POST /sharity/v1/pseudo/video-ad',
    'POST /impact/v1/vb2026/select-ngo',
    'POST /impact/v1/vb2026/selection-intent',
    'POST /impact/v1/vb2026/selection-intent/complete',
];

$files = [
    'wp-content/mu-plugins/impactshop-identity-panel.php',
    'wp-content/mu-plugins/impactshop-ngo-selector.php',
    'wp-content/mu-plugins/impactshop-saved-offers.php',
    'wp-content/mu-plugins/impactshop-pwa-push.php',
    'wp-content/mu-plugins/impactshop-click-tracking.php',
    'wp-content/mu-plugins/impactshop-ads-watch.php',
    'wp-content/mu-plugins/impactshop-vote-jysk.php',
    'wp-content/mu-plugins/sharity-points-api.php',
    'wp-content/mu-plugins/sharity-points-events.php',
    'wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php',
];

$namespace_by_file = [
    'wp-content/mu-plugins/impactshop-ads-watch.php' => 'impact/v1',
];

$inventory = [];
$registered_callbacks = [];
foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $text = (string) file_get_contents($path);
    $tokens = token_get_all($text);
    $routes = [];
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'register_rest_route') {
            continue;
        }
        $window = '';
        for ($j = $i; $j < min($count, $i + 80); $j++) {
            $part = $tokens[$j];
            $window .= is_array($part) ? $part[1] : $part;
            if ($part === ';') {
                break;
            }
        }
        if (preg_match_all("/'((?:\\\\'|[^'])*)'/", $window, $matches)) {
            $literals = array_map(static function (string $literal): string {
                return str_replace(["\\\\'", "\\\\\\"], ["'", "\\"], $literal);
            }, $matches[1]);
            $path_literals = array_values(array_filter($literals, static fn(string $value): bool => str_starts_with($value, '/')));
            if (isset($path_literals[0])) {
                $namespace = $namespace_by_file[$relative] ?? '';
                foreach ($literals as $literal) {
                    if (str_contains($literal, '/v1')) {
                        $namespace = trim($literal, '/');
                        break;
                    }
                }
                $endpoint_pairs = [];
                if (preg_match_all("~'methods'\\s*=>\\s*'(GET|POST|PUT|PATCH|DELETE)'.*?'callback'\\s*=>\\s*'([^']+)'~s", $window, $string_endpoint_matches, PREG_SET_ORDER)) {
                    foreach ($string_endpoint_matches as $match) {
                        $endpoint_pairs[] = [$match[1], $match[2]];
                    }
                }
                if (preg_match_all("~'methods'\\s*=>\\s*WP_REST_Server::(READABLE|CREATABLE|EDITABLE|DELETABLE).*?'callback'\\s*=>\\s*'([^']+)'~s", $window, $constant_endpoint_matches, PREG_SET_ORDER)) {
                    foreach ($constant_endpoint_matches as $match) {
                        $endpoint_pairs[] = [
                            ['READABLE' => 'GET', 'CREATABLE' => 'POST', 'EDITABLE' => 'POST', 'DELETABLE' => 'DELETE'][$match[1]],
                            $match[2],
                        ];
                    }
                }
                foreach ($endpoint_pairs as [$method, $callback]) {
                    $route_key = $method . ' /' . ($namespace !== '' ? $namespace : 'UNKNOWN') . $path_literals[0];
                    $routes[] = $route_key;
                    $registered_callbacks[$route_key][] = $callback;
                }
            }
        }
    }
    $inventory[$relative] = array_values(array_unique($routes));
}

$actual = [];
foreach ($inventory as $routes) {
    $actual = array_merge($actual, $routes);
}
$actual = array_values(array_unique($actual));
$registry_entry_literals = [];
$registry_source = substr($source, 0, strpos($source, 'function impactshop_owner_policy_key'));
foreach (token_get_all($registry_source) as $token) {
    if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
        $literal = str_replace(["\\\\'", "\\\\\\"], ["'", "\\"], substr($token[1], 1, -1));
        if (preg_match('~^(?:GET|POST|PUT|PATCH|DELETE) /~', $literal)) {
            $registry_entry_literals[] = $literal;
        }
    }
}
$registry_counts = array_count_values($registry_entry_literals);
$duplicate_policy_routes = array_keys(array_filter($registry_counts, static fn(int $count): bool => $count > 1));
$registry_entries = array_values(array_unique($registry_entry_literals));
$policy_routes = $registry_entries;
$callback_manifest_matches = [];
$callback_manifest_source = substr($source, strpos($source, 'function impactshop_owner_policy_callback_manifest'));
preg_match_all("~'((?:GET|POST|PUT|PATCH|DELETE) /[^']+)'\\s*=>\\s*'([^']+)'~", $callback_manifest_source, $callback_manifest_matches);
$callback_routes = $callback_manifest_matches[1] ?? [];
$callback_names = $callback_manifest_matches[2] ?? [];
$callback_manifest = count($callback_routes) === count($callback_names)
    ? array_combine($callback_routes, $callback_names)
    : [];
$callback_mismatches = [];
foreach ($registered_callbacks as $route => $callbacks) {
    $callbacks = array_values(array_unique($callbacks));
    $expected_callback = is_array($callback_manifest) ? (string) ($callback_manifest[$route] ?? '') : '';
    if (count($callbacks) !== 1 || $callbacks[0] === '' || !hash_equals($expected_callback, $callbacks[0])) {
        $callback_mismatches[] = $route;
    }
}
$missing = array_values(array_diff($expected_owner, $registry_entries));
$unclassified = array_values(array_diff($actual, array_merge($registry_entries, [
    // Dynamic/conditional routes are explicitly represented by their source
    // module and are still required to have a registry classification.
])));
$orphan_registry = array_values(array_diff($registry_entries, $actual));
$missing = array_values(array_unique(array_merge(
    $missing,
    $unclassified,
    $orphan_registry,
    $duplicate_policy_routes,
    $callback_mismatches,
    array_diff($policy_routes, $callback_routes),
    array_diff($callback_routes, $policy_routes)
)));

$result = ['version' => 3, 'expected_owner' => count($expected_owner), 'missing' => $missing, 'actual' => $actual, 'registry' => $registry_entries, 'callback_manifest' => array_values(array_unique($callback_routes)), 'duplicate_policy_routes' => $duplicate_policy_routes, 'callback_mismatches' => $callback_mismatches, 'files' => $inventory];
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
exit($missing === [] ? 0 : 1);
