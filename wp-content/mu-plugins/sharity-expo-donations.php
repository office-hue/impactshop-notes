<?php
/**
 * Plugin Name: Sharity BabaMama Expo Stripe events
 * Description: Disabled-by-default receiver for the partner's Expo Payment Link.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Sharity_Expo_Stripe_Events
{
    private const API_VERSION = '2026-10-28.endive';

    public static function register(): void
    {
        if (self::config() === null) {
            return;
        }
        register_rest_route('sharity/v1', '/expo/babamama-2026/events', [
            'methods' => 'POST',
            'callback' => [self::class, 'receive'],
            // Stripe authenticates with the raw-body signature, checked first in receive().
            'permission_callback' => '__return_true',
        ]);
    }

    private static function config(): ?array
    {
        if (!defined('SHARITY_EXPO_STRIPE_ENABLED') || SHARITY_EXPO_STRIPE_ENABLED !== true) {
            return null;
        }

        $secret = defined('SHARITY_EXPO_STRIPE_WEBHOOK_SECRET') ? SHARITY_EXPO_STRIPE_WEBHOOK_SECRET : null;
        $account = defined('SHARITY_EXPO_STRIPE_ACCOUNT') ? SHARITY_EXPO_STRIPE_ACCOUNT : null;
        $link = defined('SHARITY_EXPO_STRIPE_PAYMENT_LINK') ? SHARITY_EXPO_STRIPE_PAYMENT_LINK : null;
        $live = defined('SHARITY_EXPO_STRIPE_LIVEMODE') ? SHARITY_EXPO_STRIPE_LIVEMODE : null;

        if (!is_bool($live) || !self::isId($secret, 'whsec_')
            || !self::isId($account, 'acct_') || !self::isId($link, 'plink_')) {
            return null;
        }

        return [
            'secret' => $secret,
            'account' => $account,
            'link' => $link,
            'live' => $live,
            'binding' => hash('sha256', $account . ':' . $link . ':' . ($live ? 'live' : 'test')),
        ];
    }

    private static function isId($value, string $prefix): bool
    {
        return is_string($value) && strlen($value) <= 255
            && preg_match('/^' . preg_quote($prefix, '/') . '[A-Za-z0-9]+$/D', $value) === 1;
    }

    public static function receive(WP_REST_Request $request): WP_REST_Response
    {
        $config = self::config();
        if ($config === null) {
            return self::response(404, 'unavailable');
        }
        $raw = $request->get_body();
        $signature = $request->get_header('stripe-signature');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 262144
            || !is_string($signature) || $signature === '' || strlen($signature) > 8192) {
            return self::response(400, 'invalid_request');
        }

        try {
            sharity_donation_runtime_sdk();
        } catch (Throwable $error) {
            return self::response(503, 'verification_unavailable');
        }

        try {
            $event = Stripe\Webhook::constructEvent($raw, $signature, $config['secret'], 300)->toArray();
        } catch (Throwable $error) {
            return self::response(400, 'invalid_signature_or_event');
        }

        if (($event['object'] ?? null) !== 'event' || ($event['livemode'] ?? null) !== $config['live']
            || (($event['account'] ?? $config['account']) !== $config['account'])
            || isset($event['context']) || ($event['api_version'] ?? null) !== self::API_VERSION
            || !self::isId($event['id'] ?? null, 'evt_')
            || !is_int($event['created'] ?? null) || $event['created'] < 1 || $event['created'] > time() + 300
            || !is_string($event['type'] ?? null)) {
            return self::response(400, 'unexpected_event');
        }

        $kind = match ($event['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'payment',
            'refund.created', 'refund.updated', 'refund.failed' => 'refund',
            default => null,
        };
        if ($kind === null) {
            return self::response(200, 'ignored');
        }

        $object = $event['data']['object'] ?? null;
        if (!is_array($object) || ($object['object'] ?? null) !== ($kind === 'payment' ? 'checkout.session' : 'refund')) {
            return self::response(400, 'unexpected_object');
        }

        if ($kind === 'payment') {
            if (($object['payment_link'] ?? null) !== $config['link']
                || ($object['mode'] ?? null) !== 'payment' || ($object['payment_status'] ?? null) !== 'paid') {
                return self::response(200, 'ignored');
            }
            $sessionPrefix = $config['live'] ? 'cs_live_' : 'cs_test_';
            if (($object['livemode'] ?? null) !== $config['live'] || !self::isId($object['id'] ?? null, $sessionPrefix)) {
                return self::response(400, 'unexpected_session');
            }
        } else {
            $kind = match ($object['status'] ?? null) {
                'succeeded' => 'refund',
                'failed', 'canceled' => 'refund_reversal',
                default => null,
            };
            if ($kind === null) {
                return self::response(200, 'ignored');
            }
        }

        if (($object['currency'] ?? null) !== 'huf') {
            return self::response($kind === 'payment' ? 422 : 200, 'unsupported_currency');
        }

        $payment = $object['payment_intent'] ?? null;
        $objectId = $kind === 'payment' ? $payment : ($object['id'] ?? null);
        $amount = $object[$kind === 'payment' ? 'amount_total' : 'amount'] ?? null;
        if (!self::isId($payment, 'pi_') || !self::isId($objectId, $kind === 'payment' ? 'pi_' : 're_')
            || !is_int($amount) || $amount < 1) {
            return self::response(422, 'invalid_amount_or_identity');
        }

        try {
            return self::record($config['binding'], $event, $kind, $objectId, $payment, $amount);
        } catch (Throwable $error) {
            return self::response(503, 'storage_unavailable');
        }
    }

    private static function record(
        string $binding,
        array $event,
        string $kind,
        string $objectId,
        string $payment,
        int $amount
    ): WP_REST_Response {
        global $wpdb;

        $table = $wpdb->prefix . 'sharity_expo_stripe_events_v1';
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
            return self::response(503, 'storage_unavailable');
        }
        // This handler acknowledges only durable writes, never joins another plugin's transaction.
        if ((string) $wpdb->get_var('SELECT IF(@@autocommit = 1 AND @@in_transaction = 0, 1, 0)') !== '1') {
            return self::response(503, 'storage_unavailable');
        }

        if ($kind !== 'payment') {
            $parentAmount = $wpdb->get_var($wpdb->prepare(
                "SELECT amount_minor FROM `{$table}` WHERE binding = %s AND object_id = %s AND kind = 'payment'",
                $binding,
                $payment
            ));
            if ($parentAmount === null) {
                return self::response(503, 'payment_not_yet_matched');
            }
            if ($amount > (int) $parentAmount) {
                return self::response(409, 'refund_amount_conflict');
            }
        }

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$table}` (binding, object_id, stripe_event_id, kind, payment_id, amount_minor, stripe_created)
             VALUES (%s, %s, %s, %s, %s, %d, %d) ON DUPLICATE KEY UPDATE binding = binding",
            $binding,
            $objectId,
            $event['id'],
            $kind,
            $payment,
            $amount,
            $event['created']
        ));
        if ($inserted === false) {
            return self::response(503, 'storage_unavailable');
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, kind, payment_id, amount_minor, stripe_event_id, stripe_created FROM `{$table}`
             WHERE binding = %s AND ((kind = %s AND object_id = %s) OR stripe_event_id = %s)",
            $binding,
            $kind,
            $objectId,
            $event['id']
        ), ARRAY_A);
        if (!is_array($rows) || $rows === []) {
            return self::response(503, 'storage_unavailable');
        }

        $row = $rows[0];
        if (count($rows) !== 1 || $row['object_id'] !== $objectId || $row['kind'] !== $kind
            || $row['payment_id'] !== $payment || (string) $row['amount_minor'] !== (string) $amount
            || ($row['stripe_event_id'] === $event['id'] && (int) $row['stripe_created'] !== $event['created'])) {
            return self::response(409, 'event_conflict');
        }

        return self::response(200, 'recorded');
    }

    private static function response(int $status, string $code): WP_REST_Response
    {
        $response = new WP_REST_Response(['status' => $code], $status);
        $response->header('Cache-Control', 'no-store');
        return $response;
    }
}

add_action('rest_api_init', [Sharity_Expo_Stripe_Events::class, 'register']);
