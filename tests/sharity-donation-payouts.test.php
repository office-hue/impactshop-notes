<?php
declare(strict_types=1);
require __DIR__.'/sharity-donation-finance-read.test.php';
$start=$checks;$wpdb->transaction=['ac'=>1,'tx'=>0];
function sharity_donation_runtime_sdk():void {if(Stripe\Stripe::VERSION!==Sharity_Stripe_Checkout_Adapter::SDK_VERSION)throw new RuntimeException('SDK mismatch');}
final class PayoutHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses=[],$pages=[],$calls=[];public $mutate=null;
    public function request($m,$u,$h,$p,$f,$a='v1',$r=null){
        global $wpdb;$path=parse_url($u,PHP_URL_PATH);
        check($m==='get'&&$f===false&&$r===0&&$a==='v1','payout reads official GET only, no retry');
        check($wpdb->transaction===['ac'=>1,'tx'=>0],'payout HTTP outside transaction');
        $this->calls[]=['path'=>$path,'params'=>$p,'headers'=>array_values(array_filter($h,static fn($x)=>!str_starts_with(strtolower($x),'authorization:')))];
        if($this->mutate!==null)($this->mutate)($path,$this);
        if($path==='/v1/balance_transactions'){
            check(($p['payout']??null)==='po_SYNTHETIC'&&($p['limit']??null)===100&&!isset($p['currency'])&&!isset($p['type']),'exact payout filter and bounded whole batch');
            $data=$this->pages[$p['starting_after']??'']??null;
        }else{$data=$this->responses[$path]??null;}
        if(!is_array($data))throw new RuntimeException('PRIVATE_PROVIDER_FAILURE');
        return[json_encode($data),200,[]];
    }
}
function payoutBalance(string $id,?string $source,int $gross,int $fee,string $type,string $currency='huf'):array {
    return ['object'=>'balance_transaction','id'=>$id,'source'=>$source,'type'=>$type,'amount'=>$gross,'fee'=>$fee,'net'=>$gross-$fee,
        'currency'=>$currency,'fee_details'=>$fee?[['amount'=>$fee,'currency'=>$currency,'type'=>'stripe_fee','description'=>'PRIVATE_FEE']]:[],
        'created'=>1800000010,'available_on'=>1800000200,'status'=>'available','exchange_rate'=>null,'description'=>'PRIVATE_BALANCE'];
}
function payoutHttp(string $currency='huf'):PayoutHttp {
    $h=new PayoutHttp();$p=['object'=>'payout','id'=>'po_SYNTHETIC','livemode'=>false,'amount'=>10000,'currency'=>$currency,
        'automatic'=>true,'method'=>'standard','type'=>'bank_account','status'=>'paid','reconciliation_status'=>'completed',
        'created'=>1800000020,'arrival_date'=>1800000400,'balance_transaction'=>'txn_PAYOUT','failure_balance_transaction'=>null,
        'original_payout'=>null,'reversed_by'=>null,'destination'=>'ba_PRIVATE','failure_message'=>'PRIVATE_BANK','metadata'=>['owner'=>'PRIVATE_OWNER']];
    $h->responses=['/v1/account'=>['object'=>'account','id'=>'acct_SYNTHETIC','email'=>'PRIVATE_EMAIL'], '/v1/payouts/po_SYNTHETIC'=>$p,
        '/v1/balance_transactions/txn_PAYOUT'=>payoutBalance('txn_PAYOUT','po_SYNTHETIC',-10000,300,'payout',$currency)];
    $h->pages=[''=>['object'=>'list','has_more'=>false,'data'=>[
        payoutBalance('txn_CHARGE','ch_SYNTHETIC',12000,200,'charge',$currency),
        payoutBalance('txn_REFUND','re_SYNTHETIC',-1000,0,'refund',$currency),
        payoutBalance('txn_FEE',null,-500,0,'stripe_fee',$currency)]]];return$h;
}
function payoutRead(PayoutHttp $h,string $scope='connected',?Sharity_Stripe_Checkout_Adapter $a=null,bool $live=false):array {
    global $adapter;return sharity_donation_webhook_bounded_read(fn()=>($a??$adapter)->retrievePayoutEvidence('po_SYNTHETIC','acct_SYNTHETIC',$live,$scope),hrtime(true)/1e9+60,$h,null,15);
}
function payoutFails(PayoutHttp $h,string $code='DONATION_PAYOUT_UNAVAILABLE'):void {fails(fn()=>payoutRead($h),$code);}
$pp='/v1/payouts/po_SYNTHETIC';$bp='/v1/balance_transactions/txn_PAYOUT';
foreach(['huf','eur','usd','gbp']as$currency){foreach(['connected','platform']as$scope){
    $h=payoutHttp($currency);$prior=Stripe\ApiRequestor::httpClient();$r=payoutRead($h,$scope);
    check(Stripe\ApiRequestor::httpClient()===$prior,'restore prior SDK client');
    check($r['membership_complete']&&$r['batch_totals_reconciled']&&$r['unresolved']===[],'complete exact batch');
    check($r['payout']['amount_minor']==='10000'&&$r['payout']['currency']===strtoupper($currency),'native units preserved, including HUF');
    check($r['funding_totals'][strtoupper($currency)]['net_minor']==='10300','payout fee is funded once');
    check(!$r['order_attribution_verified']&&!$r['ngo_bank_received']&&!$r['allocation_eligible'],'batch is not order allocation or NGO-bank receipt');
    check(!str_contains(json_encode($r),'PRIVATE_'),'no PII or provider descriptions');
    check(count($h->calls)===5,'bounded5 GET clean batch');
    foreach($h->calls as$call){$headers=array_values(array_filter($call['headers'],static fn($x)=>str_starts_with($x,'Stripe-Account:')));
        check($headers===($scope==='connected'?['Stripe-Account: acct_SYNTHETIC']:[]),'all calls exact account scope');
        check(in_array('Stripe-Version: 2026-10-28.endive',$call['headers'],true),'API pinned');}
}}
$h=payoutHttp();$h->pages['']['data'][]=$h->responses[$bp];$r=payoutRead($h);
check($r['own_payout_row_included']&&$r['batch_totals_reconciled']&&count($r['items'])===4,'own payout row retained and subtracted exactly once');
$h=payoutHttp();$h->pages['']['data'][]=$h->responses[$bp];$h->pages['']['data'][3]['net']=-10301;payoutFails($h);
$h=payoutHttp();$items=$h->pages['']['data'];$h->pages=[''=>['object'=>'list','data'=>[$items[0]],'has_more'=>true],
    'txn_CHARGE'=>['object'=>'list','data'=>array_slice($items,1),'has_more'=>false]];
check(payoutRead($h)['batch_totals_reconciled']&&count($h->calls)===6,'all pages with exact cursor');
$h->pages['txn_CHARGE']['data'][]=$items[0];payoutFails($h);
$h=payoutHttp();$h->pages=[];
for($i=0;$i<10;$i++){$h->pages[$i?'txn_PAGE'.($i-1):'']=['object'=>'list','data'=>[payoutBalance('txn_PAGE'.$i,null,100,0,'adjustment')],'has_more'=>true];}
payoutFails($h,'DONATION_PAYOUT_LIMIT');check(count($h->calls)===13,'ten-page cap, no partial result');
$h=payoutHttp();$h->pages['']['data']=[];$h->pages['']['has_more']=true;payoutFails($h);
$h=payoutHttp();$h->pages['']['data']=array_fill(0,101,payoutBalance('txn_BIG',null,100,0,'adjustment'));payoutFails($h);
foreach(['pending','in_transit','failed','canceled']as$state){
    $h=payoutHttp();$h->responses[$pp]['status']=$state;
    if(in_array($state,['failed','canceled'],true)){$h->responses[$pp]['failure_balance_transaction']='txn_FAILURE';
        $h->responses['/v1/balance_transactions/txn_FAILURE']=payoutBalance('txn_FAILURE','po_SYNTHETIC',10000,0,$state==='failed'?'payout_failure':'payout_cancel');}
    $r=payoutRead($h);check(!$r['provider_paid_observed']&&in_array('payout_not_paid',$r['unresolved'],true),'status not bank success '.$state);
    if(in_array($state,['failed','canceled'],true))check($r['failure_balance']['gross_minor']==='10000','failed payout return retained');
}
foreach([['automatic'=>false],['method'=>'instant'],['reconciliation_status'=>'not_applicable'],['reconciliation_status'=>'in_progress']]as$change){
    $h=payoutHttp();$h->responses[$pp]=array_replace($h->responses[$pp],$change);$r=payoutRead($h);
    check(!$r['membership_complete']&&!$r['batch_totals_reconciled']&&$r['items']===[],'unsupported composition is not invented');
    check(!in_array('/v1/balance_transactions',array_column($h->calls,'path'),true),'no unsupported batch query');
}
$h=payoutHttp();$h->responses[$pp]['reversed_by']='po_REVERSE';check(in_array('payout_reversal_requires_reconciliation',payoutRead($h)['unresolved'],true),'reversed paid is explicitly unresolved');
$h=payoutHttp();$h->responses[$pp]['balance_transaction']=null;$r=payoutRead($h);check(!$r['batch_totals_reconciled']&&in_array('payout_balance_missing',$r['unresolved'],true),'missing debit never zero');
$h=payoutHttp();$h->responses[$pp]['status']='failed';check(in_array('payout_failure_balance_missing',payoutRead($h)['unresolved'],true),'missing failure credit explicit');
foreach(['source'=>'po_WRONG','id'=>'txn_WRONG','type'=>'charge','amount'=>-9999,'fee'=>301]as$key=>$value){$h=payoutHttp();$h->responses[$bp][$key]=$value;payoutFails($h);}
$h=payoutHttp();$h->pages['']['data'][0]['amount']++;$h->pages['']['data'][0]['net']++;
$r=payoutRead($h);check(!$r['batch_totals_reconciled']&&in_array('payout_batch_totals_unreconciled',$r['unresolved'],true),'mismatched totals retained but unconfirmed');
$h=payoutHttp();$h->pages['']['data'][2]['currency']='eur';$r=payoutRead($h);
check(count($r['funding_totals'])===2&&!$r['batch_totals_reconciled'],'foreign money not omitted or netted against HUF');
// Provider-native payout and balance FX remain separate, no arithmetic with exchange_rate.
$h=payoutHttp('eur');$h->responses[$pp]['currency']='huf';$h->responses[$pp]['amount']=4000000;$h->responses[$bp]['exchange_rate']=0.0025;
$r=payoutRead($h);check($r['batch_totals_reconciled']&&$r['payout']['currency']==='HUF'&&$r['payout_balance']['currency']==='EUR','actual native sides preserved');
$h->responses[$bp]['exchange_rate']=null;payoutFails($h);
// FX cannot reverse debit/return direction, including negative reversal payouts.
foreach([1,-1]as$sign){
    $h=payoutHttp('eur');$h->responses[$pp]['currency']='huf';$h->responses[$pp]['amount']=$sign*4000000;
    $h->responses[$pp]['original_payout']=$sign<0?'po_ORIGINAL':null;
    $h->responses[$bp]=payoutBalance('txn_PAYOUT','po_SYNTHETIC',-$sign*10000,0,'payout','eur');
    $h->responses[$bp]['exchange_rate']=0.0025;
    $h->pages['']['data']=[payoutBalance('txn_FUNDING',null,$sign*10000,0,'adjustment','eur')];
    check(payoutRead($h)['batch_totals_reconciled'],'FX debit direction valid for payout sign '.$sign);
    $h->responses[$bp]=payoutBalance('txn_PAYOUT','po_SYNTHETIC',$sign*10000,0,'payout','eur');
    $h->responses[$bp]['exchange_rate']=0.0025;
    $h->pages['']['data']=[payoutBalance('txn_FUNDING',null,-$sign*10000,0,'adjustment','eur')];
    payoutFails($h); // Opposite funding total must not manufacture reconciliation.
    $h->responses[$bp]=payoutBalance('txn_PAYOUT','po_SYNTHETIC',-$sign*10000,0,'payout','eur');
    $h->responses[$bp]['exchange_rate']=0.0025;
    $h->responses[$pp]['status']='failed';$h->responses[$pp]['failure_balance_transaction']='txn_FAILURE';
    $fp='/v1/balance_transactions/txn_FAILURE';
    $h->responses[$fp]=payoutBalance('txn_FAILURE','po_SYNTHETIC',$sign*10000,0,'payout_failure','eur');
    $h->responses[$fp]['exchange_rate']=0.0025;
    check(payoutRead($h)['failure_balance']['gross_minor']===(string)($sign*10000),'FX failure return valid for payout sign '.$sign);
    $h->responses[$fp]=payoutBalance('txn_FAILURE','po_SYNTHETIC',-$sign*10000,0,'payout_failure','eur');
    $h->responses[$fp]['exchange_rate']=0.0025;payoutFails($h);
}

$h=payoutHttp();$h->pages['']['data'][0]['status']='pending';check(in_array('batch_balance_pending',payoutRead($h)['unresolved'],true),'pending funds not free money');
foreach(['object'=>'customer','id'=>'acct_OTHER','livemode'=>true]as$key=>$value){$h=payoutHttp();$h->responses['/v1/account'][$key]=$value;payoutFails($h);check(count($h->calls)===1,'bad account stops before payout');}
foreach(['id'=>'po_OTHER','livemode'=>true,'amount'=>'10000','automatic'=>'true','status'=>'unknown']as$key=>$value){$h=payoutHttp();$h->responses[$pp][$key]=$value;payoutFails($h);}
$h=payoutHttp();unset($h->responses[$pp]['failure_balance_transaction']);payoutFails($h);
$h=payoutHttp();$count=0;$h->mutate=static function($path,$http)use(&$count,$pp):void{if($path===$pp&&++$count===2)$http->responses[$pp]['status']='failed';};payoutFails($h);
$h=payoutHttp();unset($h->responses[$bp]);$prior=Stripe\ApiRequestor::httpClient();payoutFails($h);check(Stripe\ApiRequestor::httpClient()===$prior,'failure restores client');
$h=payoutHttp();fails(fn()=>payoutRead($h,'connected',null,true),'DONATION_PAYOUT_UNAVAILABLE');check($h->calls===[],'keymode before HTTP');
$c=new Stripe\StripeClient(['api_key'=>'rk_test_SYNTHETICNOTACREDENTIAL','stripe_version'=>Sharity_Stripe_Checkout_Adapter::API_VERSION,'stripe_account'=>'acct_OTHER','max_network_retries'=>0]);
$a=new Sharity_Stripe_Checkout_Adapter($c);$h=payoutHttp();fails(fn()=>payoutRead($h,'platform',$a),'DONATION_PAYOUT_UNAVAILABLE');check($h->calls===[],'preconfigured account rejected');
$h=payoutHttp();fails(fn()=>sharity_donation_webhook_bounded_read(fn()=>$adapter->retrievePayoutEvidence('po_SYNTHETIC','acct_SYNTHETIC',false,'connected'),0,$h),'DONATION_PAYOUT_UNAVAILABLE');check($h->calls===[],'expired common deadline before HTTP');
$h=payoutHttp();fails(fn()=>sharity_donation_webhook_bounded_read(fn()=>$adapter->retrievePayoutEvidence('po_SYNTHETIC','acct_SYNTHETIC',false,'connected'),hrtime(true)/1e9+60,$h,null,4),'DONATION_PAYOUT_UNAVAILABLE');check(count($h->calls)===4,'shared cap cannot be restarted');
check(isset(WP_CLI::$commands['sharity donations payout-inspect']),'trusted CLI registered');
$opts=['account'=>'acct_SYNTHETIC','mode'=>'test','payout'=>'po_SYNTHETIC','scope'=>'connected'];$h=payoutHttp();WP_CLI::$lines=[];
sharity_donation_cli_payout_inspect([],$opts,$h);$r=json_decode(WP_CLI::$lines[0],true);
check($r['batch_totals_reconciled']&&$wpdb->writes===0,'actual CLI result with no DB writes');
foreach([[[],[]],[['extra'],$opts],[[],$opts+['extra'=>true]],[[],array_replace($opts,['mode'=>'TEST'])],[[],array_replace($opts,['payout'=>'po_/bad'])]]as[$args,$assoc]){
    $h=payoutHttp();fails(fn()=>sharity_donation_cli_payout_inspect($args,$assoc,$h),'DONATION_PAYOUT_UNAVAILABLE');check($h->calls===[],'invalid CLI no provider');
}
foreach([['ac'=>0,'tx'=>0],['ac'=>1,'tx'=>1]]as$tx){$wpdb->transaction=$tx;$h=payoutHttp();fails(fn()=>sharity_donation_cli_payout_inspect([],$opts,$h),'DONATION_PAYOUT_UNAVAILABLE');check($h->calls===[],'CLI refuses caller transaction');}
$wpdb->transaction=['ac'=>1,'tx'=>0];check($wpdb->writes===0,'all cases read-only');
echo 'Donation payout evidence: '.($checks-$start)." checks; actual SDK and CLI with offline HTTP; no live payout acceptance\n";
