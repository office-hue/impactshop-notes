<?php
/**
 * Plugin Name: ImpactShop UI Lock Guard
 * Description: Stabilitási guard az Impact Challenge UI-hoz (régi floating tabs tiltása + fallback action bar + maintenance lock).
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SHARITY_UI_LOCK_VOTE_CUTOFF')) {
    define('SHARITY_UI_LOCK_VOTE_CUTOFF', '2026-06-30 23:59:59');
}

function sharity_ui_lock_is_impact_challenge_request(): bool {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($uri === '') {
        return false;
    }

    $path = (string) parse_url($uri, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }

    $base_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    if ($base_path !== '' && $base_path !== '/' && str_starts_with($path, $base_path)) {
        $path = substr($path, strlen($base_path));
        if ($path === '') {
            $path = '/';
        }
    }

    return (bool) preg_match('~^/impact-challenge/?$~', $path);
}

function sharity_ui_lock_is_impact_challenge_rest_request($request): bool {
    if (!($request instanceof WP_REST_Request)) {
        return false;
    }

    $route = (string) $request->get_route();
    return str_starts_with($route, '/impact/v1/ads-watch');
}

function sharity_ui_lock_rest_locked_response(string $message, int $status = 503): WP_REST_Response {
    return new WP_REST_Response([
        'success' => false,
        'error' => 'impact_challenge_paused',
        'message' => $message,
        'restart_url' => 'https://factlens.eu/vb2026/',
    ], $status);
}

function sharity_ui_lock_get_frozen_tally(): array {
    global $wpdb;

    $table_votes = $wpdb->prefix . 'impactshop_ads_votes';
    $cutoff = SHARITY_UI_LOCK_VOTE_CUTOFF;
    $cutoff_ts = strtotime($cutoff) ?: 0;
    $quarter_key = function_exists('impactshop_ads_get_current_quarter_key') && $cutoff_ts > 0
        ? (string) impactshop_ads_get_current_quarter_key($cutoff_ts)
        : '';

    if ($quarter_key !== '') {
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ngo_slug, SUM(vote_weight) AS votes
                 FROM {$table_votes}
                 WHERE quarter_key = %s
                   AND created_at <= %s
                 GROUP BY ngo_slug
                 ORDER BY votes DESC",
                $quarter_key,
                $cutoff
            ),
            ARRAY_A
        );
    } else {
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ngo_slug, SUM(vote_weight) AS votes
                 FROM {$table_votes}
                 WHERE created_at <= %s
                 GROUP BY ngo_slug
                 ORDER BY votes DESC",
                $cutoff
            ),
            ARRAY_A
        );
    }

    $tally = [];
    foreach ((array) $results as $row) {
        $slug = sanitize_title((string) ($row['ngo_slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $ngo = function_exists('impactshop_ads_watch_get_ngo_by_slug')
            ? impactshop_ads_watch_get_ngo_by_slug($slug)
            : null;

        $tally[] = [
            'ngo_slug' => $slug,
            'ngo_name' => (string) ($ngo['name'] ?? $slug),
            'ngo_logo' => (string) ($ngo['logo'] ?? ''),
            'votes' => (int) ($row['votes'] ?? 0),
        ];
    }

    return [
        'quarter_key' => $quarter_key !== '' ? $quarter_key : null,
        'cutoff' => $cutoff,
        'donation_pool' => function_exists('impactshop_ads_get_pool_for_quarter')
            ? impactshop_ads_get_pool_for_quarter($quarter_key)
            : 500000,
        'rows' => $tally,
    ];
}

function sharity_ui_lock_should_render(): bool {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_starts_with($uri, '/wp-login.php') || str_starts_with($uri, '/wp-json/')) {
        return false;
    }
    return true;
}

add_action('wp_head', function () {
    if (!sharity_ui_lock_should_render()) {
        return;
    }
    echo '<style>'
       . '.ads-watch-floating-tabs{display:none!important;}'
       . '.impact-challenge-maintenance{position:relative;overflow:hidden;margin:18px auto 22px;padding:24px 22px;border-radius:28px;background:linear-gradient(135deg,rgba(13,18,36,.97) 0%,rgba(20,53,104,.95) 55%,rgba(16,185,129,.88) 100%);box-shadow:0 24px 80px rgba(2,8,23,.28);border:1px solid rgba(255,255,255,.16);color:#fff}'
       . '.impact-challenge-maintenance::before{content:"";position:absolute;inset:-20% auto auto -10%;width:220px;height:220px;border-radius:999px;background:radial-gradient(circle,rgba(255,255,255,.24) 0%,rgba(255,255,255,0) 72%);pointer-events:none}'
       . '.impact-challenge-maintenance__eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.12);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}'
       . '.impact-challenge-maintenance__title{margin:14px 0 10px;font-size:clamp(28px,4vw,42px);line-height:1.05;font-weight:900}'
       . '.impact-challenge-maintenance__copy{max-width:760px;font-size:16px;line-height:1.7;color:rgba(255,255,255,.92)}'
       . '.impact-challenge-maintenance__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}'
       . '.impact-challenge-maintenance__link{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:999px;background:#fff;color:#0f172a!important;font-weight:800;text-decoration:none;box-shadow:0 10px 26px rgba(15,23,42,.18)}'
       . '.impact-challenge-maintenance__meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;color:rgba(255,255,255,.78);font-size:13px}'
       . 'body.impact-challenge-is-paused #btn-watch-ad,body.impact-challenge-is-paused #btn-allocate-votes,body.impact-challenge-is-paused #btn-change-ngo,body.impact-challenge-is-paused #btn-show-more-ngos,body.impact-challenge-is-paused #btn-show-all-ngos,body.impact-challenge-is-paused .btn-quick-vote,body.impact-challenge-is-paused #auto-vote-enabled,body.impact-challenge-is-paused #vote-amount-input,body.impact-challenge-is-paused .step-pill,body.impact-challenge-is-paused [data-role="ads-watch-tab"],body.impact-challenge-is-paused #presence-confirm,body.impact-challenge-is-paused #btn-skip-education,body.impact-challenge-is-paused #btn-skip-video,body.impact-challenge-is-paused #btn-resume-ad{pointer-events:none!important;opacity:.45!important;filter:grayscale(1)!important}'
       . 'body.impact-challenge-is-paused #btn-watch-ad .btn-text::after{content:" (szünetel)";}'
       . 'body.impact-challenge-is-paused #ads-watch-video,body.impact-challenge-is-paused #ads-watch-ngo,body.impact-challenge-is-paused #ads-watch-vote{position:relative}'
       . 'body.impact-challenge-is-paused #ads-watch-video::after,body.impact-challenge-is-paused #ads-watch-ngo::after,body.impact-challenge-is-paused #ads-watch-vote::after{content:"A játék átmenetileg szünetel.";position:absolute;inset:auto 16px 16px auto;padding:8px 12px;border-radius:999px;background:rgba(15,23,42,.86);color:#fff;font-size:12px;font-weight:700;letter-spacing:.02em}'
       . '@media(max-width:768px){.impact-challenge-maintenance{margin:14px 0 18px;padding:20px 16px;border-radius:22px}.impact-challenge-maintenance__copy{font-size:15px}.impact-challenge-maintenance__actions{flex-direction:column}.impact-challenge-maintenance__link{width:100%}}'
       . '</style>';
}, 9999);

add_filter('body_class', function (array $classes): array {
    if (sharity_ui_lock_should_render() && sharity_ui_lock_is_impact_challenge_request()) {
        $classes[] = 'impact-challenge-is-paused';
    }
    return $classes;
});

add_action('wp_footer', function () {
    if (!sharity_ui_lock_should_render()) {
        return;
    }

    if (sharity_ui_lock_is_impact_challenge_request()) {
        echo '<script>(function(){'
           . 'document.addEventListener("DOMContentLoaded",function(){'
           . 'var root=document.getElementById("impactshop-ads-watch")||document.body;'
           . 'if(root&& !document.getElementById("impact-challenge-maintenance-banner")){'
           . 'var banner=document.createElement("section");'
           . 'banner.id="impact-challenge-maintenance-banner";'
           . 'banner.className="impact-challenge-maintenance";'
           . 'banner.innerHTML=' . wp_json_encode(
               '<div class="impact-challenge-maintenance__eyebrow">Átmeneti szünet</div>'
               . '<h2 class="impact-challenge-maintenance__title">Az Impact Challenge hamarosan újraindul.</h2>'
               . '<div class="impact-challenge-maintenance__copy">A mai aktivitás és szavazás átmenetileg le van állítva, ezért az oldalon az aktivitási gombok most nem használhatók. Addig nézz be az új játékunkba, ahol már elindult a következő élmény.</div>'
               . '<div class="impact-challenge-maintenance__actions"><a class="impact-challenge-maintenance__link" href="https://factlens.eu/vb2026/" target="_blank" rel="noopener">Ugrás az új játékhoz: factlens.eu/vb2026</a></div>'
               . '<div class="impact-challenge-maintenance__meta"><span>A mai szavazatok: 0</span><span>Rangsor: 2026.06.30. 23:59:59</span><span>Aktivitási gombok: fagyasztva</span></div>'
           ) . ';'
           . 'root.insertBefore(banner,root.firstChild);'
           . '}'
           . 'var messageText=document.querySelector(\'[data-role="ads-watch-message-text"]\');'
           . 'if(messageText){messageText.textContent="Hamarosan újraindulunk. Addig látogass el az új játékunk oldalára: factlens.eu/vb2026";}'
           . 'var liveActivity=document.getElementById("live-activity-value");'
           . 'if(liveActivity){liveActivity.textContent="0 szavazat";}'
           . 'var chance=document.getElementById("chance-value");'
           . 'if(chance){chance.textContent="hamarosan";}'
           . 'var availableVotesInline=document.getElementById("available-votes-inline");'
           . 'if(availableVotesInline){availableVotesInline.textContent="0";}'
           . 'var voteInput=document.getElementById("vote-amount-input");'
           . 'if(voteInput){voteInput.value="";voteInput.placeholder="A játék szünetel";}'
           . 'var autoVote=document.getElementById("auto-vote-enabled");'
           . 'if(autoVote){autoVote.checked=false;}'
           . '});'
           . '})();</script>';
    }

    if (function_exists('impactshop_action_bar_render')) {
        return; // primary action bar exists
    }

    $base = esc_url(home_url('/impact-challenge/'));
    $shop = esc_url(home_url('/impactshop/'));

    echo '<style>'
       . '.sharity-action-bar-fallback{position:fixed;left:50%;transform:translateX(-50%);bottom:10px;z-index:10030;width:min(980px,calc(100vw - 18px));display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;background:rgba(15,23,42,.96);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:6px}'
       . '.sharity-action-bar-fallback a{display:flex;align-items:center;justify-content:center;gap:6px;color:#fff;text-decoration:none;font-weight:700;font-size:13px;background:rgba(30,64,175,.45);padding:10px 8px;border-radius:10px}'
       . '@media(max-width:768px){.sharity-action-bar-fallback{grid-template-columns:repeat(4,minmax(0,1fr));width:calc(100vw - 10px);bottom:6px}.sharity-action-bar-fallback a{font-size:12px;padding:10px 6px}}'
       . 'body{padding-bottom:86px}'
       . '</style>';

    echo '<nav class="sharity-action-bar-fallback" aria-label="UI lock fallback">'
       . '<a href="' . $base . '#ads-watch-video">🎬 Videó</a>'
       . '<a href="' . $base . '#impactshop-offerwall">🎁 Feladatok</a>'
       . '<a href="' . $shop . '">🛍️ Impact Shop</a>'
       . '<a href="' . $base . '#impactshop-ads-watch">📊 Pontok</a>'
       . '</nav>';
}, 9999);

add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if (!sharity_ui_lock_is_impact_challenge_rest_request($request)) {
        return $result;
    }

    $route = (string) $request->get_route();
    $method = strtoupper((string) $request->get_method());

    if ($route === '/impact/v1/ads-watch/status' && $method === 'GET') {
        return null;
    }

    if ($route === '/impact/v1/ads-watch/tally' && $method === 'GET') {
        return null;
    }

    if ($method !== 'POST') {
        return $result;
    }

    if (in_array($route, [
        '/impact/v1/ads-watch/view',
        '/impact/v1/ads-watch/education',
        '/impact/v1/ads-watch/allocate',
        '/impact/v1/ads-watch/set-ngo',
        '/impact/v1/ads-watch/set-auto-vote',
    ], true)) {
        return sharity_ui_lock_rest_locked_response(
            'Az Impact Challenge átmenetileg szünetel. Hamarosan újraindulunk, addig nézz be az új játékunkhoz: https://factlens.eu/vb2026/'
        );
    }

    return $result;
}, 10, 3);

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if (!$response instanceof WP_REST_Response) {
        return $response;
    }

    if (!sharity_ui_lock_is_impact_challenge_rest_request($request)) {
        return $response;
    }

    $route = (string) $request->get_route();
    $method = strtoupper((string) $request->get_method());

    if ($route === '/impact/v1/ads-watch/status' && $method === 'GET') {
        $data = $response->get_data();
        if (is_array($data)) {
            $data['today_views'] = 0;
            $data['available_votes'] = 0;
            $data['auto_vote_enabled'] = false;
            $data['lock_notice'] = [
                'title' => 'Az Impact Challenge hamarosan újraindul.',
                'subtitle' => 'A mai aktivitás és szavazás most szünetel.',
                'restart_url' => 'https://factlens.eu/vb2026/',
                'frozen_at' => SHARITY_UI_LOCK_VOTE_CUTOFF,
            ];
            $response->set_data($data);
        }
        return $response;
    }

    if ($route === '/impact/v1/ads-watch/tally' && $method === 'GET') {
        $frozen = sharity_ui_lock_get_frozen_tally();
        $rows = $frozen['rows'];
        $pool = (int) ($frozen['donation_pool'] ?? 500000);
        $total_votes = 0;

        foreach ($rows as $index => $row) {
            $votes = (int) ($row['votes'] ?? 0);
            $total_votes += $votes;
            $rows[$index]['rank'] = $index + 1;
            $rows[$index]['percentage'] = $total_votes > 0 ? 0 : 0;
            $rows[$index]['allocation_huf'] = 0;
        }

        if ($total_votes > 0) {
            foreach ($rows as $index => $row) {
                $votes = (int) ($row['votes'] ?? 0);
                $ratio = $votes / $total_votes;
                $rows[$index]['percentage'] = round($ratio * 100, 2);
                $rows[$index]['allocation_huf'] = (int) round($pool * $ratio);
            }
        }

        $response->set_data([
            'success' => true,
            'items' => $rows,
            'donation_pool' => $pool,
            'total_votes' => $total_votes,
            'quarter_key' => $frozen['quarter_key'],
            'frozen_at' => $frozen['cutoff'],
            'lock_title' => 'Az Impact Challenge hamarosan újraindul.',
            'lock_subtitle' => 'A rangsor a 2026.06.30. 23:59:59 állapotot mutatja.',
            'restart_url' => 'https://factlens.eu/vb2026/',
        ]);
        return $response;
    }

    return $response;
}, 10, 3);
