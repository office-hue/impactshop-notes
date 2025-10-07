<?php
/*
 * 001 Impact Performance Guard
 * - REST /impactshop/v1/totals cache (120s)
 * - Optional defensive 500→200 empty rows
 * - Shorter HTTP timeouts for remote calls
 */
if (!defined('ABSPATH')) exit;

define('IMPACT_DEFENSIVE_TOTALS', true); // ideiglenesen bekapcsolható

add_filter('rest_pre_dispatch', function($result, $server, $request){
    if (!($request instanceof WP_REST_Request)) return $result;
    if ($request->get_route() === '/impactshop/v1/totals') {
        $qs = (array)$request->get_query_params();
        ksort($qs);
        $key = 'impact_totals_' . md5(http_build_query($qs));
        $cached = get_transient($key);
        if ($cached instanceof WP_REST_Response) {
            return $cached;
        }
        // átadjuk a kulcsot a későbbi cache-hez
        $request->set_param('_impact_cache_key', $key);
    }
    return $result;
}, 10, 3);

add_filter('rest_request_after_callbacks', function($response, $handler, $request){
    if (!($request instanceof WP_REST_Request)) return $response;
    if ($request->get_route() !== '/impactshop/v1/totals') return $response;

    // WP_Error → opcionális defenzív üres válasz
    if (is_wp_error($response) && defined('IMPACT_DEFENSIVE_TOTALS') && IMPACT_DEFENSIVE_TOTALS) {
        error_log('ImpactShop totals defensive: ' . $response->get_error_message());
        $response = new WP_REST_Response(['rows'=>[], 'note'=>'defensive'], 200);
    }

    // Sikeres válasz cache-elése 120 mp-re
    if ($response instanceof WP_REST_Response) {
        $status = $response->get_status();
        if ($status >= 200 && $status < 300) {
            $key = $request->get_param('_impact_cache_key');
            if ($key) set_transient($key, $response, 120);
        }
    }
    return $response;
}, 10, 3);

// Rövidebb timeout a távoli kérésekre (Dognet / külső API-k) – Sharity hostok kivételek
add_filter('http_request_args', function($args, $url){
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    // Csak dognet domainek és tipikus api.* aldomain – a sharity.hu / app.sharity.hu NEM!
    $is_dognet = (bool)preg_match('~dognet~i', $host);
    $is_api_sub = (bool)preg_match('~^api\..+~i', $host);
    $is_sharity = (bool)preg_match('~(^|\.)sharity\.hu$~i', $host);
    if (($is_dognet || $is_api_sub) && !$is_sharity) {
        $args['timeout'] = min((float)($args['timeout'] ?? 5), 1.5);
        $args['redirection'] = 2;
        $args['blocking'] = true;
    }
    return $args;
}, 10, 2);
