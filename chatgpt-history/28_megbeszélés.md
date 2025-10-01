Átugrás a tartalomra
Ezt mondtad:
Szeretnék a korábbi egyeztetésnek megfelelően akkor egy chat / Facebook feede-t, ahol lehet irogatni és kitenni ha valaki adományozott
A ChatGPT ezt mondta:

Gondolkozás
Menő! Csináljunk egy „mikro-feedet”: helyi chatfal + automatikusan felpattanó „Friss adományok” (a már létező Impact Activity API-t figyelve) + opcionális Facebook Page beágyazás. A lenti egyetlen beilleszthető WP-snippet:
Shortcode: [impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage" max_items="50"]
Tulajdonságok: show_form (0/1), show_fb (0/1), fb_page (Page URL), max_items (mentett chatüzenetek limitje).
Funkciók: gyors, moderálható (alapból jóváhagyás nélkül jelenik meg, de könnyen bővíthető), IP-alapú rate-limit, XSS védelem, Activity stream 30 mp-enként frissül.
A „Friss adományok” az eddig is használt /wp-json/impact/v1/activity végpontot hívja (tőletek jön), stílusban illeszkedik az „Impact” UI-hoz.  
Ha később a Dognet Publisher API adataival szeretnénk bővíteni a bejegyzéseket, a meglévő integrációtok ezt tudja szolgálni.  
Kód – másold be egyetlen blokkban (pl. WPCode/MU-plugin):
<?php
/**
 * Impact Microfeed – chat + „Friss adományok” + opcionális Facebook feed
 * Shortcode: [impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage" max_items="50"]
 * Függ: WordPress REST; Impact Activity JSON: /wp-json/impact/v1/activity
 */

if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu'); // meglévő konvenció
if (!function_exists('sharity_impact_fetch')) {
  function sharity_impact_fetch($path){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}

/* ---------- Állandók ---------- */
if (!defined('IMPACT_MICROFEED_OPT')) define('IMPACT_MICROFEED_OPT', 'impact_microfeed_msgs_v1');
if (!defined('IMPACT_MICROFEED_MAX')) define('IMPACT_MICROFEED_MAX', 200); // globális felső limit (biztonsági)

/* ---------- REST: Üzenet beküldés + listázás ---------- */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/microfeed',[
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $max = max(1, min(IMPACT_MICROFEED_MAX, intval($req->get_param('max') ?: 50)));
      $all = get_option(IMPACT_MICROFEED_OPT, []);
      if (!is_array($all)) $all = [];
      // visszafelé időrend, max X
      $out = array_slice(array_reverse($all), 0, $max);
      return rest_ensure_response(['items'=>$out,'count'=>count($out)]);
    },
    'permission_callback' => '__return_true'
  ]);

  register_rest_route('impact/v1','/microfeed',[
    'methods'  => 'POST',
    'callback' => 'impact_microfeed_post_message',
    'permission_callback' => function(){ return is_user_logged_in() || wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'wp_rest' ); }
  ]);
});

function impact_microfeed_post_message(WP_REST_Request $req){
  // Rate limit IP-nként (60 mp)
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'impact_microfeed_rl_'.md5($ip);
  if (get_transient($key)) {
    return new WP_Error('ratelimit','Túl gyorsan küldesz. Próbáld újra kicsit később.',['status'=>429]);
  }
  set_transient($key, 1, 60);

  $name = trim(wp_strip_all_tags((string)$req->get_param('name')));
  $text = trim((string)$req->get_param('text'));
  $hp   = trim((string)$req->get_param('company')); // honeypot (láthatatlan mező)
  if ($hp !== '') return new WP_Error('spam','Hiba.',['status'=>400]);

  if ($name === '' || $text === '') {
    return new WP_Error('badreq','Név és üzenet kötelező.',['status'=>400]);
  }

  // kemény limit + sorvédelem
  $name = mb_substr($name, 0, 60);
  $text = wp_kses($text, []); // semmilyen HTML
  $text = preg_replace('/\s+/', ' ', $text);
  $text = mb_substr($text, 0, 280);

  $row = [
    'ts'   => current_time('mysql'),
    'name' => $name,
    'text' => $text,
  ];

  $all = get_option(IMPACT_MICROFEED_OPT, []);
  if (!is_array($all)) $all = [];
  $all[] = $row;
  // tárolt méret limitálása
  $maxStored = max(1, min(IMPACT_MICROFEED_MAX, intval(get_option('impact_microfeed_max_items', 200))));
  if (count($all) > $maxStored) {
    $all = array_slice($all, -$maxStored);
  }
  update_option(IMPACT_MICROFEED_OPT, $all, false);

  return rest_ensure_response(['ok'=>true, 'item'=>$row]);
}

/* ---------- Shortcode ---------- */
add_shortcode('impact_microfeed', function($atts){
  $a = shortcode_atts([
    'show_form' => '1',
    'show_fb'   => '1',
    'fb_page'   => '',
    'max_items' => '50',
    'title'     => 'Közösségi fal',
  ], $atts, 'impact_microfeed');

  $nonce = wp_create_nonce('wp_rest');
  $api_base   = esc_url_raw( rest_url('impact/v1/microfeed') );
  $activityEP = esc_url_raw( rtrim(IMPACT_API_BASE_HOST,'/').'/wp-json/impact/v1/activity' );

  ob_start(); ?>
  <style>
    .impact-microfeed{display:grid;gap:16px}
    .impact-microfeed .card{background:#fff;border:1px solid #e6e8ea;border-radius:12px;padding:12px}
    .impact-microfeed h3{margin:0 0 8px 0;font:600 18px/1.2 system-ui}
    .imf-list{display:grid;gap:8px;max-height:420px;overflow:auto;padding-right:4px}
    .imf-item{display:flex;gap:10px}
    .imf-avatar{width:36px;height:36px;border-radius:50%;background:#f1f5f9;flex:0 0 36px;display:flex;align-items:center;justify-content:center;font-weight:700}
    .imf-meta{font-size:12px;color:#667085}
    .imf-msg{font-size:14px;margin-top:2px}
    .imf-form{display:grid;gap:8px}
    .imf-form input,.imf-form textarea{width:100%;padding:10px;border:1px solid #d0d5dd;border-radius:10px}
    .imf-form button{padding:10px 14px;border-radius:10px;border:0;background:#111827;color:#fff;font-weight:700;cursor:pointer}
    .imf-activity ul{margin:0;padding-left:1rem;display:grid;gap:.25rem}
    .imf-fb{min-height:140px}
    @media (prefers-color-scheme: dark){
      .impact-microfeed .card{background:#0b0f19;border-color:#1f2937;color:#e5e7eb}
      .imf-form input,.imf-form textarea{background:#0b0f19;border-color:#1f2937;color:#e5e7eb}
      .imf-form button{background:#2563eb}
    }
  </style>

  <div class="impact-microfeed" data-api="<?php echo esc_attr($api_base); ?>" data-activity="<?php echo esc_attr($activityEP); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-max="<?php echo intval($a['max_items']); ?>">
    <div class="card">
      <h3><?php echo esc_html($a['title']); ?></h3>
      <div class="imf-list" id="imf-list" aria-live="polite"></div>
      <?php if ($a['show_form']==='1'): ?>
        <form class="imf-form" id="imf-form">
          <input type="text" name="name" placeholder="Neved" maxlength="60" required>
          <textarea name="text" rows="3" placeholder="Írj valamit a falra (max 280 karakter)..." maxlength="280" required></textarea>
          <input type="text" name="company" value="" style="display:none"> <!-- honeypot -->
          <button type="submit">Küldés</button>
          <div id="imf-status" style="font-size:12px;color:#64748b"></div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card imf-activity">
      <h3>Friss adományok</h3>
      <ul id="imf-activity"></ul>
      <div style="font-size:12px;color:#667085;margin-top:6px">Automatikus frissítés 30 mp-enként</div>
    </div>

    <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
      <div class="card imf-fb">
        <h3>Facebook feed</h3>
        <div id="fb-root"></div>
        <div class="fb-page"
             data-href="<?php echo esc_attr($a['fb_page']); ?>"
             data-tabs="timeline"
             data-width="500"
             data-height=""
             data-small-header="false"
             data-adapt-container-width="true"
             data-hide-cover="false"
             data-show-facepile="true"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/hu_HU/sdk.js#xfbml=1&version=v17.0"></script>
      </div>
    <?php endif; ?>
  </div>

  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    var api  = root.getAttribute('data-api');
    var act  = root.getAttribute('data-activity');
    var max  = parseInt(root.getAttribute('data-max')||'50',10);
    var nonce= root.getAttribute('data-nonce');
    var list = root.querySelector('#imf-list');
    var form = root.querySelector('#imf-form');
    var status = root.querySelector('#imf-status');
    var ulAct = root.querySelector('#imf-activity');

    function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function initials(n){var p=(n||'').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase();return p||'😊';}

    async function loadChat(){
      try{
        let r = await fetch(api + '?max=' + encodeURIComponent(max), {headers:{'Accept':'application/json'}});
        if(!r.ok) throw 0;
        let j = await r.json();
        list.innerHTML = (j.items||[]).map(it=>{
          return '<div class="imf-item">'
               +   '<div class="imf-avatar">'+esc(initials(it.name||''))+'</div>'
               +   '<div class="imf-body"><div class="imf-meta">'+esc(it.name||'Ismeretlen')+' • '+esc(it.ts||'')+'</div>'
               +   '<div class="imf-msg">'+esc(it.text||'')+'</div></div>'
               + '</div>';
        }).join('') || '<div style="opacity:.7">Még nincs üzenet.</div>';
      }catch(e){
        list.innerHTML = '<div style="color:#dc2626">Hiba a chat betöltésekor.</div>';
      }
    }

    async function loadActivity(){
      try{
        let r = await fetch(act, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if(!r.ok) throw 0;
        let j = await r.json();
        if(!Array.isArray(j)) j = [];
        ulAct.innerHTML = j.map(row => '<li>'+esc(row.text||'')+'</li>').join('') || '<li style="opacity:.7">Még nincs friss aktivitás.</li>';
      }catch(e){
        ulAct.innerHTML = '<li style="color:#dc2626">Hiba az aktivitás betöltésekor.</li>';
      }
    }

    if (form){
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        status.textContent = 'Küldés…';
        let fd = new FormData(form);
        try{
          let r = await fetch(api, {
            method:'POST',
            headers:{'X-WP-Nonce': nonce},
            body: fd
          });
          let ok = r.ok;
          let j = await r.json().catch(()=>({}));
          if(!ok) throw (j && j.message) || 'Ismeretlen hiba';
          form.reset();
          status.textContent = 'Elmentve.';
          loadChat();
          setTimeout(()=>status.textContent='',1500);
        }catch(err){
          status.textContent = 'Hiba: '+(err && err.toString ? err.toString() : 'ismeretlen');
        }
      });
    }

    loadChat(); loadActivity();
    setInterval(loadActivity, 30000);
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ---------- Admin segéd: max tárolt üzenetek ---------- */
add_action('admin_init', function(){
  register_setting('reading','impact_microfeed_max_items',['type'=>'integer','default'=>200]);
  add_settings_field('impact_microfeed_max_items','Impact Microfeed – max üzenet (tárolt)','impact_microfeed_max_items_cb','reading');
});
function impact_microfeed_max_items_cb(){
  $v = intval(get_option('impact_microfeed_max_items',200));
  echo '<input type="number" min="10" max="'.IMPACT_MICROFEED_MAX.'" name="impact_microfeed_max_items" value="'.esc_attr($v).'"> <small>Mentett chat bejegyzések felső határa</small>';
}
Használat
Tedd be (WPCode vagy MU-plugin), illeszd be az oldalon:
[impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage"]
Ha cookie-hozzájárulás mögé szeretnéd rejteni a Facebook dobozt, a Complianz blokkolót tedd a szokásos „marketing” kategóriára. (Ezt a projektben terveztük is. )
Az „Adományok” lista a már létező Impact Activity API-t hívja, így natívan működik a mostani adatfolyammal. 
Ha szeretnéd, következő körben adok hozzá moderációt (kulcsszó-szűrés, „jelentés” gomb), illetve össze tudjuk kötni a riporttal, hogy a chat automatikusan generált „X € adomány érkezett Y szervezetnek” posztokat is kapjon (Dognet API → saját REST → feed).  
Ezt mondtad:
és ez h kapcsolódik a Facebook oldalamhoz?
A ChatGPT ezt mondta:
Ez a snippet két részt csinál:
Sharity belső mikro-feed – ez teljesen WordPress-en fut, lokálisan tárolja a hozzászólásokat és kiteszi mellé az Impact API „Friss adományok” listát. Ez egy kis saját közösségi fal.
Facebook oldal beágyazás – ha a shortcode-ba beteszed a fb_page="https://www.facebook.com/TeOldalad" paramétert, akkor a snippet a hivatalos Facebook Page Plugin dobozt rendereli az oldaladon. Ez közvetlenül a te Facebook oldalad timeline-ját tölti be (posztok, like gomb, kommentek), pont úgy, ahogy a Facebook engedi.
Fontos:
Ez nem „szinkronizálja” a lokális chat üzeneteket a Facebook faladra, hanem egymás mellett jelenik meg: balra a saját kis chatfal/adomány feed, jobbra (vagy alatta) a Facebook oldalad timeline.
A kapcsolódás annyi, hogy ugyanazon az oldalon egyszerre láthatóak – a felhasználó választhat, hogy a te belső faladra ír vagy a Facebook timeline-ra kommentel.
Ha tényleg szeretnél automatikus posztolást is a Facebook oldalra (pl. minden új adományüzenet kimegy a Page-re), az már a Facebook Graph API-val történne, ehhez appot kell regisztrálni a Meta fejlesztői felületén és hozzáférést kérni az oldaladhoz.
Most tehát: a shortcode-ban a show_fb="1" + fb_page="URL" paraméter aktiválja az oldalad idővonalát. Ez a kapcsolat.
Szeretnéd, hogy adjak egy bővített verziót, ahol a lokális chat és a Facebook plugin blokk vizuálisan tab-okkal van elválasztva („Chat” / „Facebook” fülek), hogy ne legyen túl hosszú az oldal?

Ezt mondtad:
Feltöltött kép
A Facebook oldalt nem látom, van nem értem minek kellene látszódnia. Legyen designosabb (GenZ) + a friss adományok HUF-ban legyenek rendes szöveggel: Az X webshopon történ vásárlással Y civil szervezetet Z forinttal támogatták, vagy valami előbb szöveg. 
A ChatGPT ezt mondta:

Gondolkozás
értem 👍 Jön egy frissebb, „Gen Z”-es verzió: fülök (Chat / Adományok / Facebook), kerek kártyák, finom animációk, és a „Friss adományok” HUF-ra váltva teljes mondattal jelenik meg.
Használat például:
[impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage" max_items="60" rate_huf="392.5"]

Tipp: a Facebook-doboz csak akkor látszik, ha helyes Page URL-t adsz meg, a Page nem korhatáros/országkorlátos, és a CMP (pl. Complianz) nem blokkolja a marketing scripteket. Ha CMP-t használsz, engedély után lesz látható.
Íme az egyben beilleszthető kód (cseréld a korábbit erre):
<?php
/**
 * Impact Microfeed – Gen Z frissítés: tabos UI (Chat / Adományok / Facebook),
 * HUF konverzió és természetes nyelvű aktivitás-szöveg.
 * Shortcode:
 *   [impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage" max_items="60" rate_huf="392.5"]
 */

if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');

if (!function_exists('sharity_impact_fetch')) {
  function sharity_impact_fetch($path){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}

if (!defined('IMPACT_MICROFEED_OPT')) define('IMPACT_MICROFEED_OPT', 'impact_microfeed_msgs_v1');
if (!defined('IMPACT_MICROFEED_MAX')) define('IMPACT_MICROFEED_MAX', 300);

/* ---------- REST: üzenetek ---------- */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'GET',
    'callback'=>function(WP_REST_Request $req){
      $max = max(1, min(IMPACT_MICROFEED_MAX, intval($req->get_param('max') ?: 60)));
      $all = get_option(IMPACT_MICROFEED_OPT, []);
      if (!is_array($all)) $all = [];
      $out = array_slice(array_reverse($all), 0, $max);
      return rest_ensure_response(['items'=>$out,'count'=>count($out)]);
    },
    'permission_callback'=>'__return_true'
  ]);

  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'POST',
    'callback'=>'impact_microfeed_post_message',
    'permission_callback'=>function(){ return is_user_logged_in() || wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest'); }
  ]);
});

function impact_microfeed_post_message(WP_REST_Request $req){
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'impact_microfeed_rl_'.md5($ip);
  if (get_transient($key)) {
    return new WP_Error('ratelimit','Túl gyorsan küldesz. Próbáld újra kicsit később.',['status'=>429]);
  }
  set_transient($key, 1, 60);

  $name = trim(wp_strip_all_tags((string)$req->get_param('name')));
  $text = trim((string)$req->get_param('text'));
  $hp   = trim((string)$req->get_param('company')); // honeypot
  if ($hp !== '') return new WP_Error('spam','Hiba.',['status'=>400]);
  if ($name==='' || $text==='') return new WP_Error('badreq','Név és üzenet kötelező.',['status'=>400]);

  $name = mb_substr($name, 0, 60);
  $text = wp_kses($text, []);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = mb_substr($text, 0, 280);

  $row = ['ts'=>current_time('mysql'),'name'=>$name,'text'=>$text];
  $all = get_option(IMPACT_MICROFEED_OPT, []);
  if (!is_array($all)) $all = [];
  $all[] = $row;

  $maxStored = max(1, min(IMPACT_MICROFEED_MAX, intval(get_option('impact_microfeed_max_items', 300))));
  if (count($all) > $maxStored) $all = array_slice($all, -$maxStored);
  update_option(IMPACT_MICROFEED_OPT, $all, false);

  return rest_ensure_response(['ok'=>true,'item'=>$row]);
}

/* ---------- Shortcode ---------- */
add_shortcode('impact_microfeed', function($atts){
  $a = shortcode_atts([
    'show_form' => '1',
    'show_fb'   => '1',
    'fb_page'   => '',
    'max_items' => '60',
    'title'     => 'Közösségi fal',
    'rate_huf'  => '392.5',   // EUR→HUF becsült árfolyam
  ], $atts, 'impact_microfeed');

  $nonce = wp_create_nonce('wp_rest');
  $api_base   = esc_url_raw(rest_url('impact/v1/microfeed'));
  $activityEP = esc_url_raw(rtrim(IMPACT_API_BASE_HOST,'/').'/wp-json/impact/v1/activity');
  $rate_huf   = floatval($a['rate_huf']);

  ob_start(); ?>
  <style>
    .imf-wrap{--ring:#e5e7eb;--card:#ffffff;--ink:#0b0f19;--muted:#667085;--accent:#7c3aed;--bg:#f8fafc}
    @media (prefers-color-scheme: dark){
      .imf-wrap{--ring:#1f2937;--card:#0b0f19;--ink:#e5e7eb;--muted:#9aa4b2;--accent:#8b5cf6;--bg:#04070d}
    }
    .imf-wrap{background:var(--bg);padding:8px;border-radius:16px}
    .imf-tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
    .imf-tab{cursor:pointer;padding:8px 12px;border:1px solid var(--ring);border-radius:999px;font-weight:700}
    .imf-tab.active{background:linear-gradient(135deg,var(--accent),#22c55e);color:white;border-color:transparent;box-shadow:0 6px 20px rgba(124,58,237,.25)}
    .imf-grid{display:grid;gap:14px}
    .imf-card{background:var(--card);border:1px solid var(--ring);border-radius:16px;padding:12px;box-shadow:0 10px 30px rgba(2,6,23,.04)}
    .imf-card h3{margin:0 0 10px 0;font:700 18px/1.2 system-ui;color:var(--ink)}
    .imf-list{display:grid;gap:10px;max-height:420px;overflow:auto;padding-right:4px}
    .imf-item{display:flex;gap:10px;align-items:flex-start}
    .imf-avatar{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--accent),#22c55e);color:white;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 40px;filter:saturate(1.1)}
    .imf-meta{font-size:12px;color:var(--muted)}
    .imf-msg{font-size:14px;margin-top:2px;color:var(--ink)}
    .imf-form{display:grid;gap:8px}
    .imf-form input,.imf-form textarea{width:100%;padding:12px;border:1px solid var(--ring);border-radius:12px;background:var(--card);color:var(--ink)}
    .imf-form button{padding:12px 16px;border-radius:12px;border:0;background:linear-gradient(135deg,var(--accent),#22c55e);color:#fff;font-weight:800;cursor:pointer;letter-spacing:.2px}
    .imf-activity ul{margin:0;padding-left:1rem;display:grid;gap:.4rem}
    .imf-note{font-size:12px;color:var(--muted);margin-top:6px}
    .imf-hidden{display:none}
    .imf-fb .fb-page{width:100%}
  </style>

  <div class="imf-wrap"
       data-api="<?php echo esc_attr($api_base); ?>"
       data-activity="<?php echo esc_attr($activityEP); ?>"
       data-nonce="<?php echo esc_attr($nonce); ?>"
       data-max="<?php echo intval($a['max_items']); ?>"
       data-rate="<?php echo esc_attr($rate_huf); ?>">
    <div class="imf-tabs">
      <div class="imf-tab active" data-tab="chat">Chat</div>
      <div class="imf-tab" data-tab="donations">Adományok</div>
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
        <div class="imf-tab" data-tab="facebook">Facebook</div>
      <?php endif; ?>
    </div>

    <div class="imf-grid">
      <!-- CHAT -->
      <div class="imf-card" data-panel="chat">
        <h3><?php echo esc_html($a['title']); ?></h3>
        <div class="imf-list" id="imf-list" aria-live="polite"></div>
        <?php if ($a['show_form']==='1'): ?>
        <form class="imf-form" id="imf-form">
          <input type="text" name="name" placeholder="Neved" maxlength="60" required>
          <textarea name="text" rows="3" placeholder="Írj valamit (max 280 karakter)..." maxlength="280" required></textarea>
          <input type="text" name="company" value="" style="display:none">
          <button type="submit">Küldés</button>
          <div id="imf-status" class="imf-note"></div>
        </form>
        <?php endif; ?>
      </div>

      <!-- ADOMÁNYOK -->
      <div class="imf-card imf-hidden" data-panel="donations">
        <h3>Friss adományok</h3>
        <ul id="imf-activity"></ul>
        <div class="imf-note">Automatikus frissítés 30 mp-enként</div>
      </div>

      <!-- FACEBOOK -->
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
      <div class="imf-card imf-fb imf-hidden" data-panel="facebook">
        <h3>Facebook</h3>
        <div id="fb-root"></div>
        <div class="fb-page"
             data-href="<?php echo esc_attr($a['fb_page']); ?>"
             data-tabs="timeline"
             data-width="1000"
             data-height=""
             data-small-header="false"
             data-adapt-container-width="true"
             data-hide-cover="false"
             data-show-facepile="true"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/hu_HU/sdk.js#xfbml=1&version=v17.0"></script>
        <div class="imf-note">Ha itt nem látszik semmi: ellenőrizd a Page URL-t, és hogy nincs életkor/ország korlátozás, illetve a sütikezelő engedte-e a marketing cookie-kat.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    var api  = root.getAttribute('data-api');
    var act  = root.getAttribute('data-activity');
    var max  = parseInt(root.getAttribute('data-max')||'60',10);
    var rate = parseFloat(root.getAttribute('data-rate')||'392.5');
    var nonce= root.getAttribute('data-nonce');

    var list = root.querySelector('#imf-list');
    var form = root.querySelector('#imf-form');
    var status = root.querySelector('#imf-status');
    var ulAct = root.querySelector('#imf-activity');

    // Tabs
    root.querySelectorAll('.imf-tab').forEach(function(tab){
      tab.addEventListener('click', function(){
        root.querySelectorAll('.imf-tab').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        var id = tab.getAttribute('data-tab');
        root.querySelectorAll('[data-panel]').forEach(p=>{
          p.classList.toggle('imf-hidden', p.getAttribute('data-panel')!==id);
        });
      });
    });

    function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function initials(n){var p=(n||'').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase();return p||'😊';}
    function titleize(slug){
      return (slug||'').replace(/[-_]+/g,' ').replace(/\b\w/g, c=>c.toUpperCase()).trim();
    }
    function eurToHuf(eur){var v = Math.round((parseFloat(eur||0)||0)*rate); return v.toLocaleString('hu-HU');}

    async function loadChat(){
      try{
        let r = await fetch(api + '?max=' + encodeURIComponent(max), {headers:{'Accept':'application/json'}});
        if(!r.ok) throw 0;
        let j = await r.json();
        list.innerHTML = (j.items||[]).map(it=>{
          return '<div class="imf-item">'
               +   '<div class="imf-avatar">'+esc(initials(it.name||''))+'</div>'
               +   '<div class="imf-body"><div class="imf-meta">'+esc(it.name||'Ismeretlen')+' • '+esc(it.ts||'')+'</div>'
               +   '<div class="imf-msg">'+esc(it.text||'')+'</div></div>'
               + '</div>';
        }).join('') || '<div class="imf-note">Még nincs üzenet.</div>';
      }catch(e){
        list.innerHTML = '<div style="color:#dc2626">Hiba a chat betöltésekor.</div>';
      }
    }

    // A /activity jelenleg olyan sorokat ad, mint: "mbe • 0,08 € • 2025-09-19 10:51"
    // Ezt feldolgozzuk természetes magyar mondattá és HUF-ra váltjuk.
    function renderActivityRow(txt){
      // próbáljuk kibontani a slug • összeg € • dátum mintát
      var m = (txt||'').match(/^\s*([^•]+?)\s*•\s*([\d.,]+)\s*€\s*•\s*(.+)\s*$/);
      if(!m){
        // fallback: nyers szöveg
        return '<li>'+esc(txt)+'</li>';
      }
      var ngoSlug = m[1].trim();
      var eur = m[2].replace(',', '.');
      var when = m[3].trim();
      var huf = eurToHuf(eur);
      var ngoName = titleize(ngoSlug);
      var sentence = 'Egy vásárlással a(z) '+ngoName+' civil szervezetet '+huf+' forinttal támogatták. ('+esc(when)+')';
      return '<li>'+esc(sentence)+'</li>';
    }

    async function loadActivity(){
      try{
        let r = await fetch(act, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if(!r.ok) throw 0;
        let j = await r.json();
        if(!Array.isArray(j)) j = [];
        ulAct.innerHTML = j.map(row => {
          var t = (typeof row==='string') ? row : (row.text || JSON.stringify(row));
          return renderActivityRow(t);
        }).join('') || '<li class="imf-note">Még nincs friss aktivitás.</li>';
      }catch(e){
        ulAct.innerHTML = '<li style="color:#dc2626">Hiba az aktivitás betöltésekor.</li>';
      }
    }

    if (form){
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        status.textContent = 'Küldés…';
        let fd = new FormData(form);
        try{
          let r = await fetch(api, {
            method:'POST',
            headers:{'X-WP-Nonce': nonce},
            body: fd
          });
          let ok = r.ok;
          let j = await r.json().catch(()=>({}));
          if(!ok) throw (j && j.message) || 'Ismeretlen hiba';
          form.reset();
          status.textContent = 'Elmentve.';
          loadChat();
          setTimeout(()=>status.textContent='',1500);
        }catch(err){
          status.textContent = 'Hiba: '+(err && err.toString ? err.toString() : 'ismeretlen');
        }
      });
    }

    loadChat(); loadActivity();
    setInterval(loadActivity, 30000);
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ---------- Admin: max tárolt üzenetek ---------- */
add_action('admin_init', function(){
  register_setting('reading','impact_microfeed_max_items',['type'=>'integer','default'=>300]);
  add_settings_field('impact_microfeed_max_items','Impact Microfeed – max üzenet (tárolt)','impact_microfeed_max_items_cb','reading');
});
function impact_microfeed_max_items_cb(){
  $v = intval(get_option('impact_microfeed_max_items',300));
  echo '<input type="number" min="10" max="'.IMPACT_MICROFEED_MAX.'" name="impact_microfeed_max_items" value="'.esc_attr($v).'"> <small>Mentett chat bejegyzések felső határa</small>';
}
Gyors ellenőrzőlista a Facebook boxhoz:
a shortcode-ban show_fb="1" és valós fb_page="https://www.facebook.com/<sajat-oldal>",
a Page ne legyen korhatáros/országkorlátozott,
ha van cookie-banner, a látogató adjon „marketing” engedélyt,
várhatóan a Facebook doboz a „Facebook” fülön jelenik meg (kattints rá).
Ha szeretnéd, bővítem úgy, hogy ha az activity API később ad „shop” nevet is, automatikusan így fogalmazzon: „Az X webshopon történt vásárlással Y civil szervezetet Z forinttal támogatták.” Most egyelőre az NGO-slugból készít olvasható nevet.
Ezt mondtad:
[impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/thinkwisesharesmart] itt mi a gond az FB oldal címével? Pedig az uRL-ből ollóztam ki: https://www.facebook.com/thinkwisesharesmart
A ChatGPT ezt mondta:
Ránéztem: a gond az, hogy a shortcode-ban hiányzik a záró idézőjel a fb_page értékének a végéről.
Most így adtad meg:

[impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/thinkwisesharesmart]
Itt az URL után nincs ".
Helyesen így néz ki:

[impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/thinkwisesharesmart"]
Tehát: mindig idézőjelek közé kell tenni az egész URL-t, és ne maradjon le a lezáró ".
Ha így is gond van a megjelenítéssel, akkor a másik tipikus ok:

az oldalad Facebook-oldal ország/korhatár korlátozást tartalmaz,
vagy a sütikezelő (Complianz) blokkolja a „marketing” scriptet → engedélyezni kell a látogató oldalon, hogy látszódjon a box.
Szeretnéd, hogy adjak egy gyors ellenőrző lépéslistát, hogyan teszteld, hogy a Page Plugin tényleg működik-e a te Facebook oldaladdal?
Ezt mondtad:
Az adományok fülön a feeden ne csak azt jelöljük, hogy egy vásárlásból X ft adomány keletkezett Egy Y Ngo-nak, hanem azt is, hogy melyik webshop-ból. Jelenleg a shop és az ngo slug-al szerepel mindenhol, meg kell oldanunk, hogy eljusson hozzád az információ, melyik slug melyik ngo (A Filloutban van erről adatbázis, de akár felvihetem egy táblázatba is.) A shop rendes neve szerepel a Shop Tábla B oszlopában.
A ChatGPT ezt mondta:

Gondolkozás
pöpec — megoldjuk a shop+NGO név feloldást. Adok egy frissített snippetet, ami:
a „Friss adományok” fülön HUF-ban, természetes magyar mondattal írja ki az eseményt,
a shop és az NGO slugot emberi névre fordítja két publikált CSV alapján (Google Sheets „Publish to the web → CSV”).
Ha nincs CSV megadva, kulturált „Title Case” fallback van.
Használat (példa)
[impact_microfeed
  show_form="1"
  show_fb="1"
  fb_page="https://www.facebook.com/thinkwisesharesmart"
  max_items="60"
  rate_huf="392.5"
  shops_csv="https://docs.google.com/spreadsheets/d/e/.../pub?gid=0&single=true&output=csv"
  ngos_csv="https://docs.google.com/spreadsheets/d/e/.../pub?gid=12345&single=true&output=csv"
]
shops_csv: 1. oszlop = shop_slug, 2. oszlop = Shop neve (pl. “visionexpress” , “Vision Express”)
ngos_csv: 1. oszlop = ngo_slug, 2. oszlop = NGO neve (pl. “bator-tabor-alapitvany” , “Bátor Tábor Alapítvány”)
Az activity API többféle formátumot is kibír:
shop • ngo • 0,08 € • 2025-09-19 10:51 — ez az ideális
ngo • 0,08 € • 2025-09-19 10:51 — régi formátum (shop nélkül)
VAGY JSON-sor (pl. { "shop":"visionexpress","ngo":"bator-tabor-alapitvany","amount_eur":0.08,"ts":"..." })
Másold be egyben (WPCode/MU-plugin):
<?php
/**
 * Impact Microfeed – Gen Z + Shop/NGO névfeloldás CSV-ből
 * Shortcode:
 * [impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/thinkwisesharesmart"
 *                   max_items="60" rate_huf="392.5"
 *                   shops_csv="https://.../output=csv" ngos_csv="https://.../output=csv"]
 */

if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');

if (!function_exists('sharity_impact_fetch')) {
  function sharity_impact_fetch($path){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}

if (!defined('IMPACT_MICROFEED_OPT')) define('IMPACT_MICROFEED_OPT', 'impact_microfeed_msgs_v1');
if (!defined('IMPACT_MICROFEED_MAX')) define('IMPACT_MICROFEED_MAX', 300);

/* ---------- REST: üzenetek ---------- */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'GET',
    'callback'=>function(WP_REST_Request $req){
      $max = max(1, min(IMPACT_MICROFEED_MAX, intval($req->get_param('max') ?: 60)));
      $all = get_option(IMPACT_MICROFEED_OPT, []);
      if (!is_array($all)) $all = [];
      $out = array_slice(array_reverse($all), 0, $max);
      return rest_ensure_response(['items'=>$out,'count'=>count($out)]);
    },
    'permission_callback'=>'__return_true'
  ]);

  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'POST',
    'callback'=>'impact_microfeed_post_message',
    'permission_callback'=>function(){ return is_user_logged_in() || wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest'); }
  ]);
});

function impact_microfeed_post_message(WP_REST_Request $req){
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'impact_microfeed_rl_'.md5($ip);
  if (get_transient($key)) {
    return new WP_Error('ratelimit','Túl gyorsan küldesz. Próbáld újra kicsit később.',['status'=>429]);
  }
  set_transient($key, 1, 60);

  $name = trim(wp_strip_all_tags((string)$req->get_param('name')));
  $text = trim((string)$req->get_param('text'));
  $hp   = trim((string)$req->get_param('company')); // honeypot
  if ($hp !== '') return new WP_Error('spam','Hiba.',['status'=>400]);
  if ($name==='' || $text==='') return new WP_Error('badreq','Név és üzenet kötelező.',['status'=>400]);

  $name = mb_substr($name, 0, 60);
  $text = wp_kses($text, []);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = mb_substr($text, 0, 280);

  $row = ['ts'=>current_time('mysql'),'name'=>$name,'text'=>$text];
  $all = get_option(IMPACT_MICROFEED_OPT, []);
  if (!is_array($all)) $all = [];
  $all[] = $row;

  $maxStored = max(1, min(IMPACT_MICROFEED_MAX, intval(get_option('impact_microfeed_max_items', 300))));
  if (count($all) > $maxStored) $all = array_slice($all, -$maxStored);
  update_option(IMPACT_MICROFEED_OPT, $all, false);

  return rest_ensure_response(['ok'=>true,'item'=>$row]);
}

/* ---------- Shortcode + UI ---------- */
add_shortcode('impact_microfeed', function($atts){
  $a = shortcode_atts([
    'show_form' => '1',
    'show_fb'   => '1',
    'fb_page'   => '',
    'max_items' => '60',
    'title'     => 'Közösségi fal',
    'rate_huf'  => '392.5',
    'shops_csv' => '',
    'ngos_csv'  => '',
  ], $atts, 'impact_microfeed');

  $nonce = wp_create_nonce('wp_rest');
  $api_base   = esc_url_raw(rest_url('impact/v1/microfeed'));
  $activityEP = esc_url_raw(rtrim(IMPACT_API_BASE_HOST,'/').'/wp-json/impact/v1/activity');

  ob_start(); ?>
  <style>
    .imf-wrap{--ring:#e5e7eb;--card:#ffffff;--ink:#0b0f19;--muted:#667085;--accent:#7c3aed;--bg:#f8fafc}
    @media (prefers-color-scheme: dark){
      .imf-wrap{--ring:#1f2937;--card:#0b0f19;--ink:#e5e7eb;--muted:#9aa4b2;--accent:#8b5cf6;--bg:#04070d}
    }
    .imf-wrap{background:var(--bg);padding:8px;border-radius:16px}
    .imf-tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
    .imf-tab{cursor:pointer;padding:8px 12px;border:1px solid var(--ring);border-radius:999px;font-weight:700}
    .imf-tab.active{background:linear-gradient(135deg,var(--accent),#22c55e);color:white;border-color:transparent;box-shadow:0 6px 20px rgba(124,58,237,.25)}
    .imf-grid{display:grid;gap:14px}
    .imf-card{background:var(--card);border:1px solid var(--ring);border-radius:16px;padding:12px;box-shadow:0 10px 30px rgba(2,6,23,.04)}
    .imf-card h3{margin:0 0 10px 0;font:700 18px/1.2 system-ui;color:var(--ink)}
    .imf-list{display:grid;gap:10px;max-height:420px;overflow:auto;padding-right:4px}
    .imf-item{display:flex;gap:10px;align-items:flex-start}
    .imf-avatar{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--accent),#22c55e);color:white;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 40px;filter:saturate(1.1)}
    .imf-meta{font-size:12px;color:var(--muted)}
    .imf-msg{font-size:14px;margin-top:2px;color:var(--ink)}
    .imf-form{display:grid;gap:8px}
    .imf-form input,.imf-form textarea{width:100%;padding:12px;border:1px solid var(--ring);border-radius:12px;background:var(--card);color:var(--ink)}
    .imf-form button{padding:12px 16px;border-radius:12px;border:0;background:linear-gradient(135deg,var(--accent),#22c55e);color:#fff;font-weight:800;cursor:pointer;letter-spacing:.2px}
    .imf-activity ul{margin:0;padding-left:1rem;display:grid;gap:.4rem}
    .imf-note{font-size:12px;color:var(--muted);margin-top:6px}
    .imf-hidden{display:none}
    .imf-fb .fb-page{width:100%}
  </style>

  <div class="imf-wrap"
       data-api="<?php echo esc_attr($api_base); ?>"
       data-activity="<?php echo esc_attr($activityEP); ?>"
       data-nonce="<?php echo esc_attr($nonce); ?>"
       data-max="<?php echo intval($a['max_items']); ?>"
       data-rate="<?php echo esc_attr($a['rate_huf']); ?>"
       data-shops="<?php echo esc_url($a['shops_csv']); ?>"
       data-ngos="<?php echo esc_url($a['ngos_csv']); ?>">
    <div class="imf-tabs">
      <div class="imf-tab active" data-tab="chat">Chat</div>
      <div class="imf-tab" data-tab="donations">Adományok</div>
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
        <div class="imf-tab" data-tab="facebook">Facebook</div>
      <?php endif; ?>
    </div>

    <div class="imf-grid">
      <!-- CHAT -->
      <div class="imf-card" data-panel="chat">
        <h3><?php echo esc_html($a['title']); ?></h3>
        <div class="imf-list" id="imf-list" aria-live="polite"></div>
        <?php if ($a['show_form']==='1'): ?>
        <form class="imf-form" id="imf-form">
          <input type="text" name="name" placeholder="Neved" maxlength="60" required>
          <textarea name="text" rows="3" placeholder="Írj valamit (max 280 karakter)..." maxlength="280" required></textarea>
          <input type="text" name="company" value="" style="display:none">
          <button type="submit">Küldés</button>
          <div id="imf-status" class="imf-note"></div>
        </form>
        <?php endif; ?>
      </div>

      <!-- ADOMÁNYOK -->
      <div class="imf-card imf-hidden" data-panel="donations">
        <h3>Friss adományok</h3>
        <ul id="imf-activity"></ul>
        <div class="imf-note">Automatikus frissítés 30 mp-enként</div>
      </div>

      <!-- FACEBOOK -->
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
      <div class="imf-card imf-fb imf-hidden" data-panel="facebook">
        <h3>Facebook</h3>
        <div id="fb-root"></div>
        <div class="fb-page"
             data-href="<?php echo esc_attr($a['fb_page']); ?>"
             data-tabs="timeline"
             data-width="1000"
             data-height=""
             data-small-header="false"
             data-adapt-container-width="true"
             data-hide-cover="false"
             data-show-facepile="true"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/hu_HU/sdk.js#xfbml=1&version=v17.0"></script>
        <div class="imf-note">Ha itt nem látszik semmi: ellenőrizd a Page URL-t, az ország/korhatár korlátozást és a cookie-banner „marketing” engedélyt.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    var api   = root.getAttribute('data-api');
    var act   = root.getAttribute('data-activity');
    var max   = parseInt(root.getAttribute('data-max')||'60',10);
    var rate  = parseFloat(root.getAttribute('data-rate')||'392.5');
    var shopsCsv = root.getAttribute('data-shops')||'';
    var ngosCsv  = root.getAttribute('data-ngos')||'';
    var nonce = root.getAttribute('data-nonce');

    var list = root.querySelector('#imf-list');
    var form = root.querySelector('#imf-form');
    var status = root.querySelector('#imf-status');
    var ulAct = root.querySelector('#imf-activity');

    // Tabs
    root.querySelectorAll('.imf-tab').forEach(function(tab){
      tab.addEventListener('click', function(){
        root.querySelectorAll('.imf-tab').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        var id = tab.getAttribute('data-tab');
        root.querySelectorAll('[data-panel]').forEach(p=>{
          p.classList.toggle('imf-hidden', p.getAttribute('data-panel')!==id);
        });
      });
    });

    function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function initials(n){var p=(n||'').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase();return p||'😊';}
    function titleize(slug){return (slug||'').replace(/[-_]+/g,' ').replace(/\b\w/g, c=>c.toUpperCase()).trim();}
    function eurToHuf(eur){var v = Math.round((parseFloat(eur||0)||0)*rate); return v.toLocaleString('hu-HU');}

    // CSV (Google Sheets publish → CSV): 1. oszlop = slug, 2. = név
    var MAP_SHOP = Object.create(null), MAP_NGO = Object.create(null);
    function parseCSV(text){
      // egyszerű, idézőjeles értékekre is működő parser
      var rows=[], i=0, field='', row=[], inQ=false;
      for(; i<text.length; i++){
        var c=text[i], n=text[i+1];
        if(inQ){
          if(c==='"' && n==='"'){ field+='"'; i++; }
          else if(c==='"'){ inQ=false; }
          else field+=c;
        }else{
          if(c==='"'){ inQ=true; }
          else if(c===','){ row.push(field); field=''; }
          else if(c==='\n'){ row.push(field); rows.push(row); field=''; row=[]; }
          else if(c!=='\r'){ field+=c; }
        }
      }
      if(field.length || row.length) { row.push(field); rows.push(row); }
      return rows;
    }
    async function loadMap(url, target){
      if(!url) return;
      try{
        let r = await fetch(url, {cache:'no-store'});
        if(!r.ok) throw 0;
        let t = await r.text();
        let rows = parseCSV(t);
        rows.forEach(function(cols){
          var slug = (cols[0]||'').trim();
          var name = (cols[1]||'').trim();
          if(slug && name){ target[slug] = name; }
        });
      }catch(e){ /* csendes fallback */ }
    }

    // Chat
    async function loadChat(){
      try{
        let r = await fetch(api + '?max=' + encodeURIComponent(max), {headers:{'Accept':'application/json'}});
        if(!r.ok) throw 0;
        let j = await r.json();
        list.innerHTML = (j.items||[]).map(it=>{
          return '<div class="imf-item">'
               +   '<div class="imf-avatar">'+esc(initials(it.name||''))+'</div>'
               +   '<div class="imf-body"><div class="imf-meta">'+esc(it.name||'Ismeretlen')+' • '+esc(it.ts||'')+'</div>'
               +   '<div class="imf-msg">'+esc(it.text||'')+'</div></div>'
               + '</div>';
        }).join('') || '<div class="imf-note">Még nincs üzenet.</div>';
      }catch(e){
        list.innerHTML = '<div style="color:#dc2626">Hiba a chat betöltésekor.</div>';
      }
    }

    // Activity render: próbálunk shop+ngo+eur+date mintát, különben fallback
    function friendlyName(map, slug){
      return map[slug] || titleize(slug||'');
    }
    function renderActivityRow(txtOrObj){
      var shopSlug='', ngoSlug='', eur='', when='';
      if (typeof txtOrObj === 'object' && txtOrObj){
        shopSlug = txtOrObj.shop || '';
        ngoSlug  = txtOrObj.ngo  || '';
        eur      = (''+(txtOrObj.amount_eur ?? txtOrObj.eur ?? '')).replace(',','.');
        when     = txtOrObj.ts || txtOrObj.date || '';
      } else {
        var txt = ''+txtOrObj;
        var parts = txt.split('•').map(s=>s.trim());
        if(parts.length >= 4){
          // shop • ngo • eur € • date
          shopSlug = parts[0]; ngoSlug = parts[1];
          eur = (parts[2]||'').replace('€','').trim().replace(',','.');
          when = parts.slice(3).join(' • ');
        } else if (parts.length === 3){
          // ngo • eur € • date (régi)
          ngoSlug = parts[0];
          eur = (parts[1]||'').replace('€','').trim().replace(',','.');
          when = parts[2];
        } else {
          return '<li>'+esc(txt)+'</li>';
        }
      }

      var huf = eurToHuf(eur);
      var ngoName  = friendlyName(MAP_NGO, ngoSlug);
      var shopName = shopSlug ? friendlyName(MAP_SHOP, shopSlug) : '';
      var sentence = shopName
        ? ('Az '+shopName+' webshopon történt vásárlással a(z) '+ngoName+' '+huf+' forinttal lett támogatva.')
        : ('Egy vásárlással a(z) '+ngoName+' '+huf+' forinttal lett támogatva.');
      if (when) sentence += ' ('+when+')';
      return '<li>'+esc(sentence)+'</li>';
    }

    async function loadActivity(){
      try{
        let r = await fetch(act, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if(!r.ok) throw 0;
        let j = await r.json();
        if(!Array.isArray(j)) j = [];
        ulAct.innerHTML = j.map(row => renderActivityRow(row.text ?? row)).join('') || '<li class="imf-note">Még nincs friss aktivitás.</li>';
      }catch(e){
        ulAct.innerHTML = '<li style="color:#dc2626">Hiba az aktivitás betöltésekor.</li>';
      }
    }

    if (form){
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        status.textContent = 'Küldés…';
        let fd = new FormData(form);
        try{
          let r = await fetch(api, { method:'POST', headers:{'X-WP-Nonce': nonce}, body: fd });
          let ok = r.ok;
          let j = await r.json().catch(()=>({}));
          if(!ok) throw (j && j.message) || 'Ismeretlen hiba';
          form.reset();
          status.textContent = 'Elmentve.';
          loadChat();
          setTimeout(()=>status.textContent='',1500);
        }catch(err){
          status.textContent = 'Hiba: '+(err && err.toString ? err.toString() : 'ismeretlen');
        }
      });
    }

    // Start: előbb töltsük be a mappingeket, aztán rendereljünk activity-t
    Promise.all([ loadMap(shopsCsv, MAP_SHOP), loadMap(ngosCsv, MAP_NGO) ]).then(function(){
      loadChat(); loadActivity();
      setInterval(loadActivity, 30000);
    });
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ---------- Admin: max tárolt üzenetek ---------- */
add_action('admin_init', function(){
  register_setting('reading','impact_microfeed_max_items',['type'=>'integer','default'=>300]);
  add_settings_field('impact_microfeed_max_items','Impact Microfeed – max üzenet (tárolt)','impact_microfeed_max_items_cb','reading');
});
function impact_microfeed_max_items_cb(){
  $v = intval(get_option('impact_microfeed_max_items',300));
  echo '<input type="number" min="10" max="'.IMPACT_MICROFEED_MAX.'" name="impact_microfeed_max_items" value="'.esc_attr($v).'"> <small>Mentett chat bejegyzések felső határa</small>';
}
Ha megadod a két publikus CSV linket (Shops, NGOs), máris név szerint fog szólni minden adománybejegyzés. Következő lépésként — ha szeretnéd — át tudjuk állítani úgy, hogy a mappinget szerveroldalon cache-eljük (WP transiens), így még gyorsabb és stabilabb lesz nagy forgalomnál.
Ezt mondtad:
Shops: https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv Az Ngo lista már csv file, azt hogy lehet ugyanígy weben megosztani? 
A ChatGPT ezt mondta:

Gondolkozás
Szuper, a Shops link jó. Az NGO-khoz három gyors, hülyebiztos megoldás közül választhatsz:
1) „Google Sheets → Publish to web (CSV)” – ajánlott
Ha most sima .csv fájlod van, csinálj belőle Google Sheet-et, és onnan publikálj CSV-linket (stabil, gyors).
Nyisd meg a Google Drive-ot → Új → Google Táblázatok.
Fájl → Importálás → Feltöltés fülön húzd be az NGO .csv-t → „Új táblázat” opció.
Ellenőrizd az oszlopokat:
A oszlop: ngo_slug (pl. bator-tabor-alapitvany)
B oszlop: NGO neve (pl. Bátor Tábor Alapítvány)
Fájl → Közzététel a weben → Teljes táblázat → Fájlformátum: CSV → Közzététel.
Másold ki az így kapott publikus CSV URL-t (úgy néz ki, mint a Shops link).
Ezt az URL-t add meg a shortcode-ban ngos_csv="...".
2) WordPress Médiatár – ha már van kész CSV
WP admin → Média → Új hozzáadása → töltsd fel az ngo-map.csv-t.
Katt a fájlra → jobb oldalon másold ki a fájl URL-jét (pl. https://app.sharity.hu/wp-content/uploads/2025/09/ngo-map.csv).
Tedd be ezt az URL-t ngos_csv-nek.
Megjegyzés: WP „text/csv”-vel szolgálja ki, fetch-el simán olvasható.
3) GitHub Gist / Raw (alternatíva)
Feltöltöd Gist-be, a raw URL-t használod ngos_csv-nek. (Nem kötelező, csak ha ezt szereted.)
Minta a shortcode-ra a te linkeddel
[impact_microfeed
  show_form="1"
  show_fb="1"
  fb_page="https://www.facebook.com/thinkwisesharesmart"
  max_items="60"
  rate_huf="392.5"
  shops_csv="https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv"
  ngos_csv="IDE-JÖN-AZ-NGO-CSV-URL"
]
Gyors ellenőrzőlista az NGO CSV-hez
Első sor tartalmazzon címsorokat vagy ne törődünk vele? Mindegy: a parser az első két oszlopot nézi.
1. oszlop = slug, 2. oszlop = rendes név. Extra oszlop maradhat, figyelmen kívül hagyjuk.
Publikus elérés: „Anyone with the link” + „Publish to the web” (Sheets esetén), vagy Médiatár URL.
Ha bedobtad az NGO CSV linket is, a „Friss adományok” fülön már így fog szólni:
„Az Vision Express webshopon történt vásárlással a(z) Bátor Tábor Alapítvány 3 920 forinttal lett támogatva. (2025-09-19 10:51)”
Következő lépésként szívesen adok egy apró „cache-térképet” (WP transients) a két mapping linkhez, hogy még gyorsabban töltsön nagy forgalomnál.

Ezt mondtad:
A legegyszerűbb a WP média tár, itt a link: https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv ellenőrizd
A ChatGPT ezt mondta:

Gondolkozás
Ránézésre a link formátuma tökéletes WP-Médiatár URL (közvetlen fájl):
https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv
Én innen nem tudom megnyitni a netet, de így tudod pillanatok alatt leellenőrizni, hogy jó-e és olvasható-e a mapping:

Nyisd meg a fenti URL-t böngészőben — ha a CSV sorai látszanak, publikusan elérhető.
(Opcionális, 10 másodperces mélyebb check) Másold be ezt a sort a böngésződ Konzoljába azon az oldalon, ahol a microfeed van:
fetch('https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv')
  .then(r=>r.text()).then(t=>console.log(t.split('\n').slice(0,5).join('\n')));
Ha visszaad pár sort, a microfeed fel tudja dolgozni.
Tedd be a shortcode-ba így
[impact_microfeed
  show_form="1"
  show_fb="1"
  fb_page="https://www.facebook.com/thinkwisesharesmart"
  max_items="60"
  rate_huf="392.5"
  shops_csv="https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv"
  ngos_csv="https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv"
]
Formátum-gyorsteszt
1. oszlop: ngo_slug (pl. bator-tabor-alapitvany)
2. oszlop: „szép név” (pl. Bátor Tábor Alapítvány)
Fejléc lehet, nem baj; a parser az első két oszlopot nézi. Extra oszlop maradhat.
Ha bármiért nem érné el a fájlt, a szöveg akkor is megjelenik, csak slugból generált (Title Case) névvel. Következő körben tehetek be egy kis diagnosztika-kapcsolót is a shortcode-hoz, ami a lap alján kiírja, hány shop/NGO mapping töltődött be.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
