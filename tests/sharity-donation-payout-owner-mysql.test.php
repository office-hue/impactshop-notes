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
    $group=sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120);
    check($group['payouts']===$r&&$group['financial']['payment']!==null,'grouped status reuses the same own-payout algorithm '.$cc);
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
final class OwnerReadRaceMysqli extends mysqli {
    public $beforeRead=null;
    public function prepare(string $query):mysqli_stmt|false {
        if($this->beforeRead!==null){($this->beforeRead)($query);}
        return parent::prepare($query);
    }
}
$ownerConnection->db->close();
$ownerConnection->db=new OwnerReadRaceMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);
$ownerConnection->db->set_charset('utf8mb4');
$ownerConnection->db->beforeRead=function($sql)use(&$inserted,$h,$ownerConnection){
    if($inserted||!str_contains($sql,'SUBSTRING(CAST(canonical_json'))return;$inserted=true;
    $ownerConnection->db->beforeRead=null;
    $second=new OrderDb((int)$GLOBALS['port']);$GLOBALS['wpdb']=$second;
    try{$x=clone $h;$x->responses['/v1/payouts/po_SYNTHETIC']['status']='failed';$x->pages['']['data']=[];d2yRecord($x,$GLOBALS['now']+1);}finally{$second->db->close();$GLOBALS['wpdb']=$ownerConnection;}
};
$r=d2abRead($o);check($inserted&&$r['status']==='snapshot','single snapshot excludes concurrent later append');
check(d2abRead($o)['status']==='reconciliation_required','next read includes failure, no stale paid resurrection');
check((int)$wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']===0,'reader releases transaction');
// Reuse this native orders/payout harness with the actual Bank owner and one borrowed connection.
$bankSource=getenv('SHARITY_BANK_PAYOUT_SOURCE');$bankFinance=getenv('SHARITY_OFFERWALL_FINANCE_SOURCE');
if(!is_string($bankSource)||!is_file($bankSource)||!is_string($bankFinance)||!is_file($bankFinance))throw new RuntimeException('Explicit exact Bank/Finance source required for bank integration');
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/fixtures/wordpress/');
require_once $bankFinance;require_once $bankSource;
class DonationBankTestDB extends OrderDb {
    public function __construct(OrderDb $old){$this->db=$old->db;$this->prefix=$old->prefix;$this->options=$old->options;}
    public function get_charset_collate():string{return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';}
    public function get_var(string $sql):mixed{$r=$this->query($sql);return $r instanceof mysqli_result?($r->fetch_row()[0]??null):null;}
}
class DonationBankFaultMysqli extends mysqli {
    public string $fault='';public string $commandsTable='';
    public function query(string $query,int $result_mode=MYSQLI_STORE_RESULT):mysqli_result|bool{
        if($this->fault==='before-journal'&&str_starts_with($query,'INSERT INTO '.$this->commandsTable.' ')){$this->fault='';throw new RuntimeException('synthetic journal transport failure');}
        $result=parent::query($query,$result_mode);
        if($this->fault==='lost-commit'&&$query==='COMMIT'){$this->fault='';return false;}
        return $result;
    }
}
$bankOldDb=$wpdb;$wpdb=new DonationBankTestDB($wpdb);$bankOptions=$options;$bankInitialSnapshot=$GLOBALS['snapshot'];
$bankDir=realpath(sys_get_temp_dir()).'/donation-bank-'.bin2hex(random_bytes(5));mkdir($bankDir,0700);$bankPolicyFile=$bankDir.'/policy.json';
$b=ImpactShop_Sharity_Bank_Payout::class;$bankPair=sodium_crypto_sign_keypair();$bankSecret=sodium_crypto_sign_secretkey($bankPair);$bankPublic=sodium_crypto_sign_publickey($bankPair);
if(!defined('SHARITY_OFFERWALL_FINANCE_SIGNING_KEY'))define('SHARITY_OFFERWALL_FINANCE_SIGNING_KEY',base64_encode($bankSecret));
if(!defined('SHARITY_OFFERWALL_FINANCE_SIGNING_KEY_ID'))define('SHARITY_OFFERWALL_FINANCE_SIGNING_KEY_ID','bank-fixture');
putenv('SHARITY_BANK_PAYOUT_POLICY_FILE='.$bankPolicyFile);$bankAccount=str_repeat('a',64);$bankFingerprint=str_repeat('b',64);
try{
    foreach(ImpactShop_Sharity_Offerwall_V2_Finance::schema_sql()as$sql)check($wpdb->query($sql)!==false,'actual finance fixture schema');
    $b::migrate();$bankHadTarget=get_option(SHARITY_DONATION_TARGET_SCHEMA_OPTION)==='1';if(!$bankHadTarget)check(sharity_donation_target_schema_apply(),'target schema for integrated bank fixture');$bankSerial=0;
    $bankApply=static function(array $data,string $operation='donation-bank-receipt',array $overrides=[])use($b,$bankSecret,&$bankSerial):array{
        $plan=$b::plan(array_replace(['operation'=>$operation,'command_id'=>'donation-bank-'.(++$bankSerial),'actor'=>'operator-fixture','key_id'=>'operator','expires_at'=>time()+600,'grant_id'=>$operation==='donation-bank-receipt'?'receipt':'return','data'=>$data],$overrides));
        $signature=sodium_crypto_sign_detached($b::signing_domain($plan).$b::canonical($plan),$bankSecret);return [$b::apply($plan,$signature),$plan,$signature];
    };
    foreach([['direct','HUF'],['direct','EUR'],['amplifier','HUF'],['amplifier','EUR']]as[$mode,$cc]){
        foreach(['cash','claims','entries','batches','budgets','meta','commands','events']as$table)$wpdb->query('DELETE FROM `'.$wpdb->prefix.'sharity_bp_'.$table.'`');
        $wpdb->query('DELETE FROM `'.sharity_donation_payout_table().'`');
        if($mode==='direct'){
            $savedSnapshot=$GLOBALS['snapshot'];$savedAccounts=$options['sharity_donation_accounts_v1'];$GLOBALS['snapshot']['currency']=$cc;
            $options['sharity_donation_accounts_v1']['test-ngo']['currencies'][$cc]=['min_minor'=>$cc==='HUF'?'100':'1','max_minor'=>'1000000'];
            try{[$o,$f,$h]=d2abFixture('BANK'.$cc,$cc);}finally{$GLOBALS['snapshot']=$savedSnapshot;$options['sharity_donation_accounts_v1']=$savedAccounts;}
            $raw=targetSave($o);
        }else{
            [$o,$ad,$raw]=$ampCloseFixture('BANK'.$cc,$cc);$inventory=sharity_donation_finance_reconcile($o['order_id'],$now);$own=[];
            $accountId=sharity_donation_order_decode($o)['snapshot']['account_id'];
            foreach($inventory['movements']as$m)if($m['facts']['account_id']===$accountId)$own[]=$m['facts'];
            $amount=array_sum(array_map(static fn($m)=>(int)$m['net_minor'],$own));$f=['account_id'=>$accountId,'net_minor'=>(string)$amount];$h=d2yHttp(strtolower($cc));
            $h->responses['/v1/payouts/po_SYNTHETIC']['amount']=$amount;$h->responses['/v1/balance_transactions/txn_PAYOUT']=d2yBalance('txn_PAYOUT','po_SYNTHETIC',-$amount,0,'payout',strtolower($cc));$h->pages['']['data']=[];
            foreach($own as$m){$balance=d2yBalance($m['balance_transaction_id'],$m['source_id'],(int)$m['gross_minor'],(int)$m['total_fee_minor'],$m['provider_type']??($m['movement_role']==='charge'?'charge':'adjustment'),strtolower($cc));
                $balance['fee_details']=array_map(static fn($fee)=>['amount'=>(int)$fee['amount_minor'],'currency'=>strtolower($fee['currency']),'type'=>$fee['type']],$m['fee_details']);$h->pages['']['data'][]=$balance;}
        }
        $receipt=d2yRecord($h);$ref=backingRef('bank-close-'.$mode.$cc);
        $close=sharity_donation_finance_close($o['order_id'],$raw['proposal_digest'],$ref,$now);
        $close=sharity_donation_finance_close($o['order_id'],$raw['proposal_digest'],$ref,$now,$close['proposal_digest']);check($close['persisted'],'native original manual close');
        $bankOrderSnapshot=sharity_donation_order_decode($o)['snapshot'];
        $domain=['account_id'=>$bankAccount,'legal_entity'=>'ngo-legal-fixture','purpose'=>'ngo_recipient','ngo_id'=>'101'];
        $bankBinding=['ngo_slug'=>$bankOrderSnapshot['ngo_slug'],'binding_version'=>$bankOrderSnapshot['binding_version'],'donation_modes'=>['direct','amplifier']];
        $scope=['provider'=>'stripe','provider_scope_ref'=>$f['account_id'],'source_policy_sha256'=>hash('sha256',$b::canonical([$domain,$bankBinding]))];
        $policy=['schema_version'=>2,'amount_scale'=>2,'enabled'=>false,'target'=>'bank-fixture','legal_entity'=>'platform-fixture','mode'=>'test',
            'accounts'=>[$bankAccount=>['legal_entity'=>'ngo-legal-fixture','purpose'=>'ngo_recipient','ngo_id'=>'101','currencies'=>['HUF','EUR'],'aliases'=>[['bank_source'=>'bank-fixture','account_fingerprint'=>$bankFingerprint]],'donation_bindings'=>[$f['account_id']=>$bankBinding]]],
            'recipients'=>[],'keys'=>['operator'=>['actor'=>'operator-fixture','roles'=>['approver'],'grant_ids'=>['receipt','return'],'public_key'=>base64_encode($bankPublic)]],
            'operation_grants'=>['receipt'=>['operation'=>'donation-bank-receipt']+$scope,'return'=>['operation'=>'donation-bank-return']+$scope],
            'projection'=>['ngo_binding_version'=>'bank-fixture-binding']];
        file_put_contents($bankPolicyFile,$b::canonical($policy));chmod($bankPolicyFile,0600);
        $data=['financial_scope'=>$scope,'cash'=>['bank_source'=>'bank-fixture','account_fingerprint'=>$bankFingerprint,'bank_tx_id'=>'bank-original-'.$cc,'currency'=>$cc,'net_minor'=>(int)$f['net_minor'],'evidence_ref'=>'fixture-bank-proof','evidence_sha256'=>hash('sha256','synthetic-bank-proof'),'parts'=>[['part_id'=>'part','classification'=>'ngo_recipient','net_minor'=>(int)$f['net_minor']]]],
            'part_id'=>'part','booked_at'=>gmdate('Y-m-d',$now),'selection'=>['account_id'=>$f['account_id'],'livemode'=>false,'payout_id'=>'po_SYNTHETIC','digest'=>$receipt['digest']],
            'orders'=>[['order_id'=>$o['order_id'],'target_revision'=>(int)$close['revision'],'target_digest'=>hash('sha256',sharity_donation_finance_json($close['target']))]]];
        $beforeGets=count($h->calls);$beforePosts=calls();$optionsBefore=$options;
        foreach(['net','currency','target','members','owner','evidence']as$bad){$wrong=$data;
            if($bad==='net'){$wrong['cash']['net_minor']-=100;$wrong['cash']['parts'][0]['net_minor']-=100;}
            if($bad==='currency')$wrong['cash']['currency']=$cc==='HUF'?'EUR':'HUF';
            if($bad==='target')$wrong['orders'][0]['target_digest']=str_repeat('0',64);
            if($bad==='members')$wrong['orders']=[];
            if($bad==='owner')$wrong['cash']['legal_entity']='spoofed-owner';
            if($bad==='evidence')$wrong['cash']['evidence_sha256']='not-a-document-digest';
            [$r]=$bankApply($wrong);check($r['status']==='conflict','signed mismatched bank input held '.$bad.' '.json_encode($r));
        }
        if($mode==='direct'){
            $wpdb->db->close();$faultDb=new DonationBankFaultMysqli('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$faultDb->set_charset('utf8mb4');$faultDb->commandsTable=$wpdb->prefix.'sharity_bp_commands';$wpdb->db=$faultDb;$bankOldDb->db=$faultDb;
            if($cc==='HUF'){
                $faultDb->fault='before-journal';[$rolled,$rolledPlan]=$bankApply($data);check($rolled['status']==='outcome_unknown','synthetic uncertain journal write must be read back');
                check($b::status($rolledPlan['command_id'])['command']===null,'readback confirms whole receipt/journal rolled back');
                check((int)$wpdb->db->query('SELECT COUNT(*) FROM `'.$wpdb->prefix.'sharity_bp_cash`')->fetch_row()[0]===0,'failed journal cannot leave cash');
                $plans=[];foreach([0,1]as$slot)$plans[]=$b::plan(['operation'=>'donation-bank-receipt','command_id'=>'parallel-bank-'.(++$bankSerial),'actor'=>'operator-fixture','key_id'=>'operator','expires_at'=>time()+600,'grant_id'=>'receipt','data'=>$data]);
                $raced=race(static function(int $slot)use($b,$plans,$bankSecret):array{
                    global $wpdb;$barrier=$wpdb->barrier;file_put_contents($barrier.'/bank-ready-'.$slot,'ready');$deadline=microtime(true)+5;
                    while(!is_file($barrier.'/bank-ready-'.(1-$slot))){if(microtime(true)>$deadline)throw new RuntimeException('bank race barrier timeout');usleep(1000);}
                    $wpdb=new DonationBankTestDB($wpdb);$plan=$plans[$slot];$signature=sodium_crypto_sign_detached($b::signing_domain($plan).$b::canonical($plan),$bankSecret);
                    return ['result'=>$b::apply($plan,$signature),'thread'=>$wpdb->db->thread_id];
                },'test-only-bank-race');
                $wpdb=new DonationBankTestDB($wpdb);$bankOldDb->db=$wpdb->db;
                $states=array_map(static fn($x)=>$x['value']['result']['status']??'error',$raced);$sorted=$states;sort($sorted);check($sorted===['applied','conflict'],'two actual connections cannot double-claim one bank receipt '.json_encode($raced));
                check($raced[0]['value']['thread']!==$raced[1]['value']['thread'],'parallel workers use distinct native connections');$winner=array_search('applied',$states,true);$applied=$raced[$winner]['value']['result'];$plan=$plans[$winner];$signature=sodium_crypto_sign_detached($b::signing_domain($plan).$b::canonical($plan),$bankSecret);
            }else{
                $faultDb->fault='lost-commit';[$unknown,$plan,$signature]=$bankApply($data);check($unknown['status']==='outcome_unknown','actual COMMIT with lost ACK is not retried');
                $applied=$b::status($plan['command_id'])['command']['result']??null;check(is_array($applied)&&$applied['status']==='applied','command readback resolves committed bank receipt');
            }
        }else{[$applied,$plan,$signature]=$bankApply($data);}
        check($applied['status']==='applied','exact signed bank credit '.$mode.$cc.' '.json_encode($applied));
        check($b::apply($plan,$signature)['status']==='already_applied','same command exactly once');
        [$again]=$bankApply($data);check($again['status']==='applied'&&$again['already_recorded']===true,'same bank receipt new command not new money');
        $view=sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial'];
        check($view['bank_receipt']===['received_at_utc'=>gmdate('Y-m-d\T00:00:00\Z',$now),'currency'=>$cc,'contribution_minor'=>$f['net_minor']],'existing owner DTO contains only its exact bank share '.$cc);
        check(!str_contains(json_encode($view),'po_')&&!str_contains(json_encode($view),'acct_')&&!str_contains(json_encode($view),'bank-original'),'no bank/provider IDs in owner DTO');
        $status=$b::status();check($status['available_minor']===[]&&$status['budgets']===[]&&$status['batches']===[],'NGO credit creates no spendable platform capacity');
        $refresh=$b::refresh_financial_summary();check($refresh['ok']===true,'existing signed Card projection '.json_encode($refresh));$envelope=$b::financial_summary();check(is_array($envelope),'fresh Card envelope');
        $rawPayload=base64_decode($envelope['payload'],true);check(sodium_crypto_sign_verify_detached(base64_decode($envelope['signature'],true),"sharity-ngo-financial-summary-v2\n".$rawPayload,$bankPublic),'existing Card signature domain');
        $payload=json_decode($rawPayload,true);$rows=array_values(array_filter($payload['entries'],static fn($r)=>$r['channel']===$mode));
        check(count($rows)===1&&$rows[0]['paid_minor']===$f['net_minor']&&$rows[0]['recipient_credited_minor']===$f['net_minor']&&$rows[0]['coverage']==='partial','Card shows verified receipt without claiming complete donation history');
        check((int)$rows[0]['entitlement_minor']-(int)$rows[0]['reserved_minor']-(int)$rows[0]['paid_minor']===0,'direct paid principal never becomes available');
        foreach([SHARITY_DONATION_FINANCE_SCHEMA_OPTION,SHARITY_DONATION_PAYOUT_SCHEMA_OPTION,SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION,SHARITY_DONATION_TARGET_SCHEMA_OPTION]as$option){
            $original=$options[$option];$options[$option]='0';
            check(sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial']['bank_receipt']===null&&$b::financial_summary()===null,'source schema OFF invalidates owner and Card together '.$option);
            $options[$option]=$original;
        }
        $commandTable=$wpdb->prefix.'sharity_bp_commands';$metaTable=$wpdb->prefix.'sharity_bp_meta';
        $originalCommand=$wpdb->get_var($wpdb->prepare("SELECT data_json FROM `{$commandTable}` WHERE id=%s",$plan['command_id']));$corrupt=json_decode($originalCommand,true);$corrupt['signature']=base64_encode(str_repeat('x',64));
        $wpdb->query($wpdb->prepare("UPDATE `{$commandTable}` SET data_json=%s WHERE id=%s",$b::canonical($corrupt),$plan['command_id']));
        check(sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial']['bank_receipt']===null&&$b::financial_summary()===null,'tampered journal signature invalidates owner and existing signed Card cache');
        $wpdb->query($wpdb->prepare("UPDATE `{$commandTable}` SET data_json=%s WHERE id=%s",$originalCommand,$plan['command_id']));
        $policyKey='policy:'.$plan['policy_sha256'];$archive=$wpdb->get_var($wpdb->prepare("SELECT data_json FROM `{$metaTable}` WHERE id=%s",$policyKey));$corrupt=json_decode($archive,true);$corrupt['keys']['operator']['roles']=[];
        $wpdb->query($wpdb->prepare("UPDATE `{$metaTable}` SET data_json=%s WHERE id=%s",$b::canonical($corrupt),$policyKey));
        check(sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial']['bank_receipt']===null&&$b::financial_summary()===null,'altered original signed policy never remains current acceptance');
        $wpdb->query($wpdb->prepare("UPDATE `{$metaTable}` SET data_json=%s WHERE id=%s",$archive,$policyKey));check(is_array($b::financial_summary()),'original journal/policy restoration recovers unchanged cache');

        $return=['financial_scope'=>$scope,'receipt_ref'=>$applied['receipt_ref'],'amount_minor'=>100,'currency'=>$cc,'booked_at'=>$data['booked_at'],'bank_tx_id'=>'bank-return-'.$cc,'evidence_ref'=>'fixture-return-proof','evidence_sha256'=>hash('sha256','synthetic-return-proof')];
        [$r]=$bankApply($return,'donation-bank-return',['command_id'=>str_repeat('r',191)]);check($r['status']==='applied','signed partial bank return with maximum-length command ID');
        $stages=new ReflectionMethod($b,'projection_stages');set_error_handler(static function(int $level,string $message):never{throw new ErrorException($message,0,$level);});
        try{$b::transaction(static fn()=>$stages->invoke(null,'unrelated-ngo',$cc,'offerwall',[]));}finally{restore_error_handler();}
        check($b::financial_summary()===null,'old Card cache invalidated after signed return');
        $view=sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial'];check($view['bank_receipt']['contribution_minor']===(string)((int)$f['net_minor']-100),'one-order partial return exact, no proportional guess');
        $tooMuch=$return;$tooMuch['bank_tx_id']='too-large';$tooMuch['amount_minor']=(int)$f['net_minor'];[$r]=$bankApply($tooMuch,'donation-bank-return');check($r['status']==='conflict','return capped by original bank receipt');
        [$r]=$bankApply($return,'donation-bank-return');check($r['status']==='conflict','one outgoing physical bank transaction cannot be reused');
        $failed=clone $h;$failed->responses['/v1/payouts/po_SYNTHETIC']['status']='failed';$failed->responses['/v1/payouts/po_SYNTHETIC']['reconciliation_status']='in_progress';$failed->pages['']['data']=[];d2yRecord($failed,$now+1);
        $view=sharity_donation_owned_status($o['subject_ref'],'order_id',$o['order_id'],$now+120)['financial'];check($view['bank_receipt']===null,'later payout failure invalidates current owner acceptance');
        check(count($h->calls)===$beforeGets&&calls()===$beforePosts&&$options===$optionsBefore,'bank operations have no provider/order/option side effects');
        if($cc==='HUF'){
            [$otherOrder,$otherFacts,$otherHttp]=d2abFixture('RETURNREUSE'.$mode);$otherRaw=targetSave($otherOrder);$reference=backingRef('return-reuse-'.$mode);
            $otherClose=sharity_donation_finance_close($otherOrder['order_id'],$otherRaw['proposal_digest'],$reference,$now);$otherClose=sharity_donation_finance_close($otherOrder['order_id'],$otherRaw['proposal_digest'],$reference,$now,$otherClose['proposal_digest']);
            $otherHttp->payoutId='po_SECOND';$payout=$otherHttp->responses['/v1/payouts/po_SYNTHETIC'];$payout['id']='po_SECOND';$payout['balance_transaction']='txn_SECOND';$otherHttp->responses['/v1/payouts/po_SECOND']=$payout;
            $otherHttp->responses['/v1/balance_transactions/txn_SECOND']=d2yBalance('txn_SECOND','po_SECOND',-(int)$otherFacts['net_minor'],0,'payout');
            $otherReceipt=sharity_donation_payout_record('po_SECOND',$otherFacts['account_id'],false,'connected',$adapter,$now,$otherHttp);
            $otherData=$data;$otherData['cash']['bank_tx_id']=$return['bank_tx_id'];$otherData['cash']['net_minor']=(int)$otherFacts['net_minor'];$otherData['cash']['parts'][0]['net_minor']=(int)$otherFacts['net_minor'];
            $otherData['selection']['payout_id']='po_SECOND';$otherData['selection']['digest']=$otherReceipt['digest'];$otherData['orders']=[['order_id'=>$otherOrder['order_id'],'target_revision'=>(int)$otherClose['revision'],'target_digest'=>hash('sha256',sharity_donation_finance_json($otherClose['target']))]];
            [$reused]=$bankApply($otherData);check($reused['status']==='conflict'&&$reused['reason']==='donation_bank_transaction_already_used','outgoing bank tx cannot become a different eligible payout receipt');
            $otherData['cash']['bank_tx_id']='fresh-bank-receipt-'.$mode;[$fresh]=$bankApply($otherData);check($fresh['status']==='applied','same new payout and order eligible with a new physical bank tx');
        }

    }
    // A mixed Direct/Amplifier payout has exact owner shares, but an unallocated return is held.
    foreach(['cash','claims','entries','batches','budgets','meta','commands','events']as$table)$wpdb->query('DELETE FROM `'.$wpdb->prefix.'sharity_bp_'.$table.'`');
    [$directOrder,$directFacts,$mixHttp]=d2abFixture('MIXEDBANK');$directRaw=targetSave($directOrder);
    [$ampOrderForBank,$ampFacts,$ampRaw]=$ampCloseFixture('MIXEDBANK');$mixOrders=[];$funding=[];$total=0;
    foreach([[$directOrder,$directRaw],[$ampOrderForBank,$ampRaw]]as[$order,$raw]){
        $reference=backingRef('mixed-bank-'.$order['order_id']);$close=sharity_donation_finance_close($order['order_id'],$raw['proposal_digest'],$reference,$now);$close=sharity_donation_finance_close($order['order_id'],$raw['proposal_digest'],$reference,$now,$close['proposal_digest']);
        $mixOrders[]=['order_id'=>$order['order_id'],'target_revision'=>(int)$close['revision'],'target_digest'=>hash('sha256',sharity_donation_finance_json($close['target']))];
        $inventory=sharity_donation_finance_reconcile($order['order_id'],$now);foreach($inventory['movements']as$m)if($m['facts']['account_id']===$data['selection']['account_id']){
            $f=$m['facts'];$total+=(int)$f['net_minor'];$balance=d2yBalance($f['balance_transaction_id'],$f['source_id'],(int)$f['gross_minor'],(int)$f['total_fee_minor'],$f['provider_type']??($f['movement_role']==='charge'?'charge':'adjustment'));
            $balance['fee_details']=array_map(static fn($fee)=>['amount'=>(int)$fee['amount_minor'],'currency'=>strtolower($fee['currency']),'type'=>$fee['type']],$f['fee_details']);$funding[]=$balance;
        }
    }
    $mixHttp->responses['/v1/payouts/po_SYNTHETIC']['amount']=$total;$mixHttp->responses['/v1/balance_transactions/txn_PAYOUT']=d2yBalance('txn_PAYOUT','po_SYNTHETIC',-$total,0,'payout');$mixHttp->pages['']['data']=$funding;$receipt=d2yRecord($mixHttp);
    $mixed=$data;$mixed['cash']['currency']='HUF';$mixed['cash']['net_minor']=$total;$mixed['cash']['parts'][0]['net_minor']=$total;$mixed['cash']['bank_tx_id']='mixed-bank-credit';$mixed['selection']['digest']=$receipt['digest'];$mixed['orders']=$mixOrders;
    [$mixedResult]=$bankApply($mixed);check($mixedResult['status']==='applied','mixed-mode payout exact full membership '.json_encode($mixedResult));
    foreach([$directOrder,$ampOrderForBank]as$order)check(sharity_donation_owned_status($order['subject_ref'],'order_id',$order['order_id'],$now+120)['financial']['bank_receipt']!==null,'each owner sees its own original contribution before return');
    $return=['financial_scope'=>$scope,'receipt_ref'=>$mixedResult['receipt_ref'],'amount_minor'=>100,'currency'=>'HUF','booked_at'=>$mixed['booked_at'],'bank_tx_id'=>'mixed-return','evidence_ref'=>'fixture-mixed-return','evidence_sha256'=>hash('sha256','synthetic-mixed-return-proof')];[$returned]=$bankApply($return,'donation-bank-return');check($returned['status']==='applied','mixed batch return recorded without invented attribution');
    foreach([$directOrder,$ampOrderForBank]as$order)check(sharity_donation_owned_status($order['subject_ref'],'order_id',$order['order_id'],$now+120)['financial']['bank_receipt']===null,'unallocated multi-order return holds current owner amount');
    check($b::refresh_financial_summary()['ok']===true,'mixed held Card projection');$envelope=$b::financial_summary();$payload=json_decode(base64_decode($envelope['payload'],true),true);$mixedRows=array_values(array_filter($payload['entries'],static fn($r)=>in_array($r['channel'],['direct','amplifier'],true)));
    check(count($mixedRows)===2,'both existing Card channels retained');foreach($mixedRows as$row)check($row['entitlement_minor']===null&&$row['paid_minor']===null&&$row['recipient_credited_minor']===null,'unknown mixed attribution never becomes fabricated proportional money');
}finally{
    foreach(['cash','claims','entries','batches','budgets','meta','commands','events']as$table)$wpdb->query('DROP TABLE IF EXISTS `'.$wpdb->prefix.'sharity_bp_'.$table.'`');
    foreach(ImpactShop_Sharity_Offerwall_V2_Finance::table_names()as$table)$wpdb->query('DROP TABLE IF EXISTS `'.$table.'`');
    if(isset($bankHadTarget)&&!$bankHadTarget)$wpdb->query('DROP TABLE IF EXISTS `'.sharity_donation_target_table().'`');
    $GLOBALS['snapshot']=$bankInitialSnapshot;$options=$bankOptions;$wpdb=$bankOldDb;putenv('SHARITY_BANK_PAYOUT_POLICY_FILE');unlink($bankPolicyFile);rmdir($bankDir);
}
echo 'Donation payout owner MariaDB: '.($checks-$ownerStart)." checks; original owner, exact net, stored evidence only\n";
