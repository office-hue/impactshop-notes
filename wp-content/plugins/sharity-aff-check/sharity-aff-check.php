<?php
/*
Plugin Name: Sharity Affiliate Integrity Checker LITE (TZ)
Description: Egyszerű, fagyásbiztos diagnosztika Dognet Publisher API-hoz: link-generálás (deeplink/base), manuális kattintás, kattintások ellenőrzése (data1), időzóna-kezeléssel.
Version: 0.10
Author: Sharity
*/
if (!defined('ABSPATH')) exit;

class Sharity_Aff_Check_Lite_TZ {
    private $opt = 'sharity_aff_lite_opts';
    private $api = 'https://api.app.dognet.com/api/v1';

    public function __construct(){
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'reg']);
        add_action('admin_post_sharity_aff_lite_gen',   [$this,'handle_gen']);   // link generálás
        add_action('admin_post_sharity_aff_lite_fetch', [$this,'handle_fetch']); // kattintások
    }

    /* ---------------- UI + Settings ---------------- */

    public function menu(){
        add_menu_page('Sharity Aff LITE','Sharity Aff LITE','manage_options','sharity-aff-lite',[$this,'page'],'dashicons-shield',76);
    }
    public function reg(){ register_setting($this->opt, $this->opt); }

    private function get(){
        $o = get_option($this->opt,[]);
        return wp_parse_args($o,[
            'email'         => '',
            'password'      => '',
            'ad_channel_id' => '26081', // numerikus ID (nem CHID)
            'campaign_id'   => '223',   // pl. Vision Express (cid)
            'tz'            => '',      // Dognet timezone (auth-ból)
            'last_link'     => '',
            'last_data1'    => '',
            'last_from'     => '',
            'last_to'       => '',
            'raw_last'      => ''
        ]);
    }
    private function save($a){ update_option($this->opt,$a); }

    public function page(){
        if (!current_user_can('manage_options')) return;
        $o = $this->get();
        echo '<div class="wrap"><h1>Sharity Affiliate Integrity Checker LITE</h1>';

        // Beállítások mentése
        if (!empty($_POST['save']) && check_admin_referer('sharity_aff_lite_save')){
            $o['email']         = sanitize_text_field($_POST['email']);
            $o['password']      = sanitize_text_field($_POST['password']);
            $o['ad_channel_id'] = sanitize_text_field($_POST['ad_channel_id']);
            $o['campaign_id']   = sanitize_text_field($_POST['campaign_id']);
            $this->save($o);
            echo '<div class="updated"><p>Beállítások mentve.</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('sharity_aff_lite_save'); ?>
            <table class="form-table">
                <tr><th>Dognet e-mail</th><td><input class="regular-text" name="email" value="<?php echo esc_attr($o['email']); ?>"></td></tr>
                <tr><th>Dognet jelszó</th><td><input class="regular-text" type="password" name="password" value="<?php echo esc_attr($o['password']); ?>"></td></tr>
                <tr><th>Ad Channel ID</th><td><input class="regular-text" name="ad_channel_id" value="<?php echo esc_attr($o['ad_channel_id']); ?>"><br><small>Számozott ID (nem <code>chid</code>).</small></td></tr>
                <tr><th>Campaign ID</th><td><input class="regular-text" name="campaign_id" value="<?php echo esc_attr($o['campaign_id']); ?>"><br><small>A go.dognet <code>cid</code> értéke.</small></td></tr>
                <?php if (!empty($o['tz'])): ?>
                <tr><th>Dognet időzóna</th><td><code><?php echo esc_html($o['tz']); ?></code> <small>(auth válaszból)</small></td></tr>
                <?php endif; ?>
            </table>
            <p><button class="button button-primary" name="save" value="1">Mentés</button></p>
        </form>

        <hr><h2>1) Link generálása</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sharity_aff_lite_gen'); ?>
            <input type="hidden" name="action" value="sharity_aff_lite_gen">
            <table class="form-table">
                <tr><th>data1 (NGO kód)</th><td><input class="regular-text" name="data1" placeholder="pl. bator-tabor-alapitvany"></td></tr>
            </table>
            <p><button class="button">Link generálása</button></p>
        </form>

        <?php if (!empty($o['raw_last'])): ?>
        <div style="border:1px solid #ddd;padding:10px;border-radius:6px;background:#fff;margin-top:10px">
            <p><strong>Állapot:</strong> <?php echo esc_html($o['raw_last']); ?></p>
            <?php if (!empty($o['last_link'])): ?>
              <p><strong>Generált link:</strong>
                 <a target="_blank" href="<?php echo esc_url($o['last_link']); ?>">
                    <?php echo esc_html($o['last_link']); ?>
                 </a>
              </p>
              <p><em>Nyisd meg új fülön (AdBlock OFF) – ez hoz létre kattintást.</em></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <hr><h2>2) Kattintások frissítése (elmúlt 30 perc, Dognet TZ)</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sharity_aff_lite_fetch'); ?>
            <input type="hidden" name="action" value="sharity_aff_lite_fetch">
            <p><button class="button">Kattintások frissítése</button></p>
        </form>
        <?php
        if ($o['last_from'] && $o['last_to']) {
            echo '<h3>Eredmény</h3>';
            echo '<p>Időablak: <code>'.esc_html($o['last_from']).'</code> → <code>'.esc_html($o['last_to']).'</code>'.(!empty($o['tz']) ? ' (TZ: <code>'.esc_html($o['tz']).'</code>)' : '').'</p>';
        }
        echo '<p style="margin-top:24px;color:#555">Használt végpontok: <code>/auth/login</code>, <code>/campaigns/links/generate</code>, <code>/clicks/filter</code> (Bearer token, 24h). </p>';
        echo '</div>';
    }

    /* ---------------- Handlers ---------------- */

    // 1) Link generálás – azonnali állapotkiírással
    public function handle_gen(){
        if (!current_user_can('manage_options')) wp_die();
        check_admin_referer('sharity_aff_lite_gen');
        $o = $this->get();
        $data1 = sanitize_text_field($_POST['data1'] ?? '');
        $o['raw_last'] = ''; // előző üzenet törlése

        if (!$o['email']||!$o['password']||!$o['ad_channel_id']||!$o['campaign_id']||!$data1){
            $o['last_link'] = '';
            $o['raw_last']  = 'Hiányzó beállítás: email/jelszó, ad_channel_id, campaign_id, vagy data1.';
            $this->save($o); wp_redirect(admin_url('admin.php?page=sharity-aff-lite')); exit;
        }

        // AUTH – /auth/login → token (24h) + timezone jön vissza.  [oai_citation:1‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
        $token = $this->auth($o);
        if (!$token){
            $o['last_link'] = '';
            $o['raw_last']  = 'AUTH HIBA – ellenőrizd az email/jelszó párost.';
            $this->save($o); wp_redirect(admin_url('admin.php?page=sharity-aff-lite')); exit;
        }

        // Link generálás – előbb deeplink, ha nem megy, base. /campaigns/links/generate  [oai_citation:2‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
        $link = $this->gen_link($token,(int)$o['ad_channel_id'],(int)$o['campaign_id'],home_url('/'),$data1);
        if (!$link) $link = $this->gen_link($token,(int)$o['ad_channel_id'],(int)$o['campaign_id'],null,$data1);

        $o['last_data1'] = $data1;
        if ($link){
            $o['last_link'] = $link;
            $o['raw_last']  = 'OK – Link generálva. Kattints rá új fülön, majd gyere vissza és nyomd meg a „Kattintások frissítése” gombot.';
        } else {
            $o['last_link'] = '';
            $o['raw_last']  = "Nem sikerült linket generálni. Gyakori okok: (1) a kampány nincs jóváhagyva erre az Ad Channel ID-re; (2) a kampány tiltja a deeplinket és a base sem engedett.";
        }
        $this->save($o); wp_redirect(admin_url('admin.php?page=sharity-aff-lite')); exit;
    }

    // 2) Kattintások – Dognet időzónában számolt időablak (30 perc)
    public function handle_fetch(){
        if (!current_user_can('manage_options')) wp_die();
        check_admin_referer('sharity_aff_lite_fetch');
        $o = $this->get();

        // AUTH (token cache a transientsben). /auth/login  [oai_citation:3‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
        $token = $this->auth($o);
        if (!$token){
            $o['raw_last']='AUTH HIBA a kattintások lekérésekor.';
            $this->save($o); wp_redirect(admin_url('admin.php?page=sharity-aff-lite')); exit;
        }

        // Dognet TZ (auth válaszból), fallback a doksi szerinti értékre
        $tzName = !empty($o['tz']) ? $o['tz'] : 'Europe/Bratislava';
        try { $tz = new DateTimeZone($tzName); } catch (\Exception $e) { $tz = new DateTimeZone('Europe/Bratislava'); }
        $now = new DateTime('now', $tz);

        // 30 perces ablak – puffer a késleltetésre
        $to   = $now->format('Y-m-d H:i:s');
        $from = (clone $now)->sub(new DateInterval('PT30M'))->format('Y-m-d H:i:s');

        // /clicks/filter – numerikus ad_channel_id + időablak.  [oai_citation:4‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
        $clicks = $this->filter_clicks($token,(int)$o['ad_channel_id'],$from,$to,100);
        $found=false; $with=false;
        if (is_array($clicks)) {
            foreach ($clicks as $c) {
                if ((int)($c['ad_channel_id']??0)===(int)$o['ad_channel_id']) {
                    $found = true;
                    $d1 = $c['data1'] ?? ($c['meta']['data1'] ?? null);
                    if ($d1!==null && (string)$d1===(string)$o['last_data1']) { $with=true; break; }
                }
            }
        }

        $sum = $found ? ($with ? "OK — megvan a data1 („{$o['last_data1']}”)."
                               : "FIGYELEM — kattintás van, de NINCS data1.")
                      : "Még nincs kattintás ebben az időablakban.";
        $o['last_from']=$from; $o['last_to']=$to;
        $o['raw_last']= $sum . "\n\nTZ: {$tzName}\nMintavétel (első 3 rekord):\n" .
            substr(wp_json_encode(array_slice((array)$clicks,0,3),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),0,6000);
        $this->save($o); wp_redirect(admin_url('admin.php?page=sharity-aff-lite')); exit;
    }

    /* ---------------- API helpers ---------------- */

    private function http_post_json($url,$body,$headers=[]){
        $args=[
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => array_merge(['Content-Type'=>'application/json'],$headers),
            'body'    => wp_json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        ];
        $res = wp_remote_post($url,$args);
        if (is_wp_error($res)) return [0,null,$res->get_error_message()];
        return [wp_remote_retrieve_response_code($res), json_decode(wp_remote_retrieve_body($res),true), null];
    }

    // /auth/login – token (24h) + timezone. Token cache 20 órára (használattal hosszabbodik).  [oai_citation:5‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
    private function auth(&$o){
        $email = $o['email']; $password = $o['password'];
        if (!$email || !$password) return false;

        $cache='sharity_dognet_token_'.md5($email);
        $t=get_transient($cache);
        if (is_string($t) && strlen($t)>10) return $t;

        list($code,$json,)=$this->http_post_json($this->api.'/auth/login',['email'=>$email,'password'=>$password]);
        $tok = $json['token'] ?? ($json['data']['token'] ?? '');
        if ($code>=200 && $code<300 && is_string($tok) && strlen($tok)>10) {
            // időzóna mentése (pl. Europe/Bratislava)
            $tz = $json['timezone'] ?? ($json['data']['timezone'] ?? null);
            if ($tz && is_string($tz)) { $o['tz']=$tz; $this->save($o); }
            set_transient($cache,$tok,20*HOUR_IN_SECONDS);
            return $tok;
        }
        return false;
    }

    // /campaigns/links/generate – deeplink próba, majd base fallback.  [oai_citation:6‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
    private function gen_link($token,$ad_channel_id,$campaign_id,$url,$data1){
        $payload = ['ad_channel_id'=>$ad_channel_id,'campaign_id'=>$campaign_id,'data1'=>$data1,'url_type'=>3];
        if ($url) $payload['url']=$url;
        list($code,$json,)=$this->http_post_json($this->api.'/campaigns/links/generate',$payload,['Authorization'=>'Bearer '.$token]);
        if ($code>=200 && $code<300 && is_array($json)){
            // több lehetséges helyről próbálunk URL-t kinyerni
            foreach (['link','url','data.link','data.url','generated_link'] as $p){
                $v=$this->pluck($json,$p); if (is_string($v) && strpos($v,'http')===0) return $v;
            }
            if (isset($json['chid']) && isset($json['url'])) return 'https://go.dognet.com/?chid='.$json['chid'].'&url='.rawurlencode($json['url']);
        }
        return false;
    }

    // /clicks/filter – ad_channel_id + időablak.  [oai_citation:7‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)
    private function filter_clicks($token,$ad_channel_id,$from,$to,$per=50){
        $payload=['filter'=>[
            ['ad_channel_id'=>['eq'=>$ad_channel_id]],
            ['created_at'   =>['gte'=>$from]],
            ['created_at'   =>['lte'=>$to]],
        ],'per-page'=>$per];
        list($code,$json,)=$this->http_post_json($this->api.'/clicks/filter',$payload,['Authorization'=>'Bearer '.$token]);
        return ($code>=200 && $code<300 && isset($json['data'])) ? $json['data'] : [];
    }

    private function pluck($arr,$path){ $v=$arr; foreach(explode('.',$path) as $k){ if(!isset($v[$k])) return null; $v=$v[$k]; } return $v; }
}

new Sharity_Aff_Check_Lite_TZ();