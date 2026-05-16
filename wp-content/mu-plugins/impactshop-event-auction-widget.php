<?php
/**
 * Plugin Name: ImpactShop Event Auction Widget
 * Description: Embeddable auction widget scaffold for the Jövőnk Vize x Miele gála flow.
 * Version: 0.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IMPACTSHOP_EVENT_AUCTION_VERSION', '0.4.1');
define('IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION', '0.3.0');
define('IMPACTSHOP_EVENT_AUCTION_SESSION_TTL', 30 * MINUTE_IN_SECONDS);
define('IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL', 4 * HOUR_IN_SECONDS);

add_action('init', 'impactshop_event_auction_ensure_schema', 5);
add_action('init', 'impactshop_event_auction_cron_schedule', 10);
add_action('rest_api_init', 'impactshop_event_auction_register_routes');
add_action('template_redirect', 'impactshop_event_auction_query_api_dispatch', 0);
add_action('template_redirect', 'impactshop_event_auction_embed_page_dispatch', 1);
add_filter('allowed_http_origins', 'impactshop_event_auction_allowed_http_origins');
add_filter('allowed_redirect_hosts', 'impactshop_event_auction_allowed_redirect_hosts');
add_shortcode('impact_event_auction_widget', 'impactshop_event_auction_shortcode');
add_filter('cron_schedules', 'impactshop_event_auction_cron_intervals');
add_action('impactshop_event_auction_auto_close', 'impactshop_event_auction_cron_auto_close_handler');

function impactshop_event_auction_campaigns(): array
{
    $baseCampaign = function_exists('impactshop_event_donation_get_campaign')
        ? impactshop_event_donation_get_campaign('jovonkvize-2026')
        : null;

    $allowedOrigins = [
        'https://jovonkvize.hu',
        'https://www.jovonkvize.hu',
        'https://wowapartments.hu',
        'https://www.wowapartments.hu',
        'https://app.sharity.hu',
        'https://www.app.sharity.hu',
    ];

    if (is_array($baseCampaign) && !empty($baseCampaign['allowed_origins']) && is_array($baseCampaign['allowed_origins'])) {
        $allowedOrigins = array_values(array_unique(array_map(
            static fn($origin) => rtrim(esc_url_raw((string) $origin), '/'),
            $baseCampaign['allowed_origins']
        )));
    }

    $theme = [
        'bg_start' => '#060d2a',
        'bg_end' => '#0d2f77',
        'accent' => '#c69a5f',
        'accent_2' => '#f4ddae',
        'text' => '#f8f4ea',
    ];

    if (is_array($baseCampaign) && !empty($baseCampaign['theme']) && is_array($baseCampaign['theme'])) {
        $theme = array_merge($theme, $baseCampaign['theme']);
    }

    $campaigns = [
        'jovonkvize-2026' => [
            'slug' => 'jovonkvize-2026',
            'auction_slug' => 'jovonkvize-miele-gala-2026',
            'title' => 'Jövőnk Vize gála aukció',
            'subtitle' => 'Miele műtárgyak és különleges felajánlások',
            'beneficiary_name' => 'Sharity Adományszervező Alapítvány',
            'description' => 'Additív aukciós modul a Jövőnk Vize x Miele gála lane-hez. A bidder regisztráció, a licit write lane, az admin close és a winner-payment backend már bekötve, az admin UI és a kommunikációs lane külön fázisban kötődik be.',
            'currency' => 'huf',
            'goal_amount' => 15000000,
            'share_url' => 'https://jovonkvize.hu',
            'hero_url' => 'https://jovonkvize.hu',
            'success_return_url' => 'https://jovonkvize.hu',
            'cancel_return_url' => 'https://jovonkvize.hu',
            'allowed_origins' => $allowedOrigins,
            'theme' => $theme,
            'lots' => impactshop_event_auction_default_lots(),
        // Aukció zárásának UTC időpontja (ISO 8601). Admin_close manuálisan is lezárhatja.
        // TODO: pontos gálanaphoz igazítani!
        'auction_end_time' => '2026-05-05T11:30:00Z', // 2026-05-05 13:30 Budapest (CEST = UTC+2) — TEST CLOSE
        // Snipe protection: ha az utolsó N másodpercben érkezik licit → meghosszabbítás M másodperccel
        'snipe_window_seconds' => 120,
        'snipe_extend_seconds' => 120,
        ],
    ];

    return apply_filters('impactshop_event_auction_campaigns', $campaigns);
}

function impactshop_event_auction_upload_image_url(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '') {
        return '';
    }

    $uploads = wp_upload_dir();
    $baseDir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
    $baseUrl = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';

    if ($baseDir === '' || $baseUrl === '') {
        return '';
    }

    $candidate = wp_normalize_path($baseDir . '/' . $relativePath);
    if (!file_exists($candidate)) {
        return '';
    }

    return trailingslashit($baseUrl) . str_replace('%2F', '/', rawurlencode($relativePath));
}

function impactshop_event_auction_placeholder_image_url(array $lot): string
{
    $title = trim((string) ($lot['item_title'] ?? 'Aukciós tétel'));
    $artist = trim((string) ($lot['artist_name'] ?? 'Jövőnk Vize'));
    $medium = trim((string) ($lot['medium'] ?? 'Műtárgy'));
    $dimensions = trim((string) ($lot['dimensions'] ?? ''));
    $lotNumber = (int) ($lot['lot_number'] ?? 0);
    $lotLabel = $lotNumber > 0 ? 'LOT ' . $lotNumber : 'JÖVŐNK VIZE';

    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" role="img" aria-label="%s"><defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%%" stop-color="#08122f"/><stop offset="100%%" stop-color="#0d2f77"/></linearGradient><linearGradient id="glow" x1="0" y1="0" x2="1" y2="1"><stop offset="0%%" stop-color="#f4ddae" stop-opacity="0.28"/><stop offset="100%%" stop-color="#c69a5f" stop-opacity="0.05"/></linearGradient></defs><rect width="800" height="1000" fill="url(#bg)"/><circle cx="640" cy="160" r="220" fill="url(#glow)"/><rect x="56" y="56" width="688" height="888" rx="34" fill="none" stroke="rgba(244,221,174,.42)" stroke-width="2"/><text x="72" y="120" fill="#f4ddae" font-family="Arial, sans-serif" font-size="32" font-weight="700" letter-spacing="3">%s</text><text x="72" y="210" fill="#f8f4ea" font-family="Georgia, serif" font-size="68" font-weight="700">%s</text><text x="72" y="280" fill="#dfe6f5" font-family="Arial, sans-serif" font-size="36">%s</text><text x="72" y="832" fill="#f4ddae" font-family="Arial, sans-serif" font-size="28" letter-spacing="2">%s</text><text x="72" y="878" fill="#f8f4ea" font-family="Arial, sans-serif" font-size="26">%s</text><text x="72" y="920" fill="#dfe6f5" font-family="Arial, sans-serif" font-size="22">Képforrás feltöltés alatt</text></svg>',
        esc_attr($artist . ' - ' . $title),
        esc_html($lotLabel),
        esc_html($title),
        esc_html($artist),
        esc_html($medium),
        esc_html($dimensions !== '' ? $dimensions : 'JVK aukciós tétel')
    );

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function impactshop_event_auction_resolve_lot_image(array $lot, string $relativePath): array
{
    $resolvedUrl = impactshop_event_auction_upload_image_url($relativePath);
    $lot['image_url'] = $resolvedUrl !== '' ? $resolvedUrl : impactshop_event_auction_placeholder_image_url($lot);
    $lot['image_is_placeholder'] = $resolvedUrl === '';
    $lot['image_path'] = $relativePath;

    return $lot;
}

function impactshop_event_auction_default_lots(): array
{
    $lots = [
        [
            'item_slug' => 'szentpeteri-toth-marta-forgiveness',
            'lot_number' => 1,
            'category' => 'artwork',
            'artist_name' => 'Szentpéteri Tóth Márta',
            'item_title' => 'Forgiveness',
            'description_short' => 'Akril festmény, 70x100 cm.',
            'description_long' => 'Scaffold lot. A végleges publikus leírás, művész-bemutatás és asset mapping külön tartalomkörben véglegesítendő.',
            'dimensions' => '70x100 cm',
            'medium' => 'Akril',
            'starting_bid' => 150000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/toth-marta.jpg',
        ],
        [
            'item_slug' => 'simon-m-veronika-kek-sugarzas',
            'lot_number' => 2,
            'category' => 'artwork',
            'artist_name' => 'Simon M. Veronika',
            'item_title' => 'Kék sugárzás',
            'description_short' => 'Festmény, 70x50 cm.',
            'description_long' => 'Scaffold lot. A végleges publikus leírás és a kép asset ellenőrzése külön körben véglegesítendő.',
            'dimensions' => '70x50 cm',
            'medium' => 'Festmény',
            'starting_bid' => 185000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/kek-sugarzas.jpg',
        ],
        [
            'item_slug' => 'tarcsi-daniel-part-iii',
            'lot_number' => 3,
            'category' => 'artwork',
            'artist_name' => 'Tarcsi Dániel',
            'item_title' => 'Part III.',
            'description_short' => 'Akril, vásznon, 33x88 cm (keretezett méret: 32x102x3 cm).',
            'description_long' => 'Scaffold lot. A lot státusz és a licitlépcső logika végleges validációja a backend implementációs fázisban kötendő be.',
            'dimensions' => '33x88 cm (keretezett méret: 32x102x3 cm)',
            'medium' => 'Akril, vásznon',
            'starting_bid' => 450000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/part-iii.jpg',
        ],
        [
            'item_slug' => 'ghyczy-gyorgy-elindulok-a-csillagokhoz',
            'lot_number' => 4,
            'category' => 'artwork',
            'artist_name' => 'Ghyczy György',
            'item_title' => 'Elindulok a csillagokhoz',
            'description_short' => 'Festmény, 90x90 cm.',
            'description_long' => 'Scaffold lot. A végleges leírás és az asset filename-normalizálás külön tartalmi körben készül el.',
            'dimensions' => '90x90 cm',
            'medium' => 'Festmény',
            'starting_bid' => 200000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/elindulok-a-csillagokhoz.jpg',
        ],
        [
            'item_slug' => 'szabo-anna-cseresznye',
            'lot_number' => 5,
            'category' => 'artwork',
            'artist_name' => 'Szabó Anna',
            'item_title' => 'Cseresznyék',
            'description_short' => 'Festmény, 50x40 cm.',
            'description_long' => 'Scaffold lot. A végleges publikus szöveg és képváltozat külön véglegesítendő.',
            'dimensions' => '50x40 cm',
            'medium' => 'Festmény',
            'starting_bid' => 60000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/szabo-anna-cseresznye.jpg',
        ],
        [
            'item_slug' => 'szabo-anna-a-no-turkizben',
            'lot_number' => 6,
            'category' => 'artwork',
            'artist_name' => 'Szabó Anna',
            'item_title' => 'A nő türkizben',
            'description_short' => 'Festmény, 50x70 cm.',
            'description_long' => 'Scaffold lot. A végleges publikus szöveg és képforrás véglegesítendő.',
            'dimensions' => '50x70 cm',
            'medium' => 'Festmény',
            'starting_bid' => 80000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/szabo-anna-no-turkizben.jpg',
        ],
        [
            'item_slug' => 'dimenzio-ingatlan-sirocco-elmenyvitorlazas',
            'lot_number' => 7,
            'category' => 'experience',
            'artist_name' => 'Dimenzió Ingatlan Kft.',
            'item_title' => 'Sirocco élményvitorlázás 10 főre',
            'description_short' => 'Különleges élményajánlat 10 főre.',
            'description_long' => 'Scaffold lot. A beváltási feltételek, dátumok és kommunikációs szöveg véglegesítése külön üzleti körben szükséges.',
            'dimensions' => '',
            'medium' => 'Élményajánlat',
            'starting_bid' => 150000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/sirocco-elmenyvitorlazas.jpg',
        ],
        [
            'item_slug' => 'n28-wine-kitchen-uzleti-ebed',
            'lot_number' => 8,
            'category' => 'experience',
            'artist_name' => 'N28 Wine and Kitchen',
            'item_title' => 'Exkluzív 2 fős üzleti ebéd utalvány',
            'description_short' => 'Elegáns ajándékutalvány, amely egy 2 fős üzleti ebédre váltható be az N28 Wine and Kitchen étteremben.',
            'description_long' => 'Egy exkluzív gasztronómiai élmény is kalapács alá kerül a „Jövőnk Vize" jótékonysági gálán: az N28 Wine and Kitchen elegáns ajándékutalványa, amely egy 2 fős üzleti ebédre váltható be. Tökéletes választás egy különleges találkozóra vagy prémium üzleti ebédre – miközben a licittel a gyermekintézmények tiszta ivóvízhez jutását is támogatják a vendégek.',
            'dimensions' => '',
            'medium' => 'Élményajánlat',
            'starting_bid' => 50000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/n28-wine-kitchen-uzleti-ebed.jpg',
        ],
        [
            'item_slug' => 'dedikalt-meccslabda-magyarorszag-argentina-2005',
            'lot_number' => 9,
            'category' => 'collectible',
            'artist_name' => 'Gyűjtői relikvia',
            'item_title' => 'Dedikált meccslabda – Magyarország vs. Argentína 2005',
            'description_short' => 'Ritka gyűjtői darab: a 2005. augusztus 17-i Magyarország–Argentína mérkőzés dedikált meccslabdája.',
            'description_long' => 'Egy igazán egyedi és megismételhetetlen darab kerül most kalapács alá: egy hivatalos mérkőzéslabda, amelyet a 2005. augusztus 17-én, a Puskás Ferenc Stadionban megrendezett Magyarország–Argentína válogatott mérkőzés alkalmával írtak alá. A labdán több játékos eredeti kézjegye található – mindkét válogatottból –, így egyszerre hordozza egy különleges futballpillanat emlékét és a sporttörténelem lenyomatát.',
            'dimensions' => '',
            'medium' => 'Sportrelikvia',
            'starting_bid' => 250000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/meccslabda-magyarorszag-argentina-2005.jpg',
        ],
        [
            'item_slug' => 'balla-gemma-ecoprint-selyemsal-nyaklac',
            'lot_number' => 10,
            'category' => 'handcraft',
            'artist_name' => 'Balla Gemma',
            'item_title' => 'Ecoprint selyemsál és selyemgubós nyaklánc',
            'description_short' => '45×180 cm ecoprint selyemsál és selyemgubós nyaklánc – kézzel készített, egyedi természetes alkotás.',
            'description_long' => 'Balla Gemma alkotóként és kézműves oktatóként hosszú évek óta a kreativitás, az igényes kézzel készült tárgyak és a hagyományos értékek elkötelezett képviselője. Munkáiban a természetközeliség, az egyediség és a játékosság harmonikusan találkozik, miközben fontos számára az alkotás örömének továbbadása is. Számos közösségi és művészeti kezdeményezés aktív résztvevője, ahol inspiráló személyiségével és nyitottságával is hozzájárul az élményekhez. Az aukció támogatására felajánlott tárgyával most ő is egy jó ügy mellé állt. A tételcsomag tartalmaz egy 45×180 cm méretű ecoprint selyemsálat és egy selyemgubós nyakláncot – mindkettő egyedi, kézzel készített darab.',
            'dimensions' => '45×180 cm (sál)',
            'medium' => 'Ecoprint selyem, selyemgubó',
            'starting_bid' => 25000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/balla-gemma-ecoprint-selyemsal.jpg',
        ],
        [
            'item_slug' => 'kocsis-katica-weiler-peter-dedikalt-konyv',
            'lot_number' => 11,
            'category' => 'collectible',
            'artist_name' => 'Kocsis Katica / Weiler Péter',
            'item_title' => 'Kocsis Katica: Weiler Péter – dedikált könyv',
            'description_short' => 'Dedikált könyv – Weiler Péter Kocsis Katicától, személyesen Weiler Péter által dedikálva.',
            'description_long' => 'A „Jövőnk Vize" jótékonysági gála aukciójára egy igazán különleges, személyes értéket képviselő licittárgy érkezik: Kocsis Katica Weiler Péter című kötete, amelyet személyesen Weiler Péter dedikált. A kedves licitáló egy olyan különleges könyvet tarthat majd a kezében, amely egyszerre őszinte életrajz, személyes vallomás és művészeti lenyomat. Kocsis Katica higgadt, érzékeny és mélyre ásó könyve a Weiler-univerzum belső világába enged betekintést: egyszerre életút, monográfia, album és inspiráló történet. A kötet különlegessége, hogy nem kíván klasszikus műfaji keretek közé illeszkedni – ahogyan maga Weiler Péter sem. Képzőművész és üzletember, aki bátor döntésekkel, merész fordulatokkal és inspiráló élethelyzetekkel teli pályát járt be. A sorokból egyszerre árad mértékletesség, őszinteség és az alkotói szabadság. Ezt a példányt személyesen Weiler Péter dedikálta a gála számára, így nemcsak egy értékes kulturális kötet, hanem egyedi, gyűjtői darab is. A licittel ráadásul a vendégek egy nemes ügyet támogatnak: hozzájárulnak ahhoz, hogy gyermekintézmények biztonságos, tiszta ivóvízhez jussanak.',
            'dimensions' => '',
            'medium' => 'Könyv',
            'starting_bid' => 20000,
            'min_increment' => 5000,
            'current_bid' => null,
            'current_winner_bidder_id' => null,
            'status' => 'live',
            'image_path' => 'jovonkvize-auction/2026/kocsis-katica-weiler-peter-konyv.jpg',
        ],
    ];

    foreach ($lots as $index => $lot) {
        $lots[$index] = impactshop_event_auction_resolve_lot_image($lot, (string) ($lot['image_path'] ?? ''));
    }

    return $lots;
}

function impactshop_event_auction_get_campaign(string $slug): ?array
{
    $slug = sanitize_title($slug);
    $campaigns = impactshop_event_auction_campaigns();
    return isset($campaigns[$slug]) && is_array($campaigns[$slug]) ? $campaigns[$slug] : null;
}

function impactshop_event_auction_client_ip(): string
{
    return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function impactshop_event_auction_request_origin(): string
{
    return rtrim(esc_url_raw((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
}

function impactshop_event_auction_origin_allowed(array $campaign): bool
{
    $origin = impactshop_event_auction_request_origin();
    $allowed = [];

    foreach ((array) ($campaign['allowed_origins'] ?? []) as $item) {
        $item = rtrim(esc_url_raw((string) $item), '/');
        if ($item !== '') {
            $allowed[] = $item;
        }
    }

    if ($origin !== '') {
        return in_array($origin, $allowed, true);
    }

    $referer = esc_url_raw((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return false;
    }

    $refererHost = strtolower((string) wp_parse_url($referer, PHP_URL_HOST));
    foreach ($allowed as $allowedOrigin) {
        $allowedHost = strtolower((string) wp_parse_url($allowedOrigin, PHP_URL_HOST));
        if ($allowedHost !== '' && $allowedHost === $refererHost) {
            return true;
        }
    }

    return false;
}

function impactshop_event_auction_send_cors_headers(array $campaign): void
{
    $origin = impactshop_event_auction_request_origin();
    if ($origin === '' || !impactshop_event_auction_origin_allowed($campaign)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

function impactshop_event_auction_allowed_http_origins(array $origins): array
{
    $extra = [];
    foreach (impactshop_event_auction_campaigns() as $campaign) {
        foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
            $origin = esc_url_raw((string) $origin);
            if ($origin !== '') {
                $extra[] = rtrim($origin, '/');
            }
        }
    }

    return array_values(array_unique(array_merge($origins, $extra)));
}

function impactshop_event_auction_allowed_redirect_hosts(array $hosts): array
{
    foreach (impactshop_event_auction_campaigns() as $campaign) {
        foreach ((array) ($campaign['allowed_origins'] ?? []) as $origin) {
            $host = wp_parse_url((string) $origin, PHP_URL_HOST);
            if ($host) {
                $hosts[] = (string) $host;
            }
        }
    }

    return array_values(array_unique($hosts));
}

function impactshop_event_auction_rate_limit(string $scope, int $limit, int $window): array
{
    $ip = impactshop_event_auction_client_ip();
    $key = 'impactshop_event_auction_rl_' . md5($scope . '|' . $ip);
    $state = get_transient($key);

    if (!is_array($state)) {
        $state = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    if (($state['reset'] ?? 0) <= time()) {
        $state = [
            'count' => 0,
            'reset' => time() + $window,
        ];
    }

    $state['count'] = (int) ($state['count'] ?? 0) + 1;
    set_transient($key, $state, max(1, (int) $window));

    return [
        'allowed' => $state['count'] <= $limit,
        'remaining' => max(0, $limit - $state['count']),
        'reset' => (int) ($state['reset'] ?? (time() + $window)),
    ];
}

function impactshop_event_auction_extract_payload(WP_REST_Request $request): array
{
    $params = (array) $request->get_json_params();
    if ($params) {
        return $params;
    }

    $body = (string) $request->get_body();
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return (array) $request->get_params();
}

function impactshop_event_auction_transient_key(string $prefix, string $token): string
{
    return 'impactshop_event_auction_' . $prefix . '_' . md5($token);
}

function impactshop_event_auction_issue_session_token(array $campaign): array
{
    $token = wp_generate_uuid4();
    $expiresAt = time() + IMPACTSHOP_EVENT_AUCTION_SESSION_TTL;
    $payload = [
        'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        'origin' => impactshop_event_auction_request_origin(),
        'ua_hash' => md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'expires_at' => $expiresAt,
    ];

    set_transient(
        impactshop_event_auction_transient_key('session', $token),
        $payload,
        IMPACTSHOP_EVENT_AUCTION_SESSION_TTL
    );

    return [
        'token' => $token,
        'expires_at' => gmdate('c', $expiresAt),
    ];
}

function impactshop_event_auction_verify_session_token(string $token, array $campaign): bool
{
    if ($token === '') {
        return false;
    }

    $stored = get_transient(impactshop_event_auction_transient_key('session', $token));
    if (!is_array($stored)) {
        return false;
    }

    if ((string) ($stored['campaign_slug'] ?? '') !== (string) ($campaign['slug'] ?? '')) {
        return false;
    }

    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return false;
    }

    $storedOrigin = (string) ($stored['origin'] ?? '');
    $currentOrigin = impactshop_event_auction_request_origin();
    if ($storedOrigin !== '' && $currentOrigin !== '' && $storedOrigin !== $currentOrigin) {
        return false;
    }

    $uaHash = md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return hash_equals((string) ($stored['ua_hash'] ?? ''), $uaHash);
}

function impactshop_event_auction_issue_bidder_token(array $campaign, string $bidderUuid): array
{
    $token = wp_generate_uuid4();
    $expiresAt = time() + IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL;
    $payload = [
        'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        'bidder_uuid' => $bidderUuid,
        'origin' => impactshop_event_auction_request_origin(),
        'ua_hash' => md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'expires_at' => $expiresAt,
    ];

    set_transient(
        impactshop_event_auction_transient_key('bidder', $token),
        $payload,
        IMPACTSHOP_EVENT_AUCTION_BIDDER_TTL
    );

    return [
        'token' => $token,
        'expires_at' => gmdate('c', $expiresAt),
    ];
}

function impactshop_event_auction_verify_bidder_token(string $token, array $campaign): ?array
{
    if ($token === '') {
        return null;
    }

    $stored = get_transient(impactshop_event_auction_transient_key('bidder', $token));
    if (!is_array($stored)) {
        return null;
    }

    if ((string) ($stored['campaign_slug'] ?? '') !== (string) ($campaign['slug'] ?? '')) {
        return null;
    }

    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return null;
    }

    $storedOrigin = (string) ($stored['origin'] ?? '');
    $currentOrigin = impactshop_event_auction_request_origin();
    if ($storedOrigin !== '' && $currentOrigin !== '' && $storedOrigin !== $currentOrigin) {
        return null;
    }

    $uaHash = md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!hash_equals((string) ($stored['ua_hash'] ?? ''), $uaHash)) {
        return null;
    }

    return $stored;
}

function impactshop_event_auction_log_event(string $campaignSlug, string $itemSlug, string $eventType, string $actor, array $payload = []): void
{
    global $wpdb;
    $table = impactshop_event_auction_events_table_name();

    $wpdb->insert(
        $table,
        [
            'campaign_slug' => $campaignSlug,
            'item_slug' => $itemSlug,
            'event_type' => $eventType,
            'actor' => $actor,
            'origin' => impactshop_event_auction_request_origin(),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => current_time('mysql', true),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );
}

function impactshop_event_auction_parse_amount($value): int
{
    if (is_numeric($value)) {
        return max(0, (int) round((float) $value, 0));
    }

    $raw = preg_replace('/[^0-9]/', '', (string) $value);
    return $raw === '' ? 0 : (int) $raw;
}

function impactshop_event_auction_find_lot(array $campaign, string $itemSlug): ?array
{
    foreach ((array) ($campaign['lots'] ?? []) as $lot) {
        if (sanitize_title((string) ($lot['item_slug'] ?? '')) === $itemSlug) {
            return (array) $lot;
        }
    }

    return null;
}

function impactshop_event_auction_current_bid_state(string $campaignSlug, string $itemSlug): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
                        "SELECT bid_uuid, bidder_uuid, bid_amount, status, stripe_session_id, stripe_payment_intent, stripe_checkout_url, return_url, created_at
             FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
               AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bid_by_uuid(string $bidUuid): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bid_uuid = %s LIMIT 1",
            $bidUuid
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bid_by_session_id(string $sessionId): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
            $sessionId
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_get_bidder(string $bidderUuid): ?array
{
    global $wpdb;
    $table = impactshop_event_auction_bidders_table_name();

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bidder_uuid = %s LIMIT 1",
            $bidderUuid
        ),
        ARRAY_A
    );
}

function impactshop_event_auction_effective_lot_status(array $lot, ?array $bidState): string
{
    $fallback = sanitize_key((string) ($lot['status'] ?? 'draft'));
    $campaignSlug = sanitize_title((string) ($lot['campaign_slug'] ?? 'jovonkvize-2026'));
    $itemSlug = sanitize_title((string) ($lot['item_slug'] ?? ''));

    // Lejart lot nyertes licit nelkul: legyen automatikusan lezart (unsold), ne maradjon live.
    if (!$bidState && in_array($fallback, ['live', 'closing'], true) && $itemSlug !== '') {
        $campaign = impactshop_event_auction_get_campaign($campaignSlug);
        if (is_array($campaign)) {
            $endIso = impactshop_event_auction_lot_end_time($campaignSlug, $itemSlug, $campaign);
            if ($endIso !== '') {
                $endTs = strtotime($endIso);
                if ($endTs !== false && $endTs > 0 && $endTs <= time()) {
                    return 'closed_unsold';
                }
            }
        }
    }

    if (!$bidState) {
        return $fallback;
    }

    $state = sanitize_key((string) ($bidState['status'] ?? ''));
    if ($state === 'winning') {
        return in_array($fallback, ['closing', 'draft'], true) ? $fallback : 'live';
    }

    if (in_array($state, ['closed', 'payment_pending', 'paid'], true)) {
        return $state;
    }

    return $fallback;
}

function impactshop_event_auction_display_label(?int $currentBid, string $status): string
{
    if ($status === 'closed_unsold') {
        return 'Lejart tétel (nyertes licit nelkul)';
    }

    if ($currentBid === null) {
        return 'Kikialtasi ar';
    }

    if ($status === 'paid') {
        return 'Kifizetett leutesi ar';
    }

    if (in_array($status, ['closed', 'payment_pending'], true)) {
        return 'Nyertes licit';
    }

    return 'Aktualis vezeto licit';
}

function impactshop_event_auction_admin_permission(WP_REST_Request $request)
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Admin jogosultsag szukseges.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_header('x-wp-nonce');
    }
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if ($nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Ervenytelen nonce.', ['status' => 403]);
    }

    return true;
}

function impactshop_event_auction_is_configured(): bool
{
    return defined('IMPACT_STRIPE_SECRET_KEY')
        && defined('IMPACT_STRIPE_PUBLIC_KEY')
        && defined('IMPACT_STRIPE_WEBHOOK_SECRET')
        && IMPACT_STRIPE_SECRET_KEY !== ''
        && IMPACT_STRIPE_PUBLIC_KEY !== ''
        && IMPACT_STRIPE_WEBHOOK_SECRET !== ''
        && defined('IMPACT_STRIPE_DEFAULT_CURRENCY')
        && IMPACT_STRIPE_DEFAULT_CURRENCY !== '';
}

function impactshop_event_auction_stripe_mode(): string
{
    if (defined('IMPACT_STRIPE_MODE') && IMPACT_STRIPE_MODE !== '') {
        return strtolower((string) IMPACT_STRIPE_MODE);
    }

    if (defined('IMPACT_STRIPE_SECRET_KEY')) {
        $key = (string) IMPACT_STRIPE_SECRET_KEY;
        if (strpos($key, 'sk_live_') === 0) {
            return 'live';
        }
        if (strpos($key, 'sk_test_') === 0) {
            return 'test';
        }
    }

    return 'unknown';
}

function impactshop_event_auction_is_staging_runtime(): bool
{
    $home = (string) home_url('/');
    return strpos($home, '/impactshop-staging') !== false || stripos($home, 'staging') !== false;
}

function impactshop_event_auction_is_paid_session(array $session): bool
{
    $paymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
    $status = strtolower((string) ($session['status'] ?? ''));

    if ($paymentStatus !== '' && $paymentStatus !== 'paid') {
        return false;
    }

    if ($status !== '' && !in_array($status, ['complete', 'completed'], true)) {
        return false;
    }

    return true;
}

function impactshop_event_auction_webhook_secret(): string
{
    if (defined('IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET') && IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET !== '') {
        return (string) IMPACT_EVENT_AUCTION_STRIPE_WEBHOOK_SECRET;
    }

    if (function_exists('impactshop_event_donation_webhook_secret')) {
        return (string) impactshop_event_donation_webhook_secret();
    }

    return defined('IMPACT_STRIPE_WEBHOOK_SECRET') ? (string) IMPACT_STRIPE_WEBHOOK_SECRET : '';
}

function impactshop_event_auction_verify_stripe_signature(string $payload, string $sigHeader, string $secret): bool
{
    if (function_exists('impactshop_event_donation_verify_stripe_signature')) {
        return impactshop_event_donation_verify_stripe_signature($payload, $sigHeader, $secret);
    }

    if ($payload === '' || $sigHeader === '' || $secret === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $sigHeader) as $item) {
        $pair = explode('=', trim($item), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }

    if (empty($parts['t']) || empty($parts['v1'])) {
        return false;
    }

    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
    foreach (explode(' ', str_replace(',', ' ', (string) $parts['v1'])) as $signature) {
        if ($signature !== '' && hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function impactshop_event_auction_fetch_stripe_session(string $sessionId): ?array
{
    $sessionId = sanitize_text_field($sessionId);
    if ($sessionId === '' || !impactshop_event_auction_is_configured()) {
        return null;
    }

    $response = wp_remote_get(
        'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
            ],
            'timeout' => 20,
        ]
    );

    if (is_wp_error($response)) {
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return null;
    }

    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    return is_array($data) ? $data : null;
}

function impactshop_event_auction_to_minor(float $amount, string $currency): int
{
    if (function_exists('impactshop_event_donation_to_minor')) {
        return impactshop_event_donation_to_minor($amount, $currency);
    }

    return (int) round($amount * 100, 0);
}

function impactshop_event_auction_redirect_result(string $status, string $bidUuid = '')
{
    $row = $bidUuid !== '' ? impactshop_event_auction_get_bid_by_uuid($bidUuid) : null;
    $campaignSlug = sanitize_title((string) ($row['campaign_slug'] ?? 'jovonkvize-2026'));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);

    $fallback = $status === 'success'
        ? (string) (($campaign['success_return_url'] ?? home_url('/')))
        : (string) (($campaign['cancel_return_url'] ?? home_url('/')));

    $returnUrl = esc_url_raw((string) ($row['return_url'] ?? $fallback));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $redirect = add_query_arg([
        'ea_status' => $status,
        'ea_campaign' => $campaignSlug,
        'ea_bid_uuid' => $bidUuid,
        'ea_item_slug' => sanitize_title((string) ($row['item_slug'] ?? '')),
    ], $returnUrl);

    wp_safe_redirect($redirect, 302);
    exit;
}

function impactshop_event_auction_bidders_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_bidders';
}

function impactshop_event_auction_bids_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_bids';
}

function impactshop_event_auction_events_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'impactshop_event_auction_events';
}

function impactshop_event_auction_ensure_schema(): void
{
    $installed = (string) get_option('impactshop_event_auction_schema_version', '');
    if ($installed === IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charsetCollate = $wpdb->get_charset_collate();
    $biddersTable = impactshop_event_auction_bidders_table_name();
    $bidsTable = impactshop_event_auction_bids_table_name();
    $eventsTable = impactshop_event_auction_events_table_name();

    $sql = "
    CREATE TABLE {$biddersTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        bidder_uuid char(36) NOT NULL,
        campaign_slug varchar(120) NOT NULL,
        email varchar(190) NOT NULL,
        phone varchar(50) DEFAULT '' NOT NULL,
        display_name varchar(190) DEFAULT '' NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bidder_uuid (bidder_uuid),
        KEY campaign_slug (campaign_slug),
        KEY email (email)
    ) {$charsetCollate};

    CREATE TABLE {$bidsTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        bid_uuid char(36) NOT NULL,
        campaign_slug varchar(120) NOT NULL,
        item_slug varchar(160) NOT NULL,
        bidder_uuid char(36) NOT NULL,
        bid_amount bigint unsigned NOT NULL DEFAULT 0,
        status varchar(40) NOT NULL DEFAULT 'pending',
        idempotency_key varchar(120) DEFAULT '' NOT NULL,
        stripe_session_id varchar(128) DEFAULT NULL,
        stripe_payment_intent varchar(128) DEFAULT NULL,
        stripe_checkout_url text NULL,
        return_url varchar(255) DEFAULT NULL,
        closed_at datetime DEFAULT NULL,
        payment_requested_at datetime DEFAULT NULL,
        payment_completed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bid_uuid (bid_uuid),
        KEY campaign_item (campaign_slug, item_slug),
        KEY bidder_uuid (bidder_uuid),
        KEY idempotency_key (idempotency_key),
        KEY stripe_session_id (stripe_session_id),
        KEY stripe_payment_intent (stripe_payment_intent)
    ) {$charsetCollate};

    CREATE TABLE {$eventsTable} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        campaign_slug varchar(120) NOT NULL,
        item_slug varchar(160) DEFAULT '' NOT NULL,
        event_type varchar(80) NOT NULL,
        actor varchar(120) DEFAULT '' NOT NULL,
        origin varchar(190) DEFAULT '' NOT NULL,
        payload_json longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY campaign_item (campaign_slug, item_slug),
        KEY event_type (event_type)
    ) {$charsetCollate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Migration: add Stripe card-on-file columns to bidders table (0.3.0)
    $biddersColCheck = $wpdb->get_results("SHOW COLUMNS FROM {$biddersTable} LIKE 'stripe_customer_id'");
    if (empty($biddersColCheck)) {
        $wpdb->query("ALTER TABLE {$biddersTable} ADD COLUMN stripe_customer_id varchar(128) DEFAULT NULL, ADD COLUMN stripe_payment_method_id varchar(128) DEFAULT NULL");
    }

    // Migration: add stripe_auth_amount column to bids table (0.3.0)
    $bidsColCheck = $wpdb->get_results("SHOW COLUMNS FROM {$bidsTable} LIKE 'stripe_auth_amount'");
    if (empty($bidsColCheck)) {
        $wpdb->query("ALTER TABLE {$bidsTable} ADD COLUMN stripe_auth_amount bigint unsigned DEFAULT NULL");
    }

    update_option('impactshop_event_auction_schema_version', IMPACTSHOP_EVENT_AUCTION_SCHEMA_VERSION, false);
}

function impactshop_event_auction_register_routes(): void
{
    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/public', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_public',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/lots/(?P<item_slug>[a-z0-9\-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_lot_detail',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/stats', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_stats',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/register-bidder', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_register_bidder',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/setup-payment', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_setup_payment',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/confirm-card-setup', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_confirm_card_setup',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/(?P<slug>[a-z0-9\-]+)/lots/(?P<item_slug>[a-z0-9\-]+)/bid', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_bid',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/admin/lots/(?P<item_slug>[a-z0-9\-]+)/close', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_admin_close',
        'permission_callback' => 'impactshop_event_auction_admin_permission',
    ]);

    register_rest_route('impact/v1', '/event-auctions/admin/lots/(?P<item_slug>[a-z0-9\-]+)/request-winner-payment', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_request_winner_payment',
        'permission_callback' => 'impactshop_event_auction_admin_permission',
    ]);

    register_rest_route('impact/v1', '/event-auctions/admin/(?P<slug>[a-z0-9\-]+)/bids', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_admin_bids',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/webhook', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_event_auction_webhook',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/success', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_success',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/event-auctions/cancel', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_event_auction_cancel',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_event_auction_query_api_dispatch(): void
{
    $action = sanitize_key((string) ($_REQUEST['impact_auction_api'] ?? ''));
    if ($action === '') {
        return;
    }

    $slug = sanitize_title((string) ($_REQUEST['campaign'] ?? ''));
    $campaign = $slug !== '' ? impactshop_event_auction_get_campaign($slug) : null;
    if ($campaign) {
        impactshop_event_auction_send_cors_headers($campaign);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'OPTIONS') {
        status_header(204);
        exit;
    }

    $request = new WP_REST_Request($method, '/impact/v1/event-auctions/' . $action);
    if ($slug !== '') {
        $request->set_param('slug', $slug);
    }

    $itemSlug = sanitize_title((string) ($_REQUEST['lot'] ?? $_REQUEST['item_slug'] ?? ''));
    if ($itemSlug !== '') {
        $request->set_param('item_slug', $itemSlug);
    }

    switch ($action) {
        case 'public':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_public($request));
            break;
        case 'stats':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_stats($request));
            break;
        case 'lot':
            impactshop_event_auction_emit_query_response(impactshop_event_auction_lot_detail($request));
            break;
        default:
            impactshop_event_auction_emit_query_response(new WP_REST_Response(['error' => 'not_found'], 404));
    }
}

function impactshop_event_auction_emit_query_response(WP_REST_Response $response): void
{
    foreach ((array) $response->get_headers() as $name => $value) {
        if (!headers_sent()) {
            header((string) $name . ': ' . (string) $value);
        }
    }

    nocache_headers();
    status_header($response->get_status());
    header('Content-Type: application/json; charset=' . get_bloginfo('charset'));
    echo wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function impactshop_event_auction_embed_page_dispatch(): void
{
    if ((string) ($_GET['impact_event_auction_embed'] ?? '') !== '1') {
        return;
    }

    $slug = sanitize_title((string) ($_GET['campaign'] ?? ($_GET['slug'] ?? 'jovonkvize-2026')));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        status_header(404);
        echo 'Ismeretlen aukciós kampány';
        exit;
    }

    status_header(200);
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    header_remove('X-Frame-Options');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $scriptSrc = esc_url(trailingslashit(home_url('/wp-content/mu-plugins')) . 'impactshop-event-auction-widget-jovonkvize-1.0.0.js?v=' . rawurlencode(IMPACTSHOP_EVENT_AUCTION_VERSION));
    $apiBase = esc_url(rest_url('impact/v1/event-auctions'));
    $fallback = esc_url(home_url('/'));
    $campaignAttr = esc_attr($slug);

    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Jövőnk Vize aukció widget</title>';
    echo '<style>html,body{margin:0;padding:0;background:transparent}#impact-event-auction-embed-root{padding:0}</style>';
    echo '</head><body>';
    echo '<div id="impact-event-auction-embed-root"></div>';
    echo '<script src="' . $scriptSrc . '" data-impact-auction-widget data-campaign="' . $campaignAttr . '" data-target="#impact-event-auction-embed-root" data-api-base="' . $apiBase . '" data-fallback-api-base="' . $fallback . '" data-poll-ms="30000" defer></script>';
    echo '</body></html>';
    exit;
}

function impactshop_event_auction_shortcode($atts = []): string
{
    $atts = shortcode_atts([
        'campaign' => 'jovonkvize-2026',
        'target' => 'impact-event-auction-shortcode-root',
    ], (array) $atts, 'impact_event_auction_widget');

    $campaign = sanitize_title((string) $atts['campaign']);
    $targetId = sanitize_html_class((string) $atts['target']);
    $scriptSrc = esc_url(trailingslashit(home_url('/wp-content/mu-plugins')) . 'impactshop-event-auction-widget-jovonkvize-1.0.0.js?v=' . rawurlencode(IMPACTSHOP_EVENT_AUCTION_VERSION));
    $apiBase = esc_url(rest_url('impact/v1/event-auctions'));
    $fallback = esc_url(home_url('/'));

    return '<div id="' . esc_attr($targetId) . '"></div>'
        . '<script src="' . $scriptSrc . '" data-impact-auction-widget data-campaign="' . esc_attr($campaign) . '" data-target="#' . esc_attr($targetId) . '" data-api-base="' . $apiBase . '" data-fallback-api-base="' . $fallback . '" data-poll-ms="30000" defer></script>';
}

function impactshop_event_auction_public(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    // Auto-close: lejárt lot-ok lezárása minden poll-on (cron fallback)
    impactshop_event_auction_maybe_auto_close_campaign($slug, $campaign);

    $security = [
        'write_enabled' => false,
        'session_token' => '',
        'expires_at' => '',
    ];

    if (impactshop_event_auction_origin_allowed($campaign)) {
        $issued = impactshop_event_auction_issue_session_token($campaign);
        $security = [
            'write_enabled' => true,
            'session_token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
        ];
    }

    return new WP_REST_Response([
        'slug' => $campaign['slug'],
        'auction_slug' => $campaign['auction_slug'],
        'title' => $campaign['title'],
        'subtitle' => $campaign['subtitle'],
        'description' => $campaign['description'],
        'beneficiary_name' => $campaign['beneficiary_name'],
        'currency' => $campaign['currency'],
        'goal_amount' => (int) ($campaign['goal_amount'] ?? 0),
        'share_url' => esc_url_raw((string) ($campaign['share_url'] ?? '')),
        'hero_url' => esc_url_raw((string) ($campaign['hero_url'] ?? '')),
        'theme' => (array) ($campaign['theme'] ?? []),
        'lots' => impactshop_event_auction_lot_summaries($campaign),
        'stats' => impactshop_event_auction_stats_payload($campaign),
        'security' => $security,
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_lot_summaries(array $campaign): array
{
    $lots = [];

    foreach ((array) ($campaign['lots'] ?? []) as $lot) {
        $lots[] = impactshop_event_auction_normalize_lot(array_merge((array) $lot, [
            'campaign_slug' => (string) ($campaign['slug'] ?? ''),
        ]));
    }

    return $lots;
}

/**
 * Visszaadja egy lot tényleges zárási időpontját (ISO 8601 UTC).
 * Ha snipe protection miatt meghosszabbítottuk, a WP option értéke az elsődleges.
 */
function impactshop_event_auction_lot_end_time(string $campaignSlug, string $itemSlug, array $campaign): string
{
    $optionKey = 'impactshop_ea_lot_end_' . $campaignSlug . '_' . $itemSlug;
    $extended  = (string) get_option($optionKey, '');
    if ($extended !== '') {
        return $extended;
    }
    return (string) ($campaign['auction_end_time'] ?? '');
}

function impactshop_event_auction_normalize_lot(array $lot): array
{
    $startingBid = (int) ($lot['starting_bid'] ?? 0);
    $campaignSlug = sanitize_title((string) ($lot['campaign_slug'] ?? 'jovonkvize-2026'));
    $itemSlug = sanitize_title((string) ($lot['item_slug'] ?? ''));
    $bidState = impactshop_event_auction_current_bid_state($campaignSlug, $itemSlug);
    $currentBid = $bidState && isset($bidState['bid_amount']) ? (int) $bidState['bid_amount'] : null;
    $currency = 'huf';
    $effectiveStatus = impactshop_event_auction_effective_lot_status($lot, $bidState);
    $displayLabel = impactshop_event_auction_display_label($currentBid, $effectiveStatus);

    return [
        'item_slug' => sanitize_title((string) ($lot['item_slug'] ?? '')),
        'lot_number' => (int) ($lot['lot_number'] ?? 0),
        'category' => sanitize_key((string) ($lot['category'] ?? 'artwork')),
        'artist_name' => sanitize_text_field((string) ($lot['artist_name'] ?? '')),
        'item_title' => sanitize_text_field((string) ($lot['item_title'] ?? '')),
        'description_short' => sanitize_text_field((string) ($lot['description_short'] ?? '')),
        'description_long' => sanitize_textarea_field((string) ($lot['description_long'] ?? '')),
        'dimensions' => sanitize_text_field((string) ($lot['dimensions'] ?? '')),
        'medium' => sanitize_text_field((string) ($lot['medium'] ?? '')),
        'starting_bid' => $startingBid,
        'starting_bid_formatted' => impactshop_event_auction_format_amount($startingBid, $currency),
        'min_increment' => (int) ($lot['min_increment'] ?? 10000),
        'current_bid' => $currentBid,
        'current_bid_formatted' => $currentBid !== null ? impactshop_event_auction_format_amount($currentBid, $currency) : '',
        'display_amount' => $currentBid !== null ? $currentBid : $startingBid,
        'display_amount_formatted' => impactshop_event_auction_format_amount($currentBid !== null ? $currentBid : $startingBid, $currency),
        'display_label' => $displayLabel,
        'current_winner_bidder_id' => $bidState['bidder_uuid'] ?? '',
        'status' => $effectiveStatus,
        'image_url' => esc_url_raw((string) ($lot['image_url'] ?? '')),
        'end_time' => impactshop_event_auction_lot_end_time(
            $campaignSlug,
            sanitize_title((string) ($lot['item_slug'] ?? '')),
            impactshop_event_auction_get_campaign($campaignSlug) ?? []
        ),
    ];
}

function impactshop_event_auction_stats_payload(array $campaign): array
{
    $leadingTotal = 0;
    $closedTotal = 0;
    $paidTotal = 0;
    $closedLots = 0;

    foreach (impactshop_event_auction_lot_summaries($campaign) as $lot) {
        $display = (int) ($lot['display_amount'] ?? 0);
        if ($display > 0) {
            $leadingTotal += $display;
        }

        $lotStatus = (string) ($lot['status'] ?? '');
        if (in_array($lotStatus, ['closed', 'payment_pending', 'paid'], true)) {
            $closedTotal += $display;
            $closedLots++;
        } elseif ($lotStatus === 'closed_unsold') {
            $closedLots++;
        }

        if ($lotStatus === 'paid') {
            $paidTotal += $display;
        }
    }

    $donationPaid = 0.0;
    if (function_exists('impactshop_event_donation_get_campaign') && function_exists('impactshop_event_donation_stats_payload')) {
        $donationCampaign = impactshop_event_donation_get_campaign((string) ($campaign['slug'] ?? ''));
        if (is_array($donationCampaign)) {
            $donationStats = impactshop_event_donation_stats_payload($donationCampaign);
            $donationPaid = (float) ($donationStats['total_amount'] ?? 0);
        }
    }

    global $wpdb;
    $biddersTable = impactshop_event_auction_bidders_table_name();
    $activeBidders = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$biddersTable} WHERE campaign_slug = %s",
            (string) ($campaign['slug'] ?? '')
        )
    );

    return [
        'currency' => 'huf',
        'auction_lots_count' => count((array) ($campaign['lots'] ?? [])),
        'active_bidders_count' => $activeBidders,
        'closed_lots_count' => $closedLots,
        'auction_leading_total_amount' => $leadingTotal,
        'auction_leading_total_amount_formatted' => impactshop_event_auction_format_amount($leadingTotal, 'huf'),
        'auction_closed_total_amount' => $closedTotal,
        'auction_closed_total_amount_formatted' => impactshop_event_auction_format_amount($closedTotal, 'huf'),
        'auction_paid_total_amount' => $paidTotal,
        'auction_paid_total_amount_formatted' => impactshop_event_auction_format_amount($paidTotal, 'huf'),
        'donation_total_amount' => $donationPaid,
        'donation_total_amount_formatted' => impactshop_event_auction_format_amount($donationPaid, 'huf'),
        'combined_paid_total_amount' => (float) $paidTotal + $donationPaid,
        'combined_paid_total_amount_formatted' => impactshop_event_auction_format_amount((float) $paidTotal + $donationPaid, 'huf'),
        'updated_at' => gmdate('c'),
    ];
}

function impactshop_event_auction_lot_detail(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    foreach (impactshop_event_auction_lot_summaries($campaign) as $lot) {
        if ((string) ($lot['item_slug'] ?? '') === $itemSlug) {
            return new WP_REST_Response([
                'lot' => $lot,
                'integration_state' => 'write_lane_partial',
            ], 200);
        }
    }

    return new WP_REST_Response(['error' => 'not_found'], 404);
}

function impactshop_event_auction_stats(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    return new WP_REST_Response(impactshop_event_auction_stats_payload($campaign), 200);
}

function impactshop_event_auction_admin_bids(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $page = max(1, (int) $request->get_param('page'));
    $perPage = (int) $request->get_param('per_page');
    if ($perPage <= 0) {
        $perPage = 50;
    }
    $perPage = min(200, $perPage);
    $offset = ($page - 1) * $perPage;

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $status = sanitize_key((string) $request->get_param('status'));
    $allowedStatuses = ['pending', 'winning', 'outbid', 'closed', 'payment_pending', 'paid', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    global $wpdb;
    $bidsTable = impactshop_event_auction_bids_table_name();
    $biddersTable = impactshop_event_auction_bidders_table_name();

    $where = ['b.campaign_slug = %s'];
    $queryArgs = [$slug];

    if ($itemSlug !== '') {
        $where[] = 'b.item_slug = %s';
        $queryArgs[] = $itemSlug;
    }

    if ($status !== '') {
        $where[] = 'b.status = %s';
        $queryArgs[] = $status;
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM {$bidsTable} b WHERE {$whereSql}";
    $total = (int) $wpdb->get_var($wpdb->prepare($countSql, ...$queryArgs));

    $rowsSql = "SELECT
            b.bid_uuid,
            b.item_slug,
            b.bidder_uuid,
            b.bid_amount,
            b.status,
            b.stripe_session_id,
            b.stripe_payment_intent,
            b.created_at,
            b.closed_at,
            b.payment_requested_at,
            b.payment_completed_at,
            d.email AS bidder_email,
            d.phone AS bidder_phone,
            d.display_name AS bidder_name
        FROM {$bidsTable} b
        LEFT JOIN {$biddersTable} d ON d.bidder_uuid = b.bidder_uuid
        WHERE {$whereSql}
        ORDER BY b.id DESC
        LIMIT %d OFFSET %d";

    $rowsArgs = $queryArgs;
    $rowsArgs[] = $perPage;
    $rowsArgs[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($rowsSql, ...$rowsArgs), ARRAY_A);
    if (!is_array($rows)) {
        $rows = [];
    }

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'bid_uuid' => (string) ($row['bid_uuid'] ?? ''),
            'item_slug' => (string) ($row['item_slug'] ?? ''),
            'bidder_uuid' => (string) ($row['bidder_uuid'] ?? ''),
            'bid_amount' => (int) ($row['bid_amount'] ?? 0),
            'bid_amount_formatted' => impactshop_event_auction_format_amount((int) ($row['bid_amount'] ?? 0), 'huf'),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'closed_at' => (string) ($row['closed_at'] ?? ''),
            'payment_requested_at' => (string) ($row['payment_requested_at'] ?? ''),
            'payment_completed_at' => (string) ($row['payment_completed_at'] ?? ''),
            'bidder_name' => (string) ($row['bidder_name'] ?? ''),
            'bidder_email' => (string) ($row['bidder_email'] ?? ''),
            'bidder_phone' => (string) ($row['bidder_phone'] ?? ''),
            'stripe_session_id' => (string) ($row['stripe_session_id'] ?? ''),
            'stripe_payment_intent' => (string) ($row['stripe_payment_intent'] ?? ''),
        ];
    }

    return new WP_REST_Response([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ],
    ], 200);
}

function impactshop_event_auction_register_bidder(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $limit = impactshop_event_auction_rate_limit('register_bidder', 8, 60);
    if (!$limit['allowed']) {
        return new WP_REST_Response([
            'error' => 'rate_limited',
            'reset_at' => gmdate('c', (int) $limit['reset']),
        ], 429);
    }

    $payload = impactshop_event_auction_extract_payload($request);
    $sessionToken = sanitize_text_field((string) ($payload['session_token'] ?? ''));
    if (!impactshop_event_auction_verify_session_token($sessionToken, $campaign)) {
        return new WP_REST_Response(['error' => 'invalid_session_token'], 403);
    }

    $email = sanitize_email((string) ($payload['email'] ?? ''));
    if ($email === '' || !is_email($email)) {
        return new WP_REST_Response(['error' => 'invalid_email'], 400);
    }

    $phone = sanitize_text_field((string) ($payload['phone'] ?? ''));
    $displayName = sanitize_text_field((string) ($payload['display_name'] ?? ''));

    global $wpdb;
    $table = impactshop_event_auction_bidders_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT bidder_uuid, email, phone, display_name
             FROM {$table}
             WHERE campaign_slug = %s
               AND email = %s
             LIMIT 1",
            $slug,
            $email
        ),
        ARRAY_A
    );

    if ($row) {
        $bidderUuid = (string) ($row['bidder_uuid'] ?? '');
        $wpdb->update(
            $table,
            [
                'phone' => $phone !== '' ? $phone : (string) ($row['phone'] ?? ''),
                'display_name' => $displayName !== '' ? $displayName : (string) ($row['display_name'] ?? ''),
            ],
            [
                'bidder_uuid' => $bidderUuid,
            ],
            ['%s', '%s'],
            ['%s']
        );
    } else {
        $bidderUuid = wp_generate_uuid4();
        $wpdb->insert(
            $table,
            [
                'bidder_uuid' => $bidderUuid,
                'campaign_slug' => $slug,
                'email' => $email,
                'phone' => $phone,
                'display_name' => $displayName,
                'created_at' => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    $issued = impactshop_event_auction_issue_bidder_token($campaign, $bidderUuid);
    impactshop_event_auction_log_event($slug, '', 'register_bidder', $bidderUuid, [
        'email_hash' => md5(strtolower($email)),
        'phone_present' => $phone !== '',
    ]);

    return new WP_REST_Response([
        'bidder_token' => $issued['token'],
        'bidder_token_expires_at' => $issued['expires_at'],
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_bid(WP_REST_Request $request): WP_REST_Response
{
    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $limit = impactshop_event_auction_rate_limit('bid', 20, 60);
    if (!$limit['allowed']) {
        return new WP_REST_Response([
            'error' => 'rate_limited',
            'reset_at' => gmdate('c', (int) $limit['reset']),
        ], 429);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    $currentBidState = impactshop_event_auction_current_bid_state((string) ($campaign['slug'] ?? ''), $itemSlug);
    $effectiveStatus = impactshop_event_auction_effective_lot_status($lot, $currentBidState);

    if (!in_array($effectiveStatus, ['live', 'closing'], true)) {
        return new WP_REST_Response(['error' => 'lot_not_live'], 409);
    }

    $payload = impactshop_event_auction_extract_payload($request);
    $sessionToken = sanitize_text_field((string) ($payload['session_token'] ?? ''));
    if (!impactshop_event_auction_verify_session_token($sessionToken, $campaign)) {
        return new WP_REST_Response(['error' => 'invalid_session_token'], 403);
    }

    $bidderToken = sanitize_text_field((string) ($payload['bidder_token'] ?? ''));
    $bidderPayload = impactshop_event_auction_verify_bidder_token($bidderToken, $campaign);
    if (!$bidderPayload) {
        return new WP_REST_Response(['error' => 'invalid_bidder_token'], 403);
    }

    $idempotencyKey = sanitize_text_field((string) ($payload['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
        return new WP_REST_Response(['error' => 'missing_idempotency_key'], 400);
    }

    $bidAmount = impactshop_event_auction_parse_amount($payload['bid_amount'] ?? 0);
    if ($bidAmount <= 0) {
        return new WP_REST_Response(['error' => 'invalid_bid_amount'], 400);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $campaignSlug = (string) ($campaign['slug'] ?? '');
    $bidderUuid = (string) ($bidderPayload['bidder_uuid'] ?? '');

    $wpdb->query('START TRANSACTION');

    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT bid_uuid, bid_amount, bidder_uuid, status
             FROM {$table}
             WHERE campaign_slug = %s
               AND idempotency_key = %s
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $idempotencyKey
        ),
        ARRAY_A
    );

    if ($existing) {
        $wpdb->query('COMMIT');
        return new WP_REST_Response([
            'bid_uuid' => (string) ($existing['bid_uuid'] ?? ''),
            'bid_amount' => (int) ($existing['bid_amount'] ?? 0),
            'bid_amount_formatted' => impactshop_event_auction_format_amount((int) ($existing['bid_amount'] ?? 0), 'huf'),
            'status' => (string) ($existing['status'] ?? 'winning'),
            'idempotent_replay' => true,
            'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
            'integration_state' => 'write_lane_partial',
        ], 200);
    }

        $current = $wpdb->get_row(
        $wpdb->prepare(
                        "SELECT id, bid_uuid, bidder_uuid, bid_amount, status, stripe_session_id, stripe_payment_intent, stripe_checkout_url
             FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
                             AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );

    $startingBid = (int) ($lot['starting_bid'] ?? 0);
    $minIncrement = (int) ($lot['min_increment'] ?? 10000);
    $currentAmount = $current ? (int) ($current['bid_amount'] ?? 0) : 0;
    // Elso licitnel a starting_bid a minimum, utana lep be a min_increment szabaly.
    $minimumRequired = $current ? ($currentAmount + $minIncrement) : $startingBid;

    if ($bidAmount < $minimumRequired) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response([
            'error' => 'bid_too_low',
            'minimum_required' => $minimumRequired,
            'minimum_required_formatted' => impactshop_event_auction_format_amount($minimumRequired, 'huf'),
        ], 409);
    }

    if ($current && (string) ($current['bidder_uuid'] ?? '') === $bidderUuid && $bidAmount <= $currentAmount) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response([
            'error' => 'already_winning',
            'current_bid' => $currentAmount,
            'current_bid_formatted' => impactshop_event_auction_format_amount($currentAmount, 'huf'),
        ], 409);
    }

    if ($current) {
        $wpdb->update(
            $table,
            ['status' => 'outbid'],
            ['id' => (int) $current['id']],
            ['%s'],
            ['%d']
        );
    }

    $bidUuid = wp_generate_uuid4();
    $inserted = $wpdb->insert(
        $table,
        [
            'bid_uuid'        => $bidUuid,
            'campaign_slug'   => $campaignSlug,
            'item_slug'       => $itemSlug,
            'bidder_uuid'     => $bidderUuid,
            'bid_amount'      => $bidAmount,
            'status'          => 'winning',
            'idempotency_key' => $idempotencyKey,
            'created_at'      => current_time('mysql', true),
        ],
        ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'bid_insert_failed'], 500);
    }

    $wpdb->query('COMMIT');

    // ── Authorize-and-capture: befoglalás az új licithez ─────────────────────
    $currency    = strtolower((string) ($campaign['currency'] ?? 'huf'));
    $amountMinor = impactshop_event_auction_to_minor((float) $bidAmount, $currency);
    $bidderFull  = impactshop_event_auction_get_bidder($bidderUuid);
    $custId      = (string) ($bidderFull['stripe_customer_id'] ?? '');
    $pmId        = (string) ($bidderFull['stripe_payment_method_id'] ?? '');

    if ($custId !== '' && $pmId !== '') {
        $pi = impactshop_event_auction_create_bid_authorization([
            'campaign_slug'    => $campaignSlug,
            'item_slug'        => $itemSlug,
            'currency'         => $currency,
            'amount_minor'     => $amountMinor,
            'customer_id'      => $custId,
            'payment_method_id'=> $pmId,
            'bid_uuid'         => $bidUuid,
        ]);
        if ($pi && !empty($pi['id'])) {
            $wpdb->update(
                $table,
                [
                    'stripe_payment_intent' => (string) $pi['id'],
                    'stripe_auth_amount'    => $amountMinor,
                ],
                ['bid_uuid' => $bidUuid],
                ['%s', '%d'],
                ['%s']
            );
            impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_authorized', $bidderUuid, [
                'bid_uuid'             => $bidUuid,
                'stripe_payment_intent'=> (string) $pi['id'],
                'amount_minor'         => $amountMinor,
            ]);
        } else {
            error_log('[impactshop-event-auction] WARNING: PI auth failed for bid ' . $bidUuid . ' — bid recorded without hold');
        }
    }

    // ── Felszabadítjuk az előző nyertes befoglalását ──────────────────────────
    if ($current && !empty($current['stripe_payment_intent'])) {
        impactshop_event_auction_cancel_payment_intent((string) $current['stripe_payment_intent']);
        impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_auth_released', (string) ($current['bidder_uuid'] ?? ''), [
            'bid_uuid'             => (string) ($current['bid_uuid'] ?? ''),
            'stripe_payment_intent'=> (string) $current['stripe_payment_intent'],
        ]);
    }
    // ─────────────────────────────────────────────────────────────────────────

    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_created', $bidderUuid, [
        'bid_uuid' => $bidUuid,
        'bid_amount' => $bidAmount,
        'idempotency_key' => $idempotencyKey,
    ]);

    // ── Snipe protection ─────────────────────────────────────────────────────
    $snipeWindow  = (int) ($campaign['snipe_window_seconds'] ?? 120);
    $snipeExtend  = (int) ($campaign['snipe_extend_seconds'] ?? 120);
    $currentEndTs = (string) impactshop_event_auction_lot_end_time($campaignSlug, $itemSlug, $campaign);
    if ($currentEndTs !== '' && $snipeWindow > 0 && $snipeExtend > 0) {
        $endTimestamp = strtotime($currentEndTs);
        if ($endTimestamp !== false && $endTimestamp > 0) {
            $secondsRemaining = $endTimestamp - time();
            if ($secondsRemaining > 0 && $secondsRemaining <= $snipeWindow) {
                $newEndTimestamp = $endTimestamp + $snipeExtend;
                $newEndIso       = gmdate('Y-m-d\TH:i:s\Z', $newEndTimestamp);
                $optionKey = 'impactshop_ea_lot_end_' . $campaignSlug . '_' . $itemSlug;
                update_option($optionKey, $newEndIso, false);
                impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'snipe_extension', $bidderUuid, [
                    'bid_uuid'     => $bidUuid,
                    'prev_end'     => $currentEndTs,
                    'new_end'      => $newEndIso,
                    'extend_secs'  => $snipeExtend,
                ]);
            }
        }
    }
    // ─────────────────────────────────────────────────────────────────────────

    // Email + SMS értesítők: új licit (admin) + felülicitált (előző nyertes)
    $bidderInfo = impactshop_event_auction_get_bidder($bidderUuid);
    $bidderName  = $bidderInfo ? sanitize_text_field((string) ($bidderInfo['display_name'] ?? '')) : '(ismeretlen)';
    $bidderEmail = $bidderInfo ? sanitize_email((string) ($bidderInfo['email'] ?? '')) : '';
    $bidderPhone = $bidderInfo ? sanitize_text_field((string) ($bidderInfo['phone'] ?? '')) : '';
    $lotTitle    = sanitize_text_field((string) ($lot['item_title'] ?? $itemSlug));
    $amountFmt   = impactshop_event_auction_format_amount($bidAmount, 'huf');
    $lotUrl      = esc_url(trailingslashit((string) ($campaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $itemSlug);
    $timestamp   = current_time('mysql', true);

    // ── Admin értesítő: új licit ───────────────────────────────────────────
    $notifyTo      = ['office@sharity.hu', 'koncz.veronika@mielemed.hu'];
    $notifySubject = '[JVK Aukció] Új licit — ' . $lotTitle . ': ' . $amountFmt;
    $notifyBody    = "Új licit érkezett a Jövőnk Vize Gála aukción.\n\n"
        . "Tétel: {$lotTitle}\n"
        . "Licit összege: {$amountFmt}\n"
        . "Licitáló neve: {$bidderName}\n"
        . "Licitáló e-mail: {$bidderEmail}\n"
        . "Licitáló telefon: {$bidderPhone}\n"
        . "Időpont (UTC): {$timestamp}\n\n"
        . "Tétel megtekintése:\n{$lotUrl}\n\n"
        . "Licit UUID: {$bidUuid}";
    impactshop_event_auction_send_email($notifyTo, $notifySubject, $notifyBody);
    impactshop_event_auction_notify_sms(
        'Sharity JVK: Új licit — ' . $lotTitle . ' | ' . $amountFmt . ' | ' . $bidderName . ' | ' . $lotUrl
    );

    // ── Outbid értesítő: az előző nyertes licitálónak ─────────────────────
    if ($current && !empty($current['bidder_uuid'])) {
        $prevInfo  = impactshop_event_auction_get_bidder((string) $current['bidder_uuid']);
        $prevEmail = $prevInfo ? sanitize_email((string) ($prevInfo['email'] ?? '')) : '';
        $prevName  = $prevInfo ? sanitize_text_field((string) ($prevInfo['display_name'] ?? '')) : '';
        $prevPhone = $prevInfo ? sanitize_text_field((string) ($prevInfo['phone'] ?? '')) : '';
        $prevAmtFmt = impactshop_event_auction_format_amount((int) ($current['bid_amount'] ?? 0), 'huf');
        if ($prevEmail !== '') {
            $outbidSubject = 'Felülicitáltak — ' . $lotTitle . ' | Sharity JVK Aukció';
            $outbidBody    = "Kedves {$prevName}!\n\n"
                . "Valaki magasabb összeggel licitált rád a Jövőnk Vize Gála aukción.\n\n"
                . "Tétel: {$lotTitle}\n"
                . "Jelenlegi legmagasabb licit: {$amountFmt}\n"
                . "A te licited volt: {$prevAmtFmt}\n\n"
                . "Szeretnéd visszaszerezni a vezető pozíciót? Licitálj újra egy kattintással:\n"
                . "{$lotUrl}\n\n"
                . "Az esetlegesen lefoglalt kártyaösszeged feloldásra kerül.\n\n"
                . "Sharity – JVK Aukció csapata";
            impactshop_event_auction_send_email([$prevEmail], $outbidSubject, $outbidBody);
        }
        if ($prevPhone !== '') {
            impactshop_event_auction_send_sms(
                $prevPhone,
                'Sharity JVK: Felülicitáltak! ' . $lotTitle . ' — új licit: ' . $amountFmt . '. Licitálj újra: ' . $lotUrl
            );
        }
    }

    return new WP_REST_Response([
        'bid_uuid' => $bidUuid,
        'bid_amount' => $bidAmount,
        'bid_amount_formatted' => impactshop_event_auction_format_amount($bidAmount, 'huf'),
        'status' => 'winning',
        'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
        'integration_state' => 'write_lane_partial',
    ], 200);
}

function impactshop_event_auction_admin_close(WP_REST_Request $request): WP_REST_Response
{
    $payload = impactshop_event_auction_extract_payload($request);
    $campaignSlug = sanitize_title((string) ($payload['campaign_slug'] ?? ''));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $actor = wp_get_current_user();
    $actorId = $actor instanceof WP_User ? (string) $actor->user_login : 'admin';

    $wpdb->query('START TRANSACTION');
    $current = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
               AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );

    if (!$current) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'no_winning_bid'], 409);
    }

    $status = sanitize_key((string) ($current['status'] ?? ''));
    if ($status === 'paid') {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'already_paid'], 409);
    }

    if (in_array($status, ['closed', 'payment_pending'], true)) {
        $wpdb->query('COMMIT');
        return new WP_REST_Response([
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
            'status' => $status,
            'idempotent_replay' => true,
            'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
        ], 200);
    }

    $closedAt = current_time('mysql', true);
    $updated = $wpdb->update(
        $table,
        [
            'status' => 'closed',
            'closed_at' => $closedAt,
        ],
        [
            'id' => (int) $current['id'],
        ],
        ['%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(['error' => 'close_failed'], 500);
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'admin_close', $actorId, [
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'bidder_uuid' => (string) ($current['bidder_uuid'] ?? ''),
        'bid_amount' => (int) ($current['bid_amount'] ?? 0),
    ]);

    // Email + SMS értesítő: nyertes lezárás (admin) + nyertes licitáló
    $winnerBidderInfo = impactshop_event_auction_get_bidder((string) ($current['bidder_uuid'] ?? ''));
    $winnerName  = $winnerBidderInfo ? sanitize_text_field((string) ($winnerBidderInfo['display_name'] ?? '')) : '(ismeretlen)';
    $winnerEmail = $winnerBidderInfo ? sanitize_email((string) ($winnerBidderInfo['email'] ?? '')) : '';
    $winnerPhone = $winnerBidderInfo ? sanitize_text_field((string) ($winnerBidderInfo['phone'] ?? '')) : '';
    $closedLotTitle = sanitize_text_field((string) ($lot['item_title'] ?? $itemSlug));
    $closedAmtFmt   = impactshop_event_auction_format_amount((int) ($current['bid_amount'] ?? 0), 'huf');
    $closedLotUrl   = esc_url(trailingslashit((string) ($campaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $itemSlug);
    $closeNotifyTo  = ['office@sharity.hu', 'koncz.veronika@mielemed.hu'];
    $closeSubject   = '[JVK Aukció] LEZÁRVA — ' . $closedLotTitle . ': nyertes ' . $winnerName . ' (' . $closedAmtFmt . ')';
    $closeBody      = "Aukciós tétel lezárásra került.\n\n"
        . "Tétel: {$closedLotTitle}\n"
        . "Nyertes neve: {$winnerName}\n"
        . "Nyertes licit összege: {$closedAmtFmt}\n"
        . "Nyertes e-mail: {$winnerEmail}\n"
        . "Nyertes telefon: {$winnerPhone}\n"
        . "Lezárás (UTC): {$closedAt}\n"
        . "Lezárta: {$actorId}\n\n"
        . "Tétel oldal:\n{$closedLotUrl}\n\n"
        . "Licit UUID: " . (string) ($current['bid_uuid'] ?? '');
    impactshop_event_auction_send_email($closeNotifyTo, $closeSubject, $closeBody);
    impactshop_event_auction_notify_sms(
        'Sharity JVK: LEZÁRVA — ' . $closedLotTitle . ' | Nyertes: ' . $winnerName . ' | ' . $closedAmtFmt . ' | ' . $closedLotUrl
    );

    // ── Authorize-and-capture: capture winner's pre-authorized PI if present ──
    impactshop_event_auction_maybe_capture_winner($current);
    // ─────────────────────────────────────────────────────────────────────────

    return new WP_REST_Response([
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'status' => 'closed',
        'closed_at' => mysql2date('c', $closedAt, false),
        'lot' => impactshop_event_auction_normalize_lot(array_merge($lot, ['campaign_slug' => $campaignSlug])),
    ], 200);
}

/**
 * After admin_close marks the bid 'closed', attempt to capture the winner's
 * PaymentIntent if one exists (authorize-and-capture flow).
 * Falls back gracefully — the existing request_winner_payment flow stays intact.
 */
function impactshop_event_auction_maybe_capture_winner(array $bid): void
{
    $piId = sanitize_text_field((string) ($bid['stripe_payment_intent'] ?? ''));
    if ($piId === '') {
        return; // No pre-authorized PI — winner will pay via checkout link (legacy)
    }

    $bidUuid     = sanitize_text_field((string) ($bid['bid_uuid'] ?? ''));
    $campaignSlug = sanitize_title((string) ($bid['campaign_slug'] ?? ''));
    $itemSlug    = sanitize_title((string) ($bid['item_slug'] ?? ''));
    $bidderUuid  = sanitize_text_field((string) ($bid['bidder_uuid'] ?? ''));

    $captured = impactshop_event_auction_capture_payment_intent($piId);

    if ($captured) {
        global $wpdb;
        $wpdb->update(
            impactshop_event_auction_bids_table_name(),
            [
                'status'               => 'paid',
                'payment_completed_at' => current_time('mysql', true),
            ],
            ['bid_uuid' => $bidUuid],
            ['%s', '%s'],
            ['%s']
        );
        impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_captured', $bidderUuid, [
            'bid_uuid'             => $bidUuid,
            'stripe_payment_intent'=> $piId,
        ]);
    } else {
        error_log('[impactshop-event-auction] PI capture failed for bid ' . $bidUuid . ' — admin must use request_winner_payment as fallback');
        impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'bid_capture_failed', $bidderUuid, [
            'bid_uuid'             => $bidUuid,
            'stripe_payment_intent'=> $piId,
        ]);
    }
}


/**
 * REST handler: returns a Stripe Checkout Session (mode=setup) URL so the
 * bidder can save a card. Called once after register-bidder, before first bid.
 */
function impactshop_event_auction_setup_payment(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_event_auction_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $payload = impactshop_event_auction_extract_payload($request);

    $sessionToken = sanitize_text_field((string) ($payload['session_token'] ?? ''));
    if (!impactshop_event_auction_verify_session_token($sessionToken, $campaign)) {
        return new WP_REST_Response(['error' => 'invalid_session_token'], 403);
    }

    $bidderToken = sanitize_text_field((string) ($payload['bidder_token'] ?? ''));
    $bidderPayload = impactshop_event_auction_verify_bidder_token($bidderToken, $campaign);
    if (!$bidderPayload) {
        return new WP_REST_Response(['error' => 'invalid_bidder_token'], 403);
    }

    $bidderUuid = (string) ($bidderPayload['bidder_uuid'] ?? '');
    $bidder = impactshop_event_auction_get_bidder($bidderUuid);
    if (!$bidder) {
        return new WP_REST_Response(['error' => 'bidder_not_found'], 404);
    }

    // Already has a saved card — idempotent
    if (!empty($bidder['stripe_customer_id']) && !empty($bidder['stripe_payment_method_id'])) {
        return new WP_REST_Response([
            'status'      => 'already_setup',
            'bidder_uuid' => $bidderUuid,
        ], 200);
    }

    $returnUrl = esc_url_raw((string) ($payload['return_url'] ?? ($campaign['hero_url'] ?? home_url('/'))));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $session = impactshop_event_auction_create_card_setup_session([
        'campaign'   => $campaign,
        'bidder'     => $bidder,
        'return_url' => $returnUrl,
    ]);

    if (!$session || empty($session['id']) || empty($session['url'])) {
        return new WP_REST_Response(['error' => 'stripe_failed'], 502);
    }

    return new WP_REST_Response([
        'status'           => 'setup_required',
        'setup_url'        => (string) $session['url'],
        'setup_session_id' => (string) $session['id'],
    ], 200);
}

/**
 * Called by the JS after Stripe redirects back to the page with ?ea_card_setup=success&session_id=...
 * Fetches the Checkout Session from Stripe and fulfills card setup inline (no webhook dependency).
 */
function impactshop_event_auction_confirm_card_setup(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_event_auction_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $slug = sanitize_title((string) $request->get_param('slug'));
    $campaign = impactshop_event_auction_get_campaign($slug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    impactshop_event_auction_send_cors_headers($campaign);

    if (!impactshop_event_auction_origin_allowed($campaign)) {
        return new WP_REST_Response(['error' => 'origin_not_allowed'], 403);
    }

    $payload    = impactshop_event_auction_extract_payload($request);
    $sessionId  = sanitize_text_field((string) ($payload['session_id'] ?? ''));
    $bidderUuid = sanitize_text_field((string) ($payload['bidder_uuid'] ?? ''));

    if ($sessionId === '' || $bidderUuid === '') {
        return new WP_REST_Response(['error' => 'missing_params'], 400);
    }

    // Idempotency: if card already saved, return early
    $bidder = impactshop_event_auction_get_bidder($bidderUuid);
    if ($bidder && !empty($bidder['stripe_customer_id']) && !empty($bidder['stripe_payment_method_id'])) {
        return new WP_REST_Response(['status' => 'already_done'], 200);
    }

    // Fetch checkout session from Stripe
    $response = wp_remote_get(
        'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
        [
            'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] confirm_card_setup: Stripe fetch error: ' . $response->get_error_message());
        return new WP_REST_Response(['error' => 'stripe_error'], 502);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        error_log('[impactshop-event-auction] confirm_card_setup: Stripe error code=' . $code);
        return new WP_REST_Response(['error' => 'stripe_error'], 502);
    }

    $session = json_decode($body, true);
    if (!is_array($session)) {
        return new WP_REST_Response(['error' => 'stripe_response_invalid'], 502);
    }

    // Security: validate bidder_uuid matches session metadata
    $metadata       = (array) ($session['metadata'] ?? []);
    $metaBidderUuid = sanitize_text_field((string) ($metadata['bidder_uuid'] ?? ''));
    if (!hash_equals($metaBidderUuid, $bidderUuid)) {
        return new WP_REST_Response(['error' => 'bidder_mismatch'], 403);
    }

    impactshop_event_auction_fulfill_card_setup($session);

    return new WP_REST_Response(['status' => 'ok'], 200);
}

/**
 * Create a Stripe Checkout Session with mode=setup to save a payment method.
 */
function impactshop_event_auction_create_card_setup_session(array $params): ?array
{
    $campaign     = (array) ($params['campaign'] ?? []);
    $bidder       = (array) ($params['bidder'] ?? []);
    $campaignSlug = sanitize_title((string) ($campaign['slug'] ?? ''));
    $bidderUuid   = sanitize_text_field((string) ($bidder['bidder_uuid'] ?? ''));

    if ($campaignSlug === '' || $bidderUuid === '') {
        return null;
    }

    if (impactshop_event_auction_is_staging_runtime() && impactshop_event_auction_stripe_mode() === 'live') {
        error_log('[impactshop-event-auction] Refusing to create live Stripe setup session on staging.');
        return null;
    }

    $returnUrl  = esc_url_raw((string) ($params['return_url'] ?? home_url('/')));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }
    $successUrl = add_query_arg(
        ['ea_card_setup' => 'success', 'bidder_uuid' => $bidderUuid, 'campaign_slug' => $campaignSlug],
        $returnUrl
    ) . '&session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = add_query_arg(
        ['ea_card_setup' => 'cancelled'],
        $returnUrl
    );

    $payload = [
        'mode'                        => 'setup',
        'payment_method_types[0]'     => 'card',
        'customer_creation'           => 'always',
        'success_url'                 => $successUrl,
        'cancel_url'                  => $cancelUrl,
        'metadata[flow]'              => 'event_auction_card_setup',
        'metadata[campaign_slug]'     => $campaignSlug,
        'metadata[bidder_uuid]'       => $bidderUuid,
    ];

    $email = sanitize_email((string) ($bidder['email'] ?? ''));
    if ($email !== '') {
        $payload['customer_email'] = $email;
    }

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
        'body'    => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] Stripe setup session failed: ' . $response->get_error_message());
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        error_log('[impactshop-event-auction] Stripe setup session error: code=' . $code . ' body=' . substr($body, 0, 400));
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id']) || empty($data['url'])) {
        return null;
    }

    return ['id' => (string) $data['id'], 'url' => (string) $data['url']];
}

/**
 * Called from the webhook when a setup-mode Checkout Session completes.
 * Saves stripe_customer_id + stripe_payment_method_id to the bidder row.
 */
function impactshop_event_auction_fulfill_card_setup(array $session): void
{
    $metadata   = (array) ($session['metadata'] ?? []);
    $bidderUuid = sanitize_text_field((string) ($metadata['bidder_uuid'] ?? ''));
    $customerId = sanitize_text_field((string) ($session['customer'] ?? ''));

    if ($bidderUuid === '' || $customerId === '') {
        error_log('[impactshop-event-auction] fulfill_card_setup: missing bidder_uuid or customer');
        return;
    }

    // Fetch SetupIntent to get the confirmed PaymentMethod ID
    $setupIntentId   = sanitize_text_field((string) ($session['setup_intent'] ?? ''));
    $paymentMethodId = '';
    if ($setupIntentId !== '') {
        $siResponse = wp_remote_get(
            'https://api.stripe.com/v1/setup_intents/' . rawurlencode($setupIntentId),
            [
                'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
                'timeout' => 15,
            ]
        );
        if (!is_wp_error($siResponse)) {
            $siData = json_decode((string) wp_remote_retrieve_body($siResponse), true);
            if (is_array($siData) && !empty($siData['payment_method'])) {
                $paymentMethodId = sanitize_text_field((string) $siData['payment_method']);
            }
        }
    }

    if ($paymentMethodId === '') {
        error_log('[impactshop-event-auction] fulfill_card_setup: could not resolve payment_method for ' . $bidderUuid);
        return;
    }

    global $wpdb;
    $wpdb->update(
        impactshop_event_auction_bidders_table_name(),
        [
            'stripe_customer_id'       => $customerId,
            'stripe_payment_method_id' => $paymentMethodId,
        ],
        ['bidder_uuid' => $bidderUuid],
        ['%s', '%s'],
        ['%s']
    );

    impactshop_event_auction_log_event(
        sanitize_title((string) ($metadata['campaign_slug'] ?? '')),
        '',
        'card_setup_completed',
        $bidderUuid,
        [
            'stripe_customer_id'       => $customerId,
            'stripe_payment_method_id' => $paymentMethodId,
        ]
    );
}

/**
 * Create an off-session PaymentIntent with capture_method=manual.
 * Authorizes (holds) the card for the bid amount without charging it.
 */
function impactshop_event_auction_create_bid_authorization(array $params): ?array
{
    $campaignSlug    = sanitize_title((string) ($params['campaign_slug'] ?? ''));
    $itemSlug        = sanitize_title((string) ($params['item_slug'] ?? ''));
    $currency        = strtolower((string) ($params['currency'] ?? 'huf'));
    $amountMinor     = (int) ($params['amount_minor'] ?? 0);
    $customerId      = sanitize_text_field((string) ($params['customer_id'] ?? ''));
    $paymentMethodId = sanitize_text_field((string) ($params['payment_method_id'] ?? ''));
    $bidUuid         = sanitize_text_field((string) ($params['bid_uuid'] ?? ''));

    if ($campaignSlug === '' || $amountMinor <= 0 || $customerId === '' || $paymentMethodId === '' || $bidUuid === '') {
        return null;
    }

    if (impactshop_event_auction_is_staging_runtime() && impactshop_event_auction_stripe_mode() === 'live') {
        error_log('[impactshop-event-auction] Refusing to create live PI auth on staging.');
        return null;
    }

    $piPayload = [
        'amount'                  => $amountMinor,
        'currency'                => $currency,
        'customer'                => $customerId,
        'payment_method'          => $paymentMethodId,
        'capture_method'          => 'manual',
        'confirm'                 => 'true',
        'off_session'             => 'true',
        'metadata[flow]'          => 'event_auction_bid_auth',
        'metadata[bid_uuid]'      => $bidUuid,
        'metadata[campaign_slug]' => $campaignSlug,
        'metadata[item_slug]'     => $itemSlug,
    ];

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
        'body'    => http_build_query($piPayload, '', '&', PHP_QUERY_RFC3986),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] PI auth request failed: ' . $response->get_error_message());
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        error_log('[impactshop-event-auction] PI auth error: code=' . $code . ' body=' . substr($body, 0, 400));
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id'])) {
        return null;
    }

    return $data;
}

/**
 * Cancel a PaymentIntent — releases the card hold for an outbid bidder.
 */
function impactshop_event_auction_cancel_payment_intent(string $piId): void
{
    if ($piId === '') {
        return;
    }

    $response = wp_remote_post(
        'https://api.stripe.com/v1/payment_intents/' . rawurlencode($piId) . '/cancel',
        [
            'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
            'body'    => '',
            'timeout' => 15,
        ]
    );

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] PI cancel failed for ' . $piId . ': ' . $response->get_error_message());
    } elseif ((int) wp_remote_retrieve_response_code($response) >= 300) {
        error_log('[impactshop-event-auction] PI cancel non-2xx: code=' . wp_remote_retrieve_response_code($response) . ' pi=' . $piId);
    }
}

/**
 * Capture a PaymentIntent — charges the winner's held card.
 */
function impactshop_event_auction_capture_payment_intent(string $piId): bool
{
    if ($piId === '') {
        return false;
    }

    $response = wp_remote_post(
        'https://api.stripe.com/v1/payment_intents/' . rawurlencode($piId) . '/capture',
        [
            'headers' => ['Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY],
            'body'    => '',
            'timeout' => 20,
        ]
    );

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] PI capture failed for ' . $piId . ': ' . $response->get_error_message());
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        error_log('[impactshop-event-auction] PI capture error: code=' . $code . ' pi=' . $piId . ' body=' . substr((string) wp_remote_retrieve_body($response), 0, 400));
        return false;
    }

    return true;
}

// ── End authorize-and-capture helpers ────────────────────────────────────────

function impactshop_event_auction_request_winner_payment(WP_REST_Request $request): WP_REST_Response
{

    $payload = impactshop_event_auction_extract_payload($request);
    $campaignSlug = sanitize_title((string) ($payload['campaign_slug'] ?? ''));
    $campaign = impactshop_event_auction_get_campaign($campaignSlug);
    if (!$campaign) {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    $itemSlug = sanitize_title((string) $request->get_param('item_slug'));
    $lot = impactshop_event_auction_find_lot($campaign, $itemSlug);
    if (!$lot) {
        return new WP_REST_Response(['error' => 'lot_not_found'], 404);
    }

    $current = impactshop_event_auction_current_bid_state($campaignSlug, $itemSlug);
    if (!$current) {
        return new WP_REST_Response(['error' => 'no_closed_winner'], 409);
    }

    $currentStatus = sanitize_key((string) ($current['status'] ?? ''));
    if ($currentStatus === 'paid') {
        return new WP_REST_Response(['error' => 'already_paid'], 409);
    }

    if ($currentStatus === 'payment_pending' && !empty($current['stripe_checkout_url'])) {
        return new WP_REST_Response([
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
            'status' => 'payment_pending',
            'stripe_checkout_url' => esc_url_raw((string) ($current['stripe_checkout_url'] ?? '')),
            'idempotent_replay' => true,
        ], 200);
    }

    if ($currentStatus !== 'closed') {
        return new WP_REST_Response(['error' => 'needs_close_first'], 409);
    }

    $bidder = impactshop_event_auction_get_bidder((string) ($current['bidder_uuid'] ?? ''));
    if (!$bidder) {
        return new WP_REST_Response(['error' => 'bidder_not_found'], 404);
    }

    $returnUrl = esc_url_raw((string) ($payload['return_url'] ?? ($campaign['hero_url'] ?? $campaign['share_url'] ?? home_url('/'))));
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $session = impactshop_event_auction_create_winner_checkout_session([
        'campaign' => $campaign,
        'lot' => $lot,
        'bid' => $current,
        'bidder' => $bidder,
        'return_url' => $returnUrl,
    ]);

    if (!$session || empty($session['id']) || empty($session['url'])) {
        return new WP_REST_Response(['error' => 'stripe_failed'], 502);
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $requestedAt = current_time('mysql', true);
    $updated = $wpdb->update(
        $table,
        [
            'status' => 'payment_pending',
            'stripe_session_id' => (string) $session['id'],
            'stripe_checkout_url' => (string) $session['url'],
            'return_url' => $returnUrl,
            'payment_requested_at' => $requestedAt,
        ],
        [
            'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        ],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_REST_Response(['error' => 'payment_request_update_failed'], 500);
    }

    $actor = wp_get_current_user();
    $actorId = $actor instanceof WP_User ? (string) $actor->user_login : 'admin';
    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'winner_payment_requested', $actorId, [
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'stripe_session_id' => (string) $session['id'],
        'bid_amount' => (int) ($current['bid_amount'] ?? 0),
    ]);

    // ── Email + SMS: fizetési link a nyertesnek ────────────────────────────
    $wpLotTitle    = sanitize_text_field((string) ($lot['item_title'] ?? $itemSlug));
    $wpAmtFmt      = impactshop_event_auction_format_amount((int) ($current['bid_amount'] ?? 0), $currency ?? 'huf');
    $wpLotUrl      = esc_url(trailingslashit((string) ($campaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $itemSlug);
    $wpCheckoutUrl = (string) $session['url'];
    $wpWinnerName  = sanitize_text_field((string) ($bidder['display_name'] ?? ''));
    $wpWinnerEmail = sanitize_email((string) ($bidder['email'] ?? ''));
    $wpWinnerPhone = sanitize_text_field((string) ($bidder['phone'] ?? ''));
    if ($wpWinnerEmail !== '') {
        $wpSubject = 'Gratulálunk! Nyertél a JVK Aukción — ' . $wpLotTitle . ': fizetési link';
        $wpBody    = "Kedves {$wpWinnerName}!\n\n"
            . "Gratulálunk, te nyerted a Jövőnk Vize Gála aukcióját!\n\n"
            . "Tétel: {$wpLotTitle}\n"
            . "Nyertes licitösszeg: {$wpAmtFmt}\n\n"
            . "A tétel véglegesítéséhez kattints az alábbi fizetési linkre:\n"
            . "{$wpCheckoutUrl}\n\n"
            . "Ha kérdésed van, írj nekünk: office@sharity.hu\n\n"
            . "Sharity – JVK Aukció csapata\n"
            . "{$wpLotUrl}";
        impactshop_event_auction_send_email([$wpWinnerEmail], $wpSubject, $wpBody);
    }
    if ($wpWinnerPhone !== '') {
        impactshop_event_auction_send_sms(
            $wpWinnerPhone,
            'Sharity JVK: Gratulálunk, nyertél! ' . $wpLotTitle . ' — ' . $wpAmtFmt . '. Fizess itt: ' . $wpCheckoutUrl
        );
    }
    // ── Admin értesítő: fizetési link kiküldve ────────────────────────────
    impactshop_event_auction_send_email(
        ['office@sharity.hu'],
        '[JVK Aukció] Fizetési link kiküldve — ' . $wpLotTitle . ' (' . $wpWinnerName . ')',
        "Fizetési link kiküldve a nyertesnek.\n\n"
            . "Tétel: {$wpLotTitle}\n"
            . "Nyertes: {$wpWinnerName} ({$wpWinnerEmail})\n"
            . "Összeg: {$wpAmtFmt}\n"
            . "Stripe checkout URL:\n{$wpCheckoutUrl}\n\n"
            . "Tétel oldal:\n{$wpLotUrl}"
    );

    return new WP_REST_Response([
        'bid_uuid' => (string) ($current['bid_uuid'] ?? ''),
        'status' => 'payment_pending',
        'payment_requested_at' => mysql2date('c', $requestedAt, false),
        'stripe_checkout_url' => (string) $session['url'],
    ], 200);
}

function impactshop_event_auction_create_winner_checkout_session(array $order): ?array
{
    $campaign = (array) ($order['campaign'] ?? []);
    $lot = (array) ($order['lot'] ?? []);
    $bid = (array) ($order['bid'] ?? []);
    $bidder = (array) ($order['bidder'] ?? []);

    $bidUuid = sanitize_text_field((string) ($bid['bid_uuid'] ?? ''));
    $campaignSlug = sanitize_title((string) ($campaign['slug'] ?? ''));
    $currency = strtolower((string) ($campaign['currency'] ?? 'huf'));
    $amountDisplay = (float) ((int) ($bid['bid_amount'] ?? 0));
    $amountMinor = impactshop_event_auction_to_minor($amountDisplay, $currency);

    if ($bidUuid === '' || $campaignSlug === '' || $amountMinor <= 0) {
        return null;
    }

    if (impactshop_event_auction_is_staging_runtime() && impactshop_event_auction_stripe_mode() === 'live') {
        error_log('[impactshop-event-auction] Refusing to create live Stripe checkout session on staging runtime.');
        return null;
    }

    $successUrl = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', rest_url('impact/v1/event-auctions/success'));
    $cancelUrl = add_query_arg('bid_uuid', rawurlencode($bidUuid), rest_url('impact/v1/event-auctions/cancel'));

    $productName = sprintf('%s – aukcios nyertes fizetes', (string) ($campaign['title'] ?? 'Sharity aukcio'));
    $productDescription = trim((string) ($lot['artist_name'] ?? '') . ' - ' . (string) ($lot['item_title'] ?? ''));
    if ($productDescription === '-') {
        $productDescription = 'Aukcios tetel';
    }

    $payload = [
        'mode' => 'payment',
        'payment_method_types[0]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][unit_amount]' => $amountMinor,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDescription,
        'line_items[0][quantity]' => 1,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata[event_auction_bid_uuid]' => $bidUuid,
        'metadata[campaign_slug]' => $campaignSlug,
        'metadata[item_slug]' => sanitize_title((string) ($lot['item_slug'] ?? '')),
        'metadata[flow]' => 'event_auction_winner_payment',
        'metadata[bid_amount]' => (string) ((int) ($bid['bid_amount'] ?? 0)),
        'metadata[currency]' => $currency,
    ];

    $displayName = sanitize_text_field((string) ($bidder['display_name'] ?? ''));
    if ($displayName !== '') {
        $payload['customer_creation'] = 'always';
        $payload['metadata[bidder_name]'] = $displayName;
    }

    $email = sanitize_email((string) ($bidder['email'] ?? ''));
    if ($email !== '') {
        $payload['customer_email'] = $email;
    }

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . IMPACT_STRIPE_SECRET_KEY,
        ],
        'body' => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] Stripe checkout request failed: ' . $response->get_error_message());
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300) {
        $requestId = (string) wp_remote_retrieve_header($response, 'request-id');
        error_log('[impactshop-event-auction] Stripe checkout response error: code=' . $code . ' request_id=' . $requestId . ' body=' . substr($body, 0, 500));
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['id']) || empty($data['url'])) {
        return null;
    }

    return [
        'id' => (string) $data['id'],
        'url' => (string) $data['url'],
    ];
}

function impactshop_event_auction_mark_payment_expired(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));
    if ($bidUuid === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
    if (!$row || sanitize_key((string) ($row['status'] ?? '')) !== 'payment_pending') {
        return;
    }

    $wpdb->update(
        $table,
        ['status' => 'closed'],
        ['bid_uuid' => $bidUuid, 'status' => 'payment_pending'],
        ['%s'],
        ['%s', '%s']
    );

    impactshop_event_auction_log_event(
        sanitize_title((string) ($row['campaign_slug'] ?? '')),
        sanitize_title((string) ($row['item_slug'] ?? '')),
        'winner_payment_expired',
        'stripe_webhook',
        [
            'bid_uuid' => $bidUuid,
            'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        ]
    );

    // ── Admin értesítő: fizetési link lejárt ──────────────────────────────
    $expCampaignSlug = sanitize_title((string) ($row['campaign_slug'] ?? ''));
    $expItemSlug     = sanitize_title((string) ($row['item_slug'] ?? ''));
    $expCampaign     = impactshop_event_auction_get_campaign($expCampaignSlug);
    $expLot          = $expCampaign ? impactshop_event_auction_find_lot($expCampaign, $expItemSlug) : null;
    $expLotTitle     = $expLot ? sanitize_text_field((string) ($expLot['item_title'] ?? $expItemSlug)) : $expItemSlug;
    $expAmtFmt       = impactshop_event_auction_format_amount((int) ($row['bid_amount'] ?? 0), 'huf');
    $expLotUrl       = $expCampaign
        ? esc_url(trailingslashit((string) ($expCampaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $expItemSlug)
        : 'https://jovonkvize.hu';
    $expWinnerInfo   = impactshop_event_auction_get_bidder((string) ($row['bidder_uuid'] ?? ''));
    $expWinnerName   = $expWinnerInfo ? sanitize_text_field((string) ($expWinnerInfo['display_name'] ?? '')) : '(ismeretlen)';
    $expWinnerEmail  = $expWinnerInfo ? sanitize_email((string) ($expWinnerInfo['email'] ?? '')) : '';
    impactshop_event_auction_send_email(
        ['office@sharity.hu'],
        '[JVK Aukció] FIGYELEM — Fizetési link lejárt: ' . $expLotTitle . ' (' . $expWinnerName . ')',
        "A nyertes fizetési linkje lejárt — újraküldés szükséges!\n\n"
            . "Tétel: {$expLotTitle}\n"
            . "Nyertes neve: {$expWinnerName}\n"
            . "Nyertes e-mail: {$expWinnerEmail}\n"
            . "Összeg: {$expAmtFmt}\n"
            . "Licit UUID: {$bidUuid}\n\n"
            . "Teendő: Küldj új fizetési linket a request-winner-payment végponton keresztül.\n\n"
            . "Tétel oldal:\n{$expLotUrl}"
    );
    impactshop_event_auction_notify_sms(
        'Sharity JVK ADMIN: Fizetési link lejárt! ' . $expLotTitle . ' — ' . $expWinnerName . '. Újraküldés szükséges.'
    );
}

function impactshop_event_auction_fulfill_payment(string $bidUuid, array $stripeData = []): void
{
    if ($bidUuid === '') {
        return;
    }

    global $wpdb;
    $table = impactshop_event_auction_bids_table_name();
    $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
    if (!$row || sanitize_key((string) ($row['status'] ?? '')) === 'paid') {
        return;
    }

    $wpdb->query('START TRANSACTION');
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE bid_uuid = %s FOR UPDATE",
            $bidUuid
        ),
        ARRAY_A
    );

    if (!$row || sanitize_key((string) ($row['status'] ?? '')) === 'paid') {
        $wpdb->query('ROLLBACK');
        return;
    }

    $ok = $wpdb->update(
        $table,
        [
            'status' => 'paid',
            'stripe_session_id' => sanitize_text_field((string) ($stripeData['stripe_session_id'] ?? ($row['stripe_session_id'] ?? ''))),
            'stripe_payment_intent' => sanitize_text_field((string) ($stripeData['stripe_payment_intent'] ?? ($row['stripe_payment_intent'] ?? ''))),
            'payment_completed_at' => current_time('mysql', true),
        ],
        [
            'bid_uuid' => $bidUuid,
        ],
        ['%s', '%s', '%s', '%s'],
        ['%s']
    );

    if ($ok === false) {
        $wpdb->query('ROLLBACK');
        return;
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event(
        sanitize_title((string) ($row['campaign_slug'] ?? '')),
        sanitize_title((string) ($row['item_slug'] ?? '')),
        'winner_payment_completed',
        'stripe_webhook',
        [
            'bid_uuid' => $bidUuid,
            'stripe_session_id' => sanitize_text_field((string) ($stripeData['stripe_session_id'] ?? '')),
            'stripe_payment_intent' => sanitize_text_field((string) ($stripeData['stripe_payment_intent'] ?? '')),
        ]
    );

    // ── Email + SMS: fizetés sikeres — nyertes + admin ─────────────────────
    $fcCampaignSlug = sanitize_title((string) ($row['campaign_slug'] ?? ''));
    $fcItemSlug     = sanitize_title((string) ($row['item_slug'] ?? ''));
    $fcCampaign     = impactshop_event_auction_get_campaign($fcCampaignSlug);
    $fcLot          = $fcCampaign ? impactshop_event_auction_find_lot($fcCampaign, $fcItemSlug) : null;
    $fcLotTitle     = $fcLot ? sanitize_text_field((string) ($fcLot['item_title'] ?? $fcItemSlug)) : $fcItemSlug;
    $fcAmtFmt       = impactshop_event_auction_format_amount((int) ($row['bid_amount'] ?? 0), 'huf');
    $fcLotUrl       = $fcCampaign
        ? esc_url(trailingslashit((string) ($fcCampaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $fcItemSlug)
        : 'https://jovonkvize.hu';
    $fcWinnerInfo   = impactshop_event_auction_get_bidder((string) ($row['bidder_uuid'] ?? ''));
    $fcWinnerName   = $fcWinnerInfo ? sanitize_text_field((string) ($fcWinnerInfo['display_name'] ?? '')) : '';
    $fcWinnerEmail  = $fcWinnerInfo ? sanitize_email((string) ($fcWinnerInfo['email'] ?? '')) : '';
    $fcWinnerPhone  = $fcWinnerInfo ? sanitize_text_field((string) ($fcWinnerInfo['phone'] ?? '')) : '';
    $fcTimestamp    = current_time('mysql', true);
    if ($fcWinnerEmail !== '') {
        impactshop_event_auction_send_email(
            [$fcWinnerEmail],
            'Fizetés sikeres — ' . $fcLotTitle . ' | Sharity JVK Aukció',
            "Kedves {$fcWinnerName}!\n\n"
                . "Fizetésed sikeresen megérkezett. Gratulálunk a vásárláshoz!\n\n"
                . "Tétel: {$fcLotTitle}\n"
                . "Fizetett összeg: {$fcAmtFmt}\n\n"
                . "Hamarosan felvesszük veled a kapcsolatot a tétel átadásával kapcsolatban.\n\n"
                . "Köszönjük, hogy részt vettél a Jövőnk Vize Gála aukción!\n\n"
                . "Sharity – JVK Aukció csapata\n"
                . $fcLotUrl
        );
    }
    if ($fcWinnerPhone !== '') {
        impactshop_event_auction_send_sms(
            $fcWinnerPhone,
            'Sharity JVK: Fizetés OK! ' . $fcLotTitle . ' — ' . $fcAmtFmt . '. Hamarosan jelentkezünk az átadás részleteivel.'
        );
    }
    impactshop_event_auction_send_email(
        ['office@sharity.hu', 'koncz.veronika@mielemed.hu'],
        '[JVK Aukció] FIZETVE — ' . $fcLotTitle . ': ' . $fcWinnerName . ' (' . $fcAmtFmt . ')',
        "Nyertes fizetése beérkezett!\n\n"
            . "Tétel: {$fcLotTitle}\n"
            . "Nyertes: {$fcWinnerName}\n"
            . "Nyertes e-mail: {$fcWinnerEmail}\n"
            . "Összeg: {$fcAmtFmt}\n"
            . "Időpont (UTC): {$fcTimestamp}\n\n"
            . "Tétel megtekintése:\n{$fcLotUrl}\n\n"
            . "Licit UUID: {$bidUuid}"
    );
    impactshop_event_auction_notify_sms(
        'Sharity JVK ADMIN: FIZETVE — ' . $fcLotTitle . ' | ' . $fcWinnerName . ' | ' . $fcAmtFmt
    );
}

function impactshop_event_auction_maybe_fulfill_from_session(array $session): void
{
    $metadata = (array) ($session['metadata'] ?? []);
    $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));
    if ($bidUuid === '') {
        return;
    }

    if (!impactshop_event_auction_is_paid_session($session)) {
        return;
    }

    impactshop_event_auction_fulfill_payment($bidUuid, [
        'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
        'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
    ]);
}

function impactshop_event_auction_webhook(): WP_REST_Response
{
    if (!impactshop_event_auction_is_configured()) {
        return new WP_REST_Response(['error' => 'not_configured'], 503);
    }

    $payload = (string) file_get_contents('php://input');
    $sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    $secret = impactshop_event_auction_webhook_secret();
    if ($secret === '' || !impactshop_event_auction_verify_stripe_signature($payload, $sigHeader, $secret)) {
        return new WP_REST_Response(['error' => 'invalid_signature'], 400);
    }

    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type'])) {
        return new WP_REST_Response(['error' => 'invalid_payload'], 400);
    }

    $eventType = (string) ($event['type'] ?? '');
    $object = (array) (($event['data']['object'] ?? []) ?: []);

    if ($eventType === 'checkout.session.completed') {
        $mode = sanitize_key((string) ($object['mode'] ?? ''));
        if ($mode === 'setup') {
            impactshop_event_auction_fulfill_card_setup($object);
        } else {
            impactshop_event_auction_maybe_fulfill_from_session($object);
        }
    } elseif ($eventType === 'checkout.session.expired') {
        impactshop_event_auction_mark_payment_expired($object);
    }

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_event_auction_success(WP_REST_Request $request)
{
    $sessionId = sanitize_text_field((string) $request->get_param('session_id'));
    $bidUuid = '';

    if ($sessionId !== '') {
        $session = impactshop_event_auction_fetch_stripe_session($sessionId);
        if (is_array($session)) {
            $metadata = (array) ($session['metadata'] ?? []);
            $bidUuid = sanitize_text_field((string) ($metadata['event_auction_bid_uuid'] ?? ''));

            if ($bidUuid === '') {
                $row = impactshop_event_auction_get_bid_by_session_id($sessionId);
                if ($row && !empty($row['bid_uuid'])) {
                    $bidUuid = sanitize_text_field((string) $row['bid_uuid']);
                }
            }

            if ($bidUuid !== '' && impactshop_event_auction_is_paid_session($session)) {
                impactshop_event_auction_fulfill_payment($bidUuid, [
                    'stripe_session_id' => sanitize_text_field((string) ($session['id'] ?? '')),
                    'stripe_payment_intent' => sanitize_text_field((string) ($session['payment_intent'] ?? '')),
                ]);
            }
        }
    }

    return impactshop_event_auction_redirect_result('success', $bidUuid);
}

function impactshop_event_auction_cancel(WP_REST_Request $request)
{
    $bidUuid = sanitize_text_field((string) $request->get_param('bid_uuid'));

    if ($bidUuid !== '') {
        global $wpdb;
        $table = impactshop_event_auction_bids_table_name();
        $wpdb->update(
            $table,
            ['status' => 'closed'],
            ['bid_uuid' => $bidUuid, 'status' => 'payment_pending'],
            ['%s'],
            ['%s', '%s']
        );

        $row = impactshop_event_auction_get_bid_by_uuid($bidUuid);
        if ($row) {
            impactshop_event_auction_log_event(
                sanitize_title((string) ($row['campaign_slug'] ?? '')),
                sanitize_title((string) ($row['item_slug'] ?? '')),
                'winner_payment_cancelled',
                'stripe_cancel',
                ['bid_uuid' => $bidUuid]
            );
        }
    }

    return impactshop_event_auction_redirect_result('cancel', $bidUuid);
}

function impactshop_event_auction_format_amount(float $amount, string $currency): string
{
    $currency = strtolower($currency);
    if ($currency === 'huf') {
        return number_format((int) round($amount, 0), 0, ',', ' ') . ' Ft';
    }

    return number_format($amount, 2, ',', ' ') . ' ' . strtoupper($currency);
}

// ── Brevo Transactional API + Vonage SMS értesítők ───────────────────────────
//
// Szükséges konstansok (wp-config.php-ban):
//   IMPACTSHOP_EA_BREVO_API_KEY     — Brevo API key (xkeysib-...)
//   IMPACTSHOP_EA_MAIL_FROM         — feladó email (pl. aukcio@sharity.hu)
//   IMPACTSHOP_EA_MAIL_FROM_NAME    — feladó neve (pl. JVK Aukció)
//   IMPACTSHOP_EA_VONAGE_API_KEY    — Vonage API key
//   IMPACTSHOP_EA_VONAGE_API_SECRET — Vonage API secret
//   IMPACTSHOP_EA_VONAGE_FROM       — SMS feladó azonosító (max 11 kar.)
//   IMPACTSHOP_EA_NOTIFY_SMS_PHONES — vesszővel elválasztott tel. számok

/**
 * Email küldés Brevo Transactional API-n keresztül (fallback: wp_mail).
 *
 * @param string[] $to
 */
function impactshop_event_auction_send_email(array $to, string $subject, string $body): void
{
    if (defined('IMPACTSHOP_EA_BREVO_API_KEY') && IMPACTSHOP_EA_BREVO_API_KEY) {
        $sender = [
            'email' => defined('IMPACTSHOP_EA_MAIL_FROM') ? IMPACTSHOP_EA_MAIL_FROM : 'aukcio@sharity.hu',
            'name'  => defined('IMPACTSHOP_EA_MAIL_FROM_NAME') ? IMPACTSHOP_EA_MAIL_FROM_NAME : 'JVK Aukció',
        ];
        $toList = [];
        foreach ($to as $addr) {
            $clean = sanitize_email((string) $addr);
            if ($clean) {
                $toList[] = ['email' => $clean];
            }
        }
        if (empty($toList)) {
            return;
        }
        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'timeout' => 10,
            'headers' => [
                'api-key'      => IMPACTSHOP_EA_BREVO_API_KEY,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'sender'      => $sender,
                'to'          => $toList,
                'subject'     => $subject,
                'textContent' => $body,
            ]),
        ]);
        if (is_wp_error($response)) {
            error_log('[impactshop-event-auction] Brevo email error: ' . $response->get_error_message());
            return;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            error_log('[impactshop-event-auction] Brevo email HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
        }
        return;
    }

    // Fallback: natív wp_mail
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    foreach ($to as $addr) {
        $addr = sanitize_email((string) $addr);
        if ($addr) {
            wp_mail($addr, $subject, $body, $headers);
        }
    }
}

/**
 * SMS küldés Vonage REST API-n keresztül.
 *
 * @param string $to   E.164 formátum (pl. +36301234567)
 * @param string $text SMS szövege
 */
function impactshop_event_auction_send_sms(string $to, string $text): bool
{
    $apiKey    = defined('IMPACTSHOP_EA_VONAGE_API_KEY')    ? IMPACTSHOP_EA_VONAGE_API_KEY    : '';
    $apiSecret = defined('IMPACTSHOP_EA_VONAGE_API_SECRET') ? IMPACTSHOP_EA_VONAGE_API_SECRET : '';
    $from      = defined('IMPACTSHOP_EA_VONAGE_FROM')       ? IMPACTSHOP_EA_VONAGE_FROM       : 'JVKAukcio';

    if (!$apiKey || !$apiSecret) {
        error_log('[impactshop-event-auction] Vonage SMS skip: API credentials not configured');
        return false;
    }

    $response = wp_remote_post('https://rest.nexmo.com/sms/json', [
        'timeout'    => 10,
        'user-agent' => 'ImpactShop-EA/' . IMPACTSHOP_EVENT_AUCTION_VERSION,
        'body'       => [
            'api_key'    => $apiKey,
            'api_secret' => $apiSecret,
            'from'       => $from,
            'to'         => preg_replace('/\s+/', '', $to),
            'text'       => $text,
            'type'       => 'unicode',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('[impactshop-event-auction] Vonage SMS wp_error: ' . $response->get_error_message());
        return false;
    }

    $data   = json_decode(wp_remote_retrieve_body($response), true);
    $status = (string) ($data['messages'][0]['status'] ?? '');
    if ($status !== '0') {
        $errText = (string) ($data['messages'][0]['error-text'] ?? 'unknown');
        error_log('[impactshop-event-auction] Vonage SMS failed to ' . $to . ': ' . $errText);
        return false;
    }

    return true;
}

/**
 * SMS küldés az összes konfigurált értesítési számra.nt-auction] Vonage SMS failed to ' . $to . ': ' . $errText);
        return false;
    }

    return true;
}

/**
 * SMS küldés az összes konfigurált értesítési számra.
 */
function impactshop_event_auction_notify_sms(string $text): void
{
    if (!defined('IMPACTSHOP_EA_NOTIFY_SMS_PHONES') || !IMPACTSHOP_EA_NOTIFY_SMS_PHONES) {
        return;
    }
    $phones = array_filter(array_map('trim', explode(',', (string) IMPACTSHOP_EA_NOTIFY_SMS_PHONES)));
    foreach ($phones as $phone) {
        impactshop_event_auction_send_sms($phone, $text);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// AUTO-CLOSE CRON — lots automatikus lezárása az end_time lejártakor
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Egyedi cron interval: every_minute (60 mp).
 */
function impactshop_event_auction_cron_intervals(array $schedules): array
{
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => 'Every Minute',
        ];
    }
    return $schedules;
}

/**
 * WP-Cron ütemezés biztosítása init-en.
 */
function impactshop_event_auction_cron_schedule(): void
{
    if (!wp_next_scheduled('impactshop_event_auction_auto_close')) {
        wp_schedule_event(time(), 'every_minute', 'impactshop_event_auction_auto_close');
    }
}

/**
 * Cron handler: minden lot-ot átvizsgál, és ha az end_time (snipe-extension-t
 * is figyelembe véve) lejárt és van nyertes licit → automatikusan lezárja.
 */
function impactshop_event_auction_cron_auto_close_handler(): void
{
    $campaigns = impactshop_event_auction_campaigns();
    foreach ($campaigns as $campaignSlug => $campaign) {
        $lots = (array) ($campaign['lots'] ?? []);
        foreach ($lots as $lot) {
            $itemSlug = sanitize_title((string) ($lot['item_slug'] ?? ''));
            if ($itemSlug === '') {
                continue;
            }

            // Csak live státuszú lot-ot érdemes vizsgálni
            $lotStatus = sanitize_key((string) ($lot['status'] ?? 'draft'));
            if ($lotStatus !== 'live') {
                continue;
            }

            // Tényleges end_time (snipe-extension-t figyelembe véve)
            $endTimeIso = impactshop_event_auction_lot_end_time($campaignSlug, $itemSlug, $campaign);
            if ($endTimeIso === '') {
                continue;
            }
            $endTimestamp = strtotime($endTimeIso);
            if ($endTimestamp === false || $endTimestamp === 0) {
                continue;
            }

            // Ha nem járt le, nincs teendő
            if ($endTimestamp > time()) {
                continue;
            }

            // Auto-lezárás
            impactshop_event_auction_auto_close_lot($campaignSlug, $campaign, $lot, $itemSlug);
        }
    }
}

/**
 * Egy lejárt lot automatikus lezárása (cron által hívva).
 * Ugyanazt a logikát követi mint az admin_close REST endpoint.
 * Idempotens: már lezárt lot-ot nem nyúl.
 */
function impactshop_event_auction_auto_close_lot(
    string $campaignSlug,
    array  $campaign,
    array  $lot,
    string $itemSlug
): void {
    global $wpdb;
    $table   = impactshop_event_auction_bids_table_name();
    $actorId = 'cron_auto_close';

    $wpdb->query('START TRANSACTION');
    $current = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE campaign_slug = %s
               AND item_slug = %s
               AND status IN ('winning', 'closed', 'payment_pending', 'paid')
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE",
            $campaignSlug,
            $itemSlug
        ),
        ARRAY_A
    );

    if (!$current) {
        $wpdb->query('ROLLBACK');
        impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'auto_close_no_bid', $actorId, []);
        return;
    }

    $status = sanitize_key((string) ($current['status'] ?? ''));
    if (in_array($status, ['closed', 'payment_pending', 'paid'], true)) {
        $wpdb->query('COMMIT');
        return; // idempotent: már lezárva
    }

    $closedAt = current_time('mysql', true);
    $updated  = $wpdb->update(
        $table,
        ['status' => 'closed', 'closed_at' => $closedAt],
        ['id' => (int) $current['id']],
        ['%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        $wpdb->query('ROLLBACK');
        impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'auto_close_db_error', $actorId, []);
        return;
    }

    $wpdb->query('COMMIT');

    impactshop_event_auction_log_event($campaignSlug, $itemSlug, 'auto_close', $actorId, [
        'bid_uuid'    => (string) ($current['bid_uuid'] ?? ''),
        'bidder_uuid' => (string) ($current['bidder_uuid'] ?? ''),
        'bid_amount'  => (int) ($current['bid_amount'] ?? 0),
    ]);

    // Email + SMS értesítők (azonos mint admin_close)
    $winnerInfo  = impactshop_event_auction_get_bidder((string) ($current['bidder_uuid'] ?? ''));
    $winnerName  = $winnerInfo ? sanitize_text_field((string) ($winnerInfo['display_name'] ?? '')) : '(ismeretlen)';
    $winnerEmail = $winnerInfo ? sanitize_email((string) ($winnerInfo['email'] ?? '')) : '';
    $winnerPhone = $winnerInfo ? sanitize_text_field((string) ($winnerInfo['phone'] ?? '')) : '';
    $lotTitle    = sanitize_text_field((string) ($lot['item_title'] ?? $itemSlug));
    $amtFmt      = impactshop_event_auction_format_amount((int) ($current['bid_amount'] ?? 0), 'huf');
    $lotUrl      = esc_url(trailingslashit((string) ($campaign['hero_url'] ?? 'https://jovonkvize.hu')) . '?lot=' . $itemSlug);

    $notifyTo      = ['office@sharity.hu', 'koncz.veronika@mielemed.hu'];
    $notifySubject = '[JVK Aukció] AUTO-LEZÁRVA — ' . $lotTitle . ': nyertes ' . $winnerName . ' (' . $amtFmt . ')';
    $notifyBody    = "Az aukciós tétel automatikusan lezárásra került (idő lejárt).\n\n"
        . "Tétel: {$lotTitle}\n"
        . "Nyertes neve: {$winnerName}\n"
        . "Nyertes licit összege: {$amtFmt}\n"
        . "Nyertes e-mail: {$winnerEmail}\n"
        . "Nyertes telefon: {$winnerPhone}\n"
        . "Lezárás (UTC): {$closedAt}\n\n"
        . "Tétel oldal:\n{$lotUrl}\n\n"
        . "Licit UUID: " . (string) ($current['bid_uuid'] ?? '');
    impactshop_event_auction_send_email($notifyTo, $notifySubject, $notifyBody);
    impactshop_event_auction_notify_sms(
        'Sharity JVK: AUTO-LEZÁRVA — ' . $lotTitle . ' | Nyertes: ' . $winnerName . ' | ' . $amtFmt . ' | ' . $lotUrl
    );

    // Stripe capture (azonos mint admin_close)
    impactshop_event_auction_maybe_capture_winner($current);
}
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Egy kampány összes lejárt live lot-ját lezárja.
 * Hívható a /public poll-ból és a cron handlerből is.
 */
function impactshop_event_auction_maybe_auto_close_campaign(string $campaignSlug, array $campaign): void
{
    $now = time();
    foreach ((array) ($campaign['lots'] ?? []) as $lot) {
        $itemSlug = sanitize_title((string) ($lot['item_slug'] ?? ''));
        if ($itemSlug === '') {
            continue;
        }
        $lotStatus = sanitize_key((string) ($lot['status'] ?? 'draft'));
        if ($lotStatus !== 'live') {
            continue;
        }
        $endTimeIso = impactshop_event_auction_lot_end_time($campaignSlug, $itemSlug, $campaign);
        if ($endTimeIso === '') {
            continue;
        }
        $endTimestamp = strtotime($endTimeIso);
        if ($endTimestamp === false || $endTimestamp === 0 || $endTimestamp > $now) {
            continue;
        }
        impactshop_event_auction_auto_close_lot($campaignSlug, $campaign, $lot, $itemSlug);
    }
}
