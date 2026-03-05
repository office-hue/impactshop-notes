<?php
/**
 * Plugin Name: ImpactShop NGO Selector
 * Description: Komárom-Esztergom NGO választó (ID-hez kötve).
 */

declare(strict_types=1);

add_action('muplugins_loaded', 'impactshop_ngo_selector_maybe_migrate');
add_action('init', 'impactshop_ngo_selector_register_shortcode');
add_action('rest_api_init', 'impactshop_ngo_selector_register_routes');
add_action('wp_enqueue_scripts', 'impactshop_ngo_selector_register_assets');

function impactshop_ngo_selector_register_assets(): void
{
    $script_path = __DIR__ . '/impactshop-ngo-selector.js';
    $version = file_exists($script_path) ? (string) filemtime($script_path) : '1.0.0';
    wp_register_script('impactshop-ngo-selector', plugins_url('impactshop-ngo-selector.js', __FILE__), [], $version, true);

    $css = <<<CSS
.impactshop-ngo-selector { max-width: 980px; margin: 0 auto; padding: 12px; }
.impactshop-ngo-selector__panel { background: #ffffff; border-radius: 18px; padding: 18px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); border: 1px solid rgba(148, 163, 184, 0.2); }
.impactshop-ngo-selector__title { margin: 0 0 6px; font-size: 24px; font-weight: 700; color: #0f172a; }
.impactshop-ngo-selector__subtitle { margin: 0 0 14px; color: #475569; font-size: 14px; }
.impactshop-ngo-selector__row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.impactshop-ngo-selector select { flex: 1; min-width: 240px; padding: 10px 12px; border-radius: 12px; border: 1px solid #cbd5f5; background: #f8fafc; font-size: 14px; }
.impactshop-ngo-selector__status { font-size: 13px; color: #64748b; }
.impactshop-ngo-card { margin-top: 16px; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); border-radius: 16px; padding: 16px; border: 1px solid #e2e8f0; }
.impactshop-ngo-card__title { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #0f172a; }
.impactshop-ngo-card__meta { font-size: 13px; color: #475569; margin-bottom: 8px; }
.impactshop-ngo-card__summary { font-size: 14px; color: #1f2937; line-height: 1.5; }
.impactshop-ngo-card__empty { padding: 14px; border-radius: 12px; background: #f1f5f9; color: #64748b; font-size: 14px; }
@media (max-width: 640px) {
  .impactshop-ngo-selector__title { font-size: 20px; }
  .impactshop-ngo-selector__panel { padding: 14px; }
}
CSS;

    wp_register_style('impactshop-ngo-selector', false, [], $version);
    wp_add_inline_style('impactshop-ngo-selector', $css);
}

function impactshop_ngo_selector_enqueue_assets(): void
{
    wp_enqueue_style('impactshop-ngo-selector');
    wp_enqueue_script('impactshop-ngo-selector');
    wp_localize_script('impactshop-ngo-selector', 'impactshopNgoSelector', [
        'restBase' => esc_url_raw(rest_url('impact/v1/ngo-selector')),
    ]);
}

function impactshop_ngo_selector_register_shortcode(): void
{
    add_shortcode('impactshop_ngo_selector', 'impactshop_ngo_selector_shortcode');
}

function impactshop_ngo_selector_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'context' => 'jysk-komarom',
        'list' => 'komarom-esztergom',
        'title' => 'Civil szervezet kiválasztása',
        'subtitle' => 'Válaszd ki a szervezetet a listából. A választás az ID-dhoz kötve mentésre kerül.',
    ], $atts, 'impactshop_ngo_selector');

    impactshop_ngo_selector_enqueue_assets();

    $context = sanitize_key((string) $atts['context']);
    $list = sanitize_key((string) $atts['list']);
    $title = esc_html((string) $atts['title']);
    $subtitle = esc_html((string) $atts['subtitle']);

    $list_url = plugins_url('impactshop-ngo-selector-data/' . $list . '.json', __FILE__);

    return sprintf(
        '<div class="impactshop-ngo-selector" data-ngo-selector data-context="%s" data-list-url="%s">' .
        '<div class="impactshop-ngo-selector__panel">' .
        '<h2 class="impactshop-ngo-selector__title">%s</h2>' .
        '<p class="impactshop-ngo-selector__subtitle">%s</p>' .
        '<div class="impactshop-ngo-selector__row">' .
        '<select data-role="ngo-select"><option value="">Válassz szervezetet…</option></select>' .
        '<span class="impactshop-ngo-selector__status" data-role="ngo-status"></span>' .
        '</div>' .
        '<div class="impactshop-ngo-card" data-role="ngo-card"></div>' .
        '</div></div>',
        esc_attr($context),
        esc_url($list_url),
        $title,
        $subtitle
    );
}

function impactshop_ngo_selector_register_routes(): void
{
    register_rest_route('impact/v1', '/ngo-selector/get', [
        'methods' => 'GET',
        'callback' => 'impactshop_ngo_selector_get',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/ngo-selector/set', [
        'methods' => 'POST',
        'callback' => 'impactshop_ngo_selector_set',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_ngo_selector_get_pseudo_id(): string
{
    if (function_exists('impactshop_offerwall_get_pseudo_id')) {
        $pseudo = (string) impactshop_offerwall_get_pseudo_id();
        if ($pseudo !== '') {
            return $pseudo;
        }
    }
    if (function_exists('sharity_points_get_pseudo_from_cookie')) {
        $pseudo = (string) sharity_points_get_pseudo_from_cookie();
        if ($pseudo !== '') {
            return $pseudo;
        }
    }
    if (!empty($_COOKIE['impactshop_pseudo_id'])) {
        return sanitize_text_field((string) wp_unslash($_COOKIE['impactshop_pseudo_id']));
    }
    return '';
}

function impactshop_ngo_selector_get(WP_REST_Request $request): WP_REST_Response
{
    $context = sanitize_key((string) $request->get_param('context'));
    $pseudo = impactshop_ngo_selector_get_pseudo_id();
    if ($pseudo === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo'], 403);
    }

    global $wpdb;
    $table = impactshop_ngo_selector_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT ngo_slug FROM {$table} WHERE pseudo_id = %s AND context_key = %s LIMIT 1",
        $pseudo,
        $context
    ), ARRAY_A);

    return new WP_REST_Response([
        'status' => 'ok',
        'ngo_slug' => (string) ($row['ngo_slug'] ?? ''),
    ], 200);
}

function impactshop_ngo_selector_set(WP_REST_Request $request): WP_REST_Response
{
    $pseudo = impactshop_ngo_selector_get_pseudo_id();
    if ($pseudo === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo'], 403);
    }

    $payload = (array) $request->get_json_params();
    $context = isset($payload['context']) ? sanitize_key((string) $payload['context']) : '';
    $ngo_slug = isset($payload['ngo_slug']) ? sanitize_key((string) $payload['ngo_slug']) : '';
    if ($context === '' || $ngo_slug === '') {
        return new WP_REST_Response(['status' => 'invalid'], 400);
    }

    global $wpdb;
    $table = impactshop_ngo_selector_table();
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (pseudo_id, context_key, ngo_slug, updated_at)
         VALUES (%s, %s, %s, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE ngo_slug = VALUES(ngo_slug), updated_at = UTC_TIMESTAMP()",
        $pseudo,
        $context,
        $ngo_slug
    ));

    return new WP_REST_Response(['status' => 'ok', 'ngo_slug' => $ngo_slug], 200);
}

function impactshop_ngo_selector_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_ngo_selector';
}

function impactshop_ngo_selector_maybe_migrate(): void
{
    $version = (int) get_option('impactshop_ngo_selector_schema', 0);
    if ($version >= 1) {
        return;
    }

    global $wpdb;
    $table = impactshop_ngo_selector_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        context_key VARCHAR(64) NOT NULL,
        ngo_slug VARCHAR(190) NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_pseudo_context (pseudo_id, context_key),
        KEY idx_context (context_key)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('impactshop_ngo_selector_schema', 1, false);
}
