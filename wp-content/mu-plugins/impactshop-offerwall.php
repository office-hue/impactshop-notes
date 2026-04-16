<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ImpactShop Offerwall integration.
 *
 * @package ImpactShop
 */

const IMPACTSHOP_OFFERWALL_SCHEMA_VERSION = '1.0.0';
const IMPACTSHOP_OFFERWALL_OPTION_PROVIDERS = 'impactshop_offerwall_providers';
const IMPACTSHOP_OFFERWALL_OPTION_SCHEMA = 'impactshop_offerwall_schema_version';
if (!defined('IMPACTSHOP_OFFERWALL_DAILY_POINTS_CAP')) {
    define('IMPACTSHOP_OFFERWALL_DAILY_POINTS_CAP', 1000);
}
if (!defined('IMPACTSHOP_OFFERWALL_DAILY_VOTES_CAP')) {
    define('IMPACTSHOP_OFFERWALL_DAILY_VOTES_CAP', 100);
}
if (!defined('IMPACTSHOP_OFFERWALL_DAILY_TX_CAP')) {
    define('IMPACTSHOP_OFFERWALL_DAILY_TX_CAP', 50);
}

add_action('muplugins_loaded', 'impactshop_offerwall_bootstrap');

function impactshop_offerwall_bootstrap(): void
{
    impactshop_offerwall_maybe_install();
    add_action('rest_api_init', 'impactshop_offerwall_register_routes');
    add_shortcode('impactshop_offerwall', 'impactshop_offerwall_shortcode');
    add_action('wp_enqueue_scripts', 'impactshop_offerwall_enqueue_assets');
    add_action('admin_menu', 'impactshop_offerwall_admin_menu');
    add_filter('wp_headers', 'impactshop_offerwall_extend_csp', 10, 2);
}

function impactshop_offerwall_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_OFFERWALL_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_OFFERWALL_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        provider VARCHAR(64) NOT NULL,
        offer_id VARCHAR(128) NOT NULL,
        offer_name VARCHAR(255) DEFAULT '',
        offer_type VARCHAR(64) DEFAULT '',
        transaction_id VARCHAR(255) NOT NULL,
        payout_usd DECIMAL(10,4) DEFAULT 0.0000,
        currency VARCHAR(4) DEFAULT 'USD',
        points_awarded INT UNSIGNED DEFAULT 0,
        votes_awarded INT UNSIGNED DEFAULT 0,
        user_ip VARCHAR(64) DEFAULT '',
        user_agent TEXT,
        postback_data LONGTEXT,
        status VARCHAR(32) DEFAULT 'completed',
        request_id CHAR(36) NOT NULL,
        awarded_at DATETIME NULL,
        reversed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_provider_tx (provider, transaction_id),
        KEY idx_pseudo (pseudo_id),
        KEY idx_provider_offer (provider, offer_id),
        KEY idx_created (created_at),
        KEY idx_status_created (status, created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option(IMPACTSHOP_OFFERWALL_OPTION_SCHEMA, IMPACTSHOP_OFFERWALL_SCHEMA_VERSION, false);
}

function impactshop_offerwall_default_providers(): array
{
    return [
        'adgate' => [
            'enabled' => false,
            'name' => 'AdGate',
            'iframe_url' => '',
            'api_key' => '',
            'postback_secret' => '',
            'signature_param' => 'signature',
            'user_param' => 'user_id',
            'iframe_hash_secret' => '',
            'iframe_hash_param' => 'secure_hash',
            'iframe_hash_format' => '{user}-{secret}',
            'mode' => 'iframe',
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
            'survey_token_secret' => '',
            'allow_ips' => [],
        ],
        'ayet' => [
            'enabled' => false,
            'name' => 'ayeT Studios',
            'iframe_url' => '',
            'api_key' => '',
            'postback_secret' => '',
            'signature_param' => '',
            'user_param' => 'externalIdentifier',
            'iframe_hash_secret' => '',
            'iframe_hash_param' => 'secure_hash',
            'iframe_hash_format' => '{user}-{secret}',
            'mode' => 'offers',
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
            'survey_token_secret' => '',
            'allow_ips' => [],
        ],
        'cpx' => [
            'enabled' => false,
            'name' => 'CPX Research',
            'iframe_url' => '',
            'api_key' => '',
            'postback_secret' => '',
            'signature_param' => 'hash',
            // CPX embed expects ext_user_id; postback commonly returns subid_1.
            'user_param' => 'ext_user_id',
            'iframe_hash_secret' => '',
            'iframe_hash_param' => 'secure_hash',
            'iframe_hash_format' => '{user}-{secret}',
            'mode' => 'iframe',
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
            'survey_token_secret' => '',
            'allow_ips' => [],
        ],
    ];
}

function impactshop_offerwall_get_providers(): array
{
    $providers = get_option(IMPACTSHOP_OFFERWALL_OPTION_PROVIDERS, []);
    if (!is_array($providers) || !$providers) {
        $providers = impactshop_offerwall_default_providers();
    }

    return $providers;
}

function impactshop_offerwall_save_providers(array $providers): void
{
    update_option(IMPACTSHOP_OFFERWALL_OPTION_PROVIDERS, $providers, false);
}

function impactshop_offerwall_register_routes(): void
{
    register_rest_route('impact/v1', '/offerwall/callback/(?P<provider>[a-z0-9_-]+)', [
        'methods' => ['GET', 'POST'],
        'callback' => 'impactshop_offerwall_handle_postback',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/offerwall/iframe/(?P<provider>[a-z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_iframe_url',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/offerwall/config', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_config',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/offerwall/offers/(?P<provider>[a-z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_offers',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/offerwall/history', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_history',
        'permission_callback' => 'impactshop_offerwall_require_pseudo_id',
    ]);

    register_rest_route('impact/v1', '/offerwall/reward-status', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_reward_status',
        'permission_callback' => 'impactshop_offerwall_require_pseudo_id',
    ]);

    register_rest_route('impact/v1', '/offerwall/stats', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_stats',
        'permission_callback' => 'impactshop_offerwall_require_pseudo_id',
    ]);

    register_rest_route('impact/v1', '/offerwall/health', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_health',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_offerwall_get_pseudo_id(): string
{
    if (function_exists('impactshop_identity_profile_cookie')) {
        return (string) impactshop_identity_profile_cookie();
    }
    if (!empty($_COOKIE['impactshop_pseudo_id'])) {
        return sanitize_text_field((string) $_COOKIE['impactshop_pseudo_id']);
    }
    return '';
}

function impactshop_offerwall_require_pseudo_id(): bool
{
    return impactshop_offerwall_get_pseudo_id() !== '';
}

function impactshop_offerwall_rate_limit(string $key, int $limit, int $window): bool
{
    if (function_exists('impactshop_ads_watch_rate_limit_check')) {
        $result = impactshop_ads_watch_rate_limit_check($key, $limit, $window, true);
        return !empty($result['allowed']);
    }

    $bucket = get_transient($key);
    $bucket = is_array($bucket) ? $bucket : ['count' => 0, 'reset' => time() + $window];
    if ($bucket['reset'] < time()) {
        $bucket = ['count' => 0, 'reset' => time() + $window];
    }
    if ($bucket['count'] >= $limit) {
        return false;
    }
    $bucket['count']++;
    set_transient($key, $bucket, $window);
    return true;
}

function impactshop_offerwall_log_fraud(string $reason, array $context = []): void
{
    do_action('impactshop_offerwall_fraud', $reason, $context);
    $payload = wp_json_encode($context);
    error_log('[offerwall] fraud:' . $reason . ' ' . $payload);
}

function impactshop_offerwall_debug_log(string $event, array $context = []): void
{
    $payload = wp_json_encode($context);
    error_log('[offerwall] ' . $event . ' ' . $payload);
}

function impactshop_offerwall_daily_cap_check(string $pseudo_id, int $points, int $votes): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $today = current_time('Y-m-d');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS tx_count,
                COALESCE(SUM(points_awarded), 0) AS points_sum,
                COALESCE(SUM(votes_awarded), 0) AS votes_sum
         FROM {$table}
         WHERE pseudo_id = %s
           AND DATE(created_at) = %s
           AND status NOT IN ('reversed', 'capped')",
        $pseudo_id,
        $today
    ), ARRAY_A);

    $tx_today = (int) ($row['tx_count'] ?? 0);
    $points_today = (int) ($row['points_sum'] ?? 0);
    $votes_today = (int) ($row['votes_sum'] ?? 0);

    $result = [
        'capped' => false,
        'reason' => '',
        'points_today' => $points_today,
        'votes_today' => $votes_today,
        'tx_today' => $tx_today,
    ];

    if (IMPACTSHOP_OFFERWALL_DAILY_TX_CAP > 0 && $tx_today >= (int) IMPACTSHOP_OFFERWALL_DAILY_TX_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'tx_cap';
    } elseif (IMPACTSHOP_OFFERWALL_DAILY_POINTS_CAP > 0 && ($points_today + $points) > (int) IMPACTSHOP_OFFERWALL_DAILY_POINTS_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'points_cap';
    } elseif (IMPACTSHOP_OFFERWALL_DAILY_VOTES_CAP > 0 && ($votes_today + $votes) > (int) IMPACTSHOP_OFFERWALL_DAILY_VOTES_CAP) {
        $result['capped'] = true;
        $result['reason'] = 'votes_cap';
    }

    return $result;
}

function impactshop_offerwall_signature_valid(array $params, array $provider): bool
{
    $secret = (string) ($provider['postback_secret'] ?? '');
    $sig_param = (string) ($provider['signature_param'] ?? 'signature');
    $signature_raw = isset($params[$sig_param]) ? (string) $params[$sig_param] : '';
    $signature = strtolower(trim($signature_raw));
    if ($secret === '' || $signature === '') {
        return true;
    }
    $transaction_id = (string) ($params['transaction_id'] ?? $params['tx_id'] ?? $params['transaction'] ?? $params['trans_id'] ?? '');
    if ($transaction_id === '') {
        return false;
    }

    if (($provider['signature_mode'] ?? '') === 'canonical_v1') {
        $user_id = (string) ($params['user_id'] ?? $params['pseudo_id'] ?? $params['ext_user_id'] ?? '');
        $payout = (string) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? 0);
        $timestamp = (string) ($params['timestamp'] ?? '');
        $canonical = $transaction_id . '|' . $user_id . '|' . $payout . '|' . $timestamp;
        $expected = hash_hmac('sha256', $canonical, $secret);
        return hash_equals(strtolower($expected), $signature);
    }

    $candidates = [
        hash_hmac('sha256', $transaction_id, $secret),
        md5($transaction_id . $secret),
        md5($secret . $transaction_id),
        md5($transaction_id . ':' . $secret),
        md5($secret . ':' . $transaction_id),
    ];
    foreach ($candidates as $expected) {
        if (hash_equals(strtolower($expected), $signature)) {
            return true;
        }
    }

    // CPX often signs a different canonical payload than the generic providers.
    $provider_name = strtolower((string) ($provider['name'] ?? ''));
    $user_param = strtolower((string) ($provider['user_param'] ?? ''));
    $is_cpx = (strpos($provider_name, 'cpx') !== false)
        || in_array($user_param, ['subid_1', 'subid1', 'ext_user_id'], true)
        || strtolower($sig_param) === 'hash';
    if (!$is_cpx) {
        return false;
    }

    $user_id = (string) ($params['pseudo_id'] ?? $params['subid_1'] ?? $params['subid1'] ?? $params['ext_user_id'] ?? $params['user_id'] ?? '');
    $payout = (string) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? $params['reward'] ?? '');
    $timestamp = (string) ($params['timestamp'] ?? '');
    $offer_id = (string) ($params['offer_id'] ?? $params['offerid'] ?? '');

    $messages = array_filter(array_unique([
        $transaction_id,
        $user_id,
        $transaction_id . '|' . $user_id,
        $user_id . '|' . $transaction_id,
        $transaction_id . '|' . $payout,
        $transaction_id . '|' . $user_id . '|' . $payout,
        $transaction_id . '|' . $user_id . '|' . $payout . '|' . $timestamp,
        $transaction_id . '|' . $payout . '|' . $timestamp,
        $transaction_id . '|' . $offer_id,
        $transaction_id . '|' . $user_id . '|' . $offer_id,
    ]), static fn ($v) => is_string($v) && $v !== '');

    foreach ($messages as $message) {
        $flex = [
            hash_hmac('sha256', $message, $secret),
            hash_hmac('sha1', $message, $secret),
            md5($message . $secret),
            md5($secret . $message),
            md5($message . ':' . $secret),
            md5($secret . ':' . $message),
            md5($message . '|' . $secret),
            md5($secret . '|' . $message),
            sha1($message . $secret),
            sha1($secret . $message),
            hash('sha256', $message . $secret),
            hash('sha256', $secret . $message),
        ];
        foreach ($flex as $expected) {
            if (hash_equals(strtolower($expected), $signature)) {
                return true;
            }
        }
    }

    return false;
}

function impactshop_offerwall_build_iframe_url(array $provider, string $pseudo_id): string
{
    $base = trim((string) ($provider['iframe_url'] ?? ''));
    if ($base === '' || $pseudo_id === '') {
        return '';
    }

    $user_param = (string) ($provider['user_param'] ?? 'user_id');
    $url = add_query_arg($user_param, $pseudo_id, $base);

    // Provider-side compatibility: CPX expects ext_user_id on the embed.
    $provider_name = strtolower((string) ($provider['name'] ?? ''));
    $is_cpx = (strpos($provider_name, 'cpx') !== false) || (stripos($base, 'cpx') !== false);
    if ($is_cpx) {
        if ($user_param !== 'ext_user_id') {
            $url = add_query_arg('ext_user_id', $pseudo_id, $url);
        }
        // Many CPX postbacks use subid_1; harmless to include on the embed too.
        if ($user_param !== 'subid_1') {
            $url = add_query_arg('subid_1', $pseudo_id, $url);
        }
    }

    $hash_secret = (string) ($provider['iframe_hash_secret'] ?? '');
    if ($hash_secret !== '') {
        $hash_param = (string) ($provider['iframe_hash_param'] ?? 'secure_hash');
        $hash_format = (string) ($provider['iframe_hash_format'] ?? '{user}-{secret}');
        $hash_input = str_replace(['{user}', '{secret}'], [$pseudo_id, $hash_secret], $hash_format);
        $hash = md5($hash_input);
        $url = add_query_arg($hash_param, $hash, $url);
    }

    return apply_filters('impactshop_offerwall_iframe_url', $url, $provider, $pseudo_id);
}

function impactshop_offerwall_get_iframe_url(WP_REST_Request $request): WP_REST_Response
{
    $provider_key = (string) $request['provider'];
    $providers = impactshop_offerwall_get_providers();
    if (empty($providers[$provider_key]) || empty($providers[$provider_key]['enabled'])) {
        return new WP_REST_Response(['status' => 'disabled'], 200);
    }

    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo'], 200);
    }

    $url = impactshop_offerwall_build_iframe_url($providers[$provider_key], $pseudo_id);
    if ($url === '') {
        return new WP_REST_Response(['status' => 'missing_url'], 200);
    }

    return new WP_REST_Response(['status' => 'ok', 'url' => $url], 200);
}

function impactshop_offerwall_handle_postback(WP_REST_Request $request): WP_REST_Response
{
    $provider_key = (string) $request['provider'];
    $providers = impactshop_offerwall_get_providers();
    if (empty($providers[$provider_key]) || empty($providers[$provider_key]['enabled'])) {
        return new WP_REST_Response(['status' => 'ignored'], 200);
    }

    $provider = $providers[$provider_key];
    $params = array_merge(
        $request->get_params(),
        $request->get_query_params(),
        $request->get_body_params(),
        $request->get_json_params() ?: []
    );
    $transaction_id = sanitize_text_field((string) ($params['transaction_id'] ?? $params['tx_id'] ?? $params['transaction'] ?? $params['trans_id'] ?? ''));
    $pseudo_id = sanitize_text_field((string) ($params['pseudo_id'] ?? $params['sub_id'] ?? $params['user_id'] ?? $params['ext_user_id'] ?? $params['subid_1'] ?? $params['subid1'] ?? ''));
    if ($pseudo_id === '') {
        $pseudo_id = impactshop_offerwall_get_pseudo_id();
    }
    impactshop_offerwall_debug_log('postback_received', [
        'provider' => $provider_key,
        'transaction_id' => $transaction_id,
        'pseudo_id' => $pseudo_id,
    ]);

    if (!impactshop_offerwall_signature_valid($params, $provider)) {
        impactshop_offerwall_debug_log('postback_invalid_signature', [
            'provider' => $provider_key,
            'transaction_id' => $transaction_id,
            'pseudo_id' => $pseudo_id,
            'sig_param' => (string) ($provider['signature_param'] ?? 'signature'),
            'sig_len' => strlen((string) ($params[(string) ($provider['signature_param'] ?? 'signature')] ?? '')),
            'query_keys' => array_keys($params),
        ]);
        return new WP_REST_Response(['status' => 'invalid_signature'], 403);
    }

    $ip = (string) ($request->get_header('x-forwarded-for') ?: $request->get_header('x-real-ip') ?: $request->get_header('client-ip'));
    $ip = $ip ? trim(explode(',', $ip)[0]) : '';
    $allowed_ips = array_filter((array) ($provider['allow_ips'] ?? []));
    if (!empty($allowed_ips) && $ip !== '' && !in_array($ip, $allowed_ips, true)) {
        impactshop_offerwall_log_fraud('ip_blocked', ['provider' => $provider_key, 'ip' => $ip]);
        return new WP_REST_Response(['status' => 'ip_blocked'], 403);
    }

    if ($transaction_id === '') {
        impactshop_offerwall_debug_log('postback_missing_transaction', [
            'provider' => $provider_key,
            'pseudo_id' => $pseudo_id,
        ]);
        return new WP_REST_Response(['status' => 'missing_transaction'], 200);
    }

    $raw_status = sanitize_text_field((string) ($params['status'] ?? $params['event'] ?? ''));
    $is_reversal = in_array($raw_status, ['2', 'reversed', 'reversal', 'canceled', 'cancelled', 'rejected'], true);

    if ($is_reversal) {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_offerwall_completions';
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, reversed_at = %s WHERE provider = %s AND transaction_id = %s",
            'reversed',
            current_time('mysql'),
            $provider_key,
            $transaction_id
        ));

        do_action('impactshop_offerwall_reversal', $pseudo_id, [
            'provider' => $provider_key,
            'transaction_id' => $transaction_id,
            'raw_status' => $raw_status,
            'updated_rows' => (int) $updated,
        ]);

        return new WP_REST_Response(['status' => 'reversed'], 200);
    }

    $rate_key = 'offerwall_postback_' . md5($pseudo_id . '|' . $provider_key);
    if (!impactshop_offerwall_rate_limit($rate_key, 50, HOUR_IN_SECONDS)) {
        impactshop_offerwall_log_fraud('rate_limited_user', ['provider' => $provider_key, 'pseudo_id' => $pseudo_id]);
        return new WP_REST_Response(['status' => 'rate_limited'], 429);
    }
    if ($ip !== '' && !impactshop_offerwall_rate_limit('offerwall_postback_ip_' . md5($ip), 200, HOUR_IN_SECONDS)) {
        impactshop_offerwall_log_fraud('rate_limited_ip', ['provider' => $provider_key, 'ip' => $ip]);
        return new WP_REST_Response(['status' => 'rate_limited'], 429);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';

    $offer_id = sanitize_text_field((string) ($params['offer_id'] ?? $params['offerid'] ?? ''));
    $offer_name = sanitize_text_field((string) ($params['offer_name'] ?? $params['offer'] ?? ''));
    $offer_type = sanitize_text_field((string) ($params['offer_type'] ?? $params['type'] ?? ''));
    $currency = strtoupper(sanitize_text_field((string) ($params['currency'] ?? 'USD')));
    $payout = (float) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? 0);

    $points_multiplier = (float) ($provider['points_multiplier'] ?? 1.0);
    $votes_multiplier = (float) ($provider['votes_multiplier'] ?? 1.0);

    $points_awarded = $payout > 0 ? max(1, (int) ceil($payout * 100 * $points_multiplier)) : 0;
    $votes_awarded = $payout > 0 ? max(1, (int) ceil($payout * 10 * $votes_multiplier)) : 0;
    if ($provider_key === 'internal_survey') {
        $points_awarded = 10;
        $votes_awarded = 10;
    }

    $request_id = wp_generate_uuid4();

    $cap_status = impactshop_offerwall_daily_cap_check($pseudo_id, $points_awarded, $votes_awarded);
    if ($cap_status['capped']) {
        impactshop_offerwall_debug_log('daily_cap_reached', [
            'provider' => $provider_key,
            'pseudo_id' => $pseudo_id,
            'reason' => $cap_status['reason'],
            'points_today' => $cap_status['points_today'],
            'votes_today' => $cap_status['votes_today'],
            'tx_today' => $cap_status['tx_today'],
        ]);
        $points_awarded = 0;
        $votes_awarded = 0;
    }

    $completion_status = $cap_status['capped'] ? 'capped' : 'approved';

    $inserted = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table}
            (pseudo_id, provider, offer_id, offer_name, offer_type, transaction_id, payout_usd, currency, points_awarded, votes_awarded, user_ip, user_agent, postback_data, status, request_id, awarded_at, created_at)
         VALUES (%s, %s, %s, %s, %s, %s, %f, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s)",
        $pseudo_id,
        $provider_key,
        $offer_id,
        $offer_name,
        $offer_type,
        $transaction_id,
        $payout,
        $currency,
        $points_awarded,
        $votes_awarded,
        $ip,
        substr((string) $request->get_header('user-agent'), 0, 500),
        wp_json_encode($params),
        $completion_status,
        $request_id,
        current_time('mysql'),
        current_time('mysql')
    ));

    if ($inserted === 0) {
        impactshop_offerwall_debug_log('postback_duplicate', [
            'provider' => $provider_key,
            'transaction_id' => $transaction_id,
            'pseudo_id' => $pseudo_id,
        ]);
        return new WP_REST_Response(['status' => 'duplicate'], 200);
    }

    if ($points_awarded > 0 && class_exists('Sharity_Points_Manager')) {
        $points_manager = new Sharity_Points_Manager();
        $points_manager->award_points_for_pseudo(
            $pseudo_id,
            $points_awarded,
            'offerwall',
            $transaction_id,
            [
                'source_type' => 'offerwall',
                'provider' => $provider_key,
                'offer_id' => $offer_id,
                'offer_name' => $offer_name,
                'payout' => $payout,
            ],
            'offerwall:' . $provider_key . ':' . $transaction_id
        );
    }

    if ($votes_awarded > 0 && function_exists('impactshop_ads_watch_add_votes')) {
        impactshop_ads_watch_add_votes($pseudo_id, $votes_awarded);
    }

    do_action('impactshop_offerwall_rewards_awarded', $pseudo_id, [
        'provider' => $provider_key,
        'offer_id' => $offer_id,
        'transaction_id' => $transaction_id,
        'points' => $points_awarded,
        'votes' => $votes_awarded,
    ]);
    do_action('impactshop_offerwall_conversion', $pseudo_id, [
        'provider' => $provider_key,
        'offer_id' => $offer_id,
        'transaction_id' => $transaction_id,
        'points' => $points_awarded,
        'votes' => $votes_awarded,
    ]);

    impactshop_offerwall_debug_log('postback_awarded', [
        'provider' => $provider_key,
        'transaction_id' => $transaction_id,
        'pseudo_id' => $pseudo_id,
        'points' => $points_awarded,
        'votes' => $votes_awarded,
    ]);

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_offerwall_get_config(): WP_REST_Response
{
    $providers = impactshop_offerwall_get_providers();
    $enabled = [];
    foreach ($providers as $key => $provider) {
        if (!empty($provider['enabled'])) {
            $enabled[] = [
                'key' => $key,
                'name' => (string) ($provider['name'] ?? $key),
                'user_param' => (string) ($provider['user_param'] ?? 'user_id'),
                'mode' => (string) ($provider['mode'] ?? ($key === 'ayet' ? 'offers' : 'iframe')),
                'points_multiplier' => (float) ($provider['points_multiplier'] ?? 1.0),
                'votes_multiplier' => (float) ($provider['votes_multiplier'] ?? 1.0),
            ];
        }
    }

    return new WP_REST_Response([
        'providers' => $enabled,
    ], 200);
}

function impactshop_offerwall_get_adslot_override(WP_REST_Request $request): string
{
    $mode = strtolower((string) $request->get_param('mode'));
    $adslot = strtolower((string) $request->get_param('adslot'));
    if ($mode === 'survey' || $adslot === 'survey') {
        return defined('AYET_OFFERWALL_SURVEYWALL_ADSLOT') ? (string) AYET_OFFERWALL_SURVEYWALL_ADSLOT : '';
    }

    return '';
}

function impactshop_offerwall_get_offers(WP_REST_Request $request): WP_REST_Response
{
    $provider_key = (string) $request['provider'];
    $providers = impactshop_offerwall_get_providers();
    if (empty($providers[$provider_key]) || empty($providers[$provider_key]['enabled'])) {
        return new WP_REST_Response(['status' => 'disabled', 'offers' => []], 200);
    }

    if ($provider_key !== 'ayet' || !function_exists('impactshop_ayet_offerwall_fetch_offers')) {
        return new WP_REST_Response(['status' => 'unsupported', 'offers' => []], 200);
    }

    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo', 'offers' => []], 200);
    }

    $adslot_override = impactshop_offerwall_get_adslot_override($request);
    $refresh = (string) $request->get_param('refresh');
    if ($refresh === '1') {
        $rate_key = 'offerwall_refresh_' . md5($pseudo_id);
        if (!impactshop_offerwall_rate_limit($rate_key, 1, 10)) {
            return new WP_REST_Response(['status' => 'rate_limited', 'offers' => []], 200);
        }
        if (function_exists('impactshop_ayet_offerwall_flush_cache')) {
            impactshop_ayet_offerwall_flush_cache($pseudo_id, $adslot_override);
        }
    }

    $ip = '';
    if (function_exists('impactshop_ayet_resolve_ip')) {
        $ip = impactshop_ayet_resolve_ip($request);
    }
    if ($ip === '') {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    $user_agent = (string) $request->get_header('user-agent');

    $offers = impactshop_ayet_offerwall_fetch_offers($pseudo_id, $ip, $user_agent, $adslot_override);
    $include_mobile = (string) $request->get_param('include_mobile') === '1';
    if ($include_mobile && function_exists('impactshop_ayet_offerwall_fetch_offers_with_ua')) {
        $merged = [];
        foreach ($offers as $offer) {
            $key = $offer['offer_id'] ?? $offer['id'] ?? null;
            if ($key !== null) {
                $merged[(string) $key] = $offer;
            }
        }
        $android_ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0 Mobile Safari/537.36';
        $android_offers = impactshop_ayet_offerwall_fetch_offers_with_ua($pseudo_id, $ip, $android_ua, 'android', $adslot_override);
        foreach ($android_offers as $offer) {
            $key = $offer['offer_id'] ?? $offer['id'] ?? null;
            if ($key === null) {
                $merged[] = $offer;
            } else {
                $merged[(string) $key] = $offer;
            }
        }
        $ios_ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $ios_offers = impactshop_ayet_offerwall_fetch_offers_with_ua($pseudo_id, $ip, $ios_ua, 'ios', $adslot_override);
        foreach ($ios_offers as $offer) {
            $key = $offer['offer_id'] ?? $offer['id'] ?? null;
            if ($key === null) {
                $merged[] = $offer;
            } else {
                $merged[(string) $key] = $offer;
            }
        }
        $offers = array_values($merged);
    }

    return new WP_REST_Response([
        'status' => 'ok',
        'offers' => $offers,
    ], 200);
}

function impactshop_offerwall_get_history(): WP_REST_Response
{
    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['items' => []], 200);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT transaction_id, offer_name, offer_type, points_awarded, votes_awarded, created_at, provider, status
         FROM {$table}
         WHERE pseudo_id = %s
         ORDER BY created_at DESC
         LIMIT 10",
        $pseudo_id
    ), ARRAY_A);

    return new WP_REST_Response(['items' => $items], 200);
}

/**
 * Proxy for AyeT Reward Status API — returns CPE task progress per user.
 * Cached 5 minutes per pseudo_id.
 */
function impactshop_offerwall_get_reward_status(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo', 'campaigns' => []], 200);
    }

    $adslot_override = impactshop_offerwall_get_adslot_override($request);
    $adslot = $adslot_override !== '' ? $adslot_override : (defined('AYET_OFFERWALL_ADSLOT') ? (string) AYET_OFFERWALL_ADSLOT : '');
    if ($adslot === '' && defined('AYET_OFFERWALL_ADSLOT_FALLBACK')) {
        $adslot = (string) AYET_OFFERWALL_ADSLOT_FALLBACK;
    }
    if ($adslot === '') {
        return new WP_REST_Response(['status' => 'missing_adslot', 'campaigns' => []], 200);
    }

    $cache_key = 'impactshop_ayet_reward_status_' . md5($pseudo_id . '|' . $adslot);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return new WP_REST_Response(['status' => 'ok', 'campaigns' => $cached], 200);
    }

    $url = 'https://www.ayetstudios.com/rest/v1/userSupport/get_reward_status';
    $url = add_query_arg([
        'placementId'        => $adslot,
        'externalIdentifier' => $pseudo_id,
    ], $url);

    $response = wp_remote_get($url, ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]);
    if (is_wp_error($response)) {
        return new WP_REST_Response(['status' => 'api_error', 'campaigns' => []], 200);
    }

    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    $campaigns = is_array($body) ? $body : [];

    set_transient($cache_key, $campaigns, 300); // 5 min cache

    return new WP_REST_Response(['status' => 'ok', 'campaigns' => $campaigns], 200);
}

/**
 * Return today's reward stats for the current pseudo_id.
 */
function impactshop_offerwall_get_stats(): WP_REST_Response
{
    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response([
            'points_today' => 0,
            'votes_today' => 0,
            'tx_today' => 0,
            'total_points' => 0,
            'total_votes' => 0,
            'total_tx' => 0,
            'ayet_points_total' => 0,
            'ayet_votes_total' => 0,
            'ayet_tx_total' => 0,
        ], 200);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $today = current_time('Y-m-d');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS tx_count,
                COALESCE(SUM(points_awarded), 0) AS points_sum,
                COALESCE(SUM(votes_awarded), 0) AS votes_sum
         FROM {$table}
         WHERE pseudo_id = %s
           AND DATE(created_at) = %s
           AND status NOT IN ('reversed', 'capped')",
        $pseudo_id,
        $today
    ), ARRAY_A);

    $totals = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS tx_count,
                COALESCE(SUM(points_awarded), 0) AS points_sum,
                COALESCE(SUM(votes_awarded), 0) AS votes_sum
         FROM {$table}
         WHERE pseudo_id = %s
           AND status NOT IN ('reversed', 'capped')",
        $pseudo_id
    ), ARRAY_A);

    $ayet_totals = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS tx_count,
                COALESCE(SUM(points_awarded), 0) AS points_sum,
                COALESCE(SUM(votes_awarded), 0) AS votes_sum
         FROM {$table}
         WHERE pseudo_id = %s
           AND provider = %s
           AND status NOT IN ('reversed', 'capped')",
        $pseudo_id,
        'ayet'
    ), ARRAY_A);

    return new WP_REST_Response([
        'points_today' => (int) ($row['points_sum'] ?? 0),
        'votes_today'  => (int) ($row['votes_sum'] ?? 0),
        'tx_today'     => (int) ($row['tx_count'] ?? 0),
        'total_points' => (int) ($totals['points_sum'] ?? 0),
        'total_votes'  => (int) ($totals['votes_sum'] ?? 0),
        'total_tx'     => (int) ($totals['tx_count'] ?? 0),
        'ayet_points_total' => (int) ($ayet_totals['points_sum'] ?? 0),
        'ayet_votes_total'  => (int) ($ayet_totals['votes_sum'] ?? 0),
        'ayet_tx_total'     => (int) ($ayet_totals['tx_count'] ?? 0),
    ], 200);
}

function impactshop_offerwall_health(): WP_REST_Response
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $since = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));
    $last = $wpdb->get_var("SELECT MAX(created_at) FROM {$table}");
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
        $since
    ));
    $by_provider = $wpdb->get_results($wpdb->prepare(
        "SELECT provider, COUNT(*) AS total, MAX(created_at) AS last_seen
         FROM {$table}
         WHERE created_at >= %s
         GROUP BY provider",
        $since
    ), ARRAY_A);

    return new WP_REST_Response([
        'status' => 'ok',
        'last_postback' => $last,
        'count_24h' => $count,
        'providers' => $by_provider,
    ], 200);
}

function impactshop_offerwall_enqueue_assets(): void
{
    if (!is_singular()) {
        return;
    }

    if (!has_shortcode(get_post()->post_content ?? '', 'impactshop_offerwall')) {
        return;
    }

    $css_version = '1.0.0';
    $js_version = '1.0.0';
    $js_path = __DIR__ . '/impactshop-offerwall.js';
    if (is_file($js_path)) {
        $js_version = (string) filemtime($js_path);
        $css_version = $js_version;
    }

    wp_register_style('impactshop-offerwall', false, [], $css_version);
    wp_enqueue_style('impactshop-offerwall');
    wp_add_inline_style('impactshop-offerwall', impactshop_offerwall_inline_css());

    wp_enqueue_script(
        'impactshop-offerwall',
        plugins_url('impactshop-offerwall.js', __FILE__),
        ['jquery'],
        $js_version,
        true
    );

    wp_localize_script('impactshop-offerwall', 'impactshopOfferwall', [
        'restUrl' => esc_url_raw(rest_url('impact/v1/offerwall')),
        'ayetSurveyUrl' => esc_url_raw(rest_url('impact/v1/ayet-surveys')),
    ]);
}

function impactshop_offerwall_inline_css(): string
{
    return
        // === Layout ===
        '.impactshop-offerwall{background:#0f172a;color:#fff;border-radius:20px;padding:20px;margin:20px 0;font-family:inherit}' .
        '.impactshop-offerwall h3{margin:0 0 12px;font-size:20px}' .
        '.impactshop-offerwall .offerwall-trust{margin:6px 0 12px;font-size:13px;color:#cbd5f5}' .
        '.impactshop-offerwall .offerwall-faq-trigger{display:inline-flex;align-items:center;gap:6px;background:#111827;border:1px solid rgba(148,163,184,.3);color:#f8fafc;padding:6px 10px;border-radius:999px;font-size:12px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-faq{margin:10px 0 16px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.08);font-size:12px;line-height:1.5}' .
        '.impactshop-offerwall .offerwall-note{margin:10px 0 16px;padding:10px 12px;border-radius:12px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.25);font-size:12px;line-height:1.5;color:#e2e8f0}' .
        '.impactshop-offerwall .offerwall-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;background:#111827;border:1px solid rgba(148,163,184,.3);color:#f8fafc;padding:6px 10px;border-radius:999px;font-size:12px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-provider-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 14px}' .
        '.impactshop-offerwall .offerwall-provider-btn{border:1px solid rgba(148,163,184,.35);background:#111827;color:#e2e8f0;border-radius:999px;padding:6px 12px;font-size:12px;cursor:pointer;transition:all .15s ease}' .
        '.impactshop-offerwall .offerwall-provider-btn.is-active{background:#2563eb;border-color:#2563eb;color:#fff}' .
        '.impactshop-offerwall .offerwall-provider-btn.is-disabled{opacity:.5;cursor:not-allowed}' .
        '.impactshop-offerwall .offerwall-provider-btn .offerwall-provider-badge{margin-left:6px;font-size:10px;background:rgba(148,163,184,.2);padding:2px 6px;border-radius:999px}' .

        // === Stats panel ===
        '.impactshop-offerwall .offerwall-stats{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}' .
        '.impactshop-offerwall .offerwall-stat{background:#111827;border-radius:12px;padding:10px 14px;flex:1;min-width:100px;text-align:center}' .
        '.impactshop-offerwall .offerwall-stat-value{display:block;font-size:22px;font-weight:700;color:#22c55e}' .
        '.impactshop-offerwall .offerwall-stat-label{display:block;font-size:11px;color:#94a3b8;margin-top:2px}' .

        // === Tabs ===
        '.impactshop-offerwall .offerwall-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 16px}' .
        '.impactshop-offerwall .offerwall-tab{background:#1e293b;border:1px solid rgba(148,163,184,.2);color:#cbd5e1;padding:8px 14px;border-radius:999px;font-size:13px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-tab.is-active{background:#2563eb;border-color:#2563eb;color:#fff}' .
        '.impactshop-offerwall .offerwall-panel{display:none}' .
        '.impactshop-offerwall .offerwall-panel.is-active{display:block}' .
        '.impactshop-offerwall .offerwall-empty{background:#111827;border-radius:14px;padding:14px;color:#cbd5e1;font-size:13px}' .

        // === Filter bar ===
        '.impactshop-offerwall .offerwall-filters{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;align-items:center}' .
        '.impactshop-offerwall .offerwall-filter-btn{background:#1e293b;border:1px solid rgba(148,163,184,.2);color:#cbd5e1;padding:5px 10px;border-radius:999px;font-size:12px;cursor:pointer;transition:all .15s}' .
        '.impactshop-offerwall .offerwall-filter-btn.active{background:#2563eb;border-color:#2563eb;color:#fff}' .
        '.impactshop-offerwall .offerwall-sort{background:#1e293b;border:1px solid rgba(148,163,184,.2);color:#cbd5e1;padding:5px 8px;border-radius:8px;font-size:12px;margin-left:auto}' .
        '.impactshop-offerwall .offerwall-refresh{background:#111827;border:1px solid rgba(148,163,184,.25);color:#cbd5e1;padding:5px 10px;border-radius:999px;font-size:12px;cursor:pointer;margin-left:8px}' .
        '.impactshop-offerwall .offerwall-refresh.is-loading{opacity:.6;pointer-events:none}' .
        '.impactshop-offerwall .offerwall-load-more{margin:14px auto 0;display:inline-flex;align-items:center;justify-content:center;background:#1e293b;border:1px solid rgba(148,163,184,.25);color:#cbd5e1;padding:7px 12px;border-radius:999px;font-size:12px;cursor:pointer}' .

        // === Card grid ===
        '.impactshop-offerwall .offerwall-cards{display:grid;gap:14px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}' .

        // === Offer card ===
        '.impactshop-offerwall .offerwall-card{background:#111827;border-radius:16px;padding:16px;position:relative;transition:transform .15s,box-shadow .15s}' .
        '.impactshop-offerwall .offerwall-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3)}' .
        '.impactshop-offerwall .offerwall-card--active{border:1px solid rgba(59,130,246,.5)}' .

        // Active badge
        '.impactshop-offerwall .offerwall-active-badge{background:rgba(59,130,246,.15);color:#60a5fa;font-size:11px;padding:3px 8px;border-radius:8px 8px 0 0;margin:-16px -16px 10px;padding:6px 16px;text-align:center}' .

        // Card header (icon + title + diff)
        '.impactshop-offerwall .offerwall-card-header{display:flex;gap:10px;align-items:flex-start;margin-bottom:8px}' .
        '.impactshop-offerwall .offerwall-card-icon{width:48px;height:48px;border-radius:12px;object-fit:cover;flex-shrink:0}' .
        '.impactshop-offerwall .offerwall-card-icon--placeholder{display:flex;align-items:center;justify-content:center;background:#1e293b;font-size:22px}' .
        '.impactshop-offerwall .offerwall-card-title{flex:1;min-width:0}' .
        '.impactshop-offerwall .offerwall-card-title strong{display:block;font-size:14px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' .

        // Difficulty badge
        '.impactshop-offerwall .offerwall-diff{display:inline-block;font-size:10px;padding:2px 7px;border-radius:999px;color:#fff;margin-top:3px;font-weight:600}' .

        // Intro text
        '.impactshop-offerwall .offerwall-intro{font-size:12px;color:#cbd5e1;line-height:1.4;margin:0 0 8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}' .

        // Reward box
        '.impactshop-offerwall .offerwall-reward{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:8px 10px;margin-bottom:10px}' .
        '.impactshop-offerwall .offerwall-reward span{display:block;font-size:12px;color:#a7f3d0;line-height:1.5}' .
        '.impactshop-offerwall .offerwall-reward span:first-child{font-weight:700;font-size:13px;color:#4ade80}' .

        // CPE stepper
        '.impactshop-offerwall .offerwall-cpe{margin-bottom:10px}' .
        '.impactshop-offerwall .offerwall-cpe-title{font-size:11px;font-weight:600;color:#94a3b8;margin-bottom:4px}' .
        '.impactshop-offerwall .offerwall-cpe-step{display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid rgba(148,163,184,.1);font-size:12px}' .
        '.impactshop-offerwall .offerwall-cpe-step:last-child{border-bottom:0}' .
        '.impactshop-offerwall .cpe-icon{flex-shrink:0;width:18px;text-align:center}' .
        '.impactshop-offerwall .cpe-name{flex:1;color:#e2e8f0}' .
        '.impactshop-offerwall .cpe-reward{color:#4ade80;font-size:11px;white-space:nowrap}' .
        '.impactshop-offerwall .cpe-time{color:#94a3b8;font-size:10px}' .
        '.impactshop-offerwall .cpe--done .cpe-name{color:#22c55e}' .
        '.impactshop-offerwall .cpe--locked .cpe-name{color:#475569}' .
        '.impactshop-offerwall .cpe--bonus .cpe-name{color:#f59e0b}' .

        // Rules accordion
        '.impactshop-offerwall .offerwall-rules{margin-bottom:10px}' .
        '.impactshop-offerwall .offerwall-rules summary{font-size:12px;color:#94a3b8;cursor:pointer;list-style:none}' .
        '.impactshop-offerwall .offerwall-rules summary::-webkit-details-marker{display:none}' .
        '.impactshop-offerwall .offerwall-rules-body{font-size:11px;color:#cbd5e1;line-height:1.5;padding:6px 0 0;white-space:pre-line}' .

        // Meta (rating, categories)
        '.impactshop-offerwall .offerwall-meta{font-size:11px;color:#64748b;margin-bottom:8px}' .

        // CTA button
        '.impactshop-offerwall .offerwall-card .offerwall-cta{display:block;width:100%;text-align:center;margin-top:10px;background:#2563eb;border:0;color:#fff;padding:8px 12px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}' .
        '.impactshop-offerwall .offerwall-cta:hover{background:#1d4ed8}' .

        // === Skeleton loading ===
        '.impactshop-offerwall .offerwall-card--skeleton{pointer-events:none;min-height:180px}' .
        '.impactshop-offerwall .sk-icon{width:48px;height:48px;border-radius:12px;background:#1e293b;margin-bottom:10px;animation:sk-pulse 1.2s ease-in-out infinite}' .
        '.impactshop-offerwall .sk-line{height:12px;border-radius:6px;background:#1e293b;margin-bottom:8px;animation:sk-pulse 1.2s ease-in-out infinite}' .
        '.impactshop-offerwall .sk-line--w60{width:60%}' .
        '.impactshop-offerwall .sk-line--w80{width:80%}' .
        '.impactshop-offerwall .sk-line--w40{width:40%}' .
        '@keyframes sk-pulse{0%,100%{opacity:.4}50%{opacity:.8}}' .

        // === Toast system ===
        '.offerwall-toast-container{position:fixed;bottom:20px;right:20px;z-index:10000;display:flex;flex-direction:column-reverse;gap:8px;pointer-events:none}' .
        '.offerwall-toast{background:#111827;color:#fff;padding:12px 16px;border-radius:12px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.4);border-left:4px solid #22c55e;animation:toast-in .3s ease;max-width:360px;pointer-events:auto}' .
        '.offerwall-toast--warning{border-left-color:#f59e0b}' .
        '.offerwall-toast--info{border-left-color:#3b82f6}' .
        '.offerwall-toast--out{animation:toast-out .3s ease forwards}' .
        '@keyframes toast-in{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}' .
        '@keyframes toast-out{to{opacity:0;transform:translateX(40px)}}' .

        // === Modal ===
        '.impactshop-offerwall .offerwall-modal{position:fixed;inset:0;background:rgba(15,23,42,.7);display:none;align-items:center;justify-content:center;z-index:9999}' .
        '.impactshop-offerwall .offerwall-modal.active{display:flex}' .
        '.impactshop-offerwall .offerwall-frame{width:min(900px,90vw);height:min(80vh,720px);border:0;border-radius:18px;background:#fff;position:relative;z-index:1}' .
        '.impactshop-offerwall .offerwall-modal-inner{position:relative}' .
        '.impactshop-offerwall .offerwall-close{position:absolute;top:-14px;right:-14px;width:40px;height:40px;border-radius:50%;border:0;background:#111827;color:#fff;font-weight:700;cursor:pointer;box-shadow:0 8px 18px rgba(15,23,42,.4);z-index:2}' .
        '.impactshop-offerwall .offerwall-close:hover{transform:scale(1.04)}' .
        '.impactshop-offerwall .offerwall-platform{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#93c5fd;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.25);padding:2px 8px;border-radius:999px;margin-left:6px}' .
        '.impactshop-offerwall .offerwall-mobile-modal{position:fixed;inset:0;background:rgba(15,23,42,.7);display:flex;align-items:center;justify-content:center;z-index:10000}' .
        '.impactshop-offerwall .offerwall-mobile-modal[hidden]{display:none}' .
        '.impactshop-offerwall .offerwall-mobile-card{background:#0b1220;color:#e2e8f0;padding:22px;border-radius:18px;box-shadow:0 12px 30px rgba(0,0,0,.45);width:min(360px,92vw);text-align:center;position:relative}' .
        '.impactshop-offerwall .offerwall-mobile-title{font-weight:700;margin-bottom:6px}' .
        '.impactshop-offerwall .offerwall-mobile-text{font-size:12px;color:#94a3b8;margin-bottom:12px}' .
        '.impactshop-offerwall .offerwall-mobile-platforms{display:flex;gap:8px;justify-content:center;margin-bottom:10px;flex-wrap:wrap}' .
        '.impactshop-offerwall .offerwall-mobile-platforms button{background:#111827;border:1px solid rgba(148,163,184,.25);color:#cbd5e1;border-radius:999px;padding:6px 10px;font-size:12px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-mobile-platforms button.is-active{background:#2563eb;border-color:#2563eb;color:#fff}' .
        '.impactshop-offerwall .offerwall-mobile-qr{width:220px;height:220px;margin:0 auto 10px;display:block;background:#fff;border-radius:12px;padding:8px}' .
        '.impactshop-offerwall .offerwall-mobile-link{display:inline-flex;align-items:center;justify-content:center;width:100%;margin:6px 0;background:#2563eb;color:#fff;border-radius:10px;padding:8px 12px;font-size:13px;font-weight:600;text-decoration:none}' .
        '.impactshop-offerwall .offerwall-mobile-copy{background:#111827;border:1px solid rgba(148,163,184,.25);color:#cbd5e1;border-radius:10px;padding:8px 12px;font-size:12px;cursor:pointer;width:100%}' .
        '.impactshop-offerwall .offerwall-mobile-close{position:absolute;top:10px;right:10px;width:32px;height:32px;border-radius:50%;border:0;background:#111827;color:#fff;font-weight:700;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-survey-section{background:#0f172a;border:1px solid rgba(148,163,184,.12);border-radius:16px;padding:14px;margin-bottom:16px}' .
        '.impactshop-offerwall .offerwall-section-title{margin:0 0 8px;font-size:15px;color:#e2e8f0}' .
        '.impactshop-offerwall .offerwall-note{color:#94a3b8;font-size:12px;margin-bottom:10px}' .
        '#cpx-survey-container{min-height:300px;border-radius:12px;background:#0b1220;padding:6px}' .

        // === History ===
        '.impactshop-offerwall .offerwall-history{margin-top:16px;background:#111827;border-radius:14px;padding:12px}' .
        '.impactshop-offerwall .offerwall-history li{display:flex;justify-content:space-between;color:#e2e8f0;font-size:13px;padding:6px 0;border-bottom:1px solid rgba(148,163,184,.2)}' .

        // === Responsive ===
        '@media (max-width:640px){' .
            '.impactshop-offerwall .offerwall-cards{grid-template-columns:1fr}' .
            '.impactshop-offerwall .offerwall-close{top:6px;right:6px}' .
            '.impactshop-offerwall .offerwall-stats{flex-direction:column}' .
            '.impactshop-offerwall .offerwall-filters{flex-wrap:wrap}' .
            '.offerwall-toast-container{top:16px;bottom:auto;right:50%;left:auto;transform:translateX(50%)}' .
        '}';
}

function impactshop_offerwall_shortcode(): string
{
    $providers = impactshop_offerwall_get_providers();
    $cpx_provider = $providers['cpx'] ?? [];
    $cpx_active = !empty($cpx_provider['enabled']);
    $ayet_provider = $providers['ayet'] ?? [];
    $ayet_active = !empty($ayet_provider['enabled']);
    $ayet_survey_active = (defined('AYET_OFFERWALL_SURVEYWALL_ADSLOT') && (string) AYET_OFFERWALL_SURVEYWALL_ADSLOT !== '')
        || (defined('AYET_OFFERWALL_ADSLOT_FALLBACK') && (string) AYET_OFFERWALL_ADSLOT_FALLBACK !== '');
    $pseudo_id = impactshop_offerwall_get_pseudo_id();
    $cpx_app_id = (string) ($cpx_provider['api_key'] ?? '');
    $cpx_secret = (string) ($cpx_provider['survey_token_secret'] ?? '');
    if ($cpx_secret === '') {
        $cpx_secret = (string) ($cpx_provider['iframe_hash_secret'] ?? '');
    }
    $cpx_hash = ($pseudo_id !== '' && $cpx_secret !== '') ? md5($pseudo_id . $cpx_secret) : '';

    $html = '<div class="impactshop-offerwall" id="impactshop-offerwall">';
    $html .= '<h3>🎁 Feladatok</h3>';
    $html .= '<p class="offerwall-trust">Néha pár órán belül fut be a jutalom.</p>';
    $html .= '<button type="button" class="offerwall-faq-trigger" data-role="offerwall-faq-trigger">Hol a jutalmam?</button>';
    $html .= '<div class="offerwall-faq" data-role="offerwall-faq" hidden>';
    $html .= '<p>Az offerwall teljesítések feldolgozása szolgáltatófüggő, ezért előfordulhat néhány órás késés.</p>';
    $html .= '</div>';
    $html .= '<div class="offerwall-stats-anchor" data-role="offerwall-stats-anchor"></div>';
    $html .= '<div class="offerwall-tabs" data-role="offerwall-tabs">';
    $html .= '<button type="button" class="offerwall-tab is-active" data-role="offerwall-tab" data-target="offerwall">🎮 Játékok</button>';
    $html .= '<button type="button" class="offerwall-tab" data-role="offerwall-tab" data-target="quiz">📋 Kvíz</button>';
    $html .= '<button type="button" class="offerwall-tab" data-role="offerwall-tab" data-target="survey">📊 Kérdőív</button>';
    $html .= '<button type="button" class="offerwall-tab" data-role="offerwall-tab" data-target="active">✅ Aktívak</button>';
    $html .= '</div>';
    $html .= '<div class="offerwall-panel is-active" data-panel="offerwall">';
    $html .= '<div class="offerwall-provider-tabs" data-role="offerwall-provider-tabs" data-scope="games">';
    $html .= '<button type="button" class="offerwall-provider-btn' . ($ayet_active ? ' is-active' : ' is-disabled') . '" data-role="offerwall-provider" data-provider="ayet"' . ($ayet_active ? '' : ' disabled') . '>AyeT</button>';
    $html .= '<button type="button" class="offerwall-provider-btn is-disabled" data-role="offerwall-provider" data-provider="torox" disabled>Torox<span class="offerwall-provider-badge">hamarosan</span></button>';
    $html .= '</div>';
    $html .= '<div class="offerwall-cards" data-role="offerwall-cards"></div>';
    $html .= '<div class="offerwall-history">';
    $html .= '<strong>Legutóbbi teljesítések</strong>';
    $html .= '<ul data-role="offerwall-history"></ul>';
    $html .= '</div>';
    $html .= '</div>';
    $quiz_html = shortcode_exists('impactshop_article_quiz')
        ? do_shortcode('[impactshop_article_quiz]')
        : '<div class="offerwall-empty">A kvíz modul jelenleg nem elérhető.</div>';
    $quiz_html = '<div class="offerwall-quiz-section" data-provider="sharity">' . $quiz_html . '</div>';
    $survey_sections = '';
    if (shortcode_exists('impactshop_internal_survey')) {
        $survey_sections .= '<div class="offerwall-survey-section" data-provider="sharity">';
        $survey_sections .= '<h3 class="offerwall-section-title">🏠 Saját kérdőíveink</h3>';
        $survey_sections .= do_shortcode('[impactshop_internal_survey]');
        $survey_sections .= '</div>';
    }
    if ($cpx_active) {
        $survey_sections .= '<div class="offerwall-survey-section offerwall-survey-cpx" data-provider="cpx">';
        $survey_sections .= '<h3 class="offerwall-section-title">🌐 Külső kérdőívek – extra pontokért</h3>';
        $survey_sections .= '<div class="offerwall-note">Töltsd ki és gyűjts extra pontokat! A jutalom a kitöltés után automatikusan jóváírásra kerül. (CPX Research: tesztüzem)</div>';
        $survey_sections .= '<div id="cpx-survey-container" data-cpx-app-id="' . esc_attr($cpx_app_id) . '" data-cpx-user="' . esc_attr($pseudo_id) . '" data-cpx-hash="' . esc_attr($cpx_hash) . '" data-cpx-subid1="' . esc_attr($pseudo_id) . '" data-cpx-enabled="' . ($cpx_active ? '1' : '0') . '"></div>';
        $survey_sections .= '</div>';
    }
    $survey_sections .= '<div class="offerwall-survey-section" data-provider="ayet">';
    $survey_sections .= '<h3 class="offerwall-section-title">🧭 AyeT kérdőívek</h3>';
    $survey_sections .= '<div class="offerwall-note">Válassz egy kérdőívet, és teljesítés után jóváírjuk a pontot és szavazatot.</div>';
    $survey_sections .= '<div class="offerwall-cards" data-role="offerwall-ayet-surveys"></div>';
    $survey_sections .= '</div>';
    $survey_html = $survey_sections !== ''
        ? $survey_sections
        : '<div class="offerwall-empty">A kérdőív modul jelenleg nem elérhető.</div>';
    $html .= '<div class="offerwall-panel" data-panel="quiz">';
    $html .= '<div class="offerwall-provider-tabs" data-role="offerwall-provider-tabs" data-scope="quiz">';
    $html .= '<button type="button" class="offerwall-provider-btn is-active" data-role="offerwall-provider" data-provider="sharity">Sharity</button>';
    $html .= '</div>';
    $html .= $quiz_html . '</div>';
    $html .= '<div class="offerwall-panel" data-panel="survey">';
    $html .= '<div class="offerwall-provider-tabs" data-role="offerwall-provider-tabs" data-scope="survey">';
    $html .= '<button type="button" class="offerwall-provider-btn is-active" data-role="offerwall-provider" data-provider="sharity">Sharity</button>';
    $html .= '<button type="button" class="offerwall-provider-btn' . ($cpx_active ? '' : ' is-disabled') . '" data-role="offerwall-provider" data-provider="cpx"' . ($cpx_active ? '' : ' disabled') . '>CPX Research</button>';
    $html .= '<button type="button" class="offerwall-provider-btn' . ($ayet_survey_active ? '' : ' is-disabled') . '" data-role="offerwall-provider" data-provider="ayet"' . ($ayet_survey_active ? '' : ' disabled') . '>AyeT</button>';
    $html .= '</div>';
    $html .= $survey_html . '</div>';
    $html .= '<div class="offerwall-panel" data-panel="active">';
    $html .= '<div class="offerwall-cards" data-role="offerwall-active"></div>';
    $html .= '</div>';
    $html .= '<div class="offerwall-modal" data-role="offerwall-modal">';
    $html .= '<div class="offerwall-modal-inner">';
    $html .= '<button type="button" class="offerwall-close" data-role="offerwall-close" aria-label="Offerwall bezárása">×</button>';
    $html .= '<iframe class="offerwall-frame" data-role="offerwall-frame" title="Offerwall" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation-by-user-activation" referrerpolicy="no-referrer"></iframe>';
    $html .= '</div></div>';
    $html .= '<div class="offerwall-mobile-modal" data-role="offerwall-mobile-modal" hidden>';
    $html .= '<div class="offerwall-mobile-card">';
    $html .= '<button type="button" class="offerwall-mobile-close" data-role="offerwall-mobile-close" aria-label="Bezárás">×</button>';
    $html .= '<div class="offerwall-mobile-title">📱 Csak mobilon végezhető</div>';
    $html .= '<div class="offerwall-mobile-text" data-role="offerwall-mobile-text">Olvasd be a QR kódot a telefonoddal, és folytasd ott.</div>';
    $html .= '<div class="offerwall-mobile-platforms" data-role="offerwall-mobile-platforms" hidden></div>';
    $html .= '<img class="offerwall-mobile-qr" data-role="offerwall-mobile-qr" alt="QR kód" loading="lazy" />';
    $html .= '<a class="offerwall-mobile-link" data-role="offerwall-mobile-link" target="_blank" rel="noopener">Folytasd mobilon</a>';
    $html .= '<button type="button" class="offerwall-mobile-copy" data-role="offerwall-mobile-copy">Link másolása</button>';
    $html .= '</div></div>';
    $html .= '</div>';

    return $html;
}

function impactshop_offerwall_admin_menu(): void
{
    add_submenu_page(
        'options-general.php',
        'Offerwall',
        'Offerwall',
        'manage_options',
        'impactshop-offerwall',
        'impactshop_offerwall_admin_page'
    );
}

function impactshop_offerwall_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('impactshop_offerwall_save')) {
        $providers = impactshop_offerwall_get_providers();
        foreach ($providers as $key => $provider) {
            $providers[$key]['enabled'] = !empty($_POST['provider'][$key]['enabled']);
            $providers[$key]['name'] = sanitize_text_field($_POST['provider'][$key]['name'] ?? $provider['name']);
            $providers[$key]['iframe_url'] = esc_url_raw($_POST['provider'][$key]['iframe_url'] ?? '');
            $providers[$key]['user_param'] = sanitize_text_field($_POST['provider'][$key]['user_param'] ?? ($provider['user_param'] ?? 'user_id'));
            $providers[$key]['iframe_hash_secret'] = sanitize_text_field($_POST['provider'][$key]['iframe_hash_secret'] ?? ($provider['iframe_hash_secret'] ?? ''));
            $providers[$key]['iframe_hash_param'] = sanitize_text_field($_POST['provider'][$key]['iframe_hash_param'] ?? ($provider['iframe_hash_param'] ?? 'secure_hash'));
            $providers[$key]['iframe_hash_format'] = sanitize_text_field($_POST['provider'][$key]['iframe_hash_format'] ?? ($provider['iframe_hash_format'] ?? '{user}-{secret}'));
            $providers[$key]['api_key'] = sanitize_text_field($_POST['provider'][$key]['api_key'] ?? '');
            $providers[$key]['postback_secret'] = sanitize_text_field($_POST['provider'][$key]['postback_secret'] ?? '');
            $providers[$key]['survey_token_secret'] = sanitize_text_field($_POST['provider'][$key]['survey_token_secret'] ?? ($provider['survey_token_secret'] ?? ''));
            $providers[$key]['points_multiplier'] = (float) ($_POST['provider'][$key]['points_multiplier'] ?? 1.0);
            $providers[$key]['votes_multiplier'] = (float) ($_POST['provider'][$key]['votes_multiplier'] ?? 1.0);
            $allowlist_raw = sanitize_text_field($_POST['provider'][$key]['allow_ips'] ?? '');
            $providers[$key]['allow_ips'] = array_values(array_filter(array_map('trim', explode(',', $allowlist_raw))));
        }
        impactshop_offerwall_save_providers($providers);
        echo '<div class="updated"><p>Mentve.</p></div>';
    }

    $providers = impactshop_offerwall_get_providers();
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_completions';
    $stats = $wpdb->get_row("SELECT COUNT(*) AS total, SUM(points_awarded) AS points, SUM(votes_awarded) AS votes FROM {$table}", ARRAY_A);
    $by_provider = $wpdb->get_results("SELECT provider, COUNT(*) AS total, SUM(points_awarded) AS points, SUM(votes_awarded) AS votes FROM {$table} GROUP BY provider", ARRAY_A);

    echo '<div class="wrap"><h1>Offerwall beállítások</h1>';
    echo '<div class="notice notice-info"><p>';
    echo 'Összes completion: <strong>' . esc_html((string) ($stats['total'] ?? 0)) . '</strong> · ';
    echo 'Összes pont: <strong>' . esc_html((string) ($stats['points'] ?? 0)) . '</strong> · ';
    echo 'Összes szavazat: <strong>' . esc_html((string) ($stats['votes'] ?? 0)) . '</strong>';
    echo '</p></div>';
    if (!empty($by_provider)) {
        echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Completion</th><th>Pont</th><th>Szavazat</th></tr></thead><tbody>';
        foreach ($by_provider as $row) {
            echo '<tr><td>' . esc_html((string) $row['provider']) . '</td><td>' . esc_html((string) $row['total']) . '</td><td>' . esc_html((string) $row['points']) . '</td><td>' . esc_html((string) $row['votes']) . '</td></tr>';
        }
        echo '</tbody></table><br />';
    }
    echo '<form method="post">';
    wp_nonce_field('impactshop_offerwall_save');
    echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Aktív</th><th>IFrame URL</th><th>User param</th><th>IFrame hash secret</th><th>Hash param</th><th>Hash format</th><th>API kulcs</th><th>Survey token secret</th><th>Postback URL</th><th>Secret</th><th>IP allowlist</th><th>Pont szorzó</th><th>Szavazat szorzó</th></tr></thead><tbody>';
    foreach ($providers as $key => $provider) {
        $postback_url = ($key === 'ayet')
            ? rest_url('impact/v1/ayet-callback')
            : rest_url('impact/v1/offerwall/callback/' . $key);
        echo '<tr>';
        echo '<td><input type="text" name="provider[' . esc_attr($key) . '][name]" value="' . esc_attr($provider['name']) . '" /></td>';
        echo '<td><input type="checkbox" name="provider[' . esc_attr($key) . '][enabled]" ' . checked(!empty($provider['enabled']), true, false) . ' /></td>';
        echo '<td><input class="regular-text" type="url" name="provider[' . esc_attr($key) . '][iframe_url]" value="' . esc_url($provider['iframe_url']) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][user_param]" value="' . esc_attr((string) ($provider['user_param'] ?? 'user_id')) . '" placeholder="user_id" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_secret]" value="' . esc_attr((string) ($provider['iframe_hash_secret'] ?? '')) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_param]" value="' . esc_attr((string) ($provider['iframe_hash_param'] ?? 'secure_hash')) . '" placeholder="secure_hash" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_format]" value="' . esc_attr((string) ($provider['iframe_hash_format'] ?? '{user}-{secret}')) . '" placeholder="{user}-{secret}" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][api_key]" value="' . esc_attr((string) ($provider['api_key'] ?? '')) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][survey_token_secret]" value="' . esc_attr((string) ($provider['survey_token_secret'] ?? '')) . '" /></td>';
        echo '<td><input class="regular-text offerwall-postback-url" type="text" readonly value="' . esc_url($postback_url) . '" /> ';
        echo '<button type="button" class="button offerwall-copy-btn" data-copy="' . esc_url($postback_url) . '">Másolás</button></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][postback_secret]" value="' . esc_attr($provider['postback_secret']) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][allow_ips]" value="' . esc_attr(implode(', ', (array) ($provider['allow_ips'] ?? []))) . '" placeholder="1.2.3.4, 5.6.7.8" /></td>';
        echo '<td><input type="number" step="0.1" name="provider[' . esc_attr($key) . '][points_multiplier]" value="' . esc_attr((string) $provider['points_multiplier']) . '" /></td>';
        echo '<td><input type="number" step="0.1" name="provider[' . esc_attr($key) . '][votes_multiplier]" value="' . esc_attr((string) $provider['votes_multiplier']) . '" /></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    submit_button('Mentés');
    echo '<script>document.querySelectorAll(".offerwall-copy-btn").forEach(function(btn){btn.addEventListener("click",function(){var text=btn.getAttribute("data-copy")||"";if(!text){return;}if(navigator.clipboard){navigator.clipboard.writeText(text);}else{var input=document.createElement(\"input\");input.value=text;document.body.appendChild(input);input.select();document.execCommand(\"copy\");document.body.removeChild(input);}btn.textContent=\"Másolva\";setTimeout(function(){btn.textContent=\"Másolás\";},1200);});});</script>';
    echo '</form></div>';
}

function impactshop_offerwall_extend_csp(array $headers, WP $wp): array
{
    $header_key = 'Content-Security-Policy';
    if (empty($headers[$header_key])) {
        return $headers;
    }

    $csp = (string) $headers[$header_key];
    if (strpos($csp, 'ayetstudios.com') !== false && strpos($csp, 'cpx-research.com') !== false) {
        return $headers;
    }

    $csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://www.ayetstudios.com');
    $csp = impactshop_offerwall_csp_append($csp, 'img-src', 'https://www.ayetstudios.com');
    $csp = impactshop_offerwall_csp_append($csp, 'img-src', 'https://quickchart.io');
    $csp = impactshop_offerwall_csp_append($csp, 'script-src', 'https://cdn.cpx-research.com');
    $csp = impactshop_offerwall_csp_append($csp, 'frame-src', 'https://wall.cpx-research.com');
    $csp = impactshop_offerwall_csp_append($csp, 'frame-src', 'https://offers.cpx-research.com');
    $csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://api.cpx-research.com');
    $csp = impactshop_offerwall_csp_append($csp, 'connect-src', 'https://wall.cpx-research.com');
    $csp = impactshop_offerwall_csp_append($csp, 'img-src', 'https://cdn.cpx-research.com');
    $headers[$header_key] = $csp;

    return $headers;
}

function impactshop_offerwall_csp_append(string $csp, string $directive, string $value): string
{
    $pattern = '/' . preg_quote($directive, '/') . '([^;]*)/i';
    if (!preg_match($pattern, $csp, $matches)) {
        return $csp;
    }
    $current = trim($matches[1]);
    if (strpos($current, $value) !== false) {
        return $csp;
    }
    $replacement = $directive . $current . ' ' . $value;
    return preg_replace($pattern, $replacement, $csp, 1) ?: $csp;
}
