<?php
/** Local real-MariaDB/real-wpdb acceptance. Never loads WordPress or production credentials. */
declare(strict_types=1);
$base = dirname(__DIR__) . '/tmp/jysk-ngo-card-directory-20261109/enrollment-test';
if (getenv('JYSK_ENROLLMENT_SANDBOX') !== 'LOCAL_ONLY') { throw new RuntimeException('explicit_local_confirmation_required'); }
$config = $base . '/mysql.json';
if (!is_file($config) || is_link($config) || (fileperms($config) & 0777) !== 0600) { throw new RuntimeException('private_config_required'); }
$cfg = json_decode(file_get_contents($config), true, 16, JSON_THROW_ON_ERROR);
if ($cfg['host'] !== '127.0.0.1' || $cfg['database'] !== 'jysk_sandbox' || $cfg['user'] !== 'jysk_test'
    || $cfg['port'] < 1024 || hash_file('sha256', $base . '/class-wpdb.php') !== $cfg['wpdbSha256']) {
    throw new RuntimeException('local_source_and_target_required');
}
define('ABSPATH', __DIR__ . '/'); define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_DEBUG', false); define('WP_DEBUG_DISPLAY', false); define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
$checks = 0; $transientWrites = 0; $httpReads = 0;
function check(bool $ok, string $name): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException('FAIL: ' . $name); } }
function blocked(callable $call, string $code): void {
    try { $call(); } catch (Throwable $e) { check(str_contains($e->getMessage(), $code), 'expected ' . $code . ', got ' . $e->getMessage()); return; }
    throw new RuntimeException('FAIL: not blocked ' . $code);
}
class WP_REST_Request {}
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data, public int $status = 200) {}
    public function header($key, $value): void { $this->headers[$key] = $value; }
}
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
function wp_parse_url($s) { return parse_url($s); }
function add_action(...$args): void {}
function add_filter(...$args): void {}
function has_filter(...$args): bool { return false; }
function apply_filters($name, $value, ...$args) { return $value; }
function do_action(...$args): void {}
function wp_load_translations_early(): void {}
function __($s) { return $s; }
function _doing_it_wrong($f, $m, $v): void { throw new RuntimeException($f . ': ' . strip_tags($m)); }
function wp_debug_backtrace_summary(...$args) { return 'local-enrollment-test'; }
function mbstring_binary_safe_encoding(): void {}
function reset_mbstring_encoding(): void {}
function is_multisite(): bool { return false; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function absint($v) { return abs((int)$v); }
function current_time($format, $gmt = false) { return '2026-11-10 10:00:00'; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function remove_accents($s) { return iconv('UTF-8', 'ASCII//TRANSLIT', $s); }
function sanitize_title($s) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($s))), '-'); }
function esc_url_raw($s) { return filter_var($s, FILTER_VALIDATE_URL) ? $s : ''; }
function get_transient($key) { return false; } // Deliberately stale cache.
function set_transient($key,$value,$ttl) {
    $GLOBALS['transientWrites']++;
    if (($GLOBALS['cacheMode'] ?? '') === 'throw') { throw new RuntimeException('synthetic_cache_failure'); }
    if (($GLOBALS['cacheMode'] ?? '') === 'false') { return false; }
    foreach (['_transient_' . $key => $value, '_transient_timeout_' . $key => time()+$ttl] as $name=>$v) {
        q('INSERT INTO jysk_enroll_options (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)',[$name,(string)$v,'no']);
    }
    return true;
}
class ImpactShop_NGO_Card_API {
    public static function get_dataset_items($refresh) {
        // Model the existing pre-transaction cache side effect; not a dataset implementation test.
        if (!empty($GLOBALS['prepareCacheWrite'])) {
            q('INSERT INTO jysk_enroll_options (option_name,option_value,autoload) VALUES (?,?,?) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)', ['_transient_fixture_dataset','prepared','no']);
        }
        return [];
    }
}
function is_wp_error($v) { return false; }
function wp_remote_get(...$args) { $GLOBALS['httpReads']++; return ['body' => $GLOBALS['csvBody'] ?? '']; }
function wp_remote_retrieve_body($v) { return $v['body']; }
require $base . '/class-wpdb.php';
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php';
require dirname(__DIR__) . '/scripts/jysk-ngo-catalog-enroll.php';

function connection(): wpdb {
    global $cfg;
    $db = new wpdb($cfg['user'], $cfg['password'], $cfg['database'], '127.0.0.1:' . $cfg['port']);
    $db->suppress_errors(true); $db->set_prefix('jysk_enroll_');
    if (!$db->ready) { throw new RuntimeException('sandbox_unavailable'); }
    return $db;
}

function cli(string $mode, string $inputFile, array $extra = []): array {
    global $cfg;
    $env = ['JYSK_NGO_DB_HOST'=>'127.0.0.1','JYSK_NGO_DB_PORT'=>(string)$cfg['port'],
        'JYSK_NGO_DB_NAME'=>'jysk_sandbox','JYSK_NGO_DB_PREFIX'=>'jysk_enroll_',
        'JYSK_NGO_DB_USER'=>$cfg['user'],'JYSK_NGO_DB_PASSWORD'=>$cfg['password']];
    $command = array_merge([PHP_BINARY,dirname(__DIR__) . '/scripts/jysk-ngo-catalog-enroll.php',$mode,
        '--' . ($mode === 'plan' ? 'input' : 'plan') . '=' . $inputFile], $extra);
    $p = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if (!is_resource($p)) { throw new RuntimeException('cli_start_failed'); }
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
    return [$exit,json_decode($out,true),$err];
}
class LossyEnrollmentConnection extends mysqli {
    public string $faultMode = '';
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool {
        $answer=parent::execute_query($query,$params);
        if (($this->faultMode === 'write' && str_starts_with($query,'INSERT INTO '))
            || ($this->faultMode === 'commit' && $query === 'COMMIT')) {
            $this->close(); throw new RuntimeException('synthetic_connection_lost');
        }
        return $answer;
    }
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool {
        $answer=parent::query($query,$result_mode);
        if (($this->faultMode === 'write' && (str_starts_with($query,'INSERT INTO ') || str_starts_with($query,'UPDATE '))) || ($this->faultMode === 'commit' && $query === 'COMMIT')) {
            $this->close(); throw new RuntimeException('synthetic_connection_lost');
        }
        return $answer;
    }
}

$wpdb = connection(); $db = $wpdb->dbh;
function q(string $sql, array $args = []) { global $db; $r = $db->execute_query($sql, $args ?: null); if ($r === false) { throw new RuntimeException('fixture_sql_error'); } return $r; }
function snapshot(): string {
    $out = [];
    foreach (['sharity_ngo_catalog','sharity_ngo_campaign_flags','sharity_ngo_enrollment_reservations','options','votes','preferences','ledger','jysk_payload'] as $s) {
        $out[$s] = q('SELECT * FROM jysk_enroll_' . $s . ' ORDER BY 1')->fetch_all(MYSQLI_ASSOC);
    }
    return json_encode($out, JSON_THROW_ON_ERROR);
}
foreach (['sharity_ngo_catalog','sharity_ngo_campaign_flags','sharity_ngo_enrollment_reservations','options','votes','preferences','ledger','jysk_payload'] as $s) { q('DROP TABLE IF EXISTS jysk_enroll_' . $s); }
$source = file_get_contents(dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php');
preg_match_all('/CREATE TABLE \{\$(catalog|flags)\} \((.*?)\) \{\$charset\}/s', $source, $ddl, PREG_SET_ORDER);
check(count($ddl) === 2, 'DDL is the actual product schema');
foreach ($ddl as $m) { q('CREATE TABLE jysk_enroll_' . ($m[1] === 'catalog' ? 'sharity_ngo_catalog' : 'sharity_ngo_campaign_flags') . ' (' . $m[2] . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); }
q('CREATE TABLE jysk_enroll_options (option_id BIGINT AUTO_INCREMENT PRIMARY KEY,option_name VARCHAR(191) UNIQUE,option_value LONGTEXT,autoload VARCHAR(20)) ENGINE=MyISAM');
$reservationDdl=file_get_contents(dirname(__DIR__) . '/scripts/jysk-ngo-catalog-reservation-schema.sql');
q(str_replace('wp_sharity_ngo_enrollment_reservations','jysk_enroll_sharity_ngo_enrollment_reservations',$reservationDdl));
check(q("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jysk_enroll_options'")->fetch_row()[0] === 'MyISAM','fixture matches live options engine');
foreach (['votes','preferences','ledger','jysk_payload'] as $s) {
    q('CREATE TABLE jysk_enroll_' . $s . ' (id INT PRIMARY KEY,ngo_id INT,content TEXT) ENGINE=InnoDB');
    q('INSERT INTO jysk_enroll_' . $s . " VALUES (1,28,'unchanged historical synthetic reference')");
}

// Compare the exact original importer output, not a reimplementation of it.
$legacy=file_get_contents($base . '/catalog-before.php');
if (hash('sha256',$legacy) !== '55198b339d2ababb0fb84cf46bf2c4dda28d43741fb0c0643b4bc34050e96d8c') {
    throw new RuntimeException('baseline_hash_mismatch');
}
$begin=strpos($legacy,'function impactshop_vb2026_upsert_catalog_rows(');
$end=strpos($legacy,'function impactshop_vb2026_parse_hu_datetime',$begin);
eval(str_replace('function impactshop_vb2026_upsert_catalog_rows(', 'function legacy_catalog_fixture(',substr($legacy,$begin,$end-$begin)));
$goldenRows=[
    ['Azonosító'=>'71','Név'=>'Árvíz Alapítvány','Státusz'=>'Aktív','Honlap'=>'https://example.test/','Székhely - Város'=>'Budapest','Cél'=>'A short mission','Kampányok száma'=>'2'],
    ['Azonosító'=>'72','Név'=>'Second Foundation','Státusz'=>'Inaktív','Honlap'=>'','Székhely - Város'=>'Pécs'],
];
$goldenDataset=['card'=>['name'=>'Árvíz Alapítvány','slug'=>'real-card-slug','share_url'=>'https://example.test/share','logo_url'=>'https://example.test/logo.png']];
legacy_catalog_fixture($goldenRows,$goldenDataset); legacy_catalog_fixture($goldenRows,$goldenDataset);
$legacyRows=q('SELECT * FROM jysk_enroll_sharity_ngo_catalog ORDER BY sharity_ngo_id')->fetch_all(MYSQLI_ASSOC);
$legacyFlags=q('SELECT * FROM jysk_enroll_sharity_ngo_campaign_flags ORDER BY sharity_ngo_id')->fetch_all(MYSQLI_ASSOC);
q('TRUNCATE jysk_enroll_sharity_ngo_catalog'); q('TRUNCATE jysk_enroll_sharity_ngo_campaign_flags');
impactshop_vb2026_upsert_catalog_rows($goldenRows,$goldenDataset); impactshop_vb2026_upsert_catalog_rows($goldenRows,$goldenDataset);
check(q('SELECT * FROM jysk_enroll_sharity_ngo_catalog ORDER BY sharity_ngo_id')->fetch_all(MYSQLI_ASSOC) === $legacyRows,'exact old catalog projection preserved');
check(q('SELECT * FROM jysk_enroll_sharity_ngo_campaign_flags ORDER BY sharity_ngo_id')->fetch_all(MYSQLI_ASSOC) === $legacyFlags,'exact old flag projection preserved');
q('TRUNCATE jysk_enroll_sharity_ngo_catalog'); q('TRUNCATE jysk_enroll_sharity_ngo_campaign_flags');
// Regression from the live CSV shape: inactive collisions already rejected by the old importer.
// Synthetic identities only; initialize the exact historical catalog + orphan flag state.
$legacyCollisionRows = [
    ['Azonosító'=>'207','Név'=>'Retired placeholder','Státusz'=>'Inaktív'],
    ['Azonosító'=>'200','Név'=>'Retired placeholder','Státusz'=>'Inaktív'],
    ['Azonosító'=>'199','Név'=>'Retired placeholder','Státusz'=>'Inaktív'],
    ['Azonosító'=>'140','Név'=>'Synthetic rescue','Státusz'=>'Aktív'],
    ['Azonosító'=>'135','Név'=>'Synthetic rescue','Státusz'=>'Inaktív'],
];
// Two legacy passes establish its existing null-to-empty flag normalization too.
legacy_catalog_fixture($legacyCollisionRows, []); legacy_catalog_fixture($legacyCollisionRows, []);
$legacyCollisionBefore = snapshot();
$collisionLog = $base . '/legacy-collisions.log'; file_put_contents($collisionLog, '');
$previousLog = ini_set('error_log', $collisionLog);
check(impactshop_vb2026_upsert_catalog_rows(array_reverse($legacyCollisionRows), []) === true, 'known inactive collision does not block refresh, independent of input order');
check(snapshot() === $legacyCollisionBefore, 'existing owners and all historical flags preserved exactly');
check(substr_count(file_get_contents($collisionLog), 'catalog_legacy_inactive_collision_skipped_id_') === 3, 'every rejected historical row has an explicit sanitized diagnostic');
$withFresh = array_merge($legacyCollisionRows, [['Azonosító'=>'300','Név'=>'Fresh unrelated NGO','Státusz'=>'Aktív']]);
check(impactshop_vb2026_upsert_catalog_rows($withFresh, []) === true, 'unrelated valid record still refreshes');
check((int)q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_catalog WHERE sharity_ngo_id=300')->fetch_row()[0] === 1, 'unrelated record stored once');
q('DELETE FROM jysk_enroll_sharity_ngo_catalog WHERE sharity_ngo_id=300');
q('DELETE FROM jysk_enroll_sharity_ngo_campaign_flags WHERE sharity_ngo_id=300');
foreach (['new-id','active','no-owner-in-batch','owner-renamed','missing-flag','selectable-flag','duplicate-id'] as $case) {
    $probe = $legacyCollisionRows;
    if ($case === 'new-id') { $probe[1]['Azonosító']='201'; }
    elseif ($case === 'active') { $probe[1]['Státusz']='Aktív'; }
    elseif ($case === 'no-owner-in-batch') { array_shift($probe); }
    elseif ($case === 'owner-renamed') { $probe[0]['Név']='Renamed owner'; }
    elseif ($case === 'missing-flag') { q('DELETE FROM jysk_enroll_sharity_ngo_campaign_flags WHERE sharity_ngo_id=200'); }
    elseif ($case === 'selectable-flag') { q('UPDATE jysk_enroll_sharity_ngo_campaign_flags SET allow_user_selection=1 WHERE sharity_ngo_id=200'); }
    else { $probe[] = $probe[1]; }
    $state = snapshot();
    blocked(fn()=>impactshop_vb2026_upsert_catalog_rows($probe, []), 'catalog_batch_identity_conflict');
    check(snapshot() === $state, 'conflict remains atomic: ' . $case);
    if ($case === 'missing-flag') { legacy_catalog_fixture([$legacyCollisionRows[1]], []); }
    if ($case === 'selectable-flag') { q('UPDATE jysk_enroll_sharity_ngo_campaign_flags SET allow_user_selection=0 WHERE sharity_ngo_id=200'); }
}
ini_set('error_log', $previousLog);
q('TRUNCATE jysk_enroll_sharity_ngo_catalog'); q('TRUNCATE jysk_enroll_sharity_ngo_campaign_flags');
$row = ['Azonosító'=>'28','Név'=>'Existing NGO','Státusz'=>'Aktív','Honlap'=>'https://existing.example.test/'];
check(impactshop_vb2026_upsert_catalog_rows([$row], []) === true, 'ordinary import');
check(impactshop_vb2026_upsert_catalog_rows([$row], []) === true, 'unchanged hash zero affected update succeeds');
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_catalog')->fetch_row()[0] === 1, 'idempotent import');
q('INSERT INTO jysk_enroll_options (option_name,option_value,autoload) VALUES (?,?,?)', ['_transient_' . IMPACTSHOP_VB2026_NGO_SYNC_TRANSIENT,(string)time(),'no']);
$before = snapshot();
check(impactshop_vb2026_upsert_catalog_rows([['Azonosító'=>'29','Név'=>'Skipped']], [], true) === false, 'fresh SQL transient overrides stale object cache');
check(snapshot() === $before, 'fresh transient no writes');
q('DELETE FROM jysk_enroll_options');
$target = ['host'=>'127.0.0.1','port'=>$cfg['port'],'database'=>'jysk_sandbox','prefix'=>'jysk_enroll_'];
$op = new JyskNgoEnrollment($db, $target);
$refs = [];
foreach (q("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (COLUMN_NAME LIKE '%ngo%id%' OR COLUMN_NAME LIKE '%beneficiary%') ORDER BY TABLE_NAME,COLUMN_NAME")->fetch_all(MYSQLI_ASSOC) as $r) {
    $refs[$r['TABLE_NAME'] . '.' . $r['COLUMN_NAME']] = 'canonical';
}
file_put_contents($base . '/normal-runtime.php', $source);
file_put_contents($base . '/source.csv', "Azonosító,Név\n28,Existing NGO\n");
file_put_contents($base . '/dataset.json', '{}');
$input = ['version'=>1,'target'=>$target,'authorityEvidenceSha256'=>str_repeat('a',64),'verifiedAt'=>gmdate('c'),'references'=>$refs];
foreach (['directory'=>dirname(__DIR__) . '/config/ngo-card-directory-jysk-20261109.json','csv'=>$base . '/source.csv',
    'dataset'=>$base . '/dataset.json','runtime'=>$base . '/normal-runtime.php'] as $key=>$path) {
    $input[$key] = ['path'=>$path,'sha256'=>hash_file('sha256',$path)];
}
q('ALTER TABLE jysk_enroll_sharity_ngo_campaign_flags ENGINE=MyISAM');
blocked(fn()=>$op->plan($input),'schema_requires_innodb');
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]),'catalog_requires_innodb');
q('ALTER TABLE jysk_enroll_sharity_ngo_campaign_flags ENGINE=InnoDB');
q('ALTER TABLE jysk_enroll_sharity_ngo_enrollment_reservations ENGINE=MyISAM');
blocked(fn()=>$op->plan($input),'schema_requires_innodb');
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]),'catalog_requires_innodb');
q('ALTER TABLE jysk_enroll_sharity_ngo_enrollment_reservations ENGINE=InnoDB');
q('RENAME TABLE jysk_enroll_sharity_ngo_enrollment_reservations TO jysk_enroll_reservation_missing');
blocked(fn()=>$op->plan($input),'schema_requires_innodb');
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]),'catalog_requires_innodb');
check((int)q("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jysk_enroll_sharity_ngo_enrollment_reservations'")->fetch_row()[0]===0,'no implicit migration');
q('RENAME TABLE jysk_enroll_reservation_missing TO jysk_enroll_sharity_ngo_enrollment_reservations');
q('INSERT INTO jysk_enroll_options (option_name,option_value,autoload) VALUES (?,?,?)',[JyskNgoEnrollment::RESERVATION_KEY,'{}','no']);
$legacyState=snapshot();
blocked(fn()=>$op->plan($input),'legacy_reservation_requires_review');
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]),'catalog_legacy_reservation_requires_review');
check(snapshot()===$legacyState,'legacy option never silently migrated or overwritten');
q('DELETE FROM jysk_enroll_options WHERE option_name=?',[JyskNgoEnrollment::RESERVATION_KEY]);
$runtimePath=$input['runtime']['path']; $runtimeOriginal=file_get_contents($runtimePath);
file_put_contents($runtimePath,str_replace('sharity_ngo_enrollment_reservations','old_storage',$runtimeOriginal));
$oldRuntime=$input; $oldRuntime['runtime']['sha256']=hash_file('sha256',$runtimePath);
blocked(fn()=>$op->plan($oldRuntime),'normal_runtime_required');
file_put_contents($runtimePath,$runtimeOriginal);
$before = snapshot(); $plan = $op->plan($input);
check(count($plan['entries']) === 8 && snapshot() === $before, 'plan reads only');
check($op->status($plan)['status'] === 'not-applied' && snapshot() === $before, 'status reads only');
blocked(fn()=>$op->apply($plan, str_repeat('0',64)), 'exact_approval_required');
$bad = $input; $bad['references'] = []; blocked(fn()=>$op->plan($bad), 'reference_inventory_drift');
q('INSERT INTO jysk_enroll_ledger VALUES (2,?,?)', [$plan['entries'][0]['ngo_id'],'orphan reference']);
blocked(fn()=>$op->apply($plan,$plan['digest']), 'prestate_drift');
q('DELETE FROM jysk_enroll_ledger WHERE id=2');

file_put_contents($base . '/input.json',json_encode($input)); chmod($base . '/input.json',0600);
file_put_contents($base . '/plan.json',json_encode($plan)); chmod($base . '/plan.json',0600);
$cliPlan=cli('plan',$base . '/input.json');
check($cliPlan[0] === 0 && count($cliPlan[1]['entries'] ?? []) === 8 && snapshot() === $before, 'full CLI plan reads only');
$cliStatus=cli('status',$base . '/plan.json');
check($cliStatus[0] === 0 && $cliStatus[1]['status'] === 'not-applied' && snapshot() === $before, 'full CLI status reads only');
q('RENAME TABLE jysk_enroll_options TO jysk_enroll_options_missing');
$missing=cli('plan',$base . '/input.json');
check($missing[0] !== 0, 'missing schema blocks CLI without migration');
q('RENAME TABLE jysk_enroll_options_missing TO jysk_enroll_options');
check(snapshot() === $before, 'missing schema did not initialize options or hooks');
// CSV old HTTP host and dataset final name/slug cannot hide duplicate organizations.
$csvPath=$base . '/source.csv'; $csvOriginal=file_get_contents($csvPath);
file_put_contents($csvPath,"Azonosító,Név,Honlap\n28,Other Name,http://www.fehervariallatotthon.hu/\n");
$dup=$input; $dup['csv']['sha256']=hash_file('sha256',$csvPath);
blocked(fn()=>$op->plan($dup),'existing_identity_conflict');
file_put_contents($csvPath,$csvOriginal);
$datasetPath=$base . '/dataset.json';
file_put_contents($datasetPath,json_encode(['other'=>['name'=>'Other','slug'=>$plan['entries'][0]['slug']]]));
$dup=$input; $dup['dataset']['sha256']=hash_file('sha256',$datasetPath);
blocked(fn()=>$op->plan($dup),'existing_identity_conflict');
// Exact already-published supplemental Card metadata has no canonical ID; it is allowed.
file_put_contents($datasetPath,json_encode([$plan['entries'][0]['slug']=>['name'=>$plan['entries'][0]['name']]]));
$metadata=$input; $metadata['dataset']['sha256']=hash_file('sha256',$datasetPath);
check(count($op->plan($metadata)['entries']) === 8,'existing own supplemental Card metadata is not a canonical collision');
file_put_contents($datasetPath,'{}');
foreach (['-1','28x','2147483647'] as $badId) {
    blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([['Azonosító'=>$badId,'Név'=>'Invalid']],[]),'catalog_csv_identity_invalid');
}
$lossy=new LossyEnrollmentConnection('127.0.0.1',$cfg['user'],$cfg['password'],$cfg['database'],$cfg['port']);
$lostOp=new JyskNgoEnrollment($lossy,$target); $lossy->faultMode='write';
blocked(fn()=>$lostOp->apply($plan,$plan['digest']),'enrollment_sql_failed');
check(snapshot() === $before,'native connection loss rolls enrollment back without reconnect');
$lossy=new LossyEnrollmentConnection('127.0.0.1',$cfg['user'],$cfg['password'],$cfg['database'],$cfg['port']);
$lossy->set_charset('utf8mb4'); $lossy->faultMode='write';
$wpdb->dbh=$lossy;
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([['Azonosító'=>'29','Név'=>'Lost connection']],[]),'catalog_sql_failed');
$wpdb->dbh=$db;
check(snapshot() === $before,'native connection loss rolls CSV import back without wpdb reconnect');


foreach (['ninth','duplicate','negative','metadata'] as $case) {
    $tampered=$plan;
    if ($case==='ninth') { $tampered['entries'][]=$tampered['entries'][0]; $expected='exact_eight_required'; }
    elseif ($case==='duplicate') { $tampered['entries'][1]['ngo_id']=$tampered['entries'][0]['ngo_id']; $expected='candidate_duplicate'; }
    elseif ($case==='negative') { $tampered['entries'][0]['ngo_id']=-1; $expected='candidate_id_invalid'; }
    else { $tampered['entries'][0]['name']='Unapproved name'; $expected='approved_metadata_changed'; }
    unset($tampered['digest']); $tampered['digest']=JyskNgoEnrollment::digest($tampered);
    blocked(fn()=>$op->apply($tampered,$tampered['digest']),$expected);
    check(snapshot() === $before,'invalid fixed plan never writes: '.$case);
}
// Every one of the 17 writes can fail without partial catalog/flags/reservation.
foreach (['sharity_ngo_catalog','sharity_ngo_campaign_flags','sharity_ngo_enrollment_reservations'] as $suffix) {
    $ids = $suffix === 'sharity_ngo_enrollment_reservations' ? [null] : array_column($plan['entries'],'ngo_id');
    foreach ($ids as $id) {
        $condition = $id === null ? "NEW.reservation_key='impactshop_jysk_ngo_enrollment_v1'" : 'NEW.sharity_ngo_id=' . $id;
        q("CREATE TRIGGER jysk_enroll_fault BEFORE INSERT ON jysk_enroll_" . $suffix . " FOR EACH ROW BEGIN IF " . $condition . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'; END IF; END");
        blocked(fn()=>$op->apply($plan,$plan['digest']), 'enrollment_sql_failed');
        check(snapshot() === $before, 'all writes rolled back at ' . $suffix);
        q('DROP TRIGGER jysk_enroll_fault');
    }
}
$second = connection();
$lock = 'ngo-enroll:' . substr(hash('sha256', 'jysk_sandbox:jysk_enroll_'),0,40);
$second->dbh->execute_query('SELECT GET_LOCK(?,0)',[$lock]);
blocked(fn()=>$op->apply($plan,$plan['digest']), 'catalog_writer_busy');
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]), 'catalog_writer_busy');
check(snapshot() === $before, 'shared lock prevents both writers');
$second->dbh->execute_query('SELECT RELEASE_LOCK(?)',[$lock]);
$uncertain=new LossyEnrollmentConnection('127.0.0.1',$cfg['user'],$cfg['password'],$cfg['database'],$cfg['port']);
$uncertainOp=new JyskNgoEnrollment($uncertain,$target); $uncertain->faultMode='commit';
blocked(fn()=>$uncertainOp->apply($plan,$plan['digest']),'commit_uncertain_run_status_with_new_connection');
$freshWp = connection(); $fresh = new JyskNgoEnrollment($freshWp->dbh, $target);
check($fresh->status($plan)['count'] === 8, 'independent connection sees eight');
$after = snapshot(); check($fresh->apply($plan,$plan['digest'])['writes'] === false && snapshot() === $after, 'repeat apply readback only');
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_catalog')->fetch_row()[0] === 9, 'catalog delta exactly eight');
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_campaign_flags')->fetch_row()[0] === 9, 'flags delta exactly eight');
check((int) q("SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_enrollment_reservations WHERE reservation_key='impactshop_jysk_ngo_enrollment_v1'")->fetch_row()[0] === 1, 'one durable transactional reservation');
check(impactshop_vb2026_upsert_catalog_rows([$row],[]) === true, 'later unchanged CSV allowed');
check(snapshot() === $after, 'unchanged CSV preserves all eight and history');
check(json_decode($before,true)['options'] === json_decode($after,true)['options'],'complete enrollment leaves MyISAM options unchanged');
$entry = $plan['entries'][0];
$collisions = [
    [['Azonosító'=>(string)$entry['ngo_id'],'Név'=>'Other NGO'],[]],
    [['Azonosító'=>'901','Név'=>$entry['name']],[]],
    [['Azonosító'=>'902','Név'=>'Renamed'],['any'=>['name'=>'Renamed','slug'=>$entry['slug']]]],
];
foreach ($collisions as [$conflict,$dataset]) {
    blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([['Azonosító'=>'900','Név'=>'Earlier in batch'], $conflict],$dataset), 'catalog_reserved_identity_conflict');
    check(snapshot() === $after, 'collision rejects even earlier batch writes');
}
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row,$row],[]), 'catalog_batch_identity_conflict');
$raw = q('SELECT payload FROM jysk_enroll_sharity_ngo_enrollment_reservations WHERE reservation_key=?',[JyskNgoEnrollment::RESERVATION_KEY])->fetch_row()[0];
q('UPDATE jysk_enroll_sharity_ngo_enrollment_reservations SET payload=? WHERE reservation_key=?',['{}',JyskNgoEnrollment::RESERVATION_KEY]);
$invalidState = snapshot();
blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$row],[]),'catalog_reservation_invalid');
check(snapshot() === $invalidState, 'invalid reservation fails closed');
q('UPDATE jysk_enroll_sharity_ngo_enrollment_reservations SET payload=? WHERE reservation_key=?',[$raw,JyskNgoEnrollment::RESERVATION_KEY]);
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_catalog c JOIN jysk_enroll_sharity_ngo_campaign_flags f ON f.sharity_ngo_id=c.sharity_ngo_id WHERE c.is_active=1 AND f.allow_public_listing=1 AND f.campaign_state=?',['active'])->fetch_row()[0] === 9, 'actual public join sees eight');
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_campaign_flags WHERE allow_user_selection=1')->fetch_row()[0] === 1, 'VB selection not expanded');
check((int) q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_campaign_flags WHERE is_featured=1 OR is_race_visible=1')->fetch_row()[0] === 0, 'no race rights');
$original = json_decode($before,true); $final = json_decode(snapshot(),true);
foreach (['votes','preferences','ledger','jysk_payload'] as $table) { check($original[$table] === $final[$table], 'historical ' . $table . ' unchanged'); }
$GLOBALS['csvBody']="Azonosító,Név,Státusz\n";
for ($n=1000;$n<1025;$n++) { $GLOBALS['csvBody'].="$n,Fixture $n,Aktív\n"; }
$GLOBALS['csvBody'].=$entry['ngo_id'].",Conflict,Aktív\n";
$beforeFailedSync=snapshot(); impactshop_vb2026_ensure_catalog_sync();
check(snapshot() === $beforeFailedSync && $transientWrites === 0,'full sync failure cannot set success freshness');
// Preparation can cache data before a later failed transaction; do not claim total options immutability.
$GLOBALS['prepareCacheWrite']=true;
impactshop_vb2026_ensure_catalog_sync();
check((int)q("SELECT COUNT(*) FROM jysk_enroll_options WHERE option_name='_transient_fixture_dataset'")->fetch_row()[0]===1,'preparation cache survives later reservation conflict');
q("DELETE FROM jysk_enroll_options WHERE option_name='_transient_fixture_dataset'");
$GLOBALS['prepareCacheWrite']=false;
check(snapshot()===$beforeFailedSync && $transientWrites===0,'transaction failure leaves data and own success freshness unchanged');
// Successful commit followed by failed cache update remains committed and reservation-protected.
$GLOBALS['csvBody']="Azonosító,Név,Státusz\n";
for ($n=1000;$n<1025;$n++) { $GLOBALS['csvBody'].="$n,Fixture $n,Aktív\n"; }
$cacheLog=$base . '/cache-errors.log'; file_put_contents($cacheLog,'');
$previousErrorLog=ini_set('error_log',$cacheLog);
foreach (['false','throw','success'] as $cacheMode) {
    $GLOBALS['cacheMode']=$cacheMode;
    impactshop_vb2026_ensure_catalog_sync();
    check((int)q('SELECT COUNT(*) FROM jysk_enroll_sharity_ngo_catalog WHERE sharity_ngo_id BETWEEN 1000 AND 1024')->fetch_row()[0]===25,'catalog commit survives cache outcome '.$cacheMode);
    check($fresh->status($plan)['count']===8,'reservation survives cache outcome '.$cacheMode);
    if ($cacheMode !== 'success') {
        check((int)q("SELECT COUNT(*) FROM jysk_enroll_options WHERE option_name LIKE '_transient%'")->fetch_row()[0]===0,'failed cache is not success freshness');
        blocked(fn()=>impactshop_vb2026_upsert_catalog_rows([$conflict],$dataset),'catalog_reserved_identity_conflict');
    }
}
ini_set('error_log',$previousErrorLog);
check(substr_count(file_get_contents($cacheLog),'catalog_sync_cache_refresh_failed')===2,'false and thrown cache failures have separate sanitized diagnostics');
check((int)q("SELECT COUNT(*) FROM jysk_enroll_options WHERE option_name IN ('_transient_impactshop_vb2026_ngo_catalog_last_sync_v1','_transient_timeout_impactshop_vb2026_ngo_catalog_last_sync_v1')")->fetch_row()[0]===2,'successful cache updates only existing freshness pair');
// Remove only these local cache-probe fixtures before the unchanged public reader checks.
q('DELETE FROM jysk_enroll_sharity_ngo_catalog WHERE sharity_ngo_id BETWEEN 1000 AND 1024');
q('DELETE FROM jysk_enroll_sharity_ngo_campaign_flags WHERE sharity_ngo_id BETWEEN 1000 AND 1024');
$transientWrites=0;
define('IMPACTSHOP_NGO_CATALOG_RECOVERY', true);
$beforeRecovery = snapshot(); $beforeHttp = $httpReads;
impactshop_vb2026_ensure_catalog_sync();
check(snapshot() === $beforeRecovery && $httpReads === $beforeHttp && $transientWrites === 0, 'recovery disables sync before any IO');


// Exact-file recovery preserves readback but cannot authorize another apply.
$runtimePath=$plan['input']['runtime']['path'];
$normalBytes=file_get_contents($runtimePath);
$recoveryBytes=str_replace("declare(strict_types=1);", "declare(strict_types=1);\ndefine('IMPACTSHOP_NGO_CATALOG_RECOVERY', true);",$normalBytes);
file_put_contents($runtimePath,$recoveryBytes);
check($op->status($plan)['count'] === 8,'applied status survives runtime recovery replacement');
$cliAfterRecovery=cli('status',$base . '/plan.json');
check($cliAfterRecovery[0] === 0 && $cliAfterRecovery[1]['count'] === 8,'full CLI status after recovery');
blocked(fn()=>$op->apply($plan,$plan['digest']),'source_hash_mismatch_runtime');
$recoveryPlan=$plan; $recoveryPlan['input']['runtime']['sha256']=hash_file('sha256',$runtimePath);
unset($recoveryPlan['digest']); $recoveryPlan['digest']=JyskNgoEnrollment::digest($recoveryPlan);
blocked(fn()=>$op->apply($recoveryPlan,$recoveryPlan['digest']),'normal_runtime_required');
file_put_contents($runtimePath,$normalBytes);
check(snapshot() === $beforeRecovery,'recovery readback leaves all rows unchanged');
$public=impactshop_vb2026_catalog_query(['campaign'=>'vb2026','active_only'=>true,'featured_only'=>false,'q'=>'','category'=>'','legal_status'=>'','city'=>''],1,100);
check(count($public['items']) === 9,'actual catalog callable reads all nine');
foreach ($plan['entries'] as $entry) {
    $items=array_values(array_filter($public['items'],static fn($r)=>$r['ngo_id'] === $entry['ngo_id']));
    check(count($items) === 1 && $items[0]['slug'] === $entry['slug'],'public canonical identity matches once');
}
// Read-only existing fund reader, copied from the pinned Card source; not deployed or edited.
$fundFile=$base . '/impactshop-sharity-vote-cast.php';
if (!is_file($fundFile) || !hash_equals(getenv('JYSK_FUND_READER_SHA256') ?: '',hash_file('sha256',$fundFile))) {
    throw new RuntimeException('pinned_fund_reader_required');
}
require $fundFile;
q('DROP TABLE IF EXISTS jysk_enroll_sharity_fund_campaigns');
q("CREATE TABLE jysk_enroll_sharity_fund_campaigns (campaign_id VARCHAR(40),wallet_scope VARCHAR(40),starts_at_utc DATETIME,voting_closes_at_utc DATETIME,financial_status VARCHAR(40),active_slot INT,status VARCHAR(40)) ENGINE=InnoDB");
q("INSERT INTO jysk_enroll_sharity_fund_campaigns VALUES ('isolated-fixture','sharity_shared','2026-01-01','2026-12-31','fixture',1,'active')");
$fund=ImpactShop_Sharity_Vote_Cast::get_eligible_ngos(new WP_REST_Request());
check($fund->status === 200 && $fund->data['data']['count'] === 9,'actual eligible reader sees all nine');
foreach ($plan['entries'] as $entry) {
    check(count(array_filter($fund->data['data']['items'],static fn($r)=>$r['ngo_id'] === (string)$entry['ngo_id'])) === 1,'fund eligible exact identity');
}
q('DROP TABLE jysk_enroll_sharity_fund_campaigns');
check(snapshot() === $beforeRecovery,'reader calls preserve old identities and stored data');

echo json_encode(['checks'=>$checks,'status'=>'passed','database'=>'isolated-tmpfs','liveWrites'=>false]), "\n";
