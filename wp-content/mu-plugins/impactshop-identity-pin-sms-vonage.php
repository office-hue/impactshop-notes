<?php
/**
 * Plugin Name: ImpactShop Identity PIN SMS (Vonage)
 * Description: Sends PIN via Vonage SMS when impactshop_identity_pin_sms is triggered.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('impactshop_identity_pin_sms', function ($result, array $payload) {
    $api_key = getenv('VONAGE_API_KEY');
    $api_secret = getenv('VONAGE_API_SECRET');
    $from = getenv('VONAGE_FROM');

    if (!$api_key || !$api_secret || !$from) {
        return [
            'status' => 'error',
            'error'  => 'missing_credentials',
        ];
    }

    $target = isset($payload['target']) ? (string)$payload['target'] : '';
    $pin = isset($payload['pin']) ? (string)$payload['pin'] : '';

    if ($target === '' || $pin === '') {
        return [
            'status' => 'error',
            'error'  => 'invalid_payload',
        ];
    }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists(\Vonage\Client::class)) {
        try {
            $basic = new \Vonage\Client\Credentials\Basic($api_key, $api_secret);
            $client = new \Vonage\Client($basic);
            $message = new \Vonage\SMS\Message\SMS(
                $target,
                $from,
                sprintf('Impact Shop PIN: %s (15 percig ervenyes)', $pin)
            );

            $response = $client->sms()->send($message);
            $current = $response->current();

            if ($current && (int)$current->getStatus() === 0) {
                return [
                    'status' => 'sent',
                    'id'     => $current->getMessageId(),
                ];
            }

            return [
                'status' => 'error',
                'error'  => $current ? (string)$current->getStatus() : 'unknown_error',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error'  => $e->getMessage(),
            ];
        }
    }

    $message = sprintf('Impact Shop PIN: %s (15 percig ervenyes)', $pin);
    $body = [
        'api_key'    => $api_key,
        'api_secret' => $api_secret,
        'to'         => $target,
        'from'       => $from,
        'text'       => $message,
    ];

    $response = wp_remote_post('https://rest.nexmo.com/sms/json', [
        'timeout' => 10,
        'body'    => $body,
    ]);

    if (is_wp_error($response)) {
        sleep(2);
        $response = wp_remote_post('https://rest.nexmo.com/sms/json', [
            'timeout' => 10,
            'body'    => $body,
        ]);
    }

    if (is_wp_error($response)) {
        do_action('impactshop_pin_sms_failed', [
            'target' => $target,
            'pin'    => $pin,
            'error'  => $response->get_error_message(),
        ]);
        return [
            'status' => 'error',
            'error'  => $response->get_error_message(),
        ];
    }

    $payload_body = json_decode((string)wp_remote_retrieve_body($response), true);
    $message_info = is_array($payload_body) && isset($payload_body['messages'][0])
        ? $payload_body['messages'][0]
        : null;

    if (!is_array($message_info) || (string)($message_info['status'] ?? '') !== '0') {
        return [
            'status' => 'error',
            'error'  => $message_info['error-text'] ?? 'unknown_error',
        ];
    }

    return [
        'status' => 'sent',
        'id'     => $message_info['message-id'] ?? null,
    ];
}, 10, 2);
