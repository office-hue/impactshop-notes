<?php
/** Included in the existing disposable MariaDB harness; no external account or money. */
declare(strict_types=1);
$movementStart = $checks;
$movementTable = sharity_donation_movement_table();
function movementCount(?string $order = null): int {
    global $wpdb; $sql = 'SELECT COUNT(*) AS n FROM `'.sharity_donation_movement_table().'`';
    if ($order !== null) { $sql .= $wpdb->prepare(' WHERE order_id = %s', $order); }
    return (int) $wpdb->db->query($sql)->fetch_assoc()['n'];
}
function movementFixture(string $tag, string $transaction): array {
    global $adapter;
    $order = financeOrder('movement-'.$tag);
    $data = $adapter->retrieveAdjustmentEvidence($order['provider_session_id'],sharity_donation_order_decode($order));
    $data['original']['initial_charge_balance']['id']=$transaction;
    $saved = reconcileFixture($order,$data);
    return [$order,$data,$saved];
}
function movementProposal(array $order): string {
    return sharity_donation_finance_reconcile($order['order_id'],$GLOBALS['now'])['movement_proposal_digest'];
}
// Native faults exercise the actual mysqli path, never a production fault hook.
class MovementMysqli extends mysqli {
    public string $fault=''; public int $inserts=0; public bool $committed=false; public $beforeLock=null;
    public function prepare(string $query): mysqli_stmt|false {
        if (str_starts_with($query,'SELECT * FROM `'.sharity_donation_order_table().'`') && $this->beforeLock !== null) {
            $callback=$this->beforeLock; $this->beforeLock=null; $callback();
        }
        if (str_starts_with($query,'INSERT INTO `'.sharity_donation_movement_table().'`')) {
            $this->inserts++;
            if ($this->fault==='second-insert' && $this->inserts===2) { throw new RuntimeException('synthetic insert failure'); }
        }
        if ($this->committed && $this->fault==='corrupt-readback' && str_contains($query,'SUBSTRING(CAST(facts_json')) {
            $query=str_replace('financial_digest, first_recorded_at_utc', "REPEAT('0',64) AS financial_digest, first_recorded_at_utc", $query);
        }
        return parent::prepare($query);
    }
    public function commit(int $flags=0, ?string $name=null): bool {
        if ($this->fault==='commit-before') { throw new RuntimeException('synthetic lost commit request'); }
        $result=parent::commit($flags,$name); $this->committed=true;
        if ($this->fault==='commit-after') { throw new RuntimeException('synthetic lost commit ACK'); }
        return $result;
    }
}
function movementConnection(string $fault=''): MovementMysqli {
    global $wpdb,$port; $wpdb->db->close();
    $db=new MovementMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);
    $db->set_charset('utf8mb4'); $db->fault=$fault; $wpdb->db=$db; return $db;
}
try {
    check(get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)==='1','explicit CLI installed movement schema');
    [$o,$data,$saved]=movementFixture('base','txn_MBASE'); $id=$o['order_id']; $proposal=movementProposal($o);
    $gets=$financeHttp->gets;$posts=calls();$opts=$options;$observations=financeCount();
    $receipt=sharity_donation_finance_record_movements($id,$proposal,$now);
    check(movementCount()===1 && $receipt['movements_recorded']===true,'one exact native movement registered');
    check(sharity_donation_finance_record_movements($id,$proposal,$now+30)===$receipt,'replay keeps original time and contents');
    foreach(['payment_verified','allocation_eligible','settlement_reconciled','apply_supported'] as $flag){check($receipt[$flag]===false,'registry not financial acceptance: '.$flag);}
    check($financeHttp->gets===$gets && calls()===$posts && $options===$opts && financeCount()===$observations
        && sharity_donation_webhook_order($id)===$o,'no SDK, provider, option, observation or order mutation');
    check(in_array('backing_not_reconciled',$receipt['unresolved'],true) && !isset($receipt['ngo_minor']),'no backing/payable claim');
    check(sharity_donation_movement_schema_apply() && movementCount()===1,'compatible schema replay preserves registry');
    // CLI requires an exact explicit digest. Generic financial --apply remains unsupported.
    foreach([true,'bad',str_repeat('0',64)] as $digest){
        fails(fn()=>sharity_donation_cli_payment_reconcile([],['order'=>$id,'record-movements'=>$digest]),
            $digest===str_repeat('0',64)?'DONATION_MOVEMENT_STALE':'DONATION_RECONCILE_UNAVAILABLE');
    }
    sharity_donation_cli_payment_reconcile([],['record-movements'=>$proposal,'order'=>$id]);
    check(json_decode(end(WP_CLI::$lines),true)===$receipt,'CLI returns full persisted receipt');
    // WordPress can disable mysqli exceptions. Duplicate readback still succeeds.
    mysqli_report(MYSQLI_REPORT_OFF);
    try{check(sharity_donation_finance_record_movements($id,$proposal,$now)===$receipt,'non-strict mysqli duplicate supported');}
    finally{mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);}
    $x=$data;$x['original']['initial_charge_balance']['status']='pending';
    $metadata=reconcileFixture($o,$x);$newProposal=movementProposal($o);
    check($newProposal!==$proposal,'new observation invalidates old proposal even with equal financial facts');
    fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_STALE');
    $replay=sharity_donation_finance_record_movements($id,$newProposal,$now+60);
    check($replay['movements']===$receipt['movements'] && movementCount()===1,'metadata is not a second movement');removeReconcileFixture($metadata);
    // Two simultaneous orders claim one real provider identity: exactly one wins.
    [$a]=movementFixture('race-a','txn_COLLISION');[$b]=movementFixture('race-b','txn_COLLISION');
    $pa=movementProposal($a);$pb=movementProposal($b);$count=movementCount();
    $result=race(fn($slot)=>sharity_donation_finance_record_movements(($slot===0?$a:$b)['order_id'],$slot===0?$pa:$pb,$now),'FROM `'.sharity_donation_order_table().'`');
    check(count(array_filter($result,fn($r)=>isset($r['value'])))===1 && movementCount()===$count+1,'two DB connections, one cross-order winner');
    check(count(array_filter($result,fn($r)=>($r['error']??'')==='DONATION_MOVEMENT_CONFLICT'))===1,'loser receives an explicit conflict');
    [$same]=movementFixture('same','txn_SAME');$p=movementProposal($same);$count=movementCount();
    $result=race(fn()=>sharity_donation_finance_record_movements($same['order_id'],$p,$now),'FROM `'.sharity_donation_order_table().'`');
    check(isset($result[0]['value'],$result[1]['value']) && $result[0]['value']===$result[1]['value'] && movementCount()===$count+1,'same order race returns one identical immutable receipt');
    // A valid changed observation alone cannot overwrite already recorded economic facts.
    removeReconcileFixture($saved);
    foreach(['money','currency','source'] as $case){
        $x=$data;
        if($case==='money'){$x['original']['initial_charge_balance']['gross_minor']='50001';$x['original']['initial_charge_balance']['net_minor']='48001';}
        if($case==='currency'){$x['original']['initial_charge_balance']['currency']='EUR';$x['original']['initial_charge_balance']['fee_details'][0]['currency']='EUR';}
        if($case==='source'){$x['original']['charge_id']='ch_ALTERNATIVE';}
        $changed=reconcileFixture($o,$x);
        fails(fn()=>sharity_donation_finance_record_movements($id,movementProposal($o),$now),'DONATION_MOVEMENT_CONFLICT');removeReconcileFixture($changed);
    }
    $saved=reconcileFixture($o,$data);
    // Signed refunds, reversal and dispute legs remain distinct exact movements.
    [$batch,$x,$batchOld]=movementFixture('batch','txn_BATCH');removeReconcileFixture($batchOld);
    $x['refunds']=[['id'=>'re_MREFUND','balance'=>reconcileBalance('txn_MREFUND','re_MREFUND','refund','-1000','-10'),
        'failure_balance'=>reconcileBalance('txn_MREVERSAL','re_MREFUND','refund_failure','1000')]];
    $x['disputes']=[['id'=>'dp_MDISPUTE','balances'=>[reconcileBalance('txn_MDISPUTE','dp_MDISPUTE','adjustment','-50000','1500')]]];
    reconcileFixture($batch,$x);$p=movementProposal($batch);$count=movementCount();
    movementConnection('second-insert');
    fails(fn()=>sharity_donation_finance_record_movements($batch['order_id'],$p,$now),'DONATION_MOVEMENT_UNAVAILABLE');
    check(movementCount()===$count && movementCount($batch['order_id'])===0,'second INSERT failure rolls back whole real batch');
    movementConnection();$batchReceipt=sharity_donation_finance_record_movements($batch['order_id'],$p,$now);
    check(count($batchReceipt['movements'])===4 && movementCount()===$count+4,'all signed legs registered once');
    // A later duplicate owned by another order rolls back preceding new rows too.
    [$conflict,$x,$old]=movementFixture('batch-conflict','txn_AAANEW');removeReconcileFixture($old);
    $x['disputes']=[['id'=>'dp_COLLISION','balances'=>[reconcileBalance('txn_COLLISION','dp_COLLISION','adjustment','-100')]]];
    reconcileFixture($conflict,$x);$p=movementProposal($conflict);$count=movementCount();
    fails(fn()=>sharity_donation_finance_record_movements($conflict['order_id'],$p,$now),'DONATION_MOVEMENT_CONFLICT');
    check(movementCount()===$count && movementCount($conflict['order_id'])===0,'cross-order conflict rolls back earlier new leg');
    // The existing Amplifier fixture has one connected and two unknown platform legs.
    $amp=sharity_donation_finance_reconcile($ampOrder['order_id'],$now);$count=movementCount();
    $r=sharity_donation_finance_record_movements($ampOrder['order_id'],$amp['movement_proposal_digest'],$now);
    check(count($r['movements'])===1 && movementCount()===$count+1 && $r['unscoped_platform_movement_count']===2
        && in_array('platform_account_unverified',$r['unresolved'],true),'platform fees/refunds stay unscoped and unregistered');
    check($r['settlement_reconciled']===false,'partial scoped registration never claims financial reconciliation');
    $missing=financeOrder('movement-empty');$count=movementCount();
    fails(fn()=>sharity_donation_finance_record_movements($missing['order_id'],movementProposal($missing),$now),'DONATION_MOVEMENT_EMPTY');
    check(movementCount()===$count,'no observations do not become an empty successful batch');
    // Lost commit reply after commit succeeds only by complete persisted readback.
    foreach(['commit-after','commit-before','corrupt-readback'] as $fault){
        [$f]=movementFixture($fault,'txn_'.str_replace('-','',$fault));$p=movementProposal($f);$count=movementCount();movementConnection($fault);
        if($fault==='commit-after'){
            $r=sharity_donation_finance_record_movements($f['order_id'],$p,$now);
            check($r['movements_recorded'] && movementCount()===$count+1,'lost commit ACK recovered by readback');
        }else{
            fails(fn()=>sharity_donation_finance_record_movements($f['order_id'],$p,$now),$fault==='corrupt-readback'?'DONATION_MOVEMENT_CONFLICT':'DONATION_MOVEMENT_UNAVAILABLE');
            check(movementCount()===$count+($fault==='corrupt-readback'?1:0),'uncertain/malformed response never fabricated as success');
        }
        movementConnection();
    }
    // Evidence append between proposal read and lock is stale, no partial registration.
    [$stale,$staleData]=movementFixture('stale','txn_STALE');$p=movementProposal($stale);$count=movementCount();
    $db=movementConnection();$db->beforeLock=function()use($stale,$staleData,$port){$old=$GLOBALS['wpdb'];$GLOBALS['wpdb']=new OrderDb((int)$port);
        try{$x=$staleData;$x['original']['initial_charge_balance']['status']='pending';reconcileFixture($stale,$x);}
        finally{$GLOBALS['wpdb']->db->close();$GLOBALS['wpdb']=$old;}};
    fails(fn()=>sharity_donation_finance_record_movements($stale['order_id'],$p,$now),'DONATION_MOVEMENT_STALE');
    check(movementCount()===$count,'new evidence after inventory excludes stale batch');movementConnection();
    // The write path must not silently reconnect or nest a caller transaction.
    $wpdb->db->query('START TRANSACTION');
    try{fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_UNAVAILABLE');
        check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='1','caller transaction remains open');}
    finally{$wpdb->db->rollback();}
    $db=movementConnection();$db->beforeLock=function()use($port){$GLOBALS['wpdb']=new OrderDb((int)$port);};
    fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_UNAVAILABLE');$db->close();
    // The actual server packet limit is checked before any registry write.
    $packet=(int)$wpdb->db->query('SELECT @@global.max_allowed_packet AS n')->fetch_assoc()['n'];$count=movementCount();
    try{
        // At 1 KiB MariaDB cannot return this evidence body intact: the existing read fails closed.
        $wpdb->db->query('SET GLOBAL max_allowed_packet=1024');movementConnection();
        fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_UNAVAILABLE');
        check(movementCount()===$count,'unreadable evidence at tiny packet limit does not write');
        // 4 KiB can read this fixture, but fails the actual INSERT payload + protocol headroom check.
        $wpdb->db->query('SET GLOBAL max_allowed_packet=4096');movementConnection();
        fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_FINANCE_PACKET_TOO_SMALL');
        check(movementCount()===$count,'packet failure has no partial registry write');
    }finally{$wpdb->db->query('SET GLOBAL max_allowed_packet='.$packet);movementConnection();}
    // Broken registry facts/schema never become success; other observation reads still work.
    $wpdb->db->query("UPDATE `{$movementTable}` SET financial_digest=REPEAT('0',64) WHERE balance_transaction_id='txn_MBASE'");
    fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_CONFLICT');
    $wpdb->query($wpdb->prepare("UPDATE `{$movementTable}` SET financial_digest=%s WHERE balance_transaction_id='txn_MBASE'",$receipt['movements'][0]['financial_digest']));
    foreach([
        ["ALTER TABLE `{$movementTable}` ENGINE=MyISAM","ALTER TABLE `{$movementTable}` ENGINE=InnoDB"],
        ["ALTER TABLE `{$movementTable}` DROP PRIMARY KEY, ADD KEY identity_nonunique(provider,livemode,account_id,balance_transaction_id)","ALTER TABLE `{$movementTable}` DROP INDEX identity_nonunique, ADD PRIMARY KEY(provider,livemode,account_id,balance_transaction_id)"],
        ["ALTER TABLE `{$movementTable}` MODIFY account_id varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL","ALTER TABLE `{$movementTable}` MODIFY account_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL"]
    ] as [$bad,$restore]){
        $count=movementCount();$wpdb->db->query($bad);
        fails(fn()=>sharity_donation_finance_record_movements($id,$proposal,$now),'DONATION_MOVEMENT_UNAVAILABLE');
        check(!sharity_donation_movement_schema_apply() && get_option(SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION)==='0' && movementCount()===$count,'bad schema disables registry without repair/data loss');
        check(sharity_donation_finance_reconcile($id,$now)['movement_proposal_digest']===$proposal,'old inventory still available');
        $wpdb->db->query($restore);check(sharity_donation_movement_schema_apply(),'explicit isolated schema restore');
    }
    echo 'Donation movement registry MariaDB: '.($checks-$movementStart)." checks; real cross-order race and commit/readback faults\n";
} finally {
    $wpdb->db->query('DROP TABLE IF EXISTS `'.$movementTable.'`');
    unset($options[SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION]);
}
