<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_AUTO_BANNER_SCHEMA = '1.0.0';
const IMPACTSHOP_AUTO_BANNER_OPTION_SCHEMA = 'impactshop_auto_banner_schema_version';
const IMPACTSHOP_AUTO_BANNER_DEFAULT_TTL_DAYS = 7;
const IMPACTSHOP_AUTO_BANNER_ADD_RATE_LIMIT = 30;
const IMPACTSHOP_AUTO_BANNER_ADD_RATE_WINDOW = 60;
const IMPACTSHOP_AUTO_BANNER_CLEANUP_OPTION = 'impactshop_auto_banner_cleanup_v1';
const IMPACTSHOP_AUTO_BANNER_FEED_IMPORT_OPTION = 'impactshop_auto_banner_feed_import_v1';
const IMPACTSHOP_AUTO_BANNER_FEED_CRON = 'impactshop_auto_banner_feed_import_cron';
const IMPACTSHOP_AUTO_BANNER_SEEN_TRANSIENT_PREFIX = 'impactshop_auto_banner_seen_';
const IMPACTSHOP_AUTO_BANNER_SEEN_TTL = 14 * DAY_IN_SECONDS;

add_action('muplugins_loaded', 'impactshop_auto_banner_boot');
add_action('cli_init', 'impactshop_auto_banner_register_cli');

function impactshop_auto_banner_boot(): void
{
    impactshop_auto_banner_maybe_install();
    add_action('impactshop_harvester_offer_saved', 'impactshop_auto_banner_from_offer', 10, 2);
    add_action('rest_api_init', 'impactshop_auto_banner_register_routes');
    add_action('admin_menu', 'impactshop_auto_banner_admin_menu');
    impactshop_auto_banner_schedule_cleanup();
    impactshop_auto_banner_schedule_feed_import();
    impactshop_auto_banner_schedule_whitelist_cleanup();
    if (defined('WP_CLI') && WP_CLI) {
        impactshop_auto_banner_register_cli();
    }
}

function impactshop_auto_banner_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_AUTO_BANNER_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_AUTO_BANNER_SCHEMA) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        status VARCHAR(20) DEFAULT 'pending',
        title VARCHAR(255) NOT NULL,
        image_url TEXT,
        shop_slug VARCHAR(190) DEFAULT '',
        banner_url TEXT,
        price_old DECIMAL(10,2) DEFAULT 0.00,
        price_new DECIMAL(10,2) DEFAULT 0.00,
        discount_percent INT DEFAULT 0,
        priority INT DEFAULT 0,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_status_time (status, starts_at, ends_at),
        KEY idx_shop (shop_slug),
        KEY idx_priority (priority)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option(IMPACTSHOP_AUTO_BANNER_OPTION_SCHEMA, IMPACTSHOP_AUTO_BANNER_SCHEMA, false);
}

function impactshop_auto_banner_register_routes(): void
{
    register_rest_route('impact/v1', '/auto-banner/next', [
        'methods' => 'GET',
        'callback' => 'impactshop_auto_banner_next',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/auto-banner/add', [
        'methods' => 'POST',
        'callback' => 'impactshop_auto_banner_add',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);
}

function impactshop_auto_banner_next(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_auto_banner_normalize_pseudo_id((string) $request->get_param('pseudo_id'));
    $banner = impactshop_auto_banner_get_active($pseudo_id);
    return new WP_REST_Response(['banner' => $banner], 200);
}

function impactshop_auto_banner_add(WP_REST_Request $request): WP_REST_Response
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = 'impactshop_auto_banner_add_' . md5($ip);
    $rate = (int) get_transient($rate_key);
    if ($rate >= IMPACTSHOP_AUTO_BANNER_ADD_RATE_LIMIT) {
        return new WP_REST_Response(['error' => 'rate_limited'], 429);
    }
    set_transient($rate_key, $rate + 1, IMPACTSHOP_AUTO_BANNER_ADD_RATE_WINDOW);

    $payload = $request->get_json_params();
    if (!is_array($payload)) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }
    $offer = impactshop_auto_banner_normalize_offer($payload);
    if ($offer === null) {
        return new WP_REST_Response(['error' => 'invalid_offer'], 422);
    }
    impactshop_auto_banner_from_offer($offer, ['source' => 'rest', 'status' => 'pending']);
    return new WP_REST_Response(['ok' => true], 200);
}

function impactshop_is_whitelisted_partner(string $shop_slug): bool
{
    static $whitelist = null;
    if (strpos($shop_slug, 'sync:') === 0) {
        $shop_slug = substr($shop_slug, 5);
    }

    if ($whitelist !== null && !empty($whitelist)) {
        $normalized = strtolower(trim($shop_slug));
        return $normalized !== '' && isset($whitelist[$normalized]);
    }

    $whitelist = [];
    $registry_path = dirname(WP_CONTENT_DIR) . '/tools/shops_registry.json';
    if (file_exists($registry_path)) {
        $raw = file_get_contents($registry_path);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                foreach ($data as $shop) {
                    if (is_array($shop) && !empty($shop['slug'])) {
                        $slug = strtolower(trim((string) $shop['slug']));
                        if ($slug !== '') {
                            $whitelist[$slug] = true;
                        }
                    }
                }
            }
        }
    }

    if (empty($whitelist) && function_exists('impactshop_get_shops')) {
        $shops = impactshop_get_shops();
        foreach ($shops as $shop) {
            if (!empty($shop['shop_slug'])) {
                $slug = strtolower(trim((string) $shop['shop_slug']));
                if ($slug !== '') {
                    $whitelist[$slug] = true;
                }
            }
        }
    }

    $normalized = strtolower(trim($shop_slug));
    return $normalized !== '' && isset($whitelist[$normalized]);
}

function impactshop_auto_banner_registry_domains(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    $registry_path = dirname(WP_CONTENT_DIR) . '/tools/shops_registry.json';
    if (!file_exists($registry_path)) {
        return $map;
    }
    $raw = file_get_contents($registry_path);
    if ($raw === false) {
        return $map;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $map;
    }
    foreach ($data as $shop) {
        if (!is_array($shop)) {
            continue;
        }
        $slug = strtolower(trim((string) ($shop['slug'] ?? '')));
        $domain = strtolower(trim((string) ($shop['domain'] ?? '')));
        if ($slug === '' || $domain === '') {
            continue;
        }
        $map[$slug] = $domain;
    }
    return $map;
}

/**
 * Validate that URL is a plausible product URL, not a DTD/asset/system URL.
 * Blocks W3C DTDs, XML schemas, and other non-product links.
 */
function impactshop_is_valid_product_url(string $url): bool
{
    if ($url === '') {
        return false;
    }

    // Must be http(s)
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }

    // Blocklist patterns (DTD, XML schema, system URLs)
    $blocked_patterns = [
        '#^https?://(www\.)?w3\.org/#i',
        '#^https?://[^/]*\.dtd#i',
        '#/DTD/#i',
        '#\.xsd$#i',
        '#\.dtd$#i',
        '#xmlns#i',
    ];

    foreach ($blocked_patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return false;
        }
    }

    return true;
}

function impactshop_auto_banner_host_matches(string $host, string $allowed): bool
{
    $host = strtolower($host);
    $allowed = strtolower($allowed);
    if ($host === $allowed) {
        return true;
    }
    return substr($host, -strlen('.' . $allowed)) === '.' . $allowed;
}

function impactshop_auto_banner_get_allowed_hosts(string $shop_slug): array
{
    $hosts = [];
    if (strpos($shop_slug, 'sync:') === 0) {
        $shop_slug = substr($shop_slug, 5);
    }
    if (function_exists('impactshop_find_shop')) {
        $row = impactshop_find_shop($shop_slug);
        $product_url = $row['product_url'] ?? ($row['homepage'] ?? '');
        $product_host = $product_url ? parse_url($product_url, PHP_URL_HOST) : '';
        if ($product_host) {
            $hosts[] = $product_host;
        }
    }

    $registry = impactshop_auto_banner_registry_domains();
    $registry_domain = $registry[strtolower(trim($shop_slug))] ?? '';
    if ($registry_domain !== '') {
        $hosts[] = $registry_domain;
        $hosts[] = 'www.' . $registry_domain;
    }

    $filtered = (array) apply_filters('impactshop_auto_banner_allowed_hosts', [], $shop_slug);
    foreach ($filtered as $host) {
        if ($host) {
            $hosts[] = $host;
        }
    }

    $hosts = array_filter(array_unique($hosts));
    return array_values($hosts);
}

function impactshop_auto_banner_is_valid_banner_url(string $banner_url, string $shop_slug): bool
{
    if (!impactshop_is_valid_product_url($banner_url)) {
        return false;
    }

    $host = parse_url($banner_url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }

    if (stripos($host, 'go.dognet.com') !== false) {
        $query = parse_url($banner_url, PHP_URL_QUERY) ?: '';
        parse_str($query, $qs);
        $deeplink = (string) ($qs['url'] ?? '');
        if ($deeplink === '') {
            return false;
        }
        $deeplink_host = parse_url($deeplink, PHP_URL_HOST);
        if ($deeplink_host === '') {
            return false;
        }
        $allowed_hosts = impactshop_auto_banner_get_allowed_hosts($shop_slug);
        if (empty($allowed_hosts)) {
            return false;
        }
        foreach ($allowed_hosts as $allowed) {
            if (impactshop_auto_banner_host_matches($deeplink_host, $allowed)) {
                return true;
            }
        }
        return false;
    }

    return true;
}

function impactshop_auto_banner_normalize_pseudo_id(string $pseudo_id): string
{
    $pseudo_id = strtolower(trim($pseudo_id));
    if ($pseudo_id === '') {
        return '';
    }
    $pseudo_id = preg_replace('/[^a-z0-9_-]/', '', $pseudo_id);
    return is_string($pseudo_id) ? $pseudo_id : '';
}

function impactshop_auto_banner_seen_transient_key(string $pseudo_id): string
{
    return IMPACTSHOP_AUTO_BANNER_SEEN_TRANSIENT_PREFIX . md5($pseudo_id);
}

function impactshop_auto_banner_get_seen_ids(string $pseudo_id, array $rows): array
{
    if ($pseudo_id !== '') {
        $seen_ids = get_transient(impactshop_auto_banner_seen_transient_key($pseudo_id));
        if (is_array($seen_ids)) {
            return array_values(array_unique(array_map('intval', $seen_ids)));
        }
        return [];
    }

    $seen_cookie = isset($_COOKIE['impactshop_seen_banners']) ? sanitize_text_field($_COOKIE['impactshop_seen_banners']) : '';
    return $seen_cookie !== '' ? array_values(array_unique(array_map('intval', explode(',', $seen_cookie)))) : [];
}

function impactshop_auto_banner_store_seen_ids(string $pseudo_id, array $seen_ids, int $row_count): void
{
    $seen_ids = array_values(array_unique(array_map('intval', $seen_ids)));
    if ($pseudo_id !== '') {
        set_transient(
            impactshop_auto_banner_seen_transient_key($pseudo_id),
            $seen_ids,
            (int) apply_filters('impactshop_auto_banner_seen_ttl', IMPACTSHOP_AUTO_BANNER_SEEN_TTL)
        );
        return;
    }

    // Anonymous fallback remains cookie-based, so it cannot reliably hold thousands of IDs.
    $seen_cap = (int) apply_filters('impactshop_auto_banner_seen_cap', max(100, min($row_count, 500)));
    if ($seen_cap <= 0) {
        $seen_cap = max(100, min($row_count, 500));
    }
    $seen_ids = array_slice($seen_ids, -$seen_cap);
    $cookie_value = implode(',', $seen_ids);
    setcookie('impactshop_seen_banners', $cookie_value, time() + (4 * HOUR_IN_SECONDS), '/', '', true, false);
}

/**
 * Get an active banner with rotation support.
 * Tracks seen banners per pseudo ID so users do not see repeats until the full active pool is exhausted.
 */
function impactshop_auto_banner_get_active(string $pseudo_id = ''): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    $now = current_time('mysql');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'active'
               AND (starts_at IS NULL OR starts_at <= %s)
               AND (ends_at IS NULL OR ends_at >= %s)
             ORDER BY priority DESC, created_at DESC, id DESC",
            $now,
            $now
        ),
        ARRAY_A
    );

    if (empty($rows)) {
        return [];
    }

    $rows = array_values(array_filter($rows, function ($row) {
        $shop_slug = (string) ($row['shop_slug'] ?? '');
        $banner_url = (string) ($row['banner_url'] ?? '');
        return impactshop_auto_banner_is_valid_banner_url($banner_url, $shop_slug);
    }));

    if (empty($rows)) {
        return [];
    }

    $seen_ids = impactshop_auto_banner_get_seen_ids($pseudo_id, $rows);

    $unseen = array_filter($rows, function ($row) use ($seen_ids) {
        return !in_array((int) $row['id'], $seen_ids, true);
    });

    if (empty($unseen)) {
        $seen_ids = [];
        $unseen = $rows;
    }

    $unseen = array_values($unseen);
    $chosen = $unseen[0];

    $seen_ids[] = (int) $chosen['id'];
    impactshop_auto_banner_store_seen_ids($pseudo_id, $seen_ids, count($rows));

    return impactshop_auto_banner_format($chosen);
}

function impactshop_auto_banner_resolve_image(string $image_url, string $shop_slug): string
{
    if ($image_url !== '') {
        return $image_url;
    }

    $shop_slug = sanitize_text_field($shop_slug);
    if ($shop_slug !== '') {
        $filtered = (string) apply_filters('impactshop_auto_banner_logo_url', '', $shop_slug);
        if ($filtered !== '') {
            return $filtered;
        }
        return site_url('/wp-content/uploads/shops/' . $shop_slug . '-logo.png');
    }

    return site_url('/wp-content/uploads/impactshop/ngo-card-default.jpg');
}

function impactshop_auto_banner_format(array $row): array
{
    $image_url = impactshop_auto_banner_resolve_image(
        (string) $row['image_url'],
        (string) $row['shop_slug']
    );

    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'image_url' => $image_url,
        'shop_slug' => (string) $row['shop_slug'],
        'banner_url' => (string) $row['banner_url'],
        'price_old' => (float) $row['price_old'],
        'price_new' => (float) $row['price_new'],
        'discount_percent' => (int) $row['discount_percent'],
        'priority' => (int) $row['priority'],
    ];
}

function impactshop_auto_banner_calculate_priority(array $offer): int
{
    $discount = (int) ($offer['discount_percent'] ?? 0);
    $freshness = 10;
    return max(0, ($discount * 2) + $freshness);
}

function impactshop_auto_banner_from_offer(array $offer, array $context = []): void
{
    if (empty($offer['title']) || empty($offer['url'])) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    $priority = impactshop_auto_banner_calculate_priority($offer);

    $status = $context['status'] ?? 'pending';
    if (!in_array($status, ['pending', 'active', 'disabled'], true)) {
        $status = 'pending';
    }

    $title = sanitize_text_field((string) $offer['title']);
    $shop_slug = sanitize_text_field((string) ($offer['shop_slug'] ?? ''));
    if ($shop_slug === '' || !impactshop_is_whitelisted_partner($shop_slug)) {
        return;
    }
    $banner_url = esc_url_raw((string) ($offer['url'] ?? ''));
    if (!impactshop_auto_banner_is_valid_banner_url($banner_url, $shop_slug)) {
        return;
    }
    $image_url = esc_url_raw((string) ($offer['image_url'] ?? ''));
    $starts_at = isset($offer['starts_at']) ? sanitize_text_field((string) $offer['starts_at']) : null;
    $ends_at = isset($offer['ends_at']) ? sanitize_text_field((string) $offer['ends_at']) : null;
    if ($ends_at === null || $ends_at === '') {
        $ends_at = gmdate('Y-m-d H:i:s', strtotime('+' . IMPACTSHOP_AUTO_BANNER_DEFAULT_TTL_DAYS . ' days'));
    }
    if ($starts_at === null || $starts_at === '') {
        $starts_at = current_time('mysql');
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE shop_slug = %s AND (banner_url = %s OR title = %s) LIMIT 1",
        $shop_slug,
        $banner_url,
        $title
    ));

    $data = [
        'status' => $status,
        'title' => $title,
        'image_url' => $image_url,
        'shop_slug' => $shop_slug,
        'banner_url' => $banner_url,
        'price_old' => (float) ($offer['price_old'] ?? 0),
        'price_new' => (float) ($offer['price_new'] ?? 0),
        'discount_percent' => (int) ($offer['discount_percent'] ?? 0),
        'priority' => $priority,
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'created_at' => current_time('mysql'),
    ];
    $formats = ['%s','%s','%s','%s','%s','%f','%f','%d','%d','%s','%s','%s'];

    if ($existing) {
        $wpdb->update($table, $data, ['id' => (int) $existing], $formats, ['%d']);
        return;
    }

    $wpdb->insert($table, $data, $formats);
}

function impactshop_auto_banner_admin_menu(): void
{
    add_submenu_page(
        'options-general.php',
        'Auto Banner',
        'Auto Banner',
        'manage_options',
        'impactshop-auto-banner',
        'impactshop_auto_banner_admin_page'
    );
}

function impactshop_auto_banner_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('impactshop_auto_banner_save')) {
        $id = absint($_POST['banner_id'] ?? 0);
        $status = sanitize_text_field((string) ($_POST['banner_status'] ?? 'pending'));
        if ($id > 0 && in_array($status, ['pending', 'active', 'disabled'], true)) {
            $wpdb->update($table, ['status' => $status], ['id' => $id], ['%s'], ['%d']);
        }
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50", ARRAY_A);

    echo '<div class="wrap"><h1>Auto Banner moderáció</h1>';
    wp_nonce_field('impactshop_auto_banner_save');
    if (empty($rows)) {
        echo '<p>Nincs banner.</p></div>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Cím</th><th>Állapot</th><th>Művelet</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . esc_html((string) $row['id']) . '</td><td>' . esc_html((string) $row['title']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>';
        echo '<form method="post">';
        wp_nonce_field('impactshop_auto_banner_save');
        echo '<input type="hidden" name="banner_id" value="' . esc_attr((string) $row['id']) . '" />';
        echo '<select name="banner_status">';
        foreach (['pending' => 'pending', 'active' => 'active', 'disabled' => 'disabled'] as $key => $label) {
            $selected = $row['status'] === $key ? ' selected' : '';
            echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        submit_button('Mentés', 'secondary small', 'submit', false);
        echo '</form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function impactshop_auto_banner_register_cli(): void
{
    if (!class_exists('WP_CLI')) {
        return;
    }

    WP_CLI::add_command('impactshop auto-banner import', 'impactshop_auto_banner_cli_import');
    WP_CLI::add_command('impactshop auto-banner import-feed', 'impactshop_auto_banner_cli_import_feed');
    WP_CLI::add_command('impactshop auto-banner cleanup', 'impactshop_auto_banner_cli_cleanup');
}

function impactshop_auto_banner_feed_candidates(): array
{
    $workspace_root = dirname(dirname(WP_CONTENT_DIR));
    return [
        $workspace_root . '/ai-agent/tmp/ingest/export-coupons.json',
        dirname(WP_CONTENT_DIR) . '/tmp/coupon-harvester/export-coupons.json',
        dirname(WP_CONTENT_DIR) . '/tmp/ingest/export-coupons.json',
    ];
}

function impactshop_auto_banner_resolve_feed_file(): ?string
{
    foreach (impactshop_auto_banner_feed_candidates() as $candidate) {
        if (is_readable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function impactshop_auto_banner_import_file(string $file, string $source = 'cli', string $status = 'active'): array
{
    if ($file === '' || !is_readable($file)) {
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => 'missing_or_unreadable_file',
        ];
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => 'read_failed',
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => 'invalid_json',
        ];
    }

    if (isset($decoded['offers']) && is_array($decoded['offers'])) {
        $items = $decoded['offers'];
    } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
        $items = $decoded['items'];
    } else {
        $items = $decoded;
    }

    if (!is_array($items)) {
        return [
            'success' => false,
            'imported' => 0,
            'skipped' => 0,
            'error' => 'no_offer_list',
        ];
    }

    $imported = 0;
    $skipped = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            $skipped++;
            continue;
        }
        $offer = impactshop_auto_banner_normalize_offer($item);
        if ($offer === null) {
            $skipped++;
            continue;
        }
        impactshop_auto_banner_from_offer($offer, ['source' => $source, 'status' => $status]);
        $imported++;
    }

    return [
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'file' => $file,
    ];
}

function impactshop_auto_banner_schedule_cleanup(): void
{
    if (!wp_next_scheduled('impactshop_auto_banner_cleanup')) {
        wp_schedule_event(time() + 300, 'daily', 'impactshop_auto_banner_cleanup');
    }
}

function impactshop_auto_banner_schedule_feed_import(): void
{
    if (!wp_next_scheduled(IMPACTSHOP_AUTO_BANNER_FEED_CRON)) {
        wp_schedule_event(time() + 600, 'hourly', IMPACTSHOP_AUTO_BANNER_FEED_CRON);
    }
}

add_action('impactshop_auto_banner_cleanup', function (): void {
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    $now = current_time('mysql');
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table}
             WHERE ends_at IS NOT NULL
               AND ends_at < %s",
            $now
        )
    );
});

add_action(IMPACTSHOP_AUTO_BANNER_FEED_CRON, function (): void {
    $file = impactshop_auto_banner_resolve_feed_file();
    if ($file === null) {
        $result = [
            'success' => false,
            'error' => 'feed_missing',
            'checked_at' => current_time('mysql'),
            'candidates' => impactshop_auto_banner_feed_candidates(),
        ];
        update_option(IMPACTSHOP_AUTO_BANNER_FEED_IMPORT_OPTION, $result, false);
        if (function_exists('impactshop_ads_watch_log')) {
            impactshop_ads_watch_log('warning', 'auto_banner_feed_missing', $result);
        }
        return;
    }

    $result = impactshop_auto_banner_import_file($file, 'feed', 'active');
    $result['checked_at'] = current_time('mysql');
    update_option(IMPACTSHOP_AUTO_BANNER_FEED_IMPORT_OPTION, $result, false);
    if (function_exists('impactshop_ads_watch_log')) {
        impactshop_ads_watch_log(
            !empty($result['success']) ? 'info' : 'error',
            !empty($result['success']) ? 'auto_banner_feed_import_complete' : 'auto_banner_feed_import_failed',
            $result
        );
    }
});

function impactshop_auto_banner_schedule_whitelist_cleanup(): void
{
    add_action('init', function (): void {
        $done = get_option(IMPACTSHOP_AUTO_BANNER_CLEANUP_OPTION, false);
        if ($done) {
            return;
        }
        $result = impactshop_auto_banner_cleanup_non_whitelisted();
        update_option(IMPACTSHOP_AUTO_BANNER_CLEANUP_OPTION, [
            'done_at' => current_time('mysql'),
            'result' => $result,
        ], false);
    }, 99);
}

function impactshop_auto_banner_cleanup_non_whitelisted(): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';
    $rows = $wpdb->get_results("SELECT id, shop_slug FROM {$table}", ARRAY_A);
    $result = [
        'checked' => 0,
        'deleted' => 0,
        'kept' => 0,
    ];

    foreach ($rows as $row) {
        $result['checked']++;
        $shop_slug = (string) ($row['shop_slug'] ?? '');
        if (strpos($shop_slug, 'sync:') === 0) {
            $shop_slug = substr($shop_slug, 5);
        }

        if ($shop_slug !== '' && impactshop_is_whitelisted_partner($shop_slug)) {
            $result['kept']++;
            continue;
        }

        $wpdb->delete($table, ['id' => (int) $row['id']], ['%d']);
        $result['deleted']++;
    }

    return $result;
}

function impactshop_auto_banner_cli_cleanup(): void
{
    $result = impactshop_auto_banner_cleanup_non_whitelisted();
    WP_CLI::success(sprintf(
        'Cleanup complete: %d checked, %d deleted, %d kept',
        $result['checked'],
        $result['deleted'],
        $result['kept']
    ));
}

function impactshop_auto_banner_cli_import(array $args, array $assoc_args): void
{
    $file = $assoc_args['file'] ?? '';
    if ($file === '') {
        WP_CLI::error('Missing --file argument.');
    }
    $result = impactshop_auto_banner_import_file($file, 'cli', 'active');
    if (empty($result['success'])) {
        WP_CLI::error((string) ($result['error'] ?? 'import_failed'));
    }
    WP_CLI::success(sprintf(
        'Imported %d auto-banner items, skipped %d.',
        (int) $result['imported'],
        (int) $result['skipped']
    ));
}

function impactshop_auto_banner_cli_import_feed(): void
{
    $file = impactshop_auto_banner_resolve_feed_file();
    if ($file === null) {
        WP_CLI::error('No readable autobanner feed file found.');
    }
    $result = impactshop_auto_banner_import_file($file, 'feed', 'active');
    if (empty($result['success'])) {
        WP_CLI::error((string) ($result['error'] ?? 'import_feed_failed'));
    }
    WP_CLI::success(sprintf(
        'Imported feed %s: %d imported, %d skipped.',
        $file,
        (int) $result['imported'],
        (int) $result['skipped']
    ));
}

function impactshop_auto_banner_normalize_offer(array $item): ?array
{
    $title = (string) ($item['title'] ?? $item['discount_label'] ?? '');
    $description = (string) ($item['description'] ?? $item['desc'] ?? '');
    $image = (string) ($item['image_url'] ?? $item['logo_url'] ?? '');
    $url = (string) ($item['cta_url'] ?? $item['url'] ?? $item['product_url'] ?? '');

    if ($title === '' || $url === '') {
        return null;
    }

    $discount = $item['discount_percent'] ?? null;
    if ($discount === null && isset($item['discount_label'])) {
        if (preg_match('/(\d{1,2})%/', (string) $item['discount_label'], $match)) {
            $discount = (int) $match[1];
        }
    }

    $price_old = (float) ($item['price_old'] ?? $item['old_price'] ?? 0);
    $price_new = (float) ($item['price_new'] ?? $item['new_price'] ?? 0);
    if ($price_old <= 0 || $price_new <= 0) {
        if (preg_match('/(\d[\d\s,.]*)\s*(Ft|HUF|€)\s*helyett\s*(\d[\d\s,.]*)/iu', $title . ' ' . $description, $match)) {
            $price_old = (float) preg_replace('/[^\d]/', '', $match[1]);
            $price_new = (float) preg_replace('/[^\d]/', '', $match[3]);
        }
    }

    if ($discount === null || (int) $discount === 0) {
        if ($price_old > 0 && $price_new > 0 && $price_new < $price_old) {
            $discount = (int) round((1 - ($price_new / $price_old)) * 100);
        } elseif (preg_match('/(\d{1,2})%\s*(kedvezmény|off|le[aá]r[aá]zva)?/iu', $title . ' ' . $description, $match)) {
            $discount = (int) $match[1];
        }
    }

    return [
        'title' => $title,
        'image_url' => $image,
        'shop_slug' => (string) ($item['shop_slug'] ?? ''),
        'url' => $url,
        'price_old' => $price_old,
        'price_new' => $price_new,
        'discount_percent' => (int) ($discount ?? 0),
        'starts_at' => $item['starts_at'] ?? null,
        'ends_at' => $item['ends_at'] ?? null,
    ];
}
