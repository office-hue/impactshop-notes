<?php
/** D2ae: actual isolated MariaDB, synthetic evidence; no live provider or grant. */
declare(strict_types=1);
(function():void {
    global $checks,$wpdb,$now,$financeHttp,$options,$port;
    $start=$checks;
    function platformHoldCapacity(array $proof):array {
        return sharity_donation_backing_transaction(false,$GLOBALS['now'],static function($q)use($proof):array{
            sharity_donation_backing_latest_locked($q,sharity_donation_backing_frame_key($proof['frame']));
            return [sharity_donation_backing_capacity($q,$proof),static function($q):void{}];
        });
    }
    foreach(['HUF','EUR','USD','GBP']as$cc){
        backingClear();[$o]=feeFixture('PLBASE'.$cc,$cc);[$fee,$p,$r]=feeBacking($o);
        $request=backingRequest($o,$p,$r,'9000','platform');$posts=calls();$gets=$financeHttp->gets;$opts=$options;
        $before=sharity_donation_webhook_order($o['order_id']);
        $dry=sharity_donation_backing_reserve($o['order_id'],$request,'',$now);
        check(!$dry['reserved']&&backingRows('claims')===0,'platform dry-run writes nothing '.$cc);
        $saved=backingReserve($request);check($saved['reserved']&&$saved['kind']==='platform'&&$saved['currency']===$cc,'native platform receipt accepted '.$cc);
        check(backingReserve($request,5)===$saved&&backingRows('claims')===1,'platform exact repeat retains timestamp '.$cc);
        foreach(['provider_verified','settlement_reconciled','activation_allowed']as$f){check(!$saved[$f],'hold does not prove full risk '.$f);}
        // Separate kinds use a single shared capacity, including fee preparation.
        $operation=feeApply($o,$fee);check($operation['prepared']&&backingRows('claims')===2,'fee correction and liability both held '.$cc);
        $cap=platformHoldCapacity($p);check($cap['held_minor']==='9400'&&$cap['available_minor']==='600','same cash counted once per obligation '.$cc);
        $snap=sharity_donation_order_decode($o)['snapshot'];$np=backingProof($snap,[backingOwn('PLNGO'.$cc)]);$np['frame']['currency']=$cc;$nr=backingApply($np);
        backingReserve(backingRequest($o,$np,$nr,'700','liability'));
        check(backingRows('claims')===3&&platformHoldCapacity($p)===$cap,'NGO and platform liabilities coexist without cross funding '.$cc);
        check(calls()===$posts&&$financeHttp->gets===$gets&&$options===$opts&&sharity_donation_webhook_order($o['order_id'])===$before,'no provider/order/option side effect '.$cc);
    }
    // An accounted fee's residual remains reserved when a new platform liability is added.
    [$o,$operation,$doc,$http,$data,$cash]=feeAccountFixture('PLRESIDUAL');$dry=feeAccount($operation,$cash);
    $accounted=feeAccount($operation,$cash,$dry['proposal_digest']);$p=$cash['cash_proof'];
    $r=['frame_key'=>$accounted['frame_key'],'revision'=>$accounted['frame_revision'],'proposal_digest'=>hash('sha256',sharity_donation_finance_json($p))];
    fails(fn()=>backingReserve(backingRequest($o,$p,$r,'1551','platform')),'DONATION_BACKING_INSUFFICIENT');
    backingReserve(backingRequest($o,$p,$r,'1550','platform'));$cap=platformHoldCapacity($p);
    check($cap['held_minor']==='1590'&&$cap['available_minor']==='0','accounted40 residual and new1550 liability each consume1590 retained cash once');
    // Private existing CLI accepts the new kind, still requiring exact apply digest.
    backingClear();[$o]=feeFixture('PLCLI');[$fee,$p,$r]=feeBacking($o,backingOwn('PLCLICASH'));
    $request=backingRequest($o,$p,$r,'700','platform');$path=backingFile($request);
    try{
        WP_CLI::$lines=[];sharity_donation_cli_backing_reserve([],['input'=>$path,'order'=>$o['order_id']]);
        $dry=json_decode(end(WP_CLI::$lines),true);check(!$dry['reserved']&&backingRows('claims')===0,'CLI dry-run no hold');
        sharity_donation_cli_backing_reserve([],['input'=>$path,'order'=>$o['order_id'],'apply'=>$dry['proposal_digest']]);
        check(backingRows('claims')===1,'CLI exact approved digest creates hold');
    }finally{unlink($path);}
    // Current cash deficit is recorded independently; a receipt remains only historical reserved state.
    $old=backingReserve($request);$next=$p;$next['frame']['previous_revision']=1;$next['frame']['previous_digest']=$r['proposal_digest'];
    $next['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-30);$next['frame']['retained_minor']='600';$next['sources'][0]['retained_minor']='600';
    $decline=backingApply($next);check($decline['deficit_minor']==='100'&&backingReserve($request)===$old,'decline cannot erase liability or imply coverage');
    fails(fn()=>feeApply($o,$fee),'DONATION_FEE_STALE');

    foreach(['connected','other','currency','live','unregistered','registry_body','stale','expired','foreign_receipt']as$case){
        backingClear();[$o,$data,$obs]=feeFixture('PLBAD'.str_replace('_','',$case));[$fee,$p,$r]=feeBacking($o);
        $request=backingRequest($o,$p,$r,'900','platform');$expected='DONATION_BACKING_SOURCE_UNPROVEN';
        if(in_array($case,['connected','other','currency','live'],true)){
            $snap=sharity_donation_order_decode($o)['snapshot'];$snap['account_id']=$case==='connected'?$snap['account_id']:'acct_OTHER';
            if($case==='currency'||$case==='live'){$snap['account_id']='acct_PLATFORM';}
            if($case==='live'){$snap['livemode']=true;}
            $p=backingProof($snap,[backingOwn('PLBAD'.$case)]);if($case==='currency'){$p['frame']['currency']='EUR';}
            $r=backingApply($p);$request=backingRequest($o,$p,$r,'900','platform');
        }
        if($case==='unregistered'||$case==='registry_body'){
            $mt=sharity_donation_movement_table();$id='txn_FEEPLBAD'.str_replace('_','',$case);
            $row=$wpdb->db->query($wpdb->prepare("SELECT * FROM `{$mt}` WHERE balance_transaction_id=%s",$id))->fetch_assoc();
            check(is_array($row),'synthetic native movement exists');
            if($case==='unregistered'){$wpdb->db->query($wpdb->prepare("DELETE FROM `{$mt}` WHERE balance_transaction_id=%s",$id));}
            else{$wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json='{}' WHERE balance_transaction_id=%s",$id));}
        }
        if($case==='stale'){$request['target_generation']=str_repeat('0',64);$expected='DONATION_BACKING_STALE';}
        if($case==='expired'){$expected='DONATION_BACKING_EXPIRED';}
        if($case==='foreign_receipt'){
            backingClear();[$other]=feeFixture('PLFOREIGN');[$fee,$p,$r]=feeBacking($other);
            $request=backingRequest($o,$p,$r,'900','platform');
        }
        fails(fn()=>backingReserve($request,$case==='expired'?3601:0),$expected);
        check(backingRows('claims')===0,'rejected platform request left no claim '.$case);
    }
    // Strict resolver never guesses a platform from unscoped or contradictory evidence.
    backingClear();[$o,$data,$obs]=feeFixture('PLAUTH');$snap=sharity_donation_order_decode($o)['snapshot'];
    $inv=sharity_donation_finance_reconcile($o['order_id'],$now);
    foreach(['direct','same','missing','ambiguous','mixed','unscoped','bad_id','wrong_owner','wrong_live']as$case){
        $i=$inv;$s=$snap;
        $idx=array_key_first(array_filter($i['movements'],static fn($m)=>$m['facts']['movement_role']==='application_fee'));
        if($case==='direct'){$s['donation_mode']='direct';}
        if($case==='same'){$i['movements'][$idx]['facts']['account_id']=$s['account_id'];}
        if($case==='missing'){$i['movements']=array_values(array_filter($i['movements'],static fn($m)=>$m['facts']['movement_role']!=='application_fee'));}
        if($case==='ambiguous'||$case==='mixed'){$x=$i['movements'][$idx];$x['facts']['movement_role']='application_fee_refund';$x['facts'][$case==='ambiguous'?'account_id':'currency']=$case==='ambiguous'?'acct_OTHER':'EUR';$i['movements'][]=$x;}
        if($case==='unscoped'){$i['unscoped_platform_movements']=[['unknown'=>true]];}
        if($case==='bad_id'){$i['movements'][$idx]['facts']['account_id']='acct_bad/path';}
        if($case==='wrong_owner'){$i['movements'][$idx]['facts']['order_id']='do_'.str_repeat('0',32);}
        if($case==='wrong_live'){$i['movements'][$idx]['facts']['livemode']=!$s['livemode'];}
        fails(fn()=>sharity_donation_backing_platform($i,$s),'DONATION_BACKING_SOURCE_UNPROVEN');
    }
    // Exact native settlement currency, not a silently assumed payment currency.
    $i=$inv;foreach($i['movements']as&$m){if($m['facts']['movement_role']==='application_fee'){$m['facts']['currency']='EUR';}}unset($m);
    check(sharity_donation_backing_platform($i,$snap)['currency']==='EUR','native currency is independent of payer currency');
    [$direct,$ds,$directSnap]=backingFixture('PLDIRECT');[$fee,$p,$r]=feeBacking($o,backingOwn('PLDIRECTCASH'));
    fails(fn()=>backingReserve(backingRequest($direct,$p,$r,'1','platform')),'DONATION_BACKING_SOURCE_UNPROVEN');
    // New evidence during frame acquisition invalidates the prior inventory before writing.
    $request=backingRequest($o,$p,$r,'700','platform');$db=backingConnection();
    $db->beforeLock=static function()use($o,$data,$port):void{
        $prior=$GLOBALS['wpdb'];$GLOBALS['wpdb']=new OrderDb((int)$port);
        try{$x=$data;$x['original']['application_fee']['initial_balance']['status']='pending';reconcileFixture($o,$x);}
        finally{$GLOBALS['wpdb']->db->close();$GLOBALS['wpdb']=$prior;}
    };
    fails(fn()=>backingReserve($request),'DONATION_BACKING_STALE');backingConnection();
    check(backingRows('claims')===0,'concurrent evidence change cannot create hold');

    // Two valid Amplifier orders compete for the same platform-owned 1000 minor.
    backingClear();[$a]=feeFixture('PLRACEA');[$b]=feeFixture('PLRACEB');[$fee,$p,$r]=feeBacking($a,backingOwn('PLRACECASH'));
    $ra=backingRequest($a,$p,$r,'700','platform');$rb=backingRequest($b,$p,$r,'700','platform');
    $da=sharity_donation_backing_reserve($a['order_id'],$ra,'',$now)['proposal_digest'];$db=sharity_donation_backing_reserve($b['order_id'],$rb,'',$now)['proposal_digest'];
    $rr=backingRace(static fn($slot)=>sharity_donation_backing_reserve($slot===0?$a['order_id']:$b['order_id'],$slot===0?$ra:$rb,$slot===0?$da:$db,$now));
    check(count(array_filter($rr,static fn($r)=>isset($r['value'])))===1&&count(array_filter($rr,static fn($r)=>($r['error']??'')==='DONATION_BACKING_INSUFFICIENT'))===1&&backingRows('claims')===1,'one winner for shared platform capacity '.json_encode($rr));
    // A fee correction and liability on the same order cannot double-spend 1000.
    backingClear();[$fee,$p,$r]=feeBacking($a,backingOwn('PLRACEKINDS'));$ra=backingRequest($a,$p,$r,'700','platform');
    $da=sharity_donation_backing_reserve($a['order_id'],$ra,'',$now)['proposal_digest'];$df=feeDry($a,$fee)['proposal_digest'];
    $rr=backingRace(static fn($slot)=>$slot===0?sharity_donation_backing_reserve($a['order_id'],$ra,$da,$now):sharity_donation_fee_prepare($a['order_id'],$fee,$now,$df));
    check(count(array_filter($rr,static fn($r)=>isset($r['value'])))===1&&backingRows('claims')===1,'liability and fee correction share the same frame lock '.json_encode($rr));
    check(count(array_filter($rr,static fn($r)=>in_array($r['error']??'',['DONATION_BACKING_INSUFFICIENT','DONATION_FEE_INSUFFICIENT'],true)))===1,'capacity loser fails explicitly');
    backingClear();[$fee,$p,$r]=feeBacking($a,backingOwn('PLRACEREPLAY'));$ra=backingRequest($a,$p,$r,'700','platform');
    $da=sharity_donation_backing_reserve($a['order_id'],$ra,'',$now)['proposal_digest'];
    $rr=backingRace(static fn()=>sharity_donation_backing_reserve($a['order_id'],$ra,$da,$now));check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value']&&backingRows('claims')===1,'identical parallel claims converge once');
    foreach(['commit-after','commit-before','corrupt-readback']as$fault){
        backingClear();[$fee,$p,$r]=feeBacking($a,backingOwn('PLACK'.str_replace('-','',$fault)));$ra=backingRequest($a,$p,$r,'700','platform');
        $dry=sharity_donation_backing_reserve($a['order_id'],$ra,'',$now);backingConnection($fault);
        if($fault==='commit-after'){$saved=sharity_donation_backing_reserve($a['order_id'],$ra,$dry['proposal_digest'],$now);backingConnection();check(backingReserve($ra)===$saved&&backingRows('claims')===1,'lost ACK readback exact without second write');}
        else{fails(fn()=>sharity_donation_backing_reserve($a['order_id'],$ra,$dry['proposal_digest'],$now),'DONATION_BACKING_UNAVAILABLE');backingConnection();check(backingRows('claims')===($fault==='commit-before'?0:1),'uncertain result has no fabricated success '.$fault);}
    }
    backingClear();
    echo 'Donation platform liability MariaDB: '.($checks-$start)." checks; native account/registry, shared cash and real races\n";
})();
