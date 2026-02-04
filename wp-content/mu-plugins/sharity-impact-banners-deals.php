<?php
/**
 * Sharity – Banners-based deals (MU) + diagnosztika
 * Shortcodes:
 *   [impactshop_deals_banners limit="12" category="" force="1"]
 *   (ha nincs más definíció): [impactshop_deals ...] is erre mutat
 *
 * Diagnosztika:
 *   https://app.sharity.hu/?impact_mini_probe=1
 *   → JSON: plugin aktív-e, bannerek száma, default_d1 mappa, ki kezeli az impactshop_deals rövidkódot
 *
 * UTF-8, BOM nélkül; NINCS záró "?>"
 */

if (!defined('SHARITY_BANNERS_CSV')) {
  define('SHARITY_BANNERS_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv');
}
if (!defined('SHARITY_BANNERS_TMP_CSV')) {
  define('SHARITY_BANNERS_TMP_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=470200079&single=true&output=csv');
}
if (!defined('SHARITY_SHOPS_PUB_CSV')) {
  define('SHARITY_SHOPS_PUB_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv');
}
if (!defined('SHARITY_GLOBAL_DEFAULT_D1')) {
  define('SHARITY_GLOBAL_DEFAULT_D1', 'bator-tabor-alapitvany');
}

/* ===== segédek ===== */
function sib_slug($s){ return strtolower(preg_replace('~[^a-z0-9\-]+~','-', (string)$s)); }
function sib_b64url($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function sib_http_get($u,$t=7){ $r=wp_remote_get($u,['timeout'=>$t,'redirection'=>3]); return is_wp_error($r)?[0,'']:[(int)wp_remote_retrieve_response_code($r),(string)wp_remote_retrieve_body($r)]; }
function sib_host($u){ $p=wp_parse_url($u); return isset($p['host'])?strtolower($p['host']):''; }

/* Shops mini-térkép: [slug => ['site','d1']] */
function sib_shops_minimap(){
  static $C=null; if($C!==null) return $C;
  $map=[];
  if(function_exists('impactshop_get_shops')){
    try{
      foreach((array)impactshop_get_shops() as $r){
        $slug=sib_slug($r['shop_slug']??''); if(!$slug) continue;
        $map[$slug]=[
          'site'=>$r['site']??($r['url']??$r['website']??''),
          'd1'  =>sib_slug($r['default_d1']??''),
        ];
      }
    }catch(\Throwable $e){}
  }
  if(!$map){
    list($code,$csv)=sib_http_get(SHARITY_SHOPS_PUB_CSV,7);
    if($code===200 && $csv){
      $lines=preg_split("/\r\n|\r|\n/",trim($csv)); $hdr=[];
      foreach($lines as $i=>$ln){
        if($ln==='') continue; $cols=str_getcsv($ln);
        if(!$i){ $hdr=$cols; continue; }
        $rec=[]; foreach($cols as $j=>$v){ $k=$hdr[$j]??('c'.$j); $rec[trim($k)]=$v; }
        $slug=sib_slug($rec['shop_slug']??''); if(!$slug) continue;
        $map[$slug]=[
          'site'=>$rec['site']??$rec['url']??$rec['website']??'',
          'd1'  =>sib_slug($rec['default_d1']??''),
        ];
      }
    }
  }
  return $C=$map;
}
function sib_default_d1($slug){ $m=sib_shops_minimap(); $slug=sib_slug($slug); $d=$m[$slug]['d1']??''; return $d?:sib_slug(SHARITY_GLOBAL_DEFAULT_D1); }
function sib_site($slug){ $m=sib_shops_minimap(); $slug=sib_slug($slug); return $m[$slug]['site']??''; }

/* Banners betöltés */
function sib_load_banners(){
  list($c,$csv)=sib_http_get(SHARITY_BANNERS_CSV,7);
  if($c!==200 || !$csv){ list($c,$csv)=sib_http_get(SHARITY_BANNERS_TMP_CSV,7); }
  if($c!==200 || !$csv) return [];
  $lines=preg_split("/\r\n|\r|\n/",trim($csv)); $hdr=[]; $out=[];
  foreach($lines as $i=>$ln){
    if($ln==='') continue; $cols=str_getcsv($ln);
    if(!$i){ $hdr=$cols; continue; }
    $rec=[]; foreach($cols as $j=>$v){ $k=$hdr[$j]??('c'.$j); $rec[trim($k)]=$v; }
    $slug=sib_slug($rec['slug']??$rec['shop_slug']??''); if(!$slug) continue;
    $href=trim($rec['href']??''); if(!$href) continue;
    $label=$rec['label']??[];
    if(is_string($label)){ $d=json_decode($label,true); if(is_array($d)) $label=$d; }
    $out[]=[
      'slug'=>$slug,
      'href'=>$href,
      'title'=>$label['title']??($rec['title']??'Ajánlat'),
      'price'=>$label['price']??($rec['price']??''),
      'old_price'=>$label['old_price']??($rec['old_price']??''),
      'pct'=>(int)($label['discount_pct']??($rec['discount_pct']??0)),
      'category'=>$rec['category']??'',
      'img'=>$rec['img']??($rec['image']??''),
    ];
  }
  return $out;
}

/* Linképítés – kezeli a Fillout href-et is (u= base64 terméklink) */
function sib_build_deal_link($slug,$href,$force=true){
  $slug=sib_slug($slug);
  $d1=sib_default_d1($slug);

  // Fillout host?
  if (strpos(sib_host($href),'fillout.com')!==false){
    $p=wp_parse_url($href); $q=[]; if(!empty($p['query'])) parse_str($p['query'],$q);
    if(!empty($q['shop'])) $slug=sib_slug($q['shop']);
    if(!empty($q['u'])){
      $b64=strtr($q['u'],'-_','+/'); $pad=strlen($b64)%4; if($pad)$b64.=str_repeat('=',4-$pad);
      $prod=base64_decode($b64)?:'';
      if($prod!==''){
        return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($prod)], home_url('/go-deal/'.rawurlencode($slug)));
      }
    }
    // nincs u → sima /go
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop'], home_url('/go/'.rawurlencode($slug)));
  }

  // Direkt terméklink
  if($force){
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($href)], home_url('/go-deal/'.rawurlencode($slug)));
  } else {
    // óvatos mód: csak akkor deeplink, ha egyezik a host a shop site-tal
    $site=sib_site($slug);
    $h1=ltrim(preg_replace('~^www\.~','', sib_host($href)));
    $h2=ltrim(preg_replace('~^www\.~','', sib_host($site)));
    $ok=($h1&&$h2)&&($h1===$h2 || (substr($h1,-strlen($h2)-1)==='.'.$h2));
    if($ok){
      return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($href)], home_url('/go-deal/'.rawurlencode($slug)));
    }
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop'], home_url('/go/'.rawurlencode($slug)));
  }
}

/* ===== Shortcode: Banners deals ===== */
function sib_sc_deals($atts){
  $a=shortcode_atts(['limit'=>'12','category'=>'','force'=>'1'],$atts,'impactshop_deals_banners');
  $force=$a['force']==='1';
  $items=sib_load_banners();
  if(!$items) return '<div>Jelenleg nincs megjeleníthető ajánlat.</div>';

  $catf=array_filter(array_map('trim', preg_split('~[|,]~',(string)$a['category'])));
  if($catf){
    $items=array_filter($items,function($it)use($catf){
      $hay=' '.strtolower((string)$it['category']).' ';
      foreach($catf as $n){ if($n!=='' && str_contains($hay, strtolower($n))) return true; }
      return false;
    });
  }
  $items=array_slice(array_values($items),0,max(1,(int)$a['limit']));
  $cards=[];
  foreach($items as $it){
    $go=sib_build_deal_link($it['slug'],$it['href'],$force);
    $img=$it['img']?'<img src="'.esc_url($it['img']).'" alt="">':'<div class="ph"></div>';
    $old=$it['old_price']?'<s class="old">'.esc_html($it['old_price']).'</s>':'';
    $pr =$it['price']?'<span class="price">'.esc_html($it['price']).'</span>':'';
    $pct=(int)$it['pct']>0?'<span class="badge">-'.(int)$it['pct'].'%</span>':'';
    $cat=$it['category']?'<div class="cat">'.esc_html($it['category']).'</div>':'';
    $cards[]='<a class="imx-deal" href="'.esc_url($go).'" target="_blank" rel="nofollow sponsored noopener">'
           .   '<div class="thumb">'.$img.'</div>'
           .   '<div class="body"><div class="title">'.esc_html($it['title']).'</div>'
           .   '<div class="row">'.$old.$pr.$pct.'</div>'.$cat.'</div>'
           . '</a>';
  }
  $css='<style>.imx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
  .imx-deal{display:flex;gap:.75rem;padding:.75rem;border:1px solid #eee;border-radius:14px;text-decoration:none;color:inherit;background:#fff;transition:transform .12s,box-shadow .12s}
  .imx-deal:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.06)}
  .imx-deal .thumb{flex:0 0 auto}.imx-deal img,.imx-deal .ph{width:88px;height:88px;object-fit:cover;border-radius:10px;background:#f7f7f7;display:block}
  .imx-deal .title{font-weight:600;line-height:1.25;margin-bottom:.25rem}.imx-deal .row{display:flex;align-items:center;gap:.5rem}
  .imx-deal .old{opacity:.6;text-decoration:line-through}.imx-deal .badge{font-size:.85em;padding:.15rem .4rem;border-radius:6px;background:#ffe8e8}
  .imx-deal .cat{font-size:.8em;opacity:.7;margin-top:.25rem}</style>';
  return $css.'<div class="imx-grid">'.implode('', $cards).'</div>';
}
add_shortcode('impactshop_deals_banners','sib_sc_deals');

/* Ha nincs más definíció, regisztráljuk az "impactshop_deals" taget is erre */
add_action('init', function(){
  global $shortcode_tags;
  if (empty($shortcode_tags['impactshop_deals'])) {
    add_shortcode('impactshop_deals','sib_sc_deals');
  }
  if (empty($shortcode_tags['impactshop_netflix'])) {
    add_shortcode('impactshop_netflix','sib_sc_deals');
  }
}, 1);

/* ==== Diagnosztika: ?impact_mini_probe=1 → JSON ==== */
add_action('init', function(){
  if (!isset($_GET['impact_mini_probe'])) return;
  header('Content-Type: application/json; charset=utf-8');
  global $shortcode_tags;
  $who = isset($shortcode_tags['impactshop_deals'])
          ? (is_array($shortcode_tags['impactshop_deals']) ? 'callable array' : (is_string($shortcode_tags['impactshop_deals'])?$shortcode_tags['impactshop_deals']:'callable'))
          : 'none';
  $b = sib_load_banners();
  $shops = sib_shops_minimap();
  $sample = isset($b[0]) ? [
    'slug'=>$b[0]['slug'],
    'href'=>$b[0]['href'],
    'built_link'=> sib_build_deal_link($b[0]['slug'],$b[0]['href'], true),
    'default_d1'=> sib_default_d1($b[0]['slug']),
  ] : null;
  echo wp_json_encode([
    'plugin' => 'sharity-impact-banners-deals (MU)',
    'impactshop_deals_owner' => $who,
    'banners_count' => count($b),
    'shops_count' => count($shops),
    'sample' => $sample,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
});
