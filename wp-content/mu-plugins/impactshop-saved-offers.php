<?php
/**
 * Plugin Name: ImpactShop Saved Offers
 * Description: Mentett affiliate ajánlatok a videós reklámokhoz és a profiloldalhoz.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_SAVED_OFFERS_SCHEMA = '2026-03-26-01';
const IMPACTSHOP_SAVED_OFFERS_OPTION_SCHEMA = 'impactshop_saved_offers_schema_version';
const IMPACTSHOP_SAVED_OFFERS_TTL_DAYS = 30;

add_action('muplugins_loaded', 'impactshop_saved_offers_bootstrap');

function impactshop_saved_offers_bootstrap(): void
{
    impactshop_saved_offers_maybe_install();
    add_action('rest_api_init', 'impactshop_saved_offers_register_routes');
    add_action('wp_enqueue_scripts', 'impactshop_saved_offers_enqueue_assets');
}

function impactshop_saved_offers_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_SAVED_OFFERS_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_SAVED_OFFERS_SCHEMA) {
        return;
    }

    global $wpdb;
    $table = impactshop_saved_offers_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(64) NOT NULL,
        content_type VARCHAR(40) NOT NULL DEFAULT 'offer',
        content_id VARCHAR(128) DEFAULT '',
        ngo_slug VARCHAR(190) DEFAULT '',
        shop_slug VARCHAR(190) DEFAULT '',
        offer_title VARCHAR(255) DEFAULT '',
        image_url TEXT NULL,
        affiliate_url TEXT NOT NULL,
        original_url TEXT NULL,
        network VARCHAR(32) DEFAULT '',
        category VARCHAR(190) DEFAULT '',
        price_label VARCHAR(190) DEFAULT '',
        source_page VARCHAR(32) DEFAULT 'ads_watch',
        first_saved_at DATETIME NOT NULL,
        last_saved_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        reopen_click_count INT UNSIGNED NOT NULL DEFAULT 0,
        first_reopened_at DATETIME NULL,
        last_reopened_at DATETIME NULL,
        purchase_detected_at DATETIME NULL,
        purchase_ledger_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_offer (pseudo_id, content_type, content_id, shop_slug),
        KEY idx_pseudo_exp (pseudo_id, expires_at),
        KEY idx_shop (shop_slug),
        KEY idx_purchase (purchase_detected_at),
        KEY idx_last_saved (last_saved_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option(IMPACTSHOP_SAVED_OFFERS_OPTION_SCHEMA, IMPACTSHOP_SAVED_OFFERS_SCHEMA, false);
}

function impactshop_saved_offers_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_saved_offers';
}

function impactshop_saved_offers_register_routes(): void
{
    register_rest_route('impact/v1', '/saved-offers', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_saved_offers_list',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/saved-offers/save', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_saved_offers_save',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/saved-offers/open/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_saved_offers_open',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'validate_callback' => static function ($value): bool {
                    return is_numeric($value) && (int) $value > 0;
                },
            ],
        ],
    ]);
}

function impactshop_saved_offers_enqueue_assets(): void
{
    if (is_admin()) {
        return;
    }

    wp_register_script(
        'impactshop-saved-offers',
        plugins_url('impactshop-saved-offers.js', __FILE__),
        [],
        '1.0.2',
        true
    );

    $css = <<<CSS
.impactshop-saved-offers-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:12px; padding:12px 14px; border-radius:14px; background:rgba(15,23,42,.16); }
.impactshop-saved-offers-btn { appearance:none; border:1px solid rgba(56,189,248,.28); background:linear-gradient(180deg,#e0f2fe 0%,#bae6fd 100%); color:#0f172a; border-radius:999px; padding:11px 18px; font-weight:800; font-size:14px; line-height:1.2; cursor:pointer; box-shadow:0 10px 24px rgba(14,165,233,.18); }
.impactshop-saved-offers-btn:hover { background:linear-gradient(180deg,#bae6fd 0%,#7dd3fc 100%); }
.impactshop-saved-offers-btn[disabled] { opacity:1; cursor:default; box-shadow:none; color:#cbd5e1; background:linear-gradient(180deg,rgba(51,65,85,.9) 0%,rgba(30,41,59,.95) 100%); border-color:rgba(148,163,184,.28); }
.impactshop-saved-offers-status { font-size:13px; color:#475569; min-height:18px; }
.impactshop-saved-offers-actions .impactshop-saved-offers-status { color:#e2e8f0; }
.impactshop-saved-offers-status.is-error { color:#b91c1c; }
.impactshop-saved-offers-status.is-success { color:#0f766e; }
.impactshop-saved-offers-block { margin-top:18px; }
.impactshop-saved-offers-list { list-style:none; padding:0; margin:12px 0 0; display:grid; gap:12px; }
.impactshop-saved-offers-card { display:grid; grid-template-columns:72px minmax(0,1fr); gap:14px; padding:14px; border:1px solid rgba(148,163,184,.28); border-radius:16px; background:rgba(255,255,255,.74); }
.impactshop-saved-offers-card img { width:72px; height:72px; object-fit:cover; border-radius:12px; background:#f8fafc; }
.impactshop-saved-offers-card h5 { margin:0 0 6px; font-size:15px; color:#0f172a; }
.impactshop-saved-offers-meta { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
.impactshop-saved-offers-chip { display:inline-flex; align-items:center; border-radius:999px; padding:4px 9px; font-size:11px; font-weight:700; background:#e2e8f0; color:#334155; }
.impactshop-saved-offers-chip.is-purchased { background:#dcfce7; color:#166534; }
.impactshop-saved-offers-chip.is-clicked { background:#dbeafe; color:#1d4ed8; }
.impactshop-saved-offers-sub { font-size:12px; color:#64748b; line-height:1.5; }
.impactshop-saved-offers-link { display:inline-flex; align-items:center; justify-content:center; margin-top:10px; padding:10px 14px; border-radius:12px; border:0; background:#0f172a; color:#fff; text-decoration:none; font-weight:700; width:max-content; }
.impactshop-saved-offers-link:hover { background:#1e293b; color:#fff; }
@media (max-width: 640px) {
  .impactshop-saved-offers-card { grid-template-columns:1fr; }
}
CSS;

    wp_register_style('impactshop-saved-offers', false);
    wp_add_inline_style('impactshop-saved-offers', $css);

    wp_localize_script('impactshop-saved-offers', 'impactshopSavedOffers', [
        'restBase' => esc_url_raw(rest_url('impact/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
        'profileUrl' => esc_url_raw(apply_filters('impactshop_identity_panel_url', site_url('/profil'))),
        'strings' => [
            'saveLabel' => 'Ajánlat mentése',
            'saveSuccess' => 'Az ajánlat elmentve a profilodba 30 napra.',
            'saveExists' => 'Ezt az ajánlatot már elmentetted, frissítettem a 30 napos határidőt.',
            'saveError' => 'Most nem sikerült elmenteni az ajánlatot.',
            'empty' => 'Nincs mentett ajánlatod az elmúlt 30 napból.',
            'sectionTitle' => 'Mentett ajánlataim',
            'openLabel' => 'Megnyitom újra',
            'ctaNoReward' => 'Mentésért nem jár jutalom.',
        ],
    ]);

    wp_enqueue_style('impactshop-saved-offers');
    wp_enqueue_script('impactshop-saved-offers');
}

function impactshop_saved_offers_list(): WP_REST_Response
{
    $pseudo_id = impactshop_saved_offers_current_pseudo();
    if ($pseudo_id === '') {
        return impactshop_saved_offers_no_store_response(['items' => []]);
    }

    impactshop_saved_offers_cleanup_expired($pseudo_id);

    global $wpdb;
    $table = impactshop_saved_offers_table();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE pseudo_id = %s
               AND expires_at >= %s
             ORDER BY last_saved_at DESC, id DESC
             LIMIT 100",
            $pseudo_id,
            current_time('mysql')
        ),
        ARRAY_A
    );

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = impactshop_saved_offers_enrich_row($row, true);
    }

    return impactshop_saved_offers_no_store_response(['items' => $items]);
}

function impactshop_saved_offers_save(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_saved_offers_current_pseudo();
    if ($pseudo_id === '') {
        return impactshop_saved_offers_no_store_response([
            'status' => 'missing_identity',
            'message' => 'Nincs azonosított profil.',
        ], 200);
    }

    impactshop_saved_offers_cleanup_expired($pseudo_id);

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_body_params();
    }

    $affiliate_url = impactshop_saved_offers_sanitize_url((string) ($params['affiliate_url'] ?? ''));
    $original_url = impactshop_saved_offers_sanitize_url((string) ($params['original_url'] ?? ''));
    $content_type = sanitize_key((string) ($params['content_type'] ?? 'offer'));
    $content_id = sanitize_text_field((string) ($params['content_id'] ?? ''));
    $offer_title = sanitize_text_field((string) ($params['offer_title'] ?? ''));
    $shop_slug = sanitize_title((string) ($params['shop_slug'] ?? ''));
    $category = sanitize_text_field((string) ($params['category'] ?? ''));
    $price_label = sanitize_text_field((string) ($params['price_label'] ?? ''));
    $image_url = impactshop_saved_offers_sanitize_url((string) ($params['image_url'] ?? ''));
    $network = sanitize_key((string) ($params['network'] ?? ''));
    $source_page = sanitize_key((string) ($params['source_page'] ?? 'ads_watch'));

    if ($affiliate_url === '') {
        return impactshop_saved_offers_no_store_response([
            'status' => 'invalid',
            'message' => 'Hiányzó affiliate link.',
        ], 400);
    }

    if ($shop_slug === '') {
        $shop_slug = impactshop_saved_offers_extract_shop_slug($affiliate_url, $original_url);
    }
    if ($network === '') {
        $network = impactshop_saved_offers_detect_network($affiliate_url, $shop_slug);
    }

    $ngo_slug = sanitize_title((string) ($params['ngo_slug'] ?? ''));
    if ($ngo_slug === '') {
        $ngo_slug = impactshop_saved_offers_extract_ngo_slug($affiliate_url);
    }

    $now = current_time('mysql');
    $expires_at = date('Y-m-d H:i:s', current_time('timestamp') + (DAY_IN_SECONDS * IMPACTSHOP_SAVED_OFFERS_TTL_DAYS));

    global $wpdb;
    $table = impactshop_saved_offers_table();
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE pseudo_id = %s
               AND content_type = %s
               AND content_id = %s
               AND shop_slug = %s
             LIMIT 1",
            $pseudo_id,
            $content_type,
            $content_id,
            $shop_slug
        ),
        ARRAY_A
    );

    $payload = [
        'ngo_slug' => $ngo_slug,
        'shop_slug' => $shop_slug,
        'offer_title' => $offer_title,
        'image_url' => $image_url,
        'affiliate_url' => $affiliate_url,
        'original_url' => $original_url,
        'network' => $network,
        'category' => $category,
        'price_label' => $price_label,
        'source_page' => $source_page,
        'last_saved_at' => $now,
        'expires_at' => $expires_at,
        'updated_at' => $now,
    ];

    $status = 'saved';
    if ($existing) {
        $wpdb->update(
            $table,
            $payload,
            ['id' => (int) $existing['id']],
            ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'],
            ['%d']
        );
        $saved_id = (int) $existing['id'];
        $status = 'updated';
    } else {
        $insert = array_merge($payload, [
            'pseudo_id' => $pseudo_id,
            'content_type' => $content_type,
            'content_id' => $content_id,
            'first_saved_at' => $now,
            'created_at' => $now,
        ]);
        $wpdb->insert(
            $table,
            $insert,
            ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
        );
        $saved_id = (int) $wpdb->insert_id;
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $saved_id), ARRAY_A);
    $item = $row ? impactshop_saved_offers_enrich_row($row, true) : null;

    return impactshop_saved_offers_no_store_response([
        'status' => $status,
        'item' => $item,
    ]);
}

function impactshop_saved_offers_open(WP_REST_Request $request)
{
    $pseudo_id = impactshop_saved_offers_current_pseudo();
    if ($pseudo_id === '') {
        return impactshop_saved_offers_redirect_fallback(home_url('/profil'));
    }

    impactshop_saved_offers_cleanup_expired($pseudo_id);

    $id = (int) $request['id'];
    if ($id <= 0) {
        return impactshop_saved_offers_redirect_fallback(home_url('/profil'));
    }

    global $wpdb;
    $table = impactshop_saved_offers_table();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id = %d
               AND pseudo_id = %s
               AND expires_at >= %s
             LIMIT 1",
            $id,
            $pseudo_id,
            current_time('mysql')
        ),
        ARRAY_A
    );

    if (!$row || empty($row['affiliate_url'])) {
        return impactshop_saved_offers_redirect_fallback(home_url('/profil'));
    }

    $now = current_time('mysql');
    $updates = [
        'reopen_click_count' => (int) ($row['reopen_click_count'] ?? 0) + 1,
        'last_reopened_at' => $now,
        'updated_at' => $now,
    ];
    if (empty($row['first_reopened_at'])) {
        $updates['first_reopened_at'] = $now;
    }
    $wpdb->update(
        $table,
        $updates,
        ['id' => (int) $row['id']],
        ['%d','%s','%s','%s'],
        ['%d']
    );

    return impactshop_saved_offers_redirect_fallback((string) $row['affiliate_url']);
}

function impactshop_saved_offers_redirect_fallback(string $target)
{
    $url = impactshop_saved_offers_sanitize_url($target);
    if ($url === '') {
        $url = home_url('/profil');
        wp_safe_redirect($url, 302, 'ImpactShop Saved Offers');
        exit;
    }
    wp_redirect($url, 302, 'ImpactShop Saved Offers');
    exit;
}

function impactshop_saved_offers_enrich_row(array $row, bool $allow_update = false): array
{
    $purchase = impactshop_saved_offers_detect_purchase_for_row($row);
    if ($allow_update && $purchase['purchase_detected_at'] !== null && empty($row['purchase_detected_at'])) {
        global $wpdb;
        $wpdb->update(
            impactshop_saved_offers_table(),
            [
                'purchase_detected_at' => $purchase['purchase_detected_at'],
                'purchase_ledger_id' => $purchase['purchase_ledger_id'],
                'updated_at' => current_time('mysql'),
            ],
            ['id' => (int) $row['id']],
            ['%s','%d','%s'],
            ['%d']
        );
        $row['purchase_detected_at'] = $purchase['purchase_detected_at'];
        $row['purchase_ledger_id'] = $purchase['purchase_ledger_id'];
    }

    return [
        'id' => (int) $row['id'],
        'offer_title' => (string) ($row['offer_title'] ?? ''),
        'image_url' => (string) ($row['image_url'] ?? ''),
        'affiliate_url' => (string) ($row['affiliate_url'] ?? ''),
        'original_url' => (string) ($row['original_url'] ?? ''),
        'shop_slug' => (string) ($row['shop_slug'] ?? ''),
        'ngo_slug' => (string) ($row['ngo_slug'] ?? ''),
        'network' => (string) ($row['network'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'price_label' => (string) ($row['price_label'] ?? ''),
        'saved_at' => (string) ($row['last_saved_at'] ?? ''),
        'expires_at' => (string) ($row['expires_at'] ?? ''),
        'reopen_click_count' => (int) ($row['reopen_click_count'] ?? 0),
        'first_reopened_at' => (string) ($row['first_reopened_at'] ?? ''),
        'last_reopened_at' => (string) ($row['last_reopened_at'] ?? ''),
        'purchase_detected_at' => (string) ($row['purchase_detected_at'] ?? ''),
        'purchase_ledger_id' => (int) ($row['purchase_ledger_id'] ?? 0),
        'open_url' => esc_url_raw(rest_url('impact/v1/saved-offers/open/' . (int) $row['id'])),
    ];
}

function impactshop_saved_offers_detect_purchase_for_row(array $row): array
{
    $existing_at = (string) ($row['purchase_detected_at'] ?? '');
    $existing_id = (int) ($row['purchase_ledger_id'] ?? 0);
    if ($existing_at !== '') {
        return [
            'purchase_detected_at' => $existing_at,
            'purchase_ledger_id' => $existing_id,
        ];
    }

    $pseudo_id = sanitize_text_field((string) ($row['pseudo_id'] ?? ''));
    $shop_slug = sanitize_title((string) ($row['shop_slug'] ?? ''));
    $saved_at = (string) ($row['first_saved_at'] ?? $row['last_saved_at'] ?? '');
    if ($pseudo_id === '' || $shop_slug === '' || $saved_at === '') {
        return [
            'purchase_detected_at' => null,
            'purchase_ledger_id' => null,
        ];
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $like_primary = '%' . $wpdb->esc_like('"shop_slug":"' . $shop_slug . '"') . '%';
    $like_alt = '%' . $wpdb->esc_like('"shop":"' . $shop_slug . '"') . '%';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, created_at
             FROM {$table}
             WHERE status = 'approved'
               AND created_at >= %s
               AND (
                    advertiser_code = %s
                    OR meta LIKE %s
                    OR meta LIKE %s
               )
               AND (
                    meta LIKE %s
                    OR meta LIKE %s
               )
             ORDER BY created_at ASC, id ASC
             LIMIT 1",
            $saved_at,
            $shop_slug,
            $like_primary,
            $like_alt,
            '%' . $wpdb->esc_like('"pseudo_id":"' . $pseudo_id . '"') . '%',
            '%' . $wpdb->esc_like('"impact_pseudo":"' . $pseudo_id . '"') . '%'
        ),
        ARRAY_A
    );

    if (!$row) {
        return [
            'purchase_detected_at' => null,
            'purchase_ledger_id' => null,
        ];
    }

    return [
        'purchase_detected_at' => (string) $row['created_at'],
        'purchase_ledger_id' => (int) $row['id'],
    ];
}

function impactshop_saved_offers_cleanup_expired(string $pseudo_id = ''): void
{
    global $wpdb;
    $table = impactshop_saved_offers_table();
    $now = current_time('mysql');
    if ($pseudo_id !== '') {
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE pseudo_id = %s AND expires_at < %s", $pseudo_id, $now));
        return;
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s", $now));
}

function impactshop_saved_offers_current_pseudo(): string
{
    if (function_exists('impactshop_identity_profile_cookie')) {
        $pseudo = (string) impactshop_identity_profile_cookie();
        if ($pseudo !== '') {
            return strtolower($pseudo);
        }
    }

    if (!empty($_COOKIE['impactshop_pseudo_id'])) {
        return strtolower(sanitize_text_field((string) wp_unslash($_COOKIE['impactshop_pseudo_id'])));
    }

    return '';
}

function impactshop_saved_offers_sanitize_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return '';
    }
    return esc_url_raw($url, ['http', 'https']);
}

function impactshop_saved_offers_extract_shop_slug(string $affiliate_url, string $original_url = ''): string
{
    foreach ([$affiliate_url, $original_url] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $parts = wp_parse_url($candidate);
        if (!is_array($parts)) {
            continue;
        }
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('#/go(?:-deal)?/([^/?#]+)#i', $path, $m)) {
            return sanitize_title($m[1]);
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host !== '' && !in_array($host, ['app.sharity.hu', 'sharity.hu', 'www.sharity.hu'], true)) {
            return sanitize_title(preg_replace('/\.[a-z.]+$/i', '', $host));
        }
    }
    return '';
}

function impactshop_saved_offers_extract_ngo_slug(string $affiliate_url): string
{
    $parts = wp_parse_url($affiliate_url);
    if (!is_array($parts)) {
        return '';
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    if (!empty($query['d1'])) {
        return sanitize_title((string) $query['d1']);
    }

    if (!empty($query['sid'])) {
        $sid = (string) $query['sid'];
        $pieces = preg_split('/[~|:]/', $sid);
        if (!empty($pieces[0])) {
            return sanitize_title((string) $pieces[0]);
        }
    }

    return '';
}

function impactshop_saved_offers_detect_network(string $affiliate_url, string $shop_slug): string
{
    $parts = wp_parse_url($affiliate_url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (strpos($shop_slug, 'cj-') === 0 || strpos($host, 'jdoqocy.com') !== false || strpos($host, 'cj.com') !== false) {
        return 'cj';
    }
    if (strpos($host, 'dognet') !== false) {
        return 'dognet';
    }
    if (strpos($shop_slug, 'arukereso') !== false || strpos($host, 'arukereso') !== false) {
        return 'arukereso';
    }
    return 'direct';
}

function impactshop_saved_offers_no_store_response(array $payload, int $status = 200): WP_REST_Response
{
    $response = new WP_REST_Response($payload, $status);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    $response->header('Pragma', 'no-cache');
    $response->header('Vary', 'Cookie');
    return $response;
}
