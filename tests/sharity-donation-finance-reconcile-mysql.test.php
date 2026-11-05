<?php
/** Included in disposable MariaDB harness. Fixtures are projected facts, never live money. */
declare(strict_types=1);
$d2iStart = $checks;
function reconcileFixture(array $order, array $data): array {
    global $wpdb, $now;
    $s = sharity_donation_order_decode($order)['snapshot'];
    $json = sharity_donation_finance_json($data);
    $row = ['order_id'=>$order['order_id'],'account_id'=>$s['account_id'],'livemode'=>$s['livemode']?'1':'0',
        'donation_mode'=>$s['donation_mode'],'provider_session_id'=>$order['provider_session_id'],
        'canonical_json'=>$json,'digest'=>hash('sha256',$json),'observed_at_utc'=>gmdate('Y-m-d H:i:s',$now)];
    check($wpdb->insert(sharity_donation_finance_table(),$row,[])===1,'insert synthetic projected observation');
    return sharity_donation_finance_read($order['order_id'],$row['digest']);
}
function removeReconcileFixture(array $row): void {
    global $wpdb; check($wpdb->query($wpdb->prepare('DELETE FROM `'.sharity_donation_finance_table().'` WHERE id = %s',$row['id']))===1,'remove only own fixture');
}
$d2iOrder=financeOrder('reconcile'); $d2iId=$d2iOrder['order_id'];
$empty=sharity_donation_finance_reconcile($d2iId,$now);
check($empty['observation_count']===0 && $empty['movements']===[] && in_array('observations_missing',$empty['unresolved'],true),'absence is explicit, not zero money');
$receipt=sharity_donation_finance_record($d2iId,$adapter,$now);
$d2iSaved=sharity_donation_finance_read($d2iId,$receipt['digest']);
$d2iData=json_decode($d2iSaved['canonical_json'],true,64,JSON_THROW_ON_ERROR);
$gets=$financeHttp->gets;$posts=calls();$factory=$runtimeFactoryCalls;$count=financeCount();$opts=$options;
$financeHttp->drop=true; // Any SDK call now fails; DB read still succeeds.
$wpdb->db->query('START TRANSACTION READ ONLY');
try {$initial=sharity_donation_finance_reconcile($d2iId,$now);sharity_donation_cli_payment_reconcile([],['order'=>$d2iId]);}
finally {$wpdb->db->query('ROLLBACK');$financeHttp->drop=false;}
check($financeHttp->gets===$gets && calls()===$posts && $runtimeFactoryCalls===$factory && financeCount()===$count && $options===$opts,'read-only SQL transaction, zero SDK/HTTP/flag/row writes');
check(json_decode(end(WP_CLI::$lines),true)===$initial,'CLI actual inventory output');
check(count($initial['movements'])===1 && $initial['unscoped_platform_movements']===[],'one original movement');
$m=$initial['movements'][0];
check($m['facts']['account_id']==='acct_SYNTHETIC' && $m['facts']['gross_minor']==='50000' && $m['facts']['net_minor']==='48000','native exact facts, real account scope');
foreach(['payment_verified','allocation_eligible','settlement_reconciled','apply_supported','atomic_snapshot'] as $flag){check($initial[$flag]===false,'never acceptance: '.$flag);}
check(!str_contains(json_encode($initial),'subject_ref') && !isset($initial['ngo_minor']),'no person or payable totals');
foreach ([[[],[]],[[$d2iId],[]],[[],['order'=>'bad']],[[],['order'=>$d2iId,'apply'=>true]],[[],['order'=>$d2iId,'apply'=>'digest']],[[],['order'=>$d2iId,'session'=>'cs_other']]] as [$args,$assoc]) {
    $lines=count(WP_CLI::$lines);fails(fn()=>sharity_donation_cli_payment_reconcile($args,$assoc),'DONATION_RECONCILE_UNAVAILABLE');
    check(count(WP_CLI::$lines)===$lines && $runtimeFactoryCalls===$factory && $financeHttp->gets===$gets,'invalid CLI gives no output or provider call');
}
// Replays with changing status/rate do not become a second financial movement.
$x=$d2iData;$x['original']['initial_charge_balance']['status']='pending';$x['original']['initial_charge_balance']['available_on']++;
$x['original']['initial_charge_balance']['exchange_rate']=1.0;
$extra=reconcileFixture($d2iOrder,$x);$r=sharity_donation_finance_reconcile($d2iId,$now);
check(count($r['movements'])===1 && count($r['movements'][0]['observations'])===2 && $r['financial_inventory_digest']===$initial['financial_inventory_digest'],'metadata change dedupes same native movement');
check($r['evidence_set_digest']!==$initial['evidence_set_digest'],'evidence version remains separately visible');
$before=$r;removeReconcileFixture($extra);$extra=reconcileFixture($d2iOrder,$x);
check(sharity_donation_finance_reconcile($d2iId,$now)===$before,'local IDs/insert order not provider chronology');removeReconcileFixture($extra);
// Financial mutation under an existing tuple is a conflict, never latest-wins.
foreach(['gross_minor','currency','source','initial_balance'] as $case) {
    $x=$d2iData;
    if($case==='gross_minor'){$x['original']['initial_charge_balance']['gross_minor']='50001';$x['original']['initial_charge_balance']['net_minor']='48001';}
    if($case==='currency'){$x['original']['initial_charge_balance']['currency']='EUR';$x['original']['initial_charge_balance']['fee_details'][0]['currency']='EUR';}
    if($case==='source'){$x['original']['charge_id']='ch_OTHER';}
    if($case==='initial_balance'){$x['original']['initial_charge_balance']['id']='txn_OTHER';}
    $extra=reconcileFixture($d2iOrder,$x);fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_CONFLICT');removeReconcileFixture($extra);
}
// Multi-component fees are unordered but multiplicity matters.
$x=$d2iData;removeReconcileFixture($d2iSaved);
$x['original']['initial_charge_balance']['fee_details']=[['amount_minor'=>'500','currency'=>'HUF','type'=>'stripe_fee'],['amount_minor'=>'500','currency'=>'HUF','type'=>'stripe_fee'],['amount_minor'=>'1000','currency'=>'HUF','type'=>'tax']];
$multi=reconcileFixture($d2iOrder,$x);$a=sharity_donation_finance_reconcile($d2iId,$now);
$x['original']['initial_charge_balance']['fee_details']=array_reverse($x['original']['initial_charge_balance']['fee_details']);
$extra=reconcileFixture($d2iOrder,$x);$b=sharity_donation_finance_reconcile($d2iId,$now);
check(count($b['movements'])===1 && $a['financial_inventory_digest']===$b['financial_inventory_digest'],'fee multiset sorted without removing duplicates');removeReconcileFixture($extra);removeReconcileFixture($multi);$d2iSaved=reconcileFixture($d2iOrder,$d2iData);
// Actual debit + refund reversal + dispute debit/credit. Nominal amounts are not movements.
function reconcileBalance(string $id,string $source,string $type,string $gross,string $fee='0',?string $scope='connected'):array {
    return ['id'=>$id,'source_id'=>$source,'type'=>$type,'scope'=>$scope,'currency'=>'HUF','gross_minor'=>$gross,'total_fee_minor'=>$fee,
        'net_minor'=>(string)((int)$gross-(int)$fee),'application_fee_minor'=>'0','processing_cost_minor'=>$fee,
        'fee_details'=>$fee==='0'?[]:[['amount_minor'=>$fee,'currency'=>'HUF','type'=>'stripe_fee']],
        'status'=>'available','created'=>1800000010,'available_on'=>1800600000,'exchange_rate'=>null];
}
$x=$d2iData;
$x['refunds']=[['id'=>'re_ONE','amount_minor'=>'1000','currency'=>'HUF','created'=>1800000010,'status'=>'failed',
    'balance'=>reconcileBalance('txn_REFUND','re_ONE','refund','-1000','-10'),
    'failure_balance'=>reconcileBalance('txn_REVERSAL','re_ONE','refund_failure','1000')]];
$x['disputes']=[['id'=>'dp_ONE','amount_minor'=>'50000','currency'=>'HUF','created'=>1800000010,'status'=>'won',
    'balances'=>[reconcileBalance('txn_DISPUTE','dp_ONE','adjustment','-50000','1500'),reconcileBalance('txn_REINSTATE','dp_ONE','adjustment','50000')]]];
$extra=reconcileFixture($d2iOrder,$x);$r=sharity_donation_finance_reconcile($d2iId,$now);
check(count($r['movements'])===5,'charge plus four actual signed movements, no nominal duplicate');
$byId=array_column(array_column($r['movements'],'facts'),null,'balance_transaction_id');
check($byId['txn_REFUND']['net_minor']==='-990' && $byId['txn_DISPUTE']['net_minor']==='-51500','signed fee/negative net retained');
$x['disputes'][0]['balances']=array_reverse($x['disputes'][0]['balances']);
check(sharity_donation_finance_json($x)===$extra['canonical_json'],'unordered resource sets canonical');
$x=$d2iData;$x['original']['initial_charge_balance']['status']='pending';$later=reconcileFixture($d2iOrder,$x);
check(count(sharity_donation_finance_reconcile($d2iId,$now)['movements'])===5,'later observation omitting adjustments does not delete money');
removeReconcileFixture($later);removeReconcileFixture($extra);
// Corrupt stored identity, digest and canonical bytes fail, even if JSON still parses.
foreach(['account_id'=>'acct_OTHER','livemode'=>'1','donation_mode'=>'amplifier','provider_session_id'=>'cs_test_OTHER',
    'digest'=>str_repeat('f',64),'observed_at_utc'=>'0000-00-00 00:00:00','canonical_json'=>' '.$d2iSaved['canonical_json']] as $field=>$bad){
    $wpdb->query($wpdb->prepare('UPDATE `'.$financeTable.'` SET `'.$field.'`=%s WHERE id=%s',$bad,$d2iSaved['id']));
    fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_UNAVAILABLE');
    $wpdb->query($wpdb->prepare('UPDATE `'.$financeTable.'` SET `'.$field.'`=%s WHERE id=%s',$d2iSaved[$field],$d2iSaved['id']));
}
foreach(['amount_float','missing_fee','wrong_scope','foreign_order','bad_sum','flag','wrong_sign'] as $case){
    $x=$d2iData;
    if($case==='amount_float'){$x['original']['initial_charge_balance']['gross_minor']=50000.0;}
    if($case==='missing_fee'){unset($x['original']['initial_charge_balance']['total_fee_minor']);}
    if($case==='wrong_scope'){$x['scope']='other';}
    if($case==='foreign_order'){$x['original']['order_id']='do_'.str_repeat('f',32);}
    if($case==='bad_sum'){$x['original']['initial_charge_balance']['fee_details'][0]['amount_minor']='1999';}
    if($case==='flag'){$x['allocation_eligible']=true;}
    if($case==='wrong_sign'){$x['original']['initial_charge_balance']['gross_minor']='-50000';$x['original']['initial_charge_balance']['net_minor']='-52000';}
    $extra=reconcileFixture($d2iOrder,$x);fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_UNAVAILABLE');removeReconcileFixture($extra);
}
// Amplifier platform facts never use the connected-account identifier.
$oldSnapshot=$snapshot;$snapshot=array_replace($snapshot,['donation_mode'=>'amplifier','votes'=>500,'package_id'=>'starter','price_book_version'=>'amplifier-net80-v1:HUF','policy_version'=>'amplifier-net80-v1']);
try{$ampOrder=financeOrder('reconcile-amp');}finally{$snapshot=$oldSnapshot;}
$x=$d2iData;$x['original']['order_id']=$ampOrder['order_id'];$x['original']['session_id']=$ampOrder['provider_session_id'];$x['original']['donation_mode']='amplifier';
$platform=reconcileBalance('txn_FINANCE','fee_ONE','application_fee','10000');unset($platform['scope'],$platform['source_id'],$platform['type']);
$x['original']['application_fee']=['id'=>'fee_ONE','amount_minor'=>'10000','currency'=>'HUF','refund_state'=>['amount_refunded_minor'=>'100'], 'initial_balance'=>$platform];
$x['fee_refunds']=[['id'=>'fr_ONE','fee_id'=>'fee_ONE','amount_minor'=>'100','currency'=>'HUF','created'=>1800000010,'connected_credit_verified'=>false,
    'balance'=>reconcileBalance('txn_FEEBACK','fr_ONE','application_fee_refund','-100','0','platform')]];
$ampSaved=reconcileFixture($ampOrder,$x);$r=sharity_donation_finance_reconcile($ampOrder['order_id'],$now);
check(count($r['movements'])===1 && count($r['unscoped_platform_movements'])===2 && in_array('platform_account_unverified',$r['unresolved'],true),'same txn ID distinct scopes, platform unresolved');
foreach($r['unscoped_platform_movements'] as $m){check($m['facts']['account_id']===null,'unknown account never invented');}
$x['original']['application_fee']['initial_balance']['id']='txn_OTHERFEE';
$extra=reconcileFixture($ampOrder,$x);fails(fn()=>sharity_donation_finance_reconcile($ampOrder['order_id'],$now),'DONATION_RECONCILE_CONFLICT');removeReconcileFixture($extra);
// A concurrent append does not alter this chosen evidence set; the next read includes it.
$x=$d2iData;$x['original']['initial_charge_balance']['available_on']+=777;
$second=new OrderDb((int)$port);$appended=null;
$wpdb->afterRead=function($sql,$row)use(&$appended,$second,$x,$d2iSaved){
    if(!str_contains($sql,'SUBSTRING(CAST'))return;
    $GLOBALS['wpdb']->afterRead=null;
    $new=$d2iSaved;unset($new['id']);$new['canonical_json']=sharity_donation_finance_json($x);$new['digest']=hash('sha256',$new['canonical_json']);
    check($second->insert(sharity_donation_finance_table(),$new,[])===1,'concurrent second connection appends evidence');$appended=$new;
};
check(sharity_donation_finance_reconcile($d2iId,$now)===$initial,'concurrent append does not create mixed observation set');
check(sharity_donation_finance_reconcile($d2iId,$now)['observation_count']===2,'next read sees appended evidence');
removeReconcileFixture(sharity_donation_finance_read($d2iId,$appended['digest']));$second->db->close();
// Reader detects SQL error, oversized body, total bytes and row limit, with no truncated result.
foreach(['SELECT id, OCTET_LENGTH','SUBSTRING(CAST'] as $needle){$wpdb->failOn=$needle;$wpdb->failure='before';fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_UNAVAILABLE');$wpdb->failOn='';$wpdb->failure='';}
$wpdb->query($wpdb->prepare('UPDATE `'.$financeTable.'` SET canonical_json=REPEAT(\'x\',%d) WHERE id=%s',SHARITY_DONATION_FINANCE_MAX_BYTES+1,$d2iSaved['id']));
fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_LIMIT');
$wpdb->query($wpdb->prepare('UPDATE `'.$financeTable.'` SET canonical_json=%s WHERE id=%s',$d2iSaved['canonical_json'],$d2iSaved['id']));
$largeIds=[];
for($i=0;$i<5;$i++){
    $digest=hash('sha256','total-limit-'.$i);
    check($wpdb->query($wpdb->prepare('INSERT INTO `'.$financeTable.'` (order_id,account_id,livemode,donation_mode,provider_session_id,canonical_json,digest,observed_at_utc) SELECT order_id,account_id,livemode,donation_mode,provider_session_id,REPEAT(\'x\',3500000),%s,observed_at_utc FROM `'.$financeTable.'` WHERE id=%s',$digest,$d2iSaved['id']))===1,'total-byte fixture');
    $largeIds[]=$wpdb->get_row($wpdb->prepare('SELECT id FROM `'.$financeTable.'` WHERE digest=%s',$digest),ARRAY_A);
}
fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_LIMIT');foreach($largeIds as $row){removeReconcileFixture($row);}
$ids=[];
for($i=0;$i<256;$i++){$x=$d2iData;$x['original']['initial_charge_balance']['available_on']+=10+$i;$ids[]=reconcileFixture($d2iOrder,$x);}
fails(fn()=>sharity_donation_finance_reconcile($d2iId,$now),'DONATION_RECONCILE_LIMIT');
foreach($ids as $row){removeReconcileFixture($row);}
check(sharity_donation_finance_reconcile($d2iId,$now)===$initial,'all corruption fixtures restored exact original inventory');
check(sharity_donation_order_read($subject,'order_id',$d2iId)===$d2iOrder,'read path never mutates stored order');
echo 'Donation reconciliation MariaDB: '.($checks-$d2iStart)." checks; read-only inventory, no settlement acceptance\n";
