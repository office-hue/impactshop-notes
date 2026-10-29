<?php
/** Existing NGO attribution and opaque CJ SID must survive the new point handoff. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS',60);
define('HOUR_IN_SECONDS',3600);
define('DAY_IN_SECONDS',86400);
function add_action(...$args) {}
function add_filter(...$args) {}
function sanitize_title($value) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$value)); }
$correlation=null;
function impactshop_sharity_affiliate_correlate($token) { return $GLOBALS['correlation']; }
require __DIR__ . '/../wp-content/mu-plugins/impactshop-rest-totals.php';
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$token='sat1_'.str_repeat('Ab_',14).'Z';
check(strlen($token)===48,'fixture token');
$correlation=['provider_key'=>'dognet','ngo_ref'=>'correct-ngo'];
check(impactshop_totals_pick_ngo_from_row(['data1'=>$token])==='correct-ngo','opaque token resolves server NGO');
$correlation=null;
check(impactshop_totals_pick_ngo_from_row(['data1'=>$token,'ngo'=>'wrong-ngo'])==='','unresolved token never becomes a label or fallback NGO');
$correlation=['provider_key'=>'cj','ngo_ref'=>'wrong-provider'];
check(impactshop_totals_pick_ngo_from_row(['d1'=>$token])==='','other provider not accepted');
$correlation=['provider_key'=>'dognet','ngo_ref'=>'correct-ngo'];
check(impactshop_totals_pick_ngo_from_row(['subid'=>'https://example.test/?d1='.urlencode($token)])==='correct-ngo','nested URL token preserves NGO');
check(impactshop_totals_pick_ngo_from_row(['data1'=>'legacy-ngo'])==='legacy-ngo','legacy NGO unchanged');
$options = ['impactshop_cj_links' => [
    ['advertiser_id'=>'42','click_url'=>'','destination'=>'https://merchant.test/','coupon_code'=>'UNTRACKED'],
    ['advertiser_id'=>'42','click_url'=>'https://www.tkqlhce.com/click-123-456'],
    ['advertiser_id'=>'43','click_url'=>'','destination'=>'https://merchant.test/'],
    ['advertiser_id'=>'44','click_url'=>'https://unapproved.test/click'],
    ['advertiser_id'=>'45','click_url'=>'javascript:alert(1)'],
], 'sharity_points_impactshop_producer_enabled'=>'1'];
function get_option($key,$default=false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_validate_redirect($url,$fallback='') {
    $p=parse_url($url);
    return is_array($p) && in_array($p['scheme']??'', ['https','http'], true)
        && in_array($p['host']??'', ['www.tkqlhce.com','app.example.test'], true) ? $url : $fallback;
}
function add_query_arg($key,$value,$url=null) {
    if (is_array($key)) { $params=$key; $url=$value; } else { $params=[$key=>$value]; }
    return $url.(str_contains($url,'?')?'&':'?').http_build_query($params);
}
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags|JSON_THROW_ON_ERROR); }
$source=file_get_contents(__DIR__.'/../wp-content/mu-plugins/impactshop-boot.php');
$start=strpos($source,'function isb_cj_links_index(');
$end=strpos($source,'/* ====================== DOGNET API', $start);
eval(substr($source,$start,$end-$start));
$sid='';$url=isb_cj_generate_click_url('cj-42',$token,'should-not-leak','',$sid);
parse_str(parse_url($url,PHP_URL_QUERY),$q);
check($sid===$token && $q['sid']===$token,'opaque case-sensitive SID unchanged and no raw pseudo');
check(parse_url($url,PHP_URL_HOST)==='www.tkqlhce.com','valid tracking preferred to untracked coupon');
foreach (['43','44','45'] as $id) {
    check(isb_cj_pick_link($id)===null,'empty or unsafe tracking is unavailable');
    check(isb_cj_generate_click_url('cj-'.$id,$token,'','',$sid)==='','no direct merchant/admin fallback');
}

// Execute the real exit-bearing handler in isolated child processes, no HTTP/DB.
function isb_q($key,$default='') { return isset($_GET[$key]) ? strip_tags((string)$_GET[$key]) : $default; }
function get_query_var($key) { return ''; }
function is_wp_error($value) { return $value instanceof Throwable; }
function isb_find_shop($shop) { return ['network_source'=>'cj','product_url'=>'https://merchant.test/']; }
function isb_clean_deeplink($url) { return str_starts_with($url,'https://') ? $url : base64_decode($url,true); }
function impactshop_identity_owner_cookie() { return $GLOBALS['owner_cookie'] ?? ''; }
function impactshop_identity_owner_authorized($pseudo) { return ($GLOBALS['owner_ok']??false) && $pseudo==='abcdef123456'; }
function home_url($path) { return 'https://app.example.test'.$path; }
function status_header($status) {}
function nocache_headers() {}
function esc_url($url) { return htmlspecialchars($url,ENT_QUOTES); }
function isb_error($message) { throw new RuntimeException('controlled:'.$message); }
function isb_log_go_click($row) {}
function apply_filters($hook,$value,...$args) {
    if ($hook==='impactshop_sharity_affiliate_prepare') {
        if (empty($GLOBALS['owner_ok'])) throw new RuntimeException('untrusted_prepare');
        $GLOBALS['prepared']=$args[0];
        return ['authorized'=>true,'provider_key'=>'cj','activation_id'=>'act1_'.str_repeat('a',32),
            'provider_token'=>'sat1_'.str_repeat('A',43)];
    }
    return $hook==='impactshop_sharity_affiliate_mark_redirected' ? true : $value;
}
function wp_safe_redirect($url,$status) {
    if (wp_validate_redirect($url,'')==='') throw new RuntimeException('admin_fallback');
    throw new RuntimeException('redirect:'.$url);
}
$start=strpos($source,'function isb_redirect_with_propagation(');
$end=strpos($source,'function isb_resolve_default_d1(', $start);
eval(substr($source,$start,$end-$start));
$start=strpos($source,'function isb_handle_go(');
$end=strpos($source,'/* ===================== REWRITE / HOOKOK', $start);
eval(substr($source,$start,$end-$start));
$mode=$argv[1]??'';
if ($mode!=='') {
    $_GET=['shop'=>'cj-42','d1'=>'chosen-ngo','u'=>base64_encode('https://merchant.test/deal?a=1&b=2'),
        'amb'=>'campaign','src'=>'impactshop'];
    $_COOKIE=['impactshop_pseudo_id'=>'abcdef123456'];
    if ($mode==='missing-second') $_GET['_impact_points_hop']='1';
    if ($mode==='invalid') $GLOBALS['owner_cookie']='fixture-invalid';
    if ($mode==='owner') { $GLOBALS['owner_cookie']='fixture-secret'; $GLOBALS['owner_ok']=true; }
    if ($mode==='anonymous') $_COOKIE=[];
    if ($mode==='disabled') $GLOBALS['options']['sharity_points_impactshop_producer_enabled']='0';
    if ($mode==='untracked') $_GET['shop']='cj-43';
    if ($mode==='bad-host') $_GET['shop']='cj-44';
    try { isb_handle_go(true); throw new RuntimeException('no_result'); }
    catch (RuntimeException $error) {
        echo wp_json_encode(['result'=>$error->getMessage(),'prepared'=>$GLOBALS['prepared']??null]);
    }
    exit;
}
function scenario($mode): string {
    $process=proc_open([PHP_BINARY,__FILE__,$mode], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
    check(is_resource($process),'child process available');
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($process)===0 && $err==='', 'real handler child completed:'.$err);
    return $out;
}
$landing=scenario('first');
check(str_contains($landing,'Folytatás a webshopba'),'first-party landing on missing Strict cookie');
check(preg_match('/location\.replace\((.*?)\);/s',$landing,$match)===1,'explicit continuation document');
$resume=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
check(parse_url($resume,PHP_URL_HOST)==='app.example.test' && parse_url($resume,PHP_URL_PATH)==='/go-deal/','fixed same-origin continuation');
parse_str(parse_url($resume,PHP_URL_QUERY),$query);
check($query['shop']==='cj-42' && $query['d1']==='chosen-ngo' && $query['amb']==='campaign'
    && $query['src']==='impactshop' && $query['u']===base64_encode('https://merchant.test/deal?a=1&b=2')
    && $query['_impact_points_hop']==='1','original handoff payload preserved');
check(!str_contains($landing,'abcdef123456') && !str_contains($landing,'fixture-secret'),'no identity/credential in continuation');
foreach (['missing-second','invalid','untracked','bad-host'] as $mode) {
    $result=json_decode(scenario($mode),true,512,JSON_THROW_ON_ERROR);
    check(str_starts_with($result['result'],'controlled:') && $result['prepared']===null,
        $mode.': no loop, silent non-points forwarding, bind or admin fallback');
}
$result=json_decode(scenario('owner'),true,512,JSON_THROW_ON_ERROR);
check(str_starts_with($result['result'],'redirect:https://www.tkqlhce.com/'),'trusted owner receives tracking redirect');
check($result['prepared']['source']==='impactshop-points' && $result['prepared']['ngo']==='chosen-ngo','existing authority context kept');
parse_str(parse_url(substr($result['result'],9),PHP_URL_QUERY),$query);
check($query['sid']==='sat1_'.str_repeat('A',43) && !str_contains($query['sid'],'abcdef123456'),'trusted opaque SID only');
foreach (['anonymous','disabled'] as $mode) {
    $result=json_decode(scenario($mode),true,512,JSON_THROW_ON_ERROR);
    check(str_starts_with($result['result'],'redirect:https://www.tkqlhce.com/') && $result['prepared']===null,
        $mode.': existing legacy redirect unchanged, no points authority');
}
echo "Impact Shop attribution compatibility passed\n";
