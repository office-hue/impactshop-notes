<?php
/** Signed Connect Checkout inbox. Receipt/recovery is NOT financial settlement.
 * Only minimal notification identity is retained, never raw payload/customer data.
 * Historical receipt/recovery is independent of switches for new checkouts.
 */
declare(strict_types=1);
const SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION = 'sharity_donation_webhooks_schema_v1';
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

/** null means a verified event outside this narrowly configured receiver. */
function sharity_donation_webhook_notification(array $event): ?array
{
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

function sharity_donation_webhook_handle(WP_REST_Request $request): WP_REST_Response
{
    try {
        $raw = $request->get_body(); $header = $request->get_header('stripe-signature');
        if (!is_string($raw) || !is_string($header)) { throw new RuntimeException('WEBHOOK_INVALID_REQUEST'); }
        $event = sharity_donation_webhook_verify($raw, $header);
        if (get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') !== '1') { throw new RuntimeException('WEBHOOK_SCHEMA_UNAVAILABLE'); }
        $note = sharity_donation_webhook_notification($event);
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
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if ($wpdb->last_error !== '' || ($status['Engine'] ?? '') !== 'InnoDB' || !is_array($columns) || !is_array($indexes)) { return false; }
        $expected = ['event_id' => ['varchar(255)', 'ascii_bin'], 'order_id' => ['char(35)', 'ascii_bin'],
            'session_id' => ['varchar(255)', 'ascii_bin'], 'identity_json' => ['longtext', 'utf8mb4_bin'],
            'identity_digest' => ['char(64)', 'ascii_bin'], 'received_at_utc' => ['datetime', null], 'recovered_at_utc' => ['datetime', null]];
        if (count($columns) !== count($expected)) { return false; }
        foreach ($columns as $c) {
            if (!isset($expected[$c['Field']]) || [$c['Type'], $c['Collation']] !== $expected[$c['Field']]
                || $c['Null'] !== ($c['Field'] === 'recovered_at_utc' ? 'YES' : 'NO') || $c['Default'] !== null || $c['Extra'] !== '') { return false; }
        }
        $actual = [];
        foreach ($indexes as $i) {
            if ($i['Sub_part'] !== null || $i['Index_type'] !== 'BTREE') { return false; }
            $actual[$i['Key_name']][] = [$i['Column_name'], (int) $i['Non_unique'], (int) $i['Seq_in_index']];
        }
        ksort($actual);
        if ($actual !== ['PRIMARY' => [['event_id', 0, 1]], 'order_id' => [['order_id', 1, 1]]]) { return false; }
        update_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '1', false);
        return get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION, '') === '1';
    } catch (Throwable $error) { return false; }
}

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
