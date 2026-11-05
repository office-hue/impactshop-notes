<?php
/** Native disposable DB + signed synthetic Connect events. Included by orders harness. */
declare(strict_types=1);
$accountEventStart = $checks; $priorOptions = $options; $priorAdapter = $adapter;
$inbox = sharity_donation_webhook_table();
$wpdb->db->query("CREATE TABLE `{$wpdb->options}` (option_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    option_name varchar(191) NOT NULL UNIQUE,option_value longtext NOT NULL,autoload varchar(20) NOT NULL) ENGINE=InnoDB");
function eventMap(): array {
    global $wpdb;
    $row=$wpdb->db->query("SELECT option_value FROM `{$wpdb->options}` WHERE option_name='sharity_donation_accounts_v1'")->fetch_assoc();
    return $row ? unserialize($row['option_value'],['allowed_classes'=>false]) : [];
}
function eventSeed(array $map): void {
    global $wpdb,$options;
    $s=$wpdb->db->prepare("INSERT INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)");
    $s->execute(['sharity_donation_accounts_v1',serialize($map),'no']);$s->close();$options['sharity_donation_accounts_v1']=$map;
}
function wp_cache_delete($key,$group): void {
    if($key==='sharity_donation_accounts_v1')$GLOBALS['options'][$key]=eventMap();
}
function accountNoticeEvent(string $type='account.updated',string $account='acct_EVENT'): array {
    return ['id'=>'evt_'.bin2hex(random_bytes(10)),'object'=>'event','type'=>$type,'created'=>time(),
        'api_version'=>Sharity_Stripe_Checkout_Adapter::API_VERSION,'livemode'=>false,'account'=>$account,
        'data'=>['object'=>$type==='account.updated' ? ['object'=>'account','id'=>$account,'email'=>'PRIVATE_EMAIL','external_accounts'=>['PRIVATE_BANK']]
            : ['object'=>'application','id'=>'ca_INDEPENDENTAPP','name'=>'PRIVATE_NAME']]];
}
function eventBinding(int $started): array {
    global $binding;
    return array_replace($binding,['account_id'=>'acct_EVENT','binding_version'=>'synthetic-before',
        'authority_verified_started_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$started)]);
}
class EventNativeDb extends mysqli {
    public string $fault='', $barrier='', $needle=''; public int $slot=0; public bool $committed=false;
    public function prepare(string $sql):mysqli_stmt|false {
        if($this->barrier!=='' && str_contains($sql,$this->needle)) {
            $dir=$this->barrier;$this->barrier='';file_put_contents($dir.'/ready-'.$this->slot,'1');$end=microtime(true)+5;
            while(!is_file($dir.'/ready-'.(1-$this->slot))){if(microtime(true)>$end)throw new RuntimeException('event race timeout');usleep(1000);}
        }
        if($this->fault==='insert' && str_starts_with($sql,'INSERT INTO `'.sharity_donation_webhook_table()))throw new RuntimeException('synthetic insert loss');
        if($this->fault==='readback' && $this->committed && str_starts_with($sql,'SELECT * FROM `'.sharity_donation_webhook_table()))throw new RuntimeException('synthetic readback loss');
        return parent::prepare($sql);
    }
    public function commit(int $flags=0,?string $name=null):bool {
        if(in_array($this->fault,['before','rollback-false','rollback-throw','rollback-noop'],true))throw new RuntimeException('synthetic before commit');
        $v=parent::commit($flags,$name);$this->committed=true;
        if($this->fault==='after')throw new RuntimeException('synthetic lost commit ACK');return$v;
    }
    public function rollback(int $flags=0,?string $name=null):bool {
        if($this->fault==='rollback-false')return false;
        if($this->fault==='rollback-throw')throw new RuntimeException('synthetic rollback failure');
        if($this->fault==='rollback-noop')return true;
        return parent::rollback($flags,$name);
    }
}
function eventConnection(string $fault=''):EventNativeDb {
    global $wpdb,$port;$wpdb->db->close();$db=new EventNativeDb('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);
    $db->set_charset('utf8mb4');$db->fault=$fault;$wpdb->db=$db;return$db;
}
try {
    $rowsBefore=$wpdb->db->query("SELECT * FROM `$inbox` ORDER BY event_id")->fetch_all(MYSQLI_ASSOC);
    $ddl=$wpdb->ddls;
    foreach([[],['apply'=>false],['apply'=>'yes'],['apply'=>true,'other'=>true]]as$args) {
        fails(fn()=>sharity_donation_cli_webhook_account_schema([],$args),'DONATION_WEBHOOK_ACCOUNT_SCHEMA_UNAVAILABLE');
    }
    check($wpdb->ddls===$ddl,'account CLI no explicit apply causes zero DDL');
    check(deliver(accountNoticeEvent())->status===503,'uninstalled account schema does not ACK new event');
    check(sharity_donation_webhook_account_schema_apply() && sharity_donation_webhook_schema_shape()===3,'explicit exact account schema installed');
    check(sharity_donation_webhook_account_schema_apply(),'compatible schema replay');
    $rowsAfter=$wpdb->db->query("SELECT * FROM `$inbox` ORDER BY event_id")->fetch_all(MYSQLI_ASSOC);
    foreach($rowsAfter as &$r){check(array_key_exists('account_key',$r)&&$r['account_key']===null,'old row gains null account discriminator');unset($r['account_key']);}unset($r);
    check($rowsBefore===$rowsAfter,'every previous inbox field/row unchanged by migration');
    $b=eventBinding(time()-30);eventSeed(['example-ngo'=>$b,'other-ngo'=>array_replace($b,['account_id'=>'acct_OTHER']),
        'also-ngo'=>$b,'live-ngo'=>array_replace($b,['livemode'=>true])]);
    $options[SHARITY_DONATION_ORDERS_ENABLED]='1';
    $pendingQuote=fixtureQuote(null,array_replace($snapshot,['ngo_slug'=>'example-ngo','account_id'=>'acct_EVENT','binding_version'=>$b['binding_version']]));
    $pending=sharity_donation_order_prepare($subject,$pendingQuote,'account-event-'.$nonce,'hu',time());
    check($pending['state']==='prepared','valid authority prepares owner order before event');
    $e=accountNoticeEvent();$before=inboxCount();
    foreach([['account','acct_OTHER'],['account',null],['livemode',true],['api_version','2020-08-27'],
        ['data.object.id','acct_OTHER'],['data.object.object','application'],['created',time()+301]] as [$path,$value]) {
        $bad=$e;$ref=&$bad;foreach(explode('.',$path)as$k)$ref=&$ref[$k];$ref=$value;unset($ref);
        check(deliver($bad)->status===400,'account invalid context '.$path);
    }
    check(deliver($e,null,'bad-signature')->status===400 && inboxCount()===$before,'bad signature/context no persistence');
    $posts=calls();check(deliver($e)->status===200,'signed account update committed');$map=eventMap();
    foreach(['example-ngo','also-ngo']as$ngo)check($map[$ngo]['verified_until']==='1970-01-01T00:00:00Z','every matching NGO expired');
    check($map['other-ngo']['verified_until']===$b['verified_until']&&$map['live-ngo']['verified_until']===$b['verified_until'],'other NGO/livemode preserved');
    $saved=sharity_donation_webhook_read($e['id']);
    check($saved['order_id']===null&&$saved['session_id']===null&&$saved['observation_digest']===null,'account receipt is not financial order');
    check(!str_contains($saved['identity_json'],'PRIVATE'),'no raw account/customer/bank data');
    check(deliver($e)->status===200 && eventMap()===$map,'duplicate exact no expiry rerun');
    $bad=$e;$bad['created']--;check(deliver($bad)->status===409,'event id conflicting minimal identity');
    check(sharity_donation_quote_binding(['ngo_slug'=>'example-ngo','donation_mode'=>'direct','currency'=>'HUF'],time())===null,'new quote denied by existing reader');
    fails(fn()=>sharity_donation_order_dispatch($subject,$pending['order_id'],$adapter,time()),'NGO_UNAVAILABLE');
    check(sharity_donation_order_by_quote($subject,$pendingQuote,time())['state']==='prepared' && calls()===$posts,
        'account invalidation stops prepared dispatch without hiding owner status or provider POST');
    // A new fresh verified bind observes the current barrier; a duplicate cannot undo it.
    $fresh=eventBinding($e['created']+1);$epoch=$map['_account_events'][sharity_donation_account_key('acct_EVENT',false)]['digest'];
    $request=['kind'=>'bind','proof'=>['synthetic'=>'fresh'],'authority_epoch'=>$epoch];
    $fresh=sharity_donation_account_change('example-ngo',$map['example-ngo']['binding_version'],$request,$fresh);
    check(deliver($e)->status===200 && eventMap()['example-ngo']===$fresh,'replay preserves later explicit verified binding');
    $late=accountNoticeEvent();$late['created']=$e['created']-10;
    check(deliver($late)->status===200 && eventMap()['example-ngo']===$fresh,'newly received older event preserves fresh verification');
    $deauth=accountNoticeEvent('account.application.deauthorized');$deauth['created']=$freshStart=sharity_donation_quote_timestamp($fresh['authority_verified_started_at_utc']);
    check(deliver($deauth)->status===200,'independent ca application id accepted without account derivation');
    check(eventMap()['example-ngo']['verified_until']==='1970-01-01T00:00:00Z','equal-second change conservatively expires');
    // Unknown account event must survive; verification started before it cannot bind.
    $unknown=accountNoticeEvent('account.updated','acct_NEW');check(deliver($unknown)->status===200,'unbound account event recorded');
    $new=array_replace(eventBinding($unknown['created']-1),['account_id'=>'acct_NEW']);
    fails(fn()=>sharity_donation_account_change('new-ngo','absent',['kind'=>'bind','authority_epoch'=>null],$new),'DONATION_ACCOUNT_STALE');
    $epoch=eventMap()['_account_events'][sharity_donation_account_key('acct_NEW',false)]['digest'];
    fails(fn()=>sharity_donation_account_change('new-ngo','absent',['kind'=>'bind','authority_epoch'=>$epoch],$new),'DONATION_ACCOUNT_STALE');
    $new['authority_verified_started_at_utc']=gmdate('Y-m-d\TH:i:s\Z',$unknown['created']+1);
    check(sharity_donation_account_change('new-ngo','absent',['kind'=>'bind','authority_epoch'=>$epoch],$new)['account_id']==='acct_NEW','fresh verification after unbound event allowed');
    foreach(['insert','before','after','readback','rollback-false','rollback-throw','rollback-noop']as$fault) {
        eventSeed(['example-ngo'=>$b]);$db=eventConnection($fault);$notice=accountNoticeEvent();$reply=deliver($notice);
        check($reply->status===($fault==='after'?200:503),'native event fault result '.$fault);
        eventConnection();$r=sharity_donation_webhook_read($notice['id']);$m=eventMap();
        check(($r!==null)===in_array($fault,['after','readback'],true),'receipt reflects actual commit '.$fault);
        check(($m['example-ngo']['verified_until']==='1970-01-01T00:00:00Z')===($r!==null),'receipt and expiry atomic '.$fault);
        check(deliver($notice)->status===200,'explicit delivery recovers unresolved operation '.$fault);
    }
    // Stale flags cannot admit nontransactional tables or missing identity uniqueness.
    foreach([$wpdb->options,$inbox]as$target) {
        eventSeed(['example-ngo'=>$b]);$notice=accountNoticeEvent();
        $wpdb->db->query("ALTER TABLE `$target` ENGINE=MyISAM");
        check(deliver($notice)->status===503,'nontransactional table rejected '.$target);
        check(eventMap()===['example-ngo'=>$b] && sharity_donation_webhook_read($notice['id'])===null,'bad engine causes zero partial writes');
        $wpdb->db->query("ALTER TABLE `$target` ENGINE=InnoDB");
    }
    $wpdb->db->query("ALTER TABLE `{$wpdb->options}` DROP INDEX option_name");
    $notice=accountNoticeEvent();check(deliver($notice)->status===503,'missing option uniqueness rejected');
    check(eventMap()===['example-ngo'=>$b] && sharity_donation_webhook_read($notice['id'])===null,'missing unique key causes zero writes');
    $wpdb->db->query("ALTER TABLE `{$wpdb->options}` ADD UNIQUE KEY option_name (option_name)");
    // Two independent native connections race the event transaction and a bind CAS.
    eventSeed(['example-ngo'=>$b]);$notice=accountNoticeEvent();$next=eventBinding($notice['created']);
    $dir=sys_get_temp_dir().'/account-event-race-'.bin2hex(random_bytes(5));mkdir($dir,0700);
    try {
        $out=race(static function(int $slot)use($dir,$notice,$b,$next):array {
            $db=eventConnection();$db->barrier=$dir;$db->slot=$slot;$db->needle=$slot?'FOR UPDATE':'UPDATE `';
            if($slot){$r=deliver($notice);return['status'=>$r->status];}
            return sharity_donation_account_change('example-ngo',$b['binding_version'],['kind'=>'bind','authority_epoch'=>null,'proof'=>'race'],$next);
        });
        check(count($out)===2,'native event/bind racers completed');
        // A deadlock/victim may be retryable, never false-ACK; explicit redelivery settles it.
        check(deliver($notice)->status===200,'event confirmed after native race');
        $options['sharity_donation_accounts_v1']=eventMap();
        check(sharity_donation_quote_binding(['ngo_slug'=>'example-ngo','donation_mode'=>'direct','currency'=>'HUF'],time())===null,'concurrent stale binding is unusable');
    } finally {foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
    $count=inboxCount();$selected=$wpdb->db->query("SELECT DISTINCT order_id FROM `$inbox` WHERE observation_digest IS NULL AND order_id > ''")->fetch_all(MYSQLI_ASSOC);
    foreach($selected as$r)check(preg_match('/^do_[a-f0-9]{32}$/D',$r['order_id'])===1,'account record excluded by existing order-only drain selection');
    check(calls()===$posts,'account notification, replay, recovery and native race create no provider POST');
    $wpdb->db->query("ALTER TABLE `$inbox` MODIFY account_key char(65) CHARACTER SET ascii COLLATE ascii_bin NULL");
    check(deliver(accountNoticeEvent())->status===503 && inboxCount()===$count,'stale schema flag cannot admit malformed account column');
    check(!sharity_donation_webhook_account_schema_apply()&&inboxCount()===$count,'unknown schema rejected preserving data');
    $wpdb->db->query("ALTER TABLE `$inbox` MODIFY account_key char(64) CHARACTER SET ascii COLLATE ascii_bin NULL");
    check(sharity_donation_webhook_account_schema_apply(),'restored fixture schema verifies');
    echo 'Account event MariaDB: '.($checks-$accountEventStart)." checks; signed events, native bind race, atomic lost-ACK recovery\n";
} finally {
    $wpdb->db->query('DROP TABLE IF EXISTS `'.$wpdb->options.'`');$options=$priorOptions;$adapter=$priorAdapter;
}
