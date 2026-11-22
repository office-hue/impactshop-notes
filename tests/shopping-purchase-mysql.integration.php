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
    public function get_param($key) { return $this->body[$key] ?? null; }
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
function home_url($path='') { return 'https://app.sharity.hu'.$path; }
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
$tables=['impactshop_affiliate_intents','impact_sharity_web_sessions','owner_grants','sharity_ngo_catalog','user_points','options'];
check(array_diff($wpdb->get_col('SHOW TABLES'),array_map(fn($t)=>'impactshop_test_'.$t,$tables))===[],'no foreign tables');
foreach($tables as $table) sql('DROP TABLE IF EXISTS impactshop_test_'.$table);
check(impactshop_sharity_affiliate_install_schema(),'canonical legacy intent schema');
check(impactshop_shopping_purchase_migrate(),'explicit canonical shopping migration');
check(impactshop_shopping_purchase_migrate(),'shopping migration idempotent');
sql('CREATE TABLE impactshop_test_impact_sharity_web_sessions(session_hash CHAR(64) PRIMARY KEY, pseudo_id VARCHAR(12), subject_ref VARCHAR(80), owner_grant_hash CHAR(64), status VARCHAR(24), issued_at_utc DATETIME NOT NULL, expires_at_utc DATETIME) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_owner_grants(grant_hash CHAR(64) PRIMARY KEY, pseudo_hash CHAR(64), state VARCHAR(24), revoked_at DATETIME NULL, expires_at DATETIME) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_options(option_name VARCHAR(191) PRIMARY KEY,option_value LONGTEXT NOT NULL) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_sharity_ngo_catalog(sharity_ngo_id BIGINT PRIMARY KEY, slug VARCHAR(128),name VARCHAR(128),is_active TINYINT) ENGINE=InnoDB');
sql('CREATE TABLE impactshop_test_user_points(pseudo_id VARCHAR(12) PRIMARY KEY,impactshop_subject_digest BINARY(32) UNIQUE NULL,current_level VARCHAR(24),created_at DATETIME,updated_at DATETIME) ENGINE=InnoDB');
$token='sw_session_'.str_repeat('A',43); $pseudo='abcdefgh1234'; $grant=str_repeat('b',64);
$wpdb->insert('impactshop_test_impact_sharity_web_sessions',['session_hash'=>hash('sha256',$token),'pseudo_id'=>$pseudo,'subject_ref'=>'hmac-sha256:'.str_repeat('c',64),'owner_grant_hash'=>$grant,'status'=>'active','issued_at_utc'=>gmdate('Y-m-d H:i:s'),'expires_at_utc'=>gmdate('Y-m-d H:i:s',time()+3600)]);
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
check($row['ngo_ref']==='ngo-seven' && $row['subject_ref']===impactshop_sharity_affiliate_subject_ref($pseudo)
    && $row['provider_scope_ref']==='26081','actual subject, NGO and server channel');
check(strtotime($row['delete_after'])-strtotime($row['created_at'])===3888000,'45 day retention');
check(callPurchase(payload(1))->get_data()['redirectUrl']===$first && countIntents()===1,'identical replay');
$p=payload(1);$p['ngoId']='8';check(callPurchase($p)->get_status()===409 && countIntents()===1,'changed NGO conflicts');
sql("UPDATE impactshop_test_impactshop_affiliate_intents SET intent_expires_at='2020-01-01'");check(callPurchase(payload(1))->get_status()===410,'expired confirmation');
// Two sessions for the same owner: no shared session-row lock can serialize these requests.
$token2='sw_session_'.str_repeat('B',43);
sql($wpdb->prepare("INSERT INTO impactshop_test_impact_sharity_web_sessions SELECT %s,pseudo_id,subject_ref,owner_grant_hash,status,issued_at_utc,expires_at_utc FROM impactshop_test_impact_sharity_web_sessions",hash('sha256',$token2)));
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
// CJ exercises the same real owner/session transaction and the existing one-use GET handoff.
putenv('CJ_PUBLISHER_ID=55555'); putenv('CJ_WEBSITE_ID=101302202');
$options['impactshop_sharity_affiliate_cj_canary_enabled']='1';
$review=['publisherId'=>'55555','websiteId'=>'101302202','advertiserId'=>'5824323','linkId'=>'15487360',
    'merchantUrl'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT,'reviewExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)];
function cjPayload($n) { return array_merge(payload($n),['merchantInput'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_MERCHANT,
    'partnerKey'=>'unice','programRef'=>IMPACTSHOP_SHARITY_AFFILIATE_CJ_PROGRAM,'providerKey'=>'cj','ngoId'=>'8']); }
function callCj($p) { return impactshop_sharity_affiliate_cj_issue(new WP_REST_Request($p,$GLOBALS['token'])); }
function handoffCj($url) { return impactshop_sharity_affiliate_cj_handoff(new WP_REST_Request(['handoffToken'=>basename(parse_url($url,PHP_URL_PATH))],'')); }
check(callCj(cjPayload(10))->get_status()===422 && countIntents()===3,'CJ without fresh review no write');
$options['impactshop_sharity_affiliate_cj_purchase_review']=$review;
$p=cjPayload(10);$p['merchantInput'].='?other=target';check(callCj($p)->get_status()===422 && countIntents()===3,'CJ exact reviewed product only');
$r=callCj(cjPayload(10));check($r->get_status()===200 && $r->get_data()['contract']==='shopping-confirmed-v1','CJ confirmed contract');
$cjUrl=$r->get_data()['data']['handoff_url'];
$row=$wpdb->get_row("SELECT * FROM impactshop_test_impactshop_affiliate_intents WHERE provider_key='cj'",ARRAY_A);
check($row['ngo_ref']==='ngo-eight' && $row['provider_scope_ref']==='55555:101302202' && $row['status']==='confirmed_cj_ready','CJ stores actual chosen NGO and account/site before outbound');
check(callCj(cjPayload(10))->get_data()['data']['handoff_url']===$cjUrl && countIntents()===4,'CJ duplicate issues one stable handoff');
$p=cjPayload(10);$p['ngoId']='7';check(callCj($p)->get_status()===409 && countIntents()===4,'CJ changed NGO conflicts');
$options['impactshop_sharity_affiliate_cj_purchase_review']['reviewExpiresAt']='2000-01-01T00:00:00Z';
check(handoffCj($cjUrl)->get_status()===409,'CJ review expiry before GET blocks outbound');
require __DIR__.'/fixtures/shopping-cj-legacy-handoff.php';
check(shopping_test_legacy_cj_handoff(new WP_REST_Request(['handoffToken'=>basename(parse_url($cjUrl,PHP_URL_PATH))],''))->get_status()===409,'exact base GET rejects new confirmed intent after review expiry');
$options['impactshop_sharity_affiliate_cj_purchase_review']=$review;
check(handoffCj($cjUrl)->get_status()===303 && handoffCj($cjUrl)->get_status()===409,'CJ one-use committed handoff');
$cjProviderToken=impactshop_sharity_affiliate_provider_token($row['activation_id']);
check(sharity_points_impactshop_attribution($cjProviderToken,'cj','unice')===hash('sha256',impactshop_sharity_affiliate_subject_ref($pseudo)),'CJ real point account binds selected owner');
$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM impactshop_test_impactshop_affiliate_intents WHERE activation_id=%s',$row['activation_id']),ARRAY_A);
check($row['ngo_ref']==='ngo-eight' && strtotime($row['delete_after'])-strtotime($row['redirected_at'])===3888000,'CJ selected NGO retained for original 45 days');
// Two independently authenticated sessions confirm the same CJ UUID concurrently.
$wpdb->close();$children=[];
for($i=0;$i<2;$i++) { $pid=pcntl_fork();check($pid>=0,'CJ fork');if($pid===0){$wpdb=connection();if($i===1)$token=$token2;$out=callCj(cjPayload(11));exit($out->get_status()===200?0:1);} $children[]=$pid; }
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'CJ concurrent response');}
$wpdb=connection();check(countIntents()===5,'concurrent CJ requests create one intent');
// A lost handoff COMMIT acknowledgement cannot release a provider redirect or repeat the transition.
$r=callCj(cjPayload(12));check($r->get_status()===200,'CJ acknowledgement fixture');$lostUrl=$r->get_data()['data']['handoff_url'];
$hit=false;$fault=function($query)use(&$hit){global $wpdb;if(!$hit && $query==='COMMIT'){$hit=true;mysqli_query($wpdb->dbh,'COMMIT');return 'SELECT * FROM deliberately_missing_cj_ack';}return $query;};
check(handoffCj($lostUrl)->get_status()===409 && $hit,'CJ lost handoff acknowledgement releases no URL');$fault=null;
check(handoffCj($lostUrl)->get_status()===409 && countIntents()===6,'CJ lost handoff never repeats outbound transition');
sql("UPDATE impactshop_test_impactshop_affiliate_intents SET intent_expires_at='2000-01-01' WHERE status='confirmed_cj_ready'");
check(impactshop_sharity_affiliate_retention_cleanup()===true && countIntents()===5,'existing cleanup removes expired confirmed pending state only');
// TT runs on this same isolated real wpdb/owner transaction; no provider request or live review.
require_once __DIR__.'/../wp-content/mu-plugins/impactshop-tradetracker.php';
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID=12345');
$ttStart=countIntents();
$ttReview=['customerId'=>'12345','siteId'=>'481183','campaignId'=>'18982','partnerKey'=>'vidaxl',
    'programRef'=>'tradetracker-18982','merchantUrl'=>'https://www.vidaxl.hu/black-friday',
    'qualificationRef'=>'synthetic-local-sql','referenceReportVerified'=>true,'donationCompatible'=>true,
    'retentionSeconds'=>2592000,'requiredRetentionSeconds'=>2592000,'reviewExpiresAt'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)];
function ttPayload($n) { return array_merge(payload($n),['merchantInput'=>'https://www.vidaxl.hu/black-friday',
    'partnerKey'=>'vidaxl','programRef'=>'tradetracker-18982','providerKey'=>'tradetracker']); }
function callTt($p) { return impactshop_shopping_tt_activate(new WP_REST_Request($p,$GLOBALS['token'])); }
check(callTt(ttPayload(20))->get_status()===403 && countIntents()===$ttStart,'TT default OFF no write');
$options['impactshop_sharity_affiliate_tt_outbound_enabled']='1';
check(callTt(ttPayload(20))->get_status()===422 && countIntents()===$ttStart,'TT missing review no write');
$options['impactshop_sharity_affiliate_tt_purchase_review']=$ttReview;
function syncTtOptions() {
    global $wpdb,$options;
    foreach (['impactshop_sharity_affiliate_runtime_enabled','impactshop_sharity_shopping_purchase_enabled','impactshop_sharity_affiliate_tt_outbound_enabled','impactshop_sharity_affiliate_tt_purchase_review'] as $name) {
        check($wpdb->replace('impactshop_test_options',['option_name'=>$name,'option_value'=>is_array($options[$name])?serialize($options[$name]):$options[$name]])!==false,'TT own uncached option fixture');
    }
}
syncTtOptions();
$r=callTt(ttPayload(20));check($r->get_status()===200,'TT real locked transaction');$ttUrl=$r->get_data()['redirectUrl'];
$row=$wpdb->get_row("SELECT * FROM impactshop_test_impactshop_affiliate_intents WHERE provider_key='tradetracker'",ARRAY_A);
parse_str(parse_url($ttUrl,PHP_URL_QUERY),$ttQuery);
check($ttQuery['c']==='18982' && $ttQuery['m']==='12' && $ttQuery['a']==='481183' && $ttQuery['u']==='/black-friday','TT own reviewed merchant route');
check(hash('sha256',ImpactShop_TradeTracker::token_for_reference($ttQuery['r']))===$row['provider_token_hash'],'TT reference retains original SAT1 hash');
check($row['provider_scope_ref']==='12345:481183' && $row['ngo_ref']==='ngo-seven' && $row['subject_ref']===impactshop_sharity_affiliate_subject_ref($pseudo),'TT freezes scope owner and chosen NGO');
check(strtotime($row['delete_after'])-strtotime($row['created_at'])===2592000,'TT approved row retention');
check(callTt(ttPayload(20))->get_data()['redirectUrl']===$ttUrl && countIntents()===$ttStart+1,'TT stable UUID replay');
$p=ttPayload(20);$p['ngoId']='8';check(callTt($p)->get_status()===409,'TT cannot switch chosen NGO');
$p=ttPayload(20);$p['authoritySnapshotId']='sharity-shopping-production-v2-20261103-01';check(callTt($p)->get_status()===409,'TT cannot rebind original authority snapshot');
$options['impactshop_sharity_affiliate_tt_purchase_review']['retentionSeconds']=3000000;
check(callTt(ttPayload(20))->get_status()===409,'TT changed review no extension');
check($wpdb->get_var($wpdb->prepare('SELECT delete_after FROM impactshop_test_impactshop_affiliate_intents WHERE activation_id=%s',$row['activation_id']))===$row['delete_after'],'TT stored expiry unchanged');
$options['impactshop_sharity_affiliate_tt_purchase_review']=$ttReview;
sql($wpdb->prepare("UPDATE impactshop_test_impactshop_affiliate_intents SET provider_scope_ref='999:481183' WHERE activation_id=%s",$row['activation_id']));
check(callTt(ttPayload(20))->get_status()===409,'TT persisted foreign scope rejected');
sql($wpdb->prepare('UPDATE impactshop_test_impactshop_affiliate_intents SET provider_scope_ref=%s,delete_after=%s WHERE activation_id=%s',$row['provider_scope_ref'],'2000-01-01',$row['activation_id']));
check(callTt(ttPayload(20))->get_status()===403,'TT expired mapping not reissued');
sql($wpdb->prepare('UPDATE impactshop_test_impactshop_affiliate_intents SET delete_after=%s WHERE activation_id=%s',$row['delete_after'],$row['activation_id']));
sql("UPDATE impactshop_test_options SET option_value='0' WHERE option_name='impactshop_sharity_affiliate_tt_outbound_enabled'");
check(get_option('impactshop_sharity_affiliate_tt_outbound_enabled')==='1' && callTt(ttPayload(20))->get_status()===403,'TT cached ON cannot bypass fresh SQL revocation');
syncTtOptions();
$wpdb->close();$children=[];
for($i=0;$i<2;$i++){ $pid=pcntl_fork();check($pid>=0,'TT fork');if($pid===0){$wpdb=connection();if($i===1)$token=$token2;$out=callTt(ttPayload(21));exit($out->get_status()===200?0:1);} $children[]=$pid; }
foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'TT concurrent response');}
$wpdb=connection();check(countIntents()===$ttStart+2,'TT concurrent sessions issue one intent');
$hit=false;$fault=function($query)use(&$hit,$ttStart){global $wpdb;if(!$hit&&$query==='COMMIT'&&countIntents()===$ttStart+3){$hit=true;mysqli_query($wpdb->dbh,'COMMIT');return 'SELECT * FROM deliberately_missing_tt_ack';}return $query;};
$r=callTt(ttPayload(22));check($r->get_status()===200 && $hit,'TT lost actual COMMIT resolved by durable readback');$fault=null;
check(countIntents()===$ttStart+3 && callTt(ttPayload(22))->get_data()['redirectUrl']===$r->get_data()['redirectUrl'],'TT lost acknowledgement does not create second intent');
$hit=false;$fault=function($query)use(&$hit,$ttStart){if(!$hit&&$query==='COMMIT'&&countIntents()===$ttStart+4){$hit=true;return 'SELECT * FROM deliberately_missing_tt_uncommitted';}return $query;};
check(callTt(ttPayload(23))->get_status()===503 && $hit,'TT absent COMMIT releases no URL');$fault=null;
check(countIntents()===$ttStart+3,'TT absent COMMIT preserves intent count');
$options['impactshop_sharity_affiliate_tt_outbound_enabled']='0';check(callTt(ttPayload(20))->get_status()===403,'TT revocation denies replay');
sql("DELETE FROM impactshop_test_impactshop_affiliate_intents WHERE provider_key='tradetracker'");
check(countIntents()===$ttStart,'TT removes only own synthetic rows before legacy failure fixtures');
// Stale/owner-less sessions, lane OFF and absent column all fail without mutation.
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET owner_grant_hash=NULL");check(callPurchase(payload(7))->get_status()===401,'ownerless session denied');
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET owner_grant_hash='".$grant."',expires_at_utc='2020-01-01'");check(callPurchase(payload(7))->get_status()===401,'expired session denied');
sql("UPDATE impactshop_test_impact_sharity_web_sessions SET expires_at_utc=DATE_ADD(NOW(),INTERVAL 1 HOUR)");
$options['impactshop_sharity_shopping_purchase_enabled']='0';check(callPurchase(payload(7))->get_status()===403,'lane OFF');$options['impactshop_sharity_shopping_purchase_enabled']='1';
sql('ALTER TABLE impactshop_test_impactshop_affiliate_intents DROP COLUMN request_payload_hash');check(callPurchase(payload(7))->get_status()===503,'missing column no HTTP migration');
check(countIntents()===5,'failure paths preserve existing intents');
$other->close();$wpdb->close();echo "shopping purchase SQL: PASS ($checks checks)\n";
