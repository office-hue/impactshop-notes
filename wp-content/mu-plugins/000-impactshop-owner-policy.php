<?php
/**
 * Plugin Name: ImpactShop owner policy registry
 * Description: First-loaded, fail-closed policy boundary for pseudo-backed surfaces.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_OWNER_POLICY_VERSION = 3;
const IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE_OPTION = 'impactshop_owner_policy_safe_disable';

/**
 * The registry is deliberately explicit. A route absent from this map is not
 * silently treated as an owner route: the token_get_all inventory gate must
 * classify every protected registration before source admission.
 */
function impactshop_owner_policy_registry(): array
{
    $registry = [
        // Identity and profile writes.
        'POST /impact/v1/identity/profile' => ['policy' => 'owner_required', 'source' => 'impactshop-identity-panel.php'],
        'POST /impact/v1/identity/restore' => ['policy' => 'access_code_exchange', 'source' => 'impactshop-identity-panel.php'],
        'POST /impact/v1/identity/code/generate' => ['policy' => 'owner_required', 'source' => 'impactshop-identity-panel.php'],

        // User-scoped profile feature writes.
        'POST /impact/v1/ngo-selector/set' => ['policy' => 'owner_required', 'source' => 'impactshop-ngo-selector.php'],
        'POST /impact/v1/saved-offers/save' => ['policy' => 'owner_required', 'source' => 'impactshop-saved-offers.php'],
        'POST /impact/v1/push/subscribe' => ['policy' => 'owner_required', 'source' => 'impactshop-pwa-push.php'],
        'POST /impact/v1/push/unsubscribe' => ['policy' => 'owner_required', 'source' => 'impactshop-pwa-push.php'],
        'POST /impact/v1/tracking/cta-click' => ['policy' => 'owner_required', 'source' => 'impactshop-click-tracking.php'],

        // Ad-watch and vote writers. Existing nonce/kill-switch/origin checks
        // remain in their original callbacks and are not replaced here.
        'POST /impact/v1/ads-watch/view' => ['policy' => 'owner_required', 'source' => 'impactshop-ads-watch.php'],
        'POST /impact/v1/ads-watch/education' => ['policy' => 'owner_required', 'source' => 'impactshop-ads-watch.php'],
        'POST /impact/v1/ads-watch/allocate' => ['policy' => 'owner_required', 'source' => 'impactshop-ads-watch.php'],
        'POST /impact/v1/ads-watch/set-ngo' => ['policy' => 'owner_required', 'source' => 'impactshop-ads-watch.php'],
        'POST /impact/v1/ads-watch/set-auto-vote' => ['policy' => 'owner_required', 'source' => 'impactshop-ads-watch.php'],
        'POST /impact/v1/vote/view' => ['policy' => 'owner_required', 'source' => 'impactshop-vote-jysk.php'],
        'POST /impact/v1/vote/cast' => ['policy' => 'owner_required', 'source' => 'impactshop-vote-jysk.php'],
        'POST /impact/v1/identity/message-read' => ['policy' => 'owner_required', 'source' => 'impactshop-vote-jysk.php'],

        // Pseudo points/vacation/referral feedback writers.
        'POST /sharity/v1/pseudo/points/earn' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'POST /sharity/v1/pseudo/vacation' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'POST /sharity/v1/pseudo/vacation/end' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'POST /sharity/v1/pseudo/last-ngo' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'POST /sharity/v1/pseudo/feedback' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'POST /sharity/v1/pseudo/video-ad' => ['policy' => 'owner_required', 'source' => 'sharity-points-events.php'],

        // VB2026 selection mutators bind a pseudo ID to a selection/intent.
        'POST /impact/v1/vb2026/select-ngo' => ['policy' => 'owner_or_service_auth', 'source' => 'impactshop-vb2026-ngo-catalog.php'],
        'POST /impact/v1/vb2026/selection-intent' => ['policy' => 'pre_auth_intent', 'source' => 'impactshop-vb2026-ngo-catalog.php'],
        'POST /impact/v1/vb2026/selection-intent/complete' => ['policy' => 'owner_or_service_auth', 'source' => 'impactshop-vb2026-ngo-catalog.php'],

        // Private reads are also owner-bound to avoid cross-device leakage.
        'GET /impact/v1/saved-offers' => ['policy' => 'owner_required', 'source' => 'impactshop-saved-offers.php'],
        'GET /impact/v1/saved-offers/open/(?P<id>\d+)' => ['policy' => 'owner_required', 'source' => 'impactshop-saved-offers.php'],
        'GET /impact/v1/vb2026/my-ngo-selection' => ['policy' => 'owner_or_service_auth', 'source' => 'impactshop-vb2026-ngo-catalog.php'],
        'GET /sharity/v1/pseudo/points' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'GET /sharity/v1/pseudo/points/history' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'GET /sharity/v1/pseudo/vacation' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'GET /sharity/v1/pseudo/last-ngo' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],
        'GET /sharity/v1/pseudo/referral' => ['policy' => 'owner_required', 'source' => 'sharity-points-api.php'],

        // Existing server-to-server/admin/public surfaces remain explicit and
        // retain their original authentication callbacks. Service routes in
        // other MU files are outside this exact pseudo-backed inventory and
        // remain classified by their native HMAC/webhook callbacks.

        // User-bound reads promoted from the public exclusion list.
        'GET /impact/v1/identity/total' => ['policy' => 'owner_required', 'source' => 'impactshop-identity-panel.php'],
        'GET /impact/v1/ngo-selector/get' => ['policy' => 'owner_required', 'source' => 'impactshop-ngo-selector.php'],
    ];
    foreach (impactshop_owner_policy_explicit_exclusions() as $route => $entry) {
        if (!isset($registry[$route])) {
            $registry[$route] = $entry;
        }
    }
    $callbacks = impactshop_owner_policy_callback_manifest();
    foreach ($registry as $route => &$entry) {
        $entry['callback'] = $callbacks[$route] ?? '';
    }
    unset($entry);
    return $registry;
}

/**
 * Every non-owner route in the inspected pseudo-backed MU set is classified
 * here. This is an explicit exclusion, not an implicit public default.
 */
function impactshop_owner_policy_explicit_exclusions(): array
{
    $public = static fn(string $source): array => ['policy' => 'explicitly_public_non_identity/read_only', 'source' => $source];
    $service = static fn(string $source): array => ['policy' => 'service_auth', 'source' => $source];
    return [
        'GET /impact/v1/identity/profile' => $public('impactshop-identity-panel.php'),
        'GET /impact/v1/identity/refresh-nonce' => $public('impactshop-identity-panel.php'),
        'GET /impact/v1/identity/messages' => $public('impactshop-vote-jysk.php'),
        'GET /impact/v1/ngo-catalog' => $public('impactshop-vb2026-ngo-catalog.php'),
        'GET /impact/v1/vb2026/featured-ngos' => $public('impactshop-vb2026-ngo-catalog.php'),
        'GET /impact/v1/vote/refresh-nonce' => $public('impactshop-vote-jysk.php'),
        'GET /impact/v1/ads-watch/config' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/next' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/status' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/tally' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/leaderboard' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/ngos' => $public('impactshop-ads-watch.php'),
        'GET /impact/v1/ads-watch/debug-rotation' => ['policy' => 'admin_capability', 'source' => 'impactshop-ads-watch.php'],
        'GET /impact/v1/vote/init' => $public('impactshop-vote-jysk.php'),
        'GET /impact/v1/vote/campaign' => $public('impactshop-vote-jysk.php'),
        'GET /impact/v1/vote/status' => $public('impactshop-vote-jysk.php'),
        'GET /impact/v1/vote/tally' => $public('impactshop-vote-jysk.php'),
        'GET /sharity/v1/user/points' => $service('sharity-points-api.php'),
        'GET /sharity/v1/user/points/history' => $service('sharity-points-api.php'),
        'GET /sharity/v1/user/vacation' => $service('sharity-points-api.php'),
        'POST /sharity/v1/user/vacation' => $service('sharity-points-api.php'),
        'POST /sharity/v1/user/vacation/end' => $service('sharity-points-api.php'),
        'GET /sharity/v1/user/last-ngo' => $service('sharity-points-api.php'),
        'POST /sharity/v1/user/last-ngo' => $service('sharity-points-api.php'),
        'GET /sharity/v1/user/referral' => $service('sharity-points-api.php'),
        'POST /sharity/v1/user/feedback' => $service('sharity-points-api.php'),
        'POST /sharity/v1/points/earn' => $service('sharity-points-api.php'),
        'POST /sharity/v1/webhook/purchase' => $service('sharity-points-api.php'),
        'POST /sharity/v1/admin/adjust' => ['policy' => 'admin_capability', 'source' => 'sharity-points-api.php'],
        'GET /impact/v1/push/public-key' => $public('impactshop-pwa-push.php'),
        // Service/webhook route exclusions are recorded by source policy and
        // are intentionally not pseudo-cookie authorities in this registry.
    ];
}

function impactshop_owner_policy_key(string $method, string $route): string
{
    return strtoupper($method) . ' ' . $route;
}

function impactshop_owner_policy_for_request(WP_REST_Request $request): ?array
{
    $registry = impactshop_owner_policy_registry();
    $key = impactshop_owner_policy_key((string) $request->get_method(), (string) $request->get_route());
    if (isset($registry[$key])) {
        return $registry[$key];
    }
    // Route regexes are represented canonically in the registry. Compare the
    // actual route against those patterns without broad substring matching.
    foreach ($registry as $pattern => $entry) {
        [$method, $route_pattern] = explode(' ', $pattern, 2);
        if ($method !== strtoupper((string) $request->get_method())) {
            continue;
        }
        $placeholder = [];
        $route_literal = preg_replace_callback(
            '~\\(\\?P<[^>]+>[^)]+\\)~',
            static function (): string {
                return '__IMPACTSHOP_ROUTE_PARAM__';
            },
            $route_pattern
        );
        if (!is_string($route_literal)) {
            continue;
        }
        $route_literal = preg_quote($route_literal, '~');
        $regex = '~^' . str_replace(preg_quote('__IMPACTSHOP_ROUTE_PARAM__', '~'), '[^/]+', $route_literal) . '$~';
        if (preg_match($regex, (string) $request->get_route()) === 1) {
            return $entry;
        }
    }
    return null;
}

function impactshop_owner_policy_safe_disabled(): bool
{
    return (bool) get_option(IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE_OPTION, false)
        || (defined('IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE') && IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE === true);
}

function impactshop_owner_policy_safe_disable_set(bool $enabled): void
{
    update_option(IMPACTSHOP_OWNER_POLICY_SAFE_DISABLE_OPTION, $enabled ? 1 : 0, false);
}

function impactshop_owner_policy_error(string $code = 'owner_policy_denied'): WP_Error
{
    return new WP_Error($code, 'A profil biztonsági kapcsolata nem ellenőrizhető.', ['status' => 403]);
}

function impactshop_owner_policy_request_has_secret_body(WP_REST_Request $request): bool
{
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_body_params();
    }
    foreach (['owner_token', 'owner_grant', 'grant', 'grant_token', 'supersedes_grant_hash'] as $secret_key) {
        if (array_key_exists($secret_key, (array) $params)) {
            return true;
        }
    }
    return false;
}

function impactshop_owner_policy_request_allowed(WP_REST_Request $request, array $entry): bool
{
    $policy = (string) ($entry['policy'] ?? '');
    if ($policy === 'access_code_exchange') {
        if (impactshop_owner_policy_safe_disabled()
            || impactshop_owner_policy_request_has_secret_body($request)
            || !function_exists('impactshop_identity_request_same_origin')) {
            return false;
        }
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }
        return $nonce !== ''
            && wp_verify_nonce($nonce, 'wp_rest')
            && impactshop_identity_request_same_origin();
    }
    if ($policy === 'owner_required') {
        if (impactshop_owner_policy_safe_disabled() || impactshop_owner_policy_request_has_secret_body($request)) {
            return false;
        }
        if (!function_exists('impactshop_identity_profile_cookie')
            || !function_exists('impactshop_identity_owner_authorized')
            || !function_exists('impactshop_identity_request_same_origin')) {
            return false;
        }
        $pseudo = (string) impactshop_identity_profile_cookie();
        return $pseudo !== ''
            && impactshop_identity_request_same_origin()
            && impactshop_identity_owner_authorized($pseudo);
    }
    if ($policy === 'owner_or_service_auth') {
        if (impactshop_owner_policy_safe_disabled()
            || impactshop_owner_policy_request_has_secret_body($request)
            || !function_exists('impactshop_vb2026_owner_or_service_authorized')) {
            return false;
        }
        return impactshop_vb2026_owner_or_service_authorized($request);
    }
    if ($policy === 'pre_auth_intent') {
        return !impactshop_owner_policy_safe_disabled()
            && !impactshop_owner_policy_request_has_secret_body($request);
    }
    if ($policy === 'admin_capability') {
        return current_user_can('manage_options') || current_user_can('manage_sharity_points');
    }
    // service_auth and explicit public/read-only routes retain their endpoint
    // callback auth; this layer never widens those permissions.
    return true;
}

/**
 * Enforce the central policy after native permission callbacks and before the
 * route callback. Existing endpoint auth remains mandatory and authoritative.
 */
function impactshop_owner_policy_before_callbacks($response, $handler, WP_REST_Request $request)
{
    $entry = impactshop_owner_policy_for_request($request);
    if (!is_array($entry)) {
        return $response;
    }
    if (!impactshop_owner_policy_request_allowed($request, $entry)) {
        return impactshop_owner_policy_error();
    }
    return $response;
}
add_filter('rest_request_before_callbacks', 'impactshop_owner_policy_before_callbacks', 5, 3);

function impactshop_owner_policy_registered_method($registered_methods, string $method): bool
{
    if (is_array($registered_methods)) {
        // WP_REST_Server::get_routes() exposes normalized method maps such as
        // ['POST' => true]. Accept only an exact boolean registration; indexed
        // lists and non-boolean values are not valid route method evidence.
        return array_key_exists($method, $registered_methods)
            && $registered_methods[$method] === true;
    }
    if (is_string($registered_methods)) {
        return in_array($method, preg_split('/[|,\s]+/', $registered_methods, -1, PREG_SPLIT_NO_EMPTY), true);
    }
    if (is_int($registered_methods)) {
        $mask = ['GET' => 1, 'POST' => 2, 'PUT' => 4, 'PATCH' => 4, 'DELETE' => 8][$method] ?? 0;
        return $mask !== 0 && ($registered_methods & $mask) !== 0;
    }
    return false;
}

function impactshop_owner_policy_callback_matches($callback, string $expected): bool
{
    if ($expected === '') {
        return false;
    }
    if (is_string($callback)) {
        return hash_equals($expected, $callback);
    }
    if (is_array($callback) && count($callback) === 2) {
        $target = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
        return hash_equals($expected, $target . '::' . (string) $callback[1]);
    }
    return false;
}

function impactshop_owner_policy_runtime_condition_enabled(string $pattern): bool
{
    if ($pattern !== 'GET /impact/v1/ads-watch/debug-rotation') {
        return true;
    }
    return function_exists('impactshop_ads_watch_debug_enabled')
        && impactshop_ads_watch_debug_enabled();
}

/** Runtime inventory snapshot consumed by the release self-test. */
function impactshop_owner_policy_runtime_inventory(): array
{
    global $wp_rest_server;
    $routes = is_object($wp_rest_server) && method_exists($wp_rest_server, 'get_routes')
        ? (array) $wp_rest_server->get_routes()
        : [];
    $registered = [];
    foreach (impactshop_owner_policy_registry() as $pattern => $entry) {
        [$method, $route] = explode(' ', $pattern, 2);
        $method_registered = false;
        $callback_registered = false;
        $callback_exact = false;
        foreach ((array) ($routes[$route] ?? []) as $handler) {
            if (!is_array($handler)) {
                continue;
            }
            $registered_methods = $handler['methods'] ?? '';
            $method_registered = $method_registered
                || impactshop_owner_policy_registered_method($registered_methods, $method);
            $callback_registered = $callback_registered || isset($handler['callback']);
            if (impactshop_owner_policy_registered_method($registered_methods, $method)) {
                $callback_exact = $callback_exact || impactshop_owner_policy_callback_matches($handler['callback'] ?? null, (string) ($entry['callback'] ?? ''));
            }
        }
        $registered[$pattern] = [
            'policy' => $entry['policy'],
            'source' => $entry['source'],
            'route_registered' => isset($routes[$route]),
            'method_registered' => $method_registered,
            'callback_registered' => $callback_registered,
            'callback_exact' => $callback_exact,
            'callback' => $entry['callback'] ?? '',
            'method' => $method,
        ];
    }
    return [
        'version' => IMPACTSHOP_OWNER_POLICY_VERSION,
        'safe_disable' => impactshop_owner_policy_safe_disabled(),
        'registered' => $registered,
    ];
}

/**
 * Canonical route -> callback manifest. The source and effective policy live
 * beside each route above; this map makes callback identity equally explicit
 * for static inventory and the runtime self-test.
 */
function impactshop_owner_policy_callback_manifest(): array
{
    return [
        'POST /impact/v1/identity/profile' => 'impactshop_identity_profile_update',
        'POST /impact/v1/identity/restore' => 'impactshop_identity_profile_restore',
        'POST /impact/v1/identity/code/generate' => 'impactshop_identity_code_generate',
        'POST /impact/v1/ngo-selector/set' => 'impactshop_ngo_selector_set',
        'POST /impact/v1/saved-offers/save' => 'impactshop_saved_offers_save',
        'POST /impact/v1/push/subscribe' => 'impactshop_pwa_push_subscribe',
        'POST /impact/v1/push/unsubscribe' => 'impactshop_pwa_push_unsubscribe',
        'POST /impact/v1/tracking/cta-click' => 'impactshop_click_tracking_handle',
        'POST /impact/v1/ads-watch/view' => 'impactshop_ads_watch_view',
        'POST /impact/v1/ads-watch/education' => 'impactshop_ads_watch_education',
        'POST /impact/v1/ads-watch/allocate' => 'impactshop_ads_watch_allocate_votes',
        'POST /impact/v1/ads-watch/set-ngo' => 'impactshop_ads_watch_set_ngo',
        'POST /impact/v1/ads-watch/set-auto-vote' => 'impactshop_ads_watch_set_auto_vote',
        'POST /impact/v1/vote/view' => 'impactshop_vote_jysk_view',
        'POST /impact/v1/vote/cast' => 'impactshop_vote_jysk_cast',
        'POST /impact/v1/identity/message-read' => 'impactshop_vote_jysk_identity_message_read',
        'POST /sharity/v1/pseudo/points/earn' => 'sharity_points_pseudo_earn',
        'POST /sharity/v1/pseudo/vacation' => 'sharity_points_pseudo_vacation_start',
        'POST /sharity/v1/pseudo/vacation/end' => 'sharity_points_pseudo_vacation_end',
        'POST /sharity/v1/pseudo/last-ngo' => 'sharity_points_set_pseudo_last_ngo',
        'POST /sharity/v1/pseudo/feedback' => 'sharity_points_pseudo_feedback',
        'POST /sharity/v1/pseudo/video-ad' => 'sharity_points_video_ad',
        'POST /impact/v1/vb2026/select-ngo' => 'impactshop_vb2026_rest_select_ngo',
        'POST /impact/v1/vb2026/selection-intent' => 'impactshop_vb2026_rest_selection_intent',
        'POST /impact/v1/vb2026/selection-intent/complete' => 'impactshop_vb2026_rest_selection_intent_complete',
        'GET /impact/v1/saved-offers' => 'impactshop_saved_offers_list',
        'GET /impact/v1/saved-offers/open/(?P<id>\d+)' => 'impactshop_saved_offers_open',
        'GET /impact/v1/vb2026/my-ngo-selection' => 'impactshop_vb2026_rest_my_ngo_selection',
        'GET /sharity/v1/pseudo/points' => 'sharity_points_get_pseudo_points',
        'GET /sharity/v1/pseudo/points/history' => 'sharity_points_get_pseudo_history',
        'GET /sharity/v1/pseudo/vacation' => 'sharity_points_pseudo_vacation_status',
        'GET /sharity/v1/pseudo/last-ngo' => 'sharity_points_get_pseudo_last_ngo',
        'GET /sharity/v1/pseudo/referral' => 'sharity_points_get_pseudo_referral',
        'POST /sharity/v1/admin/adjust' => 'sharity_points_admin_adjust',
        'GET /impact/v1/identity/profile' => 'impactshop_identity_profile_get',
        'GET /impact/v1/identity/total' => 'impactshop_identity_profile_total',
        'GET /impact/v1/identity/refresh-nonce' => 'impactshop_identity_refresh_nonce',
        'GET /impact/v1/identity/messages' => 'impactshop_vote_jysk_identity_messages',
        'GET /impact/v1/vote/init' => 'impactshop_vote_jysk_init',
        'GET /impact/v1/vote/campaign' => 'impactshop_vote_jysk_campaign',
        'GET /impact/v1/vote/status' => 'impactshop_vote_jysk_status',
        'GET /impact/v1/vote/tally' => 'impactshop_vote_jysk_tally',
        'GET /impact/v1/ngo-selector/get' => 'impactshop_ngo_selector_get',
        'GET /impact/v1/ngo-catalog' => 'impactshop_vb2026_rest_ngo_catalog',
        'GET /impact/v1/vb2026/featured-ngos' => 'impactshop_vb2026_rest_featured_ngos',
        'GET /impact/v1/vote/refresh-nonce' => 'impactshop_vote_jysk_refresh_nonce',
        'GET /impact/v1/ads-watch/debug-rotation' => 'impactshop_ads_watch_debug_rotation',
        'GET /impact/v1/ads-watch/config' => 'impactshop_ads_watch_config',
        'GET /impact/v1/ads-watch/next' => 'impactshop_ads_watch_next',
        'GET /impact/v1/ads-watch/status' => 'impactshop_ads_watch_status',
        'GET /impact/v1/ads-watch/tally' => 'impactshop_ads_watch_tally',
        'GET /impact/v1/ads-watch/leaderboard' => 'impactshop_ads_watch_leaderboard',
        'GET /impact/v1/ads-watch/ngos' => 'impactshop_ads_watch_ngos',
        'GET /sharity/v1/user/points' => 'sharity_points_get_user_points',
        'GET /sharity/v1/user/points/history' => 'sharity_points_get_history',
        'GET /sharity/v1/user/vacation' => 'sharity_points_vacation_status',
        'POST /sharity/v1/user/vacation' => 'sharity_points_vacation_start',
        'POST /sharity/v1/user/vacation/end' => 'sharity_points_vacation_end',
        'GET /sharity/v1/user/last-ngo' => 'sharity_points_get_last_ngo',
        'POST /sharity/v1/user/last-ngo' => 'sharity_points_set_last_ngo',
        'GET /sharity/v1/user/referral' => 'sharity_points_get_user_referral',
        'POST /sharity/v1/user/feedback' => 'sharity_points_user_feedback',
        'POST /sharity/v1/points/earn' => 'sharity_points_earn',
        'POST /sharity/v1/webhook/purchase' => 'sharity_points_webhook_purchase',
        'GET /impact/v1/push/public-key' => 'impactshop_pwa_push_public_key',
    ];
}

function impactshop_owner_policy_runtime_self_test(): bool
{
    global $wp_rest_server;
    if (!is_object($wp_rest_server) || !method_exists($wp_rest_server, 'get_routes')) {
        impactshop_owner_policy_safe_disable_set(true);
        return false;
    }
    $routes = (array) $wp_rest_server->get_routes();
    foreach (impactshop_owner_policy_registry() as $pattern => $entry) {
        [, $route] = explode(' ', $pattern, 2);
        $condition_enabled = impactshop_owner_policy_runtime_condition_enabled($pattern);
        // The debug endpoint is deliberately absent in normal runtime. If it
        // is registered while disabled, still validate its exact tuple; only
        // an absent disabled endpoint is exempt from safe-disable.
        if (!$condition_enabled && !isset($routes[$route])) {
            continue;
        }
        if (!isset($routes[$route])) {
            impactshop_owner_policy_safe_disable_set(true);
            return false;
        }
        [$method] = explode(' ', $pattern, 2);
        $method_registered = false;
        $callback_registered = false;
        foreach ((array) $routes[$route] as $handler) {
            if (!is_array($handler)) {
                continue;
            }
            $registered_methods = $handler['methods'] ?? '';
            $method_registered = $method_registered
                || impactshop_owner_policy_registered_method($registered_methods, $method);
            if (impactshop_owner_policy_registered_method($registered_methods, $method)
                && impactshop_owner_policy_callback_matches($handler['callback'] ?? null, (string) ($entry['callback'] ?? ''))) {
                $callback_registered = true;
            }
        }
        if (!$method_registered || !$callback_registered) {
            impactshop_owner_policy_safe_disable_set(true);
            return false;
        }
    }
    return true;
}
add_action('rest_api_init', static function (): void {
    impactshop_owner_policy_runtime_self_test();
    $GLOBALS['impactshop_owner_policy_runtime_inventory'] = impactshop_owner_policy_runtime_inventory();
}, 9999);

function impactshop_owner_policy_add_version_header($response)
{
    if ($response instanceof WP_REST_Response) {
        $response->header('X-ImpactShop-Policy-Version', (string) IMPACTSHOP_OWNER_POLICY_VERSION);
    }
    return $response;
}
add_filter('rest_post_dispatch', 'impactshop_owner_policy_add_version_header', 5, 1);

/** Fail closed for the only pseudo-backed template action in this lane. */
function impactshop_owner_policy_guard_template_actions(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    // The password-manager form stores no identity data server-side; it is an
    // explicitly public/non-identity redirect and is intentionally excluded.
}
add_action('init', 'impactshop_owner_policy_guard_template_actions', 0);
