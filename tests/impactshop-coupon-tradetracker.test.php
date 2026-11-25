<?php
/** Real feed functions; reuse the existing offline WP fixtures and Dognet/CJ regressions. */
require __DIR__ . '/impactshop-coupon-feed.test.php';
$ttBaseline = $assertions;
if (!class_exists('ImpactShop_TradeTracker', false)) {
    class ImpactShop_TradeTracker {
        public $requests = 0; public $bytes = 0; public $reads = []; public $qualifications = [];
        function __construct($customer = null, $secret = null) { $GLOBALS['tt_created']++; }
        function qualify_campaign($id) {
            if (!isset($this->qualifications[$id])) { $this->requests += $this->requests ? 1 : 3; $this->bytes += 300; }
            $this->qualifications[$id] = true;
            return ($GLOBALS['tt_campaign_responder'])($id, $this);
        }
        function get_coupon_page($resource, $id, $offset = 0, $limit = 100) {
            $this->reads[] = [$resource, $id, $offset, $limit]; $this->requests++; $this->bytes += 100;
            $rows = $offset === 0 ? [tt_row($resource, $id)] : [];
            $page = ['siteId' => '900001', 'campaign' => tt_campaign($id), 'resource' => $resource, 'offset' => $offset,
                'limit' => $limit, 'rows' => $rows, 'returned' => count($rows), 'responseBytes' => 100,
                'totalResponseBytes' => $this->bytes, 'requests' => $this->requests];
            return ($GLOBALS['tt_page_responder'])($page, $this);
        }
    }
}
function tt_map() {
    return ['qualification' => ack(), 'siteId' => '900001', 'dateTimezone' => 'Europe/Budapest',
        'nullStartMeansUnbounded' => true, 'nullEndMeansOngoing' => true, 'endDateInclusive' => true,
        'resourceMap' => ['voucher' => ['kind' => 'coupon', 'redeemMode' => 'code', 'applicability' => 'conditions_apply'],
            'offer' => ['kind' => 'sale', 'redeemMode' => 'automatic', 'applicability' => 'conditions_apply']]];
}
function tt_binding($partner = 'tt-fixture', $campaign = '700001') {
    return ['partnerKey' => $partner, 'providerKey' => 'tradetracker', 'programRef' => 'tt:' . $partner,
        'providerProgramId' => $campaign, 'siteOrChannelId' => '900001', 'publicationAllowed' => true,
        'attributionCompatibility' => 'compatible', 'checkedAt' => impactshop_coupon_iso(impactshop_coupon_now() - 60),
        'expiresAt' => impactshop_coupon_iso(impactshop_coupon_now() + 86400), 'qualification' => ack()];
}
function tt_campaign($id = '700001') {
    return ['siteId' => '900001', 'campaignId' => $id, 'assignmentStatus' => 'accepted', 'policyCashbackStatus' => 'allowed',
        'policyDiscountCodeStatus' => 'allowed', 'timeZone' => 'Europe/Budapest', 'referencesSupported' => true];
}
function tt_row($resource = 'voucher', $campaign = '700001', $id = '800001') {
    return ['ID' => $id, 'campaignID' => $campaign, 'resource' => $resource, 'name' => '<b>10% discount</b>',
        'description' => 'New customers only.', 'conditions' => 'Minimum basket applies.',
        'voucherCode' => $resource === 'voucher' ? 'FIXTURE10' : null, 'validFromDate' => '2026-01-01',
        'validToDate' => '2026-12-31', 'referenceSupported' => true, 'code' => '<a href="https://private.invalid/?secret=never-return">creative</a>'];
}
function tt_reset() {
    reset_fixture(); $p = get_option('impactshop_coupon_policy_v1'); $p['bindings'][] = tt_binding();
    $p['providers']['tradetracker'] = tt_map(); update_option('impactshop_coupon_policy_v1', $p, false);
    $GLOBALS['tt_created'] = 0;
    $GLOBALS['tt_campaign_responder'] = function ($id) { return tt_campaign($id); };
    $GLOBALS['tt_page_responder'] = function ($page) { return $page; };
}
function tt_read($client = null, $mapping = null, $policy = null, $started = 0.0) {
    return impactshop_coupon_read_tradetracker($policy ?? get_option('impactshop_coupon_policy_v1'), $mapping ?? tt_map(), $started, $client ?? new ImpactShop_TradeTracker());
}
function tt_project($binding = null) {
    return impactshop_coupon_project($binding ?? tt_binding(), impactshop_coupon_config(), impactshop_coupon_validate_policy(get_option('impactshop_coupon_policy_v1')));
}
function tt_expect_error($result, $code) { check(is_wp_error($result), 'expected fixed TT error'); eq($result->get_error_code(), 'coupon_' . $code, 'fixed TT error code'); }
tt_reset(); $client = new ImpactShop_TradeTracker(); $r = tt_read($client); $key = impactshop_coupon_binding_key(tt_binding());
eq(count($r['records'][$key]), 2, 'voucher and automatic offer both visited'); eq($r['withheld'], [], 'complete qualified binding');
eq($client->reads, [['voucher','700001',0,100],['offer','700001',0,100]], 'both exact resource pages');
$o = $r['records'][$key][0]; eq(array_keys($o), ['id','kind','code','benefitText','conditionsText','startsAt','endsAt','expiryKind','networkLabel','applicability'], 'exact public field allowlist');
check((bool) preg_match('/^coupon_[a-f0-9]{32}$/D', $o['id']), 'opaque 32hex ID'); eq($o['benefitText'], '10% discount', 'real benefit sanitized');
eq($o['conditionsText'], 'New customers only. Minimum basket applies.', 'both description and conditions preserved');
eq($o['startsAt'], '2025-12-31T23:00:00Z', 'start not shifted one day'); eq($o['endsAt'], '2026-12-31T23:00:00Z', 'inclusive end next local midnight');
check($o['id'] !== $r['records'][$key][1]['id'], 'same ID across resources has distinct identity');
check(strpos(json_encode($r['records']), 'never-return') === false && strpos(json_encode($r['records']), '800001') === false, 'no creative/tracking/internal ID leak');
check(impactshop_coupon_offer_valid($o, 'tradetracker'), 'existing cache validator accepts only TT label');
$o['networkLabel'] = 'Dognet'; check(!impactshop_coupon_offer_valid($o, 'tradetracker'), 'wrong network label rejected');
foreach (['qualification','siteId','dateTimezone','nullStartMeansUnbounded','nullEndMeansOngoing','endDateInclusive','resourceMap'] as $field) {
    tt_reset(); $map = tt_map(); unset($map[$field]); $c = new ImpactShop_TradeTracker(); tt_expect_error(tt_read($c, $map), 'qualification_missing'); eq($c->qualifications, [], 'invalid mapping zero I/O');
}
foreach (['nullStartMeansUnbounded' => 1, 'nullEndMeansOngoing' => 'true', 'endDateInclusive' => null, 'dateTimezone' => 'Invalid/Zone'] as $field => $value) {
    $m=tt_map(); $m[$field]=$value; tt_expect_error(tt_read(null,$m), 'qualification_missing');
}
$m=tt_map();$m['resourceMap']['offer']['redeemMode']='unknown';tt_expect_error(tt_read(null,$m),'qualification_missing');
foreach ([null,'','0000-01-01','2026-02-29','2024-02-30','2026-1-01','2026-01-01T00:00:00Z'] as $date) {
    $m=tt_map();$m['nullStartMeansUnbounded']=false;eq(impactshop_coupon_tt_date($date,$m),false,'unknown/invalid start is not ongoing');
}
$m=tt_map();eq(impactshop_coupon_tt_date('2024-02-29',$m),1709161200,'valid leap day');
$m['nullEndMeansOngoing']=false;eq(impactshop_coupon_tt_date(null,$m,true),false,'null end requires qualified meaning');
$m=tt_map();$m['endDateInclusive']=false;eq(impactshop_coupon_iso(impactshop_coupon_tt_date('2026-01-02',$m,true)),'2026-01-01T23:00:00Z','explicit exclusive end');
foreach (['2026-03-29'=>23,'2026-10-25'=>25] as $date=>$hours) {
    $m=tt_map();eq(impactshop_coupon_tt_date($date,$m,true)-impactshop_coupon_tt_date($date,$m),$hours*3600,'inclusive end uses calendar DST day');
}
$m=tt_map();$m['dateTimezone']='America/Havana';eq(impactshop_coupon_tt_date('2026-11-01',$m),false,'ambiguous midnight rejected');
$m['dateTimezone']='Pacific/Apia';eq(impactshop_coupon_tt_date('2011-12-30',$m),false,'nonexistent local date rejected');
foreach (['voucherCode'=>'','name'=>'','conditions'=>str_repeat('x',1001),'description'=>'https://private.invalid','referenceSupported'=>false,'validToDate'=>'bad'] as $field=>$value) {
    tt_reset();$GLOBALS['tt_page_responder']=function($p)use($field,$value){if($p['resource']==='voucher'){$p['rows'][0][$field]=$value;}return$p;};
    $r=tt_read();check(!is_wp_error($r)&&isset($r['withheld'][$key]),'invalid row withholds whole binding');
}
tt_reset();$GLOBALS['tt_page_responder']=function($p){if($p['resource']==='offer'){$p['rows'][0]['voucherCode']='UNEXPECTED';}return$p;};
check(isset(tt_read()['withheld'][$key]),'automatic offer cannot silently discard a code');
tt_reset();$GLOBALS['tt_page_responder']=function($p){unset($p['rows'][0]['validFromDate']);return$p;};check(isset(tt_read()['withheld'][$key]),'missing date is not explicit null');
foreach (['siteId'=>'other','campaignId'=>'other','assignmentStatus'=>'pending','referencesSupported'=>'true'] as $field=>$value) {
    tt_reset();$GLOBALS['tt_campaign_responder']=function($id)use($field,$value){$c=tt_campaign($id);$c[$field]=$value;return$c;};tt_expect_error(tt_read(),'scope_mismatch');
}
foreach (['policyCashbackStatus'=>null,'policyDiscountCodeStatus'=>'limited','timeZone'=>null,'referencesSupported'=>false] as $field=>$value) {
    tt_reset();$GLOBALS['tt_campaign_responder']=function($id)use($field,$value){$c=tt_campaign($id);$c[$field]=$value;return$c;};$c=new ImpactShop_TradeTracker();$r=tt_read($c);
    check(isset($r['withheld'][$key]),'unknown/disallowed campaign evidence withholds');eq($c->reads,[],'no material read for unqualified campaign');
}
foreach (['offset'=>100,'limit'=>50,'resource'=>'offer','siteId'=>'other','returned'=>0,'requests'=>1,'responseBytes'=>-1,'totalResponseBytes'=>1] as $field=>$value) {
    tt_reset();$GLOBALS['tt_page_responder']=function($p)use($field,$value){$p[$field]=$value;return$p;};tt_expect_error(tt_read(),'schema_invalid');
}
foreach (['campaignID'=>'other','resource'=>'offer','ID'=>'01'] as $field=>$value) {
    tt_reset();$GLOBALS['tt_page_responder']=function($p)use($field,$value){$p['rows'][0][$field]=$value;return$p;};tt_expect_error(tt_read(),'schema_invalid');
}
tt_reset();$GLOBALS['tt_page_responder']=function($p){$p['rows']=[$p['rows'][0],$p['rows'][0]];$p['returned']=2;return$p;};tt_expect_error(tt_read(),'schema_invalid');
tt_reset();$GLOBALS['tt_page_responder']=function($p){$a=$p['rows'][0];$b=$a;$a['ID']='800002';$p['rows']=[$a,$b];$p['returned']=2;return$p;};tt_expect_error(tt_read(),'schema_invalid');
function tt_full_page($p) { $p['rows']=[];for($i=1;$i<=100;$i++){$p['rows'][]=tt_row($p['resource'],$p['campaign']['campaignId'],(string)(800000+$p['offset']+$i));}$p['returned']=100;return$p; }
tt_reset();$GLOBALS['tt_page_responder']=function($p){if($p['resource']==='voucher'&&$p['offset']===0){return tt_full_page($p);} $p['rows']=[];$p['returned']=0;return$p;};
$c=new ImpactShop_TradeTracker();$r=tt_read($c);eq(count($r['records'][$key]),100,'full page accepted only after sentinel');eq(count($c->reads),3,'voucher sentinel plus offer empty');eq($c->reads[1][2],100,'exact sentinel offset');
tt_reset();$GLOBALS['tt_page_responder']=function($p){$p=tt_full_page($p);if($p['offset']===100){$p['rows'][0]['ID']='800001';}return$p;};tt_expect_error(tt_read(),'schema_invalid');
tt_reset();$GLOBALS['tt_page_responder']=function($p){return tt_full_page($p);};$c=new ImpactShop_TradeTracker();tt_expect_error(tt_read($c),'limits');eq(count($c->reads),17,'setup calls included; no21st HTTP');
tt_reset();$GLOBALS['tt_page_responder']=function($p){$p['rows']=[];$p['returned']=0;return$p;};$r=tt_read();eq($r,['records'=>[],'withheld'=>[]],'only complete two-resource empty is known empty');
tt_reset();$p=get_option('impactshop_coupon_policy_v1');$p['bindings'][]=tt_binding('other');$p['bindings'][]=tt_binding('second-program','700002');$c=new ImpactShop_TradeTracker();$r=tt_read($c,null,$p);
eq(count($r['records']),3,'all bindings normalized');eq(count($c->reads),4,'same campaign dedup across bindings');check($r['records'][$key][0]['id']!==$r['records'][impactshop_coupon_binding_key(tt_binding('other'))][0]['id'],'ID binds partner identity');
tt_reset();$p=get_option('impactshop_coupon_policy_v1');$p['bindings'][1]['expiresAt']=impactshop_coupon_iso(impactshop_coupon_now());$c=new ImpactShop_TradeTracker();tt_expect_error(tt_read($c,null,$p),'qualification_missing');eq($c->reads,[],'expired scope no network');
foreach (['requests'=>21,'totalResponseBytes'=>1048577] as $field=>$value) {tt_reset();$GLOBALS['tt_page_responder']=function($p)use($field,$value){$p[$field]=$value;return$p;};tt_expect_error(tt_read(),'limits');}
tt_reset();$GLOBALS['tt_page_responder']=function($p){if($p['resource']==='voucher'){$p['totalResponseBytes']=1048576;}return$p;};$c=new ImpactShop_TradeTracker();tt_expect_error(tt_read($c),'limits');eq(count($c->reads),1,'exhausted byte budget stops next HTTP');
tt_reset();$GLOBALS['tt_page_responder']=function($p){$GLOBALS['coupon_clock']=60;return$p;};tt_expect_error(tt_read(),'limits');
tt_reset();$c=new ImpactShop_TradeTracker();tt_expect_error(tt_read($c,null,null,NAN),'limits');eq($c->qualifications,[],'invalid deadline no I/O');
foreach (['tt_auth'=>'auth','tt_rate_limit'=>'rate_limit','tt_scope'=>'scope_mismatch','tt_schema'=>'schema_invalid','tt_limits'=>'limits','private fixture-secret'=>'transport'] as $message=>$code) {
    tt_reset();$GLOBALS['tt_page_responder']=function()use($message){throw new RuntimeException($message);};tt_expect_error(tt_read(),$code);
}
// Exact resource boundaries and no hidden writer in the direct adapter.
tt_reset();$beforeOptions=$GLOBALS['coupon_options'];$beforeCron=$GLOBALS['coupon_cron'];tt_read();eq($GLOBALS['coupon_options'],$beforeOptions,'direct reader writes no options');eq($GLOBALS['coupon_cron'],$beforeCron,'direct reader schedules nothing');
tt_reset();$GLOBALS['tt_page_responder']=function($p){if($p['resource']==='voucher'&&$p['offset']<1500){return tt_full_page($p);} $p['rows']=[];$p['returned']=0;if($p['resource']==='offer'){$p['totalResponseBytes']=1048576;}return$p;};$c=new ImpactShop_TradeTracker();$r=tt_read($c);check(!is_wp_error($r),'exact20calls and1MiB can complete both resources');eq($c->requests,20,'exact20 total including setup');eq(count($c->reads),17,'exact17material calls plus3setup');
foreach (['requests'=>4,'totalResponseBytes'=>400] as $field=>$value) {tt_reset();$GLOBALS['tt_page_responder']=function($p)use($field,$value){if($p['resource']==='offer'){$p[$field]=$value;}return$p;};tt_expect_error(tt_read(),'schema_invalid');}
tt_reset();$GLOBALS['tt_campaign_responder']=function($id){$GLOBALS['coupon_clock']=60;return tt_campaign($id);};$c=new ImpactShop_TradeTracker();tt_expect_error(tt_read($c),'limits');eq($c->reads,[],'late qualification does not start a material request');
// Exercise existing refresh/fence/cache/activation dispatch with a synthetic client, never a real credential.
tt_reset();putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');putenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE');
tt_expect_error(impactshop_coupon_refresh('tradetracker'),'auth_unavailable');eq($GLOBALS['tt_created'],0,'missing server input no client/HTTP');
tt_reset();putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID=900001');putenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE=fixture-only-secret');
$r=impactshop_coupon_refresh('tradetracker');eq($r['status'],'complete','existing refresh dispatches bounded TT adapter');eq(tt_project()['status'],'not_checked','OFF remains OFF after pilot read');
check(!is_wp_error(impactshop_coupon_set_enabled('tradetracker',true)),'existing enable requires qualified pilot');$GLOBALS['coupon_time']+=120;
eq(impactshop_coupon_refresh('tradetracker')['status'],'complete','post-enable refresh');eq(tt_project()['status'],'available','existing projection accepts TT normalized cache');eq(tt_project()['offers'][0]['networkLabel'],'TradeTracker','final public projection preserves network label');
$created=$GLOBALS['tt_created'];$http=count($GLOBALS['coupon_http']);tt_project();eq($GLOBALS['tt_created'],$created,'public projection never creates client');eq(count($GLOBALS['coupon_http']),$http,'public projection zero upstream');
$GLOBALS['coupon_time']+=120;$GLOBALS['tt_page_responder']=function(){throw new RuntimeException('tt_auth');};tt_expect_error(impactshop_coupon_refresh('tradetracker'),'auth');
eq(tt_project()['status'],'unavailable','failed refresh cannot reuse stale good cache');$created=$GLOBALS['tt_created'];$GLOBALS['coupon_time']+=120;
tt_expect_error(impactshop_coupon_refresh('tradetracker'),'auth');eq($GLOBALS['tt_created'],$created,'existing auth suspension reused for TT');
tt_reset();$cfg=impactshop_coupon_config();$cfg['enabledProviders'][]='tradetracker';update_option('impactshop_coupon_config_v1',$cfg,false);
$GLOBALS['tt_page_responder']=function($p){$p['rows'][0]['conditions']='https://private.invalid';return$p;};
eq(impactshop_coupon_refresh('tradetracker')['status'],'complete','malformed offers retain coverage withholding in complete snapshot');eq(tt_project()['status'],'unavailable','withheld candidate is never false none');
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');putenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE');
tt_reset();
$vidaxl = array_replace(tt_binding('vidaxl', '18982'), ['programRef' => 'tradetracker-18982', 'siteOrChannelId' => '481183']);
$policy = get_option('impactshop_coupon_policy_v1');
$policy['bindings'][1] = $vidaxl;
$policy['providers']['tradetracker']['siteId'] = '481183';
update_option('impactshop_coupon_policy_v1', $policy, false);
$GLOBALS['tt_campaign_responder'] = function ($id) {
    return array_replace(tt_campaign($id), ['siteId' => '481183']);
};
$GLOBALS['tt_page_responder'] = function ($page) {
    $page['siteId'] = '481183';
    $page['campaign']['siteId'] = '481183';
    if ($page['resource'] === 'voucher') {
        $page['rows'][] = tt_row('voucher', '18982', '2597886');
        $page['returned'] = count($page['rows']);
    }
    return $page;
};
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID=900001');
putenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE=fixture-only-secret');
eq(impactshop_coupon_refresh('tradetracker')['status'], 'complete', 'qualified VidaXL read while OFF');
check(!is_wp_error(impactshop_coupon_set_enabled('tradetracker', true)), 'qualified VidaXL fixture enables');
$GLOBALS['coupon_time'] += 120;
eq(impactshop_coupon_refresh('tradetracker')['status'], 'complete', 'VidaXL post-enable refresh');
$result = tt_project($vidaxl);
eq($result['status'], 'available', 'VidaXL voucher is publicly projected');
eq(array_column($result['offers'], 'id'), ['coupon_66be2102d2501404ab2a96fba4734690'], 'only reviewed voucher survives projection');
$GLOBALS['coupon_time'] += 120;
$GLOBALS['tt_page_responder'] = function ($page) {
    $page['siteId'] = '481183';
    $page['campaign']['siteId'] = '481183';
    return $page;
};
eq(impactshop_coupon_refresh('tradetracker')['status'], 'complete', 'scan completes if the reviewed voucher disappears');
eq(tt_project($vidaxl)['offers'], [], 'other campaign materials never replace a missing voucher');
putenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');
putenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE');
echo 'TT-specific PASS ' . ($assertions-$ttBaseline) . " assertions; existing baseline $ttBaseline reused (offline; no network)\n";
