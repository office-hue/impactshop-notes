<?php
/** Actual isolated MariaDB; no live account or money. */
declare(strict_types=1);
$targetStart=$checks;
function targetCount(?string $id=null):int {global $wpdb;return (int)$wpdb->db->query('SELECT COUNT(*) AS n FROM `'.sharity_donation_target_table().'`'.($id===null?'':$wpdb->prepare(' WHERE order_id=%s',$id)))->fetch_assoc()['n'];}
function targetFixture(string $tag):array {global $now;[$o,$data,$saved]=movementFixture('target'.$tag,'txn_TARGET'.$tag);sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);return[$o,$data,$saved];}
function targetDry(array $o):array{return sharity_donation_finance_target($o['order_id'],$GLOBALS['now']);}
function targetSave(array $o,?string $digest=null,int $offset=0):array{return sharity_donation_finance_target($o['order_id'],$GLOBALS['now']+$offset,$digest??targetDry($o)['proposal_digest']);}
class TargetMysqli extends BackingMysqli {
    public function prepare(string $sql):mysqli_stmt|false {
        if($this->fault==='target-insert'&&str_starts_with($sql,'INSERT INTO `'.sharity_donation_target_table().'`')){throw new RuntimeException('synthetic failure');}
        if($this->fault==='target-corrupt'&&$this->committed&&str_contains($sql,' AS document FROM `'.sharity_donation_target_table().'`')){$sql=str_replace('digest,created_at_utc',"REPEAT('0',64) AS digest,created_at_utc",$sql);}
        return parent::prepare($sql);
    }
}
function targetConnection(string $fault=''):TargetMysqli{global $wpdb,$port;$wpdb->db->close();$db=new TargetMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;}
try {
    check(sharity_donation_movement_schema_apply()&&sharity_donation_target_schema_apply(),'explicit target schema');
    [$o,$data,$saved]=targetFixture('BASE');$id=$o['order_id'];$gets=$financeHttp->gets;$posts=calls();$opts=$options;$before=targetCount();
    $dry=targetDry($o);$t=$dry['target'];
    check(!$dry['persisted']&&targetCount()===$before,'dry target writes nothing');
    check($t['connected_totals']['HUF']===['gross_minor'=>'50000','processing_cost_minor'=>'2000','application_fee_minor'=>'0','net_minor'=>'48000','net_before_application_fee_minor'=>'48000','observed_deficit_minor'=>'0'],'exact direct totals');
    check($t['provisional_split']===['currency'=>'HUF','ngo_minor'=>'48000','fund_minor'=>'0'],'direct net100 provisional NGO target');
    check($t['full_liability_minor']===null&&$t['full_liability_status']==='unknown','observed deficit is not total liability');
    foreach(['payment_verified','allocation_eligible','settlement_reconciled','activation_allowed']as$flag){check($t[$flag]===false,'no acceptance '.$flag);}
    check(!str_contains(json_encode($t),'subject_ref'),'no person identity');
    fails(fn()=>targetSave($o,str_repeat('0',64)),'DONATION_TARGET_STALE');
    $r=targetSave($o,$dry['proposal_digest']);check($r['persisted']&&$r['revision']===1&&$r['economic_generation']===1&&targetCount()===$before+1,'first immutable revision');
    check(targetSave($o,null,50)===$r,'replay retains original timestamp');
    check($financeHttp->gets===$gets&&calls()===$posts&&$options===$opts&&sharity_donation_webhook_order($id)===$o,'zero provider calls and order/option writes');
    // Additional metadata changes evidence revision, never economic generation or the amount.
    $x=$data;$x['original']['initial_charge_balance']['status']='pending';$metadata=reconcileFixture($o,$x);
    $r2=targetSave($o);check($r2['revision']===2&&$r2['economic_generation']===1&&$r2['target']['connected_totals']===$r['target']['connected_totals'],'status-only revision preserves economic generation');
    fails(fn()=>targetSave($o,$r['proposal_digest']),'DONATION_TARGET_STALE');
    // Signed refund, refund reversal, dispute debit + reinstatement; fee credit counted once.
    $x=$data;$x['refunds']=[['id'=>'re_TREF','balance'=>reconcileBalance('txn_TREF','re_TREF','refund','-1000','-10'),'failure_balance'=>reconcileBalance('txn_TREV','re_TREF','refund_failure','1000')]];
    $x['disputes']=[['id'=>'dp_TDIS','balances'=>[reconcileBalance('txn_TDIS','dp_TDIS','adjustment','-50000','1500'),reconcileBalance('txn_TWON','dp_TDIS','adjustment','50000')]]];
    $correction=reconcileFixture($o,$x);
    fails(fn()=>targetDry($o),'DONATION_TARGET_REGISTRY_MISMATCH');
    sharity_donation_finance_record_movements($id,movementProposal($o),$now);
    $r3=targetSave($o);check($r3['economic_generation']===2&&$r3['target']['connected_totals']['HUF']['gross_minor']==='50000'&&$r3['target']['provisional_split']['ngo_minor']==='46510','unique signed movements and returned fee exact');
    // A snapshot disappearing cannot erase a registered movement.
    removeReconcileFixture($correction);fails(fn()=>targetDry($o),'DONATION_TARGET_REGISTRY_MISMATCH');reconcileFixture($o,$x);
    [$full,$fd]=targetFixture('FULL');$fd['refunds']=[['id'=>'re_TFULL','balance'=>reconcileBalance('txn_TFULLREF','re_TFULL','refund','-50000'),'failure_balance'=>null]];
    reconcileFixture($full,$fd);sharity_donation_finance_record_movements($full['order_id'],movementProposal($full),$now);$f=targetSave($full)['target'];
    check($f['connected_totals']['HUF']['net_before_application_fee_minor']==='-2000'&&$f['connected_totals']['HUF']['observed_deficit_minor']==='2000'&&$f['provisional_split']['ngo_minor']==='0','full refund preserves negative fees, no negative payable');
    // Settlement in EUR for a HUF payment is EUR, never re-labelled HUF.
    [$fx,$xd,$old]=movementFixture('targetFX','txn_TARGETFX');removeReconcileFixture($old);
    $xd['original']['initial_charge_balance']['currency']='EUR';$xd['original']['initial_charge_balance']['fee_details'][0]['currency']='EUR';reconcileFixture($fx,$xd);
    sharity_donation_finance_record_movements($fx['order_id'],movementProposal($fx),$now);$f=targetSave($fx)['target'];
    check($f['payment_currency']==='HUF'&&$f['connected_settlement_currency']==='EUR'&&$f['provisional_split']['currency']==='EUR','payment and actual settlement distinct');
    $xd['refunds']=[['id'=>'re_TFX','balance'=>reconcileBalance('txn_TFXREF','re_TFX','refund','-500'),'failure_balance'=>null]];reconcileFixture($fx,$xd);
    sharity_donation_finance_record_movements($fx['order_id'],movementProposal($fx),$now);$f=targetSave($fx)['target'];
    check(count($f['connected_totals'])===2&&$f['connected_totals']['HUF']['net_minor']==='-500'&&$f['connected_totals']['EUR']['net_minor']==='48000'&&$f['provisional_split']===null,'mixed settlement stays separate, no dropped currency or synthetic FX');
    // Amplifier policy calculation cannot claim a reconciled split without platform/counterleg proof.
    $a=$dry['target'];$inventory=sharity_donation_finance_reconcile($full['order_id'],$now);$snap=sharity_donation_order_decode($full)['snapshot'];$snap['donation_mode']='amplifier';$snap['policy_version']='amplifier-net80-v1';
    $amp=sharity_donation_target_compute($inventory,$snap);
    check($amp['provisional_split']===null&&in_array('amplifier_platform_and_counterleg_not_proven',$amp['unresolved'],true),'unknown amplifier remains unresolved including full refund');
    // Persisted Amplifier order: an application fee is separate from payment cost, still unscoped.
    $ampData=json_decode($ampSaved['canonical_json'],true);removeReconcileFixture($ampSaved);
    $ampData['original']['initial_charge_balance']=array_replace($ampData['original']['initial_charge_balance'],[
        'id'=>'txn_TARGETAMP','total_fee_minor'=>'12000','application_fee_minor'=>'10000','processing_cost_minor'=>'2000','net_minor'=>'38000',
        'fee_details'=>[['amount_minor'=>'2000','currency'=>'HUF','type'=>'stripe_fee'],['amount_minor'=>'10000','currency'=>'HUF','type'=>'application_fee']]]);
    reconcileFixture($ampOrder,$ampData);sharity_donation_finance_record_movements($ampOrder['order_id'],movementProposal($ampOrder),$now);
    $persistedAmp=targetSave($ampOrder);$a=$persistedAmp['target'];
    check($persistedAmp['persisted']&&$a['policy']['donation_mode']==='amplifier'&&$a['connected_totals']['HUF']['net_before_application_fee_minor']==='48000'&&$a['connected_totals']['HUF']['net_minor']==='38000','persisted Amplifier retains fee separately, no double deduction');
    check($a['provisional_split']===null&&$a['unscoped_platform_movement_count']===2&&targetSave($ampOrder)===$persistedAmp,'persisted unknown platform and counterleg stays null, replay exact');
    // D2p: real registration and target of verified platform fee/refund, preserving legacy observations.
    $nativeStart=$checks;$nativeGets=$financeHttp->gets;$nativePosts=calls();$scoped=$ampData;$scoped['original']['platform_account_id']='acct_PLATFORM';
    $verified=reconcileFixture($ampOrder,$scoped);$inv=sharity_donation_finance_reconcile($ampOrder['order_id'],$now);
    check(count($inv['movements'])===3 && $inv['unscoped_platform_movements']===[],'legacy and verified native platform legs counted once');
    check(!in_array('platform_account_unverified',$inv['unresolved'],true),'verified native legs resolve account scope only');
    foreach($inv['movements'] as $m){check(count($m['observations'])===2,'both provenance records retained after supersession');}
    check(financeCount()>0 && sharity_donation_finance_read($ampOrder['order_id'],$verified['digest'])===$verified,'immutable verified evidence readback');
    fails(fn()=>targetDry($ampOrder),'DONATION_TARGET_REGISTRY_MISMATCH');
    $p=movementProposal($ampOrder);$count=movementCount();
    $record=sharity_donation_finance_record_movements($ampOrder['order_id'],$p,$now);
    check(count($record['movements'])===3 && movementCount()===$count+2 && $record['unscoped_platform_movement_count']===0,'two verified platform legs registered beside existing NGO charge');
    check(sharity_donation_finance_record_movements($ampOrder['order_id'],$p,$now+7)===$record,'verified native batch replay exact');
    $new=targetSave($ampOrder);$t=$new['target'];
    check($t['connected_totals']===$a['connected_totals'] && $t['platform_totals']['acct_PLATFORM']['HUF']===['gross_minor'=>'9900','processing_cost_minor'=>'0','application_fee_minor'=>'0','net_minor'=>'9900'],'platform balance separate from NGO and donor income');
    check($t['provisional_split']===null && in_array('amplifier_counterleg_not_proven',$t['unresolved'],true) && !$t['allocation_eligible'],'verified platform does not invent NGO counterleg or authorize allocation');
    check(targetSave($ampOrder)===$new,'platform target full persisted replay');
    // Null/malformed/current-NGO platform identity and contradictory old/new native facts are not accepted.
    foreach(['account','null','malformed','same-ngo','gross','currency','source','refund-scope'] as $case){
        $bad=$scoped;
        if($case==='account'){$bad['original']['platform_account_id']='acct_OTHER';}
        if($case==='null'){$bad['original']['platform_account_id']=null;}
        if($case==='malformed'){$bad['original']['platform_account_id']='acct_bad/path';}
        if($case==='same-ngo'){$bad['original']['platform_account_id']=$ampData['original']['account_id'];}
        if($case==='gross'){$bad['original']['application_fee']['initial_balance']['gross_minor']='10001';$bad['original']['application_fee']['initial_balance']['net_minor']='10001';}
        if($case==='currency'){$bad['original']['application_fee']['initial_balance']['currency']='EUR';}
        if($case==='source'){$bad['fee_refunds'][0]['id']='fr_CHANGED';$bad['fee_refunds'][0]['balance']['source_id']='fr_CHANGED';}
        if($case==='refund-scope'){$bad['fee_refunds'][0]['balance']['scope']='connected';}
        $added=reconcileFixture($ampOrder,$bad);
        fails(fn()=>sharity_donation_finance_reconcile($ampOrder['order_id'],$now),in_array($case,['account','gross','currency','source'],true)?'DONATION_RECONCILE_CONFLICT':'DONATION_RECONCILE_UNAVAILABLE');
        removeReconcileFixture($added);
    }
    // Contradiction across only one legacy and one verified observation must also fail.
    removeReconcileFixture($verified);$bad=$scoped;$bad['original']['application_fee']['initial_balance']['gross_minor']='10002';$bad['original']['application_fee']['initial_balance']['net_minor']='10002';
    $added=reconcileFixture($ampOrder,$bad);fails(fn()=>sharity_donation_finance_reconcile($ampOrder['order_id'],$now),'DONATION_RECONCILE_CONFLICT');removeReconcileFixture($added);
    $verified=reconcileFixture($ampOrder,$scoped);
    // A direct observation cannot acquire invented platform identity.
    $bad=$data;$bad['original']['platform_account_id']='acct_PLATFORM';$added=reconcileFixture($o,$bad);
    fails(fn()=>sharity_donation_finance_reconcile($o['order_id'],$now),'DONATION_RECONCILE_UNAVAILABLE');removeReconcileFixture($added);
    check($financeHttp->gets===$nativeGets&&calls()===$nativePosts,'account-scoped reads/writes make no provider call');
    // Two actual connections cannot claim the same platform movement for another order.
    $oldSnapshot=$snapshot;$snapshot=array_replace($snapshot,['donation_mode'=>'amplifier','votes'=>500,'package_id'=>'starter','price_book_version'=>'amplifier-net80-v1:HUF','policy_version'=>'amplifier-net80-v1']);
    try{$other=financeOrder('platform-race');}finally{$snapshot=$oldSnapshot;}
    $otherData=$scoped;$otherData['original']['order_id']=$other['order_id'];$otherData['original']['session_id']=$other['provider_session_id'];$otherData['original']['initial_charge_balance']['id']='txn_OTHERAMP';
    $otherSaved=reconcileFixture($other,$otherData);$nativeGets=$financeHttp->gets;$nativePosts=calls();$pa=movementProposal($ampOrder);$pb=movementProposal($other);$count=movementCount();
    $raceResult=backingRace(fn($slot)=>sharity_donation_finance_record_movements(($slot===0?$ampOrder:$other)['order_id'],$slot===0?$pa:$pb,$now),'START TRANSACTION');
    check(isset($raceResult[0]['value']) && ($raceResult[1]['error']??'')==='DONATION_MOVEMENT_CONFLICT' && movementCount()===$count,'native platform ownership rejects competing order, whole loser batch rolled back');
    removeReconcileFixture($otherSaved);
    // Same transaction ID on another actual account is an independent provider identity.
    $otherData['original']['platform_account_id']='acct_OTHERPLATFORM';reconcileFixture($other,$otherData);
    $rr=sharity_donation_finance_record_movements($other['order_id'],movementProposal($other),$now);
    check(count($rr['movements'])===3 && movementCount()===$count+3,'same txn IDs on distinct actual accounts remain isolated');
    // Separate native platform settlement currency, never reference-FX-converted into NGO balance.
    $fxInventory=$inv;
    foreach($fxInventory['movements'] as &$m){if($m['facts']['account_id']==='acct_PLATFORM'){$m['facts']['currency']='EUR';}}unset($m);
    $fxTarget=sharity_donation_target_compute($fxInventory,sharity_donation_order_decode($ampOrder)['snapshot']);
    check($fxTarget['platform_totals']['acct_PLATFORM']['EUR']['net_minor']==='9900' && $fxTarget['connected_totals']===$t['connected_totals'],'native currencies stay in independent buckets');
    check($financeHttp->gets===$nativeGets&&calls()===$nativePosts,'account-scoped registry/target makes no provider call');
    echo 'D2p platform MariaDB: '.($checks-$nativeStart)." checks; verified scope, legacy conflicts, replay, separate totals and two-connection ownership\n";
    // Full body integrity: intact digest column does not hide a damaged registry body.
    $mt=sharity_donation_movement_table();$original=$wpdb->db->query($wpdb->prepare("SELECT facts_json FROM `{$mt}` WHERE order_id=%s LIMIT 1",$full['order_id']))->fetch_assoc()['facts_json'];
    $wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json='{}' WHERE order_id=%s AND facts_json=%s",$full['order_id'],$original));
    fails(fn()=>targetDry($full),'DONATION_TARGET_REGISTRY_MISMATCH');$wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json=%s WHERE order_id=%s AND facts_json='{}'",$original,$full['order_id']));
    // D2q: coherent real native counterlegs change liquidity, never donor gross.
    $qStart=$checks;
    $qFixture=static function(string $tag,?callable $modify=null)use($ampData,$ampOrder):array{
        global $snapshot,$now;$previous=$snapshot;$snapshot=sharity_donation_order_decode($ampOrder)['snapshot'];
        try{$o=financeOrder('counterleg'.$tag);}finally{$snapshot=$previous;}
        $d=$ampData;$d['original']['order_id']=$o['order_id'];$d['original']['session_id']=$o['provider_session_id'];$d['original']['platform_account_id']='acct_PLATFORM';
        $d['original']['initial_charge_balance']['id']='txn_QCH'.$tag;$d['original']['application_fee']['id']='fee_Q'.$tag;
        $d['original']['application_fee']['initial_balance']['id']='txn_QFEE'.$tag;$d['original']['application_fee']['refund_state']['amount_refunded_minor']='0';
        $d['fee_refunds']=[];$d['unresolved']=[];if($modify!==null){$d=$modify($d);}$saved=reconcileFixture($o,$d);
        sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);return[$o,$d,$saved];
    };
    [$qo,$qd]=$qFixture('BASE');$expected=['currency'=>'HUF','ngo_minor'=>'38400','fund_minor'=>'9600'];
    check(targetSave($qo)['target']['provisional_split']===$expected,'no refund needs no imaginary counterleg; actual fee is not charged twice');
    $qd['fee_refunds']=[['id'=>'fr_Q','fee_id'=>'fee_QBASE','amount_minor'=>'400','currency'=>'HUF','created'=>1800000010,'connected_credit_verified'=>false,
        'balance'=>reconcileBalance('txn_QDEBIT','fr_Q','application_fee_refund','-400','0','platform')]];
    $qd['original']['application_fee']['refund_state']['amount_refunded_minor']='400';$qd['unresolved']=['fee_refund_connected_credit_unreconciled'];$missing=reconcileFixture($qo,$qd);
    sharity_donation_finance_record_movements($qo['order_id'],movementProposal($qo),$now);check(targetSave($qo)['target']['provisional_split']===null,'platform debit without NGO native proof stays null');
    $qd['fee_refunds'][0]['connected_credit_verified']=true;
    $qd['fee_refunds'][0]['connected_credit']=reconcileBalance('txn_QCREDIT','fr_Q','application_fee_refund','400')+['account_id'=>sharity_donation_order_decode($qo)['snapshot']['account_id']];
    $qd['unresolved']=[];$proof=reconcileFixture($qo,$qd);$qi=sharity_donation_finance_reconcile($qo['order_id'],$now);
    check(count($qi['movements'])===4&&!in_array('observed_fee_refund_connected_credit_unreconciled',$qi['unresolved'],true),'later actual evidence resolves historical absence without duplicate money');
    fails(fn()=>targetDry($qo),'DONATION_TARGET_REGISTRY_MISMATCH');sharity_donation_finance_record_movements($qo['order_id'],movementProposal($qo),$now);
    $qr=targetSave($qo);$qt=$qr['target'];check($qt['provisional_split']===$expected&&targetSave($qo)===$qr,'fee refund leaves donor net80/20 target unchanged and replay exact');
    check($qt['connected_totals']['HUF']['gross_minor']==='50400'&&$qt['connected_totals']['HUF']['donor_gross_minor']==='50000'&&$qt['connected_totals']['HUF']['inter_account_credit_minor']==='400'&&$qt['connected_totals']['HUF']['net_minor']==='38400','native raw credit retained separately from donor gross');
    check($qt['platform_totals']['acct_PLATFORM']['HUF']['net_minor']==='9600'&&$qt['full_liability_minor']===null&&!$qt['activation_allowed'],'actual platform net separate, liability unknown and disabled');
    check(sharity_donation_finance_read($qo['order_id'],$missing['digest'])['canonical_json']===$missing['canonical_json'],'historical evidence remains immutable');
    foreach(['bool','account','source','id','amount','sign','scope','type','fee','nominal']as$case){
        $bad=$qd;$c=&$bad['fee_refunds'][0]['connected_credit'];
        if($case==='bool'){$bad['fee_refunds'][0]['connected_credit_verified']=false;}
        if($case==='account'){$c['account_id']='acct_OTHER';}
        if($case==='source'){$c['source_id']='fr_OTHER';}
        if($case==='id'){$c['id']='txn_QCREDITOTHER';}
        if($case==='amount'){$c['gross_minor']=$c['net_minor']='401';}
        if($case==='sign'){$c['gross_minor']=$c['net_minor']='-400';}
        if($case==='scope'){$c['scope']='platform';}
        if($case==='type'){$c['type']='payout';}
        if($case==='fee'){$bad['fee_refunds'][0]['fee_id']='fee_OTHER';}
        if($case==='nominal'){$bad['fee_refunds'][0]['amount_minor']='401';}unset($c);
        $badRow=reconcileFixture($qo,$bad);fails(fn()=>sharity_donation_finance_reconcile($qo['order_id'],$now),in_array($case,['id','nominal'],true)?'DONATION_RECONCILE_CONFLICT':'DONATION_RECONCILE_UNAVAILABLE');removeReconcileFixture($badRow);
    }
    // Full refund includes both fee counterlegs, retains real fees as deficit and zero payable.
    $fullData=$qd;$fullData['refunds']=[['id'=>'re_QFULL','balance'=>reconcileBalance('txn_QFULL','re_QFULL','refund','-50000'),'failure_balance'=>null]];
    $fullData['fee_refunds'][]=['id'=>'fr_QREST','fee_id'=>'fee_QBASE','amount_minor'=>'9600','currency'=>'HUF','created'=>1800000011,'connected_credit_verified'=>true,
        'balance'=>reconcileBalance('txn_QREST','fr_QREST','application_fee_refund','-9600','0','platform'),
        'connected_credit'=>reconcileBalance('txn_QRESTC','fr_QREST','adjustment','9600')+['account_id'=>sharity_donation_order_decode($qo)['snapshot']['account_id']]];
    $fullData['original']['application_fee']['refund_state']['amount_refunded_minor']='10000';reconcileFixture($qo,$fullData);
    sharity_donation_finance_record_movements($qo['order_id'],movementProposal($qo),$now);$qt=targetSave($qo)['target'];
    check($qt['provisional_split']===['currency'=>'HUF','ngo_minor'=>'0','fund_minor'=>'0']&&$qt['connected_totals']['HUF']['observed_deficit_minor']==='2000'&&$qt['connected_totals']['HUF']['donor_gross_minor']==='0','full refund zero split, retained deficit, internal credits never new gross');
    // Platform costs participate once; loss and different currency remain explicit.
    [$cost]=$qFixture('COST',static function($d){$b=&$d['original']['application_fee']['initial_balance'];
        $b['total_fee_minor']=$b['processing_cost_minor']='5';$b['net_minor']='9995';$b['fee_details']=[['amount_minor'=>'5','currency'=>'HUF','type'=>'stripe_fee']];unset($b);return$d;});
    $ct=targetSave($cost)['target'];
    check($ct['provisional_split']===['currency'=>'HUF','ngo_minor'=>'38396','fund_minor'=>'9599'],'observed platform fee deducted exactly once from combined net');
    [$lost,$ld]=$qFixture('LOST');$ld['disputes']=[['id'=>'dp_QLOST','balances'=>[reconcileBalance('txn_QLOST','dp_QLOST','adjustment','-50000','1500')]]];reconcileFixture($lost,$ld);
    sharity_donation_finance_record_movements($lost['order_id'],movementProposal($lost),$now);check(targetSave($lost)['target']['provisional_split']['fund_minor']==='0','lost dispute has zero provisional fund, not negative payable');
    // Actual cross-currency credit is kept; never forced into HUF or used to claim matching split.
    [$fxq,$fxd]=$qFixture('FX');$fxd['fee_refunds']=$qd['fee_refunds'];$fxd['fee_refunds'][0]['fee_id']='fee_QFX';$fxd['fee_refunds'][0]['id']='fr_QFX';
    foreach(['balance','connected_credit']as$field){$fxd['fee_refunds'][0][$field]['source_id']='fr_QFX';$fxd['fee_refunds'][0][$field]['id']='txn_QFX'.($field==='balance'?'D':'C');}
    $fc=&$fxd['fee_refunds'][0]['connected_credit'];$fc['currency']='EUR';$fc['gross_minor']=$fc['net_minor']='1';$fc['exchange_rate']=0.0025;unset($fc);
    reconcileFixture($fxq,$fxd);sharity_donation_finance_record_movements($fxq['order_id'],movementProposal($fxq),$now);$qt=targetSave($fxq)['target'];
    check($qt['provisional_split']===null&&$qt['connected_totals']['EUR']['donor_gross_minor']==='0'&&in_array('settlement_currencies_require_executed_fx',$qt['unresolved'],true),'actual FX bucket separate, no invented HUF credit');
    // Different executed FX on two internal sides must not create imaginary spendable money.
    [$diff]=$qFixture('INITIALDIFF',static function($d){$f=&$d['original']['application_fee'];$f['currency']='USD';$f['amount_minor']='20';$f['initial_balance']['gross_minor']=$f['initial_balance']['net_minor']='9999';$f['initial_balance']['exchange_rate']=499.95;unset($f);return$d;});
    $dt=targetSave($diff)['target'];check($dt['provisional_split']===null&&in_array('inter_account_movements_not_balanced',$dt['unresolved'],true),'initial fee actual native imbalance cannot inflate split');
    [$diffRefund,$dr]=$qFixture('REFUNDDIFF',static function($d){$d['original']['application_fee']['currency']='USD';$d['original']['application_fee']['amount_minor']='20';$d['original']['application_fee']['initial_balance']['exchange_rate']=500;return$d;});
    $dr['fee_refunds']=[['id'=>'fr_QDIFF','fee_id'=>'fee_QREFUNDDIFF','amount_minor'=>'1','currency'=>'USD','created'=>1800000011,'connected_credit_verified'=>true,
        'balance'=>reconcileBalance('txn_QDIFFD','fr_QDIFF','application_fee_refund','-500','0','platform'),
        'connected_credit'=>reconcileBalance('txn_QDIFFC','fr_QDIFF','adjustment','499')+['account_id'=>sharity_donation_order_decode($diffRefund)['snapshot']['account_id']]]];
    $dr['fee_refunds'][0]['balance']['exchange_rate']=500;$dr['fee_refunds'][0]['connected_credit']['exchange_rate']=499;
    reconcileFixture($diffRefund,$dr);sharity_donation_finance_record_movements($diffRefund['order_id'],movementProposal($diffRefund),$now);$dt=targetSave($diffRefund)['target'];
    check($dt['provisional_split']===null&&in_array('inter_account_movements_not_balanced',$dt['unresolved'],true)&&$dt['connected_totals']['HUF']['inter_account_credit_minor']==='499','nominalUSD to two actualHUF sides preserves imbalance, never synthetic fee');
    echo 'D2q native counterleg/target: '.($checks-$qStart)." checks\n";
    // Same order two native connections acquire the order lock concurrently.
    [$same]=targetFixture('RACE');$p=targetDry($same)['proposal_digest'];$result=backingRace(fn()=>targetSave($same,$p),'START TRANSACTION');
    check(isset($result[0]['value'],$result[1]['value'])&&$result[0]['value']===$result[1]['value']&&targetCount($same['order_id'])===1,'two connections one exact target');
    // A fresh observation arrives after pre-read, before order lock.
    [$stale,$sd]=targetFixture('STALE');$p=targetDry($stale)['proposal_digest'];$db=movementConnection();
    $db->beforeLock=static function()use($stale,$sd){global $wpdb,$port;$prior=$wpdb;$wpdb=new OrderDb((int)$port);try{$sd['original']['initial_charge_balance']['status']='pending';reconcileFixture($stale,$sd);}finally{$wpdb->db->close();$wpdb=$prior;}};
    fails(fn()=>targetSave($stale,$p),'DONATION_TARGET_STALE');check(targetCount($stale['order_id'])===0,'new observation excludes stale write');targetConnection();
    foreach(['commit-before','commit-after','target-insert','target-corrupt']as$fault){
        [$case]=targetFixture(str_replace('-','',$fault));$p=targetDry($case)['proposal_digest'];targetConnection($fault);
        if($fault==='commit-after'){check(targetSave($case,$p)['persisted']&&targetCount($case['order_id'])===1,'lost COMMIT ACK readback');}
        else{fails(fn()=>targetSave($case,$p),$fault==='target-corrupt'?'DONATION_TARGET_CONFLICT':($fault==='commit-before'?'DONATION_TARGET_CONFLICT':'DONATION_TARGET_UNAVAILABLE'));
            check(targetCount($case['order_id'])===($fault==='target-corrupt'?1:0),'fault preserves actual commit outcome '.$fault);}
        targetConnection();
    }
    // Persisted readback corruption and exact schema checks are not repaired silently.
    $tt=sharity_donation_target_table();$wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET created_at_utc='0000-00-00 00:00:00' WHERE order_id=%s",$same['order_id']));
    fails(fn()=>targetDry($same),'DONATION_TARGET_CONFLICT');$wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET created_at_utc=%s WHERE order_id=%s",gmdate('Y-m-d H:i:s',$now),$same['order_id']));
    $wpdb->db->query('START TRANSACTION');try{fails(fn()=>targetDry($same),'DONATION_TARGET_UNAVAILABLE');check((string)$wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='1','caller transaction preserved');}finally{$wpdb->db->rollback();}
    check(sharity_donation_target_schema_apply()&&targetCount($same['order_id'])===1,'compatible schema replay retains rows');
    $wpdb->db->query("ALTER TABLE `{$tt}` DROP INDEX proposal, ADD KEY proposal(order_id,digest)");check(!sharity_donation_target_schema_apply(),'incorrect unique topology rejected');
    $wpdb->db->query("ALTER TABLE `{$tt}` DROP INDEX proposal, ADD UNIQUE KEY proposal(order_id,digest)");check(sharity_donation_target_schema_apply(),'fixture schema restored explicitly');
    foreach([[],['order'=>$id,'apply'=>true],['order'=>$id,'apply'=>'x'],['order'=>$id,'extra'=>true]]as$assoc){fails(fn()=>sharity_donation_cli_finance_target([],$assoc),'DONATION_TARGET_UNAVAILABLE');}
    sharity_donation_cli_finance_target([],['order'=>$id]);$cli=json_decode(end(WP_CLI::$lines),true);check($cli['proposal_digest']===targetDry($o)['proposal_digest'],'CLI exact target dry-run');
    sharity_donation_cli_finance_target([],['order'=>$id,'apply'=>$cli['proposal_digest']]);check(json_decode(end(WP_CLI::$lines),true)['persisted'],'CLI exact digest apply');
    fails(fn()=>sharity_donation_target_sum(999999999999999,1),'DONATION_TARGET_LIMIT');
    echo 'Donation target MariaDB: '.($checks-$targetStart)." checks; exact net, per-currency, registry, stale and native concurrent/commit faults\n";
} finally {targetConnection();}
