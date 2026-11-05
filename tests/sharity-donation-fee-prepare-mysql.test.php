<?php
/** Real isolated MariaDB; no provider writes or live money. */
declare(strict_types=1);
$feeStart=$checks;$feeFixturePosts=0;
function feeCount():int {global $wpdb;return(int)$wpdb->db->query('SELECT COUNT(*) AS n FROM `'.sharity_donation_fee_table().'`')->fetch_assoc()['n'];}
function feeFixture(string $tag,string $currency='HUF',?callable $change=null):array {
    global $snapshot,$ampOrder,$ampData,$now,$options,$feeFixturePosts;
    $prior=$snapshot;$snapshot=sharity_donation_order_decode($ampOrder)['snapshot'];$snapshot['currency']=$currency;
    if($currency!=='HUF'){$snapshot['price_book_version']='amplifier-net80-v1:2026-11-02:'.$currency;}
    $oldBindings=$options['sharity_donation_accounts_v1'];$options['sharity_donation_accounts_v1'][$snapshot['ngo_slug']]['currencies'][$currency]=['min_minor'=>'100','max_minor'=>'1000000'];$posts=calls();
    try{$o=financeOrder('feeprepare'.$tag);}finally{$snapshot=$prior;$options['sharity_donation_accounts_v1']=$oldBindings;$feeFixturePosts+=calls()-$posts;}
    $d=$ampData;$d['original']['order_id']=$o['order_id'];$d['original']['session_id']=$o['provider_session_id'];
    $d['original']['platform_account_id']='acct_PLATFORM';$d['original']['initial_charge_balance']['id']='txn_FCH'.$tag;
    $d['original']['application_fee']['id']='fee_F'.$tag;$d['original']['application_fee']['initial_balance']['id']='txn_FEE'.$tag;
    $d['original']['application_fee']['refund_state']['amount_refunded_minor']='0';$d['fee_refunds']=[];$d['unresolved']=[];
    $walk=static function(array &$x)use(&$walk,$currency):void {foreach($x as $k=>&$v){if(is_array($v)){$walk($v);}elseif($k==='currency'){$v=$currency;}}unset($v);};$walk($d);
    if($change!==null){$change($d);}$saved=reconcileFixture($o,$d);
    sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);targetSave($o);
    return[$o,$d,$saved];
}
function feeBacking(array $o,?array $own=null,string $cost='0'):array {
    global $now;$snap=sharity_donation_order_decode($o)['snapshot'];$inv=sharity_donation_finance_reconcile($o['order_id'],$now);
    $m=array_values(array_filter($inv['movements'],static fn($m)=>$m['facts']['movement_role']==='application_fee'))[0];
    $s=$own??['kind'=>'order_receipt','native_id'=>$m['facts']['balance_transaction_id'],'order_id'=>$o['order_id'],
        'principal_minor'=>$m['facts']['net_minor'],'retained_minor'=>$m['facts']['net_minor'],
        'evidence'=>['ref'=>'fee_movement','sha256'=>$m['financial_digest']],'retention'=>backingRef('fee_retention'),
        'ownership'=>null,'as_of_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-60),'expires_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+3600)];
    $snap['account_id']=$m['facts']['account_id'];$p=backingProof($snap,[$s]);$p['frame']['currency']=$m['facts']['currency'];$r=backingApply($p);
    $req=['version'=>1,'frame_key'=>$r['frame_key'],'frame_revision'=>$r['revision'],'frame_digest'=>$r['proposal_digest'],
        'fee_cost_bound_minor'=>$cost,'fee_cost_proof'=>backingRef('fee_cost_bound'),
        'allocations'=>[['source_key'=>sharity_donation_backing_source_key($s,$p['frame']),'amount_minor'=>(string)(400+(int)$cost)]]];
    return[$req,$p,$r];
}
function feeDry(array $o,array $r):array {return sharity_donation_fee_prepare($o['order_id'],$r,$GLOBALS['now']);}
function feeApply(array $o,array $r,?string $digest=null,int $offset=0):array {return sharity_donation_fee_prepare($o['order_id'],$r,$GLOBALS['now']+$offset,$digest??feeDry($o,$r)['proposal_digest']);}
class FeeMysqli extends BackingMysqli {
    public function prepare(string $sql):mysqli_stmt|false {
        if($this->fault==='fee-insert'&&str_starts_with($sql,'INSERT INTO `'.sharity_donation_fee_table().'`')){throw new RuntimeException('synthetic second insert failure');}
        if($this->fault==='fee-corrupt'&&$this->committed&&str_contains($sql,' AS document FROM `'.sharity_donation_fee_table().'`')){$sql=str_replace('digest,state',"REPEAT('0',64) AS digest,state",$sql);}
        return parent::prepare($sql);
    }
}
function feeConnection(string $fault=''):FeeMysqli {global $wpdb,$port;$wpdb->db->close();$db=new FeeMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;}
try {
    check(sharity_donation_backing_schema_apply(),'fee test recreates its cleaned backing tables');
    foreach([[[],[]],[[],['apply'=>'true']],[[],['apply'=>false]],[[],['apply'=>true,'extra'=>true]],[['arg'],['apply'=>true]]]as[$a,$b]){
        fails(fn()=>sharity_donation_cli_fee_schema($a,$b),'DONATION_FEE_SCHEMA_UNAVAILABLE');
    }
    check(!isset($options[SHARITY_DONATION_FEE_SCHEMA_OPTION]),'invalid CLI schema requests did not migrate');
    sharity_donation_cli_fee_schema([],['apply'=>true]);check(feeCount()===0,'explicit schema empty');
    $posts=calls();$gets=$financeHttp->gets;$opts=$options;
    foreach(['HUF','EUR','USD','GBP']as$cc){
        backingClear();[$o]=feeFixture('BASE'.$cc,$cc);[$req]=feeBacking($o);$before=feeCount();$d=feeDry($o,$req);
        check($d['refund_minor']==='400'&&$d['reserved_minor']==='400'&&$d['currency']===$cc&&!$d['prepared']&&feeCount()===$before&&backingRows('claims')===0,'native net80 delta, exact dry-run '.$cc);
        fails(fn()=>feeApply($o,$req,str_repeat('0',64)),'DONATION_FEE_STALE');
        $r=feeApply($o,$req,$d['proposal_digest']);check($r['prepared']&&feeCount()===$before+1&&backingRows('claims')===1,'atomic prepared pair '.$cc);
        check(feeApply($o,$req,null,15)===$r&&feeDry($o,$req)===$r,'replay exact timestamp and stable key '.$cc);
        foreach(['activation_allowed','settlement_reconciled','allocation_eligible','retained_cash_verified']as$f){check($r[$f]===false,'not acceptance '.$f);}
        $stored=$wpdb->db->query($wpdb->prepare('SELECT document FROM `'.sharity_donation_fee_table().'` WHERE operation_id=%s',$r['operation_id']))->fetch_assoc();
        $doc=json_decode($stored['document'],true);check($doc['request']['amount_minor']==='400'&&$doc['claim']['amount_minor']==='400'&&$doc['candidate']['cumulative_refund_minor']==='400','persisted immutable request and matching reservation');
        check(!str_contains($stored['document'],'subject_ref')&&!str_contains($stored['document'],'email'),'no donor identity in operation');
        check(sharity_donation_fee_schema_apply()&&feeCount()===$before+1,'schema replay preserves operation');
    }
    check(calls()===$posts+$feeFixturePosts&&$financeHttp->gets===$gets&&$options===$opts,'no SDK GET/POST or readiness change during fee prepare');
    // A second caller cannot use generic reserve to evade the operation row.
    $generic=$doc['claim'];fails(fn()=>backingReserve($generic),'DONATION_BACKING_PROOF_MALFORMED');
    // Two independent connections compete for one retained source: whole loser rolls back.
    backingClear();[$a]=feeFixture('RACEA');[$b]=feeFixture('RACEB');[$req]=feeBacking($a,backingOwn('fee_shared','600'));
    $da=feeDry($a,$req);$db=feeDry($b,$req);$before=feeCount();
    $raceResult=backingRace(fn($slot)=>feeApply($slot===0?$a:$b,$req,$slot===0?$da['proposal_digest']:$db['proposal_digest']));
    check(count(array_filter($raceResult,static fn($x)=>isset($x['value'])))===1&&count(array_filter($raceResult,static fn($x)=>($x['error']??'')==='DONATION_BACKING_INSUFFICIENT'))===1,'exactly one funded winner');
    check(feeCount()===$before+1&&backingRows('claims')===1,'no partial losing operation');
    // Same operation races are idempotent; same original cash cannot cover another NGO order.
    backingClear();[$a]=feeFixture('SAMERACE');[$req,$proof,$receipt]=feeBacking($a);$dry=feeDry($a,$req);$before=feeCount();
    $rr=backingRace(fn()=>feeApply($a,$req,$dry['proposal_digest']));
    check(isset($rr[0]['value'],$rr[1]['value'])&&$rr[0]['value']===$rr[1]['value']&&feeCount()===$before+1&&backingRows('claims')===1,'same request converges to one pair');
    [$foreign]=feeFixture('FOREIGNSOURCE');fails(fn()=>feeDry($foreign,$req),'DONATION_FEE_SOURCE');
    // An attempted or uncertain reservation still consumes real shared capacity.
    foreach(['attempted','unknown']as$state){
        $wpdb->db->query("UPDATE `".sharity_donation_backing_table('claims')."` SET state='".$state."'");
        fails(fn()=>feeApply($a,$req,$dry['proposal_digest']),'DONATION_FEE_CONFLICT');
    }
    // Known bounded correction cost is held in addition to the refund, never assumed zero.
    backingClear();[$a]=feeFixture('COST');[$req]=feeBacking($a,backingOwn('fee_cost','450'),'100');
    fails(fn()=>feeDry($a,$req),'DONATION_BACKING_INSUFFICIENT');
    $bad=$req;unset($bad['fee_cost_bound_minor']);fails(fn()=>feeDry($a,$bad),'DONATION_FEE_UNAVAILABLE');
    $bad=$req;$bad['fee_cost_bound_minor']=null;fails(fn()=>feeDry($a,$bad),'DONATION_FEE_UNAVAILABLE');
    // Native platform receipt identity, retained amount and account/currency are not freely relabelled.
    backingClear();[$a]=feeFixture('SCOPE');[$req,$proof]=feeBacking($a);
    foreach(['EUR','USD']as$cc){$bad=$proof;$bad['frame']['currency']=$cc;fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_CONFLICT');}
    $bad=$proof;$bad['sources'][0]['order_id']=$b['order_id'];fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_CONFLICT');
    $bad=$proof;$bad['frame']['account_id']='acct_OTHER';fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_UNPROVEN');
    // Actual fee refund and corresponding NGO credit reduce only the remaining adjustment.
    backingClear();[$a,$d]=feeFixture('PARTIAL', 'HUF',static function(&$d):void{
        $d['original']['application_fee']['refund_state']['amount_refunded_minor']='100';
        $d['fee_refunds']=[['id'=>'fr_FPART','fee_id'=>$d['original']['application_fee']['id'],'amount_minor'=>'100','currency'=>'HUF','created'=>1800000010,'connected_credit_verified'=>true,
            'balance'=>reconcileBalance('txn_FPARTDEBIT','fr_FPART','application_fee_refund','-100','0','platform'),
            'connected_credit'=>reconcileBalance('txn_FPARTCREDIT','fr_FPART','application_fee_refund','100')+['account_id'=>$d['original']['account_id']]]];
    });
    [$req]=feeBacking($a);$req['allocations'][0]['amount_minor']='300';$r=feeApply($a,$req);
    check($r['refund_minor']==='300'&&$r['reserved_minor']==='300','only remaining actual net adjustment');
    // Entire refund requires remaining original platform fee back, with no negative NGO/fund allocation.
    backingClear();[$a]=feeFixture('FULL','HUF',static function(&$d):void{$d['refunds']=[['id'=>'re_FFULL','balance'=>reconcileBalance('txn_FFULL','re_FFULL','refund','-50000'),'failure_balance'=>null]];});
    [$req]=feeBacking($a);$req['allocations'][0]['amount_minor']='10000';check(feeApply($a,$req)['refund_minor']==='10000','full refund corrects all retained fee, no imaginary negative fee');
    // Zero adjustment produces neither operation nor reservation.
    backingClear();[$a]=feeFixture('ZERO','HUF',static function(&$d):void{
        $c=&$d['original']['initial_charge_balance'];$c['processing_cost_minor']='0';$c['total_fee_minor']='10000';$c['net_minor']='40000';$c['fee_details']=[$c['fee_details'][1]];
    });[$req]=feeBacking($a);$req['allocations']=[];$before=feeCount();$z=feeApply($a,$req);
    check($z['state']==='no_correction'&&!$z['prepared']&&feeCount()===$before&&backingRows('claims')===0,'zero delta not executable');
    // A new observation between calculation and frame lock invalidates preparation.
    backingClear();[$a,$d]=feeFixture('STALE');[$req]=feeBacking($a);$dry=feeDry($a,$req);$before=feeCount();$conn=feeConnection();
    $conn->beforeLock=static function()use($a,$d):void{$x=$d;$x['original']['initial_charge_balance']['status']='pending';reconcileFixture($a,$x);};
    fails(fn()=>feeApply($a,$req,$dry['proposal_digest']),'DONATION_FEE_STALE');check(feeCount()===$before&&backingRows('claims')===0,'stale preparation rolls back both');feeConnection();
    // Immutable early pending and legacy unscoped evidence do not permanently block a proven later payment.
    backingClear();[$a,$d,$complete]=feeFixture('HISTORY');removeReconcileFixture($complete);
    $legacy=$d;unset($legacy['original']['platform_account_id']);$lr=reconcileFixture($a,$legacy);
    $pending=$d;$pending['original']=array_intersect_key($d['original'],array_flip(['evidence_version','scope','order_id','session_id','account_id','livemode','donation_mode','payment_verified','allocation_eligible','payment']));
    $pending['original']+=['evidence_state'=>'payment_pending','checkout_state'=>'open','provider_payment_status'=>'unpaid'];
    $pending['evidence_state']='payment_pending';$pending['resources_complete']=false;$pending['balances_pending']=false;$pr=reconcileFixture($a,$pending);
    // Pending/unscoped alone cannot prepare even though a registry row from the later fixture exists.
    fails(fn()=>feeDry($a,$req),'DONATION_FEE_UNRESOLVED');
    $complete=reconcileFixture($a,$d);targetSave($a);[$req]=feeBacking($a);$count=financeCount();$r=feeApply($a,$req);
    check($r['refund_minor']==='400'&&financeCount()===$count&&sharity_donation_finance_read($a['order_id'],$pr['digest'])!==null&&sharity_donation_finance_read($a['order_id'],$lr['digest'])!==null,'history retained; only exact independently scoped evidence resolves absence');
    // No synthetic FX: payment, native fee and native split must agree for this preparer.
    backingClear();[$a]=feeFixture('FX','HUF',static function(&$d):void{$d['original']['application_fee']['initial_balance']['currency']='EUR';});
    fails(fn()=>feeDry($a,$req),'DONATION_FEE_UNRESOLVED');
    // New frame revision and expired retention cannot back a prepared request.
    backingClear();[$a]=feeFixture('FRAME');[$req,$proof,$receipt]=feeBacking($a);
    $bad=$req;$bad['frame_revision']++;fails(fn()=>feeDry($a,$bad),'DONATION_FEE_STALE');
    fails(fn()=>sharity_donation_fee_prepare($a['order_id'],$req,$now+3601),'DONATION_BACKING_EXPIRED');
    // Forced second INSERT failure, before-COMMIT failure, lost ACK and corrupted readback.
    foreach(['fee-insert','commit-before','commit-after','fee-corrupt']as$fault){
        backingClear();[$a]=feeFixture(str_replace('-','',$fault));[$req]=feeBacking($a);$dry=feeDry($a,$req);$before=feeCount();feeConnection($fault);
        if($fault==='commit-after'){$r=feeApply($a,$req,$dry['proposal_digest']);check($r['prepared'],'lost ACK full exact readback');}
        else{fails(fn()=>feeApply($a,$req,$dry['proposal_digest']),$fault==='fee-corrupt'?'DONATION_FEE_CONFLICT':'DONATION_FEE_UNAVAILABLE');}
        feeConnection();$committed=in_array($fault,['commit-after','fee-corrupt'],true);
        check(feeCount()===$before+($committed?1:0)&&backingRows('claims')===($committed?1:0),'atomic pair survives or rolls back '.$fault);
        if($committed){check(feeApply($a,$req,$dry['proposal_digest'])['prepared'],'readback recovery without second write '.$fault);}
    }
    // Incompatible engine/index cannot be marked ready and does not rewrite historical rows.
    $table=sharity_donation_fee_table();$before=feeCount();
    $wpdb->db->query("ALTER TABLE `{$table}` DROP INDEX order_generation, ADD KEY order_generation(order_id,economic_generation)");
    check(!sharity_donation_fee_schema_apply()&&get_option(SHARITY_DONATION_FEE_SCHEMA_OPTION)==='0'&&feeCount()===$before,'nonunique index rejects readiness');
    $wpdb->db->query("ALTER TABLE `{$table}` DROP INDEX order_generation, ADD UNIQUE KEY order_generation(order_id,economic_generation)");check(sharity_donation_fee_schema_apply(),'own test schema repaired');
    foreach([[[],[]],[[],['order'=>$a['order_id'],'input'=>'missing','apply'=>true]],[[],['order'=>'do_bad','input'=>'missing']],[['arg'],['order'=>$a['order_id'],'input'=>'missing']]]as[$args,$assoc]){
        fails(fn()=>sharity_donation_cli_fee_prepare($args,$assoc),'DONATION_FEE_UNAVAILABLE');
    }
    check(calls()===$posts+$feeFixturePosts&&$financeHttp->gets===$gets,'whole fee suite no provider request');
    echo 'Donation fee preparation MariaDB: '.($checks-$feeStart)." checks; native races and atomic holds, offline only\n";
}finally{feeConnection();backingClear();}
