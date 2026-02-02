<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sharity_points_capture_referral_click', 1);
add_action('init', 'sharity_points_link_referral_to_pseudo', 5);
add_action('init', 'sharity_points_award_daily_login', 15);
add_action('wp_login', 'sharity_points_award_user_login', 10, 2);
add_action('template_redirect', 'sharity_points_track_wallet_download', 3);
add_action('template_redirect', 'sharity_points_track_share_page', 4);
add_filter('rest_request_after_callbacks', 'sharity_points_rest_event_bridge', 10, 3);
add_action('rest_api_init', 'sharity_points_register_video_routes');

function sharity_points_get_pseudo_cookie(): string
{
    $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])) : '';
    if ($pseudo === '') {
        return '';
    }
    $pseudo = strtolower($pseudo);
    if (function_exists('impactshop_identity_profile_valid_pseudo')) {
        return impactshop_identity_profile_valid_pseudo($pseudo) ? $pseudo : '';
    }
    return preg_match('/^[a-z0-9]{10,12}$/', $pseudo) ? $pseudo : '';
}

function sharity_points_capture_referral_click(): void
{
    if (empty($_GET['ref'])) {
        return;
    }

    $code = sanitize_key((string) wp_unslash($_GET['ref']));
    if ($code === '') {
        return;
    }

    setcookie('sharity_ref', $code, [
        'expires' => time() + DAY_IN_SECONDS * 30,
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'referral_clicks', [
        'referral_code' => $code,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'referer_url' => wp_get_referer(),
        'utm_source' => isset($_GET['utm_source']) ? sanitize_text_field((string) wp_unslash($_GET['utm_source'])) : null,
        'utm_medium' => isset($_GET['utm_medium']) ? sanitize_text_field((string) wp_unslash($_GET['utm_medium'])) : null,
        'utm_campaign' => isset($_GET['utm_campaign']) ? sanitize_text_field((string) wp_unslash($_GET['utm_campaign'])) : null,
        'cookie_set' => 1,
        'created_at' => current_time('mysql'),
    ]);

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}user_referrals
         SET click_count = click_count + 1,
             first_click_at = COALESCE(first_click_at, %s)
         WHERE referral_code = %s",
        current_time('mysql'),
        $code
    ));
}

function sharity_points_link_referral_to_pseudo(): void
{
    if (empty($_COOKIE['sharity_ref'])) {
        return;
    }

    $pseudo = sharity_points_get_pseudo_cookie();
    if ($pseudo === '') {
        return;
    }

    $code = sanitize_key((string) wp_unslash($_COOKIE['sharity_ref']));
    if ($code === '') {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'user_referrals';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, referred_pseudo_id, status FROM {$table} WHERE referral_code = %s",
        $code
    ), ARRAY_A);

    if (!$row) {
        return;
    }

    if (!empty($row['referred_pseudo_id'])) {
        return;
    }

    $wpdb->update(
        $table,
        [
            'referred_pseudo_id' => $pseudo,
            'status' => 'active',
            'registered_at' => current_time('mysql'),
        ],
        ['id' => (int) $row['id']]
    );
}

function sharity_points_award_daily_login(): void
{
    $pseudo = sharity_points_get_pseudo_cookie();
    if ($pseudo === '') {
        return;
    }

    $day = gmdate('Y-m-d');
    $cache_key = 'sharity_points_login_' . $pseudo . '_' . $day;
    if (get_transient($cache_key)) {
        return;
    }

    $manager = new Sharity_Points_Manager();
    $manager->award_points_for_pseudo(
        $pseudo,
        2,
        'login_daily',
        $day,
        ['source_type' => 'session'],
        'login_daily:' . $pseudo . ':' . $day
    );

    set_transient($cache_key, 1, HOUR_IN_SECONDS * 12);
}

function sharity_points_award_user_login(string $user_login, WP_User $user): void
{
    if (!$user instanceof WP_User) {
        return;
    }

    $user_id = (int) $user->ID;
    if ($user_id <= 0) {
        return;
    }

    $day = gmdate('Y-m-d');
    $manager = new Sharity_Points_Manager();
    $manager->award_points(
        $user_id,
        2,
        'login_daily',
        $day,
        ['source_type' => 'wp_login'],
        'login_daily:' . $user_id . ':' . $day
    );
}

function sharity_points_track_wallet_download(): void
{
    $wallet_flag = (int) get_query_var('impact_wallet_add');
    if (!$wallet_flag) {
        return;
    }

    $pseudo = sharity_points_get_pseudo_cookie();
    if ($pseudo === '') {
        return;
    }

    $slug = (string) get_query_var('ngo');
    if ($slug === '' && isset($_GET['ngo'])) {
        $slug = (string) wp_unslash($_GET['ngo']);
    }
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return;
    }

    $manager = new Sharity_Points_Manager();
    $manager->award_points_for_pseudo(
        $pseudo,
        25,
        'wallet_download',
        $slug,
        ['source_type' => 'wallet', 'ngo_slug' => $slug],
        'wallet_download:' . $pseudo . ':' . $slug
    );
}

function sharity_points_track_share_page(): void
{
    $pseudo = sharity_points_get_pseudo_cookie();
    if ($pseudo === '') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (!preg_match('#/ngo/([^/]+)/share/?#i', $uri, $matches)) {
        return;
    }

    $slug = sanitize_title((string) ($matches[1] ?? ''));
    if ($slug === '') {
        return;
    }

    $today = gmdate('Y-m-d');
    global $wpdb;
    $day_start = $today . ' 00:00:00';
    $day_end = $today . ' 23:59:59';
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions
         WHERE pseudo_id = %s AND type = 'share' AND created_at BETWEEN %s AND %s",
        $pseudo,
        $day_start,
        $day_end
    ));
    $limit = (int) apply_filters('sharity_share_daily_limit', 3);
    if ($count >= $limit) {
        return;
    }

    $manager = new Sharity_Points_Manager();
    $manager->award_points_for_pseudo(
        $pseudo,
        10,
        'share',
        $slug,
        ['source_type' => 'share_page', 'ngo_slug' => $slug],
        'share:' . $pseudo . ':' . $slug . ':' . $today
    );
}

function sharity_points_rest_event_bridge($response, $handler, WP_REST_Request $request)
{
    if (is_wp_error($response)) {
        return $response;
    }

    $route = $request->get_route();
    if (strpos($route, '/impactshop/') !== 0 && strpos($route, '/impact/') !== 0) {
        return $response;
    }
    $response_obj = rest_ensure_response($response);
    $status = (is_object($response_obj) && method_exists($response_obj, 'get_status')) ? (int) $response_obj->get_status() : 0;
    if ($status >= 400) {
        return $response;
    }

    if (strpos($route, '/impactshop/v1/profile/update') !== false) {
        $payload = (array) $request->get_json_params();
        $pseudo = isset($payload['pseudo_id']) ? (string) $payload['pseudo_id'] : '';
        $pseudo = $pseudo !== '' ? strtolower(sanitize_text_field($pseudo)) : sharity_points_get_pseudo_cookie();
        if ($pseudo !== '') {
            $manager = new Sharity_Points_Manager();
            $manager->award_points_for_pseudo(
                $pseudo,
                10,
                'profile_complete',
                null,
                ['source_type' => 'identity_panel'],
                'profile_complete:' . $pseudo
            );

            $nickname = isset($payload['nickname']) ? trim((string) $payload['nickname']) : '';
            if ($nickname !== '') {
                $manager->award_points_for_pseudo(
                    $pseudo,
                    15,
                    'nickname',
                    null,
                    ['source_type' => 'nickname'],
                    'nickname:' . $pseudo
                );
            }
        }
    }

    if (strpos($route, '/impact/v1/vote/view') !== false) {
        $pseudo = sharity_points_get_pseudo_cookie();
        if ($pseudo !== '') {
            $day = gmdate('Y-m-d');
            $payload = (array) $request->get_json_params();
            $campaign_id = isset($payload['campaign_id']) ? (string) $payload['campaign_id'] : '';
            $manager = new Sharity_Points_Manager();
            $manager->award_points_for_pseudo(
                $pseudo,
                5,
                'video_sponsor',
                $campaign_id !== '' ? $campaign_id : null,
                ['source_type' => 'impact_vote', 'campaign_id' => $campaign_id],
                'video_sponsor:' . $pseudo . ':' . $day
            );
        }
    }

    return $response;
}

function sharity_points_register_video_routes(): void
{
    register_rest_route('sharity/v1', '/pseudo/video-ad', [
        'methods' => 'POST',
        'callback' => 'sharity_points_video_ad',
        'permission_callback' => function () {
            return sharity_points_get_pseudo_cookie() !== '';
        },
    ]);
}

function sharity_points_video_ad(WP_REST_Request $request): WP_REST_Response
{
    $pseudo = sharity_points_get_pseudo_cookie();
    if ($pseudo === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $today = gmdate('Y-m-d');
    global $wpdb;
    $day_start = $today . ' 00:00:00';
    $day_end = $today . ' 23:59:59';
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions
         WHERE pseudo_id = %s AND type = 'video_ad' AND created_at BETWEEN %s AND %s",
        $pseudo,
        $day_start,
        $day_end
    ));
    $limit = (int) apply_filters('sharity_video_ad_daily_limit', 50);
    if ($count >= $limit) {
        return new WP_REST_Response(['message' => 'daily_limit_reached'], 429);
    }

    $manager = new Sharity_Points_Manager();
    $manager->award_points_for_pseudo(
        $pseudo,
        1,
        'video_ad',
        null,
        ['source_type' => 'ad_view'],
        'video_ad:' . $pseudo . ':' . $today . ':' . ($count + 1)
    );

    return new WP_REST_Response(['success' => true], 200);
}
