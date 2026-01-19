<?php
/**
 * Plugin Name: ImpactShop Activity Log
 * Description: Minimal event log for Impact Shop activity (clicks, shares, AI chat).
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_ACTIVITY_LOG_SCHEMA = 1;

add_action('muplugins_loaded', function () {
    impactshop_activity_log_maybe_migrate();
});

add_action('rest_api_init', function () {
    impactshop_activity_log_register_routes();
});

function impactshop_activity_log_maybe_migrate(): void
{
    $version = (int) get_option('impactshop_activity_log_schema', 0);
    if ($version >= IMPACTSHOP_ACTIVITY_LOG_SCHEMA) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_activity_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        happened_at datetime NOT NULL,
        pseudo_id varchar(64) DEFAULT '' NOT NULL,
        event_type varchar(40) DEFAULT '' NOT NULL,
        event_source varchar(40) DEFAULT '' NOT NULL,
        ngo_slug varchar(64) DEFAULT '' NOT NULL,
        shop_slug varchar(64) DEFAULT '' NOT NULL,
        network varchar(32) DEFAULT '' NOT NULL,
        amount_huf bigint(20) DEFAULT 0 NOT NULL,
        transaction_id varchar(64) DEFAULT '' NOT NULL,
        meta longtext,
        ip_address varchar(45) DEFAULT '' NOT NULL,
        user_agent varchar(255) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id),
        KEY event_type (event_type),
        KEY pseudo_id (pseudo_id),
        KEY happened_at (happened_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('impactshop_activity_log_schema', IMPACTSHOP_ACTIVITY_LOG_SCHEMA, false);
}

function impactshop_activity_log_register_routes(): void
{
    register_rest_route(
        'impact/v1',
        '/event',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_activity_log_event_endpoint',
            'permission_callback' => '__return_true',
        ]
    );
}

function impactshop_activity_log_event_endpoint(WP_REST_Request $request): WP_REST_Response
{
    $params = (array) $request->get_json_params();
    $event_type = isset($params['event_type']) ? (string) $params['event_type'] : '';
    $event_source = isset($params['event_source']) ? (string) $params['event_source'] : '';

    $data = [
        'event_source'   => $event_source,
        'ngo_slug'       => isset($params['ngo_slug']) ? (string) $params['ngo_slug'] : '',
        'shop_slug'      => isset($params['shop_slug']) ? (string) $params['shop_slug'] : '',
        'network'        => isset($params['network']) ? (string) $params['network'] : '',
        'amount_huf'     => isset($params['amount_huf']) ? (int) $params['amount_huf'] : 0,
        'transaction_id' => isset($params['transaction_id']) ? (string) $params['transaction_id'] : '',
        'meta'           => isset($params['meta']) ? $params['meta'] : [],
    ];

    $ok = impactshop_log_event($event_type, $data);
    if (!$ok) {
        return new WP_REST_Response(['status' => 'error'], 400);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_log_event(string $event_type, array $data = []): bool
{
    $allowed = ['go_click', 'social_share', 'impi_question'];
    if (!in_array($event_type, $allowed, true)) {
        return false;
    }

    $pseudo = '';
    if (!empty($data['pseudo_id']) && is_string($data['pseudo_id'])) {
        $pseudo = $data['pseudo_id'];
    } elseif (!empty($_COOKIE['impactshop_pseudo_id'])) {
        $pseudo = sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id']));
    }

    $row = [
        'happened_at'   => gmdate('Y-m-d H:i:s'),
        'pseudo_id'     => sanitize_text_field($pseudo),
        'event_type'    => sanitize_key($event_type),
        'event_source'  => sanitize_key((string) ($data['event_source'] ?? '')),
        'ngo_slug'      => sanitize_title((string) ($data['ngo_slug'] ?? '')),
        'shop_slug'     => sanitize_title((string) ($data['shop_slug'] ?? '')),
        'network'       => sanitize_key((string) ($data['network'] ?? '')),
        'amount_huf'    => (int) ($data['amount_huf'] ?? 0),
        'transaction_id'=> sanitize_text_field((string) ($data['transaction_id'] ?? '')),
        'meta'          => wp_json_encode($data['meta'] ?? []),
        'ip_address'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '',
    ];

    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_activity_log';
    $res = $wpdb->insert($table, $row);

    return $res !== false;
}
