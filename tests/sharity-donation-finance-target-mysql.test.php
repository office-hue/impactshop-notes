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
    // Full body integrity: intact digest column does not hide a damaged registry body.
    $mt=sharity_donation_movement_table();$original=$wpdb->db->query($wpdb->prepare("SELECT facts_json FROM `{$mt}` WHERE order_id=%s LIMIT 1",$full['order_id']))->fetch_assoc()['facts_json'];
    $wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json='{}' WHERE order_id=%s AND facts_json=%s",$full['order_id'],$original));
    fails(fn()=>targetDry($full),'DONATION_TARGET_REGISTRY_MISMATCH');$wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json=%s WHERE order_id=%s AND facts_json='{}'",$original,$full['order_id']));
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
