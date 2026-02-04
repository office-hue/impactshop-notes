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

add_action('muplugins_loaded', 'impactshop_offerwall_bootstrap');

function impactshop_offerwall_bootstrap(): void
{
    impactshop_offerwall_maybe_install();
    add_action('rest_api_init', 'impactshop_offerwall_register_routes');
    add_shortcode('impactshop_offerwall', 'impactshop_offerwall_shortcode');
    add_action('wp_enqueue_scripts', 'impactshop_offerwall_enqueue_assets');
    add_action('admin_menu', 'impactshop_offerwall_admin_menu');
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
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
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
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
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

    register_rest_route('impact/v1', '/offerwall/history', [
        'methods' => 'GET',
        'callback' => 'impactshop_offerwall_get_history',
        'permission_callback' => '__return_true',
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

function impactshop_offerwall_signature_valid(array $params, array $provider): bool
{
    $secret = (string) ($provider['postback_secret'] ?? '');
    $sig_param = (string) ($provider['signature_param'] ?? 'signature');
    $signature = isset($params[$sig_param]) ? (string) $params[$sig_param] : '';
    if ($secret === '' || $signature === '') {
        return true;
    }
    $transaction_id = (string) ($params['transaction_id'] ?? $params['tx_id'] ?? '');
    if ($transaction_id === '') {
        return false;
    }

    if (($provider['signature_mode'] ?? '') === 'canonical_v1') {
        $user_id = (string) ($params['user_id'] ?? $params['pseudo_id'] ?? $params['ext_user_id'] ?? '');
        $payout = (string) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? 0);
        $timestamp = (string) ($params['timestamp'] ?? '');
        $canonical = $transaction_id . '|' . $user_id . '|' . $payout . '|' . $timestamp;
        $expected = hash_hmac('sha256', $canonical, $secret);
        return hash_equals($expected, $signature);
    }

    $candidates = [
        hash_hmac('sha256', $transaction_id, $secret),
        md5($transaction_id . $secret),
        md5($secret . $transaction_id),
        md5($transaction_id . ':' . $secret),
        md5($secret . ':' . $transaction_id),
    ];
    foreach ($candidates as $expected) {
        if (hash_equals($expected, $signature)) {
            return true;
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
    $params = array_merge($request->get_query_params(), $request->get_json_params());

    if (!impactshop_offerwall_signature_valid($params, $provider)) {
        return new WP_REST_Response(['status' => 'invalid_signature'], 403);
    }

    $ip = (string) ($request->get_header('x-forwarded-for') ?: $request->get_header('x-real-ip') ?: $request->get_header('client-ip'));
    $ip = $ip ? trim(explode(',', $ip)[0]) : '';
    $allowed_ips = array_filter((array) ($provider['allow_ips'] ?? []));
    if (!empty($allowed_ips) && $ip !== '' && !in_array($ip, $allowed_ips, true)) {
        impactshop_offerwall_log_fraud('ip_blocked', ['provider' => $provider_key, 'ip' => $ip]);
        return new WP_REST_Response(['status' => 'ip_blocked'], 403);
    }

    $pseudo_id = sanitize_text_field((string) ($params['pseudo_id'] ?? $params['sub_id'] ?? $params['user_id'] ?? $params['ext_user_id'] ?? $params['subid_1'] ?? $params['subid1'] ?? ''));
    if ($pseudo_id === '') {
        $pseudo_id = impactshop_offerwall_get_pseudo_id();
    }

    $transaction_id = sanitize_text_field((string) ($params['transaction_id'] ?? $params['tx_id'] ?? $params['transaction'] ?? $params['trans_id'] ?? ''));
    if ($transaction_id === '') {
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

    $points_awarded = $payout > 0 ? max(1, (int) round($payout * 100 * $points_multiplier)) : 0;
    $votes_awarded = $payout > 0 ? max(1, (int) round($payout * 10 * $votes_multiplier)) : 0;

    $request_id = wp_generate_uuid4();

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
        'approved',
        $request_id,
        current_time('mysql'),
        current_time('mysql')
    ));

    if ($inserted === 0) {
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
                'points_multiplier' => (float) ($provider['points_multiplier'] ?? 1.0),
                'votes_multiplier' => (float) ($provider['votes_multiplier'] ?? 1.0),
            ];
        }
    }

    return new WP_REST_Response([
        'providers' => $enabled,
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
        "SELECT offer_name, offer_type, points_awarded, votes_awarded, created_at, provider
         FROM {$table}
         WHERE pseudo_id = %s
         ORDER BY created_at DESC
         LIMIT 10",
        $pseudo_id
    ), ARRAY_A);

    return new WP_REST_Response(['items' => $items], 200);
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

    wp_register_style('impactshop-offerwall', false, [], '1.0.0');
    wp_enqueue_style('impactshop-offerwall');
    wp_add_inline_style('impactshop-offerwall', impactshop_offerwall_inline_css());

    wp_enqueue_script(
        'impactshop-offerwall',
        plugins_url('impactshop-offerwall.js', __FILE__),
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('impactshop-offerwall', 'impactshopOfferwall', [
        'restUrl' => esc_url_raw(rest_url('impact/v1/offerwall')),
    ]);
}

function impactshop_offerwall_inline_css(): string
{
    return '.impactshop-offerwall{background:#0f172a;color:#fff;border-radius:20px;padding:20px;margin:20px 0;font-family:inherit}' .
        '.impactshop-offerwall h3{margin:0 0 12px;font-size:20px}' .
        '.impactshop-offerwall .offerwall-trust{margin:6px 0 12px;font-size:13px;color:#cbd5f5}' .
        '.impactshop-offerwall .offerwall-faq-trigger{display:inline-flex;align-items:center;gap:6px;background:#111827;border:1px solid rgba(148,163,184,.3);color:#f8fafc;padding:6px 10px;border-radius:999px;font-size:12px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-faq{margin:10px 0 16px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.08);font-size:12px;line-height:1.5}' .
        '.impactshop-offerwall .offerwall-cards{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}' .
        '.impactshop-offerwall .offerwall-card{background:#111827;border-radius:16px;padding:14px;cursor:pointer}' .
        '.impactshop-offerwall .offerwall-card span{display:block;color:#cbd5f5;font-size:13px}' .
        '.impactshop-offerwall .offerwall-modal{position:fixed;inset:0;background:rgba(15,23,42,.7);display:none;align-items:center;justify-content:center;z-index:9999}' .
        '.impactshop-offerwall .offerwall-modal.active{display:flex}' .
        '.impactshop-offerwall .offerwall-frame{width:min(900px,90vw);height:min(80vh,720px);border:0;border-radius:18px;background:#fff}' .
        '.impactshop-offerwall .offerwall-history{margin-top:16px;background:#111827;border-radius:14px;padding:12px}' .
        '.impactshop-offerwall .offerwall-history li{display:flex;justify-content:space-between;color:#e2e8f0;font-size:13px;padding:6px 0;border-bottom:1px solid rgba(148,163,184,.2)}';
}

function impactshop_offerwall_shortcode(): string
{
    $html = '<div class="impactshop-offerwall" id="impactshop-offerwall">';
    $html .= '<h3>🎁 Feladatok</h3>';
    $html .= '<p class="offerwall-trust">Néha pár órán belül fut be a jutalom.</p>';
    $html .= '<button type="button" class="offerwall-faq-trigger" data-role="offerwall-faq-trigger">Hol a jutalmam?</button>';
    $html .= '<div class="offerwall-faq" data-role="offerwall-faq" hidden>';
    $html .= '<p>Az offerwall teljesítések feldolgozása szolgáltatófüggő, ezért előfordulhat néhány órás késés.</p>';
    $html .= '</div>';
    $html .= '<div class="offerwall-cards" data-role="offerwall-cards"></div>';
    $html .= '<div class="offerwall-history">';
    $html .= '<strong>Legutóbbi teljesítések</strong>';
    $html .= '<ul data-role="offerwall-history"></ul>';
    $html .= '</div>';
    $html .= '<div class="offerwall-modal" data-role="offerwall-modal">';
    $html .= '<div class="offerwall-modal-inner">';
    $html .= '<iframe class="offerwall-frame" data-role="offerwall-frame" title="Offerwall" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation-by-user-activation" referrerpolicy="no-referrer"></iframe>';
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
    echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Aktív</th><th>IFrame URL</th><th>User param</th><th>IFrame hash secret</th><th>Hash param</th><th>Hash format</th><th>API kulcs</th><th>Postback URL</th><th>Secret</th><th>IP allowlist</th><th>Pont szorzó</th><th>Szavazat szorzó</th></tr></thead><tbody>';
    foreach ($providers as $key => $provider) {
        $postback_url = rest_url('impact/v1/offerwall/callback/' . $key);
        echo '<tr>';
        echo '<td><input type="text" name="provider[' . esc_attr($key) . '][name]" value="' . esc_attr($provider['name']) . '" /></td>';
        echo '<td><input type="checkbox" name="provider[' . esc_attr($key) . '][enabled]" ' . checked(!empty($provider['enabled']), true, false) . ' /></td>';
        echo '<td><input class="regular-text" type="url" name="provider[' . esc_attr($key) . '][iframe_url]" value="' . esc_url($provider['iframe_url']) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][user_param]" value="' . esc_attr((string) ($provider['user_param'] ?? 'user_id')) . '" placeholder="user_id" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_secret]" value="' . esc_attr((string) ($provider['iframe_hash_secret'] ?? '')) . '" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_param]" value="' . esc_attr((string) ($provider['iframe_hash_param'] ?? 'secure_hash')) . '" placeholder="secure_hash" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][iframe_hash_format]" value="' . esc_attr((string) ($provider['iframe_hash_format'] ?? '{user}-{secret}')) . '" placeholder="{user}-{secret}" /></td>';
        echo '<td><input class="regular-text" type="text" name="provider[' . esc_attr($key) . '][api_key]" value="' . esc_attr((string) ($provider['api_key'] ?? '')) . '" /></td>';
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
