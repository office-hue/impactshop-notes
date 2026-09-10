<?php
/**
 * Plugin Name: ImpactShop Identity Panel
 * Description: Shortcode for pseudo ID display, portable sign-in and nickname.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_profile_get',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/total',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_profile_total',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_profile_update',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/restore',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_profile_restore',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/refresh-nonce',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_refresh_nonce',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/code/generate',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_code_generate',
            'permission_callback' => '__return_true',
        ]
    );
});

/**
 * Allow unauthenticated nonce refresh (avoid cookie check block).
 *
 * @param mixed $result Current auth result.
 * @return mixed
 */
function impactshop_identity_allow_refresh_nonce($result)
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/impact/v1/identity/refresh-nonce') !== false) {
        return true;
    }
    return $result;
}
add_filter('rest_authentication_errors', 'impactshop_identity_allow_refresh_nonce', 5);

/**
 * Refresh REST nonce for identity panel.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_refresh_nonce(): WP_REST_Response
{
    return new WP_REST_Response(
        [
            'nonce' => wp_create_nonce('wp_rest'),
        ],
        200
    );
}

add_shortcode('impactshop_identity_panel', 'impactshop_identity_panel_shortcode');
add_shortcode('impactshop_identity_id', 'impactshop_identity_id_shortcode');

add_action('wp_enqueue_scripts', 'impactshop_identity_panel_register_assets');
add_action('wp_enqueue_scripts', 'impactshop_identity_profile_dequeue_adsense', 1000);
add_action('admin_init', 'impactshop_identity_register_broadcast_setting');
// The boot MU plugin creates its legacy pseudo cookie at init priority 1.
// Install storage and bind a new browser before that compatibility callback
// can run, otherwise a first HTML request becomes legacy_read_only forever.
add_action('init', 'impactshop_identity_maybe_install_owner_grants', 0);
add_action('init', 'impactshop_identity_maybe_bootstrap_owner_binding', 0);
add_action('init', 'impactshop_identity_handle_password_manager_save');
add_action('template_redirect', 'impactshop_identity_profile_cache_headers', -999);
add_action('template_redirect', 'impactshop_identity_profile_suppress_ads', -998);
add_action('template_redirect', 'impactshop_identity_render_code_page', 0);
add_action('template_redirect', 'impactshop_identity_maybe_complete_profile_return', 1);
add_action('elementor/frontend/widget/before_render', 'impactshop_identity_suppress_profile_adsense_widget', 1);
add_filter('rest_post_dispatch', 'impactshop_identity_profile_rest_cache_headers', 1000, 3);

/**
 * Return the normalized path used by the profile route classifier.
 *
 * The classifier is intentionally path-only: query strings, fragments and
 * host aliases must not turn the profile cache/privacy boundary off.
 *
 * @param string|null $request_uri Optional request URI override for tests.
 * @return string
 */
function impactshop_identity_profile_route_path(?string $request_uri = null): string
{
    $request_uri = $request_uri ?? (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) parse_url($request_uri, PHP_URL_PATH);
    return '/' . ltrim(untrailingslashit($path), '/');
}

/**
 * Shared classifier for the complete /profil route family.
 *
 * @param string|null $request_uri Optional request URI override for tests.
 * @return bool
 */
function impactshop_identity_is_profile_route(?string $request_uri = null): bool
{
    $path = impactshop_identity_profile_route_path($request_uri);
    return $path === '/profil' || str_starts_with($path, '/profil/');
}

/**
 * Canonical in-environment profile action target.
 *
 * @return string
 */
function impactshop_identity_canonical_profile_url(): string
{
    return home_url('/profil/#impactshop-account-top');
}

/**
 * Prevent shared/page caches from serving a profile response to another
 * cookie jar. This runs before a theme or page builder renders the page.
 *
 * @return void
 */
function impactshop_identity_profile_cache_headers(): void
{
    if (!impactshop_identity_is_profile_route()) {
        return;
    }

    nocache_headers();
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    header('Vary: Cookie', false);
}

/**
 * Disable repository and known third-party ad integrations on profile pages.
 * No rendered HTML is rewritten; each producer is disabled at its hook.
 *
 * @return void
 */
function impactshop_identity_profile_suppress_ads(): void
{
    if (!impactshop_identity_is_profile_route()) {
        return;
    }

    add_filter('googlesitekit_adsense_enabled', '__return_false', 1000);
    add_filter('googlesitekit_ads_enabled', '__return_false', 1000);
    add_filter('googlesitekit_modules_enabled', 'impactshop_identity_profile_site_kit_modules', 1000);
}

/**
 * Dequeue only clearly ad-related frontend handles on profile pages.
 *
 * @return void
 */
function impactshop_identity_profile_dequeue_adsense(): void
{
    if (!impactshop_identity_is_profile_route() || !function_exists('wp_scripts')) {
        return;
    }

    $scripts = wp_scripts();
    if (!$scripts || !is_array($scripts->queue ?? null)) {
        return;
    }
    foreach ($scripts->queue as $handle) {
        if (preg_match('/(?:adsense|google[-_]?ads)/i', (string) $handle)) {
            wp_dequeue_script((string) $handle);
        }
    }
}

/**
 * Remove only Site Kit's AdSense module from its enabled-module map.
 *
 * @param mixed $modules Site Kit module map.
 * @return mixed
 */
function impactshop_identity_profile_site_kit_modules($modules)
{
    if (!is_array($modules)) {
        return $modules;
    }
    foreach (['adsense', 'google-adsense', 'adSense'] as $key) {
        unset($modules[$key]);
    }
    return $modules;
}

/**
 * Suppress an exact Elementor AdSense widget on profile pages.
 *
 * @param mixed $widget Elementor widget instance.
 * @return void
 */
function impactshop_identity_suppress_profile_adsense_widget($widget): void
{
    if (!impactshop_identity_is_profile_route() || !is_object($widget) || !method_exists($widget, 'get_name')) {
        return;
    }

    $widget_name = strtolower((string) $widget->get_name());
    $adsense_widgets = ['adsense', 'google_adsense', 'elementor_google_adsense'];
    if (!in_array($widget_name, $adsense_widgets, true)) {
        return;
    }

    if (method_exists($widget, 'set_should_render')) {
        $widget->set_should_render(false);
    }
}

function impactshop_identity_register_broadcast_setting(): void
{
    register_setting('reading', 'impactshop_identity_broadcast_message', [
        'type' => 'string',
        'sanitize_callback' => 'impactshop_identity_sanitize_broadcast_message',
        'default' => '',
    ]);

    add_settings_field(
        'impactshop_identity_broadcast_message',
        'ImpactShop – Üzenet (Identity ID)',
        'impactshop_identity_render_broadcast_field',
        'reading'
    );
}

function impactshop_identity_owner_grant_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_identity_device_grants';
}

/**
 * Return one captured UTC clock value for the current request.
 *
 * Keeping all lifecycle comparisons on this value prevents a grant from
 * crossing a boundary half way through one request.
 */
function impactshop_identity_utc_now(): int
{
    static $now;
    if ($now === null) {
        $now = time();
    }
    return (int) $now;
}

function impactshop_identity_utc_sql(?int $timestamp = null): string
{
    return gmdate('Y-m-d H:i:s', $timestamp ?? impactshop_identity_utc_now());
}

function impactshop_identity_grant_schema_version(): int
{
    return 2;
}

/**
 * Bind a brand-new ordinary browser request to one pending owner grant.
 *
 * The legacy boot callback remains responsible for compatibility identities
 * and query overrides. This callback runs first so a normal HTML request is
 * issued exactly one pending grant and queues both binding cookies together.
 * A failed cookie/header step compensates the database row and exposes no
 * newly generated pseudo ID to the request.
 */
function impactshop_identity_maybe_bootstrap_owner_binding(): void
{
    if (!function_exists('impactshop_identity_should_touch_cookie')
        || !impactshop_identity_should_touch_cookie()) {
        return;
    }

    $query_pseudo = isset($_GET['impact_pseudo_id'])
        ? impactshop_identity_normalize_pseudo($_GET['impact_pseudo_id'])
        : '';
    if ($query_pseudo !== '') {
        return;
    }

    $known_sources = [
        $_COOKIE['impactshop_pseudo_id'] ?? null,
        $_COOKIE['impact_pseudo_id'] ?? null,
        $_COOKIE['impact_pseudo'] ?? null,
    ];
    foreach ($known_sources as $source) {
        if (impactshop_identity_normalize_pseudo($source ?? '') !== '') {
            return;
        }
    }

    // Claim the no-source ordinary-browser path before any failure can fall
    // through to impactshop-boot.php's legacy pseudo-cookie callback.
    $GLOBALS['impactshop_identity_bootstrap_block_legacy'] = true;

    try {
        $pseudo_id = impactshop_identity_profile_generate_pseudo_id();
    } catch (Throwable $exception) {
        return;
    }
    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)
        || !impactshop_identity_owner_issue($pseudo_id)) {
        return;
    }

    $pseudo_cookie_set = impactshop_identity_profile_set_cookie($pseudo_id);
    $owner_cookie_set = $pseudo_cookie_set && impactshop_identity_owner_set_pending_cookie();
    if (!$pseudo_cookie_set || !$owner_cookie_set) {
        impactshop_identity_owner_compensate_pending();
        impactshop_identity_restore_or_expire_pseudo_cookie('', $pseudo_id);
        return;
    }

    // Keep the current request on the pending path without copying the
    // HttpOnly owner token into request cookies. The next request activates.
    $_COOKIE['impactshop_pseudo_id'] = $pseudo_id;
}

function impactshop_identity_maybe_install_owner_grants(): void
{
    if ((int) get_option('impactshop_identity_owner_grants_schema', 0) >= impactshop_identity_grant_schema_version()) {
        impactshop_identity_grant_storage_ready();
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = impactshop_identity_owner_grant_table();
    $existing = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
    if ($wpdb->last_error !== '' || (is_array($existing) && strtoupper((string) ($existing['Engine'] ?? '')) !== 'INNODB')) {
        // Do not let dbDelta perform an implicit MyISAM-to-InnoDB conversion;
        // an operator-controlled staging migration is required instead.
        impactshop_identity_mark_safe_disabled();
        return;
    }
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        grant_hash char(64) NOT NULL,
        pseudo_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        last_seen_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        revoked_at datetime NULL,
        state varchar(16) NOT NULL DEFAULT 'pending',
        activated_at datetime NULL,
        supersedes_grant_hash char(64) NULL,
        version smallint unsigned NOT NULL DEFAULT 2,
        PRIMARY KEY (grant_hash),
        KEY state_expires (state, expires_at),
        KEY pseudo_active (pseudo_hash, revoked_at, expires_at),
        KEY pseudo_state (pseudo_hash, state, expires_at),
        KEY supersedes_grant (supersedes_grant_hash),
        KEY expires_at (expires_at)
    ) ENGINE=InnoDB {$charset};");
    if (!$wpdb->last_error) {
        // Existing v1 rows were already device-bound and remain readable as
        // active grants; newly issued rows always start as v2/pending.
        $wpdb->query("UPDATE {$table} SET state = 'active', activated_at = COALESCE(activated_at, created_at), version = 2 WHERE version = 1 AND revoked_at IS NULL");
        if ($wpdb->last_error !== '') {
            return;
        }
        update_option('impactshop_identity_owner_grants_schema', impactshop_identity_grant_schema_version(), false);
        impactshop_identity_grant_storage_ready();
    }
}

/** Grant lifecycle mutation is supported only by an actual transactional table. */
function impactshop_identity_grant_storage_ready(): bool
{
    static $ready = null;
    global $wpdb;
    if (function_exists('impactshop_owner_policy_safe_disabled') && impactshop_owner_policy_safe_disabled()) {
        return false;
    }
    if ($ready !== null) {
        return $ready;
    }
    $table = impactshop_identity_owner_grant_table();
    $row = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($row) || strtoupper((string) ($row['Engine'] ?? '')) !== 'INNODB') {
        impactshop_identity_mark_safe_disabled();
        $ready = false;
        return false;
    }
    $ready = true;
    return $ready;
}

function impactshop_identity_owner_hash(string $token): string
{
    return hash_hmac('sha256', $token, wp_salt('impactshop_identity_owner'));
}

function impactshop_identity_owner_pseudo_hash(string $pseudo_id): string
{
    return hash_hmac('sha256', strtolower($pseudo_id), wp_salt('impactshop_identity_owner_pseudo'));
}

function impactshop_identity_owner_cookie(): string
{
    $token = $_COOKIE['__Host-impactshop_owner'] ?? '';
    return is_string($token) ? trim((string) wp_unslash($token)) : '';
}

function impactshop_identity_request_same_origin(): bool
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $candidate = $origin !== '' ? $origin : $referer;
    if ($candidate === '') {
        return false;
    }
    $candidate_parts = wp_parse_url($candidate);
    $home_parts = wp_parse_url(home_url('/'));
    if (!is_array($candidate_parts) || !is_array($home_parts)) {
        return false;
    }
    $candidate_scheme = strtolower((string) ($candidate_parts['scheme'] ?? ''));
    $home_scheme = strtolower((string) ($home_parts['scheme'] ?? ''));
    $candidate_host = strtolower((string) ($candidate_parts['host'] ?? ''));
    $home_host = strtolower((string) ($home_parts['host'] ?? ''));
    $candidate_port = isset($candidate_parts['port']) ? (int) $candidate_parts['port'] : ($candidate_scheme === 'https' ? 443 : 80);
    $home_port = isset($home_parts['port']) ? (int) $home_parts['port'] : ($home_scheme === 'https' ? 443 : 80);
    return $candidate_scheme !== ''
        && $candidate_scheme === $home_scheme
        && $candidate_host !== ''
        && hash_equals($home_host, $candidate_host)
        && $candidate_port === $home_port;
}

function impactshop_identity_grant_state_valid(string $state): bool
{
    return in_array($state, ['pending', 'active'], true);
}

function impactshop_identity_mark_safe_disabled(): void
{
    if (function_exists('impactshop_owner_policy_safe_disable_set')) {
        impactshop_owner_policy_safe_disable_set(true);
        return;
    }
    update_option('impactshop_owner_policy_safe_disable', true, false);
}

function impactshop_identity_expire_binding_cookies(string $pseudo_id, string $owner_token): bool
{
    if (headers_sent()) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $owner_ok = setcookie('__Host-impactshop_owner', '', [
        'expires' => impactshop_identity_utc_now() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $pseudo_ok = impactshop_identity_profile_set_cookie($pseudo_id, impactshop_identity_utc_now() - 3600);
    return $owner_ok && $pseudo_ok;
}

/** Restore the previous pseudo binding, or remove a newly created one. */
function impactshop_identity_restore_or_expire_pseudo_cookie(string $prior_pseudo_id, string $new_pseudo_id): bool
{
    if (headers_sent()) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $prior_pseudo_id = strtolower(trim($prior_pseudo_id));
    if ($prior_pseudo_id !== '' && impactshop_identity_profile_valid_pseudo($prior_pseudo_id)) {
        $restored = impactshop_identity_profile_set_cookie($prior_pseudo_id);
    } else {
        $restored = impactshop_identity_profile_set_cookie($new_pseudo_id, impactshop_identity_utc_now() - 3600);
    }
    if (!$restored) {
        impactshop_identity_mark_safe_disabled();
    }
    return $restored;
}

function impactshop_identity_queue_binding_cookies(string $pseudo_id, string $owner_token, int $expires): bool
{
    if (headers_sent() || $owner_token === '' || !preg_match('/^[a-f0-9]{64}$/i', $owner_token)) {
        return false;
    }
    $owner_ok = setcookie('__Host-impactshop_owner', $owner_token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $pseudo_ok = $owner_ok && impactshop_identity_profile_set_cookie($pseudo_id, $expires);
    if (!$pseudo_ok && $owner_ok) {
        impactshop_identity_expire_binding_cookies($pseudo_id, $owner_token);
    }
    return $owner_ok && $pseudo_ok;
}

function impactshop_identity_owner_current_valid_grant_hash(): ?string
{
    $token = impactshop_identity_owner_cookie();
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token) || !impactshop_identity_grant_storage_ready()) {
        return null;
    }
    global $wpdb;
    $hash = impactshop_identity_owner_hash($token);
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT grant_hash FROM ' . impactshop_identity_owner_grant_table() . ' WHERE grant_hash = %s AND state = \'active\' AND revoked_at IS NULL AND expires_at > %s LIMIT 1',
        $hash,
        impactshop_identity_utc_sql()
    ), ARRAY_A);
    if ($wpdb->last_error !== '') {
        impactshop_identity_mark_safe_disabled();
        return null;
    }
    return is_array($row) ? (string) $row['grant_hash'] : null;
}

function impactshop_identity_grant_compensate_revoke(string $grant_hash): bool
{
    global $wpdb;
    $table = impactshop_identity_owner_grant_table();
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET revoked_at = %s, state = 'pending' WHERE grant_hash = %s AND revoked_at IS NULL",
        impactshop_identity_utc_sql(),
        $grant_hash
    ));
    $readback = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE grant_hash = %s AND revoked_at IS NOT NULL",
        $grant_hash
    ));
    if ($updated === false || $wpdb->last_error !== '' || (int) $readback !== 1) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    return true;
}

/** Renew the database row and both host-only cookies as one lifecycle unit. */
function impactshop_identity_owner_renew_coupled(string $pseudo_id, string $grant_hash): bool
{
    global $wpdb;
    if (!impactshop_identity_grant_storage_ready()) {
        return false;
    }
    $table = impactshop_identity_owner_grant_table();
    $prior = $wpdb->get_row($wpdb->prepare(
        "SELECT last_seen_at, expires_at FROM {$table} WHERE grant_hash = %s AND state = 'active' AND revoked_at IS NULL LIMIT 1",
        $grant_hash
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($prior)) {
        if ($wpdb->last_error !== '') {
            impactshop_identity_mark_safe_disabled();
        }
        return false;
    }
    $now = impactshop_identity_utc_now();
    $now_sql = impactshop_identity_utc_sql($now);
    $expires_at = impactshop_identity_utc_sql($now + (365 * DAY_IN_SECONDS));
    if (!impactshop_identity_queue_binding_cookies($pseudo_id, impactshop_identity_owner_cookie(), $now + (365 * DAY_IN_SECONDS))) {
        return false;
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        impactshop_identity_expire_binding_cookies($pseudo_id, impactshop_identity_owner_cookie());
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $locked = $wpdb->get_row($wpdb->prepare(
        "SELECT last_seen_at, expires_at FROM {$table} WHERE grant_hash = %s AND state = 'active' AND revoked_at IS NULL LIMIT 1 FOR UPDATE",
        $grant_hash
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($locked)) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, impactshop_identity_owner_cookie());
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET last_seen_at = %s, expires_at = %s WHERE grant_hash = %s AND state = 'active' AND revoked_at IS NULL",
        $now_sql,
        $expires_at,
        $grant_hash
    ));
    if ($updated !== 1) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, impactshop_identity_owner_cookie());
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $readback = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE grant_hash = %s AND state = 'active' AND expires_at = %s AND last_seen_at = %s AND revoked_at IS NULL",
        $grant_hash,
        $expires_at,
        $now_sql
    ));
    if ($wpdb->last_error !== '' || (int) $readback !== 1) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, impactshop_identity_owner_cookie());
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if ($wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, impactshop_identity_owner_cookie());
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    return true;
}

function impactshop_identity_owner_authorized(string $pseudo_id): bool
{
    $token = impactshop_identity_owner_cookie();
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token) || !impactshop_identity_grant_storage_ready()) {
        return false;
    }
    global $wpdb;
    $table = impactshop_identity_owner_grant_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT grant_hash, last_seen_at, state, created_at FROM {$table} WHERE grant_hash = %s AND pseudo_hash = %s AND state = 'active' AND revoked_at IS NULL AND expires_at > %s LIMIT 1",
        impactshop_identity_owner_hash($token),
        impactshop_identity_owner_pseudo_hash($pseudo_id),
        impactshop_identity_utc_sql()
    ), ARRAY_A);
    if ($wpdb->last_error !== '') {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if (!is_array($row)) {
        return false;
    }
    if (strtotime((string) ($row['last_seen_at'] ?? '')) < (impactshop_identity_utc_now() - 300)) {
        return impactshop_identity_owner_renew_coupled($pseudo_id, (string) $row['grant_hash']);
    }
    return true;
}

/** Activate a pending grant only after both cookies are present on a later request. */
function impactshop_identity_owner_activate_pending(string $pseudo_id): bool
{
    $token = impactshop_identity_owner_cookie();
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)
        || impactshop_identity_profile_cookie() !== $pseudo_id
        || !impactshop_identity_grant_storage_ready()) {
        return false;
    }
    global $wpdb;
    $table = impactshop_identity_owner_grant_table();
    $hash = impactshop_identity_owner_hash($token);
    $pseudo_hash = impactshop_identity_owner_pseudo_hash($pseudo_id);
    $preflight = $wpdb->get_row($wpdb->prepare(
        "SELECT state, created_at, expires_at FROM {$table} WHERE grant_hash = %s AND pseudo_hash = %s AND revoked_at IS NULL LIMIT 1",
        $hash,
        $pseudo_hash
    ), ARRAY_A);
    if ($wpdb->last_error !== '') {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if (!is_array($preflight) || ($preflight['state'] ?? '') !== 'pending') {
        return is_array($preflight) && ($preflight['state'] ?? '') === 'active';
    }
    $now = impactshop_identity_utc_now();
    $preflight_created = strtotime((string) ($preflight['created_at'] ?? ''));
    $preflight_expires = strtotime((string) ($preflight['expires_at'] ?? ''));
    if (!$preflight_created || !$preflight_expires || $now > ($preflight_created + 600) || $preflight_expires <= $now) {
        return false;
    }
    $cookie_expires = $now + (365 * DAY_IN_SECONDS);
    if (!impactshop_identity_queue_binding_cookies($pseudo_id, $token, $cookie_expires)) {
        return false;
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT grant_hash, state, created_at, expires_at, supersedes_grant_hash FROM {$table} WHERE grant_hash = %s AND pseudo_hash = %s AND revoked_at IS NULL LIMIT 1 FOR UPDATE",
        $hash,
        $pseudo_hash
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($row)) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if (($row['state'] ?? '') !== 'pending') {
        $committed = $wpdb->query('COMMIT') !== false;
        if (!$committed) {
            $wpdb->query('ROLLBACK');
            impactshop_identity_expire_binding_cookies($pseudo_id, $token);
            impactshop_identity_mark_safe_disabled();
        }
        return $committed && ($row['state'] ?? '') === 'active';
    }
    $created = strtotime((string) ($row['created_at'] ?? ''));
    $expires = strtotime((string) ($row['expires_at'] ?? ''));
    if (!$created || !$expires || $now > ($created + 600) || $expires <= $now) {
        $wpdb->query('ROLLBACK');
        // The replacement headers were already queued. Remove them before
        // returning so an expired/raced pending row can never look active in
        // the browser on the next request.
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        return false;
    }
    $superseded = (string) ($row['supersedes_grant_hash'] ?? '');
    if ($superseded !== '') {
        // Lock the superseded grant in the same transaction as the pending
        // activation. Issuance deliberately leaves it active until this
        // point, so a failed binding cannot revoke the user's old device.
        $superseded_row = $wpdb->get_row($wpdb->prepare(
            "SELECT grant_hash, revoked_at FROM {$table} WHERE grant_hash = %s LIMIT 1 FOR UPDATE",
            $superseded
        ), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($superseded_row) || !empty($superseded_row['revoked_at'])) {
            $wpdb->query('ROLLBACK');
            impactshop_identity_mark_safe_disabled();
            impactshop_identity_expire_binding_cookies($pseudo_id, $token);
            return false;
        }
        $revoked = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s, state = 'pending' WHERE grant_hash = %s AND revoked_at IS NULL",
            impactshop_identity_utc_sql($now),
            $superseded
        ));
        if ($revoked !== 1 || $wpdb->last_error !== '') {
            $wpdb->query('ROLLBACK');
            impactshop_identity_mark_safe_disabled();
            impactshop_identity_expire_binding_cookies($pseudo_id, $token);
            return false;
        }
    }
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET state = 'active', activated_at = %s, last_seen_at = %s, expires_at = %s WHERE grant_hash = %s AND state = 'pending' AND revoked_at IS NULL",
        impactshop_identity_utc_sql($now),
        impactshop_identity_utc_sql($now),
        impactshop_identity_utc_sql($cookie_expires),
        $hash
    ));
    if ($updated !== 1) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $new_readback = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE grant_hash = %s AND state = 'active' AND activated_at = %s AND last_seen_at = %s AND expires_at = %s AND revoked_at IS NULL",
        $hash,
        impactshop_identity_utc_sql($now),
        impactshop_identity_utc_sql($now),
        impactshop_identity_utc_sql($cookie_expires)
    ));
    $prior_readback = $superseded === '' ? 1 : $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE grant_hash = %s AND revoked_at IS NOT NULL AND state = 'pending'",
        $superseded
    ));
    if ($wpdb->last_error !== '' || (int) $new_readback !== 1 || (int) $prior_readback !== 1) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if ($wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_expire_binding_cookies($pseudo_id, $token);
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    return true;
}

function impactshop_identity_owner_issue(string $pseudo_id, ?string $supersedes_grant_hash = null): bool
{
    if (!is_ssl() || !impactshop_identity_grant_storage_ready()) {
        return false;
    }
    global $wpdb;
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if ($supersedes_grant_hash === null) {
        $supersedes_grant_hash = impactshop_identity_owner_current_valid_grant_hash();
    }
    $now = impactshop_identity_utc_sql();
    $expires = impactshop_identity_utc_sql(impactshop_identity_utc_now() + 600);
    $table = impactshop_identity_owner_grant_table();
    if ($wpdb->query('START TRANSACTION') === false) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $inserted = $wpdb->insert(impactshop_identity_owner_grant_table(), [
        'grant_hash' => impactshop_identity_owner_hash($token),
        'pseudo_hash' => impactshop_identity_owner_pseudo_hash($pseudo_id),
        'created_at' => $now,
        'last_seen_at' => $now,
        'expires_at' => $expires,
        'state' => 'pending',
        'activated_at' => null,
        'supersedes_grant_hash' => $supersedes_grant_hash,
        'version' => 2,
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']);
    if ($inserted === false) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $readback = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . impactshop_identity_owner_grant_table() . " WHERE grant_hash = %s AND pseudo_hash = %s AND state = 'pending'",
        impactshop_identity_owner_hash($token),
        impactshop_identity_owner_pseudo_hash($pseudo_id)
    ));
    if ($wpdb->last_error !== '' || (int) $readback !== 1) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if ($wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    // Persist the pending token only in request memory. The owner cookie is
    // emitted by the caller after the pseudo cookie succeeds, so a partial
    // browser binding cannot become usable.
    $GLOBALS['impactshop_pending_owner_token'] = $token;
    $GLOBALS['impactshop_owner_issued_this_request'] = $pseudo_id;
    return true;
}

function impactshop_identity_owner_set_pending_cookie(): bool
{
    $token = isset($GLOBALS['impactshop_pending_owner_token']) ? (string) $GLOBALS['impactshop_pending_owner_token'] : '';
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return false;
    }
    $cookie_set = setcookie('__Host-impactshop_owner', $token, [
        'expires' => impactshop_identity_utc_now() + 600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!$cookie_set) {
        impactshop_identity_grant_compensate_revoke(impactshop_identity_owner_hash($token));
        return false;
    }
    unset($GLOBALS['impactshop_pending_owner_token']);
    return true;
}

function impactshop_identity_owner_compensate_pending(): void
{
    $token = isset($GLOBALS['impactshop_pending_owner_token']) ? (string) $GLOBALS['impactshop_pending_owner_token'] : '';
    if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token)) {
        impactshop_identity_grant_compensate_revoke(impactshop_identity_owner_hash($token));
    }
    unset($GLOBALS['impactshop_pending_owner_token']);
    unset($GLOBALS['impactshop_owner_issued_this_request']);
}

function impactshop_identity_owner_revoke_current(): bool
{
    $token = impactshop_identity_owner_cookie();
    if ($token === '') {
        return true;
    }
    global $wpdb;
    $table = impactshop_identity_owner_grant_table();
    $hash = impactshop_identity_owner_hash($token);
    $existing = $wpdb->get_row($wpdb->prepare("SELECT revoked_at FROM {$table} WHERE grant_hash = %s LIMIT 1", $hash), ARRAY_A);
    if ($wpdb->last_error !== '') {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    if (!is_array($existing) || !empty($existing['revoked_at'])) {
        return true;
    }
    $updated = $wpdb->update($table, ['revoked_at' => impactshop_identity_utc_sql(), 'state' => 'pending'], ['grant_hash' => $hash, 'revoked_at' => null], ['%s', '%s'], ['%s', '%s']);
    if ($updated === false || $wpdb->last_error !== '') {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    $readback = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE grant_hash = %s AND revoked_at IS NOT NULL", $hash));
    if ($wpdb->last_error !== '' || (int) $readback !== 1) {
        impactshop_identity_mark_safe_disabled();
        return false;
    }
    return true;
}

/**
 * Resolve the compatibility state of the current device/profile binding.
 *
 * This is deliberately read-only. A missing or invalid grant never causes a
 * new grant to be minted here; only the existing bootstrap path may issue one.
 *
 * @param string $pseudo_id Current pseudo ID.
 * @return string One of binding_pending, active, legacy_read_only,
 *                invalid_or_revoked or unavailable.
 */
function impactshop_identity_profile_state(string $pseudo_id): string
{
    if ($pseudo_id === '' || !impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return 'unavailable';
    }

    if ((function_exists('impactshop_owner_policy_safe_disabled') && impactshop_owner_policy_safe_disabled())
        || !impactshop_identity_grant_storage_ready()) {
        return 'unavailable';
    }

    if (($GLOBALS['impactshop_owner_issued_this_request'] ?? '') === $pseudo_id) {
        return 'binding_pending';
    }

    $owner_token = impactshop_identity_owner_cookie();
    if ($owner_token !== '' && preg_match('/^[a-f0-9]{64}$/i', $owner_token)) {
        // A freshly issued grant is intentionally pending for this request.
        // Activation requires the owner and pseudo cookies on a subsequent GET.
        if (impactshop_identity_owner_activate_pending($pseudo_id)) {
            return 'active';
        }
        if (impactshop_identity_owner_authorized($pseudo_id)) {
            return 'active';
        }
    }

    global $wpdb;
    $table = impactshop_identity_owner_grant_table();
    $grant_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT revoked_at, expires_at, state, created_at FROM {$table} WHERE pseudo_hash = %s ORDER BY created_at DESC LIMIT 5",
        impactshop_identity_owner_pseudo_hash($pseudo_id)
    ), ARRAY_A);
    if ($wpdb->last_error !== '') {
        return 'unavailable';
    }

    $has_expired_or_revoked = false;
    $has_pending = false;
    if (is_array($grant_rows)) {
        foreach ($grant_rows as $grant_row) {
            if (!is_array($grant_row)) {
                continue;
            }
            if (($grant_row['state'] ?? '') === 'pending'
                && empty($grant_row['revoked_at'])
                && strtotime((string) ($grant_row['created_at'] ?? '')) !== false
                && impactshop_identity_utc_now() <= strtotime((string) $grant_row['created_at']) + 600
                && strtotime((string) ($grant_row['expires_at'] ?? '')) > impactshop_identity_utc_now()) {
                $has_pending = true;
            }
            if (!empty($grant_row['revoked_at']) || (!empty($grant_row['expires_at']) && strtotime((string) $grant_row['expires_at']) <= impactshop_identity_utc_now())) {
                $has_expired_or_revoked = true;
                break;
            }
        }
    }

    if ($has_pending) {
        return 'binding_pending';
    }
    return $has_expired_or_revoked ? 'invalid_or_revoked' : 'legacy_read_only';
}

/**
 * Read the current pseudo's available votes without creating or changing a
 * row. The value is intentionally scoped to the current cookie only.
 *
 * @param string $pseudo_id Current pseudo ID.
 * @return int
 */
function impactshop_identity_profile_votes_available(string $pseudo_id): int
{
    if ($pseudo_id === '' || !impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return 0;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_ads_user_votes';
    $raw = $wpdb->get_var($wpdb->prepare(
        "SELECT available_votes FROM {$table} WHERE pseudo_id = %s LIMIT 1",
        $pseudo_id
    ));
    if ($wpdb->last_error !== '' || $raw === null || !is_scalar($raw)) {
        return 0;
    }

    $raw = trim((string) $raw);
    if (!preg_match('/^-?\d+$/', $raw)) {
        return 0;
    }

    return max(0, (int) $raw);
}

/**
 * Apply the profile API's private cache contract.
 *
 * @param WP_REST_Response $response REST response.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_response_headers(WP_REST_Response $response): WP_REST_Response
{
    $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    $response->header('Pragma', 'no-cache');
    $response->header('Vary', 'Cookie');
    return $response;
}

/**
 * Enforce the profile cache contract even when another callback changes the
 * response after the profile handler has returned.
 *
 * @param mixed $response REST response.
 * @param mixed $server REST server.
 * @param mixed $request REST request.
 * @return mixed
 */
function impactshop_identity_profile_rest_cache_headers($response, $server, $request)
{
    if (!$response instanceof WP_REST_Response || !is_object($request) || !method_exists($request, 'get_route')) {
        return $response;
    }
    if ((string) $request->get_route() !== '/impact/v1/identity/profile') {
        return $response;
    }
    return impactshop_identity_profile_response_headers($response);
}

function impactshop_identity_allowed_message_tags(): array
{
    return [
        'a' => [
            'href' => true,
            'target' => true,
            'rel' => true,
        ],
        'br' => true,
        'strong' => true,
        'em' => true,
    ];
}

function impactshop_identity_sanitize_broadcast_message($value): string
{
    $value = is_string($value) ? $value : '';
    $sanitized = wp_kses($value, impactshop_identity_allowed_message_tags());
    $plain = wp_strip_all_tags($sanitized);
    if (mb_strlen($plain) > 300) {
        $plain = mb_substr($plain, 0, 300);
        return esc_html($plain);
    }
    return $sanitized;
}

function impactshop_identity_render_broadcast_field(): void
{
    $value = (string) get_option('impactshop_identity_broadcast_message', '');
    $value = wp_kses($value, impactshop_identity_allowed_message_tags());
    echo '<textarea name="impactshop_identity_broadcast_message" rows="4" cols="60" maxlength="300" style="width:100%;max-width:640px;">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Max 300 karakter. Engedélyezett: <code>&lt;a&gt;</code>, <code>&lt;br&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>.</p>';
}

/**
 * Resolve only the non-secret profile identity for rendering.
 *
 * @return array{pseudo_id:string,nickname:?string,access_code_state:string,votes_available:int|null,identity_state:string}
 */
function impactshop_identity_profile_resolve(): array
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        if (!empty($GLOBALS['impactshop_identity_bootstrap_block_legacy'])) {
            return [
                'pseudo_id'         => '',
                'nickname'          => null,
                'access_code_state' => 'missing',
                'votes_available'   => null,
                'identity_state'    => 'unavailable',
            ];
        }
        $pseudo_id = impactshop_identity_profile_generate_pseudo_id();
        $owner_issued = impactshop_identity_owner_issue($pseudo_id);
        $pseudo_cookie_set = $owner_issued && impactshop_identity_profile_set_cookie($pseudo_id);
        $owner_cookie_set = $pseudo_cookie_set && impactshop_identity_owner_set_pending_cookie();
        if (!$pseudo_cookie_set || !$owner_cookie_set) {
            if ($owner_issued) {
                impactshop_identity_owner_compensate_pending();
            }
            impactshop_identity_restore_or_expire_pseudo_cookie('', $pseudo_id);
            $pseudo_id = '';
        }
    }

    if ($pseudo_id === '') {
        return [
            'pseudo_id'         => '',
            'nickname'          => null,
            'access_code_state' => 'missing',
            'votes_available'   => null,
            'identity_state'    => 'unavailable',
        ];
    }

    $identity_state = impactshop_identity_profile_state($pseudo_id);

    $pseudo_visible = in_array($identity_state, ['active', 'legacy_read_only', 'invalid_or_revoked'], true);
    $profile_visible = in_array($identity_state, ['active', 'legacy_read_only'], true);
    return [
        'pseudo_id'         => $pseudo_visible ? $pseudo_id : '',
        'nickname'          => $profile_visible ? impactshop_identity_profile_load($pseudo_id) : null,
        'access_code_state' => $profile_visible && impactshop_identity_profile_has_access_code($pseudo_id) ? 'available' : 'missing',
        'votes_available'   => $identity_state === 'active' ? impactshop_identity_profile_votes_available($pseudo_id) : null,
        'identity_state'    => $identity_state,
    ];
}

/**
 * Resolve current URL for post-save redirect.
 *
 * @return string
 */
function impactshop_identity_current_url(): string
{
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url('/'), PHP_URL_HOST);
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $uri = wp_kses_bad_protocol($uri, ['http', 'https']);
    $url = $scheme . $host . $uri;
    return esc_url_raw($url);
}

/**
 * Handle password manager save form POST and return user to source page.
 *
 * @return void
 */
function impactshop_identity_handle_password_manager_save(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    if (empty($_POST['impactshop_identity_save_intent'])) {
        return;
    }

    $return_url = '';
    if (!empty($_POST['impactshop_identity_return'])) {
        $return_url = esc_url_raw((string) wp_unslash($_POST['impactshop_identity_return']));
    }
    if ($return_url === '') {
        $return_url = wp_get_referer() ?: home_url('/');
    }
    $redirect = wp_validate_redirect($return_url, home_url('/'));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Shortcode UI for pseudo ID and recovery tools.
 *
 * @return string
 */
function impactshop_identity_panel_shortcode(): string
{
    impactshop_identity_panel_enqueue_assets();

    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-identity-panel-' . wp_generate_password(6, false, false);
    $current_url = impactshop_identity_current_url();
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $nickname_value = (string) ($profile['nickname'] ?? '');
    $nickname_label = esc_html($nickname_value !== '' ? $nickname_value : 'Nincs becenév');

    $html = '<div class="impactshop-identity-panel" id="' . esc_attr($panel_id) . '" data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div id="impactshop-account-top"></div>';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<div class="impactshop-identity-header">';
    $html .= '<h3>Profilod</h3>';
    $html .= '<p class="impactshop-identity-summary"><span class="impactshop-identity-summary__label">Becenév</span> <strong data-role="nickname-display">' . $nickname_label . '</strong></p>';
    $html .= '<p class="impactshop-identity-hint" data-role="greeting"></p>';
    $html .= '<p class="impactshop-identity-hint" data-role="account-message"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-push" data-role="push-section" hidden>';
    $html .= '<h4>Értesítések</h4>';
    $html .= '<p class="impactshop-identity-hint">A profil üzeneteket pushban is elküldjük (ha engedélyezed).</p>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="push-toggle">Értesítések bekapcsolása</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="push-status"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block">';
    $html .= '<h4>Azonosítód</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">' . $pseudo_id . '</code>';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás</button>';
    $html .= '<button type="button" data-role="refresh-profile">Frissítés</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Ezzel kapcsoljuk össze az adományt és a jutalmakat.</p>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="generate-code">Belépési kód megjelenítése</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">A belépési kód csak külön, egyszeri biztonságos oldalon jelenik meg. Mentsd el a jelszókezelődbe.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-save">';
    $html .= '<label class="impactshop-identity-save__label">Mentés jelszókezelőbe (opcionális)</label>';
    $html .= '<form class="impactshop-identity-row" data-role="save-form" method="post" action="' . esc_url(home_url('/')) . '" autocomplete="on">';
    $html .= '<input type="hidden" name="impactshop_identity_save_intent" value="1" />';
    $html .= '<input type="hidden" name="impactshop_identity_return" data-role="save-return" value="' . esc_attr($current_url) . '" />';
    $html .= '<input type="text" name="username" data-role="save-username" autocomplete="username" placeholder="Azonosító" value="' . $pseudo_id . '" />';
    $html .= '<input type="password" name="password" data-role="save-password" autocomplete="current-password" placeholder="Belépési kód" value="" />';
    $html .= '<button type="submit" data-role="save-password-manager">Mentés</button>';
    $html .= '</form>';
    $html .= '<p class="impactshop-identity-hint">A böngésző felajánlhatja a mentést.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block">';
    $html .= '<label>Becenév (opcionális)</label>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" data-role="nickname-input" maxlength="32" placeholder="pl. Anna" />';
    $html .= '<button type="button" data-role="save-nickname">Mentés</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="nickname-status"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-points" data-role="points-section" hidden>';
    $html .= '<h4>Szinted és pontjaid</h4>';
    $html .= '<div class="impactshop-identity-row impactshop-identity-points-row">';
    $html .= '<span class="impactshop-identity-badge" data-role="points-badge">🌱</span>';
    $html .= '<div class="impactshop-identity-points-meta">';
    $html .= '<div class="impactshop-identity-level" data-role="points-level">Basic</div>';
    $html .= '<div class="impactshop-identity-total" data-role="points-total">0 pont</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar" data-role="points-progress-bar"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress-text" data-role="points-progress-text"></div>';
    $html .= '<button type="button" class="impactshop-identity-info-trigger" data-role="points-info-trigger">Miért éri meg?</button>';
    $html .= '<div class="impactshop-identity-info" data-role="points-info" hidden>';
    $html .= '<p>A magasabb szint nagyobb adománybónuszt és előnyöket ad. A pontküszöbök tájékoztató jellegűek, a szinteloszlás percentilis alapján is alakul.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-benefits" data-role="points-benefits"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-votes" data-role="votes-summary">';
    $html .= '<h4>Elkölthető szavazatok</h4>';
    $html .= '<strong data-role="votes-available">' . (isset($profile['votes_available']) && $profile['votes_available'] !== null ? esc_html((string) $profile['votes_available']) : 'Belépés szükséges') . '</strong>';
    $html .= '<p class="impactshop-identity-hint" data-role="votes-state"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-history">';
    $html .= '<h4>Legutóbbi aktivitás</h4>';
    $html .= '<ul class="impactshop-identity-list" data-role="points-history"></ul>';
    $html .= '<p class="impactshop-identity-hint" data-role="points-history-empty">Még nincs aktivitás.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-lastngo" data-role="last-ngo"></div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-vacation">';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="vacation-status">Szabadság státusz betöltése…</span>';
    $html .= '<button type="button" data-role="vacation-toggle">Szabadság mód</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-referral">';
    $html .= '<h4>Ajánlás</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code data-role="referral-code">—</code>';
    $html .= '<button type="button" data-role="referral-copy">Másolás</button>';
    $html .= '<button type="button" data-role="referral-info-trigger">Infó</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-hint" data-role="referral-link"></div>';
    $html .= '<div class="impactshop-identity-info" data-role="referral-info" hidden>';
    $html .= '<p>Oszd meg a kódot, hogy jutalmat kapjatok.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-badges" data-role="badges-section">';
    $html .= '<h4>Legacy Wall</h4>';
    $html .= '<div class="impactshop-identity-badge-list" data-role="legacy-badges"></div>';
    $html .= '<p class="impactshop-identity-hint" data-role="badges-empty">Még nincs jelvényed.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-herowall" data-role="herowall-summary">';
    $html .= '<h4>Legacy Pool</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="herowall-tier">—</span>';
    $html .= '<span data-role="herowall-points">—</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-restore">';
    $html .= '<h4 id="impactshop-restore-title">Belépés meglévő fiókba <button type="button" class="impactshop-identity-info-trigger" aria-describedby="impactshop-signin-help">i</button></h4>';
    $html .= '<p id="impactshop-signin-help" class="impactshop-identity-hint">A fiókot automatikusan létrehoztuk; az adataidat a pseudo ID és a belépési kód kapcsolja össze. Ha másik eszközön már létrehoztad, itt léphetsz be. Az elvesztett belépési kód másik ellenőrzött azonosító nélkül nem állítható helyre.</p>';
    $html .= '<label class="impactshop-identity-restore__label">Azonosító</label>';
    $html .= '<input type="text" name="impactshop_restore_pseudo" data-role="restore-pseudo" autocomplete="username" placeholder="Azonosító" />';
    $html .= '<label class="impactshop-identity-restore__label">Belépési kód</label>';
    $html .= '<input type="password" name="impactshop_restore_recovery" data-role="restore-recovery" autocomplete="current-password" placeholder="Belépési kód" />';
    $html .= '<button type="button" data-role="restore-submit">Belépés</button>';
    $html .= '<p class="impactshop-identity-hint" data-role="restore-status"></p>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-status" data-role="status"></p>';
    $html .= '</div></div>';

    return $html;
}

/**
 * Shortcode for ID-only display.
 *
 * @return string
 */
function impactshop_identity_id_shortcode(): string
{
    impactshop_identity_panel_enqueue_assets();

    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-identity-id-' . wp_generate_password(6, false, false);
    $current_url = impactshop_identity_current_url();
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $nickname_value = (string) ($profile['nickname'] ?? '');
    $nickname_label = esc_html($nickname_value !== '' ? $nickname_value : 'Nincs becenév');
        $broadcast = (string) get_option('impactshop_identity_broadcast_message', '');
    $broadcast = wp_kses($broadcast, impactshop_identity_allowed_message_tags());
    $restore_url = apply_filters('impactshop_identity_restore_url', site_url('/profil') . '#impactshop-restore-title');
    $html = '<div class="impactshop-identity-panel impactshop-identity-panel--compact" id="' . esc_attr($panel_id) . '" data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<div class="impactshop-identity-title">';
    $html .= '<h3>Fiókom</h3>';
    $html .= '<button type="button" class="impactshop-identity-info-trigger" aria-label="Fiókom információ">i</button>';
    $html .= '<div class="impactshop-identity-info-bubble" role="tooltip">';
    $html .= '<p><strong>Fontos:</strong> a fiókodat automatikusan létrehoztuk.</p>';
    $html .= '<ul>';
    $html .= '<li>Ha meg akarod tartani, kattints a <strong>Mentés</strong> gombra, hogy a jelszavaid közé kerüljön.</li>';
    $html .= '<li>Ha máshova mentenéd, használd a <strong>Másolás</strong> gombot.</li>';
    $html .= '<li>Ha később másik eszközről lépsz be, használd a <strong>Belépés meglévő fiókba</strong> pontot.</li>';
    $html .= '</ul>';
    $html .= '<p>Az elvesztett belépési kód másik ellenőrzött azonosító nélkül nem állítható helyre.</p>';
    $html .= '<p>Csak a fiókodban tudod összegyűjteni az előnyöket és a jelvényeket, ezért érdemes elmentened.</p>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-summary"><span class="impactshop-identity-summary__label">Becenév</span> <strong data-role="nickname-display">' . $nickname_label . '</strong></p>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="greeting"></p>';
    $html .= '<form class="impactshop-identity-row" data-role="save-form" method="post" action="' . esc_url(home_url('/')) . '" autocomplete="on">';
    $html .= '<input type="hidden" name="impactshop_identity_save_intent" value="1" />';
    $html .= '<input type="hidden" name="impactshop_identity_return" data-role="save-return" value="' . esc_attr($current_url) . '" />';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">' . $pseudo_id . '</code>';
    $html .= '<input type="text" class="impactshop-identity-save-ghost" name="username" data-role="save-username" autocomplete="username" value="' . $pseudo_id . '" />';
    $html .= '<input type="password" class="impactshop-identity-save-ghost" name="password" data-role="save-password" autocomplete="current-password" value="" />';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás</button>';
    $html .= '<button type="submit" data-role="save-password-manager">Mentés</button>';
    $html .= '</form>';
    $html .= '<span class="impactshop-identity-hidden" data-role="recovery-display"></span>';
    $html .= '<div class="impactshop-identity-actions">';
    $profile_action_url = impactshop_identity_canonical_profile_url();
    $html .= '<a class="impactshop-identity-link" href="' . esc_url($profile_action_url) . '" target="_blank" rel="noopener">A fiókom kezelése</a>';
    $html .= '<a class="impactshop-identity-link impactshop-identity-link--muted" data-role="identity-restore-link" href="' . esc_url($restore_url) . '" target="_blank" rel="noopener">Belépés meglévő fiókba</a>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-compact" data-role="points-compact" hidden>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="points-compact-badge">🌱</span>';
    $html .= '<strong data-role="points-compact-level">Basic</strong>';
    $html .= '<span data-role="points-compact-total">0 pont</span>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar" data-role="points-compact-bar"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress-text" data-role="points-compact-text"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-compact impactshop-identity-votes" data-role="votes-summary">';
    $html .= '<span class="impactshop-identity-summary__label">Elkölthető szavazatok</span> <strong data-role="votes-available">' . (isset($profile['votes_available']) && $profile['votes_available'] !== null ? esc_html((string) $profile['votes_available']) : 'Belépés szükséges') . '</strong>';
    $html .= '<span class="impactshop-identity-hint" data-role="votes-state"></span>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-message" data-role="broadcast-message" hidden>';
    $html .= '<strong>Üzenet</strong>';
    $html .= '<div class="impactshop-identity-message-body">' . $broadcast . '</div>';
    $html .= '</div>';
    $html .= '</div></div>';

    return $html;
}

/**
 * Register assets for the identity panel.
 *
 * @return void
 */
function impactshop_identity_panel_register_assets(): void
{
    $php_asset_version = @filemtime(__FILE__);
    $panel_script_version = max((int) (@filemtime(__DIR__ . '/impactshop-identity-panel.js') ?: 0), (int) ($php_asset_version ?: 0));
    $intl_overlay_version = max((int) (@filemtime(__DIR__ . '/impactshop-identity-panel-intl-overlay.js') ?: 0), (int) ($php_asset_version ?: 0));

    if (!$panel_script_version) {
        $panel_script_version = '1.0.6';
    }
    if (!$intl_overlay_version) {
        $intl_overlay_version = '1.0.2';
    }

    wp_register_style('impactshop-identity-panel', false);
    wp_register_script(
        'impactshop-identity-panel',
        plugins_url('impactshop-identity-panel.js', __FILE__),
        [],
        (string) $panel_script_version,
        true
    );
    wp_register_script(
        'impactshop-identity-panel-intl-overlay',
        plugins_url('impactshop-identity-panel-intl-overlay.js', __FILE__),
        ['impactshop-identity-panel'],
        (string) $intl_overlay_version,
        true
    );

    $css = <<<CSS
.impactshop-identity-panel { max-width: 720px; margin: 24px auto; font-family: inherit; color: #0f172a; }
.impactshop-identity-panel--compact { max-width: 460px; }
.impactshop-identity-card { border-radius: 18px; padding: 22px; background: rgba(255,255,255,0.7); border: 1px solid rgba(148,163,184,0.35); box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12); backdrop-filter: blur(16px); position: relative; overflow: hidden; }
.impactshop-identity-card::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at top left, rgba(59,130,246,0.18), transparent 55%), radial-gradient(circle at bottom right, rgba(14,165,233,0.14), transparent 55%); pointer-events: none; }
.impactshop-identity-card h3, .impactshop-identity-card h4 { margin: 0 0 10px; font-weight: 700; }
.impactshop-identity-summary { margin: 0 0 8px; color: #1a1a2e; line-height: 1.5; }
.impactshop-identity-summary__label { color: #475569; font-size: 13px; font-weight: 600; margin-right: 4px; }
.impactshop-identity-votes { padding: 12px 14px; border-radius: 14px; background: #f0fdfa; border: 1px solid rgba(13,148,136,.24); }
.impactshop-identity-votes h4 { color: #0f766e; }
.impactshop-identity-votes [data-role=votes-available] { color: #1a1a2e; font-size: 1.15rem; }
.impactshop-identity-title { display: flex; align-items: center; gap: 8px; position: relative; }
.impactshop-identity-info-trigger { width: 22px; height: 22px; border-radius: 999px; border: 0; background: #e2e8f0; color: #0f172a; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.impactshop-identity-info-trigger:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
.impactshop-identity-info-bubble { position: absolute; top: calc(100% + 6px); left: 0; background: #0f172a; color: #fff; padding: 12px 14px; border-radius: 12px; font-size: 13px; width: min(360px, 90vw); box-shadow: 0 14px 30px rgba(15, 23, 42, 0.35); opacity: 0; transform: translateY(6px); transition: opacity .2s ease, transform .2s ease; pointer-events: none; z-index: 5; }
.impactshop-identity-info-bubble p { margin: 0 0 8px; }
.impactshop-identity-info-bubble ul { margin: 0 0 8px; padding-left: 18px; }
.impactshop-identity-info-bubble li { margin: 0 0 6px; }
.impactshop-identity-info-bubble strong { color: #f8fafc; }
.impactshop-identity-title:hover .impactshop-identity-info-bubble,
.impactshop-identity-title:focus-within .impactshop-identity-info-bubble { opacity: 1; transform: translateY(0); pointer-events: auto; }
.impactshop-identity-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.impactshop-identity-row input { flex: 1; }
.impactshop-identity-card label { display: block; margin-top: 14px; font-weight: 600; }
.impactshop-identity-card input, .impactshop-identity-card select { width: 100%; padding: 12px; border: 1px solid rgba(148,163,184,0.6); border-radius: 12px; background: rgba(255,255,255,0.8); }
.impactshop-identity-card button { padding: 11px 16px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.2); background: #0f172a; color: #fff; cursor: pointer; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18); }
.impactshop-identity-card button:disabled { cursor: not-allowed; opacity: .6; }
.impactshop-identity-card button:hover { background: #1e293b; }
.impactshop-identity-card code { background: rgba(15,23,42,0.06); padding: 10px 12px; border-radius: 10px; font-weight: 700; letter-spacing: 0.02em; }
.impactshop-identity-actions { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
.impactshop-identity-link { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.2); background: #0f172a; color: #fff; text-decoration: none; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18); }
.impactshop-identity-link:hover { background: #1e293b; }
.impactshop-identity-link--muted { background: rgba(15,23,42,0.08); color: #0f172a; box-shadow: none; }
.impactshop-identity-link--muted:hover { background: rgba(15,23,42,0.14); }
.impactshop-identity-hidden { display: none; }
.impactshop-identity-save-ghost { position: absolute !important; left: -9999px !important; top: -9999px !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important; }
.impactshop-identity-value--recovery { background: rgba(14,165,233,0.15); color: #0e7490; }
.impactshop-identity-card hr { margin: 18px 0; border: none; border-top: 1px solid rgba(148,163,184,0.4); }
.impactshop-identity-status { margin-top: 12px; color: #0f172a; min-height: 20px; }
.impactshop-identity-hint { color: #475569; margin-top: 8px; line-height: 1.5; }
.impactshop-identity-save { margin-top: 14px; padding: 12px; border: 1px dashed rgba(148,163,184,0.5); border-radius: 14px; background: rgba(248,250,252,0.75); }
.impactshop-identity-save__label { display: block; font-weight: 600; margin-bottom: 6px; }
.impactshop-identity-restore { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(148,163,184,0.35); display: grid; gap: 10px; }
.impactshop-identity-restore h4 { margin: 0; font-size: 16px; font-weight: 700; }
.impactshop-identity-restore__label { display: block; font-weight: 600; }
.impactshop-identity-block { margin-top: 18px; }
.impactshop-identity-header { margin-bottom: 12px; }
.impactshop-identity-progress { width: 100%; height: 8px; background: rgba(148,163,184,0.3); border-radius: 999px; overflow: hidden; margin: 10px 0; }
.impactshop-identity-progress-bar { height: 100%; width: 0; background: linear-gradient(90deg, #0ea5e9, #22c55e); }
.impactshop-identity-benefits span { display: block; margin-top: 6px; font-size: 13px; color: #334155; }
.impactshop-identity-list { list-style: none; padding: 0; margin: 0; }
.impactshop-identity-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(148,163,184,0.2); font-size: 13px; }
.impactshop-identity-badge-list { display: flex; flex-wrap: wrap; gap: 8px; }
.impactshop-identity-badge-list span { background: transparent; padding: 6px 10px; border-radius: 999px; font-size: 12px; }
.impactshop-identity-info { margin-top: 8px; padding: 10px; border-radius: 12px; background: rgba(15,23,42,0.06); font-size: 12px; }
.impactshop-identity-info-trigger { margin-top: 6px; background: #111827; }
.impactshop-identity-compact { margin-top: 12px; }
.impactshop-identity-message { padding: 12px; border-radius: 14px; background: rgba(15,23,42,0.06); }
.impactshop-identity-message strong { display: block; margin-bottom: 6px; font-size: 13px; }
.impactshop-identity-message-body { font-size: 13px; color: #334155; line-height: 1.5; }
.impactshop-identity-push .impactshop-identity-row { margin-top: 6px; }
@media (max-width: 640px) {
  .impactshop-identity-row { flex-direction: column; align-items: stretch; }
  .impactshop-identity-card button { width: 100%; }
  .impactshop-identity-card { padding: 18px; border-radius: 14px; }
}
@media (prefers-contrast: more) {
  .impactshop-identity-card { border-color: #1a1a2e; background: #fff; }
  .impactshop-identity-hint, .impactshop-identity-summary__label { color: #1a1a2e; }
  .impactshop-identity-votes { border-color: #0f766e; }
}
CSS;
    wp_add_inline_style('impactshop-identity-panel', $css);

    wp_localize_script('impactshop-identity-panel', 'impactshopIdentityPanel', [
        'restBase'  => esc_url_raw(rest_url('impact/v1')),
        'restNonce' => wp_create_nonce('wp_rest'),
    ]);
}

/**
 * Enqueue assets for the identity panel.
 *
 * @return void
 */
function impactshop_identity_panel_enqueue_assets(): void
{
    static $enqueued = false;
    if ($enqueued) {
        return;
    }

    wp_enqueue_style('impactshop-identity-panel');
    wp_enqueue_script('impactshop-identity-panel');
    wp_enqueue_script('impactshop-identity-panel-intl-overlay');
    $enqueued = true;
}

/**
 * Get the current profile for the pseudo ID cookie.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_profile_get(): WP_REST_Response
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        if (!empty($GLOBALS['impactshop_identity_bootstrap_block_legacy'])) {
            $response = new WP_REST_Response([
                'pseudo_id'         => '',
                'nickname'          => null,
                'access_code_state' => 'missing',
                'votes_available'   => null,
                'identity_state'    => 'unavailable',
            ], 200);
            return impactshop_identity_profile_response_headers($response);
        }
        $pseudo_id = impactshop_identity_profile_generate_pseudo_id();
        $owner_issued = impactshop_identity_owner_issue($pseudo_id);
        $pseudo_cookie_set = $owner_issued && impactshop_identity_profile_set_cookie($pseudo_id);
        $owner_cookie_set = $pseudo_cookie_set && impactshop_identity_owner_set_pending_cookie();
        if (!$pseudo_cookie_set || !$owner_cookie_set) {
            if ($owner_issued) {
                impactshop_identity_owner_compensate_pending();
            }
            impactshop_identity_restore_or_expire_pseudo_cookie('', $pseudo_id);
            $response = new WP_REST_Response([
                'pseudo_id'         => '',
                'nickname'          => null,
                'access_code_state' => 'missing',
                'votes_available'   => null,
                'identity_state'    => 'unavailable',
            ], 200);
            return impactshop_identity_profile_response_headers($response);
        }
    }

    $identity_state = impactshop_identity_profile_state($pseudo_id);
    $pseudo_visible = in_array($identity_state, ['active', 'legacy_read_only', 'invalid_or_revoked'], true);
    $profile_visible = in_array($identity_state, ['active', 'legacy_read_only'], true);
    $response = new WP_REST_Response(
        [
            'pseudo_id'         => $pseudo_visible ? $pseudo_id : '',
            'nickname'          => $profile_visible ? impactshop_identity_profile_load($pseudo_id) : null,
            'access_code_state' => $profile_visible && impactshop_identity_profile_has_access_code($pseudo_id) ? 'available' : 'missing',
            'votes_available'   => $identity_state === 'active' ? impactshop_identity_profile_votes_available($pseudo_id) : null,
            'identity_state'    => $identity_state,
        ],
        200
    );
    return impactshop_identity_profile_response_headers($response);
}

/**
 * Update nickname for current pseudo ID.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_update(WP_REST_Request $request): WP_REST_Response
{
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['message' => 'A művelet lejárt. Töltsd újra az oldalt.'], 403);
    }
    if (!impactshop_identity_request_same_origin()) {
        return new WP_REST_Response(['message' => 'A kérés forrása nem engedélyezett.'], 403);
    }
    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? strtolower((string)$params['pseudo_id']) : '';
    $nickname = isset($params['nickname']) ? (string)$params['nickname'] : '';

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $cookie_pseudo = strtolower(impactshop_identity_profile_cookie());
    if ($cookie_pseudo === '' || $cookie_pseudo !== $pseudo_id) {
        return new WP_REST_Response(['message' => 'Azonosító nem egyezik a böngésző cookie-val.'], 403);
    }
    if (!impactshop_identity_owner_authorized($pseudo_id)) {
        return new WP_REST_Response(['message' => 'A profil ezen az eszközön még nincs biztonságosan összekapcsolva.'], 403);
    }

    $nickname = sanitize_text_field($nickname);
    $nickname = trim($nickname);
    if ($nickname !== '' && !impactshop_identity_profile_valid_nickname($nickname)) {
        return new WP_REST_Response(['message' => 'Érvénytelen becenév.'], 400);
    }

    impactshop_identity_profile_store($pseudo_id, $nickname);
    if (function_exists('impact_update_herowall')) {
        impact_update_herowall($pseudo_id);
    }
    do_action('impactshop_identity_profile_updated', $pseudo_id, $nickname);

    $response = new WP_REST_Response(['status' => 'ok', 'nickname' => $nickname], 200);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    $response->header('Vary', 'Cookie');
    return $response;
}

/**
 * Get total donation amount for current pseudo ID.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_profile_total(): WP_REST_Response
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        $response = new WP_REST_Response(['total_huf' => 0, 'pseudo_id' => ''], 200);
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('Vary', 'Cookie');
        return $response;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $sum = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount_huf) FROM {$table}
             WHERE LOWER(pseudo_id) = %s
               AND status IN ('approved','pending')",
            strtolower($pseudo_id)
        )
    );

    $response = new WP_REST_Response(
        [
            'total_huf' => (int) ($sum ?: 0),
            'pseudo_id' => $pseudo_id,
        ],
        200
    );
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    $response->header('Vary', 'Cookie');
    return $response;
}

/**
 * Restore pseudo ID using recovery code.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_restore(WP_REST_Request $request): WP_REST_Response
{
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['message' => 'A művelet lejárt. Töltsd újra az oldalt.'], 403);
    }
    if (!impactshop_identity_request_same_origin()) {
        return new WP_REST_Response(['message' => 'A kérés forrása nem engedélyezett.'], 403);
    }
    $params = (array)$request->get_json_params();
    $prior_pseudo_id = impactshop_identity_profile_cookie();
    $pseudo_id = isset($params['pseudo_id']) ? strtolower((string)$params['pseudo_id']) : '';
    $recovery_code = isset($params['recovery_code']) ? (string)$params['recovery_code'] : '';

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Hibás azonosító vagy belépési kód.'], 403);
    }

    if (!impactshop_identity_profile_rate_limit('signin', $pseudo_id)) {
        return new WP_REST_Response(['message' => 'Túl sok próbálkozás. Próbáld újra később.'], 429);
    }
    if ($recovery_code === '' || !impactshop_identity_profile_verify_access_code($pseudo_id, $recovery_code)) {
        return new WP_REST_Response(['message' => 'Hibás azonosító vagy belépési kód.'], 403);
    }

    $supersedes_grant_hash = impactshop_identity_owner_current_valid_grant_hash();
    if (!impactshop_identity_owner_issue($pseudo_id, $supersedes_grant_hash)) {
        return new WP_REST_Response(['message' => 'A fiók biztonságos összekapcsolása most nem sikerült.'], 503);
    }
    $pseudo_cookie_set = impactshop_identity_profile_set_cookie($pseudo_id);
    $owner_cookie_set = $pseudo_cookie_set && impactshop_identity_owner_set_pending_cookie();
    if (!$pseudo_cookie_set || !$owner_cookie_set) {
        impactshop_identity_owner_compensate_pending();
        impactshop_identity_restore_or_expire_pseudo_cookie($prior_pseudo_id, $pseudo_id);
        return new WP_REST_Response(['message' => 'A fiók biztonságos összekapcsolása most nem sikerült.'], 503);
    }
    $response = new WP_REST_Response(['status' => 'ok', 'pseudo_id' => $pseudo_id], 200);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    $response->header('Vary', 'Cookie');
    return $response;
}

/** Generate a portable sign-in code and issue a one-time reveal grant. */
function impactshop_identity_code_generate(WP_REST_Request $request): WP_REST_Response
{
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['message' => 'A művelet lejárt. Töltsd újra az oldalt.'], 403);
    }
    if (!impactshop_identity_request_same_origin()) {
        return new WP_REST_Response(['message' => 'A kérés forrása nem engedélyezett.'], 403);
    }

    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '' || !impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Nincs aktív profil.'], 403);
    }
    if (!impactshop_identity_owner_authorized($pseudo_id)) {
        return new WP_REST_Response(['message' => 'A kódgeneráláshoz előbb lépj be a meglévő fiókodba.'], 403);
    }
    if (!impactshop_identity_profile_rate_limit('generate', $pseudo_id)) {
        return new WP_REST_Response(['message' => 'Túl sok kódgenerálás. Próbáld újra később.'], 429);
    }

    $code = impactshop_identity_profile_generate_recovery_code();
    impactshop_identity_profile_store_code_hash($pseudo_id, $code);
    $grant = bin2hex(random_bytes(32));
    set_transient('impactshop_identity_reveal_' . hash('sha256', $grant), [
        'pseudo_id' => $pseudo_id,
        'code' => $code,
    ], 60);
    if (PHP_VERSION_ID >= 70300) {
        setcookie('impactshop_identity_reveal', $grant, [
            'expires' => time() + 60,
            'path' => '/profil/belepesi-kod/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    $response = new WP_REST_Response([
        'status' => 'ok',
        'reveal_url' => home_url('/profil/belepesi-kod/'),
    ], 200);
    $response->header('Cache-Control', 'private, no-store, max-age=0');
    $response->header('Pragma', 'no-cache');
    $response->header('Vary', 'Cookie');
    return $response;
}

/**
 * Read pseudo ID from cookie.
 *
 * @return string
 */
function impactshop_identity_profile_cookie(): string
{
    if (empty($_COOKIE['impactshop_pseudo_id']) || !is_string($_COOKIE['impactshop_pseudo_id'])) {
        return '';
    }
    $pseudo_id = strtolower(sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])));
    return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
}

/**
 * Validate pseudo ID format.
 *
 * @param string $pseudo_id Pseudo ID to validate.
 * @return bool
 */
function impactshop_identity_profile_valid_pseudo(string $pseudo_id): bool
{
    if (function_exists('impactshop_pin_valid_pseudo')) {
        return impactshop_pin_valid_pseudo($pseudo_id);
    }
    return (bool)preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id);
}

/**
 * Resolve requested bridge target from current request.
 *
 * @return string
 */
function impactshop_identity_requested_bridge_target(): string
{
    $value = isset($_REQUEST['bridge_target']) ? sanitize_text_field((string) wp_unslash($_REQUEST['bridge_target'])) : '';
    if ($value === 'restore') {
        return 'impactshop_restore';
    }
    if ($value === 'account') {
        return 'impactshop_account';
    }
    return '';
}

/**
 * Build native fallback profile URL for the given UI target.
 *
 * @param string $ui_target Bridge UI target.
 * @return string
 */
function impactshop_identity_profile_native_fallback(string $ui_target): string
{
    $base = home_url('/profil/');
    if ($ui_target === 'impactshop_restore') {
        return $base . '#impactshop-restore-title';
    }
    return $base;
}

/**
 * Complete profile-return redirect after a successful client-side action.
 *
 * @return void
 */
function impactshop_identity_maybe_complete_profile_return(): void
{
    if (is_admin()) {
        return;
    }

    if (empty($_GET['impactshop_profile_return_complete'])) {
        return;
    }

    $ui_target = impactshop_identity_requested_bridge_target();
    if ($ui_target === '' || !function_exists('impactshop_factlens_profile_return_target')) {
        return;
    }

    $target = impactshop_factlens_profile_return_target($ui_target, impactshop_identity_profile_native_fallback($ui_target));
    if (!is_string($target) || $target === '') {
        return;
    }

    $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $target_host = (string) wp_parse_url($target, PHP_URL_HOST);
    if ($target_host !== '' && $home_host !== '' && !hash_equals($home_host, $target_host)) {
        wp_redirect($target, 303);
        exit;
    }

    wp_safe_redirect($target, 303);
    exit;
}

/**
 * Validate nickname format.
 *
 * @param string $nickname Nickname to validate.

    $ui_target = impactshop_identity_requested_bridge_target();
    if ($ui_target !== '' && function_exists('impactshop_factlens_profile_return_target')) {
        $resolved = impactshop_factlens_profile_return_target($ui_target, impactshop_identity_profile_native_fallback($ui_target));
        if (is_string($resolved) && $resolved !== '') {
            $target_host = (string) wp_parse_url($resolved, PHP_URL_HOST);
            $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
            if ($target_host !== '' && $home_host !== '' && !hash_equals($home_host, $target_host)) {
                wp_redirect($resolved, 303);
                exit;
            }
            wp_safe_redirect($resolved, 303);
            exit;
        }
    }
 * @return bool
 */
function impactshop_identity_profile_valid_nickname(string $nickname): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($nickname) : strlen($nickname);
    if ($length < 2 || $length > 32) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}0-9 _.\-]+$/u', $nickname);
}

/**
 * Load nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string|null
 */
function impactshop_identity_profile_load(string $pseudo_id): ?string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return null;
    }
    return isset($value['nickname']) && is_string($value['nickname']) ? $value['nickname'] : null;
}

/**
 * Store nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $nickname Nickname (can be empty to clear).
 * @return void
 */
function impactshop_identity_profile_store(string $pseudo_id, string $nickname): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $payload = [
        'nickname'   => $nickname,
        'updated_at' => current_time('mysql', 1),
    ];

    if (is_array($existing)) {
        foreach (['recovery_code', 'recovery_hash'] as $key) {
            if (isset($existing[$key])) {
                $payload[$key] = (string) $existing[$key];
            }
        }
    }

    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/**
 * Get recovery code for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string|null
 */
function impactshop_identity_profile_get_recovery_code(string $pseudo_id): ?string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return null;
    }
    return isset($value['recovery_code']) && is_string($value['recovery_code']) ? $value['recovery_code'] : null;
}

/**
 * Store recovery code for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $recovery_code Recovery code.
 * @return void
 */
function impactshop_identity_profile_store_recovery(string $pseudo_id, string $recovery_code): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $payload = [
        'nickname'      => is_array($existing) && isset($existing['nickname']) ? (string)$existing['nickname'] : '',
        'recovery_code' => $recovery_code,
        'updated_at'    => current_time('mysql', 1),
    ];

    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/** Render the one-time, first-party-only code reveal page. */
function impactshop_identity_render_code_page(): void
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (untrailingslashit($path) !== '/profil/belepesi-kod') {
        return;
    }

    nocache_headers();
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Vary: Cookie');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'none'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    $grant = isset($_COOKIE['impactshop_identity_reveal']) ? sanitize_text_field((string) wp_unslash($_COOKIE['impactshop_identity_reveal'])) : '';
    $payload = $grant !== '' ? get_transient('impactshop_identity_reveal_' . hash('sha256', $grant)) : false;
    if ($grant !== '') {
        delete_transient('impactshop_identity_reveal_' . hash('sha256', $grant));
        setcookie('impactshop_identity_reveal', '', [
            'expires' => time() - 3600,
            'path' => '/profil/belepesi-kod/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    $pseudo = is_array($payload) && isset($payload['pseudo_id']) ? (string) $payload['pseudo_id'] : '';
    $code = is_array($payload) && isset($payload['code']) ? (string) $payload['code'] : '';
    if ($pseudo !== '' && strtolower($pseudo) !== impactshop_identity_profile_cookie()) {
        $pseudo = '';
        $code = '';
    }
    status_header(200);
    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Belépési kód</title><style>body{margin:0;background:#f8fafc;color:#0f172a;font:16px/1.5 system-ui,sans-serif}.wrap{max-width:560px;margin:12vh auto;padding:28px}.card{background:#fff;border:1px solid #cbd5e1;border-radius:20px;padding:28px;box-shadow:0 16px 40px #0f172a1a}h1{margin-top:0}.code{display:block;margin:20px 0;padding:18px;border-radius:12px;background:#e0f2fe;color:#075985;font:700 24px ui-monospace,monospace;letter-spacing:.08em;word-break:break-word}.hint{color:#475569}</style></head><body><main class="wrap"><section class="card"><h1>Belépési kód</h1>';
    if ($pseudo !== '' && $code !== '') {
        echo '<p>Az alábbi kód a pseudo ID-hoz tartozik. Mentsd el a jelszókezelődbe.</p><p><strong>Pseudo ID:</strong> ' . esc_html($pseudo) . '</p><code class="code">' . esc_html($code) . '</code><form method="post" action="" autocomplete="on"><input type="hidden" name="username" autocomplete="username" value="' . esc_attr($pseudo) . '"><input type="password" name="password" autocomplete="new-password" value="' . esc_attr($code) . '" style="position:absolute;left:-9999px"><button type="submit">Mentés a jelszókezelőbe</button></form><p class="hint">Ez az oldal egyszeri megjelenítésre szolgált. Bezárás után a kódot innen nem lehet újra kiolvasni.</p>';
    } else {
        echo '<p>Ez a biztonságos kódmegjelenítés lejárt vagy már felhasználtad. A profiloldalról kérj új kódot.</p>';
    }
    echo '</section></main></body></html>';
    exit;
}

function impactshop_identity_profile_normalize_code(string $code): string
{
    return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($code)));
}

function impactshop_identity_profile_rate_limit(string $action, string $pseudo_id): bool
{
    $key = 'impactshop_identity_rl_' . hash_hmac('sha256', $action . '|' . strtolower($pseudo_id), wp_salt('impactshop_profile'));
    $count = (int) get_transient($key);
    if ($count >= 5) {
        return false;
    }
    set_transient($key, $count + 1, 60);
    return true;
}

function impactshop_identity_profile_has_access_code(string $pseudo_id): bool
{
    $value = get_option(impactshop_identity_profile_option_key($pseudo_id), null);
    return is_array($value) && (
        !empty($value['recovery_hash']) ||
        (isset($value['recovery_code']) && is_string($value['recovery_code']) && $value['recovery_code'] !== '')
    );
}

function impactshop_identity_profile_store_code_hash(string $pseudo_id, string $code): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $payload = [
        'nickname' => is_array($existing) && isset($existing['nickname']) ? (string) $existing['nickname'] : '',
        'recovery_hash' => wp_hash_password('sharity-access-v2|' . impactshop_identity_profile_normalize_code($code)),
        'updated_at' => current_time('mysql', 1),
    ];
    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }
    update_option($key, $payload, false);
}

function impactshop_identity_profile_verify_access_code(string $pseudo_id, string $code): bool
{
    $normalized = impactshop_identity_profile_normalize_code($code);
    if ($normalized === '') {
        return false;
    }
    $value = get_option(impactshop_identity_profile_option_key($pseudo_id), null);
    if (!is_array($value)) {
        return false;
    }
    if (!empty($value['recovery_hash']) && wp_check_password('sharity-access-v2|' . $normalized, (string) $value['recovery_hash'])) {
        return true;
    }
    $legacy = isset($value['recovery_code']) ? impactshop_identity_profile_normalize_code((string) $value['recovery_code']) : '';
    if ($legacy !== '' && hash_equals($legacy, $normalized)) {
        impactshop_identity_profile_store_code_hash($pseudo_id, $normalized);
        return true;
    }
    return false;
}

/**
 * Generate a short recovery code.
 *
 * @return string
 */
function impactshop_identity_profile_generate_recovery_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = 12;
    $raw = '';
    for ($i = 0; $i < $length; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
}

/**
 * Build option key for pseudo profile.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string
 */
function impactshop_identity_profile_option_key(string $pseudo_id): string
{
    $pseudo_id = strtolower($pseudo_id);
    $hash = hash_hmac('sha256', $pseudo_id, wp_salt('impactshop_profile'));
    return 'impactshop_pseudo_profile_' . $hash;
}

/**
 * Generate pseudo ID (base36).
 *
 * @return string
 */
function impactshop_identity_profile_generate_pseudo_id(): string
{
    $raw = '';
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    for ($i = 0; $i < 12; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $raw;
}

/**
 * Set pseudo ID cookie on client.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return bool
 */
function impactshop_identity_profile_set_cookie(string $pseudo_id, ?int $expires = null): bool
{
    $pseudo_id = strtolower($pseudo_id);
    $secure = true;
    $expires = $expires ?? (impactshop_identity_utc_now() + (365 * DAY_IN_SECONDS));
    if (PHP_VERSION_ID >= 70300) {
        return setcookie('impactshop_pseudo_id', $pseudo_id, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    } else {
        return setcookie('impactshop_pseudo_id', $pseudo_id, $expires, '/; samesite=Lax', '', $secure, false);
    }
}
