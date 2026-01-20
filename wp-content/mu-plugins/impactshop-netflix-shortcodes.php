<?php
/**
 * Plugin Name: ImpactShop Netflix Shortcodes (fallback)
 * Description: Provides the legacy Netflix-style shortcode set so existing pages keep working even if WPCode snippets fail to load.
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------
 * Minimal configuration & helpers (mirrors legacy snippets)
 * ------------------------------------------------------------------ */
if (!defined('DOGNET_LOGIN_EMAIL')) {
    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
}
if (!defined('DOGNET_LOGIN_PASSWORD')) {
    define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
}
if (!defined('DOGNET_API_TOKEN')) {
    define('DOGNET_API_TOKEN', '');
}
if (!defined('DOGNET_AD_CHANNEL_ID')) {
    define('DOGNET_AD_CHANNEL_ID', 26081);
}
if (!defined('IMPACTSHOP_CACHE_TTL')) {
    define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
}
if (!defined('DOGNET_TOKEN_TTL')) {
    define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
}
if (!defined('IMPACTSHOP_FRAGMENT_TTL')) {
    define('IMPACTSHOP_FRAGMENT_TTL', 10 * MINUTE_IN_SECONDS);
}

if (!function_exists('impactshop_settings')) {
    function impactshop_settings()
    {
        return [
            'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
            'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=4',
            'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
            'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
        ];
    }
}

if (!function_exists('impactshop_q')) {
    function impactshop_q($key, $def = '')
    {
        return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
    }
}

if (!function_exists('impactshop_slugify_header')) {
    function impactshop_slugify_header($s)
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i'];
        $s = strtr($s, $map);
        $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
        return trim($s, '_');
    }
}

if (!function_exists('impactshop_b64url_encode')) {
    function impactshop_b64url_encode($value)
    {
        return base64_encode((string)$value);
    }
}

if (!function_exists('impactshop_extract_product_url')) {
    function impactshop_extract_product_url($href)
    {
        $href = trim((string)$href);
        if ($href === '') {
            return '';
        }
        $parts = wp_parse_url($href);
        if (!$parts) {
            return '';
        }
        $host = strtolower($parts['host'] ?? '');
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['u']) && is_string($qs['u'])) {
                $u = html_entity_decode($qs['u'], ENT_QUOTES, 'UTF-8');
                if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
                    $decoded = base64_decode($u, true);
                    if ($decoded !== false && preg_match('~^https?://~i', $decoded)) {
                        return $decoded;
                    }
                }
                if (preg_match('~^https?://~i', $u)) {
                    return $u;
                }
            }
        }
        if ($host && !preg_match('~(^|\\.)fillout\\.com$~i', $host) && !preg_match('~(^|\\.)app\\.sharity\\.hu$~i', $host)) {
            return $href;
        }
        return '';
    }
}

if (!function_exists('impactshop_build_deal_cta')) {
    function impactshop_build_deal_cta($shop_slug, $product_url, $fillout, $d1, $amb, $src)
    {
        $shop_slug = (string)$shop_slug;
        $product_url = trim((string)$product_url);
        $fillout = trim((string)$fillout);
        $amb = trim((string)$amb);
        $src = trim((string)$src) ?: 'impactshop';

        if ($d1) {
            $params = ['d1' => $d1, 'amb' => $amb, 'src' => $src];
            if ($product_url !== '') {
                $params['u'] = impactshop_b64url_encode($product_url);
            }
            return add_query_arg($params, home_url('/go-deal/' . rawurlencode($shop_slug)));
        }

        if ($fillout !== '') {
            $params = ['shop' => $shop_slug, 'amb' => $amb];
            if ($product_url !== '') {
                $params['u'] = impactshop_b64url_encode($product_url);
            }
            return add_query_arg($params, $fillout);
        }

        return home_url('/go-deal/' . rawurlencode($shop_slug));
    }
}

if (!function_exists('impactshop_get_ngo_name_map')) {
    function impactshop_get_ngo_name_map()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $path = trailingslashit(ABSPATH) . 'ngo_codes.csv';
        if (!file_exists($path)) {
            return $map;
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $map;
        }
        $row = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1) {
                continue;
            }
            $label = isset($data[0]) ? trim((string)$data[0]) : '';
            $slug = isset($data[1]) ? sanitize_title($data[1]) : '';
            if ($label !== '' && $slug !== '') {
                $map[$slug] = $label;
            }
        }
        fclose($handle);
        return $map;
    }
}

if (!function_exists('impactshop_format_ngo_name')) {
    function impactshop_format_ngo_name($slug)
    {
        $slug = sanitize_title((string)$slug);
        if ($slug === '') {
            return '';
        }
        $map = impactshop_get_ngo_name_map();
        if (isset($map[$slug])) {
            return $map[$slug];
        }
        $fallback = str_replace(['-', '_'], ' ', $slug);
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($fallback, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords($fallback);
    }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
    function impactshop_fetch_csv_assoc($url, $cache_key, $ttl)
    {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $resp = wp_remote_get($url, ['timeout' => 20]);
        if (is_wp_error($resp)) {
            return [];
        }

        $body = wp_remote_retrieve_body($resp);
        if (!$body) {
            return [];
        }

        if (substr($body, 0, 3) === "\xEF\xBB\xBF") {
            $body = substr($body, 3);
        }
        $lines = preg_split("/\r\n|\n|\r/", $body);
        if (!$lines || count($lines) < 1) {
            return [];
        }

        $first  = $lines[0];
        $delim  = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
        $headers_raw = str_getcsv($first, $delim);
        $headers = array_map('impactshop_slugify_header', $headers_raw);

        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '' || $lines[$i] === false) {
                continue;
            }
            $cols = str_getcsv($lines[$i], $delim);
            if (count($cols) === 1 && $cols[0] === null) {
                continue;
            }
            $row = [];
            foreach ($headers as $idx => $key) {
                $row[$key] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
            }
            if (implode('', $row) === '') {
                continue;
            }
            $rows[] = $row;
        }
        set_transient($cache_key, $rows, $ttl);
        return $rows;
    }
}

if (!function_exists('impactshop_fragment_cache')) {
    function impactshop_fragment_cache($key, callable $callback, $ttl = null)
    {
        $cacheKey = 'impactshop_fragment_' . md5($key);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
        $html = (string) call_user_func($callback);
        if ($html !== '') {
            set_transient($cacheKey, $html, $ttl ?? IMPACTSHOP_FRAGMENT_TTL);
        }
        return $html;
    }
}

if (!function_exists('impactshop_get_shops_raw')) {
    function impactshop_get_shops_raw()
    {
        $settings = impactshop_settings();
        return impactshop_fetch_csv_assoc($settings['shops_csv_url'], 'impactshop_csv_shops', $settings['cache_ttl']);
    }
}

if (!function_exists('impactshop_normalize_cj_category')) {
    function impactshop_normalize_cj_category($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return 'Egyéb';
        }
        $key = strtolower($raw);
        $map = [
            'air' => 'Szabadidő',
            'travel' => 'Szabadidő',
            'leisure' => 'Szabadidő',
            'outdoors' => 'Sport',
            'sport' => 'Sport',
            'sports' => 'Sport',
            'exercise & health' => 'Egészség',
            'exercise and health' => 'Egészség',
            'health' => 'Egészség',
            'vision care' => 'Egészség',
            'nutritional supplements' => 'Egészség',
            'cosmetics' => 'Egészség',
            'bath & body' => 'Egészség',
            'bath and body' => 'Egészség',
            'bed & bath' => 'Otthon',
            'bed and bath' => 'Otthon',
            'kitchen' => 'Otthon',
            'home' => 'Otthon',
            'furniture' => 'Bútor',
            'women\'s' => 'Divat',
            'womens' => 'Divat',
            'fashion' => 'Divat',
            'children' => 'Játék',
            'kids' => 'Játék',
            'babies' => 'Játék',
            'toys' => 'Játék',
            'games' => 'Játék',
            'photo' => 'Elektronika',
            'peripherals' => 'Elektronika',
            'computer hw' => 'Műszaki',
            'electronics' => 'Elektronika',
            'hardware' => 'Műszaki',
            'jewelry' => 'Ékszer',
            'food' => 'Élelmiszer',
        ];
        return $map[$key] ?? $raw;
    }
}

if (!function_exists('impactshop_get_cj_shops')) {
    function impactshop_get_cj_shops(array $csvMap = [])
    {
        $opt = get_option('impactshop_shops');
        $registry = [];
        if (is_string($opt)) {
            $decoded = json_decode($opt, true);
            if (is_array($decoded)) {
                $registry = $decoded;
            }
        } elseif (is_array($opt)) {
            $registry = $opt;
        }

        $out = [];
        foreach ($registry as $candidate) {
            if (!is_array($candidate) || empty($candidate['slug'])) {
                continue;
            }
            $slug = sanitize_title($candidate['slug']);
            if ($slug === '') {
                continue;
            }
            $network = strtolower((string)($candidate['network'] ?? ''));
            if ($network !== 'cj' && strpos($slug, 'cj-') !== 0) {
                continue;
            }
            $hasLink = !empty($candidate['cj_click_url']) || !empty($candidate['program_id']);
            if (!$hasLink) {
                continue;
            }

            $domain = strtolower((string)($candidate['domain'] ?? ''));
            $mapHit = $domain && isset($csvMap[$domain]) ? $csvMap[$domain] : [];
            $name = $candidate['name'] ?? ($mapHit['name'] ?? ($candidate['domain'] ?? $slug));
            $logo = $candidate['logo_url'] ?? ($candidate['logo'] ?? ($mapHit['logo'] ?? ''));
            $category = $candidate['category'] ?? ($mapHit['category'] ?? 'Egyéb');
            $category = impactshop_normalize_cj_category($category);
            $out[] = [
                'name'           => $name,
                'shop_slug'      => $slug,
                'category'       => $category,
                'logo'           => $logo,
                'dognet_base'    => '',
                'deeplink_param' => $candidate['deeplink_param'] ?? 'url',
                'product_url'    => $candidate['default_cta_url'] ?? '',
                'commission_min' => '',
                'commission_max' => '',
                'deals_feed'     => '',
                'featured'       => $candidate['featured'] ?? '',
                'added_at'       => $candidate['added_at'] ?? '',
                'default_d1'     => $candidate['default_d1'] ?? '',
                'network'        => 'cj',
                'cj_click_url'   => $candidate['cj_click_url'] ?? '',
                'cj_program_id'  => $candidate['program_id'] ?? '',
            ];
        }

        return $out;
    }
}

if (!function_exists('impactshop_get_shops')) {
    function impactshop_get_shops()
    {
        $rows = impactshop_get_shops_raw();
        $out  = [];
        $existing = [];
        $csvMap = [];
        foreach ($rows as $r) {
            $name = $r['name'] ?? ($r['nev'] ?? '');
            $slug = $r['shop_slug'] ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
            if (!$name || !$slug) {
                continue;
            }
            $domain = strtolower((string)($r['domain'] ?? ($r['site'] ?? ($r['url'] ?? ($r['website'] ?? '')))));
            $key = sanitize_title($slug);
            if ($key !== '') {
                $existing[$key] = true;
            }
            if ($domain !== '' && !isset($csvMap[$domain])) {
                $csvMap[$domain] = [
                    'name'     => $name,
                    'logo'     => $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? '')),
                    'category' => ($r['category'] ?? ($r['kategoria'] ?? 'Egyéb')) ?: 'Egyéb',
                ];
            }
            $out[] = [
                'name'           => $name,
                'shop_slug'      => $slug,
                'category'       => ($r['category'] ?? ($r['kategoria'] ?? 'Egyéb')) ?: 'Egyéb',
                'logo'           => $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? '')),
                'dognet_base'    => $r['dognet_base'] ?? '',
                'deeplink_param' => $r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url'),
                'product_url'    => $r['product_url'] ?? ($r['homepage'] ?? ''),
                'commission_min' => $r['commission_min'] ?? '',
                'commission_max' => $r['commission_max'] ?? '',
                'deals_feed'     => $r['deals_feed'] ?? '',
                'featured'       => $r['featured'] ?? ($r['kiemelt'] ?? ''),
                'added_at'       => $r['added_at'] ?? '',
            ];
        }

        foreach (impactshop_get_cj_shops($csvMap) as $cj) {
            $key = sanitize_title($cj['shop_slug'] ?? '');
            if ($key === '' || isset($existing[$key])) {
                continue;
            }
            $existing[$key] = true;
            $out[] = $cj;
        }

        return $out;
    }
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
        $key = 'dognet_api_token_cache_v1';
        if (!$force) {
            $cached = get_transient($key);
            if ($cached) {
                return $cached;
            }
        }

        $attempts = [
            ['POST', '/auth/login', ['email' => DOGNET_LOGIN_EMAIL, 'password' => DOGNET_LOGIN_PASSWORD], 'json'],
            ['POST', '/auth/login', ['email' => DOGNET_LOGIN_EMAIL, 'password' => DOGNET_LOGIN_PASSWORD], 'form'],
        ];

        foreach ($attempts as $a) {
            [$method, $path, $body, $fmt] = $a;
            $args = [
                'timeout' => 20,
                'method'  => $method,
                'headers' => [
                    'Content-Type' => $fmt === 'json' ? 'application/json' : 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
                'body' => ($fmt === 'json') ? wp_json_encode($body) : http_build_query($body),
            ];
            $resp = wp_remote_request('https://api.app.dognet.com/api/v1' . $path, $args);
            if (is_wp_error($resp)) {
                continue;
            }
            $code = wp_remote_retrieve_response_code($resp);
            $json = json_decode(wp_remote_retrieve_body($resp), true);
            if ($code >= 200 && $code < 300 && is_array($json)) {
                $token = $json['token'] ?? ($json['data']['token'] ?? ($json['result']['token'] ?? ''));
                if ($token) {
                    set_transient($key, $token, DOGNET_TOKEN_TTL);
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
            return new WP_Error('no_token', 'Dognet API token nem elérhető');
        }

        $url = 'https://api.app.dognet.com/api/v1' . $path;
        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'method' => $method,
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
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
            return new WP_Error('api_error', 'Dognet API hiba ' . $code, ['resp' => $json ?: $raw, 'code' => $code, 'path' => $path, 'method' => $method]);
        }

        return $json ?: [];
    }
}

if (!function_exists('impactshop_get_banners')) {
    function impactshop_get_banners()
    {
        $settings = impactshop_settings();
        return impactshop_fetch_csv_assoc($settings['banners_csv_url'], 'impactshop_csv_banners', $settings['cache_ttl']);
    }
}

if (!function_exists('impactshop_load_cj_links')) {
    function impactshop_load_cj_links(): array
    {
        $opt = get_option('impactshop_cj_links');
        if (is_string($opt)) {
            $decoded = json_decode($opt, true);
        } elseif (is_array($opt)) {
            $decoded = $opt;
        } else {
            $decoded = [];
        }
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('impactshop_cj_link_shop_slug')) {
    function impactshop_cj_link_shop_slug(array $link): string
    {
        $adv = (string)($link['advertiser_id'] ?? ($link['advertiserId'] ?? ''));
        $adv = trim($adv);
        if ($adv === '') {
            return '';
        }
        $adv = preg_replace('/[^0-9a-zA-Z_-]/', '', $adv);
        return 'cj-' . strtolower($adv);
    }
}

if (!function_exists('impactshop_cj_link_country_ok')) {
    function impactshop_cj_link_country_ok(array $link): bool
    {
        $targets = $link['targeted_countries'] ?? [];
        if (is_string($targets)) {
            $targets = array_filter(array_map('trim', explode(',', $targets)));
        }
        if (!is_array($targets)) {
            $targets = [];
        }
        if (!$targets) {
            return true;
        }
        $targets = array_map('strtoupper', array_map('trim', $targets));
        return in_array('HU', $targets, true) || in_array('NULL', $targets, true);
    }
}

if (!function_exists('impactshop_deals_banners_shortcode')) {
    function impactshop_deals_banners_shortcode($atts)
    {
        $a = shortcode_atts([
            'limit'    => '12',
            'category' => '',
            'force'    => '1',
            'json'     => '0',
        ], $atts, 'impactshop_deals_banners');

        $cacheKey = 'impactshop_deals_banners_' . md5(wp_json_encode($a));

        return impactshop_fragment_cache($cacheKey, function () use ($a) {
            if (!function_exists('impactshop_get_banners')) {
                return '';
            }

            $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
            $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
            $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
            $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';

            $limit = intval($a['limit']);
            $category = trim($a['category']);
            $rows = impactshop_get_banners();
            $result = [];

            $shops_by_slug = [];
            if (function_exists('impactshop_get_shops')) {
                foreach ((array)impactshop_get_shops() as $shop) {
                    if (!empty($shop['shop_slug'])) {
                        $shops_by_slug[strtolower($shop['shop_slug'])] = $shop;
                    }
                }
            }

            foreach ($rows as $row) {
                $rowCat = trim($row['category'] ?? '');
                if ($category !== '' && strcasecmp($rowCat, $category) !== 0) {
                    continue;
                }
                $slug = strtolower((string)($row['shop_slug'] ?? $row['slug'] ?? ''));
                $label = [];
                if (!empty($row['label'])) {
                    $decoded = json_decode($row['label'], true);
                    if (is_array($decoded)) {
                        $label = $decoded;
                    }
                }
                $title = '';
                if (isset($label['title']) && $label['title'] !== '') {
                    $title = (string)$label['title'];
                } elseif (!empty($row['title'])) {
                    $title = (string)$row['title'];
                }
                $price = '';
                if (isset($label['price']) && $label['price'] !== '') {
                    $price = (string)$label['price'];
                } elseif (!empty($row['price'])) {
                    $price = (string)$row['price'];
                }
                $old_price = '';
                if (isset($label['old_price']) && $label['old_price'] !== '') {
                    $old_price = (string)$label['old_price'];
                } elseif (!empty($row['old_price'])) {
                    $old_price = (string)$row['old_price'];
                }
                $pct = 0;
                if (isset($label['discount_pct']) && is_numeric($label['discount_pct'])) {
                    $pct = (float)$label['discount_pct'];
                } elseif (isset($row['pct']) && is_numeric($row['pct'])) {
                    $pct = (float)$row['pct'];
                }

                $product_url = impactshop_extract_product_url($row['href'] ?? '');
                $cta = impactshop_build_deal_cta($slug, $product_url, $fillout, $d1, $amb, $src);
                $result[] = [
                    'shop_slug' => $slug,
                    'slug'      => $slug,
                    'href'      => (string)$cta,
                    'img'       => (string)($row['img'] ?? ''),
                    'title'     => $title ?: ($shops_by_slug[$slug]['name'] ?? ''),
                    'price'     => $price,
                    'old_price' => $old_price,
                    'pct'       => $pct,
                    'shop_name' => $shops_by_slug[$slug]['name'] ?? '',
                ];
                if ($limit > 0 && count($result) >= $limit) {
                    break;
                }
            }

            $cj_links = impactshop_load_cj_links();
            if ($cj_links) {
                $existing = array_fill_keys(array_map(function ($r) {
                    return strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? ''));
                }, $result), true);
                foreach ($cj_links as $link) {
                    if (!is_array($link) || !impactshop_cj_link_country_ok($link)) {
                        continue;
                    }
                    $slug = impactshop_cj_link_shop_slug($link);
                    if ($slug === '' || isset($existing[$slug])) {
                        continue;
                    }
                    $shop = $shops_by_slug[$slug] ?? [];
                    $title = (string)($link['link_name'] ?? ($link['description'] ?? ($shop['name'] ?? $slug)));
                    $img = (string)($shop['logo'] ?? ($link['logo_url'] ?? ''));
                    $destination = (string)($link['destination'] ?? '');
                    $cta = '';
                    if ($d1) {
                        $cta = add_query_arg(
                            ['d1' => $d1, 'amb' => $amb, 'src' => $src ?: 'impactshop', 'u' => $destination],
                            home_url('/go/' . rawurlencode($slug))
                        );
                    } elseif ($fillout) {
                        $cta = add_query_arg(['shop' => $slug, 'amb' => $amb], $fillout);
                    } else {
                        $cta = home_url('/go/' . rawurlencode($slug));
                    }
                    $result[] = [
                        'shop_slug' => $slug,
                        'slug'      => $slug,
                        'href'      => $cta,
                        'img'       => $img,
                        'title'     => $title,
                        'price'     => '',
                        'old_price' => '',
                        'pct'       => 0,
                        'shop_name' => $shop['name'] ?? ($link['advertiser_name'] ?? $slug),
                    ];
                    $existing[$slug] = true;
                    if ($limit > 0 && count($result) >= $limit) {
                        break;
                    }
                }
            }

            if ($a['json'] === '1') {
                return wp_json_encode(array_values($result));
            }

            if (!$result) {
                return '';
            }

            ob_start();
            echo '<ul class="impactshop-deals-banners">';
            foreach ($result as $row) {
                $title = (string)($row['title'] ?? ($row['shop_name'] ?? $row['slug'] ?? ''));
                $href = $row['href'] ?? '#';
                echo '<li><a href="' . esc_url($href) . '" target="_blank" rel="nofollow noopener">' . esc_html($title ?: 'Ajánlat') . '</a></li>';
            }
            echo '</ul>';
            return ob_get_clean();
        });
    }

    add_shortcode('impactshop_deals_banners', 'impactshop_deals_banners_shortcode');
}

/**
 * Netflix slider for partner shops.
 * Usage: [impactshop_netflix deals_badge="1" ga4="1" autoplay="1" interval="3000"]
 */
// == ImpactShop – Netflix-sáv (EGY sor + felül kategóriaszűrő) – auto-scroll + GenZ 3D tilt ==
// Rövidkód:
// [impactshop_netflix categories="Tech,Divat,Sport" show_all="1" arrows="1" card_w="150" card_h="110" gap="16"
//                     max_items="0" shuffle="0" featured_only="0" new_days="14" deals_badge="1" ga4="1"
//                     autoplay="1" interval="3000"]

if (!function_exists('impactshop_shortcode_netflix')) {
  function impactshop_shortcode_netflix($atts) {
    if (!function_exists('sib_load_banners')) {
      error_log('[impactshop_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Szolgáltatás átmenetileg nem elérhető.</div>';
    }

    if (!function_exists('sib_load_banners')) {
      error_log('[impactshop_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Szolgáltatás átmenetileg nem elérhető.</div>';
    }

    $a = shortcode_atts([
      'categories'    => '',
      'show_all'      => '1',
      'arrows'        => '1',
      'card_w'        => '150',
      'card_h'        => '110',
      'gap'           => '16',
      'max_items'     => '0',
      'shuffle'       => '0',
      'featured_only' => '0',
      'new_days'      => '14',
      'deals_badge'   => '1',
      'ga4'           => '1',
      // ÚJ
      'autoplay'      => '1',
      'interval'      => '3000',
    ], $atts, 'impactshop_netflix');

    if (!function_exists('impactshop_get_shops')) return '<div>Hiányzó függvény: impactshop_get_shops</div>';
    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
    $ngo_label = $d1 ? impactshop_format_ngo_name($d1) : '';
    $ngo_notice = '';
    if ($d1 && $ngo_label) {
        $ngo_notice = '<div class="impactshop-ngo-banner">Jelenleg ezt a szervezetet támogatod: <strong>' . esc_html($ngo_label) . '</strong></div>';
    }

    // featured_only
    if ($a['featured_only'] === '1') {
      $shops = array_values(array_filter($shops, function($s){
        $v = strtolower(trim((string)($s['featured'] ?? '0')));
        return ($v === '1' || $v === 'true' || $v === 'yes');
      }));
    }

    // kategóriák
    $bycat = [];
    foreach ($shops as $s) {
      $cat = trim($s['category'] ?? 'Egyéb'); if ($cat==='') $cat='Egyéb';
      $bycat[$cat][] = $s;
    }
    if (trim($a['categories'])!=='') {
      $wanted = array_values(array_filter(array_map('trim', explode(',', $a['categories']))));
      $ord = []; foreach ($wanted as $w) if (isset($bycat[$w])) $ord[$w] = $bycat[$w];
      $bycat = $ord;
    } else {
      ksort($bycat, SORT_NATURAL|SORT_FLAG_CASE);
    }

    $cats = array_keys($bycat);
    $activeCat = ($a['show_all']==='1') ? '__ALL__' : ($cats[0] ?? '__ALL__');

    // CTA segédek
    $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
    $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
    $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
    $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';

    $fragment_params = [
      'atts' => $a,
      'd1'   => $d1,
      'amb'  => $amb,
      'src'  => $src,
    ];
    $fragment_key = 'impactshop_fragment_' . md5('impactshop_netflix_' . wp_json_encode($fragment_params));
    $fragment_cached = get_transient($fragment_key);
    if ($fragment_cached !== false) {
      return $fragment_cached;
    }

    // "Új" jelzés
    $nowTs = time(); $newDays = max(1, intval($a['new_days']));
    $is_new = function($s) use($nowTs,$newDays){
      if (!empty($s['is_new']) || !empty($s['new'])) {
        $v = strtolower(trim((string)($s['is_new'] ?? $s['new'])));
        if ($v==='1' || $v==='true' || $v==='yes') return true;
      }
      if (!empty($s['added_at'])) {
        $ts = strtotime($s['added_at'].' 00:00:00');
        if ($ts && ($nowTs - $ts) <= ($newDays*86400)) return true;
      }
      return false;
    };

    // Kupon/akció API – jelenlét jelzés
    $cid_has_deal = [];
    if ($a['deals_badge'] === '1' && function_exists('dognet_api_request')) {
      $cache_key = 'impactshop_coupons_present_simple';
      $cid_has_deal = get_transient($cache_key);
      if ($cid_has_deal === false) {
        $cid_has_deal = [];
        $ad_id = defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0;
        $body  = ['filter'=>['validity'=>['eq'=>'present']], 'per-page'=>500];
        if ($ad_id) $body['ad_channel_id'] = $ad_id;
        $resp = dognet_api_request('POST','/coupons/filter', $body);
        $items = [];
        if (!is_wp_error($resp)) {
          if (isset($resp['data']) && is_array($resp['data'])) $items = $resp['data'];
          elseif (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];
        }
        foreach ($items as $it) {
          $cid = 0;
          foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
            if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
          }
          if ($cid) $cid_has_deal[$cid] = true;
        }
        set_transient($cache_key, $cid_has_deal, 10 * MINUTE_IN_SECONDS);
      }
    }

    // Banners fallback kategóriára
    $banners_map = [];
    if ($a['deals_badge'] === '1' && function_exists('impactshop_get_banners')) {
      foreach (impactshop_get_banners() as $b) {
        $cat = trim($b['category'] ?? '');
        if ($cat !== '') $banners_map[$cat] = true;
      }
    }

    // allShops lista
    $allShops = [];
    foreach ($bycat as $cat=>$arr) { foreach ($arr as $s) { $allShops[] = $s + ['__cat'=>$cat]; } }
    if ($a['shuffle']==='1') shuffle($allShops);

    $uid   = 'ifx-'.substr(md5(json_encode([$a, array_keys($bycat)]).microtime(true)),0,8);
    $cardW = max(120, intval($a['card_w']));
    $cardH = max(80,  intval($a['card_h']));
    $gap   = max(8,   intval($a['gap']));
    $max   = max(0,   intval($a['max_items']));
    $autoplay = ($a['autoplay']==='1');
    $interval = max(1000, intval($a['interval']));

    ob_start(); ?>
    <style>
      .impactshop-ngo-banner{
        margin: 0 0 14px;
        padding: 10px 14px;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(14,116,144,.12), rgba(14,165,233,.10));
        color: #0f172a;
        font-weight: 600;
        border: 1px solid rgba(14,116,144,.2);
      }
      .<?php echo $uid; ?>{ --gap: <?php echo $gap; ?>px; --w: <?php echo $cardW; ?>px; --h: <?php echo $cardH; ?>px; --accent:#8b5cf6; }
      .<?php echo $uid; ?> .ifx-cats{display:flex;gap:10px;align-items:center;overflow-x:auto;padding:6px 2px 12px;margin-bottom:8px;scrollbar-width:none}
      .<?php echo $uid; ?> .ifx-cats::-webkit-scrollbar{display:none}
      .<?php echo $uid; ?> .ifx-pill{flex:0 0 auto;padding:9px 14px;border-radius:999px;font:700 13px/1 system-ui;
        background:linear-gradient(180deg,rgba(255,255,255,.85),rgba(255,255,255,.7));border:1px solid rgba(0,0,0,.08);color:#0b1220;
        box-shadow:0 4px 14px rgba(2,6,23,.07);cursor:pointer;user-select:none;transition:.15s}
      .<?php echo $uid; ?> .ifx-pill:hover{transform:translateY(-1px)}
      .<?php echo $uid; ?> .ifx-pill.active{background:linear-gradient(180deg,#fff,rgba(255,255,255,.85));border-color:rgba(0,0,0,.12);box-shadow:0 6px 18px rgba(2,6,23,.09)}

      .<?php echo $uid; ?> .ifx-track-wrap{position:relative;perspective:1000px}
      .<?php echo $uid; ?> .ifx-track{display:flex;gap:var(--gap);overflow-x:auto;scroll-snap-type:x mandatory;padding:8px 4px;scrollbar-width:none}
      .<?php echo $uid; ?> .ifx-track::-webkit-scrollbar{display:none}

      .<?php echo $uid; ?> .ifx-card{flex:0 0 auto;width:var(--w);scroll-snap-align:start;position:relative;will-change:transform}
      .<?php echo $uid; ?> .ifx-card a{
        display:block;border-radius:18px;padding:10px;text-decoration:none;color:inherit;
        background:linear-gradient(160deg, rgba(255,255,255,.70), rgba(255,255,255,.55));
        border:1px solid rgba(255,255,255,.65);
        box-shadow:
          0 18px 38px rgba(2,6,23,.12),
          inset 0 1px 0 rgba(255,255,255,.6),
          inset 0 -1px 0 rgba(2,6,23,.04);
        backdrop-filter:saturate(180%) blur(8px);
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
        transform-style:preserve-3d;
      }
      .<?php echo $uid; ?> .ifx-card a:hover{
        transform:translateY(-4px) rotateX(var(--rx,0deg)) rotateY(var(--ry,0deg));
        box-shadow:0 26px 44px rgba(2,6,23,.16);
        background:linear-gradient(160deg, rgba(255,255,255,.78), rgba(255,255,255,.62));
      }

      .<?php echo $uid; ?> .ifx-logo-box{
        width:100%;height:var(--h);display:flex;align-items:center;justify-content:center;border-radius:14px;overflow:hidden;
        background:radial-gradient(120% 120% at 10% 0%, rgba(139,92,246,.16), transparent 60%), rgba(255,255,255,.45);
        border:1px solid rgba(0,0,0,.06);
      }
      .<?php echo $uid; ?> .ifx-logo{max-width:100%;max-height:100%;object-fit:contain;display:block;filter:drop-shadow(0 1px 2px rgba(0,0,0,.12))}
      .<?php echo $uid; ?> .ifx-name{margin-top:8px;font:800 13px/1.25 system-ui;color:#0b1220;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center}

      .<?php echo $uid; ?> .ifx-badges{position:absolute;top:8px;left:8px;display:flex;gap:6px;z-index:2}
      .<?php echo $uid; ?> .ifx-badge{padding:4px 7px;border-radius:9px;font:900 10px/1 system-ui;color:#fff;background:#ef4444;border:1px solid #b91c1c;
        box-shadow:0 4px 10px rgba(239,68,68,.25)}
      .<?php echo $uid; ?> .ifx-badge.new {background:#86efac;color:#065f46;border-color:#34d399}

      .<?php echo $uid; ?> .ifx-arrow{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:999px;border:1px solid rgba(0,0,0,.12);
        background:linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.75));color:#0b1220;display:flex;align-items:center;justify-content:center;cursor:pointer;
        user-select:none;z-index:2;transition:transform .15s, box-shadow .15s;box-shadow:0 8px 18px rgba(2,6,23,.12);backdrop-filter:saturate(180%) blur(6px)}
      .<?php echo $uid; ?> .ifx-arrow:hover{transform:translateY(-50%) scale(1.07)}
      .<?php echo $uid; ?> .ifx-arrow.prev{left:-6px}
      .<?php echo $uid; ?> .ifx-arrow.next{right:-6px}
      @media (max-width:780px){ .<?php echo $uid; ?> .ifx-arrow{display:none} }

      @media (prefers-reduced-motion:reduce){
        .<?php echo $uid; ?> .ifx-card a, .<?php echo $uid; ?> .ifx-arrow{transition:none}
      }
    </style>

    <div class="<?php echo $uid; ?> impactshop-netflix" data-autoplay="<?php echo esc_attr($autoplay ? '1':'0'); ?>" data-interval="<?php echo esc_attr($interval); ?>">
      <?php echo $ngo_notice; ?>
      <div class="ifx-cats" role="tablist">
        <?php if ($a['show_all']==='1'): ?>
          <div class="ifx-pill <?php echo ($activeCat==='__ALL__')?'active':''; ?>" data-cat="__ALL__">Összes</div>
        <?php endif; ?>
        <?php foreach ($bycat as $cat => $_): ?>
          <div class="ifx-pill <?php echo ($activeCat===$cat)?'active':''; ?>" data-cat="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></div>
        <?php endforeach; ?>
      </div>

      <div class="ifx-track-wrap">
        <?php if ($a['arrows']==='1'): ?>
          <div class="ifx-arrow prev" aria-label="Vissza">‹</div>
          <div class="ifx-arrow next" aria-label="Előre">›</div>
        <?php endif; ?>
        <div class="ifx-track" data-active="<?php echo esc_attr($activeCat); ?>">
          <?php
          $render = function($S) use ($d1,$amb,$src,$fillout,$is_new,$a,$cid_has_deal,$banners_map){
            $out = '';
            $max = max(0, intval($a['max_items']));
            $i = 0;
            foreach ($S as $s) {
              if ($max>0 && $i>=$max) break; $i++;

              $cta = $d1
                ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src ?: 'impactshop'], home_url('/go/'. rawurlencode($s['shop_slug'])))
                : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout);

              $img  = $s['logo'] ?? '';
              $name = $s['name'] ?? $s['shop_slug'];
              $cat  = $s['__cat'] ?? ($s['category'] ?? '');
              $cid  = function_exists('dognet_extract_campaign_id_from_base') ? dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '') : 0;

              $hasDeal = (!empty($cid) && isset($cid_has_deal[$cid])) || ($cat && isset($banners_map[$cat]));
              $isNew = $is_new($s);

              $out .= '<div class="ifx-card" data-cat="'.esc_attr($cat).'">';
              $out .=   '<a href="'.esc_url($cta).'" aria-label="'.esc_attr($name).'">';
              $badges = '';
              if ($hasDeal) $badges .= '<span class="ifx-badge">Kupon</span>';
              if ($isNew)   $badges .= '<span class="ifx-badge new">Új</span>';
              if ($badges)  $out .= '<div class="ifx-badges">'.$badges.'</div>';
              $out .=     '<div class="ifx-logo-box">';
              if ($img) { $out .= '<img class="ifx-logo" src="'.esc_url($img).'" alt="'.esc_attr($name).'" loading="lazy">'; }
              else      { $out .= '<div style="color:#666;font:600 12px/1 system-ui">Nincs kép</div>'; }
              $out .=     '</div>';
              $out .=     '<div class="ifx-name">'.esc_html($name).'</div>';
              $out .=   '</a>';
              $out .= '</div>';
            }
            return $out;
          };

          $list = ($activeCat==='__ALL__') ? $allShops : array_map(function($x){ $x['__cat']=$x['category']??''; return $x; }, $bycat[$activeCat] ?? []);
          if ($a['shuffle']==='1' && $activeCat!=='__ALL__') shuffle($list);
          echo $render($list);
          ?>
        </div>
      </div>
    </div>

    <script>
      (function(){
        var root  = document.currentScript.previousElementSibling;
        if(!root) return;
        var track = root.querySelector('.ifx-track');
        var prev  = root.querySelector('.ifx-arrow.prev');
        var next  = root.querySelector('.ifx-arrow.next');
        var pills = root.querySelectorAll('.ifx-pill');
        var autoplay = root.getAttribute('data-autoplay')==='1';
        var interval = parseInt(root.getAttribute('data-interval')||'3000',10);
        if(isNaN(interval) || interval<1000) interval=3000;

        function scrollByAmount(dir){
          var w = track.clientWidth || 320;
          track.scrollBy({ left: dir * Math.max(240, Math.round(w*0.85)), behavior:'smooth' });
        }
        if (prev) prev.addEventListener('click', function(){ stopAuto(); scrollByAmount(-1); startAutoSoon(); });
        if (next) next.addEventListener('click', function(){ stopAuto(); scrollByAmount( 1); startAutoSoon(); });

        function setActive(cat){
          track.setAttribute('data-active', cat);
          pills.forEach(function(p){ p.classList.toggle('active', p.getAttribute('data-cat')===cat); });
          var cards = track.querySelectorAll('.ifx-card');
          cards.forEach(function(card){
            var cc = card.getAttribute('data-cat') || '';
            var show = (cat==='__ALL__') || (cc===cat);
            card.style.display = show ? '' : 'none';
          });
          track.scrollTo({left:0, behavior:'auto'});
        }
        pills.forEach(function(p){ p.addEventListener('click', function(){ stopAuto(); setActive(p.getAttribute('data-cat')); startAutoSoon(); }); });

        // Auto-scroll (kupon-sáv jelleg)
        var timer=null, hover=false;
        function step(){
          if(hover) return;
          if(track.scrollWidth <= track.clientWidth+4){ return; } // nincs mit scrollozni
          var maxLeft = track.scrollWidth - track.clientWidth;
          var atEnd = (track.scrollLeft >= maxLeft - 8);
          if(atEnd){
            // finom visszaugrás elejére
            track.scrollTo({left:0, behavior:'smooth'});
          }else{
            scrollByAmount(+1);
          }
        }
        function startAuto(){
          if(!autoplay || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
          if(timer) return;
          timer = setInterval(step, interval);
        }
        function stopAuto(){ if(timer){ clearInterval(timer); timer=null; } }
        function startAutoSoon(){ setTimeout(startAuto, 600); }

        track.addEventListener('mouseenter', function(){ hover=true; stopAuto(); });
        track.addEventListener('mouseleave', function(){ hover=false; startAutoSoon(); });
        track.addEventListener('touchstart', function(){ hover=true; stopAuto(); }, {passive:true});
        track.addEventListener('touchend',   function(){ hover=false; startAutoSoon(); }, {passive:true});
        document.addEventListener('visibilitychange', function(){ if(document.hidden) stopAuto(); else startAutoSoon(); });

        // 3D tilt – könnyű effekt (egérrel)
        track.addEventListener('mousemove', function(e){
          var card = e.target.closest('.ifx-card a'); if(!card) return;
          var r = card.getBoundingClientRect();
          var cx = (e.clientX - r.left) / r.width - .5;
          var cy = (e.clientY - r.top)  / r.height - .5;
          card.style.setProperty('--rx', (-cy*6).toFixed(2)+'deg');
          card.style.setProperty('--ry', ( cx*6).toFixed(2)+'deg');
        }, {passive:true});
        track.addEventListener('mouseleave', function(){
          track.querySelectorAll('.ifx-card a').forEach(function(a){ a.style.removeProperty('--rx'); a.style.removeProperty('--ry'); });
        });

        // indulás
        startAuto();
      })();
    </script>
    <?php
    $html = ob_get_clean();
    if ($html !== '') {
      set_transient($fragment_key, $html, IMPACTSHOP_FRAGMENT_TTL);
    }
    return $html;
  }
  add_shortcode('impactshop_netflix','impactshop_shortcode_netflix');
}

/**
 * Deals rail.
 * Usage: [impact_deals_netflix rows="2" filter="1" limit="60"]
 */
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - LINK: REST deeplink-et preferál (ha nincs, url), banner felülír, ha terméklink-gyanús
 * - ÁR: ha REST nem ad, bannerből pótoljuk
 * - Vezérlés: swipe/drag + NYILAK + görgő/trackpad + billentyű
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    if (!function_exists('sib_load_banners')) {
      error_log('[impact_deals_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Ajánlatok nem betölthetők pillanatnyilag.</div>';
    }

    if (!function_exists('sib_load_banners')) {
      error_log('[impact_deals_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Ajánlatok nem betölthetők pillanatnyilag.</div>';
    }

    $a = shortcode_atts([
      'limit'     => '12',
      'autoplay'  => '1',
      'interval'  => '3000',
      'direction' => 'right',
      'ga4'       => '1',
    ], $atts, 'impact_deals_netflix');

    $fragment_key = 'impactshop_fragment_' . md5('impact_deals_netflix_' . wp_json_encode($a));
    $fragment_cached = get_transient($fragment_key);
    if ($fragment_cached !== false) {
      return $fragment_cached;
    }

    $limit    = max(1, (int)$a['limit']);
    $autoplay = $a['autoplay'] === '1';
    $interval = max(700, (int)$a['interval']);
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ===== segédek ===== */
    $norm_url = function($u){
      $u = trim((string)$u); if ($u==='') return '';
      $p = wp_parse_url($u); if(!$p) return $u;
      $sch = strtolower($p['scheme'] ?? ''); $host = strtolower(preg_replace('~^www\.~','', $p['host'] ?? ''));
      $path = isset($p['path']) ? rtrim($p['path'], '/') : '';
      $qry  = isset($p['query']) ? $p['query'] : '';
      return ($sch && $host) ? ($sch.'://'.$host.$path.($qry?('?'.$qry):'')) : $u;
    };
    $has_u_param = function($u){
      $p = wp_parse_url((string)$u); if(!$p) return false;
      $q = $p['query'] ?? '';
      $path = $p['path'] ?? '';
      // /go-deal és /go-deal/ VAGY akár go-deal végén perjel nélkül is
      $hasGo = (bool)preg_match('~/(go-deal)(?:/|$)~i', $path);
      // többféle paramnév: u, url, to, target, dest, redirect, r
      $hasDeepParam = (bool)preg_match('~(?:^|&)(u|url|to|target|dest|redirect|r)=~i', $q);
      return $hasGo || $hasDeepParam;
    };
    $path_depth = function($u){
      $p = wp_parse_url((string)$u); $path = $p['path'] ?? '';
      if ($path === '' || $path === '/') return 0;
        $parts = array_values(array_filter(explode('/', $path), function ($x) {
            return $x !== '';
        }));
      return count($parts);
    };

    /* ===== 1) Banner feed (ár + alternatív produkt link) – index shop_slug → fallback slug ===== */
    $banner_by_slug = []; // kulcs: shop_slug (lower)
    if (function_exists('do_shortcode')) {
      $json = do_shortcode('[impactshop_deals_banners limit="'.intval($limit*3).'" force="1" json="1"]');
      $rows = json_decode(trim(wp_strip_all_tags($json)), true);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $slug = strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? '')); if(!$slug) continue;
          $banner_by_slug[$slug] = [
            'href'      => (string)($r['href'] ?? ''),
            'price'     => trim((string)($r['price'] ?? '')),
            'old_price' => trim((string)($r['old_price'] ?? '')),
            'img'       => (string)($r['img'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'pct'       => (int)($r['pct'] ?? 0),
            'shop_name' => (string)($r['shop_name'] ?? ''),
          ];
        }
      }
    }
    if (!$banner_by_slug && function_exists('sib_load_banners')) {
      try {
        foreach ((array)sib_load_banners() as $r) {
          $slug = strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? '')); if(!$slug) continue;
          $banner_by_slug[$slug] = [
            'href'      => (string)($r['href'] ?? ''),
            'price'     => trim((string)($r['price'] ?? '')),
            'old_price' => trim((string)($r['old_price'] ?? '')),
            'img'       => (string)($r['img'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'pct'       => (int)($r['pct'] ?? 0),
            'shop_name' => (string)($r['shop_name'] ?? ''),
          ];
        }
      } catch(\Throwable $e){}
    }

    /* ===== 2) REST – ez adja az alap kártyalistát (DEEPLINK-et preferáljuk!) ===== */
    $items = [];
    $rest_rows = [];
    foreach ([home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
              home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit)] as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      if ($rows){ $rest_rows = $rows; break; }
    }

    foreach ($rest_rows as $r) {
      $slug  = strtolower((string)($r['shop_slug'] ?? $r['shop'] ?? ''));
      $href_rest_url = $norm_url($r['url'] ?? '');
      $href_rest_deeplink = $norm_url($r['deeplink'] ?? '');
      $hrefR = '';
      if ($href_rest_url && $has_u_param($href_rest_url)) {
        $hrefR = $href_rest_url;
      } elseif ($href_rest_deeplink && $has_u_param($href_rest_deeplink)) {
        $hrefR = $href_rest_deeplink;
      } else {
        $hrefR = $href_rest_url ?: $href_rest_deeplink;
      }
      $price = trim((string)($r['price'] ?? ''));
      $opric = trim((string)($r['old_price'] ?? ''));
      $b = $banner_by_slug[$slug] ?? null;

      // ár pótlás bannerből
      if ($price==='')  $price = $b['price'] ?? '';
      if ($opric==='')  $opric = $b['old_price'] ?? '';

      // LINK VÁLASZTÁS: ha banner terméklinkes (has_u_param), banner nyer;
      // ha REST "főoldal-gyanús" (sekély path), és van banner link, banner nyer.
      $href = $hrefR;
      if (!empty($b['href']) && !$has_u_param($hrefR)) {
        if ($has_u_param($b['href'])) {
          $href = $b['href']; // banner biztosítja a go-deal paramétert
        } else if ($path_depth($hrefR) <= 1) {
          $href = $b['href'];
        }
      }

      $items[] = [
        'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
        'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ($b['title'] ?? '')),
        'percent'   => (float)($r['percent'] ?? $r['discount'] ?? ($b['pct'] ?? 0)),
        'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ($b['img'] ?? '')),
        'shop_slug' => $slug,
        'shop_name' => (string)($r['shop_name'] ?? ($b['shop_name'] ?? '')),
        'url'       => $href,     // végső link
        'price'     => $price,
        'old_price' => $opric,
        'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
      ];
    }

    $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
    $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
    $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
    $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';

    if ($banner_by_slug) {
      $seen = [];
      foreach ($items as $it) {
        if (!empty($it['shop_slug'])) {
          $seen[strtolower((string)$it['shop_slug'])] = true;
        }
      }
      foreach ($banner_by_slug as $slug => $b) {
        if (strpos($slug, 'cj-') !== 0 || isset($seen[$slug])) {
          continue;
        }
        $href = $b['href'] ?? '';
        if (!$href) {
          $href = $d1
            ? add_query_arg(['d1' => $d1, 'amb' => $amb, 'src' => $src ?: 'impactshop'], home_url('/go/' . rawurlencode($slug)))
            : add_query_arg(['shop' => $slug, 'amb' => $amb], $fillout);
        }
        $items[] = [
          'id'        => '',
          'title'     => (string)($b['title'] ?? ''),
          'percent'   => (int)($b['pct'] ?? 0),
          'image'     => (string)($b['img'] ?? ''),
          'shop_slug' => $slug,
          'shop_name' => (string)($b['shop_name'] ?? ''),
          'url'       => $href,
          'price'     => (string)($b['price'] ?? ''),
          'old_price' => (string)($b['old_price'] ?? ''),
          'currency'  => '',
        ];
        $seen[$slug] = true;
      }
    }

    // ha REST üres volt → közvetlenül a bannereket tesszük ki
    if (!$items && $banner_by_slug) {
      foreach ($banner_by_slug as $slug=>$b) {
        if (!$b['href'] || !$b['img']) continue;
        $items[] = [
          'id'        => '',
          'title'     => $b['title'],
          'percent'   => (int)$b['pct'],
          'image'     => $b['img'],
          'shop_slug' => $slug,
          'shop_name' => $b['shop_name'],
          'url'       => $b['href'],
          'price'     => $b['price'],
          'old_price' => $b['old_price'],
          'currency'  => '',
        ];
        if (count($items) >= $limit) break;
      }
    }

    // CTA finomítás: ha nincs d1 → Fillout, ha van d1 → /go-deal + u (termék URL)
    foreach ($items as &$item) {
      $product_url = impactshop_extract_product_url($item['url'] ?? '');
      $cta = impactshop_build_deal_cta($item['shop_slug'] ?? '', $product_url, $fillout, $d1, $amb, $src);
      if ($cta) {
        $item['url'] = $cta;
      }
    }
    unset($item);

    // szűrés + limit
    $items = array_values(array_filter($items, function ($x) {
        return !empty($x['image']) && !empty($x['url']);
    }));
    $items = array_slice($items, 0, $limit);

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{
        display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 48px;
        cursor:grab; user-select:none; -webkit-user-drag:none; touch-action:pan-x pan-y pinch-zoom;
      }
      .<?php echo $uid; ?> .card{
        position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden
      }
      .<?php echo $uid; ?> .card{ cursor:pointer; pointer-events:auto; }
      .<?php echo $uid; ?> .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; background:#0b1220 }
      .<?php echo $uid; ?> .media::before{
        content:""; position:absolute; inset:0;
        background:
          radial-gradient(120% 100% at 50% 50%, rgba(255,255,255,.06), rgba(0,0,0,.0)),
          linear-gradient(to bottom, rgba(0,0,0,.0) 55%, rgba(0,0,0,.36) 100%);
        pointer-events:none;
      }
      .<?php echo $uid; ?> .media img{
        width:100%; height:100%; object-fit:contain; object-position:center; display:block;
        filter: drop-shadow(0 6px 20px rgba(0,0,0,.25));
      }
      .<?php echo $uid; ?> .badge{
        position:absolute; left:10px; top:10px; padding:6px 10px;
        background:linear-gradient(135deg,#ef4444,#f59e0b); color:#fff; font-weight:900; font-size:12px;
        border-radius:999px; letter-spacing:.03em; z-index:3
      }
      .<?php echo $uid; ?> .shop{
        position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px;
        border-radius:10px; background:rgba(255,255,255,.96); color:#0f172a; z-index:3; max-width:64%;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis
      }
      .<?php echo $uid; ?> .label{
        position:absolute; left:12px; bottom:12px; right:116px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2
      }
      .<?php echo $uid; ?> .price{
        position:absolute; right:10px; bottom:10px; z-index:4; padding:8px 12px;
        border-radius:12px; background:rgba(255,255,255,.98); color:#0f172a; font-weight:900; font-size:14px;
        border:1px solid rgba(0,0,0,.08); box-shadow:0 6px 18px rgba(2,6,23,.22)
      }
      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb; z-index:5 }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.05) }
      .<?php echo $uid; ?> .prev{ left:8px } .<?php echo $uid; ?> .next{ right:8px }
      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }
      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
        .<?php echo $uid; ?> .rail{ padding:4px 44px }
      }
    </style>

    <div class="<?php echo $uid; ?>" tabindex="0">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim((string)($d['title'] ?? ''));
          $pct   = !empty($d['percent']) ? (int)round((float)$d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']); // végső link (deeplink preferálva)
          $pstr  = trim((string)($d['price'] ?? ''));
          $showLabel = (!$pstr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>" target="_blank" rel="nofollow sponsored noopener"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($shop); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title ?: ($shop ?: 'Ajánlat')); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <?php if ($showLabel): ?><div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div><?php endif; ?>
            <?php if ($pstr): ?><div class="price"><?php echo esc_html($pstr); ?></div><?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rail = root.querySelector('[data-rail]');
      const prev = root.querySelector('[data-prev]');
      const next = root.querySelector('[data-next]');
      const cards= Array.from(rail.querySelectorAll('.card'));
      const autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
      const interval = <?php echo (int)$interval; ?>;
      const dirSign  = <?php echo $dir_sign; ?>;

      function gap(){ const cs = getComputedStyle(rail); return parseFloat(cs.columnGap||cs.gap||'14')||14; }
      function step(){ const c = cards[0]; if(!c) return 300; const r = c.getBoundingClientRect(); return r.width + gap(); }
      function atRight(){ return rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2; }
      function atLeft(){ return rail.scrollLeft <= 0; }
      function wrapIfNeeded(){
        if (dirSign > 0 && atRight()) rail.scrollLeft = 0;
        else if (dirSign < 0 && atLeft()) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      }
      function scrollStep(sign){
        wrapIfNeeded();
        rail.scrollBy({ left: sign*step(), behavior: 'smooth' });
        setTimeout(wrapIfNeeded, 80);
      }
      requestAnimationFrame(()=>{ if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0); });

      // SWIPE/DRAG + inercia
      let isDown=false, sx=0, sl=0, moved=0, vx=0, raf=null, lastX=0, lastT=0, dragged=false;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=0; dragged=false; vx=0; sx=e.clientX; sl=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.setPointerCapture(e.pointerId); rail.style.cursor='grabbing'; if (raf) cancelAnimationFrame(raf), raf=null;
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const now=performance.now(), dt=now-lastT||1, dx=e.clientX - sx;
        moved=Math.max(moved, Math.abs(dx)); rail.scrollLeft = sl - dx;
        vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now;
      });
      function pointerEnd(e){
        if(!isDown) return; isDown=false; rail.style.cursor='grab';
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        const scrollMoved = Math.abs(rail.scrollLeft - sl);
        dragged = moved > 32 || scrollMoved > 32;
        if (!dragged) {
          const link = e.target && e.target.closest ? e.target.closest('a.card') : null;
          if (link && link.href) {
            window.open(link.href, link.getAttribute('target') || '_blank', 'noopener');
          }
        }
        let v = Math.max(-1.5, Math.min(1.5, vx)) * 24;
        function tick(){ if (Math.abs(v) < 0.1) return; rail.scrollLeft -= v; v *= 0.92; raf = requestAnimationFrame(tick); }
        raf = requestAnimationFrame(tick);
      }
      rail.addEventListener('pointerup', pointerEnd);
      rail.addEventListener('pointercancel', pointerEnd);

      // Kattintás csak ha nem húzott (küszöb 14px)
      rail.addEventListener('click', (e)=>{
        if(dragged){
          e.preventDefault();
          e.stopPropagation();
          dragged = false;
        }
      }, true);

      // Görgő/trackpad: Y→X kényelmi görgetés
      rail.addEventListener('wheel', (e)=>{
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
        rail.scrollLeft += e.deltaY; e.preventDefault();
      }, { passive:false });

      // Nyilak + billentyű
      prev && prev.addEventListener('click', ()=> scrollStep(-1));
      next && next.addEventListener('click', ()=> scrollStep(+1));
      root.addEventListener('keydown', (e)=>{ if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); } else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); } });

      // Autoplay
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();
    })();
    </script>
    <?php
    $html = ob_get_clean();
    if ($html !== '') {
      set_transient($fragment_key, $html, IMPACTSHOP_FRAGMENT_TTL);
    }
    return $html;
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}

/**
 * Coupons rail.
 * Usage: [impact_coupons_netflix show_code="1"]
 */
// == Impact Coupons – Netflix-sáv (kupon-központú, visszaszámlálóval, autoscroll, GA4 view/click/copy) ==
// Rövidkód: [impact_coupons_netflix autoplay="1" interval="3000" arrows="1" card_w="320" logo_h="48" gap="18" max_items="0" show_code="1" show_expiry="1"]
// Függ: dognet_api_request(), impactshop_get_shops(), impactshop_settings(), impactshop_q(), (opcionális) dognet_extract_campaign_id_from_base()

if (!function_exists('impact_coupons_netflix_shortcode')) {
  function impact_coupons_netflix_shortcode($atts) {
    if (!function_exists('sib_load_banners')) {
      error_log('[impact_coupons_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Kuponok nem elérhetők jelenleg.</div>';
    }

    if (!function_exists('sib_load_banners')) {
      error_log('[impact_coupons_netflix] Missing dependency: sib_load_banners()');
      return '<div class="netflix-error">Kuponok nem elérhetők jelenleg.</div>';
    }

    $a = shortcode_atts([
      'autoplay'    => '1',     // 1 = automatikus léptetés
      'interval'    => '3000',  // ms
      'arrows'      => '1',     // 1 = bal/jobb nyilak
      'card_w'      => '320',   // kártya szélesség (px)
      'logo_h'      => '48',    // logó doboz magasság (px)
      'gap'         => '18',    // kártya-köz (px)
      'max_items'   => '0',     // 0 = nincs limit
      'show_code'   => '1',     // 1 = kuponkód megjelenítése
      'show_expiry' => '1',     // 1 = visszaszámláló / lejárati info megjelenítése
    ], $atts, 'impact_coupons_netflix');

    if (!function_exists('dognet_api_request'))  return '<div>Hiányzó függvény: dognet_api_request</div>';
    if (!function_exists('impactshop_get_shops')) return '<div>Hiányzó függvény: impactshop_get_shops</div>';

    $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
    $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
    $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
    $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';

    $fragment_params = [
      'atts' => $a,
      'd1'   => $d1,
      'amb'  => $amb,
      'src'  => $src,
    ];
    $fragment_key = 'impactshop_fragment_' . md5('impact_coupons_netflix_' . wp_json_encode($fragment_params));
    $fragment_cached = get_transient($fragment_key);
    if ($fragment_cached !== false) {
      return $fragment_cached;
    }

    // -- 1) Aktív kuponok (validity=present) + cache --
    $cache_key = 'impact_coupons_present_cards_v3';
    $coupons = get_transient($cache_key);
    if ($coupons === false) {
      $ad_id = defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0;
      $body  = ['filter'=>['validity'=>['eq'=>'present']], 'per-page'=>500];
      if ($ad_id) $body['ad_channel_id'] = $ad_id;

      $resp = dognet_api_request('POST','/coupons/filter',$body);
      $items = [];
      if (!is_wp_error($resp)) {
        if (isset($resp['data'])  && is_array($resp['data']))  $items = $resp['data'];
        if (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];
      }
      $coupons = $items;
      set_transient($cache_key, $coupons, 10 * MINUTE_IN_SECONDS);
    }
    if (!$coupons || !is_array($coupons)) return '<div>Jelenleg nincs aktív kupon.</div>';

    // -- 2) Shop mapping (campaign_id → shop)
    $shops = impactshop_get_shops(); // [{shop_slug, name, logo, category, dognet_base, ...}]
    $cid_to_shop = [];
    $extract_cid = function($base) {
      if (function_exists('dognet_extract_campaign_id_from_base')) {
        return intval(dognet_extract_campaign_id_from_base($base ?: ''));
      }
      if ($base && preg_match('~[?&]cid=(\d+)~', $base, $m)) return intval($m[1]);
      return 0;
    };
    foreach ($shops as $s) {
      $cid = $extract_cid($s['dognet_base'] ?? '');
      if ($cid) $cid_to_shop[$cid] = $s;
    }

    // -- 3) Kupon-kártyák előkészítése (lejárat, címkék) --
    $cards = [];
    foreach ($coupons as $it) {
      // kampány ID
      $cid = 0;
      foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
        if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
      }
      if (!$cid || !isset($cid_to_shop[$cid])) continue;
      $shop = $cid_to_shop[$cid];

      // Fő kedvezmény: prefer % > amount > title
      $pct  = null; $amt = null; $cur = '';
      foreach (['percent','discount_percent','discount_pct'] as $k) if (isset($it[$k]) && is_numeric($it[$k])) { $pct = floatval($it[$k]); break; }
      foreach (['amount','discount_amount','value_off'] as $k)     if (isset($it[$k]) && is_numeric($it[$k])) { $amt = floatval($it[$k]); break; }
      foreach (['currency','cur'] as $k)                           if (!empty($it[$k])) { $cur = strtoupper(trim($it[$k])); break; }
      $title = ''; foreach (['title','name','label','description'] as $k) if (!empty($it[$k])) { $title = trim($it[$k]); break; }
      $code  = ''; foreach (['code','coupon','coupon_code'] as $k)        if (!empty($it[$k])) { $code  = trim($it[$k]); break; }

      $primary = '';
      if ($pct !== null && $pct > 0)     { $primary = '−'.rtrim(rtrim(number_format($pct,2,'.',''),'0'),'.').'%'; }
      elseif ($amt !== null && $amt > 0) { $primary = '−'.rtrim(rtrim(number_format($amt,2,'.',''),'0'),'.').' '.($cur ?: '€'); }
      elseif ($title)                    { $primary = $title; }
      else                               { $primary = 'Akció'; }

      // Érvényesség vége (több lehetséges kulcs)
      $expiry = '';
      foreach (['valid_to','end_at','date_to','expires_at','validTo','endAt','expiresAt'] as $k) {
        if (!empty($it[$k])) { $expiry = trim($it[$k]); break; }
      }
      // Normalizálás ISO-ra (ha csak dátum, tegyünk hozzá éjféli időt)
      if ($expiry && preg_match('~^\d{4}-\d{2}-\d{2}$~', $expiry)) $expiry .= 'T23:59:59';

      // CTA
      $cta = $d1
        ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src ?: 'impactshop'], home_url('/go/'. rawurlencode($shop['shop_slug'])))
        : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);

      $cards[] = [
        'cid'       => $cid,
        'shop_slug' => $shop['shop_slug'],
        'shop_name' => $shop['name'] ?? $shop['shop_slug'],
        'shop_logo' => $shop['logo'] ?? '',
        'category'  => $shop['category'] ?? '',
        'primary'   => $primary,
        'title'     => $title,
        'code'      => $code,
        'expiry'    => $expiry,   // ISO (ha ismert)
        'cta'       => $cta,
      ];
    }

    $cj_links = impactshop_load_cj_links();
    if ($cj_links) {
      $shop_map = [];
      foreach ($shops as $s) {
        if (!empty($s['shop_slug'])) {
          $shop_map[strtolower((string)$s['shop_slug'])] = $s;
        }
      }
      $seen = array_fill_keys(array_map(function ($c) {
        return strtolower((string)($c['shop_slug'] ?? ''));
      }, $cards), true);

      foreach ($cj_links as $link) {
        if (!is_array($link) || !impactshop_cj_link_country_ok($link)) {
          continue;
        }
        $slug = impactshop_cj_link_shop_slug($link);
        if ($slug === '' || isset($seen[$slug])) {
          continue;
        }
        $shop = $shop_map[$slug] ?? [];
        $title = (string)($link['link_name'] ?? ($link['description'] ?? ''));
        $code = (string)($link['coupon_code'] ?? '');
        $primary = $code !== '' ? ('Kupon: ' . $code) : ($title ?: 'Akció');
        $expiry = (string)($link['promotion_end'] ?? '');
        if ($expiry && preg_match('~^\d{4}-\d{2}-\d{2}$~', $expiry)) {
          $expiry .= 'T23:59:59';
        }
        $cta = $d1
          ? add_query_arg(['d1' => $d1, 'amb' => $amb, 'src' => $src ?: 'impactshop'], home_url('/go/' . rawurlencode($slug)))
          : add_query_arg(['shop' => $slug, 'amb' => $amb], $fillout);
        $cards[] = [
          'cid'       => 0,
          'shop_slug' => $slug,
          'shop_name' => $shop['name'] ?? ($link['advertiser_name'] ?? $slug),
          'shop_logo' => $shop['logo'] ?? ($link['logo_url'] ?? ''),
          'category'  => $shop['category'] ?? '',
          'primary'   => $primary,
          'title'     => $title,
          'code'      => $code,
          'expiry'    => $expiry,
          'cta'       => $cta,
        ];
        $seen[$slug] = true;
      }
    }
    if (!$cards) return '<div>Jelenleg nincs megjeleníthető kupon.</div>';

    $max = max(0, intval($a['max_items']));
    if ($max > 0) $cards = array_slice($cards, 0, $max);

    // Render
    $uid   = 'icn-'.substr(md5(json_encode([$a, count($cards)]).microtime(true)),0,8);
    $cardW = max(240, intval($a['card_w']));
    $logoH = max(36,  intval($a['logo_h']));
    $gap   = max(8,   intval($a['gap']));
    $interval = max(1200, intval($a['interval']));
    $show_expiry = ($a['show_expiry'] === '1');

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?> { --w: <?php echo $cardW; ?>px; --gap: <?php echo $gap; ?>px; --logoH: <?php echo $logoH; ?>px; }
      .<?php echo $uid; ?> .icn-wrap { position:relative; }
      .<?php echo $uid; ?> .icn-track {
        display:flex; gap:var(--gap); overflow-x:auto; scroll-snap-type:x mandatory; padding:10px 4px;
        scrollbar-width:none;
      }
      .<?php echo $uid; ?> .icn-track::-webkit-scrollbar { display:none; }

      .<?php echo $uid; ?> .icn-card { flex:0 0 auto; width:var(--w); scroll-snap-align:start; position:relative; }
      .<?php echo $uid; ?> .icn-card a {
        display:block; border-radius:16px; padding:12px; text-decoration:none;
        background:rgba(255,255,255,.72); border:1px solid rgba(0,0,0,.08);
        backdrop-filter:saturate(180%) blur(10px);
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        box-shadow:0 8px 24px rgba(0,0,0,.08); color:#111;
      }
      .<?php echo $uid; ?> .icn-card a:hover { transform:translateY(-2px) scale(1.02); box-shadow:0 12px 30px rgba(0,0,0,.12); border-color:rgba(0,0,0,.12); }

      .<?php echo $uid; ?> .icn-head { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
      .<?php echo $uid; ?> .icn-logoBox {
        width:calc(var(--logoH) * 1.7); height:var(--logoH); border-radius:10px; background:rgba(255,255,255,.6);
        display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid rgba(0,0,0,.06);
      }
      .<?php echo $uid; ?> .icn-logo { max-width:100%; max-height:100%; object-fit:contain; display:block; }
      .<?php echo $uid; ?> .icn-shop { font:600 14px/1.2 system-ui; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

      .<?php echo $uid; ?> .icn-primary { font:800 26px/1.1 system-ui; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .<?php echo $uid; ?> .icn-title   { font:600 13px/1.25 system-ui; color:#333; max-height:2.6em; overflow:hidden; }

      .<?php echo $uid; ?> .icn-actions { margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
      .<?php echo $uid; ?> .icn-code    { font:700 12px/1 system-ui; padding:6px 8px; border-radius:10px; background:#111; color:#fff; letter-spacing:0.04em; }
      .<?php echo $uid; ?> .icn-copy    { font:700 12px/1 system-ui; padding:6px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.15); background:#fff; color:#0f172a; cursor:pointer; box-shadow:0 3px 8px rgba(15,23,42,.12); transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
      .<?php echo $uid; ?> .icn-copy:hover { box-shadow:0 5px 12px rgba(15,23,42,.18); border-color:rgba(15,23,42,.18); }
      .<?php echo $uid; ?> .icn-copy:active { transform: translateY(1px); }

      /* Countdown sáv */
      .<?php echo $uid; ?> .icn-expiry { margin-top:8px; font:600 12px/1.2 system-ui; color:#333; display:flex; align-items:center; gap:8px; }
      .<?php echo $uid; ?> .icn-countdown { font:800 12px/1 system-ui; padding:4px 6px; border-radius:8px; background:rgba(255,165,0,.18); color:#7a3d00; border:1px solid rgba(122,61,0,.18); }
      .<?php echo $uid; ?> .icn-countdown.urg-24h { background:rgba(220,0,0,.14); color:#a00000; border-color:rgba(160,0,0,.25); }
      .<?php echo $uid; ?> .icn-countdown.done { opacity:.6; text-decoration:line-through; }

      /* Nyilak */
      .<?php echo $uid; ?> .icn-arrow {
        position:absolute; top:50%; transform:translateY(-50%); width:36px; height:36px; border-radius:999px;
        border:1px solid rgba(0,0,0,.15); background:rgba(255,255,255,.9); color:#111; display:flex; align-items:center; justify-content:center;
        cursor:pointer; user-select:none; z-index:2; transition:transform .15s, box-shadow .15s; box-shadow:0 6px 16px rgba(0,0,0,.10);
      }
      .<?php echo $uid; ?> .icn-arrow:hover { transform:translateY(-50%) scale(1.06); }
      .<?php echo $uid; ?> .icn-arrow.prev { left:-6px; }
      .<?php echo $uid; ?> .icn-arrow.next { right:-6px; }
      @media (max-width:780px){ .<?php echo $uid; ?> .icn-arrow{ display:none; } }
    </style>

    <div class="<?php echo $uid; ?> impact-coupons-netflix" data-show-expiry="<?php echo $show_expiry ? '1' : '0'; ?>">
      <div class="icn-wrap">
        <?php if ($a['arrows']==='1'): ?>
          <div class="icn-arrow prev" aria-hidden="true" title="Vissza">‹</div>
          <div class="icn-arrow next" aria-hidden="true" title="Előre">›</div>
        <?php endif; ?>

        <div class="icn-track">
          <?php foreach ($cards as $c): ?>
            <div class="icn-card"<?php echo $c['expiry'] ? ' data-expiry="'.esc_attr($c['expiry']).'"' : ''; ?>>
              <a href="<?php echo esc_url($c['cta']); ?>"
                 aria-label="<?php echo esc_attr($c['shop_name'].' – kupon'); ?>"
                 data-event="coupon_click"
                 data-shop-slug="<?php echo esc_attr($c['shop_slug']); ?>"
                 data-shop-name="<?php echo esc_attr($c['shop_name']); ?>"
                 data-coupon-primary="<?php echo esc_attr($c['primary']); ?>"
                 data-coupon-code="<?php echo esc_attr($c['code']); ?>">
                <div class="icn-head">
                  <div class="icn-logoBox">
                    <?php if (!empty($c['shop_logo'])): ?>
                      <img class="icn-logo" src="<?php echo esc_url($c['shop_logo']); ?>" alt="<?php echo esc_attr($c['shop_name']); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                      <span style="font:600 12px system-ui;color:#666">Logo</span>
                    <?php endif; ?>
                  </div>
                  <div class="icn-shop" title="<?php echo esc_attr($c['shop_name']); ?>"><?php echo esc_html($c['shop_name']); ?></div>
                </div>

                <div class="icn-primary"><?php echo esc_html($c['primary']); ?></div>
                <?php if (!empty($c['title'])): ?>
                  <div class="icn-title"><?php echo esc_html($c['title']); ?></div>
                <?php endif; ?>

                <?php if ($a['show_code']==='1' && !empty($c['code'])): ?>
                  <div class="icn-actions">
                    <span class="icn-code"><?php echo esc_html($c['code']); ?></span>
                    <button type="button" class="icn-copy"
                            data-event="coupon_copy"
                            data-shop-slug="<?php echo esc_attr($c['shop_slug']); ?>"
                            data-shop-name="<?php echo esc_attr($c['shop_name']); ?>"
                            data-coupon-primary="<?php echo esc_attr($c['primary']); ?>"
                            data-coupon-code="<?php echo esc_attr($c['code']); ?>">
                      Másolás
                    </button>
                  </div>
                <?php endif; ?>

                <?php if ($show_expiry): ?>
                  <div class="icn-expiry">
                    <span class="icn-countdown" aria-live="polite"></span>
                  </div>
                <?php endif; ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <script>
      (function(){
        var root  = document.currentScript.previousElementSibling;
        if(!root) return;
        var track = root.querySelector('.icn-track');
        var prev  = root.querySelector('.icn-arrow.prev');
        var next  = root.querySelector('.icn-arrow.next');
        var showExpiry = root.getAttribute('data-show-expiry') === '1';

        function scrollByAmount(dir){
          var w = track.clientWidth || 320;
          track.scrollBy({ left: dir * Math.max(280, Math.round(w*0.85)), behavior:'smooth' });
        }
        if (prev) prev.addEventListener('click', function(){ scrollByAmount(-1); });
        if (next) next.addEventListener('click', function(){ scrollByAmount( 1); });

        // Autoplay
        var autoplay = <?php echo ($a['autoplay']==='1' ? 'true' : 'false'); ?>;
        var interval = <?php echo intval($interval); ?>;
        var timer = null, paused = false;
        function tick(){
          if (paused) return;
          var maxScroll = track.scrollWidth - track.clientWidth;
          if (track.scrollLeft >= maxScroll - 10) track.scrollTo({ left:0, behavior:'auto' });
          else scrollByAmount(1);
        }
        function start(){ if (autoplay && !timer) timer = setInterval(tick, interval); }
        function stop(){ if (timer) { clearInterval(timer); timer = null; } }
        root.addEventListener('mouseenter', function(){ paused = true; });
        root.addEventListener('mouseleave', function(){ paused = false; });
        ['wheel','touchstart','mousedown','keydown'].forEach(function(ev){
          track.addEventListener(ev, function(){ stop(); setTimeout(start, interval*2); }, {passive:true});
        });
        start();

        // GA4 (view / click / copy)
        if (!window.dataLayer) window.dataLayer = [];

        // coupon_click a kártya-kattintásra
        track.addEventListener('click', function(ev){
          var a = ev.target.closest('a[data-event="coupon_click"]');
          if(!a) return;
          try {
            var q = new URLSearchParams(location.search);
            window.dataLayer.push({
              event: 'coupon_click',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              coupon_primary: a.getAttribute('data-coupon-primary') || '',
              coupon_code: a.getAttribute('data-coupon-code') || '',
              ngo:  q.get('d1')  || '',
              amb:  q.get('amb') || '',
              src:  q.get('src') || 'impactshop'
            });
          } catch(e){}
        }, true);

        // coupon_copy a másolás-gombra
        track.addEventListener('click', function(ev){
          var btn = ev.target.closest('button.icn-copy[data-event="coupon_copy"]');
          if (!btn) return;
          var code = btn.getAttribute('data-coupon-code') || '';
          if (!code) return;

          // Vágólap
          var copied = false;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function(){ copied = true; }).catch(function(){});
          }
          if (!copied) {
            var ta = document.createElement('textarea');
            ta.value = code; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e){}
            document.body.removeChild(ta);
          }

          // GA4 push
          try {
            var q = new URLSearchParams(location.search);
            window.dataLayer.push({
              event: 'coupon_copy',
              shop_slug: btn.getAttribute('data-shop-slug') || '',
              shop_name: btn.getAttribute('data-shop-name') || '',
              coupon_primary: btn.getAttribute('data-coupon-primary') || '',
              coupon_code: code,
              ngo:  q.get('d1')  || '',
              amb:  q.get('amb') || '',
              src:  q.get('src') || 'impactshop'
            });
          } catch(e){}
        }, true);

        // coupon_view – akkor lőjük, amikor a kártya először látszik (IntersectionObserver)
        var seen = new WeakSet();
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(ent){
            if (!ent.isIntersecting) return;
            var card = ent.target;
            if (seen.has(card)) return;
            seen.add(card);
            var a = card.querySelector('a[data-event="coupon_click"]');
            if (!a) return;

            var bucket = (function(){
              var exp = card.getAttribute('data-expiry');
              if (!exp) return 'unknown';
              var left = (new Date(exp).getTime()) - Date.now();
              if (left <= 0) return 'expired';
              var d = left / (24*3600*1000);
              if (d > 7) return '>7d';
              if (d > 1) return '1–7d';
              return '<24h';
            })();

            try {
              var q = new URLSearchParams(location.search);
              window.dataLayer.push({
                event: 'coupon_view',
                shop_slug: a.getAttribute('data-shop-slug') || '',
                shop_name: a.getAttribute('data-shop-name') || '',
                coupon_primary: a.getAttribute('data-coupon-primary') || '',
                coupon_code: a.getAttribute('data-coupon-code') || '',
                time_left_bucket: bucket,
                ngo:  q.get('d1')  || '',
                amb:  q.get('amb') || '',
                src:  q.get('src') || 'impactshop'
              });
            } catch(e){}
          });
        }, { root: track, rootMargin: '0px 20px 0px 0px', threshold: 0.5 });

        Array.prototype.forEach.call(track.querySelectorAll('.icn-card'), function(card){ io.observe(card); });

        // COUNTDOWN: frissítés 1 mp-enként, <24h piros, lejáratkor elrejt
        if (showExpiry) {
          function fmt2(n){ n = Math.floor(Math.abs(n)); return (n<10?'0':'')+n; }
          function fmtDate(dt){
            // YYYY-MM-DD HH:MM (helyi idő – Europe/Budapest)
            return dt.getFullYear()+'-'+fmt2(dt.getMonth()+1)+'-'+fmt2(dt.getDate())+' '+fmt2(dt.getHours())+':'+fmt2(dt.getMinutes());
          }
          function tickCountdown(){
            var cards = track.querySelectorAll('.icn-card');
            cards.forEach(function(card){
              var exp = card.getAttribute('data-expiry');
              var cd  = card.querySelector('.icn-countdown');
              if (!cd) return;
              if (!exp) {
                cd.textContent = 'Lejárat: folyamatos';
                cd.classList.remove('urg-24h','done');
                return;
              }
              var t = new Date(exp).getTime();
              if (isNaN(t)) {
                cd.textContent = 'Lejár: '+exp;
                cd.classList.remove('urg-24h','done');
                return;
              }
              var left = t - Date.now();
              if (left <= 0) {
                cd.textContent = 'Lejárt';
                cd.classList.add('done');
                // Rejtés, hogy ne maradjon "szellemkupon"
                card.style.display = 'none';
                return;
              }
              var days = Math.floor(left / (24*3600*1000));
              var rem = left % (24*3600*1000);
              var hrs  = Math.floor(rem / (3600*1000));
              rem     %= (3600*1000);
              var mins = Math.floor(rem / (60*1000));
              var secs = Math.floor((rem % (60*1000)) / 1000);

              if (days >= 7) {
                cd.textContent = 'Lejár: '+fmtDate(new Date(t));
                cd.classList.remove('urg-24h','done');
              } else if (days >= 1) {
                cd.textContent = 'Lejár: '+days+' nap';
                cd.classList.remove('urg-24h','done');
              } else {
                cd.textContent = 'Lejár: '+fmt2(hrs)+':'+fmt2(mins)+':'+fmt2(secs);
                cd.classList.toggle('urg-24h', true);
                cd.classList.remove('done');
              }
            });
          }
          tickCountdown();
          setInterval(tickCountdown, 1000);
        }
      })();
    </script>
    <?php
    $html = ob_get_clean();
    if ($html !== '') {
      set_transient($fragment_key, $html, IMPACTSHOP_FRAGMENT_TTL);
    }
    return $html;
  }
  add_shortcode('impact_coupons_netflix','impact_coupons_netflix_shortcode');
}
