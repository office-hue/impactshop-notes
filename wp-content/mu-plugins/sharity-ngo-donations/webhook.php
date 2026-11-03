<?php
/** Signed Connect Checkout inbox. Receipt/recovery is NOT financial settlement.
 * Only minimal notification identity is retained, never raw payload/customer data.
 * Historical receipt/recovery is independent of switches for new checkouts.
 */
declare(strict_types=1);
const SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION = 'sharity_donation_webhooks_schema_v1';
const SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION = 'sharity_donation_webhook_finance_schema_v1';
const SHARITY_DONATION_DRAIN_CURSOR_OPTION = 'sharity_donation_finance_drain_cursor_v1';
const SHARITY_DONATION_POLL_CURSOR_OPTION = 'sharity_donation_finance_poll_cursor_v1';
function sharity_donation_webhook_table(): string { global $wpdb; return $wpdb->prefix . 'sharity_donation_webhooks_v1'; }

/** Internal authority: call only after provider signature verification or from CLI.
 * This is deliberately separate from all owner-bound browser order reads. */
function sharity_donation_webhook_order(string $id): array
{
    if (!preg_match('/^do_[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    global $wpdb; $table = sharity_donation_order_table();
    try {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE order_id = %s LIMIT 1", $id), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($row)) { throw new RuntimeException(); }
        sharity_donation_order_owner($row['subject_ref']);
        sharity_donation_order_decode($row);
        return $row;
    } catch (Throwable $error) { throw new RuntimeException('WEBHOOK_ORDER_UNAVAILABLE'); }
}

function sharity_donation_webhook_read(string $id): ?array
{
    if (strlen($id) > 255 || !preg_match('/^evt_[A-Za-z0-9]+$/D', $id)) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    global $wpdb; $table = sharity_donation_webhook_table();
    try {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE event_id = %s LIMIT 1", $id), ARRAY_A);
        if ($wpdb->last_error !== '') { throw new RuntimeException(); }
        return is_array($row) ? $row : null;
    } catch (Throwable $error) { throw new RuntimeException('WEBHOOK_READ_UNAVAILABLE'); }
}

/** The SDK verifies exactly the raw request body; no API key/provider call. */
function sharity_donation_webhook_verify(string $raw, string $header): array
{
    if ($raw === '' || $header === '' || strlen($raw) > 262144 || strlen($header) > 8192) {
        throw new RuntimeException('WEBHOOK_INVALID_REQUEST');
    }
    if (!defined('SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET') || !is_string(SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET)
        || !preg_match('/^whsec_[A-Za-z0-9]+$/D', SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET)
        || !defined('SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE') || !is_bool(SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE)) {
        throw new RuntimeException('WEBHOOK_CONFIG_UNAVAILABLE');
    }
    sharity_donation_runtime_sdk();
    try {
        $data = Stripe\Webhook::constructEvent($raw, $header, SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET, 300)->toArray();
        if (($data['object'] ?? null) !== 'event' || !is_string($data['id'] ?? null) || strlen($data['id']) > 255
            || !preg_match('/^evt_[A-Za-z0-9]+$/D', $data['id']) || !is_string($data['type'] ?? null)) { throw new RuntimeException(); }
        return $data;
    } catch (Throwable $error) { throw new RuntimeException('WEBHOOK_INVALID_REQUEST'); }
}

/** Explicit connected-order event set; platform/account/payout notifications are separate. */
function sharity_donation_lifecycle_type(string $type): ?string
{
    if (in_array($type, ['charge.succeeded','charge.updated','charge.captured','charge.refunded','charge.failed','charge.expired'], true)) { return 'charge'; }
    if (in_array($type, ['refund.created','refund.updated','refund.failed','charge.refund.updated'], true)) { return 'refund'; }
    if (in_array($type, ['charge.dispute.created','charge.dispute.updated','charge.dispute.closed',
        'charge.dispute.funds_withdrawn','charge.dispute.funds_reinstated'], true)) { return 'dispute'; }
    return null;
}

function sharity_donation_lifecycle_id($id, string $prefix): bool
{
    return is_string($id) && strlen($id) <= 255 && preg_match('/^' . $prefix . '_[A-Za-z0-9]+$/D', $id) === 1;
}

/** Signed notification only; metadata proposes an order, the drain proves the charge. */
function sharity_donation_lifecycle_notification(array $event, string $kind, ?object $transport): ?array
{
    $r = $event['data']['object'] ?? null;
    if (!is_bool($event['livemode'] ?? null) || $event['livemode'] !== SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE
        || ($event['api_version'] ?? null) !== Sharity_Stripe_Checkout_Adapter::API_VERSION
        || !is_int($event['created'] ?? null) || $event['created'] < 1 || $event['created'] > time() + 300
        || !is_string($event['account'] ?? null) || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $event['account'])
        || !is_array($r) || ($r['object'] ?? null) !== $kind
        || !sharity_donation_lifecycle_id($r['id'] ?? null, ['charge'=>'ch','refund'=>'re','dispute'=>'(dp|du)'][$kind])
        || (array_key_exists('livemode', $r) && $r['livemode'] !== $event['livemode'])) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    $charge = $r;
    if ($kind !== 'charge') {
        // Expanded nested objects are not trusted for ownership. Re-read the exact ID.
        $ref = $r['charge'] ?? null; $id = is_array($ref) ? ($ref['id'] ?? null) : $ref;
        if (!sharity_donation_lifecycle_id($id, 'ch')) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
        sharity_donation_order_autocommit();
        $charge = sharity_donation_webhook_bounded_read(static fn() => sharity_donation_runtime_adapter()
            ->notificationCharge($id, $event['account'], $event['livemode']), hrtime(true) / 1e9 + 5, $transport, null, 1);
    }
    $id = $charge['metadata']['order_id'] ?? null;
    if (!(is_string($id) && str_starts_with($id, 'do_'))) { return null; }
    if (!preg_match('/^do_[a-f0-9]{32}$/D', $id)) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    $order = sharity_donation_webhook_order($id); $e = sharity_donation_order_decode($order); $snapshot = $e['snapshot'];
    if ($order['state'] !== 'session_recorded' || !is_string($order['provider_session_id'])) {
        throw new RuntimeException('WEBHOOK_RECOVERY_UNCONFIRMED');
    }
    sharity_donation_order_status($order['subject_ref'], $id, time());
    if ($event['account'] !== $snapshot['account_id'] || $event['livemode'] !== $snapshot['livemode']
        || ($charge['livemode'] ?? null) !== $snapshot['livemode']
        || ($charge['amount'] ?? null) !== (int)$snapshot['amount_minor']
        || ($charge['currency'] ?? null) !== strtolower($snapshot['currency'])
        || !sharity_donation_lifecycle_id($charge['payment_intent'] ?? null, 'pi')) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    foreach ($e['payload']['metadata'] as $key => $value) {
        if (($charge['metadata'][$key] ?? null) !== $value) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    }
    if ($kind !== 'charge' && array_key_exists('payment_intent', $r) && $r['payment_intent'] !== null) {
        $pi = is_array($r['payment_intent']) ? ($r['payment_intent']['id'] ?? null) : $r['payment_intent'];
        if ($pi !== $charge['payment_intent']) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    }
    // Preserve only the minimum signed progress facts, never raw financial/customer data.
    $status = $kind === 'charge' ? null : ($r['status'] ?? null);
    $allowed = $kind === 'refund' ? ['pending','requires_action','succeeded','failed','canceled']
        : ['warning_needs_response','warning_under_review','warning_closed','needs_response','under_review','won','lost',
            'appeal_needs_response','appeal_under_review','prevented'];
    if ($kind !== 'charge' && ($status !== null || $kind === 'dispute') && !in_array($status,$allowed,true)) {
        throw new RuntimeException('WEBHOOK_CONTEXT_INVALID');
    }
    $refs = $kind === 'refund' ? [$r['balance_transaction'] ?? null,$r['failure_balance_transaction'] ?? null]
        : ($kind === 'dispute' ? ($r['balance_transactions'] ?? null) : []);
    if (!is_array($refs) || !array_is_list($refs) || count($refs)>2) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    $balances=[];
    foreach ($refs as $ref) {
        if ($ref === null) { continue; }
        $bid = is_array($ref) ? ($ref['id'] ?? null) : $ref;
        if (!sharity_donation_lifecycle_id($bid,'txn') || in_array($bid,$balances,true)) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
        $balances[]=$bid;
    }
    sort($balances,SORT_STRING);
    $refundFloor = $kind === 'charge' ? ($r['amount_refunded'] ?? null) : null;
    if ($kind === 'charge' && (!is_int($refundFloor) || $refundFloor<0 || $refundFloor>$snapshot['amount_minor'])) {
        throw new RuntimeException('WEBHOOK_CONTEXT_INVALID');
    }
    return ['event_id'=>$event['id'],'event_type'=>$event['type'],'event_created'=>$event['created'],
        'api_version'=>$event['api_version'],'order_id'=>$id,'quote_id'=>$e['quote_id'],
        'session_id'=>$order['provider_session_id'],'account_id'=>$snapshot['account_id'],'livemode'=>$snapshot['livemode'],
        'resource_type'=>$kind,'resource_id'=>$r['id'],'charge_id'=>$charge['id'],'payment_intent_id'=>$charge['payment_intent'],
        'resource_status'=>$status,'balance_ids'=>$balances,'refund_floor_minor'=>$refundFloor===null?null:(string)$refundFloor];
}

/** Conservative progress only: incomparable/reopened states need another read or reconciliation. */
function sharity_donation_lifecycle_status_reached(string $kind, $before, array $current): bool
{
    $after=$current['status'] ?? null;
    if (!is_string($after)) { return false; }
    if ($before === $after) { return true; }
    if ($kind === 'refund') {
        if ($before === null || in_array($before,['pending','requires_action'],true)) {
            return in_array($after,['pending','requires_action','succeeded','failed','canceled'],true);
        }
        return $before === 'succeeded' && $after === 'failed' && is_array($current['failure_balance'] ?? null);
    }
    $later = [
        'warning_needs_response'=>['warning_under_review','warning_closed','needs_response','under_review','won','lost','prevented'],
        'warning_under_review'=>['warning_closed','needs_response','under_review','won','lost','prevented'],
        'needs_response'=>['under_review','won','lost','prevented','appeal_needs_response','appeal_under_review'],
        'under_review'=>['won','lost','prevented','appeal_needs_response','appeal_under_review'],
        'appeal_needs_response'=>['appeal_under_review','won','lost'], 'appeal_under_review'=>['won','lost'],
    ];
    return is_string($before) && in_array($after,$later[$before] ?? [],true);
}

/** A notice cannot complete or project money merely because metadata names an order. */
function sharity_donation_lifecycle_matches(array $row, array $evidence): bool
{
    $identity = json_decode($row['identity_json'], true, 16, JSON_THROW_ON_ERROR);
    $kind = sharity_donation_lifecycle_type($identity['event_type'] ?? '');
    if ($kind === null) { return str_starts_with($identity['event_type'] ?? '', 'checkout.session.'); }
    if (($identity['resource_type'] ?? null) !== $kind
        || ($identity['charge_id'] ?? null) !== ($evidence['original']['charge_id'] ?? null)
        || ($identity['payment_intent_id'] ?? null) !== ($evidence['original']['payment_intent_id'] ?? null)) { return false; }
    $resource = $identity['resource_id'] ?? null;
    if ($kind === 'charge') {
        $floor=$identity['refund_floor_minor'] ?? null;
        $actual=$evidence['original']['charge_refund_state']['amount_refunded_minor'] ?? null;
        return $resource === $identity['charge_id'] && is_string($floor) && is_string($actual)
            && preg_match('/^(0|[1-9][0-9]{0,14})$/D',$floor) && preg_match('/^(0|[1-9][0-9]{0,14})$/D',$actual)
            && (int)$actual >= (int)$floor;
    }
    if (!is_string($resource) || !array_key_exists('resource_status',$identity)
        || !is_array($identity['balance_ids'] ?? null)) { return false; }
    foreach ($evidence[$kind === 'refund' ? 'refunds' : 'disputes'] ?? [] as $r) {
        if (($r['id'] ?? null) !== $resource || !sharity_donation_lifecycle_status_reached($kind,$identity['resource_status'],$r)) { continue; }
        $current = $kind === 'refund' ? [$r['balance'] ?? null,$r['failure_balance'] ?? null] : ($r['balances'] ?? []);
        $ids=[];foreach($current as $b){if(is_array($b)&&is_string($b['id']??null)){$ids[]=$b['id'];}}
        return array_diff($identity['balance_ids'],$ids) === [];
    }
    return false;
}

/** null means a verified event outside this narrowly configured receiver. */
function sharity_donation_webhook_notification(array $event, ?object $transport = null): ?array
{
    $kind = sharity_donation_lifecycle_type($event['type']);
    if ($kind !== null) { return sharity_donation_lifecycle_notification($event, $kind, $transport); }
    if (!in_array($event['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed', 'checkout.session.expired'], true)) { return null; }
    $s = $event['data']['object'] ?? null;
    if (!is_array($s)) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    $id = $s['metadata']['order_id'] ?? null; $ref = $s['client_reference_id'] ?? null;
    // Connected accounts can also sell unrelated products; ignore only those.
    if (!(is_string($id) && str_starts_with($id, 'do_')) && !(is_string($ref) && str_starts_with($ref, 'do_'))) { return null; }
    if (!is_string($id) || $id !== $ref || !is_bool($event['livemode'] ?? null)
        || $event['livemode'] !== SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE
        || ($event['api_version'] ?? null) !== Sharity_Stripe_Checkout_Adapter::API_VERSION
        || !is_int($event['created'] ?? null) || $event['created'] < 1 || $event['created'] > time() + 300) {
        throw new RuntimeException('WEBHOOK_CONTEXT_INVALID');
    }
    $row = sharity_donation_webhook_order($id); $e = sharity_donation_order_decode($row); $snapshot = $e['snapshot'];
    if (!in_array($row['state'], ['attempted', 'session_recorded'], true)
        || ($event['account'] ?? null) !== $snapshot['account_id'] || $event['livemode'] !== $snapshot['livemode']
        || ($s['object'] ?? null) !== 'checkout.session' || ($s['livemode'] ?? null) !== $snapshot['livemode']
        || ($s['mode'] ?? null) !== 'payment' || !is_string($s['id'] ?? null) || strlen($s['id']) > 255
        || !preg_match($snapshot['livemode'] ? '/^cs_live_[A-Za-z0-9]+$/D' : '/^cs_test_[A-Za-z0-9]+$/D', $s['id'])
        || ($row['provider_session_id'] !== null && $row['provider_session_id'] !== $s['id'])
        || ($s['amount_total'] ?? null) !== (int) $snapshot['amount_minor']
        || ($s['currency'] ?? null) !== strtolower($snapshot['currency']) || ($s['expires_at'] ?? null) !== $e['expires_at']
        || ($s['status'] ?? null) !== ($event['type'] === 'checkout.session.expired' ? 'expired' : 'complete')
        || !in_array($s['payment_status'] ?? null, ['paid', 'unpaid', 'no_payment_required'], true)) {
        throw new RuntimeException('WEBHOOK_CONTEXT_INVALID');
    }
    foreach ($e['payload']['metadata'] as $key => $value) {
        if (($s['metadata'][$key] ?? null) !== $value) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    }
    return ['event_id' => $event['id'], 'event_type' => $event['type'], 'event_created' => $event['created'],
        'api_version' => $event['api_version'], 'order_id' => $id, 'quote_id' => $e['quote_id'],
        'session_id' => $s['id'], 'account_id' => $snapshot['account_id'], 'livemode' => $snapshot['livemode']];
}

/** INSERT + readback on both acknowledged and uncertain writes. No upsert. */
function sharity_donation_webhook_store(array $note, int $now): void
{
    if (get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('WEBHOOK_SCHEMA_UNAVAILABLE'); }
    sharity_donation_order_autocommit();
    $json = json_encode($note, JSON_THROW_ON_ERROR); $digest = hash('sha256', $json);
    global $wpdb; $table = sharity_donation_webhook_table();
    try {
        $wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (event_id, order_id, session_id, identity_json, identity_digest, received_at_utc)
            VALUES (%s, %s, %s, %s, %s, %s)", $note['event_id'], $note['order_id'], $note['session_id'], $json, $digest, gmdate('Y-m-d H:i:s', $now)));
    } catch (Throwable $error) { /* Lost ACK may still have committed. */ }
    $row = sharity_donation_webhook_read($note['event_id']);
    if ($row === null) { throw new RuntimeException('WEBHOOK_STORE_UNCONFIRMED'); }
    if ($row['identity_json'] !== $json || $row['identity_digest'] !== $digest
        || $row['order_id'] !== $note['order_id'] || $row['session_id'] !== $note['session_id']) { throw new RuntimeException('WEBHOOK_EVENT_CONFLICT'); }
}

function sharity_donation_webhook_handle(WP_REST_Request $request, ?object $transport = null): WP_REST_Response
{
    try {
        $raw = $request->get_body(); $header = $request->get_header('stripe-signature');
        if (!is_string($raw) || !is_string($header)) { throw new RuntimeException('WEBHOOK_INVALID_REQUEST'); }
        $event = sharity_donation_webhook_verify($raw, $header);
        if (get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('WEBHOOK_SCHEMA_UNAVAILABLE'); }
        $note = sharity_donation_webhook_notification($event, $transport);
        if ($note !== null) { sharity_donation_webhook_store($note, time()); }
        return sharity_donation_quote_private(['ok' => true, 'received' => true, 'ignored' => $note === null]);
    } catch (Throwable $error) {
        $code = $error->getMessage();
        if (in_array($code, ['WEBHOOK_INVALID_REQUEST', 'WEBHOOK_CONTEXT_INVALID'], true)) { return sharity_donation_quote_error('WEBHOOK_INVALID_REQUEST', 400); }
        if ($code === 'WEBHOOK_EVENT_CONFLICT') { return sharity_donation_quote_error('WEBHOOK_EVENT_CONFLICT', 409); }
        return sharity_donation_quote_error('WEBHOOK_UNAVAILABLE', 503);
    }
}

/** Recover only the missing session reference. GET, never retry Checkout create.
 * Single-event CLI only. No scheduler/financial processor is implied. */
function sharity_donation_webhook_recover(string $id, int $now): void
{
    if (get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('WEBHOOK_SCHEMA_UNAVAILABLE'); }
    $note = sharity_donation_webhook_read($id);
    if ($note === null) { throw new RuntimeException('WEBHOOK_NOT_FOUND'); }
    try { $identity = json_decode($note['identity_json'], true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { throw new RuntimeException('WEBHOOK_DATA_UNAVAILABLE'); }
    if (!is_array($identity) || !hash_equals($note['identity_digest'], hash('sha256', $note['identity_json']))
        || ($identity['event_id'] ?? null) !== $id || ($identity['order_id'] ?? null) !== $note['order_id']
        || ($identity['session_id'] ?? null) !== $note['session_id']) { throw new RuntimeException('WEBHOOK_DATA_UNAVAILABLE'); }
    $row = sharity_donation_webhook_order($note['order_id']); $e = sharity_donation_order_decode($row);
    if (($identity['quote_id'] ?? null) !== $e['quote_id'] || ($identity['account_id'] ?? null) !== $e['snapshot']['account_id']
        || ($identity['livemode'] ?? null) !== $e['snapshot']['livemode']
        || ($identity['api_version'] ?? null) !== Sharity_Stripe_Checkout_Adapter::API_VERSION) { throw new RuntimeException('WEBHOOK_CONTEXT_INVALID'); }
    $subject = $row['subject_ref'];
    if ($row['state'] === 'attempted') {
        sharity_donation_order_autocommit();
        $result = sharity_donation_runtime_adapter()->retrieveSession($note['session_id'], $e);
        try { sharity_donation_order_save_result($subject, $row['order_id'], $result, $now); }
        catch (Throwable $error) { /* Concurrent GETs may see different session status; verify stored identity below. */ }
    }
    // Includes concurrent dispatch/recovery. Validate the saved result, not just state.
    $saved = sharity_donation_order_read($subject, 'order_id', $row['order_id']);
    if ($saved === null || $saved['state'] !== 'session_recorded' || $saved['provider_session_id'] !== $note['session_id']) {
        throw new RuntimeException('WEBHOOK_RECOVERY_UNCONFIRMED');
    }
    sharity_donation_order_status($subject, $row['order_id'], $now);
    if ($note['recovered_at_utc'] !== null) { return; }
    sharity_donation_order_autocommit();
    global $wpdb; $table = sharity_donation_webhook_table();
    try {
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET recovered_at_utc = %s WHERE event_id = %s AND identity_digest = %s AND recovered_at_utc IS NULL",
            gmdate('Y-m-d H:i:s', $now), $id, $note['identity_digest']));
    } catch (Throwable $error) { /* Read back before claiming recovery. */ }
    $confirmed = sharity_donation_webhook_read($id);
    if ($confirmed === null || $confirmed['identity_json'] !== $note['identity_json'] || $confirmed['recovered_at_utc'] === null) {
        throw new RuntimeException('WEBHOOK_RECOVERY_UNCONFIRMED');
    }
}

function sharity_donation_webhook_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') !== '0') { return false; }
        global $wpdb; $table = sharity_donation_webhook_table();
        if ($wpdb->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            event_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            order_id char(35) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            session_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            identity_json longtext NOT NULL,
            identity_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            received_at_utc datetime NOT NULL,
            recovered_at_utc datetime NULL,
            PRIMARY KEY (event_id), KEY order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin") === false) { return false; }
        if (sharity_donation_webhook_schema_shape() === 0) { return false; }
        update_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

/** The existing inbox has two explicit schema shapes. Never repair an unknown one. */
function sharity_donation_webhook_schema_shape(): int
{
    global $wpdb; $table = sharity_donation_webhook_table();
    $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
    $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
    $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB' || !is_array($columns) || !is_array($indexes)) { return 0; }
    $expected = ['event_id'=>['varchar(255)','ascii_bin'], 'order_id'=>['char(35)','ascii_bin'],
        'session_id'=>['varchar(255)','ascii_bin'], 'identity_json'=>['longtext','utf8mb4_bin'],
        'identity_digest'=>['char(64)','ascii_bin'], 'received_at_utc'=>['datetime',null], 'recovered_at_utc'=>['datetime',null]];
    $version = count($columns) === 10 ? 2 : 1;
    if ($version === 2) { $expected += ['observation_digest'=>['char(64)','ascii_bin'], 'observed_at_utc'=>['datetime',null], 'last_attempt_at_utc'=>['datetime',null]]; }
    if (count($columns) !== count($expected)) { return 0; }
    foreach ($columns as $c) {
        $nullable = in_array($c['Field'], ['recovered_at_utc','observation_digest','observed_at_utc','last_attempt_at_utc'], true);
        if (!isset($expected[$c['Field']]) || [$c['Type'],$c['Collation']] !== $expected[$c['Field']]
            || $c['Null'] !== ($nullable ? 'YES' : 'NO') || $c['Default'] !== null || $c['Extra'] !== '') { return 0; }
    }
    $actual = [];
    foreach ($indexes as $i) {
        if ($i['Sub_part'] !== null || $i['Index_type'] !== 'BTREE') { return 0; }
        $actual[$i['Key_name']][] = [$i['Column_name'],(int)$i['Non_unique'],(int)$i['Seq_in_index']];
    }
    ksort($actual);
    return $actual === ['PRIMARY'=>[['event_id',0,1]],'order_id'=>[['order_id',1,1]]] ? $version : 0;
}

function sharity_donation_webhook_finance_schema_apply(): bool
{
    try {
        sharity_donation_order_autocommit();
        update_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION, '0', false);
        if (get_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION, '') !== '0'
            || !sharity_donation_webhook_schema_apply()) { return false; }
        if (sharity_donation_webhook_schema_shape() === 1) {
            global $wpdb; $table = sharity_donation_webhook_table();
            try {
                $wpdb->query("ALTER TABLE `{$table}`
                    ADD COLUMN observation_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                    ADD COLUMN observed_at_utc datetime NULL,
                    ADD COLUMN last_attempt_at_utc datetime NULL");
            } catch (Throwable $uncertain) { /* DDL ACK may be lost; inspect the exact shape below. */ }
        }
        if (sharity_donation_webhook_schema_shape() !== 2) { return false; }
        update_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

/** One private CurlClient per bounded scope. No SDK class is required on file load.
 * The optional offline transport/clock are only callable-code test seams, not HTTP inputs. */
function sharity_donation_webhook_bounded_read(callable $operation, float $deadline, ?object $transport = null, ?callable $clock = null, int $cap = 63)
{
    $clock = $clock ?? static fn(): float => hrtime(true) / 1e9;
    sharity_donation_runtime_sdk();
    $prior = Stripe\ApiRequestor::httpClient();
    if ($cap < 1 || $cap > 63) { throw new RuntimeException('DONATION_DRAIN_READ_LIMIT'); }
    $inner = $transport ?? new Stripe\HttpClient\CurlClient();
    $bounded = new class($inner, $deadline, $clock, $cap) implements Stripe\HttpClient\ClientInterface {
        private int $gets = 0;
        public function __construct(private object $inner, private float $deadline, private $clock, private int $cap) {}
        public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
            $left = $this->deadline - ($this->clock)();
            if ($method !== 'get' || !is_string($url) || !str_starts_with($url, 'https://api.stripe.com/v1/')
                || $hasFile !== false || $apiMode !== 'v1' || $maxNetworkRetries !== 0 || $left < 1 || $this->gets >= $this->cap) {
                throw new RuntimeException('DONATION_DRAIN_READ_LIMIT');
            }
            $this->gets++;
            if ($this->inner instanceof Stripe\HttpClient\CurlClient) {
                $this->inner->setTimeout((int)min(5, floor($left)))->setConnectTimeout((int)min(2, floor($left)));
            }
            try { $reply = $this->inner->request($method, $url, $headers, $params, false, 'v1', 0); }
            catch (Throwable $error) { throw new RuntimeException('DONATION_DRAIN_READ_UNAVAILABLE'); }
            if (($this->clock)() >= $this->deadline) { throw new RuntimeException('DONATION_DRAIN_READ_LIMIT'); }
            return $reply;
        }
    };
    try { Stripe\ApiRequestor::setHttpClient($bounded); return $operation(); }
    finally { Stripe\ApiRequestor::setHttpClient($prior); }
}

/** Opaque round-robin cursor in the existing options table, independent of WP caches.
 * CAS only coordinates scanning, never authorizes a financial write. */
function sharity_donation_webhook_cursor(?string $next = null, ?string $expected = null, string $option = SHARITY_DONATION_DRAIN_CURSOR_OPTION): ?string
{
    if (!in_array($option, [SHARITY_DONATION_DRAIN_CURSOR_OPTION, SHARITY_DONATION_POLL_CURSOR_OPTION], true)) { throw new RuntimeException('DONATION_DRAIN_CURSOR_UNAVAILABLE'); }
    global $wpdb; $table = $wpdb->options;
    if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException('DONATION_DRAIN_CURSOR_UNAVAILABLE'); }
    sharity_donation_order_autocommit();
    $read = static function () use ($wpdb,$table,$option): ?string {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT option_value FROM `{$table}` WHERE option_name = %s LIMIT 2", $option), ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows) || count($rows) > 1) { throw new RuntimeException('DONATION_DRAIN_CURSOR_UNAVAILABLE'); }
        if (!$rows) { return null; }
        $v = $rows[0]['option_value'];
        if (!is_string($v) || ($v !== '' && !preg_match('/^do_[a-f0-9]{32}$/D', $v))) { throw new RuntimeException('DONATION_DRAIN_CURSOR_UNAVAILABLE'); }
        return $v;
    };
    if ($next === null) { return $read(); }
    if ($next !== '' && !preg_match('/^do_[a-f0-9]{32}$/D', $next)) { throw new RuntimeException('DONATION_DRAIN_CURSOR_UNAVAILABLE'); }
    try {
        if ($expected === null) {
            $wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (option_name,option_value,autoload) VALUES (%s,%s,'no')", $option, $next));
        } else {
            $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", $next, $option, $expected));
        }
    } catch (Throwable $uncertain) { /* Confirm exact value below, never blindly retry. */ }
    if ($read() !== $next) { throw new RuntimeException('DONATION_DRAIN_CURSOR_CONFLICT'); }
    return $next;
}

/** Failed events remain pending but cannot forever displace later events of the same order. */
function sharity_donation_webhook_observation_attempt(array $row, int $now): void
{
    sharity_donation_order_autocommit();
    global $wpdb; $table = sharity_donation_webhook_table(); $at = gmdate('Y-m-d H:i:s',$now);
    try {
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET last_attempt_at_utc = GREATEST(COALESCE(last_attempt_at_utc,%s),%s)
            WHERE event_id = %s AND identity_digest = %s",$at,$at,$row['event_id'],$row['identity_digest']));
    } catch (Throwable $uncertain) { /* Read progress before doing external work. */ }
    $saved = sharity_donation_webhook_read($row['event_id']);
    $time = $saved['last_attempt_at_utc'] ?? null;
    $date = is_string($time) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$time,new DateTimeZone('UTC')) : false;
    if ($saved === null || $saved['identity_json'] !== $row['identity_json'] || $saved['identity_digest'] !== $row['identity_digest']
        || $date === false || $date->format('Y-m-d H:i:s') !== $time || strcmp($time,$at) < 0) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
}

/** Complete exactly the captured event, never all events for an order. */
function sharity_donation_webhook_observation_done(array $before, array $receipt, int $now): void
{
    global $wpdb; $table = sharity_donation_webhook_table();
    sharity_donation_order_autocommit();
    if ($receipt['order_id'] !== $before['order_id']) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    try {
        $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET observation_digest = %s, observed_at_utc = %s
            WHERE event_id = %s AND identity_digest = %s AND observation_digest IS NULL AND observed_at_utc IS NULL",
            $receipt['digest'], gmdate('Y-m-d H:i:s',$now), $before['event_id'], $before['identity_digest']));
    } catch (Throwable $uncertain) { /* Full identity + saved observation readback decides. */ }
    $after = sharity_donation_webhook_read($before['event_id']);
    if ($after === null) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    foreach (['event_id','order_id','session_id','identity_json','identity_digest','received_at_utc'] as $k) {
        if ($after[$k] !== $before[$k]) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    }
    if (!is_string($after['observation_digest']) || !preg_match('/^[a-f0-9]{64}$/D',$after['observation_digest'])
        || !is_string($after['observed_at_utc'])) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    $at = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$after['observed_at_utc'],new DateTimeZone('UTC'));
    if ($at === false || $at->format('Y-m-d H:i:s') !== $after['observed_at_utc']) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    // A concurrent successful processor may have recorded a different valid snapshot.
    $saved = sharity_donation_finance_read($before['order_id'],$after['observation_digest']);
    if ($saved === null) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    $o = sharity_donation_webhook_order($before['order_id']); $e = sharity_donation_order_decode($o)['snapshot'];
    sharity_donation_finance_receipt($saved, ['order_id'=>$before['order_id'],'provider_session_id'=>$before['session_id'],
        'account_id'=>$e['account_id'],'livemode'=>$e['livemode'] ? '1' : '0','donation_mode'=>$e['donation_mode'],'digest'=>$after['observation_digest']]);
}

/** Shared observation and projection stages. Callers decide evidence eligibility. */
function sharity_donation_webhook_finance_observe(string $orderId, int $now): array
{
    $receipt = sharity_donation_finance_record($orderId,sharity_donation_runtime_adapter(),$now);
    $saved = sharity_donation_finance_read($orderId,$receipt['digest']);
    if ($saved === null) { throw new RuntimeException(); }
    sharity_donation_finance_receipt($saved,$receipt);
    $evidence = json_decode($saved['canonical_json'],true,64,JSON_THROW_ON_ERROR);
    return [$receipt, $evidence];
}

function sharity_donation_webhook_finance_project(string $orderId, int $now): void
{
    $inventory = sharity_donation_finance_reconcile($orderId,$now);
    sharity_donation_finance_record_movements($orderId,$inventory['movement_proposal_digest'],$now);
    $target = sharity_donation_finance_target($orderId,$now);
    $applied = sharity_donation_finance_target($orderId,$now,$target['proposal_digest']);
    if (($applied['persisted'] ?? false) !== true || $applied['proposal_digest'] !== $target['proposal_digest']) { throw new RuntimeException(); }
}

function sharity_donation_checkout_drain(int $now, ?object $transport = null, ?callable $clock = null): array
{
    $clock = $clock ?? static fn(): float => hrtime(true) / 1e9;
    $deadline = $clock() + 60;
    if ($now < 1 || $now > 253402300799 || get_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION,'') !== '1'
        || !function_exists('sharity_donation_finance_target')) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    sharity_donation_order_autocommit();
    global $wpdb; $table = sharity_donation_webhook_table();
    $cursor = sharity_donation_webhook_cursor();
    $orders = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT order_id FROM `{$table}` WHERE observation_digest IS NULL AND order_id > %s ORDER BY order_id LIMIT 5", $cursor ?? ''), ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($orders)) { throw new RuntimeException('DONATION_DRAIN_UNAVAILABLE'); }
    $result = ['orders'=>0,'observed'=>0,'pending'=>0,'retryable'=>0,'budget_exhausted'=>false,'financial_readiness'=>false];
    if (!$orders) { if ($cursor !== null && $cursor !== '') { sharity_donation_webhook_cursor('', $cursor); } return $result; }
    foreach ($orders as $candidate) {
        if ($clock() >= $deadline) { $result['budget_exhausted'] = true; break; }
        $orderId = $candidate['order_id']; $result['orders']++;
        try {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE order_id = %s AND observation_digest IS NULL ORDER BY last_attempt_at_utc, event_id LIMIT 5", $orderId), ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($rows)) { throw new RuntimeException(); }
            $valid = [];
            sharity_donation_webhook_bounded_read(static function () use ($rows,$orderId,$now,&$valid,&$result): void {
                foreach ($rows as $r) {
                    try {
                        sharity_donation_webhook_observation_attempt($r,$now);
                        sharity_donation_webhook_recover($r['event_id'],$now); $valid[] = $r;
                    }
                    catch (Throwable $e) { $result['retryable']++; }
                }
                if (!$valid) { return; }
                [$receipt, $evidence] = sharity_donation_webhook_finance_observe($orderId,$now);
                if (($evidence['original']['evidence_state'] ?? null) !== 'original_charge_observed') { $result['pending'] += count($valid); return; }
                $matched = [];
                foreach ($valid as $r) {
                    if (sharity_donation_lifecycle_matches($r, $evidence)) { $matched[] = $r; }
                    else { $result['pending']++; }
                }
                if (!$matched) { return; }
                $valid = $matched;
                sharity_donation_webhook_finance_project($orderId,$now);
                foreach ($valid as $r) {
                    sharity_donation_webhook_observation_done($r,$receipt,$now); $result['observed']++;
                }
            },$deadline,$transport,$clock);
        } catch (Throwable $error) { $result['retryable']++; }
        $cursor = sharity_donation_webhook_cursor($orderId,$cursor);
    }
    $result['budget_exhausted'] = $result['budget_exhausted'] || $clock() >= $deadline;
    return $result;
}

/** Catch up saved sessions without inventing notifications. Pending notices stay with
 * the drain, which checks their signed progress before projection. No scheduler on load. */
function sharity_donation_finance_poll(int $now, ?object $transport = null, ?callable $clock = null): array
{
    $clock = $clock ?? static fn(): float => hrtime(true) / 1e9;
    $deadline = $clock() + 60;
    if ($now < 1 || $now > 253402300799 || get_option(SHARITY_DONATION_WEBHOOK_FINANCE_SCHEMA_OPTION, '') !== '1'
        || !function_exists('sharity_donation_finance_target')) { throw new RuntimeException('DONATION_POLL_UNAVAILABLE'); }
    sharity_donation_order_autocommit();
    global $wpdb;
    $ordersTable = sharity_donation_order_table(); $inbox = sharity_donation_webhook_table();
    $cursor = sharity_donation_webhook_cursor(null,null,SHARITY_DONATION_POLL_CURSOR_OPTION);
    $result = ['orders' => 0, 'observed' => 0, 'pending' => 0, 'retryable' => 0,
        'budget_exhausted' => false, 'financial_readiness' => false];
    $sql = $wpdb->prepare("SELECT o.order_id FROM `{$ordersTable}` o
        WHERE o.state = 'session_recorded' AND provider_session_id IS NOT NULL AND o.order_id > %s
        AND NOT EXISTS (SELECT 1 FROM `{$inbox}` i WHERE i.order_id = o.order_id AND i.observation_digest IS NULL)
        ORDER BY o.order_id ASC LIMIT 5", $cursor ?? '');
    $orders = $wpdb->get_results($sql, ARRAY_A);
    if ($wpdb->last_error !== '' || !is_array($orders)) { throw new RuntimeException('DONATION_POLL_UNAVAILABLE'); }
    if (!$orders) {
        if ($cursor !== null && $cursor !== '') { sharity_donation_webhook_cursor('', $cursor,SHARITY_DONATION_POLL_CURSOR_OPTION); }
        return $result;
    }
    foreach ($orders as $candidate) {
        if ($clock() >= $deadline) { $result['budget_exhausted'] = true; break; }
        $orderId = (string) $candidate['order_id'];
        $result['orders']++;
        try {
            $outcome = sharity_donation_webhook_bounded_read(
                static function () use ($orderId,$now,$wpdb,$inbox): string {
                    [, $evidence] = sharity_donation_webhook_finance_observe($orderId,$now);
                    if (($evidence['original']['evidence_state'] ?? null) !== 'original_charge_observed') { return 'pending'; }
                    $pending = $wpdb->get_results($wpdb->prepare("SELECT event_id FROM `{$inbox}` WHERE order_id = %s AND observation_digest IS NULL LIMIT 1",$orderId),ARRAY_A);
                    if ($wpdb->last_error !== '' || !is_array($pending)) { throw new RuntimeException('DONATION_POLL_UNAVAILABLE'); }
                    if ($pending) { return 'pending'; }
                    sharity_donation_webhook_finance_project($orderId,$now);
                    return 'observed';
                },
                $deadline, $transport, $clock, 63);
            if ($outcome === 'observed') { $result['observed']++; } else { $result['pending']++; }
        } catch (Throwable $error) {
            $result['retryable']++;
        }
        $cursor = sharity_donation_webhook_cursor($orderId,$cursor,SHARITY_DONATION_POLL_CURSOR_OPTION);
    }
    $result['budget_exhausted'] = $result['budget_exhausted'] || $clock() >= $deadline;
    return $result;
}
add_action('sharity_donation_finance_poll', static function (): void {
    try {
        $r = sharity_donation_finance_poll(time());
        if ($r['retryable'] > 0) { error_log('Sharity donation finance poll: retryable=' . $r['retryable']); }
    } catch (Throwable $error) { error_log('Sharity donation finance poll unavailable; orders retained.'); }
});

add_action('sharity_donation_finance_drain', static function (): void {
    try {
        $r = sharity_donation_checkout_drain(time());
        if ($r['retryable'] > 0) { error_log('Sharity donation finance drain: retryable=' . $r['retryable']); }
    } catch (Throwable $error) { error_log('Sharity donation finance drain unavailable; inbox retained.'); }
});

add_action('rest_api_init', static function (): void {
    register_rest_route('sharity/v1', '/donations/webhook', ['methods' => 'POST',
        'permission_callback' => '__return_true', 'callback' => 'sharity_donation_webhook_handle']);
});
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations webhook-schema', static function (array $args, array $assoc): void {
        if (!array_key_exists('apply', $assoc)) { WP_CLI::error('Explicit --apply required; no change made.'); return; }
        if (!sharity_donation_webhook_schema_apply()) { WP_CLI::error('Inbox schema incompatible or unconfirmed; no existing schema altered.'); return; }
        WP_CLI::success('Inbox schema verified; separate signing configuration required. Checkout switches unchanged.');
    });
    WP_CLI::add_command('sharity donations webhook-recover', static function (array $args, array $assoc): void {
        if (!is_string($assoc['event'] ?? null)) { WP_CLI::error('--event required.'); return; }
        try { sharity_donation_webhook_recover($assoc['event'], time()); }
        catch (Throwable $error) { WP_CLI::error('Recovery unconfirmed; event retained. No new payment attempted.'); return; }
        WP_CLI::success('Session reference recovered; this is not financial settlement.');
    });
}
