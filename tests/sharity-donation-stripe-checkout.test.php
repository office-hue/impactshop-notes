<?php
declare(strict_types=1);
// Real pinned SDK, replaced HTTP transport. No network/provider account access.
$autoload = getenv('SHARITY_DONATION_SDK_AUTOLOAD') ?: __DIR__ . '/../tmp/sharity-donation-sdk/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR, "Install the dedicated composer.lock into an isolated vendor and set SHARITY_DONATION_SDK_AUTOLOAD.\n"); exit(1); }
require $autoload;
function add_action(...$args): void {}
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/stripe-checkout.php';
$checks = 0;
function check(bool $ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function fails(callable $call, string $code): void {
    try { $call(); } catch (RuntimeException $e) {
        check($e->getMessage() === $code, 'error code: ' . $code . ' got ' . $e->getMessage());
        check($e->getPrevious() === null, 'no exception chain with provider secrets'); return;
    }
    throw new RuntimeException('Expected failure: ' . $code);
}
final class DonationHttp implements Stripe\HttpClient\ClientInterface
{
    public array $calls = [];
    public array $response = [];
    public bool $drop = false;
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        // Do not retain or print Authorization even for synthetic test credentials.
        $headers = array_values(array_filter($headers, static fn($h) => !str_starts_with(strtolower($h), 'authorization:')));
        $this->calls[] = compact('method', 'absUrl', 'headers', 'params', 'maxNetworkRetries');
        if ($this->drop) { throw new Stripe\Exception\ApiConnectionException('SYNTHETIC_PRIVATE_PROVIDER_DETAIL'); }
        return [json_encode($this->response, JSON_THROW_ON_ERROR), 200, ['request-id' => 'req_SYNTHETIC']];
    }
}
function sessionFixture(array $e): array {
    return ['object' => 'checkout.session', 'id' => $e['snapshot']['livemode'] ? 'cs_live_SYNTHETIC' : 'cs_test_SYNTHETIC',
        'livemode' => $e['snapshot']['livemode'], 'status' => 'open', 'payment_status' => 'unpaid', 'mode' => 'payment',
        'client_reference_id' => $e['order_id'], 'amount_total' => (int) $e['snapshot']['amount_minor'],
        'currency' => strtolower($e['snapshot']['currency']), 'expires_at' => $e['expires_at'],
        'metadata' => $e['payload']['metadata'], 'payment_intent' => null,
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_SYNTHETIC#token'];
}
$transport = new DonationHttp();
Stripe\ApiRequestor::setHttpClient($transport);
$now = 1800000000;
$client = Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL');
$adapter = new Sharity_Stripe_Checkout_Adapter($client, static function () use (&$now): int { return $now; });
$base = ['ngo_slug' => 'example-ngo', 'donation_mode' => 'direct', 'currency' => 'HUF', 'amount_minor' => '50000',
    'votes' => 0, 'package_id' => null, 'price_book_version' => null, 'policy_version' => 'direct-net100-v1',
    'account_id' => 'acct_SYNTHETIC', 'binding_version' => 'test-v1', 'livemode' => false];
$order = 'do_' . str_repeat('a', 32); $quote = 'dq_' . str_repeat('b', 32);
function envelope(array $s): array { global $order, $quote; return Sharity_Stripe_Checkout_Adapter::envelope($s, $order, $quote, 1800000000, 1800003600, 'hu'); }
foreach (['direct', 'amplifier'] as $mode) {
    foreach (['HUF', 'EUR', 'USD', 'GBP'] as $currency) {
        $s = array_replace($base, ['donation_mode' => $mode, 'currency' => $currency, 'amount_minor' => $currency === 'HUF' ? '50000' : '300']);
        if ($mode === 'amplifier') { $s = array_replace($s, ['votes' => 500, 'package_id' => 'starter', 'price_book_version' => $currency === 'HUF' ? 'amplifier-net80-v1:HUF' : 'amplifier-net80-v1:2026-11-02:' . $currency, 'policy_version' => 'amplifier-net80-v1']); }
        $e = envelope($s); $transport->response = sessionFixture($e); $r = $adapter->createSession($e);
        $call = end($transport->calls);
        check($call['method'] === 'post' && $call['absUrl'] === 'https://api.stripe.com/v1/checkout/sessions', 'official SDK endpoint');
        check($call['params']['line_items'][0]['price_data']['unit_amount'] === (int) $s['amount_minor'], 'minor never multiplied by100 or votes');
        check($call['params']['payment_intent_data']['application_fee_amount'] === ($mode === 'direct' ? 0 : intdiv((int) $s['amount_minor'], 5)), 'explicit zero or provisional20pct');
        check($call['maxNetworkRetries'] === 0 && in_array('Stripe-Account: acct_SYNTHETIC', $call['headers'], true), 'no retry and connected account');
        check(in_array('Stripe-Version: 2026-10-28.endive', $call['headers'], true), 'API pin on wire');
        check(in_array('Idempotency-Key: sharity-donation-checkout-v1:' . $order, $call['headers'], true), 'durable order key on wire');
        check($r['payment_verified'] === false && $r['checkout_state'] === 'open', 'checkout is not financial proof');
        check($call['params']['adaptive_pricing']['enabled'] === 'false' && $call['params']['automatic_tax']['enabled'] === 'false', 'no inherited repricing/tax');
        check(!isset($call['params']['payment_method_types']) && !isset($call['params']['customer']), 'dashboard dynamic methods, no customer writer');
        check(array_keys($call['params']['metadata']) === ['order_id', 'quote_id', 'policy_version', 'donation_mode'], 'minimal opaque metadata');
    }
}
$e = envelope($base);
check($e === json_decode(json_encode($e), true) && $e === envelope($base), 'stable serializable envelope');
check($e['payload']['success_url'] === 'https://sharity.hu/adomanyozas/example-ngo?donation_order=' . $order . '&checkout=returned', 'fixed success URL');
foreach (['hu', 'en', 'de', 'fr'] as $locale) {
    $localized = Sharity_Stripe_Checkout_Adapter::envelope($base, $order, $quote, 1800000000, 1800003600, $locale);
    check($localized['payload']['locale'] === $locale, 'four supported locales');
}
foreach (sharity_donation_packages()['packages'] as $package) {
    $s = array_replace($base, ['donation_mode' => 'amplifier', 'amount_minor' => (string) ($package['huf_whole'] * 100), 'votes' => $package['base_votes'] + $package['bonus_votes'], 'package_id' => $package['id'], 'price_book_version' => 'amplifier-net80-v1:HUF', 'policy_version' => 'amplifier-net80-v1']);
    check(envelope($s)['payload']['line_items'][0]['price_data']['unit_amount'] === $package['huf_whole'] * 100, 'five approved packages exact');
}
foreach ([['amount_minor' => '101'], ['amount_minor' => 50000], ['amount_minor' => '100000000'], ['ngo_slug' => "test\n"], ['currency' => 'JPY'], ['account_id' => 'acct_bad/'], ['livemode' => 0], ['owner' => 'forbidden']] as $bad) {
    fails(static fn() => envelope(array_replace($base, $bad)), 'DONATION_INVALID_SNAPSHOT');
}
foreach ([['votes' => 1], ['package_id' => 'starter'], ['policy_version' => 'old-policy']] as $bad) {
    fails(static fn() => envelope(array_replace($base, $bad)), 'DONATION_INVALID_POLICY');
}
$amp = array_replace($base, ['donation_mode' => 'amplifier', 'package_id' => 'starter', 'price_book_version' => 'amplifier-net80-v1:HUF', 'policy_version' => 'amplifier-net80-v1', 'votes' => 500]);
foreach ([['votes' => 501], ['votes' => '500'], ['package_id' => 'missing'], ['amount_minor' => '50100']] as $bad) {
    fails(static fn() => envelope(array_replace($amp, $bad)), 'DONATION_INVALID_POLICY');
}
$count = count($transport->calls);
$bad = $e; $bad['payload']['line_items'][0]['price_data']['unit_amount'] = 999;
fails(static fn() => $adapter->createSession($bad), 'DONATION_INVALID_ENVELOPE');
$bad = $e; $bad['options']['stripe_account'] = 'acct_OTHER';
fails(static fn() => $adapter->createSession($bad), 'DONATION_INVALID_ENVELOPE');
$now = 1800001801;
fails(static fn() => $adapter->createSession($e), 'DONATION_SESSION_WINDOW_EXPIRED');
check(count($transport->calls) === $count, 'invalid input never dispatched'); $now = 1800000000;
$transport->drop = true;
fails(static fn() => $adapter->createSession($e), 'DONATION_CHECKOUT_UNCERTAIN');
check(count($transport->calls) === $count + 1, 'uncertain create exactly one HTTP call'); $transport->drop = false;
foreach (['amount_total' => 50001, 'currency' => 'eur', 'id' => 'cs_live_BAD', 'livemode' => true, 'mode' => 'subscription', 'client_reference_id' => 'do_OTHER', 'expires_at' => 1800003601, 'metadata' => [], 'status' => 'unknown', 'payment_status' => 'invented'] as $key => $value) {
    $transport->response = array_replace(sessionFixture($e), [$key => $value]);
    fails(static fn() => $adapter->createSession($e), 'DONATION_CHECKOUT_UNCERTAIN');
}
foreach (['https://checkout.stripe.com.evil/c/pay/x', 'https://x@checkout.stripe.com/c/pay/x', 'https://checkout.stripe.com:443/c/pay/x', 'http://checkout.stripe.com/c/pay/x', "https://checkout.stripe.com/c/pay/x\n", 'https://checkout.stripe.com/c/pay/x\\evil', null] as $url) {
    $transport->response = array_replace(sessionFixture($e), ['url' => $url]);
    fails(static fn() => $adapter->createSession($e), 'DONATION_CHECKOUT_UNCERTAIN');
}
$now = 1801000000; // Readback must work long after quote/session expiry, after activation off.
foreach (['complete', 'expired'] as $status) {
    foreach (['paid', 'unpaid'] as $payment) {
        $transport->response = array_replace(sessionFixture($e), ['status' => $status, 'payment_status' => $payment, 'url' => null]);
        $r = $adapter->retrieveSession('cs_test_SYNTHETIC', $e); $call = end($transport->calls);
        check($r['checkout_state'] === $status && $r['payment_verified'] === false && $r['provider_payment_status'] === $payment, 'completed/paid hint is never payment proof');
        check($call['method'] === 'get' && $call['absUrl'] === 'https://api.stripe.com/v1/checkout/sessions/cs_test_SYNTHETIC'
            && in_array('Stripe-Account: acct_SYNTHETIC', $call['headers'], true) && $call['maxNetworkRetries'] === 0, 'historical GET original account no retry');
        check(count(array_filter($call['headers'], static fn($h) => str_starts_with($h, 'Idempotency-Key:'))) === 0, 'readback not create replay');
    }
}
$transport->response['id'] = 'cs_test_OTHER';
fails(static fn() => $adapter->retrieveSession('cs_test_SYNTHETIC', $e), 'DONATION_CHECKOUT_READBACK_UNAVAILABLE');
$transport->response = sessionFixture($e);
$transport->response['payment_intent'] = ['object' => 'payment_intent', 'id' => 'pi_SYNTHETIC', 'metadata' => $e['payload']['metadata'], 'amount' => 50000, 'currency' => 'huf', 'livemode' => false];
check($adapter->retrieveSession('cs_test_SYNTHETIC', $e)['payment_verified'] === false, 'expanded intent still no financial proof');
$transport->response['payment_intent']['metadata']['order_id'] = 'do_OTHER';
fails(static fn() => $adapter->retrieveSession('cs_test_SYNTHETIC', $e), 'DONATION_CHECKOUT_READBACK_UNAVAILABLE');
$count = count($transport->calls);
fails(static fn() => $adapter->retrieveSession('cs_test_x/../other', $e), 'DONATION_INVALID_SESSION_ID');
fails(static fn() => $adapter->retrieveSession('cs_live_OTHER', $e), 'DONATION_INVALID_SESSION_ID');
$live = envelope(array_replace($base, ['livemode' => true]));
fails(static fn() => $adapter->createSession($live), 'DONATION_KEY_MODE_MISMATCH');
check(count($transport->calls) === $count, 'mode/identifier rejected before GET/POST');
fails(static fn() => new Sharity_Stripe_Checkout_Adapter(new Stripe\StripeClient(['api_key' => 'rk_test_SYNTHETIC', 'max_network_retries' => 1])), 'DONATION_SDK_CONFIGURATION');
fails(static fn() => new Sharity_Stripe_Checkout_Adapter(new Stripe\StripeClient(['api_key' => 'rk_test_SYNTHETIC', 'stripe_context' => 'acct_OTHER', 'max_network_retries' => 0])), 'DONATION_SDK_CONFIGURATION');
fails(static fn() => new Sharity_Stripe_Checkout_Adapter(new Stripe\StripeClient(['api_key' => 'rk_test_SYNTHETIC', 'stripe_version' => '2026-09-30.endive', 'max_network_retries' => 0])), 'DONATION_SDK_CONFIGURATION');
$now = 1800000000;
$liveAdapter = new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client('rk_live_SYNTHETICNOTACREDENTIAL'), static function () use (&$now): int { return $now; });
$transport->response = sessionFixture($live);
check($liveAdapter->createSession($live)['session_id'] === 'cs_live_SYNTHETIC', 'live-shaped contract uses offline transport only');
// Current manifest is not consulted by the adapter: frozen v1 prices/votes are
// validated from the immutable policy contract and survive current book expiry.
$ampEnvelope = envelope($amp);
$now += 1000000;
$transport->response = array_replace(sessionFixture($ampEnvelope), ['status' => 'complete', 'url' => null]);
check($adapter->retrieveSession('cs_test_SYNTHETIC', $ampEnvelope)['payment_verified'] === false, 'historical amplifier readback after expiry');
echo "Donation Stripe Checkout: {$checks} checks passed (real SDK, offline HTTP; no sandbox/live acceptance)\n";
