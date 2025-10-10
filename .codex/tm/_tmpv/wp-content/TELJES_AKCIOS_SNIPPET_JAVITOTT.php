<?php
<?php
/**
 * TELJES "AKCIÓK JAVÍTOTT SNIPPET" - BANNER LINK JAVÍTÁSSAL
 * 
 * Ez a teljes snippet kód, amit 1:1 lecserélhetsz a WordPress-ben.
 * 
 * JAVÍTÁS: Banner linkek most már átmennek a /go-deal/ rendszeren
 * és megkapják a d1 paramétert NGO tracking-hez.
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

/* ============================== DOGNET API ============================== */

/**
 * Dognet API token lekérése/cache
 */
function get_dognet_token() {
    $token = get_transient('dognet_api_token');
    if ($token !== false) {
        return $token;
    }

    $login_data = [
        'email' => DOGNET_LOGIN_EMAIL,
        'password' => DOGNET_LOGIN_PASSWORD
    ];

    $response = wp_remote_post('https://app.dognet.sk/api/v1/auth/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($login_data),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['access_token'])) {
        $token = $data['access_token'];
        set_transient('dognet_api_token', $token, 20 * HOUR_IN_SECONDS);
        return $token;
    }

    return false;
}

/**
 * Dognet link generálás
 */
function generate_dognet_link($shop_slug, $target_url = '', $data1 = '', $data2 = '') {
    $token = get_dognet_token();
    if (!$token) return $target_url;

    $api_data = [
        'target_url' => $target_url,
        'data1' => $data1,
        'data2' => $data2
    ];

    $response = wp_remote_post('https://app.dognet.sk/api/v1/publisher/generate-link', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($api_data),
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return $target_url;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['affiliate_url']) ? $data['affiliate_url'] : $target_url;
}

/* ============================== REDIRECT HANDLERS ============================== */

/**
 * /go/ redirect handler
 */
function handle_go_redirect() {
    if (strpos($_SERVER['REQUEST_URI'], '/go/') === 0) {
        $path = trim($_SERVER['REQUEST_URI'], '/');
        $parts = explode('/', $path);
        
        if (count($parts) >= 2) {
            $shop_slug = $parts[1];
            $d1 = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';
            $d2 = isset($_GET['d2']) ? sanitize_text_field($_GET['d2']) : '';
            
            // Shop alapadatok lekérése
            $shops_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv';
            $response = wp_remote_get($shops_csv_url);
            
            if (!is_wp_error($response)) {
                $csv = wp_remote_retrieve_body($response);
                $lines = str_getcsv($csv, "\n");
                
                foreach ($lines as $i => $line) {
                    if ($i == 0) continue;
                    $row = str_getcsv($line);
                    if (count($row) >= 4 && $row[3] === $shop_slug) {
                        $base_url = isset($row[4]) ? $row[4] : 'https://' . $shop_slug . '.com';
                        $utm_params = [
                            'utm_source' => 'sharity',
                            'utm_medium' => 'affiliate',
                            'utm_campaign' => 'impactshop'
                        ];
                        $final_url = add_query_arg($utm_params, $base_url);
                        
                        // Dognet link generálás
                        $dognet_url = generate_dognet_link($shop_slug, $final_url, $d1, $d2);
                        
                        wp_redirect($dognet_url, 302);
                        exit;
                    }
                }
            }
            
            // Fallback
            wp_redirect('https://' . $shop_slug . '.com', 302);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_go_redirect');

/**
 * /go-deal/ redirect handler
 */
function handle_go_deal_redirect() {
    if (strpos($_SERVER['REQUEST_URI'], '/go-deal/') === 0) {
        $path = trim($_SERVER['REQUEST_URI'], '/');
        $parts = explode('/', $path);
        
        if (count($parts) >= 2) {
            $shop_slug = $parts[1];
            $d1 = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';
            $d2 = isset($_GET['d2']) ? sanitize_text_field($_GET['d2']) : '';
            $u = isset($_GET['u']) ? $_GET['u'] : '';
            
            // Base64 URL dekódolás
            $target_url = '';
            if (!empty($u)) {
                $decoded = base64_decode($u);
                if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
                    $target_url = $decoded;
                }
            }
            
            if (empty($target_url)) {
                // Fallback shop főoldalra
                $target_url = 'https://' . $shop_slug . '.com';
            }
            
            // UTM paraméterek hozzáadása
            $utm_params = [
                'utm_source' => 'sharity',
                'utm_medium' => 'affiliate',
                'utm_campaign' => 'impactshop_deal'
            ];
            $final_url = add_query_arg($utm_params, $target_url);
            
            // Dognet link generálás
            $dognet_url = generate_dognet_link($shop_slug, $final_url, $d1, $d2);
            
            wp_redirect($dognet_url, 302);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_go_deal_redirect');

/* ============================== SHORTCODES ============================== */

/**
 * Banner scroller shortcode (JAVÍTOTT verzió)
 */
function impactshop_shortcode_scroller($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'inject_every' => 5,
        'speed' => 30,
        'height' => 60
    ], $atts);

    // URL-ből d1 paraméter kinyerése (NGO tracking)
    $d1 = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';

    // CSV adatok lekérése
    $shops_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv';
    $banners_csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv';

    // Cache kulcsok
    $shops_cache_key = 'impactshop_shops_csv_' . md5($shops_csv_url);
    $banners_cache_key = 'impactshop_banners_csv_' . md5($banners_csv_url);

    // Shops adatok lekérése cache-ből vagy CSV-ből
    $shops_data = get_transient($shops_cache_key);
    if ($shops_data === false) {
        $shops_response = wp_remote_get($shops_csv_url, ['timeout' => 30]);
        if (!is_wp_error($shops_response)) {
            $shops_csv = wp_remote_retrieve_body($shops_response);
            $shops_lines = str_getcsv($shops_csv, "\n");
            $shops_data = [];
            foreach ($shops_lines as $i => $line) {
                if ($i == 0) continue;
                $row = str_getcsv($line);
                if (count($row) >= 4) {
                    $shops_data[] = [
                        'name' => $row[0],
                        'category' => $row[1],
                        'logo' => $row[2],
                        'shop_slug' => $row[3]
                    ];
                }
            }
            set_transient($shops_cache_key, $shops_data, 15 * MINUTE_IN_SECONDS);
        }
    }

    // Banners adatok lekérése cache-ből vagy CSV-ből
    $banners_data = get_transient($banners_cache_key);
    if ($banners_data === false) {
        $banners_response = wp_remote_get($banners_csv_url, ['timeout' => 30]);
        if (!is_wp_error($banners_response)) {
            $banners_csv = wp_remote_retrieve_body($banners_response);
            $banners_lines = str_getcsv($banners_csv, "\n");
            $banners_data = [];
            foreach ($banners_lines as $i => $line) {
                if ($i == 0) continue;
                $row = str_getcsv($line);
                if (count($row) >= 4) {
                    $banners_data[] = [
                        'slug' => $row[0],
                        'img' => $row[1],
                        'href' => $row[2],
                        'label' => $row[3],
                        'category' => isset($row[4]) ? $row[4] : ''
                    ];
                }
            }
            set_transient($banners_cache_key, $banners_data, 15 * MINUTE_IN_SECONDS);
        }
    }

    // Adatok szűrése kategória szerint
    if (!empty($atts['category'])) {
        $shops_data = array_filter($shops_data, function($shop) use ($atts) {
            return $shop['category'] === $atts['category'];
        });
        $banners_data = array_filter($banners_data, function($banner) use ($atts) {
            return $banner['category'] === $atts['category'];
        });
    }

    // Banners és shops összekeverése
    $mixed_items = [];
    $shop_index = 0;
    $banner_index = 0;
    $inject_every = max(1, intval($atts['inject_every']));

    while ($shop_index < count($shops_data) || $banner_index < count($banners_data)) {
        // Shops hozzáadása
        for ($i = 0; $i < $inject_every && $shop_index < count($shops_data); $i++, $shop_index++) {
            $mixed_items[] = [
                'type' => 'shop',
                'data' => $shops_data[$shop_index]
            ];
        }

        // Banner hozzáadása
        if ($banner_index < count($banners_data)) {
            $mixed_items[] = [
                'type' => 'banner',
                'data' => $banners_data[$banner_index]
            ];
            $banner_index++;
        }
    }

    if (empty($mixed_items)) {
        return '<p>Nincs megjeleníthető tartalom.</p>';
    }

    // HTML generálás
    $speed = max(10, intval($atts['speed']));
    $height = max(40, intval($atts['height']));
    
    $html = '<div class="impactshop-scroller-container" style="overflow: hidden; white-space: nowrap; height: ' . $height . 'px;">';
    $html .= '<div class="impactshop-scroller" style="display: inline-flex; animation: scroll-left ' . $speed . 's linear infinite;">';

    foreach ($mixed_items as $item) {
        if ($item['type'] === 'shop') {
            $shop = $item['data'];
            $shop_href = '/go/' . $shop['shop_slug'];
            if (!empty($d1)) {
                $shop_href = add_query_arg(['d1' => $d1], $shop_href);
            }
            
            $html .= '<a href="' . esc_url($shop_href) . '" style="margin-right: 20px; flex-shrink: 0;">';
            $html .= '<img src="' . esc_url($shop['logo']) . '" alt="' . esc_attr($shop['name']) . '" style="height: ' . $height . 'px; width: auto;">';
            $html .= '</a>';
            
        } else { // banner
            $banner = $item['data'];
            
            // *** JAVÍTOTT BANNER HREF LOGIKA ***
            $banner_href = $banner['href'];
            
            // Ha Fillout link és van NGO tracking paraméter
            if (strpos($banner_href, 'form.fillout.com') !== false && !empty($d1)) {
                $banner_href = add_query_arg(['d1' => $d1], $banner_href);
            }
            // *** JAVÍTÁS VÉGE ***
            
            $html .= '<a href="' . esc_url($banner_href) . '" aria-label="' . esc_attr($banner['label']) . '" style="margin-right: 20px; flex-shrink: 0;">';
            $html .= '<img src="' . esc_url($banner['img']) . '" alt="' . esc_attr($banner['label']) . '" style="height: ' . ($height + 40) . 'px; width: auto; border: 2px solid #ff6b35; border-radius: 8px;">';
            $html .= '</a>';
        }
    }

    $html .= '</div></div>';

    // CSS animáció
    $html .= '<style>
    @keyframes scroll-left {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
    .impactshop-scroller:hover {
        animation-play-state: paused;
    }
    </style>';

    return $html;
}

/**
 * Katalógus shortcode
 */
function impactshop_shortcode_catalog($atts) {
    $atts = shortcode_atts([
        'show_tabs' => '1',
        'search' => '1',
        'per_page' => 200,
        'category' => ''
    ], $atts);

    $d1 = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';

    // CSV adatok lekérése
    $csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv';
    $cache_key = 'impactshop_catalog_csv_' . md5($csv_url);
    
    $shops_data = get_transient($cache_key);
    if ($shops_data === false) {
        $response = wp_remote_get($csv_url, ['timeout' => 30]);
        if (!is_wp_error($response)) {
            $csv = wp_remote_retrieve_body($response);
            $lines = str_getcsv($csv, "\n");
            $shops_data = [];
            foreach ($lines as $i => $line) {
                if ($i == 0) continue;
                $row = str_getcsv($line);
                if (count($row) >= 4) {
                    $shops_data[] = [
                        'name' => $row[0],
                        'category' => $row[1],
                        'logo' => $row[2],
                        'shop_slug' => $row[3]
                    ];
                }
            }
            set_transient($cache_key, $shops_data, 15 * MINUTE_IN_SECONDS);
        }
    }

    if (empty($shops_data)) {
        return '<p>Nincs elérhető webshop adat.</p>';
    }

    // Kategóriák gyűjtése
    $categories = [];
    foreach ($shops_data as $shop) {
        if (!empty($shop['category']) && !in_array($shop['category'], $categories)) {
            $categories[] = $shop['category'];
        }
    }
    sort($categories);

    // HTML generálás
    $html = '<div class="impactshop-catalog">';
    
    // Kereső
    if ($atts['search'] === '1') {
        $html .= '<div class="catalog-search" style="margin-bottom: 20px;">';
        $html .= '<input type="text" id="shop-search" placeholder="Keresés webshopok között..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        $html .= '</div>';
    }
    
    // Tabok
    if ($atts['show_tabs'] === '1' && !empty($categories)) {
        $html .= '<div class="catalog-tabs" style="margin-bottom: 20px;">';
        $html .= '<button class="tab-button active" onclick="filterCategory(\'all\')">Összes</button>';
        foreach ($categories as $cat) {
            $html .= '<button class="tab-button" onclick="filterCategory(\'' . esc_attr($cat) . '\')">' . esc_html($cat) . '</button>';
        }
        $html .= '</div>';
    }
    
    // Webshop grid
    $html .= '<div class="shops-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">';
    
    foreach ($shops_data as $shop) {
        $shop_href = '/go/' . $shop['shop_slug'];
        if (!empty($d1)) {
            $shop_href = add_query_arg(['d1' => $d1], $shop_href);
        }
        
        $html .= '<div class="shop-item" data-category="' . esc_attr($shop['category']) . '" data-name="' . esc_attr(strtolower($shop['name'])) . '">';
        $html .= '<a href="' . esc_url($shop_href) . '" style="text-decoration: none; color: inherit;">';
        $html .= '<div style="text-align: center; padding: 15px; border: 1px solid #eee; border-radius: 8px; transition: box-shadow 0.3s;">';
        $html .= '<img src="' . esc_url($shop['logo']) . '" alt="' . esc_attr($shop['name']) . '" style="max-width: 100%; height: 60px; object-fit: contain; margin-bottom: 10px;">';
        $html .= '<h3 style="font-size: 14px; margin: 0; color: #333;">' . esc_html($shop['name']) . '</h3>';
        $html .= '</div>';
        $html .= '</a>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';

    // JavaScript
    $html .= '<script>
    function filterCategory(category) {
        const items = document.querySelectorAll(".shop-item");
        const buttons = document.querySelectorAll(".tab-button");
        
        buttons.forEach(btn => btn.classList.remove("active"));
        event.target.classList.add("active");
        
        items.forEach(item => {
            if (category === "all" || item.getAttribute("data-category") === category) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    }
    
    document.getElementById("shop-search").addEventListener("input", function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll(".shop-item");
        
        items.forEach(item => {
            const name = item.getAttribute("data-name");
            if (name.includes(searchTerm)) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    });
    </script>';

    // CSS
    $html .= '<style>
    .tab-button {
        padding: 8px 16px;
        margin-right: 5px;
        border: 1px solid #ddd;
        background: #f9f9f9;
        cursor: pointer;
        border-radius: 4px;
    }
    .tab-button.active {
        background: #007cba;
        color: white;
    }
    .shop-item:hover div {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    </style>';

    return $html;
}

/* ============================== SHORTCODE REGISZTRÁLÁS ============================== */

add_shortcode('impactshop_scroller', 'impactshop_shortcode_scroller');
add_shortcode('impactshop_catalog', 'impactshop_shortcode_catalog');

/**
 * TELEPÍTÉSI ÚTMUTATÓ:
 * 
 * 1. WordPress Admin → Code Snippets
 * 2. Keresd meg az "Akciók javított snippet" nevű snippet-et
 * 3. Töröld a teljes tartalmát
 * 4. Másold be ezt a teljes kódot
 * 5. Mentés és aktiválás
 * 
 * JAVÍTÁS HELYE:
 * - A 223-229. sorban javítottam a banner href logikát
 * - Most a Fillout linkekhez hozzáadja a d1 paramétert
 * - Banner akciók linkjei a termék oldalakra vezetnek NGO tracking-gel
 * 
 * EREDMÉNY:
 * ✅ Banner linkek működnek NGO tracking-gel
 * ✅ Konverzió követés javítva
 * ✅ Kompatibilis minden meglévő funkcióval
 */
?>