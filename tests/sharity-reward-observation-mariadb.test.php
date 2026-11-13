<?php

declare(strict_types=1);

// Opt-in disposable LOCAL fixture only. No WP bootstrap, production data or images.
// Launch an existing MariaDB10.11 image with tmpfs, memory cap, and an ephemeral
// 127.0.0.1 port; pass only that port. Caller owns container start/removal.
$port = filter_var(getenv('REWARD_TEST_MARIADB_PORT'), FILTER_VALIDATE_INT);
if (!$port || $port < 1024 || $port > 65535 || getenv('REWARD_TEST_DISPOSABLE') !== 'watchdog-rewards-20261112') {
    fwrite(STDERR, "SKIP: explicit disposable MariaDB fixture/port required\n"); exit(77);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('ABSPATH', __DIR__ . '/fixture-not-wordpress/'); define('ARRAY_A', 'ARRAY_A');
define('SHARITY_REWARD_OBSERVATION_ENABLED', true); define('SHARITY_REWARD_OBSERVATION_MIGRATE', true);
require dirname(__DIR__) . '/wp-content/mu-plugins/sharity-reward-observation.php';
function verify(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function connection(int $port, string $user = 'root', string $password = ''): mysqli {
    $db = new mysqli('127.0.0.1', $user, $password, 'watchdog_fixture', $port);
    $db->set_charset('utf8mb4'); return $db;
}
final class RealObservationPort {
    public string $prefix = 'rwm_test_';
    public int $insert_id = 812; public string $last_error = 'caller-context';
    public int $rows_affected = 4; public int $num_rows = 3;
    public string $last_query = 'caller-query'; public array $last_result = [['caller'=>true]];
    private bool $suppressed = false;
    public function __construct(public mysqli $db) {}
    public function suppress_errors(bool $value): bool { $old=$this->suppressed; $this->suppressed=$value; return $old; }
    public function prepare(string $sql, ...$args): string {
        $i=0; $prepared=preg_replace_callback('/%[sd]/', function ($m) use (&$i, $args): string {
            verify(array_key_exists($i,$args), 'fixture_parameters'); $value=$args[$i++];
            return $m[0]==='%d' ? (string)(int)$value : "'".$this->db->real_escape_string((string)$value)."'";
        },$sql); verify($i===count($args),'fixture_parameters'); return $prepared;
    }
    public function query(string $sql) {
        $this->last_query=$sql;
        try {
            $result=$this->db->query($sql); $this->last_error='';
            $this->insert_id=(int)$this->db->insert_id; $this->rows_affected=$this->db->affected_rows;
            return $result instanceof mysqli_result ? $result : $this->rows_affected;
        } catch (Throwable $error) { $this->last_error='fixture_sql_failed'; if (!$this->suppressed) throw $error; return false; }
    }
    public function get_row(string $sql, $format): ?array {
        $result=$this->query($sql); if (!$result instanceof mysqli_result) return null;
        $row=$result->fetch_assoc(); $this->last_result=$row ? [$row] : []; $this->num_rows=$result->num_rows; return $row;
    }
}
function expected_record(string $key='fixture:one', array $patch=[]): array {
    return array_replace(['lane'=>'events.award_daily_login','subject_kind'=>'pseudo','subject_id'=>'abcdefghij',
        'dedupe_key'=>$key,'points'=>7,'type'=>'bonus','source_id'=>'private-source-reference',
        'source_type'=>'synthetic','metadata'=>['source_type'=>'synthetic','comment'=>'PRIVATE_COMMENT_NOT_EXPORTED','nested'=>['z'=>2,'a'=>'árvíz/fixture']]],$patch);
}
function canonical(mysqli $db, array $e): int {
    $kind=$e['subject_kind']; $user=$kind==='user' ? (int)$e['subject_id'] : null;
    $pseudo=$kind==='pseudo' ? (string)$e['subject_id'] : null; $points=$e['points']; $type=$e['type'];
    $source=$e['source_id']; $sourceType=$e['source_type']; $key=$e['dedupe_key'];
    $meta=json_encode($e['metadata'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $s=$db->prepare('INSERT INTO rwm_test_point_transactions(user_id,pseudo_id,points,type,source_id,source_type,dedupe_key,metadata) VALUES(?,?,?,?,?,?,?,?)');
    $s->bind_param('isisssss',$user,$pseudo,$points,$type,$source,$sourceType,$key,$meta);$s->execute();return (int)$db->insert_id;
}
function observation(mysqli $db, string $event): array {
    $s=$db->prepare('SELECT * FROM rwm_test_sharity_reward_observations WHERE event_key=?');$s->bind_param('s',$event);$s->execute();return $s->get_result()->fetch_assoc();
}
$db=connection($port); $wpdb=new RealObservationPort($db);
if (($argv[1] ?? '')==='worker') {
    $expected=expected_record('fixture:concurrent',['lane'=>'events.track_share_page']);
    for ($i=0;$i<8;$i++) {
        $token=Sharity_Reward_Observation::before($expected); verify($token!==null,'concurrent_before');
        verify(Sharity_Reward_Observation::after($token,['success'=>true,'transaction_id'=>(int)$argv[2]]),'concurrent_after');
    }
    echo "worker-ok\n"; exit;
}
$exporter=getenv('REWARD_TEST_EXPORTER');
verify(is_string($exporter) && is_file($exporter),'explicit_exporter_path_required'); require $exporter;
$log=tempnam(sys_get_temp_dir(),'reward-observation-fixture-'); chmod($log,0600); $priorLog=ini_set('error_log',$log);
$reader=null; $workers=[]; $metrics=[];
try {
    verify(str_starts_with((string)$db->query('SELECT VERSION()')->fetch_row()[0],'10.11.'),'fixture_requires_mariadb_10_11');
    $db->query('CREATE TABLE rwm_test_point_transactions (
        id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id bigint unsigned NULL,pseudo_id varchar(64) NULL,
        points int NOT NULL,type varchar(50) NOT NULL,source_id varchar(100) NULL,source_type varchar(50) NULL,
        dedupe_key varchar(150) NULL,metadata JSON NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_dedupe_user(user_id,dedupe_key),UNIQUE KEY uniq_dedupe_pseudo(pseudo_id,dedupe_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    verify(Sharity_Reward_Observation::migrate(),'real_migration'); verify(Sharity_Reward_Observation::migrate(),'idempotent_migration');
    $db->query("CREATE USER 'reward_reader_fixture'@'%' IDENTIFIED BY 'synthetic-test-only'");
    $db->query("GRANT SELECT ON watchdog_fixture.rwm_test_sharity_reward_observations TO 'reward_reader_fixture'@'%'");
    $db->query("GRANT SELECT ON watchdog_fixture.rwm_test_point_transactions TO 'reward_reader_fixture'@'%'");
    $reader=connection($port,'reward_reader_fixture','synthetic-test-only');
    $e=expected_record();$start=hrtime(true);$first=Sharity_Reward_Observation::before($e);
    $metrics['candidate_ms']=(hrtime(true)-$start)/1e6;verify($first!==null,'real_candidate');
    verify($wpdb->insert_id===812 && $wpdb->last_error==='caller-context','real_caller_context');
    $id=canonical($db,$e);$start=hrtime(true);verify(Sharity_Reward_Observation::after($first,['success'=>true,'transaction_id'=>$id]),'real_result');
    $metrics['result_ms']=(hrtime(true)-$start)/1e6;
    $second=Sharity_Reward_Observation::before($e); Sharity_Reward_Observation::after($second,['success'=>false,'error'=>'earning_blocked']);
    $row=observation($db,$first['event_key']);verify($row['state']==='fulfilled' && (int)$row['candidate_attempts']===2 && (int)$row['private_transaction_id']===$id,'real_dedupe_sticky');
    verify(Sharity_Reward_Observation::before(expected_record('fixture:one',['points'=>8]))===null,'real_immutable_conflict');
    verify((int)observation($db,$first['event_key'])['content_conflicts']===1,'real_conflict_durable');
    // Conflict intentionally tested; restore only this synthetic fixture to test a clean export.
    $db->query('UPDATE rwm_test_sharity_reward_observations SET content_conflicts=0');
    $old=Sharity_Reward_Observation::before(expected_record('fixture:out-of-order'));
    $new=Sharity_Reward_Observation::before(expected_record('fixture:out-of-order'));
    Sharity_Reward_Observation::after($old,['success'=>false,'error'=>'ledger_write_failed']);
    $row=observation($db,$old['event_key']);verify($row['state']==='candidate' && (string)$row['eligibility_seen']==='1','real_stale_failure');
    Sharity_Reward_Observation::after($new,['success'=>false,'error'=>'earning_blocked']);
    verify((string)observation($db,$old['event_key'])['eligibility_seen']==='1','real_sticky_eligibility');
    // An existing source transaction must remain open and roll back normally.
    $db->begin_transaction();$rolledId=canonical($db,expected_record('fixture:must-rollback'));
    verify(Sharity_Reward_Observation::before(expected_record('fixture:caller-transaction'))===null,'real_nested_before_refused');
    verify(!Sharity_Reward_Observation::migrate(),'real_nested_ddl_refused');
    verify((string)$db->query('SELECT @@in_transaction')->fetch_row()[0]==='1','real_caller_transaction_open');$db->rollback();
    verify((int)$db->query('SELECT COUNT(*) FROM rwm_test_point_transactions WHERE id='.$rolledId)->fetch_row()[0]===0,'real_business_rollback');
    // Trigger failures exercise actual server errors, not mock query returns.
    $db->query("CREATE TRIGGER rwm_test_candidate_failure BEFORE INSERT ON rwm_test_sharity_reward_observations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_SERVER_FAILURE'");
    $missing=Sharity_Reward_Observation::before(expected_record('fixture:receipt-loss'));verify($missing===null,'real_insert_failure_fail_open');
    $creditedWithoutReceipt=canonical($db,expected_record('fixture:receipt-loss'));
    verify($creditedWithoutReceipt>0,'real_credit_survives_recorder_insert_failure');$db->query('DROP TRIGGER rwm_test_candidate_failure');
    $e=expected_record('fixture:result-loss');$lost=Sharity_Reward_Observation::before($e);$lostId=canonical($db,$e);
    $db->query("CREATE TRIGGER rwm_test_result_failure BEFORE UPDATE ON rwm_test_sharity_reward_observations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_SERVER_FAILURE'");
    verify(!Sharity_Reward_Observation::after($lost,['success'=>true,'transaction_id'=>$lostId]),'real_result_failure_fail_open');
    $db->query('DROP TRIGGER rwm_test_result_failure');verify(observation($db,$lost['event_key'])['state']==='candidate','real_lost_result_unknown');
    $errors=file_get_contents($log);verify(str_contains($errors,'sharity_reward_observation_incomplete code=recording_unconfirmed') && !str_contains($errors,'PRIVATE_SERVER_FAILURE'),'real_safe_marker_sink_fixture_only');
    // Synthetic lock holder affects ONLY the observation table. Statement-local
    // recorder cancellation must beat the fixture's one-second fallback and
    // leave every following business query's session settings unchanged.
    $originalLockWait=(int)$db->query('SELECT @@innodb_lock_wait_timeout')->fetch_row()[0];
    $originalStatementTime=(string)$db->query('SELECT @@max_statement_time')->fetch_row()[0];
    $locker=connection($port);$locker->begin_transaction();
    $locker->query("UPDATE rwm_test_sharity_reward_observations SET candidate_attempts=candidate_attempts WHERE event_key='".$first['event_key']."'");
    $db->query('SET SESSION innodb_lock_wait_timeout=1');$start=hrtime(true);
    try{$locked=Sharity_Reward_Observation::before(expected_record());$metrics['contended_candidate_ms']=(hrtime(true)-$start)/1e6;verify($locked===null && $metrics['contended_candidate_ms']<100,'real_contended_observation_bounded_fail_open');
        $start=hrtime(true);$lockedAfter=Sharity_Reward_Observation::after($first,['success'=>true,'transaction_id'=>$id]);$metrics['contended_result_ms']=(hrtime(true)-$start)/1e6;
        verify(!$lockedAfter && $metrics['contended_result_ms']<100,'real_contended_result_bounded_fail_open');
        verify((int)$db->query('SELECT COUNT(*) FROM rwm_test_point_transactions WHERE id='.$id)->fetch_row()[0]===1,'real_committed_business_credit_survives_timeout');
        verify((string)$db->query('SELECT @@max_statement_time')->fetch_row()[0]===$originalStatementTime,'real_business_session_timeout_unchanged');}
    finally{$locker->rollback();$locker->close();$db->query('SET SESSION innodb_lock_wait_timeout='.$originalLockWait);}
    // Own short parallel observation writers: one event, unchanged canonical receipt.
    $e=expected_record('fixture:concurrent',['lane'=>'events.track_share_page']);$concurrentId=canonical($db,$e);
    for($i=0;$i<4;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$concurrentId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        verify(is_resource($process),'worker_start');fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    foreach($workers as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        verify(proc_close($process)===0 && trim($out)==='worker-ok','concurrent_worker_failed');
    }$workers=[];
    $key=hash('sha256','reward-observation:v1:event:'.reward_reader_canonical(['events.track_share_page',reward_reader_hash('subject','pseudo:abcdefghij'),reward_reader_hash('dedupe','fixture:concurrent')]));
    $row=observation($db,$key);verify($row['state']==='fulfilled' && (int)$row['candidate_attempts']===32,'real_32_concurrent_attempts_one_event');
    // Positive and pre-gate/uncertain states are read independently of manager claims.
    $e=expected_record('fixture:user',['subject_kind'=>'user','subject_id'=>17]);$user=Sharity_Reward_Observation::before($e);$userId=canonical($db,$e);Sharity_Reward_Observation::after($user,['success'=>true,'transaction_id'=>$userId]);
    $blocked=Sharity_Reward_Observation::before(expected_record('fixture:blocked'));Sharity_Reward_Observation::after($blocked,['success'=>false,'error'=>'earning_blocked']);
    $uncertain=Sharity_Reward_Observation::before(expected_record('fixture:commit-uncertain'));Sharity_Reward_Observation::after($uncertain,['success'=>false,'error'=>'commit_unconfirmed']);
    // Manager wp_json_encode uses default flags: integral floats persist as ints.
    $floatMeta=['source_type'=>'synthetic','ratio'=>1.0,'fraction'=>1.25];
    $float=Sharity_Reward_Observation::beforeAward('api.signed_earn','user',17,3,'bonus',null,$floatMeta,'fixture:float');
    $floatExpected=expected_record('fixture:float',['subject_kind'=>'user','subject_id'=>17,'points'=>3,'source_id'=>null,'metadata'=>$floatMeta]);
    $floatId=canonical($db,$floatExpected);Sharity_Reward_Observation::after($float,['success'=>true,'transaction_id'=>$floatId]);
    $cronExpected=expected_record('fixture:streak',['lane'=>'cron.streak.user','subject_kind'=>'user','subject_id'=>17,'type'=>'streak_bonus','points'=>30]);
    $cron=Sharity_Reward_Observation::before($cronExpected);$cronId=canonical($db,$cronExpected);Sharity_Reward_Observation::after($cron,['success'=>true,'transaction_id'=>$cronId]);
    $request=['mode'=>'point-observations','after_id'=>'0','upper_id'=>'0','updated_since'=>'2026-01-01T00:00:00.000000Z','limit'=>100];
    $start=hrtime(true);$snapshot=reward_reader_snapshot($reader,'rwm_test_',$request,hrtime(true)+6000000000);$metrics['snapshot_ms']=(hrtime(true)-$start)/1e6;
    verify($snapshot['capture']['complete'] && !$snapshot['capture']['producer_complete'],'real_snapshot_capture_not_activation');
    $serialized=json_encode($snapshot,JSON_THROW_ON_ERROR);
    foreach(['PRIVATE_COMMENT','abcdefghij','fixture:result-loss','private_subject','private_dedupe','private-source-reference'] as $private)verify(!str_contains($serialized,$private),'real_private_projection');
    $receipts=array_column($snapshot['receipts'],null,'event_key');
    verify(isset($receipts[$lost['event_key']]) && $receipts[$lost['event_key']]['payload_hash']===$lost['expected_digest'],'real_canonical_receipt_recovers_lost_result');
    verify(isset($receipts[$user['event_key']]),'real_canonical_user_index');
    verify(isset($receipts[$float['event_key']]) && $receipts[$float['event_key']]['payload_hash']===$float['expected_digest'],'real_default_json_integral_float_semantics');
    verify(isset($receipts[$cron['event_key']]) && $receipts[$cron['event_key']]['source_id']==='point-cron' && $receipts[$float['event_key']]['source_id']==='point-api' && $receipts[$lost['event_key']]['source_id']==='point-events','real_source_groups_not_misattributed');
    verify((string)$reader->query('SELECT @@in_transaction')->fetch_row()[0]==='0','reader_transaction_closed');
    $denied=false;try{$reader->query("DELETE FROM rwm_test_sharity_reward_observations");}catch(mysqli_sql_exception $expected){$denied=true;}verify($denied,'real_reader_database_write_denied');
    // An old unresolved row is included even outside PK and updated windows.
    $aged=Sharity_Reward_Observation::before(expected_record('fixture:aged'));
    $db->query("UPDATE rwm_test_sharity_reward_observations SET first_candidate_at_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY,updated_at_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY WHERE event_key='".$aged['event_key']."'");
    $upper=(string)$db->query('SELECT MAX(observation_id) FROM rwm_test_sharity_reward_observations')->fetch_row()[0];
    $recent=$db->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(6)-INTERVAL 10 MINUTE,'%Y-%m-%dT%H:%i:%s.%fZ')")->fetch_row()[0];
    $resumed=reward_reader_snapshot($reader,'rwm_test_',array_replace($request,['after_id'=>$upper,'updated_since'=>$recent]),hrtime(true)+6000000000);
    verify(in_array($aged['event_key'],array_column($resumed['events'],'event_key'),true),'real_old_pending_outside_cursor_included');
    // Result updates of older insertion IDs are recovered through updated overlap.
    $through=$resumed['observed_at'];$agedExpected=expected_record('fixture:aged');$agedId=canonical($db,$agedExpected);
    Sharity_Reward_Observation::after($aged,['success'=>true,'transaction_id'=>$agedId]);
    $updated=reward_reader_snapshot($reader,'rwm_test_',array_replace($request,['after_id'=>$upper,'updated_since'=>$through]),hrtime(true)+6000000000);
    verify(isset(array_column($updated['receipts'],null,'event_key')[$aged['event_key']]),'real_older_id_result_update_included');
    // A canonical writer commits while a reader holds its consistent snapshot.
    $raceExpected=expected_record('fixture:concurrent-target');$race=Sharity_Reward_Observation::before($raceExpected);$targetWriter=connection($port);
    $reader->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $reader->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');$inserted=false;
    $read=static function(string $name,string $sql,array $parameters) use($reader,$targetWriter,$raceExpected,&$inserted):array {
        $st=$reader->prepare($sql);if($parameters){$types=implode('',array_map(static fn($v)=>is_int($v)?'i':'s',$parameters));$st->bind_param($types,...$parameters);}$st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
        if($name==='clock' && !$inserted){canonical($targetWriter,$raceExpected);$inserted=true;}return $rows;
    };
    try{$during=reward_reader_capture($read,'rwm_test_',$request);}finally{$reader->rollback();$targetWriter->close();}
    verify(!isset(array_column($during['receipts'],null,'event_key')[$race['event_key']]),'real_concurrent_target_not_in_old_snapshot');
    $afterRace=reward_reader_snapshot($reader,'rwm_test_',$request,hrtime(true)+6000000000);
    verify(isset(array_column($afterRace['receipts'],null,'event_key')[$race['event_key']]),'real_concurrent_target_next_snapshot_seen');
    $partial=reward_reader_snapshot($reader,'rwm_test_',array_replace($request,['limit'=>1]),hrtime(true)+6000000000);
    verify(!$partial['capture']['complete'] && !$partial['capture']['cursor']['committable'],'real_partial_limit_not_complete');
    // Real prepared EXPLAIN validates declared access paths (small fixture only).
    $queries=reward_reader_queries('rwm_test_');$params=[
        'new'=>[0,10000,101],'updates'=>['2026-01-01 00:00:00.000000','2099-01-01 00:00:00.000000',101],
        'pending'=>[101],'pending_summary'=>[],'canonical_pseudo'=>['abcdefghij','fixture:one'],'canonical_user'=>[17,'fixture:user']];
    $wanted=['new'=>'PRIMARY','updates'=>'updated','pending'=>'pending','pending_summary'=>'pending','canonical_pseudo'=>'uniq_dedupe_pseudo','canonical_user'=>'uniq_dedupe_user'];$plans=[];
    foreach($params as $name=>$values){$s=$db->prepare('EXPLAIN '.$queries[$name]);if($values){$types=implode('',array_map(static fn($v)=>is_int($v)?'i':'s',$values));$s->bind_param($types,...$values);}$s->execute();$plan=$s->get_result()->fetch_assoc();verify($plan['key']===$wanted[$name] && !in_array($plan['type'],['ALL','index'],true),'real_index_plan_'.$name);$plans[$name]=['key'=>$plan['key'],'type'=>$plan['type'],'rows'=>(int)$plan['rows']];}
    // The server statement ceiling cancels a real SELECT; no business locks used.
    $reader->query('SET SESSION max_statement_time=0.05');$cancelled=false;$start=hrtime(true);
    try{$reader->query('SELECT SLEEP(1)');}catch(mysqli_sql_exception $error){$cancelled=true;}
    $metrics['cancel_ms']=(hrtime(true)-$start)/1e6;verify($cancelled && $metrics['cancel_ms']<500,'real_statement_cancellation');
    $expired=false;try{reward_reader_snapshot($reader,'rwm_test_',$request,hrtime(true)+1000000);}catch(RuntimeException $error){$expired=$error->getMessage()==='reader_budget';}verify($expired,'real_expired_reader_budget');
    verify((string)$reader->query('SELECT @@in_transaction')->fetch_row()[0]==='0','expired_reader_no_transaction');
    echo "PASS real MariaDB10.11 helper/exporter fixture: DDL, dedupe, immutable/sticky/out-of-order, transaction refusal, server-error fail-open, canonical lost-result readback,32 concurrent attempts, SELECT-only grants, privacy, partial cursor and cancellation\n";
    echo json_encode(['synthetic_fixture_only'=>true,'latency_ms'=>$metrics,'explain'=>$plans],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
} finally {
    foreach($workers as [$process,$pipes]){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
    if($reader instanceof mysqli)$reader->close();
    foreach(['rwm_test_candidate_failure','rwm_test_result_failure'] as $trigger){try{$db->query('DROP TRIGGER IF EXISTS '.$trigger);}catch(Throwable $ignored){}}
    foreach(['rwm_test_sharity_reward_observations','rwm_test_point_transactions'] as $table){try{$db->query('DROP TABLE IF EXISTS '.$table);}catch(Throwable $ignored){}}
    try{$db->query("DROP USER IF EXISTS 'reward_reader_fixture'@'%'");}catch(Throwable $ignored){}
    $db->close();ini_set('error_log',(string)$priorLog);if(is_file($log))unlink($log);
}
