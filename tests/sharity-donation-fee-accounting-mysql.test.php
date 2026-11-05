<?php
/** Real isolated MariaDB + synthetic SDK: accounting is not provider/bank acceptance. */
declare(strict_types=1);
(static function():void{
    global $checks,$options,$now,$financeHttp,$wpdb;
$feeAccountingStart=$checks;
class FeeAccountingHttp extends FeeExecutionHttp {
    public function receipt():array{$r=parent::receipt();$r['created']=$GLOBALS['now']-10;return$r;}
}
function feeAccountInput(array $d,array $spent,int $offset=0):array{
    global $now;[$db,$q]=sharity_donation_backing_db();$row=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? ORDER BY revision DESC LIMIT 1',[$d['claim']['frame_key']]);
    $p=json_decode($row['document'],true);$p['frame']['previous_revision']=(int)$row['revision'];$p['frame']['previous_digest']=$row['digest'];
    $p['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now+$offset);$sum=0;
    foreach($p['sources']as&$s){$key=sharity_donation_backing_source_key($s,$p['frame']);$s['retained_minor']=(string)((int)$s['retained_minor']-($spent[$key]??0));$s['as_of_utc']=$p['frame']['as_of_utc'];$sum+=(int)$s['retained_minor'];}unset($s);
    $p['frame']['retained_minor']=(string)$sum;$alloc=[];foreach($d['claim']['allocations']as$a){$alloc[]=['source_key'=>$a['source_key'],'spent_minor'=>(string)($spent[$a['source_key']]??0)];}
    return ['version'=>1,'cash_proof'=>$p,'spent_allocations'=>$alloc];
}
function feeAccount(array $p,array $input,string $apply='',int $offset=0):array{
    return sharity_donation_fee_account($p['operation_id'],$p['proposal_digest'],$input,$GLOBALS['now']+$offset,$apply);
}
function feeAccountFixture(string $tag,string $currency='HUF',string $bound='50',string $cost='10'):array{
    global $options,$feeExecLog;
    backingClear();[$o,$data]=feeFixture('ACC'.$tag,$currency);[$input]=feeBacking($o,backingOwn('account'.$tag,'2000'),$bound);$p=feeApply($o,$input);
    [$row,$d,$account]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);$h=new FeeAccountingHttp($feeExecLog);$h->doc=$d;$h->account=$account;
    $options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);
    [$saved,$data]=feeMatchData($o,$d,$data,$h,static function(&$x)use($cost,$currency):void{$r=&$x['fee_refunds'][0];$r['balance']=reconcileBalance($r['balance']['id'],$r['id'],'application_fee_refund','-400',$cost,'platform');$r['balance']['currency']=$currency;foreach(['balance','connected_credit']as$leg){$r[$leg]['created']=$GLOBALS['now']-9;$r[$leg]['available_on']=$GLOBALS['now']-8;}foreach($r['balance']['fee_details']as&$fd){$fd['currency']=$r['balance']['currency'];}unset($fd);});
    feeMatch($p);$key=$d['claim']['allocations'][0]['source_key'];$in=feeAccountInput($d,[$key=>400+(int)$cost]);
    return[$o,$p,$d,$h,$data,$in];
}
function feeAccountPrepareCurrent(array $o,string $key,string $bound):array{
    global $now;[$db,$q]=sharity_donation_backing_db();$f=sharity_donation_backing_row($q,'frames','WHERE frame_key = ? ORDER BY revision DESC LIMIT 1',[$key]);$fp=json_decode($f['document'],true);
    $inv=sharity_donation_finance_reconcile($o['order_id'],$now);$snap=sharity_donation_order_decode($o)['snapshot'];$c=sharity_donation_fee_candidate($o,$snap,$inv,sharity_donation_target_compute($inv,$snap));
    return feeApply($o,['version'=>1,'frame_key'=>$key,'frame_revision'=>(int)$f['revision'],'frame_digest'=>$f['digest'],'fee_cost_bound_minor'=>$bound,'fee_cost_proof'=>backingRef('nextActual'),
        'allocations'=>[['source_key'=>sharity_donation_backing_source_key($fp['sources'][0],$fp['frame']),'amount_minor'=>(string)((int)$c['refund_minor']+(int)$bound)]]]);
}
function feeAccountObserve(array $o,array $p,array $data,string $cost='10'):array{
    global $options,$feeExecLog;[$row,$d,$account]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);$h=new FeeAccountingHttp($feeExecLog);$h->doc=$d;$h->account=$account;
    $h->refunds=array_map(static fn($r)=>['object'=>'fee_refund','id'=>$r['id'],'fee'=>$r['fee_id'],'amount'=>(int)$r['amount_minor'],'currency'=>strtolower($r['currency'])],$data['fee_refunds']);
    $options['sharity_donation_fee_execution_enabled']='1';feeExec($p,$h);$old=$data['fee_refunds'];
    [, $data]=feeMatchData($o,$d,$data,$h,static function(&$x)use($old,$cost,$d):void{$r=&$x['fee_refunds'][0];$r['balance']=reconcileBalance($r['balance']['id'],$r['id'],'application_fee_refund',(string)-(int)$d['request']['amount_minor'],$cost,'platform');
        $r['balance']['currency']=$d['currency'];foreach($r['balance']['fee_details']as&$detail){$detail['currency']=$d['currency'];}unset($detail);
        foreach(['balance','connected_credit']as$leg){$r[$leg]['created']=$GLOBALS['now']-9;$r[$leg]['available_on']=$GLOBALS['now']-8;}
        unset($r);$x['fee_refunds']=array_merge($old,$x['fee_refunds']);$x['original']['application_fee']['refund_state']['amount_refunded_minor']=(string)array_sum(array_column($x['fee_refunds'],'amount_minor'));
    });feeMatch($p);return[$d,$h,$data];
}
class FeeAccountingMysqli extends FeeExecutionMysqli{
    public function prepare(string $sql):mysqli_stmt|false{
        if($this->fault==='account-insert'&&str_starts_with($sql,'INSERT INTO `'.sharity_donation_fee_accounting_table().'`')){throw new RuntimeException('synthetic accounting insert failure');}
        if($this->fault==='claim-readback'&&$this->committed&&str_contains($sql,' AS document FROM `'.sharity_donation_backing_table('claims').'`')){$sql=str_replace('`state`',"'unknown' AS `state`",$sql);}
        return parent::prepare($sql);
    }
}
function feeAccountConnection(string $fault=''):FeeAccountingMysqli{
    global $wpdb,$port;$wpdb->db->close();$db=new FeeAccountingMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;
}
function feeAccountOtherConnection(callable $run):void{
    global $wpdb,$port;$saved=$wpdb->db;$other=new FeeAccountingMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$other->set_charset('utf8mb4');$wpdb->db=$other;
    try{$run();}finally{$other->close();$wpdb->db=$saved;}
}

foreach(['HUF','EUR','USD','GBP']as$cc){
    [$o,$p,$d,$h,$data,$in]=feeAccountFixture('BASE'.$cc,$cc);$before=feeMatchRows($d);$posts=feeExecPostCount();$gets=$financeHttp->gets;$count=backingRows('frames');
    $dry=feeAccount($p,$in);check(backingRows('frames')===$count&&feeMatchRows($d)===$before,'dry-run makes no writes '.$cc);
    check($dry['spent_minor']==='410'&&$dry['residual_minor']==='40','actual cost and residual '.$cc);
    $r=feeAccount($p,$in,$dry['proposal_digest']);check(!$dry['accounting_recorded']&&$dry['state']==='accounting_proposal'&&$r['accounting_recorded']&&$r['proposal_digest']===$dry['proposal_digest']&&backingRows('frames')===$count+1,'dry-run distinct from atomic accounting '.$cc);
    check(feeMatchCapacity($d)['available_minor']==='1550'&&feeMatchCapacity($d)['held_minor']==='40','1590 cash minus40 residual, spend never deducted twice '.$cc);
    foreach(feeMatchRows($d)as$row){check($row['state']==='accounted','operation and current claim accounted '.$cc);}
    check(feeAccount($p,$in,$r['proposal_digest'],1)===$r,'old accounting repeats exact immutable receipt '.$cc);
    check(feeMatch($p)===$r,'old reconcile reads accounting witness '.$cc);
    foreach(['activation_allowed','retained_cash_verified','allocation_eligible','settlement_reconciled','ngo_bank_receipt','backing_released']as$f){check($r[$f]===false,'no broader acceptance '.$f);}
    check(feeExecPostCount()===$posts&&$financeHttp->gets===$gets,'accounting no provider calls '.$cc);
    check(feeRecover($p,$h)['state']==='provider_recorded'&&feeMatchRows($d)[0]['state']==='accounted','GET recovery never downgrades accounted '.$cc);
    check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$posts,'historical execution never POSTs '.$cc);
}
// Successor from a later donor refund preserves original fee request, receipt and residual.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('NEXT');$dry=feeAccount($p,$in);$r=feeAccount($p,$in,$dry['proposal_digest']);$oldRows=feeMatchRows($d);
$data['refunds'][]=['id'=>'re_ACCOUNTNEXT','balance'=>reconcileBalance('txn_ACCOUNTNEXT','re_ACCOUNTNEXT','refund','-1000'),'failure_balance'=>null];
reconcileFixture($o,$data);sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);targetSave($o);
$input=['version'=>1,'frame_key'=>$r['frame_key'],'frame_revision'=>$r['frame_revision'],'frame_digest'=>hash('sha256',sharity_donation_finance_json($in['cash_proof'])),
    'fee_cost_bound_minor'=>'0','fee_cost_proof'=>backingRef('nextbound'),'allocations'=>[]];
$inv=sharity_donation_finance_reconcile($o['order_id'],$now);$snap=sharity_donation_order_decode($o)['snapshot'];$candidate=sharity_donation_fee_candidate($o,$snap,$inv,sharity_donation_target_compute($inv,$snap));
check((int)$candidate['refund_minor']>0,'later refund creates a positive incremental correction');$input['allocations']=[['source_key'=>$d['claim']['allocations'][0]['source_key'],'amount_minor'=>$candidate['refund_minor']]];
$np=feeApply($o,$input);check($np['operation_id']!==$p['operation_id']&&$np['refund_minor']===$candidate['refund_minor'],'new generation prepared once');
[$db,$q]=sharity_donation_backing_db();check(sharity_donation_fee_row($q,$p['operation_id'])===$oldRows[0],'old operation document/state/time preserved');
check(feeAccount($p,$in,$r['proposal_digest'])===$r&&feeMatch($p)===$r,'old replay/reconcile after successor independent of current claim');
$successor=feeMatchRows($d)[1];feeRecover($p,$h);check(feeMatchRows($d)[1]===$successor,'old recovery cannot modify successor');
check(feeMatchCapacity($d)['held_minor']===(string)(40+(int)$np['refund_minor']),'old residual and new hold each counted once');
// Fresh ordinary cash import already reflects this debit; accounting must not subtract again.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('POSTCASH');$cash=backingApply($in['cash_proof']);
$in=feeAccountInput($d,[],1);$in['spent_allocations'][0]['spent_minor']='410';$dry=feeAccount($p,$in,'',1);feeAccount($p,$in,$dry['proposal_digest'],1);
check(feeMatchCapacity($d)['available_minor']==='1550','generic post-spend snapshot is not debited twice');
foreach(['cash_not_reduced','over_spent','missing_source','liability_changed','old_proof','stale_digest']as$case){
    [$o,$p,$d,$h,$data,$in]=feeAccountFixture('BAD'.str_replace('_','',$case));$before=feeMatchRows($d);$n=backingRows('frames');
    if($case==='cash_not_reduced'){$in['cash_proof']['sources'][0]['retained_minor']='2000';$in['cash_proof']['frame']['retained_minor']='2000';}
    if($case==='over_spent'){$in['spent_allocations'][0]['spent_minor']='451';}
    if($case==='missing_source'){$in['spent_allocations']=[];}
    if($case==='liability_changed'){$in['cash_proof']['frame']['external_liability_minor']='1';}
    if($case==='old_proof'){$in['cash_proof']['frame']['previous_revision']=0;$in['cash_proof']['frame']['previous_digest']=null;}
    try{$dry=feeAccount($p,$in);feeAccount($p,$in,$case==='stale_digest'?str_repeat('0',64):$dry['proposal_digest']);throw new LogicException('unexpected accounting '.$case);}
    catch(RuntimeException $e){check(str_starts_with($e->getMessage(),'DONATION_'),'sanitized refusal '.$case);}
    check(feeMatchRows($d)===$before&&backingRows('frames')===$n,'failure preserves entire prior hold/frame '.$case);
}
foreach(['commit-before','commit-after','corrupt-readback','update-after']as$fault){
    [$o,$p,$d,$h,$data,$in]=feeAccountFixture('FAULT'.str_replace('-','',$fault));$dry=feeAccount($p,$in);feeExecConnection($fault);
    try{$result=feeAccount($p,$in,$dry['proposal_digest']);check($fault==='commit-after','only committed ACK loss can converge');}
    catch(RuntimeException $e){check($fault!=='commit-after','known immutable commit converges');}
    feeExecConnection();$expected=in_array($fault,['commit-after','corrupt-readback'],true)?'accounted':'confirmed';
    foreach(feeMatchRows($d)as$row){check($row['state']===$expected,'no partial state '.$fault);}
}
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('RACE');$dry=feeAccount($p,$in);$rr=backingRace(fn()=>feeAccount($p,$in,$dry['proposal_digest']));feeExecConnection();
check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value'],'two real connections converge to one accounting '.json_encode($rr));
foreach([[[],[]],[[],['operation'=>$p['operation_id'],'digest'=>true,'input'=>'bad']],[['arg'],['operation'=>$p['operation_id'],'digest'=>$p['proposal_digest'],'input'=>'bad']]]as[$a,$b]){fails(fn()=>sharity_donation_cli_fee_account($a,$b),'DONATION_FEE_INPUT');}
// Two complete generations: retain first refund evidence while adding the second.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('TWOGEN');$dry=feeAccount($p,$in);$first=feeAccount($p,$in,$dry['proposal_digest']);
$data['refunds'][]=['id'=>'re_ACCTWOGEN','balance'=>reconcileBalance('txn_ACCTWOGEN','re_ACCTWOGEN','refund','-1000'),'failure_balance'=>null];
reconcileFixture($o,$data);sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);targetSave($o);
$next=feeAccountPrepareCurrent($o,$d['claim']['frame_key'],'50');[$nd,$nh,$data]=feeAccountObserve($o,$next,$data,'10');
$key=$d['claim']['allocations'][0]['source_key'];$nextSpent=(int)$nd['request']['amount_minor']+10;$ni=feeAccountInput($nd,[$key=>$nextSpent],1);
$np=feeAccount($next,$ni,'',1);$second=feeAccount($next,$ni,$np['proposal_digest'],1);
check($second['spent_minor']===(string)$nextSpent&&feeMatchCapacity($nd)['held_minor']==='80','two complete generations preserve two residuals');
check(feeMatch($p)===$first&&feeMatch($next)===$second,'both historical accounting receipts remain readable');
check(feeMatchCapacity($nd)['available_minor']===(string)(2000-410-$nextSpent-80),'both real spends subtracted once from cash');
// Shared sources: preparation-frame cash cap includes concurrent obligations.
foreach(['same_frame','overlapping_frames']as$scenario){
    backingClear();[$oa,$da]=feeFixture('SHAREDA'.str_replace('_','',$scenario));[$ob,$dbdata]=feeFixture('SHAREDB'.str_replace('_','',$scenario));
    $source=backingOwn('shared'.$scenario,'2000');if($scenario==='same_frame'){$source['retained_minor']='1600';}
    [$req,$proof,$fr]=feeBacking($oa,$source,'50');$pa=feeApply($oa,$req);[$ad,$ah,$da]=feeAccountObserve($oa,$pa,$da);
    if($scenario==='overlapping_frames'){$initial=feeAccountInput($ad,[$req['allocations'][0]['source_key']=>410]);backingApply($initial['cash_proof']);}
    $pb=feeAccountPrepareCurrent($ob,$req['frame_key'],'50');[$bd,$bh,$dbdata]=feeAccountObserve($ob,$pb,$dbdata);
    $ia=feeAccountInput($ad,[$req['allocations'][0]['source_key']=>($scenario==='same_frame'?410:0)],1);$ia['spent_allocations'][0]['spent_minor']='410';
    $ap=feeAccount($pa,$ia,'',1);feeAccount($pa,$ia,$ap['proposal_digest'],1);
    $ib=feeAccountInput($bd,[$req['allocations'][0]['source_key']=>410],2);
    if($scenario==='same_frame'){$bad=$ib;$bad['cash_proof']['sources'][0]['retained_minor']='1100';$bad['cash_proof']['frame']['retained_minor']='1100';fails(fn()=>feeAccount($pb,$bad,'',2),'DONATION_FEE_SOURCE');}
    $bp=feeAccount($pb,$ib,'',2);feeAccount($pb,$ib,$bp['proposal_digest'],2);
    check($ib['cash_proof']['frame']['retained_minor']===($scenario==='same_frame'?'780':'1180'),'claim-frame suffix cap, no double debit '.$scenario);
    check(feeMatchCapacity($ad)['available_minor']===($scenario==='same_frame'?'700':'1100'),'shared cash conserves two spent amounts and residuals '.$scenario);
}
// Multi-source conservation: 250+200 hold becomes 220+190 spent and 30+10 residual.
backingClear();[$o,$data]=feeFixture('MULTISOURCE');$snap=sharity_donation_order_decode($o)['snapshot'];$snap['account_id']='acct_PLATFORM';
$proof=backingProof($snap,[backingOwn('multiA'),backingOwn('multiB')]);$fr=backingApply($proof);$parts=[];
foreach($proof['sources']as$i=>$s){$parts[]=['source_key'=>sharity_donation_backing_source_key($s,$proof['frame']),'amount_minor'=>$i===0?'250':'200'];}
$req=['version'=>1,'frame_key'=>$fr['frame_key'],'frame_revision'=>$fr['revision'],'frame_digest'=>$fr['proposal_digest'],'fee_cost_bound_minor'=>'50','fee_cost_proof'=>backingRef('multiBound'),'allocations'=>$parts];
$p=feeApply($o,$req);[$d,$h,$data]=feeAccountObserve($o,$p,$data);$in=feeAccountInput($d,[$parts[0]['source_key']=>220,$parts[1]['source_key']=>190]);
$dry=feeAccount($p,$in);feeAccount($p,$in,$dry['proposal_digest']);$capacity=feeMatchCapacity($d);
check($capacity['available_minor']==='1550'&&$capacity['held_minor']==='40'&&$capacity['source_available'][$parts[0]['source_key']]===750&&$capacity['source_available'][$parts[1]['source_key']]===800,'each original source conserves spent plus residual');
// Faults after the first write must roll back both table insertions and both state changes.
foreach(['frame-insert','account-insert','claim-readback']as$fault){
    [$o,$p,$d,$h,$data,$in]=feeAccountFixture('EXFAULT'.str_replace('-','',$fault));$dry=feeAccount($p,$in);$count=backingRows('frames');feeAccountConnection($fault);
    try{feeAccount($p,$in,$dry['proposal_digest']);throw new LogicException('fault expected '.$fault);}catch(RuntimeException $e){check(str_starts_with($e->getMessage(),'DONATION_'),'no false success '.$fault);}
    feeExecConnection();foreach(feeMatchRows($d)as$row){check($row['state']===($fault==='claim-readback'?'accounted':'confirmed'),'atomic or already committed '.$fault);}
    check(backingRows('frames')===$count+($fault==='claim-readback'?1:0),'frame atomicity '.$fault);
}
// Reconcile read its old context before another connection accounts the correction.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('RECONCILERACE');$dry=feeAccount($p,$in);$outer=feeAccountConnection();$accounted=null;
$outer->beforeLock=static function()use($p,$in,$dry,&$accounted):void{feeAccountOtherConnection(static function()use($p,$in,$dry,&$accounted):void{$accounted=feeAccount($p,$in,$dry['proposal_digest']);});};
check(feeMatch($p)===$accounted&&feeMatchRows($d)[0]['state']==='accounted','locked state reread prevents confirmed demotion');
// A new generation can appear immediately after commit, before the original accounting readback.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('SUCCESSORACK');$dry=feeAccount($p,$in);$outer=feeAccountConnection();$next=null;
$outer->afterCommit=static function()use($o,$data,$d,&$next):void{feeAccountOtherConnection(static function()use($o,$data,$d,&$next):void{
    $data['refunds'][]=['id'=>'re_ACCACK','balance'=>reconcileBalance('txn_ACCACK','re_ACCACK','refund','-1000'),'failure_balance'=>null];
    reconcileFixture($o,$data);sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$GLOBALS['now']);targetSave($o);
    $next=feeAccountPrepareCurrent($o,$d['claim']['frame_key'],'0');
});};
$r=feeAccount($p,$in,$dry['proposal_digest']);feeExecConnection();check($next!==null&&$r['accounting_recorded']&&feeMatch($p)===$r,'legitimate successor between commit and readback is accepted');
// Canonical but inconsistent successor claim is rejected, including a missing original claim.
[$db,$q]=sharity_donation_backing_db();$ct=sharity_donation_backing_table('claims');$old=sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',[$d['claim_key']]);
$bad=json_decode($old['document'],true);$bad['target_generation']=str_repeat('f',64);$json=sharity_donation_finance_json($bad);
$q("UPDATE `{$ct}` SET document = ?, digest = ? WHERE claim_key = ?",[$json,hash('sha256',$json),$d['claim_key']]);fails(fn()=>feeMatch($p),'DONATION_FEE_CONFLICT');
$q("UPDATE `{$ct}` SET document = ?, digest = ? WHERE claim_key = ?",[$old['document'],$old['digest'],$d['claim_key']]);check(feeMatch($p)===$r,'valid successor remains readable after synthetic corruption restored');
$q("DELETE FROM `{$ct}` WHERE claim_key = ?",[$d['claim_key']]);fails(fn()=>feeMatch($p),'DONATION_FEE_CONFLICT');
// A cash assertion predating the actual native debit cannot account the correction.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('CASHDATE');$in['cash_proof']['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-11);$in['cash_proof']['sources'][0]['as_of_utc']=$in['cash_proof']['frame']['as_of_utc'];
fails(fn()=>feeAccount($p,$in),'DONATION_FEE_SOURCE');
// Optional table absence preserves legacy preparation, execution and confirmation. DDL requires --apply.
backingClear();[$db,$q]=sharity_donation_backing_db();$at=sharity_donation_fee_accounting_table();$q("DROP TABLE `{$at}`");
fails(fn()=>sharity_donation_cli_fee_schema([],[]),'DONATION_FEE_SCHEMA_UNAVAILABLE');check($q('SHOW TABLE STATUS WHERE Name = ?',[$at])===[],'no flag means no DDL');
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('LEGACYNOACCOUNT');check(feeMatchRows($d)[0]['state']==='confirmed','missing optional accounting table preserves old workflow');
check(sharity_donation_fee_schema_apply(),'explicit schema creates additive table');
$q("ALTER TABLE `{$at}` DROP INDEX frame_residual");check(!sharity_donation_fee_schema_apply(),'wrong existing schema is rejected, not silently repaired');
$q("ALTER TABLE `{$at}` ADD INDEX frame_residual(frame_key,residual_minor)");check(sharity_donation_fee_schema_apply(),'restored isolated synthetic schema verifies');
$dry=feeAccount($p,$in);$path=backingFile($in);WP_CLI::$lines=[];sharity_donation_cli_fee_account([],['operation'=>$p['operation_id'],'digest'=>$p['proposal_digest'],'input'=>$path]);
check(str_contains(implode('',WP_CLI::$lines),'accounting_proposal')&&!str_contains(implode('',WP_CLI::$lines),'correction_accounted'),'CLI dry-run reports proposal, not stored accounting');
// Fixed anchor permits an appending writer and the identical waiter to both complete.
[$o,$p,$d,$h,$data,$in]=feeAccountFixture('ANCHOR');$dry=feeAccount($p,$in);$rr=backingRace(fn()=>feeAccount($p,$in,$dry['proposal_digest']));feeExecConnection();
check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value'],'anchor serializes latest-frame insert and waiter without retry '.json_encode($rr));

echo 'Donation fee accounting MariaDB: '.($checks-$feeAccountingStart)." checks; cash/refund generation and retained residual\n";

})();
