<?php
/**
 * Plugin Name: ImpactShop Identity PIN (stub)
 * Description: REST stub for PIN issue/verify endpoints with rate limit and audit hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

impactshop_pin_load_env('/home/sharityh/.impact-secrets/env.d/sms.env');
impactshop_pin_load_env('/home/sharityh/.impact-secrets/env.d/pin.env');

$pin_delivery_stub = getenv('PIN_DELIVERY_STUB');
$pin_delivery_stub = $pin_delivery_stub === false
    ? true
    : in_array(strtolower($pin_delivery_stub), ['1', 'true', 'yes'], true);

define('IMPACTSHOP_PIN_TTL_SEC', (int)(getenv('PIN_TTL_SEC') ?: 900));
define('IMPACTSHOP_PIN_LOCKOUT_SEC', (int)(getenv('PIN_LOCKOUT_SEC') ?: 900));
define('IMPACTSHOP_PIN_IP_HOURLY_LIMIT', (int)(getenv('PIN_IP_HOURLY_LIMIT') ?: 5));
define('IMPACTSHOP_PIN_PSEUDO_DAILY_LIMIT', (int)(getenv('PIN_PSEUDO_DAILY_LIMIT') ?: 10));
define('IMPACTSHOP_PIN_REGENERATE_DAILY_LIMIT', (int)(getenv('PIN_REGENERATE_DAILY_LIMIT') ?: 3));
define('IMPACTSHOP_PIN_COOKIE_DAYS', (int)(getenv('PIN_COOKIE_DAYS') ?: 365));
define('IMPACTSHOP_PIN_DELIVERY_STUB', $pin_delivery_stub);

/**
 * Register PIN REST routes.
 */
add_action('rest_api_init', function () {
    register_rest_route(
        'impact/v1',
        '/identity/pin/issue',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_pin_issue',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/pin/verify',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_pin_verify',
            'permission_callback' => '__return_true',
        ]
    );
});

/**
 * Warn admins if persistent object cache is missing.
 */
add_action('admin_notices', function () {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (wp_using_ext_object_cache()) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_pin_tokens';
    $active = (int)$wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE used_at IS NULL AND expires_at > NOW()"
    );
    if ($active <= 0) {
        return;
    }

    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>ImpactShop PIN:</strong> Nincs persistent object cache (Memcached/Redis). ';
    echo 'Nagy forgalomnál a rate limit lassú lehet. Aktív PIN-ek: ' . esc_html((string)$active);
    echo '</p></div>';
});

/**
 * Issue a PIN for a pseudo ID.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_pin_issue(WP_REST_Request $request): WP_REST_Response
{
    if (defined('IMPACTSHOP_PIN_DISABLED') && IMPACTSHOP_PIN_DISABLED) {
        return impactshop_pin_error('pin_disabled', 403, 'PIN disabled');
    }

    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? (string)$params['pseudo_id'] : '';
    $context = isset($params['context']) ? (string)$params['context'] : 'impactshop';
    $delivery = isset($params['delivery']) && is_array($params['delivery']) ? $params['delivery'] : [];
    $delivery_channel = isset($delivery['channel']) ? (string)$delivery['channel'] : '';
    $delivery_target = isset($delivery['target']) ? (string)$delivery['target'] : '';
    $recovery_code = isset($params['recovery_code']) ? (string)$params['recovery_code'] : '';

    if (!impactshop_pin_valid_pseudo($pseudo_id)) {
        return impactshop_pin_error('invalid_request', 400, 'Invalid pseudo_id');
    }

    if (!in_array($context, ['impactshop', 'ngo-card', 'social-ticker'], true)) {
        return impactshop_pin_error('invalid_request', 400, 'Invalid context');
    }

    if ($delivery_channel !== '' && !in_array($delivery_channel, ['email', 'sms', 'qr'], true)) {
        return impactshop_pin_error('invalid_request', 400, 'Invalid delivery channel');
    }

    if (function_exists('impactshop_identity_profile_get_recovery_code')) {
        $stored_recovery = impactshop_identity_profile_get_recovery_code($pseudo_id);
        if ($stored_recovery === null) {
            return impactshop_pin_error('recovery_required', 403, 'Recovery code required');
        }
        if ($recovery_code === '' || $recovery_code !== $stored_recovery) {
            return impactshop_pin_error('recovery_invalid', 403, 'Recovery code invalid');
        }
    }

    $ip = impactshop_pin_client_ip();
    $pseudo_hash = impactshop_pin_hash($pseudo_id);
    $ip_hash = impactshop_pin_hash($ip, 'impactshop_pin_ip');

    $retry_after = impactshop_pin_rate_limit($ip_hash, $pseudo_hash, true);
    if ($retry_after !== null) {
        impactshop_pin_audit('identity_pin_issue', $pseudo_hash, $ip_hash, 'rate_limited', 0);
        return impactshop_pin_rate_limited($retry_after);
    }

    $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = gmdate('Y-m-d H:i:s', time() + IMPACTSHOP_PIN_TTL_SEC);
    $now = current_time('mysql', 1);

    $saved = impactshop_pin_store($pseudo_hash, $pin, $ip_hash, $expires_at, $now);
    if (!$saved) {
        impactshop_pin_audit('identity_pin_issue', $pseudo_hash, $ip_hash, 'error', 0);
        return impactshop_pin_error('server_error', 500, 'PIN store failed');
    }

    impactshop_pin_audit('identity_pin_issue', $pseudo_hash, $ip_hash, 'ok', 0);
    $delivery_info = impactshop_pin_deliver($pseudo_id, $pin, $context, $delivery_channel, $delivery_target);
    if ($delivery_info['status'] === 'error') {
        return impactshop_pin_error('delivery_failed', 500, 'PIN delivery failed');
    }

    $data = [
        'status'      => 'ok',
        'pin_ttl_sec' => IMPACTSHOP_PIN_TTL_SEC,
        'rate_limit'  => [
            'ip_hour'       => IMPACTSHOP_PIN_IP_HOURLY_LIMIT,
            'pseudo_day'    => IMPACTSHOP_PIN_PSEUDO_DAILY_LIMIT,
            'regenerate_day'=> IMPACTSHOP_PIN_REGENERATE_DAILY_LIMIT,
        ],
        'delivery'    => $delivery_info,
    ];

    $response = new WP_REST_Response($data, 200);
    $response->header('X-Impact-Pin-Issued', '1');
    return $response;
}

/**
 * Verify a PIN and restore the pseudo ID cookie.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_pin_verify(WP_REST_Request $request): WP_REST_Response
{
    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? (string)$params['pseudo_id'] : '';
    $pin = isset($params['pin']) ? (string)$params['pin'] : '';

    if (!impactshop_pin_valid_pseudo($pseudo_id) || !impactshop_pin_valid_pin($pin)) {
        return impactshop_pin_error('invalid_request', 400, 'Invalid payload');
    }

    $ip = impactshop_pin_client_ip();
    $pseudo_hash = impactshop_pin_hash($pseudo_id);
    $ip_hash = impactshop_pin_hash($ip, 'impactshop_pin_ip');

    $retry_after = impactshop_pin_rate_limit($ip_hash, $pseudo_hash, false);
    if ($retry_after !== null) {
        impactshop_pin_audit('identity_pin_verify', $pseudo_hash, $ip_hash, 'rate_limited', 0);
        return impactshop_pin_rate_limited($retry_after);
    }

    $record = impactshop_pin_fetch($pseudo_hash);
    if (!$record || empty($record['pin_hash'])) {
        return impactshop_pin_error('pseudo_not_found', 404, 'PIN not found');
    }

    if (!empty($record['locked_until']) && strtotime($record['locked_until']) > time()) {
        impactshop_pin_audit('identity_pin_verify', $pseudo_hash, $ip_hash, 'locked', (int)$record['attempts']);
        return impactshop_pin_error('pin_locked', 403, 'PIN locked');
    }

    if (!empty($record['used_at'])) {
        return impactshop_pin_error('pin_used', 409, 'PIN already used');
    }

    if (!empty($record['expires_at']) && strtotime($record['expires_at']) < time()) {
        return impactshop_pin_error('pin_expired', 409, 'PIN expired');
    }

    $is_valid = wp_check_password($pin, $record['pin_hash']);
    usleep(random_int(100000, 150000));

    if (!$is_valid) {
        $attempts = isset($record['attempts']) ? (int)$record['attempts'] + 1 : 1;
        $locked_until = null;
        if ($attempts >= 3) {
            $locked_until = gmdate('Y-m-d H:i:s', time() + IMPACTSHOP_PIN_LOCKOUT_SEC);
        }
        impactshop_pin_update_attempts($pseudo_hash, $attempts, $locked_until);
        impactshop_pin_audit('identity_pin_verify', $pseudo_hash, $ip_hash, 'invalid', $attempts);
        return impactshop_pin_error('pin_invalid', 401, 'PIN invalid');
    }

    impactshop_pin_mark_used($pseudo_hash);
    impactshop_pin_audit('identity_pin_verify', $pseudo_hash, $ip_hash, 'ok', (int)$record['attempts']);
    impactshop_pin_set_cookie($pseudo_id);

    $data = [
        'status'    => 'ok',
        'token_set' => true,
        'cookie'    => [
            'name'     => 'impactshop_pseudo_id',
            'ttl_days' => IMPACTSHOP_PIN_COOKIE_DAYS,
        ],
    ];

    return new WP_REST_Response($data, 200);
}

/**
 * Rate limit check for PIN issue/verify.
 *
 * @param string $ip_hash Hashed IP.
 * @param string $pseudo_hash Hashed pseudo ID.
 * @param bool $is_issue True for issue flow, false for verify flow.
 * @return int|null Retry-after seconds or null.
 */
function impactshop_pin_rate_limit(string $ip_hash, string $pseudo_hash, bool $is_issue): ?int
{
    $ip_key = 'impact_pin_ip_' . $ip_hash;
    $pseudo_key = 'impact_pin_pseudo_' . $pseudo_hash;
    $regen_key = 'impact_pin_regen_' . $pseudo_hash;
    $combo_key = 'impact_pin_combo_' . substr($ip_hash, 0, 16) . '_' . substr($pseudo_hash, 0, 16);

    if (impactshop_pin_limit_hit($ip_key, IMPACTSHOP_PIN_IP_HOURLY_LIMIT, HOUR_IN_SECONDS)) {
        return HOUR_IN_SECONDS;
    }

    if (impactshop_pin_limit_hit($pseudo_key, IMPACTSHOP_PIN_PSEUDO_DAILY_LIMIT, DAY_IN_SECONDS)) {
        return DAY_IN_SECONDS;
    }

    if ($is_issue && impactshop_pin_limit_hit($regen_key, IMPACTSHOP_PIN_REGENERATE_DAILY_LIMIT, DAY_IN_SECONDS)) {
        return DAY_IN_SECONDS;
    }

    if ($is_issue && impactshop_pin_limit_hit($combo_key, 5, HOUR_IN_SECONDS)) {
        return HOUR_IN_SECONDS;
    }

    return null;
}

/**
 * Load environment variables from a simple key=value file.
 *
 * @param string $path File path.
 * @return void
 */
function impactshop_pin_load_env(string $path): void
{
    if ($path === '' || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && $value !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Store PIN in database (insert or update).
 *
 * @param string $pseudo_hash Hashed pseudo ID.
 * @param string $pin Plain PIN.
 * @param string $ip_hash Hashed IP.
 * @param string $expires_at Expiry datetime (UTC).
 * @param string $now Current time (UTC).
 * @return bool
 */
function impactshop_pin_store(string $pseudo_hash, string $pin, string $ip_hash, string $expires_at, string $now): bool
{
    global $wpdb;

    $table = $wpdb->prefix . 'impact_pin_tokens';
    $pin_hash = wp_hash_password($pin);

    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT id, used_at FROM $table WHERE pseudo_hash = %s", $pseudo_hash),
        ARRAY_A
    );

    if ($existing) {
        if (!empty($existing['used_at'])) {
            do_action('impactshop_identity_pin_reissue_after_use', [
                'pseudo_hash' => $pseudo_hash,
                'old_id'      => $existing['id'],
                'used_at'     => $existing['used_at'],
                'ip_hash'     => $ip_hash,
                'ts'          => gmdate('c'),
            ]);
        }
        $updated = $wpdb->update(
            $table,
            [
                'pin_hash'      => $pin_hash,
                'issued_ip_hash'=> $ip_hash,
                'expires_at'    => $expires_at,
                'attempts'      => 0,
                'locked_until'  => null,
                'used_at'       => null,
                'updated_at'    => $now,
            ],
            ['id' => (int)$existing['id']],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'pseudo_hash'   => $pseudo_hash,
            'pin_hash'      => $pin_hash,
            'issued_ip_hash'=> $ip_hash,
            'expires_at'    => $expires_at,
            'attempts'      => 0,
            'locked_until'  => null,
            'used_at'       => null,
            'created_at'    => $now,
        ],
        ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    return $inserted !== false;
}

/**
 * Fetch PIN record by pseudo hash.
 *
 * @param string $pseudo_hash Hashed pseudo ID.
 * @return array|null
 */
function impactshop_pin_fetch(string $pseudo_hash): ?array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_pin_tokens';
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE pseudo_hash = %s", $pseudo_hash),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Update attempts and optional lockout time.
 *
 * @param string $pseudo_hash Hashed pseudo ID.
 * @param int $attempts Attempt count.
 * @param string|null $locked_until Lockout time (UTC) or null.
 * @return void
 */
function impactshop_pin_update_attempts(string $pseudo_hash, int $attempts, ?string $locked_until): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_pin_tokens';
    $wpdb->update(
        $table,
        [
            'attempts'     => $attempts,
            'locked_until' => $locked_until,
            'updated_at'   => current_time('mysql', 1),
        ],
        ['pseudo_hash' => $pseudo_hash],
        ['%d', '%s', '%s'],
        ['%s']
    );
}

/**
 * Mark PIN as used.
 *
 * @param string $pseudo_hash Hashed pseudo ID.
 * @return void
 */
function impactshop_pin_mark_used(string $pseudo_hash): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_pin_tokens';
    $wpdb->update(
        $table,
        [
            'used_at'    => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
        ],
        ['pseudo_hash' => $pseudo_hash],
        ['%s', '%s'],
        ['%s']
    );
}

/**
 * Increment a transient counter and check limit.
 *
 * @param string $key Transient key.
 * @param int $limit Max count within window.
 * @param int $window_sec Window size in seconds.
 * @return bool True if limit exceeded.
 */
function impactshop_pin_limit_hit(string $key, int $limit, int $window_sec): bool
{
    $data = get_transient($key);
    if (!is_array($data)) {
        set_transient($key, ['count' => 1], $window_sec);
        return false;
    }

    $count = isset($data['count']) ? (int)$data['count'] + 1 : 1;
    if ($count > $limit) {
        return true;
    }

    $data['count'] = $count;
    set_transient($key, $data, $window_sec);
    return false;
}

/**
 * Build a rate-limited response.
 *
 * @param int $retry_after Retry-after seconds.
 * @return WP_REST_Response
 */
function impactshop_pin_rate_limited(int $retry_after): WP_REST_Response
{
    $data = [
        'error'       => 'rate_limited',
        'retry_after' => $retry_after,
    ];

    $response = new WP_REST_Response($data, 429);
    $response->header('Retry-After', (string)$retry_after);
    return $response;
}

/**
 * Build a standard error response.
 *
 * @param string $code Error code.
 * @param int $status HTTP status.
 * @param string $message Error message.
 * @return WP_REST_Response
 */
function impactshop_pin_error(string $code, int $status, string $message): WP_REST_Response
{
    $data = [
        'code'    => $code,
        'message' => $message,
        'data'    => [
            'status' => $status,
        ],
    ];

    return new WP_REST_Response($data, $status);
}

/**
 * Validate pseudo ID format.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return bool
 */
function impactshop_pin_valid_pseudo(string $pseudo_id): bool
{
    return (bool)preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id);
}

/**
 * Validate PIN format (6 digits).
 *
 * @param string $pin PIN.
 * @return bool
 */
function impactshop_pin_valid_pin(string $pin): bool
{
    return (bool)preg_match('/^[0-9]{6}$/', $pin);
}

/**
 * Hash helper for PIN system values.
 *
 * @param string $value Value to hash.
 * @param string $salt_id Salt ID for wp_salt.
 * @return string
 */
function impactshop_pin_hash(string $value, string $salt_id = 'impactshop_pin'): string
{
    return hash_hmac('sha256', $value, wp_salt($salt_id));
}

/**
 * Build transient key for PIN storage.
 *
 * @param string $pseudo_hash Hashed pseudo ID.
 * @return string
 */
function impactshop_pin_transient_key(string $pseudo_hash): string
{
    return 'impact_pin_' . $pseudo_hash;
}

/**
 * Resolve client IP with optional trusted proxy handling.
 *
 * @return string
 */
function impactshop_pin_client_ip(): string
{
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    $trusted = array_filter(array_map('trim', explode(',', (string)getenv('TRUSTED_PROXY_IPS'))));

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $remote_addr !== '' && in_array($remote_addr, $trusted, true)) {
        $ips = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = reset($ips);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $remote_addr !== '' ? $remote_addr : '0.0.0.0';
}

/**
 * Send PIN via email.
 *
 * @param string $email Recipient.
 * @param string $pin PIN.
 * @param int $ttl_sec TTL seconds.
 * @return bool
 */
function impactshop_pin_send_email(string $email, string $pin, int $ttl_sec): bool
{
    $subject = 'Impact Shop PIN';
    $minutes = (int)ceil($ttl_sec / 60);
    $body = "A PIN kodod: {$pin}\nErvenyes: {$minutes} perc.\n";

    return wp_mail($email, $subject, $body);
}

/**
 * Set pseudo ID cookie on response.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return void
 */
function impactshop_pin_set_cookie(string $pseudo_id): void
{
    $expires = time() + (IMPACTSHOP_PIN_COOKIE_DAYS * DAY_IN_SECONDS);
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    $secure = is_ssl();
    $path = defined('COOKIEPATH') ? COOKIEPATH : '/';

    if (PHP_VERSION_ID >= 70300) {
        setcookie('impactshop_pseudo_id', $pseudo_id, [
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('impactshop_pseudo_id', $pseudo_id, $expires, $path . '; samesite=Lax', $domain, $secure, false);
    }
}

/**
 * Deliver PIN via selected channel.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $pin PIN.
 * @param string $context Context tag.
 * @param string $channel Channel (email/sms/qr).
 * @param string $target Delivery target.
 * @return array
 */
function impactshop_pin_deliver(
    string $pseudo_id,
    string $pin,
    string $context,
    string $channel,
    string $target
): array {
    $test_mode = getenv('PIN_TEST_MODE');
    if ($test_mode && in_array(strtolower($test_mode), ['1', 'true', 'yes'], true)) {
        return [
            'channel'    => $channel === '' ? 'none' : $channel,
            'status'     => 'test_bypassed',
            'pin'        => $pin,
            'qr_payload' => null,
        ];
    }

    $status = 'skipped';
    $qr_payload = null;
    $target_hash = $target !== '' ? impactshop_pin_hash($target, 'impactshop_pin_target') : null;
    $pin_hash = impactshop_pin_hash($pin, 'impactshop_pin_delivery');

    if ($channel === 'email') {
        $email = sanitize_email($target);
        if ($email === '' || !is_email($email)) {
            return [
                'channel' => 'email',
                'status'  => 'error',
            ];
        }

        $status = impactshop_pin_send_email($email, $pin, IMPACTSHOP_PIN_TTL_SEC) ? 'sent' : 'error';
    } elseif ($channel === 'sms') {
        $sms_payload = [
            'pseudo_id' => $pseudo_id,
            'pin'       => $pin,
            'context'   => $context,
            'target'    => $target,
        ];
        $sms_result = apply_filters('impactshop_identity_pin_sms', null, $sms_payload);
        $status = (is_array($sms_result) && ($sms_result['status'] ?? '') === 'sent') ? 'sent' : 'queued';
    } elseif ($channel === 'qr') {
        $qr_payload = apply_filters(
            'impactshop_identity_pin_qr_payload',
            null,
            [
                'pseudo_id' => $pseudo_id,
                'pin'       => $pin,
                'context'   => $context,
            ]
        );
        if ($qr_payload !== null && !filter_var($qr_payload, FILTER_VALIDATE_URL)) {
            do_action('impactshop_pin_qr_invalid', [
                'payload'   => $qr_payload,
                'pseudo_id' => $pseudo_id,
                'ts'        => gmdate('c'),
            ]);
            $qr_payload = null;
        }
        $status = $qr_payload ? 'ready' : 'queued';
    }

    $payload = [
        'pseudo_id'   => $pseudo_id,
        'pin_hash'    => $pin_hash,
        'context'     => $context,
        'channel'     => $channel === '' ? 'none' : $channel,
        'status'      => $status,
        'target_hash' => $target_hash,
        'ts'          => gmdate('c'),
    ];

    do_action('impactshop_identity_pin_deliver', $payload);

    if (IMPACTSHOP_PIN_DELIVERY_STUB) {
        $upload = wp_upload_dir();
        if (!empty($upload['basedir'])) {
            $path = trailingslashit($upload['basedir']) . 'impactshop-pin-delivery.log';
            $log_entry = [
                '@timestamp' => gmdate('c'),
                'level'      => 'info',
                'event'      => 'pin.delivery',
            ] + $payload;
            $line = wp_json_encode($log_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            @file_put_contents($path, $line, FILE_APPEND);
        }
    }

    return [
        'channel'    => $payload['channel'],
        'status'     => $status,
        'qr_payload' => $qr_payload,
    ];
}

/**
 * Write PIN audit entry.
 *
 * @param string $action Action name.
 * @param string $pseudo_hash Hashed pseudo ID.
 * @param string $ip_hash Hashed IP.
 * @param string $status Status string.
 * @param int $attempts Attempt count.
 * @return void
 */
function impactshop_pin_audit(string $action, string $pseudo_hash, string $ip_hash, string $status, int $attempts): void
{
    $payload = [
        'ts'          => gmdate('c'),
        'action'      => $action,
        'pseudo_hash' => $pseudo_hash,
        'ip_hash'     => $ip_hash,
        'status'      => $status,
        'attempts'    => $attempts,
    ];

    do_action('impactshop_identity_pin_audit', $payload);

    $upload = wp_upload_dir();
    if (!empty($upload['basedir'])) {
        $path = trailingslashit($upload['basedir']) . 'impactshop-pin-audit.log';
        $log_entry = [
            '@timestamp' => gmdate('c'),
            'level'      => 'info',
            'event'      => 'pin.audit',
        ] + $payload;
        $line = wp_json_encode($log_entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND);
    }
}
