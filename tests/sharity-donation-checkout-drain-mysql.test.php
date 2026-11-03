<?php
/** Actual disposable MariaDB and pinned SDK with offline HTTP only. */
declare(strict_types=1);
$drainStart=$checks; $inbox=sharity_donation_webhook_table(); $originalOptions=$options;
$wpdb->options=$wpdb->prefix.'options';
$wpdb->db->query("CREATE TABLE `{$wpdb->options}` (option_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL UNIQUE, option_value longtext NOT NULL, autoload varchar(20) NOT NULL) ENGINE=InnoDB");
// Earlier negative webhook test deliberately removed this index; restore only fixture.
$wpdb->db->query("ALTER TABLE `{$inbox}` ADD KEY order_id(order_id)");
$beforeRows=inboxCount();$ddl=$wpdb->ddls;
foreach([[[],[]],[[],['apply'=>false]],[[],['apply'=>'true']],[[],['apply'=>true,'extra'=>true]],[['apply'],['apply'=>true]]]as[$args,$assoc]){
    fails(fn()=>sharity_donation_cli_webhook_finance_schema($args,$assoc),'DONATION_WEBHOOK_FINANCE_SCHEMA_UNAVAILABLE');
    check($wpdb->ddls===$ddl,'invalid migration args perform no DDL');
}
sharity_donation_cli_webhook_finance_schema([],['apply'=>true]);
check(sharity_donation_webhook_schema_shape()===2 && inboxCount()===$beforeRows,'additive upgrade preserves existing inbox');
check(sharity_donation_webhook_finance_schema_apply()&&sharity_donation_webhook_schema_apply(),'new and old schema commands accept upgraded shape');
$wpdb->db->query("ALTER TABLE `{$inbox}` MODIFY identity_digest varchar(65) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");
check(!sharity_donation_webhook_finance_schema_apply()&&inboxCount()===$beforeRows,'bad legacy shape rejected without data loss');
$wpdb->db->query("ALTER TABLE `{$inbox}` MODIFY identity_digest char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");
check(sharity_donation_webhook_finance_schema_apply(),'fixture repair accepted');
$wpdb->db->begin_transaction();
check(!sharity_donation_webhook_finance_schema_apply(),'migration cannot commit caller transaction');
check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='1','caller transaction remains');$wpdb->db->rollback();
// Real native backing tables were removed by the preceding negative suite.
check(sharity_donation_backing_schema_apply(),'restore only isolated backing schema');
class DrainHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses=[];public int $gets=0;public bool $drop=false;public $onRequest=null;
    public function request($method,$url,$headers,$params,$hasFile,$apiMode='v1',$maxNetworkRetries=null){
        global $wpdb;
        check($method==='get'&&$hasFile===false&&$maxNetworkRetries===0,'drain only GET, no retry/upload');
        check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='0','HTTP outside transaction');
        $this->gets++;
        if($this->drop){throw new RuntimeException('PRIVATE_PROVIDER_ERROR');}
        if($this->onRequest!==null){($this->onRequest)();}
        $path=parse_url($url,PHP_URL_PATH);
        if($path==='/v1/refunds'||$path==='/v1/disputes'){return [json_encode(['object'=>'list','data'=>[],'has_more'=>false]),200,[]];}
        if(!isset($this->responses[$path])){throw new RuntimeException('unexpected synthetic path');}
        return [json_encode($this->responses[$path]),200,[]];
    }
}
$drainHttp=new DrainHttp();
function drainReset():void {global $wpdb,$inbox,$drainHttp;$wpdb->db->query("DELETE FROM `{$inbox}`");$wpdb->db->query("DELETE FROM `{$wpdb->options}` WHERE option_name='".SHARITY_DONATION_DRAIN_CURSOR_OPTION."'");$drainHttp->responses=[];$drainHttp->drop=false;$drainHttp->onRequest=null;}
function drainOrder(string $tag,bool $pending=false,bool $attempted=false):array {
    global $transport,$financeHttp,$drainHttp,$options,$binding,$adapter,$subject,$now;
    $options[SHARITY_DONATION_ORDERS_ENABLED]='1';$options['sharity_donation_accounts_v1']=['test-ngo'=>$binding];
    Stripe\ApiRequestor::setHttpClient($transport);
    if($attempted){$o=pendingOrder($tag);}else{$o=newOrder($tag);sharity_donation_order_dispatch($subject,$o['order_id'],$adapter,$now);$o=sharity_donation_webhook_order($o['order_id']);}
    $event=checkoutEvent($o);check(deliver($event)->status===200,'signed drain event received');
    $virtual=$o;$virtual['provider_session_id']=$event['data']['object']['id'];$financeHttp->forOrder($virtual);
    $suffix=substr($o['order_id'],3);
    foreach($financeHttp->responses as $path=>$data){
        $json=str_replace(['pi_FINANCE','ch_FINANCE','txn_FINANCE'],['pi_'.$suffix,'ch_'.$suffix,'txn_'.$suffix],json_encode($data));
        $path=str_replace(['ch_FINANCE','txn_FINANCE'],['ch_'.$suffix,'txn_'.$suffix],$path);
        $drainHttp->responses[$path]=json_decode($json,true);
    }
    if($pending){$p='/v1/checkout/sessions/'.$virtual['provider_session_id'];$drainHttp->responses[$p]['payment_status']='unpaid';$drainHttp->responses[$p]['payment_intent']=null;}
    return[$o,$event];
}
try {
    drainReset();[$o,$event]=drainOrder('drain-success');$beforeOrder=$o;$posts=calls();$prior=Stripe\ApiRequestor::httpClient();
    $options[SHARITY_DONATION_ORDERS_ENABLED]='0';unset($options['sharity_donation_accounts_v1']);
    $r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['observed']===1&&$r['retryable']===0&&!$r['financial_readiness'],'direct pipeline observes without activation');
    check(sharity_donation_webhook_order($o['order_id'])===$beforeOrder&&calls()===$posts,'existing order unchanged, zero provider POST');
    check(Stripe\ApiRequestor::httpClient()===$prior,'prior SDK transport restored');
    $mark=sharity_donation_webhook_read($event['id']);check($mark['observation_digest']!==null,'completed event references persisted evidence');
    check(targetCount($o['order_id'])===1&&targetDry($o)['target']['provisional_split']['ngo_minor']==='48000','real registry/target receives exact NGO net');
    $gets=$drainHttp->gets;$r=sharity_donation_checkout_drain($now,$drainHttp);check($r['orders']===0&&$drainHttp->gets===$gets,'completed repeat does not call provider');
    check(sharity_donation_webhook_schema_apply(),'legacy schema command still usable');sharity_donation_webhook_recover($event['id'],$now);

    drainReset();[$pending,$pe]=drainOrder('drain-unpaid',true);$r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['pending']===1&&$r['observed']===0&&sharity_donation_webhook_read($pe['id'])['observation_digest']===null,'unpaid observation remains pending');
    check(targetCount($pending['order_id'])===0,'unpaid has no zero-money target');
    drainReset();[$lost,$le]=drainOrder('drain-recover',false,true);$posts=calls();$r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['observed']===1&&sharity_donation_webhook_order($lost['order_id'])['state']==='session_recorded'&&calls()===$posts,'attempted session recovered by GET only');

    drainReset();[$o,$ev]=drainOrder('drain-failure');$drainHttp->drop=true;$r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['retryable']>0&&sharity_donation_webhook_read($ev['id'])['observation_digest']===null,'provider failure retains pending event');
    check(Stripe\ApiRequestor::httpClient()===$transport,'failure restores original transport');$drainHttp->drop=false;
    sharity_donation_checkout_drain($now,$drainHttp);$r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['observed']===1,'cursor wraps and failed event can be processed later');

    // Updates commit before ACK is lost; completion is accepted only after actual readback.
    drainReset();[$o,$ev]=drainOrder('drain-ack');$wpdb->failOn='SET observation_digest =';$wpdb->failure='throw_after';
    $r=sharity_donation_checkout_drain($now,$drainHttp);$wpdb->failOn='';$wpdb->failure='';
    check($r['observed']===1&&sharity_donation_webhook_read($ev['id'])['observation_digest']!==null,'lost marker ACK readback');
    drainReset();[$o,$ev]=drainOrder('drain-updatefail');$wpdb->failOn='SET observation_digest =';$wpdb->failure='before';
    $r=sharity_donation_checkout_drain($now,$drainHttp);$wpdb->failOn='';$wpdb->failure='';
    check($r['observed']===0&&$r['retryable']===1&&sharity_donation_webhook_read($ev['id'])['observation_digest']===null,'failed marker never reported observed');

    drainReset();[$o,$ev]=drainOrder('drain-new-event');$late=checkoutEvent($o);$inserted=false;
    $drainHttp->onRequest=static function()use($late,&$inserted):void{if(!$inserted){$inserted=true;check(deliver($late)->status===200,'late event accepted during GET');}};
    $r=sharity_donation_checkout_drain($now,$drainHttp);$drainHttp->onRequest=null;
    check($r['observed']===1&&sharity_donation_webhook_read($late['id'])['observation_digest']===null,'late event not silently completed by earlier snapshot');

    drainReset();$many=[];for($i=0;$i<7;$i++){$many[]=drainOrder('drain-many-'.$i);}
    usort($many,static fn($a,$b)=>strcmp($a[0]['order_id'],$b[0]['order_id']));
    $poison=$many[0][1];$wpdb->query($wpdb->prepare("UPDATE `{$inbox}` SET identity_json='{}' WHERE event_id=%s",$poison['id']));
    $r=sharity_donation_checkout_drain($now,$drainHttp);check($r['orders']===5&&$r['observed']===4&&$r['retryable']>0,'five-order batch cap includes poison');
    $r=sharity_donation_checkout_drain($now,$drainHttp);check($r['orders']===2&&$r['observed']===2,'durable cursor advances beyond poison');
    check(sharity_donation_webhook_read($poison['id'])['observation_digest']===null,'poison evidence retained');
    sharity_donation_checkout_drain($now,$drainHttp);check(sharity_donation_webhook_cursor()==='','cursor wrap persisted');

    drainReset();[$o,$ev]=drainOrder('drain-events-bound');for($i=0;$i<8;$i++){check(deliver(checkoutEvent($o))->status===200,'extra signed event accepted');}
    $beforeGets=$drainHttp->gets;$r=sharity_donation_checkout_drain($now,$drainHttp);
    check($r['orders']===1&&$r['observed']===5&&$drainHttp->gets-$beforeGets===8,'one financial GET graph per order, five event rows');
    // A separate real DB connection wins a cursor write; stale caller cannot regress it.
    $current=sharity_donation_webhook_cursor();$next='do_'.str_repeat('f',32);$otherDb=new OrderDb((int)$port);
    $otherDb->query($otherDb->prepare("UPDATE `{$otherDb->options}` SET option_value=%s WHERE option_name=%s",$next,SHARITY_DONATION_DRAIN_CURSOR_OPTION));$otherDb->db->close();
    fails(fn()=>sharity_donation_webhook_cursor('',$current),'DONATION_DRAIN_CURSOR_CONFLICT');
    check(sharity_donation_webhook_cursor()===$next,'concurrent cursor preserved');

    // Five invalid earlier events of one order must not hide its sixth valid event.
    drainReset();[$o,$ev]=drainOrder('drain-same-order-poison');$wpdb->db->query("DELETE FROM `{$inbox}`");
    foreach(['A','B','C','D','E']as$letter){$bad=checkoutEvent($o);$bad['id']='evt_'.$letter;check(deliver($bad)->status===200,'same-order event accepted');$wpdb->query($wpdb->prepare("UPDATE `{$inbox}` SET identity_json='{}' WHERE event_id=%s",$bad['id']));}
    $good=checkoutEvent($o);$good['id']='evt_F';check(deliver($good)->status===200,'sixth valid event accepted');
    $r=sharity_donation_checkout_drain($now,$drainHttp);check($r['observed']===0&&$r['retryable']===5,'first five poison events remain pending');
    sharity_donation_checkout_drain($now+1,$drainHttp);$r=sharity_donation_checkout_drain($now+2,$drainHttp);
    check($r['observed']===1&&sharity_donation_webhook_read('evt_F')['observation_digest']!==null,'later same-order valid event progresses after cursor wrap');
    check(sharity_donation_webhook_read('evt_A')['observation_digest']===null,'poison event never falsely completed');

    // Corrupt marker readback must not be presented as successful processing.
    drainReset();[$o,$ev]=drainOrder('drain-marker-corruption');
    $originalDb=$wpdb;
    $wpdb=new class((int)$port) extends OrderDb {
        public function get_row(string $sql,$mode):?array{$row=parent::get_row($sql,$mode);if(str_contains($sql,'WHERE event_id =')&&($row['observed_at_utc']??null)!==null){$row['observed_at_utc']='0000-00-00 00:00:00';}return$row;}
    };
    try{$r=sharity_donation_checkout_drain($now,$drainHttp);check($r['observed']===0&&$r['retryable']===1,'corrupt observed timestamp refuses success');}
    finally{$wpdb->db->close();$wpdb=$originalDb;}

    // Actual two-connection competing drains; no duplicate registry/target or invented completion.
    drainReset();[$o,$ev]=drainOrder('drain-race');
    $raceResults=race(static function()use($now,$drainHttp){return sharity_donation_checkout_drain($now,$drainHttp);},'WHERE event_id =');
    check(targetCount($o['order_id'])===1&&sharity_donation_webhook_read($ev['id'])['observation_digest']!==null,'parallel drains one persisted target and evidence');
    foreach($raceResults as $rr){check(isset($rr['value'])||($rr['error']??null)==='DONATION_DRAIN_CURSOR_CONFLICT','parallel drain result explicit');}

    // Deterministic monotonic time and request ceilings, no sleeps or real provider calls.
    $time=100.0;$clock=static function()use(&$time):float{return $time;};$prior=Stripe\ApiRequestor::httpClient();
    $stub=new class implements Stripe\HttpClient\ClientInterface{public int $gets=0;public function request($m,$u,$h,$p,$f,$a='v1',$r=null){$this->gets++;return ['{}',200,[]];}};
    sharity_donation_webhook_bounded_read(static function()use($stub):void{$client=Stripe\ApiRequestor::httpClient();for($i=0;$i<63;$i++){$client->request('get','https://api.stripe.com/v1/account',[],[],false,'v1',0);}fails(fn()=>$client->request('get','https://api.stripe.com/v1/account',[],[],false,'v1',0),'DONATION_DRAIN_READ_LIMIT');},160,$stub,$clock);
    check($stub->gets===63&&Stripe\ApiRequestor::httpClient()===$prior,'63 request cap and restored prior');
    foreach([['post','https://api.stripe.com/v1/account',false,'v1',0],['get','https://evil.test/v1/account',false,'v1',0],['get','https://api.stripe.com/v1/account',true,'v1',0],['get','https://api.stripe.com/v1/account',false,'v1',1]]as[$m,$u,$f,$a,$retry]){
        fails(fn()=>sharity_donation_webhook_bounded_read(static fn()=>Stripe\ApiRequestor::httpClient()->request($m,$u,[],[],$f,$a,$retry),160,$stub,$clock),'DONATION_DRAIN_READ_LIMIT');
    }
    $curl=new class extends Stripe\HttpClient\CurlClient{public array $timeouts=[];public function request($m,$u,$h,$p,$f,$a='v1',$r=null){$this->timeouts[]=[$this->getTimeout(),$this->getConnectTimeout()];return ['{}',200,[]];}};
    sharity_donation_webhook_bounded_read(static fn()=>Stripe\ApiRequestor::httpClient()->request('get','https://api.stripe.com/v1/account',[],[],false,'v1',0),160,$curl,$clock);
    check($curl->timeouts===[[5,2]],'real SDK timeout properties bounded');$time=158.7;
    sharity_donation_webhook_bounded_read(static fn()=>Stripe\ApiRequestor::httpClient()->request('get','https://api.stripe.com/v1/account',[],[],false,'v1',0),160,$curl,$clock);
    check(end($curl->timeouts)===[1,1],'remaining deadline bounds both SDK timeouts');$time=159.5;
    fails(fn()=>sharity_donation_webhook_bounded_read(static fn()=>Stripe\ApiRequestor::httpClient()->request('get','https://api.stripe.com/v1/account',[],[],false,'v1',0),160,$stub,$clock),'DONATION_DRAIN_READ_LIMIT');
    check(Stripe\ApiRequestor::httpClient()===$prior,'deadline failure restores SDK prior');
    drainReset();[$o,$ev]=drainOrder('drain-deadline');$time=100.0;$drainHttp->onRequest=static function()use(&$time):void{$time+=61;};
    $r=sharity_donation_checkout_drain($now,$drainHttp,$clock);
    check($r['budget_exhausted']&&$r['observed']===0&&sharity_donation_webhook_read($ev['id'])['observation_digest']===null,'late reply never completes event');
    foreach([[['unexpected'],[]],[[],['apply'=>true]],[[],['limit'=>'6']]]as[$args,$assoc]){fails(fn()=>sharity_donation_cli_finance_drain($args,$assoc),'DONATION_DRAIN_UNAVAILABLE');}
    echo 'Donation checkout drain MariaDB: '.($checks-$drainStart)." checks; real races, signed events, bounded offline SDK, no money execution\n";
} finally {
    $wpdb->failOn='';$wpdb->failure='';$wpdb->db->query("DROP TABLE IF EXISTS `{$wpdb->options}`");$options=$originalOptions;
    Stripe\ApiRequestor::setHttpClient($transport);
}
