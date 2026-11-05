<?php
/** D2af: real MariaDB transitions and synthetic SDK; no live money or acceptance. */
declare(strict_types=1);
(static function():void{
    global $checks,$wpdb,$now,$options,$financeHttp,$feeExecLog,$port;
    $start=$checks;$priorLog=$feeExecLog;$priorOptions=$options;$priorHttp=Stripe\ApiRequestor::httpClient();
    $feeExecLog=tempnam(sys_get_temp_dir(),'sharity-handoff-');chmod($feeExecLog,0600);
    function handoffRow(array $o):array {
        [$db,$q]=sharity_donation_backing_db();return sharity_donation_backing_row($q,'claims','WHERE claim_key = ?',
            [hash('sha256',sharity_donation_finance_json([$o['order_id'],'platform']))]);
    }
    function handoffFixture(string $tag,string $currency='HUF',string $hold='10000',string $cost='0',?array $own=null):array {
        backingClear();[$o,$data]=feeFixture('HOFF'.$tag,$currency);[$req,$proof,$frame]=feeBacking($o,$own,$cost);
        $platform=backingRequest($o,$proof,$frame,$hold,'platform');$saved=backingReserve($platform);
        $req['platform_liability_digest']=$saved['proposal_digest'];return[$o,$req,$proof,$platform,$data];
    }
    class HandoffMysqli extends FeeMysqli {
        public function prepare(string $sql):mysqli_stmt|false {
            $ct=sharity_donation_backing_table('claims');
            if($this->fault==='platform-update'&&str_starts_with($sql,"UPDATE `{$ct}` SET digest=?")){return new FeeAttemptStatement($this,$sql);}
            if($this->fault==='claim-insert'&&str_starts_with($sql,"INSERT INTO `{$ct}`")){return new FeeAttemptStatement($this,$sql);}
            if($this->fault==='platform-readback'&&$this->committed&&str_contains($sql," AS document FROM `{$ct}`")){$sql=str_replace('`state`',"'unknown' AS `state`",$sql);}
            return parent::prepare($sql);
        }
    }
    function handoffConnection(string $fault=''):HandoffMysqli {
        global $wpdb,$port;$wpdb->db->close();$db=new HandoffMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;
    }
    try{
        foreach(['HUF','EUR','USD','GBP']as$cc){
            [$o,$req,$proof,$platform,$data]=handoffFixture('BASE'.$cc,$cc);$before=handoffRow($o);$count=feeCount();$posts=calls();$gets=$financeHttp->gets;
            $dry=feeDry($o,$req);check(!$dry['prepared']&&handoffRow($o)===$before&&feeCount()===$count,'handoff proposal is read-only '.$cc);
            $p=feeApply($o,$req,$dry['proposal_digest']);$after=handoffRow($o);$d=json_decode($after['document'],true);
            check($p['prepared']&&$d['amount_minor']==='9600'&&$after['created_at_utc']===$before['created_at_utc'],'partial principal retains 9600 '.$cc);
            $cap=platformHoldCapacity($proof);check($cap['held_minor']==='10000'&&$cap['available_minor']==='0'&&$cap['deficit_minor']==='0','400+9600 exact shared reservation '.$cc);
            [$op,$doc]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);
            check(sharity_donation_finance_json($doc['platform_handoff']['before'])===sharity_donation_finance_json($before)&&sharity_donation_finance_json($doc['platform_handoff']['after'])===sharity_donation_finance_json($after)&&$doc['platform_handoff']['moved_minor']==='400','immutable full before/after '.$cc);
            check(feeApply($o,$req,$dry['proposal_digest'],1)===$p&&feeDry($o,$req)===$p&&handoffRow($o)===$after,'replay never repeats transfer '.$cc);
            fails(fn()=>backingReserve($platform),'DONATION_BACKING_CLAIM_CONFLICT');
            check(calls()===$posts&&$financeHttp->gets===$gets,'no provider request during reclassification '.$cc);
            foreach(['activation_allowed','settlement_reconciled','allocation_eligible','retained_cash_verified']as$f){check(!$p[$f],'not acceptance '.$f);}
            // The existing dispatch reconstructs the original handoff input exactly once.
            [$doc,$h,$data]=feeAccountObserve($o,$p,$data,'0');$key=$req['allocations'][0]['source_key'];$input=feeAccountInput($doc,[$key=>400]);
            $ad=feeAccount($p,$input);$accounted=feeAccount($p,$input,$ad['proposal_digest']);$cap=feeMatchCapacity($doc);
            check($input['cash_proof']['frame']['retained_minor']==='9600'&&$cap['held_minor']==='9600'&&$cap['available_minor']==='0','actual400 debit leaves9600 liability and no phantom cash '.$cc);
            check(feeMatch($p)===$accounted&&feeRecover($p,$h)['state']==='provider_recorded'&&handoffRow($o)===$after,'accounted historical recovery preserves platform '.$cc);
            if($cc==='HUF'){
                // A second correction moves more of the remaining hold; prior operation remains readable.
                $data['refunds'][]=['id'=>'re_HOFFNEXT','balance'=>reconcileBalance('txn_HOFFNEXT','re_HOFFNEXT','refund','-1000'),'failure_balance'=>null];
                reconcileFixture($o,$data);sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);targetSave($o);
                $inv=sharity_donation_finance_reconcile($o['order_id'],$now);$snap=sharity_donation_order_decode($o)['snapshot'];$c=sharity_donation_fee_candidate($o,$snap,$inv,sharity_donation_target_compute($inv,$snap));
                $next=$req;$next['platform_liability_digest']=$after['digest'];$next['frame_revision']=$accounted['frame_revision'];$next['frame_digest']=hash('sha256',sharity_donation_finance_json($input['cash_proof']));$next['allocations'][0]['amount_minor']=$c['refund_minor'];
                $np=feeApply($o,$next);$nr=handoffRow($o);check($np['operation_id']!==$p['operation_id']&&json_decode($nr['document'],true)['amount_minor']===(string)(9600-(int)$c['refund_minor']),'successor consumes only new principal');
                check(feeMatch($p)===$accounted&&feeRecover($p,$h)['state']==='provider_recorded'&&handoffRow($o)===$nr,'old accounting/recovery cannot rewrite successor');
                [$nd,$nh,$data]=feeAccountObserve($o,$np,$data,'0');$ni=feeAccountInput($nd,[$key=>(int)$c['refund_minor']],1);$dry=feeAccount($np,$ni,'',1);feeAccount($np,$ni,$dry['proposal_digest'],1);
                check(feeMatchCapacity($nd)['available_minor']==='0'&&feeMatchCapacity($nd)['held_minor']===json_decode($nr['document'],true)['amount_minor'],'two complete generations conserve remaining cash');
            }
        }
        [$o,$req,$proof,$platform]=handoffFixture('FULL','HUF','400');$p=feeApply($o,$req);$row=handoffRow($o);
        check($row['state']==='exhausted'&&json_decode($row['document'],true)['amount_minor']==='0'&&json_decode($row['document'],true)['allocations']===[],'full transfer retains explicit zero row');
        check(platformHoldCapacity($proof)['held_minor']==='400'&&feeApply($o,$req)===$p,'zero source is readable without double reservation');
        fails(fn()=>backingReserve($platform),'DONATION_BACKING_CLAIM_CONFLICT');$zero=$platform;$zero['amount_minor']='0';$zero['allocations']=[];
        fails(fn()=>backingReserve($zero),'DONATION_BACKING_PROOF_MALFORMED');
        [$o,$req]=handoffFixture('COSTFAIL','HUF','10000','50');$before=handoffRow($o);$count=feeCount();fails(fn()=>feeDry($o,$req),'DONATION_BACKING_INSUFFICIENT');
        check(handoffRow($o)===$before&&feeCount()===$count,'cost needs extra cash and leaves no partial mutation');
        [$o,$req,$proof,$platform,$data]=handoffFixture('COSTOK','HUF','10000','50',backingOwn('HOFFCOST','10050'));$p=feeApply($o,$req);
        check(platformHoldCapacity($proof)['held_minor']==='10050'&&json_decode(handoffRow($o)['document'],true)['amount_minor']==='9600','fresh50 cost is not taken from liability');
        [$doc,$h,$data]=feeAccountObserve($o,$p,$data,'10');$in=feeAccountInput($doc,[$req['allocations'][0]['source_key']=>410]);$dry=feeAccount($p,$in);feeAccount($p,$in,$dry['proposal_digest']);
        check(feeMatchCapacity($doc)['held_minor']==='9640'&&feeMatchCapacity($doc)['available_minor']==='0','9600 liability plus40 fee residual consumes9640 cash');
        // Partial overlap: source A liability300, A/B fee200/200; only A200 may move.
        backingClear();[$o]=feeFixture('HOFFMULTI');$snap=sharity_donation_order_decode($o)['snapshot'];$snap['account_id']='acct_PLATFORM';
        $proof=backingProof($snap,[backingOwn('HOFFA','300'),backingOwn('HOFFB','200')]);$fr=backingApply($proof);
        $ak=sharity_donation_backing_source_key($proof['sources'][0],$proof['frame']);$bk=sharity_donation_backing_source_key($proof['sources'][1],$proof['frame']);
        $platform=backingRequest($o,$proof,$fr,'300','platform',$ak);$hold=backingReserve($platform);
        $req=['version'=>1,'frame_key'=>$fr['frame_key'],'frame_revision'=>1,'frame_digest'=>$fr['proposal_digest'],'fee_cost_bound_minor'=>'0','fee_cost_proof'=>backingRef('partial'),
            'allocations'=>[['source_key'=>$ak,'amount_minor'=>'200'],['source_key'=>$bk,'amount_minor'=>'200']],'platform_liability_digest'=>$hold['proposal_digest']];
        $bad=$req;$bad['allocations']=[['source_key'=>$bk,'amount_minor'=>'400']];fails(fn()=>feeDry($o,$bad),'DONATION_FEE_SOURCE');
        $p=feeApply($o,$req);$cap=platformHoldCapacity($proof);check($cap['held_minor']==='500'&&$cap['available_minor']==='0'&&json_decode(handoffRow($o)['document'],true)['amount_minor']==='100','only source overlap moves; B needs fresh capacity');
        foreach(['stale','wrong_frame','wrong_owner','wrong_live']as$case){
            [$o,$req,$proof]=handoffFixture('BAD'.str_replace('_','',$case));$before=handoffRow($o);$count=feeCount();$error='DONATION_FEE_SOURCE';
            if($case==='stale'){$req['platform_liability_digest']=str_repeat('0',64);$error='DONATION_FEE_STALE';}
            if($case==='wrong_owner'){[$other]=feeFixture('HOFFOTHER');$o=$other;}
            if($case==='wrong_frame'||$case==='wrong_live'){
                $snap=sharity_donation_order_decode($o)['snapshot'];$snap['account_id']=$case==='wrong_frame'?'acct_WRONG':'acct_PLATFORM';$snap['livemode']=$case==='wrong_live';
                $pp=backingProof($snap,[backingOwn('HOFFBAD'.$case,'20000')]);$fr=backingApply($pp);
                $req['frame_key']=$fr['frame_key'];$req['frame_digest']=$fr['proposal_digest'];$req['frame_revision']=$fr['revision'];
            }
            fails(fn()=>feeDry($o,$req),$error);check(feeCount()===$count,'invalid binding no operation '.$case);
        }
        foreach(['platform-update','claim-insert','fee-insert','commit-before','commit-after','platform-readback','fee-corrupt']as$fault){
            [$o,$req,$proof]=handoffFixture('FAULT'.str_replace('-','',$fault));$before=handoffRow($o);$count=feeCount();$dry=feeDry($o,$req);handoffConnection($fault);
            if($fault==='commit-after'){$r=feeApply($o,$req,$dry['proposal_digest']);check($r['prepared'],'lost commit ACK full readback converges');}
            else{try{feeApply($o,$req,$dry['proposal_digest']);throw new LogicException('fault did not fail '.$fault);}catch(RuntimeException $e){check(str_starts_with($e->getMessage(),'DONATION_'),'sanitized write/read fault '.$fault);}}
            handoffConnection();$committed=in_array($fault,['commit-after','platform-readback','fee-corrupt'],true);
            check(feeCount()===$count+($committed?1:0)&&backingRows('claims')===($committed?2:1),'whole transfer atomic '.$fault);
            check($committed?handoffRow($o)!==$before:handoffRow($o)===$before,'platform never partially changed '.$fault);
            check(platformHoldCapacity($proof)['held_minor']==='10000','fault cannot release cash '.$fault);
            if($committed){check(feeApply($o,$req,$dry['proposal_digest'])['prepared'],'readback without second transfer '.$fault);}
        }
        [$o,$req,$proof]=handoffFixture('RACE');$dry=feeDry($o,$req);$count=feeCount();
        $rr=backingRace(static fn()=>sharity_donation_fee_prepare($o['order_id'],$req,$GLOBALS['now'],$dry['proposal_digest']));handoffConnection();
        check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value']&&feeCount()===$count+1&&platformHoldCapacity($proof)['held_minor']==='10000','two actual writers converge once '.json_encode($rr));
        // Additional own collateral300: transfer cost200 versus another order reserve200, one writer wins.
        [$o,$req,$proof]=handoffFixture('RACECAP','HUF','1000','200',backingOwn('HOFFSHARED','1300'));[$other]=feeFixture('HOFFRACEOTHER');
        $fr=['frame_key'=>$req['frame_key'],'revision'=>1,'proposal_digest'=>$req['frame_digest']];$otherReq=backingRequest($other,$proof,$fr,'200','platform');
        $a=feeDry($o,$req)['proposal_digest'];$b=sharity_donation_backing_reserve($other['order_id'],$otherReq,'',$now)['proposal_digest'];
        $rr=backingRace(static fn($slot)=>$slot===0?sharity_donation_fee_prepare($o['order_id'],$req,$GLOBALS['now'],$a):sharity_donation_backing_reserve($other['order_id'],$otherReq,$b,$GLOBALS['now']));handoffConnection();
        check(count(array_filter($rr,static fn($r)=>isset($r['value'])))===1&&count(array_filter($rr,static fn($r)=>($r['error']??'')==='DONATION_BACKING_INSUFFICIENT'))===1,'shared free cash has one winner '.json_encode($rr));
        check(platformHoldCapacity($proof)['held_minor']==='1200','loser cannot free or consume others cash');
        [$o,$req,$proof]=handoffFixture('UNKNOWN');$p=feeApply($o,$req);[$row,$doc,$account]=sharity_donation_fee_context($p['operation_id'],$p['proposal_digest']);
        $h=new FeeAccountingHttp($feeExecLog);$h->doc=$doc;$h->account=$account;$h->drop=true;$options['sharity_donation_fee_execution_enabled']='1';$before=feeExecPostCount();
        check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$before+1&&platformHoldCapacity($proof)['held_minor']==='10000','unknown provider result retains both holds');
        check(feeExec($p,$h)['state']==='reconciliation_required'&&feeExecPostCount()===$before+1,'unknown never resends');
        $h->drop=false;$options['sharity_donation_fee_execution_enabled']='0';check(feeRecover($p,$h)['state']==='provider_recorded'&&platformHoldCapacity($proof)['held_minor']==='10000','disabled new dispatch preserves GET drain and holds');
        echo 'Donation fee handoff MariaDB: '.($checks-$start)." checks; atomic principal transfer, residual, races and SDK recovery\n";
    }finally{handoffConnection();backingClear();$options=$priorOptions;Stripe\ApiRequestor::setHttpClient($priorHttp);unlink($feeExecLog);$feeExecLog=$priorLog;}
})();
