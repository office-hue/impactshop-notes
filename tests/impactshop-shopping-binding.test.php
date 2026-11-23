<?php
/** Synthetic SQL boundary fixtures, using real capture/transaction and migration entrypoints. No MySQL. */
define('ABSPATH', __DIR__ . '/'); define('ARRAY_A', 'ARRAY_A');
$options = ['sharity_points_impactshop_schema'=>'2026-10-29-simple1', 'sharity_points_impactshop_producer_enabled'=>'1',
    'sharity_points_legacy_consumer_enabled'=>'0','sharity_points_impactshop_cutover_utc'=>'2026-10-29 00:00:00'];
function get_option($k,$d=false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k,$v,...$args) { $GLOBALS['options'][$k]=$v; return true; }
function add_option($k,$v,...$args) { if(isset($GLOBALS['options'][$k])) return false; $GLOBALS['options'][$k]=$v; return true; }
function delete_option($k) { unset($GLOBALS['options'][$k]); }
function wp_json_encode($v) { return json_encode($v); }
function add_action(...$a) {} function add_filter(...$a) {}
$checks=0;
function check($ok,$message) { $GLOBALS['checks']++; if(!$ok) throw new RuntimeException($message); }
require __DIR__.'/../wp-content/mu-plugins/sharity-points-money.php';
require __DIR__.'/../wp-content/mu-plugins/sharity-points-impactshop-attribution.php';
require __DIR__.'/../wp-content/mu-plugins/sharity-points-ledger-sync.php';
require __DIR__.'/../wp-content/mu-plugins/sharity-points-impactshop-manager.php';
require __DIR__.'/../wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php';
$source=file_get_contents(__DIR__.'/../wp-content/mu-plugins/sharity-points.php');
eval(substr($source,strpos($source,'function sharity_points_migrate_impactshop(')));
class BindingFixtureDb {
    public $prefix='fixture_', $last_error='', $reconnect_retries=5, $roots=[], $events=[], $intents=[], $alters=[], $failAlter=false;
    public $columns=['impactshop_point_settlements'=>['root_id','subject_digest','awarded_points','reversed_points','forgiven_points','version','needs_reconciliation'],
        'impactshop_point_changes'=>['root_id','logical_id','payload_digest','event_json','actual_delta','run_id','resolution_digest','outcome_json']];
    public $types=['impactshop_subject_digest'=>['Type'=>'binary(32)','Null'=>'YES'], 'type'=>['Type'=>"enum('purchase','purchase_reversal')",'Null'=>'NO']];
    public function prepare($q,...$a) { return ['q'=>$q,'a'=>$a]; }
    public function get_charset_collate() { return ''; }
    public function get_var($s) {
        $q=is_array($s)?$s['q']:$s; $a=is_array($s)?$s['a']:[];
        if($q==='SELECT CONNECTION_ID()') return '42';
        if(str_contains($q,'SELECT ENGINE')) return 'InnoDB';
        if(str_contains($q,'SELECT pseudo_id')) return 'abcdefgh1234';
        if(str_contains($q,'SHOW INDEX')) return 'uniq_impactshop_subject';
        if(str_contains($q,'SHOW COLUMNS')) return 'impactshop_subject_digest';
        if(str_contains($q,'SELECT root_id')) return $this->events[$a[0]]['root_id'] ?? null;
        throw new RuntimeException('Unhandled scalar fixture query: '.$q);
    }
    public function get_row($s,...$rest) {
        $q=is_array($s)?$s['q']:$s; $a=is_array($s)?$s['a']:[];
        if(str_contains($q,'SHOW COLUMNS')) {
            $name=$a[0]??''; if($name==='' && preg_match("/LIKE '([^']+)'/",$q,$m)) $name=$m[1];
            return isset($this->types[$name])?['Field'=>$name]+$this->types[$name]:null;
        }
        if(str_contains($q,'FROM fixture_impactshop_affiliate_intents')) return $this->intents[$a[0]] ?? null;
        if(str_contains($q,'FROM fixture_impactshop_point_settlements')) return $this->roots[$a[0]] ?? null;
        if(str_contains($q,'WHERE id=')) return $this->events[$a[0]] ?? null;
        if(str_contains($q,'FROM fixture_impactshop_point_changes')) {
            foreach($this->events as $id=>$e) if($e['logical_id']===$a[0] && $e['payload_digest']===$a[1]) return ['id'=>$id,'status'=>$e['status']];
            return null;
        }
        throw new RuntimeException('Unhandled row fixture query: '.$q);
    }
    public function get_col($q) {
        foreach($this->columns as $table=>$columns) if(str_contains($q,$table)) return $columns;
        return [];
    }
    public function get_results($q,...$rest) { return [['Column_name'=>'logical_id','Non_unique'=>0],['Column_name'=>'payload_digest','Non_unique'=>0]]; }
    public function query($s) {
        $q=is_array($s)?$s['q']:$s; $a=is_array($s)?$s['a']:[];
        if(str_starts_with($q,'ALTER TABLE')) {
            $this->alters[]=$q;
            if($this->failAlter) return false;
            foreach(['attribution_binding_json'=> 'text','provider_scope_ref'=>'varchar(96)','request_payload_hash'=>'char(64)'] as $name=>$type) {
                if(str_contains($q,'ADD COLUMN '.$name.' ')) $this->types[$name]=['Type'=>$type,'Null'=>'YES'];
            }
            return 1;
        }
        if(str_contains($q,'INSERT INTO fixture_impactshop_point_settlements')) {
            if(!isset($this->roots[$a[0]])) $this->roots[$a[0]]=['root_id'=>$a[0],'provider'=>$a[1],'namespace'=>$a[2],
                'subject_hex'=>$a[3]?:null,'subject_digest'=>$a[3]?:null,'original_purchase_at'=>$a[4]?:null,'merchant'=>$a[5],
                'attribution_binding_json'=>($a[7]??'')===''?null:$a[7],'state'=>'pending','version'=>0,'awarded_points'=>0];
            return 1;
        }
        if(str_contains($q,"SET state='manual'")) { $this->roots[$a[0]]['state']='manual'; return 1; }
        if(str_contains($q,'INSERT INTO fixture_impactshop_point_changes')) {
            foreach($this->events as $e) if($e['logical_id']===$a[1] && $e['payload_digest']===$a[2]) return 1;
            $id=count($this->events)+1; $this->events[$id]=['id'=>$id,'root_id'=>$a[0],'logical_id'=>$a[1],'payload_digest'=>$a[2],'event_json'=>$a[3],'status'=>'queued']; return 1;
        }
        return 1; // CREATE/transaction/session statements have no fixture side effects.
    }
}
$wpdb=new BindingFixtureDb();
check(sharity_points_migrate_impactshop()['success'],'old existing points schema migrates');
check(count($wpdb->alters)===1 && str_contains($wpdb->alters[0],'ADD COLUMN attribution_binding_json TEXT NULL'),'real ALTER required for existing table');
check(get_option('sharity_points_impactshop_binding_schema')==='1','verified points binding readiness');
check(get_option('sharity_points_impactshop_schema')==='2026-10-29-simple1','old schema marker retained');
check(sharity_points_migrate_impactshop()['success'] && count($wpdb->alters)===1,'second points migration idempotent');
check(impactshop_shopping_purchase_migrate(),'old affiliate schema migrates both columns');
check(count($wpdb->alters)===3,'affiliate migration alters missing columns');
check(impactshop_shopping_purchase_migrate() && count($wpdb->alters)===3,'affiliate rerun idempotent');
unset($wpdb->types['provider_scope_ref']);
check(impactshop_shopping_purchase_migrate() && count($wpdb->alters)===4,'partial affiliate migration completes only missing column');
$wpdb->types['provider_scope_ref']['Type']='varchar(32)';
check(!impactshop_shopping_purchase_migrate() && get_option('impactshop_sharity_affiliate_binding_schema')==='0','wrong affiliate type fails readiness');
$wpdb->types['provider_scope_ref']=['Type'=>'varchar(96)','Null'=>'YES'];
check(impactshop_shopping_purchase_migrate(),'affiliate recovers after actual type corrected');
$wpdb->types['attribution_binding_json']['Null']='NO';
check(!sharity_points_migrate_impactshop()['success'] && !sharity_points_impactshop_binding_ready(),'nonnullable points binding rejected');
unset($wpdb->types['attribution_binding_json']); $wpdb->failAlter=true;
check(!sharity_points_migrate_impactshop()['success'] && !sharity_points_impactshop_binding_ready(),'failed ALTER cannot assert readiness');
$wpdb->failAlter=false;
check(sharity_points_migrate_impactshop()['success'],'incomplete points migration recovers');
$token='sat1_'.str_repeat('A',43); $digest=hash('sha256','hmac-sha256:'.str_repeat('b',64));
$intent=['subject_ref'=>'hmac-sha256:'.str_repeat('b',64),'provider_key'=>'dognet','partner_key'=>'shop',
    'status'=>'redirected','delete_after'=>gmdate('Y-m-d H:i:s',time()+3600),'provider_scope_ref'=>'channel',
    'provider_program_ref'=>'dognet:shop','ngo_ref'=>'ngo-one','authority_snapshot_ref'=>'snapshot-one'];
$wpdb->intents[hash('sha256',$token)]=$intent;
$input=['authority'=>true,'provider'=>'dognet','namespace'=>'impactshop:dognet:channel','purchase_id'=>'order',
    'event_id'=>'sale','revision'=>'A','merchant'=>'shop','original_purchase_at'=>'2026-10-29T10:00:00Z',
    'kind'=>'award','donation_amount_huf'=>'100','authority_ref'=>'fixture-authority','provider_token'=>$token];
$prepared=sharity_points_impactshop_prepare($input); $rootId=$prepared['event']['root_id'];
$r=sharity_points_impactshop_capture($input); check($r['success'],'real capture stores first root'); $eventId=$r['event_id'];
$original=$wpdb->roots[$rootId]['attribution_binding_json']; $bound=json_decode($original,true);
check($bound===['v'=>1,'provider'=>'dognet','scopeRef'=>'channel','programRef'=>'dognet:shop','merchant'=>'shop',
    'ngoRef'=>'ngo-one','subjectDigest'=>$digest,'tokenDigest'=>hash('sha256',$token),'snapshotRef'=>'snapshot-one'],'canonical full first receipt');
check(strlen($original)<=2048 && !str_contains($original,$token),'bounded metadata contains no raw bearer');
check(!str_contains($wpdb->events[$eventId]['event_json'],'ngoRef'),'binding is root metadata only');
check(sharity_points_impactshop_decide($wpdb->roots[$rootId],$prepared['event'],0)['delta']===100,'unchanged economics');
// Simulate the already-applied event; real manager duplicate path must have no award.
$wpdb->events[$eventId]['status']='applied'; $wpdb->roots[$rootId]['state']='awarded'; $wpdb->roots[$rootId]['awarded_points']=100;
$wpdb->intents=[];
$after=sharity_points_impactshop_prepare($input);
check($after['event']['subject_digest']===null && $after['binding']===null,'cleanup removes transient evidence');
check($after['event']['payload_digest']===$prepared['event']['payload_digest'],'cleanup never changes economic digest');
check(sharity_points_impactshop_capture($input)['event_id']===$eventId && count($wpdb->events)===1,'cleanup reimport remains same event');
check((new Sharity_Impactshop_Points_Manager())->apply_impactshop_event($eventId,str_repeat('a',32))['duplicate'],'real consumer duplicate has no new point movement');
check($wpdb->roots[$rootId]['attribution_binding_json']===$original && $wpdb->roots[$rootId]['state']==='awarded','cleanup preserves original receipt and state');
$refund=$input; $refund['kind']='refund'; $refund['event_id']='refund'; $refund['donation_amount_huf']='';
check(sharity_points_impactshop_capture($refund)['success'],'refund captured after correlation cleanup');
$refundDecision=sharity_points_impactshop_decide($wpdb->roots[$rootId],sharity_points_impactshop_prepare($refund)['event'],20);
check($refundDecision['delta']===-20 && $refundDecision['forgiven']===80,'cleanup refund uses original bound root and unchanged capped reversal');
foreach(['ngo_ref'=>'ngo-two','provider_program_ref'=>'program-two','authority_snapshot_ref'=>'snapshot-two',
    'provider_scope_ref'=>'other-channel','subject_ref'=>'hmac-sha256:'.str_repeat('c',64)] as $field=>$value) {
    $wpdb->roots[$rootId]['state']='awarded'; $wpdb->intents[hash('sha256',$token)]=array_replace($intent,[$field=>$value]);
    check(sharity_points_impactshop_capture($input)['success'] && $wpdb->roots[$rootId]['state']==='manual','conflicting '.$field.' manual');
    check($wpdb->roots[$rootId]['attribution_binding_json']===$original,'conflict cannot overwrite '.$field);
}
$wpdb->intents=[]; $wpdb->roots[$rootId]['state']='awarded'; $changed=$input; $changed['provider_token']='sat1_'.str_repeat('B',43);
check(sharity_points_impactshop_capture($changed)['success'] && $wpdb->roots[$rootId]['state']==='manual','different bearer after cleanup manual');
check(sharity_points_impactshop_capture($input)['event_id']===$eventId && $wpdb->roots[$rootId]['state']==='manual','cleanup reimport cannot clear existing manual conflict');
$badScope=$input; $badScope['namespace']='impactshop:dognet:wrong-channel'; $badScope['event_id']='scope-mismatch';
$wpdb->intents[hash('sha256',$token)]=$intent;
check(sharity_points_impactshop_capture($badScope)['success'],'scope-mismatch evidence retained');
$badRoot=sharity_points_impactshop_prepare($badScope)['event']['root_id'];
check($wpdb->roots[$badRoot]['state']==='manual','first capture cross-channel binding manual');
$options['sharity_points_impactshop_binding_schema']='0';
check(sharity_points_impactshop_capture($input)['error']==='binding_schema_unavailable','scoped capture cannot discard binding during incomplete migration');
$options['sharity_points_impactshop_binding_schema']='1';
$wpdb->intents[hash('sha256',$token)]['provider_program_ref']=str_repeat('x',161);
check(sharity_points_impactshop_prepare($input)['event']['subject_digest']===null,'malformed scoped receipt cannot award identity-only');
$wpdb->roots[$rootId]['attribution_binding_json']=null; $wpdb->roots[$rootId]['state']='awarded'; $wpdb->intents[hash('sha256',$token)]=$intent;
check(sharity_points_impactshop_capture($input)['success'] && $wpdb->roots[$rootId]['attribution_binding_json']===null,'historical null root never backfilled');
$options['sharity_points_impactshop_binding_schema']='0'; $options['impactshop_sharity_affiliate_binding_schema']='0';
$legacy=$input; $legacy['purchase_id']='legacy-order'; $legacy['event_id']='legacy-sale';
$wpdb->intents[hash('sha256',$token)]['provider_scope_ref']=null;
check(sharity_points_impactshop_capture($legacy)['success'],'old markers preserve legacy capture');
$firstScoped=$input; $firstScoped['purchase_id']='first-scoped'; $firstScoped['event_id']='first-scoped-sale';
$wpdb->intents[hash('sha256',$token)]=$intent;
$beforeRoots=count($wpdb->roots); $beforeEvents=count($wpdb->events);
check(sharity_points_impactshop_capture($firstScoped)['error']==='binding_schema_unavailable','both markers off cannot hide existing scoped intent');
$options['sharity_points_impactshop_binding_schema']='1';
check(sharity_points_impactshop_capture($firstScoped)['error']==='binding_schema_unavailable','affiliate migration reset cannot create subject-only first root');
check(count($wpdb->roots)===$beforeRoots && count($wpdb->events)===$beforeEvents,'partial rollout writes no root or economic event');
$wpdb->intents[hash('sha256',$token)]['provider_scope_ref']=null;
$legacy['purchase_id']='partial-legacy'; $legacy['event_id']='partial-legacy-sale';
check(sharity_points_impactshop_capture($legacy)['success'],'affiliate marker off still permits actual null-scope legacy intent');
$legacyRoot=sharity_points_impactshop_prepare($legacy)['event']['root_id'];
check($wpdb->roots[$legacyRoot]['attribution_binding_json']===null,'fixture mirrors SQL NULLIF for missing binding');
check($wpdb->reconnect_retries===5,'real transaction manager restores connection guard');
function wp_salt($scheme) { return str_repeat('fixture', 8); }
function is_ssl() { return true; }
function wp_validate_redirect($url, $fallback) { return $url; }
function remove_query_arg($keys, $url) { return explode('?', $url, 2)[0]; }
function nocache_headers() {}
function wp_die($message, $title, $args) { throw new RuntimeException(strip_tags($message), $args['response']); }
require __DIR__.'/../wp-content/mu-plugins/impactshop-ngo-selector.php';
$wpdb = new class {
    public $last_error = '', $prefix = 'fixture_';
    public function prepare($format, $id) { return [$format, $id]; }
    public function get_results($query) {
        return ($query[1] ?? '') === '101' ? [['sharity_ngo_id'=>'101','slug'=>'confirmed-ngo']] : [];
    }
};
$_SERVER['REQUEST_URI'] = '/go/fixture-shop?d1=999';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['d1'] = '999';
check(!impactshop_dognet_verified_choice_enabled('fixture-shop'), 'Dognet choice default off');
$options['impactshop_dognet_verified_ngo_shops'] = ['fixture-shop'];
check(impactshop_dognet_verified_choice_enabled('fixture-shop') && !impactshop_dognet_verified_choice_enabled('other-shop'), 'reviewed shop switch only');
$body = '101.'.(time()+60).'.'.str_repeat('c',32);
$signature = hash_hmac('sha256', "dognet-ngo-choice-v1\0".$body, wp_salt('auth'));
$_COOKIE['__Host-sharity-shop-ngo'] = $body.'.'.$signature;
check(impactshop_ngo_selector_dognet_choice()['id'] === '101', 'active signed choice wins over raw d1');
$_COOKIE['__Host-sharity-shop-ngo'] = '102'.substr($_COOKIE['__Host-sharity-shop-ngo'], 3);
try {
    impactshop_ngo_selector_dognet_choice();
    check(false, 'tampered choice rejected');
} catch (RuntimeException $e) {
    check($e->getCode() === 503, 'tampered cookie or raw d1 cannot bypass selector');
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['sharity_chosen_ngo'=>'101','sharity_choice_token'=>str_repeat('b',64)];
try {
    impactshop_ngo_selector_dognet_choice();
    check(false, 'unauthorized POST rejected');
} catch (RuntimeException $e) {
    check($e->getCode() === 400, 'POST must match the chooser CSRF cookie');
}
echo "Shopping binding fixtures: {$checks} passed (synthetic SQL boundary, no MySQL)\n";
