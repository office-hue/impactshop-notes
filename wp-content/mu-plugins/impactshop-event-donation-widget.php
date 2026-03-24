<?php
/**
 * Plugin Name: ImpactShop Event Donation Widget
 * Description: External embeddable donation campaign widget with Stripe checkout, realtime stats and optional company donation certificate flow.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IMPACTSHOP_EVENT_DONATION_VERSION', '1.3.0');
define('IMPACTSHOP_EVENT_DONATION_SCHEMA_VERSION', '1.1.0');
define('IMPACTSHOP_EVENT_DONATION_CRON_HOOK', 'impactshop_event_donation_cert_cron');

add_action('init', 'impactshop_event_donation_ensure_schema', 5);
add_action('rest_api_init', 'impactshop_event_donation_register_routes');
add_action('template_redirect', 'impactshop_event_donation_query_api_dispatch', 0);
add_action('template_redirect', 'impactshop_event_donation_embed_page_dispatch', 1);
add_action('init', 'impactshop_event_donation_schedule_cert_cron');
add_action(IMPACTSHOP_EVENT_DONATION_CRON_HOOK, 'impactshop_event_donation_process_cert_queue');
add_filter('cron_schedules', 'impactshop_event_donation_cron_schedules');
add_filter('allowed_http_origins', 'impactshop_event_donation_allowed_http_origins');
add_filter('allowed_redirect_hosts', 'impactshop_event_donation_allowed_redirect_hosts');
add_action('wp_enqueue_scripts', 'impactshop_event_donation_maybe_enqueue_runtime');
add_shortcode('impact_event_donation_widget', 'impactshop_event_donation_shortcode');

function impactshop_event_donation_is_configured(): bool
{
    return defined('IMPACT_STRIPE_SECRET_KEY')
        && defined('IMPACT_STRIPE_PUBLIC_KEY')
        && defined('IMPACT_STRIPE_WEBHOOK_SECRET')
        && defined('IMPACT_STRIPE_DEFAULT_CURRENCY')
        && IMPACT_STRIPE_SECRET_KEY !== ''
        && IMPACT_STRIPE_PUBLIC_KEY !== ''
        && IMPACT_STRIPE_WEBHOOK_SECRET !== '';
}

function impactshop_event_donation_webhook_secret(): string
{
    if (defined('IMPACT_EVENT_DONATION_STRIPE_WEBHOOK_SECRET') && IMPACT_EVENT_DONATION_STRIPE_WEBHOOK_SECRET !== '') {
        return (string) IMPACT_EVENT_DONATION_STRIPE_WEBHOOK_SECRET;
    }

    $optionSecret = (string) get_option('impactshop_event_donation_webhook_secret', '');
    if ($optionSecret !== '') {
        return $optionSecret;
    }

    return defined('IMPACT_STRIPE_WEBHOOK_SECRET') ? (string) IMPACT_STRIPE_WEBHOOK_SECRET : '';
}

function impactshop_event_donation_campaigns(): array
{
    $campaigns = [
        'jovonkvize-2026' => [
            'slug' => 'jovonkvize-2026',
            'title' => 'Jövőnk Vize jótékonysági kampány',
            'subtitle' => 'Sharity Adományszervező Alapítvány részére',
            'beneficiary_name' => 'Sharity Adományszervező Alapítvány',
            'description' => 'Egyszeri adománygyűjtés a Jövőnk Vize gálaest céljaiért.',
            'disclaimer' => '',
            'currency' => 'huf',
            'minimum_amount' => 500,
            'maximum_amount' => 3500000,
            'preset_amounts' => [10000, 25000, 50000, 100000, 250000],
            'goal_amount' => 15000000,
            'share_url' => 'https://jovonkvize.hu',
            'hero_url' => 'https://jovonkvize.hu',
            'success_return_url' => 'https://jovonkvize.hu/',
            'cancel_return_url' => 'https://jovonkvize.hu/',
            'allowed_origins' => [
                'https://jovonkvize.hu',
                'https://www.jovonkvize.hu',
                'https://wowapartments.hu',
                'https://www.wowapartments.hu',
                'https://app.sharity.hu',
                'https://www.app.sharity.hu',
            ],
            'theme' => [
                'bg_start' => '#060d2a',
                'bg_end' => '#0d2f77',
                'accent' => '#c69a5f',
                'accent_2' => '#f4ddae',
                'text' => '#f8f4ea',
            ],
            'certificate_signature_image_url' => 'https://app.sharity.hu/wp-content/uploads/2026/03/Kepernyofoto-2026-03-03-du.-9.53.49.png',
        ],
    ];

    return apply_filters('impactshop_event_donation_campaigns', $campaigns);
}

function impactshop_event_donation_get_campaign(string $slug): ?array
{
    $slug = sanitize_title($slug);
    $campaigns = impactshop_event_donation_campaigns();
    return isset($campaigns[$slug]) && is_array($campaigns[$slug]) ? $campaigns[$slug] : null;
}

function impactshop_event_donation_allowed_http_origins(array $origins): array
{
    $extra = [];
    foreach (impactshop_event_donation_campaigns() as $campaign) {
        if (!is_array($campaign) || empty($campaign['allowed_origins']) || !is_array($campaign['allowed_origins'])) {
            continue;
        }
        foreach ($campaign['allowed_origins'] as $origin) {
            $origin = esc_url_raw((string) $origin);
            if ($origin !== '') {
                $extra[] = rtrim($origin, '/');
            }
        }
    }
    return array_values(array_unique(array_merge($origins, $extra)));
}

function impactshop_event_donation_allowed_redirect_hosts(array $hosts): array
{
    foreach (impactshop_event_donation_campaigns() as $campaign) {
        foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
            $host = wp_parse_url((string) $origin, PHP_URL_HOST);
            if ($host) {
                $hosts[] = (string) $host;
            }
        }
    }

    return array_values(array_unique($hosts));
}

function impactshop_event_donation_zero_decimal_currencies(): array
{
    // Stripe charge amounts use minor units; HUF should be sent in 2-decimal minor units.
    return ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
}

function impactshop_event_donation_minor_multiplier(string $currency): int
{
    return in_array(strtolower($currency), impactshop_event_donation_zero_decimal_currencies(), true) ? 1 : 100;
}

function impactshop_event_donation_to_minor(float $amount, string $currency): int
{
    $multiplier = impactshop_event_donation_minor_multiplier($currency);
    return (int) round($amount * $multiplier, 0);
}

function impactshop_event_donation_from_minor(int $amountMinor, string $currency): float
{
    $multiplier = impactshop_event_donation_minor_multiplier($currency);
    if ($multiplier <= 1) {
        return (float) $amountMinor;
    }
    return (float) ($amountMinor / $multiplier);
}

function impactshop_event_donation_format_amount(float $amount, string $currency): string
{
    $currency = strtolower($currency);
    if ($currency === 'huf') {
        return number_format((int) round($amount, 0), 0, ',', ' ') . ' Ft';
    }
    return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($currency);
}

function impactshop_event_donation_client_ip(): string
{
    return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function impactshop_event_donation_request_origin(): string
{
    return rtrim(esc_url_raw((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
}

function impactshop_event_donation_origin_allowed(array $campaign): bool
{
    $origin = impactshop_event_donation_request_origin();
    $allowed = [];
    foreach ((array) ($campaign['allowed_origins'] ?? []) as $item) {
        $item = rtrim(esc_url_raw((string) $item), '/');
        if ($item !== '') {
            $allowed[] = $item;
        }
    }

    if ($origin !== '') {
        return in_array($origin, $allowed, true);
    }

    $referer = esc_url_raw((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return false;
    }

    $refererHost = strtolower((string) wp_parse_url($referer, PHP_URL_HOST));
    if ($refererHost === '') {
        return false;
    }

    foreach ($allowed as $allowedOrigin) {
        $allowedHost = strtolower((string) wp_parse_url($allowedOrigin, PHP_URL_HOST));
        if ($allowedHost !== '' && $allowedHost === $refererHost) {
            return true;
        }
    }

    return false;
}

function impactshop_event_donation_allowed_origin_hosts(array $campaign): array
{
    $hosts = [];

    foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
        $host = wp_parse_url((string) $origin, PHP_URL_HOST);
        if ($host) {
            $hosts[] = strtolower((string) $host);
        }
    }

    $homeHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if ($homeHost) {
        $hosts[] = strtolower((string) $homeHost);
    }

    return array_values(array_unique($hosts));
}

function impactshop_event_donation_sanitize_return_url(string $url, array $campaign): string
{
    $url = esc_url_raw($url);
    if ($url === '') {
        return '';
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return '';
    }

    $host = strtolower((string) $host);
    $allowedHosts = impactshop_event_donation_allowed_origin_hosts($campaign);

    return in_array($host, $allowedHosts, true) ? $url : '';
}

function impactshop_event_donation_rate_limit(string $scope, int $limit, int $window): array
{
    $ip = impactshop_event_donation_client_ip();
    $key = 'impactshop_event_donate_rl_' . md5($scope . '|' . $ip);
    $state = get_transient($key);
    if (!is_array($state)) {
        $state = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    $state['count'] = (int) $state['count'] + 1;
    set_transient($key, $state, $window);

    return [
        'limit' => $limit,
        'remaining' => max(0, $limit - (int) $state['count']),
        'reset' => (int) $state['reset'],
        'blocked' => ((int) $state['count']) > $limit,
    ];
}

function impactshop_event_donation_rate_headers(array $rate): array
{
    return [
        'X-RateLimit-Limit' => (string) ($rate['limit'] ?? 0),
        'X-RateLimit-Remaining' => (string) ($rate['remaining'] ?? 0),
        'X-RateLimit-Reset' => (string) ($rate['reset'] ?? 0),
    ];
}

function impactshop_event_donation_generate_id(): string
{
    return 'ED-' . gmdate('YmdHis') . '-' . wp_generate_password(6, false, false);
}

function impactshop_event_donation_generate_cert_id(): string
{
    $year = gmdate('Y');
    $key = 'impactshop_event_donation_cert_seq_' . $year;
    $seq = (int) get_option($key, 0) + 1;
    update_option($key, $seq, false);
    return sprintf('SHA-ADOMANY-%s-%04d', $year, $seq);
}

function impactshop_event_donation_currency_name(string $currency): string
{
    return strtolower($currency) === 'huf' ? 'forint' : strtoupper($currency);
}

function impactshop_event_donation_hu_number_to_words(int $number): string
{
    if ($number === 0) {
        return 'nulla';
    }

    $units = ['', 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc'];
    $teens = [
        10 => 'tíz', 11 => 'tizenegy', 12 => 'tizenkettő', 13 => 'tizenhárom', 14 => 'tizennégy',
        15 => 'tizenöt', 16 => 'tizenhat', 17 => 'tizenhét', 18 => 'tizennyolc', 19 => 'tizenkilenc'
    ];
    $tens = ['', '', 'húsz', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven'];

    $toWordsBelow1000 = static function (int $n) use ($units, $teens, $tens): string {
        $result = '';
        if ($n >= 100) {
            $hundreds = intdiv($n, 100);
            if ($hundreds === 1) {
                $result .= 'száz';
            } elseif ($hundreds === 2) {
                $result .= 'kétszáz';
            } else {
                $result .= ($units[$hundreds] ?? '') . 'száz';
            }
            $n %= 100;
        }
        if ($n >= 20) {
            $ten = intdiv($n, 10);
            $unit = $n % 10;
            if ($unit === 0) {
                return $result . ($ten === 2 ? 'húsz' : ($tens[$ten] ?? ''));
            }
            $result .= ($tens[$ten] ?? '');
            $n = $unit;
        }
        if ($n >= 10) {
            return $result . ($teens[$n] ?? '');
        }
        if ($n > 0) {
            return $result . ($units[$n] ?? '');
        }
        return $result;
    };

    if ($number >= 1000000) {
        $millions = intdiv($number, 1000000);
        $rest = $number % 1000000;
        $prefix = $millions === 1 ? 'egymillió' : $toWordsBelow1000($millions) . 'millió';
        return $rest > 0 ? $prefix . '-' . impactshop_event_donation_hu_number_to_words($rest) : $prefix;
    }

    if ($number >= 1000) {
        $thousands = intdiv($number, 1000);
        $rest = $number % 1000;
        $prefix = $thousands === 1 ? 'ezer' : $toWordsBelow1000($thousands) . 'ezer';
        return $rest > 0 ? $prefix . '-' . $toWordsBelow1000($rest) : $prefix;
    }

    return $toWordsBelow1000($number);
}

function impactshop_event_donation_format_date_parts(string $dateStr): array
{
    $ts = strtotime($dateStr);
    if (!$ts) {
        $ts = time();
    }
    $months = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április', 5 => 'május', 6 => 'június',
        7 => 'július', 8 => 'augusztus', 9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december'
    ];
    $monthNum = (int) gmdate('n', $ts);
    return [
        'year' => gmdate('Y', $ts),
        'month' => $months[$monthNum] ?? '',
        'day' => gmdate('j', $ts),
    ];
}

function impactshop_event_donation_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_donations';
}

function impactshop_event_donation_ensure_schema(): void
{
    $current = (string) get_option('impactshop_event_donation_schema_version', '');
    if ($current === IMPACTSHOP_EVENT_DONATION_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        donation_id VARCHAR(40) NOT NULL UNIQUE,
        campaign_slug VARCHAR(120) NOT NULL,
        status ENUM('pending','completed','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
        amount_minor INT UNSIGNED NOT NULL,
        amount_display DECIMAL(12,2) NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'huf',
        donor_name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        is_company TINYINT(1) NOT NULL DEFAULT 0,
        company_name VARCHAR(255) DEFAULT NULL,
        company_tax_id VARCHAR(64) DEFAULT NULL,
        company_address VARCHAR(500) DEFAULT NULL,
        request_certificate TINYINT(1) NOT NULL DEFAULT 0,
        gdpr_email_consent TINYINT(1) NOT NULL DEFAULT 0,
        ticket_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        selected_package VARCHAR(20) DEFAULT NULL,
        donation_cert_id VARCHAR(40) DEFAULT NULL,
        donation_cert_status ENUM('none','pending','sent','failed') NOT NULL DEFAULT 'none',
        donation_cert_sent_at DATETIME DEFAULT NULL,
        stripe_session_id VARCHAR(128) DEFAULT NULL,
        stripe_payment_intent VARCHAR(128) DEFAULT NULL,
        stripe_charge_id VARCHAR(128) DEFAULT NULL,
        source_origin VARCHAR(255) DEFAULT NULL,
        return_url VARCHAR(500) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(512) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        INDEX idx_campaign_status (campaign_slug, status),
        INDEX idx_campaign_completed (campaign_slug, completed_at),
        INDEX idx_stripe_session (stripe_session_id),
        INDEX idx_stripe_pi (stripe_payment_intent),
        INDEX idx_email (email),
        INDEX idx_cert_status (donation_cert_status)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('impactshop_event_donation_schema_version', IMPACTSHOP_EVENT_DONATION_SCHEMA_VERSION, false);
}

function impactshop_event_donation_register_routes(): void
{
    register_rest_route('impact/v1', '/event-campaigns/(?P<slug>[a-z0-9\-]+)/public', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_donation_public',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/(?P<slug>[a-z0-9\-]+)/stats', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_donation_stats',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/(?P<slug>[a-z0-9\-]+)/status', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_donation_status',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/(?P<slug>[a-z0-9\-]+)/checkout', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_donation_checkout',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/webhook', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_donation_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/success', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_donation_success',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-campaigns/cancel', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_donation_cancel',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_event_donation_query_api_dispatch(): void
{
    $action = sanitize_key((string) ($_REQUEST['impact_event_api'] ?? ''));
    if ($action === '') {
        return;
    }

    $slug = sanitize_title((string) ($_REQUEST['campaign'] ?? ''));
    $campaign = $slug !== '' ? impactshop_event_donation_get_campaign($slug) : null;

    if ($campaign) {
        impactshop_event_donation_send_cors_headers($campaign);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'OPTIONS') {
        status_header(204);
        exit;
    }

    $request = new WP_REST_Request($method, '/impact/v1/event-campaigns/' . $action);
    if ($slug !== '') {
        $request->set_param('slug', $slug);
    }

    $donationId = sanitize_text_field((string) ($_REQUEST['donation_id'] ?? ''));
    if ($donationId !== '') {
        $request->set_param('donation_id', $donationId);
    }

    $raw = (string) file_get_contents('php://input');
    if ($raw !== '') {
        $request->set_body($raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                $request->set_param((string) $key, $value);
            }
        }
    }

    switch ($action) {
        case 'public':
            impactshop_event_donation_emit_query_response(impactshop_event_donation_public($request));
            break;
        case 'stats':
            impactshop_event_donation_emit_query_response(impactshop_event_donation_stats($request));
            break;
        case 'status':
            impactshop_event_donation_emit_query_response(impactshop_event_donation_status($request));
            break;
        case 'checkout':
            if ($method !== 'POST') {
                impactshop_event_donation_emit_query_response(new WP_REST_Response(['error' => 'method_not_allowed'], 405));
            }
            impactshop_event_donation_emit_query_response(impactshop_event_donation_checkout($request));
            break;
        case 'webhook':
            if ($method !== 'POST') {
                impactshop_event_donation_emit_query_response(new WP_REST_Response(['error' => 'method_not_allowed'], 405));
            }
            impactshop_event_donation_emit_query_response(impactshop_event_donation_webhook());
            break;
        default:
            impactshop_event_donation_emit_query_response(new WP_REST_Response(['error' => 'not_found'], 404));
    }
}

function impactshop_event_donation_send_cors_headers(array $campaign): void
{
    $origin = impactshop_event_donation_request_origin();
    if ($origin === '' || !impactshop_event_donation_origin_allowed($campaign)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

function impactshop_event_donation_emit_query_response(WP_REST_Response $response): void
{
    foreach ((array) $response->get_headers() as $name => $value) {
        if (!headers_sent()) {
            header((string) $name . ': ' . (string) $value);
        }
    }

    nocache_headers();
    status_header($response->get_status());
    header('Content-Type: application/json; charset=' . get_bloginfo('charset'));
    echo wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function impactshop_event_donation_embed_page_dispatch(): void
{
    if ((string) ($_GET['impact_event_embed'] ?? '') !== '1') {
        return;
    }

    $slug = sanitize_title((string) ($_GET['campaign'] ?? 'jovonkvize-2026'));
    $campaign = impactshop_event_donation_get_campaign($slug);
    if (!$campaign) {
        status_header(404);
        echo 'Unknown campaign';
        exit;
    }

    status_header(200);
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    header_remove('X-Frame-Options');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $scriptSrc = esc_url(trailingslashit(home_url('/wp-content/mu-plugins')) . 'impactshop-event-donation-widget.js?v=' . rawurlencode(IMPACTSHOP_EVENT_DONATION_VERSION));
    $apiBase = esc_url(rest_url('impact/v1/event-campaigns'));
    $fallback = esc_url(home_url('/'));
    $campaignAttr = esc_attr($slug);

    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Jövőnk Vize adomány widget</title>';
    echo '<style>html,body{margin:0;padding:0;background:transparent}#impact-event-embed-root{padding:0}</style>';
    echo '</head><body>';
    echo '<div id="impact-event-embed-root" data-impact-campaign-widget data-campaign="' . $campaignAttr . '" data-api-base="' . $apiBase . '" data-fallback-api-base="' . $fallback . '" data-poll-ms="30000"></div>';
    echo '<script src="' . $scriptSrc . '" defer></script>';
    echo '</body></html>';
    exit;
}

function impactshop_event_donation_public(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_donation_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $stats = impactshop_event_donation_stats_payload($campaign);

    return new WP_REST_Response([
        'slug' => $campaign['slug'],
        'title' => $campaign['title'],
        'subtitle' => $campaign['subtitle'],
        'description' => $campaign['description'],
        'beneficiary_name' => $campaign['beneficiary_name'],
        'disclaimer' => $campaign['disclaimer'],
        'currency' => $campaign['currency'],
        'minimum_amount' => (int) $campaign['minimum_amount'],
        'maximum_amount' => (int) $campaign['maximum_amount'],
        'preset_amounts' => array_values(array_map('intval', (array) ($campaign['preset_amounts'] ?? []))),
        'goal_amount' => (int) ($campaign['goal_amount'] ?? 0),
        'share_url' => esc_url_raw((string) ($campaign['share_url'] ?? '')),
        'hero_url' => esc_url_raw((string) ($campaign['hero_url'] ?? '')),
        'theme' => (array) ($campaign['theme'] ?? []),
        'stats' => $stats,
    ], 200);
}

function impactshop_event_donation_stats_payload(array $campaign): array
{
    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $slug = sanitize_title((string) ($campaign['slug'] ?? ''));
    $currency = strtolower((string) ($campaign['currency'] ?? 'huf'));

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) AS supporters_count,
                COALESCE(SUM(amount_minor), 0) AS amount_minor_total,
                MAX(completed_at) AS last_completed_at
             FROM {$table}
             WHERE campaign_slug = %s
               AND status = 'completed'",
            $slug
        ),
        ARRAY_A
    );

    $supporters = (int) ($row['supporters_count'] ?? 0);
    $amountMinor = (int) ($row['amount_minor_total'] ?? 0);
    $total = impactshop_event_donation_from_minor($amountMinor, $currency);
    $avg = $supporters > 0 ? ($total / $supporters) : 0;
    $goal = (float) ($campaign['goal_amount'] ?? 0);
    $progress = ($goal > 0) ? min(100, round(($total / $goal) * 100, 2)) : 0;

    $breakdownRows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                COALESCE(selected_package, '') AS package,
                SUM(ticket_count)              AS tickets,
                COUNT(*)                       AS donations
             FROM {$table}
             WHERE campaign_slug = %s
               AND status = 'completed'
             GROUP BY selected_package",
            $slug
        ),
        ARRAY_A
    ) ?: [];
    $ticketBreakdown = [];
    $totalTickets = 0;
    foreach ($breakdownRows as $br) {
        $pkg = $br['package'] !== '' ? $br['package'] : 'unknown';
        $ticketBreakdown[$pkg] = [
            'tickets'   => (int) $br['tickets'],
            'donations' => (int) $br['donations'],
        ];
        $totalTickets += (int) $br['tickets'];
    }

    return [
        'currency' => $currency,
        'total_amount' => $total,
        'total_amount_minor' => $amountMinor,
        'total_amount_formatted' => impactshop_event_donation_format_amount($total, $currency),
        'supporters_count' => $supporters,
        'average_amount' => $avg,
        'average_amount_formatted' => impactshop_event_donation_format_amount($avg, $currency),
        'goal_amount' => $goal,
        'goal_amount_formatted' => impactshop_event_donation_format_amount($goal, $currency),
        'goal_progress_percent' => $progress,
        'last_completed_at' => (string) ($row['last_completed_at'] ?? ''),
        'updated_at' => gmdate('c'),
        'total_tickets' => $totalTickets,
        'ticket_breakdown' => $ticketBreakdown,
    ];
}

function impactshop_event_donation_stats(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_donation_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    return new WP_REST_Response(impactshop_event_donation_stats_payload($campaign), 200);
}

function impactshop_event_donation_status(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_donation_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $donationId = sanitize_text_field((string) $request->get_param('donation_id'));
    if ($donationId === '') {
        return new WP_REST_Response(['error' => 'missing_donation_id'], 400);
    }

    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT donation_id, campaign_slug, status, amount_display, currency, donation_cert_status, completed_at
             FROM {$table}
             WHERE campaign_slug = %s
               AND donation_id = %s
             LIMIT 1",
            $slug,
            $donationId
        ),
        ARRAY_A
    );

    if (!$row) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    return new WP_REST_Response([
        'donation_id' => (string) ($row['donation_id'] ?? ''),
        'status' => (string) ($row['status'] ?? 'pending'),
        'amount' => (float) ($row['amount_display'] ?? 0),
        'currency' => strtolower((string) ($row['currency'] ?? 'huf')),
        'amount_formatted' => impactshop_event_donation_format_amount((float) ($row['amount_display'] ?? 0), (string) ($row['currency'] ?? 'huf')),
        'donation_certificate_status' => (string) ($row['donation_cert_status'] ?? 'none'),
        'completed_at' => (string) ($row['completed_at'] ?? ''),
    ], 200);
}

function impactshop_event_donation_parse_checkout_payload(WP_REST_Request $request): array
{
    $params = (array) $request->get_json_params();
    if ($params) {
        return $params;
    }

    $body = (string) $request->get_body();
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return (array) $request->get_params();
}

function impactshop_event_donation_checkout(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_event_donation_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_donation_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    if (!impactshop_event_donation_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'invalid_origin'], 403);
    }

    $rate = impactshop_event_donation_rate_limit('checkout_' . $slug, 25, HOUR_IN_SECONDS);
    if (!empty($rate['blocked'])) {
        return new WP_REST_Response(['error' => 'rate_limited'], 429, impactshop_event_donation_rate_headers($rate));
    }

    $params = impactshop_event_donation_parse_checkout_payload($request);

    $consent = !empty($params['consent']);
    if (!$consent) {
        return new WP_REST_Response(['error' => 'missing_consent'], 400);
    }

    $currency = strtolower((string) ($campaign['currency'] ?? 'huf'));
    $amountInput = (float) ($params['amount'] ?? 0);
    $amountInput = round($amountInput, 2);

    $minAmount = (float) ($campaign['minimum_amount'] ?? 0);
    $maxAmount = (float) ($campaign['maximum_amount'] ?? 0);
    if ($amountInput < $minAmount || ($maxAmount > 0 && $amountInput > $maxAmount)) {
        return new WP_REST_Response([
            'error' => 'invalid_amount',
            'minimum_amount' => $minAmount,
            'maximum_amount' => $maxAmount,
        ], 400);
    }

    $donorName = sanitize_text_field((string) ($params['donor_name'] ?? ''));
    $email = sanitize_email((string) ($params['email'] ?? ''));
    $isCompany = !empty($params['is_company']);
    $requestCertificate = $isCompany && !empty($params['request_certificate']);
    $companyName = sanitize_text_field((string) ($params['company_name'] ?? ''));
    $companyTaxId = sanitize_text_field((string) ($params['company_tax_id'] ?? ''));
    $companyAddress = sanitize_text_field((string) ($params['company_address'] ?? ''));
    $gdprEmailConsent = !empty($params['gdpr_email_consent']);
    $ticketCount = max(0, (int) ($params['ticket_count'] ?? 0));
    $selectedPackage = sanitize_key((string) ($params['selected_package'] ?? ''));
    $selectedPackage = in_array($selectedPackage, ['silver', 'gold', 'platinum'], true) ? $selectedPackage : '';

    if ($email === '') {
        return new WP_REST_Response(['error' => 'missing_email'], 400);
    }

    if ($isCompany && (!$requestCertificate || $companyName === '' || $companyTaxId === '' || $companyAddress === '' || !$gdprEmailConsent)) {
        return new WP_REST_Response(['error' => 'missing_company_fields'], 400);
    }

    $amountMinor = impactshop_event_donation_to_minor($amountInput, $currency);
    if ($amountMinor <= 0) {
        return new WP_REST_Response(['error' => 'invalid_amount_minor'], 400);
    }

    $returnUrl = impactshop_event_donation_sanitize_return_url((string) ($params['return_url'] ?? ''), $campaign);
    if ($returnUrl === '') {
        $returnUrl = esc_url_raw((string) ($campaign['success_return_url'] ?? home_url('/')));
    }

    $donationId = impactshop_event_donation_generate_id();

    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $inserted = $wpdb->insert(
        $table,
        [
            'donation_id' => $donationId,
            'campaign_slug' => $slug,
            'status' => 'pending',
            'amount_minor' => $amountMinor,
            'amount_display' => $amountInput,
            'currency' => $currency,
            'donor_name' => $donorName !== '' ? $donorName : null,
            'email' => $email,
            'is_company' => $isCompany ? 1 : 0,
            'company_name' => $companyName !== '' ? $companyName : null,
            'company_tax_id' => $companyTaxId !== '' ? $companyTaxId : null,
            'company_address' => $companyAddress !== '' ? $companyAddress : null,
            'request_certificate' => $requestCertificate ? 1 : 0,
            'gdpr_email_consent' => $gdprEmailConsent ? 1 : 0,
            'ticket_count' => $ticketCount,
            'selected_package' => $selectedPackage !== '' ? $selectedPackage : null,
            'source_origin' => impactshop_event_donation_request_origin(),
            'return_url' => $returnUrl,
            'ip_address' => impactshop_event_donation_client_ip(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ],
        ['%s','%s','%s','%d','%f','%s','%s','%s','%d','%s','%s','%s','%d','%d','%d','%s','%s','%s','%s','%s']
    );

    if ($inserted === false) {
        error_log('[impactshop-event-donation] DB insert failed: ' . $wpdb->last_error);
        return new WP_REST_Response(['error' => 'db_error'], 500);
    }

    $session = impactshop_event_donation_create_checkout_session([
        'donation_id' => $donationId,
        'campaign' => $campaign,
        'amount_minor' => $amountMinor,
        'amount_display' => $amountInput,
        'currency' => $currency,
        'email' => $email,
        'donor_name' => $donorName,
        'is_company' => $isCompany,
        'request_certificate' => $requestCertificate,
        'ticket_count' => $ticketCount,
        'selected_package' => $selectedPackage,
    ]);

    if (!$session || empty($session['id']) || empty($session['url'])) {
        $wpdb->update($table, ['status' => 'failed'], ['donation_id' => $donationId], ['%s'], ['%s']);
        return new WP_REST_Response(['error' => 'stripe_failed'], 502);
    }

    $wpdb->update(
        $table,
        ['stripe_session_id' => (string) $session['id']],
        ['donation_id' => $donationId],
        ['%s'],
        ['%s']
    );

    $response = new WP_REST_Response([
        'donation_id' => $donationId,
        'stripe_checkout_url' => (string) $session['url'],
    ], 200);

    foreach (impactshop_event_donation_rate_headers($rate) as $header => $value) {
        $response->header($header, $value);
    }

    return $response;
}

function impactshop_event_donation_create_checkout_session(array $order): ?array
{
    $campaign = (array) ($order['campaign'] ?? []);
    $slug = sanitize_title((string) ($campaign['slug'] ?? 'campaign'));
    $currency = strtolower((string) ($order['currency'] ?? 'huf'));
    $amountMinor = (int) ($order['amount_minor'] ?? 0);
    $amountDisplay = (float) ($order['amount_display'] ?? 0);
    $donationId = (string) ($order['donation_id'] ?? '');

    if ($donationId === '' || $amountMinor <= 0) {
        return null;
    }

    $successUrl = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', rest_url('impact/v1/event-campaigns/success'));
    $cancelUrl = add_query_arg('donation_id', rawurlencode($donationId), rest_url('impact/v1/event-campaigns/cancel'));

    $productName = sprintf('%s – adomány', (string) ($campaign['title'] ?? 'Sharity kampány'));
    $productDescription = (string) ($campaign['subtitle'] ?? 'Egyszeri adomány');

    $payload = [
        'mode' => 'payment',
        'payment_method_types[0]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][unit_amount]' => $amountMinor,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDescription,
        'line_items[0][quantity]' => 1,
        'submit_type' => 'donate',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata[event_donation_id]' => $donationId,
        'metadata[campaign_slug]' => $slug,
        'metadata[flow]' => 'event_donation',
        'metadata[request_certificate]' => !empty($order['request_certificate']) ? '1' : '0',
        'metadata[is_company]' => !empty($order['is_company']) ? '1' : '0',
        'metadata[amount_display]' => (string) $amountDisplay,
        'metadata[currency]' => $currency,
        'metadata[ticket_count]' => (string) ((int) ($order['ticket_count'] ?? 0)),
        'metadata[selected_package]' => (string) ($order['selected_package'] ?? ''),
    ];

    $customerName = sanitize_text_field((string) ($order['donor_name'] ?? ''));
    if ($customerName !== '') {
        $payload['customer_creation'] = 'always';
        $payload['metadata[donor_name]'] = $customerName;
    }

    $email = sanitize_email((string) ($order['email'] ?? ''));
    if ($email !== '') {
        $payload['customer_email'] = $email;
    }

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
        ],
        'body' => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-event-donation] Stripe checkout request failed: ' . $response->get_error_message());
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        $requestId = (string) wp_remote_retrieve_header($response, 'request-id');
        error_log('[impactshop-event-donation] Stripe checkout response error: code=' . $code . ' request_id=' . $requestId . ' body=' . substr($body, 0, 500));
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id']) || empty($data['url'])) {
        return null;
    }

    return [
        'id' => (string) $data['id'],
        'url' => (string) $data['url'],
    ];
}

function impactshop_event_donation_webhook(): WP_REST_Response
{
    if (!impactshop_event_donation_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload = (string) file_get_contents('php://input');
    $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    $secret = impactshop_event_donation_webhook_secret();
    if ($secret === '' || !impactshop_event_donation_verify_stripe_signature($payload, $sigHeader, $secret)) {
        return new WP_REST_Response(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }

    $eventType = (string) ($event['type'] ?? '');
    $object = (array) (($event['data']['object'] ?? []) ?: []);

    if ($eventType === 'checkout.session.completed') {
        impactshop_event_donation_maybe_fulfill_from_session($object);
    } elseif ($eventType === 'checkout.session.expired') {
        impactshop_event_donation_mark_expired_from_session($object);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_event_donation_verify_stripe_signature(string $payload, string $sigHeader, string $secret): bool
{
    if ($payload === '' || $sigHeader === '' || $secret === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $sigHeader) as $item) {
        $pair = explode('=', trim($item), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }

    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }

    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
    foreach (explode(' ', str_replace(',', ' ', (string) $parts['v1'])) as $signature) {
        if ($signature !== '' && hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function impactshop_event_donation_maybe_fulfill_from_session(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $donationId = sanitize_text_field((string) ($metadata['event_donation_id'] ?? ''));
    if ($donationId === '') {
        return;
    }

    impactshop_event_donation_fulfill($donationId, [
        'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
    ]);
}

function impactshop_event_donation_mark_expired_from_session(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $donationId = sanitize_text_field((string) ($metadata['event_donation_id'] ?? ''));
    if ($donationId === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $wpdb->update(
        $table,
        ['status' => 'expired'],
        ['donation_id' => $donationId, 'status' => 'pending'],
        ['%s'],
        ['%s', '%s']
    );
}

function impactshop_event_donation_fulfill(string $donationId, array $stripeData = []): void
{
    if ($donationId === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_donation_table_name();

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE donation_id = %s LIMIT 1", $donationId), ARRAY_A);
    if (!$row || ($row['status'] ?? '') === 'completed') {
        return;
    }

    $wpdb->query('START TRANSACTION');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE donation_id = %s FOR UPDATE", $donationId), ARRAY_A);
    if (!$row || ($row['status'] ?? '') === 'completed') {
        $wpdb->query('ROLLBACK');
        return;
    }

    $update = [
        'status' => 'completed',
        'stripe_session_id' => sanitize_text_field((string) ($stripeData['stripe_session_id'] ?? ($row['stripe_session_id'] ?? ''))),
        'stripe_payment_intent' => sanitize_text_field((string) ($stripeData['stripe_payment_intent'] ?? ($row['stripe_payment_intent'] ?? ''))),
        'completed_at' => current_time('mysql', 1),
    ];
    $formats = ['%s', '%s', '%s', '%s'];

    $requestCertificate = (int) ($row['request_certificate'] ?? 0) === 1;
    $hasEmail = sanitize_email((string) ($row['email'] ?? '')) !== '';
    if ($requestCertificate && $hasEmail) {
        $update['donation_cert_status'] = 'pending';
        $update['donation_cert_id'] = impactshop_event_donation_generate_cert_id();
        $formats[] = '%s';
        $formats[] = '%s';
    }

    $ok = $wpdb->update($table, $update, ['donation_id' => $donationId], $formats, ['%s']);
    if ($ok === false) {
        $wpdb->query('ROLLBACK');
        return;
    }

    $wpdb->query('COMMIT');

    // Transaction notification to admins.
    impactshop_event_donation_send_transaction_notification(
        array_merge((array) $row, ['status' => 'completed', 'completed_at' => $update['completed_at']])
    );

    // Primary path: send certificate immediately after successful payment.
    if ($requestCertificate && $hasEmail) {
        impactshop_event_donation_send_certificate_for_donation($donationId);
    }
}

function impactshop_event_donation_fetch_stripe_session(string $sessionId): ?array
{
    $sessionId = sanitize_text_field($sessionId);
    if ($sessionId === '' || !impactshop_event_donation_is_configured()) {
        return null;
    }

    $url = 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId);
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return null;
    }

    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    return is_array($data) ? $data : null;
}

function impactshop_event_donation_success(WP_REST_Request $request)
{
    $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
    $donationId = '';

    if ($sessionId !== '') {
        global $wpdb;
        $table = impactshop_event_donation_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT donation_id FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
                $sessionId
            ),
            ARRAY_A
        );

        if ($row && !empty($row['donation_id'])) {
            $donationId = sanitize_text_field((string) $row['donation_id']);
            impactshop_event_donation_fulfill($donationId, ['stripe_session_id' => $sessionId]);
        } else {
            $session = impactshop_event_donation_fetch_stripe_session($sessionId);
            if (is_array($session)) {
                $metadata = (array) ($session['metadata'] ?? []);
                $donationId = sanitize_text_field((string) ($metadata['event_donation_id'] ?? ''));
                if ($donationId !== '') {
                    impactshop_event_donation_fulfill($donationId, [
                        'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
                        'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
                    ]);
                }
            }
        }
    }

    return impactshop_event_donation_redirect_result('success', $donationId);
}

function impactshop_event_donation_cancel(WP_REST_Request $request)
{
    $donationId = sanitize_text_field((string) $request->get_param('donation_id'));

    if ($donationId !== '') {
        global $wpdb;
        $table = impactshop_event_donation_table_name();
        $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['donation_id' => $donationId, 'status' => 'pending'],
            ['%s'],
            ['%s', '%s']
        );
    }

    return impactshop_event_donation_redirect_result('cancel', $donationId);
}

function impactshop_event_donation_redirect_result(string $status, string $donationId = '')
{
    global $wpdb;
    $table = impactshop_event_donation_table_name();

    $row = null;
    if ($donationId !== '') {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT campaign_slug, return_url FROM {$table} WHERE donation_id = %s LIMIT 1",
                $donationId
            ),
            ARRAY_A
        );
    }

    $campaignSlug = sanitize_title((string) ($row['campaign_slug'] ?? 'jovonkvize-2026'));
    $campaign = impactshop_event_donation_get_campaign($campaignSlug);

    $fallback = $status === 'success'
        ? (string) (($campaign['success_return_url'] ?? home_url('/')))
        : (string) (($campaign['cancel_return_url'] ?? home_url('/')));

    $returnUrl = esc_url_raw((string) ($row['return_url'] ?? $fallback));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $redirect = add_query_arg([
        'ed_status' => $status,
        'ed_campaign' => $campaignSlug,
        'ed_donation_id' => $donationId,
    ], $returnUrl);

    wp_safe_redirect($redirect, 302);
    exit;
}

function impactshop_event_donation_cron_schedules(array $schedules): array
{
    if (!isset($schedules['impactshop_10min'])) {
        $schedules['impactshop_10min'] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display' => 'Every 10 Minutes',
        ];
    }

    return $schedules;
}

function impactshop_event_donation_schedule_cert_cron(): void
{
    if (!wp_next_scheduled(IMPACTSHOP_EVENT_DONATION_CRON_HOOK)) {
        wp_schedule_event(time() + 180, 'impactshop_10min', IMPACTSHOP_EVENT_DONATION_CRON_HOOK);
    }
}

function impactshop_event_donation_process_cert_queue(): void
{
    global $wpdb;
    $table = impactshop_event_donation_table_name();

    $rows = $wpdb->get_results(
        "SELECT donation_id, campaign_slug, email, company_name, company_tax_id, company_address, amount_display, currency, completed_at, donation_cert_id
         FROM {$table}
         WHERE status = 'completed'
           AND is_company = 1
           AND request_certificate = 1
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
        $donationId = (string) ($row['donation_id'] ?? '');
        if ($donationId !== '') {
            impactshop_event_donation_send_certificate_for_donation($donationId);
        }
    }
}

function impactshop_event_donation_certificate_html(array $row, string $campaignTitle, string $certId, string $completedAt, string $amountFormatted, string $currency, string $amountWords, string $currencyName): string
{
    $dateParts = impactshop_event_donation_format_date_parts($completedAt);

    $companyName = esc_html((string) ($row['company_name'] ?? ''));
    $companyAddress = esc_html((string) ($row['company_address'] ?? ''));
    $companyTaxId = esc_html((string) ($row['company_tax_id'] ?? ''));
    $campaignTitleEsc = esc_html($campaignTitle);
    $certIdEsc = esc_html($certId);
    $donationIdEsc = esc_html((string) ($row['donation_id'] ?? ''));
    $completedAtEsc = esc_html($completedAt);
    $currencyEsc = esc_html(strtoupper($currency));

    $signatureDataUri = (string) ($row['_signature_data_uri'] ?? '');
    $signatureHtml = $signatureDataUri !== ''
        ? '<p style="margin:10px 0 6px"><img src="' . esc_attr($signatureDataUri) . '" style="height:96px;max-width:360px" alt="aláírás"></p>'
        : '';

    return '<!doctype html><html><head><meta charset="UTF-8">'
        . '<style>@page{margin:14mm 12mm}body{font-family:DejaVu Sans,sans-serif;font-size:10.6px;line-height:1.28;color:#111;text-align:justify}h1{font-size:17px;margin:0 0 8px;text-align:center}p{margin:0 0 5px}.block{margin:7px 0;page-break-inside:avoid}.sign{margin-top:12px;text-align:left;page-break-inside:avoid}</style>'
        . '</head><body>'
        . '<h1>NYILATKOZAT</h1>'
        . '<p>nem magánszemély adózó részére</p>'
        . '<p>' . esc_html($dateParts['year']) . '. évi</p>'
        . '<p>nem közhasznú jogállású szervezetnek adott támogatásról</p>'
        . '<div class="block"><p>1. A pénzbeli juttatás jogosultjának (támogatott)</p>'
        . '<p>1.1 megnevezése: Sharity Adományszervező Alapítvány</p>'
        . '<p>1.2 székhelye: 7090 Tamási, Petőfi Sándor u. 12. Magyarország</p>'
        . '<p>1.3 adószáma: 19329006-1-17, HU19329006</p></div>'
        . '<div class="block"><p>2. A pénzbeli juttatással támogatott cél:</p><p>' . $campaignTitleEsc . '</p></div>'
        . '<div class="block"><p>3. A támogatás összege: ' . esc_html($amountFormatted) . ' ' . $currencyEsc . '</p>'
        . '<p>(azaz ' . esc_html($amountWords) . ' ' . esc_html($currencyName) . ')</p></div>'
        . '<div class="block"><p>4. A támogatást nyújtó</p>'
        . '<p>4.1 megnevezése: ' . $companyName . '</p>'
        . '<p>4.2 székhelye: ' . $companyAddress . '</p>'
        . '<p>4.3 adószáma: ' . $companyTaxId . '</p></div>'
        . '<div class="block"><p>Nyilatkozom, hogy szervezetünk a támogatást bevételként elszámolta és a juttatás nyújtásának adóévében a szervezetünk adózás előtti eredménye, adóalapja e juttatás nélkül is pozitív lenne, és nem lenne veszteséges.</p></div>'
        . '<div class="block"><p>Nyilatkozom, hogy a támogatást a fent megnevezett támogató a Sharity Mobile Application Zrt. üzemeltetésében álló Sharity megnevezésű, okostelefonokra telepíthető és azokon futtatható programon („Sharity”) keresztül juttatta szervezetünk részére.</p></div>'
        . '<div class="block"><p>Kelt: Tamási, ' . esc_html($dateParts['year']) . '. ' . esc_html($dateParts['month']) . ' ' . esc_html($dateParts['day']) . '.</p></div>'
        . '<div class="sign"><p>P. H.</p>' . $signatureHtml . '<p>.....................................................</p><p>dr. Bujdosó Arnold</p><p>igazgatósági tagja</p><p>Sharity Zrt.</p></div>'
        . '<div class="block"><p>Igazolás azonosító: ' . $certIdEsc . '</p><p>Adomány azonosító: ' . $donationIdEsc . '</p><p>Dátum: ' . $completedAtEsc . '</p></div>'
        . '</body></html>';
}

function impactshop_event_donation_signature_data_uri(array $campaign): string
{
    $url = esc_url_raw((string) ($campaign['certificate_signature_image_url'] ?? ''));
    if ($url === '') {
        return '';
    }

    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        return '';
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return '';
    }

    $body = (string) wp_remote_retrieve_body($response);
    if ($body === '') {
        return '';
    }

    $contentType = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
    if (strpos($contentType, 'image/') !== 0) {
        $contentType = 'image/png';
    } else {
        $contentType = trim(explode(';', $contentType)[0]);
    }

    return 'data:' . $contentType . ';base64,' . base64_encode($body);
}

function impactshop_event_donation_certificate_pdf_attachment(string $html, string $certId): string
{
    $autoload = trailingslashit(WPMU_PLUGIN_DIR) . 'vendor/autoload.php';
    if (!class_exists('\\Dompdf\\Dompdf') && file_exists($autoload)) {
        require_once $autoload;
    }
    if (!class_exists('\\Dompdf\\Dompdf')) {
        return '';
    }

    try {
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf = $dompdf->output();
        if ($pdf === '') {
            return '';
        }

        $safeName = preg_replace('/[^A-Za-z0-9\\-_]/', '_', $certId);
        $tmpBase = tempnam(sys_get_temp_dir(), 'sha_cert_');
        if (!$tmpBase) {
            return '';
        }
        $file = $tmpBase . '-adomanyigazolas-' . $safeName . '.pdf';
        @rename($tmpBase, $file);
        if (file_put_contents($file, $pdf) === false) {
            return '';
        }
        return $file;
    } catch (Throwable $e) {
        error_log('[impactshop-event-donation] PDF generation failed: ' . $e->getMessage());
        return '';
    }
}

function impactshop_event_donation_send_certificate_for_donation(string $donationId): bool
{
    $donationId = sanitize_text_field($donationId);
    if ($donationId === '') {
        return false;
    }

    global $wpdb;
    $table = impactshop_event_donation_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT donation_id, campaign_slug, email, company_name, company_tax_id, company_address, amount_display, currency, completed_at, donation_cert_id, donation_cert_status
             FROM {$table}
             WHERE donation_id = %s
             LIMIT 1",
            $donationId
        ),
        ARRAY_A
    );

    if (!$row) {
        return false;
    }

    $status = (string) ($row['donation_cert_status'] ?? '');
    if ($status === 'sent') {
        return true;
    }

    $email = sanitize_email((string) ($row['email'] ?? ''));
    if ($email === '') {
        return false;
    }

    $campaign = impactshop_event_donation_get_campaign((string) ($row['campaign_slug'] ?? ''));
    $campaignTitle = (string) ($campaign['title'] ?? 'Jótékonysági kampány');
    $certId = sanitize_text_field((string) ($row['donation_cert_id'] ?? impactshop_event_donation_generate_cert_id()));
    $completedAt = (string) ($row['completed_at'] ?? current_time('mysql', 1));

    $amount = (float) ($row['amount_display'] ?? 0);
    $currency = strtolower((string) ($row['currency'] ?? 'huf'));
    $amountFormatted = number_format($amount, 2, ',', ' ');
    $amountNumber = (int) round($amount, 0);
    $amountWords = impactshop_event_donation_hu_number_to_words($amountNumber);
    $currencyName = impactshop_event_donation_currency_name($currency);
    $subject = 'Adományigazolás – ' . $campaignTitle . ' – ' . $certId;
    $template = "NYILATKOZAT\n"
        . "nem magánszemély adózó részére\n"
        . impactshop_event_donation_format_date_parts($completedAt)['year'] . ". évi\n"
        . "nem közhasznú jogállású szervezetnek adott támogatásról\n\n"
        . "1. A pénzbeli juttatás jogosultjának (támogatott)\n"
        . "   1.1 megnevezése: Sharity Adományszervező Alapítvány\n"
        . "   1.2 székhelye: 7090 Tamási, Petőfi Sándor u. 12. Magyarország\n"
        . "   1.3 adószáma: 19329006-1-17, HU19329006\n\n"
        . "2. A pénzbeli juttatással támogatott cél:\n"
        . "   {$campaignTitle}\n\n"
        . "3. A támogatás összege: {$amountFormatted} " . strtoupper($currency) . "\n"
        . "   (azaz {$amountWords} {$currencyName})\n\n"
        . "4. A támogatást nyújtó\n"
        . "   4.1 megnevezése: " . (string) ($row['company_name'] ?? '') . "\n"
        . "   4.2 székhelye: " . (string) ($row['company_address'] ?? '') . "\n"
        . "   4.3 adószáma: " . (string) ($row['company_tax_id'] ?? '') . "\n\n"
        . "Nyilatkozom, hogy szervezetünk a támogatást bevételként\n"
        . "elszámolta és a juttatás nyújtásának adóévében a szervezetünk\n"
        . "adózás előtti eredménye, adóalapja e juttatás nélkül is pozitív\n"
        . "lenne, és nem lenne veszteséges.\n\n"
        . "Nyilatkozom, hogy a támogatást a fent megnevezett támogató\n"
        . "a Sharity Mobile Application Zrt. üzemeltetésében álló Sharity\n"
        . "megnevezésű, okostelefonokra telepíthető és azokon futtatható\n"
        . "programon („Sharity\") keresztül juttatta szervezetünk részére.\n\n"
        . "Kelt: Tamási, " . impactshop_event_donation_format_date_parts($completedAt)['year'] . '. ' . impactshop_event_donation_format_date_parts($completedAt)['month'] . ' ' . impactshop_event_donation_format_date_parts($completedAt)['day'] . ".\n\n"
        . "P. H.\n\n"
        . ".....................................................\n"
        . "dr. Bujdosó Arnold\n"
        . "igazgatósági tagja\n"
        . "Sharity Zrt.\n\n"
        . "Igazolás azonosító: {$certId}\n"
        . "Adomány azonosító: {$donationId}\n"
        . "Dátum: {$completedAt}\n";

    $row['_signature_data_uri'] = impactshop_event_donation_signature_data_uri($campaign);
    $html = impactshop_event_donation_certificate_html($row, $campaignTitle, $certId, $completedAt, $amountFormatted, $currency, $amountWords, $currencyName);
    $pdfAttachment = impactshop_event_donation_certificate_pdf_attachment($html, $certId);
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Sharity Impact <office@sharity.hu>',
        'Reply-To: Sharity Impact <office@sharity.hu>',
    ];
    $attachments = $pdfAttachment !== '' ? [$pdfAttachment] : [];

    $headers[] = 'Bcc: bujdoso.arnold@bujdosoiroda.com';
    $sent = wp_mail([$email, 'office@sharity.hu'], $subject, $template, $headers, $attachments);
    if ($pdfAttachment !== '' && file_exists($pdfAttachment)) {
        @unlink($pdfAttachment);
    }
    if ($sent) {
        $wpdb->update(
            $table,
            [
                'donation_cert_status' => 'sent',
                'donation_cert_id' => $certId,
                'donation_cert_sent_at' => current_time('mysql', 1),
                'notes' => null,
            ],
            ['donation_id' => $donationId],
            ['%s', '%s', '%s', '%s'],
            ['%s']
        );
        return true;
    }

    $wpdb->update(
        $table,
        [
            'donation_cert_status' => 'failed',
            'notes' => 'Certificate email send failed at ' . current_time('mysql', 1),
        ],
        ['donation_id' => $donationId],
        ['%s', '%s'],
        ['%s']
    );
    error_log('[impactshop-event-donation] donation cert send failed for donation ' . $donationId);
    return false;
}

function impactshop_event_donation_send_transaction_notification(array $row): void
{
    $emails = ['bujdoso.arnold@bujdosoiroda.com', 'koncz.veronika@mielemed.hu'];
    $amount = (float) ($row['amount_display'] ?? 0);
    $currency = strtolower((string) ($row['currency'] ?? 'huf'));
    $email = (string) ($row['email'] ?? '');
    $donorName = (string) ($row['donor_name'] ?? '');
    $donationId = (string) ($row['donation_id'] ?? '');
    $campaignSlug = (string) ($row['campaign_slug'] ?? '');
    $completedAt = (string) ($row['completed_at'] ?? '');
    $isCompany = (int) ($row['is_company'] ?? 0) === 1;
    $requestCert = (int) ($row['request_certificate'] ?? 0) === 1;
    $amountFormatted = impactshop_event_donation_format_amount($amount, $currency);

    $subject = '[Sharity] Új adomány: ' . $amountFormatted . ' – ' . $campaignSlug;
    $body = "Új teljesített adomány érkezett:\n\n"
        . "Összeg: {$amountFormatted}\n"
        . "E-mail cím: {$email}\n"
        . "Adományozó neve: " . ($donorName ?: '(nem megadott)') . "\n"
        . "Kampány: {$campaignSlug}\n"
        . "Adomány azonosító: {$donationId}\n"
        . "Teljesítés dátuma: {$completedAt}\n";

    if ($isCompany && $requestCert) {
        $body .= "\n--- Adományigazolás adatok (cég) ---\n"
            . "Cégnév: " . (string) ($row['company_name'] ?? '') . "\n"
            . "Adószám: " . (string) ($row['company_tax_id'] ?? '') . "\n"
            . "Székhely: " . (string) ($row['company_address'] ?? '') . "\n";
    }

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Sharity Impact <office@sharity.hu>',
    ];

    wp_mail($emails, $subject, $body, $headers);
}

function impactshop_event_donation_maybe_enqueue_runtime(): void
{
    if (is_admin()) {
        return;
    }

    wp_register_script(
        'impactshop-event-donation-widget',
        trailingslashit(WPMU_PLUGIN_URL) . 'impactshop-event-donation-widget.js',
        [],
        IMPACTSHOP_EVENT_DONATION_VERSION,
        true
    );
}

function impactshop_event_donation_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'campaign' => 'jovonkvize-2026',
        'api_base' => rest_url('impact/v1/event-campaigns'),
        'fallback_api_base' => home_url('/'),
        'mode' => 'compact',
    ], $atts, 'impact_event_donation_widget');

    $campaign = sanitize_title((string) $atts['campaign']);
    if (!impactshop_event_donation_get_campaign($campaign)) {
        return '';
    }

    wp_enqueue_script('impactshop-event-donation-widget');

    $id = 'impact-event-widget-' . wp_generate_password(6, false, false);
    $apiBase = esc_url_raw((string) $atts['api_base']);
    $fallbackApiBase = esc_url_raw((string) $atts['fallback_api_base']);
    $mode = sanitize_key((string) $atts['mode']);

    return sprintf(
        '<div id="%1$s" data-impact-campaign-widget data-campaign="%2$s" data-api-base="%3$s" data-fallback-api-base="%4$s" data-mode="%5$s"></div>',
        esc_attr($id),
        esc_attr($campaign),
        esc_attr($apiBase),
        esc_attr($fallbackApiBase),
        esc_attr($mode)
    );
}

// ─── WP-CLI ───────────────────────────────────────────────────────────────────

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Commands for the ImpactShop event-donation widget.
     *
     * ## EXAMPLES
     *
     *   wp impactshop event-donation stats jovonkvize-2026
     */
    class ImpactShop_Event_Donation_CLI {

        /**
         * Display ticket and donation stats for a campaign.
         *
         * ## OPTIONS
         *
         * <slug>
         * : Campaign slug (e.g. jovonkvize-2026)
         *
         * [--format=<format>]
         * : Output format: table, json, csv. Default: table.
         *
         * ## EXAMPLES
         *
         *   wp impactshop event-donation stats jovonkvize-2026
         *   wp impactshop event-donation stats jovonkvize-2026 --format=json
         *
         * @when after_wp_load
         */
        public function stats(array $args, array $assoc_args): void {
            $slug = sanitize_title((string) ($args[0] ?? ''));
            if (!$slug) {
                WP_CLI::error('Hiányzó kampány slug.');
            }

            $campaign = impactshop_event_donation_get_campaign($slug);
            if (!$campaign) {
                WP_CLI::error("Nem található kampány: {$slug}");
            }

            $payload = impactshop_event_donation_stats_payload($campaign);
            $format  = $assoc_args['format'] ?? 'table';

            if ($format === 'json') {
                WP_CLI::line(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return;
            }

            // Summary table
            $summary = [
                ['Mező', 'Érték'],
                ['Támogatók száma',     $payload['supporters_count']],
                ['Összes összeg',       $payload['total_amount_formatted']],
                ['Átlag adomány',       $payload['average_amount_formatted']],
                ['Cél előrehaladás',    $payload['goal_progress_percent'] . '%'],
                ['Összes jegy',         $payload['total_tickets']],
                ['Utolsó adomány',      $payload['last_completed_at']],
            ];
            \WP_CLI\Utils\format_items('table', array_slice($summary, 1), ['Mező', 'Érték']);

            // Ticket breakdown
            if (!empty($payload['ticket_breakdown'])) {
                WP_CLI::line('');
                WP_CLI::line('Jegy breakdown (csomag szerinti bontás):');
                $rows = [];
                foreach ($payload['ticket_breakdown'] as $pkg => $data) {
                    $rows[] = [
                        'Csomag'     => $pkg,
                        'Jegyek'     => $data['tickets'],
                        'Adományok'  => $data['donations'],
                    ];
                }
                \WP_CLI\Utils\format_items($format === 'csv' ? 'csv' : 'table', $rows, ['Csomag', 'Jegyek', 'Adományok']);
            }
        }
    }

    WP_CLI::add_command('impactshop event-donation', 'ImpactShop_Event_Donation_CLI');
}
