<?php
/**
 * Plugin Name: ImpactShop Go Bridge
 * Description: Reuses the ImpactShop Boot Dognet helpers when the legacy go resolver is active.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('isb_log_go_click')) {
    function isb_log_go_click($shop, $ngo, $finalUrl, $isCj, $pseudo)
    {
        if (!$finalUrl) {
            return;
        }
        $upload = wp_upload_dir();
        $path = rtrim($upload['basedir'] ?? '', '/') . '/impactshop-go-clicks.log';
        if (!$path) {
            return;
        }
        $parts = parse_url($finalUrl);
        $targetHost = $parts['host'] ?? '';
        $sid = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['sid'])) {
                $sid = $qs['sid'];
            }
        }
        $line = json_encode([
            'ts' => gmdate('c'),
            'shop' => $shop,
            'ngo' => $ngo,
            'sid' => $sid,
            'is_cj' => $isCj ? 1 : 0,
            'pseudo' => $pseudo,
            'target_host' => $targetHost,
        ], JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        if (function_exists('impactshop_log_event')) {
            impactshop_log_event('go_click', [
                'event_source' => 'go_bridge',
                'ngo_slug' => $ngo,
                'shop_slug' => $shop,
                'network' => $isCj ? 'cj' : 'dognet',
                'meta' => [
                    'sid' => $sid,
                    'target_host' => $targetHost,
                    'final_url' => $finalUrl,
                ],
                'pseudo_id' => $pseudo,
            ]);
        }
    }
}

add_filter('allowed_redirect_hosts', function ($hosts) {
    $hosts[] = 'go.dognet.com';
    $hosts[] = 'api.app.dognet.com';
    return array_unique(array_filter($hosts));
});

add_filter('impactshop_go_resolve', function ($target, $slug, $isDeal, $query) {
    if ($target) {
        return $target;
    }

    if (!function_exists('isb_find_shop')) {
        return $target;
    }

    $slug = sanitize_title($slug ?: '');
    if ($slug === '') {
        return $target;
    }

    $ngo = '';
    if (!empty($query['d1']) && is_string($query['d1'])) {
        $ngo = sanitize_title($query['d1']);
    } elseif (function_exists('sh_d1_for')) {
        $ngo = sh_d1_for($slug);
    }

    if ($ngo === '') {
        return $target;
    }

    $row = isb_find_shop($slug);
    if (!$row) {
        // Ha a CSV-ben nincs, próbáljuk meg az optionben tárolt shop registry-t (CJ-hez).
        $opt = get_option('impactshop_shops');
        if (is_string($opt)) {
            $decoded = json_decode($opt, true);
        } elseif (is_array($opt)) {
            $decoded = $opt;
        } else {
            $decoded = [];
        }
        if (is_array($decoded)) {
            foreach ($decoded as $candidate) {
                if (!is_array($candidate) || empty($candidate['slug'])) {
                    continue;
                }
                if (sanitize_title($candidate['slug']) === $slug) {
                    $row = [
                        'shop_slug'      => $slug,
                        'product_url'    => $candidate['default_cta_url'] ?? '',
                        'dognet_base'    => $candidate['dognet_base'] ?? '',
                        'deeplink_param' => $candidate['deeplink_param'] ?? 'url',
                        'cj_click_url'   => $candidate['cj_click_url'] ?? '',
                        'cj_program_id'  => $candidate['program_id'] ?? '',
                        'network'        => $candidate['network'] ?? '',
                    ];
                    break;
                }
            }
        }
        if (!$row) {
            // CJ fallback: olvassuk a tools/cj_shops.json listát (slug + program_id).
            if (strpos($slug, 'cj-') === 0) {
                $cj_path = dirname(WP_CONTENT_DIR) . '/tools/cj_shops.json';
                if (file_exists($cj_path)) {
                    $cj_raw = file_get_contents($cj_path);
                    $cj_list = json_decode((string) $cj_raw, true);
                    if (is_array($cj_list)) {
                        foreach ($cj_list as $candidate) {
                            if (!is_array($candidate) || empty($candidate['slug'])) {
                                continue;
                            }
                            if (sanitize_title($candidate['slug']) === $slug) {
                                $cjClick = $candidate['cj_click_url'] ?? ($candidate['tracking_template'] ?? ($candidate['click_url'] ?? ''));
                                $cjProgram = $candidate['program_id'] ?? ($candidate['advertiser_id'] ?? '');
                                $productUrl = $candidate['program_url'] ?? ($candidate['destination'] ?? ($candidate['product_url'] ?? ''));
                                $row = [
                                    'shop_slug'      => $slug,
                                    'product_url'    => $productUrl,
                                    'dognet_base'    => '',
                                    'deeplink_param' => $candidate['deeplink_param'] ?? 'url',
                                    'cj_click_url'   => $cjClick,
                                    'cj_program_id'  => $cjProgram,
                                    'network'        => 'cj',
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }
        if (!$row) {
            return $target;
        }
    }

    $targetUrl = '';
    if ($isDeal) {
        if (!empty($query['u']) && is_string($query['u'])) {
            $targetUrl = esc_url_raw($query['u']);
        } elseif (!empty($row['product_url'])) {
            $targetUrl = esc_url_raw($row['product_url']);
        }
    }

    if ($targetUrl && function_exists('isb_clean_deeplink')) {
        $targetUrl = isb_clean_deeplink($targetUrl);
    }

    $link = null;
    $cjEnv = getenv('IMPACTSHOP_ENABLE_CJ_GO');
    $cjEnabled = $cjEnv === false ? true : (bool) $cjEnv; // alapból bekapcsoljuk, de env-vel letiltható

    /**
     * CJ fallback (only if data is present and explicitly enabled).
     * We deliberately run this before Dognet so a CJ-only shop tud is működni,
     * de csak akkor aktiválódik, ha a shop sorban van CJ adat (cj_click_url vagy cj_program_id),
     * és az IMPACTSHOP_ENABLE_CJ_GO flag be van kapcsolva.
     */
    if ($cjEnabled && !$link && !empty($row['cj_click_url'])) {
        $cjBase = '';
        $cjBase = esc_url_raw($row['cj_click_url']);

        if ($cjBase) {
            $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
            $sid = function_exists('isb_build_cj_sid') ? isb_build_cj_sid($ngo) : $ngo;
            $params = [
                'sid' => $sid,
            ];
            if ($targetUrl) {
                $params[$deeplinkParam] = $targetUrl;
            }

            /**
             * Lehetőséget adunk külső filternek finomhangolni a CJ linket
             * (pl. ha a CJ domain hostot felül kell bírálni).
             */
            $cjBase = apply_filters('impactshop_cj_click_base', $cjBase, $row, $slug, $ngo, $targetUrl);
            $link = $cjBase . (strpos($cjBase, '?') === false ? '?' : '&') . http_build_query($params);
        }
    }
    if (!$link && !empty($row['cj_program_id']) && empty($row['cj_click_url']) && $targetUrl) {
        $link = $targetUrl;
    }

    if (function_exists('isb_dognet_extract_campaign_id_from_base') && function_exists('isb_dognet_api_generate_link')) {
        $cid = isb_dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
        if ($cid) {
            $api = isb_dognet_api_generate_link($cid, $targetUrl, $ngo, '');
            if (!is_wp_error($api) && !empty($api)) {
                $link = $api;
            }
        }
    }

  if (!$link && !empty($row['dognet_base'])) {
    $params = [
      'd1' => $ngo,
    ];
        if ($targetUrl) {
            $dlParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
            $params[$dlParam] = $targetUrl;
    }
    $link = $row['dognet_base'] . (strpos($row['dognet_base'], '?') === false ? '?' : '&') . http_build_query($params);
  }

  if (!$link) {
    // Fallback: ha nincs Dognet link, menjünk közvetlenül a shop URL-re UTM + d1 paraméterrel.
    $fallback = $targetUrl ?: ($row['product_url'] ?? '');
    if ($fallback) {
      $fallback = add_query_arg(
        [
          'utm_source' => 'impactshop',
          'utm_medium' => 'go',
          'utm_campaign' => $slug,
          'd1' => $ngo,
          'src' => 'impi',
        ],
        $fallback
      );
      return $fallback;
    }
    return $target;
  }

    $propagate = ['amb', 'src', 'click_id', 'aff', 'utm_source', 'utm_medium'];
    $extra = [];
    foreach ($propagate as $key) {
        if (!empty($query[$key]) && is_string($query[$key])) {
            $extra[$key] = $query[$key];
        }
    }
    if (empty($extra['src'])) {
        $extra['src'] = 'impactshop';
    }
    if ($extra) {
        $link .= (strpos($link, '?') === false ? '?' : '&') . http_build_query($extra);
    }

    return $link;
}, 5, 4);

/**
 * Early CJ handler: ha a go/ vagy go-deal/ kérés CJ-s slugra érkezik,
 * a Boot "ismeretlen shop" hibája előtt megpróbáljuk lekezelni a CJ linket.
 * Csak akkor fut le, ha a slug cj- prefixű és van cj_click_url vagy program_id az optionben.
 */
add_action('template_redirect', function () {
    if (!get_query_var('impactshop_go') && !get_query_var('impactshop_deal')) {
        return;
    }
    $slug = sanitize_title(get_query_var('impactshop_slug') ?: '');
    if ($slug === '' || strpos($slug, 'cj-') !== 0) {
        return;
    }
    $ngo = isset($_GET['d1']) ? sanitize_title(wp_unslash($_GET['d1'])) : '';
    $targetUrl = isset($_GET['u']) ? esc_url_raw(wp_unslash($_GET['u'])) : '';
    if (!$ngo) {
        return;
    }

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

    $entry = null;
    foreach ($registry as $row) {
        if (!is_array($row) || empty($row['slug'])) {
            continue;
        }
        if (sanitize_title($row['slug']) === $slug) {
            $entry = $row;
            break;
        }
    }
    if (!$entry) {
        return;
    }

    $cjBase = '';
    if (!empty($entry['cj_click_url'])) {
        $cjBase = esc_url_raw($entry['cj_click_url']);
    }
    if (!$cjBase) {
        return;
    }

    $deeplinkParam = !empty($entry['deeplink_param']) ? $entry['deeplink_param'] : 'url';
    $sid = function_exists('isb_build_cj_sid') ? isb_build_cj_sid($ngo) : $ngo;
    $params = ['sid' => $sid];
    if ($targetUrl) {
        $params[$deeplinkParam] = $targetUrl;
    }

    $cjBase = apply_filters('impactshop_cj_click_base', $cjBase, $entry, $slug, $ngo, $targetUrl);
    $link = $cjBase . (strpos($cjBase, '?') === false ? '?' : '&') . http_build_query($params);

    $propagate = ['amb', 'src', 'click_id', 'aff', 'utm_source', 'utm_medium'];
    $extra = [];
    foreach ($propagate as $key) {
        if (!empty($_GET[$key]) && is_string($_GET[$key])) {
            $extra[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
    }
    if (empty($extra['src'])) {
        $extra['src'] = 'impactshop';
    }
    if ($extra) {
        $link .= (strpos($link, '?') === false ? '?' : '&') . http_build_query($extra);
    }

    $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])) : '';
    isb_log_go_click($slug, $ngo, $link, true, $pseudo);
    wp_redirect($link, 307);
    exit;
}, 1);
