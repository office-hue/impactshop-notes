<?php
/**
 * Plugin Name: Impact Totals Cache
 * Description: Cache /impact/v1/totals responses for a short TTL to reduce load.
 * Version: 1.0.0
 * Author: ImpactShop
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACT_TOTALS_CACHE_TTL')) {
    define('IMPACT_TOTALS_CACHE_TTL', 300);
}

if (!defined('IMPACT_TOTALS_CACHE_STALE_TTL')) {
    define('IMPACT_TOTALS_CACHE_STALE_TTL', 900);
}

if (!defined('IMPACT_TOTALS_CACHE_LOCK_TTL')) {
    define('IMPACT_TOTALS_CACHE_LOCK_TTL', 30);
}

if (!defined('IMPACT_TOTALS_PREWARM_INTERVAL')) {
    define('IMPACT_TOTALS_PREWARM_INTERVAL', 120);
}

function impact_totals_cache_normalized_query(array $query): array
{
    $drop_keys = [
        '_locale',
        '_nocache',
        '_wpnonce',
        'rest_route',
        'ts',
        't',
        'cache_bust',
        'rand',
    ];
    foreach ($drop_keys as $key) {
        if (array_key_exists($key, $query)) {
            unset($query[$key]);
        }
    }
    return $query;
}

function impact_totals_cache_key(WP_REST_Request $request): string
{
    $route = $request->get_route();
    $query = impact_totals_cache_normalized_query($request->get_query_params());
    ksort($query);
    $qs = $query ? http_build_query($query) : '';
    return 'impact_totals_cache_' . md5($route . '|' . $qs);
}

function impact_totals_cache_option_key(string $cache_key): string
{
    return $cache_key . '_opt';
}

function impact_totals_cache_payload(WP_REST_Response $response): array
{
    $status = $response->get_status();
    return [
        'data'          => $response->get_data(),
        'status'        => $status,
        'expires'       => time() + IMPACT_TOTALS_CACHE_TTL,
        'stale_expires' => time() + IMPACT_TOTALS_CACHE_STALE_TTL,
    ];
}

function impact_totals_cache_store(WP_REST_Request $request, WP_REST_Response $response): void
{
    $status = $response->get_status();
    if ($status < 200 || $status >= 300) {
        return;
    }

    $cache_key = impact_totals_cache_key($request);
    $payload = impact_totals_cache_payload($response);

    set_transient($cache_key, $payload, IMPACT_TOTALS_CACHE_TTL);
    set_transient($cache_key . '_stale', $payload, IMPACT_TOTALS_CACHE_STALE_TTL);
    update_option(impact_totals_cache_option_key($cache_key), $payload, false);
    update_option(impact_totals_cache_option_key($cache_key . '_stale'), $payload, false);
}

function impact_totals_cache_schedule_refresh(array $query): void
{
    $key = 'impact_totals_cache_lock_' . md5(wp_json_encode($query));
    if (get_transient($key)) {
        return;
    }
    set_transient($key, 1, IMPACT_TOTALS_CACHE_LOCK_TTL);
    wp_schedule_single_event(time(), 'impact_totals_cache_refresh', [$query]);
}

add_action('impact_totals_cache_refresh', function (array $query): void {
    $query['_nocache'] = '1';
    $request = new WP_REST_Request('GET', '/impact/v1/totals');
    $request->set_query_params($query);
    $response = rest_do_request($request);
    if ($response instanceof WP_REST_Response) {
        impact_totals_cache_store($request, $response);
    }
});

add_filter('cron_schedules', function (array $schedules): array {
    if (!isset($schedules['impact_totals_every_2min'])) {
        $schedules['impact_totals_every_2min'] = [
            'interval' => IMPACT_TOTALS_PREWARM_INTERVAL,
            'display'  => 'Impact totals prewarm (2 min)',
        ];
    }
    return $schedules;
});

add_action('init', function (): void {
    if (!wp_next_scheduled('impact_totals_cache_prewarm')) {
        wp_schedule_event(time() + 15, 'impact_totals_every_2min', 'impact_totals_cache_prewarm');
    }
});

add_action('impact_totals_cache_prewarm', function (): void {
    $lock_key = 'impact_totals_cache_prewarm_lock';
    if (get_transient($lock_key)) {
        return;
    }
    set_transient($lock_key, 1, IMPACT_TOTALS_CACHE_LOCK_TTL);

    $request = new WP_REST_Request('GET', '/impact/v1/totals');
    $request->set_query_params(['_nocache' => '1']);
    $response = rest_do_request($request);
    if ($response instanceof WP_REST_Response) {
        impact_totals_cache_store($request, $response);
    }
});

add_filter('rest_pre_dispatch', function ($result, WP_REST_Server $server, WP_REST_Request $request) {
    if ($request->get_method() !== 'GET' || $request->get_route() !== '/impact/v1/totals') {
        return $result;
    }

    if ($request->get_param('_nocache')) {
        return $result;
    }

    $cache_key = impact_totals_cache_key($request);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        $response = rest_ensure_response($cached['data']);
        $status = isset($cached['status']) ? (int)$cached['status'] : 200;
        $response->set_status($status);
        $response->header('X-Impact-Totals-Cache', 'HIT');
        $response->header('Cache-Control', 'public, max-age=' . IMPACT_TOTALS_CACHE_TTL);
        return $response;
    }

    $opt = get_option(impact_totals_cache_option_key($cache_key));
    if (is_array($opt) && ($opt['expires'] ?? 0) >= time()) {
        $response = rest_ensure_response($opt['data'] ?? []);
        $status = isset($opt['status']) ? (int)$opt['status'] : 200;
        $response->set_status($status);
        $response->header('X-Impact-Totals-Cache', 'HIT-OPT');
        $response->header('Cache-Control', 'public, max-age=' . IMPACT_TOTALS_CACHE_TTL);
        return $response;
    }

    $stale = get_transient($cache_key . '_stale');
    if ($stale !== false) {
        impact_totals_cache_schedule_refresh($request->get_query_params());
        $response = rest_ensure_response($stale['data']);
        $status = isset($stale['status']) ? (int)$stale['status'] : 200;
        $response->set_status($status);
        $response->header('X-Impact-Totals-Cache', 'STALE');
        $response->header('Cache-Control', 'public, max-age=' . IMPACT_TOTALS_CACHE_TTL);
        return $response;
    }

    $stale_opt = get_option(impact_totals_cache_option_key($cache_key . '_stale'));
    if (is_array($stale_opt) && ($stale_opt['stale_expires'] ?? 0) >= time()) {
        impact_totals_cache_schedule_refresh($request->get_query_params());
        $response = rest_ensure_response($stale_opt['data'] ?? []);
        $status = isset($stale_opt['status']) ? (int)$stale_opt['status'] : 200;
        $response->set_status($status);
        $response->header('X-Impact-Totals-Cache', 'STALE-OPT');
        $response->header('Cache-Control', 'public, max-age=' . IMPACT_TOTALS_CACHE_TTL);
        return $response;
    }

    return $result;
}, 10, 3);

add_filter('rest_post_dispatch', function ($response, WP_REST_Server $server, WP_REST_Request $request) {
    if ($request->get_method() !== 'GET' || $request->get_route() !== '/impact/v1/totals') {
        return $response;
    }

    if ($request->get_param('_nocache')) {
        return $response;
    }

    if ($response instanceof WP_REST_Response) {
        impact_totals_cache_store($request, $response);
        $response->header('X-Impact-Totals-Cache', 'MISS');
        $response->header('Cache-Control', 'public, max-age=' . IMPACT_TOTALS_CACHE_TTL);
    }

    return $response;
}, 10, 3);
