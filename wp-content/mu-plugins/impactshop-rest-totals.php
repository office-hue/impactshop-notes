<?php
/**
 * Plugin Name: ImpactShop REST Totals
 * Description: Minimal /wp-json/impactshop/v1/totals endpoint for sticky + reports.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACTSHOP_CACHE_TTL')) {
    define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
}

if (!function_exists('impactshop_totals_build_campaign_map')) {
    function impactshop_totals_build_campaign_map(): array
    {
        if (!function_exists('impactshop_get_shops') || !function_exists('dognet_extract_campaign_id_from_base')) {
            return [];
        }

        $map = [];
        $shops = impactshop_get_shops();
        foreach ($shops as $shop) {
            $cid = dognet_extract_campaign_id_from_base($shop['dognet_base'] ?? '');
            if ($cid) {
                $map[$cid] = [
                    'shop'      => $shop['name'] ?? '(ismeretlen shop)',
                    'shop_slug' => $shop['shop_slug'] ?? '',
                ];
            }
        }
        return $map;
    }
}

if (!function_exists('impactshop_totals_pick_ngo_from_row')) {
    function impactshop_totals_pick_ngo_from_row(array $row): string
    {
        $keys = [
            'd1',
            'data1',
            'ref1',
            'sub_id',
            'subid',
            'sub_id1',
            'ngo',
            'ngo_name',
            'last_click_data1',
            'last_click_d1',
            'last_click_subid',
            'lc_data1',
            'lc_d1',
            'lc_subid',
        ];

        $cands = [];
        foreach ($keys as $key) {
            if (isset($row[$key]) && !is_array($row[$key])) {
                $value = trim((string)$row[$key]);
                if ($value !== '') {
                    $cands[] = $value;
                }
            }
        }

        if (!empty($row['last_click']) && is_array($row['last_click'])) {
            foreach (['data1', 'd1', 'subid', 'sub_id1', 'sub_id'] as $key) {
                if (!empty($row['last_click'][$key]) && is_string($row['last_click'][$key])) {
                    $cands[] = trim($row['last_click'][$key]);
                }
            }
        }

        if (!$cands) {
            return '';
        }

        foreach ($cands as $value) {
            if (preg_match('~^[a-z0-9._-]{3,}$~i', $value) && preg_match('~[a-z]~i', $value)) {
                return sanitize_title($value);
            }
        }

        foreach ($cands as $value) {
            $value = trim($value);
            if (stripos($value, 'http://') === 0 || stripos($value, 'https://') === 0) {
                $qs = parse_url($value, PHP_URL_QUERY);
                if ($qs) {
                    parse_str($qs, $parts);
                    foreach (['d1', 'ngo', 'org', 'utm_term'] as $key) {
                        if (!empty($parts[$key]) && is_string($parts[$key])) {
                            $candidate = trim($parts[$key]);
                            if ($candidate !== '') {
                                return sanitize_title($candidate);
                            }
                        }
                    }
                }
            }
        }

        foreach ($cands as $value) {
            if (!preg_match('~^\\d+(?:[.,]\\d+)?$~', $value)) {
                return sanitize_title($value);
            }
        }

        return '';
    }
}

if (!function_exists('impactshop_totals_accumulate_row')) {
    function impactshop_totals_accumulate_row(array &$out, array $map, string $group, array $item): void
    {
        $cid = (int)($item['campaign_id'] ?? $item['campaignId'] ?? 0);
        $ngo = impactshop_totals_pick_ngo_from_row($item);
        $shopName = $map[$cid]['shop'] ?? '(ismeretlen shop)';
        $shopSlug = $map[$cid]['shop_slug'] ?? '';
        $ngoName = $ngo !== '' ? $ngo : '(ismeretlen NGO)';
        $keyParts = [$shopSlug, $ngoName];

        if ($group === 'shop') {
            $keyParts = [$shopSlug];
        } elseif ($group === 'ngo') {
            $keyParts = [$ngoName];
        }

        $key = implode('|', $keyParts);
        if (!isset($out[$key])) {
            $out[$key] = [
                'count' => 0,
                'amount' => 0.0,
                'commission' => 0.0,
                'shop_name' => $shopName,
                'shop_slug' => $shopSlug,
                'ngo' => $ngoName,
            ];
        }

        $out[$key]['count']++;
        $out[$key]['amount'] += (float)($item['amount'] ?? $item['price'] ?? 0);
        $out[$key]['commission'] += (float)($item['commission'] ?? $item['publisher_commission'] ?? 0);
    }
}

if (!function_exists('dognet_api_list_conversions_page')) {
    function dognet_api_list_conversions_page(array $params, int $page = 1, int $perPage = 100)
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $payload = ['page' => $page, 'per_page' => $perPage];

        if (!empty($params['date_from'])) {
            $payload['date_from'] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $payload['date_to'] = $params['date_to'];
        }
        if (!empty($params['campaign_id'])) {
            $payload['campaign_id'] = (int)$params['campaign_id'];
        }
        if (!empty($params['status']) && $params['status'] !== 'all') {
            $payload['status'] = $params['status'];
        }

        $candidates = [
            ['POST', '/publisher/conversions/search'],
            ['POST', '/conversions/search'],
            ['GET', '/publisher/conversions'],
            ['GET', '/conversions'],
        ];

        $lastErr = null;
        foreach ($candidates as $cand) {
            [$method, $path] = $cand;
            if ($method === 'GET') {
                $qs = [];
                foreach (['page', 'per_page', 'date_from', 'date_to', 'status', 'campaign_id'] as $key) {
                    if (isset($payload[$key]) && $payload[$key] !== '' && $payload[$key] !== null) {
                        $qs[$key] = $payload[$key];
                    }
                }
                $resp = dognet_api_request('GET', $path . ($qs ? ('?' . http_build_query($qs)) : ''));
            } else {
                $resp = dognet_api_request('POST', $path, $payload);
            }

            if (is_wp_error($resp)) {
                $lastErr = $resp;
                continue;
            }

            $raw = $resp;
            $items = [];
            $lastPage = $page;

            if (isset($raw['data']['data']) && is_array($raw['data']['data'])) {
                $items = $raw['data']['data'];
                $lastPage = (int)($raw['data']['last_page'] ?? $page);
                return ['items' => $items, 'last_page' => $lastPage, 'source' => $path . ' ' . $method];
            }
            if (isset($raw['data']) && is_array($raw['data']) && isset($raw['meta'])) {
                $items = $raw['data'];
                $lastPage = (int)($raw['meta']['last_page'] ?? $page);
                return ['items' => $items, 'last_page' => $lastPage, 'source' => $path . ' ' . $method];
            }
            if (isset($raw['data']) && is_array($raw['data'])) {
                $items = $raw['data'];
                return ['items' => $items, 'last_page' => $lastPage, 'source' => $path . ' ' . $method];
            }
            if (isset($raw['items']) && is_array($raw['items'])) {
                $items = $raw['items'];
                return ['items' => $items, 'last_page' => $lastPage, 'source' => $path . ' ' . $method];
            }
        }

        return is_wp_error($lastErr) ? $lastErr : new WP_Error('dognet_empty', 'Üres Dognet válasz');
    }
}

if (!function_exists('impactshop_totals_collect')) {
    function impactshop_totals_collect(string $from, string $to, string $status = 'all', string $group = 'shop_ngo')
    {
        $from = date('Y-m-d', strtotime($from));
        $to = date('Y-m-d', strtotime($to));
        $key = 'impactshop_totals_' . md5($from . '|' . $to . '|' . $status . '|' . $group);

        $cached = get_transient($key);
        if ($cached !== false) {
            return $cached;
        }

        if (!function_exists('dognet_api_request')) {
            return new WP_Error('missing_dognet', 'Dognet API helper hiányzik.');
        }

        $map = impactshop_totals_build_campaign_map();
        $out = [];
        $page = 1;
        $perPage = 100;
        $maxLoop = 50;

        $usedFallback = false;
        do {
            $params = ['date_from' => $from, 'date_to' => $to, 'status' => $status];
            $res = dognet_api_list_conversions_page($params, $page, $perPage);
            if (is_wp_error($res)) {
                if (function_exists('dognet_api_list_conversions_all')) {
                    $fallback = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
                    if (!isset($fallback['error']) && !empty($fallback['items'])) {
                        foreach ($fallback['items'] as $item) {
                            impactshop_totals_accumulate_row($out, $map, $group, $item);
                        }
                        $usedFallback = true;
                        break;
                    }
                }
                $any = false;
                foreach (array_keys($map) as $cid) {
                    $res2 = dognet_api_list_conversions_page([
                        'date_from' => $from,
                        'date_to' => $to,
                        'status' => $status,
                        'campaign_id' => $cid,
                    ], 1, $perPage);
                    if (!is_wp_error($res2) && !empty($res2['items'])) {
                        foreach ($res2['items'] as $item) {
                            $any = true;
                            impactshop_totals_accumulate_row($out, $map, $group, $item);
                        }
                    }
                }
                if (!$any) {
                    return $res;
                }
                break;
            }

            if (!empty($res['items'])) {
                foreach ($res['items'] as $item) {
                    impactshop_totals_accumulate_row($out, $map, $group, $item);
                }
            }

            $last = (int)($res['last_page'] ?? $page);
            $page++;
        } while (!$usedFallback && $page <= $last && $page <= $maxLoop);

        ksort($out);

        $meta = ['grand' => ['orders' => 0, 'order_value' => 0.0, 'commission' => 0.0]];
        foreach ($out as $row) {
            $meta['grand']['orders'] += (int)$row['count'];
            $meta['grand']['order_value'] += (float)$row['amount'];
            $meta['grand']['commission'] += (float)$row['commission'];
        }

        $payload = ['rows' => $out, 'meta' => $meta];
        set_transient($key, $payload, IMPACTSHOP_CACHE_TTL);
        return $payload;
    }
}

if (!function_exists('impactshop_rest_totals')) {
    function impactshop_rest_totals(WP_REST_Request $request)
    {
        $from = sanitize_text_field($request->get_param('from') ?: date('Y-m-01'));
        $to = sanitize_text_field($request->get_param('to') ?: date('Y-m-t'));
        $status = sanitize_text_field($request->get_param('status') ?: 'all');
        $group = sanitize_text_field($request->get_param('group') ?: 'shop_ngo');

        $tot = impactshop_totals_collect($from, $to, $status, $group);
        if (is_wp_error($tot)) {
            return new WP_REST_Response(
                [
                    'code' => $tot->get_error_code(),
                    'message' => $tot->get_error_message(),
                    'data' => $tot->get_error_data(),
                ],
                502
            );
        }

        $rows = [];
        $dataRows = $tot['rows'] ?? [];
        foreach ($dataRows as $vals) {
            $rows[] = [
                'shop_name' => $vals['shop_name'] ?? '',
                'shop_slug' => $vals['shop_slug'] ?? '',
                'ngo' => $vals['ngo'] ?? '',
                'orders' => (int)($vals['count'] ?? 0),
                'order_value' => round((float)($vals['amount'] ?? 0), 2),
                'commission' => round((float)$vals['commission'], 2),
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'group' => $group,
            'rows' => $rows,
            'meta' => $tot['meta'] ?? [],
        ];
    }
}

add_action('rest_api_init', function () {
    register_rest_route('impactshop/v1', '/totals', [
        'methods' => 'GET',
        'callback' => 'impactshop_rest_totals',
        'permission_callback' => '__return_true',
    ]);
});
