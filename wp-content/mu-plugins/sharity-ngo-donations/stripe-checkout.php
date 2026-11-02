<?php
/**
 * Explicitly loaded D2 adapter. No hooks, endpoints, SDK autoload or activation.
 * Caller must authenticate, recheck activation before a NEW order, persist this
 * immutable envelope and durably claim ONE attempt before createSession().
 * Never call under a DB transaction. An uncertain outcome requires readback,
 * not another create; Stripe idempotency keys may expire after 24 hours.
 * Readback uses the original account and remains possible after deactivation.
 */
declare(strict_types=1);

final class Sharity_Stripe_Checkout_Adapter
{
    public const API_VERSION = '2026-10-28.endive';
    public const SDK_VERSION = '22.1.0';
    // Immutable v1 contract for historical readback; future policies are additive.
    // Do not resolve an accepted order from the current mutable price manifest.
    private const PACKAGES_V1 = [
        'starter' => [50000, 500], 'supporter' => [100000, 1100], 'champion' => [250000, 3000],
        'hero' => [500000, 6500], 'legend' => [1000000, 15000],
    ];
    private Stripe\StripeClient $stripe;
    private $now;

    public static function client(string $key): Stripe\StripeClient
    {
        if (!class_exists(Stripe\StripeClient::class) || Stripe\Stripe::VERSION !== self::SDK_VERSION
            || !preg_match('/^(rk|sk)_(test|live)_[A-Za-z0-9]+$/D', $key)) {
            throw new RuntimeException('DONATION_SDK_UNAVAILABLE');
        }
        return new Stripe\StripeClient(['api_key' => $key, 'stripe_version' => self::API_VERSION, 'max_network_retries' => 0]);
    }

    public function __construct(Stripe\StripeClient $stripe, ?callable $now = null)
    {
        if (Stripe\Stripe::VERSION !== self::SDK_VERSION || $stripe->getStripeVersion() !== self::API_VERSION
            || $stripe->getMaxNetworkRetries() !== 0 || $stripe->getApiBase() !== 'https://api.stripe.com'
            || $stripe->getStripeContext() !== null) {
            throw new RuntimeException('DONATION_SDK_CONFIGURATION');
        }
        $this->stripe = $stripe;
        $this->now = $now ?? static fn(): int => time();
    }

    /** Deterministic request; accepted snapshot is never repriced from current FX/bindings. */
    public static function envelope(array $snapshot, string $orderId, string $quoteId, int $createdAt, int $expiresAt, string $locale): array
    {
        $keys = array_keys($snapshot); sort($keys);
        $required = ['account_id', 'amount_minor', 'binding_version', 'currency', 'donation_mode', 'livemode', 'ngo_slug', 'package_id', 'policy_version', 'price_book_version', 'votes'];
        if ($keys !== $required || !preg_match('/^do_[a-f0-9]{32}$/D', $orderId)
            || !preg_match('/^dq_[a-f0-9]{32}$/D', $quoteId) || $createdAt < 1
            || $expiresAt <= $createdAt || $expiresAt - $createdAt < 1800 || $expiresAt - $createdAt > 86400
            || !in_array($locale, ['hu', 'en', 'de', 'fr'], true)
            || !is_string($snapshot['ngo_slug']) || strlen($snapshot['ngo_slug']) > 100
            || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $snapshot['ngo_slug'])
            || !is_string($snapshot['account_id']) || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $snapshot['account_id'])
            || !is_string($snapshot['binding_version']) || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $snapshot['binding_version'])
            || !is_bool($snapshot['livemode']) || !is_string($snapshot['currency'])
            || !sharity_donation_quote_minor($snapshot['amount_minor'], $snapshot['currency'])
            || (int) $snapshot['amount_minor'] > 99999999) {
            throw new RuntimeException('DONATION_INVALID_SNAPSHOT');
        }
        $mode = $snapshot['donation_mode'];
        $amount = (int) $snapshot['amount_minor'];
        if ($mode === 'direct') {
            if ($snapshot['policy_version'] !== 'direct-net100-v1' || $snapshot['votes'] !== 0
                || $snapshot['package_id'] !== null || $snapshot['price_book_version'] !== null) {
                throw new RuntimeException('DONATION_INVALID_POLICY');
            }
        } elseif ($mode === 'amplifier') {
            $package = is_string($snapshot['package_id']) ? (self::PACKAGES_V1[$snapshot['package_id']] ?? null) : null;
            if ($package === null || $snapshot['policy_version'] !== 'amplifier-net80-v1'
                || $snapshot['votes'] !== $package[1]
                || !is_string($snapshot['price_book_version'])
                || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $snapshot['price_book_version'])
                || $amount % 100 !== 0
                || ($snapshot['currency'] === 'HUF' && ($amount !== $package[0]
                    || $snapshot['price_book_version'] !== 'amplifier-net80-v1:HUF'))) {
                throw new RuntimeException('DONATION_INVALID_POLICY');
            }
        } else { throw new RuntimeException('DONATION_INVALID_POLICY'); }
        $metadata = ['order_id' => $orderId, 'quote_id' => $quoteId,
            'policy_version' => $snapshot['policy_version'], 'donation_mode' => $mode];
        $returnBase = 'https://sharity.hu/adomanyozas/' . $snapshot['ngo_slug'] . '?donation_order=' . $orderId;
        $payload = [
            'mode' => 'payment', 'ui_mode' => 'hosted_page', 'locale' => $locale,
            'adaptive_pricing' => ['enabled' => false], 'automatic_tax' => ['enabled' => false],
            'allow_promotion_codes' => false, 'integration_identifier' => 'sharity-ngo-donations-qzmxvprk',
            'client_reference_id' => $orderId, 'metadata' => $metadata,
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => strtolower($snapshot['currency']),
                'unit_amount' => $amount, 'product_data' => ['name' => $mode === 'direct' ? 'Direct donation' : 'Impact Amplifier']]]],
            // Provisional amplifier collection, NOT final net80/20 allocation.
            'payment_intent_data' => ['metadata' => $metadata, 'application_fee_amount' => $mode === 'amplifier' ? intdiv($amount, 5) : 0],
            'success_url' => $returnBase . '&checkout=returned', 'cancel_url' => $returnBase . '&checkout=cancelled',
            'expires_at' => $expiresAt,
        ];
        return ['version' => 1, 'snapshot' => $snapshot, 'order_id' => $orderId, 'quote_id' => $quoteId,
            'created_at' => $createdAt, 'expires_at' => $expiresAt, 'locale' => $locale, 'payload' => $payload,
            'options' => ['stripe_account' => $snapshot['account_id'], 'stripe_version' => self::API_VERSION,
                'max_network_retries' => 0, 'idempotency_key' => 'sharity-donation-checkout-v1:' . $orderId]];
    }

    private function validateEnvelope(array $e): void
    {
        try {
            $expected = self::envelope($e['snapshot'], $e['order_id'], $e['quote_id'], $e['created_at'], $e['expires_at'], $e['locale']);
        } catch (Throwable $error) { throw new RuntimeException('DONATION_INVALID_ENVELOPE'); }
        if ($e !== $expected) { throw new RuntimeException('DONATION_INVALID_ENVELOPE'); }
        $key = $this->stripe->getApiKey();
        if (!is_string($key) || !preg_match('/^(rk|sk)_(test|live)_[A-Za-z0-9]+$/D', $key, $parts)
            || ($parts[2] === 'live') !== $e['snapshot']['livemode']) { throw new RuntimeException('DONATION_KEY_MODE_MISMATCH'); }
    }

    /** Exactly one attempt. Every exception after dispatch is unresolved; no raw exception escapes. */
    public function createSession(array $e): array
    {
        $this->validateEnvelope($e);
        $now = ($this->now)();
        if ($e['created_at'] > $now || $e['expires_at'] < $now + 1800 || $e['expires_at'] > $now + 86400) {
            throw new RuntimeException('DONATION_SESSION_WINDOW_EXPIRED');
        }
        try {
            $session = $this->stripe->checkout->sessions->create($e['payload'], $e['options']);
            return $this->validateSession($session->toArray(), $e, null);
        } catch (Throwable $error) {
            // No key, SDK payload, provider message or previous exception chain.
            throw new RuntimeException('DONATION_CHECKOUT_UNCERTAIN');
        }
    }

    /** Historical readback deliberately ignores current activation, prices and local expiry. */
    public function retrieveSession(string $sessionId, array $e): array
    {
        $this->validateEnvelope($e);
        if (!$this->validSessionId($sessionId, $e['snapshot']['livemode'])) { throw new RuntimeException('DONATION_INVALID_SESSION_ID'); }
        $options = $e['options']; unset($options['idempotency_key']);
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent']], $options);
            return $this->validateSession($session->toArray(), $e, $sessionId);
        } catch (Throwable $error) { throw new RuntimeException('DONATION_CHECKOUT_READBACK_UNAVAILABLE'); }
    }

    private function validSessionId($id, bool $live): bool
    {
        return is_string($id) && strlen($id) <= 255
            && preg_match($live ? '/^cs_live_[A-Za-z0-9]+$/D' : '/^cs_test_[A-Za-z0-9]+$/D', $id) === 1;
    }

    private function validateSession(array $s, array $e, ?string $knownId): array
    {
        if (!$this->validSessionId($s['id'] ?? null, $e['snapshot']['livemode'])
            || ($knownId !== null && $s['id'] !== $knownId) || ($s['object'] ?? null) !== 'checkout.session'
            || ($s['livemode'] ?? null) !== $e['snapshot']['livemode'] || ($s['mode'] ?? null) !== 'payment'
            || ($s['client_reference_id'] ?? null) !== $e['order_id']
            || ($s['amount_total'] ?? null) !== (int) $e['snapshot']['amount_minor']
            || ($s['currency'] ?? null) !== strtolower($e['snapshot']['currency'])
            || ($s['expires_at'] ?? null) !== $e['expires_at']
            || !in_array($s['status'] ?? null, ['open', 'complete', 'expired'], true)
            || !in_array($s['payment_status'] ?? null, ['paid', 'unpaid', 'no_payment_required'], true)
            || !$this->matchesMetadata($s['metadata'] ?? null, $e['payload']['metadata'])) {
            throw new RuntimeException('DONATION_SESSION_MISMATCH');
        }
        $pi = $s['payment_intent'] ?? null;
        if (is_array($pi) && (!$this->matchesMetadata($pi['metadata'] ?? null, $e['payload']['metadata'])
            || ($pi['amount'] ?? null) !== (int) $e['snapshot']['amount_minor']
            || ($pi['currency'] ?? null) !== strtolower($e['snapshot']['currency'])
            || ($pi['livemode'] ?? null) !== $e['snapshot']['livemode'])) {
            throw new RuntimeException('DONATION_SESSION_MISMATCH');
        }
        $url = $s['url'] ?? null;
        if (($s['status'] === 'open' && !is_string($url)) || ($url !== null && !$this->validUrl($url))) {
            throw new RuntimeException('DONATION_SESSION_URL');
        }
        return ['session_id' => $s['id'], 'checkout_state' => $s['status'], 'checkout_url' => $s['status'] === 'open' ? $url : null,
            'expires_at' => $s['expires_at'], 'provider_payment_status' => $s['payment_status'], 'payment_verified' => false];
    }

    private function matchesMetadata($actual, array $expected): bool
    {
        if (!is_array($actual)) { return false; }
        foreach ($expected as $key => $value) { if (($actual[$key] ?? null) !== $value) { return false; } }
        return true;
    }

    private function validUrl($url): bool
    {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return false; }
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? null) === 'https' && ($p['host'] ?? null) === 'checkout.stripe.com'
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']) && str_starts_with($p['path'] ?? '', '/c/pay/');
    }
}
