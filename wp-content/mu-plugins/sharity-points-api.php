<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'sharity_points_register_routes');

function sharity_points_register_routes(): void
{
    register_rest_route('sharity/v1', '/user/points', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_user_points',
        'permission_callback' => 'sharity_points_read_permission',
    ]);

    register_rest_route('sharity/v1', '/user/points/history', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_history',
        'permission_callback' => 'sharity_points_read_permission',
    ]);

    register_rest_route('sharity/v1', '/points/earn', [
        'methods' => 'POST',
        'callback' => 'sharity_points_earn',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('sharity/v1', '/admin/adjust', [
        'methods' => 'POST',
        'callback' => 'sharity_points_admin_adjust',
        'permission_callback' => 'sharity_points_manage_permission',
    ]);

    register_rest_route('sharity/v1', '/webhook/purchase', [
        'methods' => 'POST',
        'callback' => 'sharity_points_webhook_purchase',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('sharity/v1', '/user/vacation', [
        [
            'methods' => 'GET',
            'callback' => 'sharity_points_vacation_status',
            'permission_callback' => 'sharity_points_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'sharity_points_vacation_start',
            'permission_callback' => 'sharity_points_read_permission',
        ],
    ]);

    register_rest_route('sharity/v1', '/user/vacation/end', [
        'methods' => 'POST',
        'callback' => 'sharity_points_vacation_end',
        'permission_callback' => 'sharity_points_read_permission',
    ]);

    register_rest_route('sharity/v1', '/user/last-ngo', [
        [
            'methods' => 'GET',
            'callback' => 'sharity_points_get_last_ngo',
            'permission_callback' => 'sharity_points_read_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'sharity_points_set_last_ngo',
            'permission_callback' => 'sharity_points_read_permission',
        ],
    ]);

    register_rest_route('sharity/v1', '/user/referral', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_user_referral',
        'permission_callback' => 'sharity_points_read_permission',
    ]);

    register_rest_route('sharity/v1', '/user/feedback', [
        'methods' => 'POST',
        'callback' => 'sharity_points_user_feedback',
        'permission_callback' => 'sharity_points_read_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/points', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_pseudo_points',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/points/history', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_pseudo_history',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/points/earn', [
        'methods' => 'POST',
        'callback' => 'sharity_points_pseudo_earn',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/vacation', [
        [
            'methods' => 'GET',
            'callback' => 'sharity_points_pseudo_vacation_status',
            'permission_callback' => 'sharity_points_pseudo_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'sharity_points_pseudo_vacation_start',
            'permission_callback' => 'sharity_points_pseudo_permission',
        ],
    ]);

    register_rest_route('sharity/v1', '/pseudo/vacation/end', [
        'methods' => 'POST',
        'callback' => 'sharity_points_pseudo_vacation_end',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/last-ngo', [
        [
            'methods' => 'GET',
            'callback' => 'sharity_points_get_pseudo_last_ngo',
            'permission_callback' => 'sharity_points_pseudo_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'sharity_points_set_pseudo_last_ngo',
            'permission_callback' => 'sharity_points_pseudo_permission',
        ],
    ]);

    register_rest_route('sharity/v1', '/pseudo/referral', [
        'methods' => 'GET',
        'callback' => 'sharity_points_get_pseudo_referral',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);

    register_rest_route('sharity/v1', '/pseudo/feedback', [
        'methods' => 'POST',
        'callback' => 'sharity_points_pseudo_feedback',
        'permission_callback' => 'sharity_points_pseudo_permission',
    ]);
}

function sharity_points_read_permission(): bool
{
    return is_user_logged_in();
}

function sharity_points_manage_permission(): bool
{
    return current_user_can('manage_sharity_points') || current_user_can('manage_options');
}

function sharity_points_pseudo_permission(): bool
{
    return sharity_points_get_pseudo_from_cookie() !== '';
}

function sharity_points_get_pseudo_from_cookie(): string
{
    $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])) : '';
    if ($pseudo === '') {
        return '';
    }
    if (function_exists('sharity_normalize_pseudo_id')) {
        return sharity_normalize_pseudo_id($pseudo);
    }
    $pseudo = strtolower($pseudo);
    if (function_exists('impactshop_identity_profile_valid_pseudo')) {
        return impactshop_identity_profile_valid_pseudo($pseudo) ? $pseudo : '';
    }
    return preg_match('/^[a-z0-9]{10,12}$/', $pseudo) ? $pseudo : '';
}

function sharity_points_validate_signature(string $body, string $timestamp, string $signature): bool
{
    $secret = defined('SHARITY_POINTS_HMAC_SECRET') ? SHARITY_POINTS_HMAC_SECRET : wp_salt('sharity_points');
    $payload = $timestamp . '.' . $body;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

function sharity_points_require_signature(WP_REST_Request $request): ?WP_REST_Response
{
    $body = $request->get_body();
    $timestamp = (string) $request->get_header('x-sharity-timestamp');
    $signature = (string) $request->get_header('x-sharity-signature');

    if ($timestamp === '' || $signature === '') {
        return new WP_REST_Response(['message' => 'Unauthorized'], 401);
    }

    if (!ctype_digit($timestamp)) {
        return new WP_REST_Response(['message' => 'Invalid timestamp'], 401);
    }

    $timestamp_int = (int) $timestamp;
    if (abs(time() - $timestamp_int) > 300) {
        return new WP_REST_Response(['message' => 'Signature expired'], 401);
    }

    if (!sharity_points_validate_signature($body, $timestamp, $signature)) {
        return new WP_REST_Response(['message' => 'Unauthorized'], 401);
    }

    $replay_key = 'sharity_points_sig_' . hash('sha256', $timestamp . '.' . $signature);
    if (get_transient($replay_key)) {
        return new WP_REST_Response(['message' => 'Replay detected'], 409);
    }
    set_transient($replay_key, 1, 300);

    return null;
}

function sharity_points_get_user_points(WP_REST_Request $request): WP_REST_Response
{
    $user_id = (int) $request->get_param('user_id');
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }

    if ($user_id !== get_current_user_id() && !sharity_points_manage_permission()) {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $manager = new Sharity_Points_Manager();
    $snapshot = $manager->get_points_snapshot($user_id);

    if (empty($snapshot)) {
        return new WP_REST_Response(['message' => 'Not found'], 404);
    }

    $level_manager = new Sharity_Level_Manager();
    $level_config = $level_manager->get_level_config((string) $snapshot['current_level']);

    return new WP_REST_Response([
        'user_id' => $user_id,
        'points' => [
            'total' => (int) $snapshot['points_total'],
            'lifetime' => (int) $snapshot['points_lifetime'],
            'decayed' => (int) $snapshot['points_decayed'],
        ],
        'level' => [
            'current' => $snapshot['current_level'],
            'locked_until' => $snapshot['level_locked_until'],
        ],
        'benefits' => [
            'donation_multiplier' => $level_config['multiplier'],
            'vote_weight_ad' => $level_config['vote_ad'],
            'vote_weight_sponsor' => $level_config['vote_sponsor'],
            'discount_percent' => (int) ($level_config['discount'] ?? 0),
        ],
        'activity' => [
            'last_activity' => $snapshot['last_activity_at'],
            'streak_days' => (int) $snapshot['streak_days'],
        ],
    ], 200);
}

function sharity_points_get_history(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(100, max(1, (int) $request->get_param('per_page')));
    $offset = ($page - 1) * $per_page;

    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, points, type, source_id, metadata, created_at
         FROM {$wpdb->prefix}point_transactions
         WHERE user_id = %d
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        $user_id,
        $per_page,
        $offset
    ), ARRAY_A);

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions WHERE user_id = %d",
        $user_id
    ));

    $items = array_map(static function (array $row): array {
        if (!empty($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);
            if (is_array($decoded)) {
                $row['metadata'] = $decoded;
            }
        }
        return $row;
    }, $items);

    return new WP_REST_Response([
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'transactions' => $items,
    ], 200);
}

function sharity_points_get_pseudo_points(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $manager = new Sharity_Points_Manager();
    $snapshot = $manager->get_points_snapshot_for_pseudo($pseudo_id);

    if (empty($snapshot)) {
        return new WP_REST_Response(['message' => 'Not found'], 404);
    }

    $level_manager = new Sharity_Level_Manager();
    $level_config = $level_manager->get_level_config((string) $snapshot['current_level']);

    return new WP_REST_Response([
        'pseudo_id' => $pseudo_id,
        'points' => [
            'total' => (int) $snapshot['points_total'],
            'lifetime' => (int) $snapshot['points_lifetime'],
            'decayed' => (int) $snapshot['points_decayed'],
        ],
        'level' => [
            'current' => $snapshot['current_level'],
            'locked_until' => $snapshot['level_locked_until'],
        ],
        'benefits' => [
            'donation_multiplier' => $level_config['multiplier'],
            'vote_weight_ad' => $level_config['vote_ad'],
            'vote_weight_sponsor' => $level_config['vote_sponsor'],
            'discount_percent' => (int) ($level_config['discount'] ?? 0),
        ],
        'activity' => [
            'last_activity' => $snapshot['last_activity_at'],
            'streak_days' => (int) $snapshot['streak_days'],
        ],
    ], 200);
}

function sharity_points_get_pseudo_history(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(100, max(1, (int) $request->get_param('per_page')));
    $offset = ($page - 1) * $per_page;

    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT id, points, type, source_id, metadata, created_at
         FROM {$wpdb->prefix}point_transactions
         WHERE pseudo_id = %s
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        $pseudo_id,
        $per_page,
        $offset
    ), ARRAY_A);

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions WHERE pseudo_id = %s",
        $pseudo_id
    ));

    $items = array_map(static function (array $row): array {
        if (!empty($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);
            if (is_array($decoded)) {
                $row['metadata'] = $decoded;
            }
        }
        return $row;
    }, $items);

    return new WP_REST_Response([
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'transactions' => $items,
    ], 200);
}

function sharity_points_pseudo_earn(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $payload = (array) $request->get_json_params();
    $points = (int) ($payload['points'] ?? 0);
    $type = (string) ($payload['type'] ?? '');
    $source_id = isset($payload['source_id']) ? (string) $payload['source_id'] : null;
    $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];
    $dedupe_key = isset($payload['dedupe_key']) ? (string) $payload['dedupe_key'] : null;

    $allowed = ['profile_complete', 'bonus'];
    if ($points <= 0 || $points > 50 || !in_array($type, $allowed, true)) {
        return new WP_REST_Response(['message' => 'Invalid payload'], 400);
    }

    $manager = new Sharity_Points_Manager();
    $result = $manager->award_points_for_pseudo($pseudo_id, $points, $type, $source_id, $metadata, $dedupe_key);

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    return new WP_REST_Response($result, 200);
}

function sharity_points_earn(WP_REST_Request $request): WP_REST_Response
{
    $sig_error = sharity_points_require_signature($request);
    if ($sig_error instanceof WP_REST_Response && !sharity_points_manage_permission()) {
        return $sig_error;
    }

    $payload = (array) $request->get_json_params();
    $user_id = (int) ($payload['user_id'] ?? 0);
    $points = (int) ($payload['points'] ?? 0);
    $type = (string) ($payload['type'] ?? '');
    $source_id = isset($payload['source_id']) ? (string) $payload['source_id'] : null;
    $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];
    $dedupe_key = isset($payload['dedupe_key']) ? (string) $payload['dedupe_key'] : null;

    if ($dedupe_key === null || $dedupe_key === '') {
        return new WP_REST_Response(['message' => 'Missing dedupe_key'], 400);
    }

    $manager = new Sharity_Points_Manager();
    $result = $manager->award_points($user_id, $points, $type, $source_id, $metadata, $dedupe_key);

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    return new WP_REST_Response($result, 200);
}

function sharity_points_admin_adjust(WP_REST_Request $request): WP_REST_Response
{
    $payload = (array) $request->get_json_params();
    $user_id = (int) ($payload['user_id'] ?? 0);
    $pseudo_id = isset($payload['pseudo_id']) ? sanitize_text_field((string) $payload['pseudo_id']) : '';
    $points = (int) ($payload['points'] ?? 0);
    $reason = isset($payload['reason']) ? sanitize_text_field((string) $payload['reason']) : '';
    $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];
    $dedupe_key = isset($payload['dedupe_key']) ? (string) $payload['dedupe_key'] : null;

    if ($points === 0) {
        return new WP_REST_Response(['message' => 'Invalid points'], 400);
    }

    $metadata['source_type'] = 'admin_adjustment';
    $metadata['reason'] = $reason;

    $manager = new Sharity_Points_Manager();

    if ($user_id > 0) {
        $result = $manager->award_points($user_id, $points, 'admin_adjustment', $reason, $metadata, $dedupe_key);
    } elseif ($pseudo_id !== '') {
        $result = $manager->award_points_for_pseudo($pseudo_id, $points, 'admin_adjustment', $reason, $metadata, $dedupe_key);
    } else {
        return new WP_REST_Response(['message' => 'Missing subject'], 400);
    }

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    return new WP_REST_Response($result, 200);
}

function sharity_points_webhook_purchase(WP_REST_Request $request): WP_REST_Response
{
    $sig_error = sharity_points_require_signature($request);
    if ($sig_error instanceof WP_REST_Response && !sharity_points_manage_permission()) {
        return $sig_error;
    }

    $payload = (array) $request->get_json_params();
    $pseudo_id = isset($payload['pseudo_id']) ? sanitize_text_field((string) $payload['pseudo_id']) : '';
    $amount_huf = (float) ($payload['amount_huf'] ?? 0);
    $points = (int) ($payload['points'] ?? 0);
    $source_id = isset($payload['source_id']) ? (string) $payload['source_id'] : null;
    $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];
    $dedupe_key = isset($payload['dedupe_key']) ? (string) $payload['dedupe_key'] : null;

    if ($dedupe_key === null || $dedupe_key === '') {
        return new WP_REST_Response(['message' => 'Missing dedupe_key'], 400);
    }

    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Missing pseudo_id'], 400);
    }

    if ($points === 0) {
        $points = (int) round($amount_huf);
    }

    if ($points <= 0) {
        return new WP_REST_Response(['message' => 'Invalid points'], 400);
    }

    $metadata['source_type'] = $metadata['source_type'] ?? 'purchase_webhook';
    $status = sanitize_key((string) ($metadata['status'] ?? 'approved'));
    if ($status !== 'approved') {
        return new WP_REST_Response(['message' => 'pending'], 202);
    }

    $manager = new Sharity_Points_Manager();
    $result = $manager->award_points_for_pseudo($pseudo_id, $points, 'purchase', $source_id, $metadata, $dedupe_key);

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    if ($source_id !== null && function_exists('sharity_points_handle_referral')) {
        $ledger_id = is_numeric($source_id) ? (int) $source_id : 0;
        sharity_points_handle_referral($pseudo_id, $ledger_id, $metadata);
    }

    return new WP_REST_Response($result, 200);
}

function sharity_points_vacation_status(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $manager = new Sharity_Vacation_Manager();
    return new WP_REST_Response($manager->get_vacation_status($user_id), 200);
}

function sharity_points_vacation_start(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $days = (int) ($request->get_json_params()['days'] ?? 14);
    $manager = new Sharity_Vacation_Manager();
    $result = $manager->activate_vacation($user_id, $days);
    $status = ($result['success'] ?? false) ? 200 : 400;
    return new WP_REST_Response($result, $status);
}

function sharity_points_vacation_end(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $manager = new Sharity_Vacation_Manager();
    return new WP_REST_Response($manager->deactivate_vacation($user_id), 200);
}

function sharity_points_pseudo_vacation_status(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    $manager = new Sharity_Vacation_Manager();
    return new WP_REST_Response($manager->get_vacation_status_for_pseudo($pseudo_id), 200);
}

function sharity_points_pseudo_vacation_start(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    $days = (int) ($request->get_json_params()['days'] ?? 14);
    $manager = new Sharity_Vacation_Manager();
    $result = $manager->activate_vacation_for_pseudo($pseudo_id, $days);
    $status = ($result['success'] ?? false) ? 200 : 400;
    return new WP_REST_Response($result, $status);
}

function sharity_points_pseudo_vacation_end(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    $manager = new Sharity_Vacation_Manager();
    return new WP_REST_Response($manager->deactivate_vacation_for_pseudo($pseudo_id), 200);
}

function sharity_points_get_last_ngo(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $slug = (string) get_user_meta($user_id, 'impactshop_last_ngo_slug', true);
    return new WP_REST_Response(['slug' => $slug], 200);
}

function sharity_points_set_last_ngo(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    $payload = (array) $request->get_json_params();
    $slug = isset($payload['slug']) ? sanitize_text_field((string) $payload['slug']) : '';
    if ($slug === '') {
        delete_user_meta($user_id, 'impactshop_last_ngo_slug');
        return new WP_REST_Response(['slug' => ''], 200);
    }
    update_user_meta($user_id, 'impactshop_last_ngo_slug', $slug);
    return new WP_REST_Response(['slug' => $slug], 200);
}

function sharity_points_get_pseudo_last_ngo(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    if (function_exists('impactshop_identity_profile_last_ngo')) {
        $slug = (string) impactshop_identity_profile_last_ngo($pseudo_id);
        return new WP_REST_Response(['slug' => $slug], 200);
    }

    return new WP_REST_Response(['slug' => ''], 200);
}

function sharity_points_set_pseudo_last_ngo(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $payload = (array) $request->get_json_params();
    $slug = isset($payload['slug']) ? sanitize_text_field((string) $payload['slug']) : '';

    if (function_exists('impactshop_identity_profile_store_last_ngo')) {
        impactshop_identity_profile_store_last_ngo($pseudo_id, $slug);
    }

    return new WP_REST_Response(['slug' => $slug], 200);
}

function sharity_points_get_user_referral(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $manager = new Sharity_Referral_Manager();
    $data = $manager->get_or_create_for_user($user_id);
    if (empty($data)) {
        return new WP_REST_Response(['message' => 'Not found'], 404);
    }

    $code = (string) $data['referral_code'];

    return new WP_REST_Response([
        'referral_code' => $code,
        'url' => add_query_arg('ref', $code, home_url('/')),
        'status' => $data['status'],
        'expires_at' => $data['expires_at'],
    ], 200);
}

function sharity_points_get_pseudo_referral(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $manager = new Sharity_Referral_Manager();
    $data = $manager->get_or_create_for_pseudo($pseudo_id);
    if (empty($data)) {
        return new WP_REST_Response(['message' => 'Not found'], 404);
    }

    $code = (string) $data['referral_code'];

    return new WP_REST_Response([
        'referral_code' => $code,
        'url' => add_query_arg('ref', $code, home_url('/')),
        'status' => $data['status'],
        'expires_at' => $data['expires_at'],
    ], 200);
}

function sharity_points_user_feedback(WP_REST_Request $request): WP_REST_Response
{
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $payload = (array) $request->get_json_params();
    $rating_raw = isset($payload['rating']) ? (int) $payload['rating'] : 0;
    $rating = ($rating_raw >= 1 && $rating_raw <= 5) ? $rating_raw : null;
    $comment = isset($payload['comment']) ? sanitize_text_field((string) $payload['comment']) : '';
    $channel = isset($payload['channel']) ? sanitize_key((string) $payload['channel']) : '';

    $now = current_time('timestamp');
    $month_key = date_i18n('Y-m', $now);
    $month_start = date_i18n('Y-m-01 00:00:00', $now);
    $month_end = date_i18n('Y-m-t 23:59:59', $now);

    global $wpdb;
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions
         WHERE user_id = %d AND type = %s AND created_at BETWEEN %s AND %s",
        $user_id,
        'feedback',
        $month_start,
        $month_end
    ));

    $limit = (int) apply_filters('sharity_feedback_monthly_limit', 2);
    if ($count >= $limit) {
        return new WP_REST_Response([
            'message' => 'Monthly feedback limit reached',
            'remaining' => 0,
            'limit' => $limit,
        ], 429);
    }

    $metadata = [
        'source_type' => 'feedback',
        'rating' => $rating,
        'comment' => $comment,
        'channel' => $channel,
        'submitted_at' => current_time('mysql'),
    ];

    $manager = new Sharity_Points_Manager();
    $dedupe_key = sprintf('feedback:user:%d:%s:%d', $user_id, $month_key, $count + 1);
    $result = $manager->award_points($user_id, 20, 'feedback', $month_key, $metadata, $dedupe_key);

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    $result['remaining'] = max(0, $limit - ($count + 1));
    $result['limit'] = $limit;

    return new WP_REST_Response($result, 200);
}

function sharity_points_pseudo_feedback(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = sharity_points_get_pseudo_from_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $payload = (array) $request->get_json_params();
    $rating_raw = isset($payload['rating']) ? (int) $payload['rating'] : 0;
    $rating = ($rating_raw >= 1 && $rating_raw <= 5) ? $rating_raw : null;
    $comment = isset($payload['comment']) ? sanitize_text_field((string) $payload['comment']) : '';
    $channel = isset($payload['channel']) ? sanitize_key((string) $payload['channel']) : '';

    $now = current_time('timestamp');
    $month_key = date_i18n('Y-m', $now);
    $month_start = date_i18n('Y-m-01 00:00:00', $now);
    $month_end = date_i18n('Y-m-t 23:59:59', $now);

    global $wpdb;
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}point_transactions
         WHERE pseudo_id = %s AND type = %s AND created_at BETWEEN %s AND %s",
        $pseudo_id,
        'feedback',
        $month_start,
        $month_end
    ));

    $limit = (int) apply_filters('sharity_feedback_monthly_limit', 2);
    if ($count >= $limit) {
        return new WP_REST_Response([
            'message' => 'Monthly feedback limit reached',
            'remaining' => 0,
            'limit' => $limit,
        ], 429);
    }

    $metadata = [
        'source_type' => 'feedback',
        'rating' => $rating,
        'comment' => $comment,
        'channel' => $channel,
        'submitted_at' => current_time('mysql'),
    ];

    $manager = new Sharity_Points_Manager();
    $dedupe_key = sprintf('feedback:pseudo:%s:%s:%d', $pseudo_id, $month_key, $count + 1);
    $result = $manager->award_points_for_pseudo($pseudo_id, 20, 'feedback', $month_key, $metadata, $dedupe_key);

    if (!($result['success'] ?? false)) {
        return new WP_REST_Response(['message' => $result['error'] ?? 'Failed'], 400);
    }

    $result['remaining'] = max(0, $limit - ($count + 1));
    $result['limit'] = $limit;

    return new WP_REST_Response($result, 200);
}
