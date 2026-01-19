<?php
/**
 * Plugin Name: ImpactShop Identity PIN QR (QuickChart)
 * Description: Provides a QR payload via QuickChart API.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('impactshop_identity_pin_qr_payload', function ($payload, array $data) {
    $pin = isset($data['pin']) ? (string)$data['pin'] : '';
    if ($pin === '') {
        return null;
    }

    $text = 'impactshop-pin:' . $pin;
    $url = add_query_arg(
        [
            'text' => $text,
            'size' => '220',
        ],
        'https://quickchart.io/qr'
    );

    return $url;
}, 10, 2);
