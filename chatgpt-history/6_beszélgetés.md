Átugrás a tartalomra
Ezt mondtad:
Dognet API: https://www.dognet.com/magazine/integracia-en/api-access/    https://support.qualityunit.com/481318-Get-retrieve-banner-codes-via-API-only-for-affiliates     https://www.dognet.com/magazine/integracia-en/general-tracking-integration/          Szeretnék egy API kapcsolatot a dognap hálózatom és a wordpress weboldalam között
A ChatGPT ezt mondta:

Befejezte a gondolkodást
Ezt mondtad:
Dognet API: https://www.dognet.com/magazine/integracia-en/api-access/ https://support.qualityunit.com/481318-Get-retrieve-banner-codes-via-API-only-for-affiliates https://www.dognet.com/magazine/integracia-en/general-tracking-integration/ Szeretnék egy API kapcsolatot a dognap hálózatom és a wordpress weboldalam között
A ChatGPT ezt mondta:
Kérés a GPT-5 prohoz
Reasoned for 17m 13s
Szuper – teljesen megoldható az API‑kapcsolat a Dognet és a WordPress között. A Dognet a QualityUnit Post Affiliate Network (Post Affiliate Pro) motorjára épül, és ehhez hivatalos PHP API‑t adnak (a PapApi.class.php fájl). Ezzel affiliate (publisher) oldalon le tudjuk hívni a bannereket/linkeket, statisztikákat, és dinamikusan meg tudjuk jeleníteni az oldalon. A lényeget röviden összefoglalom, majd adok egy kész WordPress‑bővítmény kódot, amit fel tudsz tenni.
Mit fog tudni az integráció?
Bejelentkezés a Dognet API‑ba a PapApi.class.php segítségével (affiliate fiókkal). A bejelentkezés a .../scripts/server.php végpontra történik, a Dognet oldalán hivatalosan így dokumentálják és példakódot is adnak hozzá. 
Affiliate network Dognet - Dognet.sk
Bannerek és linkek dinamikus lehívása: a Pap_Affiliates_Promo_BannersGrid rácson keresztül lekérjük a „bannercode” mezőt – ez maga az elhelyezhető HTML kód (kép‑, szöveg‑, HTML‑banner stb.). 
Post Affiliate Pro Support Portal
Affiliate network Dognet - Dognet.sk
SubID (data1/data2) hozzáfűzése: a lekért banner‑linkekhez automatikusan hozzáírjuk a data1 / data2 paramétereket (pl. NGO kód, csatorna/ambassador azonosító). A Post Affiliate Pro gyárilag támogatja a data1 és data2 paramétereket; Dognet kampányokban gyakran el is várják a data1 használatát. 
Post Affiliate Pro
3d.sk
Affiliate network Dognet - Dognet.sk
WP‑oldalon shortcode‑ok:
[dognet_banners] – bannerek megjelenítésére (típus, darabszám, data1/data2 megadásával).
[dognet_link] – egyedi affiliate link generálására (banner ID, opcionális deeplink és data1/data2).
Gyorsítótárazás (WP transients), hogy ne terheljük feleslegesen az API‑t.
Beállítási felület a WP adminban (Dognet alap URL, felhasználó, jelszó, szerep).
Kompatibilitás a jelenlegi Dognet adminnal (login.dognet.sk) és felkészülés a Dognet 2.0‑ra (állítható alap URL). A Dognet 2.0 cikkei REST API‑t is ígérnek főként hirdetőknek (merchants); affiliátusként a PAP API marad a biztos út, de az alap URL mezővel rugalmas a bővítmény.
Affiliate network Dognet - Dognet.sk
+1
Megjegyzés a hitelesítéshez és 2FA‑hoz: a PAP API a felhasználónév/jelszó párossal autentikál; ha 2FA aktív, tokenre is szükség van – ilyenkor külön API‑felhasználót érdemes használni, vagy egyeztetni a támogatással. 
Post Affiliate Pro Support Portal
+1
Lépések (5 perc)
PapApi letöltése: töltsd le a legfrissebb PapApi.class.php fájlt a Dognetből, és tedd majd a bővítmény includes/ könyvtárába. (Dognet „API access” cikk, hivatalos letöltési link.)
Affiliate network Dognet - Dognet.sk
Bővítmény feltöltése (lent a kód) a wp-content/plugins/dognet-api-connector/ mappába.
PapApi.class.php bemásolása a wp-content/plugins/dognet-api-connector/includes/ mappába.
Bővítmény bekapcsolása → Beállítások → Dognet API: add meg az alap URL‑t (https://login.dognet.sk), a Dognet affiliate felhasználódat és jelszavadat, majd Kapcsolat tesztelése.
Shortcode használat oldalakon/bejegyzésekben:
Bannerek:
[dognet_banners type="I,T" limit="8" data1="NGO123" data2="AMB1"]
Egy darab link (deeplinkkel):
[dognet_link banner_id="11110001" url="https://merchant.hu/kategoria/termek" data1="NGO123" text="Megnézem"]
Ez passzol a Sharity Impact Shop folyamathoz is (NGO kód a data1‑ben; ambassador a data2‑ben). Ha a kiválasztott NGO kódját Tally adja át az oldal URL‑jében, a shortcode‑ot úgy is bővíthetjük, hogy automatikusan beolvassa (pl. $_GET['ngo']) – ez a kód váz könnyen kiegészíthető.
WordPress bővítmény – Dognet API Connector (PAP)
Hely: wp-content/plugins/dognet-api-connector/dognet-api-connector.php
(Hozz létre egy includes/ mappát és tedd bele a PapApi.class.php fájlt, amit a Dognetből töltöttél le.)
<?php
/**
 * Plugin Name: Dognet API Connector (PAP)
 * Description: Kapcsolat a Dognet (Post Affiliate Network) API-val: bannerek/URL-ek lehívása és statisztika megjelenítése. Affiliate szerephez optimalizálva.
 * Version: 0.1.0
 * Author: Your Team
 */

if (!defined('ABSPATH')) exit;

class Dognet_API_Connector {
    const OPTION_KEY = 'dognet_api_connector';
    private $options;

    public function __construct() {
        $this->options = get_option(self::OPTION_KEY, [
            'base_url' => 'https://login.dognet.sk',
            'username' => '',
            'password' => '',
            'role'     => 'affiliate', // vagy merchant
        ]);

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('dognet_banners', [$this, 'shortcode_banners']);
        add_shortcode('dognet_link',    [$this, 'shortcode_link']);
    }

    public static function instance() {
        static $inst = null;
        if ($inst === null) $inst = new self();
        return $inst;
    }

    private function pap_api_path() {
        return plugin_dir_path(__FILE__) . 'includes/PapApi.class.php';
    }

    private function get_session() {
        $path = $this->pap_api_path();
        if (!file_exists($path)) {
            return new WP_Error('pap_missing', 'Hiányzik a PapApi.class.php (helye: /includes/). Töltsd le a Dognetből és másold be.');
        }
        require_once $path;
        $server = rtrim($this->options['base_url'], '/') . '/scripts/server.php';
        try {
            $session = new Pap_Api_Session($server);
            $roleConst = (strtolower($this->options['role']) === 'merchant') ? Pap_Api_Session::MERCHANT : Pap_Api_Session::AFFILIATE;
            if (!$session->login($this->options['username'], $this->options['password'], $roleConst)) {
                return new WP_Error('pap_login', 'Sikertelen PAP bejelentkezés: ' . $session->getMessage());
            }
            return $session;
        } catch (Exception $e) {
            return new WP_Error('pap_exception', $e->getMessage());
        }
    }

    public function add_settings_page() {
        add_options_page('Dognet API', 'Dognet API', 'manage_options', 'dognet-api', [$this, 'render_settings']);
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, function($input){
            $out = [];
            $out['base_url'] = esc_url_raw($input['base_url']);
            $out['username'] = sanitize_text_field($input['username']);
            $out['password'] = $input['password']; // igény esetén titkosítsd (pl. wp-config kulcsokkal)
            $out['role']     = in_array($input['role'], ['affiliate','merchant'], true) ? $input['role'] : 'affiliate';
            return $out;
        });
    }

    public function render_settings() {
        ?>
        <div class="wrap">
            <h1>Dognet API beállítások</h1>
            <p>A bővítmény a <code>PapApi.class.php</code> fájlt használja (helye: <code>wp-content/plugins/dognet-api-connector/includes/</code>). A fájl a Dognet adminból tölthető le.</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_KEY); $o = $this->options; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>Dognet alap URL</label></th>
                        <td><input type="text" name="<?php echo self::OPTION_KEY; ?>[base_url]" value="<?php echo esc_attr($o['base_url']); ?>" class="regular-text" placeholder="https://login.dognet.sk"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Felhasználónév (affiliate email)</label></th>
                        <td><input type="text" name="<?php echo self::OPTION_KEY; ?>[username]" value="<?php echo esc_attr($o['username']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Jelszó</label></th>
                        <td><input type="password" name="<?php echo self::OPTION_KEY; ?>[password]" value="<?php echo esc_attr($o['password']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Szerep</label></th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[role]">
                                <option value="affiliate" <?php selected($o['role'],'affiliate'); ?>>Affiliate</option>
                                <option value="merchant"  <?php selected($o['role'],'merchant'); ?>>Merchant</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Kapcsolat teszt</h2>
            <?php
            if (isset($_GET['test']) && current_user_can('manage_options')) {
                $res = $this->get_session();
                if (is_wp_error($res)) {
                    echo '<div class="notice notice-error"><p>'.esc_html($res->get_error_message()).'</p></div>';
                } else {
                    $ver = $res->getAppVersion();
                    echo '<div class="notice notice-success"><p>PAP kapcsolat OK. Verzió: '.esc_html($ver ?: 'ismeretlen').'</p></div>';
                }
            }
            $url = admin_url('options-general.php?page=dognet-api&test=1');
            echo '<p><a class="button button-primary" href="'.esc_url($url).'">Kapcsolat tesztelése</a></p>';
            ?>
        </div>
        <?php
    }

    public function shortcode_banners($atts) {
        $atts = shortcode_atts([
            'type'  => '',   // A,E,F,H,I,V,T (link/email/flash/HTML/image/PDF/text)
            'limit' => 10,
            'data1' => '',   // SubID1 (pl. NGO kód)
            'data2' => '',   // SubID2 (pl. ambassador)
        ], $atts, 'dognet_banners');

        $cache_key = 'dognet_banners_' . md5(serialize($atts));
        $cached = get_transient($cache_key);
        if ($cached) return $cached;

        $session = $this->get_session();
        if (is_wp_error($session)) return '<div class="dognet-error">'.esc_html($session->get_error_message()).'</div>';

        try {
            $request = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $session);
            $request->setLimit(0, intval($atts['limit']));
            if (!empty($atts['type'])) {
                $request->addFilter('rtype', Gpf_Data_Filter::IN, strtoupper($atts['type']));
            }
            $request->addParam('columns', new Gpf_Rpc_Array(array(array('bannercode'), array('name'), array('rtype'))));
            $request->sendNow();
            $recordset = $request->getGrid()->getRecordset();

            $out = '<div class="dognet-banners">';
            foreach ($recordset as $rec) {
                $code = $rec->get('bannercode'); // HTML <a ...><img ...></a> vagy szöveges link
                $code = $this->append_subids_to_link_code($code, $atts['data1'], $atts['data2']);
                $out .= '<div class="dognet-banner">'.$code.'</div>';
            }
            $out .= '</div>';

            set_transient($cache_key, $out, HOUR_IN_SECONDS);
            return $out;
        } catch (Exception $e) {
            return '<div class="dognet-error">'.esc_html($e->getMessage()).'</div>';
        }
    }

    public function shortcode_link($atts) {
        $atts = shortcode_atts([
            'banner_id' => '', // a_bid
            'url'       => '', // deeplink (merchant oldali cél)
            'data1'     => '',
            'data2'     => '',
            'text'      => 'Vásárlás',
        ], $atts, 'dognet_link');

        $session = $this->get_session();
        if (is_wp_error($session)) return '<div class="dognet-error">'.esc_html($session->get_error_message()).'</div>';

        try {
            // Lekérünk egy szöveges link bannert a minta URL miatt, majd paraméterezünk.
            $req = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $session);
            $req->setLimit(0, 1);
            $req->addFilter('rtype', Gpf_Data_Filter::EQUALS, 'T'); // Text-Link
            $req->sendNow();
            $recordset = $req->getGrid()->getRecordset();
            if ($recordset->getSize() < 1) return '<a>'.esc_html($atts['text']).'</a>';
            $code = $recordset->get(0)->get('bannercode');

            if (preg_match('/href=[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                $href = $m[1];
                $href = $this->append_params($href, array_filter([
                    'a_bid' => $atts['banner_id'] ?: null,
                    'data1' => $atts['data1'] ?: null,
                    'data2' => $atts['data2'] ?: null,
                ], fn($v) => $v !== null));

                if (!empty($atts['url'])) {
                    // Egyes programoknál a deeplink paraméter neve eltérhet (pl. desturl). Szükség szerint módosítható.
                    $href = $this->append_params($href, ['desturl' => $atts['url']]);
                }
                return '<a href="'.esc_url($href).'">'.esc_html($atts['text']).'</a>';
            }
            return '<a>'.esc_html($atts['text']).'</a>';
        } catch (Exception $e) {
            return '<div class="dognet-error">'.esc_html($e->getMessage()).'</div>';
        }
    }

    private function append_subids_to_link_code($html, $data1, $data2) {
        if ($data1 === '' && $data2 === '') return $html;
        return preg_replace_callback('/href=([\'"])([^\'"]+)\1/i', function($m) use ($data1, $data2){
            $url = $this->append_params($m[2], array_filter([
                'data1' => $data1 ?: null,
                'data2' => $data2 ?: null,
            ], fn($v) => $v !== null));
            return 'href="'.esc_url($url).'"';
        }, $html);
    }

    private function append_params($url, $params) {
        $parts = wp_parse_url($url);
        $query = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $query);
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $query[$k] = $v;
        }
        $parts['query'] = http_build_query($query);
        $new = (isset($parts['scheme']) ? $parts['scheme'].'://' : '') .
               (isset($parts['host']) ? $parts['host'] : '') .
               (isset($parts['port']) ? ':'.$parts['port'] : '') .
               (isset($parts['path']) ? $parts['path'] : '') .
               (!empty($parts['query']) ? '?'.$parts['query'] : '') .
               (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        return $new;
    }
}
Dognet_API_Connector::instance();
Fontos tudnivalók és hivatkozások
API elérés módja Dognetnél: PAP alacsony szintű API, PapApi.class.php letöltés, .../scripts/server.php végpont, affiliate/merchant szerep szerinti login. 
Affiliate network Dognet - Dognet.sk
Post Affiliate Pro Support Portal
Bannerek lekérése affiliateként – minta kód, rács neve, „bannercode” mező: 
Post Affiliate Pro Support Portal
Általános (merchant oldali) tracking kódok – háttérismeretként (ezt nem kell a publisher WP oldaladon elhelyezni): 
Affiliate network Dognet - Dognet.sk
SubID (data1/data2) támogatás a Post Affiliate Pro rendszerben és egy Dognet kampány speciális elvárása (data1 kötelező): 
Post Affiliate Pro
3d.sk
Affiliate network Dognet - Dognet.sk
Dognet = Post Affiliate Network (QualityUnit) technikai háttér: 
Affiliate network Dognet - Dognet.sk
Dognet 2.0 (2025) – REST API hirdetőknek, 2025. okt. 1‑től az új admin az elsődleges:
Affiliate network Dognet - Dognet.sk
+1
Hogyan illeszkedik a jelenlegi Impact Shop folyamathoz?
A data1‑be mehet az NGO kód, a data2‑be az ambassador/csatorna (ahogy korábban egyeztettük).
Ha /go/{shop} átirányítást használsz Redirection pluginnal, két opció van:
marad a Redirection → a cél URL (Dognet link) végére appendeljük a data1/data2 paramétereket;
a fenti [dognet_link] shortcode‑dal közvetlenül generáljuk a végső Dognet URL‑t (így a Redirection lépés elhagyható).
Következő lépések (ajánlott)
Tedd fel a bővítményt és a PapApi fájlt, majd teszteld a kapcsolatot.
Tegyél ki egy teszt oldalt a bannerekkel:
[dognet_banners type="I,T" limit="6" data1="TESZTNGO" data2="TESZTAMB"]
Ellenőrizd a linkek végén, hogy megjelenik‑e a data1/data2.
Ha szeretnéd, kiegészítem a bővítményt admin riport oldallal (utolsó 30 nap tranzakciói, jutalékok) és automatikus Tally→data1 kitöltéssel.
Ha szeretnéd, a fenti kódból összeállítok egy ZIP‑et leírással – jelezd, és adok kész csomagot (vagy feltöltőd a tárhelyre).
Ezt mondtad:
már dognet 2.0 -t használunk, ennek van új dokumentációja https://docs.google.com/document/d/1HYiJ357wBRXQkXFWQE_BPRoy5lmnqpPom9sgt168SUg/edit?tab=t.0#heading=h.8nd56nk6skz9
A ChatGPT ezt mondta:
Kérés a GPT-5 prohoz
Reasoned for 18m 20s
Szuper – köszönöm a Dognet 2.0‑s (REST) dokumentációt. Fontos: az általad megosztott anyag Merchant API (hirdetői) dokumentáció, tehát hirdetői fiókra vonatkozó végpontokat ír le (Bearer tokenes auth, tranzakciók listázása/szűrése, státuszok jóváhagyása/elutasítása, jutalék újraszámítás stb.).
Közben a Dognet nyilvános “API access” cikke továbbra is jelzi, hogy a publisher (affiliate) oldali integrációhoz a Post Affiliate Network/PAP alacsony szintű API-ja (PapApi.class.php) használható – pl. bannerek lekérésére. Ezt a QualityUnit hivatalos mintakódja is megerősíti. 
Affiliate network Dognet - Dognet.sk
Post Affiliate Pro Support
Az alábbiakban két út van, és mindkettőhöz adok kész WordPress‑megoldást:

1) Ha hirdetőként (Merchant) szeretnél kapcsolódni Dognet 2.0‑hoz (REST)
Mit tud az új API?
Bejelentkezés (Bearer token): POST /api/v1/auth/login e‑mail + jelszó → token; minden kérést ezzel kell hitelesíteni. A token érvényessége 24 óra minden utolsó API‑hívástól számítva (tehát minden hívás hosszabbítja). Rate limit: 240 req/min auth mellett. 
Enumerations: GET /api/v1/common/enums?lang=… – statikus listák, fordítási támogatással. Cache‑elhető. 
Tranzakciók listázása:
Gyors lista: GET /api/v1/raw-transactions (legújabb 20).
Összetett szűrés: POST /api/v1/raw-transactions/filter (pl. dátum, campaign_id, rstatus, user_id; kombinálható and/or operátorokkal). 
Lapozás:
Klasszikus: page, per-page, sort (alap: -id).
last_id görgetés: az első válasz meta.last_id értékét add vissza a következő kérésben last_id mezőként; így nagyon nagy adatmennyiségen is hatékonyan lehet végigmenni. (Ilyenkor a meta‑ban nem lesznek oldalszámok; üres data esetén állj le.) 
Státusz frissítés: PUT /api/v1/raw-transactions/{id} – rstatus: "A"|"D", opcionális note_merchant. (Megjegyzés: P→A/D után már nem módosítható; támogatás szükséges a visszaállításhoz.) 
Jutalék újraszámítás: POST /api/v1/raw-transactions/recalculate-commission (total_cost, raw_transaction_id, update). 
Megjegyzés a 2.0 váltáshoz: a Dognet cikke szerint 2025. október 1‑től a 2.0 lesz az elsődleges admin. Tehát a merchant oldali folyamatokhoz mindenképp érdemes a REST‑et használni.
Affiliate network Dognet - Dognet.sk
Kész WP‑bővítmény (Merchant REST)
Fájl: wp-content/plugins/dognet2-merchant-api/dognet2-merchant-api.php
Telepítés: hozz létre mappát dognet2-merchant-api néven, másold be az alábbi fájlt, majd Bővítmények → Aktiválás. Adminban: Beállítások → Dognet 2.0 (Merchant) → e‑mail/jelszó megadása → Kapcsolat teszt.
Az admin oldalon azonnal tudsz raw-transactions listát lekérni szűrőkkel, státuszokat frissíteni és jutalékot újraszámolni.
<?php
/**
 * Plugin Name: Dognet 2.0 Merchant API Connector
 * Description: WordPress integráció a Dognet 2.0 (Merchant) REST API-hoz: auth, enumerations, raw-transactions listázás/szűrés, státusz frissítés, jutalék újraszámítás.
 * Version: 0.2.0
 * Author: Your Team
 */

if (!defined('ABSPATH')) exit;

final class Dognet2_Merchant_API {
    const OPT_KEY = 'dognet2_merchant_api';
    const NONCE   = 'dognet2m_nonce';

    private $opts;

    public function __construct() {
        $this->opts = get_option(self::OPT_KEY, [
            'base_url' => 'https://api.app.dognet.com/api/v1',
            'email'    => '',
            'password' => '',
            'token'    => '',
            'token_until' => 0,
            'tz'       => '',
            'currency' => '',
        ]);
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_dognet2m_save', [$this, 'handle_save']);
        add_action('admin_post_dognet2m_login', [$this, 'handle_login']);
        add_action('admin_post_dognet2m_query', [$this, 'handle_query']);
        add_action('admin_post_dognet2m_update', [$this, 'handle_update_status']);
        add_action('admin_post_dognet2m_recalc', [$this, 'handle_recalc']);
    }

    public static function instance() { static $i; return $i ?: $i = new self(); }

    public function menu() {
        add_options_page('Dognet 2.0 (Merchant)', 'Dognet 2.0 (Merchant)', 'manage_options', 'dognet2-merchant', [$this,'render']);
    }

    public function register_settings() {
        register_setting(self::OPT_KEY, self::OPT_KEY, function($in){
            return [
                'base_url' => esc_url_raw($in['base_url']),
                'email'    => sanitize_text_field($in['email']),
                'password' => $in['password'],
                'token'    => sanitize_text_field($this->opts['token']),
                'token_until' => intval($this->opts['token_until']),
                'tz'       => sanitize_text_field($this->opts['tz']),
                'currency' => sanitize_text_field($this->opts['currency']),
            ];
        });
    }

    private function headers($is_login = false) {
        $h = ['Content-Type' => 'application/json'];
        if (!$is_login) {
            $tok = $this->get_token();
            if ($tok) $h['Authorization'] = 'Bearer '.$tok;
        }
        return $h;
    }

    private function get_token() {
        // ha van érvényes token, használd; ha nincs/lejárt, próbálj újra loginolni
        if (!empty($this->opts['token']) && time() < intval($this->opts['token_until'])) {
            return $this->opts['token'];
        }
        // login frissítés
        if (!empty($this->opts['email']) && !empty($this->opts['password'])) {
            $resp = $this->api('POST', '/auth/login', [
                'email'    => $this->opts['email'],
                'password' => $this->opts['password'],
            ], true);
            if (!is_wp_error($resp) && !empty($resp['token'])) {
                $this->opts['token'] = $resp['token'];
                // 24 óra - hagyjunk puffer-t (23 óra)
                $this->opts['token_until'] = time() + 23*3600;
                $this->opts['tz'] = $resp['timezone'] ?? '';
                $this->opts['currency'] = $resp['currency'] ?? '';
                update_option(self::OPT_KEY, $this->opts, false);
                return $this->opts['token'];
            }
        }
        return '';
    }

    private function api($method, $path, $body = null, $is_login = false) {
        $url = rtrim($this->opts['base_url'], '/').$path;
        $args = [
            'method'  => $method,
            'headers' => $this->headers($is_login),
            'timeout' => 20,
        ];
        if ($body !== null) $args['body'] = wp_json_encode($body);
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code === 401 && !$is_login) {
            // token lejárt → próbálj egyszer újra
            delete_transient('dognet2m_last_call');
            $this->opts['token_until'] = 0;
            update_option(self::OPT_KEY, $this->opts, false);
            $this->get_token();
            return $this->api($method, $path, $body, $is_login);
        }
        if ($code >= 300) return new WP_Error('dognet2m_http', 'API hiba: '.$code, ['body'=>$json]);
        // aktivitás: token élettartam frissül szerveroldalt minden hívással
        set_transient('dognet2m_last_call', time(), 3600);
        return $json;
    }

    public function handle_save() {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE)) wp_die('Nope');
        $in = $_POST;
        $this->opts['base_url'] = esc_url_raw($in['base_url'] ?? $this->opts['base_url']);
        $this->opts['email']    = sanitize_text_field($in['email'] ?? '');
        $this->opts['password'] = $in['password'] ?? '';
        update_option(self::OPT_KEY, $this->opts, false);
        wp_redirect(admin_url('options-general.php?page=dognet2-merchant&saved=1'));
        exit;
    }

    public function handle_login() {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE)) wp_die('Nope');
        $tok = $this->get_token();
        $ok = !empty($tok);
        wp_redirect(admin_url('options-general.php?page=dognet2-merchant&login='.($ok?'ok':'fail')));
        exit;
    }

    public function handle_query() {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE)) wp_die('Nope');
        $filter = [];
        if (!empty($_POST['created_from'])) $filter[] = ['created_at'=>['gte'=>sanitize_text_field($_POST['created_from'])]];
        if (!empty($_POST['created_to']))   $filter[] = ['created_at'=>['lte'=>sanitize_text_field($_POST['created_to'])]];
        if (!empty($_POST['campaign_id'])) {
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $_POST['campaign_id']))));
            $filter[] = count($ids) > 1 ? ['campaign_id'=>['in'=>$ids]] : ['campaign_id'=>['eq'=>($ids[0] ?? 0)]];
        }
        if (!empty($_POST['rstatus'])) $filter[] = ['rstatus'=>['eq'=>sanitize_text_field($_POST['rstatus'])]];

        $payload = [];
        if (!empty($filter)) $payload['filter'] = $filter;
        if (!empty($_POST['page']))     $payload['page'] = intval($_POST['page']);
        if (!empty($_POST['per_page'])) $payload['per-page'] = intval($_POST['per_page']);
        if (!empty($_POST['sort']))     $payload['sort'] = sanitize_text_field($_POST['sort']);
        if (!empty($_POST['last_id']))  $payload['last_id'] = intval($_POST['last_id']);

        $resp = $this->api('POST', '/raw-transactions/filter', $payload);
        set_transient('dognet2m_last_query', $resp, 15*MINUTE_IN_SECONDS);
        wp_redirect(admin_url('options-general.php?page=dognet2-merchant&queried=1'));
        exit;
    }

    public function handle_update_status() {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE)) wp_die('Nope');
        $id  = intval($_POST['rt_id'] ?? 0);
        $st  = sanitize_text_field($_POST['rstatus'] ?? '');
        $note= sanitize_text_field($_POST['note_merchant'] ?? '');
        if ($id && in_array($st, ['A','D'], true)) {
            $body = ['rstatus'=>$st];
            if ($note !== '') $body['note_merchant'] = $note;
            $this->api('PUT', '/raw-transactions/'.$id, $body);
        }
        wp_redirect(admin_url('options-general.php?page=dognet2-merchant&updated=1'));
        exit;
    }

    public function handle_recalc() {
        if (!current_user_can('manage_options') || !check_admin_referer(self::NONCE)) wp_die('Nope');
        $id    = intval($_POST['rt_id'] ?? 0);
        $cost  = floatval($_POST['total_cost'] ?? 0);
        $upd   = !empty($_POST['update']) ? true : false;
        if ($id && $cost > 0) {
            $this->api('POST', '/raw-transactions/recalculate-commission', [
                'total_cost' => $cost,
                'raw_transaction_id' => $id,
                'update' => $upd
            ]);
        }
        wp_redirect(admin_url('options-general.php?page=dognet2-merchant&recalc=1'));
        exit;
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $last = get_transient('dognet2m_last_query');
        $login_ok = isset($_GET['login']) && $_GET['login']==='ok';
        ?>
        <div class="wrap">
            <h1>Dognet 2.0 (Merchant) – REST API</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="action" value="dognet2m_save">
                <table class="form-table">
                    <tr><th>API base URL</th><td><input class="regular-text" name="base_url" value="<?php echo esc_attr($this->opts['base_url']); ?>"></td></tr>
                    <tr><th>E-mail (merchant)</th><td><input class="regular-text" name="email" value="<?php echo esc_attr($this->opts['email']); ?>"></td></tr>
                    <tr><th>Jelszó</th><td><input class="regular-text" type="password" name="password" value="<?php echo esc_attr($this->opts['password']); ?>"></td></tr>
                </table>
                <?php submit_button('Beállítások mentése'); ?>
            </form>
            <form style="margin-top:10px" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="action" value="dognet2m_login">
                <?php submit_button('Kapcsolat tesztelése'); ?>
            </form>
            <?php if ($login_ok): ?>
                <div class="notice notice-success"><p>Bejelentkezés sikeres. TZ: <?php echo esc_html($this->opts['tz']); ?>, pénznem: <?php echo esc_html($this->opts['currency']); ?>.</p></div>
            <?php endif; ?>

            <hr>
            <h2>Raw transactions lekérdezés</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE); ?>
                <input type="hidden" name="action" value="dognet2m_query">
                <table class="form-table">
                    <tr><th>Dátum tól</th><td><input name="created_from" placeholder="2025-01-01 00:00:00"></td></tr>
                    <tr><th>Dátum ig</th><td><input name="created_to" placeholder="2025-01-31 23:59:59"></td></tr>
                    <tr><th>Campaign ID(k)</th><td><input name="campaign_id" placeholder="4887,4881"></td></tr>
                    <tr><th>rstatus</th><td>
                        <select name="rstatus"><option value="">(mind)</option><option value="P">P</option><option value="A">A</option><option value="D">D</option></select>
                        <span class="description">Lehetséges értékek az /enums végponton is lekérhetők.</span>
                    </td></tr>
                    <tr><th>Lapozás</th><td>
                        page: <input name="page" size="3" value="1"> ,
                        per-page: <input name="per_page" size="3" value="20"> ,
                        sort: <input name="sort" size="8" value="-id"> ,
                        last_id: <input name="last_id" size="8" placeholder="">
                    </td></tr>
                </table>
                <?php submit_button('Lekérdezés'); ?>
            </form>

            <?php if ($last): ?>
                <h3>Eredmény</h3>
                <pre style="max-height:400px;overflow:auto;background:#fff;border:1px solid #ddd;padding:10px"><?php echo esc_html(wp_json_encode($last, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); ?></pre>

                <h3>Gyors műveletek</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px">
                    <?php wp_nonce_field(self::NONCE); ?>
                    <input type="hidden" name="action" value="dognet2m_update">
                    <input name="rt_id" placeholder="raw_transaction ID">
                    <select name="rstatus"><option value="A">Approve (A)</option><option value="D">Decline (D)</option></select>
                    <input name="note_merchant" placeholder="Megjegyzés (opcionális)" size="40">
                    <?php submit_button('Státusz frissítés', 'secondary', '', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE); ?>
                    <input type="hidden" name="action" value="dognet2m_recalc">
                    <input name="rt_id" placeholder="raw_transaction ID">
                    <input name="total_cost" placeholder="Új bruttó érték (pl. 25.00)">
                    <label><input type="checkbox" name="update" value="1"> update</label>
                    <?php submit_button('Jutalék újraszámítás', 'secondary', '', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
Dognet2_Merchant_API::instance();
Miért jó így?
A login/token‑kezelés a dokumentáció szerint történik (Bearer, 24h, rate limits). 
Támogatja az /enums, raw‑transactions szűrés + last_id görgetés logikát (óriás listákra optimalizálva). 
Egy kattintásos Approve/Decline és commission recalc adminból. 
Biztonság: A bővítmény csak manage_options jogosultsággal érhető el; minden művelet nonce‑olt. A jelszó WP‑opcióban tárolódik – élesben javaslom titkosítani (pl. wp-config.php kulccsal vagy KMS‑szel).
2) Ha publisherként (Affiliate) szeretnél integrálni
A Dognet cikke és a QualityUnit dokumentációja alapján a publisher oldali API‑zásra ma is a PAP alacsony szintű API a hivatalos út (bannerek, linkek dinamikus lekérése, tranzakciók riportjai stb.). Ezt a múltkor küldött WP‑bővítményem már megoldja (shortcode‑ok: bannerek, egyedi link generálás; data1/data2 SubID támogatás az NGO/ambassador kódokhoz). 
Affiliate network Dognet - Dognet.sk
Post Affiliate Pro Support
Megjegyzés: A Dognet 2.0 bevezetése mellett is elérhető a PAP API a publisher műveletekre – maga a Dognet közli, hogy a rendszer PAN‑re épül és a “low‑level requests” tovább használható. Amint megjelenik hivatalos 2.0‑s Affiliate REST, át tudjuk húzni a bővítményt arra, de jelenleg a PAP a stabil, dokumentált megoldás. 
Affiliate network Dognet - Dognet.sk
Hogyan illeszkedik az Impact Shop folyamathoz?
Publisher oldalon marad a data1 = NGO kód, data2 = ambassador/csatorna – ezeket a PAP‑os shortcode‑jaim automatikusan fűzik a Dognet link végére (változatlanul kompatibilis). 
Post Affiliate Pro Support
Merchant oldalon (ha van saját kampányod a Dognetben) a fenti 2.0‑s plugin segítségével házon belül tudsz státuszt zárni (A/D), megjegyzést adni és jutalékot újraszámolni (pl. visszaküldött rendelés vagy kosárérték‑módosulás esetén). 
Következő lépések (javaslat)
Mondd meg, hogy publisher vagy merchant (vagy mindkettő) oldalra kéred az integrációt – fent mindkettőre adtam kész megoldást.
Ha merchant: másold fel a fenti Dognet 2.0 Merchant API Connector plugint, állítsd be az adminban, és egy teszt lekérdezéssel nézzük meg, jön‑e adat. (A dokumentáció szerinti válasz JSON‑t listázza az adminban.) 
Ha publisher: használd a korábban küldött PAP‑os bővítményt a bannerekhez/linkekhez. (Ez teljesen kompatibilis Dognet 2.0 adminváltás mellett is.) 
Post Affiliate Pro Support
Ha szeretnéd, a merchant plugint kiegészítem riport‑táblákkal (heti/havi aggregátumok), exporttal (CSV), vagy cron‑os szinkronnal saját táblába – szólj, és beépítem.
Ezt mondtad:
publisher vagyok
A ChatGPT ezt mondta:
Kérés a GPT-5 prohoz
Reasoned for 16m 6s
Szuper, köszi a pontosítást — publisherként (affiliate) fogunk integrálni.
Röviden: a jelenleg publikusan dokumentált és stabil út a Post Affiliate Pro (PAP) API a Dogneten belül a publisher oldali feladatokra (bannerek, linkek, SubID‑k, csatornák). A feltöltött Dognet 2.0 dokumentum Merchant (hirdetői) REST API, ami nem a publisher oldalra készült — jó háttér, de nekünk nem ez kell.
Miért PAP API?
A Dognet (történetileg) a QualityUnit Post Affiliate Network/PAP megoldásán futott, ehhez hivatalos API és kliensosztály (PapApi.class.php) érhető el. 
Affiliate network Dognet - Dognet.sk
Post Affiliate Pro Support
+1

A PAP‑on belül elérhető a bannerek/linkek lekérése (a “Banners & Links” tartalmak affiliate oldalról), sőt külön példacikkük is van a „Get (retrieve) banner codes via API (only for affiliates)” témáról (a „Work with banners” szekcióban). 
Post Affiliate Pro Support

A SubID/Ad channel paraméterezést a PAP natívan támogatja: data1/data2 (d1/d2) és chan=CHANNEL_CODE a linkben. 
Post Affiliate Pro Support
Post Affiliate Pro

Dognet 2.0 alatt a kiemelt publisher‑változás, hogy július 1., 2025‑től a kifizetésekhez a jutalékok ≥90%-ának „új ad channeles linkekből” kell származnia — vagyis a csatornakód használata (chan) kötelező gyakorlat lesz. 
Affiliate network Dognet - Dognet.sk

Kész WordPress bővítmény – Dognet PAP Publisher Connector
Cél: PAP API‑val belépni affiliate szerepben, bannereket dinamikusan lehívni, és a linkekhez automatikusan hozzáfűzni chan/data1/data2 paramétereket (Impact Shop: data1 = NGO kód, data2 = ambassador/csatorna).
Telepítés: lásd a lépéseket a kód után.
Fájl: wp-content/plugins/dognet-pap-publisher/dognet-pap-publisher.php
<?php
/**
 * Plugin Name: Dognet PAP Publisher Connector
 * Description: Publisher (affiliate) integráció a Dognet/PAP API-hoz: bannerek és linkek lehívása, SubID (data1,data2) és csatorna (chan) automatikus fűzése.
 * Version: 0.3.0
 * Author: Your Team
 */

if (!defined('ABSPATH')) exit;

final class Dognet_PAP_Publisher {
    const OPT = 'dognet_pap_pub';
    private $o;

    public function __construct() {
        $this->o = get_option(self::OPT, [
            'base_url' => 'https://login.dognet.sk', // PAP/PAN szerver gyökér (nem az új REST!)
            'username' => '',
            'password' => '',
            // lekérdezési param nevek (GET-ből), első nem üres nyer
            'q_data1'  => 'd1,data1,ngo,ngocode',
            'q_data2'  => 'd2,data2,amb,channel',
            'q_chan'   => 'chan,adchannel',
        ]);
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'register']);
        add_shortcode('dognet_banners', [$this,'sc_banners']);
        add_shortcode('dognet_link',    [$this,'sc_link']);
    }
    public static function instance(){ static $i; return $i?:$i=new self(); }

    /* --------- Admin --------- */
    public function menu() {
        add_options_page('Dognet PAP (Publisher)', 'Dognet PAP (Publisher)', 'manage_options', 'dognet-pap-publisher', [$this,'render']);
    }
    public function register() {
        register_setting(self::OPT, self::OPT, function($in){
            return [
                'base_url' => esc_url_raw($in['base_url'] ?? $this->o['base_url']),
                'username' => sanitize_text_field($in['username'] ?? ''),
                'password' => $in['password'] ?? '',
                'q_data1'  => sanitize_text_field($in['q_data1'] ?? $this->o['q_data1']),
                'q_data2'  => sanitize_text_field($in['q_data2'] ?? $this->o['q_data2']),
                'q_chan'   => sanitize_text_field($in['q_chan']  ?? $this->o['q_chan']),
            ];
        });
    }
    public function render() {
        if (!current_user_can('manage_options')) return;
        $o = $this->o;
        ?>
        <div class="wrap">
            <h1>Dognet PAP (Publisher) – beállítások</h1>
            <p>A bővítmény a <code>PapApi.class.php</code> fájlt használja. Töltsd le a PAP API csomagot a merchant panelből (Tools → Integration → API Integration), és másold ide: <code>wp-content/plugins/dognet-pap-publisher/includes/PapApi.class.php</code>. :contentReference[oaicite:5]{index=5}</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT); ?>
                <table class="form-table">
                    <tr><th>Base URL (PAP)</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[base_url]" value="<?php echo esc_attr($o['base_url']); ?>" placeholder="https://login.dognet.sk"></td></tr>
                    <tr><th>Affiliate e‑mail</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[username]" value="<?php echo esc_attr($o['username']); ?>"></td></tr>
                    <tr><th>Jelszó</th><td><input type="password" class="regular-text" name="<?php echo self::OPT; ?>[password]" value="<?php echo esc_attr($o['password']); ?>"></td></tr>
                    <tr><th>Query → data1</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_data1]" value="<?php echo esc_attr($o['q_data1']); ?>"><br><small>Vesszővel: mely GET paraméterekből próbáljon data1‑et olvasni (pl. d1,data1,ngo)</small></td></tr>
                    <tr><th>Query → data2</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_data2]" value="<?php echo esc_attr($o['q_data2']); ?>"></td></tr>
                    <tr><th>Query → chan</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_chan]"  value="<?php echo esc_attr($o['q_chan']); ?>"><br><small>Dognet 2.0 csatornákhoz: <code>chan</code> (kötelezően ajánlott). :contentReference[oaicite:6]{index=6}</small></td></tr>
                </table>
                <?php submit_button('Mentés'); ?>
            </form>
            <p><em>Megjegyzés:</em> a Dognet 2.0 Merchant REST dokumentuma nem szükséges a publisher integrációhoz; a jelen bővítmény a PAP API‑t használja. :contentReference[oaicite:7]{index=7}</p>
        </div>
        <?php
    }

    /* --------- PAP session --------- */
    private function pap_api_path(){
        return plugin_dir_path(__FILE__).'includes/PapApi.class.php';
    }
    private function session() {
        $path = $this->pap_api_path();
        if (!file_exists($path)) return new WP_Error('pap_missing', 'Hiányzik a PapApi.class.php az includes könyvtárból.');
        require_once $path;
        $server = rtrim($this->o['base_url'], '/') . '/scripts/server.php';
        try {
            $s = new Pap_Api_Session($server);
            if (!$s->login($this->o['username'], $this->o['password'])) {
                return new WP_Error('pap_login', 'PAP bejelentkezés sikertelen: '.$s->getMessage());
            }
            return $s;
        } catch (Exception $e) {
            return new WP_Error('pap_exc', $e->getMessage());
        }
    }

    /* --------- Helpers --------- */
    private function pick_from_query($csv){
        $keys = array_map('trim', explode(',', $csv));
        foreach ($keys as $k) {
            if (isset($_GET[$k]) && $_GET[$k] !== '') {
                return sanitize_text_field(wp_unslash($_GET[$k]));
            }
        }
        return '';
    }
    private function add_params_to_href($html, $params) {
        if (!$html) return $html;
        return preg_replace_callback('/href=([\'"])([^\'"]+)\1/i', function($m) use ($params){
            $u = $this->append_params($m[2], $params);
            return 'href="'.esc_url($u).'"';
        }, $html);
    }
    private function append_params($url, $new){
        $parts = wp_parse_url($url);
        $q = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $q);
        foreach ($new as $k=>$v){ if($v!=='' && $v!==null) $q[$k]=$v; }
        $parts['query'] = http_build_query($q);
        $built = (isset($parts['scheme'])?$parts['scheme'].'://':'').
                 ($parts['host']??'').
                 (isset($parts['port'])?':'.$parts['port']:'').
                 ($parts['path']??'').
                 (!empty($parts['query'])?('?'.$parts['query']):'').
                 (isset($parts['fragment'])?('#'.$parts['fragment']):'');
        return $built;
    }

    /* --------- Shortcodes --------- */

    // [dognet_banners types="I,T" limit="8" d1="" d2="" chan="" read_query="1"]
    public function sc_banners($atts){
        $a = shortcode_atts([
            'types'      => '',   // 'I' (image), 'T' (text), 'H' (HTML), stb.
            'limit'      => 8,
            'd1'         => '',   // SubID1 (NGO kód)
            'd2'         => '',   // SubID2 (ambassador/csatorna)
            'chan'       => '',   // PAP ad channel kód (Dognet 2.0-hoz is)
            'read_query' => '1',  // GET-ből felülírhatja a d1/d2/chan-t
        ], $atts, 'dognet_banners');

        if ($a['read_query'] === '1') {
            $a['d1']   = $this->pick_from_query($this->o['q_data1']) ?: $a['d1'];
            $a['d2']   = $this->pick_from_query($this->o['q_data2']) ?: $a['d2'];
            $a['chan'] = $this->pick_from_query($this->o['q_chan'])  ?: $a['chan'];
        }

        $cache_key = 'dognet_pap_banners_'.md5(json_encode($a));
        if ($html = get_transient($cache_key)) return $html;

        $s = $this->session();
        if (is_wp_error($s)) return '<div class="notice notice-error">'.esc_html($s->get_error_message()).'</div>';

        try {
            // Bannerek lekérése affiliate nézetben
            $req = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $s);
            $req->setLimit(0, (int)$a['limit']);
            if (!empty($a['types'])) $req->addFilter('rtype', Gpf_Data_Filter::IN, strtoupper($a['types']));
            $req->addParam('columns', new Gpf_Rpc_Array([['bannercode'],['name'],['rtype']]));
            $req->sendNow();
            $rs = $req->getGrid()->getRecordset();

            $out = '<div class="dognet-pap-banners">';
            foreach ($rs as $r) {
                $code = $r->get('bannercode'); // PAP előállított HTML
                $code = $this->add_params_to_href($code, array_filter([
                    'chan'  => $a['chan'],   // PAP csatorna (Dognet 2.0 elvárásaira felkészítve) :contentReference[oaicite:8]{index=8}
                    'data1' => $a['d1'],     // SubID1 (NGO)
                    'data2' => $a['d2'],     // SubID2 (ambassador)
                ], fn($v)=>$v!==''));
                $out .= '<div class="dognet-pap-banner">'.$code.'</div>';
            }
            $out .= '</div>';

            set_transient($cache_key, $out, HOUR_IN_SECONDS);
            return $out;
        } catch (Exception $e) {
            return '<div class="notice notice-error">'.esc_html($e->getMessage()).'</div>';
        }
    }

    // [dognet_link text="Megnézem" url="" d1="" d2="" chan="" a_bid=""]
    public function sc_link($atts){
        $a = shortcode_atts([
            'text' => 'Vásárlás',
            'url'  => '',     // (opcionális) deeplink a kereskedő oldalára
            'd1'   => '',
            'd2'   => '',
            'chan' => '',
            'a_bid'=> '',     // (opcionális) banner ID erőltetése
            'read_query' => '1',
        ], $atts, 'dognet_link');

        if ($a['read_query'] === '1') {
            $a['d1']   = $this->pick_from_query($this->o['q_data1']) ?: $a['d1'];
            $a['d2']   = $this->pick_from_query($this->o['q_data2']) ?: $a['d2'];
            $a['chan'] = $this->pick_from_query($this->o['q_chan'])  ?: $a['chan'];
        }

        $s = $this->session();
        if (is_wp_error($s)) return '<div class="notice notice-error">'.esc_html($s->get_error_message()).'</div>';

        try {
            // Kérünk egy szöveges link-mintát, hogy legyen kiinduló href
            $req = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $s);
            $req->setLimit(0, 1);
            $req->addFilter('rtype', Gpf_Data_Filter::EQUALS, 'T'); // text link
            $req->addParam('columns', new Gpf_Rpc_Array([['bannercode']]));
            $req->sendNow();

            $code = $req->getGrid()->getRecordset()->get(0)->get('bannercode');
            if (!preg_match('/href=[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                return '<a>'.esc_html($a['text']).'</a>';
            }
            $href = $m[1];
            $href = $this->append_params($href, array_filter([
                'a_bid' => $a['a_bid'] ?: null,
                'chan'  => $a['chan'] ?: null,
                'data1' => $a['d1']   ?: null,
                'data2' => $a['d2']   ?: null,
            ], fn($v)=>$v!==null));

            if (!empty($a['url'])) {
                // PAP-nál gyakori a 'desturl' deeplink param
                $href = $this->append_params($href, ['desturl' => $a['url']]);
            }

            return '<a href="'.esc_url($href).'">'.esc_html($a['text']).'</a>';
        } catch (Exception $e) {
            return '<div class="notice notice-error">'.esc_html($e->getMessage()).'</div>';
        }
    }
}
Dognet_PAP_Publisher::instance();
Telepítés (3 lépés)
Mappa + fájlok: hozd létre: wp-content/plugins/dognet-pap-publisher/, benne a fenti dognet-pap-publisher.php.
PAP API osztály: töltsd le a PapApi.class.php fájlt a PAP panelből (Merchant panel → Tools → Integration → API Integration → Download PAP API) és másold:
wp-content/plugins/dognet-pap-publisher/includes/PapApi.class.php. 
Post Affiliate Pro Support
Aktiválás: WP admin → Bővítmények → Dognet PAP Publisher Connector → Bekapcsolás → Beállítások → Dognet PAP (Publisher).
Base URL (PAP): pl. https://login.dognet.sk (PAP szerver), nem a Dognet 2.0 REST.
login.dognet.sk
Használat – shortcode példák
Bannerek listázása, automatikus NGO/AMb/chan felvétellel (GET‑ből is):
[dognet_banners types="I,T" limit="8" d1="NGO123" d2="AMB1" chan="FB2025" read_query="1"]
Ha az URL-ben jön ?ngo=...&amb=...&chan=..., a bővítmény ezeket felülírja a linkben.
A chan paraméter a PAP „Ad channels” funkcióját használja (Dognet 2.0 előírásaihoz igazodva).
Post Affiliate Pro
Affiliate network Dognet - Dognet.sk
Egyedi deeplink (pl. kategória/termék oldalra):
[dognet_link text="Megnézem" url="https://kereskedo.hu/kategoria/termek" d1="NGO123" d2="AMB1" chan="FB2025"]
A PAP‑ban a deeplink param neve gyakran desturl. A Dognet saját útmutatója is ír a linkek kézi módosításáról (deeplink). 
Affiliate network Dognet - Dognet.sk
Megjegyzések az Impact Shop folyamathoz
data1 = NGO kód, data2 = ambassador/csatorna – ezeket vagy shortcode‑ban adod meg, vagy a Tally átirányítás GET paramétereiből olvassuk be automatikusan (d1,data1,ngo,ngocode / d2,data2,amb,channel).
chan: a Dognet 2.0 kifizetési szabályai miatt használd, és rendezd előre az „Ad channels” kódjaidat (pl. „FB2025”, „NEWSLETTER”, „YT‑Q3”). A PAP leírás is chan=CHANNEL_CODE formát javasol.
Post Affiliate Pro
Affiliate network Dognet - Dognet.sk
A bővítmény transient cache‑t használ (1 óra) a bannerekhez, hogy kímélje az API‑t.
Hivatkozások / bizonyítékok
PAP API elérhetőség, kliensosztály (PapApi.class.php): hivatalos QualityUnit leírás és követelmények. 
Post Affiliate Pro Support
+1
Bannerek affiliate oldali elérése és kódok: „Banners & Links” funkció + „Get (retrieve) banner codes via API (only for affiliates)” (a „Work with banners” részben). 
Post Affiliate Pro Support
+1
SubID/Ad channel paraméterezés: d1/d2 szerepel a trackelésben; csatorna: chan=CHANNEL_CODE.
Post Affiliate Pro Support
Post Affiliate Pro
Dognet 2.0 publisher szabályok (csatornás linkek kötelezőek a kifizetéshez). 
Affiliate network Dognet - Dognet.sk
Az általad küldött PDF: Merchant API (hirdetői) – nem vonatkozik a publisher oldalra.
Következő lépés (ajánlott)
Másold be a bővítményt és a PapApi.class.php‑t; állítsd be az affiliate belépési adataidat.
Tedd egy teszt oldalra:
[dognet_banners types="I,T" limit="6" chan="TEST2025" read_query="1"]
Nyisd meg az oldalt ?ngo=NGO123&amb=AMB1&chan=FB2025 paramokkal, és ellenőrizd, hogy a generált href linkek végén megjelenik‑e chan, data1, data2.
Ha szeretnéd, készítek hozzá CSV export/riport shortcake‑et is (pl. top linkek), vagy beolvasom a banner kategóriákat; ezekhez is van PAP API minta. 
Post Affiliate Pro Support
Ha van konkrét shop/kampány, amit első körben ki szeretnél tenni (vagy megadod a használt query param neveket a Tally‑ban), a fenti kódot azonnal ahhoz igazítom.
Ezt mondtad:
Mappa + fájlok: hozd létre: wp-content/plugins/dognet-pap-publisher/, benne a fenti dognet-pap-publisher.php. hol hozzam létre? Mindig arra tekintettel fogalmazz megfelelő részletezettség mellett, hogy nekem nincs informatikai tudásom és tapasztalatom.
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Teljesen érthető kérdés — lépésről lépésre megmutatom, pontosan hol és hogyan hozd létre a wp-content/plugins/dognet-pap-publisher/ mappát, még akkor is, ha nincs informatikai előképzettséged. A végén adok egy letölthető, kész bővítmény-ZIP-et is, hogy ne kelljen kézzel fájlokat létrehoznod.
Hol van a wp-content/plugins mappa?
A WordPress webhelyed fájljai a tárhelyeden (hostingon) találhatók. A legfontosabb hely a WordPress gyökérmappa (ahol az wp-config.php van). Ebben van egy wp-content nevű mappa, azon belül pedig a plugins mappa:
/ (webhely gyökér)
├─ wp-admin
├─ wp-content
│  ├─ plugins   ← IDE kerülnek a bővítmények
│  └─ themes
└─ wp-config.php
Ezt a mappát két egyszerű módon érheted el:
1) A legegyszerűbb: feltöltés ZIP-ből a WordPress adminon
Ezzel nem kell kézzel mappákat létrehoznod.
Töltsd le a kész csomagot:
Dognet PAP Publisher plugin – ZIP
Lépj be a WordPress Vezérlőpultba → Bővítmények → Új hozzáadása → Bővítmény feltöltése
Válaszd ki a letöltött ZIP-et → Telepítés most → Bővítmény bekapcsolása.
Egy apró plusz lépés kell: a PAP API egy külön fájlja (PapApi.class.php) kell még a bővítmény alá. Ezt csak egy fájl bemásolása jelenti:
Lépj be a tárhelyed Fájlkezelőjébe (pl. cPanel / Plesk) vagy használj SFTP-t.
Navigálj ide: wp-content/plugins/dognet-pap-publisher/includes/
Másold ide a PapApi.class.php fájlt (a Dognet/PAP API csomagból származik).
Vissza a WordPress-be: Beállítások → Dognet PAP (Publisher) menüben add meg:
Base URL (PAP): pl. https://login.dognet.sk
Affiliate e-mail és jelszó (Dognet affiliate belépőd).
Használat: tegyél be egy oldalra például ilyet:
 [dognet_banners types="I,T" limit="6" d1="NGO123" d2="AMB1" chan="FB2025" read_query="1"]
Ha az oldal URL-jéhez hozzáírod, hogy ?ngo=NGO123&amb=AMB1&chan=FB2025, a linkek végén ezek automatikusan megjelennek.
Megjegyzés: Az általad korábban megosztott Dognet 2.0 anyag Merchant (hirdetői) REST API – publisherként ezt nem kell használnod; a mi bővítményünk a PAP (publisher) API-t használja.
2) Kézi létrehozás a tárhelyen (ha valamiért nem megy a ZIP-feltöltés)
Nyisd meg a tárhelyed Fájlkezelőjét (vagy csatlakozz SFTP-vel).
Menj a webhelyed gyökérmappájába (ahol a wp-config.php van).
Nyisd meg: wp-content → plugins.
Kattints „Új mappa” és hozd létre: dognet-pap-publisher.
Ebbe hozz létre még egy mappát: includes.
A dognet-pap-publisher mappába másold be a dognet-pap-publisher.php fájlt (a letöltött ZIP-ből ki tudod szedni).
Az includes mappába másold be a PapApi.class.php fájlt (a Dognet/PAP API csomagból).
Lépj a WordPress Vezérlőpult → Bővítmények képernyőre, és kapcsold be a Dognet PAP Publisher Connector bővítményt.
Állítsd be a Beállítások → Dognet PAP (Publisher) oldalon, és tedd ki a shortcode-ot egy oldalra (lásd fent).
„Mi az a PapApi.class.php és honnan szerzem?”
Ez a Dognet/QualityUnit PAP hivatalos kliens fájlja. A Dognet/PAP admin felületen érhető el letöltésre (API integrációs rész). A bővítmény ezt használja a bejelentkezéshez és a bannerek lekéréséhez.
Gyors ellenőrzőlista (el fog indulni?)
A Bővítmények listában látod: Dognet PAP Publisher Connector → Bekapcsolva.
A wp-content/plugins/dognet-pap-publisher/includes/ mappában benne van a PapApi.class.php.
A Beállítások → Dognet PAP (Publisher) oldalon megadtad a PAP szerver URL-t (pl. https://login.dognet.sk) és a belépési adataidat.
Egy oldalon elhelyeztél egy shortcode-ot (pl. [dognet_banners ...]), és az oldalon meg is jelennének a bannerek/linkek.
Ha bárhol piros hibaüzenetet látsz, szó szerint megmondja, mi hiányzik (pl. „Hiányzik a PapApi.class.php…”). Ezt pótold az includes mappában, és kész.
Mit hova ír a rendszer? (Impact Shop vonatkozás)
data1 mező: NGO kód
data2 mező: nagykövet/csatorna azonosító
chan: Dognet „Ad channel” kód
A fenti shortcode ezeket automatikusan hozzáfűzi a Dognet-linkek végéhez. URL-ből is át tudjuk venni: ?ngo=...&amb=...&chan=....
Szeretnéd, hogy a PapApi.class.php bemásolás lépését is „megoldjam helyetted” (pl. pontos tárhely-felület lépéseivel: cPanel, Plesk vagy SFTP képernyők alapján)? Szívesen leírom célzottan a nálad használt felülethez illesztve.


Ezt mondtad:
Dognet PAP Publisher plugin – ZIP nem tudom innen letölteni
A ChatGPT ezt mondta:
Értem 👍 Nem kell kézzel mappát létrehoznod a tárhelyen — adok egy ZIP fájlt, amit közvetlenül a WordPress adminból fel tudsz tölteni.
Itt van a letölthető bővítmény:
📦 dognet-pap-publisher.zip

Mit kell vele tenned?
Menj a WordPress Vezérlőpultba → Bővítmények → Új hozzáadása → Bővítmény feltöltése.
Válaszd ki a most letöltött dognet-pap-publisher.zip fájlt → Telepítés most → Bekapcsolás.
Ezután a tárhelyed fájlkezelőjében (vagy SFTP-vel) navigálj ide:
wp-content/plugins/dognet-pap-publisher/includes/
és ide másold be a PapApi.class.php fájlt (a Dognet API csomagból).
Vissza a WordPress-ben: Beállítások → Dognet PAP (Publisher). Itt add meg:
Base URL: https://login.dognet.sk
Affiliate e-mail és jelszó (a Dognet affiliate belépésed).
Ezután oldalakon/bejegyzésekben használhatod például:
[dognet_banners types="I,T" limit="6" chan="FB2025" read_query="1"]
vagy
[dognet_link text="Megnézem" url="https://kereskedo.hu/termek" d1="NGO123" d2="AMB1" chan="FB2025"]
Szeretnéd, hogy lépésről lépésre képekkel mutassam meg, hogyan töltsd fel a ZIP-et a WordPress adminban?
Ezt mondtad:
felöltés sikerült, most a tárhelyemre próbálok belépni, de nincs nálam belépő link, a WP dől át lehet oda menni?
A ChatGPT ezt mondta:
Ez nagyon jó hír, hogy a bővítmény feltöltése sikerült! 🎉
A következő lépés az, hogy a bővítmény includes mappájába bemásoljuk a PapApi.class.php fájlt. Ez azonban már nem a WordPress adminon belül történik, hanem a webtárhely fájlrendszerében.

Hogyan tudsz belépni a tárhelyed fájljaiba?
WordPress adminból közvetlenül nem lehet a tárhely fájlrendszerébe belépni.
– A WP adminban csak a tartalmat, bővítményeket, kinézetet tudod kezelni.
– A wp-content/plugins/... mappát közvetlenül onnan nem tudod elérni.
Két tipikus mód van a tárhelyhez hozzáférni:
Tárhelyszolgáltató felülete (pl. cPanel, DirectAdmin, Plesk): belépve van egy File Manager menüpont, amivel tallózhatod a WordPress fájlokat.
FTP vagy SFTP kapcsolat: ehhez kell egy FTP kliens program (pl. FileZilla), és a tárhelyedhez tartozó FTP hozzáférési adatok (szerver cím, felhasználónév, jelszó).
Hol találod a belépési linket/adatokat?
Általában a tárhelyszolgáltatód küldött e-mailben telepítéskor, vagy az ügyfélkapuban megtalálod.
Olyasmi link, mint: https://cpanel.sajatdomain.hu vagy https://tarhelyszolgaltato.hu/login.
👉 Ha szeretnéd, segítek:
Megírod, hogy melyik szolgáltatónál van a tárhelyed (pl. NetMasters, RackForest, cPanel, WordPress.com stb.),
Én pontosan leírom, hová kell belépni, és hol találod a fájlkezelőt, hogy bemásolhasd a PapApi.class.php fájlt.
Szeretnéd, hogy megnézzem, hogyan kell belépni a tárhelyedhez a szolgáltatód felületén?
Ezt mondtad:
WordPress.com tárhelyszolgáltatóhoz milyen linken lehet belépni?
A ChatGPT ezt mondta:
Értem 👍 Ez fontos különbség:
👉 WordPress.com és 👉 saját (pl. cPanel-es) WordPress tárhely nem ugyanaz.

WordPress.com (ahol a webhelyed címe pl. valami.wordpress.com vagy fizetős WordPress.com előfizetés alatt fut):
A fájlokhoz nem férsz hozzá közvetlenül (nincs wp-content/plugins/ mappa, sem FTP).
Ilyen esetben saját bővítményt sem tudsz feltölteni, csak a WordPress.com által engedélyezett pluginokat.
Belépési link: mindig https://wordpress.com/log-in → innen jutsz be az admin felületre.
Saját tárhely (pl. RackForest, NetMasters, cPanel stb., ahol te telepítetted a WordPresst):
Van wp-content/plugins/ mappád, FTP hozzáférésed és fájlkezelőd.
Itt tudsz saját ZIP-ből pluginokat feltölteni, és a PapApi.class.php fájlt is bemásolni.
🔑 Ezért valószínűleg most az a gond:
Ha tényleg WordPress.com-on van a weboldalad, akkor nem tudod feltölteni a Dognet PAP Publisher plugint, mert ehhez közvetlen fájlhozzáférés kellene.
📌 Megoldások:

Ellenőrizd: a weboldalad címe végződik-e .wordpress.com-ra, vagy a WordPress.com fiókodban fut?
Ha igen → Sajnos itt nem működik a Dognet plugin (csak nagyon korlátozott külső integráció lehetséges).
Ha a domain saját (pl. sajatdomain.hu) és tárhelyszolgáltatónál telepített WordPress fut rajta → akkor a tárhelyszolgáltatód (nem a WordPress.com) ad belépési linket (pl. cPanel).
❓ Tudod nekem mondani a weboldalad címét (pl. valami.hu vagy valami.wordpress.com), hogy biztosra menjünk, WordPress.com-os vagy saját tárhelyes WordPressed van?
Ezt mondtad:
sharity.hu nincs saját tárhely
A ChatGPT ezt mondta:
Köszi, hogy pontosítottál 🙏
Ha a sharity.hu weboldalad a WordPress.com szolgáltatásán fut (nincs mögötte külön tárhely, pl. cPanel vagy NetMasters), akkor ez a helyzet:

1. WordPress.com korlátozásai
A WordPress.com alap- és prémium csomagjaiban nem férsz hozzá a fájlokhoz (wp-content/plugins/...), és nem lehet egyedi bővítményt telepíteni.
Csak azokat a plugineket használhatod, amelyeket a WordPress.com előre engedélyezett.
Ez azt is jelenti, hogy a Dognethez készített egyedi PAP Publisher plugin-t nem tudod használni WordPress.com-on.
2. Mikor lehet saját plugint használni WordPress.com-on?
Csak a legmagasabb csomagban (WordPress.com Business vagy Commerce) engedélyezett a Saját plugin feltöltés.
Ha Business csomagod van, akkor a WordPress adminban a Bővítmények menüben van lehetőség feltöltésre → ott fel tudod tölteni a ZIP-et (és utána már nem kell tárhelyhez hozzáférned).
3. Ha nincs Business csomagod
Akkor a Dognet integrációhoz két lehetőség van:
Átváltasz Business csomagra a WordPress.com-on, és akkor működik a plugin.
Vagy átköltözteted a weboldalt egy saját tárhelyre (pl. RackForest, NetMasters, Tárhely.eu), ahol teljes hozzáférést kapsz a fájlokhoz és plugineket telepíthetsz.
👉 Tehát:
Ha most nincs Business csomagod, akkor a Dognet plugin telepítése nem fog menni WordPress.com-on.
Ha szeretnéd, tudok segíteni abban, hogyan tudod megnézni, milyen csomagod van WordPress.com-on, és hogy érdemes-e frissíteni Business csomagra, vagy inkább saját tárhelyre költözni (ami hosszú távon olcsóbb és szabadabb megoldás szokott lenni).
Szeretnéd, hogy megnézzük, milyen WordPress.com csomagod van most (és hogy elég-e a pluginozáshoz)?



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
