<?php
/**
 * Impact Challenge - Vote Purchase (Stripe Checkout)
 *
 * @package ImpactShop
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IMPACTSHOP_VOTE_PURCHASE_VERSION', '1.0.7');
define('IMPACTSHOP_VOTE_PURCHASE_SCHEMA_VERSION', '1.0.0');

add_action('init', 'impactshop_vote_purchase_ensure_schema', 5);
add_action('rest_api_init', 'impactshop_vote_purchase_register_routes');
add_action('admin_menu', 'impactshop_vote_purchase_register_admin_page');
add_action('init', 'impactshop_vote_purchase_schedule_cert_cron');
add_action('impactshop_vote_purchase_cert_cron', 'impactshop_vote_purchase_process_cert_queue');
add_filter('cron_schedules', 'impactshop_vote_purchase_cron_schedules');

function impactshop_vote_purchase_is_configured(): bool
{
    return defined('IMPACT_STRIPE_SECRET_KEY')
        && defined('IMPACT_STRIPE_PUBLIC_KEY')
        && defined('IMPACT_STRIPE_WEBHOOK_SECRET')
        && defined('IMPACT_STRIPE_MODE')
        && defined('IMPACT_STRIPE_DEFAULT_CURRENCY')
        && IMPACT_STRIPE_SECRET_KEY !== ''
        && IMPACT_STRIPE_PUBLIC_KEY !== ''
        && IMPACT_STRIPE_WEBHOOK_SECRET !== ''
        && IMPACT_STRIPE_DEFAULT_CURRENCY !== '';
}

function impactshop_vote_purchase_zero_decimal_currencies(): array
{
    return ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
}

function impactshop_vote_purchase_get_packages(): array
{
    return [
        'starter' => [
            'label' => 'Starter',
            'emoji' => '🌱',
            'votes' => 500,
            'bonus_votes' => 0,
            'prices' => [
                'huf' => 500,
                'eur' => 1.50,
                'usd' => 1.50,
                'gbp' => 1.20,
                'czk' => 35,
                'pln' => 6,
            ],
            'badge' => null,
        ],
        'supporter' => [
            'label' => 'Supporter',
            'emoji' => '🌿',
            'votes' => 1000,
            'bonus_votes' => 100,
            'prices' => [
                'huf' => 1000,
                'eur' => 3.00,
                'usd' => 3.00,
                'gbp' => 2.50,
                'czk' => 70,
                'pln' => 12,
            ],
            'badge' => null,
        ],
        'champion' => [
            'label' => 'Champion',
            'emoji' => '🌳',
            'votes' => 2500,
            'bonus_votes' => 500,
            'prices' => [
                'huf' => 2500,
                'eur' => 7.00,
                'usd' => 7.00,
                'gbp' => 6.00,
                'czk' => 175,
                'pln' => 30,
            ],
            'badge' => 'Legjobb érték',
        ],
        'hero' => [
            'label' => 'Hero',
            'emoji' => '🔥',
            'votes' => 5000,
            'bonus_votes' => 1500,
            'prices' => [
                'huf' => 5000,
                'eur' => 14.00,
                'usd' => 14.00,
                'gbp' => 12.00,
                'czk' => 350,
                'pln' => 60,
            ],
            'badge' => null,
        ],
        'legend' => [
            'label' => 'Legend',
            'emoji' => '💎',
            'votes' => 10000,
            'bonus_votes' => 5000,
            'prices' => [
                'huf' => 10000,
                'eur' => 28.00,
                'usd' => 28.00,
                'gbp' => 24.00,
                'czk' => 700,
                'pln' => 120,
            ],
            'badge' => null,
        ],
    ];
}

function impactshop_vote_purchase_get_public_config(): array
{
    $packages = impactshop_vote_purchase_get_packages();
    $default_currency = defined('IMPACT_STRIPE_DEFAULT_CURRENCY') ? IMPACT_STRIPE_DEFAULT_CURRENCY : 'huf';
    $currency = strtolower($default_currency);
    return [
        'enabled' => impactshop_vote_purchase_is_configured(),
        'currency' => $currency,
        'packages' => $packages,
        'publicKey' => defined('IMPACT_STRIPE_PUBLIC_KEY') ? IMPACT_STRIPE_PUBLIC_KEY : '',
    ];
}

function impactshop_vote_purchase_ensure_schema(): void
{
    $version = (string) get_option('impactshop_vote_purchase_schema_version', '');
    if ($version === IMPACTSHOP_VOTE_PURCHASE_SCHEMA_VERSION) {
        impactshop_vote_purchase_ensure_ads_votes_source_column();
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'impactshop_vote_purchases';

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(32) NOT NULL UNIQUE,
        pseudo_id VARCHAR(20) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        ngo_slug VARCHAR(190) NOT NULL,
        package_id VARCHAR(20) NOT NULL DEFAULT 'starter',
        votes INT UNSIGNED NOT NULL,
        bonus_votes INT UNSIGNED NOT NULL DEFAULT 0,
        amount_minor INT UNSIGNED NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'huf',
        amount_display DECIMAL(10,2) NOT NULL,
        donation_part INT UNSIGNED NOT NULL,
        revenue_part INT UNSIGNED NOT NULL,
        quarter_key VARCHAR(10) NOT NULL,
        status ENUM('pending','completed','failed','expired','voided') NOT NULL DEFAULT 'pending',
        stripe_session_id VARCHAR(128) DEFAULT NULL,
        stripe_payment_intent VARCHAR(128) DEFAULT NULL,
        stripe_charge_id VARCHAR(128) DEFAULT NULL,
        is_company TINYINT(1) NOT NULL DEFAULT 0,
        company_name VARCHAR(255) DEFAULT NULL,
        company_tax_id VARCHAR(30) DEFAULT NULL,
        company_address VARCHAR(500) DEFAULT NULL,
        gdpr_email_consent TINYINT(1) NOT NULL DEFAULT 0,
        company_data_deleted_at DATETIME DEFAULT NULL,
        donation_cert_id VARCHAR(30) DEFAULT NULL,
        donation_cert_status ENUM('none','pending','generated','sent') NOT NULL DEFAULT 'none',
        donation_cert_sent_at DATETIME DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(512) DEFAULT NULL,
        consent_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        voided_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        return_url VARCHAR(500) DEFAULT NULL,
        INDEX idx_ngo (ngo_slug, quarter_key),
        INDEX idx_quarter_status (quarter_key, status),
        INDEX idx_stripe_pi (stripe_payment_intent),
        INDEX idx_stripe_session (stripe_session_id),
        INDEX idx_email (email),
        INDEX idx_status_created (status, created_at),
        INDEX idx_currency (currency, created_at),
        INDEX idx_company (is_company, donation_cert_status),
        INDEX idx_cert_id (donation_cert_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('impactshop_vote_purchase_schema_version', IMPACTSHOP_VOTE_PURCHASE_SCHEMA_VERSION, false);
    impactshop_vote_purchase_ensure_ads_votes_source_column();
}

function impactshop_vote_purchase_ensure_ads_votes_source_column(): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_votes';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
    $has_source = false;
    $has_source_ref = false;
    foreach ($columns as $column) {
        if (($column['Field'] ?? '') === 'source') {
            $has_source = true;
        }
        if (($column['Field'] ?? '') === 'source_ref') {
            $has_source_ref = true;
        }
    }
    if (!$has_source) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN source ENUM('organic','purchased') NOT NULL DEFAULT 'organic' AFTER ad_type");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_source_quarter (source, quarter_key)");
    }
    if (!$has_source_ref) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN source_ref VARCHAR(32) DEFAULT NULL AFTER source");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_source_ref (source_ref)");
    }
}

function impactshop_vote_purchase_register_routes(): void
{
    register_rest_route('impact/v1', '/vote-purchase/start', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vote_purchase_start',
        'permission_callback' => 'impactshop_vote_purchase_require_nonce',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/status', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_purchase_status',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/history', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_purchase_history',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/saved-company-data', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_purchase_saved_company_data',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/webhook', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vote_purchase_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/success', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_purchase_success',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote-purchase/cancel', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_purchase_cancel',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_vote_purchase_require_nonce(WP_REST_Request $request)
{
    if (!impactshop_vote_purchase_https_ok()) {
        return new WP_Error('HTTPS_REQUIRED', 'HTTPS szükséges.', ['status' => 403]);
    }
    if (!impactshop_vote_purchase_check_origin()) {
        return new WP_Error('INVALID_ORIGIN', 'Hibás origin.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('INVALID_NONCE', 'Érvénytelen nonce.', ['status' => 403]);
    }

    return true;
}

function impactshop_vote_purchase_check_origin(): bool
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    if ($origin === '') {
        return true;
    }
    $origin_host = wp_parse_url($origin, PHP_URL_HOST);
    $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
    return $origin_host && $home_host && $origin_host === $home_host;
}

function impactshop_vote_purchase_https_ok(): bool
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return wp_parse_url(home_url(), PHP_URL_SCHEME) === 'https';
}

function impactshop_vote_purchase_get_pseudo_id(?WP_REST_Request $request = null): string
{
    $pseudo = '';
    if ($request) {
        $pseudo = (string) $request->get_param('pseudo_id');
    }
    if ($pseudo === '' && isset($_COOKIE['impactshop_pseudo_id'])) {
        $pseudo = (string) $_COOKIE['impactshop_pseudo_id'];
    }
    $pseudo = strtolower(sanitize_text_field($pseudo));
    if (function_exists('sharity_normalize_pseudo_id')) {
        $pseudo = sharity_normalize_pseudo_id($pseudo);
    }
    return $pseudo;
}

function impactshop_vote_purchase_get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return sanitize_text_field((string) $ip);
}

function impactshop_vote_purchase_rate_limit(string $pseudo_id): array
{
    $ip = impactshop_vote_purchase_get_client_ip();
    if (function_exists('impactshop_ads_watch_rate_limit_check')) {
        $pseudo_rate = impactshop_ads_watch_rate_limit_check('vote_purchase_pseudo_' . $pseudo_id, 5, HOUR_IN_SECONDS);
        $ip_rate = impactshop_ads_watch_rate_limit_check('vote_purchase_ip_' . $ip, 10, HOUR_IN_SECONDS);
        return [
            'pseudo' => $pseudo_rate,
            'ip' => $ip_rate,
        ];
    }

    $pseudo_key = 'vote_purchase_pseudo_' . $pseudo_id;
    $ip_key = 'vote_purchase_ip_' . $ip;
    return [
        'pseudo' => impactshop_vote_purchase_rate_limit_check($pseudo_key, 5, HOUR_IN_SECONDS),
        'ip' => impactshop_vote_purchase_rate_limit_check($ip_key, 10, HOUR_IN_SECONDS),
    ];
}

function impactshop_vote_purchase_rate_limit_check(string $key, int $limit, int $window): array
{
    $data = get_transient($key);
    if (!is_array($data)) {
        $data = ['count' => 0, 'reset' => time() + $window];
    }
    $data['count'] = (int) $data['count'] + 1;
    set_transient($key, $data, $window);
    $remaining = max(0, $limit - $data['count']);
    return [
        'limit' => $limit,
        'remaining' => $remaining,
        'reset' => (int) ($data['reset'] ?? (time() + $window)),
        'blocked' => $data['count'] > $limit,
    ];
}

function impactshop_vote_purchase_headers_from_rate(array $rate): array
{
    return [
        'X-RateLimit-Limit' => (string) ($rate['limit'] ?? 0),
        'X-RateLimit-Remaining' => (string) ($rate['remaining'] ?? 0),
        'X-RateLimit-Reset' => (string) ($rate['reset'] ?? 0),
    ];
}

function impactshop_vote_purchase_validate_ngo(string $ngo_slug): bool
{
    if ($ngo_slug === '') {
        return false;
    }
    if (function_exists('impactshop_ads_watch_get_ngo_list')) {
        $list = impactshop_ads_watch_get_ngo_list($ngo_slug, 10);
        foreach ($list as $item) {
            if (($item['slug'] ?? '') === $ngo_slug) {
                return true;
            }
        }
    }
    return false;
}

function impactshop_vote_purchase_get_amount_minor($amount_display, string $currency): int
{
    $currency = strtolower($currency);
    if (in_array($currency, impactshop_vote_purchase_zero_decimal_currencies(), true)) {
        return (int) round((float) $amount_display, 0);
    }
    return (int) round(((float) $amount_display) * 100, 0);
}

function impactshop_vote_purchase_start(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_vote_purchase_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    if (function_exists('impactshop_ads_is_quarter_locked') && impactshop_ads_is_quarter_locked()) {
        return new WP_REST_Response(['error' => 'quarter_locked'], 503);
    }

    $pseudo_id = impactshop_vote_purchase_get_pseudo_id($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response(['error' => 'missing_pseudo'], 400);
    }

    $rate = impactshop_vote_purchase_rate_limit($pseudo_id);
    if (($rate['pseudo']['blocked'] ?? false) || ($rate['ip']['blocked'] ?? false)) {
        $headers = array_merge(
            impactshop_vote_purchase_headers_from_rate($rate['pseudo']),
            impactshop_vote_purchase_headers_from_rate($rate['ip'])
        );
        return new WP_REST_Response(['error' => 'rate_limited'], 429, $headers);
    }

    $params = (array) $request->get_json_params();
    $ngo_slug = strtolower(sanitize_text_field((string) ($params['ngo_slug'] ?? '')));

    $package_id = strtolower(sanitize_text_field((string) ($params['package_id'] ?? '')));
    $packages = impactshop_vote_purchase_get_packages();
    if (!isset($packages[$package_id])) {
        return new WP_REST_Response(['error' => 'invalid_package'], 400);
    }

    $default_currency = defined('IMPACT_STRIPE_DEFAULT_CURRENCY') ? IMPACT_STRIPE_DEFAULT_CURRENCY : 'huf';
    $currency = strtolower(sanitize_text_field((string) ($params['currency'] ?? $default_currency)));
    $package = $packages[$package_id];
    if (!isset($package['prices'][$currency])) {
        return new WP_REST_Response(['error' => 'invalid_currency'], 400);
    }

    $consent = (bool) ($params['consent'] ?? false);
    if (!$consent) {
        return new WP_REST_Response(['error' => 'missing_consent'], 400);
    }

    $is_company = (bool) ($params['is_company'] ?? false);
    $email = sanitize_email((string) ($params['email'] ?? ''));
    $company_name = sanitize_text_field((string) ($params['company_name'] ?? ''));
    $company_tax_id = sanitize_text_field((string) ($params['company_tax_id'] ?? ''));
    $company_address = sanitize_text_field((string) ($params['company_address'] ?? ''));
    $gdpr_email_consent = (bool) ($params['gdpr_email_consent'] ?? false);
    $save_company_data = (bool) ($params['save_company_data'] ?? false);

    if ($is_company) {
        if ($email === '' || $company_name === '' || $company_tax_id === '' || $company_address === '' || !$gdpr_email_consent) {
            return new WP_REST_Response(['error' => 'missing_company_fields'], 400);
        }
    }

    $amount_display = (float) $package['prices'][$currency];
    $amount_huf_display = (float) ($package['prices']['huf'] ?? $amount_display);
    $amount_minor = impactshop_vote_purchase_get_amount_minor($amount_display, $currency);
    $votes = (int) $package['votes'];
    $bonus_votes = (int) ($package['bonus_votes'] ?? 0);
    $total_votes = $votes + $bonus_votes;
    $donation_part = (int) round($amount_huf_display * 0.5, 0);
    $revenue_part = (int) round($amount_huf_display * 0.5, 0);

    $quarter_key = function_exists('impactshop_ads_get_active_quarter')
        ? impactshop_ads_get_active_quarter()
        : gmdate('Y') . 'Q' . (int) ceil(gmdate('n') / 3);

    $order_id = 'VP-' . gmdate('YmdHis') . '-' . wp_generate_password(6, false, false);
    $return_url = esc_url_raw((string) ($params['return_url'] ?? ''));
    if ($return_url !== '' && wp_parse_url($return_url, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST)) {
        $return_url = '';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $wpdb->insert($table, [
        'order_id' => $order_id,
        'pseudo_id' => $pseudo_id,
        'email' => $email !== '' ? $email : null,
        'ngo_slug' => $ngo_slug,
        'package_id' => $package_id,
        'votes' => $total_votes,
        'bonus_votes' => $bonus_votes,
        'amount_minor' => $amount_minor,
        'currency' => $currency,
        'amount_display' => $amount_display,
        'donation_part' => $donation_part,
        'revenue_part' => $revenue_part,
        'quarter_key' => $quarter_key,
        'status' => 'pending',
        'is_company' => $is_company ? 1 : 0,
        'company_name' => $company_name !== '' ? $company_name : null,
        'company_tax_id' => $company_tax_id !== '' ? $company_tax_id : null,
        'company_address' => $company_address !== '' ? $company_address : null,
        'gdpr_email_consent' => $gdpr_email_consent ? 1 : 0,
        'ip_address' => impactshop_vote_purchase_get_client_ip(),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        'consent_at' => current_time('mysql', 1),
        'return_url' => $return_url !== '' ? $return_url : null,
    ], ['%s','%s','%s','%s','%s','%d','%d','%d','%s','%f','%d','%d','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s']);
    if ($wpdb->last_error) {
        error_log('[impactshop-vote-purchase] db insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(['error' => 'db_error'], 500);
    }

    if ($save_company_data && $is_company) {
        update_option('impactshop_vote_purchase_company_' . $pseudo_id, [
            'company_name' => $company_name,
            'company_tax_id' => $company_tax_id,
            'company_address' => $company_address,
            'email' => $email,
        ], false);
    }

    $session = impactshop_vote_purchase_create_checkout_session([
        'order_id' => $order_id,
        'pseudo_id' => $pseudo_id,
        'ngo_slug' => $ngo_slug,
        'package_id' => $package_id,
        'votes' => $total_votes,
        'bonus_votes' => $bonus_votes,
        'amount_display' => $amount_display,
        'amount_minor' => $amount_minor,
        'currency' => $currency,
        'donation_part' => $donation_part,
        'quarter_key' => $quarter_key,
        'email' => $email,
    ]);

    if (!$session || empty($session['url'])) {
        $wpdb->update($table, ['status' => 'failed'], ['order_id' => $order_id], ['%s'], ['%s']);
        return new WP_REST_Response(['error' => 'stripe_failed'], 502);
    }

    $wpdb->update($table, ['stripe_session_id' => $session['id']], ['order_id' => $order_id], ['%s'], ['%s']);

    return new WP_REST_Response([
        'order_id' => $order_id,
        'stripe_checkout_url' => $session['url'],
    ], 200, impactshop_vote_purchase_headers_from_rate($rate['pseudo']));
}

function impactshop_vote_purchase_create_checkout_session(array $order): ?array
{
    $unit_amount = impactshop_vote_purchase_get_amount_minor((float) $order['amount_display'], (string) $order['currency']);
    $package_label = ucfirst((string) ($order['package_id'] ?? 'Csomag'));
    $product_name = sprintf(
        '%s – %d szavazat / votes – Impact Challenge',
        $package_label,
        (int) $order['votes']
    );

    $success_url = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', rest_url('impact/v1/vote-purchase/success'));
    $cancel_url = add_query_arg('order_id', $order['order_id'], rest_url('impact/v1/vote-purchase/cancel'));

    $payload = [
        'mode' => 'payment',
        'payment_method_types[0]' => 'card',
        'line_items[0][price_data][currency]' => strtolower((string) $order['currency']),
        'line_items[0][price_data][unit_amount]' => $unit_amount,
        'line_items[0][price_data][product_data][name]' => $product_name,
        'line_items[0][price_data][product_data][description]' => '50% közös adományalap / 50% platform',
        'line_items[0][quantity]' => 1,
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'locale' => 'auto',
        'metadata[order_id]' => (string) $order['order_id'],
        'metadata[pseudo_id]' => (string) $order['pseudo_id'],
        'metadata[ngo_slug]' => (string) $order['ngo_slug'],
        'metadata[votes]' => (string) $order['votes'],
        'metadata[bonus_votes]' => (string) $order['bonus_votes'],
        'metadata[donation_part]' => (string) $order['donation_part'],
        'metadata[quarter_key]' => (string) $order['quarter_key'],
    ];

    if (!empty($order['email'])) {
        $payload['customer_email'] = (string) $order['email'];
    }

    error_log('[impactshop-vote-purchase] stripe payload: order_id=' . $order['order_id'] . ' currency=' . $payload['line_items[0][price_data][currency]'] . ' unit_amount=' . $unit_amount . ' amount_display=' . $order['amount_display']);

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
        ],
        'body' => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-vote-purchase] stripe request failed: ' . $response->get_error_message());
        return null;
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        $request_id = wp_remote_retrieve_header($response, 'request-id');
        $body_preview = substr((string) $body, 0, 500);
        error_log('[impactshop-vote-purchase] stripe response error: code=' . $code . ' request_id=' . $request_id . ' body=' . $body_preview);
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['url']) || empty($data['id'])) {
        return null;
    }
    return [
        'id' => $data['id'],
        'url' => $data['url'],
    ];
}

function impactshop_vote_purchase_webhook(): WP_REST_Response
{
    if (!impactshop_vote_purchase_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload = file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    if (!impactshop_vote_purchase_verify_stripe_signature($payload, $sig_header, IMPACT_STRIPE_WEBHOOK_SECRET)) {
        return new WP_REST_Response(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }

    if ($event['type'] === 'checkout.session.completed') {
        $session = $event['data']['object'] ?? [];
        $meta = $session['metadata'] ?? [];
        $order_id = isset($meta['order_id']) ? (string) $meta['order_id'] : '';
        if ($order_id !== '') {
            impactshop_vote_purchase_fulfill($order_id, [
                'stripe_session_id' => (string) ($session['id'] ?? ''),
                'stripe_payment_intent' => (string) ($session['payment_intent'] ?? ''),
                'amount_total' => (int) ($session['amount_total'] ?? 0),
                'currency' => (string) ($session['currency'] ?? ''),
            ]);
        }
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_vote_purchase_verify_stripe_signature(string $payload, string $sig_header, string $secret): bool
{
    if ($sig_header === '' || $secret === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $sig_header) as $item) {
        $pair = explode('=', trim($item), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }
    $signed = $parts['t'] . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    $signatures = explode(' ', str_replace(',', ' ', (string) $parts['v1']));
    foreach ($signatures as $signature) {
        if ($signature !== '' && hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

function impactshop_vote_purchase_fulfill(string $order_id, array $stripe_payload): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s", $order_id), ARRAY_A);
    if (!$order) {
        return;
    }
    if (($order['status'] ?? '') === 'completed') {
        return;
    }

    $wpdb->query('START TRANSACTION');
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s FOR UPDATE", $order_id), ARRAY_A);
    if (!$order || ($order['status'] ?? '') === 'completed') {
        $wpdb->query('ROLLBACK');
        return;
    }

    $votes = (int) ($order['votes'] ?? 0);
    $quarter_key = (string) ($order['quarter_key'] ?? '');
    $donation_part = (int) ($order['donation_part'] ?? 0);
    $pseudo_id = (string) ($order['pseudo_id'] ?? '');
    $ngo_slug = (string) ($order['ngo_slug'] ?? '');

    impactshop_vote_purchase_allocate_votes($order_id, $pseudo_id, $ngo_slug, $votes, $quarter_key);
    impactshop_vote_purchase_increment_pool($quarter_key, $donation_part);
    impactshop_vote_purchase_add_stats_votes($pseudo_id, $votes);

    $update = [
        'status' => 'completed',
        'stripe_session_id' => $stripe_payload['stripe_session_id'] ?? $order['stripe_session_id'],
        'stripe_payment_intent' => $stripe_payload['stripe_payment_intent'] ?? $order['stripe_payment_intent'],
        'completed_at' => current_time('mysql', 1),
    ];
    $update_formats = ['%s','%s','%s','%s'];

    $email = (string) ($order['email'] ?? '');
    $is_company = (int) ($order['is_company'] ?? 0) === 1;
    if ($is_company && $email !== '') {
        $update['donation_cert_status'] = 'pending';
        $update['donation_cert_id'] = impactshop_vote_purchase_generate_cert_id();
        $update_formats[] = '%s';
        $update_formats[] = '%s';
    }

    $wpdb->update($table, $update, ['order_id' => $order_id], $update_formats, ['%s']);

    $wpdb->query('COMMIT');
}

function impactshop_vote_purchase_allocate_votes(string $order_id, string $pseudo_id, string $ngo_slug, int $votes, string $quarter_key): void
{
    if ($votes <= 0 || $pseudo_id === '') {
        return;
    }
    if (function_exists('impactshop_ads_watch_add_votes')) {
        impactshop_ads_watch_add_votes($pseudo_id, $votes);
    }
}

function impactshop_vote_purchase_generate_cert_id(): string
{
    $year = gmdate('Y');
    $key = 'impactshop_vote_purchase_cert_seq_' . $year;
    $seq = (int) get_option($key, 0) + 1;
    update_option($key, $seq, false);
    return sprintf('IMPACT-DC-%s-%04d', $year, $seq);
}

function impactshop_vote_purchase_currency_name(string $currency): string
{
    $currency = strtolower($currency);
    $map = [
        'huf' => 'forint',
        'eur' => 'euró',
        'usd' => 'dollár',
        'gbp' => 'font',
        'czk' => 'korona',
        'pln' => 'zloty',
    ];
    return $map[$currency] ?? $currency;
}

function impactshop_vote_purchase_hu_number_to_words(int $number): string
{
    if ($number === 0) {
        return 'nulla';
    }
    $units = [
        1 => 'egy', 2 => 'kettő', 3 => 'három', 4 => 'négy', 5 => 'öt',
        6 => 'hat', 7 => 'hét', 8 => 'nyolc', 9 => 'kilenc'
    ];
    $teens = [
        10 => 'tíz', 11 => 'tizenegy', 12 => 'tizenkettő', 13 => 'tizenhárom', 14 => 'tizennégy',
        15 => 'tizenöt', 16 => 'tizenhat', 17 => 'tizenhét', 18 => 'tizennyolc', 19 => 'tizenkilenc'
    ];
    $tens = [
        2 => 'huszon', 3 => 'harminc', 4 => 'negyven', 5 => 'ötven',
        6 => 'hatvan', 7 => 'hetven', 8 => 'nyolcvan', 9 => 'kilencven'
    ];

    $result = '';

    if ($number >= 1000000) {
        $millions = (int) floor($number / 1000000);
        $result .= ($millions === 1 ? 'egymillió' : impactshop_vote_purchase_hu_number_to_words($millions) . 'millió');
        $number = $number % 1000000;
        if ($number === 0) {
            return $result;
        }
    }

    if ($number >= 1000) {
        $thousands = (int) floor($number / 1000);
        if ($thousands === 1) {
            $result .= 'ezer';
        } else {
            $result .= impactshop_vote_purchase_hu_number_to_words($thousands) . 'ezer';
        }
        $number = $number % 1000;
        if ($number === 0) {
            return $result;
        }
    }

    if ($number >= 100) {
        $hundreds = (int) floor($number / 100);
        if ($hundreds === 1) {
            $result .= 'száz';
        } else {
            $result .= ($units[$hundreds] ?? '') . 'száz';
        }
        $number = $number % 100;
        if ($number === 0) {
            return $result;
        }
    }

    if ($number >= 20) {
        $ten = (int) floor($number / 10);
        $unit = $number % 10;
        if ($unit === 0) {
            return $result . ($ten === 2 ? 'húsz' : ($tens[$ten] ?? ''));
        }
        $result .= ($tens[$ten] ?? '');
        $number = $unit;
    }

    if ($number >= 10) {
        return $result . ($teens[$number] ?? '');
    }

    if ($number > 0) {
        return $result . ($units[$number] ?? '');
    }

    return $result;
}

function impactshop_vote_purchase_format_date_parts(string $date_str): array
{
    $ts = strtotime($date_str);
    if (!$ts) {
        $ts = time();
    }
    $months = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április', 5 => 'május', 6 => 'június',
        7 => 'július', 8 => 'augusztus', 9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december'
    ];
    $month_num = (int) gmdate('n', $ts);
    return [
        'year' => gmdate('Y', $ts),
        'month' => $months[$month_num] ?? '',
        'day' => gmdate('j', $ts),
    ];
}

function impactshop_vote_purchase_cron_schedules(array $schedules): array
{
    if (!isset($schedules['impactshop_10min'])) {
        $schedules['impactshop_10min'] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display' => 'Every 10 Minutes',
        ];
    }
    return $schedules;
}

function impactshop_vote_purchase_schedule_cert_cron(): void
{
    if (!wp_next_scheduled('impactshop_vote_purchase_cert_cron')) {
        wp_schedule_event(time() + 120, 'impactshop_10min', 'impactshop_vote_purchase_cert_cron');
    }
}

function impactshop_vote_purchase_process_cert_queue(): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $rows = $wpdb->get_results(
        "SELECT order_id, email, company_name, company_tax_id, company_address, amount_display, currency, donation_part, completed_at, donation_cert_id
         FROM {$table}
         WHERE status = 'completed'
           AND is_company = 1
           AND email <> ''
           AND donation_cert_status = 'pending'
         ORDER BY completed_at ASC
         LIMIT 20",
        ARRAY_A
    );
    if (!$rows) {
        return;
    }

    foreach ($rows as $row) {
        $email = (string) ($row['email'] ?? '');
        if ($email === '') {
            continue;
        }
        $cert_id = (string) ($row['donation_cert_id'] ?? impactshop_vote_purchase_generate_cert_id());
        $subject = 'Adományigazolás – Impact Challenge – ' . $cert_id;
        $amount = number_format((float) ($row['amount_display'] ?? 0), 2, ',', ' ');
        $currency = strtoupper((string) ($row['currency'] ?? 'HUF'));
        $donation_huf = number_format((int) ($row['donation_part'] ?? 0), 0, ',', ' ');
        $completed_at = (string) ($row['completed_at'] ?? current_time('mysql', 1));

        $message = "Adományigazolás\n\n";
        $message .= "Igazolás azonosító: {$cert_id}\n";
        $message .= "Rendelés: {$row['order_id']}\n";
        $message .= "Dátum: {$completed_at}\n\n";
        $message .= "Cég neve: {$row['company_name']}\n";
        $message .= "Adószám: {$row['company_tax_id']}\n";
        $message .= "Székhely: {$row['company_address']}\n\n";
        $message .= "Befizetés: {$amount} {$currency}\n";
        $message .= "Közös adományalap része (50%): {$donation_huf} HUF\n\n";
        $message .= "Köszönjük a támogatást!\n";

        $amount_number = (int) round((float) ($row['amount_display'] ?? 0), 0);
        $amount_words = impactshop_vote_purchase_hu_number_to_words($amount_number);
        $currency_name = impactshop_vote_purchase_currency_name((string) ($row['currency'] ?? 'huf'));
        $date_parts = impactshop_vote_purchase_format_date_parts((string) ($row['completed_at'] ?? ''));

        $template = "NYILATKOZAT\n"
            . "nem magánszemély adózó részére\n"
            . "{$date_parts['year']}. évi\n"
            . "nem közhasznú jogállású szervezetnek adott támogatásról\n\n"
            . "1. A pénzbeli juttatás jogosultjának (támogatott)\n"
            . "   1.1 megnevezése: Sharity Adományszervező Alapítvány\n"
            . "   1.2 székhelye: 7090 Tamási, Petőfi Sándor u. 12. Magyarország\n"
            . "   1.3 adószáma: 19329006-1-17, HU19329006\n\n"
            . "2. A pénzbeli juttatással támogatott cél:\n"
            . "   Impact Challenge közös adományalap\n\n"
            . "3. A támogatás összege: {$amount} {$currency}\n"
            . "   (azaz {$amount_words} {$currency_name})\n\n"
            . "4. A támogatást nyújtó\n"
            . "   4.1 megnevezése: {$row['company_name']}\n"
            . "   4.2 székhelye: {$row['company_address']}\n"
            . "   4.3 adószáma: {$row['company_tax_id']}\n\n"
            . "Nyilatkozom, hogy szervezetünk a támogatást bevételként\n"
            . "elszámolta és a juttatás nyújtásának adóévében a szervezetünk\n"
            . "adózás előtti eredménye, adóalapja e juttatás nélkül is pozitív\n"
            . "lenne, és nem lenne veszteséges.\n\n"
            . "Nyilatkozom, hogy a támogatást a fent megnevezett támogató\n"
            . "a Sharity Mobile Application Zrt. üzemeltetésében álló Sharity\n"
            . "megnevezésű, okostelefonokra telepíthető és azokon futtatható\n"
            . "programon („Sharity\") keresztül juttatta szervezetünk részére.\n"
            . "A Sharity a civil szervezetek részére történő adományozás\n"
            . "terén kínál innovatív lehetőségeket. Az Alkalmazás felületén\n"
            . "keresztül az Alkalmazást letöltő Végfelhasználók az ott\n"
            . "feltüntetett civil szervezetek által indított adománygyűjtési\n"
            . "kampányok részére pénzeszközt adományozhatnak az Alkalmazásba\n"
            . "épített online fizetési felületen keresztül.\n\n"
            . "Kelt: ............, {$date_parts['year']}. {$date_parts['month']} {$date_parts['day']}.\n\n"
            . "P. H.\n\n"
            . ".....................................................\n"
            . "a szervezet képviselőjének aláírása\n\n"
            . "Igazolás azonosító: {$cert_id}\n"
            . "Rendelés: {$row['order_id']}\n"
            . "Dátum: {$completed_at}\n";

        $recipients = [$email, 'office@sharity.hu'];
        $sent = wp_mail($recipients, $subject, $template);
        if ($sent) {
            $wpdb->update($table, [
                'donation_cert_status' => 'sent',
                'donation_cert_id' => $cert_id,
                'donation_cert_sent_at' => current_time('mysql', 1),
            ], ['order_id' => $row['order_id']], ['%s','%s','%s'], ['%s']);
        } else {
            error_log('[impactshop-vote-purchase] donation cert send failed for order ' . $row['order_id']);
        }
    }
}

function impactshop_vote_purchase_increment_pool(string $quarter_key, int $donation_part): void
{
    if ($quarter_key === '' || $donation_part <= 0) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_quarters';
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET pool_amount = pool_amount + %d WHERE quarter_key = %s",
        $donation_part,
        $quarter_key
    ));
}

function impactshop_vote_purchase_add_stats_votes(string $pseudo_id, int $votes): void
{
    if ($pseudo_id === '' || $votes <= 0) {
        return;
    }
    if (!function_exists('impactshop_ads_watch_get_user_stats')) {
        return;
    }
    $stats = impactshop_ads_watch_get_user_stats($pseudo_id);
    $stats['total_votes'] = (int) ($stats['total_votes'] ?? 0) + $votes;

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_user_stats';
    $wpdb->update($table, [
        'total_votes' => $stats['total_votes'],
        'updated_at' => current_time('mysql', 1),
    ], ['pseudo_id' => $pseudo_id], ['%d','%s'], ['%s']);
}

function impactshop_vote_purchase_status(WP_REST_Request $request): WP_REST_Response
{
    $order_id = sanitize_text_field((string) ($request->get_param('order_id') ?? ''));
    if ($order_id === '') {
        return new WP_REST_Response(['error' => 'missing_order_id'], 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT order_id, ngo_slug, votes, amount_display, currency, donation_part, revenue_part, status, created_at, completed_at, is_company, donation_cert_status
         FROM {$table} WHERE order_id = %s",
        $order_id
    ), ARRAY_A);

    if (!$row) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    return new WP_REST_Response([
        'order_id' => $row['order_id'],
        'ngo_slug' => $row['ngo_slug'],
        'votes' => (int) $row['votes'],
        'amount' => (float) $row['amount_display'],
        'currency' => $row['currency'],
        'donation_part' => (int) $row['donation_part'],
        'revenue_part' => (int) $row['revenue_part'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'completed_at' => $row['completed_at'],
        'is_company' => (bool) $row['is_company'],
        'donation_certificate_status' => $row['donation_cert_status'],
    ], 200);
}

function impactshop_vote_purchase_history(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_vote_purchase_get_pseudo_id($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response(['orders' => []], 200);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT order_id, ngo_slug, votes, amount_display, currency, status, is_company, created_at
         FROM {$table} WHERE pseudo_id = %s ORDER BY created_at DESC LIMIT 50",
        $pseudo_id
    ), ARRAY_A);

    return new WP_REST_Response(['orders' => $rows], 200);
}

function impactshop_vote_purchase_saved_company_data(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_vote_purchase_get_pseudo_id($request);
    if ($pseudo_id === '') {
        return new WP_REST_Response(['company' => null], 200);
    }
    $data = get_option('impactshop_vote_purchase_company_' . $pseudo_id, []);
    return new WP_REST_Response(['company' => $data], 200);
}

function impactshop_vote_purchase_success(WP_REST_Request $request)
{
    $session_id = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
    $target = home_url('/impact-challenge/');
    $anchor = 'ads-watch-status-bar';

    if ($session_id !== '') {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_vote_purchases';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT order_id, return_url FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
            $session_id
        ), ARRAY_A);
        if ($row) {
            $target = $row['return_url'] ?: $target;
            $target = add_query_arg([
                'vp_status' => 'success',
                'order_id' => $row['order_id'],
            ], $target);
        }
    }

    if (strpos($target, '#') === false) {
        $target .= '#' . $anchor;
    }
    wp_redirect($target);
    exit;
}

function impactshop_vote_purchase_cancel(WP_REST_Request $request)
{
    $order_id = sanitize_text_field((string) ($request->get_param('order_id') ?? ''));
    $target = home_url('/impact-challenge/');
    $anchor = 'ads-watch-status-bar';
    if ($order_id !== '') {
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_vote_purchases';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT return_url FROM {$table} WHERE order_id = %s LIMIT 1",
            $order_id
        ), ARRAY_A);
        if ($row) {
            $target = $row['return_url'] ?: $target;
        }
        $target = add_query_arg([
            'vp_status' => 'cancel',
            'order_id' => $order_id,
        ], $target);
    }
    if (strpos($target, '#') === false) {
        $target .= '#' . $anchor;
    }
    wp_redirect($target);
    exit;
}

function impactshop_vote_purchase_register_admin_page(): void
{
    add_submenu_page(
        'edit.php?post_type=impact_sponsor_video',
        'Szavazat-vásárlások',
        'Szavazat-vásárlások',
        'manage_options',
        'impactshop-vote-purchases',
        'impactshop_vote_purchase_admin_page'
    );
}

function impactshop_vote_purchase_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_vote_purchases';
    $rows = $wpdb->get_results("SELECT order_id, ngo_slug, votes, amount_display, currency, status, is_company, created_at FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A);

    echo '<div class="wrap"><h1>Szavazat-vásárlások</h1>';
    if (!$rows) {
        echo '<p>Nincs adat.</p></div>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Order</th><th>NGO</th><th>Szavazat</th><th>Összeg</th><th>Státusz</th><th>Cég</th><th>Dátum</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['order_id']) . '</td>';
        echo '<td>' . esc_html($row['ngo_slug']) . '</td>';
        echo '<td>' . esc_html((int) $row['votes']) . '</td>';
        echo '<td>' . esc_html(number_format((float) $row['amount_display'], 2, ',', ' ')) . ' ' . esc_html(strtoupper($row['currency'])) . '</td>';
        echo '<td>' . esc_html($row['status']) . '</td>';
        echo '<td>' . ((int) $row['is_company'] === 1 ? 'igen' : 'nem') . '</td>';
        echo '<td>' . esc_html($row['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impactshop vote-purchase void', function($args, $assoc_args) {
        $order_id = isset($assoc_args['order_id']) ? (string) $assoc_args['order_id'] : '';
        if ($order_id === '') {
            WP_CLI::error('order_id hiányzik.');
        }
        global $wpdb;
        $table = $wpdb->prefix . 'impactshop_vote_purchases';
        $row = $wpdb->get_row($wpdb->prepare("SELECT order_id, status, donation_part, quarter_key FROM {$table} WHERE order_id = %s", $order_id), ARRAY_A);
        if (!$row) {
            WP_CLI::error('Order nem található.');
        }
        if ($row['status'] !== 'completed') {
            WP_CLI::error('Csak completed order voidolható.');
        }
        $wpdb->update($table, [
            'status' => 'voided',
            'voided_at' => current_time('mysql', 1),
        ], ['order_id' => $order_id], ['%s','%s'], ['%s']);
        impactshop_vote_purchase_increment_pool((string) $row['quarter_key'], -1 * (int) $row['donation_part']);
        WP_CLI::success('Order voidolva: ' . $order_id);
    });
}
