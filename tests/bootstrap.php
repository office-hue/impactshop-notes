<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = __DIR__ . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(
        STDERR,
        "WP tests not found. Run: bash bin/install-wp-tests.sh <db> <user> <pass> <host> <version>\n"
    );
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugins(): void
{
    require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-pin.php';

    $wallet_candidates = [
        dirname(__DIR__) . '/wp-content/plugins/impactshop-wallet/impactshop-wallet.php',
        dirname(__DIR__) . '/wp-content/plugins/impactshop-wallet.php',
        dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-wallet.php',
    ];

    $wallet_env_paths = getenv('IMPACTSHOP_WALLET_PLUGIN_PATHS');
    if (is_string($wallet_env_paths) && $wallet_env_paths !== '') {
        $extra_candidates = preg_split('/[,:;]/', $wallet_env_paths);
        if (is_array($extra_candidates)) {
            foreach ($extra_candidates as $candidate) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $wallet_candidates[] = $candidate;
                }
            }
        }
    }

    foreach ($wallet_candidates as $wallet_path) {
        if (file_exists($wallet_path)) {
            require_once $wallet_path;
            break;
        }
    }

    if (!function_exists('impactshop_wallet_plugin_loaded')) {
        function impactshop_wallet_plugin_loaded(): bool
        {
            return class_exists('ImpactShop_Wallet_Passes', false);
        }
    }

    if (!function_exists('impactshop_tests_reset_routes')) {
        function impactshop_tests_reset_routes(): void
        {
            global $wp_rest_server;
            $wp_rest_server = new WP_REST_Server();
        }
    }

    if (!function_exists('impactshop_tests_registered_routes')) {
        function impactshop_tests_registered_routes(): array
        {
            $server = rest_get_server();
            $routes = $server->get_routes();
            $items = [];

            foreach ($routes as $route => $handlers) {
                foreach ($handlers as $handler) {
                    $items[] = [
                        'namespace'           => $handler['namespace'] ?? '',
                        'route'               => $route,
                        'args'                => $handler['args'] ?? [],
                        'permission_callback' => $handler['permission_callback'] ?? null,
                    ];
                }
            }

            return $items;
        }
    }
}

tests_add_filter('muplugins_loaded', '_manually_load_plugins');

require $_tests_dir . '/includes/bootstrap.php';
