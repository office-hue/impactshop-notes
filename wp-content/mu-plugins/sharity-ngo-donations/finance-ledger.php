<?php
/** Immutable, sanitized provider observations. Never a financial posting or settlement. */
declare(strict_types=1);

const SHARITY_DONATION_FINANCE_SCHEMA_OPTION = 'sharity_donation_finance_schema_v1';
const SHARITY_DONATION_FINANCE_MAX_BYTES = 4 * 1024 * 1024;

function sharity_donation_finance_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sharity_donation_finance_v1';
}

/** Explicit CLI migration only; incompatible tables are never altered or repaired here. */
function sharity_donation_finance_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '') !== '0') { return false; }
        global $wpdb;
        $table = sharity_donation_finance_table();
        if ($wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            account_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            livemode tinyint(1) NOT NULL,
            donation_mode varchar(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            provider_session_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            canonical_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            observed_at_utc datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_digest (order_id, digest)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin") === false || $wpdb->last_error !== '') { return false; }
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB') { return false; }
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($columns)) { return false; }
        $expected = [
            'id' => ['bigint unsigned', null], 'order_id' => ['char(35)', 'ascii_bin'],
            'account_id' => ['varchar(128)', 'ascii_bin'], 'livemode' => ['tinyint(1)', null],
            'donation_mode' => ['varchar(9)', 'ascii_bin'], 'provider_session_id' => ['varchar(255)', 'ascii_bin'],
            'canonical_json' => ['longtext', 'utf8mb4_bin'], 'digest' => ['char(64)', 'ascii_bin'],
            'observed_at_utc' => ['datetime', null],
        ];
        if (count($columns) !== count($expected)) { return false; }
        foreach ($columns as $column) {
            $name = $column['Field'];
            if (!isset($expected[$name])) { return false; }
            $type = $column['Type'];
            if ($name === 'id') { $type = preg_replace('/^bigint\([0-9]+\) unsigned$/D', 'bigint unsigned', $type); }
            if ($type !== $expected[$name][0] || $column['Collation'] !== $expected[$name][1]
                || $column['Null'] !== 'NO' || $column['Default'] !== null
                || $column['Extra'] !== ($name === 'id' ? 'auto_increment' : '')) { return false; }
        }
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($indexes)) { return false; }
        $topology = array_map(static fn($i) => [$i['Key_name'], (int) $i['Seq_in_index'], $i['Column_name'],
            (int) $i['Non_unique'], $i['Sub_part'], $i['Index_type']], $indexes);
        sort($topology);
        $expectedTopology = [['PRIMARY', 1, 'id', 0, null, 'BTREE'],
            ['order_digest', 1, 'order_id', 0, null, 'BTREE'], ['order_digest', 2, 'digest', 0, null, 'BTREE']];
        sort($expectedTopology);
        if ($topology !== $expectedTopology) { return false; }
        update_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

/** Provider resource lists are sets, not a timeline. Non-resource lists keep their order. */
function sharity_donation_finance_canonical($value)
{
    if (!is_array($value)) { return $value; }
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) { $value[$key] = sharity_donation_finance_canonical($item); }
        return $value;
    }
    $value = array_map('sharity_donation_finance_canonical', $value);
    if ($value !== [] && count(array_filter($value, static fn($item) => is_array($item) && is_string($item['id'] ?? null))) === count($value)) {
        usort($value, static fn($a, $b) => strcmp($a['id'], $b['id']));
        for ($i = 1; $i < count($value); $i++) {
            if ($value[$i - 1]['id'] === $value[$i]['id']) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
        }
    }
    return $value;
}

function sharity_donation_finance_read(string $orderId, string $digest): ?array
{
    global $wpdb;
    $table = sharity_donation_finance_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE order_id = %s AND digest = %s", $orderId, $digest), ARRAY_A);
    if ($wpdb->last_error !== '' || ($row !== null && !is_array($row))) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
    return $row;
}

/** A successful INSERT response alone is insufficient; validate the complete persisted fact. */
function sharity_donation_finance_receipt(?array $row, array $expected): array
{
    if ($row === null) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
    }
    $id = $row['id'] ?? null;
    $time = $row['observed_at_utc'] ?? null;
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,19}$/D', $id) || !is_string($time)
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $time)
        || !hash_equals($row['digest'], hash('sha256', $row['canonical_json']))) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time, new DateTimeZone('UTC'));
    if ($date === false || $date->format('Y-m-d H:i:s') !== $time) { throw new RuntimeException('DONATION_FINANCE_UNAVAILABLE'); }
    return ['id' => $id, 'order_id' => $row['order_id'], 'digest' => $row['digest'], 'observed_at_utc' => $time];
}

function sharity_donation_finance_record(string $orderId, Sharity_Stripe_Checkout_Adapter $adapter, int $observedAt): array
{
    try {
        if (get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '') !== '1'
            || !preg_match('/^do_[a-f0-9]{32}$/D', $orderId) || $observedAt < 1 || $observedAt > 253402300799) { throw new RuntimeException(); }
        $row = sharity_donation_webhook_order($orderId);
        $status = sharity_donation_order_status($row['subject_ref'], $orderId, $observedAt);
        if ($row['state'] !== 'session_recorded' || $status['state'] !== 'session_recorded') { throw new RuntimeException(); }
        $envelope = sharity_donation_order_decode($row);
        sharity_donation_order_autocommit();
        $evidence = $adapter->retrieveAdjustmentEvidence($row['provider_session_id'], $envelope);
        if (($evidence['evidence_version'] ?? null) !== 1 || ($evidence['scope'] ?? null) !== 'observed_adjustment_resources') { throw new RuntimeException(); }
        foreach (['payment_verified', 'allocation_eligible', 'atomic_snapshot', 'settlement_reconciled'] as $key) {
            if (($evidence[$key] ?? null) !== false) { throw new RuntimeException(); }
        }
        $original = $evidence['original'] ?? [];
        $snapshot = $envelope['snapshot'];
        $identity = ['evidence_version' => 1, 'scope' => 'original_charge_only', 'order_id' => $orderId,
            'session_id' => $row['provider_session_id'], 'account_id' => $snapshot['account_id'],
            'livemode' => $snapshot['livemode'], 'donation_mode' => $snapshot['donation_mode'],
            'payment_verified' => false, 'allocation_eligible' => false,
            'payment' => ['amount_minor' => $snapshot['amount_minor'], 'currency' => $snapshot['currency']]];
        foreach ($identity as $key => $value) { if (($original[$key] ?? null) !== $value) { throw new RuntimeException(); } }
        // The adapter already projects allowed fields; never persist its raw Stripe input.
        $json = json_encode(sharity_donation_finance_canonical($evidence), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        if (strlen($json) > SHARITY_DONATION_FINANCE_MAX_BYTES) { throw new RuntimeException('DONATION_FINANCE_EVIDENCE_TOO_LARGE'); }
        $digest = hash('sha256', $json);
        $expected = ['order_id' => $orderId, 'account_id' => $snapshot['account_id'], 'livemode' => $snapshot['livemode'] ? '1' : '0',
            'donation_mode' => $snapshot['donation_mode'], 'provider_session_id' => $row['provider_session_id'],
            'canonical_json' => $json, 'digest' => $digest];
        sharity_donation_order_autocommit();
        $existing = sharity_donation_finance_read($orderId, $digest);
        if ($existing !== null) { return sharity_donation_finance_receipt($existing, $expected); }
        global $wpdb;
        $table = sharity_donation_finance_table();
        $insert = $expected + ['observed_at_utc' => gmdate('Y-m-d H:i:s', $observedAt)];
        $sql = $wpdb->prepare("INSERT INTO `{$table}` (`" . implode('`,`', array_keys($insert)) . '`) VALUES ('
            . implode(',', array_fill(0, count($insert), '%s')) . ')', ...array_values($insert));
        $packet = $wpdb->get_row('SELECT @@max_allowed_packet AS packet_bytes', ARRAY_A);
        if ($wpdb->last_error !== '' || !is_string($sql) || !ctype_digit((string) ($packet['packet_bytes'] ?? ''))) { throw new RuntimeException(); }
        // Check the actual escaped query plus protocol headroom before sending it.
        if ((int) $packet['packet_bytes'] <= strlen($sql) + 1024) { throw new RuntimeException('DONATION_FINANCE_PACKET_TOO_SMALL'); }
        try { $wpdb->query($sql); } catch (Throwable $uncertain) { /* Read the unique identity; never blindly repeat INSERT. */ }
        return sharity_donation_finance_receipt(sharity_donation_finance_read($orderId, $digest), $expected);
    } catch (Throwable $error) {
        $code = in_array($error->getMessage(), ['DONATION_FINANCE_EVIDENCE_TOO_LARGE', 'DONATION_FINANCE_PACKET_TOO_SMALL'], true)
            ? $error->getMessage() : 'DONATION_FINANCE_UNAVAILABLE';
        throw new RuntimeException($code);
    }
}
