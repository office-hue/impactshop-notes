<?php
declare(strict_types=1);

// Run only with a disposable MariaDB database exposed on loopback and the pinned Stripe SDK.
$port = filter_var(getenv('SHARITY_EXPO_TEST_DB_PORT'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1024, 'max_range' => 65535],
]);
$sdk = getenv('SHARITY_EXPO_TEST_SDK_INIT');
if ($port === false || !is_string($sdk) || !is_file($sdk)) {
    throw new RuntimeException('Explicit isolated DB port and pinned Stripe SDK init required');
}

const ABSPATH = __DIR__;
const ARRAY_A = 'ARRAY_A';
define('EXPO_TEST_SECRET', 'whsec_' . bin2hex(random_bytes(24)));

$checks = 0;
$routes = [];
$sdkAvailable = true;

function expoCheck(bool $ok, string $description): void
{
    $GLOBALS['checks']++;
    if (!$ok) {
        throw new RuntimeException($description);
    }
}

function add_action(string $hook, callable $callback): void
{
    expoCheck($hook === 'rest_api_init' && $callback === [Sharity_Expo_Stripe_Events::class, 'register'], 'REST init only');
}

function register_rest_route(string $namespace, string $route, array $definition): void
{
    $GLOBALS['routes'][$namespace . $route] = $definition;
}

function sharity_donation_runtime_sdk(): void
{
    if (!$GLOBALS['sdkAvailable']) {
        throw new RuntimeException('synthetic SDK error');
    }
    require_once $GLOBALS['sdk'];
    expoCheck(Stripe\Stripe::VERSION === '22.1.0', 'pinned Stripe SDK');
}

final class WP_REST_Request
{
    public function __construct(private string $body, private string $signature)
    {
    }

    public function get_body(): string
    {
        return $this->body;
    }

    public function get_header(string $name): string
    {
        return $name === 'stripe-signature' ? $this->signature : '';
    }
}

final class WP_REST_Response
{
    public array $headers = [];

    public function __construct(public array $data, public int $status)
    {
    }

    public function header(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }
}

final class ExpoTestDatabase
{
    public string $prefix;
    public mysqli $db;

    public function __construct(int $port)
    {
        $this->prefix = 'exptest_' . bin2hex(random_bytes(4)) . '_';
        $this->db = new mysqli('127.0.0.1', 'root', 'isolated-expo-fixture-only', 'expo_ingress_test', $port);
    }

    public function prepare(string $sql, ...$args): string
    {
        $index = 0;
        return preg_replace_callback('/%[sd]/', function ($match) use ($args, &$index): string {
            $value = $args[$index++];
            return $match[0] === '%d' ? (string) (int) $value : "'" . $this->db->real_escape_string((string) $value) . "'";
        }, $sql);
    }

    public function query(string $sql)
    {
        $result = $this->db->query($sql);
        return $result === true ? $this->db->affected_rows : $result;
    }

    public function get_results(string $sql, $mode): array
    {
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function get_var(string $sql)
    {
        return $this->db->query($sql)->fetch_row()[0] ?? null;
    }
}

function expoEvent(string $type, string $eventId, array $object): array
{
    return [
        'id' => 'evt_' . $eventId, 'object' => 'event', 'api_version' => '2026-10-28.endive',
        'created' => time(), 'livemode' => false, 'type' => $type, 'data' => ['object' => $object],
    ];
}

function expoPayment(string $eventId, array $values = [], string $type = 'checkout.session.completed'): array
{
    return expoEvent($type, $eventId, $values + [
        'id' => 'cs_test_fixture', 'object' => 'checkout.session', 'payment_link' => 'plink_expo',
        'payment_intent' => 'pi_expo', 'mode' => 'payment', 'payment_status' => 'paid',
        'livemode' => false, 'currency' => 'huf', 'amount_total' => 300000,
        'customer_details' => ['email' => 'synthetic@example.invalid'],
    ]);
}

function expoRefund(string $eventId, array $values = [], string $type = 'refund.created'): array
{
    return expoEvent($type, $eventId, $values + [
        'id' => 're_expo', 'object' => 'refund', 'payment_intent' => 'pi_expo',
        'currency' => 'huf', 'amount' => 50000, 'status' => 'succeeded',
    ]);
}

function expoDeliver(array $event, int $status, string $description, int $offset = 0, bool $valid = true): void
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $ts = time() + $offset;
    $mac = hash_hmac('sha256', $ts . '.' . $body, $valid ? EXPO_TEST_SECRET : 'synthetic-wrong-secret');
    $result = Sharity_Expo_Stripe_Events::receive(new WP_REST_Request($body, "t={$ts},v1={$mac}"));
    expoCheck($result->status === $status && $result->headers['Cache-Control'] === 'no-store', $description);
}

require __DIR__ . '/../wp-content/mu-plugins/sharity-expo-donations.php';

Sharity_Expo_Stripe_Events::register();
expoCheck($routes === [], 'default OFF registers no route');
expoDeliver(expoPayment('off'), 404, 'OFF performs no signature or storage access');
define('SHARITY_EXPO_STRIPE_ENABLED', true);
Sharity_Expo_Stripe_Events::register();
expoCheck($routes === [], 'required config missing leaves route closed');
define('SHARITY_EXPO_STRIPE_ACCOUNT', 'acct_fixture');
define('SHARITY_EXPO_STRIPE_PAYMENT_LINK', 'plink_expo');
define('SHARITY_EXPO_STRIPE_LIVEMODE', false);
define('SHARITY_EXPO_STRIPE_WEBHOOK_SECRET', EXPO_TEST_SECRET);
Sharity_Expo_Stripe_Events::register();
$route = $routes['sharity/v1/expo/babamama-2026/events'] ?? [];
expoCheck(count($routes) === 1 && ($route['methods'] ?? '') === 'POST', 'only intended POST route');

$sdkAvailable = false;
expoDeliver(expoPayment('nosdk'), 503, 'missing SDK is retryable and does not write');
$sdkAvailable = true;
expoDeliver(expoPayment('badSig'), 400, 'untrusted signature refused', valid: false);
expoDeliver(expoPayment('stale'), 400, 'stale signature refused', offset: -301);
expoDeliver(array_replace(expoPayment('wrongmode'), ['livemode' => true]), 400, 'wrong Stripe mode');
expoDeliver(expoPayment('foreignacct') + ['account' => 'acct_other'], 400, 'foreign account');
expoDeliver(expoPayment('ctx') + ['context' => 'acct_fixture'], 400, 'unsupported event context');
expoDeliver(array_replace(expoPayment('badver'), ['api_version' => '2024-12-18.acacia']), 400, 'unsupported Stripe event version');

$wpdb = new ExpoTestDatabase($port);
$table = $wpdb->prefix . 'sharity_expo_stripe_events_v1';
expoDeliver(expoPayment('noddl'), 503, 'missing table fails closed');
$schema = file_get_contents(__DIR__ . '/../scripts/sharity-expo-stripe-events.sql');
try {
    $wpdb->query(str_replace('__PREFIX__', $wpdb->prefix, $schema));
    expoDeliver(expoPayment('other', ['payment_link' => 'plink_other']), 200, 'foreign campaign ignored');
    expoDeliver(expoPayment('unpaid', ['payment_status' => 'unpaid']), 200, 'unpaid ignored');
    expoDeliver(expoPayment('othercurrency', ['currency' => 'eur']), 422, 'campaign currency mismatch');
    expoDeliver(expoPayment('invalidamount', ['amount_total' => '300000']), 422, 'numeric string is not a minor-unit amount');
    expoDeliver(expoRefund('before'), 503, 'refund before its payment can be retried');
    expoDeliver(expoRefund('pending', ['status' => 'pending']), 200, 'pending refund ignored');
    expoCheck((int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`") === 0, 'ignored events leave no data');

    $payment = expoPayment('paid');
    expoDeliver($payment, 200, 'verified direct snapshot without event.account persisted');
    expoDeliver($payment, 200, 'identical delivery idempotent');
    expoDeliver(expoPayment('async', [], 'checkout.session.async_payment_succeeded'), 200, 'different event same payment counted once');
    expoDeliver(expoPayment('wrongamount', ['amount_total' => 300001]), 409, 'business identity conflict');
    expoDeliver(expoPayment('paid', ['payment_intent' => 'pi_other']), 409, 'event identity conflict');
    expoDeliver(expoRefund('before'), 200, 'out-of-order refund saved after retry');
    expoDeliver(expoRefund('updated', [], 'refund.updated'), 200, 'created and updated refund deduplicated');
    expoDeliver(expoRefund('another', ['id' => 're_second', 'amount' => 70000]), 200, 'separate partial refund saved');
    expoDeliver(expoRefund('excess', ['id' => 're_excess', 'amount' => 300001]), 409, 'individual refund cannot exceed payment');

    $wpdb->db->begin_transaction();
    expoDeliver(expoPayment('pendingDb', ['payment_intent' => 'pi_blocked']), 503, 'no ACK in foreign transaction');
    $wpdb->db->rollback();
    $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY object_id", ARRAY_A);
    expoCheck(count($rows) === 3 && (int) $rows[0]['amount_minor'] === 300000, 'three immutable rows, original payment intact');
    expoCheck((int) $rows[1]['amount_minor'] + (int) $rows[2]['amount_minor'] === 120000, 'HUF partial refunds stored once');
    expoCheck(!str_contains(json_encode($rows), 'synthetic@example.invalid'), 'customer data not retained');

    expoDeliver(expoRefund('failedLater', ['status' => 'failed'], 'refund.failed'), 200, 'refund failure after success retained');
    expoDeliver(expoRefund('cancelFirst', ['id' => 're_cancel', 'status' => 'canceled'], 'refund.updated'), 200, 'cancellation recorded before delayed success');
    expoDeliver(expoRefund('staleSuccess', ['id' => 're_cancel']), 200, 'out-of-order success cannot erase cancellation');
    expoDeliver(expoRefund('failedAgain', ['status' => 'failed'], 'refund.updated'), 200, 'reversal deduplicated by refund and kind');
    $remainingRefundMinor = $wpdb->get_var("SELECT SUM(r.amount_minor) FROM `{$table}` r
        WHERE r.kind = 'refund' AND NOT EXISTS (SELECT 1 FROM `{$table}` v
        WHERE v.binding = r.binding AND v.object_id = r.object_id AND v.kind = 'refund_reversal')");
    expoCheck((int) $remainingRefundMinor === 70000, 'reversals exclude refunds in both delivery orders');
    expoCheck((int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE kind = 'refund_reversal'") === 2, 'one correction per reversed refund');

} finally {
    $wpdb->db->query("DROP TABLE IF EXISTS `{$table}`");
}
echo "Expo Stripe webhook: {$checks} checks passed using pinned SDK and isolated MariaDB\n";
