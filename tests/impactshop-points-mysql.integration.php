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
function current_time($format) { return gmdate('Y-m-d H:i:s'); }
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
$wpdb = connection();
$source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/sharity-points.php');
eval(substr($source, strpos($source, 'function sharity_points_migrate_impactshop(')));
$existing = $wpdb->get_col('SHOW TABLES');
$owned=['user_points','point_transactions','level_history','decay_logs','impactshop_point_settlements','impactshop_point_changes','impactshop_affiliate_intents'];
check(array_diff($existing,array_map(fn($t)=>'impactshop_test_'.$t,$owned))===[], 'own database contains no foreign tables');
foreach($owned as $t){sql($wpdb,'DROP TABLE IF EXISTS impactshop_test_'.$t);}
foreach (['user_points','point_transactions','level_history','decay_logs'] as $table) {
    check(preg_match('/\$tables\[\] = "(CREATE TABLE \{\$wpdb->prefix\}' . $table . '.*?) \{\$charset\};";/s', $source, $m) === 1, 'extract exact schema ' . $table);
    sql($wpdb, str_replace('{$wpdb->prefix}', 'impactshop_test_', $m[1]) . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
check(sharity_points_migrate_impactshop(true)['success'], 'read-only preflight');
check(!isset($options['sharity_points_impactshop_migration_lock']), 'preflight does not acquire mutable migration lock');
check(sharity_points_migrate_impactshop()['success'], 'explicit additive migration');
check(sharity_points_migrate_impactshop()['success'], 'migration idempotent');
sql($wpdb, "CREATE TABLE impactshop_test_impactshop_affiliate_intents(provider_token_hash char(64) PRIMARY KEY, subject_ref varchar(80),provider_key varchar(16),partner_key varchar(128),status varchar(24),delete_after datetime) ENGINE=InnoDB");
$manager = new Sharity_Points_Manager();
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
    if($pid===0){$wpdb=connection();$r=(new Sharity_Points_Manager())->apply_impactshop_event($parallel,$run);exit($r['success']?0:1);}
    $children[]=$pid;
}
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'concurrent worker success');}
$wpdb=connection();
check(total()===250,'concurrent duplicate exactly once');
check((int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_point_transactions WHERE dedupe_key='impactshop:event:{$parallel}'")===1,'single concurrent history row');
// Existing point writer still operates against the extended schema.
$r=$manager->award_points_for_pseudo($pseudo,5,'bonus','existing',['source_type'=>'existing_reward'],'existing:1');
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
    if($pid===0){$wpdb=connection();if($i===0){$r=(new Sharity_Points_Manager())->apply_impactshop_event($decayRefund,$run);exit($r['success']?0:1);}(new Sharity_Decay_Manager())->calculate_and_apply_decay_for_pseudo($pseudo);exit(0);}
    $children[]=$pid;
}
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'refund/decay worker successful');}
$wpdb=connection();
$movement=(int)$wpdb->get_var('SELECT COALESCE(SUM(points),0) FROM impactshop_test_point_transactions WHERE id>'.$prior);
check(total()===1000+$movement && total()>=700 && total()<=800,'concurrent refund/decay balance equals committed history');
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
echo "Impact Shop SQL integration passed ({$checks} checks); no live/provider writes\n";
