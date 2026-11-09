<?php
declare(strict_types=1);

// Selected production function bodies, unchanged; synthetic WP storage/owner.
// No WordPress bootstrap, network, production data or real credentials.
function fixture_request(array $input): array {
    $p = proc_open([PHP_BINARY, __FILE__, '--request'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($p) !== 0 || $err !== '') throw new RuntimeException($err . $out);
    return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}
function check($actual, $expected, string $name): void {
    if ($actual !== $expected) throw new RuntimeException($name . ': ' . json_encode([$actual,$expected]));
}
if (($argv[1] ?? '') !== '--request') {
    $base = 'https://app.sharity.hu/profil/';
    $raw = $base . '?bridge_target=account&return=https%3A%2F%2Fsharity.hu%2Fsajat-sharity&prctx=prctx_fixture&ui_lang=hu';
    $complete = $raw . '&impactshop_profile_return_complete=1';
    $safe = [
        $base => $base,
        $base.'?jysk_return=1&bridge_target=account' => $base.'?jysk_return=1&bridge_target=account&impactshop_profile_return_complete=1',
        $raw => $complete,
        $complete => $complete,
        $raw.'#account' => $complete.'#account',
        $raw.'&selection_intent=select_fixture' => $raw.'&selection_intent=select_fixture',
        'https://app.sharity.hu/impactshop/?bridge_target=account' => 'https://app.sharity.hu/impactshop/?bridge_target=account',
    ];
    foreach (['sajat-sharity','vasarlasi-seged','hatas-korok'] as $target) {
        $url = $base.'?bridge_target=restore&return='.rawurlencode('https://sharity.hu/'.$target);
        $safe[$url] = $url.'&impactshop_profile_return_complete=1';
    }
    foreach ($safe as $url=>$expected) {
        $r=fixture_request(['action'=>'return','url'=>$url]); check($r['target'],$expected,'raw context');
        $r=fixture_request(['action'=>'save','url'=>$url]); check($r['redirect'],[$expected,303],'save redirect'); check($r['writes'],[],'save no writes');
    }
    $unsafe = [null,[], '', 'https://evil.test/profil/', '//evil.test/', 'http://app.sharity.hu/profil/',
        'https://user@app.sharity.hu/profil/', 'https://app.sharity.hu:443/profil/',
        $base.'belepesi-kod/', $raw.'&return=x', $raw.'&%72eturn=x', $raw.'&return[]=x',
        $raw.'&bridge.target=restore', $raw.'&bridge_target=restore', $raw.'&prctx[]=x',
        $raw.'&selection_intent[]=x', $raw.'&jysk_return=0', $raw.'&selection_intent=',
        $complete.'&selection_intent=x', $complete.'&impactshop_profile_return_complete=1',
        $raw.'%0D%0ALocation:x', $raw.'\\evil', $raw.'%', $raw.';return=x',str_repeat('x',4097)];
    foreach ($unsafe as $url) {
        check(fixture_request(['action'=>'return','url'=>$url])['target'],null,'reject malformed context');
        $r=fixture_request(['action'=>'generate','url'=>$url]); check($r['status'],400,'generate reject context'); check($r['writes'],[],'invalid context no writes');
    }
    foreach ([['contract'=>null,'status'=>409],['confirm'=>false,'status'=>409],['nonce'=>'bad','status'=>403],['origin'=>'https://evil.test','status'=>403],['owner'=>false,'status'=>403],['paused'=>true,'status'=>503]] as $case) {
        $r=fixture_request(['action'=>'generate','url'=>$raw]+$case);
        check($r['status'],$case['status'],'generate rejection'); check($r['writes'],[],'rejected request no hash/reveal writes');
    }
    $r=fixture_request(['action'=>'generate','url'=>$raw]);
    check($r['status'],200,'generate'); check(count($r['writes']),2,'exactly hash and reveal writes');
    check($r['reveal']['profile_return_url'],$complete,'grant carries exact context');
    check(strlen($r['reveal']['code']),14,'existing code format preserved');
    $r=fixture_request(['action'=>'save','url'=>$raw,'contract'=>'legacy']);
    check($r['redirect'],[$raw,302],'legacy POST unchanged');
    $r=fixture_request(['action'=>'reveal','url'=>$complete]);
    check(str_contains($r['html'],'name="impactshop_identity_code_save_contract" value="2"'),true,'reveal v2 form');
    check(str_contains($r['html'],'action="https://app.sharity.hu/"'),true,'reveal normal POST target');
    check(str_contains($r['html'],htmlspecialchars($complete,ENT_QUOTES)),true,'reveal preserves return');
    check($r['consumed'],1,'one-time grant consumed');
    $r=fixture_request(['action'=>'reveal','url'=>$raw,'expired'=>true]);
    check(str_contains($r['html'],'Vissza a profilhoz'),true,'expired recovery link');
    check(str_contains($r['html'],'SYNTHETIC'),false,'expired no secret');
    echo "profile code save: PASS (raw-return matrix, compatibility, no-write failures, generate, save, reveal)\n";
    exit;
}

$in=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$writes=[]; $transients=[]; $consumed=0; $result=[];
class WP_REST_Request {
    public function get_param($key) { global $in; return match($key) {
        'code_save_contract' => array_key_exists('contract',$in)?$in['contract']:2,
        'confirm_generation' => $in['confirm']??true,
        'profile_return_url' => $in['url']??null, default=>null}; }
    public function get_header($key) {global $in; return $in['nonce']??'fixture';}
}
class WP_REST_Response {
    public function __construct(public $data,public $status){}
    public function header(...$args):void{}
}
class FixtureRedirect extends RuntimeException {}
function home_url($path='/'){return 'https://app.sharity.hu'.$path;}
function wp_parse_url($url,$part=-1){return parse_url($url,$part);}
function untrailingslashit($s){return rtrim($s,'/');}
function wp_unslash($s){return $s;}
function sanitize_text_field($s){return $s;}
function esc_url_raw($s){return $s;}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);}
function wp_verify_nonce($n,$a){return $n==='fixture';}
function wp_salt($s){return 'synthetic-fixture-salt';}
function wp_hash_password($s){return password_hash($s,PASSWORD_DEFAULT);}
function current_time(...$args){return '2026-11-09 12:00:00';}
function is_ssl(){return true;}
function nocache_headers(){}
function status_header($s){}
function get_option($key,$default=null){global $in; return $key==='impactshop_identity_code_generation_paused'?($in['paused']??false):['nickname'=>'Synthetic','recovery_hash'=>'previous-hash'];}
function update_option($key,$value,...$args){global $writes;$writes[]=['hash',$value];return true;}
function add_option($key,$value,...$args){return update_option($key,$value);}
function get_transient($key){global $transients;return $transients[$key]??false;}
function set_transient($key,$value,$ttl){global $writes,$transients;$transients[$key]=$value;if(str_starts_with($key,'impactshop_identity_reveal_'))$writes[]=['reveal',$value];return true;}
function delete_transient($key){global $transients,$consumed;unset($transients[$key]);$consumed++;}
function impactshop_identity_owner_authorized($pseudo){global $in;return $in['owner']??true;}
function wp_get_referer(){return false;}
function wp_validate_redirect($url,$fallback){return $url;}
function wp_safe_redirect($url,$status=302){global $result;$result['redirect']=[$url,$status];throw new FixtureRedirect();}

// Extract entire token-delimited bodies; never substitute code inside a body.
$source=file_get_contents(dirname(__DIR__).'/wp-content/mu-plugins/impactshop-identity-panel.php');
$tokens=token_get_all($source); $functions=[];
for($i=0,$n=count($tokens);$i<$n;$i++) {
    if(!is_array($tokens[$i]) || $tokens[$i][0]!==T_FUNCTION)continue;
    $start=$i; $name=null;
    for($j=$i+1;$j<$n;$j++){if(is_array($tokens[$j])&&$tokens[$j][0]===T_STRING){$name=$tokens[$j][1];break;}if($tokens[$j]==='(')break;}
    if(!$name)continue;
    $body='';$depth=0;$opened=false;
    for(;$i<$n;$i++){$t=$tokens[$i];$body.=is_array($t)?$t[1]:$t;if($t==='{'){$depth++;$opened=true;}if($t==='}'&&--$depth===0&&$opened)break;}
    $functions[$name]=$body;
}
foreach(['profile_home_path','profile_environment_path','profile_reveal_cookie_path','profile_route_path','code_save_return','handle_password_manager_save','code_generate','request_same_origin','profile_cookie','profile_valid_pseudo','profile_rate_limit','profile_generate_recovery_code','profile_option_key','profile_store_code_hash','profile_normalize_code','render_code_page'] as $name){
    $full='impactshop_identity_'.$name;if(!isset($functions[$full]))throw new RuntimeException('Missing '.$full);eval($functions[$full]);
}
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_ORIGIN']=$in['origin']??'https://app.sharity.hu';
$_COOKIE['impactshop_pseudo_id']='synthetic123';
ob_start();
register_shutdown_function(function()use(&$result,&$writes,&$consumed){$result['html']=ob_get_clean();$result['writes']=$writes;$result['consumed']=$consumed;foreach($writes as $w){if($w[0]==='reveal')$result['reveal']=$w[1];}echo json_encode($result,JSON_THROW_ON_ERROR);});
try{
    switch($in['action']){
        case 'return':$result['target']=impactshop_identity_code_save_return($in['url']??null);break;
        case 'generate':$r=impactshop_identity_code_generate(new WP_REST_Request());$result['status']=$r->status;break;
        case 'save':$_POST=['impactshop_identity_save_intent'=>'1','impactshop_identity_code_save_contract'=>$in['contract']??'2','impactshop_identity_return'=>$in['url']??''];impactshop_identity_handle_password_manager_save();break;
        case 'reveal':
            $_SERVER['REQUEST_URI']='/profil/belepesi-kod/';$_COOKIE['impactshop_identity_reveal']='fixture-grant';
            if(empty($in['expired']))$transients['impactshop_identity_reveal_'.hash('sha256','fixture-grant')]=['pseudo_id'=>'synthetic123','code'=>'SYNTHETIC-CODE','profile_return_url'=>$in['url']];
            impactshop_identity_render_code_page();break;
    }
}catch(FixtureRedirect $e){}
