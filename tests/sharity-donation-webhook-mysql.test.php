<?php
/** Included by the existing isolated MariaDB harness inside its cleanup scope. */
declare(strict_types=1);
if (!isset($wpdb, $adapter, $transport, $subject, $now, $binding)) { throw new RuntimeException('Run sharity-donation-orders-mysql.test.php'); }
function sharity_donation_runtime_sdk(): void { if (!class_exists(Stripe\Webhook::class)) { throw new RuntimeException('Pinned SDK missing'); } }
define('SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET', 'whsec_SYNTHETICLOCALTESTONLY');
define('SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE', false);
$beforeDdl = $wpdb->ddls;
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/webhook.php';
check($wpdb->ddls === $beforeDdl, 'webhook definitions do not migrate');
check(sharity_donation_webhook_schema_apply(), 'real inbox schema verified');
$inbox = sharity_donation_webhook_table();
function inboxCount(): int { global $wpdb; $t = sharity_donation_webhook_table(); return (int) $wpdb->db->query("SELECT COUNT(*) AS n FROM `{$t}`")->fetch_assoc()['n']; }
function pendingOrder(string $tag): array {
    global $transport, $adapter, $subject, $now;
    $o = newOrder($tag); $transport->drop = true;
    try { sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now); }
    finally { $transport->drop = false; }
    return sharity_donation_order_read($subject, 'order_id', $o['order_id']);
}
function checkoutEvent(array $o, string $type = 'checkout.session.completed'): array {
    $e = sharity_donation_order_decode($o); $s = $e['snapshot'];
    $id = 'cs_test_' . substr(hash('sha256', $o['order_id']), 0, 40);
    return ['id' => 'evt_' . bin2hex(random_bytes(12)), 'object' => 'event', 'type' => $type, 'created' => time(),
        'api_version' => Sharity_Stripe_Checkout_Adapter::API_VERSION, 'livemode' => false, 'account' => $s['account_id'],
        'pending_webhooks' => 1, 'data' => ['object' => ['object' => 'checkout.session', 'id' => $id,
            'client_reference_id' => $o['order_id'], 'metadata' => $e['payload']['metadata'], 'amount_total' => (int) $s['amount_minor'],
            'currency' => strtolower($s['currency']), 'mode' => 'payment', 'livemode' => false, 'expires_at' => $e['expires_at'],
            'status' => $type === 'checkout.session.expired' ? 'expired' : 'complete', 'payment_status' => 'paid', 'url' => null,
            'customer_details' => ['email' => 'synthetic@example.invalid', 'name' => 'LOCAL FIXTURE']]]];
}
function deliver(array $event, ?int $timestamp = null, ?string $header = null, ?string $raw = null): WP_REST_Response {
    $raw ??= json_encode($event, JSON_THROW_ON_ERROR);
    $sig = $header ?? Stripe\WebhookSignature::generateSignatureHeader($raw, SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET, $timestamp);
    $r = new WP_REST_Request([], ''); $r->raw = $raw; $r->signature = $sig;
    return sharity_donation_webhook_handle($r);
}
$o = pendingOrder('webhook-base'); $event = checkoutEvent($o); $before = inboxCount();
foreach (['', 't=' . time() . ',v1=invalid'] as $header) { check(deliver($event, null, $header)->status === 400, 'missing/bad signature rejected'); }
check(deliver($event, time() - 600)->status === 400, 'old signature rejected');
check(deliver($event, time() + 600)->status === 400, 'future signature rejected');
$signed = json_encode($event); $sig = Stripe\WebhookSignature::generateSignatureHeader($signed, SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET);
check(deliver($event, null, $sig, $signed . ' ')->status === 400, 'signature uses exact raw body');
check(deliver($event, null, null, str_repeat('x', 262145))->status === 400, 'oversized body rejected');
check(deliver($event, null, str_repeat('x', 8193))->status === 400, 'oversized header rejected');
check(deliver($event, null, null, '{')->status === 400, 'signed invalid JSON rejected');
check(inboxCount() === $before, 'invalid signatures write nothing');
$variants = [
    ['account', 'acct_FOREIGN'], ['api_version', '2020-08-27'], ['livemode', true], ['created', '12'],
    ['data.object.livemode', true], ['data.object.object', 'payment_intent'], ['data.object.id', 'cs_live_WRONG'],
    ['data.object.client_reference_id', 'do_' . str_repeat('f',32)], ['data.object.metadata.quote_id', 'dq_' . str_repeat('e',32)],
    ['data.object.metadata.policy_version', 'other'], ['data.object.metadata.donation_mode', 'amplifier'],
    ['data.object.mode', 'subscription'], ['data.object.amount_total', '50000'], ['data.object.amount_total', 500],
    ['data.object.currency', 'eur'], ['data.object.expires_at', 1], ['data.object.status', 'open'], ['data.object.payment_status', 'unknown'],
];
foreach ($variants as [$path, $value]) {
    $bad = $event; $ref = &$bad; foreach (explode('.', $path) as $segment) { $ref = &$ref[$segment]; } $ref = $value; unset($ref);
    check(deliver($bad)->status === 400, 'signed foreign context rejected: ' . $path);
}
$unknown = $event; $unknown['data']['object']['client_reference_id'] = $unknown['data']['object']['metadata']['order_id'] = 'do_' . str_repeat('d',32);
check(deliver($unknown)->status === 503, 'unknown own order not acknowledged as safely stored');
check(inboxCount() === $before, 'wrong context writes nothing');
$unrelated = $event; $unrelated['type'] = 'charge.succeeded';
check(deliver($unrelated)->body['ignored'] === true, 'out of scope verified event ignored');
$unrelated = $event; $unrelated['data']['object']['metadata'] = []; $unrelated['data']['object']['client_reference_id'] = null;
check(deliver($unrelated)->body['ignored'] === true, 'unrelated merchant checkout ignored');
$options[SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION] = '0';
check(deliver($event)->status === 503, 'missing inbox readiness no ACK'); $options[SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION] = '1';
$wpdb->failOn = 'INSERT INTO `' . $inbox . '`'; $wpdb->failure = 'before';
check(deliver($event)->status === 503, 'failed insert no ACK'); $wpdb->failOn = ''; $wpdb->failure = '';
check(inboxCount() === $before, 'failed insert no receipt');
$options[SHARITY_DONATION_ORDERS_ENABLED] = '0'; $options['sharity_donation_accounts_v1'] = [];
$reply = deliver($event);
check($reply->status === 200 && $reply->body['received'] === true, 'historical webhook accepts while checkout/binding off');
check($reply->headers['Cache-Control'] === 'private, no-store', 'private receipt');
$row = sharity_donation_webhook_read($event['id']);
check($row['recovered_at_utc'] === null && !str_contains($row['identity_json'], 'LOCAL FIXTURE') && !str_contains($row['identity_json'], 'email') && !str_contains($row['identity_json'], $subject), 'minimal pending inbox without customer/owner data');
$changedDelivery = $event; $changedDelivery['pending_webhooks'] = 99; $changedDelivery['data']['object']['customer_details']['email'] = 'changed@example.invalid';
check(deliver($changedDelivery)->status === 200 && inboxCount() === $before + 1, 'volatile delivery details do not break duplicate');
$changedIdentity = $event; $changedIdentity['created'] -= 1;
check(deliver($changedIdentity)->status === 409 && sharity_donation_webhook_read($event['id']) === $row, 'event identity conflict preserves original');
foreach (['checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'] as $type) {
    check(deliver(checkoutEvent($o, $type))->status === 200, 'distinct notification same session: ' . $type);
}
foreach (['lost_ack', 'throw_after'] as $failure) {
    $wpdb->failOn = 'INSERT INTO `' . $inbox . '`'; $wpdb->failure = $failure;
    check(deliver(checkoutEvent($o))->status === 200, 'readback recovers inbox insert ' . $failure); $wpdb->failOn = ''; $wpdb->failure = '';
}
$parallel = checkoutEvent($o); $before = inboxCount();
$results = race(fn($slot) => deliver($parallel)->status, 'WHERE order_id =');
check(($results[0]['value'] ?? null) === 200 && ($results[1]['value'] ?? null) === 200 && inboxCount() === $before + 1, 'parallel duplicate requests both ACK one durable notification');
$wpdb->db->begin_transaction(); $ddls = $wpdb->ddls;
check(!sharity_donation_webhook_schema_apply() && $wpdb->ddls === $ddls, 'schema no implicit outer transaction commit');
check(deliver(checkoutEvent($o))->status === 503, 'inbox refuses uncommitted surrounding transaction'); $wpdb->db->rollback();

final class RecoveryHttp implements Stripe\HttpClient\ClientInterface {
    public array $session; public string $account; public string $log; public bool $drop = false; public bool $vary = false;
    public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        global $wpdb;
        if ($method !== 'get' || $url !== 'https://api.stripe.com/v1/checkout/sessions/' . $this->session['id']
            || $maxNetworkRetries !== 0 || !in_array('Stripe-Account: ' . $this->account, $headers, true)) { throw new RuntimeException('wrong provider GET context'); }
        if ((int) $wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx'] !== 0) { throw new RuntimeException('GET under transaction'); }
        file_put_contents($this->log, "GET\n", FILE_APPEND | LOCK_EX);
        if ($this->drop) { throw new Stripe\Exception\ApiConnectionException('synthetic unavailable'); }
        $s = $this->session;
        if ($this->vary && getmypid() % 2 === 0) { $s['payment_status'] = 'unpaid'; }
        return [json_encode($s), 200, []];
    }
}
$get = new RecoveryHttp(); $get->session = $event['data']['object']; $get->account = $event['account']; $get->log = $log;
Stripe\ApiRequestor::setHttpClient($get); $before = calls();
$get->drop = true;
fails(fn() => sharity_donation_webhook_recover($event['id'], $now + 9000), 'DONATION_CHECKOUT_READBACK_UNAVAILABLE');
$get->drop = false;
check(sharity_donation_webhook_read($event['id'])['recovered_at_utc'] === null, 'failed GET keeps pending evidence');
$wpdb->db->begin_transaction();
fails(fn() => sharity_donation_webhook_recover($event['id'], $now), 'ORDER_TRANSACTION_UNSAFE'); $wpdb->db->rollback();
check(calls() === $before + 1, 'failed transaction never calls GET');
$wpdb->failOn = "SET state = 'session_recorded'"; $wpdb->failure = 'lost_ack';
sharity_donation_webhook_recover($event['id'], $now + 9000); $wpdb->failOn = ''; $wpdb->failure = '';
check(calls() === $before + 2 && sharity_donation_order_status($subject, $o['order_id'], $now)['payment_verified'] === false, 'historical recovery GET only, no payment proof');
check(sharity_donation_webhook_read($event['id'])['recovered_at_utc'] !== null, 'recovery marker read back');
$runtimeBefore = $GLOBALS['runtimeFactoryCalls']; $before = calls();
sharity_donation_webhook_recover($event['id'], $now);
check(calls() === $before && $GLOBALS['runtimeFactoryCalls'] === $runtimeBefore, 'completed recovery no SDK/key/provider');
$later = checkoutEvent($o, 'checkout.session.async_payment_succeeded'); check(deliver($later)->status === 200, 'later callback accepted');
sharity_donation_webhook_recover($later['id'], $now);
check(calls() === $before, 'recorded same session no new provider call');
$wrongSession = $event; $wrongSession['id'] = 'evt_' . bin2hex(random_bytes(12)); $wrongSession['data']['object']['id'] = 'cs_test_OTHER';
check(deliver($wrongSession)->status === 400, 'existing session cannot be replaced by event');
$options[SHARITY_DONATION_ORDERS_ENABLED] = '1'; $options['sharity_donation_accounts_v1'] = ['test-ngo' => $binding];
Stripe\ApiRequestor::setHttpClient($transport);
$raceOrder = pendingOrder('webhook-recovery-race'); $raceEvent = checkoutEvent($raceOrder); check(deliver($raceEvent)->status === 200, 'race receipt');
$get->session = $raceEvent['data']['object']; $get->vary = true; Stripe\ApiRequestor::setHttpClient($get);
$results = race(function($slot) use ($raceEvent, $now) { sharity_donation_webhook_recover($raceEvent['id'], $now); return true; }, 'WHERE order_id =');
check(($results[0]['value'] ?? null) === true && ($results[1]['value'] ?? null) === true, 'concurrent recovery different provider status same session both succeed');
$stored = sharity_donation_order_read($subject, 'order_id', $raceOrder['order_id']);
check($stored['provider_session_id'] === $raceEvent['data']['object']['id'] && $stored['state'] === 'session_recorded', 'recovery one immutable recorded session');
$extra = checkoutEvent($raceOrder); check(deliver($extra)->status === 200, 'new same-session evidence');
$wpdb->failOn = 'SET recovered_at_utc'; $wpdb->failure = 'before';
fails(fn() => sharity_donation_webhook_recover($extra['id'], $now), 'WEBHOOK_RECOVERY_UNCONFIRMED');
$wpdb->failure = 'throw_after'; sharity_donation_webhook_recover($extra['id'], $now); $wpdb->failOn = ''; $wpdb->failure = '';
check(sharity_donation_webhook_read($extra['id'])['recovered_at_utc'] !== null, 'lost recovery marker ACK readback');
Stripe\ApiRequestor::setHttpClient($transport);
$prepared = newOrder('webhook-prepared'); check(deliver(checkoutEvent($prepared))->status === 400, 'unattempted order not bound to provider event');
$before = inboxCount(); $wpdb->db->query("ALTER TABLE `{$inbox}` DROP INDEX order_id");
check(!sharity_donation_webhook_schema_apply() && get_option(SHARITY_DONATION_WEBHOOKS_SCHEMA_OPTION) === '0', 'missing schema index clears readiness');
check(inboxCount() === $before, 'schema verification preserves inbox data');
