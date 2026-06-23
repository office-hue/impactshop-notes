<?php
/**
 * Plugin Name: ImpactShop VB2026 NGO Catalog
 * Description: Sharity NGO katalogus es VB2026 NGO-valasztasi lane.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY = 'vb2026';
const IMPACTSHOP_VB2026_NGO_CSV_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRHyEoPuoisnLotuF5fN7lqjTgfrB5Q_zWJWbg_l6IzVh2uL2I9dPFhrgZ2aEMuMA/pub?gid=340592635&single=true&output=csv';
const IMPACTSHOP_VB2026_NGO_SYNC_TRANSIENT = 'impactshop_vb2026_ngo_catalog_last_sync_v1';
const IMPACTSHOP_VB2026_NGO_SYNC_TTL = 900;
const IMPACTSHOP_VB2026_NGO_INTENT_TTL = 900;
const IMPACTSHOP_VB2026_NGO_MIN_ACTIVE_ROWS = 25;
const IMPACTSHOP_VB2026_NGO_ACTIVE_DROP_RATIO = 0.35;

add_action('muplugins_loaded', 'impactshop_vb2026_ngo_catalog_maybe_migrate');
add_action('init', 'impactshop_vb2026_ngo_catalog_register_routes');
add_action('init', 'impactshop_vb2026_ngo_catalog_register_rewrite');
add_action('rest_api_init', 'impactshop_vb2026_ngo_catalog_register_rest');
add_filter('query_vars', 'impactshop_vb2026_ngo_catalog_query_vars');
add_action('template_redirect', 'impactshop_vb2026_ngo_catalog_template_redirect');

function impactshop_vb2026_ngo_catalog_maybe_migrate(): void
{
    $version = (int) get_option('impactshop_vb2026_ngo_catalog_schema', 0);
    if ($version >= 1) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $catalog = $wpdb->prefix . 'sharity_ngo_catalog';
    $flags = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $selection = $wpdb->prefix . 'vb2026_user_ngo_selection';
    $intents = $wpdb->prefix . 'vb2026_selection_intents';
    $audit = $wpdb->prefix . 'vb2026_ngo_selection_audit_log';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("
        CREATE TABLE {$catalog} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sharity_ngo_id BIGINT UNSIGNED NOT NULL,
            slug VARCHAR(191) NOT NULL,
            slug_source VARCHAR(32) NOT NULL DEFAULT 'ngo_card',
            name VARCHAR(255) NOT NULL,
            postal_code VARCHAR(32) NULL,
            city VARCHAR(191) NULL,
            county VARCHAR(191) NULL,
            category_label VARCHAR(191) NULL,
            legal_status_label VARCHAR(191) NULL,
            short_mission TEXT NULL,
            website_url TEXT NULL,
            raw_logo_url TEXT NULL,
            raw_cover_image_url TEXT NULL,
            logo_url TEXT NULL,
            cover_image_url TEXT NULL,
            share_url TEXT NULL,
            details_url TEXT NULL,
            campaign_count INT NOT NULL DEFAULT 0,
            source_status_label VARCHAR(64) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            source_created_at DATETIME NULL,
            source_last_synced_at DATETIME NOT NULL,
            source_row_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_sharity_ngo_id (sharity_ngo_id),
            UNIQUE KEY uk_slug (slug),
            KEY idx_active_name (is_active, name(120)),
            KEY idx_city (city(120)),
            KEY idx_category (category_label(120))
        ) {$charset};
    ");

    dbDelta("
        CREATE TABLE {$flags} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_key VARCHAR(64) NOT NULL,
            sharity_ngo_id BIGINT UNSIGNED NOT NULL,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            is_race_visible TINYINT(1) NOT NULL DEFAULT 0,
            allow_public_listing TINYINT(1) NOT NULL DEFAULT 1,
            allow_user_selection TINYINT(1) NOT NULL DEFAULT 1,
            display_priority INT NOT NULL DEFAULT 1000,
            hero_badge VARCHAR(64) NULL,
            campaign_copy_short TEXT NULL,
            campaign_copy_long LONGTEXT NULL,
            campaign_state VARCHAR(64) NOT NULL DEFAULT 'active',
            updated_by BIGINT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_campaign_ngo (campaign_key, sharity_ngo_id),
            KEY idx_featured (campaign_key, is_featured, display_priority)
        ) {$charset};
    ");

    dbDelta("
        CREATE TABLE {$selection} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pseudo_id VARCHAR(191) NOT NULL,
            contest_scope VARCHAR(64) NOT NULL,
            selected_sharity_ngo_id BIGINT UNSIGNED NOT NULL,
            selection_source VARCHAR(64) NOT NULL,
            was_featured_at_selection_time TINYINT(1) NOT NULL DEFAULT 0,
            selection_lock_state VARCHAR(32) NOT NULL DEFAULT 'open',
            selected_at DATETIME NOT NULL,
            last_changed_at DATETIME NOT NULL,
            invalidated_at DATETIME NULL,
            invalidation_reason VARCHAR(64) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_pseudo_scope (pseudo_id, contest_scope),
            KEY idx_scope_ngo (contest_scope, selected_sharity_ngo_id)
        ) {$charset};
    ");

    dbDelta("
        CREATE TABLE {$intents} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            intent_token_hash CHAR(64) NOT NULL,
            contest_scope VARCHAR(64) NOT NULL,
            selected_sharity_ngo_id BIGINT UNSIGNED NOT NULL,
            return_to VARCHAR(64) NULL,
            created_for_session_id VARCHAR(191) NULL,
            created_for_pseudo_id VARCHAR(191) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_intent_hash (intent_token_hash),
            KEY idx_status_expires (status, expires_at),
            KEY idx_scope_ngo (contest_scope, selected_sharity_ngo_id)
        ) {$charset};
    ");

    dbDelta("
        CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pseudo_id VARCHAR(191) NOT NULL,
            contest_scope VARCHAR(64) NOT NULL,
            previous_sharity_ngo_id BIGINT UNSIGNED NULL,
            new_sharity_ngo_id BIGINT UNSIGNED NOT NULL,
            selection_source VARCHAR(64) NOT NULL,
            actor_type VARCHAR(32) NOT NULL,
            result_state VARCHAR(32) NOT NULL,
            reason_code VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_pseudo_scope_created (pseudo_id, contest_scope, created_at),
            KEY idx_new_ngo_created (new_sharity_ngo_id, created_at)
        ) {$charset};
    ");

    update_option('impactshop_vb2026_ngo_catalog_schema', 1, false);
}

function impactshop_vb2026_ngo_catalog_register_routes(): void
{
    add_rewrite_rule('^szervezetek/?$', 'index.php?impact_vb2026_ngo_catalog=1', 'top');
}

function impactshop_vb2026_ngo_catalog_query_vars(array $vars): array
{
    $vars[] = 'impact_vb2026_ngo_catalog';
    return $vars;
}

function impactshop_vb2026_ngo_catalog_register_rewrite(): void
{
    if (get_option('impactshop_vb2026_ngo_catalog_rewrite_flushed') === '1') {
        return;
    }
    flush_rewrite_rules(false);
    update_option('impactshop_vb2026_ngo_catalog_rewrite_flushed', '1', false);
}

function impactshop_vb2026_ngo_catalog_register_rest(): void
{
    register_rest_route('impact/v1', '/ngo-catalog', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vb2026_rest_ngo_catalog',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vb2026/featured-ngos', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vb2026_rest_featured_ngos',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vb2026/my-ngo-selection', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vb2026_rest_my_ngo_selection',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vb2026/select-ngo', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vb2026_rest_select_ngo',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vb2026/selection-intent', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vb2026_rest_selection_intent',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vb2026/selection-intent/complete', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vb2026_rest_selection_intent_complete',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_vb2026_rest_ngo_catalog(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();

    $page = max(1, (int) $request->get_param('page'));
    $perPage = min(48, max(1, (int) ($request->get_param('per_page') ?: 24)));
    $featuredOnly = impactshop_vb2026_bool_param($request->get_param('featured_only'));
    $activeOnly = $request->get_param('active_only');
    $activeOnly = $activeOnly === null ? true : impactshop_vb2026_bool_param($activeOnly);

    $filters = [
        'q' => sanitize_text_field((string) $request->get_param('q')),
        'category' => sanitize_text_field((string) $request->get_param('category')),
        'legal_status' => sanitize_text_field((string) $request->get_param('legal_status')),
        'city' => sanitize_text_field((string) $request->get_param('city')),
        'county' => sanitize_text_field((string) $request->get_param('county')),
        'campaign' => sanitize_key((string) ($request->get_param('campaign') ?: IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY)),
        'featured_only' => $featuredOnly,
        'active_only' => $activeOnly,
    ];

    $payload = impactshop_vb2026_catalog_query($filters, $page, $perPage);

    return new WP_REST_Response([
        'ok' => true,
        'campaign' => $filters['campaign'],
        'filters' => $filters,
        'results' => $payload['items'],
        'pagination' => $payload['pagination'],
    ], 200);
}

function impactshop_vb2026_rest_featured_ngos(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();
    $campaignKey = sanitize_key((string) ($request->get_param('campaign') ?: IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY));
    $items = impactshop_vb2026_get_featured_items($campaignKey, 10);

    return new WP_REST_Response([
        'ok' => true,
        'campaign_key' => $campaignKey,
        'updated_at' => gmdate('c'),
        'items' => $items,
    ], 200);
}

function impactshop_vb2026_rest_my_ngo_selection(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();
    $resolution = impactshop_vb2026_resolve_request_pseudo($request, true);
    if (empty($resolution['pseudo_id'])) {
        return impactshop_vb2026_error_response('UNAUTHORIZED', 'A saját NGO kiválasztáshoz azonosított fiók szükséges.', 401);
    }

    $contestScope = sanitize_key((string) ($request->get_param('contest_scope') ?: IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY));
    $selection = impactshop_vb2026_get_selection_by_pseudo($resolution['pseudo_id'], $contestScope);

    return new WP_REST_Response([
        'ok' => true,
        'contest_scope' => $contestScope,
        'source_status' => 'ready',
        'pseudo_id_masked' => impactshop_vb2026_mask_pseudo($resolution['pseudo_id']),
    ] + impactshop_vb2026_selection_response_payload($selection), 200);
}

function impactshop_vb2026_rest_select_ngo(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();
    $resolution = impactshop_vb2026_resolve_request_pseudo($request, true);
    if (empty($resolution['pseudo_id'])) {
        return impactshop_vb2026_error_response('UNAUTHORIZED', 'NGO választáshoz azonosított fiók szükséges.', 401);
    }
    if (empty($resolution['service_auth']) && !impactshop_vb2026_browser_write_allowed($request)) {
        return impactshop_vb2026_error_response('UNAUTHORIZED', 'A választási kéréshez same-origin hívás szükséges.', 401);
    }

    $payload = (array) $request->get_json_params();
    $contestScope = sanitize_key((string) ($payload['contest_scope'] ?? IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY));
    $ngoId = absint($payload['selected_sharity_ngo_id'] ?? 0);
    $sourceContext = sanitize_key((string) ($payload['source_context'] ?? ($resolution['service_auth'] ? 'vb_prod_bridge' : 'sharity_catalog')));
    if ($ngoId <= 0) {
        return impactshop_vb2026_error_response('NGO_NOT_FOUND', 'Hiányzik a kiválasztott NGO azonosítója.', 400);
    }

    $result = impactshop_vb2026_upsert_selection($resolution['pseudo_id'], $contestScope, $ngoId, $sourceContext, $resolution['service_auth'] ? 'service' : 'browser');
    if (!$result['ok']) {
        return impactshop_vb2026_error_response($result['code'], $result['message'], $result['status']);
    }

    return new WP_REST_Response([
        'ok' => true,
        'selected_ngo' => $result['selected_ngo'],
        'effective_from' => $result['effective_from'],
        'selection_message' => 'Mostantól ezt a civil ügyet támogatod a VB2026 játékban.',
    ], 200);
}

function impactshop_vb2026_rest_selection_intent(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();
    $payload = (array) $request->get_json_params();
    $contestScope = sanitize_key((string) ($payload['contest_scope'] ?? IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY));
    $ngoId = absint($payload['selected_sharity_ngo_id'] ?? 0);
    $returnTo = sanitize_key((string) ($payload['return_to'] ?? 'vb-prod'));
    if ($ngoId <= 0) {
        return impactshop_vb2026_error_response('NGO_NOT_FOUND', 'Hiányzik a kiválasztott NGO azonosítója.', 400);
    }
    if (!impactshop_vb2026_is_allowed_return_to($returnTo)) {
        return impactshop_vb2026_error_response('RETURN_TO_NOT_ALLOWED', 'Ez a visszatérési cél jelenleg nem engedélyezett.', 400);
    }

    $ngo = impactshop_vb2026_get_catalog_row($ngoId);
    if (!$ngo) {
        return impactshop_vb2026_error_response('NGO_NOT_FOUND', 'A kiválasztott szervezet nem található.', 404);
    }
    if ((int) $ngo['is_active'] !== 1) {
        return impactshop_vb2026_error_response('NGO_NOT_ACTIVE', 'Ez a szervezet jelenleg nem aktív.', 409);
    }
    if ((int) ($ngo['allow_user_selection'] ?? 1) !== 1 || (($ngo['campaign_state'] ?? 'active') !== 'active')) {
        return impactshop_vb2026_error_response('NGO_NOT_SELECTABLE', 'Ez a szervezet jelenleg nem választható.', 409);
    }

    $token = 'vb2026_sel_' . wp_generate_password(24, false, false);
    $tokenHash = hash('sha256', $token);
    $createdAt = current_time('mysql', true);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + IMPACTSHOP_VB2026_NGO_INTENT_TTL);
    $sessionId = function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';
    $pseudo = impactshop_vb2026_get_pseudo_id();

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'vb2026_selection_intents',
        [
            'intent_token_hash' => $tokenHash,
            'contest_scope' => $contestScope,
            'selected_sharity_ngo_id' => $ngoId,
            'return_to' => $returnTo,
            'created_for_session_id' => $sessionId ?: null,
            'created_for_pseudo_id' => $pseudo ?: null,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'completed_at' => null,
            'created_at' => $createdAt,
        ],
        ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    return new WP_REST_Response([
        'ok' => true,
        'intent_token' => $token,
        'expires_at' => gmdate('c', strtotime($expiresAt . ' UTC')),
        'auth_url' => add_query_arg([
            'bridge_target' => 'account',
            'selection_intent' => $token,
        ], 'https://app.sharity.hu/profil/'),
    ], 200);
}

function impactshop_vb2026_rest_selection_intent_complete(WP_REST_Request $request): WP_REST_Response
{
    impactshop_vb2026_ensure_catalog_sync();
    $resolution = impactshop_vb2026_resolve_request_pseudo($request, true);
    if (empty($resolution['pseudo_id'])) {
        return impactshop_vb2026_error_response('UNAUTHORIZED', 'A kiválasztás befejezéséhez azonosított fiók szükséges.', 401);
    }
    if (empty($resolution['service_auth']) && !impactshop_vb2026_browser_write_allowed($request)) {
        return impactshop_vb2026_error_response('UNAUTHORIZED', 'A kiválasztás befejezéséhez same-origin kérés szükséges.', 401);
    }

    $payload = (array) $request->get_json_params();
    $token = sanitize_text_field((string) ($payload['intent_token'] ?? ''));
    if ($token === '') {
        return impactshop_vb2026_error_response('SELECTION_INTENT_INVALID', 'Hiányzik az intent token.', 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'vb2026_selection_intents';
    $hash = hash('sha256', $token);
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE intent_token_hash = %s LIMIT 1",
        $hash
    ), ARRAY_A);
    if (!$row) {
        return impactshop_vb2026_error_response('SELECTION_INTENT_INVALID', 'A kiválasztási szándék nem található.', 404);
    }
    if (($row['status'] ?? '') !== 'pending') {
        return impactshop_vb2026_error_response('SELECTION_INTENT_INVALID', 'Ez a kiválasztási szándék már nem aktív.', 409);
    }
    if (!empty($row['expires_at']) && strtotime((string) $row['expires_at'] . ' UTC') < time()) {
        return impactshop_vb2026_error_response('SELECTION_INTENT_EXPIRED', 'A kiválasztási szándék lejárt.', 409);
    }
    if (!empty($row['created_for_pseudo_id']) && $row['created_for_pseudo_id'] !== $resolution['pseudo_id']) {
        return impactshop_vb2026_error_response('SELECTION_INTENT_INVALID', 'Ez a kiválasztási szándék nem ehhez a fiókhoz tartozik.', 409);
    }

    $result = impactshop_vb2026_upsert_selection(
        $resolution['pseudo_id'],
        sanitize_key((string) ($row['contest_scope'] ?? IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY)),
        (int) $row['selected_sharity_ngo_id'],
        'selection_intent_complete',
        $resolution['service_auth'] ? 'service' : 'browser'
    );
    if (!$result['ok']) {
        return impactshop_vb2026_error_response($result['code'], $result['message'], $result['status']);
    }

    $wpdb->update(
        $table,
        [
            'status' => 'completed',
            'completed_at' => current_time('mysql', true),
        ],
        ['id' => (int) $row['id']],
        ['%s', '%s'],
        ['%d']
    );

    return new WP_REST_Response([
        'ok' => true,
        'selected_ngo' => $result['selected_ngo'],
        'effective_from' => $result['effective_from'],
        'selection_message' => 'A civil ügy kiválasztása sikeresen befejeződött.',
    ], 200);
}

function impactshop_vb2026_catalog_template_redirect(): void
{
    if ((string) get_query_var('impact_vb2026_ngo_catalog') !== '1') {
        return;
    }
    impactshop_vb2026_ensure_catalog_sync();
    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    echo impactshop_vb2026_catalog_render_page();
    exit;
}

function impactshop_vb2026_catalog_render_page(): string
{
    $restBase = esc_url_raw(rest_url('impact/v1'));
    $campaign = IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY;
    ob_start();
    ?>
<!doctype html>
<html lang="hu">
<head>
  <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sharity szervezetek</title>
  <style>
    :root {
      --bg: #071220;
      --panel: rgba(10, 20, 38, 0.92);
      --panel-soft: rgba(17, 33, 58, 0.86);
      --border: rgba(141, 208, 255, 0.18);
      --text: #f4f8ff;
      --muted: #9cb5d3;
      --accent: #ffb347;
      --accent-soft: rgba(255, 179, 71, 0.18);
      --ok: #49d98c;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Sora", "Segoe UI", system-ui, sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(44, 145, 255, 0.12), transparent 30%),
        linear-gradient(180deg, #071220 0%, #0b1830 100%);
    }
    .shell {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
      padding: 32px 0 64px;
    }
    .hero, .panel {
      border: 1px solid var(--border);
      border-radius: 26px;
      background: var(--panel);
      box-shadow: 0 24px 60px rgba(0,0,0,0.28);
    }
    .hero {
      padding: 28px;
      display: grid;
      gap: 16px;
      margin-bottom: 20px;
    }
    .eyebrow {
      color: #8dd0ff;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-size: 12px;
      font-weight: 700;
    }
    h1, h2, h3, p { margin: 0; }
    .hero h1 {
      font-size: clamp(2rem, 4vw, 3.4rem);
      line-height: 0.96;
    }
    .hero p {
      color: var(--muted);
      max-width: 760px;
      line-height: 1.55;
    }
    .hero__actions, .filters, .selection-actions, .card-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }
    .btn, button, select, input {
      font: inherit;
    }
    .btn, button {
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px;
      padding: 12px 16px;
      color: var(--text);
      background: rgba(255,255,255,0.04);
      cursor: pointer;
    }
    .btn--primary, button.btn--primary {
      background: linear-gradient(135deg, #ffd34d, #ff9f40);
      color: #182235;
      border-color: rgba(255, 210, 77, 0.4);
      font-weight: 700;
    }
    .panel { padding: 22px; margin-bottom: 18px; }
    .selection-banner {
      display: grid;
      gap: 14px;
    }
    .selection-banner__row, .featured-grid, .catalog-grid {
      display: grid;
      gap: 16px;
    }
    .selection-banner__row {
      grid-template-columns: 1.2fr 1fr;
    }
    .featured-grid {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .filters {
      margin-top: 16px;
    }
    .filters input, .filters select {
      min-height: 48px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.1);
      background: rgba(255,255,255,0.05);
      color: var(--text);
      padding: 0 14px;
      min-width: 180px;
    }
    .catalog-grid {
      grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
      margin-top: 18px;
    }
    .ngo-card {
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 18px;
      background: var(--panel-soft);
      display: grid;
      gap: 14px;
    }
    .ngo-card__head {
      display: grid;
      grid-template-columns: 64px 1fr;
      gap: 14px;
      align-items: center;
    }
    .ngo-card__logo {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      overflow: hidden;
      background: rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      color: #8dd0ff;
    }
    .ngo-card__logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .ngo-card__badges {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 8px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.04em;
      background: rgba(255,255,255,0.07);
      color: #d9e8ff;
    }
    .badge--featured { background: var(--accent-soft); color: #ffd34d; }
    .badge--selected { background: rgba(73, 217, 140, 0.18); color: #8ff0bc; }
    .ngo-card__meta, .help, .selection-copy, .empty {
      color: var(--muted);
      line-height: 1.5;
    }
    .ngo-card__title {
      font-size: 1.15rem;
      font-weight: 700;
    }
    .ngo-card__mission {
      min-height: 66px;
      color: #e4eefc;
    }
    .selection-banner__status {
      border-radius: 16px;
      padding: 16px;
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(255,255,255,0.04);
    }
    .selection-banner__status strong {
      display: block;
      margin-bottom: 8px;
      color: #fff;
    }
    .hidden { display: none !important; }
    @media (max-width: 840px) {
      .selection-banner__row { grid-template-columns: 1fr; }
      .shell { width: min(100% - 20px, 1180px); padding-top: 20px; }
      .hero, .panel { padding: 18px; }
    }
  </style>
</head>
<body>
  <main class="shell" data-vb2026-ngo-catalog data-rest-base="<?php echo esc_attr($restBase); ?>" data-campaign="<?php echo esc_attr($campaign); ?>">
    <section class="hero">
      <div class="eyebrow">Sharity x VB2026</div>
      <h1>Válassz civil ügyet</h1>
      <p>A VB2026 kiemeltjei mellett más Sharity-szervezetet is választhatsz. A saját választásod lesz az irányadó akkor is, ha nem a kiemelt listából döntesz.</p>
      <div class="hero__actions">
        <button type="button" class="btn btn--primary" data-scroll-target="featuredSection">Top 10 megnézése</button>
        <button type="button" class="btn" data-scroll-target="catalogSection">Összes szervezet</button>
      </div>
    </section>

    <section class="panel selection-banner">
      <div class="eyebrow">A te ügyed</div>
      <div class="selection-banner__row">
        <div class="selection-banner__status" id="mySelectionCard">
          <strong>Betöltés folyamatban...</strong>
          <div class="selection-copy" id="mySelectionCopy">A saját NGO állapotodat rögtön ellenőrizzük.</div>
          <div class="selection-actions" style="margin-top:12px;">
            <button type="button" class="btn btn--primary" id="mySelectionBrowseBtn">Szervezetek böngészése</button>
            <button type="button" class="btn hidden" id="mySelectionManageBtn">A választásom kezelése</button>
          </div>
        </div>
        <div class="selection-banner__status">
          <strong>Mit érdemes tudni?</strong>
          <div class="help">Ha még nincs kiválasztott ügyed, itt a Sharity oldalon döntesz róla. A FactLens VB2026 játék csak ezt az állapotot olvassa vissza, nem tárol külön saját NGO-truthot.</div>
        </div>
      </div>
    </section>

    <section class="panel" id="featuredSection">
      <div class="eyebrow">VB2026 kiemeltek</div>
      <h2>Top 10 szervezet</h2>
      <p class="help" style="margin-top:8px;">Ők kapják a legerősebb kampányfókuszt, de nem vagy rájuk korlátozva.</p>
      <div class="featured-grid" id="featuredGrid" style="margin-top:16px;"></div>
    </section>

    <section class="panel" id="catalogSection">
      <div class="eyebrow">Összes szervezet</div>
      <h2>Keress a teljes Sharity-katalógusban</h2>
      <div class="filters">
        <input type="search" id="filterQuery" placeholder="Kezdd el írni a szervezet nevét...">
        <select id="filterCategory"><option value="">Minden kategória</option></select>
        <select id="filterLegalStatus"><option value="">Minden jogállás</option></select>
        <select id="filterCity"><option value="">Minden település</option></select>
        <button type="button" class="btn" id="filterResetBtn">Szűrők törlése</button>
      </div>
      <div class="catalog-grid" id="catalogGrid"></div>
      <div class="empty hidden" id="catalogEmpty" style="margin-top:18px;">Nem találtunk ilyen szervezetet. Próbáld más névre vagy szűrőre.</div>
    </section>
  </main>

  <script>
    (() => {
      const root = document.querySelector('[data-vb2026-ngo-catalog]');
      if (!root) return;

      const restBase = root.getAttribute('data-rest-base');
      const campaign = root.getAttribute('data-campaign') || 'vb2026';
      const refs = {
        featuredGrid: document.getElementById('featuredGrid'),
        catalogGrid: document.getElementById('catalogGrid'),
        catalogEmpty: document.getElementById('catalogEmpty'),
        filterQuery: document.getElementById('filterQuery'),
        filterCategory: document.getElementById('filterCategory'),
        filterLegalStatus: document.getElementById('filterLegalStatus'),
        filterCity: document.getElementById('filterCity'),
        filterResetBtn: document.getElementById('filterResetBtn'),
        mySelectionCopy: document.getElementById('mySelectionCopy'),
        mySelectionCard: document.getElementById('mySelectionCard'),
        mySelectionBrowseBtn: document.getElementById('mySelectionBrowseBtn'),
        mySelectionManageBtn: document.getElementById('mySelectionManageBtn'),
      };

      const state = {
        selection: null,
        selectionState: 'loading',
        featured: [],
        catalog: [],
      };

      function escapeHtml(value) {
        return String(value ?? '')
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#39;');
      }

      async function fetchJson(url, options = {}) {
        const res = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'content-type': 'application/json', ...(options.headers || {}) },
          ...options,
        });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok) {
          const error = new Error(payload?.error?.message || payload?.message || `HTTP ${res.status}`);
          error.status = res.status;
          throw error;
        }
        return payload;
      }

      function cardMarkup(item) {
        const badges = [];
        if (item.is_featured_vb2026) badges.push('<span class="badge badge--featured">VB2026 kiemelt</span>');
        if (item.is_user_selected) badges.push('<span class="badge badge--selected">Most ezt támogatod</span>');
        if (item.is_active) badges.push('<span class="badge">Aktív</span>');
        const website = item.website_url ? `<a class="btn" href="${escapeHtml(item.website_url)}" target="_blank" rel="noopener">Honlap</a>` : '';
        const share = item.share_url ? `<a class="btn" href="${escapeHtml(item.share_url)}" target="_blank" rel="noopener">Megosztás</a>` : '';
        const details = item.details_url ? `<a class="btn" href="${escapeHtml(item.details_url)}" target="_blank" rel="noopener">Részletek</a>` : '';
        const logo = item.logo_url
          ? `<img src="${escapeHtml(item.logo_url)}" alt="${escapeHtml(item.name)}">`
          : escapeHtml((item.name || '?').slice(0, 2).toUpperCase());
        return `
          <article class="ngo-card" data-ngo-id="${escapeHtml(item.ngo_id)}">
            <div class="ngo-card__head">
              <div class="ngo-card__logo">${logo}</div>
              <div>
                <div class="ngo-card__title">${escapeHtml(item.name)}</div>
                <div class="ngo-card__meta">${escapeHtml(item.city || 'Település nélkül')} · ${escapeHtml(item.category_label || 'Kategória nélkül')}</div>
                <div class="ngo-card__badges">${badges.join('')}</div>
              </div>
            </div>
            <div class="ngo-card__mission">${escapeHtml(item.short_mission || 'Ehhez a szervezethez hamarosan részletesebb bemutató is érkezik.')}</div>
            <div class="ngo-card__meta">${escapeHtml(item.legal_status_label || 'Jogállás nélkül')}</div>
            <div class="card-actions">
              <button type="button" class="btn btn--primary" data-select-ngo="${escapeHtml(item.ngo_id)}">Támogatom ezt az ügyet</button>
              ${details}
              ${share}
              ${website}
            </div>
          </article>
        `;
      }

      function renderMySelection() {
        const selection = state.selection;
        if (state.selectionState === 'auth_required') {
          refs.mySelectionCard.querySelector('strong').textContent = 'A saját választásodhoz jelentkezz be';
          refs.mySelectionCopy.textContent = 'Bejelentkezés után itt rögtön látni fogod, melyik civil ügy van a VB2026 játékodhoz kapcsolva.';
          refs.mySelectionManageBtn.classList.add('hidden');
          refs.mySelectionBrowseBtn.textContent = 'Szervezetek böngészése';
          return;
        }

        if (state.selectionState === 'unavailable') {
          refs.mySelectionCard.querySelector('strong').textContent = 'A saját választásod most nem olvasható be';
          refs.mySelectionCopy.textContent = 'A szervezeti lista böngészése működik, de a személyes választási állapotod átmenetileg nem tölthető be.';
          refs.mySelectionManageBtn.classList.add('hidden');
          refs.mySelectionBrowseBtn.textContent = 'Szervezetek böngészése';
          return;
        }

        if (!selection || !selection.has_selection) {
          refs.mySelectionCard.querySelector('strong').textContent = 'Még nincs kiválasztott ügyed';
          refs.mySelectionCopy.textContent = selection?.attention_message || 'Válassz most civil ügyet, hogy a VB2026 játékodhoz egyértelműen kapcsolódjon a támogatási irány.';
          refs.mySelectionManageBtn.classList.add('hidden');
          refs.mySelectionBrowseBtn.textContent = 'Szervezetek böngészése';
          return;
        }

        const ngo = selection.selected_ngo || {};
        refs.mySelectionCard.querySelector('strong').textContent = `Most ezt támogatod: ${ngo.name || '-'}`;
        refs.mySelectionCopy.textContent = selection.needs_attention
          ? (selection.attention_message || 'A választott szervezeted már figyelmet igényel.')
          : `${ngo.city || 'Település nélkül'} · ${ngo.short_mission || 'A választásod aktív a VB2026 játékhoz.'}`;
        refs.mySelectionManageBtn.classList.remove('hidden');
      }

      function applyCatalogFilters(items) {
        const q = (refs.filterQuery.value || '').trim().toLowerCase();
        const category = refs.filterCategory.value || '';
        const legalStatus = refs.filterLegalStatus.value || '';
        const city = refs.filterCity.value || '';
        return items.filter((item) => {
          if (q && !`${item.name || ''} ${item.short_mission || ''}`.toLowerCase().includes(q)) return false;
          if (category && item.category_label !== category) return false;
          if (legalStatus && item.legal_status_label !== legalStatus) return false;
          if (city && item.city !== city) return false;
          return true;
        });
      }

      function renderCatalog() {
        const items = applyCatalogFilters(state.catalog);
        refs.catalogGrid.innerHTML = items.map(cardMarkup).join('');
        refs.catalogEmpty.classList.toggle('hidden', items.length > 0);
      }

      function renderFeatured() {
        refs.featuredGrid.innerHTML = state.featured.map(cardMarkup).join('');
      }

      function fillOptions(select, values) {
        const current = select.value;
        select.innerHTML = `<option value="">${select.options[0]?.textContent || 'Összes'}</option>`;
        values.forEach((value) => {
          if (!value) return;
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value;
          select.appendChild(option);
        });
        select.value = current;
      }

      function hydrateFilterOptions(items) {
        fillOptions(refs.filterCategory, [...new Set(items.map((item) => item.category_label).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'hu')));
        fillOptions(refs.filterLegalStatus, [...new Set(items.map((item) => item.legal_status_label).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'hu')));
        fillOptions(refs.filterCity, [...new Set(items.map((item) => item.city).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'hu')));
      }

      async function loadSelection() {
        try {
          state.selection = await fetchJson(`${restBase}/vb2026/my-ngo-selection?contest_scope=${encodeURIComponent(campaign)}`);
          state.selectionState = 'ready';
        } catch (error) {
          state.selection = null;
          state.selectionState = error?.status === 401 ? 'auth_required' : 'unavailable';
        }
        renderMySelection();
      }

      async function loadFeatured() {
        const payload = await fetchJson(`${restBase}/vb2026/featured-ngos?campaign=${encodeURIComponent(campaign)}`);
        state.featured = Array.isArray(payload.items) ? payload.items : [];
        renderFeatured();
      }

      async function loadCatalog() {
        const payload = await fetchJson(`${restBase}/ngo-catalog?campaign=${encodeURIComponent(campaign)}&active_only=1&per_page=48`);
        state.catalog = Array.isArray(payload.results) ? payload.results : [];
        hydrateFilterOptions(state.catalog);
        renderCatalog();
      }

      async function selectNgo(ngoId) {
        try {
          await fetchJson(`${restBase}/vb2026/select-ngo`, {
            method: 'POST',
            body: JSON.stringify({
              selected_sharity_ngo_id: Number(ngoId),
              contest_scope: campaign,
              source_context: 'sharity_catalog',
            }),
          });
          await loadSelection();
          await loadCatalog();
          await loadFeatured();
        } catch (error) {
          try {
            const payload = await fetchJson(`${restBase}/vb2026/selection-intent`, {
              method: 'POST',
              body: JSON.stringify({
                selected_sharity_ngo_id: Number(ngoId),
                contest_scope: campaign,
                return_to: 'catalog',
              }),
            });
            if (payload?.auth_url) {
              window.location.assign(payload.auth_url);
              return;
            }
          } catch {
            state.selectionState = 'unavailable';
            renderMySelection();
          }
          alert(error.message || 'A választás most nem sikerült.');
        }
      }

      root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-select-ngo]');
        if (trigger?.dataset.selectNgo) {
          selectNgo(trigger.dataset.selectNgo);
          return;
        }
        const scrollTrigger = event.target.closest('[data-scroll-target]');
        if (scrollTrigger?.dataset.scrollTarget) {
          document.getElementById(scrollTrigger.dataset.scrollTarget)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });

      [refs.filterQuery, refs.filterCategory, refs.filterLegalStatus, refs.filterCity].forEach((element) => {
        element.addEventListener('input', renderCatalog);
        element.addEventListener('change', renderCatalog);
      });

      refs.filterResetBtn.addEventListener('click', () => {
        refs.filterQuery.value = '';
        refs.filterCategory.value = '';
        refs.filterLegalStatus.value = '';
        refs.filterCity.value = '';
        renderCatalog();
      });

      refs.mySelectionBrowseBtn.addEventListener('click', () => {
        document.getElementById('catalogSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      refs.mySelectionManageBtn.addEventListener('click', () => {
        document.getElementById('catalogSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });

      Promise.all([loadSelection(), loadFeatured(), loadCatalog()]).catch(() => {
        refs.catalogEmpty.textContent = 'A szervezeti lista most átmenetileg nem érhető el.';
        refs.catalogEmpty.classList.remove('hidden');
      });
    })();
  </script>
</body>
</html>
    <?php
    return (string) ob_get_clean();
}

function impactshop_vb2026_bool_param($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function impactshop_vb2026_error_response(string $code, string $message, int $status): WP_REST_Response
{
    return new WP_REST_Response([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $status);
}

function impactshop_vb2026_mask_pseudo(string $pseudo): string
{
    $pseudo = trim($pseudo);
    if (strlen($pseudo) <= 6) {
        return $pseudo;
    }
    return substr($pseudo, 0, 3) . '***' . substr($pseudo, -3);
}

function impactshop_vb2026_get_pseudo_id(): string
{
    if (function_exists('sharity_points_get_pseudo_from_cookie')) {
        $pseudo = (string) sharity_points_get_pseudo_from_cookie();
        if ($pseudo !== '') {
            return $pseudo;
        }
    }
    if (function_exists('impactshop_identity_profile_cookie')) {
        $pseudo = (string) impactshop_identity_profile_cookie();
        if ($pseudo !== '') {
            return $pseudo;
        }
    }
    if (!empty($_COOKIE['impactshop_pseudo_id'])) {
        return sanitize_text_field((string) wp_unslash($_COOKIE['impactshop_pseudo_id']));
    }
    return '';
}

function impactshop_vb2026_resolve_service_token(): string
{
    $option = get_option('impactshop_factlens_bridge_service_token');
    if (is_string($option) && trim($option) !== '') {
        return trim($option);
    }
    if (defined('IMPACTSHOP_FACTLENS_BRIDGE_SERVICE_TOKEN') && is_string(IMPACTSHOP_FACTLENS_BRIDGE_SERVICE_TOKEN) && trim(IMPACTSHOP_FACTLENS_BRIDGE_SERVICE_TOKEN) !== '') {
        return trim(IMPACTSHOP_FACTLENS_BRIDGE_SERVICE_TOKEN);
    }
    if (defined('SHARITY_POINTS_HMAC_SECRET') && is_string(SHARITY_POINTS_HMAC_SECRET) && trim(SHARITY_POINTS_HMAC_SECRET) !== '') {
        return trim(SHARITY_POINTS_HMAC_SECRET);
    }
    return wp_salt('sharity_points');
}

function impactshop_vb2026_resolve_request_pseudo(WP_REST_Request $request, bool $allowServiceAuth): array
{
    $pseudo = impactshop_vb2026_get_pseudo_id();
    if ($pseudo !== '') {
        return [
            'pseudo_id' => $pseudo,
            'service_auth' => false,
        ];
    }

    if (!$allowServiceAuth) {
        return ['pseudo_id' => '', 'service_auth' => false];
    }

    $header = trim((string) $request->get_header('authorization'));
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return ['pseudo_id' => '', 'service_auth' => false];
    }
    $provided = trim((string) ($matches[1] ?? ''));
    $expected = impactshop_vb2026_resolve_service_token();
    if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
        return ['pseudo_id' => '', 'service_auth' => false];
    }

    $headerPseudo = sanitize_text_field((string) $request->get_header('x-sharity-pseudo-id'));
    if ($headerPseudo === '') {
        return ['pseudo_id' => '', 'service_auth' => false];
    }
    if (function_exists('impactshop_identity_profile_valid_pseudo') && !impactshop_identity_profile_valid_pseudo($headerPseudo)) {
        return ['pseudo_id' => '', 'service_auth' => false];
    }

    return [
        'pseudo_id' => strtolower($headerPseudo),
        'service_auth' => true,
    ];
}

function impactshop_vb2026_browser_write_allowed(WP_REST_Request $request): bool
{
    $origin = trim((string) $request->get_header('origin'));
    $referer = trim((string) $request->get_header('referer'));
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if ($origin !== '') {
        $originHost = wp_parse_url($origin, PHP_URL_HOST);
        return is_string($originHost) && is_string($host) && strtolower($originHost) === strtolower($host);
    }
    if ($referer !== '') {
        $refererHost = wp_parse_url($referer, PHP_URL_HOST);
        return is_string($refererHost) && is_string($host) && strtolower($refererHost) === strtolower($host);
    }
    return false;
}

function impactshop_vb2026_is_allowed_return_to(string $returnTo): bool
{
    return in_array($returnTo, ['vb-prod', 'catalog', 'sharity'], true);
}

function impactshop_vb2026_name_key(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = remove_accents(mb_strtolower(trim($value)));
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', (string) $value);
    return trim((string) $value);
}

function impactshop_vb2026_safe_url(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }
    $sanitized = esc_url_raw($value);
    return $sanitized !== '' ? $sanitized : null;
}

function impactshop_vb2026_parse_campaign_count(string $value): int
{
    if (preg_match('/(\d+)/', $value, $matches)) {
        return (int) $matches[1];
    }
    return 0;
}

function impactshop_vb2026_truncate(?string $value, int $limit = 320): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = wp_strip_all_tags($value);
    if (mb_strlen($value) <= $limit) {
        return $value;
    }
    return trim(mb_substr($value, 0, $limit - 1)) . '…';
}

function impactshop_vb2026_ensure_catalog_sync(): void
{
    $lastSync = get_transient(IMPACTSHOP_VB2026_NGO_SYNC_TRANSIENT);
    if ($lastSync && is_numeric($lastSync) && ((int) $lastSync + IMPACTSHOP_VB2026_NGO_SYNC_TTL) > time()) {
        return;
    }

    $datasetItems = class_exists('ImpactShop_NGO_Card_API')
        ? ImpactShop_NGO_Card_API::get_dataset_items(true)
        : [];
    if (!is_array($datasetItems)) {
        $datasetItems = [];
    }

    $response = wp_remote_get(IMPACTSHOP_VB2026_NGO_CSV_URL, [
        'timeout' => 20,
        'redirection' => 3,
        'headers' => ['Accept' => 'text/csv'],
    ]);
    if (is_wp_error($response)) {
        return;
    }
    $body = (string) wp_remote_retrieve_body($response);
    if ($body === '') {
        return;
    }

    $rows = impactshop_vb2026_parse_csv_rows($body);
    if (!$rows) {
        return;
    }

    if (!impactshop_vb2026_catalog_sync_passes_publish_gate($rows)) {
        return;
    }

    impactshop_vb2026_upsert_catalog_rows($rows, $datasetItems);
    set_transient(IMPACTSHOP_VB2026_NGO_SYNC_TRANSIENT, time(), IMPACTSHOP_VB2026_NGO_SYNC_TTL);
}

function impactshop_vb2026_catalog_sync_passes_publish_gate(array $rows): bool
{
    global $wpdb;
    $catalogTable = $wpdb->prefix . 'sharity_ngo_catalog';

    $incomingActive = 0;
    foreach ($rows as $row) {
        if (trim((string) ($row['Státusz'] ?? '')) === 'Aktív') {
            $incomingActive += 1;
        }
    }

    if ($incomingActive < IMPACTSHOP_VB2026_NGO_MIN_ACTIVE_ROWS) {
        return false;
    }

    $currentActive = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$catalogTable} WHERE is_active = 1");
    if ($currentActive > 0) {
        $minimumAllowed = max(
            IMPACTSHOP_VB2026_NGO_MIN_ACTIVE_ROWS,
            (int) floor($currentActive * IMPACTSHOP_VB2026_NGO_ACTIVE_DROP_RATIO)
        );
        if ($incomingActive < $minimumAllowed) {
            return false;
        }
    }

    return true;
}

function impactshop_vb2026_parse_csv_rows(string $csv): array
{
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
    $stream = fopen('php://temp', 'r+');
    if (!$stream) {
        return [];
    }
    fwrite($stream, $csv);
    rewind($stream);
    $header = fgetcsv($stream);
    if (!$header) {
        fclose($stream);
        return [];
    }
    $header = array_map(static fn($value) => trim((string) $value), $header);
    $rows = [];
    while (($row = fgetcsv($stream)) !== false) {
        if (!$row || count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }
        $assoc = [];
        foreach ($header as $index => $label) {
            $assoc[$label] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }
        $rows[] = $assoc;
    }
    fclose($stream);
    return $rows;
}

function impactshop_vb2026_upsert_catalog_rows(array $rows, array $datasetItems): void
{
    global $wpdb;
    $catalogTable = $wpdb->prefix . 'sharity_ngo_catalog';
    $flagsTable = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $now = current_time('mysql', true);

    $cardsByName = [];
    foreach ($datasetItems as $slug => $item) {
        if (!is_array($item)) {
            continue;
        }
        $item['slug'] = $item['slug'] ?? $slug;
        $key = impactshop_vb2026_name_key((string) ($item['name'] ?? $slug));
        if ($key !== '' && !isset($cardsByName[$key])) {
            $cardsByName[$key] = $item;
        }
    }

    foreach ($rows as $row) {
        $ngoId = absint($row['Azonosító'] ?? 0);
        $name = trim((string) ($row['Név'] ?? ''));
        if ($ngoId <= 0 || $name === '') {
            continue;
        }

        $card = $cardsByName[impactshop_vb2026_name_key($name)] ?? null;
        $slug = sanitize_title((string) ($card['slug'] ?? $name));
        if ($slug === '') {
            continue;
        }

        $shortMission = impactshop_vb2026_truncate($row['Cél'] ?? '') ?: impactshop_vb2026_truncate($row['Tevékenység'] ?? '');
        $isActive = trim((string) ($row['Státusz'] ?? '')) === 'Aktív' ? 1 : 0;
        $campaignCount = impactshop_vb2026_parse_campaign_count((string) ($row['Kampányok száma'] ?? '0'));
        $logoUrl = impactshop_vb2026_safe_url($card['logo_url'] ?? '') ?: impactshop_vb2026_safe_url($row['Logó'] ?? '');
        $coverUrl = impactshop_vb2026_safe_url($card['og_image'] ?? '') ?: impactshop_vb2026_safe_url($row['Kép'] ?? '');
        $shareUrl = impactshop_vb2026_safe_url($card['share_url'] ?? '');
        $detailsUrl = impactshop_vb2026_safe_url($card['cta_url'] ?? '') ?: impactshop_vb2026_safe_url($row['Adomany.sharity.hu link'] ?? '');
        $slugSource = is_array($card) ? 'ngo_card' : 'fallback_name';
        $createdAt = impactshop_vb2026_parse_hu_datetime((string) ($row['Létrehozás dátuma'] ?? ''));
        $hash = hash('sha256', wp_json_encode([
            'ngo_id' => $ngoId,
            'slug' => $slug,
            'name' => $name,
            'city' => $row['Székhely - Város'] ?? '',
            'category' => $row['Kategóriák'] ?? '',
            'legal_status' => $row['Jogállás'] ?? '',
            'mission' => $shortMission,
            'website' => $row['Honlap'] ?? '',
            'logo' => $logoUrl,
            'cover' => $coverUrl,
            'share' => $shareUrl,
            'details' => $detailsUrl,
            'active' => $isActive,
            'campaign_count' => $campaignCount,
        ]));

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, source_row_hash FROM {$catalogTable} WHERE sharity_ngo_id = %d LIMIT 1",
            $ngoId
        ), ARRAY_A);
        if (is_array($existing) && ($existing['source_row_hash'] ?? '') === $hash) {
            $wpdb->update(
                $catalogTable,
                [
                    'source_last_synced_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $data = [
                'sharity_ngo_id' => $ngoId,
                'slug' => $slug,
                'slug_source' => $slugSource,
                'name' => $name,
                'postal_code' => trim((string) ($row['Székhely - Irányítószám'] ?? '')) ?: null,
                'city' => trim((string) ($row['Székhely - Város'] ?? '')) ?: null,
                'county' => null,
                'category_label' => trim((string) ($row['Kategóriák'] ?? '')) ?: null,
                'legal_status_label' => trim((string) ($row['Jogállás'] ?? '')) ?: null,
                'short_mission' => $shortMission,
                'website_url' => impactshop_vb2026_safe_url($row['Honlap'] ?? ''),
                'raw_logo_url' => impactshop_vb2026_safe_url($row['Logó'] ?? ''),
                'raw_cover_image_url' => impactshop_vb2026_safe_url($row['Kép'] ?? ''),
                'logo_url' => $logoUrl,
                'cover_image_url' => $coverUrl,
                'share_url' => $shareUrl,
                'details_url' => $detailsUrl,
                'campaign_count' => $campaignCount,
                'source_status_label' => trim((string) ($row['Státusz'] ?? '')) ?: null,
                'is_active' => $isActive,
                'source_created_at' => $createdAt,
                'source_last_synced_at' => $now,
                'source_row_hash' => $hash,
                'created_at' => is_array($existing) ? null : $now,
                'updated_at' => $now,
            ];

            if (is_array($existing)) {
                unset($data['created_at']);
                $wpdb->update($catalogTable, $data, ['id' => (int) $existing['id']]);
            } else {
                $wpdb->insert($catalogTable, $data);
            }
        }

        $featured = is_array($card) && isset($card['rank']) && (int) $card['rank'] > 0 && (int) $card['rank'] <= 10 ? 1 : 0;
        $priority = $featured ? max(1, (int) ($card['rank'] ?? 1000)) : 1000 + $ngoId;
        $badge = $featured ? 'TOP 10' : null;
        $flagExists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$flagsTable} WHERE campaign_key = %s AND sharity_ngo_id = %d LIMIT 1",
            IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY,
            $ngoId
        ));
        if (!$flagExists) {
            $wpdb->insert($flagsTable, [
                'campaign_key' => IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY,
                'sharity_ngo_id' => $ngoId,
                'is_featured' => $featured,
                'is_race_visible' => $featured,
                'allow_public_listing' => 1,
                'allow_user_selection' => $isActive,
                'display_priority' => $priority,
                'hero_badge' => $badge,
                'campaign_copy_short' => $featured ? 'VB2026 kiemelt szervezet' : null,
                'campaign_copy_long' => null,
                'campaign_state' => 'active',
                'updated_by' => null,
                'updated_at' => $now,
            ]);
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$flagsTable}
                 SET is_featured = %d,
                     is_race_visible = %d,
                     display_priority = %d,
                     hero_badge = %s,
                     campaign_copy_short = %s,
                     allow_user_selection = %d,
                     updated_at = %s
                 WHERE campaign_key = %s AND sharity_ngo_id = %d",
                $featured,
                $featured,
                $priority,
                $badge,
                $featured ? 'VB2026 kiemelt szervezet' : null,
                $isActive,
                $now,
                IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY,
                $ngoId
            ));
        }
    }
}

function impactshop_vb2026_parse_hu_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y.m.d. H:i:s', $value, new DateTimeZone('Europe/Budapest'));
    if (!$dt) {
        return null;
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

function impactshop_vb2026_catalog_query(array $filters, int $page, int $perPage): array
{
    global $wpdb;
    $catalog = $wpdb->prefix . 'sharity_ngo_catalog';
    $flags = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $selectionPayload = impactshop_vb2026_selection_response_payload(impactshop_vb2026_get_selection_by_pseudo(
        impactshop_vb2026_get_pseudo_id(),
        (string) $filters['campaign']
    ));
    $selectedNgoId = !empty($selectionPayload['selected_ngo']['ngo_id']) ? (int) $selectionPayload['selected_ngo']['ngo_id'] : 0;

    $where = ["cf.campaign_key = %s"];
    $params = [$filters['campaign']];

    if ($filters['active_only']) {
        $where[] = "c.is_active = 1";
    }
    if ($filters['featured_only']) {
        $where[] = "cf.is_featured = 1";
    }
    $where[] = "cf.allow_public_listing = 1";
    $where[] = "cf.campaign_state = 'active'";
    if ($filters['q'] !== '') {
        $where[] = "(c.name LIKE %s OR c.short_mission LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($filters['q']) . '%';
        $params[] = '%' . $wpdb->esc_like($filters['q']) . '%';
    }
    if ($filters['category'] !== '') {
        $where[] = "c.category_label = %s";
        $params[] = $filters['category'];
    }
    if ($filters['legal_status'] !== '') {
        $where[] = "c.legal_status_label = %s";
        $params[] = $filters['legal_status'];
    }
    if ($filters['city'] !== '') {
        $where[] = "c.city = %s";
        $params[] = $filters['city'];
    }

    $whereSql = implode(' AND ', $where);
    $offset = ($page - 1) * $perPage;
    $baseFrom = "FROM {$catalog} c
        INNER JOIN {$flags} cf
          ON cf.sharity_ngo_id = c.sharity_ngo_id
        WHERE {$whereSql}";

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) {$baseFrom}", ...$params));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, cf.is_featured, cf.is_race_visible, cf.hero_badge, cf.allow_public_listing, cf.allow_user_selection
         {$baseFrom}
         ORDER BY cf.is_featured DESC, cf.display_priority ASC, c.name ASC
         LIMIT %d OFFSET %d",
        ...array_merge($params, [$perPage, $offset])
    ), ARRAY_A);

    return [
        'items' => array_map(static function (array $row) use ($selectedNgoId): array {
            return impactshop_vb2026_catalog_row_to_public_item($row, $selectedNgoId);
        }, is_array($rows) ? $rows : []),
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $total,
            'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ],
    ];
}

function impactshop_vb2026_catalog_row_to_public_item(array $row, int $selectedNgoId = 0): array
{
    $badges = [];
    if (!empty($row['is_featured'])) {
        $badges[] = 'VB2026 kiemelt';
    }
    if (!empty($row['hero_badge'])) {
        $badges[] = (string) $row['hero_badge'];
    }

    return [
        'ngo_id' => (int) $row['sharity_ngo_id'],
        'slug' => (string) $row['slug'],
        'name' => (string) $row['name'],
        'city' => (string) ($row['city'] ?? ''),
        'category_label' => (string) ($row['category_label'] ?? ''),
        'legal_status_label' => (string) ($row['legal_status_label'] ?? ''),
        'short_mission' => (string) ($row['short_mission'] ?? ''),
        'logo_url' => (string) ($row['logo_url'] ?? ''),
        'cover_image_url' => (string) ($row['cover_image_url'] ?? ''),
        'website_url' => (string) ($row['website_url'] ?? ''),
        'share_url' => (string) ($row['share_url'] ?? ''),
        'details_url' => (string) ($row['details_url'] ?? ''),
        'is_active' => (bool) ($row['is_active'] ?? 0),
        'is_featured_vb2026' => (bool) ($row['is_featured'] ?? 0),
        'is_in_vb2026_race' => (bool) ($row['is_race_visible'] ?? 0),
        'is_user_selected' => $selectedNgoId > 0 && $selectedNgoId === (int) $row['sharity_ngo_id'],
        'campaign_badges' => $badges,
    ];
}

function impactshop_vb2026_get_featured_items(string $campaignKey, int $limit = 10): array
{
    global $wpdb;
    $catalog = $wpdb->prefix . 'sharity_ngo_catalog';
    $flags = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, cf.is_featured, cf.is_race_visible, cf.hero_badge, cf.display_priority
         FROM {$catalog} c
         INNER JOIN {$flags} cf
           ON cf.sharity_ngo_id = c.sharity_ngo_id
         WHERE cf.campaign_key = %s
           AND c.is_active = 1
           AND cf.allow_public_listing = 1
           AND cf.is_featured = 1
           AND cf.campaign_state = 'active'
         ORDER BY cf.display_priority ASC, c.name ASC
         LIMIT %d",
        $campaignKey,
        $limit
    ), ARRAY_A);

    $selectedNgoId = 0;
    $pseudo = impactshop_vb2026_get_pseudo_id();
    if ($pseudo !== '') {
        $selection = impactshop_vb2026_get_selection_by_pseudo($pseudo, $campaignKey);
        if (!empty($selection['selected_sharity_ngo_id'])) {
            $selectedNgoId = (int) $selection['selected_sharity_ngo_id'];
        }
    }

    return array_map(static function (array $row) use ($selectedNgoId): array {
        $item = impactshop_vb2026_catalog_row_to_public_item($row, $selectedNgoId);
        $item['rank'] = (int) ($row['display_priority'] ?? 0);
        $item['hero_badge'] = (string) ($row['hero_badge'] ?? '');
        $item['share_percent'] = null;
        return $item;
    }, is_array($rows) ? $rows : []);
}

function impactshop_vb2026_get_catalog_row(int $ngoId): ?array
{
    global $wpdb;
    $catalog = $wpdb->prefix . 'sharity_ngo_catalog';
    $flags = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT c.*, cf.is_featured, cf.is_race_visible, cf.hero_badge, cf.allow_public_listing, cf.allow_user_selection, cf.campaign_state
         FROM {$catalog} c
         LEFT JOIN {$flags} cf
           ON cf.sharity_ngo_id = c.sharity_ngo_id
          AND cf.campaign_key = %s
         WHERE c.sharity_ngo_id = %d
         LIMIT 1",
        IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY,
        $ngoId
    ), ARRAY_A);
    return is_array($row) ? $row : null;
}

function impactshop_vb2026_get_selection_by_pseudo(string $pseudoId, string $contestScope): ?array
{
    if ($pseudoId === '') {
        return null;
    }
    global $wpdb;
    $selectionTable = $wpdb->prefix . 'vb2026_user_ngo_selection';
    $catalog = $wpdb->prefix . 'sharity_ngo_catalog';
    $flags = $wpdb->prefix . 'sharity_ngo_campaign_flags';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, c.name, c.slug, c.city, c.short_mission, c.logo_url, c.is_active,
                cf.is_featured, cf.is_race_visible, cf.allow_user_selection, cf.campaign_state
         FROM {$selectionTable} s
         LEFT JOIN {$catalog} c
           ON c.sharity_ngo_id = s.selected_sharity_ngo_id
         LEFT JOIN {$flags} cf
           ON cf.sharity_ngo_id = s.selected_sharity_ngo_id
          AND cf.campaign_key = s.contest_scope
         WHERE s.pseudo_id = %s AND s.contest_scope = %s
         LIMIT 1",
        $pseudoId,
        $contestScope
    ), ARRAY_A);
    return is_array($row) ? $row : null;
}

function impactshop_vb2026_selection_response_payload(?array $selection): array
{
    $manageUrl = '/szervezetek/?campaign=' . rawurlencode(IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY) . '&view=my-choice';
    $browseUrl = '/szervezetek/?campaign=' . rawurlencode(IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY);
    if (!$selection) {
        return [
        'has_selection' => false,
        'source_status' => 'ready',
        'selected_ngo' => null,
        'is_featured' => false,
        'is_in_vb2026_race' => false,
        'can_switch' => true,
        'needs_attention' => false,
            'attention_message' => null,
            'switch_help_copy' => 'A választásodat bármikor módosíthatod.',
            'selection_urls' => [
                'manage' => $manageUrl,
                'browse' => $browseUrl,
                'select' => $browseUrl,
            ],
        ];
    }

    $needsAttention = false;
    $attentionMessage = null;
    if (!empty($selection['invalidated_at'])) {
        $needsAttention = true;
        $attentionMessage = 'A korábban kiválasztott szervezet már új döntést kér tőled.';
    } elseif ((int) ($selection['is_active'] ?? 0) !== 1 || (int) ($selection['allow_user_selection'] ?? 1) !== 1 || (($selection['campaign_state'] ?? 'active') !== 'active')) {
        $needsAttention = true;
        $attentionMessage = 'A kiválasztott szervezet jelenleg nem választható, kérjük válassz újat.';
    }

    return [
        'has_selection' => true,
        'source_status' => 'ready',
        'selected_ngo' => [
            'ngo_id' => (int) $selection['selected_sharity_ngo_id'],
            'name' => (string) ($selection['name'] ?? ''),
            'slug' => (string) ($selection['slug'] ?? ''),
            'city' => (string) ($selection['city'] ?? ''),
            'short_mission' => (string) ($selection['short_mission'] ?? ''),
            'logo_url' => (string) ($selection['logo_url'] ?? ''),
            'is_active' => (bool) ($selection['is_active'] ?? 0),
        ],
        'is_featured' => (bool) ($selection['is_featured'] ?? 0),
        'is_in_vb2026_race' => (bool) ($selection['is_race_visible'] ?? 0),
        'can_switch' => (($selection['selection_lock_state'] ?? 'open') === 'open'),
        'needs_attention' => $needsAttention,
        'attention_message' => $attentionMessage,
        'switch_help_copy' => 'A választásodat bármikor módosíthatod.',
        'selection_urls' => [
            'manage' => $manageUrl,
            'browse' => $browseUrl,
            'select' => $browseUrl,
        ],
    ];
}

function impactshop_vb2026_upsert_selection(string $pseudoId, string $contestScope, int $ngoId, string $selectionSource, string $actorType): array
{
    $ngo = impactshop_vb2026_get_catalog_row($ngoId);
    if (!$ngo) {
        return ['ok' => false, 'code' => 'NGO_NOT_FOUND', 'message' => 'A kiválasztott szervezet nem található.', 'status' => 404];
    }
    if ((int) ($ngo['is_active'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'NGO_NOT_ACTIVE', 'message' => 'Ez a szervezet jelenleg nem aktív.', 'status' => 409];
    }
    if ((int) ($ngo['allow_user_selection'] ?? 1) !== 1 || (($ngo['campaign_state'] ?? 'active') !== 'active')) {
        return ['ok' => false, 'code' => 'NGO_NOT_SELECTABLE', 'message' => 'Ez a szervezet jelenleg nem választható.', 'status' => 409];
    }

    global $wpdb;
    $selectionTable = $wpdb->prefix . 'vb2026_user_ngo_selection';
    $auditTable = $wpdb->prefix . 'vb2026_ngo_selection_audit_log';
    $existing = impactshop_vb2026_get_selection_by_pseudo($pseudoId, $contestScope);
    $now = current_time('mysql', true);
    $existingNgoId = !empty($existing['selected_sharity_ngo_id']) ? (int) $existing['selected_sharity_ngo_id'] : 0;

    if ($existingNgoId === $ngoId) {
        return [
            'ok' => true,
            'selected_ngo' => impactshop_vb2026_catalog_row_to_public_item($ngo),
            'effective_from' => gmdate('c'),
        ];
    }

    $data = [
        'pseudo_id' => $pseudoId,
        'contest_scope' => $contestScope,
        'selected_sharity_ngo_id' => $ngoId,
        'selection_source' => $selectionSource,
        'was_featured_at_selection_time' => !empty($ngo['is_featured']) ? 1 : 0,
        'selection_lock_state' => 'open',
        'selected_at' => $existing ? ($existing['selected_at'] ?? $now) : $now,
        'last_changed_at' => $now,
        'invalidated_at' => null,
        'invalidation_reason' => null,
    ];

    if ($existing) {
        $wpdb->update($selectionTable, $data, ['id' => (int) $existing['id']]);
    } else {
        $wpdb->insert($selectionTable, $data);
    }

    $wpdb->insert($auditTable, [
        'pseudo_id' => $pseudoId,
        'contest_scope' => $contestScope,
        'previous_sharity_ngo_id' => $existingNgoId ?: null,
        'new_sharity_ngo_id' => $ngoId,
        'selection_source' => $selectionSource,
        'actor_type' => $actorType,
        'result_state' => 'selected',
        'reason_code' => null,
        'created_at' => $now,
    ]);

    return [
        'ok' => true,
        'selected_ngo' => impactshop_vb2026_catalog_row_to_public_item($ngo),
        'effective_from' => gmdate('c'),
    ];
}
