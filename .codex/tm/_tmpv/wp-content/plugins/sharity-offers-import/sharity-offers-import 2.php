<?php
/**
 * Plugin Name: Sharity Offers Import
 * Description: Több XML feed (Dognet/Árukereső stb.) importálása Offer (Ajánlat) CPT-be. Akció-szűrés (oldprice>price), képek, Fillout CTA, óránkénti frissítés.
 * Version: 1.1.1
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

final class Sharity_Offers_Import {
  const OPT_KEY = 'sharity_offers_settings';
  const CRON_HOOK = 'sharity_offers_import_cron';
  const SKU_META = '_sku';
  const LAST_SEEN_META = '_last_seen_ts';
  const SOURCE_TAG_META = '_source_shop';

  public function __construct() {
    add_action('init', [$this,'register_cpt_and_tax']);
    add_action('admin_menu', [$this,'admin_menu']);
    add_action('admin_init', [$this,'register_settings']);
    add_action(self::CRON_HOOK, [$this,'run_import']);
    add_shortcode('offers', [$this,'offers_grid_shortcode']);
  }

  /** Activation / Deactivation */
  public static function on_activate() {
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      wp_schedule_event(time()+300, 'hourly', self::CRON_HOOK);
    }
    // CPT azonnali regisztráció + permalinks frissítés
    $me = new self();
    $me->register_cpt_and_tax();
    flush_rewrite_rules();
  }
  public static function on_deactivate() {
    wp_clear_scheduled_hook(self::CRON_HOOK);
    flush_rewrite_rules();
  }

  /** CPT + Taxonomy */
  public function register_cpt_and_tax(){
    register_post_type('offer', [
      'labels' => ['name'=>'Ajánlatok','singular_name'=>'Ajánlat'],
      'public' => true,
      'has_archive' => true,
      'rewrite' => ['slug'=>'akciok'],
      'supports' => ['title','thumbnail','editor','excerpt'],
      'show_in_rest' => true,
      'menu_icon' => 'dashicons-tag',
    ]);
    register_taxonomy('shop', 'offer', [
      'labels' => ['name'=>'Shopok','singular_name'=>'Shop'],
      'hierarchical' => false,
      'show_in_rest' => true,
    ]);
  }

  /** Admin settings page */
  public function admin_menu(){
    add_options_page('Sharity Offers Import','Sharity Offers Import','manage_options','sharity-offers-import',[$this,'settings_page']);
  }
  public function register_settings(){
    register_setting(self::OPT_KEY, self::OPT_KEY, [$this,'sanitize_opts']);
    add_settings_section('main','Feed és megjelenítés beállítások','__return_false', self::OPT_KEY);
    add_settings_field('feeds_block','Feedek (shop|url – soronként)',['Sharity_Offers_Import','field_feeds_block'], self::OPT_KEY,'main', ['me'=>$this]);
    add_settings_field('fillout_form_id','Fillout form ID',['Sharity_Offers_Import','field_form_id'], self::OPT_KEY,'main', ['me'=>$this]);
  }
  public function sanitize_opts($opts){
    $clean = [];
    $clean['feeds_block'] = isset($opts['feeds_block']) ? trim(wp_kses_post($opts['feeds_block'])) : '';
    $clean['fillout_form_id'] = isset($opts['fillout_form_id']) ? sanitize_text_field($opts['fillout_form_id']) : '';
    return $clean;
  }
  public function get_opts(){
    $defaults = ['feeds_block'=>'','fillout_form_id'=>''];
    return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
  }
  public static function field_feeds_block($args){
    $me = $args['me']; $o = $me->get_opts(); ?>
    <textarea name="<?php echo esc_attr(self::OPT_KEY.'[feeds_block]'); ?>" rows="10" style="width:100%;max-width:800px;" placeholder="arukereso-filmzene|https://...xml&#10;masikshop|https://...xml"><?php echo esc_textarea($o['feeds_block']); ?></textarea>
    <p class="description">Egy sor = <code>shop_slug|feed_url</code>. Üres sor és <code>#</code>-tel kezdődő megjegyzés megengedett.</p>
    <?php
  }
  public static function field_form_id($args){
    $me = $args['me']; $o = $me->get_opts(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPT_KEY.'[fillout_form_id]'); ?>" value="<?php echo esc_attr($o['fillout_form_id']); ?>" class="regular-text" placeholder="pl. abcDEF123"/>
    <p class="description">A Fillout űrlap azonosítója. A CTA erre mutat.</p>
    <?php
  }

  public function settings_page(){
    if (!current_user_can('manage_options')) return;
    if (isset($_POST['sharity_run_import_now']) && check_admin_referer('sharity_run_import')) {
      $report = $this->run_import();
      echo '<div class="updated"><p><strong>Import lefutott.</strong></p><pre style="white-space:pre-wrap;">'.esc_html($report).'</pre></div>';
    } ?>
    <div class="wrap">
      <h1>Sharity Offers Import</h1>
      <form method="post" action="options.php">
        <?php settings_fields(self::OPT_KEY); do_settings_sections(self::OPT_KEY); submit_button('Beállítások mentése'); ?>
      </form>
      <hr>
      <h2>Kézi indítás</h2>
      <form method="post">
        <?php wp_nonce_field('sharity_run_import'); ?>
        <p><button class="button button-primary">Import most</button></p>
      </form>
      <p class="description">Az import óránként automatikusan fut (WP cron).</p>
    </div>
    <?php
  }

  /** Import runner (multiple feeds) */
  public function run_import(){
    $o = $this->get_opts();
    $pairs = $this->parse_feeds_block($o['feeds_block']);
    if (empty($pairs)) return "Nincs megadva feed.";
    $start = time();
    $reports = [];
    foreach ($pairs as $pair){
      list($shop_slug, $feed_url) = $pair;
      $r = $this->process_single_feed($feed_url, $shop_slug, $start);
      $reports[] = sprintf("[%s] Kész: %d, Frissült: %d, Kihagyva (nem akció): %d, Hibák: %d",
        $shop_slug, $r['created'], $r['updated'], $r['skipped'], $r['errors']);
    }
    return implode("\n", $reports);
  }

  private function parse_feeds_block($text){
    $out = [];
    $lines = preg_split("/\r\n|\n|\r/", (string)$text);
    foreach ($lines as $line){
      $line = trim($line);
      if ($line==='' || strpos($line,'#')===0) continue;
      $parts = explode('|', $line, 2);
      if (count($parts)!==2) continue;
      $shop = sanitize_title(trim($parts[0]));
      $url  = esc_url_raw(trim($parts[1]));
      if ($shop && $url) $out[] = [$shop,$url];
    }
    return $out;
  }

  private function process_single_feed($feed_url, $shop_slug, $run_ts){
    $created=$updated=$skipped=$errors=0;

    // 1) Download
    $resp = wp_remote_get($feed_url, ['timeout'=>45]);
    if (is_wp_error($resp)) return compact('created','updated','skipped','errors') + ['errors'=>1];
    $body = wp_remote_retrieve_body($resp);
    if (!$body) return compact('created','updated','skipped','errors') + ['errors'=>1];

    // 2) Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    if (!$xml) return compact('created','updated','skipped','errors') + ['errors'=>1];

    // 3) Items
    if (isset($xml->product))           $products = $xml->product;
    elseif (isset($xml->products->product)) $products = $xml->products->product;
    elseif (isset($xml->channel->item)) $products = $xml->channel->item;
    else                                 $products = [];

    // 4) Ensure shop term
    $term = term_exists($shop_slug, 'shop');
    if (!$term) $term = wp_insert_term($shop_slug, 'shop', ['slug'=>$shop_slug,'description'=>'Automatikus']);

    foreach ($products as $p){
      $item_id    = $this->sx($p, 'item_id') ?: $this->sx($p, 'id') ?: $this->sx($p, 'sku');
      $name       = $this->sx($p, 'name') ?: $this->sx($p, 'title');
      $price      = floatval($this->sx($p, 'price'));
      $oldprice   = floatval($this->sx($p, 'oldprice'));
      $product_url= $this->sx($p, 'product_url') ?: $this->sx($p, 'url') ?: '';
      $picture    = $this->sx($p, 'picture') ?: $this->sx($p, 'image') ?: '';
      $instock    = intval($this->sx($p, 'instock') ?: $this->sx($p, 'stock') ?: 1);

      if (!$item_id || !$name){ $skipped++; continue; }
      if (!($oldprice>0 && $price>0 && $oldprice>$price)){ $skipped++; continue; }

      $existing = $this->find_offer_by_sku($item_id);
      $discount_pct = max(0, round((($oldprice - $price)/$oldprice)*100));

      $postarr = [
        'post_type' => 'offer',
        'post_status' => 'publish',
        'post_title' => wp_strip_all_tags($name),
        'post_content' => '',
      ];

      if ($existing){
        $postarr['ID'] = $existing->ID;
        $post_id = wp_update_post($postarr, true);
        if (is_wp_error($post_id)){ $errors++; continue; }
        $updated++;
      } else {
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)){ $errors++; continue; }
        add_post_meta($post_id, self::SKU_META, (string)$item_id, true);
        $created++;
      }

      update_post_meta($post_id,'price',$oldprice);
      update_post_meta($post_id,'sale_price',$price);
      update_post_meta($post_id,'discount_pct',$discount_pct);
      update_post_meta($post_id,'product_url',esc_url_raw($product_url));
      update_post_meta($post_id,'image_url',esc_url_raw($picture));
      update_post_meta($post_id,'in_stock',$instock);
      update_post_meta($post_id,self::LAST_SEEN_META,$run_ts);
      update_post_meta($post_id,self::SOURCE_TAG_META,$shop_slug);

      if (!is_wp_error($term)){
        wp_set_object_terms($post_id, intval($term['term_id'] ?? $term), 'shop', false);
      }
      if ($picture && !has_post_thumbnail($post_id)){
        $this->set_featured_image($picture, $post_id);
      }
    }

    // 5) Draft missing for this shop
    $this->draft_missing_offers($run_ts, $shop_slug);

    return compact('created','updated','skipped','errors');
  }

  private function sx($node, $key){
    return isset($node->{$key}) ? (string)$node->{$key} : '';
  }
  private function find_offer_by_sku($sku){
    $q = new WP_Query([
      'post_type'=>'offer','post_status'=>'any','posts_per_page'=>1,
      'meta_query'=>[['key'=>self::SKU_META,'value'=>$sku,'compare'=>'=']],
      'fields'=>'ids'
    ]);
    if ($q->have_posts()){ $id=$q->posts[0]; return get_post($id); }
    return null;
  }
  private function set_featured_image($image_url, $post_id){
    if (!function_exists('media_sideload_image')) require_once ABSPATH.'wp-admin/includes/media.php';
    if (!function_exists('download_url')) require_once ABSPATH.'wp-admin/includes/file.php';
    if (!function_exists('wp_read_image_metadata')) require_once ABSPATH.'wp-admin/includes/image.php';
    $tmp = download_url($image_url, 45);
    if (is_wp_error($tmp)) return false;
    $file_array = [
      'name' => basename(parse_url($image_url, PHP_URL_PATH) ?: ('img_'.md5($image_url).'.jpg')),
      'tmp_name' => $tmp
    ];
    $id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($id)){ @unlink($tmp); return false; }
    set_post_thumbnail($post_id, $id);
    return true;
  }
  private function draft_missing_offers($run_ts, $shop_slug){
    $q = new WP_Query([
      'post_type'=>'offer','post_status'=>'publish','posts_per_page'=>-1,
      'tax_query'=>[['taxonomy'=>'shop','field'=>'slug','terms'=>[$shop_slug]]],
      'meta_query'=>[
        ['key'=>self::SOURCE_TAG_META,'value'=>$shop_slug,'compare'=>'='],
        ['key'=>self::LAST_SEEN_META,'value'=>$run_ts-1,'compare'=>'<','type'=>'NUMERIC']
      ]
    ]);
    while($q->have_posts()){ $q->the_post(); wp_update_post(['ID'=>get_the_ID(),'post_status'=>'draft']); }
    wp_reset_postdata();
  }

  /** Shortcode with Fillout CTA */
  public function offers_grid_shortcode($atts){
    $o = $this->get_opts();
    $a = shortcode_atts([
      'shop'=>'','orderby'=>'discount','order'=>'DESC','per_page'=>24,
    ], $atts);
    $meta_key = ($a['orderby']==='expiry') ? 'valid_to' : 'discount_pct';
    $args = [
      'post_type'=>'offer','post_status'=>'publish','posts_per_page'=>intval($a['per_page']),
      'orderby'=>($meta_key==='valid_to'?'meta_value':'meta_value_num'),
      'meta_key'=>$meta_key,'order'=>$a['order'],
      'meta_query'=>[['key'=>'discount_pct','value'=>0,'compare'=>'>','type'=>'NUMERIC']],
    ];
    if (!empty($a['shop'])){
      $args['tax_query']=[['taxonomy'=>'shop','field'=>'slug','terms'=>array_map('trim', explode(',', $a['shop']))]];
    }
    $q = new WP_Query($args); ob_start();
    if ($q->have_posts()){
      echo '<div class="offers-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">';
      while($q->have_posts()){ $q->the_post();
        $price=get_post_meta(get_the_ID(),'price',true);
        $sale =get_post_meta(get_the_ID(),'sale_price',true);
        $disc =get_post_meta(get_the_ID(),'discount_pct',true);
        $img  =get_the_post_thumbnail_url(get_the_ID(),'medium') ?: esc_url(get_post_meta(get_the_ID(),'image_url',true));
        $cta  = esc_url( $this->fillout_url(get_the_ID(), $o['fillout_form_id']) );
        echo '<article class="offer-card" style="position:relative;border:1px solid #eee;border-radius:12px;padding:12px;background:#fff;">';
        if($disc){ echo '<span style="position:absolute;top:8px;left:8px;background:#f00;color:#fff;border-radius:999px;padding:4px 8px;font-weight:600;">-'.intval($disc).'%</span>'; }
        if($img){ echo '<a href="'.$cta.'"><img src="'.$img.'" alt="'.esc_attr(get_the_title()).'" style="width:100%;height:auto;border-radius:8px;"></a>'; }
        echo '<h3 style="font-size:16px;line-height:1.3;margin:8px 0;"><a href="'.$cta.'" style="text-decoration:none;">'.esc_html(get_the_title()).'</a></h3>';
        echo '<div>';
        if($sale){ echo '<span style="font-weight:700;">'.number_format_i18n($sale,0).' Ft</span> '; if($price){ echo '<s style="color:#888;margin-left:6px;">'.number_format_i18n($price,0).' Ft</s>'; } }
        elseif($price){ echo '<span>'.number_format_i18n($price,0).' Ft</span>'; }
        echo '</div>';
        echo '<a href="'.$cta.'" class="offer-cta" style="display:inline-block;margin-top:8px;background:#111;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;">Vásárlással támogatok</a>';
        echo '</article>';
      }
      echo '</div>'; wp_reset_postdata();
    } else {
      echo '<p>Jelenleg nincs akciós ajánlat.</p>';
    }
    return ob_get_clean();
  }

  private function fillout_url($post_id, $form_id){
    if (!$form_id) return '#';
    $shop_terms = wp_get_post_terms($post_id,'shop',['fields'=>'slugs']);
    $shop = $shop_terms ? $shop_terms[0] : 'shop';
    $amb  = isset($_COOKIE['amb']) ? sanitize_text_field($_COOKIE['amb']) : '';
    $target = get_post_meta($post_id,'product_url',true);
    $params = ['shop'=>$shop,'amb'=>$amb,'target'=>$target,'src'=>'impactshop'];
    return 'https://fillout.com/forms/'.$form_id.'?'. http_build_query($params);
  }
}

/** Bootstrap */
add_action('plugins_loaded', function(){ $GLOBALS['sharity_offers_import'] = new Sharity_Offers_Import(); });
register_activation_hook(__FILE__, ['Sharity_Offers_Import','on_activate']);
register_deactivation_hook(__FILE__, ['Sharity_Offers_Import','on_deactivate']);
