<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SHARITY_POINTS_MONITORING_LIMIT')) {
    define('SHARITY_POINTS_MONITORING_LIMIT', 200);
}
if (!defined('SHARITY_POINTS_WEBHOOK_RETRY_LIMIT')) {
    define('SHARITY_POINTS_WEBHOOK_RETRY_LIMIT', 50);
}
if (!defined('SHARITY_POINTS_WEBHOOK_RETRY_MAX_ATTEMPTS')) {
    define('SHARITY_POINTS_WEBHOOK_RETRY_MAX_ATTEMPTS', 5);
}

const SHARITY_POINTS_WEBHOOK_RETRY_OPTION = 'sharity_points_webhook_retry_queue';

add_action('sharity_points_earned', 'sharity_points_handle_webhooks', 10, 4);
add_action('sharity_level_changed', 'sharity_points_handle_level_webhooks', 10, 4);
add_action('sharity_points_decayed', 'sharity_points_handle_decay_webhooks', 10, 3);
add_action('sharity_points_webhook_retry', 'sharity_points_process_webhook_retry');

function sharity_points_monitoring_enabled(): bool
{
    $enabled = (bool) get_option('sharity_points_monitoring_enabled', false);
    return (bool) apply_filters('sharity_points_monitoring_enabled', $enabled);
}

function sharity_points_log_event(string $event, array $payload): void
{
    if (!sharity_points_monitoring_enabled()) {
        return;
    }

    $log = get_option('sharity_points_monitoring_log', []);
    if (!is_array($log)) {
        $log = [];
    }

    $payload['event'] = $event;
    $payload['timestamp'] = current_time('mysql', true);

    $log[] = $payload;
    if (count($log) > SHARITY_POINTS_MONITORING_LIMIT) {
        $log = array_slice($log, -SHARITY_POINTS_MONITORING_LIMIT);
    }

    update_option('sharity_points_monitoring_log', $log, false);
}

function sharity_points_dispatch_webhook(array $payload): void
{
    $url = (string) get_option('sharity_points_webhook_url', '');
    $secret = (string) get_option('sharity_points_webhook_secret', '');

    if ($url === '' || $secret === '') {
        return;
    }

    $body = wp_json_encode($payload);
    if ($body === false) {
        return;
    }

    $signature = hash_hmac('sha256', $body, $secret);

    $response = wp_remote_post($url, [
        'body' => $body,
        'timeout' => 5,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Sharity-Signature' => $signature,
        ],
    ]);
    if (is_wp_error($response)) {
        sharity_points_enqueue_webhook_retry($payload, 1);
        do_action('sharity_points_error', 'webhook_failed', [
            'error' => $response->get_error_message(),
            'payload' => $payload,
        ]);
        return;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    if ($status >= 400) {
        sharity_points_enqueue_webhook_retry($payload, 1);
        do_action('sharity_points_error', 'webhook_failed', [
            'status' => $status,
            'payload' => $payload,
        ]);
    }
}

function sharity_points_enqueue_webhook_retry(array $payload, int $attempts): void
{
    $queue = get_option(SHARITY_POINTS_WEBHOOK_RETRY_OPTION, []);
    if (!is_array($queue)) {
        $queue = [];
    }

    $attempts = max(1, $attempts);
    $delay = (int) apply_filters('sharity_points_webhook_retry_delay', 60, $attempts);
    $queue[] = [
        'id' => wp_generate_uuid4(),
        'payload' => $payload,
        'attempts' => $attempts,
        'next_retry' => time() + max(30, $delay),
    ];

    if (count($queue) > SHARITY_POINTS_WEBHOOK_RETRY_LIMIT) {
        $queue = array_slice($queue, -SHARITY_POINTS_WEBHOOK_RETRY_LIMIT);
    }

    update_option(SHARITY_POINTS_WEBHOOK_RETRY_OPTION, $queue, false);
}

function sharity_points_process_webhook_retry(): void
{
    $queue = get_option(SHARITY_POINTS_WEBHOOK_RETRY_OPTION, []);
    if (!is_array($queue) || empty($queue)) {
        return;
    }

    $url = (string) get_option('sharity_points_webhook_url', '');
    $secret = (string) get_option('sharity_points_webhook_secret', '');
    if ($url === '' || $secret === '') {
        return;
    }

    $now = time();
    $remaining = [];

    foreach ($queue as $item) {
        $next_retry = (int) ($item['next_retry'] ?? 0);
        if ($next_retry > $now) {
            $remaining[] = $item;
            continue;
        }

        $payload = isset($item['payload']) && is_array($item['payload']) ? $item['payload'] : [];
        $attempts = (int) ($item['attempts'] ?? 1);

        $body = wp_json_encode($payload);
        if ($body === false) {
            continue;
        }

        $signature = hash_hmac('sha256', $body, $secret);
        $response = wp_remote_post($url, [
            'body' => $body,
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Sharity-Signature' => $signature,
            ],
        ]);

        $failed = is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400;
        if ($failed) {
            $attempts++;
            if ($attempts <= SHARITY_POINTS_WEBHOOK_RETRY_MAX_ATTEMPTS) {
                $delay = (int) apply_filters('sharity_points_webhook_retry_delay', 60, $attempts);
                $item['attempts'] = $attempts;
                $item['next_retry'] = $now + max(30, $delay);
                $remaining[] = $item;
                continue;
            }

            do_action('sharity_points_error', 'webhook_retry_exhausted', [
                'payload' => $payload,
            ]);
        }
    }

    update_option(SHARITY_POINTS_WEBHOOK_RETRY_OPTION, $remaining, false);
}

function sharity_points_subject_payload($subject): array
{
    if (is_numeric($subject)) {
        return ['user_id' => (int) $subject];
    }

    $pseudo_id = is_string($subject) ? $subject : '';
    return ['pseudo_id' => $pseudo_id];
}

function sharity_points_handle_webhooks($user_id, int $points, string $type, array $metadata): void
{
    $subject = sharity_points_subject_payload($user_id ?? ($metadata['pseudo_id'] ?? ''));
    $payload = array_merge([
        'event' => 'points.earned',
        'points' => $points,
        'type' => $type,
        'metadata' => $metadata,
    ], $subject);

    sharity_points_log_event('points.earned', $payload);
    sharity_points_dispatch_webhook($payload);
}

function sharity_points_handle_level_webhooks($subject, string $old_level, string $new_level, int $points): void
{
    $payload = array_merge([
        'event' => 'level.changed',
        'old_level' => $old_level,
        'new_level' => $new_level,
        'points_total' => $points,
    ], sharity_points_subject_payload($subject));

    sharity_points_log_event('level.changed', $payload);
    sharity_points_dispatch_webhook($payload);
}

function sharity_points_handle_decay_webhooks($subject, int $amount, int $days_inactive): void
{
    $payload = array_merge([
        'event' => 'points.decayed',
        'decay_amount' => $amount,
        'days_inactive' => $days_inactive,
    ], sharity_points_subject_payload($subject));

    sharity_points_log_event('points.decayed', $payload);
    sharity_points_dispatch_webhook($payload);
}
