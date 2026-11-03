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

    /** One exact connected-charge GET to resolve a signed refund/dispute notification.
     * Caller verifies signature and applies the one-request timeout wrapper first.
     * No customer details or arbitrary metadata escape the adapter. */
    public function notificationCharge(string $id, string $account, bool $live): array
    {
        try {
            if (!$this->evidenceId($id, 'ch') || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $account)
                || $this->stripe->getStripeAccount() !== null
                || !preg_match('/^(rk|sk)_(test|live)_[A-Za-z0-9]+$/D', $this->stripe->getApiKey(), $mode)
                || ($mode[2] === 'live') !== $live) { throw new RuntimeException(); }
            $c = $this->stripe->charges->retrieve($id, [], ['stripe_account'=>$account,
                'stripe_version'=>self::API_VERSION, 'max_network_retries'=>0])->toArray();
            if (($c['object'] ?? null) !== 'charge' || ($c['id'] ?? null) !== $id
                || ($c['livemode'] ?? null) !== $live) { throw new RuntimeException(); }
            $out = array_intersect_key($c, array_flip(['id','object','livemode','payment_intent','amount','currency']));
            $out['metadata'] = is_array($c['metadata'] ?? null)
                ? array_intersect_key($c['metadata'], array_flip(['order_id','quote_id','policy_version','donation_mode','package_id'])) : [];
            return $out;
        } catch (Throwable $error) { throw new RuntimeException('DONATION_NOTIFICATION_UNAVAILABLE'); }
    }

    /** Platform-authenticated GETs only. No identity/contact/bank data leaves this adapter. */
    public function inspectAccount(string $accountId): array
    {
        try {
            $key = $this->stripe->getApiKey();
            if (!preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $accountId)
                || $this->stripe->getStripeAccount() !== null || $this->stripe->getStripeContext() !== null
                || !is_string($key) || !preg_match('/^(rk|sk)_(test|live)_[A-Za-z0-9]+$/D', $key, $mode)) { throw new RuntimeException(); }
            $live = $mode[2] === 'live';
            $opts = ['stripe_version' => self::API_VERSION, 'max_network_retries' => 0];
            $platform = $this->stripe->accounts->retrieve(null, [], $opts)->toArray();
            if (($platform['object'] ?? null) !== 'account' || !is_string($platform['id'] ?? null)
                || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $platform['id']) || $platform['id'] === $accountId
                || (array_key_exists('livemode', $platform) && $platform['livemode'] !== $live)) { throw new RuntimeException(); }
            $a = $this->stripe->accounts->retrieve($accountId, [], $opts)->toArray();
            if (($a['object'] ?? null) !== 'account' || ($a['id'] ?? null) !== $accountId
                || (array_key_exists('livemode', $a) && $a['livemode'] !== $live)) { throw new RuntimeException(); }
            $r = is_array($a['requirements'] ?? null) ? $a['requirements'] : [];
            $counts = [];
            foreach (['currently_due', 'past_due', 'pending_verification', 'errors'] as $field) {
                $counts[$field] = is_array($r[$field] ?? null) && array_is_list($r[$field]) ? count($r[$field]) : null;
            }
            $disabled = array_key_exists('disabled_reason', $r) ? ($r['disabled_reason'] === null ? false : true) : null;
            $caps = [];
            foreach (['card_payments', 'transfers'] as $name) {
                $value = $a['capabilities'][$name] ?? null;
                $caps[$name] = in_array($value, ['active', 'inactive', 'pending'], true) ? $value : null;
            }
            $payer = $a['controller']['fees']['payer'] ?? null;
            $losses = $a['controller']['losses']['payments'] ?? null;
            $payer = in_array($payer, ['account', 'application', 'application_express', 'application_custom'], true) ? $payer : null;
            $losses = in_array($losses, ['stripe', 'application'], true) ? $losses : null;
            $flags = [];
            foreach (['charges_enabled', 'payouts_enabled', 'details_submitted'] as $field) {
                $flags[$field] = is_bool($a[$field] ?? null) ? $a[$field] : null;
            }
            $currency = is_string($a['default_currency'] ?? null) && preg_match('/^[a-z]{3}$/D', $a['default_currency'])
                ? strtoupper($a['default_currency']) : null;
            return ['schema_version' => 'sharity-donation-account-inspect-v1', 'platform_account_id' => $platform['id'],
                'account_id' => $accountId, 'livemode' => $live, 'observed_at_utc' => gmdate('Y-m-d\TH:i:s\Z', ($this->now)()),
                'capabilities' => $caps, 'controller' => ['fees_payer' => $payer, 'losses_payments' => $losses],
                'flags' => $flags, 'requirements' => $counts + ['disabled' => $disabled], 'default_currency' => $currency,
                'activation_allowed' => false];
        } catch (Throwable $error) { throw new RuntimeException('DONATION_ACCOUNT_INSPECT_UNAVAILABLE'); }
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

    /** Original charge evidence only, never a final ledger/allocation or payout authorization.
     * At most six GETs (the extra platform identity read is Amplifier-only); no provider writes/retries. Caller must be outside a DB transaction.
     * Historical readback ignores current activation/quote expiry and follows provider links.
     */
    public function retrievePaymentEvidence(string $sessionId, array $e): array
    {
        $this->validateEnvelope($e);
        if (!$this->validSessionId($sessionId, $e['snapshot']['livemode'])) { throw new RuntimeException('DONATION_INVALID_SESSION_ID'); }
        // Removing a per-request header alone would inherit a client-level account.
        if ($this->stripe->getStripeAccount() !== null || $this->stripe->getStripeContext() !== null) { throw new RuntimeException('DONATION_EVIDENCE_UNSAFE_CONFIG'); }
        $options = $e['options']; unset($options['idempotency_key']);
        $platform = $options; unset($platform['stripe_account']);
        try {
            $s = $this->stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent']], $options)->toArray();
            $session = $this->validateSession($s, $e, $sessionId);
            $result = ['evidence_version' => 1, 'scope' => 'original_charge_only', 'order_id' => $e['order_id'],
                'session_id' => $sessionId, 'account_id' => $e['snapshot']['account_id'], 'livemode' => $e['snapshot']['livemode'],
                'donation_mode' => $e['snapshot']['donation_mode'], 'payment_verified' => false,
                'allocation_eligible' => false, 'evidence_state' => 'payment_pending',
                'payment' => ['amount_minor' => $e['snapshot']['amount_minor'], 'currency' => $e['snapshot']['currency']],
                'checkout_state' => $session['checkout_state'], 'provider_payment_status' => $session['provider_payment_status']];
            if ($session['checkout_state'] !== 'complete' || $session['provider_payment_status'] !== 'paid') { return $result; }
            $pi = $s['payment_intent'] ?? null;
            if (!is_array($pi) || ($pi['object'] ?? null) !== 'payment_intent'
                || !$this->evidenceId($pi['id'] ?? null, 'pi') || ($pi['status'] ?? null) !== 'succeeded'
                || ($pi['amount_received'] ?? null) !== (int) $e['snapshot']['amount_minor']
                || !array_key_exists('transfer_data', $pi) || $pi['transfer_data'] !== null) { throw new RuntimeException(); }
            $expectedFee = $e['payload']['payment_intent_data']['application_fee_amount'];
            if (!array_key_exists('application_fee_amount', $pi)
                || ($pi['application_fee_amount'] !== $expectedFee && !($expectedFee === 0 && $pi['application_fee_amount'] === null))) { throw new RuntimeException(); }
            $chargeId = $pi['latest_charge'] ?? null;
            if (!$this->evidenceId($chargeId, 'ch')) { throw new RuntimeException(); }
            $charge = $this->stripe->charges->retrieve($chargeId, ['expand' => ['refunds']], $options)->toArray();
            $gross = (int) $e['snapshot']['amount_minor'];
            if (($charge['object'] ?? null) !== 'charge' || ($charge['id'] ?? null) !== $chargeId
                || ($charge['payment_intent'] ?? null) !== $pi['id'] || ($charge['livemode'] ?? null) !== $e['snapshot']['livemode']
                || ($charge['status'] ?? null) !== 'succeeded' || ($charge['paid'] ?? null) !== true || ($charge['captured'] ?? null) !== true
                || ($charge['amount'] ?? null) !== $gross || ($charge['amount_captured'] ?? null) !== $gross
                || ($charge['currency'] ?? null) !== strtolower($e['snapshot']['currency'])
                || !$this->matchesMetadata($charge['metadata'] ?? null, $e['payload']['metadata'])
                || !is_bool($charge['disputed'] ?? null) || !array_key_exists('failure_balance_transaction', $charge)
                || ($charge['failure_balance_transaction'] !== null && !$this->evidenceId($charge['failure_balance_transaction'], 'txn'))
                || !array_key_exists('application_fee', $charge) || !array_key_exists('application_fee_amount', $charge)
                || ($charge['application_fee_amount'] !== $expectedFee && !($expectedFee === 0 && $charge['application_fee_amount'] === null))) { throw new RuntimeException(); }
            $chargeRefunds = $this->evidenceRefunds($charge, $gross, 'refund', 're');
            $btId = $charge['balance_transaction'] ?? null;
            if (!$this->evidenceId($btId, 'txn')) { throw new RuntimeException(); }
            $bt = $this->evidenceBalance($this->stripe->balanceTransactions->retrieve($btId, [], $options)->toArray(), $btId, $chargeId, ['charge', 'payment']);
            $this->evidenceConversion($bt, $e['snapshot']['currency'], $gross);
            $af = null;
            $pending = $bt['status'] === 'pending';
            $adjustments = $chargeRefunds['adjustments_required'] || $charge['disputed'] || $charge['failure_balance_transaction'] !== null;
            $crossCurrency = $bt['currency'] !== $e['snapshot']['currency'];
            if ($expectedFee === 0) {
                if ($charge['application_fee'] !== null || $bt['application_fee_minor'] !== '0') { throw new RuntimeException(); }
            } else {
                // fee.account identifies the NGO. The credential's own account owns the platform legs.
                $owner = $this->stripe->accounts->retrieve(null, [], $platform)->toArray();
                if (($owner['object'] ?? null) !== 'account' || !is_string($owner['id'] ?? null)
                    || !preg_match('/^acct_[A-Za-z0-9]{1,123}$/D', $owner['id']) || $owner['id'] === $e['snapshot']['account_id']
                    || (array_key_exists('livemode', $owner) && $owner['livemode'] !== $e['snapshot']['livemode'])) { throw new RuntimeException(); }
                $result['platform_account_id'] = $owner['id'];
                $afId = $charge['application_fee'];
                if (!$this->evidenceId($afId, 'fee')) { throw new RuntimeException(); }
                $fee = $this->stripe->applicationFees->retrieve($afId, [], $platform)->toArray();
                if (($fee['object'] ?? null) !== 'application_fee' || ($fee['id'] ?? null) !== $afId
                    || ($fee['account'] ?? null) !== $e['snapshot']['account_id'] || ($fee['charge'] ?? null) !== $chargeId
                    || ($fee['livemode'] ?? null) !== $e['snapshot']['livemode']) { throw new RuntimeException(); }
                $feeAmount = $this->evidenceMoney($fee['amount'] ?? null, 1);
                $feeCurrency = $this->evidenceCurrency($fee['currency'] ?? null);
                $feeRefunds = $this->evidenceRefunds($fee, $feeAmount, 'fee_refund', 'fr');
                // Compare only within one currency. No MNB/float conversion of actual money.
                if (($feeCurrency === $e['snapshot']['currency'] && $feeAmount !== $expectedFee)
                    || ($feeCurrency === $bt['currency'] && (string) $feeAmount !== $bt['application_fee_minor'])
                    || $bt['application_fee_minor'] === '0') { throw new RuntimeException(); }
                $feeBtId = $fee['balance_transaction'] ?? null;
                if (!$this->evidenceId($feeBtId, 'txn')) { throw new RuntimeException(); }
                $feeBt = $this->evidenceBalance($this->stripe->balanceTransactions->retrieve($feeBtId, [], $platform)->toArray(), $feeBtId, $afId, ['application_fee']);
                $this->evidenceConversion($feeBt, $feeCurrency, $feeAmount);
                if ($feeBt['application_fee_minor'] !== '0') { throw new RuntimeException(); }
                $af = ['id' => $afId, 'amount_minor' => (string) $feeAmount, 'currency' => $feeCurrency,
                    'refund_state' => $feeRefunds, 'initial_balance' => $feeBt];
                $pending = $pending || $feeBt['status'] === 'pending';
                $adjustments = $adjustments || $feeRefunds['adjustments_required'];
                $crossCurrency = $crossCurrency || $feeCurrency !== $bt['currency'] || $feeBt['currency'] !== $feeCurrency;
            }
            return array_replace($result, ['evidence_state' => 'original_charge_observed',
                'payment_intent_id' => $pi['id'], 'charge_id' => $chargeId,
                'initial_charge_balance' => $bt, 'application_fee' => $af,
                'charge_refund_state' => $chargeRefunds, 'disputed' => $charge['disputed'],
                'failure_balance_transaction_id' => $charge['failure_balance_transaction'],
                'adjustments_required' => $adjustments, 'balances_pending' => $pending, 'cross_currency' => $crossCurrency]);
        } catch (Throwable $error) { throw new RuntimeException('DONATION_EVIDENCE_UNAVAILABLE'); }
    }

    /** Complete bounded resource lists, NOT an atomic snapshot or settlement authorization.
     * At most62 GETs (two original reads, three ten-page collections, up to20 exact-source credits).
     * All external calls remain outside any caller-owned DB transaction.
     */
    public function retrieveAdjustmentEvidence(string $sessionId, array $e): array
    {
        $original = $this->retrievePaymentEvidence($sessionId, $e);
        $result = ['evidence_version' => 1, 'scope' => 'observed_adjustment_resources',
            'original' => $original, 'payment_verified' => false, 'allocation_eligible' => false,
            'atomic_snapshot' => false, 'evidence_state' => 'payment_pending', 'resources_complete' => false,
            'settlement_reconciled' => false, 'refunds' => [], 'disputes' => [], 'fee_refunds' => [],
            'unresolved' => [], 'balances_pending' => false];
        if ($original['evidence_state'] !== 'original_charge_observed') { return $result; }
        $options = $e['options']; unset($options['idempotency_key']);
        $platform = $options; unset($platform['stripe_account']);
        try {
            $chargeId = $original['charge_id']; $piId = $original['payment_intent_id'];
            $refunds = $this->adjustmentList(fn($p) => $this->stripe->refunds->all($p, $options),
                ['charge' => $chargeId, 'expand' => ['data.balance_transaction', 'data.failure_balance_transaction']], 'refund', ['re']);
            // Always list disputes: the current charge flag is not dispute history.
            $disputes = $this->adjustmentList(fn($p) => $this->stripe->disputes->all($p, $options), ['charge' => $chargeId], 'dispute', ['dp', 'du']);
            $fee = $original['application_fee'];
            $feeRefunds = $fee === null ? [] : $this->adjustmentList(
                fn($p) => $this->stripe->applicationFees->allRefunds($fee['id'], $p, $platform),
                ['expand' => ['data.balance_transaction']], 'fee_refund', ['fr']);
            foreach ([[$original['charge_refund_state'], count($refunds)], [$fee['refund_state'] ?? null, count($feeRefunds)]] as [$inline, $count]) {
                if ($inline !== null && ($count < $inline['listed_count']
                    || (!$inline['has_more'] && $count !== $inline['listed_count'])
                    || ($inline['has_more'] && $count <= $inline['listed_count']))) { throw new RuntimeException(); }
            }
            $seen = ['connected:' . $original['initial_charge_balance']['id'] => true];
            if ($fee !== null) { $seen['platform:' . $fee['initial_balance']['id']] = true; }
            $pending = $original['balances_pending']; $unresolved = [];
            $successfulRefunds = 0; $nonfailedRefunds = 0;
            foreach ($refunds as $r) {
                $amount = $this->evidenceMoney($r['amount'] ?? null, 1);
                $currency = $this->evidenceCurrency($r['currency'] ?? null);
                $status = $r['status'] ?? null;
                if (($r['charge'] ?? null) !== $chargeId || !array_key_exists('payment_intent', $r)
                    || ($r['payment_intent'] !== null && $r['payment_intent'] !== $piId)
                    || $currency !== $original['payment']['currency'] || $amount > (int) $original['payment']['amount_minor']
                    || !in_array($status, ['pending', 'requires_action', 'succeeded', 'failed', 'canceled'], true)) { throw new RuntimeException(); }
                $entry = ['id' => $r['id'], 'amount_minor' => (string) $amount, 'currency' => $currency,
                    'created' => $this->adjustmentCreated($r), 'status' => $status];
                $entry['balance'] = $this->adjustmentBalance($r, 'balance_transaction', $r['id'], 'connected',
                    ['refund', 'payment_refund'], $seen, $pending, $unresolved);
                $entry['failure_balance'] = $this->adjustmentBalance($r, 'failure_balance_transaction', $r['id'], 'connected',
                    ['refund_failure', 'payment_failure_refund', 'payment_refund', 'adjustment'], $seen, $pending, $unresolved);
                if ($entry['balance'] !== null) {
                    if ((int) $entry['balance']['gross_minor'] >= 0) { throw new RuntimeException(); }
                    $this->evidenceConversion($entry['balance'], $currency, -$amount);
                }
                if ($entry['failure_balance'] !== null) {
                    if ($entry['balance'] === null || !in_array($status, ['failed', 'canceled'], true)) { throw new RuntimeException(); }
                    if ((int) $entry['failure_balance']['gross_minor'] <= 0) { throw new RuntimeException(); }
                    $this->evidenceConversion($entry['failure_balance'], $currency, $amount);
                }
                if ($status === 'succeeded') {
                    $successfulRefunds += $amount;
                    if ($entry['balance'] === null) { $unresolved[] = 'refund_balance_missing'; }
                }
                if (!in_array($status, ['failed', 'canceled'], true)) { $nonfailedRefunds += $amount; }
                if (in_array($status, ['pending', 'requires_action'], true)) { $unresolved[] = 'refund_unresolved'; }
                if (in_array($status, ['failed', 'canceled'], true) && $entry['balance'] !== null && $entry['failure_balance'] === null) {
                    $unresolved[] = 'refund_reversal_missing';
                }
                $result['refunds'][] = $entry;
            }
            // Stripe aggregates can include in-progress refunds. Neither total is silently selected as final.
            $aggregate = (int) $original['charge_refund_state']['amount_refunded_minor'];
            if ($aggregate < $successfulRefunds || $aggregate > $nonfailedRefunds) { throw new RuntimeException(); }
            if ($aggregate !== $successfulRefunds) { $unresolved[] = 'refund_aggregate_pending'; }
            foreach ($disputes as $d) {
                $status = $d['status'] ?? null;
                if (($d['charge'] ?? null) !== $chargeId || ($d['livemode'] ?? null) !== $e['snapshot']['livemode']
                    || !array_key_exists('payment_intent', $d) || ($d['payment_intent'] !== null && $d['payment_intent'] !== $piId)
                    || !in_array($status, ['warning_needs_response', 'warning_under_review', 'warning_closed', 'needs_response', 'under_review', 'won', 'lost', 'appeal_needs_response', 'appeal_under_review', 'prevented'], true)
                    || !is_array($d['balance_transactions'] ?? null) || !array_is_list($d['balance_transactions'])
                    || count($d['balance_transactions']) > 2) { throw new RuntimeException(); }
                $entry = ['id' => $d['id'], 'amount_minor' => (string) $this->evidenceMoney($d['amount'] ?? null, 1),
                    'currency' => $this->evidenceCurrency($d['currency'] ?? null), 'created' => $this->adjustmentCreated($d),
                    'status' => $status, 'balances' => []];
                foreach ($d['balance_transactions'] as $b) {
                    $balance = $this->adjustmentBalance(['balance' => $b], 'balance', $d['id'], 'connected',
                        ['adjustment'], $seen, $pending, $unresolved);
                    if ($balance === null) { $unresolved[] = 'dispute_balance_missing'; }
                    else { $entry['balances'][] = $balance; }
                }
                // The disputed nominal amount is not a balance movement, even when currencies coincide.
                if (!in_array($status, ['warning_closed', 'won', 'lost', 'prevented'], true)) { $unresolved[] = 'dispute_open'; }
                if (in_array($status, ['won', 'lost'], true) && $entry['balances'] === []) { $unresolved[] = 'dispute_balance_missing'; }
                $result['disputes'][] = $entry;
            }
            if ($original['disputed'] && $disputes === []) { throw new RuntimeException(); }
            $feeRefunded = 0;
            $counterlegBudget = count($feeRefunds) <= 20;
            foreach ($feeRefunds as $r) {
                $amount = $this->evidenceMoney($r['amount'] ?? null, 1);
                $currency = $this->evidenceCurrency($r['currency'] ?? null);
                if (($r['fee'] ?? null) !== $fee['id'] || $currency !== $fee['currency']) { throw new RuntimeException(); }
                $feeRefunded += $amount;
                $entry = ['id' => $r['id'], 'amount_minor' => (string) $amount, 'currency' => $currency,
                    'created' => $this->adjustmentCreated($r), 'fee_id' => $fee['id']];
                $entry['balance'] = $this->adjustmentBalance($r, 'balance_transaction', $r['id'], 'platform',
                    ['application_fee_refund'], $seen, $pending, $unresolved);
                if ($entry['balance'] === null) { $unresolved[] = 'fee_refund_balance_missing'; }
                else { if ((int) $entry['balance']['gross_minor'] >= 0) { throw new RuntimeException(); }
                    $this->evidenceConversion($entry['balance'], $currency, -$amount); }
                // A platform debit is not proof of NGO credit. Source identity is mandatory.
                $credit = $counterlegBudget ? $this->feeRefundConnectedCredit($r['id'], $amount, $currency,
                    $options, $seen, $pending, $unresolved) : null;
                $entry['connected_credit'] = $credit;
                $entry['connected_credit_verified'] = $credit !== null;
                if (!$counterlegBudget) { $unresolved[] = 'fee_refund_counterleg_read_limit'; }
                if ($credit === null) { $unresolved[] = 'fee_refund_connected_credit_unreconciled'; }
                $result['fee_refunds'][] = $entry;
            }
            if ($fee !== null && (string) $feeRefunded !== $fee['refund_state']['amount_refunded_minor']) { throw new RuntimeException(); }
            if ($original['failure_balance_transaction_id'] !== null) { $unresolved[] = 'charge_failure_requires_reconciliation'; }
            // Detect changes visible in the original aggregate while the lists were fetched.
            // This is NOT a provider atomic snapshot and does not exclude later or unobserved events.
            if ($this->retrievePaymentEvidence($sessionId, $e) !== $original) { throw new RuntimeException(); }
            $unresolved = array_values(array_unique($unresolved)); sort($unresolved);
            return array_replace($result, ['evidence_state' => 'adjustment_resources_observed', 'resources_complete' => true,
                'balances_pending' => $pending, 'unresolved' => $unresolved]);
        } catch (Throwable $error) { throw new RuntimeException('DONATION_ADJUSTMENT_EVIDENCE_UNAVAILABLE'); }
    }

    /** Exact-source conditional proof, not a promise that Stripe exposes every counterleg.
     * One GET per refund (up to20 per observation), no pagination or fallback lookup.
     */
    private function feeRefundConnectedCredit(string $source, int $amount, string $currency,
        array $options, array &$seen, bool &$pending, array &$unresolved): ?array
    {
        $list = $this->stripe->balanceTransactions->all(['source' => $source, 'limit' => 2], $options)->toArray();
        if (($list['object'] ?? null) !== 'list' || !is_bool($list['has_more'] ?? null)
            || !is_array($list['data'] ?? null) || !array_is_list($list['data']) || count($list['data']) > 2) { throw new RuntimeException(); }
        // Missing/ambiguous mapping is unresolved; no inferred zero or selected first row.
        if ($list['has_more'] || count($list['data']) !== 1) { return null; }
        $b = $list['data'][0];
        if (!is_array($b) || !in_array($b['type'] ?? null, ['application_fee_refund', 'adjustment'], true)) { return null; }
        $credit = $this->adjustmentBalance(['credit' => $b], 'credit', $source, 'connected',
            ['application_fee_refund', 'adjustment'], $seen, $pending, $unresolved);
        if ($credit === null || (int) $credit['gross_minor'] <= 0 || (int) $credit['application_fee_minor'] !== 0) { throw new RuntimeException(); }
        $this->evidenceConversion($credit, $currency, $amount);
        return $credit + ['account_id' => $options['stripe_account']];
    }

    private function adjustmentList(callable $fetch, array $params, string $object, array $prefixes): array
    {
        $all = []; $seen = [];
        for ($page = 0; $page < 10; $page++) {
            $list = $fetch($params + ['limit' => 100])->toArray();
            if (($list['object'] ?? null) !== 'list' || !is_bool($list['has_more'] ?? null)
                || !is_array($list['data'] ?? null) || !array_is_list($list['data']) || count($list['data']) > 100
                || ($list['has_more'] && $list['data'] === [])) { throw new RuntimeException(); }
            foreach ($list['data'] as $row) {
                if (!is_array($row) || ($row['object'] ?? null) !== $object) { throw new RuntimeException(); }
                $valid = false;
                foreach ($prefixes as $prefix) { $valid = $valid || $this->evidenceId($row['id'] ?? null, $prefix); }
                if (!$valid || isset($seen[$row['id']])) { throw new RuntimeException(); }
                $seen[$row['id']] = true; $all[] = $row;
            }
            if (!$list['has_more']) { return $all; }
            $params['starting_after'] = $list['data'][count($list['data']) - 1]['id'];
        }
        throw new RuntimeException();
    }

    private function adjustmentCreated(array $row): int
    {
        if (!is_int($row['created'] ?? null) || $row['created'] < 1) { throw new RuntimeException(); }
        return $row['created'];
    }

    private function adjustmentBalance(array $row, string $field, string $source, string $scope, array $types,
        array &$seen, bool &$pending, array &$unresolved): ?array
    {
        if (!array_key_exists($field, $row)) { $unresolved[] = 'balance_field_missing'; return null; }
        $b = $row[$field];
        if ($b === null) { return null; }
        // Expanded balances are requested explicitly. No unbounded per-row fetch fallback.
        if (is_string($b) && $this->evidenceId($b, 'txn')) { $unresolved[] = 'balance_not_expanded'; return null; }
        if (!is_array($b) || !$this->evidenceId($b['id'] ?? null, 'txn') || isset($seen[$scope . ':' . $b['id']])) { throw new RuntimeException(); }
        $balance = $this->evidenceBalance($b, $b['id'], $source, $types, true);
        $seen[$scope . ':' . $b['id']] = true; $pending = $pending || $balance['status'] === 'pending';
        return $balance + ['scope' => $scope, 'source_id' => $source, 'type' => $b['type']];
    }

    private function evidenceId($id, string $prefix): bool
    {
        // Except Checkout IDs, Stripe resource IDs do not encode test/live mode.
        return is_string($id) && strlen($id) <= 255 && preg_match('/^' . $prefix . '_[A-Za-z0-9]+$/D', $id) === 1;
    }

    private function evidenceMoney($value, int $minimum = 0): int
    {
        if (!is_int($value) || $value < $minimum || $value > 999999999999) { throw new RuntimeException(); }
        return $value;
    }

    private function evidenceCurrency($value): string
    {
        if (!in_array($value, ['huf', 'eur', 'usd', 'gbp'], true)) { throw new RuntimeException(); }
        return strtoupper($value);
    }

    /** Paginated lists signal adjustments even when aggregates lag. Never infer missing = empty. */
    private function evidenceRefunds(array $resource, int $amount, string $object, string $prefix): array
    {
        $refunded = $this->evidenceMoney($resource['amount_refunded'] ?? null);
        $list = $resource['refunds'] ?? null;
        if ($refunded > $amount || !is_bool($resource['refunded'] ?? null) || $resource['refunded'] !== ($refunded === $amount)
            || !is_array($list) || ($list['object'] ?? null) !== 'list' || !is_bool($list['has_more'] ?? null)
            || !is_array($list['data'] ?? null) || !array_is_list($list['data'])) { throw new RuntimeException(); }
        foreach ($list['data'] as $refund) {
            if (!is_array($refund) || ($refund['object'] ?? null) !== $object || !$this->evidenceId($refund['id'] ?? null, $prefix)) { throw new RuntimeException(); }
        }
        return ['amount_refunded_minor' => (string) $refunded, 'fully_refunded' => $resource['refunded'],
            'listed_count' => count($list['data']), 'has_more' => $list['has_more'],
            'adjustments_required' => $refunded > 0 || $list['data'] !== [] || $list['has_more']];
    }

    /** Project only arithmetic facts; discard descriptions, customer/card data and raw payloads. */
    private function evidenceBalance(array $b, string $id, string $source, array $types, bool $signed = false): array
    {
        $amount = $this->evidenceMoney($b['amount'] ?? null, $signed ? -999999999999 : 1);
        $fee = $this->evidenceMoney($b['fee'] ?? null, $signed ? -999999999999 : 0);
        $net = $this->evidenceMoney($b['net'] ?? null, -999999999999);
        $currency = $this->evidenceCurrency($b['currency'] ?? null);
        if (($b['object'] ?? null) !== 'balance_transaction' || ($b['id'] ?? null) !== $id || ($b['source'] ?? null) !== $source
            || !in_array($b['type'] ?? null, $types, true) || $net !== $amount - $fee
            || !in_array($b['status'] ?? null, ['available', 'pending'], true)
            || !is_int($b['created'] ?? null) || $b['created'] < 1 || !is_int($b['available_on'] ?? null) || $b['available_on'] < 1
            || !array_key_exists('exchange_rate', $b) || !is_array($b['fee_details'] ?? null) || !array_is_list($b['fee_details'])) { throw new RuntimeException(); }
        $rate = $b['exchange_rate'];
        if ($rate !== null && ((!is_int($rate) && !is_float($rate)) || !is_finite((float) $rate) || $rate <= 0)) { throw new RuntimeException(); }
        $sum = 0; $applicationFee = 0; $details = [];
        foreach ($b['fee_details'] as $d) {
            if (!is_array($d) || ($d['currency'] ?? null) !== strtolower($currency) || !is_string($d['type'] ?? null)
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $d['type'])) { throw new RuntimeException(); }
            $value = $this->evidenceMoney($d['amount'] ?? null, $signed ? -999999999999 : 0);
            $sum += $value;
            if (!$signed && $sum > $fee) { throw new RuntimeException(); }
            if ($d['type'] === 'application_fee') { $applicationFee += $value; }
            $details[] = ['amount_minor' => (string) $value, 'currency' => $currency, 'type' => $d['type']];
        }
        if ($sum !== $fee) { throw new RuntimeException(); }
        return ['id' => $id, 'currency' => $currency, 'gross_minor' => (string) $amount, 'total_fee_minor' => (string) $fee,
            'net_minor' => (string) $net, 'application_fee_minor' => (string) $applicationFee,
            'processing_cost_minor' => (string) ($fee - $applicationFee), 'fee_details' => $details,
            'status' => $b['status'], 'created' => $b['created'], 'available_on' => $b['available_on'],
            // Informational provider number only: never used in money arithmetic.
            'exchange_rate' => $rate];
    }

    private function evidenceConversion(array $balance, string $sourceCurrency, int $sourceAmount): void
    {
        if ($balance['currency'] === $sourceCurrency) {
            if ($balance['gross_minor'] !== (string) $sourceAmount || ($balance['exchange_rate'] !== null && (float) $balance['exchange_rate'] !== 1.0)) { throw new RuntimeException(); }
        } elseif ($balance['exchange_rate'] === null) { throw new RuntimeException(); }
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
