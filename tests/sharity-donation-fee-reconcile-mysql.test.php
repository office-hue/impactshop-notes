<?php
/** Included before fee execution cleanup: real MariaDB, synthetic original receipts, no live Stripe. */
declare(strict_types=1);
$feeReconcileStart=$checks;
function feeMatch(array $p):array{return sharity_donation_fee_reconcile($p['operation_id'],$p['proposal_digest'],$GLOBALS['now']);}
function feeMatchRows(array $d):array{
    [$db,$q]=sharity_donation_backing_db();return[sharity_donation_fee_row($q,$d['operation_id']),sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',[$d['claim_key']])];
}
function feeMatchCapacity(array $d):array{
    global $wpdb;[$db,$q]=sharity_donation_backing_db();$frame=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? ORDER BY revision DESC LIMIT 1',[$d['claim']['frame_key']]);
    return sharity_donation_backing_capacity($q,json_decode($frame['document'],true));
}
function feeMatchReject(array $p,string $label):void{
    try{feeMatch($p);throw new LogicException('unexpected confirmation: '.$label);}catch(RuntimeException $e){check(str_starts_with($e->getMessage(),'DONATION_'),'sanitized refusal '.$label);}
}
function feeMatchData(array $o,array $d,array $data,FeeExecutionHttp $http,?callable $change=null,bool $record=true):array{
    global $now;$r=$http->receipt();$id=$r['id'];$tag=substr($d['operation_id'],0,24);
    $data['original']['application_fee']['refund_state']['amount_refunded_minor']=$d['request']['amount_minor'];
    $data['fee_refunds']=[['id'=>$id,'fee_id'=>$r['fee'],'amount_minor'=>(string)$r['amount'],'currency'=>$d['currency'],'created'=>$r['created'],
        'balance'=>reconcileBalance('txn_MD'.$tag,$id,'application_fee_refund',(string)-$r['amount'],'0','platform'),
        'connected_credit_verified'=>true,'connected_credit'=>reconcileBalance('txn_MC'.$tag,$id,'application_fee_refund',(string)$r['amount'])+['account_id'=>$http->account]]];
    $walk=static function(array &$x)use(&$walk,$d):void{foreach($x as $k=>&$v){if(is_array($v)){$walk($v);}elseif($k==='currency'){$v=$d['currency'];}}unset($v);};$walk($data);
    if($change!==null){$change($data);}$saved=reconcileFixture($o,$data);
    if($record){sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);}
    return[$saved,$data];
}
foreach(['HUF','EUR','USD','GBP']as$cc){
    [$o,$p,$d,$h,$data]=feeExecSetup('MATCH'.$cc,$cc);$options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);
    feeMatchReject($p,'provider acknowledgement alone '.$cc);
    [$saved,$data]=feeMatchData($o,$d,$data,$h);$beforeRows=feeMatchRows($d);$beforeCap=feeMatchCapacity($d);$posts=feeExecPostCount();$requests=$h->requests;$gets=$financeHttp->gets;
    $r=feeMatch($p);check($r['state']==='correction_confirmed'&&$r['correction_confirmed']&&count($r['matched_movements'])===2&&$r['reserved_minor']==='400','exact native two-leg confirmation '.$cc);
    foreach(['settlement_reconciled','allocation_eligible','activation_allowed','retained_cash_verified','ngo_bank_receipt','backing_released']as$k){check($r[$k]===false,'not broader acceptance '.$k);}
    foreach(feeMatchRows($d)as$i=>$row){$beforeRows[$i]['state']='confirmed';check($row===$beforeRows[$i],'only state changed, immutable content/time '.$cc);}
    check(feeMatchCapacity($d)===$beforeCap,'confirmed amount counted exactly once '.$cc);
    $options['sharity_donation_fee_execution_enabled']='0';$options['sharity_donation_orders_enabled']='0';
    check(feeMatch($p)===$r,'idempotent historical match after switches off '.$cc);
    check(feeExecPostCount()===$posts&&$h->requests===$requests&&$financeHttp->gets===$gets,'local reconciliation makes no provider call '.$cc);
    check(feeRecover($p,$h)['state']==='provider_recorded'&&feeMatchRows($d)[0]['state']==='confirmed','provider GET recovery preserves confirmed '.$cc);
    $options['sharity_donation_fee_execution_enabled']='1';
    check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$posts,'confirmed cannot dispatch again '.$cc);
    check(sharity_donation_fee_schema_apply()&&feeMatch($p)===$r,'explicit schema replay preserves confirmation '.$cc);
    $options['sharity_donation_orders_enabled']='1';
}
// Actual provider cost can be bounded without releasing the unused bound.
backingClear();[$o,$data]=feeFixture('MATCHCOST');[$input]=feeBacking($o,null,'50');$p=feeApply($o,$input);
[$row,$d,$account]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);$h=new FeeExecutionHttp($feeExecLog);$h->doc=$d;$h->account=$account;
$options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);
feeMatchData($o,$d,$data,$h,static function(&$x):void{$r=&$x['fee_refunds'][0];$r['balance']=reconcileBalance($r['balance']['id'],$r['id'],'application_fee_refund','-400','10','platform');});
$beforeCap=feeMatchCapacity($d);check(feeMatch($p)['reserved_minor']==='450'&&feeMatchCapacity($d)===$beforeCap,'actual cost10 within bound50 preserves whole450 hold');
foreach(['no_receipt','prepared','unknown','missing_debit','missing_credit','pending','unregistered','currency','account','fee','amount','created','registry_owner','registry_digest','receipt_digest','claim_document','live','negative_cost','cost_over_bound']as$case){
    [$o,$p,$d,$h,$data]=feeExecSetup('CASE'.str_replace('_','',$case));$options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);
    $alter=static function(&$x)use($case):void{
        $r=&$x['fee_refunds'][0];
        if($case==='missing_debit'){$r['balance']=null;$x['balances_pending']=true;}
        if($case==='missing_credit'){$r['connected_credit']=null;$r['connected_credit_verified']=false;}
        if($case==='pending'){$r['balance']['status']='pending';$r['connected_credit']['status']='pending';}
        if($case==='currency'){foreach(['balance','connected_credit']as$k){$r[$k]['currency']='EUR';$r[$k]['exchange_rate']=1.2;}}
        if($case==='account'){$r['connected_credit']['account_id']='acct_OTHER';}
        if($case==='fee'){$r['fee_id']='fee_OTHER';}
        if($case==='amount'){$r['amount_minor']='401';}
        if($case==='created'){$r['created']++;}
        if($case==='live'){$x['original']['livemode']=!$x['original']['livemode'];}
        if(in_array($case,['negative_cost','cost_over_bound'],true)){$fee=$case==='negative_cost'?'-1':'1';$r['balance']=reconcileBalance($r['balance']['id'],$r['id'],'application_fee_refund','-400',$fee,'platform');}
    };
    $record=!in_array($case,['unregistered','account','fee','amount','live'],true);
    feeMatchData($o,$d,$data,$h,$alter,$record);$rt=sharity_donation_fee_receipt_table();$ct=sharity_donation_backing_table('claims');$ft=sharity_donation_fee_table();$mt=sharity_donation_movement_table();
    if($case==='no_receipt'){$wpdb->db->query($wpdb->prepare("DELETE FROM `{$rt}` WHERE operation_id=%s",$p['operation_id']));}
    if(in_array($case,['prepared','unknown'],true)){foreach([[$ct,'claim_key',$d['claim_key']],[$ft,'operation_id',$p['operation_id']]]as[$t,$k,$id]){$wpdb->db->query($wpdb->prepare("UPDATE `{$t}` SET state=%s WHERE `{$k}`=%s",$case,$id));}}
    if($case==='registry_owner'){$wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET order_id=%s WHERE order_id=%s AND balance_transaction_id LIKE 'txn_MC%%'",'do_'.str_repeat('f',32),$o['order_id']));}
    if($case==='registry_digest'){$wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET financial_digest=%s WHERE order_id=%s AND balance_transaction_id LIKE 'txn_MC%%'",str_repeat('0',64),$o['order_id']));}
    if($case==='receipt_digest'){$wpdb->db->query($wpdb->prepare("UPDATE `{$rt}` SET digest=%s WHERE operation_id=%s",str_repeat('0',64),$p['operation_id']));}
    if($case==='claim_document'){$wpdb->db->query($wpdb->prepare("UPDATE `{$ct}` SET digest=%s WHERE claim_key=%s",str_repeat('0',64),$d['claim_key']));}
    $before=feeExecPostCount();
    if($case==='unknown'){check(feeMatch($p)['correction_confirmed'],'unknown with exact proof converges');}
    else{feeMatchReject($p,$case);$state=$wpdb->db->query($wpdb->prepare("SELECT state FROM `{$ft}` WHERE operation_id=%s",$p['operation_id']))->fetch_assoc()['state'];check($state===$case||$state==='attempted','refusal preserves prior operation '.$case);}
    check(feeExecPostCount()===$before,'refusal never provider POST '.$case);feeExecConnection();
}
foreach(['commit-before','commit-after','corrupt-readback','update-after']as$fault){
    [$o,$p,$d,$h,$data]=feeExecSetup('FAULT'.str_replace('-','',$fault));$options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);feeMatchData($o,$d,$data,$h);
    feeExecConnection($fault);
    if($fault==='commit-after'){check(feeMatch($p)['correction_confirmed'],'lost committed ACK converges through full readback');}
    else{feeMatchReject($p,$fault);}
    feeExecConnection();$state=in_array($fault,['commit-after','corrupt-readback'],true)?'confirmed':'attempted';
    foreach(feeMatchRows($d)as$row){check($row['state']===$state,'both rows atomic '.$fault);}
}
[$o,$p,$d,$h,$data]=feeExecSetup('MATCHRACE');$options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);feeMatchData($o,$d,$data,$h);$posts=feeExecPostCount();
$rr=backingRace(fn()=>feeMatch($p));feeExecConnection();check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value']&&$rr[0]['value']['correction_confirmed'],'two genuine connections converge to same confirmation');
check(feeExecPostCount()===$posts,'parallel local confirmation sends no POST');
foreach([[[],[]],[[],['operation'=>$p['operation_id'],'digest'=>true]],[['arg'],['operation'=>$p['operation_id'],'digest'=>$p['proposal_digest']]],[[],['operation'=>$p['operation_id'],'digest'=>$p['proposal_digest'],'apply'=>true]]]as[$a,$b]){fails(fn()=>sharity_donation_cli_fee_reconcile($a,$b),'DONATION_FEE_INPUT');}
echo 'Donation fee reconciliation MariaDB: '.($checks-$feeReconcileStart)." checks; matched legs, retained backing, no provider calls\n";
