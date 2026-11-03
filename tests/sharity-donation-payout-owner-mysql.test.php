<?php
declare(strict_types=1);
// Included before D2y's own table cleanup; real loopback MariaDB + offline SDK.
$ownerStart=$checks;
function d2abFixture(string $tag,string $currency='HUF'):array {
    global $wpdb,$now;$tag=preg_replace('/[^A-Za-z0-9]/','',$tag);
    $wpdb->db->query('DELETE FROM `'.sharity_donation_payout_table().'`');
    [$o,$d,$old]=movementFixture('owner'.$tag,'txn_OWNER'.$tag);
    if($currency!=='HUF'){
        removeReconcileFixture($old);$b=&$d['original']['initial_charge_balance'];$b['currency']=$currency;$b['exchange_rate']=1;
        foreach($b['fee_details'] as &$fee){$fee['currency']=$currency;}unset($fee,$b);reconcileFixture($o,$d);
    }
    sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);
    $f=sharity_donation_finance_reconcile($o['order_id'],$now)['movements'][0]['facts'];
    $h=d2yHttp(strtolower($currency));$h->responses['/v1/payouts/po_SYNTHETIC']['amount']=(int)$f['net_minor'];
    $h->responses['/v1/balance_transactions/txn_PAYOUT']=d2yBalance('txn_PAYOUT','po_SYNTHETIC',-(int)$f['net_minor'],0,'payout',strtolower($currency));
    $h->pages['']['data']=[d2yBalance('txn_OWNER'.$tag,$f['source_id'],(int)$f['gross_minor'],(int)$f['total_fee_minor'],'charge',strtolower($currency))];
    return[$o,$f,$h];
}
function d2abRead(array $o):array {return sharity_donation_payout_owner($GLOBALS['subject'],$o['order_id'],$GLOBALS['now']+120);}
foreach(['HUF','EUR','USD','GBP']as$cc){
    [$o,$f,$h]=d2abFixture($cc,$cc);$receipt=d2yRecord($h);$before=calls();$beforeGet=count($h->calls);$rows=d2yCount();$ddl=$wpdb->ddls;
    $r=d2abRead($o);check($r['status']==='snapshot'&&count($r['records'])===1,'owned payout snapshot '.$cc);
    check($r['records'][0]['currency']===$cc&&$r['records'][0]['payout_minor']===$f['net_minor']&&$r['records'][0]['contribution_minor']===$f['net_minor'],'exact own NET '.$cc);
    check($r['records'][0]['bank_receipt']===false&&$r['bank_receipt']===false,'bank receipt never inferred');
    check($r['records'][0]['observed_at_utc']===gmdate('Y-m-d\TH:i:s\Z',$now),'observation not arrival date');
    check(calls()===$before&&count($h->calls)===$beforeGet&&d2yCount()===$rows&&$wpdb->ddls===$ddl,'owner GET has no provider/row/DDL mutation');
    $j=json_encode($r);foreach(['po_','acct_','txn_','ch_','PRIVATE_','order_id','payout_amount_minor']as$secret){check(!str_contains($j,$secret),'no batch/provider identity '.$secret);}
    check(sharity_donation_payout_owner('hmac-sha256:'.str_repeat('0',64),$o['order_id'],$now)['status']==='unavailable','foreign owner denied');
    $options[SHARITY_DONATION_ORDERS_ENABLED]='0';check(d2abRead($o)===$r,'checkout off retains reader');$options[SHARITY_DONATION_ORDERS_ENABLED]='1';
    check(d2yRecord($h,$now+60)===$receipt&&d2abRead($o)===$r,'identical proof preserves original observation and no double count');
    $h->pages['']['data'][]=$h->responses['/v1/balance_transactions/txn_PAYOUT'];d2yRecord($h,$now+1);
    check(count(d2abRead($o)['records'])===1&&d2abRead($o)['records'][0]['payout_minor']===$f['net_minor'],'optional payout row duplicates no money');
}
foreach([0,1]as$offset){
[$o,$f,$h]=d2abFixture('METADATA'.$offset);$h->pages['']['data'][0]['status']='pending';d2yRecord($h);
check(d2abRead($o)['records'][0]['payout_minor']===null,'pending balance not payout allocation');
$h->pages['']['data'][0]['status']='available';$h->pages['']['data'][0]['available_on']+=10;d2yRecord($h,$now+$offset);
check(d2abRead($o)['status']==='snapshot'&&d2abRead($o)['records'][0]['payout_minor']===$f['net_minor'],'nonfinancial metadata progression not permanent conflict including same-second proof');
}
[$o,$f,$h]=d2abFixture('FAIL');d2yRecord($h);
$failure=clone $h;$failure->responses['/v1/payouts/po_SYNTHETIC']['status']='failed';$failure->responses['/v1/payouts/po_SYNTHETIC']['reconciliation_status']='in_progress';$failure->pages['']['data']=[];
d2yRecord($failure,$now+1);$r=d2abRead($o);check($r['status']==='reconciliation_required'&&$r['records'][0]['payout_minor']===null,'empty later failure cannot hide behind paid proof');
d2yRecord($h,$now+60);check(d2abRead($o)['status']==='reconciliation_required','stale paid replay cannot revive failed payout');
foreach(['reversed_by','original_payout']as$key){
    [$o,$f,$h]=d2abFixture(strtoupper($key));d2yRecord($h);$h->responses['/v1/payouts/po_SYNTHETIC'][$key]='po_OTHER';d2yRecord($h,$now+1);
    check(d2abRead($o)['status']==='reconciliation_required'&&d2abRead($o)['records'][0]['payout_minor']===null,'reversal blocks '.$key);
}
[$o,$f,$h]=d2abFixture('PARTIAL');$h->responses['/v1/payouts/po_SYNTHETIC']['reconciliation_status']='in_progress';d2yRecord($h);
check(d2abRead($o)['status']==='pending'&&d2abRead($o)['records']===[],'partial with no verified membership never allocates');
foreach(['fee','unknown','fx','negative']as$case){
    [$o,$f,$h]=d2abFixture(strtoupper($case));$pp='/v1/payouts/po_SYNTHETIC';$bp='/v1/balance_transactions/txn_PAYOUT';
    if($case==='fee'){$h->responses[$pp]['amount']-=10;$h->responses[$bp]=d2yBalance('txn_PAYOUT','po_SYNTHETIC',-$h->responses[$pp]['amount'],10,'payout');}
    if($case==='unknown'){$h->pages['']['data'][]=d2yBalance('txn_UNKNOWN',null,10,0,'adjustment');$h->responses[$pp]['amount']+=10;$h->responses[$bp]['amount']-=10;$h->responses[$bp]['net']-=10;}
    if($case==='negative'){$h->pages['']['data'][]=d2yBalance('txn_NEGATIVE',null,-10,0,'stripe_fee');$h->responses[$pp]['amount']-=10;$h->responses[$bp]['amount']+=10;$h->responses[$bp]['net']+=10;}
    if($case==='fx'){$h->responses[$pp]['currency']='eur';$h->responses[$bp]['exchange_rate']=1;}
    d2yRecord($h);$r=d2abRead($o);check($r['status']==='reconciliation_required'&&$r['records'][0]['payout_minor']===null,'no arbitrary allocation '.$case);
}
// Shared batch: expose only this order's exact contribution, never other order gross.
[$o,$f,$h]=d2abFixture('SHARED');[$other]=movementFixture('ownerOther','txn_OWNEROTHER');sharity_donation_finance_record_movements($other['order_id'],movementProposal($other),$now);
$of=sharity_donation_finance_reconcile($other['order_id'],$now)['movements'][0]['facts'];
$h->pages['']['data'][]=d2yBalance('txn_OWNEROTHER',$of['source_id'],(int)$of['gross_minor'],(int)$of['total_fee_minor'],'charge');
$h->responses[$pp]['amount']+=(int)$of['net_minor'];$h->responses[$bp]['amount']-=(int)$of['net_minor'];$h->responses[$bp]['net']-=(int)$of['net_minor'];d2yRecord($h);
$r=d2abRead($o);check($r['status']==='snapshot'&&$r['records'][0]['payout_minor']===$f['net_minor'],'shared batch exact own allocation only');
// Another immutable batch claiming the same movement: both become unresolved.
$row=$wpdb->db->query('SELECT * FROM `'.sharity_donation_payout_table().'` LIMIT 1')->fetch_assoc();
$e=json_decode($row['canonical_json'],true);$e['payout']['id']='po_DUP';$e['payout_balance']['source_id']='po_DUP';$e=sharity_donation_payout_evidence($e,'po_DUP','acct_SYNTHETIC',false,'connected');
$j=sharity_donation_finance_json($e);$wpdb->db->query($wpdb->prepare('INSERT INTO `'.sharity_donation_payout_table().'` (account_id,livemode,account_scope,payout_id,canonical_json,digest,observed_at_utc) VALUES (%s,0,%s,%s,%s,%s,%s)','acct_SYNTHETIC','connected','po_DUP',$j,hash('sha256',$j),gmdate('Y-m-d H:i:s',$now+1)));
$r=d2abRead($o);check(count($r['records'])===2&&$r['status']==='reconciliation_required'&&array_column($r['records'],'payout_minor')===[null,null],'duplicate cross-batch membership never twice credited');
// Corrupt, foreign and missing evidence never becomes an empty success.
[$o,$f,$h]=d2abFixture('BOUNDS');$receipt=d2yRecord($h);$pt=sharity_donation_payout_table();$saved=$wpdb->db->query("SELECT * FROM `{$pt}` LIMIT 1")->fetch_assoc();
$wpdb->db->query("UPDATE `{$pt}` SET digest=REPEAT('0',64)");check(d2abRead($o)['status']==='unavailable','bad digest unavailable');
$wpdb->db->query($wpdb->prepare("UPDATE `{$pt}` SET digest=%s,account_id='acct_FOREIGN'",$saved['digest']));check(d2abRead($o)['status']==='pending','foreign account cannot match');
$wpdb->db->query("UPDATE `{$pt}` SET account_id='acct_SYNTHETIC',livemode=1");check(d2abRead($o)['status']==='pending','foreign live mode cannot match');
$wpdb->db->query("UPDATE `{$pt}` SET livemode=0");$options[SHARITY_DONATION_PAYOUT_SCHEMA_OPTION]='0';check(d2abRead($o)['status']==='unavailable','missing schema only payout unavailable');$options[SHARITY_DONATION_PAYOUT_SCHEMA_OPTION]='1';
$wpdb->db->query("UPDATE `{$pt}` SET canonical_json=REPEAT('x',4194305)");check(d2abRead($o)['status']==='unavailable','oversize before body fetch');
$wpdb->db->query($wpdb->prepare("UPDATE `{$pt}` SET canonical_json=%s",$saved['canonical_json']));
for($i=0;$i<256;$i++){$wpdb->db->query($wpdb->prepare("INSERT INTO `{$pt}` (account_id,livemode,account_scope,payout_id,canonical_json,digest,observed_at_utc) VALUES ('acct_SYNTHETIC',0,'connected',%s,%s,%s,%s)",'po_LIMIT'.$i,$saved['canonical_json'],$saved['digest'],$saved['observed_at_utc']));}
check(d2abRead($o)['status']==='unavailable','257 rows returns unavailable, no truncation');
// Snapshot race: failure inserted on second connection during first original-evidence read.
[$o,$f,$h]=d2abFixture('RACE');d2yRecord($h);$ownerConnection=$wpdb;$inserted=false;
$ownerConnection->afterRead=function($sql)use(&$inserted,$h,$ownerConnection){
    if($inserted||!str_contains($sql,'SUBSTRING(CAST(canonical_json'))return;$inserted=true;$ownerConnection->afterRead=null;
    $second=new OrderDb((int)$GLOBALS['port']);$GLOBALS['wpdb']=$second;
    try{$x=clone $h;$x->responses['/v1/payouts/po_SYNTHETIC']['status']='failed';$x->pages['']['data']=[];d2yRecord($x,$GLOBALS['now']+1);}finally{$second->db->close();$GLOBALS['wpdb']=$ownerConnection;}
};
$r=d2abRead($o);check($inserted&&$r['status']==='snapshot','single snapshot excludes concurrent later append');
check(d2abRead($o)['status']==='reconciliation_required','next read includes failure, no stale paid resurrection');
check((int)$wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']===0,'reader releases transaction');
echo 'Donation payout owner MariaDB: '.($checks-$ownerStart)." checks; original owner, exact net, stored evidence only\n";
