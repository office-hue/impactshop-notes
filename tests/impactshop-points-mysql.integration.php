<?php
/** Explicit local-only SQL integration: actual wpdb/managers, synthetic rows only.
 * IMPACTSHOP_MYSQL_CONFIG points at the private configuration of the disposable container.
 * IMPACTSHOP_WPDB_FILE points at the read-only, hash-verified wpdb source from the target.
 * No production clock, profile, hook registration or credentials are changed.
 */
declare(strict_types=1);

$configFile = getenv('IMPACTSHOP_MYSQL_CONFIG') ?: '';
$wpdbFile = getenv('IMPACTSHOP_WPDB_FILE') ?: '';
if (getenv('IMPACTSHOP_MYSQL_SANDBOX_CONFIRM') !== 'LOCAL_IMPACTSHOP_ONLY'
    || !is_file($configFile) || is_link($configFile) || (fileperms($configFile) & 0777) !== 0600) {
    throw new RuntimeException('Explicit private local sandbox configuration required');
}
$cfg = json_decode(file_get_contents($configFile), true, 16, JSON_THROW_ON_ERROR);
if (($cfg['host'] ?? '') !== '127.0.0.1' || ($cfg['database'] ?? '') !== 'impactshop_sandbox'
    || ($cfg['user'] ?? '') !== 'impactshop_test' || !is_int($cfg['port']) || $cfg['port'] < 1024
    || !is_file($wpdbFile) || is_link($wpdbFile)
    || hash_file('sha256', $wpdbFile) !== ($cfg['wpdbSha256'] ?? '')) {
    throw new RuntimeException('Refusing nonlocal or unverified database input');
}
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$filters = []; $actions = []; $fault = null; $checks = 0;
function check(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $message); }
}
function add_filter($name, $callback, $priority = 10, ...$rest): void { $GLOBALS['filters'][$name][$priority][] = $callback; }
function has_filter($name, $callback) {
    foreach ($GLOBALS['filters'][$name] ?? [] as $priority => $callbacks) {
        if (in_array($callback, $callbacks, true)) { return $priority; }
    }
    return false;
}
function add_action(...$args): void {}
function did_action($name): int { return $name === 'template_redirect' ? 1 : 0; }
function apply_filters($name, $value, ...$rest) {
    foreach ($GLOBALS['filters'][$name] ?? [] as $callbacks) {
        foreach ($callbacks as $cb) { $value = $cb($value, ...$rest); }
    }
    if ($name === 'query' && is_callable($GLOBALS['fault'])) { $value = ($GLOBALS['fault'])($value); }
    return $value;
}
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function current_time($format) { return $format === 'timestamp' ? time() : gmdate($format === 'mysql' ? 'Y-m-d H:i:s' : $format); }
function wp_load_translations_early(): void {}
function __($text) { return $text; }
function _doing_it_wrong($function, $message, $version): void { throw new RuntimeException($function . ': ' . strip_tags($message)); }
function wp_debug_backtrace_summary(...$args) { return 'isolated-local-impactshop-test'; }
function mbstring_binary_safe_encoding(): void {}
function reset_mbstring_encoding(): void {}
function is_wp_error($value): bool { return false; }
function absint($value): int { return abs((int) $value); }
function is_multisite(): bool { return false; }
function do_action($name, ...$args): void {
    global $wpdb, $actions;
    $row = mysqli_fetch_row(mysqli_query($wpdb->dbh, 'SELECT @@in_transaction'));
    check($row[0] === '0', 'reward action is outside the transaction');
    $actions[] = $name;
}
require $wpdbFile;
require __DIR__ . '/../wp-content/mu-plugins/sharity-level-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-impactshop-manager.php';
function connection(): wpdb {
    global $cfg;
    $db = new wpdb($cfg['user'], $cfg['password'], $cfg['database'], '127.0.0.1:' . $cfg['port']);
    $db->suppress_errors(true); $db->set_prefix('impactshop_test_');
    if (!$db->ready) { throw new RuntimeException('Local database unavailable'); }
    return $db;
}
function sql(wpdb $db, string $query) {
    $answer = $db->query($query);
    if ($answer === false || $db->last_error !== '') { throw new RuntimeException('Sandbox SQL failed: ' . $db->last_error); }
    return $answer;
}
$options = ['sharity_points_impactshop_schema' => '2026-10-29-simple1',
    'sharity_points_legacy_consumer_enabled' => '0', 'sharity_points_impactshop_producer_enabled' => '1',
    'sharity_points_impactshop_consumer_enabled' => '1', 'sharity_points_impactshop_cutover_utc' => '2026-10-29 00:00:00',
    'sharity_points_impactshop_lanes' => ['impactshop:partner:test']];
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) return false; $GLOBALS['options'][$key]=$value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function wp_salt($scheme) { return str_repeat('s', 64); }
function impactshop_sharity_affiliate_subject_ref($pseudo) { return 'hmac-sha256:' . hash_hmac('sha256', 'impactshop-subject-v1' . "\0" . strtoupper($pseudo), wp_salt('auth')); }
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-impactshop-attribution.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-ledger-sync.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-decay-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-vacation-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-cron.php';
$wpdb = connection();
$source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/sharity-points.php');
eval(substr($source, strpos($source, 'function sharity_points_migrate_impactshop(')));
$existing = $wpdb->get_col('SHOW TABLES');
$owned=['user_points','point_transactions','level_history','decay_logs','user_referrals','impactshop_point_settlements','impactshop_point_changes','impactshop_affiliate_intents'];
check(array_diff($existing,array_map(fn($t)=>'impactshop_test_'.$t,$owned))===[], 'own database contains no foreign tables');
foreach($owned as $t){sql($wpdb,'DROP TABLE IF EXISTS impactshop_test_'.$t);}
foreach (['user_points','point_transactions','level_history','decay_logs','user_referrals'] as $table) {
    check(preg_match('/\$tables\[\] = "(CREATE TABLE \{\$wpdb->prefix\}' . $table . '.*?) \{\$charset\};";/s', $source, $m) === 1, 'extract exact schema ' . $table);
    sql($wpdb, str_replace('{$wpdb->prefix}', 'impactshop_test_', $m[1]) . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
check(sharity_points_migrate_impactshop(true)['success'], 'read-only preflight');
check(!isset($options['sharity_points_impactshop_migration_lock']), 'preflight does not acquire mutable migration lock');
check(sharity_points_migrate_impactshop()['success'], 'explicit additive migration');
check(sharity_points_migrate_impactshop()['success'], 'migration idempotent');
sql($wpdb, "CREATE TABLE impactshop_test_impactshop_affiliate_intents(provider_token_hash char(64) PRIMARY KEY, subject_ref varchar(80),provider_key varchar(16),partner_key varchar(128),status varchar(24),delete_after datetime,activation_id varchar(64),ngo_ref varchar(128),provider_program_ref varchar(160),source_placement varchar(64),redirected_at datetime) ENGINE=InnoDB");
$manager = new Sharity_Impactshop_Points_Manager();
$pseudo='abcdef123456';
check($manager && sharity_points_impactshop_bind_subject($pseudo)['success'], 'server subject bound');
$token='sat1_' . str_repeat('a',43);
$wpdb->insert('impactshop_test_impactshop_affiliate_intents', ['provider_token_hash'=>hash('sha256',$token), 'subject_ref'=>impactshop_sharity_affiliate_subject_ref($pseudo),'provider_key'=>'partner','partner_key'=>'test','status'=>'redirected','delete_after'=>gmdate('Y-m-d H:i:s',time()+3600)]);
function input(string $purchase, string $event, string $kind='award', string $amount='100'): array {
    global $token;
    return ['authority'=>true,'provider'=>'partner','namespace'=>'impactshop:partner:test','purchase_id'=>$purchase,'event_id'=>$event,'revision'=>'1','merchant'=>'test',
        'original_purchase_at'=>'2026-10-29T10:00:00Z','kind'=>$kind,'donation_amount_huf'=>$amount,'authority_ref'=>'signed-contract-v1','provider_token'=>$token];
}
function captured(array $event): int {
    $r=sharity_points_impactshop_capture($event);check($r['success'],'capture');return $r['event_id'];
}
function total(): int { global $wpdb; return (int)$wpdb->get_var("SELECT points_total FROM impactshop_test_user_points WHERE pseudo_id='abcdef123456'"); }
function eventRow(int $id): array { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM impactshop_test_impactshop_point_changes WHERE id=%d',$id),ARRAY_A); }
$run=str_repeat('1',32);
$award=captured(input('order1','award1'));
check(captured(input('order1','award1'))===$award,'duplicate capture same row');
$r=$manager->apply_impactshop_event($award,$run);
check($r['success'] && $r['actual_delta']===100 && total()===100,'atomic award');
check($manager->apply_impactshop_event($award,$run)['duplicate'] && total()===100,'award replay no movement');
// Original subject survives expiry of the bearer correlation.
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2020-01-01'");
sql($wpdb,"UPDATE impactshop_test_user_points SET points_total=20");
$refund=captured(input('order1','refund1','refund',''));
$r=$manager->apply_impactshop_event($refund,$run);
check($r['success'] && $r['actual_delta']===-20 && $r['forgiven_points']===80 && total()===0,'refund uses durable subject after token expiry');
sql($wpdb,"UPDATE impactshop_test_user_points SET points_total=50");
check($manager->apply_impactshop_event($refund,$run)['duplicate'] && total()===50,'no later debt collection');
$again=captured(input('order1','refund2','refund',''));
$r=$manager->apply_impactshop_event($again,$run);
check($r['status']==='manual' && total()===50,'second correction manual');
$resolved=$manager->resolve_impactshop_event($again,$r['root_version'],0,'No additional reversal',7);
check($resolved['success'] && $resolved['actual_delta']===0,'zero resolution accepted');
check($manager->resolve_impactshop_event($again,$r['root_version'],0,'No additional reversal',7)['duplicate'],'manual replay original outcome');
check($manager->resolve_impactshop_event($again,$r['root_version'],1,'changed',7)['error']==='resolution_conflict','changed resolution409');
check(json_decode(eventRow($again)['outcome_json'],true)['resolution']['admin_id']===7,'zero adjustment retains admin audit');
// A conflict must be retained rather than overwritten by the unique event key.
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2030-01-01'");
$a=captured(input('order2','conflict','award','100.10'));
$b=captured(input('order2','conflict','award','100.20'));
check($a!==$b,'same rounded amount different original payload retained');
check($manager->apply_impactshop_event($a,$run)['status']==='manual','conflict no award');
check($manager->apply_impactshop_event($b,$run)['status']==='manual' && total()===50,'conflict second no award');
$unknown=input('order3','unbound');$unknown['provider_token']='';
$uid=captured($unknown);$u=$manager->apply_impactshop_event($uid,$run);
check($u['status']==='manual','unbound held manually');
check($manager->resolve_impactshop_event($uid,$u['root_version'],10,'cannot guess subject',7)['error']==='unbound_subject','nonzero unbound cannot resolve');
check($manager->resolve_impactshop_event($uid,$u['root_version'],0,'No verified subject',7)['success'],'unbound zero resolution');
// Lost commit ACK: storage committed, caller gets unknown, replay reads the durable result.
$lost=captured(input('order4','lost'));
$hit=false;$fault=function($query)use(&$hit){ global $wpdb;if($query==='COMMIT'&&!$hit){$hit=true;check(mysqli_query($wpdb->dbh,'COMMIT')===true,'server actually committed');return 'SELECT * FROM impactshop_lost_ack';}return $query;};
$r=$manager->apply_impactshop_event($lost,$run);$fault=null;
check(!$r['success'] && total()===150,'unknown ACK not success though commit present');
check($manager->apply_impactshop_event($lost,$run)['duplicate'] && total()===150,'readback duplicate after uncertain commit');
// A root cap remains held; restarting a batch does not spend it under another run.
$held=captured(input('order5','cap','award','100001'));
check($manager->apply_impactshop_event($held,$run)['status']==='held','root cap hold');
check($manager->apply_impactshop_event($held,str_repeat('2',32))['duplicate'],'held no automatic new run');
check(eventRow($held)['run_id']===$run && total()===150,'held original run retained');
// Two processes contend on the same event and balance; only one writes history.
check(function_exists('pcntl_fork'),'pcntl required for actual concurrent transaction test');
$parallel=captured(input('order6','parallel','award','100'));
$wpdb->close();$children=[];
for($i=0;$i<2;$i++){
    $pid=pcntl_fork();check($pid!==-1,'fork worker');
    if($pid===0){$wpdb=connection();$r=(new Sharity_Impactshop_Points_Manager())->apply_impactshop_event($parallel,$run);exit($r['success']?0:1);}
    $children[]=$pid;
}
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'concurrent worker success');}
$wpdb=connection();
check(total()===250,'concurrent duplicate exactly once');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE dedupe_key='impactshop:event:{$parallel}'")===1,'single concurrent history row');
// Existing point writer still operates against the extended schema.
$r=(new Sharity_Points_Manager())->award_points_for_pseudo($pseudo,5,'bonus','existing',['source_type'=>'existing_reward'],'existing:1');
check($r['success'] && total()===255,'existing writer compatibility: '.json_encode($r));
// SQL failure rolls back history, root and balance together.
$rolled=captured(input('order7','rollback','award','20'));
$hit=false;$fault=function($q)use(&$hit){if(str_starts_with($q,'UPDATE impactshop_test_user_points SET points_total=points_total+')&&!$hit){$hit=true;return 'SELECT * FROM impactshop_injected_failure';}return $q;};
$r=$manager->apply_impactshop_event($rolled,$run);$fault=null;
check(!$r['success'] && total()===255 && eventRow($rolled)['status']==='queued','SQL failure fully rolled back');
check($manager->apply_impactshop_event($rolled,$run)['actual_delta']===20 && total()===275,'confirmed later retry only once');
// The existing vacation earning filter also applies to the new positive path.
sql($wpdb,"UPDATE impactshop_test_user_points SET freeze_until='2030-01-01'");
$frozen=captured(input('order8','frozen','award','100'));
$r=$manager->apply_impactshop_event($frozen,$run);
check($r['status']==='held' && total()===275 && eventRow($frozen)['reason']==='earning_blocked','freeze prevents automatic positive award');
$allowedRefund=captured(input('order7','frozen-refund','refund',''));
check($manager->apply_impactshop_event($allowedRefund,$run)['actual_delta']===-20 && total()===255,'freeze never blocks refund');
sql($wpdb,'UPDATE impactshop_test_user_points SET freeze_until=NULL');
// Refund and the existing decay writer overlap on the same balance row.
$before=captured(input('order9','before-decay','award','200'));
check($manager->apply_impactshop_event($before,$run)['actual_delta']===200,'seed concurrent refund');
sql($wpdb,"UPDATE impactshop_test_user_points SET points_total=1000,last_activity_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 45 DAY),last_decay_check_at=NULL,level_locked_until=NULL");
$decayRefund=captured(input('order9','concurrent-refund','refund',''));
$prior=(int)$wpdb->get_var('SELECT COALESCE(MAX(id),0) FROM impactshop_test_point_transactions');
$wpdb->close();$children=[];
for($i=0;$i<2;$i++){
    $pid=pcntl_fork();check($pid!==-1,'fork refund/decay worker');
    if($pid===0){$wpdb=connection();if($i===0){$r=(new Sharity_Impactshop_Points_Manager())->apply_impactshop_event($decayRefund,$run);exit($r['success']?0:1);}(new Sharity_Decay_Manager())->calculate_and_apply_decay_for_pseudo($pseudo);exit(0);}
    $children[]=$pid;
}
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'refund/decay worker successful');}
$wpdb=connection();
$movement=(int)$wpdb->get_var('SELECT COALESCE(SUM(points),0) FROM impactshop_test_point_transactions WHERE id>'.$prior);
check(total()===1000+$movement && total()>=700 && total()<800,'concurrent refund/decay balance equals committed history with an actual deduction');
// Losing the consumer connection releases GET_LOCK. Never continue this batch after reconnect.
$c1=captured(input('order10','connection-first','award','10'));
$c2=captured(input('order11','connection-second','award','10'));
$beforeTotal=total();$hit=false;
$fault=function($q)use(&$hit){
    global $wpdb;
    if($q==='COMMIT'&&!$hit){
        $hit=true;check(mysqli_query($wpdb->dbh,'COMMIT')===true,'commit before killed connection');
        $id=mysqli_fetch_row(mysqli_query($wpdb->dbh,'SELECT CONNECTION_ID()'))[0];
        $other=connection();check(mysqli_query($other->dbh,'KILL CONNECTION '.(int)$id)===true,'kill own consumer connection');$other->close();
        return 'SELECT CONNECTION_ID()';
    }
    return $q;
};
sharity_points_impactshop_consume();$fault=null;
check($hit && total()===$beforeTotal+10,'only first committed before connection loss');
check(eventRow($c1)['status']==='applied' && eventRow($c2)['status']==='queued','consumer stops before second event after lost lock');
$previousRun=$options['sharity_points_impactshop_run']['id'];
sharity_points_impactshop_consume();
check(total()===$beforeTotal+20 && eventRow($c2)['status']==='applied','next cron safely resumes durable run');
check($options['sharity_points_impactshop_run']['id']===$previousRun,'reconnect did not reset run budget');
// Pending attribution is durable; an expired token cannot replace the canonical merchant.
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2030-01-01'");
$pending=input('order12','pending12','pending','');$pending['authority_ref']='';
$pendingId=captured($pending);
check($manager->apply_impactshop_event($pendingId,$run)['status']==='applied','pending root captured before expiry');
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2020-01-01'");
$final=input('order12','final12','award','100');$final['merchant']='';
$finalId=captured($final);$beforeTotal=total();
check($manager->apply_impactshop_event($finalId,$run)['actual_delta']===100 && total()===$beforeTotal+100,'final after expiry uses immutable subject and merchant');
// A known partial/complex event in the same fetched page must stop an earlier queued award.
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2030-01-01'");
$queued=captured(input('order13','queued-before-correction','award','100'));
$partial=input('order13','partial-before-consume','adjustment','');$partial['manual_reason']='partial_correction';
$partialId=captured($partial);$unchanged=total();
check($manager->apply_impactshop_event($queued,$run)['status']==='manual' && total()===$unchanged,'complex capture latches manual before earlier award is consumed');
check($manager->apply_impactshop_event($partialId,$run)['status']==='manual','complex event remains separately reviewable');
// The live shared level manager reports SQL failure as success=false; roll back all economic writes.
sql($wpdb,"UPDATE impactshop_test_user_points SET current_level='bronze' WHERE pseudo_id='abcdef123456'");
$levelFailure=captured(input('order14','level-failure','award','10'));
$beforeTotal=total();$beforeTx=(int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_point_transactions');
$beforeLevels=(int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_level_history');
sql($wpdb,"CREATE TRIGGER impactshop_test_reject_level BEFORE INSERT ON impactshop_test_level_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated level failure'");
try {
    $r=$manager->apply_impactshop_event($levelFailure,$run);
    check(!$r['success'] && total()===$beforeTotal,'explicit shared-level failure rolls back balance');
    check((int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_point_transactions')===$beforeTx
        && (int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_level_history')===$beforeLevels
        && $wpdb->get_var("SELECT current_level FROM impactshop_test_user_points WHERE pseudo_id='abcdef123456'")==='bronze'
        && eventRow($levelFailure)['status']==='queued','level failure rolls back history, level and event');
} finally { sql($wpdb,'DROP TRIGGER impactshop_test_reject_level'); }
check($manager->apply_impactshop_event($levelFailure,$run)['actual_delta']===10,'same event resumes after level failure');
// Two purchases share the first-purchase/shop entitlement, even with the live strict content port.
sql($wpdb,'UPDATE impactshop_test_impactshop_point_settlements SET needs_reconciliation=0');
$bonus1=captured(input('order15','bonus-root-one','award','10'));
$bonus2=captured(input('order16','bonus-root-two','award','10'));
$beforeTotal=total();
check($manager->apply_impactshop_event($bonus1,$run)['actual_delta']===10
    && $manager->apply_impactshop_event($bonus2,$run)['actual_delta']===10,'different roots awarded before reconciliation');
sharity_points_impactshop_reconcile();
check(total()===$beforeTotal+20+125,'first purchase and shop bonus paid once across roots');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE type IN ('first_purchase','shop_discovery')")===2
    && (int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_impactshop_point_settlements WHERE needs_reconciliation=1')===0,
    'both roots reconciled without loosening shared content dedupe');
$afterBonus=total();sharity_points_impactshop_reconcile();
check(total()===$afterBonus,'reconciliation replay awards no bonus');
// A partial referral on root A resumes on root B with the unchanged strict shared port.
sql($wpdb,"INSERT INTO impactshop_test_user_referrals(referrer_pseudo_id,referred_pseudo_id,referral_code,status) VALUES('fedcba654321','abcdef123456','isolated-code','pending')");
sql($wpdb,"CREATE TRIGGER impactshop_test_reject_referral BEFORE INSERT ON impactshop_test_point_transactions FOR EACH ROW BEGIN IF NEW.type='referral_bonus' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated referred failure'; END IF; END");
try {
    check(!sharity_points_handle_referral($pseudo,0,['source_type'=>'impactshop_bonus','root_id'=>'root-A'],true),'partial referral does not mark completed');
    check((int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE type='referral'")===1
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE type='referral_bonus'")===0,'first side committed, second side failed');
} finally { sql($wpdb,'DROP TRIGGER impactshop_test_reject_referral'); }
check(sharity_points_handle_referral($pseudo,0,['source_type'=>'impactshop_bonus','root_id'=>'root-B'],true),'different root resumes partial referral');
check($wpdb->get_var('SELECT status FROM impactshop_test_user_referrals')==='completed'
    && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE type IN ('referral','referral_bonus')")===2,
    'exactly two referral entries and terminal completion');
// A malformed existing entitlement remains visible for reconciliation instead of being silently accepted.
$bonus3=captured(input('order17','bonus-conflict','award','10'));
check($manager->apply_impactshop_event($bonus3,$run)['actual_delta']===10,'third root awarded');
sql($wpdb,"UPDATE impactshop_test_point_transactions SET points=99 WHERE dedupe_key='first_purchase:abcdef123456'");
sharity_points_impactshop_reconcile();
check((int)$wpdb->get_var('SELECT COUNT(*) FROM impactshop_test_impactshop_point_settlements WHERE needs_reconciliation=1')===1,
    'wrong bonus amount cannot mark entitlement fulfilled');
// Existing decay runs on a real timestamp; daily replay and the existing 100-point floor hold.
$decayPseudo='123456abcdef';
sql($wpdb,"INSERT INTO impactshop_test_user_points(pseudo_id,points_total,points_lifetime,current_level,last_activity_at) VALUES('{$decayPseudo}',101,101,'basic',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 45 DAY))");
$decay=new Sharity_Decay_Manager();
check($decay->calculate_and_apply_decay_for_pseudo($decayPseudo)===1
    && (int)$wpdb->get_var("SELECT points_total FROM impactshop_test_user_points WHERE pseudo_id='{$decayPseudo}'")===100,'real native decay preserves 100-point floor');
check($decay->calculate_and_apply_decay_for_pseudo($decayPseudo)===0
    && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE pseudo_id='{$decayPseudo}' AND type='decay'")===1,'same-day decay is not repeated');
// JYSK's existing canonical award signature, actual native port and shared level boundary.
sql($wpdb,"UPDATE impactshop_test_user_points SET points_total=1995,points_lifetime=1995,current_level='basic' WHERE pseudo_id='{$decayPseudo}'");
$jyskAward=static fn($meta=['source_type'=>'jysk_campaign'])=>(new Sharity_Points_Manager())->award_points_for_pseudo($decayPseudo,5,'bonus','jysk-fixture',$meta,'jysk:canonical-sql');
sql($wpdb,"CREATE TRIGGER impactshop_test_reject_level BEFORE INSERT ON impactshop_test_level_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated JYSK level failure'");
try {
    check(!$jyskAward()['success'] && (int)$wpdb->get_var("SELECT points_total FROM impactshop_test_user_points WHERE pseudo_id='{$decayPseudo}'")===1995
        && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE dedupe_key='jysk:canonical-sql'")===0,'JYSK shared port rolls back level SQL failure');
} finally { sql($wpdb,'DROP TRIGGER impactshop_test_reject_level'); }
check($jyskAward()['success'] && $wpdb->get_var("SELECT current_level FROM impactshop_test_user_points WHERE pseudo_id='{$decayPseudo}'")==='bronze','JYSK award commits ledger, total and level');
check($jyskAward()['duplicate'] && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE dedupe_key='jysk:canonical-sql'")===1,'JYSK canonical replay writes once');
check($jyskAward(['source_type'=>'jysk_campaign','changed'=>true])['error']==='dedupe_conflict','JYSK same-key changed payload remains conflict');
// Simulate a lost ACK after a real COMMIT on the native mysqli path; verify actual durable readback.
$oldConnection=$wpdb->dbh;
$wpdb->dbh=new class($cfg['host'],$cfg['user'],$cfg['password'],$cfg['database'],$cfg['port']) extends mysqli {
    public bool $loseCommitAck=true;
    public function query(string $query, int $result_mode=MYSQLI_STORE_RESULT): mysqli_result|bool {
        $result=parent::query($query,$result_mode);
        if ($query==='COMMIT' && $this->loseCommitAck) { $this->loseCommitAck=false; return false; }
        return $result;
    }
};
$oldConnection->close();$actionsBefore=count($actions);
$r=(new Sharity_Points_Manager())->award_points_for_pseudo($decayPseudo,5,'bonus','ack-fixture',['source_type'=>'jysk_campaign'],'jysk:lost-ack');
check($r['success'] && $r['recovered_commit'] && count($actions)===$actionsBefore
    && (int)$wpdb->get_var("SELECT points_total FROM impactshop_test_user_points WHERE pseudo_id='{$decayPseudo}'")===2005,'shared port lost ACK uses durable readback and emits no unconfirmed hook');
check((new Sharity_Points_Manager())->award_points_for_pseudo($decayPseudo,5,'bonus','ack-fixture',['source_type'=>'jysk_campaign'],'jysk:lost-ack')['duplicate']
    && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE dedupe_key='jysk:lost-ack'")===1,'readback then replay cannot double-credit JYSK');
// Real Dognet adapter -> immutable SQL capture -> existing economic transaction.
require __DIR__ . '/../wp-content/mu-plugins/impactshop-dognet-points.php';
function impactshop_sharity_affiliate_table() { global $wpdb; return $wpdb->prefix.'impactshop_affiliate_intents'; }
$affiliateSource=file_get_contents(__DIR__.'/../wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php');
$start=strpos($affiliateSource,'function impactshop_sharity_affiliate_correlate(');
$end=strpos($affiliateSource,'function impactshop_sharity_affiliate_retention_cleanup(', $start);
eval(substr($affiliateSource,$start,$end-$start));
function impactshop_get_shops() { return [['shop_slug'=>'dognet-test','dognet_base'=>'https://go.dognet.com/?cid=357']]; }
function dognet_extract_campaign_id_from_base($base) { parse_str(parse_url($base,PHP_URL_QUERY),$q);return $q['cid']??0; }
function get_transient($key) { return $GLOBALS['dognet_quotes'][$key]??false; }
function set_transient($key,$value,$ttl) { $GLOBALS['dognet_quotes'][$key]=$value; }
function wp_remote_get($url,$args) { ++$GLOBALS['dognet_fx_reads'];return ['body'=>file_get_contents(__DIR__.'/fixtures/impactshop-mnb-eur-20261029.html')]; }
function wp_remote_retrieve_response_code($response) { return 200; }
function wp_remote_retrieve_body($response) { return $response['body']; }
$dognet_fx_reads=0;
$options['sharity_points_impactshop_cutover_utc']='2026-10-26 00:00:00';
$options['sharity_points_impactshop_lanes'][]='impactshop:dognet:26081';
$dognetToken='sat1_'.str_repeat('d',43);
$wpdb->insert('impactshop_test_impactshop_affiliate_intents',['provider_token_hash'=>hash('sha256',$dognetToken),
    'subject_ref'=>impactshop_sharity_affiliate_subject_ref($pseudo),'provider_key'=>'dognet','partner_key'=>'dognet-test',
    'status'=>'redirected','delete_after'=>'2030-01-01','redirected_at'=>'2026-10-28 08:00:00']);
$dn=['id'=>'500','campaign_id'=>'357','ad_channel_id'=>'26081','order_id'=>'dognet-order-1','data1'=>$dognetToken,
    'rstatus'=>'P','rtype'=>'S','created_at'=>'2026-10-28 10:00:00','commission'=>'10','total_cost'=>'100','date_approved'=>'2026-10-28 14:00:00'];
$beforeDognet=total();$pendingEvent=impactshop_dognet_points_event($dn,'26081');$pendingId=captured($pendingEvent);
$r=$manager->apply_impactshop_event($pendingId,$run);
check($r['success'] && total()===$beforeDognet && eventRow($pendingId)['status']==='applied','Dognet real P persists binding without points');
$estimated=$dn;$estimated['commission']='19';
check(captured(impactshop_dognet_points_event($estimated,'26081'))===$pendingId,'Dognet provisional commission changes remain same pending SQL event');
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2020-01-01' WHERE provider_key='dognet'");
$dn['rstatus']='A';$finalEvent=impactshop_dognet_points_event($dn,'26081');$finalId=captured($finalEvent);
$r=$manager->apply_impactshop_event($finalId,$run);
check($r['success'] && $r['actual_delta']===2000 && total()===$beforeDognet+2000,'Dognet expired real intent still credits frozen owner once');
$reads=$dognet_fx_reads;
$dn['updated_at']='changed';$dn['commission']='10.0000';
check(captured(impactshop_dognet_points_event($dn,'26081'))===$finalId && $dognet_fx_reads===$reads,'Dognet replay same durable SQL event and quote');
check($manager->apply_impactshop_event($finalId,$run)['duplicate'] && total()===$beforeDognet+2000,'Dognet replay no double balance/history movement');
$dn['commission']='11';$conflict=captured(impactshop_dognet_points_event($dn,'26081'));
$r=$manager->apply_impactshop_event($conflict,$run);
check($r['success'] && eventRow($conflict)['status']==='manual' && total()===$beforeDognet+2000,'Dognet changed final stays manual with existing award preserved');
$dn['commission']='10';$dn['rstatus']='D';$decline=captured(impactshop_dognet_points_event($dn,'26081'));$manager->apply_impactshop_event($decline,$run);
check(eventRow($decline)['status']==='manual' && total()===$beforeDognet+2000,'Dognet ambiguous D cannot create automatic reversal');
// First-seen A has its own new order and fresh authenticated binding.
sql($wpdb,"UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after='2030-01-01' WHERE provider_key='dognet'");
$dn['id']='501';$dn['order_id']='dognet-order-2';$dn['rstatus']='A';
$firstA=captured(impactshop_dognet_points_event($dn,'26081'));$r=$manager->apply_impactshop_event($firstA,$run);
check($r['success'] && $r['actual_delta']===2000 && total()===$beforeDognet+4000,'Dognet first A real attribution and atomic balance');
$dn['id']='502';$dn['order_id']='';$dn['rstatus']='P';
$badPending=captured(impactshop_dognet_points_event($dn,'26081'));$manager->apply_impactshop_event($badPending,$run);
$dn['order_id']='new-order-after-manual';$dn['rstatus']='A';
$blocked=captured(impactshop_dognet_points_event($dn,'26081'));$manager->apply_impactshop_event($blocked,$run);
check(eventRow($blocked)['root_id']===eventRow($badPending)['root_id'] && eventRow($blocked)['status']==='manual' && total()===$beforeDognet+4000,'Raw ID cannot escape manual unresolved P by changing order on A');
$dn['id']='503';$dn['rstatus']='P';$dn['order_id']='pending-original';
$validPending=captured(impactshop_dognet_points_event($dn,'26081'));$manager->apply_impactshop_event($validPending,$run);
$dn['rstatus']='A';$dn['order_id']='pending-corrected';
$blocked=captured(impactshop_dognet_points_event($dn,'26081'));$manager->apply_impactshop_event($blocked,$run);
check(eventRow($blocked)['root_id']===eventRow($validPending)['root_id'] && eventRow($blocked)['status']==='manual' && total()===$beforeDognet+4000,'Raw ID changing order P to A quarantines original root');
echo "Impact Shop SQL integration passed ({$checks} checks); no live/provider writes\n";
