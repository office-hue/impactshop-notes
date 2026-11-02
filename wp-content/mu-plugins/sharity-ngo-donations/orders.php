<?php
/** D2c internal store; explicitly loaded, no routes/hooks/SDK loading or migration.
 * Public callers must first use the canonical Web Session authority. Subject is
 * that authority's opaque subject_ref, never a browser-provided owner ID.
 * Prepared -> attempted is irreversible. Unknown outcomes require readback or
 * verified webhook reconciliation, never a fresh provider POST/key/claim reset.
 */
declare(strict_types=1);
const SHARITY_DONATION_ORDERS_ENABLED = 'sharity_donation_orders_enabled';
const SHARITY_DONATION_ORDERS_SCHEMA_OPTION = 'sharity_donation_orders_schema_v1';
function sharity_donation_order_table(): string { global $wpdb; return $wpdb->prefix . 'sharity_donation_orders_v1'; }

function sharity_donation_order_owner(string $subject): void
{
    if (!preg_match('/^hmac-sha256:[a-f0-9]{64}$/D', $subject)) { throw new RuntimeException('INVALID_OWNER'); }
}

/** Internal raw row; all lookups include the authenticated owner. */
function sharity_donation_order_read(string $subject, string $column, string $value): ?array
{
    sharity_donation_order_owner($subject);
    if (!in_array($column, ['order_id', 'quote_id', 'request_key_hash'], true)) { throw new RuntimeException('INVALID_REQUEST'); }
    global $wpdb; $table = sharity_donation_order_table();
    try {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = %s AND subject_ref = %s LIMIT 1", $value, $subject), ARRAY_A);
        if ($wpdb->last_error !== '') { throw new RuntimeException(); }
        return is_array($row) ? $row : null;
    } catch (Throwable $e) { throw new RuntimeException('ORDER_READ_UNAVAILABLE'); }
}

/** No provider call may depend on an uncommitted surrounding transaction. */
function sharity_donation_order_autocommit(): void
{
    global $wpdb;
    try {
        $r = $wpdb->get_row('SELECT @@autocommit AS ac, @@in_transaction AS tx', ARRAY_A);
        if ($wpdb->last_error !== '' || (int) ($r['ac'] ?? 0) !== 1 || (int) ($r['tx'] ?? 1) !== 0) { throw new RuntimeException(); }
    } catch (Throwable $e) { throw new RuntimeException('ORDER_TRANSACTION_UNSAFE'); }
}

function sharity_donation_order_decode(array $row): array
{
    try {
        $e = json_decode($row['envelope_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($e) || ($e['order_id'] ?? null) !== $row['order_id'] || ($e['quote_id'] ?? null) !== $row['quote_id']
            || $e !== Sharity_Stripe_Checkout_Adapter::envelope($e['snapshot'] ?? null, $e['order_id'] ?? null, $e['quote_id'] ?? null, $e['created_at'] ?? null, $e['expires_at'] ?? null, $e['locale'] ?? null)) { throw new RuntimeException(); }
        return $e;
    } catch (Throwable $error) { throw new RuntimeException('ORDER_DATA_UNAVAILABLE'); }
}

function sharity_donation_order_activation(array $snapshot, int $now): void
{
    if (get_option(SHARITY_DONATION_ORDERS_ENABLED, '0') !== '1'
        || get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('ORDERS_DISABLED'); }
    $binding = sharity_donation_quote_binding($snapshot, $now);
    $bounds = $binding['currencies'][$snapshot['currency']] ?? null;
    if ($binding === null || $binding['account_id'] !== $snapshot['account_id']
        || $binding['binding_version'] !== $snapshot['binding_version'] || $binding['livemode'] !== $snapshot['livemode']
        || $bounds === null || (int) $snapshot['amount_minor'] < (int) $bounds['min_minor']
        || (int) $snapshot['amount_minor'] > (int) $bounds['max_minor']) { throw new RuntimeException('NGO_UNAVAILABLE'); }
}

/** One quote -> one durable order. Same owner/key/input reads the original row. */
function sharity_donation_order_prepare(string $subject, string $quoteId, string $key, string $locale, int $now): array
{
    sharity_donation_order_owner($subject);
    if (!preg_match('/^dq_[a-f0-9]{32}$/D', $quoteId) || !preg_match('/^[A-Za-z0-9._:-]{16,80}$/D', $key)
        || !in_array($locale, ['hu', 'en', 'de', 'fr'], true) || $now < 1) { throw new RuntimeException('INVALID_REQUEST'); }
    $keyHash = hash('sha256', "sharity-donation-order-v1\0" . $subject . "\0" . $key);
    $digest = hash('sha256', json_encode(['quote_id' => $quoteId, 'locale' => $locale]));
    $existing = sharity_donation_order_read($subject, 'request_key_hash', $keyHash);
    if ($existing !== null) {
        if (!hash_equals($existing['request_digest'], $digest)) { throw new RuntimeException('IDEMPOTENCY_CONFLICT'); }
        sharity_donation_order_decode($existing); return $existing;
    }
    $quoted = sharity_donation_order_read($subject, 'quote_id', $quoteId);
    if ($quoted !== null) {
        // A same-key winner can commit between the key lookup and this read.
        if (!hash_equals($quoted['request_key_hash'], $keyHash)) { throw new RuntimeException('QUOTE_ALREADY_ORDERED'); }
        if (!hash_equals($quoted['request_digest'], $digest)) { throw new RuntimeException('IDEMPOTENCY_CONFLICT'); }
        sharity_donation_order_decode($quoted); return $quoted;
    }
    if (get_option(SHARITY_DONATION_ORDERS_ENABLED, '0') !== '1'
        || get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('ORDERS_DISABLED'); }
    global $wpdb; $quotes = sharity_donation_quote_table(); $table = sharity_donation_order_table();
    try {
        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$quotes}` WHERE quote_id = %s AND subject_ref = %s LIMIT 1", $quoteId, $subject), ARRAY_A);
        if ($wpdb->last_error !== '') { throw new RuntimeException(); }
    } catch (Throwable $e) { throw new RuntimeException('QUOTE_READ_UNAVAILABLE'); }
    if (!is_array($quote)) { throw new RuntimeException('QUOTE_NOT_FOUND'); }
    $expiry = strtotime($quote['expires_at_utc'] . ' UTC');
    if ($expiry === false || $expiry <= $now) { throw new RuntimeException('QUOTE_EXPIRED'); }
    try {
        $snapshot = json_decode($quote['snapshot_json'], true, 32, JSON_THROW_ON_ERROR);
        $orderId = 'do_' . bin2hex(random_bytes(16));
        $envelope = Sharity_Stripe_Checkout_Adapter::envelope($snapshot, $orderId, $quoteId, $now, $now + 3600, $locale);
    } catch (Throwable $e) { throw new RuntimeException('DONATION_INVALID_ENVELOPE'); }
    sharity_donation_order_activation($snapshot, $now);
    sharity_donation_order_autocommit();
    // INSERT SELECT checks the quote is still present/unchanged/unexpired at the
    // DB write. Unique keys arbitrate races; even an apparent success is read back.
    try {
        $wpdb->query($wpdb->prepare("INSERT INTO `{$table}`
            (order_id, quote_id, request_key_hash, request_digest, subject_ref, locale, envelope_json,
             created_at_utc, expires_at_utc, state, result_json)
            SELECT %s, quote_id, %s, %s, subject_ref, %s, %s, %s, %s, 'prepared', ''
            FROM `{$quotes}` WHERE quote_id = %s AND subject_ref = %s
              AND snapshot_json = %s AND expires_at_utc > UTC_TIMESTAMP()",
            $orderId, $keyHash, $digest, $locale, json_encode($envelope, JSON_THROW_ON_ERROR),
            gmdate('Y-m-d H:i:s', $now), gmdate('Y-m-d H:i:s', $now + 3600), $quoteId, $subject, $quote['snapshot_json']));
    } catch (Throwable $e) { /* The INSERT may have committed; read back its immutable key. */ }
    $stored = sharity_donation_order_read($subject, 'request_key_hash', $keyHash);
    if ($stored !== null) {
        if (!hash_equals($stored['request_digest'], $digest)) { throw new RuntimeException('IDEMPOTENCY_CONFLICT'); }
        sharity_donation_order_decode($stored); return $stored;
    }
    $quoted = sharity_donation_order_read($subject, 'quote_id', $quoteId);
    if ($quoted !== null) {
        // A same-key winner can commit between the key lookup and this read.
        if (!hash_equals($quoted['request_key_hash'], $keyHash)) { throw new RuntimeException('QUOTE_ALREADY_ORDERED'); }
        if (!hash_equals($quoted['request_digest'], $digest)) { throw new RuntimeException('IDEMPOTENCY_CONFLICT'); }
        sharity_donation_order_decode($quoted); return $quoted;
    }
    throw new RuntimeException('ORDER_UNCONFIRMED');
}

/** Strict persisted projection; no raw provider response/error or paid claim. */
function sharity_donation_order_result(array $result, array $envelope): array
{
    $keys = array_keys($result); sort($keys);
    if ($keys !== ['checkout_state', 'checkout_url', 'expires_at', 'payment_verified', 'provider_payment_status', 'session_id']
        || !is_string($result['session_id']) || strlen($result['session_id']) > 255
        || !preg_match($envelope['snapshot']['livemode'] ? '/^cs_live_[A-Za-z0-9]+$/D' : '/^cs_test_[A-Za-z0-9]+$/D', $result['session_id'])
        || !in_array($result['checkout_state'], ['open', 'complete', 'expired'], true)
        || !in_array($result['provider_payment_status'], ['paid', 'unpaid', 'no_payment_required'], true)
        || $result['expires_at'] !== $envelope['expires_at'] || $result['payment_verified'] !== false) { throw new RuntimeException('ORDER_RESULT_INVALID'); }
    $url = $result['checkout_url'];
    if ($result['checkout_state'] === 'open') {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { throw new RuntimeException('ORDER_RESULT_INVALID'); }
        $p = parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || ($p['host'] ?? '') !== 'checkout.stripe.com'
            || isset($p['user']) || isset($p['pass']) || isset($p['port']) || !str_starts_with($p['path'] ?? '', '/c/pay/')) { throw new RuntimeException('ORDER_RESULT_INVALID'); }
    } elseif ($url !== null) { throw new RuntimeException('ORDER_RESULT_INVALID'); }
    return $result;
}

/** Internal one-time result persistence, not an HTTP/browser supplied operation. */
function sharity_donation_order_save_result(string $subject, string $orderId, array $result, int $now): bool
{
    $row = sharity_donation_order_read($subject, 'order_id', $orderId);
    if ($row === null) { throw new RuntimeException('ORDER_NOT_FOUND'); }
    $result = sharity_donation_order_result($result, sharity_donation_order_decode($row));
    if ($row['state'] === 'session_recorded') {
        if (json_decode($row['result_json'], true) !== $result) { throw new RuntimeException('ORDER_RESULT_CONFLICT'); }
        return true;
    }
    if ($row['state'] !== 'attempted') { throw new RuntimeException('ORDER_NOT_ATTEMPTED'); }
    sharity_donation_order_autocommit();
    global $wpdb; $table = sharity_donation_order_table();
    try {
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET state = 'session_recorded', provider_session_id = %s, result_json = %s, session_recorded_at_utc = %s
            WHERE order_id = %s AND subject_ref = %s AND state = 'attempted' AND provider_session_id IS NULL AND result_json = ''",
            $result['session_id'], json_encode($result, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s', $now), $orderId, $subject));
    } catch (Throwable $e) { /* Uncertain write: read back; never POST again. */ }
    $saved = sharity_donation_order_read($subject, 'order_id', $orderId);
    if ($saved !== null && $saved['state'] === 'session_recorded') {
        if ($saved['provider_session_id'] !== $result['session_id'] || json_decode($saved['result_json'], true) !== $result) { throw new RuntimeException('ORDER_RESULT_CONFLICT'); }
        return true;
    }
    return false;
}

function sharity_donation_order_status(string $subject, string $orderId, int $now): array
{
    if (!preg_match('/^do_[a-f0-9]{32}$/D', $orderId)) { throw new RuntimeException('INVALID_REQUEST'); }
    $row = sharity_donation_order_read($subject, 'order_id', $orderId);
    if ($row === null) { throw new RuntimeException('ORDER_NOT_FOUND'); }
    $e = sharity_donation_order_decode($row);
    if (!in_array($row['state'], ['prepared', 'attempted', 'session_recorded'], true)) { throw new RuntimeException('ORDER_DATA_UNAVAILABLE'); }
    $result = [];
    if ($row['state'] === 'session_recorded') {
        try { $result = sharity_donation_order_result(json_decode($row['result_json'], true, 32, JSON_THROW_ON_ERROR), $e); }
        catch (Throwable $error) { throw new RuntimeException('ORDER_DATA_UNAVAILABLE'); }
        if ($row['provider_session_id'] !== $result['session_id']) { throw new RuntimeException('ORDER_DATA_UNAVAILABLE'); }
    }
    return ['id' => $orderId, 'ngo_slug' => $e['snapshot']['ngo_slug'], 'donation_mode' => $e['snapshot']['donation_mode'],
        'currency' => $e['snapshot']['currency'], 'amount_minor' => $e['snapshot']['amount_minor'], 'votes' => $e['snapshot']['votes'],
        'policy_version' => $e['snapshot']['policy_version'], 'state' => $row['state'] === 'attempted' ? 'reconciliation_required' : $row['state'],
        'checkout_state' => $result['checkout_state'] ?? null,
        'checkout_url' => ($result['checkout_state'] ?? null) === 'open' && $e['expires_at'] > $now ? $result['checkout_url'] : null,
        'payment_verified' => false];
}

/** Owns the only createSession call. No claim lease/reset or retry exists. */
function sharity_donation_order_dispatch(string $subject, string $orderId, Sharity_Stripe_Checkout_Adapter $adapter, int $now): array
{
    if (!preg_match('/^do_[a-f0-9]{32}$/D', $orderId)) { throw new RuntimeException('INVALID_REQUEST'); }
    $row = sharity_donation_order_read($subject, 'order_id', $orderId);
    if ($row === null) { throw new RuntimeException('ORDER_NOT_FOUND'); }
    $e = sharity_donation_order_decode($row);
    if ($row['state'] !== 'prepared') { return sharity_donation_order_status($subject, $orderId, $now); }
    sharity_donation_order_activation($e['snapshot'], $now);
    if ($e['created_at'] > $now || $e['expires_at'] < $now + 1800) { throw new RuntimeException('DONATION_SESSION_WINDOW_EXPIRED'); }
    sharity_donation_order_autocommit();
    global $wpdb; $table = sharity_donation_order_table();
    try {
        $claimed = $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET state = 'attempted', attempted_at_utc = %s
            WHERE order_id = %s AND subject_ref = %s AND state = 'prepared' AND envelope_json = %s",
            gmdate('Y-m-d H:i:s', $now), $orderId, $subject, $row['envelope_json']));
    } catch (Throwable $error) { $claimed = false; }
    // Even committed-but-lost ACK does NOT authorize this process to POST.
    if ($claimed !== 1) { return sharity_donation_order_status($subject, $orderId, $now); }
    $confirmed = sharity_donation_order_read($subject, 'order_id', $orderId);
    if ($confirmed === null || $confirmed['state'] !== 'attempted' || $confirmed['envelope_json'] !== $row['envelope_json']) { throw new RuntimeException('ORDER_CLAIM_UNCONFIRMED'); }
    sharity_donation_order_autocommit();
    try { $result = $adapter->createSession($e); }
    catch (Throwable $error) { return sharity_donation_order_status($subject, $orderId, $now); }
    sharity_donation_order_save_result($subject, $orderId, $result, $now);
    return sharity_donation_order_status($subject, $orderId, $now);
}
function sharity_donation_order_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '') !== '0') { return false; }
        global $wpdb;
        $table = sharity_donation_order_table();
        $result = $wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            quote_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            request_key_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            request_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            subject_ref varchar(76) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            locale char(2) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            envelope_json longtext NOT NULL,
            provider_session_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
            created_at_utc datetime NOT NULL,
            expires_at_utc datetime NOT NULL,
            state varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            attempted_at_utc datetime NULL,
            session_recorded_at_utc datetime NULL,
            result_json longtext NOT NULL,
            PRIMARY KEY (order_id),
            UNIQUE KEY quote_id (quote_id),
            UNIQUE KEY request_key_hash (request_key_hash),
            UNIQUE KEY provider_session_id (provider_session_id),
            KEY state (state),
            KEY subject_ref (subject_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
        if ($result === false) { return false; }
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB'
            || !is_array($columns) || !is_array($indexes)) { return false; }
        $expected = [
            'order_id' => ['char(35)', 'ascii_bin'], 'quote_id' => ['char(35)', 'ascii_bin'],
            'request_key_hash' => ['char(64)', 'ascii_bin'], 'request_digest' => ['char(64)', 'ascii_bin'],
            'subject_ref' => ['varchar(76)', 'ascii_bin'], 'locale' => ['char(2)', 'ascii_bin'],
            'envelope_json' => ['longtext', 'utf8mb4_bin'], 'provider_session_id' => ['varchar(255)', 'ascii_bin'],
            'created_at_utc' => ['datetime', null], 'expires_at_utc' => ['datetime', null],
            'state' => ['varchar(16)', 'ascii_bin'], 'attempted_at_utc' => ['datetime', null],
            'session_recorded_at_utc' => ['datetime', null], 'result_json' => ['longtext', 'utf8mb4_bin'],
        ];
        if (count($columns) !== count($expected)) { return false; }
        foreach ($columns as $column) {
            if (!isset($expected[$column['Field']])) { return false; }
            $e = $expected[$column['Field']];
            if ($column['Type'] !== $e[0] || $column['Collation'] !== $e[1]
                || $column['Null'] !== 'NO' && $column['Null'] !== 'YES') { return false; }
            // provider_session_id, attempted_at_utc, session_recorded_at_utc are nullable.
            $nullable = in_array($column['Field'], ['provider_session_id', 'attempted_at_utc', 'session_recorded_at_utc'], true);
            if (($column['Null'] === 'NO') === $nullable) { return false; }
            if ($column['Default'] !== null || $column['Extra'] !== '') { return false; }
        }
        $topology = array_map(static function ($index): array {
            return [$index['Key_name'], (int) $index['Seq_in_index'], $index['Column_name'], (int) $index['Non_unique'], $index['Sub_part']];
        }, $indexes);
        sort($topology);
        $expectedTopology = [
            ['PRIMARY', 1, 'order_id', 0, null], ['provider_session_id', 1, 'provider_session_id', 0, null],
            ['quote_id', 1, 'quote_id', 0, null], ['request_key_hash', 1, 'request_key_hash', 0, null],
            ['state', 1, 'state', 1, null], ['subject_ref', 1, 'subject_ref', 1, null],
        ];
        sort($expectedTopology);
        if ($topology !== $expectedTopology) { return false; }
        update_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_ORDERS_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}
