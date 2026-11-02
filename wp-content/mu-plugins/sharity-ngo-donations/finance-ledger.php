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
    $types = ['refund' => ['refund', 'payment_refund'], 'refund_reversal' => ['refund_failure', 'payment_failure_refund', 'payment_refund', 'adjustment'],
        'dispute' => ['adjustment'], 'application_fee_refund' => ['application_fee_refund']];
    if (isset($types[$role])) {
        if (($b['source_id'] ?? null) !== $source || ($b['scope'] ?? null) !== ($account === null ? 'platform' : 'connected')
            || !in_array($facts['provider_type'], $types[$role], true)) { throw new RuntimeException(); }
    } elseif ($facts['provider_type'] !== null || isset($b['source_id']) || isset($b['scope'])) { throw new RuntimeException(); }
    foreach (['gross_minor', 'total_fee_minor', 'net_minor', 'application_fee_minor', 'processing_cost_minor'] as $key) {
        sharity_donation_finance_minor($b[$key] ?? null); $facts[$key] = $b[$key];
    }
    if ((int) $facts['net_minor'] !== (int) $facts['gross_minor'] - (int) $facts['total_fee_minor']
        || (int) $facts['processing_cost_minor'] !== (int) $facts['total_fee_minor'] - (int) $facts['application_fee_minor']
        || !is_array($b['fee_details'] ?? null) || !array_is_list($b['fee_details'])) { throw new RuntimeException(); }
    $gross = (int) $facts['gross_minor'];
    if ((in_array($role, ['charge', 'application_fee', 'refund_reversal'], true) && $gross <= 0)
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
        $movements = []; $digests = []; $unresolved = ['cross_order_movement_registry_not_applied', 'backing_not_reconciled']; $paymentBindings = [];
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
                $unresolved[] = 'platform_account_unverified';
                $add($fee['initial_balance'], $feeId, 'application_fee', null);
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
                if ($feeId === null || ($refund['fee_id'] ?? null) !== $feeId || ($refund['connected_credit_verified'] ?? null) !== false || !array_key_exists('balance', $refund)) { throw new RuntimeException(); }
                if ($refund['balance'] !== null) { $add($refund['balance'], $source, 'application_fee_refund', null); }
                $unresolved[] = 'observed_fee_refund_connected_credit_unreconciled';
            }
        }
        if ($index === []) { $unresolved[] = 'observations_missing'; }
        ksort($movements, SORT_STRING); ksort($digests, SORT_STRING); $known = []; $unscoped = []; $financial = [];
        foreach ($movements as $movement) {
            ksort($movement['observations'], SORT_STRING); $movement['observations'] = array_values($movement['observations']);
            $financial[] = $movement['financial_digest'];
            if ($movement['facts']['account_id'] === null) { $unscoped[] = $movement; } else { $known[] = $movement; }
        }
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
