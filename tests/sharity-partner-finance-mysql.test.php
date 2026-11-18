<?php
/** Real isolated MySQL + actual B/Offerwall transaction owner; WordPress API shim only. */
declare(strict_types=1);
$source = getenv('SHARITY_BANK_PAYOUT_SOURCE') ?: '';
$host = getenv('OW_TEST_DB_HOST') ?: '';
$name = getenv('OW_TEST_DB_NAME') ?: '';
$port = (int) getenv('OW_TEST_DB_PORT');
if (!extension_loaded('mysqli') || !extension_loaded('sodium') || !is_file($source)
    || !is_file(dirname($source) . '/impactshop-sharity-offerwall-v2-finance.php') || $host === '' || $name === '' || !$port) {
    fwrite(STDERR, "SKIP: explicit local DB configuration and actual B source required\n"); exit(77);
}
if ($host !== '127.0.0.1' || !preg_match('/^owv2_test_[A-Za-z0-9_]+$/D', $name) || $port <= 1024) {
    throw new RuntimeException('Refusing nonlocal or non-test database');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$actions = []; $options = [];
function add_action($hook, $callback, ...$unused): void { $GLOBALS['actions'][] = [$hook, $callback]; }
function get_option($key, $default=false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$unused): bool { $GLOBALS['options'][$key]=$value; return true; }
final class PfdWpdb
{
    public mysqli $dbh;
    public string $prefix = 'pfd_';
    public string $last_error = '';
    public function __construct()
    {
        $this->dbh = new mysqli(getenv('OW_TEST_DB_HOST'),getenv('OW_TEST_DB_USER'),getenv('OW_TEST_DB_PASSWORD') ?: '',getenv('OW_TEST_DB_NAME'),(int)getenv('OW_TEST_DB_PORT'));
        $this->dbh->set_charset('utf8mb4');
    }
    public function get_charset_collate(): string { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'; }
    public function prepare(string $sql, ...$values): string
    {
        $i=0; $db=$this->dbh;
        $out=preg_replace_callback('/%[sd]/',static function($m) use (&$i,$values,$db):string {
            if (!array_key_exists($i,$values)) throw new RuntimeException('Fixture placeholder count');
            $v=$values[$i++]; return $m[0]==='%d' ? (string)(int)$v : "'".$db->real_escape_string((string)$v)."'";
        },$sql);
        if($i!==count($values)) throw new RuntimeException('Fixture unused parameter');
        return $out;
    }
    public function query(string $sql) { $this->last_error=''; return $this->dbh->query($sql); }
    public function get_results(string $sql, ...$unused): array { return $this->query($sql)->fetch_all(MYSQLI_ASSOC); }
    public function get_row(string $sql, ...$unused): ?array { return $this->query($sql)->fetch_assoc(); }
    public function get_var(string $sql) { $row=$this->query($sql)->fetch_row(); return $row[0] ?? null; }
}
$wpdb = new PfdWpdb();
$checks=0;
function check($condition, string $label): void { $GLOBALS['checks']++; if(!$condition) throw new RuntimeException('FAIL: '.$label); }
function count_table(string $suffix): int { return (int)$GLOBALS['wpdb']->get_var('SELECT COUNT(*) FROM pfd_'.$suffix); }
function entries(): array { return array_map(static fn($r)=>json_decode($r['data_json'],true,64,JSON_THROW_ON_ERROR),$GLOBALS['wpdb']->get_results('SELECT data_json FROM pfd_sharity_bp_entries')); }
function totals(): array { $sum=[];foreach(entries() as $e){$k=$e['ngo_id'].':'.$e['currency'];$sum[$k]=($sum[$k]??0)+$e['delta_minor'];}ksort($sum);return $sum; }
require dirname($source) . '/impactshop-sharity-offerwall-v2-finance.php';
require $source;
require __DIR__.'/../wp-content/mu-plugins/impactshop-partner-finance.php';
ImpactShop_Partner_Finance::register();
$tables=[];
foreach($wpdb->get_results("SHOW TABLES LIKE 'pfd\\_%'") as $row){$t=(string)array_values($row)[0];check((bool)preg_match('/^pfd_[a-z0-9_]+$/D',$t),'own isolated prefix');$tables[]=$t;}
foreach($tables as $t)$wpdb->query('DROP TABLE '.$t);
$directory=realpath(sys_get_temp_dir()).'/pfd-'.bin2hex(random_bytes(8));
mkdir($directory,0700);
$policyFile=$directory.'/policy.json';
define('SHARITY_BANK_PAYOUT_POLICY_FILE',$policyFile);
$pair=sodium_crypto_sign_keypair();$secret=sodium_crypto_sign_secretkey($pair);
$account=str_repeat('b',64);
$partner=['revision'=>'contract1','provider'=>'dognet','bank_account_id'=>$account,'provider_scope_ref'=>'26081','source_kind'=>'operator_evidence','ngo_rate_bps'=>6000,'rounding'=>'per_transaction_floor_minor','allow_operator_evidence'=>true];
$policy=['schema_version'=>2,'amount_scale'=>2,'mode'=>'test','enabled'=>false,'target'=>'local-fixture','legal_entity'=>'fixture-entity',
    'accounts'=>[$account=>['legal_entity'=>'fixture-entity','currencies'=>['HUF','EUR'],'aliases'=>[['bank_source'=>'fixture-bank','account_fingerprint'=>str_repeat('a',64)]]]],
    'recipients'=>[], 'keys'=>['fixture'=>['actor'=>'fixture-collector','roles'=>['collector','approver'],'grant_ids'=>['operator','direct','dog'],'public_key'=>base64_encode(sodium_crypto_sign_publickey($pair))]],
    'partners'=>['shop'=>$partner,'shop-other'=>$partner,'direct-shop'=>array_replace($partner,['provider'=>'partner','source_kind'=>'partner_tx','allow_operator_evidence'=>false]),
        'dog-shop'=>array_replace($partner,['source_kind'=>'dognet_intent','provider_scope_ref'=>'26081','allow_operator_evidence'=>false])]];
$policy['keys']['collector']=['actor'=>'collector-only','roles'=>['collector'],'grant_ids'=>['operator'],'public_key'=>base64_encode(sodium_crypto_sign_publickey($pair))];
$policy['operation_grants']=[];
foreach(['operator'=>'shop','direct'=>'direct-shop','dog'=>'dog-shop'] as $grant=>$partnerId){
    $rule=ImpactShop_Partner_Finance::partner_policy($policy,['partner_id'=>$partnerId,'policy_revision'=>'contract1']);
    $policy['operation_grants'][$grant]=['operation'=>'partner-statement','provider'=>$rule['provider'],'provider_scope_ref'=>$rule['provider_scope_ref'],'source_policy_sha256'=>hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical($rule))];
}
function write_policy(): void { global $policyFile,$policy;file_put_contents($policyFile,json_encode($policy,JSON_THROW_ON_ERROR));chmod($policyFile,0600); }
write_policy();
function request_plan(array $statement, string $id): array
{
    global $policy;
    $grantId=null;foreach($policy['operation_grants'] as $idKey=>$grant){$scope=array_intersect_key($grant,array_flip(['provider','provider_scope_ref','source_policy_sha256']));if(ImpactShop_Sharity_Bank_Payout::canonical($scope)===ImpactShop_Sharity_Bank_Payout::canonical($statement['financial_scope'])){$grantId=$idKey;break;}}
    if($grantId===null)throw new RuntimeException('Fixture exact grant missing');
    return ImpactShop_Sharity_Bank_Payout::plan(['operation'=>'partner-statement','grant_id'=>$grantId,'command_id'=>$id,'actor'=>'fixture-collector','key_id'=>'fixture','expires_at'=>time()+300,'data'=>$statement]);
}
function apply_plan(array $plan): array
{
    global $secret;
    return ImpactShop_Sharity_Bank_Payout::apply($plan,sodium_crypto_sign_detached(ImpactShop_Sharity_Bank_Payout::signing_domain($plan).ImpactShop_Sharity_Bank_Payout::canonical($plan),$secret));
}
function run_statement(array $statement,string $id): array { return apply_plan(request_plan($statement,$id)); }
function fixture(string $id, string $partner='shop', string $currency='EUR'): array
{
    global $policy;
    $digest=hash('sha256','source:'.$id);
    $rule=ImpactShop_Partner_Finance::partner_policy($policy,['partner_id'=>$partner,'policy_revision'=>'contract1']);
    return ['partner_id'=>$partner,'statement_id'=>$id,'revision'=>1,'previous_revision'=>0,'bank_account_id'=>$rule['bank_account_id'],'original_statement_key'=>null,'expected_revision'=>0,'financial_scope'=>['provider'=>$rule['provider'],'provider_scope_ref'=>$rule['provider_scope_ref'],'source_policy_sha256'=>hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical($rule))],'policy_revision'=>'contract1','currency'=>$currency,'net_minor'=>10001,
        'cash'=>['bank_source'=>'fixture-bank','account_fingerprint'=>str_repeat('a',64),'bank_tx_id'=>'bank:'.$id,'currency'=>$currency,'net_minor'=>10001,
            'evidence_ref'=>'bank-proof:'.$id,'parts'=>[['part_id'=>'affiliate','classification'=>'impact_shop','net_minor'=>10001]]],
        'part_id'=>'affiliate','evidence_ref'=>'statement-proof:'.$id,'source_digest'=>$digest,'lines'=>[
            ['provider_tx_id'=>$id.':tx-a','net_minor'=>4000,'attribution'=>['ngo_id'=>'ngo-a','evidence_ref'=>'allocation:'.$id,'source_digest'=>$digest]],
            ['provider_tx_id'=>$id.':tx-b','net_minor'=>6001,'attribution'=>['ngo_id'=>'ngo-b','evidence_ref'=>'allocation:'.$id,'source_digest'=>$digest]],
        ]];
}
function add_fixture_partner(string $id,array $rule,string $grant): void {
    global $policy;$policy['partners'][$id]=$rule;
    $selected=ImpactShop_Partner_Finance::partner_policy($policy,['partner_id'=>$id,'policy_revision'=>$rule['revision']]);
    $policy['operation_grants'][$grant]=['operation'=>'partner-statement','provider'=>$selected['provider'],'provider_scope_ref'=>$selected['provider_scope_ref'],'source_policy_sha256'=>hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical($selected))];
    $policy['keys']['fixture']['grant_ids'][]=$grant;write_policy();
}
function next_revision(array $s): array {
    $s['original_statement_key'] = $s['original_statement_key'] ?? hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical([$s['financial_scope']['provider'],$s['bank_account_id'],$s['partner_id'],$s['statement_id']]));
    $s['expected_revision']=$s['revision'];$s['previous_revision']=$s['revision'];$s['revision']++;return $s;
}
function rejected(array $statement,string $id,string $label): void
{
    $before=[count_table('sharity_partner_statements'),count_table('sharity_partner_transactions'),count_table('sharity_bp_entries'),count_table('sharity_bp_cash'),count_table('sharity_bp_claims')];
    $out=run_statement($statement,$id);
    check(!in_array($out['status'],['applied','already_applied'],true),$label.' rejected');
    check($before===[count_table('sharity_partner_statements'),count_table('sharity_partner_transactions'),count_table('sharity_bp_entries'),count_table('sharity_bp_cash'),count_table('sharity_bp_claims')],$label.' atomic no mutation');
}
try {
    check(!ImpactShop_Partner_Finance::schema_ready(),'schema initially absent');
    foreach(ImpactShop_Sharity_Offerwall_V2_Finance::schema_sql() as $sql)$wpdb->query($sql);
    check(ImpactShop_Sharity_Bank_Payout::migrate()['schema_ready'],'actual B schema');
    check(ImpactShop_Partner_Finance::migrate()['schema_ready'],'explicit partner migration');
    check(ImpactShop_Partner_Finance::migrate()['schema_ready'],'idempotent partner migration');
    try { ImpactShop_Sharity_Bank_Payout::transaction(static fn()=>ImpactShop_Partner_Finance::migrate());check(false,'nested DDL rejected'); }
    catch(RuntimeException $e){check($e->getMessage()==='partner_schema_migration_inside_transaction','no DDL inside owner transaction');}
    try { ImpactShop_Sharity_Bank_Payout::transaction(static fn()=>ImpactShop_Partner_Finance::handle(['data'=>fixture('unsigned')],$GLOBALS['policy']));check(false,'unsigned rejected'); }
    catch(RuntimeException $e){check($e->getMessage()==='signed_bank_operation_required','unsigned even held/zero statement rejected');}
    $scopeFixture=fixture('scope-test');
    foreach (['provider'=>'partner','provider_scope_ref'=>'wrong','source_policy_sha256'=>str_repeat('f',64)] as $field=>$wrong) {
        $badPlan=request_plan($scopeFixture,'bad-scope-'.$field);$badPlan['data']['financial_scope'][$field]=$wrong;
        check(apply_plan($badPlan)['status']==='conflict','signed wrong exact scope '.$field);
    }
    $badPlan=request_plan($scopeFixture,'wrong-grant');$badPlan['grant_id']='direct';
    check(apply_plan($badPlan)['status']==='conflict','signed wrong operation grant');
    $badPlan=request_plan($scopeFixture,'collector-apply');$badPlan['key_id']='collector';$badPlan['actor']='collector-only';
    check(apply_plan($badPlan)['status']==='conflict','collector key cannot apply even with exact grant');
    check(count_table('sharity_partner_statements')===0&&count_table('sharity_bp_entries')===0&&count_table('sharity_bp_claims')===0,'planning and denied grants no financial writes');
    $bad=fixture('swapped-bank');$bad['bank_account_id']=$bad['financial_scope']['provider_scope_ref'];
    rejected($bad,'swapped-bank','bank identity not provider scope');
    $first=fixture('first');$plan=request_plan($first,'first-command');$r=apply_plan($plan);
    check($r['status']==='applied','actual signed first statement: '.json_encode($r));
    check(totals()===['ngo-a:EUR'=>2400,'ngo-b:EUR'=>3600],'approved 60%, two original NGOs');
    check(apply_plan($plan)['status']==='already_applied'&&count_table('sharity_bp_entries')===2,'signed command replay');
    check(run_statement($first,'first-replay')['status']==='applied'&&count_table('sharity_bp_entries')===2,'same statement revision new command no double grant');
    $firstStored=json_decode($wpdb->get_var("SELECT original_json FROM pfd_sharity_partner_statements LIMIT 1"),true,64,JSON_THROW_ON_ERROR);
    check($firstStored['financial_context']['version']===2&&$firstStored['financial_context']['claim']['account_id']===$account,'server-owned immutable rule and stored claim wrapper');
    check($firstStored['financial_context']['source_policy_sha256']===$first['financial_scope']['source_policy_sha256'],'original selected policy hash frozen');
    $bad=$first;$bad['lines'][0]['net_minor']++;$bad['lines'][1]['net_minor']--;rejected($bad,'changed-rev1','changed revision body');
    $second=next_revision($first);$second['net_minor']=9000;$second['lines'][1]['net_minor']=5000;
    check(run_statement($second,'correction')['status']==='applied','negative signed correction');
    check(totals()===['ngo-a:EUR'=>2400,'ngo-b:EUR'=>3000]&&count_table('sharity_partner_statements')===2,'cumulative delta and immutable original history');
    $bad=next_revision($second);$bad['lines'][0]['attribution']['ngo_id']='ngo-c';rejected($bad,'remap','NGO remap');
    $bad=next_revision($second);$bad['lines'][0]['net_minor']=4001;$bad['net_minor']++;rejected($bad,'growing-capacity','original line capacity');
    $bad=next_revision($second);$bad['lines'][0]['provider_tx_id']='new-tx';rejected($bad,'changing-set','original transaction set');
    $bad=fixture('other-statement','shop-other');$bad['lines'][0]['provider_tx_id']='first:tx-a';rejected($bad,'duplicate-provider-tx','provider transaction across partners');
    $held=fixture('held');$held['lines'][1]['attribution']=[];
    $r=run_statement($held,'held');check($r['status']==='applied'&&$r['lines'][1]['held']===true,'one missing mapping held');
    check($r['lines'][0]['ngo_minor']===2400&&$r['lines'][1]['ngo_minor']===0,'no held amount redistribution');
    $held=next_revision($held);$held['lines'][1]['attribution']=fixture('held')['lines'][1]['attribution'];
    check(run_statement($held,'resolve-held')['status']==='applied','later signed evidence resolves held line');
    $frozen=fixture('frozen');$r=run_statement($frozen,'frozen');check($r['status']==='applied','frozen-context setup');
    $savedPolicy=$policy;$staleFirst=fixture('stale-rule');
    $policy['partners']['shop']['ngo_rate_bps']=5000;write_policy();
    rejected($staleFirst,'stale-first-rule','first issue requires current selected rule hash');
    $frozen=next_revision($frozen);$frozen['net_minor']=9000;$frozen['lines'][1]['net_minor']=5000;
    $claimsBefore=count_table('sharity_bp_claims');$r=run_statement($frozen,'frozen-rate-correction');
    check($r['status']==='applied'&&$r['lines'][1]['ngo_minor']===3000,'correction keeps original 60% despite current 50%');
    unset($policy['partners']['shop']);$policy['accounts'][$account]['aliases'][0]['account_fingerprint']=str_repeat('d',64);write_policy();
    $frozen=next_revision($frozen);$frozen['net_minor']=8000;$frozen['lines'][1]['net_minor']=4000;
    $r=run_statement($frozen,'frozen-without-current-policy');
    check($r['status']==='applied'&&$r['lines'][1]['ngo_minor']===2400,'original lookup precedes missing current policy and changed bank alias');
    check(count_table('sharity_bp_claims')===$claimsBefore,'corrections never reclaim cash under current aliases');
    $policy=$savedPolicy;write_policy();
    $sourceOriginal=fixture('source-owner');check(run_statement($sourceOriginal,'source-owner')['status']==='applied','canonical source ownership setup');
    $originalKey=next_revision($sourceOriginal)['original_statement_key'];
    $canonicalBefore=$wpdb->get_var($wpdb->prepare('SELECT tx_key FROM pfd_sharity_partner_transactions WHERE statement_key=%s ORDER BY provider_tx_id LIMIT 1',$originalKey));
    $rotatedAccount=str_repeat('e',64);$policy['accounts'][$rotatedAccount]=['legal_entity'=>'fixture-entity','currencies'=>['HUF','EUR'],'aliases'=>[['bank_source'=>'fixture-bank','account_fingerprint'=>str_repeat('f',64)]]];
    $rotatedRule=$policy['partners']['shop'];$rotatedRule['bank_account_id']=$rotatedAccount;
    add_fixture_partner('bank-shop',$rotatedRule,'bankrotate');
    $rotated=fixture('source-owner-rotated','bank-shop');$rotated['cash']['account_fingerprint']=str_repeat('f',64);
    foreach($rotated['lines'] as $i=>&$line)$line['provider_tx_id']=$sourceOriginal['lines'][$i]['provider_tx_id'];unset($line);
    rejected($rotated,'bank-rotated-source','same provider principal cannot remint on another receiving bank');
    $separateRule=$policy['partners']['shop'];$separateRule['provider_scope_ref']='independent-scope';
    add_fixture_partner('scope-shop',$separateRule,'separate-scope');
    $separate=fixture('source-owner-separate','scope-shop');foreach($separate['lines'] as $i=>&$line)$line['provider_tx_id']=$sourceOriginal['lines'][$i]['provider_tx_id'];unset($line);
    check(run_statement($separate,'separate-scope')['status']==='applied','proven independent provider scopes may reuse local transaction IDs');
    $sourceOriginal=next_revision($sourceOriginal);$sourceOriginal['net_minor']=9000;$sourceOriginal['lines'][1]['net_minor']=5000;
    check(run_statement($sourceOriginal,'source-owner-correction')['status']==='applied','correction retains frozen owned transaction references');
    check($wpdb->get_var($wpdb->prepare('SELECT tx_key FROM pfd_sharity_partner_transactions WHERE statement_key=%s ORDER BY provider_tx_id LIMIT 1',$originalKey))===$canonicalBefore,'correction preserves historical row and entry source identity');
    $historical=fixture('historical-row');foreach($historical['lines'] as &$line)$line['attribution']=[];unset($line);
    check(run_statement($historical,'historical-row')['status']==='applied','historical wrapped-row fixture');
    $historicalKey=next_revision($historical)['original_statement_key'];
    $historicalStored=json_decode($wpdb->get_var($wpdb->prepare('SELECT original_json FROM pfd_sharity_partner_statements WHERE statement_key=%s',$historicalKey)),true,64,JSON_THROW_ON_ERROR);
    unset($historicalStored['financial_context']['transaction_keys']);
    foreach($historical['lines'] as $line){$oldKey=hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical([$historical['financial_scope']['provider'],$account,$line['provider_tx_id']]));$wpdb->query($wpdb->prepare('UPDATE pfd_sharity_partner_transactions SET tx_key=%s WHERE statement_key=%s AND provider_tx_id=%s',$oldKey,$historicalKey,$line['provider_tx_id']));}
    $wpdb->query($wpdb->prepare('UPDATE pfd_sharity_partner_statements SET original_json=%s WHERE statement_key=%s',json_encode($historicalStored,JSON_THROW_ON_ERROR),$historicalKey));
    $historical=next_revision($historical);$resolved=fixture('historical-row');foreach($historical['lines'] as $i=>&$line)$line['attribution']=$resolved['lines'][$i]['attribution'];unset($line);
    check(run_statement($historical,'historical-row-correction')['status']==='applied','retained immutable context resolves exact legacy row ID');
    $historyStored=json_decode($wpdb->get_var($wpdb->prepare('SELECT original_json FROM pfd_sharity_partner_statements WHERE statement_key=%s ORDER BY revision DESC LIMIT 1',$historicalKey)),true,64,JSON_THROW_ON_ERROR);
    check($historyStored['financial_context']['transaction_keys'][$historical['lines'][0]['provider_tx_id']]===hash('sha256',ImpactShop_Sharity_Bank_Payout::canonical([$historical['financial_scope']['provider'],$account,$historical['lines'][0]['provider_tx_id']])),'legacy row reference frozen without rekey/history rewrite');
    $legacy=fixture('legacy-proof');check(run_statement($legacy,'legacy-proof')['status']==='applied','legacy proof fixture');
    $legacyKey=next_revision($legacy)['original_statement_key'];
    $wpdb->query($wpdb->prepare('UPDATE pfd_sharity_partner_statements SET original_json=%s WHERE statement_key=%s',json_encode($legacy,JSON_THROW_ON_ERROR),$legacyKey));
    $legacyBefore=$wpdb->get_var($wpdb->prepare('SELECT original_json FROM pfd_sharity_partner_statements WHERE statement_key=%s',$legacyKey));
    rejected(next_revision($legacy),'legacy-without-proof','legacy correction missing immutable policy evidence');
    $legacyReissue=fixture('legacy-source-reissue','scope-shop');foreach($legacyReissue['lines'] as $i=>&$line)$line['provider_tx_id']=$legacy['lines'][$i]['provider_tx_id'];unset($line);
    rejected($legacyReissue,'legacy-ownership-unknown','unproven legacy source scope cannot be guessed for a new source key');
    check($wpdb->get_var($wpdb->prepare('SELECT original_json FROM pfd_sharity_partner_statements WHERE statement_key=%s',$legacyKey))===$legacyBefore,'legacy original retained unchanged');
    $bad=fixture('bad-digest');$bad['lines'][1]['attribution']['source_digest']=str_repeat('f',64);rejected($bad,'bad-digest','source digest mismatch');
    $bad=fixture('unsupported');$bad['currency']='USD';rejected($bad,'unsupported','unsupported currency');
    $bad=fixture('account');$bad['cash']['account_fingerprint']=str_repeat('f',64);rejected($bad,'wrong-account','account binding');
    $eurBefore=totals();$huf=fixture('huf','shop','HUF');check(run_statement($huf,'huf')['status']==='applied','HUF bookkeeping scale100');
    check(totals()['ngo-a:HUF']===2400&&totals()['ngo-b:HUF']===3600,'fractional HUF entitlement preserved');
    $missing=fixture('missing-table','direct-shop');foreach($missing['lines']as &$line)$line['attribution']=[];unset($line);
    $r=run_statement($missing,'missing-table');check($r['status']==='applied'&&$r['lines'][0]['held']&&$r['lines'][1]['held'],'missing attribution table holds only lines');
    $wpdb->query("CREATE TABLE pfd_impact_partner_tx (partner_code VARCHAR(64),event_id VARCHAR(128),ngo_code VARCHAR(64),currency CHAR(3),payment_status VARCHAR(16),status VARCHAR(16),UNIQUE KEY identity (partner_code,event_id)) ENGINE=InnoDB");
    $direct=fixture('direct','direct-shop');foreach($direct['lines']as &$line){$line['attribution']=[];$wpdb->query($wpdb->prepare("INSERT INTO pfd_impact_partner_tx VALUES ('direct-shop',%s,'ngo-direct','EUR','paid','approved')",$line['provider_tx_id']));}unset($line);
    $r=run_statement($direct,'direct');check($r['status']==='applied'&&$r['lines'][0]['ngo_id']==='ngo-direct','locked existing partner attribution');
    $wpdb->query("CREATE TABLE pfd_impactshop_affiliate_intents (provider_token_hash CHAR(64) PRIMARY KEY,provider_key VARCHAR(32),partner_key VARCHAR(64),ngo_ref VARCHAR(128),provider_scope_ref VARCHAR(64),status VARCHAR(32),redirected_at DATETIME,delete_after DATETIME) ENGINE=InnoDB");
    $wpdb->query("INSERT INTO pfd_impactshop_affiliate_intents VALUES ('".str_repeat('c',64)."','dognet','dog-shop','ngo-dog','26081','redirected','2026-01-01','2026-01-02')");
    $dog=fixture('dog','dog-shop');foreach($dog['lines']as &$line)$line['attribution']=['provider_token_hash'=>str_repeat('c',64),'provider_scope_ref'=>'26081'];unset($line);
    $r=run_statement($dog,'dog');check($r['status']==='applied'&&$r['lines'][0]['ngo_id']==='ngo-dog','locked retained Dognet original, no display FX');
    $bad=fixture('dog-bad','dog-shop');foreach($bad['lines']as &$line)$line['attribution']=['provider_token_hash'=>str_repeat('c',64),'provider_scope_ref'=>'wrong'];unset($line);rejected($bad,'dog-bad','Dognet scope binding');
    $denied=fixture('operator-denied','dog-shop');foreach($denied['lines']as &$line)$line['attribution']['source_kind']='operator_evidence';unset($line);rejected($denied,'operator-denied','operator evidence disabled');
    $claimed=fixture('already-claimed');$cashPlan=ImpactShop_Sharity_Bank_Payout::plan(['operation'=>'cash-import','command_id'=>'legacy-claim','actor'=>'fixture-collector','key_id'=>'fixture','expires_at'=>time()+300,'data'=>['cash'=>$claimed['cash'],'part_id'=>'affiliate','channel'=>'legacy','claim_id'=>'legacy:once']]);
    check(apply_plan($cashPlan)['status']==='applied','preexisting original cash claim');rejected($claimed,'cash-reuse','same physical part other channel');
    $wpdb->query("CREATE TRIGGER pfd_fail_statement BEFORE INSERT ON pfd_sharity_partner_statements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic late failure'");
    rejected(fixture('late-failure'),'late-failure','failure after entries rolls back receipt and entitlement');$wpdb->query('DROP TRIGGER pfd_fail_statement');
    check(function_exists('pcntl_fork'),'native concurrency available');
    $race=fixture('race');$plans=[request_plan($race,'race-0'),request_plan($race,'race-1')];$wpdb->dbh->close();$children=[];
    for($i=0;$i<2;$i++){$pid=pcntl_fork();check($pid>=0,'fork');if($pid===0){$wpdb=new PfdWpdb();$r=apply_plan($plans[$i]);file_put_contents($directory.'/race-'.$i.'.json',json_encode($r));exit(in_array($r['status'],['applied','conflict'],true)?0:1);}$children[]=$pid;}
    foreach($children as $pid){pcntl_waitpid($pid,$wait);check(pcntl_wifexited($wait)&&pcntl_wexitstatus($wait)===0,'child completed');}
    $wpdb=new PfdWpdb();$statuses=[];for($i=0;$i<2;$i++)$statuses[]=json_decode(file_get_contents($directory.'/race-'.$i.'.json'),true)['status'];sort($statuses);
    check($statuses===['applied','conflict'],'parallel signed requests serialized with current prestate');
    check((int)$wpdb->get_var("SELECT COUNT(*) FROM pfd_sharity_partner_transactions WHERE provider_tx_id LIKE 'race:%'")===2,'race grants exactly one two-line statement');
    $wpdb->query('ALTER TABLE pfd_sharity_partner_transactions DROP COLUMN binding_json');
    check(!ImpactShop_Partner_Finance::schema_ready(),'partial migration schema not ready');
    rejected(fixture('partial-schema'),'partial-schema','handler never auto migrates');
    echo "Partner finance real MySQL: {$checks} checks; actual B signature and pinned owner; no provider or bank calls\n";
} finally {
    if(!isset($wpdb->dbh)||!$wpdb->dbh instanceof mysqli)$wpdb=new PfdWpdb();
    foreach($wpdb->get_results("SHOW TABLES LIKE 'pfd\\_%'") as $row){$table=(string)array_values($row)[0];if(preg_match('/^pfd_[a-z0-9_]+$/D',$table))$wpdb->query('DROP TABLE '.$table);}
    foreach(glob($directory.'/*')?:[]as$file)if(is_file($file)&&!is_link($file))unlink($file);
    rmdir($directory);$wpdb->dbh->close();
}
