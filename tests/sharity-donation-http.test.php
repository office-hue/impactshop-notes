<?php
declare(strict_types=1);
// Real HTTP handlers, isolated auth/store boundaries; no DB or Stripe calls.
const WP_CLI = true;
const SHARITY_DONATION_ORDERS_ENABLED = 'sharity_donation_orders_enabled';
const SHARITY_DONATION_ORDERS_SCHEMA_OPTION = 'sharity_donation_orders_schema_v1';
$checks = 0; $hooks = []; $routes = []; $options = []; $calls = [];
function check(bool $v, string $why): void { $GLOBALS['checks']++; if (!$v) { throw new RuntimeException($why); } }
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $body, public int $status = 200) {}
    public function header(string $k, string $v): void { $this->headers[$k] = $v; }
}
class WP_REST_Request {
    public array $headers = ['content-type' => 'application/json; charset=utf-8', 'idempotency-key' => 'synthetic-checkout-123'];
    public array $url = []; public array $query = [];
    public function __construct(public mixed $json = null) {}
    public function get_json_params(): mixed { return $this->json; }
    public function get_header($k): string { return $this->headers[$k] ?? ''; }
    public function is_json_content_type(): bool { return str_starts_with($this->get_header('content-type'), 'application/json'); }
    public function get_url_params(): array { return $this->url; }
}
class WP_CLI {
    public static array $commands = []; public static array $messages = [];
    public static function add_command($k, $cb): void { self::$commands[$k] = $cb; }
    public static function error($s): void { self::$messages[] = ['error', $s]; }
    public static function success($s): void { self::$messages[] = ['success', $s]; }
}
class ImpactShop_Sharity_Web_Session {
    public static mixed $answer;
    public static function authenticate_request($r): mixed {
        $GLOBALS['calls'][] = ['auth'];
        if (self::$answer instanceof Throwable) { throw self::$answer; }
        return self::$answer;
    }
}
function add_action($k, $cb, ...$args): void { $GLOBALS['hooks'][$k][] = $cb; }
function register_rest_route($ns, $path, $args): void { $GLOBALS['routes'][$ns . $path] = $args; }
function get_option($k, $default = false): mixed { return $GLOBALS['options'][$k] ?? $default; }
function resultFixture(string $state = 'session_recorded'): array {
    return ['id' => 'do_' . str_repeat('b', 32), 'ngo_slug' => 'test-ngo', 'donation_mode' => 'direct',
        'currency' => 'HUF', 'amount_minor' => '50000', 'votes' => 0, 'policy_version' => 'direct-net100-v1',
        'state' => $state, 'checkout_state' => 'open', 'checkout_url' => 'https://checkout.stripe.com/c/pay/synthetic', 'payment_verified' => false];
}
function sharity_donation_order_prepare($subject, $quote, $key, $locale, $now): array {
    $GLOBALS['calls'][] = ['prepare', $subject, $quote, $key, $locale];
    if ($GLOBALS['storeError'] !== null) { throw $GLOBALS['storeError']; }
    return ['order_id' => 'do_' . str_repeat('b', 32), 'state' => $GLOBALS['rowState'], 'envelope_json' => 'PRIVATE_SYNTHETIC_DATA'];
}
function sharity_donation_runtime_adapter(): object {
    $GLOBALS['calls'][] = ['factory'];
    if ($GLOBALS['factoryError']) { throw new RuntimeException('SYNTHETIC_PRIVATE_KEY_OR_PATH'); }
    return new stdClass();
}
function sharity_donation_order_dispatch($subject, $id, $adapter, $now): array {
    $GLOBALS['calls'][] = ['dispatch', $subject, $id];
    return resultFixture($GLOBALS['dispatchState']);
}
function sharity_donation_order_status($subject, $id, $now): array {
    $GLOBALS['calls'][] = ['status', $subject, $id];
    if ($GLOBALS['storeError'] !== null) { throw $GLOBALS['storeError']; }
    return resultFixture($GLOBALS['rowState'] === 'attempted' ? 'reconciliation_required' : $GLOBALS['rowState']);
}
function sharity_donation_order_schema_apply(): bool { $GLOBALS['calls'][] = ['schema']; return $GLOBALS['schemaOk']; }
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/http.php';
function resetCase(): void {
    $GLOBALS['calls'] = []; $GLOBALS['options'] = [SHARITY_DONATION_ORDERS_ENABLED => '1', SHARITY_DONATION_ORDERS_SCHEMA_OPTION => '1'];
    $GLOBALS['storeError'] = null; $GLOBALS['factoryError'] = false; $GLOBALS['rowState'] = 'prepared';
    $GLOBALS['dispatchState'] = 'session_recorded'; $GLOBALS['schemaOk'] = true;
    ImpactShop_Sharity_Web_Session::$answer = ['subject_ref' => 'hmac-sha256:' . str_repeat('a', 64)];
}
function request(): WP_REST_Request { return new WP_REST_Request(['quote_id' => 'dq_' . str_repeat('c', 32), 'locale' => 'hu']); }
function expectResponse(WP_REST_Response $r, int $status): void {
    check($r->status === $status, 'HTTP ' . $status . ' got ' . $r->status);
    check($r->headers === ['Cache-Control' => 'private, no-store', 'Vary' => 'Authorization'], 'private response');
    check(!str_contains(json_encode($r->body), 'PRIVATE'), 'no private error/row leak');
}
function callNames(): array { return array_column($GLOBALS['calls'], 0); }
resetCase();
foreach ($hooks['rest_api_init'] as $cb) { $cb(); }
check($routes['sharity/v1/donations/checkout']['methods'] === 'POST', 'real POST registration');
check($routes['sharity/v1/donations/status/(?P<id>do_[a-f0-9]{32})']['methods'] === 'GET', 'real GET registration');
check(in_array('sharity donations order-schema', array_keys(WP_CLI::$commands), true), 'real CLI registration');
check($calls === [], 'load/registration has no store/provider/migration');
foreach ([new RuntimeException('PRIVATE_AUTH'), new TypeError('PRIVATE_TYPE'), null, ['subject_ref' => "hmac-sha256:" . str_repeat('a',64) . "\n"], new WP_REST_Response(['ok'=>false],403)] as $answer) {
    resetCase(); ImpactShop_Sharity_Web_Session::$answer = $answer;
    $expected = $answer instanceof Throwable ? 503 : ($answer instanceof WP_REST_Response ? 403 : 401);
    expectResponse(sharity_donation_order_handle(request()), $expected);
    expectResponse(sharity_donation_order_status_handle(request()), $expected);
    check(callNames() === ['auth','auth'], 'rejected auth before storage');
}
foreach ([null, [], 'text', 7, ['quote_id'=>'dq_'.str_repeat('c',32)], ['locale'=>'hu'], ['quote_id'=>[], 'locale'=>'hu'],
    ['quote_id'=>'dq_'.str_repeat('c',32), 'locale'=>['en']], ['quote_id'=>'dq_'.str_repeat('c',32), 'locale'=>'es'],
    ['quote_id'=>'dq_'.str_repeat('c',32)."\n", 'locale'=>'hu'], ['quote_id'=>'dq_'.str_repeat('c',32),'locale'=>'hu','owner'=>'evil']] as $input) {
    resetCase(); expectResponse(sharity_donation_order_handle(new WP_REST_Request($input)),400);
    check(callNames()===['auth'],'invalid body no store');
}
foreach ([['content-type','text/plain'],['idempotency-key','short'],['idempotency-key',str_repeat('x',81)],['idempotency-key',str_repeat('x',16)."\n"]] as [$k,$v]) {
    resetCase(); $r=request();$r->headers[$k]=$v;expectResponse(sharity_donation_order_handle($r),400);check(callNames()===['auth'],'bad header no store');
}
foreach (['hu','en','de','fr'] as $locale) {
    resetCase();$r=request();$r->json['locale']=$locale;$response=sharity_donation_order_handle($r);expectResponse($response,200);
    check(callNames()===['auth','prepare','factory','dispatch'],'prepared order path');
    check($calls[1][1]===ImpactShop_Sharity_Web_Session::$answer['subject_ref'] && $calls[1][4]===$locale,'canonical owner and locale');
    check($response->body['order']['payment_verified']===false,'checkout not payment proof');
}
foreach (['prepared','attempted','session_recorded'] as $state) {
    resetCase();$rowState=$state;$options=[];$factoryError=true;
    expectResponse(sharity_donation_order_handle(request()),$state==='attempted'?202:200);
    check(callNames()===['auth','prepare','status'],'disabled replay never asks SDK/key');
}
resetCase();$dispatchState='reconciliation_required';expectResponse(sharity_donation_order_handle(request()),202);check(count(array_filter(callNames(),fn($s)=>$s==='dispatch'))===1,'uncertain no retry');
resetCase();$factoryError=true;expectResponse(sharity_donation_order_handle(request()),503);check(!in_array('dispatch',callNames(),true),'missing runtime no dispatch');
foreach (['INVALID_REQUEST'=>400,'QUOTE_NOT_FOUND'=>404,'ORDER_NOT_FOUND'=>404,'IDEMPOTENCY_CONFLICT'=>409,'QUOTE_ALREADY_ORDERED'=>409,'QUOTE_EXPIRED'=>410,'DONATION_SESSION_WINDOW_EXPIRED'=>410,'PRIVATE_DB'=>503] as $error=>$status) {
    resetCase();$storeError=new RuntimeException($error);expectResponse(sharity_donation_order_handle(request()),$status);check(!in_array('factory',callNames(),true),'failure before factory');
}
resetCase();$storeError=new TypeError('PRIVATE_TYPE');expectResponse(sharity_donation_order_handle(request()),503);
resetCase();$rowState='attempted';$options=[];$r=request();$r->url=['id'=>'do_'.str_repeat('b',32)];$r->query=['id'=>'do_'.str_repeat('e',32),'owner'=>'evil'];$r->json=['id'=>'do_'.str_repeat('f',32)];
expectResponse(sharity_donation_order_status_handle($r),202);check(callNames()===['auth','status'] && $calls[1][2]===$r->url['id'],'URL-only status with no provider');
foreach ([null,[],'../other','do_'.str_repeat('a',32)."\n"] as $id) {
    resetCase();$r=request();$r->url=['id'=>$id];expectResponse(sharity_donation_order_status_handle($r),400);check(callNames()===['auth'],'invalid URL no store');
}
resetCase();$storeError=new RuntimeException('ORDER_NOT_FOUND');$r=request();$r->url=['id'=>'do_'.str_repeat('b',32)];expectResponse(sharity_donation_order_status_handle($r),404);
resetCase();$storeError=new TypeError('PRIVATE_ROW');expectResponse(sharity_donation_order_status_handle($r),503);
$cli=WP_CLI::$commands['sharity donations order-schema'];resetCase();$cli([],[]);check($calls===[] && end(WP_CLI::$messages)[0]==='error','CLI requires apply');
$cli([],['apply'=>true]);check(callNames()===['schema'] && end(WP_CLI::$messages)[0]==='success','explicit CLI schema');
resetCase();$schemaOk=false;$cli([],['apply'=>true]);check(callNames()===['schema'] && end(WP_CLI::$messages)[0]==='error','failed schema reported');
echo "PASS donation HTTP boundary {$checks} checks; mocked auth/store only, no provider\n";
