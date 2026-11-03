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

/** Deterministic, read-only inventory of persisted observations; never a posting proposal. */
function sharity_donation_finance_json($value): string
{
    return json_encode(sharity_donation_finance_canonical($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
}

function sharity_donation_finance_id($value, string $prefix): string
{
    if (!is_string($value) || !preg_match('/^' . $prefix . '_[A-Za-z0-9]{1,240}$/D', $value)) { throw new RuntimeException(); }
    return $value;
}

/** Adapter amounts are bounded; integer arithmetic only, including signed corrections. */
function sharity_donation_finance_minor($value): int
{
    if (PHP_INT_SIZE < 8 || !is_string($value) || !preg_match('/^(0|-?[1-9][0-9]{0,14})$/D', $value)) { throw new RuntimeException(); }
    return (int) $value;
}

function sharity_donation_finance_movement(array $b, string $source, string $role, ?string $account, array $identity): array
{
    $facts = $identity + ['provider' => 'stripe', 'account_id' => $account,
        'balance_transaction_id' => sharity_donation_finance_id($b['id'] ?? null, 'txn'), 'source_id' => $source,
        'movement_role' => $role, 'provider_type' => $b['type'] ?? null, 'currency' => $b['currency'] ?? null];
    if (!in_array($facts['currency'], ['HUF', 'EUR', 'USD', 'GBP'], true)) { throw new RuntimeException(); }
    if ($account !== null && !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $account)) { throw new RuntimeException(); }
    $types = ['refund' => ['refund', 'payment_refund'], 'refund_reversal' => ['refund_failure', 'payment_failure_refund', 'payment_refund', 'adjustment'],
        'dispute' => ['adjustment'], 'application_fee_refund' => ['application_fee_refund'],
        'fee_refund_credit' => ['application_fee_refund', 'adjustment']];
    if (isset($types[$role])) {
        if (($b['source_id'] ?? null) !== $source || ($b['scope'] ?? null) !== ($role === 'application_fee_refund' ? 'platform' : 'connected')
            || !in_array($facts['provider_type'], $types[$role], true)) { throw new RuntimeException(); }
    } elseif ($facts['provider_type'] !== null || isset($b['source_id']) || isset($b['scope'])) { throw new RuntimeException(); }
    foreach (['gross_minor', 'total_fee_minor', 'net_minor', 'application_fee_minor', 'processing_cost_minor'] as $key) {
        sharity_donation_finance_minor($b[$key] ?? null); $facts[$key] = $b[$key];
    }
    if ((int) $facts['net_minor'] !== (int) $facts['gross_minor'] - (int) $facts['total_fee_minor']
        || (int) $facts['processing_cost_minor'] !== (int) $facts['total_fee_minor'] - (int) $facts['application_fee_minor']
        || !is_array($b['fee_details'] ?? null) || !array_is_list($b['fee_details'])) { throw new RuntimeException(); }
    $gross = (int) $facts['gross_minor'];
    if ((in_array($role, ['charge', 'application_fee', 'refund_reversal', 'fee_refund_credit'], true) && $gross <= 0)
        || (in_array($role, ['refund', 'application_fee_refund'], true) && $gross >= 0)
        || (in_array($role, ['charge', 'application_fee'], true) && (int) $facts['total_fee_minor'] < 0)) { throw new RuntimeException(); }
    $sum = 0; $application = 0; $details = [];
    foreach ($b['fee_details'] as $detail) {
        $amount = sharity_donation_finance_minor($detail['amount_minor'] ?? null);
        if (($detail['currency'] ?? null) !== $facts['currency'] || !is_string($detail['type'] ?? null)
            || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $detail['type'])
            || abs($sum) > PHP_INT_MAX - abs($amount) || abs($application) > PHP_INT_MAX - abs($amount)) { throw new RuntimeException(); }
        $sum += $amount; if ($detail['type'] === 'application_fee') { $application += $amount; }
        $details[] = ['amount_minor' => $detail['amount_minor'], 'currency' => $detail['currency'], 'type' => $detail['type']];
    }
    if ($sum !== (int) $facts['total_fee_minor'] || $application !== (int) $facts['application_fee_minor']) { throw new RuntimeException(); }
    // Fee components are a multiset: preserve duplicate components, discard only ordering.
    usort($details, static fn($a, $b) => strcmp(sharity_donation_finance_json($a), sharity_donation_finance_json($b)));
    $facts['fee_details'] = $details;
    if (!in_array($b['status'] ?? null, ['pending', 'available'], true) || !is_int($b['created'] ?? null) || $b['created'] < 1
        || !is_int($b['available_on'] ?? null) || $b['available_on'] < 1 || !array_key_exists('exchange_rate', $b)) { throw new RuntimeException(); }
    $rate = $b['exchange_rate'];
    if ($rate !== null && ((!is_int($rate) && !is_float($rate)) || !is_finite((float) $rate) || $rate <= 0)) { throw new RuntimeException(); }
    return ['facts' => $facts, 'financial_digest' => hash('sha256', sharity_donation_finance_json($facts)),
        'observed_metadata' => ['status' => $b['status'], 'created' => $b['created'], 'available_on' => $b['available_on'], 'exchange_rate' => $rate]];
}

function sharity_donation_finance_reconcile(string $orderId, int $now): array
{
    try {
        if (get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION, '') !== '1' || !preg_match('/^do_[a-f0-9]{32}$/D', $orderId)) { throw new RuntimeException(); }
        $order = sharity_donation_webhook_order($orderId);
        $status = sharity_donation_order_status($order['subject_ref'], $orderId, $now);
        if ($order['state'] !== 'session_recorded' || $status['state'] !== 'session_recorded') { throw new RuntimeException(); }
        $envelope = sharity_donation_order_decode($order); $snapshot = $envelope['snapshot'];
        global $wpdb; $table = sharity_donation_finance_table();
        // Fix the local observation set before reading bounded bodies; this is not a provider snapshot.
        $index = $wpdb->get_results($wpdb->prepare("SELECT id, OCTET_LENGTH(canonical_json) AS evidence_bytes FROM `{$table}` WHERE order_id = %s ORDER BY id LIMIT 257", $orderId), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($index)) { throw new RuntimeException(); }
        $total = 0;
        if (count($index) > 256) { throw new RuntimeException('DONATION_RECONCILE_LIMIT'); }
        foreach ($index as $item) {
            if (!is_string($item['id'] ?? null) || !preg_match('/^[1-9][0-9]{0,19}$/D', $item['id'])
                || !ctype_digit((string) ($item['evidence_bytes'] ?? ''))) { throw new RuntimeException(); }
            $bytes = (int) $item['evidence_bytes']; $total += $bytes;
            if ($bytes > SHARITY_DONATION_FINANCE_MAX_BYTES || $total > 16 * 1024 * 1024) { throw new RuntimeException('DONATION_RECONCILE_LIMIT'); }
        }
        $movements = []; $digests = []; $unresolved = ['cross_order_movement_registry_not_applied', 'backing_not_reconciled']; $paymentBindings = []; $platformIds = []; $feeRefunds = [];
        $expectedOriginal = ['evidence_version' => 1, 'scope' => 'original_charge_only', 'order_id' => $orderId,
            'session_id' => $order['provider_session_id'], 'account_id' => $snapshot['account_id'],
            'livemode' => $snapshot['livemode'], 'donation_mode' => $snapshot['donation_mode'], 'payment_verified' => false, 'allocation_eligible' => false,
            'payment' => ['amount_minor' => $snapshot['amount_minor'], 'currency' => $snapshot['currency']]];
        foreach ($index as $item) {
            // Bound even a concurrently damaged body before bringing it into PHP memory. No truncation accepted.
            $limit = SHARITY_DONATION_FINANCE_MAX_BYTES + 1;
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, order_id, account_id, livemode, donation_mode, provider_session_id, SUBSTRING(CAST(canonical_json AS BINARY),1,{$limit}) AS canonical_json, digest, observed_at_utc FROM `{$table}` WHERE order_id = %s AND id = %s", $orderId, $item['id']), ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($row) || !is_string($row['canonical_json'] ?? null)
                || strlen($row['canonical_json']) !== (int) $item['evidence_bytes']) { throw new RuntimeException(); }
            $json = $row['canonical_json']; $e = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($e) || sharity_donation_finance_json($e) !== $json) { throw new RuntimeException(); }
            $expected = ['id' => $item['id'], 'order_id' => $orderId, 'account_id' => $snapshot['account_id'],
                'livemode' => $snapshot['livemode'] ? '1' : '0', 'donation_mode' => $snapshot['donation_mode'],
                'provider_session_id' => $order['provider_session_id'], 'canonical_json' => $json, 'digest' => hash('sha256', $json)];
            sharity_donation_finance_receipt($row, $expected); $digest = $expected['digest'];
            if (isset($digests[$digest])) { throw new RuntimeException(); } $digests[$digest] = true;
            if (($e['evidence_version'] ?? null) !== 1 || ($e['scope'] ?? null) !== 'observed_adjustment_resources') { throw new RuntimeException(); }
            foreach (['payment_verified', 'allocation_eligible', 'atomic_snapshot', 'settlement_reconciled'] as $key) {
                if (($e[$key] ?? null) !== false) { throw new RuntimeException(); }
            }
            $original = $e['original'] ?? [];
            foreach ($expectedOriginal as $key => $value) { if (($original[$key] ?? null) !== $value) { throw new RuntimeException(); } }
            $platformAccount = null;
            if (array_key_exists('platform_account_id', $original)) {
                $platformAccount = sharity_donation_finance_id($original['platform_account_id'], 'acct');
                if (strlen($platformAccount) > 128 || $platformAccount === $snapshot['account_id'] || $snapshot['donation_mode'] !== 'amplifier'
                    || ($original['evidence_state'] ?? null) !== 'original_charge_observed') { throw new RuntimeException(); }
                $platformIds[$platformAccount] = true;
                if (count($platformIds) > 1) { throw new RuntimeException('DONATION_RECONCILE_CONFLICT'); }
            }
            foreach (['refunds', 'disputes', 'fee_refunds', 'unresolved'] as $key) {
                if (!is_array($e[$key] ?? null) || !array_is_list($e[$key])) { throw new RuntimeException(); }
            }
            if (!is_bool($e['resources_complete'] ?? null) || !is_bool($e['balances_pending'] ?? null)) { throw new RuntimeException(); }
            foreach ($e['unresolved'] as $reason) {
                if (!is_string($reason) || !preg_match('/^[a-z][a-z0-9_]{0,127}$/D', $reason)) { throw new RuntimeException(); }
                $unresolved[] = 'observed_' . $reason;
            }
            if (($original['evidence_state'] ?? null) === 'payment_pending') {
                if ($e['evidence_state'] !== 'payment_pending' || $e['resources_complete'] || $e['refunds'] !== [] || $e['disputes'] !== [] || $e['fee_refunds'] !== []
                    || isset($original['initial_charge_balance'])) { throw new RuntimeException(); }
                $unresolved[] = 'payment_pending_observed'; continue;
            }
            if (($original['evidence_state'] ?? null) !== 'original_charge_observed' || $e['evidence_state'] !== 'adjustment_resources_observed' || !$e['resources_complete']
                || ($original['checkout_state'] ?? null) !== 'complete' || ($original['provider_payment_status'] ?? null) !== 'paid' || !array_key_exists('application_fee', $original)) { throw new RuntimeException(); }
            $charge = sharity_donation_finance_id($original['charge_id'] ?? null, 'ch');
            $intent = sharity_donation_finance_id($original['payment_intent_id'] ?? null, 'pi');
            $fee = $original['application_fee'];
            if (($snapshot['donation_mode'] === 'direct') !== ($fee === null)) { throw new RuntimeException(); }
            $feeId = $fee === null ? null : sharity_donation_finance_id($fee['id'] ?? null, 'fee');
            $paymentBindings[sharity_donation_finance_json([$intent, $charge, $feeId,
                sharity_donation_finance_id($original['initial_charge_balance']['id'] ?? null, 'txn'),
                $fee === null ? null : sharity_donation_finance_id($fee['initial_balance']['id'] ?? null, 'txn')])] = true;
            if (count($paymentBindings) !== 1) { throw new RuntimeException('DONATION_RECONCILE_CONFLICT'); }
            $identity = ['order_id' => $orderId, 'livemode' => $snapshot['livemode'], 'session_id' => $order['provider_session_id'], 'payment_intent_id' => $intent, 'charge_id' => $charge];
            $add = static function (array $balance, string $source, string $role, ?string $account) use (&$movements, $identity, $digest): void {
                $m = sharity_donation_finance_movement($balance, $source, $role, $account, $identity);
                $key = sharity_donation_finance_json(['stripe', $identity['livemode'], $account, $m['facts']['balance_transaction_id']]);
                if (isset($movements[$key]) && $movements[$key]['financial_digest'] !== $m['financial_digest']) { throw new RuntimeException('DONATION_RECONCILE_CONFLICT'); }
                if (!isset($movements[$key])) { $movements[$key] = ['facts' => $m['facts'], 'financial_digest' => $m['financial_digest'], 'observations' => []]; }
                $movements[$key]['observations'][$digest] = ['evidence_digest' => $digest] + $m['observed_metadata'];
            };
            $add($original['initial_charge_balance'], $charge, 'charge', $snapshot['account_id']);
            if ($fee !== null) {
                $add($fee['initial_balance'], $feeId, 'application_fee', $platformAccount);
            }
            if (($original['failure_balance_transaction_id'] ?? null) !== null) { $unresolved[] = 'charge_failure_balance_not_projected'; }
            foreach ($e['refunds'] as $refund) {
                $source = sharity_donation_finance_id($refund['id'] ?? null, 're');
                foreach (['balance' => 'refund', 'failure_balance' => 'refund_reversal'] as $field => $role) {
                    if (!array_key_exists($field, $refund)) { throw new RuntimeException(); }
                    if ($refund[$field] !== null) { $add($refund[$field], $source, $role, $snapshot['account_id']); }
                }
            }
            foreach ($e['disputes'] as $dispute) {
                $source = sharity_donation_finance_id($dispute['id'] ?? null, '(?:dp|du)');
                if (!is_array($dispute['balances'] ?? null) || !array_is_list($dispute['balances'])) { throw new RuntimeException(); }
                foreach ($dispute['balances'] as $balance) { $add($balance, $source, 'dispute', $snapshot['account_id']); }
            }
            foreach ($e['fee_refunds'] as $refund) {
                $source = sharity_donation_finance_id($refund['id'] ?? null, 'fr');
                $verified = $refund['connected_credit_verified'] ?? null;
                $credit = $refund['connected_credit'] ?? null;
                if ($feeId === null || ($refund['fee_id'] ?? null) !== $feeId || !is_bool($verified)
                    || $verified !== ($credit !== null) || !array_key_exists('balance', $refund)) { throw new RuntimeException(); }
                $amount = sharity_donation_finance_minor($refund['amount_minor'] ?? null);
                $currency = $refund['currency'] ?? null;
                if ($amount <= 0 || $amount > sharity_donation_finance_minor($fee['amount_minor'] ?? null)
                    || !in_array($currency, ['HUF','EUR','USD','GBP'], true) || $currency !== ($fee['currency'] ?? null)) { throw new RuntimeException(); }
                $facts = [$feeId, $refund['amount_minor'], $currency];
                if (isset($feeRefunds[$source]) && $feeRefunds[$source]['facts'] !== $facts) { throw new RuntimeException('DONATION_RECONCILE_CONFLICT'); }
                $feeRefunds[$source] ??= ['facts' => $facts, 'debit' => null, 'credit' => null];
                foreach (['debit' => $refund['balance'], 'credit' => $credit] as $side => $balance) {
                    if ($balance === null) { continue; }
                    $nativeId = sharity_donation_finance_id($balance['id'] ?? null, 'txn');
                    if ($feeRefunds[$source][$side] !== null && $feeRefunds[$source][$side] !== $nativeId) { throw new RuntimeException('DONATION_RECONCILE_CONFLICT'); }
                    $feeRefunds[$source][$side] = $nativeId;
                    $nativeGross = sharity_donation_finance_minor($balance['gross_minor'] ?? null);
                    if (($balance['currency'] ?? null) === $currency) {
                        if ($nativeGross !== ($side === 'credit' ? $amount : -$amount)
                            || (($balance['exchange_rate'] ?? null) !== null && (float)$balance['exchange_rate'] !== 1.0)) { throw new RuntimeException(); }
                    } elseif (($balance['exchange_rate'] ?? null) === null) { throw new RuntimeException(); }
                }
                if ($refund['balance'] !== null) { $add($refund['balance'], $source, 'application_fee_refund', $platformAccount); }
                if ($credit !== null) {
                    if ($platformAccount === null || ($credit['account_id'] ?? null) !== $snapshot['account_id']
                        || ($credit['application_fee_minor'] ?? null) !== '0') { throw new RuntimeException(); }
                    $add($credit, $source, 'fee_refund_credit', $snapshot['account_id']);
                }
            }
        }
        // Do not infer old account scopes. Supersede an unscoped leg only when the
        // same native transaction is independently observed with matching immutable facts.
        foreach ($movements as $key => $legacy) {
            $f = $legacy['facts'];
            if ($f['account_id'] !== null || $platformIds === []) { continue; }
            $account = array_key_first($platformIds);
            $knownKey = sharity_donation_finance_json(['stripe', $f['livemode'], $account, $f['balance_transaction_id']]);
            if (!isset($movements[$knownKey])) { continue; }
            $expected = $f; $expected['account_id'] = $account;
            if (sharity_donation_finance_json($expected) !== sharity_donation_finance_json($movements[$knownKey]['facts'])) {
                throw new RuntimeException('DONATION_RECONCILE_CONFLICT');
            }
            $movements[$knownKey]['observations'] += $legacy['observations'];
            unset($movements[$key]);
        }
        // A later exact native observation can resolve historical absence; never erase its row.
        $counterlegsComplete = true;
        foreach ($feeRefunds as $refund) {
            if ($refund['debit'] === null || $refund['credit'] === null) { $counterlegsComplete = false; }
        }
        if ($counterlegsComplete) {
            $unresolved = array_values(array_diff($unresolved, ['observed_fee_refund_connected_credit_unreconciled', 'observed_fee_refund_counterleg_read_limit']));
        } else { $unresolved[] = 'observed_fee_refund_connected_credit_unreconciled'; }
        if ($index === []) { $unresolved[] = 'observations_missing'; }
        ksort($movements, SORT_STRING); ksort($digests, SORT_STRING); $known = []; $unscoped = []; $financial = [];
        foreach ($movements as $movement) {
            ksort($movement['observations'], SORT_STRING); $movement['observations'] = array_values($movement['observations']);
            $financial[] = $movement['financial_digest'];
            if ($movement['facts']['account_id'] === null) { $unscoped[] = $movement; } else { $known[] = $movement; }
        }
        if ($unscoped !== []) { $unresolved[] = 'platform_account_unverified'; }
        sort($financial, SORT_STRING); $unresolved = array_values(array_unique($unresolved)); sort($unresolved, SORT_STRING);
        $evidenceSet = hash('sha256', sharity_donation_finance_json(array_keys($digests)));
        $financialSet = hash('sha256', sharity_donation_finance_json($financial));
        $proposal = hash('sha256', sharity_donation_finance_json(['version' => 1, 'operation' => 'record_movements',
            'order_id' => $orderId, 'evidence_set_digest' => $evidenceSet, 'financial_inventory_digest' => $financialSet]));
        return ['inventory_version' => 1, 'scope' => 'persisted_observations_only', 'order_id' => $orderId,
            'payment_verified' => false, 'allocation_eligible' => false, 'settlement_reconciled' => false, 'apply_supported' => false, 'atomic_snapshot' => false,
            'observation_count' => count($index), 'evidence_digests' => array_keys($digests),
            'evidence_set_digest' => $evidenceSet, 'financial_inventory_digest' => $financialSet, 'movement_proposal_digest' => $proposal,
            'movements' => $known, 'unscoped_platform_movements' => $unscoped, 'unresolved' => $unresolved];
    } catch (Throwable $error) {
        throw new RuntimeException(in_array($error->getMessage(), ['DONATION_RECONCILE_LIMIT', 'DONATION_RECONCILE_CONFLICT'], true) ? $error->getMessage() : 'DONATION_RECONCILE_UNAVAILABLE');
    }
}

const SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION = 'sharity_donation_movement_schema_v1';

function sharity_donation_movement_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sharity_donation_movements_v1';
}

/** Exact schema, including the cross-order provider identity. No automatic repair. */
function sharity_donation_movement_schema_valid(callable $read): bool
{
    $table = sharity_donation_movement_table();
    $columns = $read("SHOW FULL COLUMNS FROM `{$table}`");
    $expected = ['provider' => ['varchar(16)', 'ascii_bin'], 'livemode' => ['tinyint(1)', null],
        'account_id' => ['varchar(128)', 'ascii_bin'], 'balance_transaction_id' => ['varchar(244)', 'ascii_bin'],
        'order_id' => ['char(35)', 'ascii_bin'], 'facts_json' => ['longtext', 'utf8mb4_bin'],
        'financial_digest' => ['char(64)', 'ascii_bin'], 'first_recorded_at_utc' => ['datetime', null]];
    if (!is_array($columns) || count($columns) !== count($expected)) { return false; }
    foreach ($columns as $c) {
        if (!isset($expected[$c['Field']]) || [$c['Type'], $c['Collation']] !== $expected[$c['Field']]
            || $c['Null'] !== 'NO' || $c['Default'] !== null || $c['Extra'] !== '') { return false; }
    }
    $indexes = $read("SHOW INDEX FROM `{$table}`");
    if (!is_array($indexes)) { return false; }
    $topology = array_map(static fn($i) => [$i['Key_name'], (int) $i['Seq_in_index'], $i['Column_name'],
        (int) $i['Non_unique'], $i['Sub_part'], $i['Index_type']], $indexes);
    $required = [['PRIMARY', 1, 'provider', 0, null, 'BTREE'], ['PRIMARY', 2, 'livemode', 0, null, 'BTREE'],
        ['PRIMARY', 3, 'account_id', 0, null, 'BTREE'], ['PRIMARY', 4, 'balance_transaction_id', 0, null, 'BTREE'],
        ['order_id', 1, 'order_id', 1, null, 'BTREE']];
    sort($topology); sort($required);
    return $topology === $required;
}

function sharity_donation_movement_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION, '') !== '0') { return false; }
        global $wpdb; $table = sharity_donation_movement_table();
        if ($wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            provider varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            livemode tinyint(1) NOT NULL,
            account_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            balance_transaction_id varchar(244) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            facts_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            financial_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            first_recorded_at_utc datetime NOT NULL,
            PRIMARY KEY (provider, livemode, account_id, balance_transaction_id),
            KEY order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin") === false || $wpdb->last_error !== '') { return false; }
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB') { return false; }
        if (!sharity_donation_movement_schema_valid(static function (string $sql) use ($wpdb): array {
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($rows)) { throw new RuntimeException(); }
            return $rows;
        })) { return false; }
        update_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

/** Explicit registry-only operation. No provider call, payable total or financial readiness. */
function sharity_donation_finance_record_movements(string $orderId, string $proposalDigest, int $now): array
{
    $db = null; $active = false;
    try {
        if (!preg_match('/^[a-f0-9]{64}$/D', $proposalDigest) || $now < 1 || $now > 253402300799
            || get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException(); }
        sharity_donation_order_autocommit();
        $order = sharity_donation_webhook_order($orderId);
        $inventory = sharity_donation_finance_reconcile($orderId, $now);
        if (!hash_equals($inventory['movement_proposal_digest'], $proposalDigest)) { throw new RuntimeException('DONATION_MOVEMENT_STALE'); }
        if ($inventory['movements'] === []) { throw new RuntimeException('DONATION_MOVEMENT_EMPTY'); }
        $snapshot = sharity_donation_order_decode($order)['snapshot'];
        global $wpdb;
        // wpdb may reconnect automatically. Use its exact native handle for the whole transaction.
        $db = $wpdb->dbh;
        if (!$db instanceof mysqli || $db->thread_id < 1) { throw new RuntimeException(); }
        $thread = $db->thread_id;
        $pinned = static function () use ($db, $thread): void {
            global $wpdb;
            if ($wpdb->dbh !== $db || $db->thread_id !== $thread) { throw new RuntimeException(); }
        };
        $query = static function (string $sql, array $params = []) use ($db, $pinned): array {
            $pinned();
            $statement = @$db->prepare($sql);
            if ($statement === false) { throw new RuntimeException(); }
            try {
                if (!@$statement->execute($params)) { throw new mysqli_sql_exception('', $statement->errno); }
                $result = $statement->get_result();
                $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                if ($result instanceof mysqli_result) { $result->free(); }
                $pinned();
                return $rows;
            } finally { $statement->close(); }
        };
        $assertTransaction = static function (bool $expected) use ($query): void {
            $state = $query('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0] ?? [];
            if ((string) ($state['ac'] ?? '') !== '1' || (string) ($state['tx'] ?? '') !== ($expected ? '1' : '0')) { throw new RuntimeException(); }
        };
        $assertTransaction(false);
        $table = sharity_donation_movement_table(); $orders = sharity_donation_order_table(); $observations = sharity_donation_finance_table();
        $query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $query('START TRANSACTION'); $active = true;
        // Same source mutex as backing import/reserve. A later registry fact must serialize
        // with any hold that relies on the corresponding operator-owned collateral claim.
        if (function_exists('sharity_donation_backing_lock_movements')) {
            sharity_donation_backing_lock_movements($query, $inventory['movements']);
        }
        $lockedOrder = $query("SELECT * FROM `{$orders}` WHERE order_id = ? FOR UPDATE", [$orderId]);
        if (count($lockedOrder) !== 1 || array_map(static fn($v) => is_int($v) ? (string) $v : $v, $lockedOrder[0]) !== $order) { throw new RuntimeException('DONATION_MOVEMENT_STALE'); }
        // The range lock also excludes a new observation until this selected batch commits.
        $lockedEvidence = $query("SELECT account_id, livemode, donation_mode, provider_session_id, digest,
            OCTET_LENGTH(canonical_json) AS bytes, SHA2(canonical_json,256) AS content_digest
            FROM `{$observations}` FORCE INDEX (order_digest) WHERE order_id = ? ORDER BY digest LIMIT 257 FOR UPDATE", [$orderId]);
        $digests = []; $bytes = 0;
        foreach ($lockedEvidence as $e) {
            $size = (int) $e['bytes']; $bytes += $size;
            if ($size < 1 || $size > SHARITY_DONATION_FINANCE_MAX_BYTES || $bytes > 16 * 1024 * 1024
                || $e['account_id'] !== $snapshot['account_id'] || (string) $e['livemode'] !== ($snapshot['livemode'] ? '1' : '0')
                || $e['donation_mode'] !== $snapshot['donation_mode'] || $e['provider_session_id'] !== $order['provider_session_id']
                || $e['digest'] !== $e['content_digest']) { throw new RuntimeException('DONATION_MOVEMENT_STALE'); }
            $digests[] = $e['digest'];
        }
        if ($digests !== $inventory['evidence_digests']) { throw new RuntimeException('DONATION_MOVEMENT_STALE'); }
        // Acquire metadata locks before verifying engines/indexes; no DDL can invalidate them mid-batch.
        $query("SELECT provider FROM `{$table}` LIMIT 0");
        foreach ([$orders, $observations, $table] as $name) {
            $status = $query('SHOW TABLE STATUS WHERE Name = ?', [$name]);
            if (count($status) !== 1 || $status[0]['Engine'] !== 'InnoDB') { throw new RuntimeException(); }
        }
        if (!sharity_donation_movement_schema_valid($query)) { throw new RuntimeException(); }
        $packet = (int) ($query('SELECT @@max_allowed_packet AS bytes')[0]['bytes'] ?? 0);
        $expectedRows = [];
        foreach ($inventory['movements'] as $movement) {
            $facts = $movement['facts']; $json = sharity_donation_finance_json($facts);
            if (strlen($json) > SHARITY_DONATION_FINANCE_MAX_BYTES || $packet <= strlen($json) + 4096) { throw new RuntimeException('DONATION_FINANCE_PACKET_TOO_SMALL'); }
            $expectedRows[] = ['provider' => $facts['provider'], 'livemode' => $facts['livemode'] ? '1' : '0',
                'account_id' => $facts['account_id'], 'balance_transaction_id' => $facts['balance_transaction_id'],
                'order_id' => $orderId, 'facts_json' => $json, 'financial_digest' => $movement['financial_digest']];
        }
        $readback = static function (array $expected) use ($query, $table): array {
            $rows = $query("SELECT provider, livemode, account_id, balance_transaction_id, order_id,
                SUBSTRING(CAST(facts_json AS BINARY),1," . (SHARITY_DONATION_FINANCE_MAX_BYTES + 1) . ") AS facts_json,
                financial_digest, first_recorded_at_utc FROM `{$table}`
                WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ?",
                array_slice(array_values($expected), 0, 4));
            if (count($rows) !== 1) { throw new RuntimeException(); }
            $row = $rows[0];
            foreach ($expected as $key => $value) {
                if ((string) $row[$key] !== $value) { throw new RuntimeException('DONATION_MOVEMENT_CONFLICT'); }
            }
            $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $row['first_recorded_at_utc'], new DateTimeZone('UTC'));
            if ($time === false || $time->format('Y-m-d H:i:s') !== $row['first_recorded_at_utc']
                || !hash_equals($row['financial_digest'], hash('sha256', $row['facts_json']))) { throw new RuntimeException(); }
            unset($row['facts_json']); $row['livemode'] = (string) $row['livemode'] === '1';
            return $row;
        };
        foreach ($expectedRows as $expected) {
            $insert = $expected + ['first_recorded_at_utc' => gmdate('Y-m-d H:i:s', $now)];
            try {
                $query("INSERT INTO `{$table}` (`" . implode('`,`', array_keys($insert)) . '`) VALUES (?,?,?,?,?,?,?,?)', array_values($insert));
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) { throw $e; }
                // A duplicate is not success until every immutable field matches this order.
            }
            $readback($expected);
        }
        $assertTransaction(true);
        try { if (!$db->commit()) { throw new RuntimeException(); } }
        catch (Throwable $uncertain) {
            // End any uncommitted attempt, then decide from the whole persisted batch. No retry/reconnect.
            $pinned(); if (!$db->rollback()) { throw new RuntimeException(); }
        }
        $assertTransaction(false); $active = false;
        $receipts = array_map($readback, $expectedRows);
        return ['registry_version' => 1, 'scope' => 'selected_account_scoped_movements_only', 'order_id' => $orderId,
            'movement_proposal_digest' => $proposalDigest, 'movements_recorded' => true, 'movements' => $receipts,
            'payment_verified' => false, 'allocation_eligible' => false, 'settlement_reconciled' => false, 'apply_supported' => false,
            'unscoped_platform_movement_count' => count($inventory['unscoped_platform_movements']),
            'unresolved' => array_values(array_diff($inventory['unresolved'], ['cross_order_movement_registry_not_applied']))];
    } catch (Throwable $error) {
        if ($active && $db instanceof mysqli) { try { $db->rollback(); } catch (Throwable $ignored) {} }
        throw new RuntimeException(in_array($error->getMessage(), ['DONATION_MOVEMENT_STALE', 'DONATION_MOVEMENT_EMPTY',
            'DONATION_MOVEMENT_CONFLICT', 'DONATION_FINANCE_PACKET_TOO_SMALL', 'DONATION_RECONCILE_LIMIT', 'DONATION_RECONCILE_CONFLICT'], true)
            ? $error->getMessage() : 'DONATION_MOVEMENT_UNAVAILABLE');
    }
}

/** D2m: trusted operator assertions and prepared holds; never a provider verification or payout. */
const SHARITY_DONATION_BACKING_SCHEMA_OPTION = 'sharity_donation_backing_schema_v1';
const SHARITY_DONATION_BACKING_MAX_BYTES = 65536;

function sharity_donation_backing_table(string $kind): string
{
    global $wpdb;
    if (!in_array($kind, ['frames', 'sources', 'claims'], true)) { throw new RuntimeException(); }
    return $wpdb->prefix . 'sharity_donation_backing_' . $kind . '_v1';
}

/** Small native SQL adapter, pinned to the existing handle. No reconnect or nested transaction. */
function sharity_donation_backing_db(): array
{
    global $wpdb;
    $db = $wpdb->dbh;
    if (!$db instanceof mysqli || $db->thread_id < 1) { throw new RuntimeException(); }
    $thread = $db->thread_id;
    $query = static function (string $sql, array $params = []) use ($db, $thread): array {
        global $wpdb;
        if ($wpdb->dbh !== $db || $db->thread_id !== $thread) { throw new RuntimeException(); }
        $stmt = @$db->prepare($sql);
        if ($stmt === false) { throw new RuntimeException(); }
        try {
            if (!@$stmt->execute($params)) { throw new mysqli_sql_exception('', $stmt->errno); }
            $result = $stmt->get_result();
            $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            if ($result instanceof mysqli_result) { $result->free(); }
            if ($wpdb->dbh !== $db || $db->thread_id !== $thread) { throw new RuntimeException(); }
            return $rows;
        } finally { $stmt->close(); }
    };
    $state = $query('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0] ?? [];
    if ((string) ($state['ac'] ?? '') !== '1' || (string) ($state['tx'] ?? '') !== '0') { throw new RuntimeException(); }
    return [$db, $query];
}

function sharity_donation_backing_schema(): array
{
    $hash = 'char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL';
    $json = 'longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL';
    return [
        'frames' => [['frame_key'=>$hash, 'revision'=>'int unsigned NOT NULL', 'digest'=>$hash,
            'document'=>$json, 'created_at_utc'=>'datetime NOT NULL'],
            ['PRIMARY'=>[0,['frame_key','revision']], 'proof'=>[0,['frame_key','digest']]]],
        'sources' => [['source_key'=>$hash, 'frame_key'=>$hash, 'digest'=>$hash, 'document'=>$json,
            'created_at_utc'=>'datetime NOT NULL'], ['PRIMARY'=>[0,['source_key']], 'frame'=>[1,['frame_key']]]],
        'claims' => [['claim_key'=>$hash, 'frame_key'=>$hash,
            'order_id'=>'char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            'kind'=>'varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL', 'digest'=>$hash,
            'document'=>$json, 'state'=>'varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            'created_at_utc'=>'datetime NOT NULL'],
            ['PRIMARY'=>[0,['claim_key']], 'order_kind'=>[0,['order_id','kind']], 'frame'=>[1,['frame_key']]]],
    ];
}

function sharity_donation_backing_schema_valid(callable $query): bool
{
    foreach (sharity_donation_backing_schema() as $kind=>[$columns,$indexes]) {
        $table = sharity_donation_backing_table($kind);
        $query("SELECT 1 FROM `{$table}` LIMIT 0"); // Metadata lock before inspecting a transactional table.
        $status = $query('SHOW TABLE STATUS WHERE Name = ?', [$table]);
        if (count($status)!==1 || $status[0]['Engine']!=='InnoDB' || $status[0]['Collation']!=='utf8mb4_bin') { return false; }
        $actual = $query("SHOW FULL COLUMNS FROM `{$table}`");
        if (count($actual)!==count($columns)) { return false; }
        foreach ($actual as $c) {
            $definition = $columns[$c['Field']] ?? '';
            $type = preg_replace('/int\([0-9]+\)/', 'int', $c['Type']);
            $expectedType = strtolower(explode(' NOT NULL', explode(' CHARACTER SET', $definition)[0])[0]);
            $collation = str_contains($definition,'COLLATE ascii_bin') ? 'ascii_bin' : (str_contains($definition,'COLLATE utf8mb4_bin') ? 'utf8mb4_bin' : null);
            if ($type!==$expectedType || $c['Collation']!==$collation || $c['Null']!=='NO' || $c['Default']!==null || $c['Extra']!=='') { return false; }
        }
        $actualIndexes = array_map(static fn($i)=>[$i['Key_name'],(int)$i['Seq_in_index'],$i['Column_name'],(int)$i['Non_unique'],$i['Sub_part'],$i['Index_type']],$query("SHOW INDEX FROM `{$table}`"));
        $expectedIndexes=[];
        foreach ($indexes as $name=>[$nonUnique,$fields]) { foreach ($fields as $i=>$field) { $expectedIndexes[]=[$name,$i+1,$field,$nonUnique,null,'BTREE']; } }
        sort($actualIndexes); sort($expectedIndexes);
        if ($actualIndexes!==$expectedIndexes) { return false; }
    }
    return true;
}

function sharity_donation_backing_schema_apply(): bool
{
    try {
        [$db,$query]=sharity_donation_backing_db();
        update_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION,'0',false);
        if (get_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION)!=='0') { return false; }
        foreach (sharity_donation_backing_schema() as $kind=>[$columns,$indexes]) {
            $parts=[];
            foreach ($columns as $name=>$definition) { $parts[]="`{$name}` {$definition}"; }
            foreach ($indexes as $name=>[$nonUnique,$fields]) {
                $parts[]=($name==='PRIMARY'?'PRIMARY KEY':($nonUnique?'KEY':'UNIQUE KEY')." `{$name}`").' (`'.implode('`,`',$fields).'`)';
            }
            $query('CREATE TABLE IF NOT EXISTS `'.sharity_donation_backing_table($kind).'` ('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        }
        if (!sharity_donation_backing_schema_valid($query)) { return false; }
        update_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION,'1',false);
        return get_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION)==='1';
    } catch (Throwable $error) { return false; }
}

/** Strict fields, exact minor units and opaque artifact references; never import raw provider/bank data. */
function sharity_donation_backing_keys($value, array $keys): void
{
    if (!is_array($value)) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    $actual=array_keys($value);sort($actual);sort($keys);
    if ($actual!==$keys) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
}
function sharity_donation_backing_string($value, string $pattern): string
{
    if (!is_string($value) || !preg_match($pattern,$value)) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    return $value;
}
function sharity_donation_backing_amount($value): int
{
    $amount=sharity_donation_finance_minor($value);
    if ($amount<0) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    return $amount;
}
function sharity_donation_backing_sum(int $a, int $b): int
{
    if ($a<0 || $b<0 || $a>999999999999999-$b) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
    return $a+$b;
}
function sharity_donation_backing_time($value): int
{
    $value=sharity_donation_backing_string($value,'/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D');
    $time=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$value,new DateTimeZone('UTC'));
    if (!$time || $time->format('Y-m-d\TH:i:s\Z')!==$value) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    return $time->getTimestamp();
}
function sharity_donation_backing_reference($value): void
{
    sharity_donation_backing_keys($value,['ref','sha256']);
    sharity_donation_backing_string($value['ref'],'/^[A-Za-z][A-Za-z0-9_-]{1,127}$/D');
    sharity_donation_backing_string($value['sha256'],'/^[a-f0-9]{64}$/D');
}
function sharity_donation_backing_source_key(array $source, array $frame): string
{
    return hash('sha256',sharity_donation_finance_json([$frame['provider'],$frame['livemode'],$frame['account_id'],$source['native_id']]));
}
function sharity_donation_backing_frame_key(array $frame): string
{
    return hash('sha256',sharity_donation_finance_json([$frame['provider'],$frame['livemode'],$frame['account_id'],$frame['currency']]));
}
function sharity_donation_backing_proof(array $proof): array
{
    sharity_donation_backing_keys($proof,['version','frame','sources']);
    $f=$proof['frame'];
    sharity_donation_backing_keys($f,['provider','account_id','livemode','currency','as_of_utc','expires_at_utc','retained_minor',
        'external_liability_minor','external_liability_proof','retention_proof','confirmed','previous_revision','previous_digest']);
    if ($proof['version']!==1 || $f['provider']!=='stripe' || !is_bool($f['livemode']) || $f['confirmed']!==true
        || !in_array($f['currency'],['HUF','EUR','USD','GBP'],true)) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    sharity_donation_backing_string($f['account_id'],'/^acct_[A-Za-z0-9]{1,123}$/D');
    $asOf=sharity_donation_backing_time($f['as_of_utc']);$expiry=sharity_donation_backing_time($f['expires_at_utc']);
    if ($expiry<=$asOf || !is_int($f['previous_revision']) || $f['previous_revision']<0 || $f['previous_revision']>=2147483647
        || ($f['previous_revision']===0 ? $f['previous_digest']!==null : !is_string($f['previous_digest']))) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    if ($f['previous_revision']>0) { sharity_donation_backing_string($f['previous_digest'],'/^[a-f0-9]{64}$/D'); }
    $retained=sharity_donation_backing_amount($f['retained_minor']);sharity_donation_backing_amount($f['external_liability_minor']);
    sharity_donation_backing_reference($f['external_liability_proof']);sharity_donation_backing_reference($f['retention_proof']);
    if (!is_array($proof['sources']) || !array_is_list($proof['sources']) || count($proof['sources'])>256) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
    $sources=[];$sum=0;
    foreach ($proof['sources'] as $s) {
        sharity_donation_backing_keys($s,['kind','native_id','order_id','principal_minor','retained_minor','evidence','retention','ownership','as_of_utc','expires_at_utc']);
        sharity_donation_backing_string($s['native_id'],'/^[A-Za-z][A-Za-z0-9_-]{1,127}$/D');
        $principal=sharity_donation_backing_amount($s['principal_minor']);$kept=sharity_donation_backing_amount($s['retained_minor']);
        if ($principal===0 || $kept>$principal || sharity_donation_backing_time($s['as_of_utc'])>$asOf
            || sharity_donation_backing_time($s['expires_at_utc'])<=sharity_donation_backing_time($s['as_of_utc'])) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
        sharity_donation_backing_reference($s['evidence']);sharity_donation_backing_reference($s['retention']);
        if ($s['kind']==='order_receipt') {
            sharity_donation_backing_string($s['native_id'],'/^txn_[A-Za-z0-9]{1,123}$/D');
            sharity_donation_backing_string($s['order_id'],'/^do_[a-f0-9]{32}$/D');
            if ($s['ownership']!==null) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
        } elseif ($s['kind']==='own_collateral') {
            sharity_donation_backing_keys($s['ownership'],['owner','ngo_funds','common_fund','proof']);
            if ($s['order_id']!==null || $s['ownership']['owner']!=='sharity' || $s['ownership']['ngo_funds']!==false || $s['ownership']['common_fund']!==false) { throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN'); }
            sharity_donation_backing_reference($s['ownership']['proof']);
        } else { throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN'); }
        $key=sharity_donation_backing_source_key($s,$f);
        if (isset($sources[$key])) { throw new RuntimeException('DONATION_BACKING_SOURCE_CONFLICT'); }
        $sources[$key]=$s;$sum=sharity_donation_backing_sum($sum,$kept);
    }
    if ($sum!==$retained) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
    ksort($sources,SORT_STRING);$proof['sources']=array_values($sources);
    if (strlen(sharity_donation_finance_json($proof))>SHARITY_DONATION_BACKING_MAX_BYTES) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
    return $proof;
}

/** The opened inode must still be the private, regular file that was inspected. */
function sharity_donation_backing_read_proof(string $path): array
{
    clearstatcache(true,$path);$stat=@lstat($path);
    if (!is_array($stat) || ($stat['mode']&0170000)!==0100000 || ($stat['mode']&0077)!==0
        || $stat['nlink']!==1 || !function_exists('posix_geteuid') || $stat['uid']!==posix_geteuid()) { throw new RuntimeException('DONATION_BACKING_PROOF_PERMISSION'); }
    $handle=@fopen($path,'rb');if (!$handle) { throw new RuntimeException('DONATION_BACKING_UNAVAILABLE'); }
    try {
        $opened=fstat($handle);
        foreach (['dev','ino','mode','uid','nlink','size'] as $key) { if (($opened[$key]??null)!==$stat[$key]) { throw new RuntimeException('DONATION_BACKING_PROOF_PERMISSION'); } }
        if ($stat['size']>SHARITY_DONATION_BACKING_MAX_BYTES) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
        $body=stream_get_contents($handle,SHARITY_DONATION_BACKING_MAX_BYTES+1);
        if (!is_string($body) || strlen($body)!==$stat['size']) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
        $value=json_decode($body,true,32,JSON_THROW_ON_ERROR);
        // Canonical JSON rejects duplicate keys and ambiguous numeric/object representations.
        if (!is_array($value) || sharity_donation_finance_json($value)!==$body) { throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED'); }
        return $value;
    } finally { fclose($handle); }
}

function sharity_donation_backing_row(callable $q, string $kind, string $where, array $params): ?array
{
    $table=sharity_donation_backing_table($kind);$limit=SHARITY_DONATION_BACKING_MAX_BYTES+1;
    $fields=array_keys(sharity_donation_backing_schema()[$kind][0]);
    $fields=array_values(array_diff($fields,['document']));$projection='`'.implode('`,`',$fields).'`';
    $rows=$q("SELECT {$projection}, SUBSTRING(CAST(document AS BINARY),1,{$limit}) AS document FROM `{$table}` {$where}",$params);
    if (count($rows)>1) { throw new RuntimeException(); }
    if ($rows===[]) { return null; }
    $r=$rows[0];$json=$r['document'];
    if (strlen($json)>SHARITY_DONATION_BACKING_MAX_BYTES || !hash_equals($r['digest'],hash('sha256',$json))) { throw new RuntimeException(); }
    $doc=json_decode($json,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($doc) || sharity_donation_finance_json($doc)!==$json) { throw new RuntimeException(); }
    $time=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['created_at_utc'],new DateTimeZone('UTC'));
    if (!$time || $time->format('Y-m-d H:i:s')!==$r['created_at_utc']) { throw new RuntimeException(); }
    if ($kind==='frames') {
        $p=sharity_donation_backing_proof($doc);
        if (sharity_donation_backing_frame_key($p['frame'])!==$r['frame_key'] || (string)($p['frame']['previous_revision']+1)!==(string)$r['revision']) { throw new RuntimeException(); }
    }
    return $r;
}
function sharity_donation_backing_source_identity(array $s, array $f): array
{
    return ['provider'=>$f['provider'],'account_id'=>$f['account_id'],'livemode'=>$f['livemode'],'currency'=>$f['currency'],
        'native_id'=>$s['native_id'],'kind'=>$s['kind'],'order_id'=>$s['order_id'],'principal_minor'=>$s['principal_minor'],
        'evidence'=>$s['evidence'],'ownership'=>$s['ownership']];
}
function sharity_donation_backing_insert(callable $q,string $kind,array $row): void
{
    $table=sharity_donation_backing_table($kind);
    $q("INSERT INTO `{$table}` (`".implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
}

/** Serialize an existing frame on its fixed primary key before taking the latest-row gap lock.
 * A waiting latest-row range lock can otherwise block the holder's next revision INSERT.
 */
function sharity_donation_backing_latest_locked(callable $q,string $key): ?array
{
    $anchor=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? AND revision = 1 FOR UPDATE',[$key]);
    $latest=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? ORDER BY revision DESC LIMIT 1 FOR UPDATE',[$key]);
    if($latest!==null&&$anchor===null){throw new RuntimeException('DONATION_BACKING_SOURCE_CONFLICT');}return $latest;
}

/** Holds the shared frame first; all provider access is outside this module. */
function sharity_donation_backing_transaction(bool $apply,int $now,callable $work,bool $requireCommitAck=false): array
{
    $db=null;$active=false;
    try {
        if ($now<1 || $now>253402300799 || get_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION)!=='1'
            || get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)!=='1') { throw new RuntimeException(); }
        [$db,$q]=sharity_donation_backing_db();
        $q('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$q('START TRANSACTION');$active=true;
        if (!sharity_donation_backing_schema_valid($q)) { throw new RuntimeException(); }
        foreach ([sharity_donation_order_table(),sharity_donation_finance_table(),sharity_donation_movement_table()] as $table) {
            $q("SELECT 1 FROM `{$table}` LIMIT 0");$status=$q('SHOW TABLE STATUS WHERE Name = ?',[$table]);
            if (count($status)!==1 || $status[0]['Engine']!=='InnoDB') { throw new RuntimeException(); }
        }
        if (!sharity_donation_movement_schema_valid($q)) { throw new RuntimeException(); }
        [$receipt,$readback]=$work($q);
        $state=$q('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0];
        if ((string)$state['ac']!=='1' || (string)$state['tx']!=='1') { throw new RuntimeException(); }
        if ($apply) {
            try { if (!$db->commit()) { throw new RuntimeException(); } }
            catch (Throwable $uncertain) {
                // Immutable writes may converge by readback; a money-dispatch attempt may not.
                if ($requireCommitAck) { throw new RuntimeException('DONATION_FEE_ATTEMPT_UNCERTAIN'); }
                $q('SELECT 1');if (!$db->rollback()) { throw new RuntimeException(); }
            }
        } elseif (!$db->rollback()) { throw new RuntimeException(); }
        $state=$q('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0];
        if ((string)$state['ac']!=='1' || (string)$state['tx']!=='0') { throw new RuntimeException(); }
        $active=false;
        if ($apply) { $readback($q); }
        return $receipt;
    } catch (Throwable $e) {
        if ($active && $db instanceof mysqli) { try { $db->rollback(); } catch (Throwable $ignored) {} }
        $allowed=['STALE','SOURCE_UNPROVEN','SOURCE_CONFLICT','CLAIM_CONFLICT','INSUFFICIENT','EXPIRED','PROOF_MALFORMED','LIMIT'];
        $feeCodes=['DONATION_FEE_ATTEMPT_UNCERTAIN','DONATION_FEE_INPUT','DONATION_FEE_STALE','DONATION_FEE_SOURCE','DONATION_FEE_CONFLICT'];
        throw new RuntimeException(in_array($e->getMessage(),$feeCodes,true)||in_array(str_replace('DONATION_BACKING_','',$e->getMessage()),$allowed,true)?$e->getMessage():'DONATION_BACKING_UNAVAILABLE');
    }
}
function sharity_donation_backing_match(callable $q,string $kind,array $expected,string $where,array $params): void
{
    $r=sharity_donation_backing_row($q,$kind,$where,$params);
    if ($r===null) { throw new RuntimeException(); }
    foreach ($expected as $k=>$v) { if ((string)($r[$k]??'')!==(string)$v) { throw new RuntimeException(); } }
}

/** Every hold, including confirmed corrections, counts once against its original sources. */
function sharity_donation_backing_capacity(callable $q,array $proof,array $skipClaims=[],array $previewAccounting=[]): array
{
    foreach ($proof['sources'] as $s) {
        if ($s['kind']!=='own_collateral' || (int)$s['retained_minor']===0) { continue; }
        $f=$proof['frame'];$mt=sharity_donation_movement_table();
        $known=$q("SELECT order_id FROM `{$mt}` WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ? FOR UPDATE",
            [$f['provider'],$f['livemode']?'1':'0',$f['account_id'],$s['native_id']]);
        if ($known!==[]) { throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN'); }
    }
    $f=$proof['frame'];$key=sharity_donation_backing_frame_key($f);$table=sharity_donation_backing_table('claims');
    $rows=$q("SELECT claim_key FROM `{$table}` WHERE frame_key = ? ORDER BY claim_key LIMIT 1025 FOR UPDATE",[$key]);
    if (count($rows)>1024) { throw new RuntimeException('DONATION_BACKING_LIMIT'); }
    $held=0;$bySource=[];$history=sharity_donation_fee_accounting_history($q,$key);
    foreach ($rows as $r) {
        $row=sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',[$r['claim_key']]);
        $doc=json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);
        sharity_donation_backing_claim_proof($doc);
        if ($row['frame_key']!==$key || $doc['frame_key']!==$key || $doc['order_id']!==$row['order_id'] || $doc['kind']!==$row['kind']
            || hash('sha256',sharity_donation_finance_json([$doc['order_id'],$doc['kind']]))!==$row['claim_key']
            || !in_array($row['state'],['prepared','attempted','unknown','confirmed','accounted'],true)) { throw new RuntimeException(); }
        if(in_array($row['claim_key'],$skipClaims,true)){continue;}
        if($row['state']==='accounted'){sharity_donation_fee_accounting_claim($q,$row,$history);continue;}
        $held=sharity_donation_backing_sum($held,(int)$doc['amount_minor']);
        foreach ($doc['allocations'] as $a) { $bySource[$a['source_key']]=sharity_donation_backing_sum($bySource[$a['source_key']]??0,(int)$a['amount_minor']); }
    }
    foreach($history+$previewAccounting as$d){$held=sharity_donation_backing_sum($held,(int)$d['residual_minor']);
        foreach($d['sources']as$a){$bySource[$a['source_key']]=sharity_donation_backing_sum($bySource[$a['source_key']]??0,(int)$a['residual_minor']);}}
    $free=(int)$f['retained_minor']-sharity_donation_backing_sum((int)$f['external_liability_minor'],$held);
    $deficit=max(0,-$free);$sourceFree=[];
    foreach ($proof['sources'] as $s) {
        $id=sharity_donation_backing_source_key($s,$f);$sourceFree[$id]=(int)$s['retained_minor']-($bySource[$id]??0);
        $deficit=max($deficit,-$sourceFree[$id]);unset($bySource[$id]);
    }
    if ($bySource!==[]) { throw new RuntimeException('DONATION_BACKING_SOURCE_CONFLICT'); }
    return ['available_minor'=>(string)$free,'held_minor'=>(string)$held,'deficit_minor'=>(string)$deficit,'source_available'=>$sourceFree];
}

function sharity_donation_backing_import(array $input,string $applyDigest,int $now): array
{
    $proof=sharity_donation_backing_proof($input);$json=sharity_donation_finance_json($proof);$digest=hash('sha256',$json);
    if ($applyDigest!=='' && !hash_equals($digest,$applyDigest)) { throw new RuntimeException('DONATION_BACKING_STALE'); }
    $apply=$applyDigest!=='';$f=$proof['frame'];$key=sharity_donation_backing_frame_key($f);
    return sharity_donation_backing_transaction($apply,$now,static fn($q)=>
        sharity_donation_backing_import_locked($q,$proof,$json,$digest,$apply,$f,$key,$now));
}
/** Internal transaction-joining import; never starts or commits another transaction. */
function sharity_donation_backing_import_locked(callable $q,array $proof,string $json,string $digest,bool $apply,array $f,string $key,int $now,bool $deferCapacity=false): array
{
        $current=sharity_donation_backing_latest_locked($q,$key);
        $replay=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? AND digest = ?',[$key,$digest]);
        $expectedSources=[];
        foreach ($proof['sources'] as $s) {
            $sourceKey=sharity_donation_backing_source_key($s,$f);$identity=sharity_donation_finance_json(sharity_donation_backing_source_identity($s,$f));
            $row=['source_key'=>$sourceKey,'frame_key'=>$key,'digest'=>hash('sha256',$identity),'document'=>$identity];
            $old=sharity_donation_backing_row($q,'sources','WHERE source_key = ? FOR UPDATE',[$sourceKey]);
            if ($old!==null) { foreach ($row as $field=>$v) { if ($old[$field]!==$v) { throw new RuntimeException('DONATION_BACKING_SOURCE_CONFLICT'); } } }
            $expectedSources[]=[$row,$old];
        }
        if ($replay===null) {
            if (($current===null ? 0 : (int)$current['revision'])!==$f['previous_revision'] || ($current['digest']??null)!==$f['previous_digest']) { throw new RuntimeException('DONATION_BACKING_STALE'); }
            if (sharity_donation_backing_time($f['as_of_utc'])>$now || sharity_donation_backing_time($f['expires_at_utc'])<=$now) { throw new RuntimeException('DONATION_BACKING_EXPIRED'); }
            if ($current!==null) {
                $oldProof=json_decode($current['document'],true,32,JSON_THROW_ON_ERROR);
                if ($f['as_of_utc']<=$oldProof['frame']['as_of_utc']) { throw new RuntimeException('DONATION_BACKING_STALE'); }
                $next=[];foreach($proof['sources'] as $s){$next[sharity_donation_backing_source_key($s,$f)]=$s;}
                foreach($oldProof['sources'] as $s){$k=sharity_donation_backing_source_key($s,$f);
                    if (!isset($next[$k]) || (int)$next[$k]['retained_minor']>(int)$s['retained_minor'] || $next[$k]['as_of_utc']<$s['as_of_utc']) { throw new RuntimeException('DONATION_BACKING_SOURCE_CONFLICT'); }
                }
            }
            // A known donation movement cannot be relabelled as platform-owned collateral.
            foreach ($proof['sources'] as $s) {
                if ($s['kind']!=='own_collateral' || (int)$s['retained_minor']===0) { continue; }
                $mt=sharity_donation_movement_table();
                $known=$q("SELECT order_id FROM `{$mt}` WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ? FOR UPDATE",
                    [$f['provider'],$f['livemode']?'1':'0',$f['account_id'],$s['native_id']]);
                if ($known!==[]) { throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN'); }
            }
            // Sources precede orders/movements in the common lock order. No foreign order cash.
            $receiptSources=array_filter($proof['sources'],static fn($s)=>$s['kind']==='order_receipt');
            usort($receiptSources,static fn($a,$b)=>strcmp($a['order_id'],$b['order_id']));
            foreach ($receiptSources as $s) {
                $orders=sharity_donation_order_table();$or=$q("SELECT * FROM `{$orders}` WHERE order_id = ? FOR UPDATE",[$s['order_id']]);
                if(count($or)!==1){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
                $snapshot=sharity_donation_order_decode($or[0])['snapshot'];
                if($or[0]['state']!=='session_recorded'||$snapshot['livemode']!==$f['livemode']){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
                $role=$snapshot['account_id']===$f['account_id']?'charge':'application_fee';
                if($role==='application_fee'&&$snapshot['donation_mode']!=='amplifier'){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
                $mt=sharity_donation_movement_table();$mr=$q("SELECT order_id,SUBSTRING(CAST(facts_json AS BINARY),1,65537) AS facts_json,financial_digest FROM `{$mt}` WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ? FOR UPDATE",[$f['provider'],$f['livemode']?'1':'0',$f['account_id'],$s['native_id']]);
                if(count($mr)!==1 || strlen($mr[0]['facts_json'])>65536 || $mr[0]['order_id']!==$s['order_id'] || $mr[0]['financial_digest']!==$s['evidence']['sha256'] || hash('sha256',$mr[0]['facts_json'])!==$mr[0]['financial_digest']) { throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN'); }
                $facts=json_decode($mr[0]['facts_json'],true,32,JSON_THROW_ON_ERROR);
                if($facts['provider']!==$f['provider']||$facts['livemode']!==$f['livemode']||$facts['account_id']!==$f['account_id']
                    ||$facts['order_id']!==$s['order_id']||$facts['balance_transaction_id']!==$s['native_id']
                    ||$facts['movement_role']!==$role||$facts['currency']!==$f['currency']||$facts['net_minor']!==$s['principal_minor']){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
            }
        }
        // Existing claims survive a decrease; the deficit is recorded, not silently hidden.
        $capacityProof=$replay!==null && $current!==null ? json_decode($current['document'],true,32,JSON_THROW_ON_ERROR) : $proof;
        $capacity=$deferCapacity?[]:sharity_donation_backing_capacity($q,$capacityProof);
        $row=$replay??['frame_key'=>$key,'revision'=>(string)($f['previous_revision']+1),'digest'=>$digest,'document'=>$json,'created_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
        if($row['document']!==$json){throw new RuntimeException();}
        if($apply && $replay===null){
            foreach($expectedSources as [$source,$old]){if($old===null){sharity_donation_backing_insert($q,'sources',$source+['created_at_utc'=>$row['created_at_utc']]);}}
            sharity_donation_backing_insert($q,'frames',$row);
        }
        $receipt=['scope'=>'operator_assertion','apply'=>$apply,'frame_key'=>$key,'revision'=>(int)$row['revision'],'proposal_digest'=>$digest,
            'created_at_utc'=>$row['created_at_utc'],'capacity_frame_revision'=>$replay!==null?(int)$current['revision']:(int)$row['revision'],
            'provider_verified'=>false,'retained_cash_verified'=>false,'activation_allowed'=>false]+$capacity;
        unset($receipt['source_available']);
        $readback=static function($query) use($row,$expectedSources):void{
            sharity_donation_backing_match($query,'frames',$row,'WHERE frame_key = ? AND revision = ?',[$row['frame_key'],$row['revision']]);
            foreach($expectedSources as [$s,$old]){sharity_donation_backing_match($query,'sources',$s,'WHERE source_key = ?',[$s['source_key']]);}
        };
        if($apply){$readback($q);}return[$receipt,$readback];
}

function sharity_donation_backing_claim_proof(array $p): array
{
    sharity_donation_backing_keys($p,['version','order_id','kind','frame_key','frame_revision','frame_digest','target_generation','amount_minor','allocations']);
    if($p['version']!==1 || !in_array($p['kind'],['liability','correction','payout','fee_refund'],true) || !is_int($p['frame_revision']) || $p['frame_revision']<1 || $p['frame_revision']>2147483647){throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED');}
    sharity_donation_backing_string($p['order_id'],'/^do_[a-f0-9]{32}$/D');
    foreach(['frame_key','frame_digest','target_generation'] as $k){sharity_donation_backing_string($p[$k],'/^[a-f0-9]{64}$/D');}
    $amount=sharity_donation_backing_amount($p['amount_minor']);
    if($amount===0 || !is_array($p['allocations']) || !array_is_list($p['allocations']) || count($p['allocations'])>256){throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED');}
    $alloc=[];$sum=0;
    foreach($p['allocations'] as $a){
        sharity_donation_backing_keys($a,['source_key','amount_minor']);sharity_donation_backing_string($a['source_key'],'/^[a-f0-9]{64}$/D');
        $n=sharity_donation_backing_amount($a['amount_minor']);if($n===0 || isset($alloc[$a['source_key']])){throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED');}
        $alloc[$a['source_key']]=$a;$sum=sharity_donation_backing_sum($sum,$n);
    }
    if($sum!==$amount){throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED');}
    ksort($alloc,SORT_STRING);$p['allocations']=array_values($alloc);
    return $p;
}
function sharity_donation_backing_target(array $inventory): string
{
    return hash('sha256',sharity_donation_finance_json(['version'=>1,'order_id'=>$inventory['order_id'],
        'evidence_set_digest'=>$inventory['evidence_set_digest'],'financial_inventory_digest'=>$inventory['financial_inventory_digest']]));
}

function sharity_donation_backing_reserve(string $orderId,array $input,string $applyDigest,int $now): array
{
    $proof=sharity_donation_backing_claim_proof($input);if($proof['kind']==='fee_refund'){throw new RuntimeException('DONATION_BACKING_PROOF_MALFORMED');}$json=sharity_donation_finance_json($proof);$digest=hash('sha256',$json);
    if($proof['order_id']!==$orderId || ($applyDigest!==''&&!hash_equals($digest,$applyDigest))){throw new RuntimeException('DONATION_BACKING_STALE');}
    sharity_donation_order_autocommit();$order=sharity_donation_webhook_order($orderId);$snapshot=sharity_donation_order_decode($order)['snapshot'];
    $inventory=sharity_donation_finance_reconcile($orderId,$now);$apply=$applyDigest!=='';
    return sharity_donation_backing_transaction($apply,$now,static function($q)use($proof,$json,$digest,$apply,$order,$snapshot,$inventory,$orderId,$now):array{
        $frame=sharity_donation_backing_latest_locked($q,$proof['frame_key']);
        if($frame===null){throw new RuntimeException('DONATION_BACKING_STALE');}
        $fp=json_decode($frame['document'],true,32,JSON_THROW_ON_ERROR);$f=$fp['frame'];
        if($f['account_id']!==$snapshot['account_id']||$f['livemode']!==$snapshot['livemode']){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
        $sources=[];
        foreach($fp['sources'] as $s){
            $id=sharity_donation_backing_source_key($s,$f);$sources[$id]=$s;$identity=sharity_donation_finance_json(sharity_donation_backing_source_identity($s,$f));
            sharity_donation_backing_match($q,'sources',['source_key'=>$id,'frame_key'=>$proof['frame_key'],'document'=>$identity,'digest'=>hash('sha256',$identity)],'WHERE source_key = ? FOR UPDATE',[$id]);
        }
        $orders=sharity_donation_order_table();$locked=$q("SELECT * FROM `{$orders}` WHERE order_id = ? FOR UPDATE",[$orderId]);
        if(count($locked)!==1 || array_map(static fn($v)=>is_int($v)?(string)$v:$v,$locked[0])!==$order){throw new RuntimeException('DONATION_BACKING_STALE');}
        $key=hash('sha256',sharity_donation_finance_json([$orderId,$proof['kind']]));
        $old=sharity_donation_backing_row($q,'claims','WHERE claim_key = ? FOR UPDATE',[$key]);
        if($old!==null && ($old['document']!==$json||$old['digest']!==$digest||$old['frame_key']!==$proof['frame_key']||$old['order_id']!==$orderId||$old['kind']!==$proof['kind']||!in_array($old['state'],['prepared','attempted','unknown'],true))){throw new RuntimeException('DONATION_BACKING_CLAIM_CONFLICT');}
        sharity_donation_backing_capacity($q,$fp);
        if($old===null){
            if((int)$frame['revision']!==$proof['frame_revision']||$frame['digest']!==$proof['frame_digest']||sharity_donation_backing_target($inventory)!==$proof['target_generation']){throw new RuntimeException('DONATION_BACKING_STALE');}
            if(sharity_donation_backing_time($f['expires_at_utc'])<=$now||sharity_donation_backing_time($f['as_of_utc'])>$now){throw new RuntimeException('DONATION_BACKING_EXPIRED');}
            $ot=sharity_donation_finance_table();$e=$q("SELECT digest,SHA2(canonical_json,256) AS actual FROM `{$ot}` FORCE INDEX (order_digest) WHERE order_id = ? ORDER BY digest LIMIT 257 FOR UPDATE",[$orderId]);
            if(array_column($e,'digest')!==$inventory['evidence_digests']||array_column($e,'actual')!==$inventory['evidence_digests']||$e===[]){throw new RuntimeException('DONATION_BACKING_STALE');}
            $capacity=sharity_donation_backing_capacity($q,$fp);
            if((int)$capacity['deficit_minor']>0||(int)$capacity['available_minor']<(int)$proof['amount_minor']){throw new RuntimeException('DONATION_BACKING_INSUFFICIENT');}
            foreach($fp['sources'] as $s){if((int)$s['retained_minor']>0 && sharity_donation_backing_time($s['expires_at_utc'])<=$now){throw new RuntimeException('DONATION_BACKING_EXPIRED');}}
            foreach($proof['allocations'] as $a){
                $s=$sources[$a['source_key']]??null;
                if($s===null||($s['kind']==='order_receipt'&&$s['order_id']!==$orderId)){throw new RuntimeException('DONATION_BACKING_SOURCE_UNPROVEN');}
                if(($capacity['source_available'][$a['source_key']]??-1)<(int)$a['amount_minor']){throw new RuntimeException('DONATION_BACKING_INSUFFICIENT');}
            }
        }
        $row=$old??['claim_key'=>$key,'frame_key'=>$proof['frame_key'],'order_id'=>$orderId,'kind'=>$proof['kind'],'digest'=>$digest,
            'document'=>$json,'state'=>'prepared','created_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
        if($apply && $old===null){sharity_donation_backing_insert($q,'claims',$row);}
        $readback=static function($query)use($row,$key):void{sharity_donation_backing_match($query,'claims',$row,'WHERE claim_key = ?',[$key]);};
        if($apply){$readback($q);}
        return [['scope'=>'prepared_only','apply'=>$apply,'reserved'=>$apply||$old!==null,'claim_key'=>$key,'order_id'=>$orderId,'kind'=>$proof['kind'],
            'proposal_digest'=>$digest,'state'=>$row['state'],'amount_minor'=>$proof['amount_minor'],'currency'=>$f['currency'],
            'created_at_utc'=>$row['created_at_utc'],'provider_verified'=>false,'settlement_reconciled'=>false,'activation_allowed'=>false],$readback];
    });
}

/** Compatible D2l writer: absent new schema preserves the old path; existing schema must be exact. */
function sharity_donation_backing_lock_movements(callable $q,array $movements): void
{
    $table=sharity_donation_backing_table('sources');
    $status=$q('SHOW TABLE STATUS WHERE Name = ?',[$table]);
    if ($status===[]) { return; }
    if (!sharity_donation_backing_schema_valid($q)) { throw new RuntimeException(); }
    $keys=[];
    foreach ($movements as $movement) {
        $f=$movement['facts'];
        $keys[]=sharity_donation_backing_source_key(['native_id'=>$f['balance_transaction_id']],$f);
    }
    sort($keys,SORT_STRING);
    foreach (array_unique($keys) as $key) { $q("SELECT source_key FROM `{$table}` WHERE source_key = ? FOR UPDATE",[$key]); }
}

/** D2n: immutable targets, not a release of money or a proof of future liability. */
const SHARITY_DONATION_TARGET_SCHEMA_OPTION = 'sharity_donation_target_schema_v1';
function sharity_donation_target_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sharity_donation_targets_v1';
}
function sharity_donation_target_schema_valid(callable $q): bool
{
    $t = sharity_donation_target_table(); $q("SELECT 1 FROM `{$t}` LIMIT 0");
    $status = $q('SHOW TABLE STATUS WHERE Name = ?', [$t]);
    if (count($status) !== 1 || $status[0]['Engine'] !== 'InnoDB' || $status[0]['Collation'] !== 'utf8mb4_bin') { return false; }
    $expected = ['order_id'=>['char(35)','ascii_bin'], 'revision'=>['int unsigned',null],
        'economic_generation'=>['int unsigned',null], 'digest'=>['char(64)','ascii_bin'],
        'document'=>['longtext','utf8mb4_bin'], 'created_at_utc'=>['datetime',null]];
    $columns = $q("SHOW FULL COLUMNS FROM `{$t}`");
    if (count($columns) !== count($expected)) { return false; }
    foreach ($columns as $c) {
        if (($expected[$c['Field']] ?? null) !== [preg_replace('/int\([0-9]+\)/','int',$c['Type']),$c['Collation']]
            || $c['Null'] !== 'NO' || $c['Default'] !== null || $c['Extra'] !== '') { return false; }
    }
    $actual = array_map(static fn($i)=>[$i['Key_name'],(int)$i['Seq_in_index'],$i['Column_name'],(int)$i['Non_unique'],$i['Sub_part'],$i['Index_type']],$q("SHOW INDEX FROM `{$t}`"));
    $want = [['PRIMARY',1,'order_id',0,null,'BTREE'],['PRIMARY',2,'revision',0,null,'BTREE'],
        ['proposal',1,'order_id',0,null,'BTREE'],['proposal',2,'digest',0,null,'BTREE']];
    sort($actual); sort($want); return $actual === $want;
}
function sharity_donation_target_schema_apply(): bool
{
    try {
        [$db,$q] = sharity_donation_backing_db();
        update_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION,'0',false);
        if (get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION) !== '0') { return false; }
        $t = sharity_donation_target_table();
        $q("CREATE TABLE IF NOT EXISTS `{$t}` (
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            revision int unsigned NOT NULL, economic_generation int unsigned NOT NULL,
            digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            document longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            created_at_utc datetime NOT NULL,
            PRIMARY KEY(order_id,revision), UNIQUE KEY proposal(order_id,digest)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
        if (!sharity_donation_target_schema_valid($q)) { return false; }
        update_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION,'1',false);
        return get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION) === '1';
    } catch (Throwable $e) { return false; }
}
function sharity_donation_target_sum(int $a, int $b): int
{
    // Keep every output representable by the same exact minor-unit contract as inputs.
    $n = $a + $b;
    if (!is_int($n) || abs($n) > 999999999999999) { throw new RuntimeException('DONATION_TARGET_LIMIT'); }
    return $n;
}
function sharity_donation_target_compute(array $inventory, array $snapshot): array
{
    $totals = []; $chargeCurrencies = []; $refs = []; $platformTotals = []; $creditGross = []; $feeDebits = []; $feeCredits = [];
    $unresolved = array_values(array_diff($inventory['unresolved'],['cross_order_movement_registry_not_applied']));
    $unresolved[] = 'future_liability_not_proven';
    $fields = ['gross_minor','processing_cost_minor','application_fee_minor','net_minor'];
    foreach ($inventory['movements'] as $m) {
        $f = $m['facts']; $c = $f['currency'];
        if ($f['order_id'] !== $inventory['order_id'] || $f['livemode'] !== $snapshot['livemode']) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        if (in_array($f['movement_role'], ['application_fee','application_fee_refund'], true)) {
            if ($snapshot['donation_mode'] !== 'amplifier' || $f['account_id'] === $snapshot['account_id']) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
            $account = sharity_donation_finance_id($f['account_id'], 'acct');
            if (strlen($account) > 128) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
            $platformTotals[$account][$c] ??= array_fill_keys($fields, 0);
            if (count($platformTotals) !== 1) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
            foreach ($fields as $key) { $platformTotals[$account][$c][$key] = sharity_donation_target_sum($platformTotals[$account][$c][$key], sharity_donation_finance_minor($f[$key])); }
            if ($f['movement_role'] === 'application_fee_refund') { $feeDebits[$f['source_id']] = true; }
            $refs[] = $m['financial_digest'];
            continue;
        }
        if ($f['account_id'] !== $snapshot['account_id'] || !in_array($f['movement_role'],['charge','refund','refund_reversal','dispute','fee_refund_credit'],true)) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        if ($f['movement_role'] === 'charge') { $chargeCurrencies[] = $c; }
        if ($f['movement_role'] === 'fee_refund_credit') {
            if ($snapshot['donation_mode'] !== 'amplifier') { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
            $creditGross[$c] = sharity_donation_target_sum($creditGross[$c] ?? 0, sharity_donation_finance_minor($f['gross_minor']));
            $feeCredits[$f['source_id']] = true;
        }
        $totals[$c] ??= array_fill_keys($fields,0);
        foreach ($fields as $key) { $totals[$c][$key] = sharity_donation_target_sum($totals[$c][$key],sharity_donation_finance_minor($f[$key])); }
        $refs[] = $m['financial_digest'];
    }
    if (count($chargeCurrencies) !== 1) { throw new RuntimeException('DONATION_TARGET_MISSING_CHARGE'); }
    $settlementCurrency = $chargeCurrencies[0]; ksort($totals,SORT_STRING); sort($refs,SORT_STRING);
    foreach ($totals as $c => &$bucket) {
        $donorGross = sharity_donation_target_sum($bucket['gross_minor'], -($creditGross[$c] ?? 0));
        if (isset($creditGross[$c])) { $bucket['inter_account_credit_minor'] = $creditGross[$c]; $bucket['donor_gross_minor'] = $donorGross; }
        $bucket['net_before_application_fee_minor'] = sharity_donation_target_sum($donorGross,-$bucket['processing_cost_minor']);
        $bucket['observed_deficit_minor'] = max(0,-$bucket['net_before_application_fee_minor']);
        foreach ($bucket as &$n) { $n = (string)$n; } unset($n);
    } unset($bucket);
    foreach ($platformTotals as &$currencies) {
        ksort($currencies, SORT_STRING);
        foreach ($currencies as &$bucket) { foreach ($bucket as &$n) { $n = (string) $n; } unset($n); } unset($bucket);
    } unset($currencies);
    $coherent = count($totals) === 1;
    if (!$coherent) { $unresolved[] = 'settlement_currencies_require_executed_fx'; }
    $split = null;
    if ($snapshot['donation_mode'] === 'direct') {
        if ($snapshot['policy_version'] !== 'direct-net100-v1') { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        foreach ($inventory['movements'] as $m) {
            if ($m['facts']['application_fee_minor'] !== '0') { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        }
        if ($inventory['unscoped_platform_movements'] !== []) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        if ($coherent) { $split = ['currency'=>$settlementCurrency,'ngo_minor'=>(string)max(0,(int)$totals[$settlementCurrency]['net_before_application_fee_minor']),'fund_minor'=>'0']; }
    } else {
        if ($snapshot['donation_mode'] !== 'amplifier' || $snapshot['policy_version'] !== 'amplifier-net80-v1') { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
        // Both actual native sides and coherent currency are required. No future-liability claim.
        ksort($feeDebits); ksort($feeCredits);
        $platform = count($platformTotals) === 1 ? reset($platformTotals) : [];
        $missing = $platform === [] || $inventory['unscoped_platform_movements'] !== [];
        $counterlegs = $feeDebits === $feeCredits
            && !in_array('observed_fee_refund_connected_credit_unreconciled', $unresolved, true);
        if ($missing || !$counterlegs) {
            $unresolved[] = $missing ? 'amplifier_platform_and_counterleg_not_proven' : 'amplifier_counterleg_not_proven';
        } elseif (!$coherent || array_keys($platform) !== [$settlementCurrency]) {
            $unresolved[] = 'settlement_currencies_require_executed_fx';
        } else {
            $net = sharity_donation_target_sum((int)$totals[$settlementCurrency]['net_before_application_fee_minor'],
                -sharity_donation_finance_minor($platform[$settlementCurrency]['processing_cost_minor']));
            $observedNet = sharity_donation_target_sum(sharity_donation_finance_minor($totals[$settlementCurrency]['net_minor']),
                sharity_donation_finance_minor($platform[$settlementCurrency]['net_minor']));
            if ($net !== $observedNet) {
                // Different executed FX or missing internal movement cannot become synthetic proceeds.
                $unresolved[] = 'inter_account_movements_not_balanced';
            } else {
                $available = max(0, $net); $fund = intdiv($available, 5);
                $split = ['currency'=>$settlementCurrency, 'ngo_minor'=>(string)($available-$fund), 'fund_minor'=>(string)$fund];
            }
        }
    }
    $unresolved = array_values(array_unique($unresolved)); sort($unresolved,SORT_STRING);
    $policy = array_intersect_key($snapshot,array_flip(['policy_version','donation_mode','ngo_slug','account_id','livemode','currency','amount_minor','package_id','votes','price_book_version','binding_version']));
    return ($platformTotals === [] ? [] : ['platform_totals'=>$platformTotals]) + ['target_version'=>1,'order_id'=>$inventory['order_id'],'status'=>'reconciliation_required',
        'policy'=>$policy,'policy_digest'=>hash('sha256',sharity_donation_finance_json($policy)),
        'evidence_set_digest'=>$inventory['evidence_set_digest'],'financial_inventory_digest'=>$inventory['financial_inventory_digest'],
        'economic_digest'=>hash('sha256',sharity_donation_finance_json([$policy,$inventory['financial_inventory_digest']])),
        'registered_movement_digests'=>$refs,'unscoped_platform_movement_count'=>count($inventory['unscoped_platform_movements']),
        'payment_currency'=>$snapshot['currency'],'connected_settlement_currency'=>$settlementCurrency,
        'connected_totals'=>$totals,'provisional_split'=>$split,'full_liability_minor'=>null,'full_liability_status'=>'unknown',
        'backing_target_generation'=>sharity_donation_backing_target($inventory),
        'unresolved'=>$unresolved,'payment_verified'=>false,'allocation_eligible'=>false,'settlement_reconciled'=>false,'activation_allowed'=>false];
}
function sharity_donation_target_read(callable $q, string $where, array $params): ?array
{
    $t = sharity_donation_target_table(); $max = SHARITY_DONATION_FINANCE_MAX_BYTES+1;
    $rows = $q("SELECT order_id,revision,economic_generation,digest,created_at_utc,SUBSTRING(CAST(document AS BINARY),1,{$max}) AS document FROM `{$t}` {$where}",$params);
    if ($rows === []) { return null; }
    if (count($rows) !== 1) { throw new RuntimeException(); }
    $r = $rows[0]; $doc = json_decode($r['document'],true,64,JSON_THROW_ON_ERROR);
    foreach (['revision','economic_generation'] as $field) {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$r[$field]) || (int)$r[$field] > 2147483647) { throw new RuntimeException(); }
        $r[$field] = (string)$r[$field];
    }
    $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['created_at_utc'],new DateTimeZone('UTC'));
    if (strlen($r['document'])>SHARITY_DONATION_FINANCE_MAX_BYTES || !is_array($doc) || sharity_donation_finance_json($doc)!==$r['document']
        || ($doc['order_id']??null)!==$r['order_id'] || !hash_equals(hash('sha256',$r['document']),$r['digest'])
        || !is_string($doc['economic_digest']??null) || !preg_match('/^[a-f0-9]{64}$/D',$doc['economic_digest'])
        || $time===false || $time->format('Y-m-d H:i:s')!==$r['created_at_utc'] || (int)$r['economic_generation']>(int)$r['revision']) { throw new RuntimeException('DONATION_TARGET_CONFLICT'); }
    return $r;
}
/** D2s: owner-only projection of the last persisted target. Never a live
 * provider check or a recomputation. No transaction, write, lock or SDK call. */
function sharity_donation_financial_read_base(string $status): array
{
    return ['version'=>1, 'status'=>$status, 'snapshot_at_utc'=>null,
        'settlement'=>null, 'provisional_split'=>null, 'settlement_reconciled'=>false,
        'payout_verified'=>false, 'ngo_bank_received'=>false];
}

function sharity_donation_financial_read(string $subject, string $orderId, int $now): array
{
    try {
        if (!preg_match('/^do_[a-f0-9]{32}$/D', $orderId) || $now < 1) { throw new RuntimeException(); }
        $order = sharity_donation_order_read($subject, 'order_id', $orderId);
        if ($order === null) { throw new RuntimeException(); }
        // Keep the internal order_status/public contract free of financial reads.
        $public = sharity_donation_order_public($order, $now);
        $snapshot = sharity_donation_order_decode($order)['snapshot'];
        if (get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION) !== '1') { throw new RuntimeException(); }
        if ($public['state'] !== 'session_recorded') { return sharity_donation_financial_read_base('pending'); }
        [, $q] = sharity_donation_backing_db();
        $saved = sharity_donation_target_read($q, 'WHERE order_id = ? ORDER BY revision DESC LIMIT 1', [$orderId]);
        if ($saved === null) { return sharity_donation_financial_read_base('pending'); }
        $target = json_decode($saved['document'], true, 64, JSON_THROW_ON_ERROR);
        $policy = array_intersect_key($snapshot, array_flip(['policy_version','donation_mode','ngo_slug','account_id','livemode','currency','amount_minor','package_id','votes','price_book_version','binding_version']));
        $policyJson = sharity_donation_finance_json($policy);
        if (($target['target_version'] ?? null) !== 1 || ($target['order_id'] ?? null) !== $orderId
            || ($target['status'] ?? null) !== 'reconciliation_required'
            || sharity_donation_finance_json($target['policy'] ?? null) !== $policyJson
            || ($target['policy_digest'] ?? null) !== hash('sha256', $policyJson)
            || ($target['payment_currency'] ?? null) !== $snapshot['currency']
            || ($target['full_liability_status'] ?? null) !== 'unknown') { throw new RuntimeException(); }
        foreach (['payment_verified','allocation_eligible','settlement_reconciled','activation_allowed'] as $flag) {
            if (($target[$flag] ?? null) !== false) { throw new RuntimeException(); }
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $saved['created_at_utc'], new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $saved['created_at_utc']
            || $date->getTimestamp() < 1 || $date->getTimestamp() > $now) { throw new RuntimeException(); }
        $totals = $target['connected_totals'] ?? null;
        if (!is_array($totals) || count($totals) < 1 || count($totals) > 4
            || !is_string($target['connected_settlement_currency'] ?? null)
            || !isset($totals[$target['connected_settlement_currency']])) { throw new RuntimeException(); }
        ksort($totals, SORT_STRING); $rows = [];
        foreach ($totals as $currency => $bucket) {
            if (!in_array($currency, ['HUF','EUR','USD','GBP'], true) || !is_array($bucket)) { throw new RuntimeException(); }
            // V1 adds these two fields together only for a fee-refund credit.
            if (array_key_exists('inter_account_credit_minor', $bucket) !== array_key_exists('donor_gross_minor', $bucket)) { throw new RuntimeException(); }
            $credit = array_key_exists('inter_account_credit_minor', $bucket) ? $bucket['inter_account_credit_minor'] : '0';
            sharity_donation_finance_minor($credit);
            $row = ['currency'=>$currency, 'inter_account_credit_minor'=>$credit];
            foreach (['donor_gross_minor','processing_cost_minor','application_fee_minor','net_minor','observed_deficit_minor'] as $field) {
                $value = $field === 'donor_gross_minor' && !array_key_exists($field, $bucket) ? ($bucket['gross_minor'] ?? null) : ($bucket[$field] ?? null);
                $number = sharity_donation_finance_minor($value);
                if ($field === 'observed_deficit_minor' && $number < 0) { throw new RuntimeException(); }
                $row[$field] = $value;
            }
            $rows[] = $row;
        }
        if (!array_key_exists('provisional_split', $target)) { throw new RuntimeException(); }
        $split = $target['provisional_split'];
        if ($split !== null) {
            if (!is_array($split) || count($totals) !== 1
                || ($split['currency'] ?? null) !== $target['connected_settlement_currency']
                || sharity_donation_finance_minor($split['ngo_minor'] ?? null) < 0
                || sharity_donation_finance_minor($split['fund_minor'] ?? null) < 0
                || ($snapshot['donation_mode'] === 'direct' && $split['fund_minor'] !== '0')) { throw new RuntimeException(); }
            $split = ['currency'=>$split['currency'], 'ngo_minor'=>$split['ngo_minor'], 'fund_minor'=>$split['fund_minor']];
        }
        return array_replace(sharity_donation_financial_read_base('snapshot'), [
            'snapshot_at_utc'=>$date->format('Y-m-d\TH:i:s\Z'), 'settlement'=>$rows, 'provisional_split'=>$split]);
    } catch (Throwable $error) { return sharity_donation_financial_read_base('unavailable'); }
}

/** CLI-only dry-run/apply. No provider call; no backing mutation or funds release. */
function sharity_donation_finance_target(string $orderId, int $now, string $applyDigest=''): array
{
    $db=null; $active=false;
    try {
        if (get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION)!=='1' || get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)!=='1'
            || $now<1 || $now>253402300799 || ($applyDigest!==''&&!preg_match('/^[a-f0-9]{64}$/D',$applyDigest))) { throw new RuntimeException(); }
        sharity_donation_order_autocommit();
        $order=sharity_donation_webhook_order($orderId); $snapshot=sharity_donation_order_decode($order)['snapshot'];
        $inventory=sharity_donation_finance_reconcile($orderId,$now);
        if(count($inventory['movements'])>4096){throw new RuntimeException('DONATION_TARGET_LIMIT');}
        $target=sharity_donation_target_compute($inventory,$snapshot);$json=sharity_donation_finance_json($target);$digest=hash('sha256',$json);
        if($applyDigest!==''&&!hash_equals($digest,$applyDigest)){throw new RuntimeException('DONATION_TARGET_STALE');}
        [$db,$q]=sharity_donation_backing_db();
        $q('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$q('START TRANSACTION');$active=true;
        $orders=sharity_donation_order_table();$observations=sharity_donation_finance_table();$movements=sharity_donation_movement_table();$table=sharity_donation_target_table();
        $locked=$q("SELECT * FROM `{$orders}` WHERE order_id = ? FOR UPDATE",[$orderId]);
        if(count($locked)!==1||array_map(static fn($v)=>is_int($v)?(string)$v:$v,$locked[0])!==$order){throw new RuntimeException('DONATION_TARGET_STALE');}
        foreach([$orders,$observations,$movements] as $name){$q("SELECT 1 FROM `{$name}` LIMIT 0");$s=$q('SHOW TABLE STATUS WHERE Name = ?',[$name]);if(count($s)!==1||$s[0]['Engine']!=='InnoDB'){throw new RuntimeException();}}
        if(!sharity_donation_target_schema_valid($q)||!sharity_donation_movement_schema_valid($q)){throw new RuntimeException();}
        $e=$q("SELECT account_id,livemode,donation_mode,provider_session_id,digest,SHA2(canonical_json,256) AS actual FROM `{$observations}` FORCE INDEX(order_digest) WHERE order_id = ? ORDER BY digest LIMIT 257 FOR UPDATE",[$orderId]);
        if(array_column($e,'digest')!==$inventory['evidence_digests']||array_column($e,'actual')!==$inventory['evidence_digests']){throw new RuntimeException('DONATION_TARGET_STALE');}
        foreach($e as $row){if($row['account_id']!==$snapshot['account_id']||(string)$row['livemode']!==($snapshot['livemode']?'1':'0')||$row['donation_mode']!==$snapshot['donation_mode']||$row['provider_session_id']!==$order['provider_session_id']){throw new RuntimeException('DONATION_TARGET_STALE');}}
        // Compare the complete registry set, including bodies, not only an untrusted stored hash.
        $rows=$q("SELECT provider,livemode,account_id,balance_transaction_id,order_id,financial_digest,SHA2(facts_json,256) AS actual FROM `{$movements}` FORCE INDEX(order_id) WHERE order_id = ? ORDER BY provider,livemode,account_id,balance_transaction_id LIMIT 4097 FOR UPDATE",[$orderId]);
        $want=[];foreach($inventory['movements'] as $m){$f=$m['facts'];$want[]=['provider'=>$f['provider'],'livemode'=>$f['livemode']?'1':'0','account_id'=>$f['account_id'],'balance_transaction_id'=>$f['balance_transaction_id'],'order_id'=>$orderId,'financial_digest'=>$m['financial_digest'],'actual'=>$m['financial_digest']];}
        $actual=array_map(static fn($r)=>array_map(static fn($v)=>is_int($v)?(string)$v:$v,$r),$rows);
        $sort=static fn($a,$b)=>strcmp(sharity_donation_finance_json($a),sharity_donation_finance_json($b));usort($actual,$sort);usort($want,$sort);
        if($actual!==$want){throw new RuntimeException('DONATION_TARGET_REGISTRY_MISMATCH');}
        $last=sharity_donation_target_read($q,'WHERE order_id = ? ORDER BY revision DESC LIMIT 1 FOR UPDATE',[$orderId]);
        $old=sharity_donation_target_read($q,'WHERE order_id = ? AND digest = ? FOR UPDATE',[$orderId,$digest]);
        if($old!==null&&($last!==$old||$old['document']!==$json)){throw new RuntimeException('DONATION_TARGET_STALE');}
        $revision=$last===null?1:(int)$last['revision']+1;
        $generation=$last===null?1:(int)$last['economic_generation']+(json_decode($last['document'],true)['economic_digest']!==$target['economic_digest']?1:0);
        if($revision>2147483647||strlen($json)>SHARITY_DONATION_FINANCE_MAX_BYTES){throw new RuntimeException('DONATION_TARGET_LIMIT');}
        $row=$old??['order_id'=>$orderId,'revision'=>(string)$revision,'economic_generation'=>(string)$generation,'digest'=>$digest,'document'=>$json,'created_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
        $readback=static function()use($q,$row,$orderId):void{if(sharity_donation_finance_json(sharity_donation_target_read($q,'WHERE order_id = ? AND revision = ?',[$orderId,$row['revision']]))!==sharity_donation_finance_json($row)){throw new RuntimeException('DONATION_TARGET_CONFLICT');}};
        $apply=$applyDigest!=='';
        if($apply&&$old===null){
            $packet=(int)($q('SELECT @@max_allowed_packet AS bytes')[0]['bytes']??0);
            if($packet<=strlen($json)+4096){throw new RuntimeException('DONATION_FINANCE_PACKET_TOO_SMALL');}
            $q("INSERT INTO `{$table}` (order_id,revision,economic_generation,digest,document,created_at_utc) VALUES (?,?,?,?,?,?)",array_values($row));
            $readback();
        }
        $state=$q('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0];
        if((string)$state['ac']!=='1'||(string)$state['tx']!=='1'){throw new RuntimeException();}
        if($apply){try{if(!$db->commit()){throw new RuntimeException();}}catch(Throwable $uncertain){$q('SELECT 1');if(!$db->rollback()){throw new RuntimeException();}}}
        elseif(!$db->rollback()){throw new RuntimeException();}
        $state=$q('SELECT @@autocommit AS ac, @@in_transaction AS tx')[0];
        if((string)$state['ac']!=='1'||(string)$state['tx']!=='0'){throw new RuntimeException();}$active=false;
        if($apply){$readback();}
        return ['scope'=>'provisional_target_only','persisted'=>$apply||$old!==null,'proposal_digest'=>$digest,'revision'=>(int)$row['revision'],
            'economic_generation'=>(int)$row['economic_generation'],'created_at_utc'=>$row['created_at_utc'],'target'=>$target];
    }catch(Throwable $e){
        if($active&&$db instanceof mysqli){try{$db->rollback();}catch(Throwable $ignored){}}
        $codes=['DONATION_TARGET_STALE','DONATION_TARGET_CONFLICT','DONATION_TARGET_REGISTRY_MISMATCH','DONATION_TARGET_MISSING_CHARGE','DONATION_TARGET_LIMIT','DONATION_FINANCE_PACKET_TOO_SMALL'];
        throw new RuntimeException(in_array($e->getMessage(),$codes,true)?$e->getMessage():'DONATION_TARGET_UNAVAILABLE');
    }
}

/**
 * D2y: durable provider payout observation. Never a bank receipt, posting or funding source.
 * Payout debit and failure facts are stored as evidence only, never as donor funding.
 */
const SHARITY_DONATION_PAYOUT_SCHEMA_OPTION = 'sharity_donation_payout_schema_v1';
const SHARITY_DONATION_PAYOUT_MAX_BYTES = 4 * 1024 * 1024;

function sharity_donation_payout_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sharity_donation_payouts_v1';
}

/** Exact read-only topology check shared by apply, record and reconcile. */
function sharity_donation_payout_schema_valid(callable $read): bool
{
    $table = sharity_donation_payout_table();
    $status=$read("SHOW TABLE STATUS WHERE Name = '{$table}'");
    if (count($status)!==1 || ($status[0]['Engine']??null)!=='InnoDB') { return false; }
    $columns = $read("SHOW FULL COLUMNS FROM `{$table}`");
    $expected = ['id' => ['bigint unsigned', null], 'account_id' => ['varchar(128)', 'ascii_bin'],
        'livemode' => ['tinyint(1)', null], 'account_scope' => ['varchar(9)', 'ascii_bin'],
        'payout_id' => ['varchar(255)', 'ascii_bin'], 'canonical_json' => ['longtext', 'utf8mb4_bin'],
        'digest' => ['char(64)', 'ascii_bin'], 'observed_at_utc' => ['datetime', null]];
    if (!is_array($columns) || count($columns) !== count($expected)) { return false; }
    foreach ($columns as $c) {
        $name = $c['Field'];
        if (!isset($expected[$name])) { return false; }
        $type = $c['Type'];
        if ($name === 'id') { $type = preg_replace('/^bigint\([0-9]+\) unsigned$/D', 'bigint unsigned', $type); }
        if ($type !== $expected[$name][0] || $c['Collation'] !== $expected[$name][1]
            || $c['Null'] !== 'NO' || $c['Default'] !== null
            || $c['Extra'] !== ($name === 'id' ? 'auto_increment' : '')) { return false; }
    }
    $indexes = $read("SHOW INDEX FROM `{$table}`");
    if (!is_array($indexes)) { return false; }
    $topology = array_map(static fn($i) => [$i['Key_name'], (int) $i['Seq_in_index'], $i['Column_name'],
        (int) $i['Non_unique'], $i['Sub_part'], $i['Index_type']], $indexes);
    $required = [['PRIMARY', 1, 'id', 0, null, 'BTREE'], ['account_payout_digest', 1, 'account_id', 0, null, 'BTREE'],
        ['account_payout_digest', 2, 'livemode', 0, null, 'BTREE'], ['account_payout_digest', 3, 'payout_id', 0, null, 'BTREE'],
        ['account_payout_digest', 4, 'digest', 0, null, 'BTREE']];
    sort($topology); sort($required);
    return $topology === $required;
}

/** Explicit CLI migration only; incompatible tables are never altered or repaired here. */
function sharity_donation_payout_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION, '') !== '0') { return false; }
        global $wpdb; $table = sharity_donation_payout_table();
        if ($wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            account_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            livemode tinyint(1) NOT NULL,
            account_scope varchar(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            payout_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            canonical_json longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            observed_at_utc datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY account_payout_digest (account_id, livemode, payout_id, digest)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin") === false || $wpdb->last_error !== '') { return false; }
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB') { return false; }
        if (!sharity_donation_payout_schema_valid(static function (string $sql) use ($wpdb): array {
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($rows)) { throw new RuntimeException(); }
            return $rows;
        })) { return false; }
        update_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

function sharity_donation_payout_read(string $accountId, bool $live, string $payoutId, string $digest): ?array
{
    global $wpdb; $table = sharity_donation_payout_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT id,account_id,livemode,account_scope,payout_id,digest,observed_at_utc, SUBSTRING(CAST(canonical_json AS BINARY),1,4194305) AS canonical_json FROM `{$table}` WHERE account_id = %s AND livemode = %d AND payout_id = %s AND digest = %s",
        $accountId, $live ? 1 : 0, $payoutId, $digest), ARRAY_A);
    if ($wpdb->last_error !== '' || ($row !== null && !is_array($row))) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    return $row;
}

/** Whole-row identity and content verification; never acceptance on INSERT alone. */
function sharity_donation_payout_receipt(?array $row, array $expected): array
{
    if ($row === null) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    }
    $id = $row['id'] ?? null; if (is_int($id)) { $id=(string)$id; }
    $time = $row['observed_at_utc'] ?? null; $stamp = $row['canonical_json'] ?? null;
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,19}$/D', $id) || !is_string($time) || !is_string($stamp)
        || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $time)
        || !hash_equals((string) ($row['digest'] ?? ''), hash('sha256', $stamp))) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time, new DateTimeZone('UTC'));
    if ($date === false || $date->format('Y-m-d H:i:s') !== $time) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    try { $decoded = json_decode($stamp, true, 64, JSON_THROW_ON_ERROR); } catch (Throwable $error) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    if (!is_array($decoded) || !is_array($decoded['items'] ?? null) || !is_array($decoded['payout'] ?? null)
        || !is_array($decoded['funding_totals'] ?? null)) { throw new RuntimeException('DONATION_PAYOUT_UNAVAILABLE'); }
    if (strlen($stamp)>SHARITY_DONATION_PAYOUT_MAX_BYTES || !in_array($row['account_scope']??null,['connected','platform'],true)
        || !in_array((string)($row['livemode']??''),['0','1'],true)) { throw new RuntimeException(); }
    $validated=sharity_donation_payout_evidence($decoded,$row['payout_id'],$row['account_id'],(string)$row['livemode']==='1',$row['account_scope']);
    if ($stamp!==sharity_donation_finance_json($validated)) { throw new RuntimeException(); }
    return ['id' => $id, 'account_id' => $row['account_id'], 'livemode' => $row['livemode'],
        'payout_id' => $row['payout_id'], 'digest' => $row['digest'], 'observed_at_utc' => $time];
}


/** Explicit provider read outside any DB transaction, then durable evidence with full readback. */
function sharity_donation_payout_record(string $payoutId, string $accountId, bool $live, string $scope, Sharity_Stripe_Checkout_Adapter $adapter, int $observedAt, ?object $transport=null): array
{
    try {
        if (get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION, '') !== '1' || $observedAt < 1 || $observedAt > 253402300799
            || !in_array($scope, ['connected', 'platform'], true)
            || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $accountId)
            || !preg_match('/^po_[A-Za-z0-9]{1,252}$/D', $payoutId)) { throw new RuntimeException(); }
        sharity_donation_order_autocommit();
        global $wpdb;
        if (!sharity_donation_payout_schema_valid(static function(string $sql) use($wpdb):array {
            $rows=$wpdb->get_results($sql,ARRAY_A);
            if ($wpdb->last_error!=='' || !is_array($rows)) { throw new RuntimeException(); }
            return $rows;
        })) { throw new RuntimeException(); }
        // D2x contract already excludes retries and provider writes; bound it again here.
        $evidence = sharity_donation_webhook_bounded_read(
            static fn() => $adapter->retrievePayoutEvidence($payoutId, $accountId, $live, $scope),
            hrtime(true) / 1e9 + 60, $transport, null, 15);
        $canonical = sharity_donation_payout_evidence($evidence, $payoutId, $accountId, $live, $scope);
        $json = sharity_donation_finance_json($canonical);
        if (strlen($json) > SHARITY_DONATION_PAYOUT_MAX_BYTES) { throw new RuntimeException('DONATION_PAYOUT_EVIDENCE_TOO_LARGE'); }
        $digest = hash('sha256', $json);
        $expected = ['account_id' => $accountId, 'livemode' => $live ? '1' : '0', 'account_scope' => $scope,
            'payout_id' => $payoutId, 'canonical_json' => $json, 'digest' => $digest];
        sharity_donation_order_autocommit();
        $existing = sharity_donation_payout_read($accountId, $live, $payoutId, $digest);
        if ($existing !== null) { return sharity_donation_payout_receipt($existing, $expected); }
        global $wpdb; $table = sharity_donation_payout_table();
        $insert = $expected + ['observed_at_utc' => gmdate('Y-m-d H:i:s', $observedAt)];
        $sql = $wpdb->prepare("INSERT INTO `{$table}` (`" . implode('`,`', array_keys($insert)) . '`) VALUES ('
            . implode(',', array_fill(0, count($insert), '%s')) . ')', ...array_values($insert));
        $packet = $wpdb->get_row('SELECT @@max_allowed_packet AS packet_bytes', ARRAY_A);
        if ($wpdb->last_error !== '' || !is_string($sql) || !ctype_digit((string) ($packet['packet_bytes'] ?? ''))) { throw new RuntimeException(); }
        if ((int) $packet['packet_bytes'] <= strlen($sql) + 1024) { throw new RuntimeException('DONATION_PAYOUT_PACKET_TOO_SMALL'); }
        try { $wpdb->query($sql); } catch (Throwable $uncertain) { /* Unique identity readback decides; never blind reinsert. */ }
        return sharity_donation_payout_receipt(sharity_donation_payout_read($accountId, $live, $payoutId, $digest), $expected);
    } catch (Throwable $error) {
        $code = in_array($error->getMessage(), ['DONATION_PAYOUT_EVIDENCE_TOO_LARGE', 'DONATION_PAYOUT_PACKET_TOO_SMALL',
            'DONATION_PAYOUT_UNAVAILABLE', 'DONATION_PAYOUT_LIMIT'], true) ? $error->getMessage() : 'DONATION_PAYOUT_UNAVAILABLE';
        throw new RuntimeException($code);
    }
}


/** Normalize the D2x allowlist without changing native money or provider identity. */
function sharity_donation_payout_balance(array $b): array
{
    if (!array_key_exists('source_id',$b) || ($b['source_id']!==null && (!is_string($b['source_id'])
        || !preg_match('/^[A-Za-z][A-Za-z0-9]*_[A-Za-z0-9]{1,252}$/D',$b['source_id']) || strlen($b['source_id'])>255))
        || !is_string($b['type']??null) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$b['type'])
        || !is_array($b['fee_details']??null) || count($b['fee_details'])>100) { throw new RuntimeException(); }
    $raw=$b;unset($raw['source_id'],$raw['type'],$raw['scope']);
    // This existing helper validates only the monetary/metadata projection here.
    $m=sharity_donation_finance_movement($raw,$b['source_id']??'','payout_evidence',null,[]);
    $money=['currency','gross_minor','total_fee_minor','net_minor','application_fee_minor','processing_cost_minor','fee_details'];
    foreach (array_slice($money,1,5) as $key) { if (abs((int)$m['facts'][$key])>999999999999) { throw new RuntimeException(); } }
    return ['id'=>$b['id'],'source_id'=>$b['source_id'],'type'=>$b['type']]
        +array_intersect_key($m['facts'],array_flip($money))+$m['observed_metadata'];
}

/** Stored observations are revalidated, including all derived totals/flags. */
function sharity_donation_payout_evidence(array $e,string $id,string $account,bool $live,string $scope): array
{
    if (($e['evidence_version']??null)!==1 || ($e['account_id']??null)!==$account || ($e['livemode']??null)!==$live
        || ($e['account_scope']??null)!==$scope || !in_array($scope,['connected','platform'],true)) { throw new RuntimeException(); }
    foreach (['order_attribution_verified','ngo_bank_received','allocation_eligible'] as $flag) {
        if (($e[$flag]??null)!==false) { throw new RuntimeException(); }
    }
    $p=$e['payout']??null;
    if (!is_array($p) || ($p['id']??null)!==$id || ($p['livemode']??null)!==$live
        || !in_array($p['currency']??null,['HUF','EUR','USD','GBP'],true) || !is_bool($p['automatic']??null)
        || !in_array($p['method']??null,['standard','instant'],true) || !in_array($p['type']??null,['bank_account','card'],true)
        || !in_array($p['status']??null,['pending','in_transit','paid','failed','canceled'],true)
        || !in_array($p['reconciliation_status']??null,['completed','in_progress','not_applicable'],true)
        || !is_int($p['created']??null) || $p['created']<1 || !is_int($p['arrival_date']??null) || $p['arrival_date']<1) { throw new RuntimeException(); }
    $amount=sharity_donation_finance_minor($p['amount_minor']??null);
    if ($amount===0 || abs($amount)>999999999999) { throw new RuntimeException(); }
    foreach (['balance_transaction_id'=>'txn','failure_balance_transaction_id'=>'txn','original_payout_id'=>'po','reversed_by_id'=>'po'] as $key=>$prefix) {
        if (!array_key_exists($key,$p) || ($p[$key]!==null && (!is_string($p[$key]) || !preg_match('/^'.$prefix.'_[A-Za-z0-9]{1,251}$/D',$p[$key])))) { throw new RuntimeException(); }
    }
    if ($amount<0 && $p['original_payout_id']===null) { throw new RuntimeException(); }
    $p=array_intersect_key($p,array_flip(['id','livemode','amount_minor','currency','automatic','method','type','status',
        'reconciliation_status','created','arrival_date','balance_transaction_id','failure_balance_transaction_id','original_payout_id','reversed_by_id']));
    $out=['evidence_version'=>1,'account_id'=>$account,'account_scope'=>$scope,'livemode'=>$live,'payout'=>$p];$unresolved=[];
    foreach (['payout_balance'=>'balance_transaction_id','failure_balance'=>'failure_balance_transaction_id'] as $name=>$ref) {
        if (!array_key_exists($name,$e) || ($e[$name]!==null && !is_array($e[$name]))) { throw new RuntimeException(); }
        $b=$e[$name]===null?null:sharity_donation_payout_balance($e[$name]);
        if (($b===null)!==($p[$ref]===null)) { throw new RuntimeException(); }
        if ($b!==null) {
            $expect=($name==='payout_balance'?-1:1)*$amount;
            if ($b['id']!==$p[$ref] || $b['source_id']!==$id
                || !in_array($b['type'],$name==='payout_balance'?['payout']:['payout_failure','payout_cancel'],true)
                || (((int)$b['gross_minor']<=>0)!==($expect<=>0))
                || ($b['currency']===$p['currency'] && ($b['gross_minor']!==(string)$expect || ($b['exchange_rate']!==null && (float)$b['exchange_rate']!==1.0)))
                || ($b['currency']!==$p['currency'] && $b['exchange_rate']===null)) { throw new RuntimeException(); }
            if ($b['status']!=='available') { $unresolved[]='payout_balance_pending'; }
        }
        $out[$name]=$b;
    }
    $debit=$out['payout_balance'];$failure=$out['failure_balance'];
    if ($debit===null) { $unresolved[]='payout_balance_missing'; }
    if ($debit!==null && $failure!==null && $debit['id']===$failure['id']) { throw new RuntimeException(); }
    if ($p['status']!=='paid') { $unresolved[]='payout_not_paid'; }
    if (in_array($p['status'],['failed','canceled'],true)) {
        if ($failure===null) { $unresolved[]='payout_failure_balance_missing'; }
    } elseif ($failure!==null) { $unresolved[]='payout_failure_observed'; }
    if ($p['original_payout_id']!==null || $p['reversed_by_id']!==null) { $unresolved[]='payout_reversal_requires_reconciliation'; }
    $complete=$p['automatic'] && $p['method']==='standard' && $p['reconciliation_status']==='completed';
    if (!is_array($e['items']??null) || !array_is_list($e['items']) || count($e['items'])>1000 || (!$complete && $e['items']!==[])) { throw new RuntimeException(); }
    $items=[];$totals=[];$own=false;
    foreach ($e['items'] as $raw) {
        if (!is_array($raw)) { throw new RuntimeException(); }$b=sharity_donation_payout_balance($raw);
        if (isset($items[$b['id']])) { throw new RuntimeException(); }$items[$b['id']]=$b;
        if ($b['id']===$p['balance_transaction_id']) {
            if (sharity_donation_finance_json($b)!==sharity_donation_finance_json($debit)) { throw new RuntimeException(); }
            $own=true;continue;
        }
        if ($b['id']===$p['failure_balance_transaction_id']
            && sharity_donation_finance_json($b)!==sharity_donation_finance_json($failure)) { throw new RuntimeException(); }
        if (in_array($b['type'],['payout','payout_failure','payout_cancel'],true)) { $unresolved[]='other_payout_movement_in_batch'; }
        if ($b['status']!=='available') { $unresolved[]='batch_balance_pending'; }
        $cc=$b['currency'];$totals[$cc]??=['gross_minor'=>0,'total_fee_minor'=>0,'net_minor'=>0];
        foreach (array_keys($totals[$cc]) as $key) { $totals[$cc][$key]+=(int)$b[$key]; }
    }
    $balanced=$complete && $debit!==null && count($totals)===1 && isset($totals[$debit['currency']])
        && $totals[$debit['currency']]['net_minor']===-(int)$debit['net_minor'];
    if (!$complete) { $unresolved[]=$p['automatic'] && $p['method']==='standard' && $p['reconciliation_status']==='in_progress'
        ?'payout_reconciliation_pending':'payout_membership_unavailable'; }
    elseif (!$balanced) { $unresolved[]='payout_batch_totals_unreconciled'; }
    foreach ($totals as &$v) { foreach ($v as &$n) { $n=(string)$n; }unset($n); }unset($v);
    sort($unresolved);$unresolved=array_values(array_unique($unresolved));
    $derived=['membership_complete'=>$complete,'own_payout_row_included'=>$own,'funding_totals'=>$totals,
        'batch_totals_reconciled'=>$balanced,'provider_paid_observed'=>$p['status']==='paid','unresolved'=>$unresolved];
    foreach ($derived as $key=>$v) {
        if (!array_key_exists($key,$e) || sharity_donation_finance_json($e[$key])!==sharity_donation_finance_json($v)) { throw new RuntimeException(); }
    }
    return $out+['items'=>array_values($items)]+$derived+['order_attribution_verified'=>false,'ngo_bank_received'=>false,'allocation_eligible'=>false];
}

/** Selected immutable observation + one consistent existing financial-registry snapshot. */
function sharity_donation_payout_reconcile(string $id,string $account,bool $live,string $digest,int $now): array
{
    $db=null;$active=false;
    try {
        if (get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION)!=='1' || get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)!=='1'
            || !preg_match('/^po_[A-Za-z0-9]{1,252}$/D',$id) || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D',$account)
            || !preg_match('/^[a-f0-9]{64}$/D',$digest) || $now<1 || $now>253402300799) { throw new RuntimeException(); }
        [$db,$q]=sharity_donation_backing_db();$q('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $q('START TRANSACTION READ ONLY');$active=true;
        $pt=sharity_donation_payout_table();$mt=sharity_donation_movement_table();
        foreach ([$pt,$mt] as $table) {
            $q("SELECT 1 FROM `{$table}` LIMIT 0");$status=$q('SHOW TABLE STATUS WHERE Name = ?',[$table]);
            if (count($status)!==1 || $status[0]['Engine']!=='InnoDB') { throw new RuntimeException(); }
        }
        if (!sharity_donation_payout_schema_valid($q) || !sharity_donation_movement_schema_valid($q)) { throw new RuntimeException(); }
        $result=sharity_donation_payout_reconcile_snapshot($q,$id,$account,$live,$digest,$now);
        if ((string)($q('SELECT @@in_transaction AS tx')[0]['tx']??'')!=='1') { throw new RuntimeException(); }
        $q('ROLLBACK');$active=false;return $result;
    } catch (Throwable $error) {
        if ($active && $db instanceof mysqli) { try { $db->rollback(); } catch (Throwable $ignored) {} }
        throw new RuntimeException(in_array($error->getMessage(),['DONATION_PAYOUT_CONFLICT','DONATION_PAYOUT_LIMIT'],true)?$error->getMessage():'DONATION_PAYOUT_UNAVAILABLE');
    }
}

// Fee corrections are prepared locally. Dispatch and final settlement are separate steps.
/** Internal read on caller-owned pinned snapshot; never starts/ends a transaction. */
function sharity_donation_payout_reconcile_snapshot(callable $q,string $id,string $account,bool $live,string $digest,int $now): array
{
    if ((string)($q('SELECT @@in_transaction AS tx')[0]['tx']??'')!=='1') { throw new RuntimeException(); }
    $pt=sharity_donation_payout_table();$mt=sharity_donation_movement_table();
        $rows=$q("SELECT id,account_id,livemode,account_scope,payout_id,digest,observed_at_utc,
            SUBSTRING(CAST(canonical_json AS BINARY),1,".(SHARITY_DONATION_PAYOUT_MAX_BYTES+1).") AS canonical_json
            FROM `{$pt}` WHERE account_id=? AND livemode=? AND payout_id=? AND digest=?",[$account,$live?'1':'0',$id,$digest]);
        if (count($rows)!==1) { throw new RuntimeException(); }$row=$rows[0];
        $receipt=sharity_donation_payout_receipt($row,['account_id'=>$account,'livemode'=>$live?'1':'0','payout_id'=>$id,'digest'=>$digest]);
        $e=json_decode($row['canonical_json'],true,64,JSON_THROW_ON_ERROR);
        $items=[];$unmatched=[];$byOrder=[];$mapped=[];$own=[];
        foreach ($e['items'] as $b) {
            if ($b['id']===$e['payout']['balance_transaction_id'] || $b['id']===$e['payout']['failure_balance_transaction_id']) { continue; }
            if (in_array($b['type'],['payout','payout_failure','payout_cancel'],true)) { $own[]=$b;continue; }
            $items[$b['id']]=$b;
        }
        if ($items!==[]) {
            $params=array_merge([$live?'1':'0',$account],array_keys($items));$marks=implode(',',array_fill(0,count($items),'?'));
            $where="WHERE provider='stripe' AND livemode=? AND account_id=? AND balance_transaction_id IN ({$marks})";
            $meta=$q("SELECT balance_transaction_id,OCTET_LENGTH(facts_json) AS bytes FROM `{$mt}` {$where} LIMIT 1001",$params);
            $bytes=0;foreach ($meta as $m) {
                $size=(int)$m['bytes'];$bytes+=$size;
                if ($size<1 || $size>SHARITY_DONATION_FINANCE_MAX_BYTES || $bytes>16*1024*1024) { throw new RuntimeException('DONATION_PAYOUT_LIMIT'); }
            }
            if (count($meta)>1000) { throw new RuntimeException('DONATION_PAYOUT_LIMIT'); }
            $registered=$q("SELECT * FROM `{$mt}` {$where} LIMIT 1001",$params);
            if (count($registered)!==count($meta)) { throw new RuntimeException(); }
            foreach ($registered as $m) {
                $f=json_decode($m['facts_json'],true,64,JSON_THROW_ON_ERROR);$tid=$m['balance_transaction_id'];
                if (!is_array($f) || !isset($items[$tid]) || isset($mapped[$tid]) || !preg_match('/^do_[a-f0-9]{32}$/D',$m['order_id'])
                    || $m['facts_json']!==sharity_donation_finance_json($f) || !hash_equals($m['financial_digest'],hash('sha256',$m['facts_json']))
                    || ($f['provider']??null)!=='stripe' || ($f['livemode']??null)!==$live || ($f['account_id']??null)!==$account
                    || ($f['balance_transaction_id']??null)!==$tid || ($f['order_id']??null)!==$m['order_id']) { throw new RuntimeException('DONATION_PAYOUT_CONFLICT'); }
                $b=$items[$tid];
                foreach (['source_id','currency','gross_minor','total_fee_minor','net_minor','application_fee_minor','processing_cost_minor','fee_details'] as $key) {
                    if (!array_key_exists($key,$f) || sharity_donation_finance_json($f[$key])!==sharity_donation_finance_json($b[$key])) { throw new RuntimeException('DONATION_PAYOUT_CONFLICT'); }
                }
                $type=$f['provider_type']??null;
                if (($type!==null && $type!==$b['type']) || ($type===null && !in_array($b['type'],
                    ($f['movement_role']??null)==='charge'?['charge','payment']:(($f['movement_role']??null)==='application_fee'?['application_fee']:[]),true))) { throw new RuntimeException('DONATION_PAYOUT_CONFLICT'); }
                $mapped[$tid]=true;$byOrder[$m['order_id']][]=$m;
            }
        }
        foreach ($items as $tid=>$b) { if (!isset($mapped[$tid])) { $unmatched[]=$b; } }
        $orders=[];
        foreach ($byOrder as $orderId=>$matches) {
            // Reuse original order/envelope and observation provenance, not current binding.
            $inventory=sharity_donation_finance_reconcile($orderId,$now);$known=[];
            foreach ($inventory['movements'] as $m) { $f=$m['facts'];if ($f['account_id']===$account && $f['livemode']===$live) { $known[$f['balance_transaction_id']]=$m; } }
            $contributions=[];$totals=[];
            foreach ($matches as $m) {
                $tid=$m['balance_transaction_id'];$origin=$known[$tid]??null;
                if ($origin===null || $origin['financial_digest']!==$m['financial_digest']
                    || sharity_donation_finance_json($origin['facts'])!==$m['facts_json']) { throw new RuntimeException('DONATION_PAYOUT_CONFLICT'); }
                $b=$items[$tid];$contributions[]=$b+['movement_role'=>$origin['facts']['movement_role']];
                $totals[$b['currency']]??=['gross_minor'=>0,'total_fee_minor'=>0,'net_minor'=>0];
                foreach (array_keys($totals[$b['currency']]) as $key) { $totals[$b['currency']][$key]+=(int)$b[$key]; }
            }
            foreach ($totals as &$v) { foreach ($v as &$n) { $n=(string)$n; }unset($n); }unset($v);
            $orders[]=['order_id'=>$orderId,'contributions'=>$contributions,'batch_contribution_totals'=>$totals,
                'payout_allocation_minor'=>null,'payout_allocation_verified'=>false];
        }
        return ['payout_report_version'=>1,'scope'=>'selected_observation_registry_snapshot','receipt'=>$receipt,
            'payout'=>$e['payout'],'payout_balance'=>$e['payout_balance'],'failure_balance'=>$e['failure_balance'],
            'provider_paid_observed'=>$e['provider_paid_observed'],'membership_complete'=>$e['membership_complete'],
            'batch_totals_reconciled'=>$e['batch_totals_reconciled'],'funding_totals'=>$e['funding_totals'],
            'matched_orders'=>$orders,'unmatched_items'=>$unmatched,'other_payout_movements'=>$own,
            'movement_matching_complete'=>$e['membership_complete'] && count($items)>0 && $unmatched===[] && $own===[],
            'unresolved'=>$e['unresolved'],'ngo_bank_received'=>false,'allocation_eligible'=>false,'payout_allocation_verified'=>false];
}

/** Owner-only stored payout evidence. No bank receipt, posting, provider call or write. */
function sharity_donation_payout_owner(string $subject,string $orderId,int $now): array
{
    $base=static fn(string $status):array=>['version'=>1,'status'=>$status,'records'=>[],'bank_receipt'=>false];
    $db=null;$active=false;
    try {
        if (!preg_match('/^do_[a-f0-9]{32}$/D',$orderId) || $now<1 || $now>253402300799
            || get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION)!=='1' || get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)!=='1') { throw new RuntimeException(); }
        [$db,$q]=sharity_donation_backing_db();$q('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $q('START TRANSACTION READ ONLY');$active=true;
        foreach ([sharity_donation_order_table(),sharity_donation_finance_table(),sharity_donation_movement_table(),sharity_donation_payout_table()] as $table) {
            $q("SELECT 1 FROM `{$table}` LIMIT 0");$engine=$q('SHOW TABLE STATUS WHERE Name = ?',[$table]);
            if (count($engine)!==1 || $engine[0]['Engine']!=='InnoDB') { throw new RuntimeException(); }
        }
        if (!sharity_donation_payout_schema_valid($q) || !sharity_donation_movement_schema_valid($q)) { throw new RuntimeException(); }
        $order=sharity_donation_order_read($subject,'order_id',$orderId);
        if ($order===null) { throw new RuntimeException(); }
        $public=sharity_donation_order_public($order,$now);$snapshot=sharity_donation_order_decode($order)['snapshot'];
        $result=$base('pending');
        if ($public['state']==='session_recorded') {
            $inventory=sharity_donation_finance_reconcile($orderId,$now);$own=[];
            foreach ($inventory['movements'] as $m) {
                $f=$m['facts'];
                if ($f['account_id']===$snapshot['account_id'] && $f['livemode']===$snapshot['livemode']) { $own[$f['balance_transaction_id']]=true; }
            }
            $pt=sharity_donation_payout_table();$params=[$snapshot['account_id'],$snapshot['livemode']?'1':'0'];
            // Size index first: never download 256 potentially 4MiB bodies blindly.
            $index=$q("SELECT id,OCTET_LENGTH(canonical_json) AS bytes FROM `{$pt}` WHERE account_id=? AND livemode=? ORDER BY id LIMIT 257",$params);
            if (count($index)>256) { throw new RuntimeException(); }$bytes=0;
            foreach ($index as $i) { $n=(int)$i['bytes'];$bytes+=$n;if ($n<1 || $n>SHARITY_DONATION_PAYOUT_MAX_BYTES || $bytes>16*1024*1024) { throw new RuntimeException(); } }
            $groups=[];$reversed=[];
            foreach ($index as $i) {
                $rows=$q("SELECT * FROM `{$pt}` WHERE id=?",[$i['id']]);
                if (count($rows)!==1 || strlen($rows[0]['canonical_json'])!==(int)$i['bytes']) { throw new RuntimeException(); }
                $r=$rows[0];$receipt=sharity_donation_payout_receipt($r,['account_id'=>$params[0],'livemode'=>$params[1]]);
                if ($receipt['observed_at_utc']>gmdate('Y-m-d H:i:s',$now)) { throw new RuntimeException(); }
                $e=json_decode($r['canonical_json'],true,64,JSON_THROW_ON_ERROR);
                if ($e['account_scope']!=='connected') { continue; }
                $p=$e['payout'];$id=$p['id'];$groups[$id]??=['rows'=>[],'relevant'=>false];
                $groups[$id]['rows'][]=['row'=>$r,'e'=>$e];
                foreach ($e['items'] as $b) { if (isset($own[$b['id']])) { $groups[$id]['relevant']=true; } }
                if ($p['original_payout_id']!==null) { $reversed[$p['original_payout_id']]=true; }
            }
            $records=[];$members=[];
            foreach ($groups as $id=>$g) {
                if (!$g['relevant']) { continue; }
                if (count($records)>=50) { throw new RuntimeException(); }
                $candidate=null;$candidateRank=-1;$core=null;$membership=null;$bad=isset($reversed[$id]);$seenAt='';$paid=false;
                foreach ($g['rows'] as $item) {
                    $e=$item['e'];$p=$e['payout'];$at=$item['row']['observed_at_utc'];$seenAt=max($seenAt,$at);
                    if (in_array($p['status'],['failed','canceled'],true) || $p['failure_balance_transaction_id']!==null
                        || $p['reversed_by_id']!==null || $p['original_payout_id']!==null) { $bad=true; }
                    $identity=array_intersect_key($p,array_flip(['id','currency','amount_minor','created','automatic','method','type']));
                    $identity=sharity_donation_finance_json($identity);
                    if ($core!==null && $core!==$identity) { $bad=true; }$core=$identity;
                    if ($e['membership_complete']) {
                        // Ignore the optional own payout row; its inclusion changes no funding.
                        $funding=array_values(array_filter($e['items'],static fn($b)=>!in_array($b['id'],[$p['balance_transaction_id'],$p['failure_balance_transaction_id']],true)));
                        $money=static fn($b)=>$b===null?null:array_intersect_key($b,array_flip(['id','source_id','type','currency','gross_minor','total_fee_minor','net_minor','application_fee_minor','processing_cost_minor','fee_details']));
                        $economic=['balance'=>$money($e['payout_balance']),'items'=>array_map($money,$funding)];
                        $economic=sharity_donation_finance_json($economic);
                        if ($membership!==null && $membership!==$economic) { $bad=true; }$membership=$economic;
                        foreach ($funding as $b) { if (isset($own[$b['id']])) { $members[$b['id']][$id]=true; } }
                    }
                    if ($p['status']==='paid' && $e['membership_complete']) { $paid=true; }
                    if ($e['membership_complete']) {
                        // Prefer complete evidence of the same money, not row/time chronology.
                        $rank=($p['status']==='paid'?2:0)+($e['batch_totals_reconciled'] && $e['unresolved']===[]?1:0);
                        if ($rank>$candidateRank || ($rank===$candidateRank && strcmp($at.$item['row']['digest'],$candidate['row']['observed_at_utc'].$candidate['row']['digest'])>0)) {
                            $candidate=$item;$candidateRank=$rank;
                        }
                    }
                }
                $currency=null;$net=null;$amount=null;
                if ($candidate!==null) {
                    $report=sharity_donation_payout_reconcile_snapshot($q,$id,$params[0],$snapshot['livemode'],$candidate['row']['digest'],$now);
                    $matched=array_values(array_filter($report['matched_orders'],static fn($o)=>$o['order_id']===$orderId));
                    if (count($matched)!==1) { $bad=true; }
                    else {
                        $totals=$matched[0]['batch_contribution_totals'];
                        if (count($totals)===1) { $currency=array_key_first($totals);$net=$totals[$currency]['net_minor']; }
                        else { $bad=true; }
                        $b=$report['payout_balance'];
                        $exact=$report['movement_matching_complete'] && $report['batch_totals_reconciled'] && $report['unresolved']===[]
                            && $currency!==null && $report['payout']['currency']===$currency && $b!==null && $b['currency']===$currency
                            && $b['total_fee_minor']==='0' && (int)$net>0;
                        foreach ($report['matched_orders'] as $o) { foreach ($o['contributions'] as $m) {
                            if ($m['currency']!==$currency || (int)$m['net_minor']<0) { $exact=false; }
                        } }
                        if ($paid && $exact && !$bad) { $amount=$net; } elseif ($paid) { $bad=true; }
                    }
                } elseif ($paid) { $bad=true; }
                $state=$bad?'reconciliation_required':($amount!==null?'paid_observed':'pending');
                $records[$id]=['observed_at_utc'=>str_replace(' ','T',($state==='paid_observed'?$candidate['row']['observed_at_utc']:$seenAt)).'Z',
                    'provider_state'=>$state,'currency'=>$currency,'contribution_minor'=>$net,'payout_minor'=>$state==='paid_observed'?$amount:null,'bank_receipt'=>false];
            }
            // Same movement in two payout batches is unresolved, never counted twice.
            foreach ($members as $ids) { if (count($ids)>1) { foreach ($ids as $id=>$_) {
                $records[$id]['provider_state']='reconciliation_required';$records[$id]['payout_minor']=null;
            } } }
            $records=array_values($records);
            usort($records,static fn($a,$b)=>strcmp(sharity_donation_finance_json($a),sharity_donation_finance_json($b)));
            $state=$records===[]?'pending':'snapshot';
            foreach ($records as $r) { if ($r['provider_state']==='reconciliation_required') { $state='reconciliation_required'; } }
            $result=$base($state);$result['records']=$records;
        }
        if ((string)($q('SELECT @@in_transaction AS tx')[0]['tx']??'')!=='1') { throw new RuntimeException(); }
        $q('ROLLBACK');$active=false;return $result;
    } catch (Throwable $error) {
        if ($active && $db instanceof mysqli) { try { $db->rollback(); } catch (Throwable $ignored) {} }
        return $base('unavailable');
    }
}


const SHARITY_DONATION_FEE_SCHEMA_OPTION = 'sharity_donation_fee_operations_schema_v1';
function sharity_donation_fee_table(): string { global $wpdb; return $wpdb->prefix . 'sharity_donation_fee_operations_v1'; }
function sharity_donation_fee_schema_valid(callable $q): bool
{
    $t=sharity_donation_fee_table();$q("SELECT 1 FROM `{$t}` LIMIT 0");
    $s=$q('SHOW TABLE STATUS WHERE Name = ?',[$t]);
    if(count($s)!==1||$s[0]['Engine']!=='InnoDB'||$s[0]['Collation']!=='utf8mb4_bin'){return false;}
    $expected=['operation_id'=>['char(64)','ascii_bin'],'order_id'=>['char(35)','ascii_bin'],
        'economic_generation'=>['int unsigned',null],'claim_key'=>['char(64)','ascii_bin'],
        'digest'=>['char(64)','ascii_bin'],'document'=>['longtext','utf8mb4_bin'],
        'state'=>['varchar(12)','ascii_bin'],'created_at_utc'=>['datetime',null]];
    $cols=$q("SHOW FULL COLUMNS FROM `{$t}`");if(count($cols)!==count($expected)){return false;}
    foreach($cols as $c){if(!isset($expected[$c['Field']])||[preg_replace('/int\([0-9]+\)/','int',$c['Type']),$c['Collation']]!==$expected[$c['Field']]
        ||$c['Null']!=='NO'||$c['Default']!==null||$c['Extra']!==''){return false;}}
    $actual=array_map(static fn($r)=>[$r['Key_name'],(int)$r['Seq_in_index'],$r['Column_name'],(int)$r['Non_unique'],$r['Sub_part'],$r['Index_type']],$q("SHOW INDEX FROM `{$t}`"));
    $want=[['PRIMARY',1,'operation_id',0,null,'BTREE'],['order_generation',1,'order_id',0,null,'BTREE'],['order_generation',2,'economic_generation',0,null,'BTREE']];
    sort($actual);sort($want);return $actual===$want;
}
function sharity_donation_fee_schema_apply(): bool
{
    try{
        [$db,$q]=sharity_donation_backing_db();$t=sharity_donation_fee_table();
        update_option(SHARITY_DONATION_FEE_SCHEMA_OPTION,'0',false);
        if(get_option(SHARITY_DONATION_FEE_SCHEMA_OPTION)!=='0'){return false;}
        $q("CREATE TABLE IF NOT EXISTS `{$t}` (
            operation_id char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            economic_generation int unsigned NOT NULL,
            claim_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            document longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            state varchar(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at_utc datetime NOT NULL,
            PRIMARY KEY(operation_id), UNIQUE KEY order_generation(order_id,economic_generation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
        if(!sharity_donation_fee_schema_valid($q)||!sharity_donation_fee_receipt_schema_apply($q)||!sharity_donation_fee_accounting_schema_apply($q)){return false;}
        update_option(SHARITY_DONATION_FEE_SCHEMA_OPTION,'1',false);
        return get_option(SHARITY_DONATION_FEE_SCHEMA_OPTION)==='1';
    }catch(Throwable $e){return false;}
}
function sharity_donation_fee_row(callable $q,string $operation): ?array
{
    $t=sharity_donation_fee_table();
    $rows=$q("SELECT operation_id,order_id,economic_generation,claim_key,digest,state,created_at_utc,SUBSTRING(CAST(document AS BINARY),1,65537) AS document FROM `{$t}` WHERE operation_id = ?",[$operation]);
    if($rows===[]){return null;}if(count($rows)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $r=array_map(static fn($v)=>is_int($v)?(string)$v:$v,$rows[0]);
    $d=json_decode($r['document'],true,32,JSON_THROW_ON_ERROR);
    $time=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['created_at_utc'],new DateTimeZone('UTC'));
    if(strlen($r['document'])>65536||!is_array($d)||sharity_donation_finance_json($d)!==$r['document']
        ||hash('sha256',$r['document'])!==$r['digest']||($d['operation_id']??null)!==$r['operation_id']
        ||($d['order_id']??null)!==$r['order_id']||(string)($d['economic_generation']??'')!==$r['economic_generation']
        ||($d['claim_key']??null)!==$r['claim_key']||!in_array($r['state'],['prepared','attempted','unknown','confirmed','accounted'],true)
        ||!$time||$time->format('Y-m-d H:i:s')!==$r['created_at_utc']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    return $r;
}

/** Resolve original nominal fee and every already observed refund; never add observations as cash. */
function sharity_donation_fee_candidate(array $order,array $snapshot,array $inventory,array $target): array
{
    if($snapshot['donation_mode']!=='amplifier'){throw new RuntimeException('DONATION_FEE_MODE');}
    if($target['provisional_split']===null||$inventory['unscoped_platform_movements']!==[]
        ||array_diff($target['unresolved'],['backing_not_reconciled','future_liability_not_proven','payment_pending_observed'])!==[]){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
    $currency=$target['provisional_split']['currency'];$platforms=$target['platform_totals']??[];
    if(count($platforms)!==1||$snapshot['currency']!==$currency){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
    $account=array_key_first($platforms);
    if(array_keys($platforms[$account])!==[$currency]){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
    $original=null;$refunds=[];$reported=0;$completeAmounts=[];
    foreach($inventory['evidence_digests'] as $digest){
        $row=sharity_donation_finance_read($order['order_id'],$digest);
        if($row===null||strlen($row['canonical_json'])>SHARITY_DONATION_FINANCE_MAX_BYTES||hash('sha256',$row['canonical_json'])!==$digest){throw new RuntimeException('DONATION_FEE_STALE');}
        $e=json_decode($row['canonical_json'],true,64,JSON_THROW_ON_ERROR);$o=$e['original'];
        // Reconcile already validates pending rows and exact-facts legacy supersession.
        // Neither can supply current completeness; at least one complete scoped observation is required below.
        if($o['evidence_state']==='payment_pending'){continue;}
        $fee=$o['application_fee']??null;
        if(!is_array($fee)||(isset($o['platform_account_id'])&&$o['platform_account_id']!==$account)||$fee['currency']!==$currency){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
        $amount=sharity_donation_finance_minor($fee['amount_minor']);$b=$fee['initial_balance'];
        $id=sharity_donation_finance_id($fee['id'],'fee');
        if($amount<1||$b['currency']!==$currency||$b['gross_minor']!==(string)$amount){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
        $identity=['fee_id'=>$id,'amount_minor'=>(string)$amount,'balance_id'=>$b['id']];
        if($original!==null&&$original!==$identity){throw new RuntimeException('DONATION_FEE_CONFLICT');}$original=$identity;
        $refunded=sharity_donation_finance_minor($fee['refund_state']['amount_refunded_minor']??null);
        if($refunded<0||$refunded>$amount){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $reported=max($reported,$refunded);
        foreach($e['fee_refunds'] as $r){
            $n=sharity_donation_finance_minor($r['amount_minor']);
            if($n<=0||$r['fee_id']!==$id||$r['currency']!==$currency||isset($refunds[$r['id']])&&$refunds[$r['id']]!==$n){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $refunds[$r['id']]=$n;
        }
        // A complete observation must actually enumerate the latest known nominal fee refunds.
        $own=0;foreach($e['fee_refunds'] as $r){$own=sharity_donation_target_sum($own,(int)$r['amount_minor']);}
        if($own===$refunded&&!$e['balances_pending']&&$e['resources_complete']&&($o['platform_account_id']??null)===$account){$completeAmounts[$refunded]=true;}
    }
    $sum=0;foreach($refunds as $n){$sum=sharity_donation_target_sum($sum,$n);}
    if($original===null||!isset($completeAmounts[$reported])||$sum!==$reported){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
    $native=$platforms[$account][$currency];
    if((int)$native['gross_minor']!==(int)$original['amount_minor']-$reported){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $delta=sharity_donation_target_sum((int)$native['net_minor'],-(int)$target['provisional_split']['fund_minor']);
    if($delta<0||$delta>(int)$original['amount_minor']-$reported){throw new RuntimeException('DONATION_FEE_UNRESOLVED');}
    return $original+['account_id'=>$account,'currency'=>$currency,'amount_refunded_minor'=>(string)$reported,
        'refund_minor'=>(string)$delta,'cumulative_refund_minor'=>(string)($reported+$delta)];
}

/** Atomic operation + reserved platform backing. No provider request or financial allocation. */
function sharity_donation_fee_prepare(string $orderId,array $input,int $now,string $applyDigest=''): array
{
    return sharity_donation_fee_transition($orderId,$input,$now,$applyDigest,false);
}

/** Shared validation/lock order for preparation and the one durable dispatch attempt. */
function sharity_donation_fee_transition(string $orderId,array $input,int $now,string $applyDigest,bool $attempt,?callable $clock=null): array
{
    try{
        if($attempt&&($clock===null||$applyDigest===''||get_option('sharity_donation_fee_execution_enabled','0')!=='1')){throw new RuntimeException('DONATION_FEE_DISPATCH_DISABLED');}
        sharity_donation_backing_keys($input,['version','frame_key','frame_revision','frame_digest','fee_cost_bound_minor','fee_cost_proof','allocations']);
        if($input['version']!==1||!is_int($input['frame_revision'])||$input['frame_revision']<1||$input['frame_revision']>2147483647){throw new RuntimeException('DONATION_FEE_INPUT');}
        foreach(['frame_key','frame_digest'] as $k){sharity_donation_backing_string($input[$k],'/^[a-f0-9]{64}$/D');}
        $cost=sharity_donation_backing_amount($input['fee_cost_bound_minor']);sharity_donation_backing_reference($input['fee_cost_proof']);
        if(get_option(SHARITY_DONATION_FEE_SCHEMA_OPTION)!=='1'||get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION)!=='1'
            ||get_option(SHARITY_DONATION_BACKING_SCHEMA_OPTION)!=='1'||get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)!=='1'){throw new RuntimeException();}
        sharity_donation_order_autocommit();$order=sharity_donation_webhook_order($orderId);$snapshot=sharity_donation_order_decode($order)['snapshot'];
        $inventory=sharity_donation_finance_reconcile($orderId,$now);$target=sharity_donation_target_compute($inventory,$snapshot);
        $candidate=sharity_donation_fee_candidate($order,$snapshot,$inventory,$target);$targetJson=sharity_donation_finance_json($target);$targetDigest=hash('sha256',$targetJson);
        $amount=(int)$candidate['refund_minor'];$held=sharity_donation_backing_sum($amount,$cost);
        $claim=['version'=>1,'order_id'=>$orderId,'kind'=>'fee_refund','frame_key'=>$input['frame_key'],
            'frame_revision'=>$input['frame_revision'],'frame_digest'=>$input['frame_digest'],
            'target_generation'=>sharity_donation_backing_target($inventory),'amount_minor'=>(string)$held,'allocations'=>$input['allocations']];
        if($amount>0){$claim=sharity_donation_backing_claim_proof($claim);}
        elseif($cost!==0||$input['allocations']!==[]){throw new RuntimeException('DONATION_FEE_INPUT');}
        $apply=$applyDigest!=='';
        if($apply&&!preg_match('/^[a-f0-9]{64}$/D',$applyDigest)){throw new RuntimeException('DONATION_FEE_INPUT');}
        return sharity_donation_backing_transaction($apply,$now,static function($q)use($input,$candidate,$targetJson,$targetDigest,$claim,$inventory,$order,$snapshot,$orderId,$now,$apply,$applyDigest,$amount,$held,$attempt,$clock):array{
            // The shared frame and all its source identities precede the order/movement locks.
            $frame=sharity_donation_backing_latest_locked($q,$input['frame_key']);
            if($frame===null||$frame['digest']!==$input['frame_digest']||(int)$frame['revision']!==$input['frame_revision']){throw new RuntimeException('DONATION_FEE_STALE');}
            $fp=json_decode($frame['document'],true,32,JSON_THROW_ON_ERROR);$f=$fp['frame'];
            if($f['account_id']!==$candidate['account_id']||$f['livemode']!==$snapshot['livemode']||$f['currency']!==$candidate['currency']){throw new RuntimeException('DONATION_FEE_SOURCE');}
            $checkedNow=$attempt?$clock():$now;$validUntil=sharity_donation_backing_time($f['expires_at_utc']);
            if(sharity_donation_backing_time($f['as_of_utc'])>$checkedNow||$validUntil<=$checkedNow){throw new RuntimeException('DONATION_BACKING_EXPIRED');}
            $sources=[];
            foreach($fp['sources'] as $s){
                $key=sharity_donation_backing_source_key($s,$f);$sources[$key]=$s;$identity=sharity_donation_finance_json(sharity_donation_backing_source_identity($s,$f));
                sharity_donation_backing_match($q,'sources',['source_key'=>$key,'frame_key'=>$input['frame_key'],'document'=>$identity,'digest'=>hash('sha256',$identity)],'WHERE source_key = ? FOR UPDATE',[$key]);
                if((int)$s['retained_minor']>0){$validUntil=min($validUntil,sharity_donation_backing_time($s['expires_at_utc']));}
                if($validUntil<=($attempt?$clock():$now)){throw new RuntimeException('DONATION_BACKING_EXPIRED');}
            }
            $ot=sharity_donation_order_table();$ft=sharity_donation_finance_table();$mt=sharity_donation_movement_table();
            $locked=$q("SELECT * FROM `{$ot}` WHERE order_id = ? FOR UPDATE",[$orderId]);
            if(count($locked)!==1||array_map(static fn($v)=>is_int($v)?(string)$v:$v,$locked[0])!==$order){throw new RuntimeException('DONATION_FEE_STALE');}
            foreach([$ot,$ft,$mt] as $t){$q("SELECT 1 FROM `{$t}` LIMIT 0");$s=$q('SHOW TABLE STATUS WHERE Name = ?',[$t]);if(count($s)!==1||$s[0]['Engine']!=='InnoDB'){throw new RuntimeException();}}
            if(!sharity_donation_fee_schema_valid($q)||!sharity_donation_target_schema_valid($q)||!sharity_donation_movement_schema_valid($q)){throw new RuntimeException();}
            $rows=$q("SELECT account_id,livemode,donation_mode,provider_session_id,digest,SHA2(canonical_json,256) AS actual FROM `{$ft}` FORCE INDEX(order_digest) WHERE order_id = ? ORDER BY digest LIMIT 257 FOR UPDATE",[$orderId]);
            if(array_column($rows,'digest')!==$inventory['evidence_digests']||array_column($rows,'actual')!==$inventory['evidence_digests']){throw new RuntimeException('DONATION_FEE_STALE');}
            foreach($rows as $r){if($r['account_id']!==$snapshot['account_id']||(string)$r['livemode']!==($snapshot['livemode']?'1':'0')||$r['donation_mode']!==$snapshot['donation_mode']||$r['provider_session_id']!==$order['provider_session_id']){throw new RuntimeException('DONATION_FEE_STALE');}}
            $rows=$q("SELECT provider,livemode,account_id,balance_transaction_id,order_id,financial_digest,SHA2(facts_json,256) AS actual FROM `{$mt}` FORCE INDEX(order_id) WHERE order_id = ? ORDER BY provider,livemode,account_id,balance_transaction_id LIMIT 4097 FOR UPDATE",[$orderId]);
            $want=[];foreach($inventory['movements'] as $m){$f=$m['facts'];$want[]=['provider'=>$f['provider'],'livemode'=>$f['livemode']?'1':'0','account_id'=>$f['account_id'],'balance_transaction_id'=>$f['balance_transaction_id'],'order_id'=>$orderId,'financial_digest'=>$m['financial_digest'],'actual'=>$m['financial_digest']];}
            $actual=array_map(static fn($r)=>array_map(static fn($v)=>is_int($v)?(string)$v:$v,$r),$rows);
            $sort=static fn($a,$b)=>strcmp(sharity_donation_finance_json($a),sharity_donation_finance_json($b));usort($actual,$sort);usort($want,$sort);
            if(count($want)>4096||$actual!==$want){throw new RuntimeException('DONATION_FEE_STALE');}
            $saved=sharity_donation_target_read($q,'WHERE order_id = ? ORDER BY revision DESC LIMIT 1 FOR UPDATE',[$orderId]);
            if($saved===null||$saved['digest']!==$targetDigest||$saved['document']!==$targetJson){throw new RuntimeException('DONATION_FEE_STALE');}
            $op=hash('sha256',sharity_donation_finance_json([$orderId,'application_fee_refund',$saved['economic_generation'],$candidate['cumulative_refund_minor']]));
            $key=hash('sha256',sharity_donation_finance_json([$orderId,'fee_refund']));
            $request=['fee_id'=>$candidate['fee_id'],'amount_minor'=>$candidate['refund_minor'],'metadata'=>['sharity_operation'=>$op]];
            $document=['version'=>1,'operation_id'=>$op,'order_id'=>$orderId,'economic_generation'=>(int)$saved['economic_generation'],
                'target_digest'=>$targetDigest,'target_revision'=>(int)$saved['revision'],'evidence_set_digest'=>$inventory['evidence_set_digest'],
                'financial_inventory_digest'=>$inventory['financial_inventory_digest'],'platform_account_id'=>$candidate['account_id'],'livemode'=>$snapshot['livemode'],
                'currency'=>$candidate['currency'],'candidate'=>$candidate,'request'=>$request,'request_digest'=>hash('sha256',sharity_donation_finance_json($request)),
                'idempotency_key'=>'sharity:fee-refund:'.$op,'claim_key'=>$key,'claim'=>$claim,
                'fee_cost_bound_minor'=>$input['fee_cost_bound_minor'],'fee_cost_proof'=>$input['fee_cost_proof']];
            $json=sharity_donation_finance_json($document);$digest=hash('sha256',$json);
            if(strlen($json)>65536){throw new RuntimeException('DONATION_FEE_INPUT');}
            if($apply&&!hash_equals($digest,$applyDigest)){throw new RuntimeException('DONATION_FEE_STALE');}
            $base=['proposal_digest'=>$digest,'refund_minor'=>$candidate['refund_minor'],'currency'=>$candidate['currency'],
                'provider_action'=>'none','activation_allowed'=>false,'settlement_reconciled'=>false,'allocation_eligible'=>false,'retained_cash_verified'=>false];
            $old=sharity_donation_backing_row($q,'claims','WHERE claim_key = ? FOR UPDATE',[$key]);
            $replacing=$old!==null&&$old['state']==='accounted';
            if($replacing){
                $previous=sharity_donation_fee_accounting_claim($q,$old,sharity_donation_fee_accounting_history($q,$input['frame_key']));
                if($previous['request']['fee_id']!==$candidate['fee_id']||$previous['platform_account_id']!==$candidate['account_id']||$previous['currency']!==$candidate['currency']
                    ||($amount>0&&((int)$candidate['cumulative_refund_minor']<=(int)$previous['candidate']['cumulative_refund_minor']||(int)$saved['economic_generation']<=$previous['economic_generation']))){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            }
            if($amount===0){if($old!==null&&!$replacing){throw new RuntimeException('DONATION_FEE_CONFLICT');}return [$base+['state'=>'no_correction','operation_id'=>null,'prepared'=>false],static function($q):void{}];}
            $claimJson=sharity_donation_finance_json($claim);$claimDigest=hash('sha256',$claimJson);
            $opRow=sharity_donation_fee_row($q,$op);
            $expectedClaim=['claim_key'=>$key,'frame_key'=>$input['frame_key'],'order_id'=>$orderId,'kind'=>'fee_refund',
                'digest'=>$claimDigest,'document'=>$claimJson,'state'=>'prepared','created_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
            if($old!==null&&!$replacing){$expectedClaim['created_at_utc']=$old['created_at_utc'];}
            if($replacing){if($opRow!==null||$attempt){throw new RuntimeException('DONATION_FEE_CONFLICT');}}
            elseif(($old===null)!==($opRow===null)||$old!==null&&(sharity_donation_finance_json($old)!==sharity_donation_finance_json($expectedClaim)||$opRow['document']!==$json||$opRow['digest']!==$digest||$opRow['state']!=='prepared')){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $capacity=sharity_donation_backing_capacity($q,$fp);
            if((int)$capacity['deficit_minor']>0){throw new RuntimeException('DONATION_BACKING_INSUFFICIENT');}
            if($old===null||$replacing){
                if((int)$capacity['available_minor']<$held){throw new RuntimeException('DONATION_BACKING_INSUFFICIENT');}
                foreach($claim['allocations'] as $a){
                    $s=$sources[$a['source_key']]??null;
                    if($s===null||$s['kind']==='order_receipt'&&($s['order_id']!==$orderId||$s['native_id']!==$candidate['balance_id'])){throw new RuntimeException('DONATION_FEE_SOURCE');}
                    if(($capacity['source_available'][$a['source_key']]??-1)<(int)$a['amount_minor']){throw new RuntimeException('DONATION_BACKING_INSUFFICIENT');}
                }
            }
            $opRow??=['operation_id'=>$op,'order_id'=>$orderId,'economic_generation'=>$saved['economic_generation'],'claim_key'=>$key,
                'digest'=>$digest,'document'=>$json,'state'=>'prepared','created_at_utc'=>$expectedClaim['created_at_utc']];
            if($attempt){
                if($validUntil<=$clock()){throw new RuntimeException('DONATION_BACKING_EXPIRED');}
                if($old===null||!sharity_donation_fee_receipt_schema_valid($q)||sharity_donation_fee_receipt_read($q,$op)!==null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
                $expectedClaim['state']='attempted';$opRow['state']='attempted';
                $ct=sharity_donation_backing_table('claims');$t=sharity_donation_fee_table();
                $q("UPDATE `{$ct}` SET state = 'attempted' WHERE claim_key = ? AND state = 'prepared'",[$key]);
                if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
                $q("UPDATE `{$t}` SET state = 'attempted' WHERE operation_id = ? AND state = 'prepared'",[$op]);
                if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            }
            $readback=static function($q)use($expectedClaim,$key,$opRow,$op):void{
                sharity_donation_backing_match($q,'claims',$expectedClaim,'WHERE claim_key = ?',[$key]);
                if(sharity_donation_finance_json(sharity_donation_fee_row($q,$op))!==sharity_donation_finance_json($opRow)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            };
            if($apply&&($old===null||$replacing)){
                if($replacing){$ct=sharity_donation_backing_table('claims');
                    $q("UPDATE `{$ct}` SET frame_key=?,order_id=?,kind=?,digest=?,document=?,state='prepared',created_at_utc=? WHERE claim_key=? AND state='accounted' AND digest=?",
                        [$expectedClaim['frame_key'],$orderId,'fee_refund',$claimDigest,$claimJson,$expectedClaim['created_at_utc'],$key,$old['digest']]);
                    if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
                }else{sharity_donation_backing_insert($q,'claims',$expectedClaim);}$t=sharity_donation_fee_table();
                $q("INSERT INTO `{$t}` (operation_id,order_id,economic_generation,claim_key,digest,document,state,created_at_utc) VALUES (?,?,?,?,?,?,?,?)",array_values($opRow));
            }
            if($apply){$readback($q);}
            return [$base+($attempt?['valid_until'=>$validUntil]:[])+['state'=>$attempt?'attempted':'prepared','operation_id'=>$op,'prepared'=>$apply||($old!==null&&!$replacing),'reserved_minor'=>(string)$held,
                'created_at_utc'=>$opRow['created_at_utc'],'idempotency_key'=>$document['idempotency_key'],'request_digest'=>$document['request_digest']],$readback];
        },$attempt);
    }catch(Throwable $e){
        $allowed=['DONATION_FEE_ATTEMPT_UNCERTAIN','DONATION_FEE_DISPATCH_DISABLED','DONATION_FEE_INPUT','DONATION_FEE_MODE','DONATION_FEE_UNRESOLVED','DONATION_FEE_CONFLICT','DONATION_FEE_STALE','DONATION_FEE_SOURCE',
            'DONATION_BACKING_INSUFFICIENT','DONATION_BACKING_EXPIRED','DONATION_BACKING_SOURCE_CONFLICT'];
        throw new RuntimeException(in_array($e->getMessage(),$allowed,true)?$e->getMessage():'DONATION_FEE_UNAVAILABLE');
    }
}

/** D2aa: provider acknowledgement is separate from immutable request and retained cash. */
function sharity_donation_fee_receipt_table(): string
{
    global $wpdb;return $wpdb->prefix.'sharity_donation_fee_receipts_v1';
}
function sharity_donation_fee_receipt_schema_valid(callable $q): bool
{
    $t=sharity_donation_fee_receipt_table();$q("SELECT 1 FROM `{$t}` LIMIT 0");
    $st=$q('SHOW TABLE STATUS WHERE Name = ?',[$t]);
    if(count($st)!==1||$st[0]['Engine']!=='InnoDB'||$st[0]['Collation']!=='utf8mb4_bin'){return false;}
    $want=['operation_id'=>['char(64)','ascii_bin'],'digest'=>['char(64)','ascii_bin'],
        'document'=>['longtext','utf8mb4_bin'],'created_at_utc'=>['datetime',null]];
    $cols=$q("SHOW FULL COLUMNS FROM `{$t}`");if(count($cols)!==4){return false;}
    foreach($cols as $c){if(($want[$c['Field']]??null)!==[$c['Type'],$c['Collation']]
        ||$c['Null']!=='NO'||$c['Default']!==null||$c['Extra']!==''){return false;}}
    $idx=$q("SHOW INDEX FROM `{$t}`");return count($idx)===1&&$idx[0]['Key_name']==='PRIMARY'
        &&$idx[0]['Column_name']==='operation_id'&&(int)$idx[0]['Non_unique']===0&&$idx[0]['Sub_part']===null&&$idx[0]['Index_type']==='BTREE';
}
function sharity_donation_fee_receipt_schema_apply(callable $q): bool
{
    $t=sharity_donation_fee_receipt_table();
    $q("CREATE TABLE IF NOT EXISTS `{$t}` (
        operation_id char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        document longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        created_at_utc datetime NOT NULL, PRIMARY KEY(operation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    return sharity_donation_fee_receipt_schema_valid($q);
}
function sharity_donation_fee_receipt_read(callable $q,string $op): ?array
{
    $t=sharity_donation_fee_receipt_table();
    $rows=$q("SELECT operation_id,digest,created_at_utc,SUBSTRING(CAST(document AS BINARY),1,4097) AS document FROM `{$t}` WHERE operation_id = ?",[$op]);
    if($rows===[]){return null;}if(count($rows)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $r=$rows[0];$d=json_decode($r['document'],true,16,JSON_THROW_ON_ERROR);
    $time=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['created_at_utc'],new DateTimeZone('UTC'));
    if(strlen($r['document'])>4096||!is_array($d)||($d['operation_id']??null)!==$op||$r['operation_id']!==$op
        ||sharity_donation_finance_json($d)!==$r['document']||hash('sha256',$r['document'])!==$r['digest']
        ||!$time||$time->format('Y-m-d H:i:s')!==$r['created_at_utc']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    return $r;
}
function sharity_donation_fee_context(string $op,string $digest): array
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$op)||!preg_match('/^[a-f0-9]{64}$/D',$digest)
        ||get_option(SHARITY_DONATION_FEE_SCHEMA_OPTION)!=='1'){throw new RuntimeException('DONATION_FEE_INPUT');}
    [$db,$q]=sharity_donation_backing_db();
    if(!sharity_donation_fee_schema_valid($q)||!sharity_donation_fee_receipt_schema_valid($q)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $r=sharity_donation_fee_row($q,$op);
    if($r===null||!hash_equals($r['digest'],$digest)){throw new RuntimeException('DONATION_FEE_STALE');}
    $d=json_decode($r['document'],true,32,JSON_THROW_ON_ERROR);
    $order=sharity_donation_webhook_order($r['order_id']);$envelope=sharity_donation_order_decode($order);$s=$envelope['snapshot'];
    $status=sharity_donation_order_status($order['subject_ref'],$order['order_id'],time());
    if($order['state']!=='session_recorded'||$status['state']!=='session_recorded'||$s['donation_mode']!=='amplifier'
        ||$d['livemode']!==$s['livemode']||$d['idempotency_key']!=='sharity:fee-refund:'.$op
        ||hash('sha256',sharity_donation_finance_json($d['request']))!==$d['request_digest']
        ||sharity_donation_finance_json($d['request'])!==sharity_donation_finance_json(['fee_id'=>$d['candidate']['fee_id'],
            'amount_minor'=>$d['candidate']['refund_minor'],'metadata'=>['sharity_operation'=>$op]])){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    return [$r,$d,$s['account_id']];
}
function sharity_donation_fee_receipt_validate(array $d,array $r): array
{
    sharity_donation_backing_keys($r,['id','fee_id','amount_minor','currency','created','operation_id','platform_account_id','livemode']);
    sharity_donation_finance_id($r['id'],'fr');
    if($r['fee_id']!==$d['request']['fee_id']||$r['amount_minor']!==$d['request']['amount_minor']
        ||$r['currency']!==$d['currency']||$r['operation_id']!==$d['operation_id']
        ||$r['platform_account_id']!==$d['platform_account_id']||$r['livemode']!==$d['livemode']
        ||!is_int($r['created'])||$r['created']<1||$r['created']>253402300799){throw new RuntimeException('DONATION_FEE_RECEIPT');}
    return $r;
}
function sharity_donation_fee_result(string $state,?array $receipt=null): array
{
    return ['state'=>$state,'receipt'=>$receipt,'activation_allowed'=>false,'settlement_reconciled'=>false,
        'allocation_eligible'=>false,'retained_cash_verified'=>false,'ngo_bank_receipt'=>false,'backing_released'=>false];
}
function sharity_donation_fee_receipt_save(array $d,array $receipt,int $now): array
{
    $receipt=sharity_donation_fee_receipt_validate($d,$receipt);$json=sharity_donation_finance_json($receipt);
    if(strlen($json)>4096){throw new RuntimeException('DONATION_FEE_RECEIPT');}
    $op=$d['operation_id'];$digest=hash('sha256',$json);
    return sharity_donation_backing_transaction(true,$now,static function($q)use($d,$op,$json,$digest,$now,$receipt):array{
        // Same frame -> order -> operation order. Never update or release the backing claim.
        sharity_donation_backing_latest_locked($q,$d['claim']['frame_key']);
        $ot=sharity_donation_order_table();$q("SELECT order_id FROM `{$ot}` WHERE order_id = ? FOR UPDATE",[$d['order_id']]);
        $t=sharity_donation_fee_table();$q("SELECT operation_id FROM `{$t}` WHERE operation_id = ? FOR UPDATE",[$op]);
        if(!sharity_donation_fee_receipt_schema_valid($q)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $row=sharity_donation_fee_row($q,$op);
        if($row===null||$row['document']!==sharity_donation_finance_json($d)||!in_array($row['state'],['attempted','unknown','confirmed','accounted'],true)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $old=sharity_donation_fee_receipt_read($q,$op);
        if($old!==null&&($old['digest']!==$digest||$old['document']!==$json)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $expected=['operation_id'=>$op,'digest'=>$digest,'document'=>$json,'created_at_utc'=>$old['created_at_utc']??gmdate('Y-m-d H:i:s',$now)];
        if($old===null){$t=sharity_donation_fee_receipt_table();$q("INSERT INTO `{$t}` (operation_id,digest,document,created_at_utc) VALUES (?,?,?,?)",array_values($expected));}
        $read=static function($q)use($op,$expected):void{
            if(sharity_donation_finance_json(sharity_donation_fee_receipt_read($q,$op))!==sharity_donation_finance_json($expected)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        };
        $read($q);return[sharity_donation_fee_result('provider_recorded',$receipt),$read];
    });
}
/** Sole money-write caller. A successful attempt claim is not reconstructible from readback. */
function sharity_donation_fee_execute(string $op,string $digest,object $adapter,int $now,?callable $clock=null): array
{
    $clock=$clock??static fn():int=>time();
    if(get_option('sharity_donation_fee_execution_enabled','0')!=='1'){throw new RuntimeException('DONATION_FEE_DISPATCH_DISABLED');}
    [$row,$d,$account]=sharity_donation_fee_context($op,$digest);
    if($row['state']!=='prepared'){return sharity_donation_fee_result('reconciliation_required');}
    // Provider identity/completeness read is outside the transaction, before claiming an attempt.
    $adapter->verifyApplicationFeeRefund($d,$account,true);
    $c=$d['claim'];$input=['version'=>1,'frame_key'=>$c['frame_key'],'frame_revision'=>$c['frame_revision'],
        'frame_digest'=>$c['frame_digest'],'allocations'=>$c['allocations'],
        'fee_cost_bound_minor'=>$d['fee_cost_bound_minor'],'fee_cost_proof'=>$d['fee_cost_proof']];
    $attempt=sharity_donation_fee_transition($d['order_id'],$input,$now,$digest,true,$clock);
    // No restart/retry path may reach this line after an uncertain claim or provider response.
    try{
        sharity_donation_order_autocommit();
        if(get_option('sharity_donation_fee_execution_enabled','0')!=='1'||$clock()>=$attempt['valid_until']){return sharity_donation_fee_result('reconciliation_required');}
        $r=$adapter->createApplicationFeeRefund($d);
        return sharity_donation_fee_receipt_save($d,$r,$now);
    }catch(Throwable $uncertain){return sharity_donation_fee_result('reconciliation_required');}
}
/** Historical GET-only recovery stays available when new dispatch or checkout is disabled. */
function sharity_donation_fee_recover(string $op,string $digest,object $adapter,int $now): array
{
    [$row,$d,$account]=sharity_donation_fee_context($op,$digest);
    if($row['state']==='prepared'){return sharity_donation_fee_result('not_attempted');}
    $adapter->verifyApplicationFeeRefund($d,$account,false);
    $matches=$adapter->findApplicationFeeRefunds($d);
    if(count($matches)!==1){return sharity_donation_fee_result('reconciliation_required');}
    return sharity_donation_fee_receipt_save($d,$matches[0],$now);
}

/** Match a specific executed refund to both registered native money legs. Cash remains held. */
function sharity_donation_fee_reconcile(string $op,string $digest,int $now): array
{
    [$initial,$doc,$connected]=sharity_donation_fee_context($op,$digest);
    if($initial['state']==='accounted'){
        return sharity_donation_backing_transaction(false,$now,static function($q)use($op,$digest,$doc):array{
            sharity_donation_backing_latest_locked($q,$doc['claim']['frame_key']);
            $row=sharity_donation_fee_accounting_row($q,$op);if($row===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $d=sharity_donation_fee_accounting_verify($q,$row);if($d['operation_digest']!==$digest){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            return[sharity_donation_fee_account_result($row),static function($q):void{}];
        });
    }
    return sharity_donation_backing_transaction(true,$now,static function($q)use($op,$digest,$initial,$doc,$connected,$now):array{
        $frame=sharity_donation_backing_latest_locked($q,$doc['claim']['frame_key']);
        if($frame===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        // The initial context may predate a concurrent accounting commit. Never demote it.
        if((sharity_donation_fee_row($q,$op)['state']??null)==='accounted'){
            $accounted=sharity_donation_fee_accounting_row($q,$op);if($accounted===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $ad=sharity_donation_fee_accounting_verify($q,$accounted);if($ad['operation_digest']!==$digest){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            return[sharity_donation_fee_account_result($accounted),static function($q)use($accounted):void{sharity_donation_fee_accounting_verify($q,$accounted);}];
        }
        $fp=json_decode($frame['document'],true,32,JSON_THROW_ON_ERROR);$f=$fp['frame'];
        if($f['provider']!=='stripe'||$f['account_id']!==$doc['platform_account_id']||$f['livemode']!==$doc['livemode']||$f['currency']!==$doc['currency']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        [$receipt,$storedReceipt,$matched,$expectedMovements,$wanted]=sharity_donation_fee_match_locked($q,$doc,$connected,$now);
        $t=sharity_donation_fee_table();$q("SELECT operation_id FROM `{$t}` WHERE operation_id = ? FOR UPDATE",[$op]);
        $row=sharity_donation_fee_row($q,$op);
        if($row===null||$row['document']!==$initial['document']||$row['digest']!==$digest||!in_array($row['state'],['attempted','unknown','confirmed'],true)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $claim=sharity_donation_backing_row($q,'claims','WHERE claim_key = ? FOR UPDATE',[$doc['claim_key']]);
        $claimJson=sharity_donation_finance_json(sharity_donation_backing_claim_proof($doc['claim']));
        if($claim===null||$claim['claim_key']!==hash('sha256',sharity_donation_finance_json([$doc['order_id'],'fee_refund']))
            ||$claim['frame_key']!==$doc['claim']['frame_key']||$claim['order_id']!==$doc['order_id']||$claim['kind']!=='fee_refund'
            ||$claim['document']!==$claimJson||$claim['digest']!==hash('sha256',$claimJson)||$claim['state']!==$row['state']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        if(sharity_donation_fee_receipt_read($q,$op)!==$storedReceipt){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        if($row['state']!=='confirmed'){
            $ct=sharity_donation_backing_table('claims');$oldState=$row['state'];
            $q("UPDATE `{$ct}` SET state = 'confirmed' WHERE claim_key = ? AND state = ?",[$doc['claim_key'],$oldState]);
            if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $q("UPDATE `{$t}` SET state = 'confirmed' WHERE operation_id = ? AND state = ?",[$op,$oldState]);
            if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        }
        $row['state']='confirmed';$claim['state']='confirmed';ksort($matched,SORT_STRING);
        $read=static function($q)use($op,$row,$claim,$doc,$storedReceipt,$expectedMovements):void{
            if(sharity_donation_fee_row($q,$op)!==$row||sharity_donation_fee_receipt_read($q,$op)!==$storedReceipt){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            sharity_donation_backing_match($q,'claims',$claim,'WHERE claim_key = ?',[$doc['claim_key']]);
            foreach($expectedMovements as [$sql,$params,$expected]){if($q($sql,$params)!==[$expected]){throw new RuntimeException('DONATION_FEE_CONFLICT');}}
        };
        $read($q);
        return[sharity_donation_fee_result('correction_confirmed',$receipt)+['correction_confirmed'=>true,'matched_movements'=>$matched,
            'reserved_minor'=>$doc['claim']['amount_minor']],$read];
    });
}

/** Reuses the D2ac original receipt + two available native movement proof under caller locks. */
function sharity_donation_fee_match_locked(callable $q,array $doc,string $connected,int $now): array
{
    $op=$doc['operation_id'];
        $ot=sharity_donation_order_table();$orderRows=$q("SELECT * FROM `{$ot}` WHERE order_id = ? FOR UPDATE",[$doc['order_id']]);
        if(count($orderRows)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $order=sharity_donation_webhook_order($doc['order_id']);$s=sharity_donation_order_decode($order)['snapshot'];
        $status=sharity_donation_order_status($order['subject_ref'],$order['order_id'],$now);
        if($order['state']!=='session_recorded'||$status['state']!=='session_recorded'||$s['donation_mode']!=='amplifier'
            ||$s['account_id']!==$connected||$s['livemode']!==$doc['livemode']||$s['currency']!==$doc['currency']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        if(!sharity_donation_fee_schema_valid($q)||!sharity_donation_fee_receipt_schema_valid($q)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $storedReceipt=sharity_donation_fee_receipt_read($q,$op);
        if($storedReceipt===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $receipt=sharity_donation_fee_receipt_validate($doc,json_decode($storedReceipt['document'],true,16,JSON_THROW_ON_ERROR));
        // Lock the immutable observation range before reading; later inserts cannot change this proof.
        $ft=sharity_donation_finance_table();
        $index=$q("SELECT digest,SHA2(canonical_json,256) AS actual FROM `{$ft}` FORCE INDEX(order_digest) WHERE order_id = ? ORDER BY digest LIMIT 257 FOR UPDATE",[$doc['order_id']]);
        $inventory=sharity_donation_finance_reconcile($doc['order_id'],$now);
        if(array_column($index,'digest')!==$inventory['evidence_digests']||array_column($index,'actual')!==$inventory['evidence_digests']){throw new RuntimeException('DONATION_FEE_STALE');}
        $legs=null;
        foreach($inventory['evidence_digests'] as $evidenceDigest){
            $e=sharity_donation_finance_read($doc['order_id'],$evidenceDigest);
            if($e===null||hash('sha256',$e['canonical_json'])!==$evidenceDigest){throw new RuntimeException('DONATION_FEE_STALE');}
            $e=json_decode($e['canonical_json'],true,64,JSON_THROW_ON_ERROR);
            foreach($e['fee_refunds'] as $refund){
                if($refund['id']!==$receipt['id']){continue;}
                if($refund['fee_id']!==$receipt['fee_id']||$refund['amount_minor']!==$receipt['amount_minor']
                    ||$refund['currency']!==$receipt['currency']||($refund['created']??null)!==$receipt['created']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
                $debit=$refund['balance'];$credit=$refund['connected_credit'];
                if(!$refund['connected_credit_verified']||$debit===null||$credit===null||!$e['resources_complete']||$e['balances_pending']){continue;}
                if(($e['original']['platform_account_id']??null)!==$doc['platform_account_id']||$debit['currency']!==$doc['currency']||$credit['currency']!==$doc['currency']){continue;}
                // An actual available observation is required; local observation order is not provider time.
                if($debit['status']!=='available'||$credit['status']!=='available'){continue;}
                $amount=(int)$receipt['amount_minor'];$cost=(int)$debit['processing_cost_minor'];
                if($debit['gross_minor']!==(string)-$amount||$debit['application_fee_minor']!=='0'||$cost<0
                    ||$cost>(int)$doc['fee_cost_bound_minor']||$debit['net_minor']!==(string)(-$amount-$cost)
                    ||$credit['gross_minor']!==$receipt['amount_minor']||$credit['net_minor']!==$receipt['amount_minor']
                    ||$credit['total_fee_minor']!=='0'){throw new RuntimeException('DONATION_FEE_CONFLICT');}
                $candidate=['application_fee_refund'=>$debit['id'],'fee_refund_credit'=>$credit['id']];
                if($legs!==null&&$legs!==$candidate){throw new RuntimeException('DONATION_FEE_CONFLICT');}$legs=$candidate;$matchedEvidenceDigest=$evidenceDigest;
            }
        }
        if($legs===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $matched=[];$expectedMovements=[];$mt=sharity_donation_movement_table();
        $wanted=[];
        foreach($inventory['movements'] as $m){
            $facts=$m['facts'];$role=$facts['movement_role'];
            if(!isset($legs[$role])||$facts['source_id']!==$receipt['id']){continue;}
            $account=$role==='application_fee_refund'?$doc['platform_account_id']:$connected;
            if($facts['balance_transaction_id']!==$legs[$role]||$facts['account_id']!==$account||$facts['livemode']!==$doc['livemode']
                ||$facts['currency']!==$doc['currency']||$facts['order_id']!==$doc['order_id']||isset($wanted[$role])){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $wanted[$role]=$m;
        }
        if(count($wanted)!==2){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        // Same type locks use the registry primary-key order, not role order.
        uasort($wanted,static fn($a,$b)=>strcmp($a['facts']['account_id'].':'.$a['facts']['balance_transaction_id'],$b['facts']['account_id'].':'.$b['facts']['balance_transaction_id']));
        foreach($wanted as $role=>$m){
            $facts=$m['facts'];$params=['stripe',$doc['livemode']?'1':'0',$facts['account_id'],$facts['balance_transaction_id']];
            $sql="SELECT order_id,financial_digest,SUBSTRING(CAST(facts_json AS BINARY),1,65537) AS facts_json FROM `{$mt}` WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ?";
            $rows=$q($sql.' FOR UPDATE',$params);$expected=['order_id'=>$doc['order_id'],'financial_digest'=>$m['financial_digest'],'facts_json'=>sharity_donation_finance_json($facts)];
            if($rows!==[$expected]||strlen($expected['facts_json'])>65536){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            $expectedMovements[]=[$sql,$params,$expected];
            $matched[$role]=['account_id'=>$facts['account_id'],'balance_transaction_id'=>$facts['balance_transaction_id'],'financial_digest'=>$m['financial_digest']];
        }
    return [$receipt,$storedReceipt,$matched,$expectedMovements,$wanted,$matchedEvidenceDigest];
}

/** D2ad: immutable evidence that a specific correction is reflected in retained cash. */
function sharity_donation_fee_accounting_table(): string { global $wpdb; return $wpdb->prefix.'sharity_donation_fee_accounting_v1'; }
function sharity_donation_fee_accounting_schema_valid(callable $q): bool
{
    $t=sharity_donation_fee_accounting_table();$q("SELECT 1 FROM `{$t}` LIMIT 0");
    $s=$q('SHOW TABLE STATUS WHERE Name = ?',[$t]);
    if(count($s)!==1||$s[0]['Engine']!=='InnoDB'||$s[0]['Collation']!=='utf8mb4_bin'){return false;}
    $want=['operation_id'=>['char(64)','ascii_bin'],'frame_key'=>['char(64)','ascii_bin'],
        'frame_revision'=>['int unsigned',null],'residual_minor'=>['bigint unsigned',null],
        'digest'=>['char(64)','ascii_bin'],'document'=>['longtext','utf8mb4_bin'],'created_at_utc'=>['datetime',null]];
    $cols=$q("SHOW FULL COLUMNS FROM `{$t}`");if(count($cols)!==count($want)){return false;}
    foreach($cols as $c){if(!isset($want[$c['Field']])||[preg_replace('/int\([0-9]+\)/','int',$c['Type']),$c['Collation']]!==$want[$c['Field']]
        ||$c['Null']!=='NO'||$c['Default']!==null||$c['Extra']!==''){return false;}}
    $actual=array_map(static fn($r)=>[$r['Key_name'],(int)$r['Seq_in_index'],$r['Column_name'],(int)$r['Non_unique'],$r['Sub_part'],$r['Index_type']],$q("SHOW INDEX FROM `{$t}`"));
    $expected=[['PRIMARY',1,'operation_id',0,null,'BTREE'],['frame_revision',1,'frame_key',0,null,'BTREE'],['frame_revision',2,'frame_revision',0,null,'BTREE'],['frame_residual',1,'frame_key',1,null,'BTREE'],['frame_residual',2,'residual_minor',1,null,'BTREE']];
    sort($actual);sort($expected);return $actual===$expected;
}
function sharity_donation_fee_accounting_schema_apply(callable $q): bool
{
    $t=sharity_donation_fee_accounting_table();
    $q("CREATE TABLE IF NOT EXISTS `{$t}` (
        operation_id char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        frame_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        frame_revision int unsigned NOT NULL, residual_minor bigint unsigned NOT NULL,
        digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        document longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        created_at_utc datetime NOT NULL,
        PRIMARY KEY(operation_id), UNIQUE KEY frame_revision(frame_key,frame_revision), KEY frame_residual(frame_key,residual_minor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    return sharity_donation_fee_accounting_schema_valid($q);
}
function sharity_donation_fee_accounting_row(callable $q,string $op): ?array
{
    $t=sharity_donation_fee_accounting_table();
    $rs=$q("SELECT operation_id,frame_key,frame_revision,residual_minor,digest,created_at_utc,SUBSTRING(CAST(document AS BINARY),1,65537) AS document FROM `{$t}` WHERE operation_id = ?",[$op]);
    if($rs===[]){return null;}if(count($rs)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $r=array_map(static fn($v)=>is_int($v)?(string)$v:$v,$rs[0]);$d=json_decode($r['document'],true,32,JSON_THROW_ON_ERROR);
    $time=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$r['created_at_utc'],new DateTimeZone('UTC'));
    sharity_donation_backing_keys($d,['version','scope','operation_id','operation_digest','input_digest','receipt_digest','evidence_digest','frame_key','original_frame','previous_frame','cash_frame','currency','spent_minor','residual_minor','sources','movements']);
    if(strlen($r['document'])>65536||sharity_donation_finance_json($d)!==$r['document']||hash('sha256',$r['document'])!==$r['digest']
        ||$d['version']!==1||$d['scope']!=='fee_correction_accounting'||$d['operation_id']!==$op||$r['operation_id']!==$op
        ||$d['frame_key']!==$r['frame_key']||(string)$d['cash_frame']['revision']!==$r['frame_revision']||$d['residual_minor']!==$r['residual_minor']
        ||!$time||$time->format('Y-m-d H:i:s')!==$r['created_at_utc']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    foreach(['operation_id','operation_digest','input_digest','receipt_digest','evidence_digest','frame_key']as$k){sharity_donation_backing_string($d[$k],'/^[a-f0-9]{64}$/D');}
    foreach(['original_frame','previous_frame','cash_frame']as$k){sharity_donation_backing_keys($d[$k],['revision','digest']);
        if(!is_int($d[$k]['revision'])||$d[$k]['revision']<1||$d[$k]['revision']>2147483647){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        sharity_donation_backing_string($d[$k]['digest'],'/^[a-f0-9]{64}$/D');}
    if($d['cash_frame']['revision']!==$d['previous_frame']['revision']+1||$d['original_frame']['revision']>$d['previous_frame']['revision']
        ||!in_array($d['currency'],['HUF','EUR','USD','GBP'],true)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $spent=sharity_donation_backing_amount($d['spent_minor']);$residual=sharity_donation_backing_amount($d['residual_minor']);
    if($spent===0||!is_array($d['sources'])||!array_is_list($d['sources'])||count($d['sources'])>256){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $ss=0;$rr=0;$keys=[];
    foreach($d['sources']as$a){sharity_donation_backing_keys($a,['source_key','spent_minor','residual_minor']);sharity_donation_backing_string($a['source_key'],'/^[a-f0-9]{64}$/D');
        if(isset($keys[$a['source_key']])){throw new RuntimeException('DONATION_FEE_CONFLICT');}$keys[$a['source_key']]=true;
        $ss=sharity_donation_backing_sum($ss,sharity_donation_backing_amount($a['spent_minor']));$rr=sharity_donation_backing_sum($rr,sharity_donation_backing_amount($a['residual_minor']));}
    if($ss!==$spent||$rr!==$residual){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    return $r;
}
/** The current claim must be the original accounted hold or an independently valid later fee operation. */
function sharity_donation_fee_accounting_current_claim(callable $q,array $op,array $od,array $account): void
{
    $claim=sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',[$op['claim_key']]);
    if($claim===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $c=sharity_donation_backing_claim_proof(json_decode($claim['document'],true,32,JSON_THROW_ON_ERROR));
    if($claim['claim_key']!==$op['claim_key']||$claim['order_id']!==$op['order_id']||$claim['kind']!=='fee_refund'
        ||$claim['frame_key']!==$account['frame_key']||$c['order_id']!==$op['order_id']||$c['kind']!=='fee_refund'||$c['frame_key']!==$account['frame_key']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    if($c===sharity_donation_backing_claim_proof($od['claim'])){
        if($claim['state']!=='accounted'){throw new RuntimeException('DONATION_FEE_CONFLICT');}return;
    }
    $t=sharity_donation_fee_table();
    $rows=$q("SELECT operation_id FROM `{$t}` WHERE order_id = ? AND economic_generation > ? ORDER BY economic_generation DESC LIMIT 1",[$op['order_id'],$op['economic_generation']]);
    if(count($rows)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}$next=sharity_donation_fee_row($q,$rows[0]['operation_id']);
    if($next===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}$nd=json_decode($next['document'],true,32,JSON_THROW_ON_ERROR);
    if($next['claim_key']!==$op['claim_key']||$next['state']!==$claim['state']||sharity_donation_backing_claim_proof($nd['claim'])!==$c
        ||$nd['request']['fee_id']!==$od['request']['fee_id']||$nd['platform_account_id']!==$od['platform_account_id']||$nd['livemode']!==$od['livemode']||$nd['currency']!==$od['currency']
        ||(int)$nd['candidate']['cumulative_refund_minor']<=(int)$od['candidate']['cumulative_refund_minor']||$c['frame_revision']<$account['cash_frame']['revision']
        ||$nd['request_digest']!==hash('sha256',sharity_donation_finance_json($nd['request']))
        ||$next['operation_id']!==hash('sha256',sharity_donation_finance_json([$op['order_id'],'application_fee_refund',$next['economic_generation'],$nd['candidate']['cumulative_refund_minor']]))
        ||$nd['request']['amount_minor']!==$nd['candidate']['refund_minor']
        ||sharity_donation_backing_sum(sharity_donation_backing_amount($nd['request']['amount_minor']),sharity_donation_backing_amount($nd['fee_cost_bound_minor']))!==(int)$c['amount_minor']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $frame=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? AND revision = ?',[$c['frame_key'],$c['frame_revision']]);
    $target=sharity_donation_target_read($q,'WHERE order_id = ? AND revision = ?',[$op['order_id'],$nd['target_revision']]);
    if($frame===null||$frame['digest']!==$c['frame_digest']||$target===null||$target['digest']!==$nd['target_digest']||$target['economic_generation']!==$next['economic_generation']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    if($next['state']==='accounted'){
        $row=sharity_donation_fee_accounting_row($q,$next['operation_id']);
        if($row===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}$d=json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);
        if($d['operation_digest']!==$next['digest']||$d['frame_key']!==$c['frame_key']||$d['original_frame']!==['digest'=>$c['frame_digest'],'revision'=>$c['frame_revision']]){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    }
}

/** Immutable witnesses plus the current valid claim generation. */
function sharity_donation_fee_accounting_verify(callable $q,array $row): array
{
    $d=json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);$op=sharity_donation_fee_row($q,$d['operation_id']);
    if($op===null||$op['state']!=='accounted'||$op['digest']!==$d['operation_digest']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $od=json_decode($op['document'],true,32,JSON_THROW_ON_ERROR);$c=sharity_donation_backing_claim_proof($od['claim']);
    if($c['kind']!=='fee_refund'||$c['frame_key']!==$d['frame_key']||$c['frame_revision']!==$d['original_frame']['revision']||$c['frame_digest']!==$d['original_frame']['digest']
        ||$od['currency']!==$d['currency']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    sharity_donation_fee_accounting_current_claim($q,$op,$od,$d);
    $receipt=sharity_donation_fee_receipt_read($q,$d['operation_id']);
    if($receipt===null||$receipt['digest']!==$d['receipt_digest']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $rd=sharity_donation_fee_receipt_validate($od,json_decode($receipt['document'],true,16,JSON_THROW_ON_ERROR));
    $evidence=sharity_donation_finance_read($op['order_id'],$d['evidence_digest']);
    if($evidence===null||hash('sha256',$evidence['canonical_json'])!==$d['evidence_digest']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $ev=json_decode($evidence['canonical_json'],true,64,JSON_THROW_ON_ERROR);$link=false;
    foreach($ev['fee_refunds']as$rf){if($rf['id']!==$rd['id']){continue;}
        $link=$rf['fee_id']===$rd['fee_id']&&$rf['amount_minor']===$rd['amount_minor']&&$rf['currency']===$rd['currency']&&$rf['created']===$rd['created']
            &&$rf['connected_credit_verified']&&$ev['resources_complete']&&!$ev['balances_pending']
            &&($rf['balance']['status']??null)==='available'&&($rf['connected_credit']['status']??null)==='available'
            &&($rf['balance']['id']??null)===$d['movements']['application_fee_refund']['facts']['balance_transaction_id']
            &&($rf['connected_credit']['id']??null)===$d['movements']['fee_refund_credit']['facts']['balance_transaction_id'];
    }
    if(!$link){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $alloc=[];foreach($c['allocations']as$a){$alloc[$a['source_key']]=(int)$a['amount_minor'];}
    foreach($d['sources']as$a){if(!isset($alloc[$a['source_key']])||sharity_donation_backing_sum((int)$a['spent_minor'],(int)$a['residual_minor'])!==$alloc[$a['source_key']]){throw new RuntimeException('DONATION_FEE_CONFLICT');}unset($alloc[$a['source_key']]);}
    if($alloc!==[]){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $frames=[];
    foreach(['original_frame','previous_frame','cash_frame']as$kind){$ref=$d[$kind];$f=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? AND revision = ?',[$d['frame_key'],$ref['revision']]);
        if($f===null||$f['digest']!==$ref['digest']){throw new RuntimeException('DONATION_FEE_CONFLICT');}$frames[$kind]=json_decode($f['document'],true,32,JSON_THROW_ON_ERROR);}
    $new=$frames['cash_frame']['frame'];
    if($new['previous_digest']!==$d['previous_frame']['digest']||$new['account_id']!==$od['platform_account_id']||$new['livemode']!==$od['livemode']||$new['currency']!==$od['currency']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    sharity_donation_backing_keys($d['movements'],['application_fee_refund','fee_refund_credit']);
    $ot=sharity_donation_order_table();$orders=$q("SELECT * FROM `{$ot}` WHERE order_id = ?",[$op['order_id']]);
    if(count($orders)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}$snap=sharity_donation_order_decode($orders[0])['snapshot'];
    $mt=sharity_donation_movement_table();$facts=[];
    foreach($d['movements']as$role=>$m){sharity_donation_backing_keys($m,['financial_digest','facts']);$f=$m['facts'];
        $json=sharity_donation_finance_json($f);$expected=['order_id'=>$op['order_id'],'financial_digest'=>$m['financial_digest'],'facts_json'=>$json];
        $rs=$q("SELECT order_id,financial_digest,SUBSTRING(CAST(facts_json AS BINARY),1,65537) AS facts_json FROM `{$mt}` WHERE provider = ? AND livemode = ? AND account_id = ? AND balance_transaction_id = ?",['stripe',$od['livemode']?'1':'0',$f['account_id'],$f['balance_transaction_id']]);
        if(strlen($json)>65536||hash('sha256',$json)!==$m['financial_digest']||$rs!==[$expected]
            ||$f['provider']!=='stripe'||$f['livemode']!==$od['livemode']||$f['order_id']!==$op['order_id']||$f['currency']!==$od['currency']
            ||$f['source_id']!==$rd['id']||$f['movement_role']!==$role||$f['account_id']!==($role==='application_fee_refund'?$od['platform_account_id']:$snap['account_id'])){throw new RuntimeException('DONATION_FEE_CONFLICT');}$facts[$role]=$f;
    }
    $cost=(int)$facts['application_fee_refund']['processing_cost_minor'];$nominal=(int)$rd['amount_minor'];
    if($cost<0||$cost>(int)$od['fee_cost_bound_minor']||$facts['application_fee_refund']['net_minor']!==(string)(-$nominal-$cost)
        ||$facts['fee_refund_credit']['net_minor']!==(string)$nominal||(int)$d['spent_minor']!==$nominal+$cost
        ||(int)$d['residual_minor']!==(int)$c['amount_minor']-(int)$d['spent_minor']){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    return $d;
}
function sharity_donation_fee_accounting_history(callable $q,string $frame): array
{
    $t=sharity_donation_fee_accounting_table();$exists=$q('SHOW TABLE STATUS WHERE Name = ?',[$t]);
    if($exists===[]){return [];}
    if(!sharity_donation_fee_accounting_schema_valid($q)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
    $rs=$q("SELECT operation_id FROM `{$t}` WHERE frame_key = ? ORDER BY frame_revision LIMIT 1025",[$frame]);
    if(count($rs)>1024){throw new RuntimeException('DONATION_BACKING_LIMIT');}$out=[];$bytes=0;
    foreach($rs as$r){$row=sharity_donation_fee_accounting_row($q,$r['operation_id']);
        if($row===null||$row['frame_key']!==$frame){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $bytes+=strlen($row['document']);if($bytes>16777216){throw new RuntimeException('DONATION_BACKING_LIMIT');}
        $out[$r['operation_id']]=sharity_donation_fee_accounting_verify($q,$row);}
    return $out;
}
function sharity_donation_fee_accounting_claim(callable $q,array $claim,array $history): array
{
    foreach($history as$d){$op=sharity_donation_fee_row($q,$d['operation_id']);$od=json_decode($op['document'],true,32,JSON_THROW_ON_ERROR);
        if($op['claim_key']!==$claim['claim_key']||$od['claim']['target_generation']!==json_decode($claim['document'],true)['target_generation']){continue;}
        $json=sharity_donation_finance_json(sharity_donation_backing_claim_proof($od['claim']));
        if($claim['state']!=='accounted'||$claim['document']!==$json||$claim['digest']!==hash('sha256',$json)||$claim['frame_key']!==$d['frame_key']||$claim['order_id']!==$op['order_id']||$claim['kind']!=='fee_refund'){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        return $od;
    }
    throw new RuntimeException('DONATION_FEE_CONFLICT');
}
/** All preparation-frame inequalities; accounting time never implies cash snapshot inclusion. */
function sharity_donation_fee_cash_caps(callable $q,array $proof,array $history): void
{
    $f=$proof['frame'];$frame=sharity_donation_backing_frame_key($f);
    foreach($proof['sources']as$s){$key=sharity_donation_backing_source_key($s,$f);$spends=[];$total=0;$baselines=[];
        foreach($history as$d){foreach($d['sources']as$a){if($a['source_key']!==$key||$a['spent_minor']==='0'){continue;}
            $r=$d['original_frame']['revision'];$spends[$r]=sharity_donation_backing_sum($spends[$r]??0,(int)$a['spent_minor']);$total=sharity_donation_backing_sum($total,(int)$a['spent_minor']);
            $baselines[$r]=$d['original_frame']['digest'];}}
        if((int)$s['retained_minor']>(int)$s['principal_minor']-$total){throw new RuntimeException('DONATION_FEE_SOURCE');}
        foreach($baselines as$r=>$digest){$row=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? AND revision = ?',[$frame,$r]);
            if($row===null||$row['digest']!==$digest){throw new RuntimeException('DONATION_FEE_SOURCE');}
            $p=json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);$base=null;
            foreach($p['sources']as$bs){if(sharity_donation_backing_source_key($bs,$p['frame'])===$key){$base=(int)$bs['retained_minor'];}}
            if($base===null){throw new RuntimeException('DONATION_FEE_SOURCE');}$spent=0;
            foreach($spends as$sr=>$amount){if($sr>=$r){$spent=sharity_donation_backing_sum($spent,$amount);}}
            if((int)$s['retained_minor']>$base-$spent){throw new RuntimeException('DONATION_FEE_SOURCE');}
        }
    }
}
function sharity_donation_fee_account_result(array $row,bool $persisted=true): array
{
    $d=json_decode($row['document'],true,32,JSON_THROW_ON_ERROR);
    return sharity_donation_fee_result($persisted?'correction_accounted':'accounting_proposal')+['accounting_recorded'=>$persisted,'operation_id'=>$row['operation_id'],'proposal_digest'=>$row['digest'],
        'frame_key'=>$row['frame_key'],'frame_revision'=>(int)$row['frame_revision'],'spent_minor'=>$d['spent_minor'],
        'residual_minor'=>$d['residual_minor'],'currency'=>$d['currency'],'created_at_utc'=>$row['created_at_utc'],'provider_action'=>'none'];
}
function sharity_donation_fee_account(string $op,string $digest,array $input,int $now,string $applyDigest=''): array
{
    sharity_donation_backing_keys($input,['version','cash_proof','spent_allocations']);
    if($input['version']!==1||($applyDigest!==''&&!preg_match('/^[a-f0-9]{64}$/D',$applyDigest))){throw new RuntimeException('DONATION_FEE_INPUT');}
    $proof=sharity_donation_backing_proof($input['cash_proof']);$cashJson=sharity_donation_finance_json($proof);$cashDigest=hash('sha256',$cashJson);
    if(!is_array($input['spent_allocations'])||!array_is_list($input['spent_allocations'])||count($input['spent_allocations'])>256){throw new RuntimeException('DONATION_FEE_INPUT');}
    $alloc=[];foreach($input['spent_allocations']as$a){sharity_donation_backing_keys($a,['source_key','spent_minor']);sharity_donation_backing_string($a['source_key'],'/^[a-f0-9]{64}$/D');sharity_donation_backing_amount($a['spent_minor']);
        if(isset($alloc[$a['source_key']])){throw new RuntimeException('DONATION_FEE_INPUT');}$alloc[$a['source_key']]=$a;}ksort($alloc,SORT_STRING);
    $input['cash_proof']=$proof;$input['spent_allocations']=array_values($alloc);$inputDigest=hash('sha256',sharity_donation_finance_json($input));
    [$initial,$od,$connected]=sharity_donation_fee_context($op,$digest);$apply=$applyDigest!=='';
    return sharity_donation_backing_transaction($apply,$now,static function($q)use($op,$digest,$od,$connected,$proof,$cashJson,$cashDigest,$inputDigest,$alloc,$now,$apply,$applyDigest):array{
        if(!sharity_donation_fee_accounting_schema_valid($q)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $key=$od['claim']['frame_key'];$current=sharity_donation_backing_latest_locked($q,$key);
        if($current===null){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $old=sharity_donation_fee_accounting_row($q,$op);
        if($old!==null){$d=sharity_donation_fee_accounting_verify($q,$old);
            if($d['operation_digest']!==$digest||$d['input_digest']!==$inputDigest||($apply&&!hash_equals($old['digest'],$applyDigest))){throw new RuntimeException('DONATION_FEE_STALE');}
            $read=static function($q)use($op,$old):void{if(sharity_donation_fee_accounting_row($q,$op)!==$old){throw new RuntimeException('DONATION_FEE_CONFLICT');}sharity_donation_fee_accounting_verify($q,$old);};
            return[sharity_donation_fee_account_result($old),$read];}
        $before=json_decode($current['document'],true,32,JSON_THROW_ON_ERROR);$f=$proof['frame'];
        if(sharity_donation_backing_frame_key($f)!==$key||$f['previous_revision']!==(int)$current['revision']||$f['previous_digest']!==$current['digest']
            ||$f['external_liability_minor']!==$before['frame']['external_liability_minor']){throw new RuntimeException('DONATION_FEE_STALE');}
        $sourceKeys=static fn($p)=>array_map(static fn($s)=>sharity_donation_backing_source_key($s,$p['frame']),$p['sources']);
        if($sourceKeys($proof)!==$sourceKeys($before)){throw new RuntimeException('DONATION_FEE_SOURCE');}
        // All existing source, identity, cash-proof and order restrictions still apply.
        [$cashResult,$cashRead]=sharity_donation_backing_import_locked($q,$proof,$cashJson,$cashDigest,$apply,$f,$key,$now,true);
        [$receipt,$storedReceipt,$matched,$movementReads,$movements,$evidenceDigest]=sharity_donation_fee_match_locked($q,$od,$connected,$now);
        $t=sharity_donation_fee_table();$q("SELECT operation_id FROM `{$t}` WHERE operation_id = ? FOR UPDATE",[$op]);
        $row=sharity_donation_fee_row($q,$op);$claim=sharity_donation_backing_row($q,'claims','WHERE claim_key = ? FOR UPDATE',[$od['claim_key']]);
        $claimJson=sharity_donation_finance_json(sharity_donation_backing_claim_proof($od['claim']));
        if($row===null||$row['state']!=='confirmed'||$row['digest']!==$digest||$row['document']!==sharity_donation_finance_json($od)
            ||$claim===null||$claim['state']!=='confirmed'||$claim['document']!==$claimJson||$claim['digest']!==hash('sha256',$claimJson)
            ||$claim['order_id']!==$od['order_id']||$claim['kind']!=='fee_refund'||$claim['frame_key']!==$key){throw new RuntimeException('DONATION_FEE_CONFLICT');}
        $cashAfter=$receipt['created'];
        foreach($movements as$m){$meta=null;foreach($m['observations']as$ob){if($ob['evidence_digest']===$evidenceDigest){$meta=$ob;}}if(!is_array($meta)||$meta['status']!=='available'){throw new RuntimeException('DONATION_FEE_SOURCE');}
            $cashAfter=max($cashAfter,$meta['created'],$meta['available_on']);}
        $spent=-(int)$movements['application_fee_refund']['facts']['net_minor'];$residual=(int)$od['claim']['amount_minor']-$spent;$ss=0;$sources=[];
        $cashSources=[];foreach($proof['sources']as$s){$cashSources[sharity_donation_backing_source_key($s,$f)]=$s;}
        foreach($od['claim']['allocations']as$a){$part=$alloc[$a['source_key']]??null;
            if($part===null||(int)$part['spent_minor']>(int)$a['amount_minor']){throw new RuntimeException('DONATION_FEE_SOURCE');}
            $s=$cashSources[$a['source_key']]??null;
            if($s===null||((int)$part['spent_minor']>0&&(sharity_donation_backing_time($s['as_of_utc'])<$cashAfter||sharity_donation_backing_time($s['expires_at_utc'])<=$now))){throw new RuntimeException('DONATION_FEE_SOURCE');}
            $ss=sharity_donation_backing_sum($ss,(int)$part['spent_minor']);$sources[]=$part+['residual_minor'=>(string)((int)$a['amount_minor']-(int)$part['spent_minor'])];unset($alloc[$a['source_key']]);}
        if($alloc!==[]||$spent!==$ss||$residual<0||sharity_donation_backing_time($f['as_of_utc'])<$cashAfter){throw new RuntimeException('DONATION_FEE_SOURCE');}
        $history=sharity_donation_fee_accounting_history($q,$key);
        if(count($history)>=1024){throw new RuntimeException('DONATION_BACKING_LIMIT');}
        $movements=array_map(static fn($m)=>['facts'=>$m['facts'],'financial_digest'=>$m['financial_digest']],$movements);ksort($movements,SORT_STRING);
        $d=['version'=>1,'scope'=>'fee_correction_accounting','operation_id'=>$op,'operation_digest'=>$digest,'input_digest'=>$inputDigest,
            'receipt_digest'=>$storedReceipt['digest'],'evidence_digest'=>$evidenceDigest,'frame_key'=>$key,'original_frame'=>['revision'=>$od['claim']['frame_revision'],'digest'=>$od['claim']['frame_digest']],
            'previous_frame'=>['revision'=>(int)$current['revision'],'digest'=>$current['digest']],
            'cash_frame'=>['revision'=>$f['previous_revision']+1,'digest'=>$cashDigest],'currency'=>$od['currency'],'spent_minor'=>(string)$spent,'residual_minor'=>(string)$residual,'sources'=>$sources,'movements'=>$movements];
        $json=sharity_donation_finance_json($d);$proposal=hash('sha256',$json);
        if(strlen($json)>65536){throw new RuntimeException('DONATION_BACKING_LIMIT');}
        if($apply&&!hash_equals($applyDigest,$proposal)){throw new RuntimeException('DONATION_FEE_STALE');}
        sharity_donation_fee_cash_caps($q,$proof,array_values($history+[$op=>$d]));
        // Preview skips only the exact confirmed claim just proved above, keeping its residual.
        $capacity=sharity_donation_backing_capacity($q,$proof,[$od['claim_key']],[$op=>$d]);
        $account=['operation_id'=>$op,'frame_key'=>$key,'frame_revision'=>(string)($f['previous_revision']+1),'residual_minor'=>(string)$residual,
            'digest'=>$proposal,'document'=>$json,'created_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
        if($apply){$at=sharity_donation_fee_accounting_table();$q("INSERT INTO `{$at}` (operation_id,frame_key,frame_revision,residual_minor,digest,document,created_at_utc) VALUES (?,?,?,?,?,?,?)",array_values($account));
            $ct=sharity_donation_backing_table('claims');
            foreach([[$ct,'claim_key',$od['claim_key']],[$t,'operation_id',$op]]as[$table,$field,$id]){
                $q("UPDATE `{$table}` SET state = 'accounted' WHERE `{$field}` = ? AND state = 'confirmed'",[$id]);
                if((int)($q('SELECT ROW_COUNT() AS n')[0]['n']??0)!==1){throw new RuntimeException('DONATION_FEE_CONFLICT');}}
        }
        // A successor may already occupy the claim; verify it without requiring the old claim bytes.
        $read=static function($q)use($op,$account,$cashRead):void{$cashRead($q);
            if(sharity_donation_finance_json(sharity_donation_fee_accounting_row($q,$op))!==sharity_donation_finance_json($account)){throw new RuntimeException('DONATION_FEE_CONFLICT');}
            sharity_donation_fee_accounting_verify($q,$account);
        };
        if($apply){$read($q);}
        return[sharity_donation_fee_account_result($account,$apply),$read];
    });
}
