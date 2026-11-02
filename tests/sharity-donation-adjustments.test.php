<?php
declare(strict_types=1);
// Reuse the real SDK, synthetic fixture and existing regression/CLI boundary suite.
require __DIR__ . '/sharity-donation-finance-read.test.php';
$checks = 0;
final class AdjustmentHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses = []; public array $calls = []; public $modify = null;
    public function request($method, $url, $headers, $params, $hasFile, $apiMode='v1', $maxNetworkRetries=null) {
        $path=parse_url($url,PHP_URL_PATH); $headers=array_values(array_filter($headers,static fn($h)=>!str_starts_with(strtolower($h),'authorization:')));
        $this->calls[]=compact('method','path','headers','params','maxNetworkRetries');
        $key=$path.(isset($params['starting_after'])?'#'.$params['starting_after']:'');
        if(!array_key_exists($key,$this->responses)){throw new RuntimeException('PRIVATE_UNEXPECTED_REQUEST');}
        $r=$this->responses[$key];if($this->modify!==null){$r=($this->modify)($r,$path,$params,count($this->calls));}
        return [json_encode($r,JSON_THROW_ON_ERROR),200,[]];
    }
}
function alist(array $data=[],bool $more=false):array{return ['object'=>'list','data'=>$data,'has_more'=>$more];}
function abalance(string $id,string $source,int $amount,int $fee=0,string $type='refund',string $currency='huf'):array{
    return ['object'=>'balance_transaction','id'=>$id,'source'=>$source,'type'=>$type,'amount'=>$amount,'fee'=>$fee,'net'=>$amount-$fee,
        'currency'=>$currency,'status'=>'available','created'=>1800000040,'available_on'=>1800000040,'exchange_rate'=>null,
        'fee_details'=>$fee===0?[]:[['type'=>'stripe_fee','amount'=>$fee,'currency'=>$currency,'description'=>'PRIVATE_DESCRIPTION']]];
}
function arefund(string $id='re_ONE',int $amount=1000,string $status='succeeded',string $currency='huf'):array{
    return ['object'=>'refund','id'=>$id,'charge'=>'ch_SYNTHETIC','payment_intent'=>'pi_SYNTHETIC','amount'=>$amount,
        'currency'=>$currency,'created'=>1800000040,'status'=>$status,'balance_transaction'=>abalance('txn_'.str_replace('_','',$id),$id,-$amount,0,'refund',$currency),
        'failure_balance_transaction'=>null,'receipt_number'=>'PRIVATE_RECEIPT'];
}
function abase(string $mode='direct',string $currency='HUF'):array{
    [$e,$p]=fixture($mode,$currency);$p['/v1/refunds']=alist();$p['/v1/disputes']=alist();
    if($mode==='amplifier'){$p['/v1/application_fees/fee_SYNTHETIC/refunds']=alist();$p['/v1/balance_transactions']=alist();}return [$e,$p];
}
function adjustmentRead(array $e,array $p,?callable $modify=null):array{
    global $transport,$adapter;
    // Keep the inline summary consistent with synthetic paginated collections by default.
    // Contradictory summaries are injected explicitly by the modify callback below.
    foreach (['/v1/refunds'=>['/v1/charges/ch_SYNTHETIC','refund','re'], '/v1/application_fees/fee_SYNTHETIC/refunds'=>['/v1/application_fees/fee_SYNTHETIC','fee_refund','fr']] as $list=>[$parent,$object,$prefix]) {
        if (!isset($p[$parent])) { continue; } $count=0;
        foreach ($p as $key=>$page) { if (($key===$list || str_starts_with($key,$list.'#')) && is_array($page['data']??null)) { $count+=count($page['data']); } }
        $inline=[]; for($i=0;$i<min(10,$count);$i++) { $inline[]=['id'=>$prefix.'_INLINE'.$i,'object'=>$object]; }
        $p[$parent]['refunds']=alist($inline,$count>10);
    }
    $transport=new AdjustmentHttp();$transport->responses=$p;$transport->modify=$modify;
    Stripe\ApiRequestor::setHttpClient($transport);
    $adapter=new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client('rk_test_SYNTHETICNOTACREDENTIAL'));
    return $adapter->retrieveAdjustmentEvidence('cs_test_SYNTHETIC',$e);
}
function adjustmentFails(array $e,array $p,?callable $modify=null):void{fails(static fn()=>adjustmentRead($e,$p,$modify),'DONATION_ADJUSTMENT_EVIDENCE_UNAVAILABLE');}
$R='/v1/refunds';$D='/v1/disputes';$FR='/v1/application_fees/fee_SYNTHETIC/refunds';
foreach(['direct','amplifier'] as $mode){foreach(['HUF','EUR','USD','GBP'] as $currency){
    [$e,$p]=abase($mode,$currency);$r=adjustmentRead($e,$p);
    check($r['resources_complete'] && $r['unresolved']===[] && !$r['atomic_snapshot'],'complete fetched lists are not atomic');
    check(!$r['allocation_eligible'] && !$r['payment_verified'] && !$r['settlement_reconciled'],'no accounting permission');
    check($r['original']['payment']['amount_minor']===$e['snapshot']['amount_minor'] && count($transport->calls)===($mode==='direct'?8:15),'units and bounded base readback');
    foreach($transport->calls as $call){
        check($call['method']==='get' && $call['maxNetworkRetries']===0,'GET only no retry');
        check(in_array('Stripe-Version: 2026-10-28.endive',$call['headers'],true),'pinned API');
        $platform=str_contains($call['path'],'application_fees')||$call['path']===$FB||$call['path']==='/v1/account';
        $scope=array_values(array_filter($call['headers'],static fn($h)=>str_starts_with($h,'Stripe-Account:')));
        check($scope===($platform?[]:['Stripe-Account: acct_SYNTHETIC']),'right account per call');
        check(!array_filter($call['headers'],static fn($h)=>str_starts_with($h,'Idempotency-Key:')),'no creation key');
    }
    check(!str_contains(json_encode($r),'PRIVATE_'),'minimal fields only');
}}
[$e,$p]=abase('amplifier');$accountReads=0;
adjustmentFails($e,$p,static function($r,$path)use(&$accountReads){
    if($path==='/v1/account' && ++$accountReads===2){$r['id']='acct_CHANGED';}return $r;
});
check($accountReads===2,'second original read verifies same platform identity');
[$e,$p]=abase();$p[$C]['amount_refunded']=600;
$p[$R]=alist([arefund('re_A',100)],true);$p[$R.'#re_A']=alist([arefund('re_B',200)],true);$p[$R.'#re_B']=alist([arefund('re_C',300)]);
$r=adjustmentRead($e,$p);check(count($r['refunds'])===3 && $r['unresolved']===[],'all pages retained, cumulative refund matches');
$listCalls=array_values(array_filter($transport->calls,static fn($c)=>$c['path']==='/v1/refunds'));
check($listCalls[1]['params']['starting_after']==='re_A' && $listCalls[2]['params']['limit']===100,'explicit pagination cursor/limit');
check($listCalls[0]['params']['expand']===['data.balance_transaction','data.failure_balance_transaction'],'both refund movements requested');
[$e,$p]=abase();$rf=arefund(status:'failed');$rf['failure_balance_transaction']=abalance('txn_RETURN','re_ONE',1000,-50,'refund_failure');
$p[$R]=alist([$rf]);$r=adjustmentRead($e,$p);
check($r['refunds'][0]['balance']['net_minor']==='-1000' && $r['refunds'][0]['failure_balance']['net_minor']==='1050','signed debit and reversal credit/fee');
check($r['refunds'][0]['failure_balance']['processing_cost_minor']==='-50' && $r['unresolved']===[],'signed fee reversal retained');
foreach(['adjustment','payment_refund','payment_failure_refund'] as $type){$q=$p;$q[$R]['data'][0]['failure_balance_transaction']['type']=$type;check(adjustmentRead($e,$q)['resources_complete'],'documented failure types');}
foreach(['pending','requires_action'] as $state){$q=$p;$q[$R]['data'][0]['status']=$state;$q[$R]['data'][0]['failure_balance_transaction']=null;$q[$R]['data'][0]['balance_transaction']=null;$r=adjustmentRead($e,$q);check(in_array('refund_unresolved',$r['unresolved'],true),'pending not settled');}
$q=$p;$q[$R]['data'][0]['failure_balance_transaction']=null;check(in_array('refund_reversal_missing',adjustmentRead($e,$q)['unresolved'],true),'missing reversal explicit');
[$e,$p]=abase();$p[$C]['amount_refunded']=1000;$p[$R]=alist([arefund()]);
foreach([null,'txn_NOTEXPANDED'] as $missing){$q=$p;$q[$R]['data'][0]['balance_transaction']=$missing;check(in_array('refund_balance_missing',adjustmentRead($e,$q)['unresolved'],true),'missing balance not zero money');}
$q=$p;unset($q[$R]['data'][0]['failure_balance_transaction']);check(in_array('balance_field_missing',adjustmentRead($e,$q)['unresolved'],true),'absent nullable field is not inferred empty');
$q=$p;$q[$R]['data'][0]['balance_transaction']['status']='pending';check(adjustmentRead($e,$q)['balances_pending'],'pending balance flagged');
// Cross-currency signs and native settlement values; no reference-rate arithmetic.
$q=$p;$q[$R]['data'][0]['balance_transaction']=abalance('txn_FX','re_ONE',-250,-5,'refund','eur');$q[$R]['data'][0]['balance_transaction']['exchange_rate']=0.25;
$r=adjustmentRead($e,$q);check($r['refunds'][0]['balance']['net_minor']==='-245' && $r['refunds'][0]['balance']['currency']==='EUR','separate native FX balance');
$bad=$q;$bad[$R]['data'][0]['balance_transaction']['amount']=250;$bad[$R]['data'][0]['balance_transaction']['net']=255;adjustmentFails($e,$bad);
$bad=$q;$bad[$R]['data'][0]['balance_transaction']['exchange_rate']=null;adjustmentFails($e,$bad);
$dispute=['object'=>'dispute','id'=>'du_ONE','charge'=>'ch_SYNTHETIC','payment_intent'=>'pi_SYNTHETIC','livemode'=>false,'amount'=>60000,'currency'=>'eur','created'=>1800000040,'status'=>'won',
    'balance_transactions'=>[abalance('txn_DISPUTE','du_ONE',-50000,1500,'adjustment'),abalance('txn_WON','du_ONE',50000,0,'adjustment')],'evidence'=>['customer_email_address'=>'PRIVATE_EMAIL']];
[$e,$p]=abase();$p[$D]=alist([$dispute]);$r=adjustmentRead($e,$p);
check(count($r['disputes'][0]['balances'])===2 && $r['unresolved']===[],'won dispute read even current disputed flag false');
check($r['disputes'][0]['amount_minor']==='60000' && $r['disputes'][0]['currency']==='EUR','nominal dispute can exceed payment and differ currency');
check(!str_contains(json_encode($r),'PRIVATE_'),'dispute evidence PII not projected');
foreach(['needs_response','under_review','appeal_needs_response','appeal_under_review','warning_needs_response','warning_under_review','lost','warning_closed','prevented'] as $state){
 $q=$p;$q[$D]['data'][0]['status']=$state;$r=adjustmentRead($e,$q);check($r['resources_complete'],'supported dispute status');
 check(in_array('dispute_open',$r['unresolved'],true)===!in_array($state,['lost','warning_closed','prevented'],true),'open dispute blocks resolution');
}
$q=$p;$q[$D]['data'][0]['balance_transactions']=[];check(in_array('dispute_balance_missing',adjustmentRead($e,$q)['unresolved'],true),'terminal dispute without movements not final');
[$e,$p]=abase('amplifier');$p[$F]['amount_refunded']=200;
$p[$FR]=alist([['object'=>'fee_refund','id'=>'fr_ONE','fee'=>'fee_SYNTHETIC','amount'=>200,'currency'=>'huf','created'=>1800000040,
 'balance_transaction'=>abalance('txn_FEEREFUND','fr_ONE',-200,0,'application_fee_refund')]]);
$r=adjustmentRead($e,$p);check($r['fee_refunds'][0]['balance']['scope']==='platform' && !$r['fee_refunds'][0]['connected_credit_verified'],'platform debit does not synthesize connected credit');
check(in_array('fee_refund_connected_credit_unreconciled',$r['unresolved'],true),'missing counterpart blocks settlement');
// Same Stripe transaction ID is account-scoped, not globally unique.
$q=$p;$q[$FR]['data'][0]['balance_transaction']['id']='txn_CHARGE';check(adjustmentRead($e,$q)['resources_complete'],'same id allowed across distinct accounts');
$bad=$p;$bad[$FR]['data'][0]['fee']='fee_OTHER';adjustmentFails($e,$bad);
$bad=$p;$bad[$FR]['data'][0]['amount']=201;adjustmentFails($e,$bad);
$bad=$p;$bad[$FR]['data'][0]['balance_transaction']['source']='fr_OTHER';adjustmentFails($e,$bad);
// D2q: only an actual positive NGO balance with exact refund source proves the counterleg.
$qStart=$checks;$CL='/v1/balance_transactions';$q=$p;
$q[$CL]=alist([abalance('txn_CREDIT','fr_ONE',200,0,'application_fee_refund')]);
$got=adjustmentRead($e,$q);$credit=$got['fee_refunds'][0]['connected_credit'];
check($got['fee_refunds'][0]['connected_credit_verified']&&$credit['gross_minor']==='200'&&$credit['scope']==='connected'&&$credit['account_id']==='acct_SYNTHETIC','sanitized actual credit retained, not only a boolean');
check(!in_array('fee_refund_connected_credit_unreconciled',$got['unresolved'],true)&&!str_contains(json_encode($got),'PRIVATE_'),'exact source proof and no PII');
$clCalls=array_values(array_filter($transport->calls,static fn($c)=>$c['path']===$CL));
check(count($clCalls)===1&&$clCalls[0]['params']===['source'=>'fr_ONE','limit'=>2],'single exact-source bounded GET');
check($clCalls[0]['method']==='get'&&$clCalls[0]['maxNetworkRetries']===0&&in_array('Stripe-Account: acct_SYNTHETIC',$clCalls[0]['headers'],true)&&in_array('Stripe-Version: 2026-10-28.endive',$clCalls[0]['headers'],true),'frozen account/pinned API/no POST/retry');
check(!array_filter($clCalls[0]['headers'],static fn($h)=>str_starts_with($h,'Idempotency-Key:')),'no checkout idempotency key on credit read');
foreach([alist(),alist([$q[$CL]['data'][0],array_replace($q[$CL]['data'][0],['id'=>'txn_OTHER'])]),alist([$q[$CL]['data'][0]],true),alist([array_replace($q[$CL]['data'][0],['type'=>'payout'])])] as $candidate){
 $z=$q;$z[$CL]=$candidate;$got=adjustmentRead($e,$z);check(!$got['fee_refunds'][0]['connected_credit_verified']&&$got['fee_refunds'][0]['connected_credit']===null&&in_array('fee_refund_connected_credit_unreconciled',$got['unresolved'],true),'missing/ambiguous/unrecognized proof remains unresolved');
}
foreach([['source','fr_OTHER'],['id','txn_CHARGE'],['amount',-200],['amount',201],['currency','eur'],['net',201]]as[$field,$value]){
 $z=$q;$z[$CL]['data'][0][$field]=$value;adjustmentFails($e,$z);
}
$z=$q;unset($z[$CL]['has_more']);adjustmentFails($e,$z);
adjustmentFails($e,$q,static function($r,$path)use($CL){if($path===$CL){throw new RuntimeException('PRIVATE_PROVIDER_ERROR');}return$r;});
$z=$q;$z[$CL]['data'][0]=abalance('txn_CREDIT','fr_ONE',200,5,'adjustment');$got=adjustmentRead($e,$z);
check($got['fee_refunds'][0]['connected_credit']['net_minor']==='195'&&$got['fee_refunds'][0]['connected_credit']['processing_cost_minor']==='5','actual fee retained, not assumed zero');
$z=$q;$z[$CL]['data'][0]=abalance('txn_CREDIT','fr_ONE',1,0,'adjustment','eur');$z[$CL]['data'][0]['exchange_rate']=0.005;$got=adjustmentRead($e,$z);
check($got['fee_refunds'][0]['connected_credit']['currency']==='EUR'&&$got['fee_refunds'][0]['connected_credit']['gross_minor']==='1','actual FX bucket retained without synthesizing money');
$z=$q;$z[$CL]['data'][0]['status']='pending';check(adjustmentRead($e,$z)['balances_pending'],'pending credit remains pending');
foreach([20,21]as$n){
 [$be,$bp]=abase('amplifier');$bp[$F]['amount_refunded']=$n;$bp[$FR]=alist();
 for($i=0;$i<$n;$i++){$bp[$FR]['data'][]=['object'=>'fee_refund','id'=>'fr_B'.$i,'fee'=>'fee_SYNTHETIC','amount'=>1,'currency'=>'huf','created'=>1800000040,'balance_transaction'=>abalance('txn_B'.$i,'fr_B'.$i,-1,0,'application_fee_refund')];}
 $got=adjustmentRead($be,$bp);check(count(array_filter($transport->calls,static fn($c)=>$c['path']===$CL))===($n===20?20:0),'bounded whole counterleg batch, no truncated success');
 check(in_array('fee_refund_counterleg_read_limit',$got['unresolved'],true)===($n>20),'explicit excess read budget');
}
foreach(['direct','amplifier']as$mode){[$be,$bp]=abase($mode);adjustmentRead($be,$bp);check(!array_filter($transport->calls,static fn($c)=>$c['path']===$CL),'no imaginary counterleg GET when no fee refund');}
echo 'D2q SDK counterleg: '.($checks-$qStart)." checks\n";
// Malformed lists, wrong economic linkage, foreign balance/source, floats and stale aggregates fail closed.
[$e,$p]=abase();$p[$C]['amount_refunded']=1000;$p[$R]=alist([arefund()]);
$cases=[[$R,['object'],'refund'],[$R,['has_more'],null],[$R,['data'],null],[$R,['data',0,'id'],'re_bad/path'],[$R,['data',0,'object'],'charge'],
 [$R,['data',0,'charge'],'ch_OTHER'],[$R,['data',0,'payment_intent'],'pi_OTHER'],[$R,['data',0,'amount'],'1000'],[$R,['data',0,'amount'],1000.5],
 [$R,['data',0,'currency'],'eur'],[$R,['data',0,'created'],0],[$R,['data',0,'status'],'invented'],
 [$R,['data',0,'balance_transaction','id'],'txn_CHARGE'],[$R,['data',0,'balance_transaction','source'],'re_OTHER'],
 [$R,['data',0,'balance_transaction','net'],-999],[$R,['data',0,'balance_transaction','fee_details'],[['amount'=>1,'currency'=>'huf','type'=>'stripe_fee']]],
 [$R,['data',0,'balance_transaction','type'],'payout'],[$C,['amount_refunded'],999]];
foreach($cases as [$path,$keys,$value]){$q=$p;$ref=&$q[$path];foreach($keys as $key){$ref=&$ref[$key];}$ref=$value;unset($ref);adjustmentFails($e,$q);}
$q=$p;$q[$R]['data'][]=$q[$R]['data'][0];adjustmentFails($e,$q);
$q=$p;$q[$R]=alist([],true);adjustmentFails($e,$q);
$q=$p;$q[$R]['has_more']=true;$q[$R.'#re_ONE']=$q[$R];adjustmentFails($e,$q);
// Contradictory inline count with zero monetary aggregate must not be accepted.
[$ce,$cp]=abase();
adjustmentFails($ce,$cp,static function($r,$path)use($C){if($path===$C){$r['refunds']=alist([['object'=>'refund','id'=>'re_MISSING']]);}return $r;});
[$ce,$cp]=abase('amplifier');
adjustmentFails($ce,$cp,static function($r,$path)use($F){if($path===$F){$r['refunds']=alist([],true);}return $r;});
// Cap ten pages without accepting a truncated collection.
$q=$p;for($i=0;$i<10;$i++){$key=$R.($i===0?'':'#re_P'.($i-1));$q[$key]=alist([arefund('re_P'.$i,1)],true);}
adjustmentFails($e,$q);check(count(array_filter($transport->calls,static fn($c)=>$c['path']==='/v1/refunds'))===10,'bounded page budget');
adjustmentFails($e,$p,static function($r,$path,$params,$count)use($C){if($path===$C&&$count>5){$r['amount_refunded']=1100;}return $r;});
adjustmentFails($e,$p,static function($r,$path){if($path==='/v1/refunds'){throw new RuntimeException('PRIVATE_PROVIDER_ERROR');}return $r;});
[$e,$p]=abase();$p[$S]['payment_status']='unpaid';$r=adjustmentRead($e,$p);check(!$r['resources_complete']&&count($transport->calls)===1,'unpaid no adjustment network');
// Existing CLI owner/order checks and new flag work together; no globals/argv parsing.
[$e,$p]=abase('amplifier');adjustmentRead($e,$p);$transport->calls=[];$wpdb->row=$stored;$wpdb->transaction=['ac'=>1,'tx'=>0];$factoryCalls=0;
sharity_donation_cli_payment_inspect([],['adjustments'=>true,'order'=>$e['order_id']]);
$last=json_decode(end(WP_CLI::$lines),true,64,JSON_THROW_ON_ERROR);check($last['scope']==='observed_adjustment_resources' && $wpdb->writes===0,'CLI opt-in reaches reader with no write');
foreach([false,'true','1',1,null] as $flag){$n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect([],['order'=>$e['order_id'],'adjustments'=>$flag]));check($factoryCalls===$n,'bad flag rejected before SDK');}
$wpdb->transaction=['ac'=>1,'tx'=>1];$n=$factoryCalls;fails(static fn()=>sharity_donation_cli_payment_inspect([],['order'=>$e['order_id'],'adjustments'=>true]));check($factoryCalls===$n,'adjustments outside DB transactions only');
echo "PASS donation adjustment reader: {$checks} checks; real SDK/offline HTTP, no ledger or live acceptance\n";
