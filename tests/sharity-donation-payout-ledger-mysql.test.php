<?php
declare(strict_types=1);
// Included only in the existing disposable MariaDB harness.
final class D2yHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses=[],$pages=[],$calls=[];public $mutate=null;
    public function request($m,$u,$h,$p,$f,$a='v1',$r=null){
        global $wpdb;$path=parse_url($u,PHP_URL_PATH);
        check($m==='get'&&$f===false&&$r===0&&$a==='v1','payout reads official GET only, no retry');
        check((string)$wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='0','payout HTTP outside transaction');
        $this->calls[]=['path'=>$path,'params'=>$p,'headers'=>array_values(array_filter($h,static fn($x)=>!str_starts_with(strtolower($x),'authorization:')))];
        if($this->mutate!==null)($this->mutate)($path,$this);
        if($path==='/v1/balance_transactions'){
            check(($p['payout']??null)==='po_SYNTHETIC'&&($p['limit']??null)===100&&!isset($p['currency'])&&!isset($p['type']),'exact payout filter and bounded whole batch');
            $data=$this->pages[$p['starting_after']??'']??null;
        }else{$data=$this->responses[$path]??null;}
        if(!is_array($data))throw new RuntimeException('PRIVATE_PROVIDER_FAILURE');
        return[json_encode($data),200,[]];
    }
}
function d2yBalance(string $id,?string $source,int $gross,int $fee,string $type,string $currency='huf'):array {
    return ['object'=>'balance_transaction','id'=>$id,'source'=>$source,'type'=>$type,'amount'=>$gross,'fee'=>$fee,'net'=>$gross-$fee,
        'currency'=>$currency,'fee_details'=>$fee?[['amount'=>$fee,'currency'=>$currency,'type'=>'stripe_fee','description'=>'PRIVATE_FEE']]:[],
        'created'=>1800000010,'available_on'=>1800000200,'status'=>'available','exchange_rate'=>null,'description'=>'PRIVATE_BALANCE'];
}
function d2yHttp(string $currency='huf'):D2yHttp {
    $h=new D2yHttp();$p=['object'=>'payout','id'=>'po_SYNTHETIC','livemode'=>false,'amount'=>10000,'currency'=>$currency,
        'automatic'=>true,'method'=>'standard','type'=>'bank_account','status'=>'paid','reconciliation_status'=>'completed',
        'created'=>1800000020,'arrival_date'=>1800000400,'balance_transaction'=>'txn_PAYOUT','failure_balance_transaction'=>null,
        'original_payout'=>null,'reversed_by'=>null,'destination'=>'ba_PRIVATE','failure_message'=>'PRIVATE_BANK','metadata'=>['owner'=>'PRIVATE_OWNER']];
    $h->responses=['/v1/account'=>['object'=>'account','id'=>'acct_SYNTHETIC','email'=>'PRIVATE_EMAIL'], '/v1/payouts/po_SYNTHETIC'=>$p,
        '/v1/balance_transactions/txn_PAYOUT'=>d2yBalance('txn_PAYOUT','po_SYNTHETIC',-10000,300,'payout',$currency)];
    $h->pages=[''=>['object'=>'list','has_more'=>false,'data'=>[
        d2yBalance('txn_CHARGE','ch_SYNTHETIC',12000,200,'charge',$currency),
        d2yBalance('txn_REFUND','re_SYNTHETIC',-1000,0,'refund',$currency),
        d2yBalance('txn_FEE',null,-500,0,'stripe_fee',$currency)]]];return$h;
}
$payoutStart=$checks;$adapter=$fixtureAdapter;$pt=sharity_donation_payout_table();
$pp='/v1/payouts/po_SYNTHETIC';$bp='/v1/balance_transactions/txn_PAYOUT';
function d2yRecord(D2yHttp $h,int $at=0,string $scope='connected'):array {
    global $adapter,$now;return sharity_donation_payout_record('po_SYNTHETIC','acct_SYNTHETIC',false,$scope,$adapter,$at?:$now,$h);
}
function d2yCount():int {global $wpdb;return(int)$wpdb->db->query('SELECT COUNT(*) AS n FROM `'.sharity_donation_payout_table().'`')->fetch_assoc()['n'];}
function d2yReport(array $r):array {return sharity_donation_payout_reconcile('po_SYNTHETIC','acct_SYNTHETIC',false,$r['digest'],$GLOBALS['now']);}
final class D2yFaultDb extends OrderDb {
    public string $fault='';public bool $armed=false;public int $inserts=0;
    public function query(string $sql){$r=parent::query($sql);if(str_starts_with($sql,'INSERT INTO `'.sharity_donation_payout_table().'`')){$this->armed=true;$this->inserts++;}return$r;}
    public function get_row(string $sql,$mode):?array{$r=parent::get_row($sql,$mode);if($this->armed&&str_contains($sql,'payout_id =')){
        if($this->fault==='missing')return null;if($this->fault==='corrupt'&&$r!==null)$r['account_id']='acct_OTHER';}return$r;}
}
$ddl=$wpdb->ddls;$posts=calls();
try {
    foreach([[[],[]],[[],['apply'=>false]],[[],['apply'=>'true']],[['apply'],[]],[[],['apply'=>true,'extra'=>true]]]as[$a,$b]){
        fails(fn()=>sharity_donation_cli_payout_schema($a,$b),'DONATION_PAYOUT_SCHEMA_UNAVAILABLE');check($wpdb->ddls===$ddl,'only exact apply executes DDL');
    }
    sharity_donation_cli_payout_schema([],['apply'=>true]);check(get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION)==='1','actual payout schema');
    check(isset(WP_CLI::$commands['sharity donations payout-record'],WP_CLI::$commands['sharity donations payout-reconcile']),'new CLI registered');
    foreach(['huf','eur','usd','gbp']as$cc){foreach(['connected','platform']as$scope){
        $h=d2yHttp($cc);$r=d2yRecord($h,0,$scope);$saved=sharity_donation_payout_read('acct_SYNTHETIC',false,'po_SYNTHETIC',$r['digest']);
        check(!str_contains($saved['canonical_json'],'PRIVATE_')&&!str_contains($saved['canonical_json'],'subject_ref'),'sanitized native payout stored');
        check(d2yRecord($h,$now+60,$scope)===$r,'duplicate keeps original ID/time');$report=d2yReport($r);
        check($report['batch_totals_reconciled']&&count($report['unmatched_items'])===3&&$report['matched_orders']===[],'unknown full batch retained');
        check(!$report['ngo_bank_received']&&!$report['allocation_eligible']&&!$report['payout_allocation_verified'],'no financial or bank acceptance');
        check($report['payout']['currency']===strtoupper($cc)&&$report['payout']['amount_minor']==='10000','no HUF scaling or relabeling');
    }}
    $h=d2yHttp();$old=d2yRecord($h);$h->responses[$pp]['created']+=1;$r=d2yRecord($h);
    check($old['digest']!==$r['digest']&&$old['id']!==$r['id'],'changed fact appends');
    $h->pages['']['data']=array_reverse($h->pages['']['data']);check(d2yRecord($h)===$r,'list order canonical');
    $count=d2yCount();check(sharity_donation_payout_schema_apply()&&d2yCount()===$count,'schema repeat preserves rows');
    foreach(['before','lost_ack','throw_after']as$i=>$fault){
        $h=d2yHttp();$h->responses[$pp]['created']+=10+$i;$count=d2yCount();$wpdb->failOn='INSERT INTO `'.$pt.'`';$wpdb->failure=$fault;
        if($fault==='before'){fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_UNAVAILABLE');check(d2yCount()===$count,'failed write not success');}
        else{$r=d2yRecord($h);check(d2yCount()===$count+1,'lost write reply resolved by content readback');}
        $wpdb->failOn='';$wpdb->failure='';
    }
    foreach(['missing','corrupt']as$i=>$fault){
        $h=d2yHttp();$h->responses[$pp]['created']+=30+$i;$wpdb->db->close();$wpdb=new D2yFaultDb((int)$port);$wpdb->fault=$fault;
        fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_UNAVAILABLE');check($wpdb->inserts===1,'bad readback no success/reinsert');
        $wpdb->fault='';$r=d2yRecord($h,$now+60);check($wpdb->inserts===1&&$r['observed_at_utc']===gmdate('Y-m-d H:i:s',$now),'later read recovers exact first insert');
    }
    $wpdb->db->close();$wpdb=new OrderDb((int)$port);
    $h=d2yHttp();$h->responses[$pp]['created']+=100;$before=d2yCount();
    $race=race(static fn($slot)=>d2yRecord($h), 'payout_id =');
    check(isset($race[0]['value'],$race[1]['value'])&&$race[0]['value']===$race[1]['value']&&d2yCount()===$before+1,'two connections same proof one row');
    $before=d2yCount();$h->responses[$pp]['created']+=100;
    $race=race(static function($slot)use($h,$pp){$h->responses[$pp]['created']+=$slot;return d2yRecord($h);},'payout_id =');
    check(isset($race[0]['value'],$race[1]['value'])&&$race[0]['value']['digest']!==$race[1]['value']['digest']&&d2yCount()===$before+2,'two connections distinct observations preserved');
    // Exact order origin is created through the existing observation + movement paths.
    [$o,$data,$observation]=movementFixture('payout','txn_D2YORDER');
    $registered=sharity_donation_finance_record_movements($o['order_id'],movementProposal($o),$now);
    $f=sharity_donation_finance_reconcile($o['order_id'],$now)['movements'][0]['facts'];$beforeOrder=sharity_donation_webhook_order($o['order_id']);
    $h=d2yHttp();$gross=(int)$f['gross_minor'];$fee=(int)$f['total_fee_minor'];$net=(int)$f['net_minor'];
    $h->responses[$pp]['created']+=300;$h->responses[$pp]['amount']=$net;
    $h->responses[$bp]=d2yBalance('txn_PAYOUT','po_SYNTHETIC',-$net,0,'payout');
    $h->pages['']['data']=[d2yBalance('txn_D2YORDER',$f['source_id'],$gross,$fee,'charge')];
    $matched=d2yRecord($h);$report=d2yReport($matched);
    check($report['movement_matching_complete']&&count($report['matched_orders'])===1,'verified original order mapped');
    check($report['matched_orders'][0]['order_id']===$o['order_id']&&$report['matched_orders'][0]['batch_contribution_totals']['HUF']['net_minor']===(string)$net,'native contribution not claimed final payout');
    check($report['matched_orders'][0]['payout_allocation_minor']===null,'batch fee never apportioned arbitrarily');
    $h->pages['']['data'][]=$h->responses[$bp];$r=d2yRecord($h);check(d2yReport($r)['movement_matching_complete'],'own payout row is not unknown funding');
    $mt=sharity_donation_movement_table();$stored=$wpdb->db->query("SELECT * FROM `{$mt}` WHERE balance_transaction_id='txn_D2YORDER'")->fetch_assoc();
    foreach(['source_id'=>'ch_WRONG','currency'=>'EUR','order_id'=>'do_'.str_repeat('f',32)]as$key=>$value){
        $bad=$f;$bad[$key]=$value;$json=sharity_donation_finance_json($bad);
        $wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json=%s,financial_digest=%s WHERE balance_transaction_id='txn_D2YORDER'",$json,hash('sha256',$json)));
        fails(fn()=>d2yReport($matched),'DONATION_PAYOUT_CONFLICT');
    }
    $wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET facts_json=%s,financial_digest=%s WHERE balance_transaction_id='txn_D2YORDER'",$stored['facts_json'],$stored['financial_digest']));
    $wpdb->db->query("UPDATE `{$mt}` SET financial_digest=REPEAT('0',64) WHERE balance_transaction_id='txn_D2YORDER'");
    fails(fn()=>d2yReport($matched),'DONATION_PAYOUT_CONFLICT');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$mt}` SET financial_digest=%s,account_id='acct_FOREIGN' WHERE balance_transaction_id='txn_D2YORDER'",$stored['financial_digest']));
    check(d2yReport($matched)['matched_orders']===[],'same transaction in foreign account never matches');
    $wpdb->db->query("UPDATE `{$mt}` SET account_id='acct_SYNTHETIC' WHERE balance_transaction_id='txn_D2YORDER'");
    $obs=sharity_donation_finance_table();$oldJson=$observation['canonical_json']??null;
    // Registry can be syntactically valid but cannot outrank missing original evidence.
    $savedObs=$wpdb->db->query($wpdb->prepare("SELECT * FROM `{$obs}` WHERE order_id=%s",$o['order_id']))->fetch_assoc();
    $wpdb->db->query($wpdb->prepare("DELETE FROM `{$obs}` WHERE order_id=%s",$o['order_id']));
    fails(fn()=>d2yReport($matched),'DONATION_PAYOUT_CONFLICT');
    $cols=array_keys($savedObs);$wpdb->db->query($wpdb->prepare("INSERT INTO `{$obs}` (`".implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'%s')).')',...array_values($savedObs)));
    check(d2yReport($matched)['movement_matching_complete'],'original evidence restored only in fixture');
    $cli=['payout'=>'po_SYNTHETIC','account'=>'acct_SYNTHETIC','mode'=>'test','scope'=>'connected'];
    sharity_donation_cli_payout_record([],$cli,$h);$rr=json_decode(end(WP_CLI::$lines),true);check($rr['digest']===$r['digest'],'actual record CLI same observation');
    sharity_donation_cli_payout_reconcile([],['payout'=>'po_SYNTHETIC','account'=>'acct_SYNTHETIC','mode'=>'test','digest'=>$r['digest']]);
    check(json_decode(end(WP_CLI::$lines),true)['movement_matching_complete'],'actual read-only CLI');
    foreach([[[],[]],[[],['mode'=>'bad']],[['extra'],$cli],[[],$cli+['extra'=>true]]]as[$a,$b]){fails(fn()=>sharity_donation_cli_payout_record($a,$b,$h),'DONATION_PAYOUT_UNAVAILABLE');}
    foreach(['START TRANSACTION','SET autocommit=0']as$sql){
        $wpdb->db->query($sql);$gets=count($h->calls);
        fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_UNAVAILABLE');fails(fn()=>d2yReport($r),'DONATION_PAYOUT_UNAVAILABLE');
        check(count($h->calls)===$gets,'caller transaction never reaches HTTP');$wpdb->db->rollback();$wpdb->db->query('SET autocommit=1');
    }
    check(sharity_donation_webhook_order($o['order_id'])===$beforeOrder,'order untouched by every report/record');
    $h=d2yHttp();$h->responses[$pp]['method']='instant';$manual=d2yRecord($h);$r=d2yReport($manual);
    check(!$r['membership_complete']&&!$r['movement_matching_complete']&&$r['matched_orders']===[],'instant no invented items');
    $h=d2yHttp();$h->responses[$pp]['status']='failed';$h->responses[$pp]['failure_balance_transaction']='txn_FAILURE';
    $h->responses['/v1/balance_transactions/txn_FAILURE']=d2yBalance('txn_FAILURE','po_SYNTHETIC',10000,0,'payout_failure');
    $r=d2yReport(d2yRecord($h));check(!$r['provider_paid_observed']&&$r['failure_balance']['gross_minor']==='10000','failure return stored distinct');
    $h->pages['']['data'][]=$h->responses['/v1/balance_transactions/txn_FAILURE'];
    $failureReceipt=d2yRecord($h);check(d2yReport($failureReceipt)['failure_balance']['id']==='txn_FAILURE','matching failure item survives in separate proof');
    $h->pages['']['data'][3]['source']='po_FOREIGN';
    fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_UNAVAILABLE');

    $h=d2yHttp();$h->responses[$pp]['amount']=-10000;$h->responses[$pp]['original_payout']='po_OLD';$h->responses[$bp]=d2yBalance('txn_PAYOUT','po_SYNTHETIC',10000,0,'payout');
    $r=d2yReport(d2yRecord($h));check($r['payout']['amount_minor']==='-10000'&&in_array('payout_reversal_requires_reconciliation',$r['unresolved'],true),'negative reversal persists');
    $h=d2yHttp('eur');$h->responses[$pp]['currency']='huf';$h->responses[$pp]['amount']=4000000;$h->responses[$bp]['exchange_rate']=0.0025;
    $r=d2yReport(d2yRecord($h));check($r['payout']['currency']==='HUF'&&$r['payout_balance']['currency']==='EUR','native FX sides not recomputed');
    $before=d2yCount();$packet=(int)$wpdb->db->query('SELECT @@max_allowed_packet AS n')->fetch_assoc()['n'];
    try{$wpdb->db->query('SET GLOBAL max_allowed_packet=1024');$wpdb->db->close();$wpdb=new OrderDb((int)$port);
        $h=d2yHttp();$h->responses[$pp]['created']+=500;fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_PACKET_TOO_SMALL');check(d2yCount()===$before,'small actual packet rejects before INSERT');
    }finally{$wpdb->db->query('SET GLOBAL max_allowed_packet='.$packet);$wpdb->db->close();$wpdb=new OrderDb((int)$port);}
    // Real SDK 1000-item/100-fee batch exceeds canonical4MiB without raw payload padding.
    $h=d2yHttp();$h->pages=[];$cursor='';
    for($page=0;$page<10;$page++){$rows=[];for($j=0;$j<100;$j++){$tid='txn_BIG'.($page*100+$j);$b=d2yBalance($tid,null,200,100,'adjustment');
        $b['fee_details']=array_fill(0,100,['amount'=>1,'currency'=>'huf','type'=>'stripe_fee']);$rows[]=$b;}
        $h->pages[$cursor]=['object'=>'list','data'=>$rows,'has_more'=>$page<9];$cursor=$tid;}
    fails(fn()=>d2yRecord($h),'DONATION_PAYOUT_EVIDENCE_TOO_LARGE');check(d2yCount()===$before,'oversize no truncation/insert');
    foreach(['SHOW TABLE STATUS','SHOW FULL COLUMNS','SHOW INDEX']as$sql){$wpdb->failOn=$sql;$wpdb->failure='before';
        check(!sharity_donation_payout_schema_apply()&&get_option(SHARITY_DONATION_PAYOUT_SCHEMA_OPTION)==='0','schema read fault disables');$wpdb->failOn='';$wpdb->failure='';check(sharity_donation_payout_schema_apply(),'schema restored');}
    foreach([["ENGINE=MyISAM","ENGINE=InnoDB"],["DROP INDEX account_payout_digest, ADD KEY account_payout_digest(account_id,livemode,payout_id,digest)","DROP INDEX account_payout_digest, ADD UNIQUE KEY account_payout_digest(account_id,livemode,payout_id,digest)"]]as[$bad,$restore]){
        $n=d2yCount();$wpdb->db->query("ALTER TABLE `{$pt}` {$bad}");check(!sharity_donation_payout_schema_apply()&&d2yCount()===$n,'incompatible schema rejected without data loss');
        $wpdb->db->query("ALTER TABLE `{$pt}` {$restore}");check(sharity_donation_payout_schema_apply(),'fixture schema restore');
    }
    check(calls()===$posts+1,'only explicit synthetic order fixture created one offline Checkout; payout operations no POST');
    echo 'Donation payout ledger MariaDB: '.($checks-$payoutStart)." checks; two real connections, offline SDK, no payout/bank acceptance\n";
}finally{$wpdb->failOn='';$wpdb->failure='';$wpdb->db->rollback();$wpdb->db->query('SET autocommit=1');$wpdb->db->query("DROP TABLE IF EXISTS `{$pt}`");unset($options[SHARITY_DONATION_PAYOUT_SCHEMA_OPTION]);Stripe\ApiRequestor::setHttpClient($transport);}
