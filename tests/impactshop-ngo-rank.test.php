<?php
/** Actual card collector/rebuild with synthetic WP data; no DB/network. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$checks=0; $queries=[]; $cache=[]; $optionWrites=0; $totalsCalls=0;
$options=['impactshop_ads_current_quarter'=>'2026Q1','impactshop_ngo_card_approved_slugs'=>[]];
$quarter=['start_at'=>gmdate('Y-m-d H:i:s',time()-3600),'end_at'=>gmdate('Y-m-d H:i:s',time()+3600),'status'=>'active'];
$quarterGood=$quarter; $tally=[]; $quarterError=$tallyError='';
function check($ok,$message) { ++$GLOBALS['checks']; if (!$ok) throw new RuntimeException($message); }
function get_option($key,$default=false) { return $GLOBALS['options'][$key]??$default; }
function update_option(...$args) { ++$GLOBALS['optionWrites']; throw new RuntimeException('Unexpected option write'); }
function get_transient($key) { return $GLOBALS['cache'][$key]??false; }
function set_transient($key,$value,$ttl) { $GLOBALS['cache'][$key]=$value; }
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function is_admin() { return false; }
function is_wp_error($x) { return false; }
function apply_filters($name,$value,...$args) { return $value; }
function __($text,$domain='') { return $text; }
function sanitize_title($text) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/','-',$text),'-')); }
function home_url($path='') { return 'https://synthetic.example'.$path; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function wp_upload_dir() { return []; }
function wp_json_encode($x) { return json_encode($x); }
function impactshop_resolve_ngo_name($slug) { return 'Fixture '.$slug; }
function impactshop_totals_collect(...$args) { ++$GLOBALS['totalsCalls']; return ['rows'=>$GLOBALS['totals']]; }
function impactshop_ads_get_active_quarter() { throw new RuntimeException('Seeding helper must not run'); }
function impactshop_ads_calculate_tally($key=null) { throw new RuntimeException('Legacy tally can hide DB failures'); }
if (($argv[1]??'')!=='no-alias') {
    function impactshop_canonical_ngo_slug($slug) { return $slug==='alias-c'?'c':$slug; }
}
class CardReadDatabase {
    public $prefix='wp_'; public $last_error='';
    function prepare($sql,...$args) { return ['sql'=>$sql,'args'=>$args]; }
    function get_row($q,$format) {
        $GLOBALS['queries'][]=$q; $this->last_error=$GLOBALS['quarterError'];
        check($q['args']===['2026Q1'] && str_contains($q['sql'],'WHERE quarter_key = %s'), 'quarter scoped read');
        return $GLOBALS['quarter'];
    }
    function get_results($q,$format) {
        $GLOBALS['queries'][]=$q; $this->last_error=$GLOBALS['tallyError'];
        check($q['args']===['2026Q1'] && str_contains($q['sql'],'WHERE quarter_key = %s'), 'no all-time tally query');
        return $GLOBALS['tally'];
    }
    function __call($method,$args) { throw new RuntimeException('Unexpected DB operation '.$method); }
}
$wpdb=new CardReadDatabase();
require __DIR__.'/../wp-content/mu-plugins/impactshop-ngo-card.php';
$collect=new ReflectionMethod(ImpactShop_NGO_Card_API::class,'collect_challenge_rank_map');
$rebuild=new ReflectionMethod(ImpactShop_NGO_Card_API::class,'rebuild_dataset');
$getDataset=new ReflectionMethod(ImpactShop_NGO_Card_API::class,'get_dataset');
set_error_handler(static function($severity,$message,$file,$line) {
    if (error_reporting() & $severity) throw new ErrorException($message,0,$severity,$file,$line);
    return false;
});
try {
    // Load the actual card without its optional new dependency first.
    check($collect->invoke(null)===null, 'missing policy does not fatal or invent Base');
    check($rebuild->invoke(null)===null && $totalsCalls===0 && !$queries, 'missing dependency stops before existing writes/amount reads');
    require __DIR__.'/../wp-content/mu-plugins/sharity-support-policy.php';
    check($collect->invoke(null)===['map'=>[],'active_total'=>0], 'empty valid quarter is distinguishable from failure');
    $tally=[['ngo_slug'=>'A','votes'=>'100'],['ngo_slug'=>'b','votes'=>'90'],['ngo_slug'=>'c','votes'=>'80'],['ngo_slug'=>'d','votes'=>'80'],['ngo_slug'=>'zero','votes'=>'0']];
    $r=$collect->invoke(null);
    check($r===['map'=>['a'=>1,'b'=>2,'c'=>3,'d'=>3,'zero'=>0],'active_total'=>4], 'actual collector keeps boundary ties and zero');
    $counts=[]; for ($i=1;$i<=9;$i++) $counts[]=['ngo_slug'=>'n'.$i,'votes'=>200-$i];
    $tally=array_merge($counts,[['ngo_slug'=>'ten','votes'=>100],['ngo_slug'=>'eleven','votes'=>100],['ngo_slug'=>'twelve','votes'=>90]]);
    $r=$collect->invoke(null);
    check($r['map']['ten']===10 && $r['map']['eleven']===10 && $r['map']['twelve']===12, 'actual collector top-ten competition rank');
    $tally=[['ngo_slug'=>'c','votes'=>'30'],['ngo_slug'=>'Alias C','votes'=>30],['ngo_slug'=>'d','votes'=>50]];
    $r=$collect->invoke(null);
    check(function_exists('impactshop_canonical_ngo_slug') ? $r['map']===['c'=>1,'d'=>2] : $r['map']===['alias-c'=>2,'c'=>2,'d'=>1], 'existing optional canonicalizer chain preserved');
    foreach (['','2026Q0','2026Q4\n',null] as $bad) {
        $before=count($queries); $options['impactshop_ads_current_quarter']=$bad;
        check($collect->invoke(null)===null && count($queries)===$before, 'invalid quarter never queries all-time votes');
    }
    $options['impactshop_ads_current_quarter']='2026Q1';
    foreach ([null,[],array_replace($quarterGood,['status'=>'closed']),array_replace($quarterGood,['start_at'=>'2026-02-30 00:00:00']),array_replace($quarterGood,['end_at'=>gmdate('Y-m-d H:i:s',time()-10)]),array_replace($quarterGood,['start_at'=>gmdate('Y-m-d H:i:s',time()+10)])] as $bad) {
        $quarter=$bad; check($collect->invoke(null)===null, 'missing/invalid/inactive window not an authoritative Base');
    }
    $quarter=$quarterGood; $quarterError='synthetic SQL failure';
    check($collect->invoke(null)===null, 'quarter query error not ignored');
    $quarterError=''; $tallyError='synthetic SQL failure'; $tally=[];
    check($collect->invoke(null)===null, 'failed tally returning [] not valid empty');
    $tallyError='';
    foreach ([null,[['ngo_slug'=>'a','votes'=>1.5]],[['ngo_slug'=>'a','votes'=>'9223372036854775808']],[['ngo_slug'=>'','votes'=>1]],[['ngo_slug'=>'a','votes'=>PHP_INT_MAX],['ngo_slug'=>'a','votes'=>1]]] as $bad) {
        $tally=$bad; check($collect->invoke(null)===null, 'invalid counts/rows cannot replace a valid cache');
    }
    // Exercise both actual item-builder paths, preserving money and CTA values.
    $totals=[['ngo_slug'=>'a','ngo'=>'Foundation A','donation_converted'=>1234,'rank'=>1],['ngo_slug'=>'b','ngo'=>'Foundation B','donation_converted'=>4321,'rank'=>2]];
    $options['impactshop_ngo_card_approved_slugs']=['a'=>['name'=>'Foundation A'],'b'=>['name'=>'Foundation B'],'c'=>['name'=>'Foundation C'],'zero'=>['name'=>'Foundation Zero']];
    $tally=[['ngo_slug'=>'c','votes'=>100],['ngo_slug'=>'a','votes'=>50],['ngo_slug'=>'b','votes'=>50]];
    $first=$rebuild->invoke(null);
    check($first['items']['c']['rank']===1 && $first['items']['c']['badge_status']['key']==='legend' && $first['items']['c']['amount']['huf']===0, 'positive-vote NGO with no money row receives proper tier, still zero money');
    check($first['items']['a']['rank']===2 && $first['items']['b']['rank']===2, 'both existing-money cards use competition rank');
    check($first['items']['a']['amount']['huf']===1234 && $first['items']['b']['amount']['huf']===4321, 'existing financial amounts unchanged');
    check($first['items']['zero']['rank']===0 && $first['items']['zero']['badge_status']['key']==='base', 'no votes is Base');
    $tally=[]; $empty=$rebuild->invoke(null);
    foreach ($empty['items'] as $slug=>$item) {
        check($item['rank']===0 && $item['badge_status']['key']==='base', 'empty current quarter never falls back to monetary rank');
        foreach (['amount','share_url','cta_url','go_url','fillout_url','donation_rate','next_milestone','logo_url'] as $key) check($item[$key]===$first['items'][$slug][$key], 'ranking leaves '.$key.' unchanged');
    }
    $cache['impactshop_ngo_card_dataset_v4']=array_replace($first,['generated_at'=>time()-10000,'stale_at'=>time()-9000,'expires_at'=>time()-8000]);
    $quarter=null; $totalsBefore=$totalsCalls;
    check($getDataset->invoke(null,false)===null && $totalsCalls===$totalsBefore, 'strict read unavailable before amount/option work');
    $stale=$getDataset->invoke(null,true);
    check($stale['stale']===true && $stale['items']===$first['items'], 'existing explicitly stale fallback preserved');
    check($optionWrites===0, 'no option seeds/business writes in tested reads');
} finally { restore_error_handler(); }
echo "NGO rank card: {$checks} checks passed; actual PHP, synthetic WP, no DB/network\n";
