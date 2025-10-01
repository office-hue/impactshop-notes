Átugrás a tartalomra
Ezt mondtad:
Nyereményjáték: ezt egyeztettük eddig: Amennyiben az Impact shop vásárláshoz szeretnék nyereményjátékot szervezni, azaz, ha valaki vásárol az Impact Shop-ban, részt vesz egy sorsoláson, arra van valami megoldásod?
A ChatGPT ezt mondta:

Ennyi ideig gondolkozott: 20s
Van rá jó, jog- és technika-barát megoldás — sőt, kettő út van, attól függően, hogy akarsz-e “csak vásárlóknak” belépést, vagy biztosra mennél jogilag.
A) “Biztos” út: belépés nem kötött vásárláshoz (de a vásárlás extra esélyt ad)
Ez EU/HU gyakorlatban a legsimább: nem lesz “szerencsejáték/engedélyköteles” vita, mert nem feltétel a vásárlás.
Belépés: rövid űrlap (név, e-mail, szabályzat elfogadás).
Extra sorsjegy(ek): ha Dognetben valós vásárlás jön ugyanazzal az e-maillel/azonosítóval, +N extra esély.
Kommunikáció: “Vásárlással több esélyed van, de nem kötelező.”
Előny: kicsi jogi súrlódás, olcsó admin.
B) “Kemény” út: belépés csak vásárlóknak
Ezt is lehet, de: HU-ban “nyereményjáték vásárláshoz kötve” sokszor bejelentés/engedély és adózási szabályok alá esik (nyereményadó, NAV bejelentés, játékszabály, sorsolási jegyzőkönyv, sőt felügyeleti szempontok).
Terv: vállalható, de mindenképp jogásszal/NAV-val egyeztetünk a pontos formáról, határidőkről, nyeremény értékplafonról.
Nem jogi tanács: a gyakorlatban a (A) modellt választják a cégek, és hozzáadnak vásárlás-alapú extra esélyeket. Így marketingcél teljesül, compliance nyugodt.
Megvalósítás – hogyan csináljuk meg Sharityn
Alább azt a technikai tervet adom, amit bármelyik út mellett használhatunk. A kulcs: amit most is építünk (Dognet 2.0, d1=NGO-kód, /go/{shop}), közvetlenül illeszkedik a játékhoz.
1) Stabil azonosítók
NGO kód (d1): marad kötelező a linkekben — ettől tudunk webshop × NGO bontásban is toplistát.
Click/Order azonosító: a Dognet visszaad clickid/refid + order id. Ezzel egyetlen vásárlás = egyértelmű sorsjegy.
Felhasználó összekötés: legjobb, ha az űrlapon e-mailt kérünk, és a /go átirányításkor session-be sütünk egy entry_uid-t (pl. sha256(email|timestamp|salt)). A Dognet konverziót visszakötjük az adott entry_uid-hez a click paraméterein keresztül (refid).
2) Adatséma (WP saját táblák)
Hozzunk két egyszerű táblát:
wp_impactshop_promotions

id (PK), slug, title, start_at, end_at, rules_url, prize_desc, multiplier_per_purchase (pl. 5 extra esély/vásárlás), status
wp_impactshop_entries
id (PK), promotion_id, entry_uid, email, ngo_code, shop_slug, source (form | purchase),
dognet_order_id (ha purchase), dognet_click_id, amount, state (pending|approved),
consent_rules (bool), consent_marketing (bool), created_at
Így ugyanabból az adatból lehet összesített adományt számolni (külön modul), és sorsjegyet kiosztani.
3) Folyamat (A-modell: nem kötelező a vásárlás)
Belépő űrlap az Impact Shopon (név, e-mail, szabályzat checkbox, opcionális NGO választó). Mentjük wp_impactshop_entries-be (source=form, 1 sorsjegy).
/go/{shop} kattnál továbbadjuk a refid/clickid-et és sütiben/sessionben visszük az entry_uid-t.
Dognet szinkron (cron, pl. 15 perc / naponta): új konverziókat lehúzzuk, kiképezzük shop_slug, ngo_code(d1), order_id, amount, status.
Ha találunk illeszkedő entry_uid/e-mailt: beszúrunk N extra sorsjegyet a wp_impactshop_entries-be (source=purchase).
Ha nem találunk, létrehozunk egy új “purchase” bejegyzést anonim módon (vagy kérünk önkéntes e-mail-összekötést a nyeremény átvételéhez).
Csalásszűrés: ugyanarra a order_id-ra csak 1× jár plusz esély; napi/összesített limit e-mail/személy/kártya/összeg alapján (beállítható).
Átlátható sorsolás: Egyenlő esélyű lista = minden entry egy sorszám. Sorsolás napján a seed legyen nyilvános (pl. “a Szerencsejáték Zrt. … heti lottó első 5 számának összege + promó id hash”), és random_int()-tel válasszunk. Jegyzőkönyv + tanúk; eredmény publikálása.
4) Frontend / admin
Játék állapot-widget: fut-e, hány nevező, hány extra esély, top NGO/shop (marketing).
Export: CSV a sorsjegyekről (id, email, source, shop, ngo).
Nyertesek kezelése: státusz: draft_winner → confirmed (miután visszaigazolta).
5) Jog és adatvédelem (gyors ellenőrzőlista)
Játékszabályzat (időszak, részvételi feltételek, kizárások, nyeremény, adózás, sorsolás módja, pótnyertes).
Adatkezelési tájékoztató (külön cél: játék lebonyolítása; megőrzési idő; adatfeldolgozó(k)).
Hozzájárulások: külön checkbox a szabályzathoz (kötelező), külön marketinghez (opcionális).
**Adózás/Bejelentés
Ezt mondtad:
B) “Kemény” út: belépés **csak vásárlóknak**
Lehet ez a verzió, mert jogilag meg tudom oldani hogy rendben legyen.
A ChatGPT ezt mondta:

Ennyi ideig gondolkozott: 15s
Szuper — akkor megyünk a B-verzióval (csak vásárlók vehetnek részt). Összeraktam egy konkrét, azonnal beilleszthető megoldást WordPresshez, ami:
a Dognet 2.0 riportból veszi a vásárlásokat,
(shop × NGO) bontásban tárolja az adatokat (későbbi adomány-toplistákhoz is jó),
duplikáció-biztosan hoz létre sorsjegyeket (egy order → egy sorsjegy, vagy szabály szerinti több),
ad shortcode-okat (statisztika, részvételi szám),
és egy determinista sorsolási segédfüggvényt (nyilvános seeddel átlátható).
Jog oldala: mivel vásárláshoz kötött, játékszabály, adatkezelés, adózás/NAV bejelentés legyen rendben. Technikailag úgy állítottam be, hogy alapértelmezetten csak jóváhagyott (approved) rendelések adnak sorsjegyet — ez a legvédettebb.
Mit kapsz most?
1) Beállítható állandók (Dognet és promó)
A kód tetején állítsd be:
IMPACTSHOP_PROMO_SLUG – pl. impactshop-2025-q4
IMPACTSHOP_RAFFLE_START / END – dátumablak (mit vegyünk figyelembe)
DOGNET_API_BASE, DOGNET_PUBLISHER_ID, DOGNET_API_TOKEN – a pdognet/Dognet 2.0 adataid (ha kész)
2) Saját táblák
wp_impactshop_raffle_entries: minden sorsjegy (forrás: approved vásárlás)
wp_impactshop_totals: összesítés (shop × NGO), hogy később az adomány-toplistát azonnal tudd mutatni
3) Cron szinkron
Óránként lefut (állítható), lekéri az új/meg nem látott rendeléseket a megadott időablakra
Dedup: (order_id + program_id) kulcson
Csak approved státuszt vesz figyelembe (alapértelmezés). Ha más szabály kell, írom.
4) Shortcode-ok
[impactshop_raffle_count promo="impactshop-2025-q4"] → résztvevők / sorsjegyek száma
[impactshop_raffle_totals promo="impactshop-2025-q4" state="approved"] → (shop×NGO) toplista rövidített összesítéssel
5) Átlátható sorsolás
impactshop_draw_winner($promo_slug, $public_seed) → visszaad 1 nyertest a sorsjegy-listából.
A public_seed lehet pl. “lottó sorsolás dátum + számok”, így ellenőrizhető.
WPCode snippet (illeszd be egyben, Aktiválás: Auto Insert – Run Everywhere)
<?php
/**
 * ImpactShop – Vásárláshoz kötött nyereményjáték (Dognet 2.0)
 * - Csak jóváhagyott (approved) rendelések adnak sorsjegyet (alapbeállítás).
 * - Adatokat shop × NGO bontásban is tárolja (összesítésekhez).
 */

/* =======================
 * 1) KONFIG
 * ======================= */

// Promó meta
define('IMPACTSHOP_PROMO_SLUG', 'impactshop-2025-q4');
define('IMPACTSHOP_RAFFLE_START', '2025-10-01 00:00:00');
define('IMPACTSHOP_RAFFLE_END',   '2025-12-31 23:59:59');
define('IMPACTSHOP_TIMEZONE', 'Europe/Budapest');

// Dognet 2.0 (pdognet) API – TÖLTSD KI
define('DOGNET_API_BASE',      'https://api.dognet.example/v2'); // példa
define('DOGNET_PUBLISHER_ID',  'YOUR_PUBLISHER_ID');
define('DOGNET_API_TOKEN',     'YOUR_SECRET_TOKEN');

// Sorsjegy szabályok
define('IMPACTSHOP_TICKETS_PER_APPROVED_ORDER', 1); // 1 rendelés = 1 sorsjegy
// Ha extra esélyt adnál kosárérték szerint, itt kezeld (pl. minden 20 000 Ft után +1): implementálva lent.

/* =======================
 * 2) DB TÁBLÁK
 * ======================= */

register_activation_hook(__FILE__ ?? 'impactshop_raffle_wpcode', function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $entries = $wpdb->prefix . 'impactshop_raffle_entries';
    $totals  = $wpdb->prefix . 'impactshop_totals';

    $sql1 = "CREATE TABLE IF NOT EXISTS $entries (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      promo_slug VARCHAR(191) NOT NULL,
      entry_uid VARCHAR(191) NOT NULL,          -- belső azonosító (hash)
      email VARCHAR(191) DEFAULT '' ,
      shop_slug VARCHAR(191) DEFAULT '',
      ngo_code VARCHAR(191) DEFAULT '',
      dognet_order_id VARCHAR(191) NOT NULL,
      dognet_program_id VARCHAR(191) DEFAULT '',
      dognet_click_id VARCHAR(191) DEFAULT '',
      amount DECIMAL(12,2) DEFAULT 0,
      currency VARCHAR(16) DEFAULT 'HUF',
      state ENUM('pending','approved','rejected') DEFAULT 'approved',
      created_at DATETIME NOT NULL,
      UNIQUE KEY uniq_order (promo_slug, dognet_order_id, dognet_program_id)
    ) $charset;";

    $sql2 = "CREATE TABLE IF NOT EXISTS $totals (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      promo_slug VARCHAR(191) NOT NULL,
      shop_slug VARCHAR(191) NOT NULL,
      ngo_code VARCHAR(191) NOT NULL,
      total_approved DECIMAL(14,2) DEFAULT 0,
      orders_approved INT DEFAULT 0,
      total_pending DECIMAL(14,2) DEFAULT 0,
      orders_pending INT DEFAULT 0,
      updated_at DATETIME NOT NULL,
      UNIQUE KEY uniq_pair (promo_slug, shop_slug, ngo_code)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
});

/* =======================
 * 3) SEGÉD – idők, safe-int
 * ======================= */

function impactshop_now_ts() {
    try { $tz = new DateTimeZone(IMPACTSHOP_TIMEZONE); } catch (Exception $e) { $tz = new DateTimeZone('UTC'); }
    return (new DateTime('now', $tz))->getTimestamp();
}
function impactshop_ts($str) {
    try { $tz = new DateTimeZone(IMPACTSHOP_TIMEZONE); } catch (Exception $e) { $tz = new DateTimeZone('UTC'); }
    return (new DateTime($str, $tz))->getTimestamp();
}
function impactshop_safe_int($v, $min = 0) {
    $n = intval($v); return ($n < $min) ? $min : $n;
}
function impactshop_hash_uid($email, $order_id) {
    return hash('sha256', $email.'|'.$order_id.'|'.IMPACTSHOP_PROMO_SLUG);
}

/* =======================
 * 4) DOR – Dognet lekérés (helykitöltő, API-hoz igazítandó)
 * ======================= */

function impactshop_dognet_fetch_conversions($since_ts, $until_ts) {
    // Itt illeszd a valós Dognet 2.0 riport végponthoz (merchant/program, order id, status, amount, currency, d1, refid/clickid).
    // Átmeneti dummy szerkezet, hogy a többi kód működjön:
    // return tömb: minden elem asszociatív:
    //  - order_id, program_id, merchant_slug, status ('approved'|'pending'|'rejected'),
    //  - amount, currency, d1 (ngo_code), click_id, email (ha van), created_at (ts)
    return []; // <-- illeszd be az API hívást, auth: DOGNET_API_TOKEN fejléccel, stb.
}

/* =======================
 * 5) AGGREGÁLÁS & SORSJEGY
 * ======================= */

function impactshop_upsert_totals($promo, $shop, $ngo, $amount, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_totals';
    $now = date('Y-m-d H:i:s', impactshop_now_ts());

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE promo_slug=%s AND shop_slug=%s AND ngo_code=%s", $promo, $shop, $ngo));
    if (!$row) {
        $wpdb->insert($table, [
            'promo_slug' => $promo,
            'shop_slug'  => $shop,
            'ngo_code'   => $ngo,
            'total_approved' => ($status==='approved') ? $amount : 0,
            'orders_approved'=> ($status==='approved') ? 1 : 0,
            'total_pending'  => ($status==='pending')  ? $amount : 0,
            'orders_pending' => ($status==='pending')  ? 1 : 0,
            'updated_at'     => $now,
        ]);
    } else {
        if ($status==='approved') {
            $wpdb->update($table, [
                'total_approved' => (float)$row->total_approved + (float)$amount,
                'orders_approved'=> (int)$row->orders_approved + 1,
                'updated_at'     => $now
            ], ['id'=>$row->id]);
        } elseif ($status==='pending') {
            $wpdb->update($table, [
                'total_pending'  => (float)$row->total_pending + (float)$amount,
                'orders_pending' => (int)$row->orders_pending + 1,
                'updated_at'     => $now
            ], ['id'=>$row->id]);
        }
    }
}

function impactshop_tickets_by_amount($amount) {
    // Ha értékalapú extra esélyt szeretnél, itt add hozzá.
    // Pl. 1 sorsjegy alapon + minden teljes 20 000 Ft után +1:
    // $extra = floor(max(0, $amount) / 20000);
    // return IMPACTSHOP_TICKETS_PER_APPROVED_ORDER + $extra;
    return IMPACTSHOP_TICKETS_PER_APPROVED_ORDER;
}

function impactshop_store_ticket($promo, $email, $shop, $ngo, $order_id, $program_id, $click_id, $amount, $currency, $status) {
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_raffle_entries';
    $uid = impactshop_hash_uid($email ?: 'anon', $order_id);
    $now = date('Y-m-d H:i:s', impactshop_now_ts());

    // Csak approved rendelésből képezünk sorsjegyet (B-verzió védett beállítás)
    if ($status !== 'approved') return 0;

    // Dedup: ha már van ilyen order → ne szúrjuk újra
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE promo_slug=%s AND dognet_order_id=%s AND dognet_program_id=%s",
        $promo, $order_id, $program_id
    ));
    if ($exists) return 0;

    $tickets = impactshop_tickets_by_amount($amount);
    for ($i=0; $i<$tickets; $i++) {
        $wpdb->insert($table, [
            'promo_slug'        => $promo,
            'entry_uid'         => $uid,
            'email'             => $email,
            'shop_slug'         => $shop,
            'ngo_code'          => $ngo,
            'dognet_order_id'   => $order_id,
            'dognet_program_id' => $program_id,
            'dognet_click_id'   => $click_id,
            'amount'            => $amount,
            'currency'          => $currency ?: 'HUF',
            'state'             => 'approved',
            'created_at'        => $now,
        ]);
    }
    return $tickets;
}

/* =======================
 * 6) CRON – óránkénti szinkron
 * ======================= */

add_action('impactshop_raffle_cron', function () {
    $since_ts = impactshop_ts(IMPACTSHOP_RAFFLE_START);
    $until_ts = impactshop_ts(IMPACTSHOP_RAFFLE_END);
    $now = impactshop_now_ts();
    if ($now < $since_ts) return; // még nem indult

    $until = min($now, $until_ts);

    $convs = impactshop_dognet_fetch_conversions($since_ts, $until);
    if (!is_array($convs) || empty($convs)) return;

    foreach ($convs as $c) {
        $order_id   = (string)($c['order_id'] ?? '');
        $program_id = (string)($c['program_id'] ?? '');
        $shop       = (string)($c['merchant_slug'] ?? '');
        $status     = strtolower((string)($c['status'] ?? ''));
        $amount     = (float)($c['amount'] ?? 0);
        $currency   = (string)($c['currency'] ?? 'HUF');
        $ngo        = (string)($c['d1'] ?? '');
        $click_id   = (string)($c['click_id'] ?? '');
        $email      = (string)($c['email'] ?? '');

        if ($order_id === '' || $program_id === '' || $shop === '') continue;

        // Összesítés frissítése (mindig)
        if (in_array($status, ['approved','pending'], true)) {
            impactshop_upsert_totals(IMPACTSHOP_PROMO_SLUG, $shop, $ngo, $amount, $status);
        }

        // Sorsjegy csak approved esetben
        if ($status === 'approved') {
            impactshop_store_ticket(IMPACTSHOP_PROMO_SLUG, $email, $shop, $ngo, $order_id, $program_id, $click_id, $amount, $currency, $status);
        }
    }
});

// Cron ütemezés (óránként)
add_action('init', function () {
    if (!wp_next_scheduled('impactshop_raffle_cron')) {
        wp_schedule_event(time() + 60, 'hourly', 'impactshop_raffle_cron');
    }
});

/* =======================
 * 7) SHORTCODE-OK
 * ======================= */

add_shortcode('impactshop_raffle_count', function ($atts) {
    $a = shortcode_atts(['promo' => IMPACTSHOP_PROMO_SLUG], $atts);
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_raffle_entries';
    $n = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE promo_slug=%s", $a['promo']));
    return esc_html(number_format_i18n($n)) . ' sorsjegy';
});

add_shortcode('impactshop_raffle_totals', function ($atts) {
    $a = shortcode_atts(['promo' => IMPACTSHOP_PROMO_SLUG, 'state' => 'approved', 'limit' => 10], $atts);
    global $wpdb;
    $t = $wpdb->prefix . 'impactshop_totals';
    $state = $a['state'] === 'pending' ? 'pending' : 'approved';
    $limit = impactshop_safe_int($a['limit'], 1);

    if ($state === 'approved') {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT shop_slug, ngo_code, total_approved AS total, orders_approved AS orders FROM $t WHERE promo_slug=%s ORDER BY total_approved DESC LIMIT %d", $a['promo'], $limit));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT shop_slug, ngo_code, total_pending AS total, orders_pending AS orders FROM $t WHERE promo_slug=%s ORDER BY total_pending DESC LIMIT %d", $a['promo'], $limit));
    }
    if (!$rows) return 'Nincs adat.';
    $out = '<ul class="impactshop-totals">';
    foreach ($rows as $r) {
        $out .= '<li><strong>'.esc_html($r->shop_slug).'</strong> × '.esc_html($r->ngo_code).' — '.esc_html(number_format_i18n($r->total, 0)).' '.$state.' (' . esc_html($r->orders) . ' rendelés)</li>';
    }
    $out .= '</ul>';
    return $out;
});

/* =======================
 * 8) SORSOLÁS SEGÉD (adminból hívd)
 * ======================= */

function impactshop_draw_winner($promo_slug, $public_seed) {
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_raffle_entries';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, entry_uid, email, shop_slug, ngo_code, dognet_order_id FROM $table WHERE promo_slug=%s ORDER BY id ASC", $promo_slug), ARRAY_A);
    if (!$rows) return null;

    // Nyilvános seed → reprodukálható eredmény
    $seed = hash('sha256', $promo_slug.'|'.$public_seed.'|'.count($rows));
    // Seedből determinista index (nem piszkáljuk a globális RNG-t)
    $idx = hexdec(substr($seed, 0, 8)) % count($rows);
    return $rows[$idx];
}
Hogyan kapcsolódik ez a mostani rendszeredhez?
URL-forma marad: /go/{shop}?d1={ngo_code}&amb={amb}&src=impactshop&utm_*
– a szinkron a Dognet riportból olvassa vissza a merchant/program-ot, az order id-t, a státuszt és a d1-et (NGO).
CSV/Sheets-es partnerlista és XML feed: érintetlenül működik tovább; ugyanebből a (shop, ngo) világból épül a toplista is.
Toplisták / összesítések: a fenti wp_impactshop_totals pont erre készül (approved/pending bontás).
Mire kérek tőled két dolgot most?
Véglegesítsük a promó meta-adatokat: név/slug, időszak, nyeremény, szabályzat URL.
Dognet 2.0 API végpont + auth – amint megadod az endpoint nevét és a mezőket (order id, status, d1, program/merchant, amount, currency, click id), megírom a impactshop_dognet_fetch_conversions() hívást azonnal a te fiókodhoz.
Ha szeretnéd, adok egy apró admin oldalt is a sorsolás gombhoz és a nyertes exportjához — de ezzel a gerinccel már a lényeg biztonságban van.
A ChatGPT ezt mondta:

Gondolkozás
Szuper: akkor haladjunk a B-verzióval (csak vásárlók vehetnek részt), és ültessük rá a mostani Impact Shop rendszeredre. Az előző kódtörzsed már majdnem kész: hiányzik a Dognet 2.0 lekérő, meg jól jön egy pici admin felület (sorsolás gomb + export). Alább megadom a betéttéglákat, amiket egy az egyben be tudsz illeszteni a már elküldött snippetbe.
1) Dognet 2.0 lekérő függvény – gyakorlati minta
Ezt a függvényt cseréld le a snippetben (a komment is jelzi, hol van). A minta GET végponttal számol és JSON választ vár. Ha nálad POST/CSV, csak a wp_remote_* részt kell átírni. A kulcs: a kimenet mezőnevei legyenek az itt használtak: order_id, program_id, merchant_slug, status, amount, currency, d1, click_id, email, created_at.
function impactshop_dognet_fetch_conversions($since_ts, $until_ts) {
    // Időablak ISO8601
    $since = gmdate('c', $since_ts);
    $



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
