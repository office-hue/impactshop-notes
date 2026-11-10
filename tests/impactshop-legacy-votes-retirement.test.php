<?php
declare(strict_types=1);
/** Runs actual modified PHP functions without bootstrapping WordPress or making economic writes. */
$checks = 0;
function retirement_check(bool $value, string $label): void { ++$GLOBALS['checks']; if (!$value) throw new RuntimeException($label); }
function load_retirement_function(string $file, string $name): void {
    $tokens = token_get_all(file_get_contents(__DIR__ . '/../wp-content/mu-plugins/' . $file));
    $capture = false; $source = ''; $depth = 0; $opened = false;
    for ($i = 0; $i < count($tokens); ++$i) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $j = $i + 1; while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) ++$j;
            if (($tokens[$j][1] ?? '') === $name) $capture = true;
        }
        if (!$capture) continue;
        $text = is_array($token) ? $token[1] : $token; $source .= $text;
        if ($text === '{') { ++$depth; $opened = true; }
        if ($text === '}' && --$depth === 0 && $opened) { eval($source); return; }
    }
    throw new RuntimeException('function_not_found_' . $name);
}
class WP_REST_Response {
    public function __construct(public array $data, public int $status = 200) {}
}
class WP_REST_Request implements ArrayAccess {
    public function __construct(private array $query = [], private array $body = [], private array $json = [], private array $headers = []) {}
    public function get_query_params(): array { return $this->query; }
    public function get_body_params(): array { return $this->body; }
    public function get_json_params(): array { return $this->json; }
    public function get_params(): array { return array_merge($this->query, $this->body, $this->json); }
    public function get_param(string $key) { return $this->get_params()[$key] ?? null; }
    public function get_header(string $key): string { return $this->headers[$key] ?? ''; }
    public function offsetExists(mixed $offset): bool { return isset($this->query[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->get_param($offset); }
    public function offsetSet(mixed $offset, mixed $value): void { throw new LogicException('no_request_write'); }
    public function offsetUnset(mixed $offset): void { throw new LogicException('no_request_write'); }
}
class Sharity_Legacy_Votes_Retirement {
    public static array $receipts = [];
    public static function blocked(): bool { return true; }
    public static function capture(...$args): WP_REST_Response { self::$receipts[] = $args; return new WP_REST_Response(['code' => 'pending_manual']); }
}
class ImpactShop_Sharity_Offerwall_V2 { public static function callback($request): WP_REST_Response { return new WP_REST_Response(['code' => 'v2_owned']); } }
define('AYET_API_KEY', 'synthetic-secret-not-production');
function impactshop_ayet_resolve_ip($request): string { return '127.0.0.1'; }
function impactshop_ayet_ip_allowed(string $ip): bool { return true; }
function impactshop_offerwall_get_providers(): array { return ['synthetic' => ['enabled' => false, 'postback_secret' => 'synthetic-secret', 'signature_param' => 'signature']]; }
foreach ([
    'impactshop-ads-watch.php' => ['impactshop_ads_watch_view', 'impactshop_ads_watch_education', 'impactshop_ads_watch_allocate_votes', 'impactshop_ads_watch_add_votes', 'impactshop_ads_watch_spend_votes', 'impactshop_ads_watch_get_user_votes'],
    'impactshop-vote-purchase.php' => ['impactshop_vote_purchase_start', 'impactshop_vote_purchase_fulfill'],
    'impactshop-offerwall-survey.php' => ['impactshop_offerwall_survey_submit'],
    'impactshop-offerwall-article-quiz.php' => ['impactshop_article_quiz_submit', 'impactshop_article_quiz_handle_rewards'],
    'impactshop-ayet-offerwall.php' => ['impactshop_ayet_callback', 'impactshop_ayet_retired_callback', 'impactshop_ayet_build_sorted_query', 'impactshop_ayet_surveys', 'impactshop_ayet_revoke_votes', 'impactshop_ayet_handle_reversal'],
    'impactshop-offerwall.php' => ['impactshop_offerwall_build_iframe_url', 'impactshop_offerwall_get_iframe_url', 'impactshop_offerwall_get_offers', 'impactshop_offerwall_handle_postback', 'impactshop_offerwall_retired_postback', 'impactshop_offerwall_signature_valid'],
] as $file => $names) foreach ($names as $name) load_retirement_function($file, $name);
$request = new WP_REST_Request();
foreach (['impactshop_ads_watch_view', 'impactshop_ads_watch_education', 'impactshop_ads_watch_allocate_votes', 'impactshop_vote_purchase_start', 'impactshop_offerwall_survey_submit', 'impactshop_article_quiz_submit', 'impactshop_offerwall_get_iframe_url', 'impactshop_offerwall_get_offers', 'impactshop_ayet_surveys'] as $name) {
    retirement_check($name($request)->status === 410, $name . ' closes before any other function');
}
retirement_check(impactshop_offerwall_build_iframe_url([], 'abcdef123456') === '', 'no new iframe');
retirement_check(!impactshop_ads_watch_spend_votes('abcdef123456', 1)['ok'], 'no old spending');
foreach ([['impactshop_ads_watch_add_votes', ['abcdef123456', 1]], ['impactshop_vote_purchase_fulfill', ['order', []]], ['impactshop_ayet_revoke_votes', ['abcdef123456', 1]], ['impactshop_ayet_handle_reversal', ['abcdef123456', 'tx', 'name']]] as [$name, $args]) {
    try { $name(...$args); throw new LogicException('write_not_closed'); } catch (RuntimeException $e) { retirement_check($e->getMessage() === 'legacy_votes_retired', $name . ' no side effects'); }
}
$wpdb = new class { public string $prefix = 'synthetic_'; public function prepare($sql, ...$args) { return $sql; } public function get_var($sql) { return null; } public function insert(...$args) { throw new LogicException('old_read_created_row'); } };
retirement_check(impactshop_ads_watch_get_user_votes('abcdef123456') === 0, 'read does not create old wallet');
impactshop_article_quiz_handle_rewards('abcdef123456', []); retirement_check(true, 'no old quiz reward hook');
retirement_check(impactshop_ayet_callback(new WP_REST_Request(['external_identifier' => 'ow2_synthetic']))->data['code'] === 'v2_owned', 'V2 prefix dispatch before retirement');
retirement_check(impactshop_ayet_callback(new WP_REST_Request(['custom_1' => 'ow2s_synthetic']))->data['code'] === 'v2_owned', 'V2 start dispatch before retirement');
$params = ['transaction_id' => 'synthetic-tx', 'external_identifier' => 'abcdef123456', 'payout_usd' => '1.00', 'currency_amount' => '50'];
$sig = hash_hmac('sha256', impactshop_ayet_build_sorted_query($params), AYET_API_KEY);
$signed = new WP_REST_Request($params, [], [], ['x-ayetstudios-security-hash' => $sig]);
retirement_check(impactshop_ayet_callback($signed)->status === 200, 'signed late AyeT stored');
retirement_check(end(Sharity_Legacy_Votes_Retirement::$receipts)[4] === 'signed_payload', 'AyeT exact fields verified');
$chargeback = $params + ['callback_type' => 'chargeback'];
$chargebackSig = hash_hmac('sha256', impactshop_ayet_build_sorted_query($chargeback), AYET_API_KEY);
retirement_check(impactshop_ayet_callback(new WP_REST_Request($chargeback, [], [], ['x-ayetstudios-security-hash' => $chargebackSig]))->status === 200, 'same transaction signed chargeback retained');
$receipt = end(Sharity_Legacy_Votes_Retirement::$receipts);
retirement_check($receipt[1] === $params['transaction_id'] && $receipt[2] === 'reversal', 'chargeback has separate event identity');
retirement_check($receipt[3]['status'] === 'chargeback', 'signed callback type preserved');
retirement_check(impactshop_ayet_callback(new WP_REST_Request($params, ['payout_usd' => '900'], [], ['x-ayetstudios-security-hash' => $sig]))->status === 400, 'no mixed AyeT body');
retirement_check(impactshop_ayet_callback(new WP_REST_Request($params))->status === 403, 'no unsigned AyeT receipt');
$generic = ['provider' => 'synthetic', 'transaction_id' => 'old-tx', 'pseudo_id' => 'abcdef123456', 'payout' => '3', 'signature' => hash_hmac('sha256', 'old-tx', 'synthetic-secret')];
retirement_check(impactshop_offerwall_handle_postback(new WP_REST_Request($generic))->status === 200, 'disabled provider authenticated late event retained');
retirement_check(end(Sharity_Legacy_Votes_Retirement::$receipts)[4] === 'transaction_reference_only', 'partial signature not full entitlement proof');
retirement_check(impactshop_offerwall_handle_postback(new WP_REST_Request($generic, ['payout' => '4']))->status === 400, 'generic conflicting maps rejected');
unset($generic['signature']); retirement_check(impactshop_offerwall_handle_postback(new WP_REST_Request($generic))->status === 403, 'no permissive missing signature');
$stripe = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-vote-purchase.php');
$webhook = substr($stripe, strpos($stripe, 'function impactshop_vote_purchase_webhook()'), strpos($stripe, 'function impactshop_vote_purchase_verify_stripe_signature') - strpos($stripe, 'function impactshop_vote_purchase_webhook()'));
retirement_check(strpos($webhook, 'return Sharity_Legacy_Votes_Retirement::capture') < strpos($webhook, 'impactshop_vote_purchase_fulfill('), 'Stripe durable response precedes unconditional ACK');
echo "impactshop-legacy-retirement: PASS ({$checks} actual-function checks; no provider or DB writes)\n";
