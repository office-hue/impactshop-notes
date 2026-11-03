<?php
/** Real isolated MariaDB; synthetic proofs are not provider/bank acceptance. */
declare(strict_types=1);
$backingStart=$checks;
function backingRows(string $kind): int { global $wpdb;return (int)$wpdb->db->query('SELECT COUNT(*) AS n FROM `'.sharity_donation_backing_table($kind).'`')->fetch_assoc()['n']; }
function backingRef(string $tag): array{return ['ref'=>'proof_'.$tag,'sha256'=>hash('sha256',$tag)];}
function backingFixture(string $tag): array {
    global $now;
    [$o,$data,$saved]=movementFixture('backing'.$tag,'txn_BACK'.$tag);
    $inventory=sharity_donation_finance_reconcile($o['order_id'],$now);
    sharity_donation_finance_record_movements($o['order_id'],$inventory['movement_proposal_digest'],$now);
    $snapshot=sharity_donation_order_decode($o)['snapshot'];$m=$inventory['movements'][0];
    $source=['kind'=>'order_receipt','native_id'=>$m['facts']['balance_transaction_id'],'order_id'=>$o['order_id'],
        'principal_minor'=>$m['facts']['net_minor'],'retained_minor'=>$m['facts']['net_minor'],
        'evidence'=>['ref'=>'movement_'.$tag,'sha256'=>$m['financial_digest']], 'retention'=>backingRef('retention'.$tag),
        'ownership'=>null,'as_of_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-60),'expires_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+3600)];
    return [$o,$source,$snapshot];
}
function backingProof(array $snapshot,array $sources,int $revision=0,?string $digest=null,int $offset=60): array {
    global $now;$sum=0;foreach($sources as $s){$sum+=(int)$s['retained_minor'];}
    return ['version'=>1,'frame'=>['provider'=>'stripe','account_id'=>$snapshot['account_id'],'livemode'=>$snapshot['livemode'],'currency'=>'HUF',
        'as_of_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-$offset),'expires_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+3600),
        'retained_minor'=>(string)$sum,'external_liability_minor'=>'0','external_liability_proof'=>backingRef('liabilities'),
        'retention_proof'=>backingRef('frame_retention'),'confirmed'=>true,'previous_revision'=>$revision,'previous_digest'=>$digest], 'sources'=>$sources];
}
function backingOwn(string $tag,string $amount='1000'): array {
    global $now;
    return ['kind'=>'own_collateral','native_id'=>'own_'.$tag,'order_id'=>null,'principal_minor'=>$amount,'retained_minor'=>$amount,
        'evidence'=>backingRef('cash'.$tag),'retention'=>backingRef('keep'.$tag),
        'ownership'=>['owner'=>'sharity','ngo_funds'=>false,'common_fund'=>false,'proof'=>backingRef('ownership'.$tag)],
        'as_of_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-60),'expires_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+3600)];
}
function backingApply(array $p,int $offset=0): array { global $now;$d=sharity_donation_backing_import($p,'',$now+$offset);return sharity_donation_backing_import($p,$d['proposal_digest'],$now+$offset); }
function backingRequest(array $o,array $p,array $receipt,string $amount,string $kind='liability',?string $source=null): array {
    global $now;$inventory=sharity_donation_finance_reconcile($o['order_id'],$now);
    return ['version'=>1,'order_id'=>$o['order_id'],'kind'=>$kind,'frame_key'=>$receipt['frame_key'],'frame_revision'=>$receipt['revision'],
        'frame_digest'=>$receipt['proposal_digest'],'target_generation'=>sharity_donation_backing_target($inventory),'amount_minor'=>$amount,
        'allocations'=>[['source_key'=>$source??sharity_donation_backing_source_key($p['sources'][0],$p['frame']),'amount_minor'=>$amount]]];
}
function backingReserve(array $r,int $offset=0): array { global $now;$d=sharity_donation_backing_reserve($r['order_id'],$r,'',$now+$offset);return sharity_donation_backing_reserve($r['order_id'],$r,$d['proposal_digest'],$now+$offset); }
function backingClear(): void {global $wpdb;
    $t=sharity_donation_fee_accounting_table();if($wpdb->db->query($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s',$t))->num_rows){$wpdb->db->query('DELETE FROM `'.$t.'`');}
    foreach(['claims','sources','frames'] as $k){$wpdb->db->query('DELETE FROM `'.sharity_donation_backing_table($k).'`');}}
class BackingMysqli extends mysqli {
    public $beforeLock=null;public string $fault='';public bool $committed=false;public bool $armed=false;public string $barrier='';public string $barrierNeedle='frame_key = ? AND revision = 1 FOR UPDATE';public int $slot=0;
    public function prepare(string $sql): mysqli_stmt|false {
        if(str_contains($sql,'SUBSTRING(CAST(document AS BINARY)') && str_contains($sql,'SELECT *')){throw new RuntimeException('unbounded backing document projection');}
        if($this->beforeLock!==null && str_contains($sql,'frame_key = ? AND revision = 1 FOR UPDATE')){$hook=$this->beforeLock;$this->beforeLock=null;$hook();}

        if($this->barrier!=='' && !$this->armed && str_contains($sql,$this->barrierNeedle)){
            $this->armed=true;file_put_contents($this->barrier.'/ready-'.$this->slot,'ready');$until=microtime(true)+5;
            while(!is_file($this->barrier.'/ready-'.(1-$this->slot))){if(microtime(true)>$until){throw new RuntimeException('barrier timeout');}usleep(1000);}
        }
        if($this->fault==='frame-insert' && str_starts_with($sql,'INSERT INTO `'.sharity_donation_backing_table('frames').'`')){throw new RuntimeException('synthetic frame failure');}
        if($this->fault==='corrupt-readback' && $this->committed && str_contains($sql,'SUBSTRING(CAST(document AS BINARY)')){$sql=str_replace(' AS document FROM'," AS document, REPEAT('0',64) AS digest FROM",$sql);}
        return parent::prepare($sql);
    }
    public function commit(int $flags=0,?string $name=null):bool {
        if($this->fault==='commit-before'){throw new RuntimeException('synthetic lost request');}
        $r=parent::commit($flags,$name);$this->committed=true;
        if($this->fault==='commit-after'){throw new RuntimeException('synthetic lost ACK');}return$r;
    }
}
function backingConnection(string $fault=''):BackingMysqli {global $wpdb,$port;$wpdb->db->close();$db=new BackingMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;}
function backingRace(callable $fn,string $needle='frame_key = ? AND revision = 1 FOR UPDATE'):array {
    $dir=sys_get_temp_dir().'/sharity-backing-race-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    try {return race(static function($slot)use($fn,$dir,$needle){$db=backingConnection();$db->barrier=$dir;$db->barrierNeedle=$needle;$db->slot=$slot;return$fn($slot);});}
    finally {foreach(glob($dir.'/*') as $f){unlink($f);}rmdir($dir);}
}
$proofFiles=[];
function backingFile(array $p):string {global $proofFiles;$f=tempnam(sys_get_temp_dir(),'sharity-backing-');chmod($f,0600);file_put_contents($f,sharity_donation_finance_json($p));$proofFiles[]=$f;return$f;}
try {
    check(sharity_donation_movement_schema_apply() && sharity_donation_backing_schema_apply(),'explicit backing schema available');
    [$o,$s,$snap]=backingFixture('A');[$other,$s2]=backingFixture('B');$p=backingProof($snap,[$s]);
    $gets=$financeHttp->gets;$posts=calls();$beforeOrder=sharity_donation_webhook_order($o['order_id']);
    $dry=sharity_donation_backing_import($p,'',$now);
    check(backingRows('frames')===0&&backingRows('sources')===0&&!$dry['apply'],'dry import writes nothing');
    $r=backingApply($p);check(backingRows('frames')===1&&backingRows('sources')===1&&$r['revision']===1,'single immutable frame and native source');
    check(backingApply($p,5)===$r,'same proof retains original receipt/time');
    check(sharity_donation_backing_schema_apply()&&backingRows('frames')===1,'schema replay preserves proof');
    $reordered=$p;$reordered['frame']=array_reverse($reordered['frame'],true);check(backingApply($reordered)===$r,'key order does not change identity');
    $req=backingRequest($o,$p,$r,'40000');$d=sharity_donation_backing_reserve($o['order_id'],$req,'',$now);
    check(backingRows('claims')===0&&!$d['reserved'],'reservation dry-run never inserts');
    $claim=backingReserve($req);check($claim['reserved']&&$claim['state']==='prepared'&&backingRows('claims')===1,'prepared claim durably holds');
    check(backingReserve($req,5)===$claim,'claim replay original receipt');
    $changed=$req;$changed['amount_minor']='1';$changed['allocations'][0]['amount_minor']='1';
    fails(fn()=>backingReserve($changed),'DONATION_BACKING_CLAIM_CONFLICT');
    $next=$p;$next['frame']['previous_revision']=1;$next['frame']['previous_digest']=$r['proposal_digest'];$next['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-30);
    $next['frame']['retained_minor']='30000';$next['sources'][0]['retained_minor']='30000';$r2=backingApply($next);
    check($r2['deficit_minor']==='10000'&&$r2['available_minor']==='-10000'&&backingRows('claims')===1,'lower cash stored with deficit and original hold');
    check(backingReserve($req)===$claim,'same claim readback after frame changed');
    $new=backingRequest($o,$next,$r2,'1','payout');fails(fn()=>backingReserve($new),'DONATION_BACKING_INSUFFICIENT');
    $oldRequest=backingRequest($o,$p,$r,'1','correction');fails(fn()=>backingReserve($oldRequest),'DONATION_BACKING_STALE');
    $tamper=$next;$tamper['frame']['previous_revision']=2;$tamper['frame']['previous_digest']=$r2['proposal_digest'];$tamper['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-10);
    $tamper['frame']['retained_minor']='48000';$tamper['sources'][0]['retained_minor']='48000';fails(fn()=>backingApply($tamper),'DONATION_BACKING_SOURCE_CONFLICT');
    $bad=$p;$bad['sources'][0]['order_id']=$other['order_id'];fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_CONFLICT');
    $bad=$p;$bad['frame']['currency']='EUR';fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_CONFLICT');
    $bad=$p;$bad['frame']['previous_revision']=7;$bad['frame']['previous_digest']=str_repeat('0',64);fails(fn()=>backingApply($bad),'DONATION_BACKING_STALE');
    check($financeHttp->gets===$gets&&calls()===$posts&&sharity_donation_webhook_order($o['order_id'])===$beforeOrder,'backing operations do not call SDK/write orders');
    foreach(['provider_verified','retained_cash_verified','activation_allowed'] as $flag){check($r[$flag]===false,'operator proof not verified money: '.$flag);}

    // Two real connections reach the same frame lock together; exactly one 700/1000 hold wins.
    backingClear();$p=backingProof($snap,[backingOwn('race')]);$r=backingApply($p);
    $ra=backingRequest($o,$p,$r,'700');$rb=backingRequest($other,$p,$r,'700');
    $results=backingRace(static fn($slot)=>backingReserve($slot===0?$ra:$rb));
    check(count(array_filter($results,static fn($v)=>isset($v['value'])))===1 && backingRows('claims')===1,'one shared collateral winner');
    check(count(array_filter($results,static fn($v)=>($v['error']??'')==='DONATION_BACKING_INSUFFICIENT'))===1,'loser explicit insufficient');
    backingClear();$r=backingApply($p);$ra=backingRequest($o,$p,$r,'700');
    $results=backingRace(static fn()=>backingReserve($ra));check(isset($results[0]['value'],$results[1]['value'])&&$results[0]['value']===$results[1]['value']&&backingRows('claims')===1,'concurrent same claim one receipt');
    // Account total alone cannot permit double allocation of an individual source.
    backingClear();$p=backingProof($snap,[backingOwn('one'),backingOwn('two')]);$r=backingApply($p);
    backingReserve(backingRequest($o,$p,$r,'700'));fails(fn()=>backingReserve(backingRequest($other,$p,$r,'700')),'DONATION_BACKING_INSUFFICIENT');
    $otherSource=sharity_donation_backing_source_key($p['sources'][1],$p['frame']);
    check(backingReserve(backingRequest($other,$p,$r,'700','liability',$otherSource))['reserved'],'distinct cash source still available');
    // Old/unknown operations hold their allocations too; no generic release exists.
    $wpdb->db->query('UPDATE `'.sharity_donation_backing_table('claims')."` SET state='unknown'");
    fails(fn()=>backingReserve(backingRequest($o,$p,$r,'700','payout')),'DONATION_BACKING_INSUFFICIENT');
    backingClear();$p=backingProof($snap,[$s]);$r=backingApply($p);
    fails(fn()=>backingReserve(backingRequest($other,$p,$r,'1')),'DONATION_BACKING_SOURCE_UNPROVEN');
    $req=backingRequest($o,$p,$r,'1');$req['target_generation']=str_repeat('0',64);fails(fn()=>backingReserve($req),'DONATION_BACKING_STALE');
    $req=backingRequest($o,$p,$r,'1');fails(fn()=>backingReserve($req,3601),'DONATION_BACKING_EXPIRED');
    backingClear();$p=backingProof($snap,[backingOwn('liabilities')]);$p['frame']['external_liability_minor']='400';$r=backingApply($p);
    fails(fn()=>backingReserve(backingRequest($o,$p,$r,'700')),'DONATION_BACKING_INSUFFICIENT');
    check(backingReserve(backingRequest($o,$p,$r,'600'))['reserved'],'external account burden deducted once');

    foreach(['commit-before','commit-after','frame-insert','corrupt-readback'] as $fault){
        backingClear();$p=backingProof($snap,[backingOwn('fault'.$fault)]);$dry=sharity_donation_backing_import($p,'',$now);backingConnection($fault);
        if($fault==='commit-after'){check(sharity_donation_backing_import($p,$dry['proposal_digest'],$now)['revision']===1,'lost frame ACK readback success');}
        else {fails(fn()=>sharity_donation_backing_import($p,$dry['proposal_digest'],$now),'DONATION_BACKING_UNAVAILABLE');}
        backingConnection();$saved=in_array($fault,['commit-after','corrupt-readback'],true)?1:0;
        check(backingRows('frames')===$saved&&backingRows('sources')===$saved,'full atomic frame/source outcome '.$fault);
    }
    backingClear();$p=backingProof($snap,[backingOwn('claimack')]);$r=backingApply($p);$req=backingRequest($o,$p,$r,'500');
    $d=sharity_donation_backing_reserve($o['order_id'],$req,'',$now);backingConnection('commit-before');
    fails(fn()=>sharity_donation_backing_reserve($o['order_id'],$req,$d['proposal_digest'],$now),'DONATION_BACKING_UNAVAILABLE');
    backingConnection();check(backingRows('claims')===0,'lost request leaves no hold');backingConnection('commit-after');
    check(sharity_donation_backing_reserve($o['order_id'],$req,$d['proposal_digest'],$now)['reserved'],'lost claim ACK readback');backingConnection();
    $wpdb->db->begin_transaction();fails(fn()=>backingApply($p),'DONATION_BACKING_UNAVAILABLE');$wpdb->db->rollback();

    // Review regression: an expired zero-retained historical source does not block new money.
    backingClear();$zero=backingOwn('closed');$zero['retained_minor']='0';$zero['expires_at_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-1);
    $p=backingProof($snap,[$zero,backingOwn('fresh')]);$r=backingApply($p);
    $key=sharity_donation_backing_source_key($p['sources'][1],$p['frame']);
    check(backingReserve(backingRequest($o,$p,$r,'500','liability',$key))['reserved'],'zero historical expiry does not freeze new cash');
    backingClear();$expired=$zero;$expired['retained_minor']='1000';$p=backingProof($snap,[$expired,backingOwn('freshpositive')]);$r=backingApply($p);
    $key=sharity_donation_backing_source_key($p['sources'][1],$p['frame']);
    fails(fn()=>backingReserve(backingRequest($o,$p,$r,'500','liability',$key)),'DONATION_BACKING_EXPIRED');
    // A replay after a new frame/source was added returns the original proof receipt and current labelled capacity.
    backingClear();$p=backingProof($snap,[backingOwn('first')]);$r=backingApply($p);$next=$p;$next['sources'][]=backingOwn('additional');
    $next['frame']['retained_minor']='2000';$next['frame']['previous_revision']=1;$next['frame']['previous_digest']=$r['proposal_digest'];
    $next['frame']['as_of_utc']=gmdate('Y-m-d\TH:i:s\Z',$now-30);$newReceipt=backingApply($next);
    $key=sharity_donation_backing_source_key($next['sources'][1],$next['frame']);backingReserve(backingRequest($o,$next,$newReceipt,'500','liability',$key));
    $replayed=backingApply($p);check($replayed['revision']===1&&$replayed['created_at_utc']===$r['created_at_utc']&&$replayed['capacity_frame_revision']===2&&$replayed['held_minor']==='500'&&backingRows('frames')===2,'historic replay cannot reset newer sources/claims');
    backingClear();$fake=backingOwn('relabel');$fake['native_id']=$s['native_id'];$p=backingProof($snap,[$fake]);
    fails(fn()=>backingApply($p),'DONATION_BACKING_SOURCE_UNPROVEN');
    // Append new provider evidence on another real connection after the target was calculated.
    backingClear();$p=backingProof($snap,[backingOwn('stale')]);$r=backingApply($p);$req=backingRequest($o,$p,$r,'100');
    $db=backingConnection();$db->beforeLock=static function()use($o,$port){$old=$GLOBALS['wpdb'];$GLOBALS['wpdb']=new OrderDb((int)$port);
        try{$raw=$GLOBALS['wpdb']->db->query('SELECT canonical_json FROM `'.sharity_donation_finance_table().'` WHERE order_id = '."'".$o['order_id']."'")->fetch_assoc();
            $data=json_decode($raw['canonical_json'],true);$data['original']['initial_charge_balance']['status']='pending';reconcileFixture($o,$data);}
        finally{$GLOBALS['wpdb']->db->close();$GLOBALS['wpdb']=$old;}};
    fails(fn()=>backingReserve($req),'DONATION_BACKING_STALE');backingConnection();check(backingRows('claims')===0,'stale evidence race no reservation');
    // Actual oversized stored body; bounded projection refuses it before parsing.
    $ft=sharity_donation_backing_table('frames');$saved=$wpdb->db->query("SELECT document,digest FROM `{$ft}`")->fetch_assoc();
    $wpdb->db->query("UPDATE `{$ft}` SET document=REPEAT('x',70000),digest=SHA2(REPEAT('x',70000),256)");
    fails(fn()=>backingApply($p),'DONATION_BACKING_UNAVAILABLE');$wpdb->db->query($wpdb->prepare("UPDATE `{$ft}` SET document=%s,digest=%s",$saved['document'],$saved['digest']));
    $req=backingRequest($o,$p,$r,'500');backingReserve($req);
    // Foreign account and live/test separation cannot claim this frame.
    $wrong=$p;$wrong['frame']['account_id']='acct_OTHER';backingClear();$rWrong=backingApply($wrong);
    fails(fn()=>backingReserve(backingRequest($o,$wrong,$rWrong,'1')),'DONATION_BACKING_SOURCE_UNPROVEN');
    backingClear();$wrong=$p;$wrong['frame']['livemode']=true;$rWrong=backingApply($wrong);
    fails(fn()=>backingReserve(backingRequest($o,$wrong,$rWrong,'1')),'DONATION_BACKING_SOURCE_UNPROVEN');
    backingClear();$r=backingApply($p);backingReserve(backingRequest($o,$p,$r,'500'));

    // Late canonical evidence invalidates asserted own cash, without erasing the existing hold.
    backingClear();[$late]=movementFixture('backingLate','txn_BACKLATE');$own=backingOwn('late');$own['native_id']='txn_BACKLATE';
    $pLate=backingProof($snap,[$own]);$rLate=backingApply($pLate);$requestLate=backingRequest($other,$pLate,$rLate,'500');backingReserve($requestLate);
    sharity_donation_finance_record_movements($late['order_id'],movementProposal($late),$now);
    fails(fn()=>backingReserve($requestLate),'DONATION_BACKING_SOURCE_UNPROVEN');
    check(backingRows('claims')===1,'late ownership conflict preserves hold for reconciliation');
    // Both native writers start together and serialize on the same identity.
    backingClear();[$lateRace]=movementFixture('backingLateRace','txn_BACKLATERACE');$own=backingOwn('laterace');$own['native_id']='txn_BACKLATERACE';
    $pRace=backingProof($snap,[$own]);$rRace=backingApply($pRace);$requestRace=backingRequest($other,$pRace,$rRace,'500');$mp=movementProposal($lateRace);
    $results=backingRace(static fn($slot)=>$slot===0?backingReserve($requestRace):sharity_donation_finance_record_movements($lateRace['order_id'],$mp,$now),'START TRANSACTION');
    check(isset($results[1]['value'])&&$results[1]['value']['movements_recorded'],'late real movement registered under shared source lock');
    check(isset($results[0]['value'])||($results[0]['error']??'')==='DONATION_BACKING_SOURCE_UNPROVEN','reservation is either prior hold or explicitly refused');
    fails(fn()=>backingReserve($requestRace),'DONATION_BACKING_SOURCE_UNPROVEN');
    check(backingRows('claims')<=1,'no second claim from misclassified source');
    backingClear();$r=backingApply($p);backingReserve(backingRequest($o,$p,$r,'500'));

    // Exact schema drift blocks writes, not the existing observation reader.
    foreach([['frames','ALTER TABLE `%s` ENGINE=MyISAM','ALTER TABLE `%s` ENGINE=InnoDB'],
        ['claims','ALTER TABLE `%s` DROP INDEX order_kind','ALTER TABLE `%s` ADD UNIQUE KEY order_kind(order_id,kind)'],
        ['sources','ALTER TABLE `%s` MODIFY digest char(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL','ALTER TABLE `%s` MODIFY digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL']] as [$kind,$bad,$restore]){
        $t=sharity_donation_backing_table($kind);$wpdb->db->query(sprintf($bad,$t));
        check(!sharity_donation_backing_schema_apply(),'schema drift rejected '.$kind);
        fails(fn()=>backingApply($p),'DONATION_BACKING_UNAVAILABLE');$wpdb->db->query(sprintf($restore,$t));check(sharity_donation_backing_schema_apply(),'explicit test restoration '.$kind);
    }
    $f=backingFile($p);check(sharity_donation_backing_read_proof($f)===json_decode(sharity_donation_finance_json($p),true),'canonical private file');
    chmod($f,0644);fails(fn()=>sharity_donation_backing_read_proof($f),'DONATION_BACKING_PROOF_PERMISSION');chmod($f,0600);
    $link=$f.'-link';symlink($f,$link);fails(fn()=>sharity_donation_backing_read_proof($link),'DONATION_BACKING_PROOF_PERMISSION');unlink($link);
    $bad=$p;$bad['customer_email']='PRIVATE';fails(fn()=>backingApply($bad),'DONATION_BACKING_PROOF_MALFORMED');
    $bad=$p;unset($bad['frame']['external_liability_proof']);fails(fn()=>backingApply($bad),'DONATION_BACKING_PROOF_MALFORMED');
    $bad=$p;$bad['sources'][0]['ownership']['ngo_funds']=true;fails(fn()=>backingApply($bad),'DONATION_BACKING_SOURCE_UNPROVEN');
    foreach([[],['input'=>$f,'apply'=>true],['input'=>$f,'other'=>true]] as $args){fails(fn()=>sharity_donation_cli_backing_import([],$args),'DONATION_BACKING_UNAVAILABLE');}
    check(backingRows('claims')===1,'invalid proof and CLI preserve existing claim');
    echo 'Donation backing MariaDB: '.($checks-$backingStart)." checks; actual frame/source race, deficit, replay, uncertainty; no live funds\n";
} finally {
    backingConnection();foreach(['claims','sources','frames'] as $k){$wpdb->db->query('DROP TABLE IF EXISTS `'.sharity_donation_backing_table($k).'`');}
    unset($options[SHARITY_DONATION_BACKING_SCHEMA_OPTION]);foreach($proofFiles as $f){if(is_file($f)){unlink($f);}}
}
