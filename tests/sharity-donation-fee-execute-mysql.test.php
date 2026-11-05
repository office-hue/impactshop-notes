<?php
/** Included in isolated real MariaDB harness; pinned SDK, synthetic HTTP, no network. */
declare(strict_types=1);
$feeExecStart=$checks;$feeExecPrior=Stripe\ApiRequestor::httpClient();$feeExecOptions=$options;
$feeExecLog=tempnam(sys_get_temp_dir(),'sharity-fee-post-');chmod($feeExecLog,0600);
class FeeExecutionHttp implements Stripe\HttpClient\ClientInterface {
    public array $doc=[];public string $account='';public array $refunds=[];public array $requests=[];
    public bool $drop=false;public bool $badScope=false;public bool $endless=false;public int $pageSize=100;public $afterPost=null;public $afterRead=null;
    public function __construct(public string $log){}
    public function receipt():array{$d=$this->doc;return['object'=>'fee_refund','id'=>'fr_'.substr($d['operation_id'],0,24),
        'fee'=>$d['request']['fee_id'],'amount'=>(int)$d['request']['amount_minor'],'currency'=>strtolower($d['currency']),
        'created'=>1800000000,'metadata'=>['sharity_operation'=>$d['operation_id'],'private_note'=>'NEVER_STORE'],
        'balance_transaction'=>null,'private_detail'=>'NEVER_STORE'];}
    public function request($method,$url,$headers,$params,$hasFile,$apiMode='v1',$maxNetworkRetries=null){
        global $wpdb;
        check((int)$wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']===0,'provider outside DB transaction');
        check($maxNetworkRetries===0,'no provider retries');
        foreach($headers as $h){check(!preg_match('/^Stripe-(Account|Context):/i',$h),'platform context only');}
        $path=parse_url($url,PHP_URL_PATH);$this->requests[]=[$method,$path,$params];$d=$this->doc;
        if($method==='get'&&$path==='/v1/account'){$r=['object'=>'account','id'=>$this->badScope?'acct_WRONG':$d['platform_account_id']];}
        elseif($method==='get'&&$path==='/v1/application_fees/'.$d['request']['fee_id']){$r=['object'=>'application_fee','id'=>$d['request']['fee_id'],
            'amount'=>(int)$d['candidate']['amount_minor'],'amount_refunded'=>(int)$d['candidate']['amount_refunded_minor'],
            'account'=>$this->account,'livemode'=>$d['livemode'],'currency'=>strtolower($d['currency']),'originating_transaction'=>null];}
        elseif($path==='/v1/application_fees/'.$d['request']['fee_id'].'/refunds'&&$method==='get'){
            $offset=0;if(isset($params['starting_after'])){foreach($this->refunds as $i=>$row){if($row['id']===$params['starting_after']){$offset=$i+1;}}}
            $data=array_slice($this->refunds,$offset,$this->pageSize);$r=['object'=>'list','data'=>$data,'has_more'=>$this->endless||$offset+count($data)<count($this->refunds)];
        }elseif($path==='/v1/application_fees/'.$d['request']['fee_id'].'/refunds'&&$method==='post'){
            check($params===['amount'=>(int)$d['request']['amount_minor'],'metadata'=>$d['request']['metadata']],'frozen exact request');
            check(in_array('Idempotency-Key: '.$d['idempotency_key'],$headers,true),'frozen exact key');
            file_put_contents($this->log,$d['operation_id']."\n",FILE_APPEND|LOCK_EX);$r=$this->receipt();$this->refunds=[$r];
            if($this->afterPost!==null){($this->afterPost)();}
            if($this->drop){throw new Stripe\Exception\ApiConnectionException('PRIVATE_UNCERTAIN');}
        }else{throw new RuntimeException('Unexpected synthetic HTTP');}
        if($method==='get'&&$this->afterRead!==null){($this->afterRead)();}
        return[json_encode($r,JSON_THROW_ON_ERROR),200,['request-id'=>'req_SYNTHETIC']];
    }
}
function feeExecSetup(string $tag,string $currency='HUF'):array {
    global $feeExecLog,$now;
    backingClear();[$o,$data]=feeFixture('EXEC'.$tag,$currency);[$input]=feeBacking($o);$p=feeApply($o,$input);
    [$row,$d,$account]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);
    $http=new FeeExecutionHttp($feeExecLog);$http->doc=$d;$http->account=$account;
    return[$o,$p,$d,$http,$data];
}
function feeExecPostCount():int{global $feeExecLog;return count(file($feeExecLog,FILE_IGNORE_NEW_LINES));}
function feeExec(array $p,FeeExecutionHttp $http):array {
    global $adapter,$now;return sharity_donation_fee_bounded(fn()=>sharity_donation_fee_execute($p['operation_id'],$p['proposal_digest'],$adapter,$now,static fn():int=>$GLOBALS['feeExecClock']??$GLOBALS['now']),$http->doc['request']['fee_id'],true,$http);
}
function feeRecover(array $p,FeeExecutionHttp $http):array {
    global $adapter,$now;return sharity_donation_fee_bounded(fn()=>sharity_donation_fee_recover($p['operation_id'],$p['proposal_digest'],$adapter,$now,static fn():int=>$GLOBALS['feeExecClock']??$GLOBALS['now']),$http->doc['request']['fee_id'],false,$http);
}
class FeeAttemptStatement extends mysqli_stmt {
    public function execute(?array $params=null):bool{$r=parent::execute($params);throw new RuntimeException('synthetic UPDATE ACK loss');}
}
class FeeExecutionMysqli extends FeeMysqli {
    public $afterCommit=null;
    public function commit(int $flags=0,?string $name=null):bool{$r=parent::commit($flags,$name);if($this->afterCommit!==null){($this->afterCommit)();}return$r;}
    public function prepare(string $sql):mysqli_stmt|false {
        if($this->fault==='update-after'&&str_starts_with($sql,'UPDATE `'.sharity_donation_fee_table().'`')){return new FeeAttemptStatement($this,$sql);}
        if($this->fault==='receipt-insert'&&str_starts_with($sql,'INSERT INTO `'.sharity_donation_fee_receipt_table().'`')){throw new RuntimeException('synthetic receipt failure');}
        return parent::prepare($sql);
    }
}
function feeExecConnection(string $fault=''):FeeExecutionMysqli{
    global $wpdb,$port;$wpdb->db->close();$db=new FeeExecutionMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);
    $db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;
}
try{
    check(sharity_donation_backing_schema_apply(),'execution backing restored');
    [$o,$p,$d,$h]=feeExecSetup('BASE');$before=feeExecPostCount();
    fails(fn()=>feeExec($p,$h),'DONATION_FEE_DISPATCH_DISABLED');check($h->requests===[],'default off no GET or POST');
    $options['sharity_donation_fee_execution_enabled']='1';$r=feeExec($p,$h);
    check($r['state']==='provider_recorded'&&feeExecPostCount()===$before+1,'one acknowledged durable attempt sends one POST');
    foreach(['activation_allowed','settlement_reconciled','allocation_eligible','retained_cash_verified','ngo_bank_receipt','backing_released']as$k){check($r[$k]===false,'no financial acceptance '.$k);}
    [$row,$stored]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);
    check($row['state']==='attempted'&&$stored===$d&&$row['digest']===$p['proposal_digest'],'original request and digest immutable');
    check(!str_contains(json_encode($r),'NEVER_STORE'),'no raw provider metadata');
    check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$before+1,'same process restart cannot POST twice');
    $options['sharity_donation_fee_execution_enabled']='0';$options['sharity_donations_enabled']='0';
    check(feeRecover($p,$h)===$r,'historical recovery after both switches disabled');
    [$db,$q]=sharity_donation_backing_db();$claim=sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',[$d['claim_key']]);
    check($claim['state']==='attempted'&&json_decode($claim['document'],true)['amount_minor']==='400','acknowledgement retains entire claim');
    check(sharity_donation_fee_schema_apply(),'schema replay preserves receipt and immutable operation');
    $options['sharity_donation_fee_execution_enabled']='1';
    foreach(['commit-before','commit-after','corrupt-readback','update-after']as$fault){
        [$o,$p,$d,$h]=feeExecSetup(str_replace('-','',$fault));$before=feeExecPostCount();$conn=feeExecConnection($fault);
        try{feeExec($p,$h);throw new LogicException('fault expected');}catch(RuntimeException $e){check(in_array($e->getMessage(),['DONATION_FEE_ATTEMPT_UNCERTAIN','DONATION_FEE_UNAVAILABLE'],true),'attempt fault sanitized '.$fault);}
        feeExecConnection();check(feeExecPostCount()===$before,'uncertain attempt never sends POST '.$fault);
        [$row]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);
        check($row['state']===(in_array($fault,['commit-after','corrupt-readback'],true)?'attempted':'prepared'),'attempt atomic outcome '.$fault);
        if($row['state']==='attempted'){check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$before,'attempt cannot resume money POST');}
    }
    [$o,$p,$d,$h]=feeExecSetup('RACE');$before=feeExecPostCount();
    $rr=backingRace(fn()=>feeExec($p,$h));
    check(count(array_filter($rr,fn($v)=>($v['value']['state']??'')==='provider_recorded'))===1&&feeExecPostCount()===$before+1,'two real connections only one POST');
    feeExecConnection();
    [$o,$p,$d,$h]=feeExecSetup('LOST');$before=feeExecPostCount();$h->drop=true;
    check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$before+1,'lost provider response retained');
    $h->drop=false;$options['sharity_donation_fee_execution_enabled']='0';
    check(feeRecover($p,$h)['state']==='provider_recorded'&&feeExecPostCount()===$before+1,'lost response GET-only recovery');
    // Empty, ambiguous, malformed, foreign scope and incomplete pages never reconcile successfully.
    foreach(['empty','multiple','wrongamount','wrongcurrency','wrongfee','wrongowner','endless']as$case){
        $copy=clone $h;$copy->refunds=[$copy->receipt()];
        if($case==='empty'){$copy->refunds=[];}
        if($case==='multiple'){$copy->refunds[]=$copy->receipt();$copy->refunds[1]['id']='fr_SECOND';}
        if($case==='wrongamount'){$copy->refunds[0]['amount']++;}
        if($case==='wrongcurrency'){$copy->refunds[0]['currency']='eur';}
        if($case==='wrongfee'){$copy->refunds[0]['fee']='fee_OTHER';}
        if($case==='wrongowner'){$copy->badScope=true;}
        if($case==='endless'){$copy->endless=true;}
        if(in_array($case,['empty','multiple'],true)){check(feeRecover($p,$copy)['state']==='reconciliation_required','no unique receipt '.$case);}
        else{fails(fn()=>feeRecover($p,$copy),'DONATION_FEE_PROVIDER');}
    }
    $h->refunds=[];for($i=0;$i<101;$i++){$x=$h->receipt();$x['id']='fr_OTHER'.$i;$x['metadata']=[];$h->refunds[]=$x;}$h->refunds[]=$h->receipt();
    check(feeRecover($p,$h)['state']==='provider_recorded','complete pagination finds exact operation on second page');
    $options['sharity_donation_fee_execution_enabled']='1';
    [$o,$p,$d,$h]=feeExecSetup('STALE');$before=feeExecPostCount();
    fails(fn()=>sharity_donation_fee_execute($p['operation_id'],str_repeat('0',64),$adapter,$now),'DONATION_FEE_STALE');
    fails(fn()=>sharity_donation_fee_bounded(fn()=>sharity_donation_fee_execute($p['operation_id'],$p['proposal_digest'],$adapter,$now+4000,static fn():int=>$GLOBALS['now']+4000),$d['request']['fee_id'],true,$h),'DONATION_BACKING_EXPIRED');
    check(feeExecPostCount()===$before,'stale digest/expired cash zero POST');
    [$o,$p,$d,$h,$data]=feeExecSetup('TARGET');$before=feeExecPostCount();
    $data['original']['initial_charge_balance']['status']='pending';reconcileFixture($o,$data);
    fails(fn()=>feeExec($p,$h),'DONATION_FEE_STALE');check(feeExecPostCount()===$before,'new observation invalidates old target before money');
    // Fresh clock after GETs, lock waits and durable COMMIT; all expiry paths send zero POST.
    foreach(['read','lock','aftercommit']as$phase){
        [$o,$p,$d,$h]=feeExecSetup('CLOCK'.$phase);$before=feeExecPostCount();$GLOBALS['feeExecClock']=$now;
        if($phase==='read'){$h->afterRead=static function()use($now):void{$GLOBALS['feeExecClock']=$now+4000;};}
        $conn=feeExecConnection();
        if($phase==='lock'){$conn->beforeLock=static function()use($now):void{$GLOBALS['feeExecClock']=$now+4000;};}
        if($phase==='aftercommit'){$conn->afterCommit=static function()use($now):void{$GLOBALS['feeExecClock']=$now+4000;};}
        if($phase==='aftercommit'){check(feeExec($p,$h)['state']==='reconciliation_required','expired after commit retained attempted');}
        else{fails(fn()=>feeExec($p,$h),'DONATION_BACKING_EXPIRED');}
        check(feeExecPostCount()===$before,'fresh expiry zero POST '.$phase);unset($GLOBALS['feeExecClock']);feeExecConnection();
    }
    // Receipt INSERT failure leaves attempted; subsequent GET can finish without a second POST.
    [$o,$p,$d,$h]=feeExecSetup('RECEIPT');$before=feeExecPostCount();$conn=feeExecConnection();
    $h->afterPost=static function()use($conn):void{$conn->fault='receipt-insert';};
    check(feeExec($p,$h)['state']==='reconciliation_required','receipt persistence failure not success');feeExecConnection();$h->afterPost=null;
    check(feeRecover($p,$h)['state']==='provider_recorded'&&feeExecPostCount()===$before+1,'receipt persistence recovered GET-only');
    // A lost receipt commit ACK can converge by full readback, but cannot authorize a POST.
    [$o,$p,$d,$h]=feeExecSetup('RECEIPTACK');$conn=feeExecConnection();$h->afterPost=static function()use($conn):void{$conn->fault='commit-after';};
    check(feeExec($p,$h)['state']==='provider_recorded','immutable receipt commit loss uses exact full readback');feeExecConnection();
    foreach(['EUR','USD','GBP']as$cc){
        [$o,$p,$d,$h]=feeExecSetup('CURRENCY'.$cc,$cc);$r=feeExec($p,$h);
        check($r['state']==='provider_recorded'&&$r['receipt']['currency']===$cc&&$r['receipt']['amount_minor']==='400','native currency preserved '.$cc);
    }
    $rt=sharity_donation_fee_receipt_table();$original=$wpdb->db->query($wpdb->prepare("SELECT * FROM `{$rt}` WHERE operation_id=%s",$p['operation_id']))->fetch_assoc();
    $wpdb->db->query($wpdb->prepare("UPDATE `{$rt}` SET digest=%s WHERE operation_id=%s",str_repeat('0',64),$p['operation_id']));
    fails(fn()=>feeRecover($p,$h),'DONATION_FEE_CONFLICT');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$rt}` SET digest=%s WHERE operation_id=%s",$original['digest'],$p['operation_id']));
    $wpdb->db->query("ALTER TABLE `{$rt}` ENGINE=MyISAM");
    check(!sharity_donation_fee_schema_apply(),'nontransactional receipt table cannot authorize dispatch');
    $wpdb->db->query("ALTER TABLE `{$rt}` ENGINE=InnoDB");check(sharity_donation_fee_schema_apply(),'explicit restored receipt shape');
    check(feeRecover($p,$h)===$r,'schema replay preserves original receipt content');
    foreach([[[],[]],[[],['operation'=>$p['operation_id'],'digest'=>true]],[['arg'],['operation'=>$p['operation_id'],'digest'=>$p['proposal_digest']]]]as[$a,$b]){
        fails(fn()=>sharity_donation_cli_fee_execute($a,$b),'DONATION_FEE_INPUT');fails(fn()=>sharity_donation_cli_fee_recover($a,$b),'DONATION_FEE_INPUT');
    }
    require __DIR__.'/sharity-donation-fee-reconcile-mysql.test.php';
    require __DIR__.'/sharity-donation-fee-accounting-mysql.test.php';
    echo 'Donation fee execution MariaDB: '.($checks-$feeExecStart)." checks; durable attempt + GET recovery; synthetic Stripe only\n";
}finally{feeExecConnection();backingClear();$options=$feeExecOptions;Stripe\ApiRequestor::setHttpClient($feeExecPrior);unlink($feeExecLog);}
