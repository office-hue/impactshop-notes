<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_CLICK_TRACKING_SCHEMA = '1.1.0';
const IMPACTSHOP_CLICK_TRACKING_OPTION_SCHEMA = 'impactshop_click_tracking_schema_version';

add_action('muplugins_loaded', 'impactshop_click_tracking_boot');

function impactshop_click_tracking_is_always_reward_enabled(): bool
{
    if (function_exists('impactshop_ads_watch_is_always_reward_enabled')) {
        return (bool) impactshop_ads_watch_is_always_reward_enabled();
    }

    return (bool) apply_filters('impactshop_ads_watch_always_reward_enabled', false);
}

function impactshop_click_tracking_is_sandbox_request(WP_REST_Request $request): bool
{
    if (function_exists('impactshop_ads_watch_get_request_write_mode')) {
        return impactshop_ads_watch_get_request_write_mode($request) === 'sandbox';
    }
    $referer = (string) ($request->get_header('referer') ?? '');
    $dev_slug = defined('IMPACTSHOP_ADS_DEV_CLONE_SLUG') ? IMPACTSHOP_ADS_DEV_CLONE_SLUG : 'impact-challenge-dev';
    return $referer !== '' && strpos($referer, '/' . $dev_slug) !== false;
}

function impactshop_click_tracking_build_instance_dedupe(string $content_type, string $content_id, string $pseudo_id): string
{
    return sprintf(
        'cta_click:%s:%s:%s:%s:%d',
        $content_type,
        $content_id !== '' ? $content_id : 'unknown',
        $pseudo_id,
        current_time('Y-m-d-H-i-s'),
        wp_rand(1000, 999999)
    );
}

function impactshop_click_tracking_boot(): void
{
    impactshop_click_tracking_maybe_install();
    add_action('rest_api_init', 'impactshop_click_tracking_register_routes');
}

function impactshop_click_tracking_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_CLICK_TRACKING_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_CLICK_TRACKING_SCHEMA) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_click_tracking';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        content_type VARCHAR(40) NOT NULL,
        content_id VARCHAR(128) DEFAULT '',
        cta_url TEXT,
        shop_slug VARCHAR(190) DEFAULT '',
        category VARCHAR(190) DEFAULT '',
        price_range VARCHAR(64) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_pseudo (pseudo_id),
        KEY idx_content (content_type, content_id),
        KEY idx_shop (shop_slug)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option(IMPACTSHOP_CLICK_TRACKING_OPTION_SCHEMA, IMPACTSHOP_CLICK_TRACKING_SCHEMA, false);
}

function impactshop_click_tracking_register_routes(): void
{
    register_rest_route('impact/v1', '/tracking/cta-click', [
        'methods' => 'POST',
        'callback' => 'impactshop_click_tracking_handle',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_click_tracking_handle(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_body_params();
    }

    $pseudo_id = '';
    if (function_exists('impactshop_identity_profile_cookie')) {
        $pseudo_id = (string) impactshop_identity_profile_cookie();
    }
    if ($pseudo_id === '' && !empty($_COOKIE['impactshop_pseudo_id'])) {
        $pseudo_id = sanitize_text_field((string) $_COOKIE['impactshop_pseudo_id']);
    }

    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'missing_identity'], 200);
    }

    $content_type = sanitize_text_field((string) ($params['content_type'] ?? '')); 
    $content_id = sanitize_text_field((string) ($params['content_id'] ?? ''));
    $cta_url = esc_url_raw((string) ($params['cta_url'] ?? ''));
    $shop_slug = sanitize_text_field((string) ($params['shop_slug'] ?? ''));
    $category = sanitize_text_field((string) ($params['category'] ?? ''));
    $price_range = sanitize_text_field((string) ($params['price_range'] ?? ''));
    $points_hint = (int) ($params['points'] ?? 0);
    if ($points_hint < 0) {
        $points_hint = 0;
    }
    $dedupe_key = sanitize_text_field((string) ($params['dedupe_key'] ?? ''));

    if ($content_type === '') {
        $content_type = 'unknown';
    }

    if ($dedupe_key === '') {
        if (impactshop_click_tracking_is_always_reward_enabled()) {
            $dedupe_key = impactshop_click_tracking_build_instance_dedupe($content_type, $content_id, $pseudo_id);
        } else {
            $dedupe_key = 'cta_click:' . $content_type . ':' . $content_id . ':' . md5($cta_url);
        }
    } elseif (impactshop_click_tracking_is_always_reward_enabled()) {
        $dedupe_key = $dedupe_key . ':' . current_time('Y-m-d-H-i-s') . ':' . wp_rand(1000, 999999);
    }

    $dedupe_key = substr($dedupe_key, 0, 140);

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_click_tracking';
    $wpdb->insert(
        $table,
        [
            'pseudo_id' => $pseudo_id,
            'content_type' => $content_type,
            'content_id' => $content_id,
            'cta_url' => $cta_url,
            'shop_slug' => $shop_slug,
            'category' => $category,
            'price_range' => $price_range,
            'created_at' => current_time('mysql'),
        ],
        ['%s','%s','%s','%s','%s','%s','%s','%s']
    );

    if (impactshop_click_tracking_is_sandbox_request($request)) {
        return new WP_REST_Response([
            'status' => 'ok',
            'sandbox' => true,
            'reward_enabled' => $points_hint > 0,
            'duplicate' => false,
            'awarded_points' => 0,
            'awarded_votes' => 0,
            'new_total' => null,
            'available_votes' => function_exists('impactshop_ads_watch_get_user_votes')
                ? (int) impactshop_ads_watch_get_user_votes($pseudo_id)
                : null,
        ], 200);
    }

    $reward_enabled = $points_hint > 0;
    $cta_points = $reward_enabled ? 5 : 0;
    $cta_votes = $reward_enabled ? 5 : 0;
    $awarded_points = 0;
    $awarded_votes = 0;
    $duplicate = false;
    $new_total = null;
    $available_votes = function_exists('impactshop_ads_watch_get_user_votes')
        ? (int) impactshop_ads_watch_get_user_votes($pseudo_id)
        : null;

    if ($reward_enabled && $cta_points > 0) {
        $points_result = ['success' => false, 'duplicate' => false, 'new_total' => null];

        if (class_exists('Sharity_Points_Manager')) {
            $manager = new Sharity_Points_Manager();
            $points_result = $manager->award_points_for_pseudo(
                $pseudo_id,
                $cta_points,
                'bonus',
                'cta_click',
                [
                    'source_type' => 'cta_click',
                    'content_type' => $content_type,
                    'content_id' => $content_id,
                    'cta_url' => $cta_url,
                    'shop_slug' => $shop_slug,
                    'category' => $category,
                    'price_range' => $price_range,
                ],
                $dedupe_key
            );
            $duplicate = !empty($points_result['duplicate']);
            if (!empty($points_result['success']) && !$duplicate) {
                $awarded_points = $cta_points;
            }
            if (isset($points_result['new_total']) && is_numeric($points_result['new_total'])) {
                $new_total = (int) $points_result['new_total'];
            }
        } else {
            $fallback_dedupe_key = 'impactshop_cta_click:' . md5($pseudo_id . '|' . $dedupe_key);
            if (get_transient($fallback_dedupe_key)) {
                $duplicate = true;
            } else {
                set_transient($fallback_dedupe_key, 1, DAY_IN_SECONDS);
                $awarded_points = $cta_points;
            }
        }

        if (!$duplicate && $awarded_points > 0 && function_exists('impactshop_ads_watch_add_votes')) {
            $available_votes = (int) impactshop_ads_watch_add_votes($pseudo_id, $cta_votes);
            $awarded_votes = $cta_votes;
        } elseif (function_exists('impactshop_ads_watch_get_user_votes')) {
            $available_votes = (int) impactshop_ads_watch_get_user_votes($pseudo_id);
        }
    }

    if ($new_total === null && class_exists('Sharity_Points_Manager')) {
        $manager = new Sharity_Points_Manager();
        $snapshot = $manager->get_points_snapshot_for_pseudo($pseudo_id);
        if (isset($snapshot['points_total']) && is_numeric($snapshot['points_total'])) {
            $new_total = (int) $snapshot['points_total'];
        }
    }

    if ($shop_slug !== '') {
        $prefs_key = 'user_prefs_' . $pseudo_id;
        $prefs = get_transient($prefs_key);
        $prefs = is_array($prefs) ? $prefs : ['shop_clicks' => [], 'categories' => [], 'price_ranges' => []];
        $prefs['shop_clicks'][$shop_slug] = ($prefs['shop_clicks'][$shop_slug] ?? 0) + 1;
        if ($category !== '') {
            $prefs['categories'][$category] = ($prefs['categories'][$category] ?? 0) + 1;
        }
        if ($price_range !== '') {
            $prefs['price_ranges'][$price_range] = ($prefs['price_ranges'][$price_range] ?? 0) + 1;
        }
        set_transient($prefs_key, $prefs, DAY_IN_SECONDS);
    }

    return new WP_REST_Response([
        'status' => 'ok',
        'reward_enabled' => $reward_enabled,
        'duplicate' => $duplicate,
        'awarded_points' => $awarded_points,
        'awarded_votes' => $awarded_votes,
        'new_total' => $new_total,
        'available_votes' => $available_votes,
    ], 200);
}
