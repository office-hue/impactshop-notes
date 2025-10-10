<?php
/*
Plugin Name: Sharity Anti-Hijack
Description: Kliensoldali védelem affiliate link-átírás ellen (Dognet). Figyelmeztet és blokkol, ha a go.dognet.com link CHID-je nem a tiéd. Shortcode: [sharity_aff_guard]
Version: 1.0.0
Author: Sharity
*/
if (!defined('ABSPATH')) exit;

class Sharity_Anti_Hijack {
    private $opt = 'sharity_anti_hijack_opts';

    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'reg']);
        add_action('wp_enqueue_scripts', [$this,'enqueue']);
        add_shortcode('sharity_aff_guard', [$this,'shortcode']);
    }

    public function menu() {
        add_options_page('Sharity Anti-Hijack', 'Sharity Anti-Hijack', 'manage_options', 'sharity-anti-hijack', [$this,'settings_page']);
    }

    public function reg() {
        register_setting($this->opt, $this->opt);
    }

    private function get() {
        $o = get_option($this->opt, []);
        return wp_parse_args($o, [
            'chid' => '', // pl. KVirfJde
            'message' => 'Figyelem: a böngésződben futó kiegészítő átírta az affiliate linket. Ha így folytatod, az adomány és a nyereményjáték-részvétel elveszhet. Kapcsold ki az ütköző plugint/bővítményt, majd próbáld újra.',
            'enable_block' => 'yes', // blokkoljuk-e a kattintást eltérésnél
        ]);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $o = $this->get();
        echo '<div class="wrap"><h1>Sharity Anti-Hijack</h1>';
        if (!empty($_POST['save']) && check_admin_referer('sharity_ah_save')) {
            $o['chid'] = sanitize_text_field($_POST['chid'] ?? '');
            $o['message'] = sanitize_textarea_field($_POST['message'] ?? '');
            $o['enable_block'] = isset($_POST['enable_block']) ? 'yes' : 'no';
            update_option($this->opt, $o);
            echo '<div class="updated"><p>Mentve.</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('sharity_ah_save'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Saját CHID (Dognet csatornakód)</th>
                    <td><input class="regular-text" name="chid" value="<?php echo esc_attr($o['chid']); ?>" placeholder="pl. KVirfJde"></td>
                </tr>
                <tr>
                    <th scope="row">Figyelmeztető üzenet</th>
                    <td><textarea name="message" rows="4" class="large-text"><?php echo esc_textarea($o['message']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row">Kattintás blokkolása eltérésnél</th>
                    <td><label><input type="checkbox" name="enable_block" <?php checked($o['enable_block'], 'yes'); ?>> Engedélyezve</label></td>
                </tr>
            </table>
            <p><button class="button button-primary" name="save" value="1">Mentés</button></p>
        </form>

        <h2>Használat</h2>
        <p>Helyezd el a figyelmeztetés helyét: <code>[sharity_aff_guard]</code>. A sáv csak akkor jelenik meg, ha eltérést észlelünk.</p>
        <p>A védelem kliensoldali, deeplink <strong>nélkül is</strong> működik. A Dognet API szerinti mérések ettől függetlenül zajlanak (auth/login – 24h token, clicks/filter – Dognet timezone).  [oai_citation:1‡Publisher API documentation.pdf](file-service://file-HFXocFeqMKeHVZHWUC4y1p)</p>
        <?php
        echo '</div>';
    }

    public function shortcode($atts = []) {
        $o = $this->get();
        ob_start();
        ?>
        <div id="sharity-aff-guard" style="display:none;margin:12px 0;padding:12px;border:1px solid #d66;background:#fff6f6;border-radius:6px;font-weight:600;"></div>
        <?php
        return ob_get_clean();
    }

    public function enqueue() {
        $o = $this->get();
        if (empty($o['chid'])) return;

        // Minimális, inline JS – nincs külön fájl
        $data = [
            'chid'   => $o['chid'],
            'msg'    => $o['message'],
            'block'  => ($o['enable_block'] === 'yes'),
        ];
        $js = '(function(){
  var CFG = '.wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';

  function warn(showMsg){
    var box = document.getElementById("sharity-aff-guard");
    if (!box) {
      box = document.createElement("div");
      box.id = "sharity-aff-guard";
      box.style.cssText="margin:12px 0;padding:12px;border:1px solid #d66;background:#fff6f6;border-radius:6px;font-weight:600;";
      var host = document.querySelector("main, #content, .site-content, body");
      (host||document.body).insertBefore(box,(host||document.body).firstChild);
    }
    box.textContent = showMsg || CFG.msg;
    box.style.display = "block";
  }

  function parseURL(href){
    try { return new URL(href, location.href); } catch(e){ return null; }
  }

  function isDognet(u){ return u && u.hostname === "go.dognet.com"; }

  function checkAnchor(a, evt){
    var u = parseURL(a.getAttribute("href")||"");
    if (!isDognet(u)) return false;
    var chid = u.searchParams.get("chid");
    if (!chid) return false;
    if (chid !== CFG.chid) {
      if (CFG.block && evt) evt.preventDefault();
      warn();
      return true;
    }
    return false;
  }

  // Kattintás figyelése capture módban – mielőtt más szkriptek átvennék
  document.addEventListener("click", function(e){
    var a = e.target.closest ? e.target.closest("a[href]") : null;
    if (!a) return;
    checkAnchor(a, e);
  }, true);

  // Link-átírások figyelése (pl. külső plugin): DOM változásra újraellenőrzünk
  var obs = new MutationObserver(function(muts){
    for (var i=0;i<muts.length;i++){
      var m = muts[i];
      if (m.type === "attributes" && m.attributeName === "href" && m.target.tagName === "A") {
        checkAnchor(m.target, null);
      } else if (m.addedNodes && m.addedNodes.length) {
        m.addedNodes.forEach(function(n){
          if (n.nodeType===1 && n.matches && n.matches("a[href]")) checkAnchor(n, null);
          if (n.querySelectorAll) n.querySelectorAll("a[href]").forEach(function(a){ checkAnchor(a, null); });
        });
      }
    }
  });
  try { obs.observe(document.documentElement, {subtree:true, childList:true, attributes:true, attributeFilter:["href"]}); } catch(e){}

  // Oldalbetöltés után első pásztázás
  function initialScan(){
    document.querySelectorAll("a[href]").forEach(function(a){ checkAnchor(a, null); });
  }
  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initialScan); } else { initialScan(); }

})();';
        // Beágyazzuk a frontendre
        wp_register_script('sharity-anti-hijack', false);
        wp_enqueue_script('sharity-anti-hijack');
        wp_add_inline_script('sharity-anti-hijack', $js);
    }
}

new Sharity_Anti_Hijack();