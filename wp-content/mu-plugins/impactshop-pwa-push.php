<?php
/**
 * Plugin Name: ImpactShop PWA Push
 * Description: Web Push for profile messages (targeted only).
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_PUSH_VERSION = '2026.02.17.1';
const IMPACTSHOP_PUSH_CRON_HOOK = 'impactshop_pwa_push_cron';

add_action('init', 'impactshop_pwa_push_maybe_migrate', 1);
add_action('rest_api_init', 'impactshop_pwa_push_register_routes');
add_filter('cron_schedules', 'impactshop_pwa_push_cron_schedules');
add_action('init', 'impactshop_pwa_push_schedule_cron');
add_action(IMPACTSHOP_PUSH_CRON_HOOK, 'impactshop_pwa_push_process_queue');

function impactshop_pwa_push_is_configured(): bool
{
    return defined('IMPACT_PUSH_VAPID_PUBLIC')
        && defined('IMPACT_PUSH_VAPID_PRIVATE')
        && defined('IMPACT_PUSH_SUBJECT')
        && IMPACT_PUSH_VAPID_PUBLIC !== ''
        && IMPACT_PUSH_VAPID_PRIVATE !== ''
        && IMPACT_PUSH_SUBJECT !== '';
}

function impactshop_pwa_push_autoload(): bool
{
    if (class_exists('Minishlink\\WebPush\\WebPush')) {
        return true;
    }

    $candidates = [
        WP_CONTENT_DIR . '/mu-plugins/vendor/autoload.php',
        dirname(__DIR__, 2) . '/vendor/autoload.php',
    ];
    foreach ($candidates as $autoload) {
        if (is_readable($autoload)) {
            require_once $autoload;
            break;
        }
    }

    return class_exists('Minishlink\\WebPush\\WebPush');
}

function impactshop_pwa_push_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . $name;
}

function impactshop_pwa_push_get_pseudo_id(): string
{
    if (function_exists('impactshop_vote_jysk_get_pseudo_id')) {
        return impactshop_vote_jysk_get_pseudo_id();
    }
    if (empty($_COOKIE['impactshop_pseudo_id'])) {
        return '';
    }
    $pseudo = strtolower(sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])));
    if (!preg_match('/^[a-z0-9]{10,12}$/', $pseudo)) {
        return '';
    }
    return $pseudo;
}

function impactshop_pwa_push_check_origin(): bool
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if (!$host) {
        return true;
    }
    foreach ([$origin, $referer] as $value) {
        if ($value === '') {
            continue;
        }
        $value_host = wp_parse_url($value, PHP_URL_HOST);
        if ($value_host && $value_host === $host) {
            return true;
        }
    }
    return false;
}

function impactshop_pwa_push_require_nonce(WP_REST_Request $request)
{
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('INVALID_NONCE', 'Hibas nonce.', ['status' => 403]);
    }
    if (!impactshop_pwa_push_check_origin()) {
        return new WP_Error('INVALID_ORIGIN', 'Hibas origin.', ['status' => 403]);
    }
    return true;
}

function impactshop_pwa_push_cron_schedules(array $schedules): array
{
    if (!isset($schedules['impactshop_5min'])) {
        $schedules['impactshop_5min'] = [
            'interval' => 300,
            'display' => 'ImpactShop 5 perc',
        ];
    }
    return $schedules;
}

function impactshop_pwa_push_schedule_cron(): void
{
    if (!impactshop_pwa_push_is_configured()) {
        return;
    }
    if (!wp_next_scheduled(IMPACTSHOP_PUSH_CRON_HOOK)) {
        wp_schedule_event(time() + 60, 'impactshop_5min', IMPACTSHOP_PUSH_CRON_HOOK);
    }
}

function impactshop_pwa_push_maybe_migrate(): void
{
    if (get_option('impactshop_pwa_push_migrated') === IMPACTSHOP_PUSH_VERSION) {
        return;
    }

    global $wpdb;
    $subscriptions = impactshop_pwa_push_table('impact_push_subscriptions');
    $deliveries = impactshop_pwa_push_table('impact_push_deliveries');

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $sql_subs = "CREATE TABLE {$subscriptions} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(20) NOT NULL,
        endpoint TEXT NOT NULL,
        endpoint_hash CHAR(64) NOT NULL,
        public_key VARCHAR(255) NOT NULL,
        auth_token VARCHAR(255) NOT NULL,
        content_encoding VARCHAR(20) NOT NULL DEFAULT 'aes128gcm',
        user_agent VARCHAR(255) DEFAULT '',
        platform VARCHAR(50) DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY endpoint_hash (endpoint_hash),
        KEY pseudo_id (pseudo_id),
        KEY is_active (is_active)
    ) {$charset};";

    $sql_deliveries = "CREATE TABLE {$deliveries} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id BIGINT UNSIGNED NOT NULL,
        subscription_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        http_code INT DEFAULT NULL,
        error_message VARCHAR(255) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_message_subscription (message_id, subscription_id),
        KEY message_id (message_id),
        KEY subscription_id (subscription_id)
    ) {$charset};";

    dbDelta($sql_subs);
    dbDelta($sql_deliveries);

    update_option('impactshop_pwa_push_migrated', IMPACTSHOP_PUSH_VERSION);
}

function impactshop_pwa_push_register_routes(): void
{
    register_rest_route('impact/v1', '/push/public-key', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_pwa_push_public_key',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/push/subscribe', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_pwa_push_subscribe',
        'permission_callback' => 'impactshop_pwa_push_require_nonce',
    ]);

    register_rest_route('impact/v1', '/push/unsubscribe', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_pwa_push_unsubscribe',
        'permission_callback' => 'impactshop_pwa_push_require_nonce',
    ]);
}

function impactshop_pwa_push_public_key(): WP_REST_Response
{
    if (!impactshop_pwa_push_is_configured()) {
        return new WP_REST_Response(['enabled' => false], 200);
    }
    return new WP_REST_Response([
        'enabled' => true,
        'publicKey' => IMPACT_PUSH_VAPID_PUBLIC,
    ], 200);
}

function impactshop_pwa_push_extract_subscription(array $params): array
{
    if (isset($params['subscription']) && is_array($params['subscription'])) {
        $params = $params['subscription'];
    }
    $endpoint = isset($params['endpoint']) ? (string) $params['endpoint'] : '';
    $keys = isset($params['keys']) && is_array($params['keys']) ? $params['keys'] : [];
    $public_key = isset($keys['p256dh']) ? (string) $keys['p256dh'] : '';
    $auth_token = isset($keys['auth']) ? (string) $keys['auth'] : '';
    $content_encoding = isset($params['contentEncoding']) ? (string) $params['contentEncoding'] : '';
    return [
        'endpoint' => $endpoint,
        'public_key' => $public_key,
        'auth_token' => $auth_token,
        'content_encoding' => $content_encoding !== '' ? $content_encoding : 'aes128gcm',
    ];
}

function impactshop_pwa_push_subscribe(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_pwa_push_is_configured()) {
        return new WP_REST_Response(['message' => 'push_disabled'], 503);
    }

    $pseudo_id = impactshop_pwa_push_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'missing_identity'], 403);
    }

    $subscription = impactshop_pwa_push_extract_subscription((array) $request->get_json_params());
    if ($subscription['endpoint'] === '' || $subscription['public_key'] === '' || $subscription['auth_token'] === '') {
        return new WP_REST_Response(['message' => 'invalid_subscription'], 400);
    }

    global $wpdb;
    $table = impactshop_pwa_push_table('impact_push_subscriptions');
    $now = gmdate('Y-m-d H:i:s');
    $endpoint_hash = hash('sha256', $subscription['endpoint']);

    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT id FROM {$table} WHERE endpoint_hash = %s", $endpoint_hash),
        ARRAY_A
    );

    $data = [
        'pseudo_id' => $pseudo_id,
        'endpoint' => $subscription['endpoint'],
        'endpoint_hash' => $endpoint_hash,
        'public_key' => $subscription['public_key'],
        'auth_token' => $subscription['auth_token'],
        'content_encoding' => $subscription['content_encoding'],
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'platform' => substr((string) ($request->get_param('platform') ?? ''), 0, 50),
        'is_active' => 1,
        'last_seen_at' => $now,
    ];

    if ($existing) {
        $wpdb->update($table, $data, ['id' => (int) $existing['id']], ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s'], ['%d']);
    } else {
        $data['created_at'] = $now;
        $wpdb->insert($table, $data, ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_pwa_push_unsubscribe(WP_REST_Request $request): WP_REST_Response
{
    $subscription = impactshop_pwa_push_extract_subscription((array) $request->get_json_params());
    if ($subscription['endpoint'] === '') {
        return new WP_REST_Response(['message' => 'invalid_subscription'], 400);
    }

    global $wpdb;
    $table = impactshop_pwa_push_table('impact_push_subscriptions');
    $endpoint_hash = hash('sha256', $subscription['endpoint']);

    $wpdb->update($table, [
        'is_active' => 0,
        'last_seen_at' => gmdate('Y-m-d H:i:s'),
    ], [
        'endpoint_hash' => $endpoint_hash,
    ], ['%d', '%s'], ['%s']);

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_pwa_push_process_queue(): void
{
    if (!impactshop_pwa_push_is_configured()) {
        return;
    }
    if (!impactshop_pwa_push_autoload()) {
        return;
    }
    if (!class_exists('Minishlink\\WebPush\\WebPush')) {
        return;
    }

    global $wpdb;
    $messages_table = impactshop_pwa_push_table('impact_vote_messages');
    $targets_table = impactshop_pwa_push_table('impact_vote_message_targets');
    $subs_table = impactshop_pwa_push_table('impact_push_subscriptions');
    $deliveries_table = impactshop_pwa_push_table('impact_push_deliveries');
    $now = gmdate('Y-m-d H:i:s');

    $webPush = new Minishlink\WebPush\WebPush([
        'VAPID' => [
            'subject' => IMPACT_PUSH_SUBJECT,
            'publicKey' => IMPACT_PUSH_VAPID_PUBLIC,
            'privateKey' => IMPACT_PUSH_VAPID_PRIVATE,
        ],
    ]);
    $webPush->setReuseVAPIDHeaders(true);

    $processed = 0;
    $max_per_run = 200;

    $targeted_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT m.id, m.content, m.priority, t.pseudo_id
             FROM {$messages_table} m
             INNER JOIN {$targets_table} t ON m.id = t.message_id
             WHERE m.type = 'targeted'
               AND t.is_read = 0
               AND m.start_at <= %s AND m.end_at >= %s
             ORDER BY m.priority DESC, m.start_at DESC
             LIMIT 200",
            $now,
            $now
        ),
        ARRAY_A
    );

    foreach ($targeted_messages as $message) {
        if ($processed >= $max_per_run) {
            break;
        }
        $message_id = (int) $message['id'];
        $pseudo_id = (string) $message['pseudo_id'];
        if ($pseudo_id === '') {
            continue;
        }

        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$subs_table} WHERE pseudo_id = %s AND is_active = 1",
                $pseudo_id
            ),
            ARRAY_A
        );
        if (!$subscriptions) {
            continue;
        }

        $body = trim(wp_strip_all_tags((string) $message['content']));
        if ($body === '') {
            continue;
        }
        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 177) . '...';
        }

        $payload = wp_json_encode([
            'title' => 'Sharity',
            'body' => $body,
            'url' => site_url('/profil'),
            'messageId' => $message_id,
        ]);

        foreach ($subscriptions as $subscription_row) {
            if ($processed >= $max_per_run) {
                break;
            }
            $subscription_id = (int) $subscription_row['id'];
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$deliveries_table} WHERE message_id = %d AND subscription_id = %d",
                    $message_id,
                    $subscription_id
                )
            );
            if ($existing) {
                continue;
            }

            $subscription = Minishlink\WebPush\Subscription::create([
                'endpoint' => $subscription_row['endpoint'],
                'publicKey' => $subscription_row['public_key'],
                'authToken' => $subscription_row['auth_token'],
                'contentEncoding' => $subscription_row['content_encoding'],
            ]);

            $status = 'sent';
            $http_code = null;
            $error_message = '';

            try {
                $report = $webPush->sendOneNotification($subscription, $payload, ['TTL' => 3600]);
                if ($report) {
                    $http_code = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
                    if (!$report->isSuccess()) {
                        $status = 'failed';
                        $error_message = substr((string) $report->getReason(), 0, 255);
                        if ($report->isSubscriptionExpired()) {
                            $wpdb->update($subs_table, ['is_active' => 0], ['id' => $subscription_id], ['%d'], ['%d']);
                        }
                    }
                }
            } catch (Throwable $e) {
                $status = 'failed';
                $error_message = substr($e->getMessage(), 0, 255);
            }

            $wpdb->insert($deliveries_table, [
                'message_id' => $message_id,
                'subscription_id' => $subscription_id,
                'status' => $status,
                'http_code' => $http_code,
                'error_message' => $error_message,
                'created_at' => $now,
            ], ['%d', '%d', '%s', '%d', '%s', '%s']);

            $processed++;
        }
    }

    if ($processed >= $max_per_run) {
        return;
    }

    $global_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT m.id, m.content, m.priority
             FROM {$messages_table} m
             WHERE m.type = 'global'
               AND m.start_at <= %s AND m.end_at >= %s
             ORDER BY m.priority DESC, m.start_at DESC
             LIMIT 50",
            $now,
            $now
        ),
        ARRAY_A
    );

    if (!$global_messages) {
        return;
    }

    $all_subscriptions = $wpdb->get_results(
        "SELECT * FROM {$subs_table} WHERE is_active = 1",
        ARRAY_A
    );

    if (!$all_subscriptions) {
        return;
    }

    foreach ($global_messages as $message) {
        if ($processed >= $max_per_run) {
            break;
        }
        $message_id = (int) $message['id'];
        $body = trim(wp_strip_all_tags((string) $message['content']));
        if ($body === '') {
            continue;
        }
        if (mb_strlen($body) > 180) {
            $body = mb_substr($body, 0, 177) . '...';
        }

        $payload = wp_json_encode([
            'title' => 'Sharity',
            'body' => $body,
            'url' => site_url('/profil'),
            'messageId' => $message_id,
        ]);

        foreach ($all_subscriptions as $subscription_row) {
            if ($processed >= $max_per_run) {
                break;
            }
            $subscription_id = (int) $subscription_row['id'];
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$deliveries_table} WHERE message_id = %d AND subscription_id = %d",
                    $message_id,
                    $subscription_id
                )
            );
            if ($existing) {
                continue;
            }

            $subscription = Minishlink\WebPush\Subscription::create([
                'endpoint' => $subscription_row['endpoint'],
                'publicKey' => $subscription_row['public_key'],
                'authToken' => $subscription_row['auth_token'],
                'contentEncoding' => $subscription_row['content_encoding'],
            ]);

            $status = 'sent';
            $http_code = null;
            $error_message = '';

            try {
                $report = $webPush->sendOneNotification($subscription, $payload, ['TTL' => 3600]);
                if ($report) {
                    $http_code = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
                    if (!$report->isSuccess()) {
                        $status = 'failed';
                        $error_message = substr((string) $report->getReason(), 0, 255);
                        if ($report->isSubscriptionExpired()) {
                            $wpdb->update($subs_table, ['is_active' => 0], ['id' => $subscription_id], ['%d'], ['%d']);
                        }
                    }
                }
            } catch (Throwable $e) {
                $status = 'failed';
                $error_message = substr($e->getMessage(), 0, 255);
            }

            $wpdb->insert($deliveries_table, [
                'message_id' => $message_id,
                'subscription_id' => $subscription_id,
                'status' => $status,
                'http_code' => $http_code,
                'error_message' => $error_message,
                'created_at' => $now,
            ], ['%d', '%d', '%s', '%d', '%s', '%s']);

            $processed++;
        }
    }
}
