<?php
declare(strict_types=1);
// Pinned real SDK, synthetic transport only. Optional isolated MariaDB adds native CAS coverage.
require getenv('SHARITY_DONATION_SDK_AUTOLOAD') ?: __DIR__.'/../tmp/sharity-donation-sdk/vendor/autoload.php';
const WP_CLI = true;
class WP_CLI { public static array $commands=[], $lines=[]; public static function add_command($n,$f):void{self::$commands[$n]=$f;} public static function line($v):void{self::$lines[]=$v;} public static function error($e):void{throw new RuntimeException($e);} }
function add_action(...$a):void{}
function wp_cache_delete($key,$group):void{$GLOBALS['cacheDeletes'][]=[$key,$group];}
function sharity_donation_runtime_adapter():Sharity_Stripe_Checkout_Adapter{return $GLOBALS['adapter'];}
function get_option($key,$default=false){return $GLOBALS['optionFixture'][$key]??$default;}
$checks=0;$cacheDeletes=[];
function check(bool $v,string $why):void{$GLOBALS['checks']++;if(!$v)throw new RuntimeException($why);}
function fails(callable $f,string $code):void{try{$f();}catch(Throwable $e){check($e->getMessage()===$code&&$e->getPrevious()===null,'expected '.$code.', got '.$e->getMessage());return;}throw new RuntimeException('expected failure '.$code);}
require __DIR__.'/../wp-content/mu-plugins/sharity-ngo-donations.php';
foreach(['stripe-checkout.php','finance-ledger.php','finance-read.php']as$f){require __DIR__.'/../wp-content/mu-plugins/sharity-ngo-donations/'.$f;}
class AccountHttp implements Stripe\HttpClient\ClientInterface {
 public array $responses=[], $calls=[];public bool $drop=false;public $afterGet=null;
 public function request($method,$url,$headers,$params,$hasFile,$apiMode='v1',$maxNetworkRetries=null){
  $headers=array_values(array_filter($headers,static fn($h)=>!str_starts_with(strtolower($h),'authorization:')));
  $this->calls[]=compact('method','url','headers','params','maxNetworkRetries');
  if($this->drop)throw new RuntimeException('SYNTHETIC_PRIVATE_PROVIDER_MESSAGE');
  if($method!=='get'||!isset($this->responses[$url]))throw new RuntimeException('Unexpected provider request');
  if($this->afterGet!==null)($this->afterGet)();
  return[json_encode($this->responses[$url],JSON_THROW_ON_ERROR),200,['request-id'=>'req_SYNTHETIC']];
 }
}
function accountProvider(string $id='acct_NGO'):array{return['id'=>$id,'object'=>'account','email'=>'PRIVATE_EMAIL','individual'=>['name'=>'PRIVATE_NAME'],
 'external_accounts'=>['data'=>[['bank_account'=>'PRIVATE_BANK']]],'default_currency'=>'huf','charges_enabled'=>true,'payouts_enabled'=>true,'details_submitted'=>true,
 'capabilities'=>['card_payments'=>'active','transfers'=>'inactive'],'requirements'=>['currently_due'=>[],'past_due'=>[],'pending_verification'=>[],'errors'=>[],'disabled_reason'=>null],
 'controller'=>['fees'=>['payer'=>'account'],'losses'=>['payments'=>'stripe']]];}
function accountProof(string $slug='example-ngo'):array{global $now;$e=[];foreach(['representation','fee_terms','currency_support','platform_costs','retention','readiness']as$f){$e[$f]=['ref'=>'fixture_'.$f,'sha256'=>hash('sha256',$f)];}
 return['version'=>1,'ngo_slug'=>$slug,'account_id'=>'acct_NGO','platform_account_id'=>'acct_PLATFORM','livemode'=>false,'verified_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-60),
 'verified_until_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+3600),'settlement_currency'=>'HUF','payout_currency'=>'HUF','fee_payer'=>'account','losses_payments'=>'stripe',
 'platform_costs_minor'=>'0','platform_liability_minor'=>'0','currencies'=>['HUF'=>['min_minor'=>'50000','max_minor'=>'1000000'],'EUR'=>['min_minor'=>'100','max_minor'=>'10000']],
 'mode_currencies'=>['direct'=>['HUF'=>true,'EUR'=>true],'amplifier'=>['HUF'=>false]],'evidence'=>$e,'confirmed'=>true];}
$now=1800000000;$http=new AccountHttp();Stripe\ApiRequestor::setHttpClient($http);
$platformUrl='https://api.stripe.com/v1/account';$ngoUrl='https://api.stripe.com/v1/accounts/acct_NGO';
$http->responses=[$platformUrl=>['id'=>'acct_PLATFORM','object'=>'account'],$ngoUrl=>accountProvider()];
$client=Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL');
$adapter=new Sharity_Stripe_Checkout_Adapter($client,static function()use(&$now):int{return$now;});
$p=$adapter->inspectAccount('acct_NGO');
check(array_column($http->calls,'url')===[$platformUrl,$ngoUrl],'actual SDK platform/NGO GET paths');
foreach($http->calls as$c){check($c['method']==='get'&&$c['maxNetworkRetries']===0,'GET no retries');check(in_array('Stripe-Version: 2026-10-28.endive',$c['headers'],true),'pinned API');foreach($c['headers']as$h){check(!str_starts_with(strtolower($h),'stripe-account:')&&!str_starts_with(strtolower($h),'stripe-context:'),'no inherited context');}}
check($p['livemode']===false&&$p['default_currency']==='HUF'&&!$p['activation_allowed'],'key mode and lowercase provider currency');
check(!str_contains(json_encode($p),'PRIVATE'),'PII redacted');
$d=accountProof();sharity_donation_account_proof($d,'example-ngo',$now);$payload=sharity_donation_account_binding_payload($d,$p,$now);
check($payload['modes']===['direct'=>true,'amplifier'=>false]&&$payload['mode_currencies']['direct']['EUR'],'direct does not require transfers capability');
$variants=[['charges_enabled',false],['payouts_enabled',null],['details_submitted',false],['default_currency',null]];
foreach($variants as[$field,$v]){$a=accountProvider();$a[$field]=$v;$http->responses[$ngoUrl]=$a;$bad=$adapter->inspectAccount('acct_NGO');fails(fn()=>sharity_donation_account_binding_payload($d,$bad,$now),'DONATION_ACCOUNT_PROVIDER_UNPROVEN');}
foreach(['currently_due','past_due','pending_verification','errors','disabled_reason']as$f){$a=accountProvider();unset($a['requirements'][$f]);$http->responses[$ngoUrl]=$a;$bad=$adapter->inspectAccount('acct_NGO');fails(fn()=>sharity_donation_account_binding_payload($d,$bad,$now),'DONATION_ACCOUNT_PROVIDER_UNPROVEN');}
foreach(['card_payments','fees','losses']as$f){$a=accountProvider();if($f==='card_payments'){$a['capabilities'][$f]='pending';}else{unset($a['controller'][$f]);}$http->responses[$ngoUrl]=$a;$bad=$adapter->inspectAccount('acct_NGO');fails(fn()=>sharity_donation_account_binding_payload($d,$bad,$now),'DONATION_ACCOUNT_PROVIDER_UNPROVEN');}
$http->responses[$ngoUrl]=accountProvider();$http->responses[$ngoUrl]['id']='acct_OTHER';fails(fn()=>$adapter->inspectAccount('acct_NGO'),'DONATION_ACCOUNT_INSPECT_UNAVAILABLE');
$http->responses[$ngoUrl]=accountProvider();$http->responses[$ngoUrl]['livemode']=true;fails(fn()=>$adapter->inspectAccount('acct_NGO'),'DONATION_ACCOUNT_INSPECT_UNAVAILABLE');unset($http->responses[$ngoUrl]['livemode']);
$http->responses[$platformUrl]['id']='acct_NGO';$before=count($http->calls);fails(fn()=>$adapter->inspectAccount('acct_NGO'),'DONATION_ACCOUNT_INSPECT_UNAVAILABLE');check(count($http->calls)===$before+1,'own platform refused before NGO GET');$http->responses[$platformUrl]['id']='acct_PLATFORM';
$inherited=new Stripe\StripeClient(['api_key'=>'sk_test_SYNTHETIC','stripe_version'=>Sharity_Stripe_Checkout_Adapter::API_VERSION,'max_network_retries'=>0,'stripe_account'=>'acct_FOREIGN']);
$badAdapter=new Sharity_Stripe_Checkout_Adapter($inherited);$before=count($http->calls);fails(fn()=>$badAdapter->inspectAccount('acct_NGO'),'DONATION_ACCOUNT_INSPECT_UNAVAILABLE');check(count($http->calls)===$before,'inherited client refused without GET');
$http->drop=true;fails(fn()=>$adapter->inspectAccount('acct_NGO'),'DONATION_ACCOUNT_INSPECT_UNAVAILABLE');$http->drop=false;
$mutators=[static function(&$x){$x['platform_costs_minor']='1';},static function(&$x){$x['platform_liability_minor']='unknown';},static function(&$x){$x['fee_payer']='application';},static function(&$x){$x['losses_payments']='application';},static function(&$x){$x['account_id']=$x['platform_account_id'];}];
foreach($mutators as$mutate){$x=$d;$mutate($x);fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_ACCOUNT_MODEL_UNPROVEN');}
$x=$d;$x['mode_currencies']['amplifier']['HUF']=true;fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_ACCOUNT_AMPLIFIER_UNAVAILABLE');
$x=$d;$x['verified_until_utc']=gmdate('Y-m-d\TH:i:s\Z',$now);fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_ACCOUNT_PROOF_EXPIRED');
$x=$d;$x['mode_currencies']['direct']['USD']=true;fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_ACCOUNT_PROOF_INVALID');
$x=$d;unset($x['evidence']['platform_costs']);fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_BACKING_PROOF_MALFORMED');
$x=$d;$x['currencies']['HUF']['min_minor']='501';fails(fn()=>sharity_donation_account_proof($x,'example-ngo',$now),'DONATION_ACCOUNT_PROOF_INVALID');
fails(fn()=>sharity_donation_account_binding_payload($d,$p,$now+31),'DONATION_ACCOUNT_PROVIDER_UNPROVEN');
foreach(['inspect','bind','disable']as$op){check(isset(WP_CLI::$commands['sharity donations account-'.$op]),'CLI registered '.$op);fails(fn()=>sharity_donation_cli_account([],[],$op),'DONATION_ACCOUNT_ARGUMENTS');fails(fn()=>sharity_donation_cli_account(['extra'],['account'=>'acct_NGO'],$op),'DONATION_ACCOUNT_ARGUMENTS');}
sharity_donation_cli_account([],['account'=>'acct_NGO'],'inspect');check(json_decode(end(WP_CLI::$lines),true)['account_id']==='acct_NGO','CLI sanitized inspection');
check(count(array_filter($http->calls,static fn($c)=>$c['method']!=='get'))===0,'all provider operations GET');
if(getenv('SHARITY_DONATION_TEST_PORT')){require __DIR__.'/sharity-donation-account-cas-mysql.test.php';}
echo 'Donation account checks: '.$checks."\n";
