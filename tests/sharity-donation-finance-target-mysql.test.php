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
// Reject all writes, transaction commands and locking reads during the real projection.
class FinancialReadMysqli extends TargetMysqli {
    public int $readQueries=0;
    private function readOnly(string $sql):void { $this->readQueries++; if(!str_starts_with($sql,'SELECT ')||preg_match('/FOR UPDATE|LOCK IN SHARE/i',$sql)){throw new RuntimeException('unexpected financial read SQL');} }
    public function query(string $sql,int $result_mode=MYSQLI_STORE_RESULT):mysqli_result|bool { $this->readOnly($sql);return parent::query($sql,$result_mode); }
    public function prepare(string $sql):mysqli_stmt|false { $this->readOnly($sql);return parent::prepare($sql); }
}
function financialRead(array $o,?string $subject=null,?int $at=null):array {
    global $wpdb,$port,$now;
    $wpdb->db->close();$db=new FinancialReadMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$wpdb->db=$db;
    try{$result=sharity_donation_financial_read($subject??$o['subject_ref'],$o['order_id'],$at??$now);check($db->readQueries<=5,'bounded stored SQL only');return$result;}
    finally{targetConnection();}
}
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
    $readStart=$checks;[$readOrder]=targetFixture('OWNERREAD');$readId=$readOrder['order_id'];
    $getsBefore=$financeHttp->gets;$postsBefore=calls();$optsBefore=$options;
    check(financialRead($readOrder)['status']==='pending','no target is pending, not zero or failure');
    $readSaved=targetSave($readOrder);$view=financialRead($readOrder);
    check($view['version']===1&&$view['status']==='snapshot'&&$view['snapshot_at_utc']===gmdate('Y-m-d\TH:i:s\Z',$now),'persisted snapshot date');
    check($view['settlement']===[['currency'=>'HUF','inter_account_credit_minor'=>'0','donor_gross_minor'=>'50000','processing_cost_minor'=>'2000','application_fee_minor'=>'0','net_minor'=>'48000','observed_deficit_minor'=>'0']],'direct original gross fallback and exact minor values');
    check($view['provisional_split']===['currency'=>'HUF','ngo_minor'=>'48000','fund_minor'=>'0'],'existing provisional target, not new arithmetic');
    foreach(['settlement_reconciled','payout_verified','ngo_bank_received']as$flag){check($view[$flag]===false,'no financial acceptance '.$flag);}
    $json=json_encode($view);foreach(['acct_','txn_','subject','digest','platform_totals','unresolved','policy']as$private){check(!str_contains($json,$private),'no private field '.$private);}
    check(financialRead($readOrder,null,$now+100)===$view,'GET does not restamp historical data');
    check(financialRead($readOrder,'hmac-sha256:'.str_repeat('f',64))['status']==='unavailable','other owner cannot read amounts');
    check(financialRead($readOrder,'bad')['status']==='unavailable','invalid owner rejected');
    $options[SHARITY_DONATION_ORDERS_ENABLED]='0';check(financialRead($readOrder)===$view,'historical read after disabling new checkout');$options=$optsBefore;
    unset($options[SHARITY_DONATION_TARGET_SCHEMA_OPTION]);check(financialRead($readOrder)['status']==='unavailable','missing schema explicit');$options=$optsBefore;
    check(financialRead($full)['settlement'][0]['net_minor']==='-2000'&&financialRead($full)['settlement'][0]['observed_deficit_minor']==='2000','refund deficit remains signed');
    $multi=financialRead($fx);check(count($multi['settlement'])===2&&$multi['provisional_split']===null,'currencies not relabelled or combined');
    $ampView=financialRead($fxq);check($ampView['settlement'][0]['currency']==='EUR'&&$ampView['settlement'][0]['donor_gross_minor']==='0','interaccount credit is not donor gross');
    check($ampView['settlement'][0]['inter_account_credit_minor']==='1','actual native credit is explicit, not hidden in net');
    $tt=sharity_donation_target_table();$raw=$wpdb->db->query($wpdb->prepare("SELECT document,digest,created_at_utc FROM `{$tt}` WHERE order_id=%s",$readId))->fetch_assoc();
    foreach(['version','policy','payment-currency','minor','negative-zero','credit-pair','credit-invalid','credit-null','overflow','flag','split-currency','negative-split','missing-split','fifth-currency','future','zero-date','digest']as$case){
        $doc=json_decode($raw['document'],true);$stamp=$raw['created_at_utc'];
        if($case==='version'){$doc['target_version']=2;}
        if($case==='policy'){$doc['policy']['votes']=1;$doc['policy_digest']=hash('sha256',sharity_donation_finance_json($doc['policy']));}
        if($case==='payment-currency'){$doc['payment_currency']='USD';}
        if($case==='minor'){$doc['connected_totals']['HUF']['gross_minor']=50000;}
        if($case==='negative-zero'){$doc['connected_totals']['HUF']['net_minor']='-0';}
        if($case==='credit-pair'){$doc['connected_totals']['HUF']['donor_gross_minor']='50000';}
        if($case==='credit-invalid'){$doc['connected_totals']['HUF']['donor_gross_minor']='50000';$doc['connected_totals']['HUF']['inter_account_credit_minor']='-0';}
        if($case==='credit-null'){$doc['connected_totals']['HUF']['donor_gross_minor']='50000';$doc['connected_totals']['HUF']['inter_account_credit_minor']=null;}
        if($case==='overflow'){$doc['connected_totals']['HUF']['net_minor']='1000000000000000';}
        if($case==='flag'){$doc['payment_verified']=true;}
        if($case==='split-currency'){$doc['provisional_split']['currency']='EUR';}
        if($case==='negative-split'){$doc['provisional_split']['ngo_minor']='-1';}
        if($case==='missing-split'){unset($doc['provisional_split']);}
        if($case==='fifth-currency'){$doc['connected_totals']=array_fill_keys(['HUF','EUR','USD','GBP','JPY'],$doc['connected_totals']['HUF']);}
        if($case==='future'){$stamp=gmdate('Y-m-d H:i:s',$now+1);}
        if($case==='zero-date'){$stamp='0000-00-00 00:00:00';}
        $body=sharity_donation_finance_json($doc);$hash=$case==='digest'?str_repeat('0',64):hash('sha256',$body);
        $wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET document=%s,digest=%s,created_at_utc=%s WHERE order_id=%s",$body,$hash,$stamp,$readId));
        $bad=financialRead($readOrder);check($bad['status']==='unavailable'&&$bad['settlement']===null&&$bad['provisional_split']===null,'corruption not partial success '.$case);
    }
    $wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET document=%s,digest=%s,created_at_utc=%s WHERE order_id=%s",$raw['document'],$raw['digest'],$raw['created_at_utc'],$readId));
    check(financialRead($readOrder)===$view&&sharity_donation_webhook_order($readId)===$readOrder,'restored fixture exact, order unchanged');
    check($financeHttp->gets===$getsBefore&&calls()===$postsBefore&&$options===$optsBefore,'no provider or option writes');
    // V2 groups the owner, payment and preliminary money in one read-only snapshot.
    $pending=financeOrder('v2-pending-proof');
    $v2Start=$checks;$getBaseline=$financeHttp->gets;$postBaseline=calls();$ddlBaseline=$wpdb->ddls;
    $owned=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
    $v2=$owned['financial'];
    check($owned['order']===sharity_donation_order_public($readOrder,$now),'V2 preserves immutable checkout/order');
    check($v2['version']===2&&$v2['status']==='snapshot'&&$v2['payment']===['verified_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now),'currency'=>'HUF','amount_minor'=>'50000'],'V2 original payment from nested stored proof');
    check($v2['settlement']===$view['settlement']&&$v2['provisional_split']===$view['provisional_split'],'V2 current target uses existing V1 money');
    check($v2['approved_split']===null&&$v2['bank_receipt']===null&&$v2['reconciliation_status']==='pending','no approval or bank claim without writer');
    check($v2['adjustment_status']==='none','complete unchanged refund/dispute evidence');
    check(sharity_donation_owned_status($readOrder['subject_ref'],'quote_id',$readOrder['quote_id'],$now)['financial']===$v2,'quote and ID same snapshot projection');
    check(sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now+60)['financial']===$v2,'proof timestamp stable across GET');
    fails(fn()=>sharity_donation_owned_status('hmac-sha256:'.str_repeat('f',64),'order_id',$readId,$now),'ORDER_NOT_FOUND');
    $currentOptions=$options;unset($options[SHARITY_DONATION_TARGET_SCHEMA_OPTION]);
    $missing=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
    check($missing['order']===$owned['order']&&$missing['financial']['payment']===$v2['payment']&&$missing['financial']['settlement']===null,'missing target keeps own order and original payment, not zero money');
    unset($options[SHARITY_DONATION_FINANCE_SCHEMA_OPTION]);
    $missing=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
    check($missing['financial']['status']==='unavailable'&&$missing['order']===$owned['order'],'missing finance schema preserves owned order');$options=$currentOptions;
    $wpdb->db->query('START TRANSACTION');
    try {
        $caller=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
        check($caller['order']===$owned['order']&&$caller['financial']['status']==='unavailable','unknown caller snapshot exposes historical order only');
        check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='1','caller transaction not committed or rolled back');
        try { sharity_donation_backing_db();throw new LogicException('writer accepted transaction'); }
        catch (RuntimeException $expected) { check(true,'writer adapter still refuses active caller transaction'); }
    } finally { $wpdb->db->rollback(); }
    check(sharity_donation_owned_status($pending['subject_ref'],'order_id',$pending['order_id'],$now)['financial']['payment']===null,'recorded session alone not payment proof');
    $refund=$readData??json_decode(sharity_donation_finance_read($readId,sharity_donation_finance_reconcile($readId,$now)['evidence_digests'][0])['canonical_json'],true);
    $refund['refunds']=[['id'=>'re_STATUS','amount_minor'=>'10000','currency'=>'HUF','created'=>1800000010,'status'=>'succeeded',
        'balance'=>reconcileBalance('txn_STATUSREFUND','re_STATUS','refund','-10000'),'failure_balance'=>null]];
    $refund['original']['charge_refund_state']['amount_refunded_minor']='10000';
    // Actual second connection commits a refund after the owner read. The first GET must keep the older whole snapshot.
    $firstConnection=$wpdb;$inserted=null;
    $firstConnection->afterRead=function($sql)use(&$inserted,$firstConnection,$readOrder,$refund){
        if($inserted!==null||!str_contains($sql,'WHERE `order_id`'))return;
        $firstConnection->afterRead=null;$second=new OrderDb((int)$GLOBALS['port']);$GLOBALS['wpdb']=$second;
        try{$inserted=reconcileFixture($readOrder,$refund);}finally{$second->db->close();$GLOBALS['wpdb']=$firstConnection;}
    };
    $beforeRefund=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
    $firstConnection->afterRead=null;
    check($inserted!==null&&$beforeRefund['financial']===$v2,'two-connection refund race does not mix current adjustment with older target');
    $afterRefund=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial'];
    check($afterRefund['payment']===$v2['payment']&&$afterRefund['adjustment_status']==='partial_refund','refund does not erase original payment');
    check($afterRefund['settlement']===null&&$afterRefund['provisional_split']===null,'new evidence invalidates old target before worker runs');
    removeReconcileFixture($inserted);
    $incomplete=$refund;$incomplete['resources_complete']=false;$incompleteSaved=reconcileFixture($readOrder,$incomplete);
    $incompleteView=sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial'];
    check($incompleteView['payment']===$v2['payment']&&$incompleteView['adjustment_status']==='unknown'&&$incompleteView['settlement']===null,'incomplete adjustment keeps independently proven original payment only');
    removeReconcileFixture($incompleteSaved);
    $ft=sharity_donation_finance_table();$proof=sharity_donation_finance_read($readId,sharity_donation_finance_reconcile($readId,$now)['evidence_digests'][0]);
    $wpdb->db->query($wpdb->prepare("UPDATE `{$ft}` SET digest=%s WHERE id=%s",str_repeat('0',64),$proof['id']));
    check(sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial']['payment']===null,'corrupt receipt cannot prove payment');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$ft}` SET digest=%s WHERE id=%s",$proof['digest'],$proof['id']));
    $fullRefund=$refund;$fullRefund['refunds'][0]['amount_minor']='50000';$fullRefund['refunds'][0]['balance']=reconcileBalance('txn_STATUSFULL','re_STATUS','refund','-50000');
    $fullRefund['original']['charge_refund_state']['amount_refunded_minor']='50000';$fullSaved=reconcileFixture($readOrder,$fullRefund);
    check(sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial']['adjustment_status']==='full_refund','full refund separate from original payment');removeReconcileFixture($fullSaved);
    $dispute=$refund;$dispute['refunds']=[];$dispute['original']['charge_refund_state']['amount_refunded_minor']='0';$dispute['original']['disputed']=true;
    $dispute['disputes']=[['id'=>'dp_STATUS','amount_minor'=>'50000','currency'=>'HUF','created'=>1800000010,'status'=>'needs_response','balances'=>[]]];
    $openSaved=reconcileFixture($readOrder,$dispute);
    check(sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial']['adjustment_status']==='disputed','unambiguous open dispute visible');
    $dispute['original']['disputed']=false;$dispute['disputes'][0]['status']='warning_closed';$closedSaved=reconcileFixture($readOrder,$dispute);
    check(sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now)['financial']['adjustment_status']==='unknown','mixed open/closed evidence is not a local-ID timeline');
    removeReconcileFixture($openSaved);removeReconcileFixture($closedSaved);
    // The real transaction refuses DML; the callback is test-only and issues no provider call.
    $readOnlyRejected=false;$wpdb->afterRead=function($sql)use(&$readOnlyRejected,$readId){
        if(!str_contains($sql,'WHERE `order_id`'))return;$GLOBALS['wpdb']->afterRead=null;
        try{$GLOBALS['wpdb']->db->query("UPDATE `".sharity_donation_order_table()."` SET state=state WHERE order_id='".$readId."'");}
        catch(mysqli_sql_exception $e){$readOnlyRejected=$e->getCode()===1792;}
    };
    sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now);
    check($readOnlyRejected,'actual grouped transaction rejects a write attempt');$wpdb->afterRead=null;
    $pinned=$wpdb;$replacement=null;
    $pinned->afterRead=function($sql)use(&$replacement){
        if(!str_contains($sql,'WHERE `order_id`'))return;$GLOBALS['wpdb']->afterRead=null;
        $replacement=new OrderDb((int)$GLOBALS['port']);$GLOBALS['wpdb']=$replacement;
    };
    try{fails(fn()=>sharity_donation_owned_status($readOrder['subject_ref'],'order_id',$readId,$now),'ORDER_READ_UNAVAILABLE');
        check($pinned->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='0','connection replacement cannot expose mixed financial state');}
    finally{if($replacement!==null){$replacement->db->close();}$wpdb=$pinned;$pinned->afterRead=null;}
    $json=json_encode($owned);foreach(['acct_','txn_','ch_','pi_','subject_ref','canonical_json','digest']as$private){check(!str_contains($json,$private),'V2 projection hides '.$private);}
    check($financeHttp->gets===$getBaseline&&calls()===$postBaseline&&$wpdb->ddls===$ddlBaseline,'all grouped reads zero provider/SDK/schema writes');
    check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='0','group-owned transaction released');
    echo 'V2 grouped financial read MariaDB: '.($checks-$v2Start)." checks\n";


    // Direct HUF operator close: actual transactions, replay and worker/owner compatibility.
    $closeStart = $checks;
    function directCloseTest(string $id,string $expect,array $ref,int $at,string $apply=''):array {
        global $financeHttp,$options;$gets=$financeHttp->gets;$posts=calls();$opts=$options;$before=sharity_donation_webhook_order($id);
        try{return sharity_donation_finance_close($id,$expect,$ref,$at,$apply);}
        finally{check($financeHttp->gets===$gets && calls()===$posts && $options===$opts && sharity_donation_webhook_order($id)===$before,'one close call has zero provider/order/option writes');}
    }

    [$closeOrder,$closeData] = targetFixture('CLOSE'); $closeId = $closeOrder['order_id'];
    $rawClose = targetSave($closeOrder); $expectClose = $rawClose['proposal_digest'];
    $refClose = backingRef('close'); $closeGets = $financeHttp->gets; $closePosts = calls(); $closeOptions = $options;
    $beforeClose = targetCount($closeId);
    $dryClose = directCloseTest($closeId,$expectClose,$refClose,$now);
    check(!$dryClose['persisted'] && !$dryClose['evidence_recorded'] && targetCount($closeId)===$beforeClose,'close preview never writes');
    $closed = directCloseTest($closeId,$expectClose,$refClose,$now,$dryClose['proposal_digest']);
    check($closed['persisted'] && !$closed['already_closed'] && $closed['current_applicable'] && targetCount($closeId)===$beforeClose+1,'first close one revision');
    check($closed['economic_generation']===$rawClose['economic_generation'] && sharity_donation_finance_json($closed['target']['manual_close']['approved_split'])===sharity_donation_finance_json(['currency'=>'HUF','ngo_minor'=>'48000','fund_minor'=>'0']),'close preserves generation and exact direct net');
    foreach(['payment_verified','allocation_eligible','settlement_reconciled','activation_allowed']as$flag){check($closed['target'][$flag]===false,'close retains conservative '.$flag);}
    $closeView=sharity_donation_owned_status($closeOrder['subject_ref'],'order_id',$closeId,$now)['financial'];
    check($closeView['reconciliation_status']==='approved' && sharity_donation_finance_json($closeView['approved_split'])===sharity_donation_finance_json(['currency'=>'HUF','ngo_minor'=>'48000','fund_minor'=>'0','approved_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now)]) && $closeView['provisional_split']===null,'existing V2 consumer shape: approved and preliminary mutually exclusive');
    check($closeView['bank_receipt']===null && !str_contains(json_encode($closeView),'proof_close'),'approval never exposes private reference or bank claim');
    check(financialRead($closeOrder)['status']==='snapshot','legacy V1 can still read closed target');
    $replayClose=directCloseTest($closeId,$expectClose,$refClose,$now+60,$dryClose['proposal_digest']);
    check($replayClose['already_closed'] && $replayClose['revision']===$closed['revision'] && $replayClose['created_at_utc']===$closed['created_at_utc'],'replay keeps original approval time');
    $otherRef=directCloseTest($closeId,$expectClose,backingRef('different'),$now+60);
    check($otherRef['already_closed'] && !$otherRef['evidence_recorded'] && $otherRef['revision']===$closed['revision'] && targetCount($closeId)===$beforeClose+1,'different reference does not create or claim another approval');
    $workerClose=targetSave($closeOrder);
    check($workerClose['proposal_digest']===hash('sha256',sharity_donation_finance_json($closed['target'])) && $workerClose['revision']===$closed['revision'] && $workerClose['target']===$closed['target'],'real worker dry/apply preserves complete closed target');
    $accountOptions=$options;unset($options['sharity_donation_accounts_v1']);
    check(sharity_donation_owned_status($closeOrder['subject_ref'],'order_id',$closeId,$now)['financial']['approved_split']===$closeView['approved_split'],'new checkout account availability is independent of historical net');$options=$accountOptions;
    $changed=$closeData;$changed['original']['initial_charge_balance']['status']='pending';$changedReceipt=reconcileFixture($closeOrder,$changed);
    $changedView=sharity_donation_owned_status($closeOrder['subject_ref'],'order_id',$closeId,$now)['financial'];
    check($changedView['reconciliation_status']==='reopened' && $changedView['approved_split']===null,'metadata-only evidence immediately hides old approval before worker');
    $historical=directCloseTest($closeId,$expectClose,$refClose,$now+60,$dryClose['proposal_digest']);
    check($historical['persisted'] && !$historical['current_applicable'] && $historical['revision']===$closed['revision'],'old exact successful request remains readable after reopening');
    fails(fn()=>directCloseTest($closeId,$expectClose,backingRef('stale'),$now),'DONATION_CLOSE_STALE');
    $newRaw=targetSave($closeOrder);
    check($newRaw['revision']===$closed['revision']+1 && $newRaw['economic_generation']===$closed['economic_generation'] && !isset($newRaw['target']['manual_close']),'changed evidence new ordinary revision without economic increment');
    check(sharity_donation_owned_status($closeOrder['subject_ref'],'order_id',$closeId,$now)['financial']['reconciliation_status']==='reopened','worker does not erase reopening state');
    $newDry=directCloseTest($closeId,$newRaw['proposal_digest'],backingRef('reclose'),$now);
    $reclosed=directCloseTest($closeId,$newRaw['proposal_digest'],backingRef('reclose'),$now,$newDry['proposal_digest']);
    check($reclosed['revision']===$newRaw['revision']+1 && $reclosed['current_applicable'],'fresh source can be reviewed again');
    // Paid but open/incomplete correction cannot become an approved current net.
    $dispute=$changed;$dispute['original']['disputed']=true;
    $dispute['disputes']=[['id'=>'dp_CLOSE','amount_minor'=>'50000','currency'=>'HUF','created'=>1800000010,'status'=>'needs_response','balances'=>[]]];
    $disputeRow=reconcileFixture($closeOrder,$dispute);$disputedRaw=targetSave($closeOrder);
    fails(fn()=>directCloseTest($closeId,$disputedRaw['proposal_digest'],backingRef('dispute'),$now),'DONATION_CLOSE_STALE');
    check(sharity_donation_owned_status($closeOrder['subject_ref'],'order_id',$closeId,$now)['financial']['approved_split']===null,'open dispute has no approved net');
    // Competing source committed after preview prevents apply; old request still replays.
    [$staleOrder,$staleData]=targetFixture('CLOSESTALE');$staleRaw=targetSave($staleOrder);
    $staleDry=directCloseTest($staleOrder['order_id'],$staleRaw['proposal_digest'],backingRef('stale-preview'),$now);
    $staleData['resources_complete']=false;reconcileFixture($staleOrder,$staleData);
    fails(fn()=>directCloseTest($staleOrder['order_id'],$staleRaw['proposal_digest'],backingRef('stale-preview'),$now,$staleDry['proposal_digest']),'DONATION_CLOSE_UNAVAILABLE');
    [$deficitOrder,$deficitData]=targetFixture('CLOSEDEFICIT');
    $deficitData['refunds']=[['id'=>'re_CLOSEFULL','amount_minor'=>'50000','currency'=>'HUF','created'=>1800000010,'status'=>'succeeded',
        'balance'=>reconcileBalance('txn_CLOSEFULL','re_CLOSEFULL','refund','-50000'),'failure_balance'=>null]];
    $deficitData['original']['charge_refund_state']['amount_refunded_minor']='50000';reconcileFixture($deficitOrder,$deficitData);
    sharity_donation_finance_record_movements($deficitOrder['order_id'],movementProposal($deficitOrder),$now);$deficitRaw=targetSave($deficitOrder);
    check($deficitRaw['target']['connected_totals']['HUF']['observed_deficit_minor']==='2000','full refund retains processing fee deficit');
    fails(fn()=>directCloseTest($deficitOrder['order_id'],$deficitRaw['proposal_digest'],backingRef('deficit'),$now),'DONATION_CLOSE_STALE');

    // Native lost commit ACK is resolved by full row readback, not a second INSERT.
    [$ackOrder]=targetFixture('CLOSEACK');$ackRaw=targetSave($ackOrder);$ackRef=backingRef('close-ack');
    $ackDry=directCloseTest($ackOrder['order_id'],$ackRaw['proposal_digest'],$ackRef,$now);
    targetConnection('commit-after');$ack=directCloseTest($ackOrder['order_id'],$ackRaw['proposal_digest'],$ackRef,$now,$ackDry['proposal_digest']);targetConnection();
    check($ack['persisted'] && targetCount($ackOrder['order_id'])===2,'lost ACK full readback confirms single close');
    targetConnection('commit-before');
    try{fails(fn()=>directCloseTest($staleOrder['order_id'],$staleRaw['proposal_digest'],backingRef('stale-preview'),$now,$staleDry['proposal_digest']),'DONATION_CLOSE_UNAVAILABLE');}finally{targetConnection();}
    // A source metadata mutation after the replay lookup must not hide in its older RR view.
    class CloseMetadataMysqli extends TargetMysqli {
        public $beforeEvidence = null;
        public function prepare(string $sql): mysqli_stmt|false {
            if ($this->beforeEvidence !== null && str_contains($sql,'FORCE INDEX(order_digest)') && str_contains($sql,'FOR UPDATE')) {
                $f=$this->beforeEvidence;$this->beforeEvidence=null;$f();
            }
            return parent::prepare($sql);
        }
    }
    foreach (['observed_at_utc','account_id'] as $metadataColumn) {
        [$metaOrder]=targetFixture('CLOSEMETA'.str_replace('_','',$metadataColumn));$metaRaw=targetSave($metaOrder);$metaRef=backingRef('metadata');
        $metaDry=directCloseTest($metaOrder['order_id'],$metaRaw['proposal_digest'],$metaRef,$now);
        $wpdb->db->close();$metaDb=new CloseMetadataMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$metaDb->set_charset('utf8mb4');$wpdb->db=$metaDb;
        $metaDb->beforeEvidence=static function()use($metadataColumn,$metaOrder,$now,$port):void{
            $otherDb=new mysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);
            $stmt=$otherDb->prepare('UPDATE `'.sharity_donation_finance_table().'` SET '.$metadataColumn.'=? WHERE order_id=?');
            $stmt->execute([$metadataColumn==='account_id'?'acct_CHANGED':gmdate('Y-m-d H:i:s',$now+60),$metaOrder['order_id']]);$stmt->close();$otherDb->close();
        };
        try{fails(fn()=>directCloseTest($metaOrder['order_id'],$metaRaw['proposal_digest'],$metaRef,$now,$metaDry['proposal_digest']),'DONATION_CLOSE_STALE');}
        finally{targetConnection();}
        check(targetCount($metaOrder['order_id'])===1,'concurrent metadata '.$metadataColumn.' refuses approval');
    }
    [$beforeOrder]=targetFixture('CLOSEBEFORE');$beforeRaw=targetSave($beforeOrder);$beforeRef=backingRef('commit-before');
    $beforeDry=directCloseTest($beforeOrder['order_id'],$beforeRaw['proposal_digest'],$beforeRef,$now);
    targetConnection('commit-before');
    try{fails(fn()=>directCloseTest($beforeOrder['order_id'],$beforeRaw['proposal_digest'],$beforeRef,$now,$beforeDry['proposal_digest']),'DONATION_CLOSE_CONFLICT');}finally{targetConnection();}
    check(targetCount($beforeOrder['order_id'])===1,'failed commit never claims an approved row');

    // Two actual connections race before the shared order lock, then converge on one row.
    [$raceClose]=targetFixture('CLOSERACE');$raceRaw=targetSave($raceClose);$raceRef=backingRef('close-race');
    $raceDry=directCloseTest($raceClose['order_id'],$raceRaw['proposal_digest'],$raceRef,$now);
    $raceCloseResult=backingRace(static fn()=>directCloseTest($raceClose['order_id'],$raceRaw['proposal_digest'],$raceRef,$GLOBALS['now'],$raceDry['proposal_digest']),'WHERE order_id = ? FOR UPDATE');
    check(isset($raceCloseResult[0]['value'],$raceCloseResult[1]['value']) && targetCount($raceClose['order_id'])===2,'two connections one close row');
    check($raceCloseResult[0]['value']['revision']===$raceCloseResult[1]['value']['revision'] && count(array_filter($raceCloseResult,static fn($r)=>!$r['value']['already_closed']))===1,'concurrent close returns original revision to loser');
    // Corruption with recomputed document digest must still fail metadata validation.
    $tt=sharity_donation_target_table();$goodCloseJson=sharity_donation_finance_json($ack['target']);$badCloseDoc=$ack['target'];$badCloseDoc['manual_close']['proposal_digest']=str_repeat('0',64);$badCloseJson=sharity_donation_finance_json($badCloseDoc);
    $wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET document=%s,digest=%s WHERE order_id=%s AND revision=%d",$badCloseJson,hash('sha256',$badCloseJson),$ackOrder['order_id'],$ack['revision']));
    $corruptStatus=sharity_donation_owned_status($ackOrder['subject_ref'],'order_id',$ackOrder['order_id'],$now);
    check($corruptStatus['order']['id']===$ackOrder['order_id'] && $corruptStatus['financial']['approved_split']===null,'corrupt optional approval cannot hide owner order or prove net');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$tt}` SET document=%s,digest=%s WHERE order_id=%s AND revision=%d",$goodCloseJson,hash('sha256',$goodCloseJson),$ackOrder['order_id'],$ack['revision']));
    $wpdb->db->query('START TRANSACTION');
    try{fails(fn()=>directCloseTest($ackOrder['order_id'],$ackRaw['proposal_digest'],$ackRef,$now),'DONATION_CLOSE_UNAVAILABLE');check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='1','close refuses and preserves caller transaction');}finally{$wpdb->db->rollback();}
    check(sharity_donation_webhook_order($closeId)===$closeOrder,'all close scenarios preserve original order');
    echo 'Direct manual close MariaDB: '.($checks-$closeStart)." checks\n";

    echo 'D2s financial owner read MariaDB: '.($checks-$readStart)." checks\n";
    echo 'Donation target MariaDB: '.($checks-$targetStart)." checks; exact net, per-currency, registry, stale and native concurrent/commit faults\n";
} finally {targetConnection();}
