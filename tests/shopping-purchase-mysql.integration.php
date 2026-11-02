<?php
/** Local-only integration: real wpdb, owner-session authority, point binding and affiliate runtime.
 * Same isolated configuration convention as impactshop-points-mysql.integration.php.
 * Additional SHOPPING_SESSION_SOURCE is the hash-pinned canonical ai-agent server patch.
 */
declare(strict_types=1);
$configFile = getenv('IMPACTSHOP_MYSQL_CONFIG') ?: '';
$wpdbFile = getenv('IMPACTSHOP_WPDB_FILE') ?: '';
$sessionFile = getenv('SHOPPING_SESSION_SOURCE') ?: '';
if (getenv('IMPACTSHOP_MYSQL_SANDBOX_CONFIRM') !== 'LOCAL_IMPACTSHOP_ONLY'
    || !is_file($configFile) || is_link($configFile) || (fileperms($configFile) & 0777) !== 0600) {
    throw new RuntimeException('Explicit private local sandbox configuration required');
}
$cfg = json_decode(file_get_contents($configFile), true, 16, JSON_THROW_ON_ERROR);
if (($cfg['host'] ?? '') !== '127.0.0.1' || ($cfg['database'] ?? '') !== 'impactshop_sandbox'
    || ($cfg['user'] ?? '') !== 'impactshop_test' || !is_int($cfg['port']) || $cfg['port'] < 1024
    || !is_file($wpdbFile) || is_link($wpdbFile) || hash_file('sha256', $wpdbFile) !== ($cfg['wpdbSha256'] ?? '')
    || !is_file($sessionFile) || is_link($sessionFile) || hash_file('sha256', $sessionFile) !== ($cfg['sessionSha256'] ?? '')) {
    throw new RuntimeException('Refusing nonlocal or unverified input');
}
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_DEBUG', false); define('WP_DEBUG_DISPLAY', false); define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
$checks = 0; $fault = null;
function check($value, $label) { $GLOBALS['checks']++; if (!$value) throw new RuntimeException('FAIL: ' . $label); }
function add_action(...$args) {} function add_filter(...$args) {}
function apply_filters($name, $value, ...$args) { return $name === 'query' && is_callable($GLOBALS['fault']) ? ($GLOBALS['fault'])($value) : $value; }
function has_filter(...$args) { return false; } function did_action($name) { return 1; }
function wp_load_translations_early() {} function __($s) { return $s; }
function _doing_it_wrong($fn, $message, $version) { throw new RuntimeException($fn . ': ' . strip_tags($message)); }
function wp_debug_backtrace_summary(...$args) { return 'isolated-shopping-test'; }
function mbstring_binary_safe_encoding() {} function reset_mbstring_encoding() {}
function absint($value) { return abs((int)$value); }
function is_multisite() { return false; } function wp_salt(...$args) { return str_repeat('s',64); }
function current_time($format, ...$args) { return gmdate($format === 'mysql' ? 'Y-m-d H:i:s' : $format); }
function wp_json_encode($v, $flags=0) { return json_encode($v,$flags); }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error {}
class WP_REST_Request {
    public function __construct(public array $body, public string $token) {}
    public function get_json_params() { return $this->body; }
    public function get_header($name) { return $name === 'authorization' ? 'Bearer ' . $this->token : ($name === 'x-sharity-service-authorization' ? 'Bearer ' . str_repeat('k',40) : ''); }
}
class WP_REST_Response {
    public function __construct(private $data, private $status=200) {}
    public function get_data() { return $this->data; } public function get_status() { return $this->status; }
    public function header(...$args) {}
}
$options=['impactshop_sharity_affiliate_runtime_enabled'=>'1','impactshop_sharity_shopping_purchase_enabled'=>'1',
    'impactshop_sharity_web_session_service_token'=>str_repeat('k',40),'sharity_points_impactshop_schema'=>'2026-10-29-simple1'];
function get_option($key,$default=false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value,...$args) { $GLOBALS['options'][$key]=$value; return true; }
function impactshop_identity_owner_grant_table() { return $GLOBALS['wpdb']->prefix . 'owner_grants'; }
function impactshop_identity_owner_pseudo_hash($pseudo) { return hash('sha256',$pseudo); }
function impactshop_identity_grant_storage_ready() { return true; }
function isb_get_shops() { return [['shop_slug'=>'test-shop','site'=>'https://shop.example','dognet_base'=>'https://go.dognet.com/?cid=357&chid=26081&refid=publisher','network_source'=>'dognet','deeplink_param'=>'url']]; }
require $wpdbFile;
require $sessionFile;
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-impactshop-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-ledger-sync.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-impactshop-attribution.php';
require __DIR__ . '/../wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php';
function connection() {
    global $cfg;
    $db=new wpdb($cfg['user'],$cfg['password'],$cfg['database'],'127.0.0.1:'.$cfg['port']);
    $db->suppress_errors(true); $db->set_prefix('impactshop_test_');
    check($db->ready,'database ready'); return $db;
}
function sql($query) { global $wpdb; $r=$wpdb->query($query); check($r!==false && $wpdb->last_error==='','sandbox SQL'); return $r; }
function dbDelta($query) { sql(preg_replace('/\)\s+DEFAULT CHARACTER SET.*$/s', ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $query)); }
$wpdb=connection();
$tables=['impactshop_affiliate_intents','impact_sharity_web_sessions','owner_grants','sharity_ngo_catalog','user_points'];
check(array_diff($wpdb->get_col('SHOW TABLES'),array_map(fn($t)=>'impactshop_test_'.$t,$tables))===[],'no foreign tables');
foreach($tables as $table) sql('DROP TABLE IF EXISTS impactshop_test_'.$table);
check(impactshop_sharity_affiliate_install_schema(),'canonical legacy intent schema');
sql('ALTER TABLE impactshop_test_impactshop_affiliate_intents ADD COLUMN request_payload_hash CHAR(64) NULL');
sql('CREATE TABLE impactshop_test_impact_sharity_web_sessions(session_hash CHAR(64) PRIMARY KEY, pseudo_id VARCHAR(12), subject_ref VARCHAR(80), owner_grant_hash CHAR(64), status VARCHAR(24), expires_at_utc DATETIME) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_owner_grants(grant_hash CHAR(64) PRIMARY KEY, pseudo_hash CHAR(64), state VARCHAR(24), revoked_at DATETIME NULL, expires_at DATETIME) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_sharity_ngo_catalog(sharity_ngo_id BIGINT PRIMARY KEY, slug VARCHAR(128),name VARCHAR(128),is_active TINYINT) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_user_points(pseudo_id VARCHAR(12) PRIMARY KEY,impactshop_subject_digest BINARY(32) UNIQUE NULL,current_level VARCHAR(24),created_at DATETIME,updated_at DATETIME) ENGINE=InnoDB');
$token='sw_session_'.str_repeat('A',43); $pseudo='abcdefgh1234'; $grant=str_repeat('b',64);
$wpdb->insert('impactshop_test_impact_sharity_web_sessions',['session_hash'=>hash('sha256',$token),'pseudo_id'=>$pseudo,'subject_ref'=>'hmac-sha256:'.str_repeat('c',64),'owner_grant_hash'=>$grant,'status'=>'active','expires_at_utc'=>gmdate('Y-m-d H:i:s',time()+3600)]);
$wpdb->insert('impactshop_test_owner_grants',['grant_hash'=>$grant,'pseudo_hash'=>hash('sha256',$pseudo),'state'=>'active','expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)]);
sql("INSERT INTO impactshop_test_sharity_ngo_catalog VALUES (7,'ngo-seven','NGO Seven',1),(8,'ngo-eight','NGO Eight',1)");
function payload($n) { return ['authoritySnapshotId'=>'sharity-shopping-production-v2-20261102-01','confirmationId'=>sprintf('12345678-1234-4123-8123-%012d',$n),
    'disclosureVersion'=>'shopping-purchase-v1','merchantInput'=>'https://shop.example/product?sku=a&sku=b&variant.id=blue&x=%252F',
    'ngoId'=>'7','partnerKey'=>'test-shop','programRef'=>'dognet:test-shop','providerKey'=>'dognet']; }
function callPurchase($p) { return impactshop_shopping_dognet_activate(new WP_REST_Request($p,$GLOBALS['token'])); }
function countIntents() { return (int)$GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM impactshop_test_impactshop_affiliate_intents'); }
$r=callPurchase(payload(1)); check($r->get_status()===200,'real owner session issues URL');
$first=$r->get_data()['redirectUrl']; parse_str(parse_url($first,PHP_URL_QUERY),$q);
check($q['url']===payload(1)['merchantInput'],'product query preserved');
check(sharity_points_impactshop_attribution($q['d1'],'dognet','test-shop')===hash('sha256',impactshop_sharity_affiliate_subject_ref($pseudo)),'real points account binds SAT1');
$row=$wpdb->get_row('SELECT * FROM impactshop_test_impactshop_affiliate_intents',ARRAY_A);
check($row['ngo_ref']==='ngo-seven' && $row['subject_ref']===impactshop_sharity_affiliate_subject_ref($pseudo),'actual subject and NGO');
check(strtotime($row['delete_after'])-strtotime($row['created_at'])===3888000,'45 day retention');
check(callPurchase(payload(1))->get_data()['redirectUrl']===$first && countIntents()===1,'identical replay');
$p=payload(1);$p['ngoId']='8';check(callPurchase($p)->get_status()===409 && countIntents()===1,'changed NGO conflicts');
sql("UPDATE impactshop_test_impactshop_affiliate_intents SET intent_expires_at='2020-01-01'");check(callPurchase(payload(1))->get_status()===410,'expired confirmation');
// Two sessions for the same owner: no shared session-row lock can serialize these requests.
$token2='sw_session_'.str_repeat('B',43);
sql($wpdb->prepare("INSERT INTO impactshop_test_impact_sharity_web_sessions SELECT %s,pseudo_id,subject_ref,owner_grant_hash,status,expires_at_utc FROM impactshop_test_impact_sharity_web_sessions",hash('sha256',$token2)));
check(function_exists('pcntl_fork'),'fork available');$wpdb->close();$children=[];
for($i=0;$i<2;$i++) { $pid=pcntl_fork();check($pid>=0,'fork');if($pid===0){$wpdb=connection();if($i===1)$token=$token2;$out=callPurchase(payload(2));exit($out->get_status()===200?0:1);} $children[]=$pid; }
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'concurrent response');}
$wpdb=connection();check(countIntents()===2,'two connections create one intent');
// Revoke after initial authentication but before the transaction's owner recheck.
$other=connection();$hit=false;
$fault=function($query)use(&$hit,$other){if(!$hit && $query==='START TRANSACTION'){$hit=true;$other->query("UPDATE impactshop_test_owner_grants SET state='revoked',revoked_at=NOW()");}return $query;};
check(callPurchase(payload(3))->get_status()===401,'revoked owner rechecked');$fault=null;
check(countIntents()===2,'revocation writes no intent');sql("UPDATE impactshop_test_owner_grants SET state='active',revoked_at=NULL");
// An NGO deactivated between read authentication and locked selection must fail.
$hit=false;$fault=function($query)use(&$hit,$other){if(!$hit && $query==='START TRANSACTION'){$hit=true;$other->query('UPDATE impactshop_test_sharity_ngo_catalog SET is_active=0 WHERE sharity_ngo_id=7');}return $query;};
check(callPurchase(payload(4))->get_status()===422,'fresh NGO lock rejects deactivation');$fault=null;
sql('UPDATE impactshop_test_sharity_ngo_catalog SET is_active=1 WHERE sharity_ngo_id=7');
// Server commits but reply is lost: only durable readback may return the URL.
$hit=false;$fault=function($query)use(&$hit){global $wpdb;if(!$hit && $query==='COMMIT' && (int)$wpdb->get_var("SELECT COUNT(*) FROM impactshop_test_impactshop_affiliate_intents")===3){$hit=true;mysqli_query($wpdb->dbh,'COMMIT');return 'SELECT * FROM deliberately_missing_ack';}return $query;};
$r=callPurchase(payload(5));$fault=null;check($hit && $r->get_status()===200 && countIntents()===3,'lost ACK resolved by durable readback');
check(callPurchase(payload(5))->get_data()===$r->get_data(),'lost ACK replay same result');
// Real connection death just before intent INSERT. wpdb must not reconnect/autocommit.
$hit=false;$fault=function($query)use(&$hit,$other){global $wpdb;if(!$hit && str_starts_with($query,'INSERT INTO `impactshop_test_impactshop_affiliate_intents`')){$hit=true;$other->query('KILL CONNECTION '.(int)mysqli_thread_id($wpdb->dbh));}return $query;};
$r=callPurchase(payload(6));$fault=null;check($hit && $r->get_status()===503,'dead connection gives no URL');
$wpdb=connection();check(countIntents()===3,'connection death rolls back intent');
// Stale/owner-less sessions, lane OFF and absent column all fail without mutation.
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET owner_grant_hash=NULL");check(callPurchase(payload(7))->get_status()===401,'ownerless session denied');
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET owner_grant_hash='".$grant."',expires_at_utc='2020-01-01'");check(callPurchase(payload(7))->get_status()===401,'expired session denied');
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET expires_at_utc=DATE_ADD(NOW(),INTERVAL 1 HOUR)");
$options['impactshop_sharity_shopping_purchase_enabled']='0';check(callPurchase(payload(7))->get_status()===403,'lane OFF');$options['impactshop_sharity_shopping_purchase_enabled']='1';
sql('ALTER TABLE impactshop_test_impactshop_affiliate_intents DROP COLUMN request_payload_hash');check(callPurchase(payload(7))->get_status()===503,'missing column no HTTP migration');
check(countIntents()===3,'failure paths preserve existing intents');
$other->close();$wpdb->close();echo "shopping purchase SQL: PASS ($checks checks)\n";
