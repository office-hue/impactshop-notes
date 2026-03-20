<?php
/**
 * Plugin Name: ImpactShop REST Coupons
 * Description: Exposes /wp-json/impactshop/v1/coupons so clients can fetch the current coupon/deal feed.
 * Author: ImpactShop
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
    function dognet_extract_campaign_id_from_base($dognet_base)
    {
        if (!$dognet_base) {
            return 0;
        }
        $qs = parse_url($dognet_base, PHP_URL_QUERY);
        if (!$qs) {
            return 0;
        }
        parse_str($qs, $parts);
        if (isset($parts['cid'])) {
            return intval($parts['cid']);
        }
        if (isset($parts['campaign_id'])) {
            return intval($parts['campaign_id']);
        }
        return 0;
    }
}

if (!function_exists('dognet_get_token')) {
    function dognet_get_token($force = false)
    {
        $cacheKey = 'dognet_api_token_cache_v1';
        if (!$force) {
            $cached = get_transient($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $attempts = [
            ['POST', '/auth/login', ['email' => DOGNET_LOGIN_EMAIL ?? '', 'password' => DOGNET_LOGIN_PASSWORD ?? ''], 'json'],
            ['POST', '/auth/login', ['email' => DOGNET_LOGIN_EMAIL ?? '', 'password' => DOGNET_LOGIN_PASSWORD ?? ''], 'form'],
        ];

        foreach ($attempts as $attempt) {
            [$method, $path, $payload, $type] = $attempt;
            $args = [
                'timeout' => 20,
                'method'  => $method,
                'headers' => [
                    'Content-Type' => $type === 'json' ? 'application/json' : 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
                'body'    => ($type === 'json') ? wp_json_encode($payload) : http_build_query($payload),
            ];
            $resp = wp_remote_request('https://api.app.dognet.com/api/v1' . $path, $args);
            if (is_wp_error($resp)) {
                continue;
            }
            $code = wp_remote_retrieve_response_code($resp);
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if ($code >= 200 && $code < 300) {
                $token = '';
                foreach (['token', 'access_token'] as $key) {
                    if (!empty($body[$key])) {
                        $token = $body[$key];
                        break;
                    }
                }
                if (!$token && isset($body['data']) && is_array($body['data'])) {
                    foreach (['token', 'access_token'] as $key) {
                        if (!empty($body['data'][$key])) {
                            $token = $body['data'][$key];
                            break;
                        }
                    }
                }
                if ($token) {
                    set_transient($cacheKey, $token, defined('DOGNET_TOKEN_TTL') ? DOGNET_TOKEN_TTL : 20 * HOUR_IN_SECONDS);
                    return $token;
                }
            }
        }

        return '';
    }
}

if (!function_exists('dognet_api_request')) {
    function dognet_api_request($method, $path, $body = null)
    {
        $token = dognet_get_token(false);
        if (!$token) {
            return new WP_Error('dognet_no_token', 'Dognet API token nem elérhető');
        }

        $base = defined('DOGNET_API_BASE') ? rtrim(DOGNET_API_BASE, '/') : 'https://api.app.dognet.com/api/v1';
        $url  = $base . $path;

        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];
        if ($body !== null) {
            $args['body'] = is_string($body) ? $body : wp_json_encode($body);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code === 401) {
            delete_transient('dognet_api_token_cache_v1');
            $token = dognet_get_token(true);
            if ($token) {
                return dognet_api_request($method, $path, $body);
            }
        }

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'dognet_api_error',
                sprintf('Dognet API hiba (HTTP %d)', $code),
                ['code' => $code, 'response' => $json ?: $raw, 'path' => $path, 'method' => $method]
            );
        }

        return $json ?: [];
    }
}

if (!function_exists('impactshop_rest_fetch_shops_csv')) {
    function impactshop_rest_fetch_shops_csv(): array
    {
        $cacheKey = 'impactshop_rest_csv_shops';
        $cached   = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $url = defined('IMPACTSHOP_SHOPS_CSV_URL')
            ? IMPACTSHOP_SHOPS_CSV_URL
            : 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv';

        $resp = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($resp)) {
            return [];
        }
        $body = wp_remote_retrieve_body($resp);
        if ($body === '') {
            return [];
        }
        if (strncmp($body, "\xEF\xBB\xBF", 3) === 0) {
            $body = substr($body, 3);
        }

        $lines = preg_split("/\r\n|\n|\r/", trim($body));
        if (!$lines) {
            return [];
        }

        $first   = array_shift($lines);
        $delim   = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
        $headers = str_getcsv($first, $delim);
        $rows    = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, $delim);
            $row  = [];
            foreach ($headers as $idx => $key) {
                $row[$key] = $cols[$idx] ?? '';
            }
            $rows[] = $row;
        }

        set_transient($cacheKey, $rows, 15 * MINUTE_IN_SECONDS);
        return $rows;
    }
}

if (!function_exists('impactshop_rest_coupon_shops')) {
    function impactshop_rest_coupon_shops(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $rawRows = function_exists('impactshop_get_shops')
            ? (array)impactshop_get_shops()
            : impactshop_rest_fetch_shops_csv();

        $map = [];
        foreach ($rawRows as $shop) {
            $slug = sanitize_title($shop['shop_slug'] ?? ($shop['slug'] ?? ($shop['go_slug'] ?? '')));
            if ($slug === '') {
                continue;
            }

            $name = $shop['name'] ?? ($shop['nev'] ?? $slug);
            $logo = $shop['logo_url'] ?? ($shop['logo'] ?? ($shop['image'] ?? ''));
            $category = $shop['category'] ?? ($shop['kategoria'] ?? '');
            $default_d1 = sanitize_title($shop['default_d1'] ?? '');
            $dognet_base = $shop['dognet_base'] ?? ($shop['base_url'] ?? '');

            $map[$slug] = [
                'name'       => $name ?: $slug,
                'cid'        => dognet_extract_campaign_id_from_base($dognet_base),
                'logo'       => $logo,
                'category'   => $category,
                'default_d1' => $default_d1,
                'site'       => $shop['product_url'] ?? ($shop['homepage'] ?? ($shop['site'] ?? '')),
            ];
        }

        return $cache = $map;
    }
}

if (!function_exists('impactshop_rest_coupons_handler')) {
    function impactshop_rest_coupons_handler(WP_REST_Request $request)
    {
        $perPage   = (int)($request->get_param('per_page') ?: $request->get_param('limit') ?: 100);
        $perPage   = max(1, min(500, $perPage));
        $validity  = $request->get_param('validity') ?: 'present';
        $shopParam = sanitize_title($request->get_param('shop') ?: '');

        $body = [
            'per-page' => $perPage,
            'filter'   => [
                ['validity' => ['eq' => $validity]],
            ],
        ];
        if ($shopParam !== '') {
            $body['filter'][] = ['shop_slug' => ['eq' => $shopParam]];
        }
        if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID) {
            $body['ad_channel_id'] = (int)DOGNET_AD_CHANNEL_ID;
        }

        $resp = dognet_api_request('POST', '/coupons/filter', $body);
        if (is_wp_error($resp)) {
            return new WP_REST_Response([
                'code'    => $resp->get_error_code(),
                'message' => $resp->get_error_message(),
            ], 502);
        }

        $rawItems = $resp['data'] ?? ($resp['items'] ?? []);
        if (!is_array($rawItems)) {
            $rawItems = [];
        }

        $shopMap  = impactshop_rest_coupon_shops();
        $cidIndex = [];
        foreach ($shopMap as $slug => $info) {
            if (!empty($info['cid'])) {
                $cidIndex[(int)$info['cid']] = $slug;
            }
        }

        $items = [];
        foreach ($rawItems as $item) {
            $cid = 0;
            foreach (['campaign_id', 'campaignId', 'cid', 'campaign'] as $key) {
                if (isset($item[$key])) {
                    $cid = is_array($item[$key]) ? intval($item[$key]['id'] ?? 0) : intval($item[$key]);
                    break;
                }
            }
            $shopSlug = $cid && isset($cidIndex[$cid]) ? $cidIndex[$cid] : '';
            if ($shopSlug === '' && !empty($item['shop_slug'])) {
                $shopSlug = sanitize_title($item['shop_slug']);
            }

            $shopMeta = $shopSlug && isset($shopMap[$shopSlug]) ? $shopMap[$shopSlug] : null;
            $shopName = $shopMeta['name'] ?? ($item['shop_name'] ?? $shopSlug);

            $items[] = [
                'title'        => $item['title'] ?? ($item['name'] ?? ''),
                'description'  => $item['description'] ?? '',
                'code'         => $item['code'] ?? ($item['coupon'] ?? ''),
                'url'          => $item['url'] ?? ($item['deeplink'] ?? ''),
                'valid_from'   => $item['valid_from'] ?? ($item['start_date'] ?? ''),
                'valid_to'     => $item['valid_to'] ?? ($item['end_date'] ?? ''),
                'shop_slug'    => $shopSlug,
                'shop_name'    => $shopName ?: $shopSlug,
                'shop_logo'    => $shopMeta['logo'] ?? '',
                'shop_category'=> $shopMeta['category'] ?? '',
                'shop_site'    => $shopMeta['site'] ?? '',
                'default_d1'   => $shopMeta['default_d1'] ?? '',
                'campaign_id'  => $cid,
                'discount'     => $item['discount_value'] ?? '',
                'currency'     => $item['currency'] ?? '',
            ];
        }

        return [
            'items' => $items,
            'meta'  => [
                'count'       => count($items),
                'per_page'    => $perPage,
                'validity'    => $validity,
                'generated_at'=> current_time('mysql'),
            ],
        ];
    }
}

add_action('rest_api_init', function () {
    register_rest_route('impactshop/v1', '/coupons', [
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback'            => 'impactshop_rest_coupons_handler',
        'args'                => [
            'per_page' => [
                'description' => 'Maximum coupons to return (1-500).',
                'type'        => 'integer',
            ],
            'validity' => [
                'description' => 'Dognet validity filter (present|future|past).',
                'type'        => 'string',
            ],
            'shop' => [
                'description' => 'Optional shop slug filter.',
                'type'        => 'string',
            ],
        ],
    ]);
});
