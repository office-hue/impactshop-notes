<?php
/**
 * Plugin Name: ImpactShop Event Auction Widget
 * Description: Embeddable auction widget scaffold for the Jovonk Vize x Miele gala flow.
 * Version: 0.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IMPACTSHOP_EVENT_AUCTION_VERSION', '0.2.0');
define('IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION', '0.2.0');
define('IMPACTSHOP_EVENT_AUCTION_SESSION_TTL', 30 * MINUTE_IN_SECONDS);
define('IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL', 4 * HOUR_IN_SECONDS);

add_action('init', 'impactshop_event_auction_ensure_schema', 5);
add_action('rest_api_init', 'impactshop_event_auction_register_routes');
add_action('template_redirect', 'impactshop_event_auction_query_api_dispatch', 0);
add_action('template_redirect', 'impactshop_event_auction_embed_page_dispatch', 1);
add_filter('allowed_http_origins', 'impactshop_event_auction_allowed_http_origins');
add_filter('allowed_redirect_hosts', 'impactshop_event_auction_allowed_redirect_hosts');
add_shortcode('impact_event_auction_widget', 'impactshop_event_auction_shortcode');

function impactshop_event_auction_campaigns(): array
{
    $baseCampaign = function_exists('impactshop_event_donation_get_campaign')
        ? impactshop_event_donation_get_campaign('jovonkvize-2026')
        : null;

    $allowedOrigins = [
        'https://jovonkvize.hu',
        'https://www.jovonkvize.hu',
        'https://wowapartments.hu',
        'https://www.wowapartments.hu',
        'https://app.sharity.hu',
        'https://www.app.sharity.hu',
    ];

    if (is_array($baseCampaign) && !empty($baseCampaign['allowed_origins']) && is_array($baseCampaign['allowed_origins'])) {
        $allowedOrigins = array_values(array_unique(array_map(
            static fn($origin) => rtrim(esc_url_raw((string) $origin), '/'),
            $baseCampaign['allowed_origins']
        )));
    }

    $theme = [
        'bg_start' => '#060d2a',
        'bg_end' => '#0d2f77',
        'accent' => '#c69a5f',
        'accent_2' => '#f4ddae',
        'text' => '#f8f4ea',
    ];

    if (is_array($baseCampaign) && !empty($baseCampaign['theme']) && is_array($baseCampaign['theme'])) {
        $theme = array_merge($theme, $baseCampaign['theme']);
    }

    $campaigns = [
        'jovonkvize-2026' => [
            'slug' => 'jovonkvize-2026',
            'auction_slug' => 'jovonkvize-miele-gala-2026',
            'title' => 'Jovonk Vize gala aukcio',
            'subtitle' => 'Miele mutargyak es kulonleges felajanlasok',
            'beneficiary_name' => 'Sharity Adomanyszervezo Alapitvany',
            'description' => 'Additiv auction modul a Jovonk Vize x Miele gala lane-hez. A bidder regisztracio, a licit write lane, az admin close es a winner-payment backend mar bekotve, az admin UI es a kommunikacios lane kulon fazisban kotodik be.',
            'currency' => 'huf',
            'goal_amount' => 15000000,
            'share_url' => 'https://jovonkvize.hu',
            'hero_url' => 'https://jovonkvize.hu',
            'success_return_url' => 'https://jovonkvize.hu',
            'cancel_return_url' => 'https://jovonkvize.hu',
            'allowed_origins' => $allowedOrigins,
            'theme' => $theme,
            'lots' => impactshop_event_auction_default_lots(),
        ],
    ];

    return apply_filters('impactshop_event_auction_campaigns', $campaigns);
}

function impactshop_event_auction_default_lots(): array
{
    return [
        [
            'item_slug' => 'szentpeteri-toth-marta-forgiveness',
            'lot_number' => 1,
            'category' => 'artwork',
            'artist_name' => 'Szentpeteri Toth Marta',
            'item_title' => 'Forgiveness',
            'description_short' => 'Akril festmeny, 70x100 cm.',
            'description_long' => 'Scaffold lot. A vegleges publikus leiras, muvesz-bemutatas es asset mapping kulon tartalomkorben veglegesitendo.',
            'dimensions' => '70x100 cm',
            'medium' => 'Akril',
            'starting_bid' => 150000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/toth-marta.jpg',
        ],
        [
            'item_slug' => 'simon-m-veronika-kek-sugarzas',
            'lot_number' => 2,
            'category' => 'artwork',
            'artist_name' => 'Simon M. Veronika',
            'item_title' => 'Kek sugarzas',
            'description_short' => 'Festmeny, 70x50 cm.',
            'description_long' => 'Scaffold lot. A vegleges publikus leiras es a kep asset ellenorzese kulon korben veglegesitendo.',
            'dimensions' => '70x50 cm',
            'medium' => 'Festmeny',
            'starting_bid' => 185000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/kek-sugarzas.jpg',
        ],
        [
            'item_slug' => 'tarcsi-daniel-part-iii',
            'lot_number' => 3,
            'category' => 'artwork',
            'artist_name' => 'Tarcsi Daniel',
            'item_title' => 'Part III.',
            'description_short' => 'Festmeny, 30x100 cm.',
            'description_long' => 'Scaffold lot. A lot status es a licitlepcso logika vegleges validacioja a backend implementacios fazisban kotendo be.',
            'dimensions' => '30x100 cm',
            'medium' => 'Festmeny',
            'starting_bid' => 450000,
            'min_increment' => 25000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/part-iii.jpg',
        ],
        [
            'item_slug' => 'ghyczy-gyorgy-elindulok-a-csillagokhoz',
            'lot_number' => 4,
            'category' => 'artwork',
            'artist_name' => 'Ghyczy Gyorgy',
            'item_title' => 'Elindulok a csillagokhoz',
            'description_short' => 'Festmeny, 90x90 cm.',
            'description_long' => 'Scaffold lot. A vegleges leiras es az asset filename-normalizalas kulon tartalmi korben keszul el.',
            'dimensions' => '90x90 cm',
            'medium' => 'Festmeny',
            'starting_bid' => 200000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/elindulok-a-csillagokhoz.jpg',
        ],
        [
            'item_slug' => 'szabo-anna-cseresznye',
            'lot_number' => 5,
            'category' => 'artwork',
            'artist_name' => 'Szabo Anna',
            'item_title' => 'Cseresznye',
            'description_short' => 'Festmeny, 50x40 cm.',
            'description_long' => 'Scaffold lot. A vegleges publikus szoveg es kepvaltozat kulon veglegesitendo.',
            'dimensions' => '50x40 cm',
            'medium' => 'Festmeny',
            'starting_bid' => 60000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/szabo-anna-cseresznye.jpg',
        ],
        [
            'item_slug' => 'szabo-anna-a-no-turkizben',
            'lot_number' => 6,
            'category' => 'artwork',
            'artist_name' => 'Szabo Anna',
            'item_title' => 'A no turkizben',
            'description_short' => 'Festmeny, 50x70 cm.',
            'description_long' => 'Scaffold lot. A vegleges publikus szoveg es kepforras veglegesitendo.',
            'dimensions' => '50x70 cm',
            'medium' => 'Festmeny',
            'starting_bid' => 80000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/szabo-anna-no-turkizben.jpg',
        ],
        [
            'item_slug' => 'dimenzio-ingatlan-sirocco-elmenyvitorlazas',
            'lot_number' => 7,
            'category' => 'experience',
            'artist_name' => 'Dimenzio Ingatlan Kft.',
            'item_title' => 'Sirocco elmenyvitorlazas 10 fore',
            'description_short' => 'Kulonleges elmenyajanlat 10 fore.',
            'description_long' => 'Scaffold lot. A beváltási feltetelek, datumok es kommunikacios szoveg veglegesitese kulon uzleti korben szukseges.',
            'dimensions' => '',
            'medium' => 'Elmenyajanlat',
            'starting_bid' => 150000,
            'min_increment' => 10000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_url' => 'https://app.sharity.hu/wp-content/uploads/jovonkvize-auction/2026/sirocco-elmenyvitorlazas.jpg',
        ],
    ];
}

function impactshop_event_auction_get_campaign(string $slug): ?array
{
    $slug = sanitize_title($slug);
    $campaigns = impactshop_event_auction_campaigns();
    return isset($campaigns[$slug]) && is_array($campaigns[$slug]) ? $campaigns[$slug] : null;
}

function impactshop_event_auction_client_ip(): string
{
    return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function impactshop_event_auction_request_origin(): string
{
    return rtrim(esc_url_raw((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
}

function impactshop_event_auction_origin_allowed(array $campaign): bool
{
    $origin = impactshop_event_auction_request_origin();
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
    foreach ($allowed as $allowedOrigin) {
        $allowedHost = strtolower((string) wp_parse_url($allowedOrigin, PHP_URL_HOST));
        if ($allowedHost !== '' && $allowedHost === $refererHost) {
            return true;
        }
    }

    return false;
}

function impactshop_event_auction_send_cors_headers(array $campaign): void
{
    $origin = impactshop_event_auction_request_origin();
    if ($origin === '' || !impactshop_event_auction_origin_allowed($campaign)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

function impactshop_event_auction_allowed_http_origins(array $origins): array
{
    $extra = [];
    foreach (impactshop_event_auction_campaigns() as $campaign) {
        foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
            $origin = esc_url_raw((string) $origin);
            if ($origin !== '') {
                $extra[] = rtrim($origin, '/');
            }
        }
    }

    return array_values(array_unique(array_merge($origins, $extra)));
}

function impactshop_event_auction_allowed_redirect_hosts(array $hosts): array
{
    foreach (impactshop_event_auction_campaigns() as $campaign) {
        foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
            $host = wp_parse_url((string) $origin, PHP_URL_HOST);
            if ($host) {
                $hosts[] = (string) $host;
            }
        }
    }

    return array_values(array_unique($hosts));
}

function impactshop_event_auction_rate_limit(string $scope, int $limit, int $window): array
{
    $ip = impactshop_event_auction_client_ip();
    $key = 'impactshop_event_auction_rl_' . md5($scope . '|' . $ip);
    $state = get_transient($key);

    if (!is_array($state)) {
        $state = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    if (($state['reset'] ?? 0) <= time()) {
        $state = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    $state['count'] = (int) ($state['count'] ?? 0) + 1;
    set_transient($key, $state, max(1, (int) $window));

    return [
        'allowed' => $state['count'] <= $limit,
        'remaining' => max(0, $limit - $state['count']),
        'reset' => (int) ($state['reset'] ?? (time() + $window)),
    ];
}

function impactshop_event_auction_extract_payload(WP_REST_Request $request): array
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

function impactshop_event_auction_transient_key(string $prefix, string $token): string
{
    return 'impactshop_event_auction_' . $prefix . '_' . md5($token);
}

function impactshop_event_auction_issue_session_token(array $campaign): array
{
    $token = wp_generate_uuid4();
    $expiresAt = time() + IMPACTSHOP_EVENT_AUCTION_SESSION_TTL;
    $payload = [
        'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        'origin' => impactshop_event_auction_request_origin(),
        'ua_hash' => md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'expires_at' => $expiresAt,
    ];

    set_transient(
        impactshop_event_auction_transient_key('session', $token),
        $payload,
        IMPACTSHOP_EVENT_AUCTION_SESSION_TTL
    );

    return [
        'token' => $token,
        'expires_at' => gmdate('c', $expiresAt),
    ];
}

function impactshop_event_auction_verify_session_token(string $token, array $campaign): bool
{
    if ($token === '') {
        return false;
    }

    $stored = get_transient(impactshop_event_auction_transient_key('session', $token));
    if (!is_array($stored)) {
        return false;
    }

    if ((string) ($stored['campaign_slug'] ?? '') !== (string) ($campaign['slug'] ?? '')) {
        return false;
    }

    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return false;
    }

    $storedOrigin = (string) ($stored['origin'] ?? '');
    $currentOrigin = impactshop_event_auction_request_origin();
    if ($storedOrigin !== '' && $currentOrigin !== '' && $storedOrigin !== $currentOrigin) {
        return false;
    }

    $uaHash = md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return hash_equals((string) ($stored['ua_hash'] ?? ''), $uaHash);
}

function impactshop_event_auction_issue_bidder_token(array $campaign, string $bidderUuid): array
{
    $token = wp_generate_uuid4();
    $expiresAt = time() + IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL;
    $payload = [
        'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        'bidder_uuid' => $bidderUuid,
        'origin' => impactshop_event_auction_request_origin(),
        'ua_hash' => md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'expires_at' => $expiresAt,
    ];

    set_transient(
        impactshop_event_auction_transient_key('bidder', $token),
        $payload,
        IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL
    );

    return [
        'token' => $token,
        'expires_at' => gmdate('c', $expiresAt),
    ];
}

function impactshop_event_auction_verify_bidder_token(string $token, array $campaign): ?array
{
    if ($token === '') {
        return null;
    }

    $stored = get_transient(impactshop_event_auction_transient_key('bidder', $token));
    if (!is_array($stored)) {
        return null;
    }

    if ((string) ($stored['campaign_slug'] ?? '') !== (string) ($campaign['slug'] ?? '')) {
        return null;
    }

    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return null;
    }

    $storedOrigin = (string) ($stored['origin'] ?? '');
    $currentOrigin = impactshop_event_auction_request_origin();
    if ($storedOrigin !== '' && $currentOrigin !== '' && $storedOrigin !== $currentOrigin) {
        return null;
    }

    $uaHash = md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!hash_equals((string) ($stored['ua_hash'] ?? ''), $uaHash)) {
        return null;
    }

    return $stored;
}

function impactshop_event_auction_log_event(string $campaignSlug, string $itemSlug, string $eventType, string $actor, array $payload = []): void
{
    global $wpdb;
    $table = impactshop_event_auction_events_table_name();

    $wpdb->insert(
        $table,
        [
            'campaign_slug' => $campaignSlug,
            'item_slug' => $itemSlug,
            'event_type' => $eventType,
            'actor' => $actor,
            'origin' => impactshop_event_auction_request_origin(),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => current_time('mysql', true),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );
}

function impactshop_event_auction_parse_amount($value): int
{
    if (is_numeric($value)) {
        return max(0, (int) round((float) $value, 0));
    }

    $raw = preg_replace('/[^0-9]/', '', (string) $value);
    return $raw === '' ? 0 : (int) $raw;
}

function impactshop_event_auction_find_lot(array $campaign, string $itemSlug): ?array
{
    foreach ((array) ($campaign['lots'] ?? []) as $lot) {
        if (sanitize_title((string) ($lot['item_slug'] ?? '')) === $itemSlug) {
            return (array) $lot;
        }
    }

    return null;
}

function impactshop_event_auction_current_bid_state(string $campaignSlug, string $itemSlug): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
                        "SELECT bid_uuid, bidder_uuid, bid_amount, status, stripe_session_id, stripe_payment_intent, stripe_checkout_url, return_url, created_at
             FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
               AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bid_by_uuid(string $bidUuid): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bid_uuid = %s LIMIT 1",
            $bidUuid
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bid_by_session_id(string $sessionId): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
            $sessionId
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bidder(string $bidderUuid): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bidders_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bidder_uuid = %s LIMIT 1",
            $bidderUuid
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_effective_lot_status(array $lot, ?array $bidState): string
{
    $fallback = sanitize_key((string) ($lot['status'] ?? 'draft'));
    if (!$bidState) {
        return $fallback;
    }

    $state = sanitize_key((string) ($bidState['status'] ?? ''));
    if ($state === 'winning') {
        return in_array($fallback, ['closing', 'draft'], true) ? $fallback : 'live';
    }

    if (in_array($state, ['closed', 'payment_pending', 'paid'], true)) {
        return $state;
    }

    return $fallback;
}

function impactshop_event_auction_display_label(?int $currentBid, string $status): string
{
    if ($currentBid === null) {
        return 'Kikialtasi ar';
    }

    if ($status === 'paid') {
        return 'Kifizetett leutesi ar';
    }

    if (in_array($status, ['closed', 'payment_pending'], true)) {
        return 'Nyertes licit';
    }

    return 'Aktualis vezeto licit';
}

function impactshop_event_auction_admin_permission(WP_REST_Request $request)
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Admin jogosultsag szukseges.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_header('x-wp-nonce');
    }
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Ervenytelen nonce.', ['status' => 403]);
    }

    return true;
}

function impactshop_event_auction_is_configured(): bool
{
    return defined('IMPACT_STRIPE_SECRET_KEY')
        && defined('IMPACT_STRIPE_PUBLIC_KEY')
        && defined('IMPACT_STRIPE_WEBHOOK_SECRET')
        && IMPACT_STRIPE_SECRET_KEY !== ''
        && IMPACT_STRIPE_PUBLIC_KEY !== ''
        && IMPACT_STRIPE_WEBHOOK_SECRET !== ''
        && defined('IMPACT_STRIPE_DEFAULT_CURRENCY')
        && IMPACT_STRIPE_DEFAULT_CURRENCY !== '';
}

function impactshop_event_auction_stripe_mode(): string
{
    if (defined('IMPACT_STRIPE_MODE') && IMPACT_STRIPE_MODE !== '') {
        return strtolower((string) IMPACT_STRIPE_MODE);
    }

    if (defined('IMPACT_STRIPE_SECRET_KEY')) {
        $key = (string) IMPACT_STRIPE_SECRET_KEY;
        if (strpos($key, 'sk_live_') === 0) {
            return 'live';
        }
        if (strpos($key, 'sk_test_') === 0) {
            return 'test';
        }
    }

    return 'unknown';
}

function impactshop_event_auction_is_staging_runtime(): bool
{
    $home = (string) home_url('/');
    return strpos($home, '/impactshop-staging') !== false || stripos($home, 'staging') !== false;
}

function impactshop_event_auction_is_paid_session(array $session): bool
{
    $paymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
    $status = strtolower((string) ($session['status'] ?? ''));

    if ($paymentStatus !== '' && $paymentStatus !== 'paid') {
        return false;
    }

    if ($status !== '' && !in_array($status, ['complete', 'completed'], true)) {
        return false;
    }

    return true;
}

function impactshop_event_auction_webhook_secret(): string
{
    if (defined('IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET') && IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET !== '') {
        return (string) IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET;
    }

    if (function_exists('impactshop_event_donation_webhook_secret')) {
        return (string) impactshop_event_donation_webhook_secret();
    }

    return defined('IMPACT_STRIPE_WEBHOOK_SECRET') ? (string) IMPACT_STRIPE_WEBHOOK_SECRET : '';
}

function impactshop_event_auction_verify_stripe_signature(string $payload, string $sigHeader, string $secret): bool
{
    if (function_exists('impactshop_event_donation_verify_stripe_signature')) {
        return impactshop_event_donation_verify_stripe_signature($payload, $sigHeader, $secret);
    }

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

function impactshop_event_auction_fetch_stripe_session(string $sessionId): ?array
{
    $sessionId = sanitize_text_field($sessionId);
    if ($sessionId === '' || !impactshop_event_auction_is_configured()) {
        return null;
    }

    $response = wp_remote_get(
        'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
            ],
            'timeout' => 20,
        ]
    );

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

function impactshop_event_auction_to_minor(float $amount, string $currency): int
{
    if (function_exists('impactshop_event_donation_to_minor')) {
        return impactshop_event_donation_to_minor($amount, $currency);
    }

    return (int) round($amount * 100, 0);
}

function impactshop_event_auction_redirect_result(string $status, string $bidUuid = '')
{
    $row = $bidUuid !== '' ? impactshop_event_auction_get_bid_by_uuid($bidUuid) : null;
    $campaignSlug = sanitize_title((string) ($row['campaign_slug'] ?? 'jovonkvize-2026'));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);

    $fallback = $status === 'success'
        ? (string) (($campaign['success_return_url'] ?? home_url('/')))
        : (string) (($campaign['cancel_return_url'] ?? home_url('/')));

    $returnUrl = esc_url_raw((string) ($row['return_url'] ?? $fallback));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $redirect = add_query_arg([
        'ea_status' => $status,
        'ea_campaign' => $campaignSlug,
        'ea_bid_uuid' => $bidUuid,
        'ea_item_slug' => sanitize_title((string) ($row['item_slug'] ?? '')),
    ], $returnUrl);

    wp_safe_redirect($redirect, 302);
    exit;
}

function impactshop_event_auction_bidders_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_bidders';
}

function impactshop_event_auction_bids_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_bids';
}

function impactshop_event_auction_events_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_events';
}

function impactshop_event_auction_ensure_schema(): void
{
    $installed = (string) get_option('impactshop_event_auction_schema_version', '');
    if ($installed === IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charsetCollate = $wpdb->get_charset_collate();
    $biddersTable = impactshop_event_auction_bidders_table_name();
    $bidsTable = impactshop_event_auction_bids_table_name();
    $eventsTable = impactshop_event_auction_events_table_name();

    $sql = "
    CREATE TABLE {$biddersTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        bidder_uuid char(36) NOT NULL,
        campaign_slug varchar(120) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(50) DEFAULT '' NOT NULL,
        display_name varchar(190) DEFAULT '' NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bidder_uuid (bidder_uuid),
        KEY campaign_slug (campaign_slug),
        KEY email (email)
    ) {$charsetCollate};

    CREATE TABLE {$bidsTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        bid_uuid char(36) NOT NULL,
        campaign_slug varchar(120) NOT NULL,
        item_slug varchar(160) NOT NULL,
        bidder_uuid char(36) NOT NULL,
        bid_amount bigint unsigned NOT NULL DEFAULT 0,
        status varchar(40) NOT NULL DEFAULT 'pending',
        idempotency_key varchar(120) DEFAULT '' NOT NULL,
        stripe_session_id varchar(128) DEFAULT NULL,
        stripe_payment_intent varchar(128) DEFAULT NULL,
        stripe_checkout_url text NULL,
        return_url varchar(255) DEFAULT NULL,
        closed_at datetime DEFAULT NULL,
        payment_requested_at datetime DEFAULT NULL,
        payment_completed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bid_uuid (bid_uuid),
        KEY campaign_item (campaign_slug, item_slug),
        KEY bidder_uuid (bidder_uuid),
        KEY idempotency_key (idempotency_key),
        KEY stripe_session_id (stripe_session_id),
        KEY stripe_payment_intent (stripe_payment_intent)
    ) {$charsetCollate};

    CREATE TABLE {$eventsTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        campaign_slug varchar(120) NOT NULL,
        item_slug varchar(160) DEFAULT '' NOT NULL,
        event_type varchar(80) NOT NULL,
        actor varchar(120) DEFAULT '' NOT NULL,
        origin varchar(190) DEFAULT '' NOT NULL,
        payload_json longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY campaign_item (campaign_slug, item_slug),
        KEY event_type (event_type)
    ) {$charsetCollate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('impactshop_event_auction_schema_version', IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION, false);
}

function impactshop_event_auction_register_routes(): void
{
    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/public', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_public',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/lots/(?P<item_slug>[a-z0-9\-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_lot_detail',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/stats', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_stats',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/register-bidder', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_register_bidder',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/lots/(?P<item_slug>[a-z0-9\-]+)/bid', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_bid',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/admin/lots/(?P<item_slug>[a-z0-9\-]+)/close', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_admin_close',
        'permission_callback' => 'impactshop_event_auction_admin_permission',
    ]);

    register_rest_route('impact/v1', '/event-auctions/admin/lots/(?P<item_slug>[a-z0-9\-]+)/request-winner-payment', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_request_winner_payment',
        'permission_callback' => 'impactshop_event_auction_admin_permission',
    ]);

    register_rest_route('impact/v1', '/event-auctions/webhook', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/success', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_success',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/cancel', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_cancel',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_event_auction_query_api_dispatch(): void
{
    $action = sanitize_key((string) ($_REQUEST['impact_auction_api'] ?? ''));
    if ($action === '') {
        return;
    }

    $slug = sanitize_title((string) ($_REQUEST['campaign'] ?? ''));
    $campaign = $slug !== '' ? impactshop_event_auction_get_campaign($slug) : null;
    if ($campaign) {
        impactshop_event_auction_send_cors_headers($campaign);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'OPTIONS') {
        status_header(204);
        exit;
    }

    $request = new WP_REST_Request($method, '/impact/v1/event-auctions/' . $action);
    if ($slug !== '') {
        $request->set_param('slug', $slug);
    }

    $itemSlug = sanitize_title((string) ($_REQUEST['lot'] ?? $_REQUEST['item_slug'] ?? ''));
    if ($itemSlug !== '') {
        $request->set_param('item_slug', $itemSlug);
    }

    switch ($action) {
        case 'public':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_public($request));
            break;
        case 'stats':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_stats($request));
            break;
        case 'lot':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_lot_detail($request));
            break;
        default:
            impactshop_event_auction_emit_query_response(new WP_REST_Response(['error' => 'not_found'], 404));
    }
}

function impactshop_event_auction_emit_query_response(WP_REST_Response $response): void
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

function impactshop_event_auction_embed_page_dispatch(): void
{
    if ((string) ($_GET['impact_event_auction_embed'] ?? '') !== '1') {
        return;
    }

    $slug = sanitize_title((string) ($_GET['campaign'] ?? 'jovonkvize-2026'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        status_header(404);
        echo 'Unknown auction campaign';
        exit;
    }

    status_header(200);
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    header_remove('X-Frame-Options');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $scriptSrc = esc_url(trailingslashit(home_url('/wp-content/mu-plugins')) . 'impactshop-event-auction-widget-jovonkvize-1.0.0.js?v=' . rawurlencode(IMPACTSHOP_EVENT_AUCTION_VERSION));
    $apiBase = esc_url(rest_url('impact/v1/event-auctions'));
    $fallback = esc_url(home_url('/'));
    $campaignAttr = esc_attr($slug);

    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Jovonk Vize aukcio widget</title>';
    echo '<style>html,body{margin:0;padding:0;background:transparent}#impact-event-auction-embed-root{padding:0}</style>';
    echo '</head><body>';
    echo '<div id="impact-event-auction-embed-root"></div>';
    echo '<script src="' . $scriptSrc . '" data-impact-auction-widget data-campaign="' . $campaignAttr . '" data-target="#impact-event-auction-embed-root" data-api-base="' . $apiBase . '" data-fallback-api-base="' . $fallback . '" data-poll-ms="30000" defer></script>';
    echo '</body></html>';
    exit;
}

function impactshop_event_auction_shortcode($atts = []): string
{
    $atts = shortcode_atts([
        'campaign' => 'jovonkvize-2026',
        'target' => 'impact-event-auction-shortcode-root',
    ], (array) $atts, 'impact_event_auction_widget');

    $campaign = sanitize_title((string) $atts['campaign']);
    $targetId = sanitize_html_class((string) $atts['target']);
    $scriptSrc = esc_url(trailingslashit(home_url('/wp-content/mu-plugins')) . 'impactshop-event-auction-widget-jovonkvize-1.0.0.js?v=' . rawurlencode(IMPACTSHOP_EVENT_AUCTION_VERSION));
    $apiBase = esc_url(rest_url('impact/v1/event-auctions'));
    $fallback = esc_url(home_url('/'));

    return '<div id="' . esc_attr($targetId) . '"></div>'
        . '<script src="' . $scriptSrc . '" data-impact-auction-widget data-campaign="' . esc_attr($campaign) . '" data-target="#' . esc_attr($targetId) . '" data-api-base="' . $apiBase . '" data-fallback-api-base="' . $fallback . '" data-poll-ms="30000" defer></script>';
}

function impactshop_event_auction_public(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    $security = [
        'write_enabled' => false,
        'session_token' => '',
        'expires_at' => '',
    ];

    if (impactshop_event_auction_origin_allowed($campaign)) {
        $issued = impactshop_event_auction_issue_session_token($campaign);
        $security = [
            'write_enabled' => true,
            'session_token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
        ];
    }

    return new WP_REST_Response([
        'slug' => $campaign['slug'],
        'auction_slug' => $campaign['auction_slug'],
        'title' => $campaign['title'],
        'subtitle' => $campaign['subtitle'],
        'description' => $campaign['description'],
        'beneficiary_name' => $campaign['beneficiary_name'],
        'currency' => $campaign['currency'],
        'goal_amount' => (int) ($campaign['goal_amount'] ?? 0),
        'share_url' => esc_url_raw((string) ($campaign['share_url'] ?? '')),
        'hero_url' => esc_url_raw((string) ($campaign['hero_url'] ?? '')),
        'theme' => (array) ($campaign['theme'] ?? []),
        'lots' => impactshop_event_auction_lot_summaries($campaign),
        'stats' => impactshop_event_auction_stats_payload($campaign),
        'security' => $security,
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_lot_summaries(array $campaign): array
{
    $lots = [];

    foreach ((array) ($campaign['lots'] ?? []) as $lot) {
        $lots[] = impactshop_event_auction_normalize_lot(array_merge((array) $lot, [
            'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        ]));
    }

    return $lots;
}

function impactshop_event_auction_normalize_lot(array $lot): array
{
    $startingBid = (int) ($lot['starting_bid'] ?? 0);
    $campaignSlug = sanitize_title((string) ($lot['campaign_slug'] ?? 'jovonkvize-2026'));
    $itemSlug = sanitize_title((string) ($lot['item_slug'] ?? ''));
    $bidState = impactshop_event_auction_current_bid_state($campaignSlug, $itemSlug);
    $currentBid = $bidState && isset($bidState['bid_amount']) ? (int) $bidState['bid_amount'] : null;
    $currency = 'huf';
    $effectiveStatus = impactshop_event_auction_effective_lot_status($lot, $bidState);
    $displayLabel = impactshop_event_auction_display_label($currentBid, $effectiveStatus);

    return [
        'item_slug' => sanitize_title((string) ($lot['item_slug'] ?? '')),
        'lot_number' => (int) ($lot['lot_number'] ?? 0),
        'category' => sanitize_key((string) ($lot['category'] ?? 'artwork')),
        'artist_name' => sanitize_text_field((string) ($lot['artist_name'] ?? '')),
        'item_title' => sanitize_text_field((string) ($lot['item_title'] ?? '')),
        'description_short' => sanitize_text_field((string) ($lot['description_short'] ?? '')),
        'description_long' => sanitize_textarea_field((string) ($lot['description_long'] ?? '')),
        'dimensions' => sanitize_text_field((string) ($lot['dimensions'] ?? '')),
        'medium' => sanitize_text_field((string) ($lot['medium'] ?? '')),
        'starting_bid' => $startingBid,
        'starting_bid_formatted' => impactshop_event_auction_format_amount($startingBid, $currency),
        'min_increment' => (int) ($lot['min_increment'] ?? 10000),
        'current_bid' => $currentBid,
        'current_bid_formatted' => $currentBid !== null ? impactshop_event_auction_format_amount($currentBid, $currency) : '',
        'display_amount' => $currentBid !== null ? $currentBid : $startingBid,
        'display_amount_formatted' => impactshop_event_auction_format_amount($currentBid !== null ? $currentBid : $startingBid, $currency),
        'display_label' => $displayLabel,
        'current_winner_bidder_id' => $bidState['bidder_uuid'] ?? '',
        'status' => $effectiveStatus,
        'image_url' => esc_url_raw((string) ($lot['image_url'] ?? '')),
    ];
}

function impactshop_event_auction_stats_payload(array $campaign): array
{
    $leadingTotal = 0;
    $closedTotal = 0;
    $paidTotal = 0;
    $closedLots = 0;

    foreach (impactshop_event_auction_lot_summaries($campaign) as $lot) {
        $display = (int) ($lot['display_amount'] ?? 0);
        if ($display > 0) {
            $leadingTotal += $display;
        }

        if (in_array((string) ($lot['status'] ?? ''), ['closed', 'payment_pending', 'paid'], true)) {
            $closedTotal += $display;
            $closedLots++;
        }

        if ((string) ($lot['status'] ?? '') === 'paid') {
            $paidTotal += $display;
        }
    }

    $donationPaid = 0.0;
    if (function_exists('impactshop_event_donation_get_campaign') && function_exists('impactshop_event_donation_stats_payload')) {
        $donationCampaign = impactshop_event_donation_get_campaign((string) ($campaign['slug'] ?? ''));
        if (is_array($donationCampaign)) {
            $donationStats = impactshop_event_donation_stats_payload($donationCampaign);
            $donationPaid = (float) ($donationStats['total_amount'] ?? 0);
        }
    }

    global $wpdb;
    $biddersTable = impactshop_event_auction_bidders_table_name();
    $activeBidders = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$biddersTable} WHERE campaign_slug = %s",
            (string) ($campaign['slug'] ?? '')
        )
    );

    return [
        'currency' => 'huf',
        'auction_lots_count' => count((array) ($campaign['lots'] ?? [])),
        'active_bidders_count' => $activeBidders,
        'closed_lots_count' => $closedLots,
        'auction_leading_total_amount' => $leadingTotal,
        'auction_leading_total_amount_formatted' => impactshop_event_auction_format_amount($leadingTotal, 'huf'),
        'auction_closed_total_amount' => $closedTotal,
        'auction_closed_total_amount_formatted' => impactshop_event_auction_format_amount($closedTotal, 'huf'),
        'auction_paid_total_amount' => $paidTotal,
        'auction_paid_total_amount_formatted' => impactshop_event_auction_format_amount($paidTotal, 'huf'),
        'donation_total_amount' => $donationPaid,
        'donation_total_amount_formatted' => impactshop_event_auction_format_amount($donationPaid, 'huf'),
        'combined_paid_total_amount' => (float) $paidTotal + $donationPaid,
        'combined_paid_total_amount_formatted' => impactshop_event_auction_format_amount((float) $paidTotal + $donationPaid, 'huf'),
        'updated_at' => gmdate('c'),
    ];
}

function impactshop_event_auction_lot_detail(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    foreach (impactshop_event_auction_lot_summaries($campaign) as $lot) {
        if ((string) ($lot['item_slug'] ?? '') === $itemSlug) {
            return new WP_REST_Response([
                'lot' => $lot,
                'integration_state' => 'write_lane_partial',
            ], 200);
        }
    }

    return new WP_REST_Response(['error' => 'not_found'], 404);
}

function impactshop_event_auction_stats(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    return new WP_REST_Response(impactshop_event_auction_stats_payload($campaign), 200);
}

function impactshop_event_auction_register_bidder(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $limit = impactshop_event_auction_rate_limit('register_bidder', 8, 60);
    if (!$limit['allowed']) {
        return new WP_REST_Response([
            'error' => 'rate_limited',
            'reset_at' => gmdate('c', (int) $limit['reset']),
        ], 429);
    }

    $payload = impactshop_event_auction_extract_payload($request);
    $sessionToken = sanitize_text_field((string) ($payload['session_token'] ?? ''));
    if (!impactshop_event_auction_verify_session_token($sessionToken, $campaign)) {
        return new WP_REST_Response(['error' => 'invalid_session_token'], 403);
    }

    $email = sanitize_email((string) ($payload['email'] ?? ''));
    if ($email === '' || !is_email($email)) {
        return new WP_REST_Response(['error' => 'invalid_email'], 400);
    }

    $phone = sanitize_text_field((string) ($payload['phone'] ?? ''));
    $displayName = sanitize_text_field((string) ($payload['display_name'] ?? ''));

    global $wpdb;
    $table = impactshop_event_auction_bidders_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT bidder_uuid, email, phone, display_name
             FROM {$table}
             WHERE campaign_slug = %s
               AND email = %s
             LIMIT 1",
            $slug,
            $email
        ),
        ARRAY_A
    );

    if ($row) {
        $bidderUuid = (string) ($row['bidder_uuid'] ?? '');
        $wpdb->update(
            $table,
            [
                'phone' => $phone !== '' ? $phone : (string) ($row['phone'] ?? ''),
                'display_name' => $displayName !== '' ? $displayName : (string) ($row['display_name'] ?? ''),
            ],
            [
                'bidder_uuid' => $bidderUuid,
            ],
            ['%s', '%s'],
            ['%s']
        );
    } else {
        $bidderUuid = wp_generate_uuid4();
        $wpdb->insert(
            $table,
            [
                'bidder_uuid' => $bidderUuid,
                'campaign_slug' => $slug,
                'email' => $email,
                'phone' => $phone,
                'display_name' => $displayName,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    $issued = impactshop_event_auction_issue_bidder_token($campaign, $bidderUuid);
    impactshop_event_auction_log_event($slug, '', 'register_bidder', $bidderUuid, [
        'email_hash' => md5(strtolower($email)),
        'phone_present' => $phone !== '',
    ]);

    return new WP_REST_Response([
        'bidder_token' => $issued['token'],
        'bidder_token_expires_at' => $issued['expires_at'],
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_bid(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $limit = impactshop_event_auction_rate_limit('bid', 20, 60);
    if (!$limit['allowed']) {
        return new WP_REST_Response([
            'error' => 'rate_limited',
            'reset_at' => gmdate('c', (int) $limit['reset']),
        ], 429);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    $currentBidState = impactshop_event_auction_current_bid_state((string) ($campaign['slug'] ?? ''), $itemSlug);
    $effectiveStatus = impactshop_event_auction_effective_lot_status($lot, $currentBidState);

    if (!in_array($effectiveStatus, ['live', 'closing'], true)) {
        return new WP_REST_Response(['error' => 'lot_not_live'], 409);
    }

    $payload = impactshop_event_auction_extract_payload($request);
    $sessionToken = sanitize_text_field((string) ($payload['session_token'] ?? ''));
    if (!impactshop_event_auction_verify_session_token($sessionToken, $campaign)) {
        return new WP_REST_Response(['error' => 'invalid_session_token'], 403);
    }

    $bidderToken = sanitize_text_field((string) ($payload['bidder_token'] ?? ''));
    $bidderPayload = impactshop_event_auction_verify_bidder_token($bidderToken, $campaign);
    if (!$bidderPayload) {
        return new WP_REST_Response(['error' => 'invalid_bidder_token'], 403);
    }

    $idempotencyKey = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        return new WP_REST_Response(['error' => 'missing_idempotency_key'], 400);
    }

    $bidAmount = impactshop_event_auction_parse_amount($payload['bid_amount'] ?? 0);
    if ($bidAmount <= 0) {
        return new WP_REST_Response(['error' => 'invalid_bid_amount'], 400);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $campaignSlug = (string) ($campaign['slug'] ?? '');
    $bidderUuid = (string) ($bidderPayload['bidder_uuid'] ?? '');

    $wpdb->query('START TRANSACTION');

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT bid_uuid, bid_amount, bidder_uuid, status
             FROM {$table}
             WHERE campaign_slug = %s
               AND idempotency_key = %s
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $idempotencyKey
        ),
        ARRAY_A
    );

    if ($existing) {
        $wpdb->query('COMMIT');
        return new WP_REST_Response([
            'bid_uuid' => (string) ($existing['bid_uuid'] ?? ''),
            'bid_amount' => (int) ($existing['bid_amount'] ?? 0),
            'bid_amount_formatted' => impactshop_event_auction_format_amount((int) ($existing['bid_amount'] ?? 0), 'huf'),
            'status' => (string) ($existing['status'] ?? 'winning'),
            'idempotent_replay' => true,
            'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
            'integration_state' => 'write_lane_partial',
        ], 200);
    }

        $current = $wpdb->get_row(
        $wpdb->prepare(
                        "SELECT id, bid_uuid, bidder_uuid, bid_amount, status, stripe_session_id, stripe_payment_intent, stripe_checkout_url
             FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
                             AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );

    $currentAmount = $current ? (int) ($current['bid_amount'] ?? 0) : (int) ($lot['starting_bid'] ?? 0);
    $minimumRequired = $currentAmount + (int) ($lot['min_increment'] ?? 10000);

    if ($bidAmount < $minimumRequired) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response([
            'error' => 'bid_too_low',
            'minimum_required' => $minimumRequired,
            'minimum_required_formatted' => impactshop_event_auction_format_amount($minimumRequired, 'huf'),
        ], 409);
    }

    if ($current && (string) ($current['bidder_uuid'] ?? '') === $bidderUuid && $bidAmount <= $currentAmount) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response([
            'error' => 'already_winning',
            'current_bid' => $currentAmount,
            'current_bid_formatted' => impactshop_event_auction_format_amount($currentAmount, 'huf'),
        ], 409);
    }

    if ($current) {
        $wpdb->update(
            $table,
            ['status' => 'outbid'],
            ['id' => (int) $current['id']],
            ['%s'],
            ['%d']
        );
    }

    $bidUuid = wp_generate_uuid4();
    $inserted = $wpdb->insert(
        $table,
        [
            'bid_uuid' => $bidUuid,
            'campaign_slug' => $campaignSlug,
            'item_slug' => $itemSlug,
            'bidder_uuid' => $bidderUuid,
            'bid_amount' => $bidAmount,
            'status' => 'winning',
            'idempotency_key' => $idempotencyKey,
            'created_at' => current_time('mysql', true),
        ],
        ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'bid_insert_failed'], 500);
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_created', $bidderUuid, [
        'bid_uuid' => $bidUuid,
        'bid_amount' => $bidAmount,
        'idempotency_key' => $idempotencyKey,
    ]);

    return new WP_REST_Response([
        'bid_uuid' => $bidUuid,
        'bid_amount' => $bidAmount,
        'bid_amount_formatted' => impactshop_event_auction_format_amount($bidAmount, 'huf'),
        'status' => 'winning',
        'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_admin_close(WP_REST_Request $request): WP_REST_Response
{
    $payload = impactshop_event_auction_extract_payload($request);
    $campaignSlug = sanitize_title((string) ($payload['campaign_slug'] ?? ''));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $actor = wp_get_current_user();
    $actorId = $actor instanceof WP_User ? (string) $actor->user_login : 'admin';

    $wpdb->query('START TRANSACTION');
    $current = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
               AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );

    if (!$current) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'no_winning_bid'], 409);
    }

    $status = sanitize_key((string) ($current['status'] ?? ''));
    if ($status === 'paid') {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'already_paid'], 409);
    }

    if (in_array($status, ['closed', 'payment_pending'], true)) {
        $wpdb->query('COMMIT');
        return new WP_REST_Response([
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
            'status' => $status,
            'idempotent_replay' => true,
            'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
        ], 200);
    }

    $closedAt = current_time('mysql', true);
    $updated = $wpdb->update(
        $table,
        [
            'status' => 'closed',
            'closed_at' => $closedAt,
        ],
        [
            'id' => (int) $current['id'],
        ],
        ['%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'close_failed'], 500);
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'admin_close', $actorId, [
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'bidder_uuid' => (string) ($current['bidder_uuid'] ?? ''),
        'bid_amount' => (int) ($current['bid_amount'] ?? 0),
    ]);

    return new WP_REST_Response([
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'status' => 'closed',
        'closed_at' => mysql2date('c', $closedAt, false),
        'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
    ], 200);
}

function impactshop_event_auction_request_winner_payment(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_event_auction_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload = impactshop_event_auction_extract_payload($request);
    $campaignSlug = sanitize_title((string) ($payload['campaign_slug'] ?? ''));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    $current = impactshop_event_auction_current_bid_state($campaignSlug, $itemSlug);
    if (!$current) {
        return new WP_REST_Response(['error' => 'no_closed_winner'], 409);
    }

    $currentStatus = sanitize_key((string) ($current['status'] ?? ''));
    if ($currentStatus === 'paid') {
        return new WP_REST_Response(['error' => 'already_paid'], 409);
    }

    if ($currentStatus === 'payment_pending' && !empty($current['stripe_checkout_url'])) {
        return new WP_REST_Response([
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
            'status' => 'payment_pending',
            'stripe_checkout_url' => esc_url_raw((string) ($current['stripe_checkout_url'] ?? '')),
            'idempotent_replay' => true,
        ], 200);
    }

    if ($currentStatus !== 'closed') {
        return new WP_REST_Response(['error' => 'needs_close_first'], 409);
    }

    $bidder = impactshop_event_auction_get_bidder((string) ($current['bidder_uuid'] ?? ''));
    if (!$bidder) {
        return new WP_REST_Response(['error' => 'bidder_not_found'], 404);
    }

    $returnUrl = esc_url_raw((string) ($payload['return_url'] ?? ($campaign['hero_url'] ?? $campaign['share_url'] ?? home_url('/'))));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $session = impactshop_event_auction_create_winner_checkout_session([
        'campaign' => $campaign,
        'lot' => $lot,
        'bid' => $current,
        'bidder' => $bidder,
        'return_url' => $returnUrl,
    ]);

    if (!$session || empty($session['id']) || empty($session['url'])) {
        return new WP_REST_Response(['error' => 'stripe_failed'], 502);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $requestedAt = current_time('mysql', true);
    $updated = $wpdb->update(
        $table,
        [
            'status' => 'payment_pending',
            'stripe_session_id' => (string) $session['id'],
            'stripe_checkout_url' => (string) $session['url'],
            'return_url' => $returnUrl,
            'payment_requested_at' => $requestedAt,
        ],
        [
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        ],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_REST_Response(['error' => 'payment_request_update_failed'], 500);
    }

    $actor = wp_get_current_user();
    $actorId = $actor instanceof WP_User ? (string) $actor->user_login : 'admin';
    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'winner_payment_requested', $actorId, [
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'stripe_session_id' => (string) $session['id'],
        'bid_amount' => (int) ($current['bid_amount'] ?? 0),
    ]);

    return new WP_REST_Response([
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'status' => 'payment_pending',
        'payment_requested_at' => mysql2date('c', $requestedAt, false),
        'stripe_checkout_url' => (string) $session['url'],
    ], 200);
}

function impactshop_event_auction_create_winner_checkout_session(array $order): ?array
{
    $campaign = (array) ($order['campaign'] ?? []);
    $lot = (array) ($order['lot'] ?? []);
    $bid = (array) ($order['bid'] ?? []);
    $bidder = (array) ($order['bidder'] ?? []);

    $bidUuid = sanitize_text_field((string) ($bid['bid_uuid'] ?? ''));
    $campaignSlug = sanitize_title((string) ($campaign['slug'] ?? ''));
    $currency = strtolower((string) ($campaign['currency'] ?? 'huf'));
    $amountDisplay = (float) ((int) ($bid['bid_amount'] ?? 0));
    $amountMinor = impactshop_event_auction_to_minor($amountDisplay, $currency);

    if ($bidUuid === '' || $campaignSlug === '' || $amountMinor <= 0) {
        return null;
    }

    if (impactshop_event_auction_is_staging_runtime() && impactshop_event_auction_stripe_mode() === 'live') {
        error_log('[impactshop-event-auction] Refusing to create live Stripe checkout session on staging runtime.');
        return null;
    }

    $successUrl = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', rest_url('impact/v1/event-auctions/success'));
    $cancelUrl = add_query_arg('bid_uuid', rawurlencode($bidUuid), rest_url('impact/v1/event-auctions/cancel'));

    $productName = sprintf('%s – aukcios nyertes fizetes', (string) ($campaign['title'] ?? 'Sharity aukcio'));
    $productDescription = trim((string) ($lot['artist_name'] ?? '') . ' - ' . (string) ($lot['item_title'] ?? ''));
    if ($productDescription === '-') {
        $productDescription = 'Aukcios tetel';
    }

    $payload = [
        'mode' => 'payment',
        'payment_method_types[0]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][unit_amount]' => $amountMinor,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDescription,
        'line_items[0][quantity]' => 1,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata[event_auction_bid_uuid]' => $bidUuid,
        'metadata[campaign_slug]' => $campaignSlug,
        'metadata[item_slug]' => sanitize_title((string) ($lot['item_slug'] ?? '')),
        'metadata[flow]' => 'event_auction_winner_payment',
        'metadata[bid_amount]' => (string) ((int) ($bid['bid_amount'] ?? 0)),
        'metadata[currency]' => $currency,
    ];

    $displayName = sanitize_text_field((string) ($bidder['display_name'] ?? ''));
    if ($displayName !== '') {
        $payload['customer_creation'] = 'always';
        $payload['metadata[bidder_name]'] = $displayName;
    }

    $email = sanitize_email((string) ($bidder['email'] ?? ''));
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
        error_log('[impactshop-event-auction] Stripe checkout request failed: ' . $response->get_error_message());
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        $requestId = (string) wp_remote_retrieve_header($response, 'request-id');
        error_log('[impactshop-event-auction] Stripe checkout response error: code=' . $code . ' request_id=' . $requestId . ' body=' . substr($body, 0, 500));
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

function impactshop_event_auction_mark_payment_expired(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));
    if ($bidUuid === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
    if (!$row || sanitize_key((string) ($row['status'] ?? '')) !== 'payment_pending') {
        return;
    }

    $wpdb->update(
        $table,
        ['status' => 'closed'],
        ['bid_uuid' => $bidUuid, 'status' => 'payment_pending'],
        ['%s'],
        ['%s', '%s']
    );

    impactshop_event_auction_log_event(
        sanitize_title((string) ($row['campaign_slug'] ?? '')),
        sanitize_title((string) ($row['item_slug'] ?? '')),
        'winner_payment_expired',
        'stripe_webhook',
        [
            'bid_uuid' => $bidUuid,
            'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        ]
    );
}

function impactshop_event_auction_fulfill_payment(string $bidUuid, array $stripeData = []): void
{
    if ($bidUuid === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
    if (!$row || sanitize_key((string) ($row['status'] ?? '')) === 'paid') {
        return;
    }

    $wpdb->query('START TRANSACTION');
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bid_uuid = %s FOR UPDATE",
            $bidUuid
        ),
        ARRAY_A
    );

    if (!$row || sanitize_key((string) ($row['status'] ?? '')) === 'paid') {
        $wpdb->query('ROLLBACK');
        return;
    }

    $ok = $wpdb->update(
        $table,
        [
            'status' => 'paid',
            'stripe_session_id' => sanitize_text_field((string) ($stripeData['stripe_session_id'] ?? ($row['stripe_session_id'] ?? ''))),
            'stripe_payment_intent' => sanitize_text_field((string) ($stripeData['stripe_payment_intent'] ?? ($row['stripe_payment_intent'] ?? ''))),
            'payment_completed_at' => current_time('mysql', true),
        ],
        [
            'bid_uuid' => $bidUuid,
        ],
        ['%s', '%s', '%s', '%s'],
        ['%s']
    );

    if ($ok === false) {
        $wpdb->query('ROLLBACK');
        return;
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event(
        sanitize_title((string) ($row['campaign_slug'] ?? '')),
        sanitize_title((string) ($row['item_slug'] ?? '')),
        'winner_payment_completed',
        'stripe_webhook',
        [
            'bid_uuid' => $bidUuid,
            'stripe_session_id' => sanitize_text_field((string) ($stripeData['stripe_session_id'] ?? '')),
            'stripe_payment_intent' => sanitize_text_field((string) ($stripeData['stripe_payment_intent'] ?? '')),
        ]
    );
}

function impactshop_event_auction_maybe_fulfill_from_session(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));
    if ($bidUuid === '') {
        return;
    }

    if (!impactshop_event_auction_is_paid_session($session)) {
        return;
    }

    impactshop_event_auction_fulfill_payment($bidUuid, [
        'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
    ]);
}

function impactshop_event_auction_webhook(): WP_REST_Response
{
    if (!impactshop_event_auction_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload = (string) file_get_contents('php://input');
    $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    $secret = impactshop_event_auction_webhook_secret();
    if ($secret === '' || !impactshop_event_auction_verify_stripe_signature($payload, $sigHeader, $secret)) {
        return new WP_REST_Response(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }

    $eventType = (string) ($event['type'] ?? '');
    $object = (array) (($event['data']['object'] ?? []) ?: []);

    if ($eventType === 'checkout.session.completed') {
        impactshop_event_auction_maybe_fulfill_from_session($object);
    } elseif ($eventType === 'checkout.session.expired') {
        impactshop_event_auction_mark_payment_expired($object);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_event_auction_success(WP_REST_Request $request)
{
    $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
    $bidUuid = '';

    if ($sessionId !== '') {
        $session = impactshop_event_auction_fetch_stripe_session($sessionId);
        if (is_array($session)) {
            $metadata = (array) ($session['metadata'] ?? []);
            $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));

            if ($bidUuid === '') {
                $row = impactshop_event_auction_get_bid_by_session_id($sessionId);
                if ($row && !empty($row['bid_uuid'])) {
                    $bidUuid = sanitize_text_field((string) $row['bid_uuid']);
                }
            }

            if ($bidUuid !== '' && impactshop_event_auction_is_paid_session($session)) {
                impactshop_event_auction_fulfill_payment($bidUuid, [
                    'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
                    'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
                ]);
            }
        }
    }

    return impactshop_event_auction_redirect_result('success', $bidUuid);
}

function impactshop_event_auction_cancel(WP_REST_Request $request)
{
    $bidUuid = sanitize_text_field((string) $request->get_param('bid_uuid'));

    if ($bidUuid !== '') {
        global $wpdb;
        $table = impactshop_event_auction_bids_table_name();
        $wpdb->update(
            $table,
            ['status' => 'closed'],
            ['bid_uuid' => $bidUuid, 'status' => 'payment_pending'],
            ['%s'],
            ['%s', '%s']
        );

        $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
        if ($row) {
            impactshop_event_auction_log_event(
                sanitize_title((string) ($row['campaign_slug'] ?? '')),
                sanitize_title((string) ($row['item_slug'] ?? '')),
                'winner_payment_cancelled',
                'stripe_cancel',
                ['bid_uuid' => $bidUuid]
            );
        }
    }

    return impactshop_event_auction_redirect_result('cancel', $bidUuid);
}

function impactshop_event_auction_format_amount(float $amount, string $currency): string
{
    $currency = strtolower($currency);
    if ($currency === 'huf') {
        return number_format((int) round($amount, 0), 0, ',', ' ') . ' Ft';
    }

    return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($currency);
}