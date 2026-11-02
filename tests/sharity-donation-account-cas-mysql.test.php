<?php
/** Included by account-binding test. Only disposable loopback donation_test. */
declare(strict_types=1);
$port=getenv('SHARITY_DONATION_TEST_PORT');
if(!is_string($port)||!preg_match('/^[1-9][0-9]{3,4}$/D',$port)||(int)$port>65535||!function_exists('pcntl_fork'))throw new RuntimeException('isolated DB/pcntl required');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
class AccountStmt extends mysqli_stmt {
 public function __construct(public AccountDb $owner,public string $sql){parent::__construct($owner,$sql);}
 public function execute(?array $params=null):bool{
  $write=str_starts_with($this->sql,'INSERT INTO')||str_starts_with($this->sql,'UPDATE ');
  if($write&&$this->owner->barrier!==''){
   $dir=$this->owner->barrier;$this->owner->barrier='';file_put_contents($dir.'/ready-'.$this->owner->slot,'1');$until=microtime(true)+5;
   while(!is_file($dir.'/ready-'.(1-$this->owner->slot))){if(microtime(true)>$until)throw new RuntimeException('barrier timeout');usleep(1000);}
  }
  if($write&&$this->owner->fault==='before')throw new RuntimeException('synthetic write failure');
  $ok=parent::execute($params);
  if($write){$this->owner->written=true;if($this->owner->fault==='after')throw new RuntimeException('synthetic lost ACK');}
  return$ok;
 }
}
class AccountDb extends mysqli {
 public string $fault='', $barrier='';public int $slot=0;public bool $written=false;
 public function prepare(string $sql):mysqli_stmt|false{
  if($this->fault==='corrupt'&&$this->written&&str_starts_with($sql,'SELECT option_value'))$sql=str_replace('SELECT option_value',"SELECT 'a:0:{}' AS option_value",$sql);
  return new AccountStmt($this,$sql);
 }
}
function accountConnection():AccountDb{global $port;$db=new AccountDb('127.0.0.1','root','isolated-donation-test-only','donation_test',(int)$port);$db->set_charset('utf8mb4');return$db;}
$table='d2o_'.bin2hex(random_bytes(6)).'_options';$wpdb=(object)['dbh'=>accountConnection(),'options'=>$table];
$wpdb->dbh->query("CREATE TABLE `$table` (option_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,option_name varchar(191) NOT NULL UNIQUE,option_value longtext NOT NULL,autoload varchar(20) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$proofPath=tempnam(sys_get_temp_dir(),'sharity-account-proof-');chmod($proofPath,0600);
function accountWriteProof(array $d):string{global $proofPath;file_put_contents($proofPath,sharity_donation_finance_json($d));chmod($proofPath,0600);return$proofPath;}
function accountMap():array{global $wpdb;$r=$wpdb->dbh->query("SELECT option_value FROM `{$wpdb->options}` WHERE option_name='sharity_donation_accounts_v1'")->fetch_assoc();return$r?unserialize($r['option_value'],['allowed_classes'=>false]):[];}
function accountSeed(array $map):void{global $wpdb;$s=$wpdb->dbh->prepare("INSERT INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)");$s->execute(['sharity_donation_accounts_v1',serialize($map),'no']);$s->close();}
function accountRace(callable $call):array{global $wpdb;
 $dir=sys_get_temp_dir().'/sharity-account-race-'.bin2hex(random_bytes(6));mkdir($dir,0700);$pids=[];
 $wpdb->dbh->close();
 try{
  for($i=0;$i<2;$i++){$pid=pcntl_fork();if($pid===-1)throw new RuntimeException('fork failed');if($pid===0){$wpdb->dbh=accountConnection();$wpdb->dbh->barrier=$dir;$wpdb->dbh->slot=$i;try{$r=['value'=>$call($i)];}catch(Throwable $e){$r=['error'=>$e->getMessage()];}file_put_contents($dir.'/result-'.$i,json_encode($r));$wpdb->dbh->close();exit(0);}$pids[]=$pid;}
  foreach($pids as$pid){pcntl_waitpid($pid,$status);check(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'native race child exit');}
  return [json_decode(file_get_contents($dir.'/result-0'),true),json_decode(file_get_contents($dir.'/result-1'),true)];
 }finally{$wpdb->dbh=accountConnection();foreach(scandir($dir)as$f)if($f!=='.'&&$f!=='..')unlink($dir.'/'.$f);rmdir($dir);}
}
try{
 $beforeCalls=count($http->calls);$d=accountProof();accountWriteProof($d);
 // Fresh clock after GET permits ordinary multi-second network latency.
 $http->afterGet=static function()use(&$now):void{$now+=2;};
 $first=sharity_donation_account_bind('example-ngo',$proofPath,'absent',$adapter,static fn():int=>$GLOBALS['now']);$http->afterGet=null;
 check($first['account_id']==='acct_NGO'&&accountMap()['example-ngo']===$first,'first bind persisted serialized WordPress array');
 check(count($http->calls)===$beforeCalls+2,'binding two actual GETs only');
 check(sharity_donation_account_bind('example-ngo',$proofPath,'absent',$adapter,static fn():int=>$GLOBALS['now'])===$first,'replay retains original provider observation and binding');
 $GLOBALS['optionFixture']['sharity_donation_accounts_v1']=accountMap();
 check(sharity_donation_quote_binding(['ngo_slug'=>'example-ngo','donation_mode'=>'direct','currency'=>'HUF'],$now)!==null,'actual existing quote binding accepts map');
 $other=$first;$other['account_id']='acct_OTHER';accountSeed(accountMap()+['other-ngo'=>$other]);
 $oldCalls=count($http->calls);
 $disabled=sharity_donation_account_change('example-ngo',$first['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'HUF']);
 check(!$disabled['mode_currencies']['direct']['HUF']&&$disabled['mode_currencies']['direct']['EUR']&&$disabled['modes']['direct'],'only one cell disabled');
 check(accountMap()['other-ngo']===$other&&count($http->calls)===$oldCalls,'sibling preserved, disabling has zero provider calls');
 check(sharity_donation_account_change('example-ngo',$first['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'HUF'])===$disabled,'disable replay exact');
 fails(fn()=>sharity_donation_account_bind('example-ngo',$proofPath,'absent',$adapter,static fn():int=>$GLOBALS['now']),'DONATION_ACCOUNT_STALE');
 $GLOBALS['optionFixture']['sharity_donation_accounts_v1']=accountMap();check(sharity_donation_quote_binding(['ngo_slug'=>'example-ngo','donation_mode'=>'direct','currency'=>'HUF'],$now)===null,'reader enforces disabled cell');
 $all=sharity_donation_account_change('example-ngo',$disabled['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'all']);check($all['modes']['direct']===false&&count($all['mode_currencies']['direct'])===4,'all currencies disabled only selected mode');
 check(in_array(['alloptions','options'],$cacheDeletes,true)&&in_array(['notoptions','options'],$cacheDeletes,true)&&in_array(['sharity_donation_accounts_v1','options'],$cacheDeletes,true),'all option cache paths invalidated');
 $d=accountProof();$p=$adapter->inspectAccount('acct_NGO');$payload=sharity_donation_account_binding_payload($d,$p,$now);$request=['kind'=>'bind','proof'=>$d];
 foreach(['before','after','corrupt']as$fault){accountSeed([]);$wpdb->dbh->fault=$fault;$wpdb->dbh->written=false;
  if($fault==='after'){$x=sharity_donation_account_change('example-ngo','absent',$request,$payload);check(accountMap()['example-ngo']===$x,'lost ACK resolved by full native readback');}
  else{fails(fn()=>sharity_donation_account_change('example-ngo','absent',$request,$payload),'DONATION_ACCOUNT_WRITE_UNCONFIRMED');check(isset(accountMap()['example-ngo'])===($fault==='corrupt'),'actual write result separate from error');}
  $wpdb->dbh->fault='';$wpdb->dbh->written=false;
 }
 accountSeed([]);$r=accountRace(fn()=>sharity_donation_account_change('example-ngo','absent',$request,$payload));check(isset($r[0]['value'],$r[1]['value'])&&$r[0]['value']===$r[1]['value']&&count(accountMap())===1,'same concurrent request one exact binding');
 accountSeed([]);$r=accountRace(fn($i)=>sharity_donation_account_change($i?'second-ngo':'first-ngo','absent',$request,$payload));
 check(count(array_filter($r,static fn($x)=>isset($x['value'])))===1&&count(accountMap())===1,'competing sibling writes do not overwrite winner');
 $loser=isset($r[0]['error'])?'first-ngo':'second-ngo';sharity_donation_account_change($loser,'absent',$request,$payload);check(count(accountMap())===2,'explicit stale retry preserves both NGO bindings');
 accountSeed(['example-ngo'=>$first]);$changed=$d;$changed['verified_until_utc']=gmdate('Y-m-d\TH:i:s\Z',$now+4000);$newPayload=sharity_donation_account_binding_payload($changed,$p,$now);
 $r=accountRace(fn($i)=>$i?sharity_donation_account_change('example-ngo',$first['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'HUF']):sharity_donation_account_change('example-ngo',$first['binding_version'],['kind'=>'bind','proof'=>$changed],$newPayload));
 check(count(array_filter($r,static fn($x)=>isset($x['value'])))===1,'bind versus disable one CAS winner');
 $current=accountMap()['example-ngo'];if($current['mode_currencies']['direct']['HUF']){$current=sharity_donation_account_change('example-ngo',$current['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'HUF']);}
 fails(fn()=>sharity_donation_account_change('example-ngo',$first['binding_version'],['kind'=>'bind','proof'=>$changed],$newPayload),'DONATION_ACCOUNT_STALE');check(accountMap()['example-ngo']===$current,'old request cannot undo disable');
 $wpdb->dbh->query('START TRANSACTION');try{fails(fn()=>sharity_donation_account_change('example-ngo',$current['binding_version'],['kind'=>'disable','mode'=>'direct','currency'=>'all']),'DONATION_ACCOUNT_STORAGE_UNAVAILABLE');}finally{$wpdb->dbh->rollback();}
 accountWriteProof($d);chmod($proofPath,0644);fails(fn()=>sharity_donation_account_bind('example-ngo',$proofPath,'absent',$adapter,static fn():int=>$GLOBALS['now']),'DONATION_BACKING_PROOF_PERMISSION');chmod($proofPath,0600);
 $bad=$d;$bad['evidence']['platform_costs']['raw_key']='PRIVATE';accountWriteProof($bad);fails(fn()=>sharity_donation_account_bind('example-ngo',$proofPath,'absent',$adapter,static fn():int=>$GLOBALS['now']),'DONATION_BACKING_PROOF_MALFORMED');
 check(count(array_filter($http->calls,static fn($c)=>$c['method']!=='get'))===0,'DB path still no provider POST');
 echo "Account native MySQL CAS/replay/race/uncertain-write checks completed\n";
}finally{$wpdb->dbh->query("DROP TABLE `$table`");$wpdb->dbh->close();unlink($proofPath);}
