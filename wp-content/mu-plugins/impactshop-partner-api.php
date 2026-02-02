<?php
/**
 * Plugin Name: ImpactShop Partner API
 * Description: Partner webhook + discount quote + dispute endpoints for non-affiliate integrations.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_PARTNER_SCHEMA_VERSION = '2026-02-01-1';
const IMPACTSHOP_PARTNER_SCHEMA_OPTION = 'impactshop_partner_schema_version';

impactshop_partner_load_env('/home/sharityh/.impact-secrets/env.d/partner.env');

add_action('muplugins_loaded', function () {
    impactshop_partner_maybe_create_tables();
});

function impactshop_partner_maybe_create_tables(): void
{
    global $wpdb;

    $current = (string) get_option(IMPACTSHOP_PARTNER_SCHEMA_OPTION, '');
    if ($current === IMPACTSHOP_PARTNER_SCHEMA_VERSION) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $tx_table = $wpdb->prefix . 'impact_partner_tx';
    $sql_tx = "CREATE TABLE {$tx_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      partner_code VARCHAR(64) NOT NULL,
      event_id VARCHAR(128) NOT NULL,
      event_type VARCHAR(32) NOT NULL,
      pseudo_id_hash CHAR(64) NOT NULL,
      ngo_code VARCHAR(64) NULL,
      amount_gross INT NOT NULL,
      currency CHAR(3) NOT NULL DEFAULT 'HUF',
      payment_status VARCHAR(16) NOT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'pending',
      discount_tier VARCHAR(16) NULL,
      partner_max_discount DECIMAL(5,4) NULL,
      discount_rate DECIMAL(5,4) NULL,
      discount_amount INT NULL,
      idempotency_key VARCHAR(128) NOT NULL,
      proof_hash CHAR(64) NULL,
      payload_json LONGTEXT NULL,
      response_json LONGTEXT NULL,
      ledger_id BIGINT UNSIGNED NULL,
      reconcile_status VARCHAR(16) NULL,
      reconcile_batch_id VARCHAR(64) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      approved_at DATETIME NULL,
      declined_at DATETIME NULL,
      void_at DATETIME NULL,
      updated_at DATETIME NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY uniq_partner_event (partner_code, event_id),
      KEY idx_idempotency (idempotency_key),
      KEY idx_status (status),
      KEY idx_created (created_at)
    ) {$charset};";

    $config_table = $wpdb->prefix . 'impact_partner_config';
    $sql_config = "CREATE TABLE {$config_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      partner_code VARCHAR(64) NOT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'active',
      webhook_url VARCHAR(255) NULL,
      webhook_mode VARCHAR(16) NOT NULL DEFAULT 'live',
      partner_max_discount DECIMAL(5,4) NULL,
      discount_cap_amount INT NULL,
      discount_min_cart INT NULL,
      discount_stackable TINYINT(1) NOT NULL DEFAULT 0,
      currency CHAR(3) NOT NULL DEFAULT 'HUF',
      allowed_event_types VARCHAR(255) NULL,
      idempotency_ttl_sec INT NOT NULL DEFAULT 86400,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY uniq_partner_code (partner_code),
      KEY idx_status (status),
      KEY idx_mode (webhook_mode)
    ) {$charset};";

    dbDelta($sql_tx);
    dbDelta($sql_config);

    update_option(IMPACTSHOP_PARTNER_SCHEMA_OPTION, IMPACTSHOP_PARTNER_SCHEMA_VERSION, true);
}

add_action('rest_api_init', function () {
    $namespace = 'impact/v1';

    register_rest_route($namespace, '/partner/transaction', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_partner_transaction',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/partner/discount/quote', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_partner_discount_quote',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/partner/dispute', [
        'methods'             => 'POST',
        'callback'            => 'impactshop_partner_dispute',
        'permission_callback' => '__return_true',
    ]);
});

function impactshop_partner_transaction(WP_REST_Request $request)
{
    $request_id = impactshop_partner_request_id();
    $raw_body = (string) $request->get_body();

    $payload = impactshop_partner_decode_payload($raw_body, $request_id);
    if (is_wp_error($payload)) {
        return impactshop_partner_handle_error($payload, $request_id, 'partner_tx_rejected', null, $raw_body);
    }

    $partner_code = (string) ($payload['partner_code'] ?? '');
    $auth_check = impactshop_partner_verify_auth($request, $raw_body, $partner_code, $request_id);
    if (is_wp_error($auth_check)) {
        return impactshop_partner_handle_error($auth_check, $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $rate_check = impactshop_partner_rate_limit($partner_code, 'transaction', $request_id);
    if (is_wp_error($rate_check)) {
        return impactshop_partner_handle_error($rate_check, $request_id, 'partner_tx_rate_limited', $payload, $raw_body);
    }

    $idempotency_key = (string) $request->get_header('idempotency-key');
    if ($idempotency_key === '') {
        $error = new WP_Error('partner_idempotency_missing', 'Missing Idempotency-Key header', ['status' => 400]);
        return impactshop_partner_handle_error($error, $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $validation = impactshop_partner_validate_transaction($payload);
    if (is_wp_error($validation)) {
        return impactshop_partner_handle_error($validation, $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $config = impactshop_partner_get_config($partner_code);
    if (!$config) {
        return impactshop_partner_handle_error(new WP_Error('partner_auth_failed', 'Unknown partner configuration', ['status' => 403]), $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }
    if (($config['status'] ?? '') !== 'active') {
        return impactshop_partner_handle_error(new WP_Error('partner_auth_failed', 'Partner is not active', ['status' => 403]), $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $allowed_event_types = impactshop_partner_parse_csv(($config['allowed_event_types'] ?? ''));
    if ($allowed_event_types && !in_array($payload['event_type'], $allowed_event_types, true)) {
        return impactshop_partner_handle_error(new WP_Error('partner_payload_invalid', 'Event type is not allowed', ['status' => 422]), $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    global $wpdb;
    $tx_table = $wpdb->prefix . 'impact_partner_tx';

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$tx_table} WHERE partner_code = %s AND event_id = %s LIMIT 1",
            $partner_code,
            $payload['event_id']
        ),
        ARRAY_A
    );

    $now = current_time('mysql');

    if ($existing) {
        $existing_status = (string) ($existing['status'] ?? '');
        $incoming_payment = (string) ($payload['payment_status'] ?? '');
        $ledger_id = (int) ($existing['ledger_id'] ?? 0);

        if ($incoming_payment === 'refunded' && $existing_status !== 'declined') {
            $wpdb->update(
                $tx_table,
                [
                    'status' => 'declined',
                    'payment_status' => 'refunded',
                    'declined_at' => $now,
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($ledger_id > 0) {
                impactshop_partner_mark_ledger_rejected($ledger_id, 'partner_refund');
            }

            $response_payload = impactshop_partner_build_response('accepted', $ledger_id, $payload['event_id'], $partner_code, 'Refund applied');
            impactshop_partner_store_response((int) $existing['id'], $response_payload, $now);
            impactshop_partner_audit('partner_tx_refund', $partner_code, $payload['event_id'], $request_id, 'ok');
            return rest_ensure_response($response_payload);
        }

        $response_payload = impactshop_partner_build_response('duplicate', $ledger_id, $payload['event_id'], $partner_code, 'Duplicate event');
        impactshop_partner_store_response((int) $existing['id'], $response_payload, $now);
        impactshop_partner_audit('partner_tx_duplicate', $partner_code, $payload['event_id'], $request_id, 'duplicate');
        return rest_ensure_response($response_payload);
    }

    $idempotency_conflict = impactshop_partner_check_idempotency_conflict($idempotency_key, $raw_body, $request_id);
    if ($idempotency_conflict instanceof WP_REST_Response) {
        return $idempotency_conflict;
    }
    if (is_wp_error($idempotency_conflict)) {
        return impactshop_partner_handle_error($idempotency_conflict, $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $payload['currency'] = strtoupper((string) $payload['currency']);

    $status = impactshop_partner_status_from_payment($payload['payment_status']);
    $tier = impactshop_partner_nullable_string($payload['discount_tier'] ?? null);
    $partner_max_discount = impactshop_partner_nullable_float($payload['partner_max_discount'] ?? null);
    $discount_rate = impactshop_partner_nullable_float($payload['discount_rate'] ?? null);
    $discount_amount = impactshop_partner_nullable_int($payload['discount_amount'] ?? null);

    $inserted = $wpdb->insert(
        $tx_table,
        [
            'partner_code' => $partner_code,
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'pseudo_id_hash' => impactshop_partner_hash($payload['pseudo_id']),
            'ngo_code' => impactshop_partner_nullable_string($payload['ngo_code'] ?? null),
            'amount_gross' => (int) $payload['amount_gross'],
            'currency' => $payload['currency'],
            'payment_status' => $payload['payment_status'],
            'status' => $status,
            'discount_tier' => $tier,
            'partner_max_discount' => $partner_max_discount,
            'discount_rate' => $discount_rate,
            'discount_amount' => $discount_amount,
            'idempotency_key' => $idempotency_key,
            'proof_hash' => impactshop_partner_hash($raw_body),
            'payload_json' => wp_json_encode($payload),
            'created_at' => $now,
        ],
        ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%s']
    );

    if (!$inserted) {
        return impactshop_partner_handle_error(new WP_Error('partner_server_error', 'Failed to store transaction', ['status' => 500]), $request_id, 'partner_tx_rejected', $payload, $raw_body);
    }

    $tx_id = (int) $wpdb->insert_id;
    $ledger_id = 0;

    if ($status === 'approved') {
        $ledger_id = impactshop_partner_insert_ledger($payload, $partner_code, $partner_max_discount, $discount_rate, $discount_amount);
        if ($ledger_id <= 0) {
            $wpdb->update(
                $tx_table,
                [
                    'status' => 'pending',
                    'updated_at' => $now,
                ],
                ['id' => $tx_id],
                ['%s', '%s'],
                ['%d']
            );
            return impactshop_partner_handle_error(new WP_Error('partner_server_error', 'Ledger insert failed', ['status' => 500]), $request_id, 'partner_tx_rejected', $payload, $raw_body);
        }

        $wpdb->update(
            $tx_table,
            [
                'ledger_id' => $ledger_id,
                'approved_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $tx_id],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }

    if ($status === 'declined') {
        $wpdb->update(
            $tx_table,
            [
                'declined_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $tx_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    $response_payload = impactshop_partner_build_response('accepted', $ledger_id, $payload['event_id'], $partner_code, 'Accepted');
    impactshop_partner_store_response($tx_id, $response_payload, $now);
    impactshop_partner_audit('partner_tx_received', $partner_code, $payload['event_id'], $request_id, 'ok');

    return rest_ensure_response($response_payload);
}

function impactshop_partner_discount_quote(WP_REST_Request $request)
{
    $request_id = impactshop_partner_request_id();
    $raw_body = (string) $request->get_body();

    $payload = impactshop_partner_decode_payload($raw_body, $request_id);
    if (is_wp_error($payload)) {
        return impactshop_partner_handle_error($payload, $request_id, 'partner_discount_rejected', null, $raw_body);
    }

    $partner_code = (string) ($payload['partner_code'] ?? '');
    $auth_check = impactshop_partner_verify_auth($request, $raw_body, $partner_code, $request_id);
    if (is_wp_error($auth_check)) {
        return impactshop_partner_handle_error($auth_check, $request_id, 'partner_discount_rejected', $payload, $raw_body);
    }

    $rate_check = impactshop_partner_rate_limit($partner_code, 'discount', $request_id);
    if (is_wp_error($rate_check)) {
        return impactshop_partner_handle_error($rate_check, $request_id, 'partner_discount_rate_limited', $payload, $raw_body);
    }

    $validation = impactshop_partner_validate_discount($payload);
    if (is_wp_error($validation)) {
        return impactshop_partner_handle_error($validation, $request_id, 'partner_discount_rejected', $payload, $raw_body);
    }

    $config = impactshop_partner_get_config($partner_code);
    if (!$config || ($config['status'] ?? '') !== 'active') {
        return impactshop_partner_handle_error(new WP_Error('partner_auth_failed', 'Partner is not active', ['status' => 403]), $request_id, 'partner_discount_rejected', $payload, $raw_body);
    }

    $payload['currency'] = strtoupper((string) $payload['currency']);

    $amount_gross = (int) $payload['amount_gross'];
    $min_cart = isset($config['discount_min_cart']) ? (int) $config['discount_min_cart'] : 0;
    $max_discount = isset($config['partner_max_discount']) ? (float) $config['partner_max_discount'] : 0.0;
    $discount_cap = isset($config['discount_cap_amount']) ? (int) $config['discount_cap_amount'] : 0;

    $tier = impactshop_partner_level_for_pseudo($payload['pseudo_id']);
    $multiplier = impactshop_partner_tier_multiplier($tier);
    $discount_rate = impactshop_partner_round_rate($max_discount * $multiplier);

    $explain = impactshop_partner_discount_explain($tier, $multiplier);
    if ($amount_gross < $min_cart) {
        $discount_rate = 0.0;
        $explain .= ' | Minimum cart value not reached';
    }

    $discount_amount = (int) round($amount_gross * $discount_rate);
    if ($discount_cap > 0) {
        $discount_amount = min($discount_amount, $discount_cap);
    }

    $amount_net = max(0, $amount_gross - $discount_amount);

    $response = [
        'tier' => $tier,
        'partner_max_discount' => $max_discount,
        'discount_rate' => $discount_rate,
        'discount_amount' => $discount_amount,
        'amount_net' => $amount_net,
        'explain' => $explain,
    ];

    impactshop_partner_audit('partner_discount_quote', $partner_code, '', $request_id, 'ok');

    return rest_ensure_response($response);
}

function impactshop_partner_dispute(WP_REST_Request $request)
{
    $request_id = impactshop_partner_request_id();
    $raw_body = (string) $request->get_body();

    $payload = impactshop_partner_decode_payload($raw_body, $request_id);
    if (is_wp_error($payload)) {
        return impactshop_partner_handle_error($payload, $request_id, 'partner_dispute_rejected', null, $raw_body);
    }

    $partner_code = (string) ($payload['partner_code'] ?? '');
    $auth_check = impactshop_partner_verify_auth($request, $raw_body, $partner_code, $request_id);
    if (is_wp_error($auth_check)) {
        return impactshop_partner_handle_error($auth_check, $request_id, 'partner_dispute_rejected', $payload, $raw_body);
    }

    $rate_check = impactshop_partner_rate_limit($partner_code, 'dispute', $request_id);
    if (is_wp_error($rate_check)) {
        return impactshop_partner_handle_error($rate_check, $request_id, 'partner_dispute_rate_limited', $payload, $raw_body);
    }

    $validation = impactshop_partner_validate_dispute($payload);
    if (is_wp_error($validation)) {
        return impactshop_partner_handle_error($validation, $request_id, 'partner_dispute_rejected', $payload, $raw_body);
    }

    global $wpdb;
    $tx_table = $wpdb->prefix . 'impact_partner_tx';

    $tx = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, ledger_id FROM {$tx_table} WHERE partner_code = %s AND event_id = %s LIMIT 1",
            $partner_code,
            $payload['event_id']
        ),
        ARRAY_A
    );

    if (!$tx) {
        return impactshop_partner_handle_error(new WP_Error('partner_payload_invalid', 'Transaction not found for dispute', ['status' => 422]), $request_id, 'partner_dispute_rejected', $payload, $raw_body);
    }

    $dispute_id = 'disp_' . wp_generate_uuid4();
    $now = current_time('mysql');

    $wpdb->update(
        $tx_table,
        [
            'reconcile_status' => 'disputed',
            'updated_at' => $now,
        ],
        ['id' => (int) $tx['id']],
        ['%s', '%s'],
        ['%d']
    );

    $response = [
        'status' => 'opened',
        'dispute_id' => $dispute_id,
        'ledger_id' => (string) ($tx['ledger_id'] ?? ''),
    ];

    impactshop_partner_audit('partner_dispute_opened', $partner_code, $payload['event_id'], $request_id, 'ok');

    return rest_ensure_response($response);
}

function impactshop_partner_decode_payload(string $raw_body, string $request_id)
{
    if ($raw_body === '') {
        return new WP_Error('partner_payload_invalid', 'Empty body', ['status' => 400, 'request_id' => $request_id]);
    }

    $decoded = json_decode($raw_body, true);
    if (!is_array($decoded)) {
        return new WP_Error('partner_payload_invalid', 'Invalid JSON body', ['status' => 400, 'request_id' => $request_id]);
    }

    return $decoded;
}

function impactshop_partner_verify_auth(WP_REST_Request $request, string $raw_body, string $partner_code, string $request_id)
{
    $signature = (string) $request->get_header('x-impact-signature');
    if ($signature === '') {
        return new WP_Error('partner_signature_missing', 'Missing signature header', ['status' => 401, 'request_id' => $request_id]);
    }

    $timestamp = (string) $request->get_header('x-impact-timestamp');
    if ($timestamp === '' || !ctype_digit($timestamp)) {
        return new WP_Error('partner_auth_failed', 'Missing or invalid timestamp header', ['status' => 401, 'request_id' => $request_id]);
    }

    $now_ms = (int) round(microtime(true) * 1000);
    $delta = abs($now_ms - (int) $timestamp);
    if ($delta > 300000) {
        return new WP_Error('partner_auth_failed', 'Timestamp outside allowed window', ['status' => 401, 'request_id' => $request_id]);
    }

    $token = impactshop_partner_extract_bearer($request->get_header('authorization'));
    if ($token === '') {
        return new WP_Error('partner_auth_failed', 'Missing bearer token', ['status' => 401, 'request_id' => $request_id]);
    }

    $secrets = impactshop_partner_get_secrets();
    $partner_secret = $secrets[$partner_code] ?? null;
    if (!$partner_secret || (empty($partner_secret['api_key']) && empty($partner_secret['api_key_secondary'])) || (empty($partner_secret['hmac_secret']) && empty($partner_secret['hmac_secret_secondary']))) {
        return new WP_Error('partner_auth_failed', 'Partner secret not found', ['status' => 401, 'request_id' => $request_id]);
    }

    $key_id = (string) $request->get_header('x-impact-key-id');
    $primary_key_id = (string) ($partner_secret['key_id'] ?? '');
    $secondary_key_id = (string) ($partner_secret['key_id_secondary'] ?? ($partner_secret['secondary_key_id'] ?? ''));
    if ($key_id !== '' && $key_id !== $primary_key_id && $key_id !== $secondary_key_id) {
        return new WP_Error('partner_auth_failed', 'Key id mismatch', ['status' => 401, 'request_id' => $request_id]);
    }

    $valid_api_keys = array_filter([
        (string) ($partner_secret['api_key'] ?? ''),
        (string) ($partner_secret['api_key_secondary'] ?? ''),
    ]);
    $token_valid = false;
    foreach ($valid_api_keys as $api_key) {
        if (hash_equals($api_key, $token)) {
            $token_valid = true;
            break;
        }
    }
    if (!$token_valid) {
        return new WP_Error('partner_auth_failed', 'Invalid API key', ['status' => 401, 'request_id' => $request_id]);
    }

    $valid_hmac_secrets = array_filter([
        (string) ($partner_secret['hmac_secret'] ?? ''),
        (string) ($partner_secret['hmac_secret_secondary'] ?? ''),
    ]);
    $signature_valid = false;
    foreach ($valid_hmac_secrets as $hmac_secret) {
        $expected = 'sha256=' . hash_hmac('sha256', $raw_body, $hmac_secret);
        if (hash_equals($expected, $signature)) {
            $signature_valid = true;
            break;
        }
    }
    if (!$signature_valid) {
        return new WP_Error('partner_auth_failed', 'Invalid signature', ['status' => 401, 'request_id' => $request_id]);
    }

    return true;
}

function impactshop_partner_validate_transaction(array $payload)
{
    $required = ['partner_code', 'event_id', 'event_type', 'pseudo_id', 'ngo_code', 'amount_gross', 'currency', 'timestamp', 'payment_status'];
    foreach ($required as $field) {
        if (empty($payload[$field]) && $payload[$field] !== 0) {
            return new WP_Error('partner_payload_invalid', "Missing field: {$field}", ['status' => 400]);
        }
    }

    if (!preg_match('/^[a-z0-9\-]{3,64}$/', (string) $payload['partner_code'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid partner_code', ['status' => 400]);
    }
    if (!preg_match('/^[A-Za-z0-9_\-]{3,128}$/', (string) $payload['event_id'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid event_id', ['status' => 400]);
    }
    if (!preg_match('/^[a-z0-9]{10,12}$/', (string) $payload['pseudo_id'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid pseudo_id', ['status' => 400]);
    }
    if (!preg_match('/^[a-z0-9\-]{2,64}$/', (string) $payload['ngo_code'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid ngo_code', ['status' => 400]);
    }

    $event_type = (string) $payload['event_type'];
    if (!in_array($event_type, ['purchase', 'booking', 'retail'], true)) {
        return new WP_Error('partner_payload_invalid', 'Invalid event_type', ['status' => 400]);
    }

    $currency = strtoupper((string) $payload['currency']);
    if (!in_array($currency, ['HUF', 'EUR', 'USD'], true)) {
        return new WP_Error('partner_payload_invalid', 'Invalid currency', ['status' => 400]);
    }
    $payload['currency'] = $currency;

    $amount = (int) $payload['amount_gross'];
    if ($amount < 0 || $amount > 10000000) {
        return new WP_Error('partner_payload_invalid', 'Invalid amount_gross', ['status' => 400]);
    }

    $timestamp = (string) $payload['timestamp'];
    if ($timestamp === '' || strtotime($timestamp) === false) {
        return new WP_Error('partner_payload_invalid', 'Invalid timestamp', ['status' => 400]);
    }

    $payment_status = (string) $payload['payment_status'];
    if (!in_array($payment_status, ['paid', 'unpaid', 'refunded'], true)) {
        return new WP_Error('partner_payload_invalid', 'Invalid payment_status', ['status' => 400]);
    }

    return true;
}

function impactshop_partner_validate_discount(array $payload)
{
    $required = ['partner_code', 'pseudo_id', 'amount_gross', 'currency'];
    foreach ($required as $field) {
        if (empty($payload[$field]) && $payload[$field] !== 0) {
            return new WP_Error('partner_payload_invalid', "Missing field: {$field}", ['status' => 400]);
        }
    }

    if (!preg_match('/^[a-z0-9\-]{3,64}$/', (string) $payload['partner_code'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid partner_code', ['status' => 400]);
    }
    if (!preg_match('/^[a-z0-9]{10,12}$/', (string) $payload['pseudo_id'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid pseudo_id', ['status' => 400]);
    }

    $currency = strtoupper((string) $payload['currency']);
    if (!in_array($currency, ['HUF', 'EUR', 'USD'], true)) {
        return new WP_Error('partner_payload_invalid', 'Invalid currency', ['status' => 400]);
    }

    $amount = (int) $payload['amount_gross'];
    if ($amount < 0 || $amount > 10000000) {
        return new WP_Error('partner_payload_invalid', 'Invalid amount_gross', ['status' => 400]);
    }

    return true;
}

function impactshop_partner_validate_dispute(array $payload)
{
    $required = ['ledger_id', 'event_id', 'partner_code', 'reason'];
    foreach ($required as $field) {
        if (empty($payload[$field]) && $payload[$field] !== 0) {
            return new WP_Error('partner_payload_invalid', "Missing field: {$field}", ['status' => 400]);
        }
    }

    if (!preg_match('/^[a-z0-9\-]{3,64}$/', (string) $payload['partner_code'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid partner_code', ['status' => 400]);
    }
    if (!preg_match('/^[A-Za-z0-9_\-]{3,128}$/', (string) $payload['event_id'])) {
        return new WP_Error('partner_payload_invalid', 'Invalid event_id', ['status' => 400]);
    }

    return true;
}

function impactshop_partner_check_idempotency_conflict(string $idempotency_key, string $raw_body, string $request_id)
{
    global $wpdb;
    $tx_table = $wpdb->prefix . 'impact_partner_tx';

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, proof_hash, response_json, event_id, partner_code, ledger_id, created_at FROM {$tx_table} WHERE idempotency_key = %s LIMIT 1",
            $idempotency_key
        ),
        ARRAY_A
    );

    if (!$existing) {
        return true;
    }

    $ttl_sec = 86400;
    $config = impactshop_partner_get_config((string) ($existing['partner_code'] ?? ''));
    if ($config && isset($config['idempotency_ttl_sec'])) {
        $ttl_sec = (int) $config['idempotency_ttl_sec'];
    }
    if ($ttl_sec > 0 && !empty($existing['created_at'])) {
        $created_ts = strtotime((string) $existing['created_at']);
        if ($created_ts !== false && (time() - $created_ts) > $ttl_sec) {
            return true;
        }
    }

    $expected_hash = impactshop_partner_hash($raw_body);
    if (!hash_equals((string) $existing['proof_hash'], $expected_hash)) {
        return new WP_Error('partner_duplicate', 'Idempotency key conflict', ['status' => 409, 'request_id' => $request_id]);
    }

    $response_payload = null;
    if (!empty($existing['response_json'])) {
        $decoded = json_decode((string) $existing['response_json'], true);
        if (is_array($decoded)) {
            $response_payload = $decoded;
        }
    }

    if (!$response_payload) {
        $response_payload = impactshop_partner_build_response(
            'duplicate',
            (int) ($existing['ledger_id'] ?? 0),
            (string) ($existing['event_id'] ?? ''),
            (string) ($existing['partner_code'] ?? ''),
            'Duplicate event'
        );
    }

    impactshop_partner_audit('partner_tx_duplicate', (string) ($existing['partner_code'] ?? ''), (string) ($existing['event_id'] ?? ''), $request_id, 'duplicate');

    return rest_ensure_response($response_payload);
}

function impactshop_partner_build_response(string $status, int $ledger_id, string $event_id, string $partner_code, string $message): array
{
    return [
        'status' => $status,
        'ledger_id' => $ledger_id > 0 ? (string) $ledger_id : '',
        'event_id' => $event_id,
        'partner_code' => $partner_code,
        'message' => $message,
    ];
}

function impactshop_partner_store_response(int $tx_id, array $response, string $now): void
{
    global $wpdb;
    $tx_table = $wpdb->prefix . 'impact_partner_tx';
    $wpdb->update(
        $tx_table,
        [
            'response_json' => wp_json_encode($response),
            'updated_at' => $now,
        ],
        ['id' => $tx_id],
        ['%s', '%s'],
        ['%d']
    );
}

function impactshop_partner_status_from_payment(string $payment_status): string
{
    if ($payment_status === 'paid') {
        return 'approved';
    }
    if ($payment_status === 'refunded') {
        return 'declined';
    }
    return 'pending';
}

function impactshop_partner_insert_ledger(array $payload, string $partner_code, ?float $partner_max_discount, ?float $discount_rate, ?int $discount_amount): int
{
    global $wpdb;
    $ledger_table = $wpdb->prefix . 'impact_ledger';

    $amount_gross = (float) $payload['amount_gross'];
    $currency = strtoupper((string) $payload['currency']);
    $rate = 1.0;

    if ($currency !== 'HUF') {
        $rate = function_exists('impactshop_get_huf_rate') ? (float) impactshop_get_huf_rate() : 392.0;
    }

    $amount_huf = $currency === 'HUF' ? $amount_gross : ($amount_gross * $rate);
    $amount_net = $payload['amount_net'] ?? null;

    if ($amount_net === null && $discount_amount !== null) {
        $amount_net = max(0, $amount_gross - (float) $discount_amount);
    }

    $event_id = 'partner:' . $partner_code . ':' . $payload['event_id'];
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$ledger_table} WHERE source_ref = %s LIMIT 1", $event_id));
    if ($existing) {
        return (int) $existing;
    }

    $pseudo_id = strtolower((string) $payload['pseudo_id']);
    $ngo_slug = sanitize_title((string) $payload['ngo_code']);
    $shop_slug = sanitize_title($partner_code);
    $happened_at = (string) $payload['timestamp'];
    if (strtotime($happened_at) === false) {
        $happened_at = current_time('mysql');
    }

    $row = [
        'pseudo_id' => $pseudo_id,
        'ngo_slug' => $ngo_slug,
        'ngo_display' => $ngo_slug,
        'shop_slug' => $shop_slug,
        'shop_display' => $partner_code,
        'amount_huf' => (int) round($amount_huf),
        'channel' => 'partner',
        'status' => 'approved',
        'happened_at' => $happened_at,
        'source_ref' => $event_id,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'source' => 'shop',
        'platform' => 'partner',
        'ngo_code' => $payload['ngo_code'] ?? null,
        'advertiser_code' => $partner_code,
        'currency' => $currency,
        'amount_gross' => $amount_gross,
        'amount_net' => $amount_net,
        'exchange_rate' => $rate,
        'event_id' => $event_id,
        'meta' => wp_json_encode([
            'partner_code' => $partner_code,
            'event_id' => $payload['event_id'],
            'payment_status' => $payload['payment_status'],
            'discount_tier' => $payload['discount_tier'] ?? null,
            'partner_max_discount' => $partner_max_discount,
            'discount_rate' => $discount_rate,
            'discount_amount' => $discount_amount,
        ]),
        'approved_at' => current_time('mysql'),
    ];

    $inserted = $wpdb->insert($ledger_table, $row);
    if (!$inserted) {
        return 0;
    }

    return (int) $wpdb->insert_id;
}

function impactshop_partner_mark_ledger_rejected(int $ledger_id, string $reason): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';

    if (function_exists('impact_ledger_update_status')) {
        impact_ledger_update_status($ledger_id, 'rejected', $reason);
        return;
    }

    $wpdb->update(
        $table,
        [
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $ledger_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
}

function impactshop_partner_get_config(string $partner_code): ?array
{
    global $wpdb;
    if ($partner_code === '') {
        return null;
    }

    $table = $wpdb->prefix . 'impact_partner_config';
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE partner_code = %s LIMIT 1", $partner_code),
        ARRAY_A
    );

    return $row ?: null;
}

function impactshop_partner_get_secrets(): array
{
    $raw = getenv('IMPACT_PARTNER_SECRETS');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function impactshop_partner_extract_bearer(?string $auth_header): string
{
    if (!$auth_header) {
        return '';
    }

    if (stripos($auth_header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($auth_header, 7));
}

function impactshop_partner_request_id(): string
{
    return 'req_' . wp_generate_uuid4();
}

function impactshop_partner_error_response(WP_Error $error, string $request_id): WP_REST_Response
{
    $details = $error->get_error_data();
    $status = 500;
    if (is_array($details) && isset($details['status'])) {
        $status = (int) $details['status'];
    }
    $payload = [
        'code' => $error->get_error_code(),
        'message' => $error->get_error_message(),
        'details' => $details,
        'request_id' => $request_id,
    ];

    return new WP_REST_Response($payload, $status);
}

function impactshop_partner_handle_error(WP_Error $error, string $request_id, string $event, ?array $payload = null, ?string $raw_body = null): WP_REST_Response
{
    $partner_code = is_array($payload) ? (string) ($payload['partner_code'] ?? '') : '';
    $event_id = is_array($payload) ? (string) ($payload['event_id'] ?? '') : '';
    impactshop_partner_audit_failure($event, $partner_code, $event_id, $request_id, $error, $raw_body);

    return impactshop_partner_error_response($error, $request_id);
}

function impactshop_partner_hash(string $value): string
{
    return hash('sha256', $value);
}

function impactshop_partner_load_env(string $path): void
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

function impactshop_partner_parse_csv(string $value): array
{
    if ($value === '') {
        return [];
    }

    $parts = array_map('trim', explode(',', $value));
    $parts = array_filter($parts, static function ($item) {
        return $item !== '';
    });

    return array_values($parts);
}

function impactshop_partner_rate_limit(string $partner_code, string $endpoint, string $request_id)
{
    if ($partner_code === '') {
        return true;
    }

    $limit = (int) apply_filters('impactshop_partner_rate_limit_rpm', 60, $partner_code, $endpoint);
    if ($limit <= 0) {
        return true;
    }

    $key = 'impactshop_partner_rl_' . sanitize_key($partner_code . '_' . $endpoint);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return new WP_Error('partner_rate_limited', 'Rate limit exceeded', ['status' => 429, 'request_id' => $request_id]);
    }

    set_transient($key, $count + 1, 60);

    return true;
}

function impactshop_partner_level_for_pseudo(string $pseudo_id): string
{
    if (class_exists('Sharity_Level_Manager')) {
        $manager = new Sharity_Level_Manager();
        if (method_exists($manager, 'calculate_level_for_pseudo')) {
            return (string) $manager->calculate_level_for_pseudo($pseudo_id);
        }
    }

    return 'basic';
}

function impactshop_partner_tier_multiplier(string $tier): float
{
    $tier = strtolower($tier);
    $map = [
        'legend' => 1.00,
        'platinum' => 0.90,
        'gold' => 0.80,
        'silver' => 0.70,
        'bronze' => 0.60,
        'basic' => 0.50,
    ];

    return $map[$tier] ?? 0.50;
}

function impactshop_partner_round_rate(float $rate): float
{
    if ($rate <= 0) {
        return 0.0;
    }
    $step = 0.005;
    return round($rate / $step) * $step;
}

function impactshop_partner_discount_explain(string $tier, float $multiplier): string
{
    $percent = (int) round($multiplier * 100);
    return ucfirst($tier) . " szint -> {$percent}% a max kedvezmenybol";
}

function impactshop_partner_nullable_float($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    return (float) $value;
}

function impactshop_partner_nullable_int($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return (int) $value;
}

function impactshop_partner_nullable_string($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    return (string) $value;
}

function impactshop_partner_audit(string $event, string $partner_code, string $event_id, string $request_id, string $status, array $context = []): void
{
    $upload = wp_upload_dir();
    if (empty($upload['basedir'])) {
        return;
    }

    $path = trailingslashit($upload['basedir']) . 'impactshop-partner-audit.log';
    $entry = [
        'ts' => gmdate('c'),
        'event' => $event,
        'partner_code' => $partner_code,
        'event_id' => $event_id,
        'request_id' => $request_id,
        'status' => $status,
    ];

    if (!empty($context)) {
        $entry['context'] = $context;
    }

    @file_put_contents($path, wp_json_encode($entry) . "\n", FILE_APPEND);
}

function impactshop_partner_audit_failure(string $event, string $partner_code, string $event_id, string $request_id, WP_Error $error, ?string $raw_body = null): void
{
    $details = $error->get_error_data();
    $status = is_array($details) && isset($details['status']) ? (int) $details['status'] : 0;

    $context = [
        'error_code' => $error->get_error_code(),
        'error_message' => $error->get_error_message(),
        'status_code' => $status,
        'ip' => impactshop_partner_request_ip(),
    ];

    if ($raw_body !== null && $raw_body !== '') {
        $context['payload_hash'] = impactshop_partner_hash($raw_body);
    }

    impactshop_partner_audit($event, $partner_code, $event_id, $request_id, 'error', $context);
}

function impactshop_partner_request_ip(): string
{
    $candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $value));
                $value = (string) ($parts[0] ?? '');
            }
            return $value;
        }
    }

    return '';
}
