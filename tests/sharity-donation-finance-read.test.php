<?php
declare(strict_types=1);
// Real pinned SDK + replaced HTTP. Synthetic identities, no network/provider money.
require getenv('SHARITY_DONATION_SDK_AUTOLOAD') ?: __DIR__ . '/../tmp/sharity-donation-sdk/vendor/autoload.php';
function add_action(...$args): void {}
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/stripe-checkout.php';
$checks = 0;
function check(bool $ok, string $why): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($why); } }
function fails(callable $f, string $code = 'DONATION_EVIDENCE_UNAVAILABLE'): void {
    try { $f(); } catch (RuntimeException $e) { check($e->getMessage() === $code && $e->getPrevious() === null, 'sanitized failure: ' . $code); return; }
    throw new RuntimeException('Expected failure ' . $code);
}
final class EvidenceHttp implements Stripe\HttpClient\ClientInterface {
    public array $calls = []; public array $responses = []; public ?string $drop = null;
    public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        $headers = array_values(array_filter($headers, static fn($h) => !str_starts_with(strtolower($h), 'authorization:')));
        $path = parse_url($url, PHP_URL_PATH);
        $this->calls[] = compact('method', 'url', 'path', 'headers', 'params', 'maxNetworkRetries');
        if ($path === $this->drop) { throw new Stripe\Exception\ApiConnectionException('PRIVATE_PROVIDER_PAYLOAD_EMAIL_KEY'); }
        if (!isset($this->responses[$path])) { throw new RuntimeException('Unexpected request'); }
        return [json_encode($this->responses[$path], JSON_THROW_ON_ERROR), 200, ['request-id' => 'req_SYNTHETIC']];
    }
}
function fixture(string $mode = 'direct', string $currency = 'HUF'): array {
    $gross = $currency === 'HUF' ? 50000 : 300;
    $s = ['ngo_slug'=>'example-ngo','donation_mode'=>$mode,'currency'=>$currency,'amount_minor'=>(string)$gross,
        'votes'=>$mode==='direct'?0:500,'package_id'=>$mode==='direct'?null:'starter',
        'price_book_version'=>$mode==='direct'?null:($currency==='HUF'?'amplifier-net80-v1:HUF':'amplifier-net80-v1:2026-11-02:'.$currency),
        'policy_version'=>$mode==='direct'?'direct-net100-v1':'amplifier-net80-v1','account_id'=>'acct_SYNTHETIC','binding_version'=>'v1','livemode'=>false];
    $e = Sharity_Stripe_Checkout_Adapter::envelope($s, 'do_'.str_repeat('a',32), 'dq_'.str_repeat('b',32),1800000000,1800003600,'hu');
    $cc = strtolower($currency); $af = $mode==='direct'?0:intdiv($gross,5); $cost = $currency==='HUF'?2000:20;
    $pi = ['object'=>'payment_intent','id'=>'pi_SYNTHETIC','metadata'=>$e['payload']['metadata'],'amount'=>$gross,
        'currency'=>$cc,'livemode'=>false,'amount_received'=>$gross,'status'=>'succeeded','latest_charge'=>'ch_SYNTHETIC',
        'application_fee_amount'=>$af,'transfer_data'=>null,'customer'=>'cus_PRIVATE'];
    $list = ['object'=>'list','data'=>[],'has_more'=>false];
    $session = ['object'=>'checkout.session','id'=>'cs_test_SYNTHETIC','livemode'=>false,'status'=>'complete','payment_status'=>'paid','mode'=>'payment',
        'client_reference_id'=>$e['order_id'],'amount_total'=>$gross,'currency'=>$cc,'expires_at'=>$e['expires_at'],'metadata'=>$e['payload']['metadata'],
        'payment_intent'=>$pi,'url'=>null,'customer_email'=>'PRIVATE_EMAIL'];
    $charge = ['object'=>'charge','id'=>'ch_SYNTHETIC','payment_intent'=>'pi_SYNTHETIC','livemode'=>false,'status'=>'succeeded','paid'=>true,'captured'=>true,
        'amount'=>$gross,'amount_captured'=>$gross,'currency'=>$cc,'metadata'=>$e['payload']['metadata'],'balance_transaction'=>'txn_CHARGE',
        'application_fee'=>$af?'fee_SYNTHETIC':null,'application_fee_amount'=>$af,'amount_refunded'=>0,'refunded'=>false,'disputed'=>false,
        'failure_balance_transaction'=>null,'refunds'=>$list,'billing_details'=>['email'=>'PRIVATE_EMAIL'],'receipt_url'=>'https://example.test/PRIVATE_RECEIPT'];
    $detail = ['amount'=>$cost,'currency'=>$cc,'type'=>'stripe_fee','description'=>'PRIVATE_DESCRIPTION','application'=>null];
    $bt = ['object'=>'balance_transaction','id'=>'txn_CHARGE','source'=>'ch_SYNTHETIC','type'=>'charge','amount'=>$gross,'currency'=>$cc,
        'fee'=>$cost+$af,'net'=>$gross-$cost-$af,'fee_details'=>[$detail],'created'=>1800000010,'available_on'=>1800600000,'status'=>'available','exchange_rate'=>null];
    if ($af) { $bt['fee_details'][]=['amount'=>$af,'currency'=>$cc,'type'=>'application_fee','description'=>'PRIVATE_DESCRIPTION']; }
    $fee = ['object'=>'application_fee','id'=>'fee_SYNTHETIC','account'=>'acct_SYNTHETIC','charge'=>'ch_SYNTHETIC','livemode'=>false,
        'amount'=>$af,'currency'=>$cc,'amount_refunded'=>0,'refunded'=>false,'refunds'=>$list,'balance_transaction'=>'txn_FEE'];
    $fbt = ['object'=>'balance_transaction','id'=>'txn_FEE','source'=>'fee_SYNTHETIC','type'=>'application_fee','amount'=>$af,'currency'=>$cc,
        'fee'=>0,'net'=>$af,'fee_details'=>[],'created'=>1800000010,'available_on'=>1800600000,'status'=>'available','exchange_rate'=>null];
    return [$e, ['/v1/account'=>['object'=>'account','id'=>'acct_PLATFORM','email'=>'PRIVATE_PLATFORM_EMAIL'], '/v1/checkout/sessions/cs_test_SYNTHETIC'=>$session,'/v1/charges/ch_SYNTHETIC'=>$charge,
        '/v1/balance_transactions/txn_CHARGE'=>$bt,'/v1/application_fees/fee_SYNTHETIC'=>$fee,'/v1/balance_transactions/txn_FEE'=>$fbt]];
}
$transport = new EvidenceHttp(); Stripe\ApiRequestor::setHttpClient($transport);
$adapter = new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL'), static fn()=>1809000000);
function readEvidence(array $e, array $responses): array { global $transport,$adapter; $transport->responses=$responses;$transport->calls=[];return $adapter->retrievePaymentEvidence('cs_test_SYNTHETIC',$e); }
$S='/v1/checkout/sessions/cs_test_SYNTHETIC';$C='/v1/charges/ch_SYNTHETIC';$B='/v1/balance_transactions/txn_CHARGE';$F='/v1/application_fees/fee_SYNTHETIC';$FB='/v1/balance_transactions/txn_FEE';
foreach (['direct','amplifier'] as $mode) { foreach (['HUF','EUR','USD','GBP'] as $currency) {
    [$e,$responses]=fixture($mode,$currency);$r=readEvidence($e,$responses);
    check($r['evidence_state']==='original_charge_observed' && !$r['payment_verified'] && !$r['allocation_eligible'],'read is not settlement/release');
    check($r['payment']['amount_minor']===$e['snapshot']['amount_minor'] && $r['initial_charge_balance']['gross_minor']===$e['snapshot']['amount_minor'],'exact HUF cents and all minor values');
    check($r['initial_charge_balance']['processing_cost_minor']===($currency==='HUF'?'2000':'20'),'application fee never charged twice');
    check(!$r['adjustments_required'] && !$r['balances_pending'] && !$r['cross_currency'],'clean original facts only');
    check(count($transport->calls)===($mode==='direct'?3:6),'bounded GET graph');
    foreach ($transport->calls as $i=>$call) {
        check($call['method']==='get' && $call['maxNetworkRetries']===0 && str_starts_with($call['url'],'https://api.stripe.com/v1/'),'GET official SDK no retry');
        check(in_array('Stripe-Version: 2026-10-28.endive',$call['headers'],true),'pinned API');
        $accounts=array_values(array_filter($call['headers'],static fn($h)=>str_starts_with($h,'Stripe-Account:')));
        check($accounts===($i<3?['Stripe-Account: acct_SYNTHETIC']:[]),'connected then platform scope');
        check(!array_filter($call['headers'],static fn($h)=>str_starts_with($h,'Idempotency-Key:')),'no create key on evidence GET');
    }
    check(!str_contains(json_encode($r),'PRIVATE_') && !isset($r['metadata']),'no PII/description/raw metadata projection');
    check($mode==='direct' ? !array_key_exists('platform_account_id',$r) : ($r['platform_account_id']??null)==='acct_PLATFORM','platform identity only in Amplifier, direct stays unchanged');
} }
[$e,$responses]=fixture('amplifier');
foreach ([['object'=>'customer','id'=>'acct_PLATFORM'], ['object'=>'account'], ['object'=>'account','id'=>null],
    ['object'=>'account','id'=>'acct_bad/path'], ['object'=>'account','id'=>'acct_SYNTHETIC'],
    ['object'=>'account','id'=>'acct_PLATFORM','livemode'=>true], ['object'=>'account','id'=>'acct_PLATFORM','livemode'=>'false']] as $badAccount) {
    $p=$responses;$p['/v1/account']=$badAccount;fails(fn()=>readEvidence($e,$p));
}
$p=$responses;unset($p['/v1/account']);fails(fn()=>readEvidence($e,$p));
$p=$responses;$p['/v1/account']['livemode']=false;check(readEvidence($e,$p)['platform_account_id']==='acct_PLATFORM','optional provider mode must match');
$transport->drop='/v1/account';fails(fn()=>readEvidence($e,$responses));$transport->drop=null;
// Three currency facts, never summed or converted using the reference FX.
$x=$responses;$x[$B]=array_replace($x[$B],['currency'=>'eur','amount'=>12500,'fee'=>3000,'net'=>9500,'exchange_rate'=>0.25]);
$x[$B]['fee_details']=[['amount'=>500,'currency'=>'eur','type'=>'stripe_fee'],['amount'=>2500,'currency'=>'eur','type'=>'application_fee']];
$x[$F]['amount']=2500;$x[$F]['currency']='eur';
$x[$FB]=array_replace($x[$FB],['currency'=>'gbp','amount'=>2150,'fee'=>50,'net'=>2100,'exchange_rate'=>0.86,'fee_details'=>[['amount'=>50,'currency'=>'gbp','type'=>'stripe_fee']]]);
$r=readEvidence($e,$x);
check($r['cross_currency'] && $r['payment']['currency']==='HUF' && $r['initial_charge_balance']['currency']==='EUR' && $r['application_fee']['initial_balance']['currency']==='GBP','separate charge/payment/platform currencies');
check($r['application_fee']['initial_balance']['processing_cost_minor']==='50' && !isset($r['allocation_minor']),'platform fee preserved, no derived allocation/refund');
check($r['initial_charge_balance']['exchange_rate']===0.25 && $r['application_fee']['initial_balance']['exchange_rate']===0.86,'provider rates informational');
foreach ([$B,$FB] as $path) { $p=$responses;$p[$path]['status']='pending';$r=readEvidence($e,$p);check($r['balances_pending'] && !$r['allocation_eligible'],'pending blocks any release claim'); }
foreach ([['open','unpaid'],['complete','unpaid'],['expired','unpaid'],['complete','no_payment_required']] as [$state,$paid]) {
    $p=$responses;$p[$S]['status']=$state;$p[$S]['payment_status']=$paid;if($state==='open'){$p[$S]['url']='https://checkout.stripe.com/c/pay/cs_test_SYNTHETIC';}
    $r=readEvidence($e,$p);check($r['evidence_state']==='payment_pending' && !isset($r['initial_charge_balance']) && count($transport->calls)===1,'unpaid/incomplete never zero financial evidence');
}
foreach ([$C,$F] as $path) {
    foreach (['partial','full','list','has_more'] as $case) {
        $p=$responses;
        if($case==='partial'){$p[$path]['amount_refunded']=100;}
        if($case==='full'){$p[$path]['amount_refunded']=$p[$path]['amount'];$p[$path]['refunded']=true;}
        if($case==='list'){$p[$path]['refunds']['data']=[['object'=>$path===$C?'refund':'fee_refund','id'=>$path===$C?'re_SYNTHETIC':'fr_SYNTHETIC','description'=>'PRIVATE_REFUND']];}
        if($case==='has_more'){$p[$path]['refunds']['has_more']=true;}
        $r=readEvidence($e,$p);check($r['adjustments_required'] && !$r['payment_verified'] && !str_contains(json_encode($r),'PRIVATE_'),'refund/partial/pagination requires later reconciliation');
    }
}
foreach (['disputed'=>true,'failure_balance_transaction'=>'txn_FAILURE'] as $key=>$value) {
    $p=$responses;$p[$C][$key]=$value;check(readEvidence($e,$p)['adjustments_required'],'dispute/failure never final-net claim');
}
$bad = [
 [$S,['payment_intent','object'],'charge'],[$S,['payment_intent','id'],'pi_x/other'],[$S,['payment_intent','status'],'processing'],
 [$S,['payment_intent','amount_received'],49999],[$S,['payment_intent','amount_received'],'50000'],[$S,['payment_intent','livemode'],true],
 [$S,['payment_intent','metadata','order_id'],'do_OTHER'],[$S,['payment_intent','application_fee_amount'],9999],[$S,['payment_intent','transfer_data'],['destination'=>'acct_OTHER']],
 [$S,['payment_intent','latest_charge'],'ch_x/../other'],[$C,['id'],'ch_OTHER'],[$C,['object'],'refund'],[$C,['payment_intent'],'pi_OTHER'],
 [$C,['livemode'],true],[$C,['paid'],false],[$C,['captured'],false],[$C,['amount_captured'],49999],[$C,['amount'],50001],[$C,['currency'],'eur'],
 [$C,['metadata','quote_id'],'dq_OTHER'],[$C,['balance_transaction'],'txn_x/other'],[$C,['application_fee_amount'],9999],[$C,['application_fee'],'fee_bad/other'],
 [$C,['amount_refunded'],-1],[$C,['amount_refunded'],50001],[$C,['refunded'],true],[$C,['disputed'],'false'],[$C,['failure_balance_transaction'],false],
 [$B,['id'],'txn_OTHER'],[$B,['source'],'ch_OTHER'],[$B,['type'],'refund'],[$B,['amount'],'50000'],[$B,['amount'],50000.5],[$B,['amount'],1000000000000],
 [$B,['net'],37999],[$B,['fee'],-1],[$B,['status'],'invented'],[$B,['currency'],'jpy'],[$B,['created'],'1800000010'],[$B,['available_on'],0],
 [$B,['exchange_rate'],0],[$B,['exchange_rate'],'1.0'],[$B,['exchange_rate'],1.1],[$B,['fee_details',0,'amount'],2001],[$B,['fee_details',0,'amount'],-1],
 [$B,['fee_details',0,'currency'],'eur'],[$B,['fee_details',0,'type'],'invalid type'],[$B,['fee_details'],null],
 [$F,['account'],'acct_OTHER'],[$F,['charge'],'ch_OTHER'],[$F,['livemode'],true],[$F,['amount'],9999],[$F,['currency'],'jpy'],[$F,['amount_refunded'],'0'],
 [$F,['balance_transaction'],null],[$FB,['source'],'fee_OTHER'],[$FB,['id'],'txn_OTHER'],[$FB,['amount'],10001],[$FB,['net'],9999],[$FB,['type'],'charge'],
];
foreach ($bad as [$path,$keys,$value]) {
    $p=$responses;$target=&$p[$path];foreach($keys as $key){$target=&$target[$key];}$target=$value;unset($target);
    fails(static fn()=>readEvidence($e,$p));check(count($transport->calls)<=6,'bad responses stop bounded graph');
}
foreach ([$C,$F] as $path) { foreach (['refunds','amount_refunded','refunded'] as $key) { $p=$responses;unset($p[$path][$key]);fails(static fn()=>readEvidence($e,$p)); }
    foreach (['object'=>null,'data'=>null,'has_more'=>null] as $key=>$value){$p=$responses;$p[$path]['refunds'][$key]=$value;fails(static fn()=>readEvidence($e,$p));}
    $p=$responses;$p[$path]['refunds']['data']=[['object'=>'incorrect','id'=>'re_SYNTHETIC']];fails(static fn()=>readEvidence($e,$p));
}
foreach ([$S,$C,$B,'/v1/account',$F,$FB] as $i=>$path) { $transport->drop=$path;fails(static fn()=>readEvidence($e,$responses));check(count($transport->calls)===$i+1,'network failure no retry or fallback'); }$transport->drop=null;
$p=$x;$p[$B]['exchange_rate']=null;fails(static fn()=>readEvidence($e,$p));
$p=$x;$p[$FB]['exchange_rate']=null;fails(static fn()=>readEvidence($e,$p));
[$direct,$p]=fixture();$p[$C]['application_fee']='fee_SYNTHETIC';fails(static fn()=>readEvidence($direct,$p));
[$direct,$p]=fixture();$p[$B]['fee_details'][0]['type']='application_fee';fails(static fn()=>readEvidence($direct,$p));
[$direct,$p]=fixture();$p[$C]['application_fee_amount']=null;$p[$S]['payment_intent']['application_fee_amount']=null;
check(readEvidence($direct,$p)['application_fee']===null,'direct nullable zero fee supported');
// Negative initial net is faithfully retained, never eligible for payout.
[$direct,$p]=fixture();$p[$B]['fee']=60000;$p[$B]['net']=-10000;$p[$B]['fee_details'][0]['amount']=60000;
$r=readEvidence($direct,$p);check($r['initial_charge_balance']['net_minor']==='-10000' && !$r['allocation_eligible'],'negative fee impact visible, no money claim');
$inherited = new Sharity_Stripe_Checkout_Adapter(new Stripe\StripeClient(['api_key'=>'rk_test_SYNTHETIC','stripe_version'=>Sharity_Stripe_Checkout_Adapter::API_VERSION,'max_network_retries'=>0,'stripe_account'=>'acct_OTHER']));
$count=count($transport->calls);fails(static fn()=>$inherited->retrievePaymentEvidence('cs_test_SYNTHETIC',$e),'DONATION_EVIDENCE_UNSAFE_CONFIG');check(count($transport->calls)===$count,'inherited account refused before network');
fails(static fn()=>$adapter->retrievePaymentEvidence('cs_live_SYNTHETIC',$e),'DONATION_INVALID_SESSION_ID');
$invalid=$e;$invalid['options']['stripe_account']='acct_OTHER';fails(static fn()=>$adapter->retrievePaymentEvidence('cs_test_SYNTHETIC',$invalid),'DONATION_INVALID_ENVELOPE');
// Trusted admin CLI: exercise the real order decoder/status and autocommit read,
// replacing only WPDB transport/runtime construction (not financial validation).
define('WP_CLI',true);define('ARRAY_A','ARRAY_A');
class WP_CLI {
    public static array $commands=[];public static array $lines=[];
    public static function add_command($name,$handler):void{self::$commands[$name]=$handler;}
    public static function line($text):void{self::$lines[]=$text;}
    public static function error($text):void{throw new RuntimeException($text);}
}
function sharity_donation_runtime_adapter(): Sharity_Stripe_Checkout_Adapter {$GLOBALS['factoryCalls']++;return $GLOBALS['adapter'];}
final class EvidenceDb {
    public string $prefix='wp_';public string $last_error='';public array $row=[];public array $transaction=['ac'=>1,'tx'=>0];public int $writes=0;
    public function prepare($sql,...$args){return ['sql'=>$sql,'args'=>$args];}
    public function get_row($query,$format){
        if(is_string($query) && str_starts_with($query,'SELECT @@autocommit')){return $this->transaction;}
        if(!is_array($query) || !str_starts_with($query['sql'],'SELECT * FROM')){throw new RuntimeException('Unexpected DB read');}
        check($query['args'][0]===$this->row['order_id'],'CLI original order binding');
        if(count($query['args'])===2){check($query['args'][1]===$this->row['subject_ref'],'status retains owner scope');}
        return $this->row;
    }
    public function query($sql){$this->writes++;throw new RuntimeException('Read-only CLI wrote DB');}
}
require __DIR__.'/../wp-content/mu-plugins/sharity-ngo-donations/orders.php';
require __DIR__.'/../wp-content/mu-plugins/sharity-ngo-donations/webhook.php';
require __DIR__.'/../wp-content/mu-plugins/sharity-ngo-donations/finance-read.php';
check(isset(WP_CLI::$commands['sharity donations payment-inspect']),'explicit CLI registered');
$factoryCalls=0;
fails(fn()=>sharity_donation_cli_finance_schema([],['apply'=>true]), 'DONATION_FINANCE_SCHEMA_UNAVAILABLE');
fails(fn()=>sharity_donation_cli_payment_record([],['order'=>'do_'.str_repeat('a',32)]), 'DONATION_FINANCE_UNAVAILABLE');
fails(fn()=>sharity_donation_cli_payment_reconcile([],['order'=>'do_'.str_repeat('a',32)]), 'DONATION_RECONCILE_UNAVAILABLE');
check(isset(WP_CLI::$commands['sharity donations fee-schema'],WP_CLI::$commands['sharity donations fee-prepare']),'fee CLI registered without optional ledger');
foreach([[[],[]],[[],['apply'=>true]],[[],['apply'=>'true']]]as[$args,$assoc]){fails(fn()=>sharity_donation_cli_fee_schema($args,$assoc),'DONATION_FEE_SCHEMA_UNAVAILABLE');}
fails(fn()=>sharity_donation_cli_fee_prepare([],['order'=>'do_'.str_repeat('a',32),'input'=>'/private/missing-proof']),'DONATION_FEE_UNAVAILABLE');
check($factoryCalls===0, 'missing optional journal never constructs SDK');
[$e,$responses]=fixture('amplifier');$transport->responses=$responses;$transport->calls=[];$factoryCalls=0;
$result=['session_id'=>'cs_test_SYNTHETIC','checkout_state'=>'complete','checkout_url'=>null,'expires_at'=>$e['expires_at'],'provider_payment_status'=>'paid','payment_verified'=>false];
$stored=['order_id'=>$e['order_id'],'quote_id'=>$e['quote_id'],'subject_ref'=>'hmac-sha256:'.str_repeat('c',64),
    'state'=>'session_recorded','provider_session_id'=>'cs_test_SYNTHETIC','result_json'=>json_encode($result),'envelope_json'=>json_encode($e)];
$wpdb=new EvidenceDb();$wpdb->row=$stored;
sharity_donation_cli_payment_inspect([],['order'=>$e['order_id']]);
check($factoryCalls===1 && count($transport->calls)===6 && $wpdb->writes===0,'CLI read-only with recorded session');
$projection=json_decode(WP_CLI::$lines[0],true,64,JSON_THROW_ON_ERROR);
check($projection['order_id']===$e['order_id'] && $projection['payment_verified']===false && !str_contains(WP_CLI::$lines[0],'subject_ref'),'CLI minimal non-settlement output');
foreach ([[[],[]],[['do_BAD'],[]],[[],['order'=>'do_BAD']],[[],['order'=>$e['order_id'],'session'=>'cs_test_OTHER']]] as [$args,$assoc]) {
    $n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect($args,$assoc));check($factoryCalls===$n,'invalid CLI args no SDK');
}
foreach (['prepared','attempted'] as $state) {$wpdb->row=array_replace($stored,['state'=>$state]);$n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect([],['order'=>$e['order_id']]));check($factoryCalls===$n,'no SDK for absent recorded session');}
foreach (['result_json'=>'{}','provider_session_id'=>'cs_test_OTHER','envelope_json'=>'{}'] as $key=>$value) {$wpdb->row=array_replace($stored,[$key=>$value]);$n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect([],['order'=>$e['order_id']]));check($factoryCalls===$n,'corrupt stored result no SDK');}
$wpdb->row=$stored;
foreach ([['ac'=>0,'tx'=>0],['ac'=>1,'tx'=>1]] as $tx) {$wpdb->transaction=$tx;$n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect([],['order'=>$e['order_id']]));check($factoryCalls===$n,'no SDK/provider under DB transaction');}
check($wpdb->writes===0 && count(WP_CLI::$lines)===1,'no DB/flags writes or partial error payload');
check(isset(WP_CLI::$commands['sharity donations finance-poll']),'poll CLI registered');
foreach([[[],['unexpected'=>true]],[['arg'],[]],[[],[]]]as[$args,$assoc]){
    fails(fn()=>sharity_donation_cli_finance_poll($args,$assoc),'DONATION_POLL_UNAVAILABLE');
}
check($wpdb->writes===0,'poll partial installation and bad arguments never write or call provider');
echo "PASS donation original financial evidence and CLI: {$checks} checks; real SDK, offline HTTP, WPDB transport mocked, no settlement acceptance\n";
