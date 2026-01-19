<?php
/**
 * Plugin Name: ImpactShop Health Endpoint
 * Description: Provides a lightweight REST health check for impact/v1/health.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'impact/v1',
        '/health',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_health_endpoint',
            'permission_callback' => '__return_true',
        ]
    );
});

function impactshop_health_endpoint(WP_REST_Request $request): WP_REST_Response
{
    global $wpdb;
    $pin_table = $wpdb->prefix . 'impact_pin_tokens';
    $active_pins = $wpdb->get_var(
        "SELECT COUNT(*) FROM $pin_table WHERE used_at IS NULL AND expires_at > NOW()"
    );
    $vonage_configured = getenv('VONAGE_API_KEY') ? true : false;

    $data = [
        'status'    => 'ok',
        'timestamp' => current_time('mysql'),
        'pin'       => [
            'status'             => 'ok',
            'active_pins'         => (int)$active_pins,
            'vonage_configured'   => $vonage_configured,
        ],
    ];

    return new WP_REST_Response($data, 200);
}
