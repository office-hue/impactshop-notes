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
function isb_cj_pick_link($id) { return ['click_url'=>'https://example.test/click']; }
function add_query_arg($key,$value,$url) { return $url.(str_contains($url,'?')?'&':'?').urlencode($key).'='.urlencode($value); }
$source=file_get_contents(__DIR__.'/../wp-content/mu-plugins/impactshop-boot.php');
$start=strpos($source,'function isb_cj_generate_click_url(');
$end=strpos($source,'/* ====================== DOGNET API', $start);
eval(substr($source,$start,$end-$start));
$sid='';$url=isb_cj_generate_click_url('cj-42',$token,'should-not-leak','',$sid);
parse_str(parse_url($url,PHP_URL_QUERY),$q);
check($sid===$token && $q['sid']===$token,'opaque case-sensitive SID unchanged and no raw pseudo');
echo "Impact Shop attribution compatibility passed\n";
