<?php
/**
 * Plugin Name: ImpactShop Identity Panel
 * Description: Shortcode for pseudo ID display, recovery code, and nickname.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_profile_get',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/total',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_profile_total',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_profile_update',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/restore',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_profile_restore',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/refresh-nonce',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_refresh_nonce',
            'permission_callback' => '__return_true',
        ]
    );
});

/**
 * Allow unauthenticated nonce refresh (avoid cookie check block).
 *
 * @param mixed $result Current auth result.
 * @return mixed
 */
function impactshop_identity_allow_refresh_nonce($result)
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/impact/v1/identity/refresh-nonce') !== false) {
        return true;
    }
    return $result;
}
add_filter('rest_authentication_errors', 'impactshop_identity_allow_refresh_nonce', 5);

/**
 * Refresh REST nonce for identity panel.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_refresh_nonce(): WP_REST_Response
{
    return new WP_REST_Response(
        [
            'nonce' => wp_create_nonce('wp_rest'),
        ],
        200
    );
}

add_shortcode('impactshop_identity_panel', 'impactshop_identity_panel_shortcode');
add_shortcode('impactshop_identity_id', 'impactshop_identity_id_shortcode');

add_action('wp_enqueue_scripts', 'impactshop_identity_panel_register_assets');

/**
 * Resolve pseudo ID + recovery code for rendering.
 *
 * @return array{pseudo_id:string,recovery_code:string}
 */
function impactshop_identity_profile_resolve(): array
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        $pseudo_id = impactshop_identity_profile_generate_pseudo_id();
        impactshop_identity_profile_set_cookie($pseudo_id);
    }

    $recovery = impactshop_identity_profile_get_recovery_code($pseudo_id);
    if ($recovery === null) {
        $recovery = impactshop_identity_profile_generate_recovery_code();
        impactshop_identity_profile_store_recovery($pseudo_id, $recovery);
    }

    return [
        'pseudo_id'     => $pseudo_id,
        'recovery_code' => $recovery,
    ];
}

/**
 * Shortcode UI for pseudo ID and recovery tools.
 *
 * @return string
 */
function impactshop_identity_panel_shortcode(): string
{
    impactshop_identity_panel_enqueue_assets();

    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-identity-panel-' . wp_generate_password(6, false, false);
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $recovery_code = esc_html($profile['recovery_code']);

    $html = '<div class="impactshop-identity-panel" id="' . esc_attr($panel_id) . '" ';
    $html .= 'data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<p class="impactshop-identity-greeting" data-role="greeting">Szia, üdvözöllek a Sharity oldalán.</p>';
    $html .= '<h3>Fiókom</h3>';
    $html .= '<p class="impactshop-identity-total" data-role="total-display">Támogatásaim összege: —</p>';
    $html .= '<div class="impactshop-identity-points-compact" data-role="points-compact" hidden>';
    $html .= '<h4>Pontjaid</h4>';
    $html .= '<div class="impactshop-identity-points-summary">';
    $html .= '<span class="impactshop-identity-badge" data-role="points-compact-badge">🌱</span>';
    $html .= '<div class="impactshop-identity-points-meta">';
    $html .= '<div class="impactshop-identity-level" data-role="points-compact-level">Basic</div>';
    $html .= '<div class="impactshop-identity-points-total" data-role="points-compact-total">0 pont</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar"><span data-role="points-compact-bar"></span></div>';
    $html .= '<p data-role="points-compact-text"></p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-message" data-role="account-message" hidden></div>';
    $html .= '<p class="impactshop-identity-hint">Fontos: csak a fiókodban tudod megőrizni az eredményeidet és a jutalmaidat.</p>';
    $html .= '<div class="impactshop-identity-points" data-role="points-section" hidden>';
    $html .= '<div class="impactshop-identity-section-title">';
    $html .= '<h4>Pontjaid</h4>';
    $html .= '<button type="button" class="impactshop-identity-info" data-role="points-info-trigger" aria-label="Pontok információ">i</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-popover" data-role="points-info" hidden>';
    $html .= '<strong>Pontszerzési lehetőségek</strong>';
    $html .= '<ul>';
    $html .= '<li>Impact Shop vásárlás</li>';
    $html .= '<li>Videó megtekintés / szponzorált aktivitás</li>';
    $html .= '<li>Megosztás és ajánlás</li>';
    $html .= '<li>Visszajelzés / aktivitás</li>';
    $html .= '</ul>';
    $html .= '<strong>Előnyök, amik a szintedhez tartoznak</strong>';
    $html .= '<ul>';
    $html .= '<li>Szavazati súly: több pont = nagyobb súly</li>';
    $html .= '<li>Adomány szorzó: magasabb szint = több támogatás</li>';
    $html .= '<li>Kedvezmény kategória: partnertől függő, Basic–Legend</li>';
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-points-summary">';
    $html .= '<span class="impactshop-identity-badge" data-role="points-badge">🌱</span>';
    $html .= '<div class="impactshop-identity-points-meta">';
    $html .= '<div class="impactshop-identity-level" data-role="points-level">Basic</div>';
    $html .= '<div class="impactshop-identity-points-total" data-role="points-total">0 pont</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar"><span data-role="points-progress-bar"></span></div>';
    $html .= '<p data-role="points-progress-text"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-benefits" data-role="points-benefits"></div>';
    $html .= '<div class="impactshop-identity-last-ngo" data-role="last-ngo"></div>';
    $html .= '<div class="impactshop-identity-history">';
    $html .= '<h4>Legutóbbi pontmozgások</h4>';
    $html .= '<ul data-role="points-history"></ul>';
    $html .= '<p class="impactshop-identity-hint" data-role="points-history-empty" hidden>Nincs még pontmozgás.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-vacation" data-role="vacation-section">';
    $html .= '<h4>Vakáció mód</h4>';
    $html .= '<p class="impactshop-identity-hint" data-role="vacation-status">Betöltés…</p>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="vacation-toggle">Bekapcsolás</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-referral" data-role="referral-section">';
    $html .= '<div class="impactshop-identity-section-title">';
    $html .= '<h4>Ajánlói kód</h4>';
    $html .= '<button type="button" class="impactshop-identity-info" data-role="referral-info-trigger" aria-label="Ajánlói kód információ">i</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-popover" data-role="referral-info" hidden>';
    $html .= '<p>Oszd meg a linket ismerőseiddel. Ha regisztrálnak és vásárolnak, mindketten pontokat kaptok.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value" data-role="referral-code">—</code>';
    $html .= '<button type="button" data-role="referral-copy">Másolás</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="referral-link"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-badges" data-role="badges-section" hidden>';
    $html .= '<h4>Legacy Wall</h4>';
    $html .= '<p class="impactshop-identity-hint impactshop-identity-hero-note">A Legacy Wall a megszerzett jelvényeidet gyűjti egy vitrinszerű falon.</p>';
    $html .= '<div class="impactshop-identity-legacy-badges" data-role="legacy-badges"></div>';
    $html .= '<p class="impactshop-identity-hint" data-role="badges-empty" hidden>Még nincs jelvényed.</p>';
    $html .= '<div class="impactshop-identity-herowall" data-role="herowall-summary" hidden>';
    $html .= '<h4>Legacy Pool</h4>';
    $html .= '<p class="impactshop-identity-hint" data-role="herowall-tier"></p>';
    $html .= '<p class="impactshop-identity-hint" data-role="herowall-points"></p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<h4>Fiókom adatai</h4>';
    $html .= '<div id="impactshop-account-top" class="impactshop-identity-anchor"></div>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">' . $pseudo_id . '</code>';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás (ID)</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value impactshop-identity-value--recovery" data-role="recovery-display">' . $recovery_code . '</code>';
    $html .= '<button type="button" data-role="copy-recovery">Másolás (kód)</button>';
    $html .= '<button type="button" data-role="share-both">Megosztás</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Fontos: őrizd meg az azonosító ID-t és a helyreállító kódot! Ne add át másnak! Ezekkel az adatokkal bármikor, bármilyen eszközön eléred a fiókodat.</p>';
    $html .= '<div class="impactshop-identity-save" id="impactshop-account">';
    $html .= '<label class="impactshop-identity-save__label">Mentés jelszókezelőbe (opcionális)</label>';
    $html .= '<form class="impactshop-identity-save-form" data-role="save-form" autocomplete="on" method="post" action="">';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" name="username" data-role="save-username" autocomplete="username" placeholder="Azonosító" value="' . $pseudo_id . '" />';
    $html .= '<input type="password" name="password" data-role="save-password" autocomplete="new-password" placeholder="Helyreállító kód" value="' . $recovery_code . '" />';
    $html .= '<button type="submit" data-role="save-password-manager">Mentés</button>';
    $html .= '</div>';
    $html .= '</form>';
    $html .= '<p class="impactshop-identity-hint">A böngésző felajánlja a mentést, ha támogatja.</p>';
    $html .= '</div>';
    $html .= '<label>Becenév (opcionális)</label>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" data-role="nickname-input" maxlength="32" placeholder="pl. Anna" />';
    $html .= '<button type="button" data-role="save-nickname">Mentés</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-inline-status" data-role="nickname-status"></p>';
    $html .= '<div class="impactshop-identity-restore">';
    $html .= '<h4 id="impactshop-restore-title">Azonosító helyreállítás</h4>';
    $html .= '<p class="impactshop-identity-hint">Új eszközön add meg az azonosítót és a helyreállító kódot.</p>';
    $html .= '<form class="impactshop-identity-restore__form" autocomplete="on">';
    $html .= '<label class="impactshop-identity-restore__label" for="impactshop-restore-id">Azonosító</label>';
    $html .= '<input type="text" id="impactshop-restore-id" data-role="restore-pseudo" name="username" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" placeholder="Azonosító" />';
    $html .= '<label class="impactshop-identity-restore__label" for="impactshop-restore-recovery">Helyreállító kód</label>';
    $html .= '<input type="password" id="impactshop-restore-recovery" data-role="restore-recovery" name="password" autocomplete="current-password" autocapitalize="none" autocorrect="off" spellcheck="false" placeholder="Helyreállító kód" />';
    $html .= '<button type="button" data-role="restore-submit">Helyreállítás</button>';
    $html .= '</form>';
    $html .= '<p class="impactshop-identity-inline-status" data-role="restore-status"></p>';
    $html .= '</div>';

    $html .= '<p class="impactshop-identity-status" data-role="status"></p>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Shortcode for ID-only display.
 *
 * @return string
 */
function impactshop_identity_id_shortcode(): string
{
    impactshop_identity_panel_enqueue_assets();

    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-identity-id-' . wp_generate_password(6, false, false);
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $recovery_code = esc_html($profile['recovery_code']);
    $html = '<div class="impactshop-identity-panel impactshop-identity-panel--compact" id="' . esc_attr($panel_id) . '" ';
    $html .= 'data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<p class="impactshop-identity-greeting" data-role="greeting">Szia, üdvözöllek a Sharity oldalán.</p>';
    $html .= '<h3>Fiókom</h3>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="scroll-account">Fiókom kezelése</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="scroll-restore">Ez nem az én fiókom</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-total" data-role="total-display">Támogatásaim összege: —</p>';
    $html .= '<div class="impactshop-identity-points-compact" data-role="points-compact" hidden>';
    $html .= '<h4>Pontjaid</h4>';
    $html .= '<div class="impactshop-identity-points-summary">';
    $html .= '<span class="impactshop-identity-badge" data-role="points-compact-badge">🌱</span>';
    $html .= '<div class="impactshop-identity-points-meta">';
    $html .= '<div class="impactshop-identity-level" data-role="points-compact-level">Basic</div>';
    $html .= '<div class="impactshop-identity-points-total" data-role="points-compact-total">0 pont</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar"><span data-role="points-compact-bar"></span></div>';
    $html .= '<p data-role="points-compact-text"></p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Fontos: csak a fiókodban tudod megőrizni az eredményeidet és a jutalmaidat.</p>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Register assets for the identity panel.
 *
 * @return void
 */
function impactshop_identity_panel_register_assets(): void
{
    wp_register_style('impactshop-identity-panel', false);
    $script_path = __DIR__ . '/impactshop-identity-panel.js';
    $script_url = plugins_url('impactshop-identity-panel.js', __FILE__);
    $script_version = file_exists($script_path) ? (string)filemtime($script_path) : null;
    wp_register_script('impactshop-identity-panel', $script_url, [], $script_version, true);

    $css = <<<CSS
.impactshop-identity-panel { max-width: 640px; margin: 24px auto; font-family: inherit; color: #0f172a; }
.impactshop-identity-panel--compact { max-width: 460px; }
.impactshop-identity-card { border-radius: 18px; padding: 22px; background: rgba(255,255,255,0.7); border: 1px solid rgba(148,163,184,0.35); box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12); backdrop-filter: blur(16px); position: relative; overflow: hidden; }
.impactshop-identity-card::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at top left, rgba(59,130,246,0.18), transparent 55%), radial-gradient(circle at bottom right, rgba(14,165,233,0.14), transparent 55%); pointer-events: none; }
.impactshop-identity-card h3, .impactshop-identity-card h4 { margin: 0 0 10px; font-weight: 700; }
.impactshop-identity-section-title { display: flex; align-items: center; gap: 10px; }
.impactshop-identity-section-title h4 { margin: 0; }
.impactshop-identity-card .impactshop-identity-info { width: 28px; height: 28px; padding: 0; border-radius: 999px; border: 1px solid rgba(15,23,42,0.2); background: #fff; color: #0f172a; font-weight: 800; font-size: 14px; display: grid; place-items: center; box-shadow: none; }
.impactshop-identity-card .impactshop-identity-info:hover { background: #f1f5f9; }
.impactshop-identity-popover { padding: 12px 14px; border-radius: 12px; background: rgba(15, 23, 42, 0.94); color: #f8fafc; font-size: 13px; line-height: 1.4; display: grid; gap: 8px; max-width: 420px; box-shadow: 0 18px 36px rgba(15, 23, 42, 0.35); }
.impactshop-identity-popover ul { margin: 0; padding-left: 18px; display: grid; gap: 4px; }
.impactshop-identity-popover strong { font-weight: 700; }
.impactshop-identity-greeting { margin: 0 0 12px; font-size: 1rem; font-weight: 600; color: #0f172a; }
.impactshop-identity-total { margin: 6px 0 0; font-weight: 700; color: #0f172a; }
.impactshop-identity-message { margin: 12px 0 0; padding: 12px 14px; border-radius: 12px; background: rgba(14, 116, 144, 0.08); border: 1px solid rgba(14, 116, 144, 0.25); color: #0f172a; font-weight: 600; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.impactshop-identity-message button { background: #0f172a; color: #fff; border: 0; border-radius: 10px; padding: 8px 12px; font-size: 13px; }
.impactshop-identity-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.impactshop-identity-row input { flex: 1; }
.impactshop-identity-card label { display: block; margin-top: 14px; font-weight: 600; }
.impactshop-identity-card input, .impactshop-identity-card select { width: 100%; padding: 12px; border: 1px solid rgba(148,163,184,0.6); border-radius: 12px; background: rgba(255,255,255,0.8); }
.impactshop-identity-card button { padding: 11px 16px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.2); background: #0f172a; color: #fff; cursor: pointer; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18); }
.impactshop-identity-card button:hover { background: #1e293b; }
.impactshop-identity-card code { background: rgba(15,23,42,0.06); padding: 10px 12px; border-radius: 10px; font-weight: 700; letter-spacing: 0.02em; }
.impactshop-identity-value--recovery { background: rgba(14,165,233,0.15); color: #0e7490; }
.impactshop-identity-card hr { margin: 18px 0; border: none; border-top: 1px solid rgba(148,163,184,0.4); }
.impactshop-identity-status { margin-top: 12px; color: #0f172a; min-height: 20px; }
.impactshop-identity-hint { color: #475569; margin-top: 8px; line-height: 1.5; }
.impactshop-identity-save { margin-top: 14px; padding: 12px; border: 1px dashed rgba(148,163,184,0.5); border-radius: 14px; background: rgba(248,250,252,0.75); }
.impactshop-identity-save__label { display: block; font-weight: 600; margin-bottom: 6px; }
.impactshop-identity-inline-status { margin-top: 6px; color: #0f766e; font-weight: 600; min-height: 18px; }
.impactshop-identity-restore { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(148,163,184,0.35); display: grid; gap: 10px; }
.impactshop-identity-restore h4 { margin: 0; font-size: 16px; font-weight: 700; }
.impactshop-identity-restore__label { display: block; font-weight: 600; }
.impactshop-identity-restore__form { display: grid; gap: 10px; }
.impactshop-identity-anchor { scroll-margin-top: 120px; }
.impactshop-identity-restore { scroll-margin-top: 120px; }
.impactshop-identity-points-compact { margin-top: 12px; padding: 14px 14px 10px; border-radius: 16px; border: 1px solid rgba(148,163,184,0.35); background: rgba(248,250,252,0.75); display: grid; gap: 10px; }
.impactshop-identity-points-compact h4 { margin: 0; font-size: 18px; }
.impactshop-identity-points { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(148,163,184,0.35); display: grid; gap: 12px; }
.impactshop-identity-points-summary { display: flex; align-items: center; gap: 14px; }
.impactshop-identity-badge { font-size: 28px; width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.85); display: grid; place-items: center; border: 1px solid rgba(148,163,184,0.4); }
.impactshop-identity-points-meta { display: grid; gap: 4px; }
.impactshop-identity-level { font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase; font-size: 13px; color: #0f172a; }
.impactshop-identity-points-total { font-size: 22px; font-weight: 800; color: #0f172a; }
.impactshop-identity-progress { display: grid; gap: 6px; }
.impactshop-identity-progress-bar { height: 8px; background: rgba(148,163,184,0.35); border-radius: 999px; overflow: hidden; }
.impactshop-identity-progress-bar span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #38bdf8, #2563eb); border-radius: 999px; transition: width 0.4s ease; }
.impactshop-identity-benefits { display: grid; gap: 6px; font-size: 13px; color: #1e293b; }
.impactshop-identity-benefits span { display: inline-flex; gap: 8px; align-items: center; }
.impactshop-identity-last-ngo { padding: 10px 12px; border-radius: 12px; border: 1px dashed rgba(148,163,184,0.5); background: rgba(248,250,252,0.8); display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: 13px; }
.impactshop-identity-history ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
.impactshop-identity-history li { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: #0f172a; padding: 8px 10px; border-radius: 10px; background: rgba(255,255,255,0.65); border: 1px solid rgba(148,163,184,0.25); }
.impactshop-identity-history li span { font-weight: 600; }
.impactshop-identity-vacation, .impactshop-identity-referral { display: grid; gap: 8px; }
.impactshop-identity-referral code { font-size: 14px; }
.impactshop-identity-referral p { word-break: break-word; }
.impactshop-identity-badges { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(148,163,184,0.35); display: grid; gap: 10px; }
.impactshop-identity-legacy-badges { display: flex; flex-wrap: wrap; gap: 8px; }
.impactshop-legacy-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.85); border: 1px solid rgba(148,163,184,0.35); font-size: 12px; font-weight: 600; color: #0f172a; }
.impactshop-legacy-badge-icon { font-size: 14px; }
.impactshop-identity-herowall { padding: 12px; border-radius: 14px; border: 1px dashed rgba(148,163,184,0.45); background: rgba(248,250,252,0.9); display: grid; gap: 6px; }
@media (max-width: 640px) {
  .impactshop-identity-row { flex-direction: column; align-items: stretch; }
  .impactshop-identity-card button { width: 100%; }
}
CSS;
    wp_add_inline_style('impactshop-identity-panel', $css);

    $nonce = wp_create_nonce('wp_rest');
    wp_add_inline_script(
        'impactshop-identity-panel',
        'window.impactshopIdentityPanel = window.impactshopIdentityPanel || {};'
        . 'window.impactshopIdentityPanel.restNonce = ' . wp_json_encode($nonce) . ';',
        'before'
    );
}

/**
 * Enqueue assets for the identity panel.
 *
 * @return void
 */
function impactshop_identity_panel_enqueue_assets(): void
{
    static $enqueued = false;
    if ($enqueued) {
        return;
    }

    wp_enqueue_style('impactshop-identity-panel');
    wp_enqueue_script('impactshop-identity-panel');
    $enqueued = true;
}

/**
 * Require REST nonce for mutating endpoints.
 *
 * @param WP_REST_Request $request REST request.
 * @return bool
 */
function impactshop_identity_require_nonce(WP_REST_Request $request): bool
{
    $nonce = $request->get_header('X-WP-Nonce');
    return (bool)$nonce && wp_verify_nonce($nonce, 'wp_rest');
}

/**
 * Get the current profile for the pseudo ID cookie.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_profile_get(): WP_REST_Response
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        $pseudo_id = impactshop_identity_profile_generate_pseudo_id();
        impactshop_identity_profile_set_cookie($pseudo_id);
    }

    $nickname = impactshop_identity_profile_load($pseudo_id);
    $recovery = impactshop_identity_profile_get_recovery_code($pseudo_id);
    if ($recovery === null) {
        $recovery = impactshop_identity_profile_generate_recovery_code();
        impactshop_identity_profile_store_recovery($pseudo_id, $recovery);
    }
    $response = new WP_REST_Response(
        [
            'pseudo_id'     => $pseudo_id,
            'nickname'      => $nickname,
            'recovery_code' => $recovery,
            'last_ngo'      => impactshop_identity_profile_last_ngo($pseudo_id),
        ],
        200
    );
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    $response->header('Pragma', 'no-cache');
    $response->header('Vary', 'Cookie');
    return $response;
}

/**
 * Update nickname for current pseudo ID.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_update(WP_REST_Request $request): WP_REST_Response
{
    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? (string)$params['pseudo_id'] : '';
    $nickname = isset($params['nickname']) ? (string)$params['nickname'] : '';
    $pseudo_id = strtolower($pseudo_id);

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $cookie_pseudo = impactshop_identity_profile_cookie();
    if ($cookie_pseudo === '' || strtolower($cookie_pseudo) !== $pseudo_id) {
        return new WP_REST_Response(['message' => 'Azonosító nem egyezik a böngésző cookie-val.'], 403);
    }

    $nickname = sanitize_text_field($nickname);
    $nickname = trim($nickname);
    if ($nickname !== '' && !impactshop_identity_profile_valid_nickname($nickname)) {
        return new WP_REST_Response(['message' => 'Érvénytelen becenév.'], 400);
    }

    impactshop_identity_profile_store($pseudo_id, $nickname);
    return new WP_REST_Response(['status' => 'ok', 'nickname' => $nickname], 200);
}

/**
 * Get total donation amount for current pseudo ID.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_profile_total(): WP_REST_Response
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['total_huf' => 0, 'pseudo_id' => ''], 200);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $sum = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount_huf) FROM {$table}
             WHERE LOWER(pseudo_id) = %s
               AND status IN ('approved','pending')",
            strtolower($pseudo_id)
        )
    );

    return new WP_REST_Response(
        [
            'total_huf' => (int) ($sum ?: 0),
            'pseudo_id' => $pseudo_id,
        ],
        200
    );
}

/**
 * Restore pseudo ID using recovery code.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_restore(WP_REST_Request $request): WP_REST_Response
{
    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? (string)$params['pseudo_id'] : '';
    $recovery_code = isset($params['recovery_code']) ? (string)$params['recovery_code'] : '';
    $pseudo_id = strtolower($pseudo_id);
    $recovery_code = impactshop_identity_profile_normalize_recovery($recovery_code);

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown';
    $rate_key = 'impactshop_restore_rate_' . hash_hmac('sha256', $ip, wp_salt('impactshop_restore_rate'));
    $attempts = (int)get_transient($rate_key);
    if ($attempts >= 5) {
        return new WP_REST_Response([
            'message'     => 'Túl sok helyreállítási kísérlet. Próbáld újra később.',
            'retry_after' => HOUR_IN_SECONDS,
        ], 429);
    }

    if (!preg_match('/^[A-Z0-9]{12}$/', $recovery_code)) {
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);
        return new WP_REST_Response(['message' => 'Érvénytelen helyreállító kód formátum.'], 400);
    }

    $stored = impactshop_identity_profile_get_recovery_code($pseudo_id);
    if ($stored === null) {
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);
        return new WP_REST_Response(['message' => 'Nincs tárolt helyreállító kód.'], 404);
    }
    $stored_normalized = impactshop_identity_profile_normalize_recovery($stored);
    if ($recovery_code === '' || !hash_equals($stored_normalized, $recovery_code)) {
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);
        return new WP_REST_Response(['message' => 'Helyreállító kód nem egyezik.'], 403);
    }

    impactshop_identity_profile_set_cookie($pseudo_id);
    delete_transient($rate_key);
    return new WP_REST_Response(['status' => 'ok', 'pseudo_id' => $pseudo_id], 200);
}

/**
 * Read pseudo ID from cookie.
 *
 * @return string
 */
function impactshop_identity_profile_cookie(): string
{
    if (empty($_COOKIE['impactshop_pseudo_id']) || !is_string($_COOKIE['impactshop_pseudo_id'])) {
        return '';
    }
    return strtolower(sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])));
}

/**
 * Validate pseudo ID format.
 *
 * @param string $pseudo_id Pseudo ID to validate.
 * @return bool
 */
function impactshop_identity_profile_valid_pseudo(string $pseudo_id): bool
{
    if (function_exists('impactshop_pin_valid_pseudo')) {
        return impactshop_pin_valid_pseudo($pseudo_id);
    }
    return (bool)preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id);
}

/**
 * Validate nickname format.
 *
 * @param string $nickname Nickname to validate.
 * @return bool
 */
function impactshop_identity_profile_valid_nickname(string $nickname): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($nickname) : strlen($nickname);
    if ($length < 2 || $length > 32) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}0-9 _.\-]+$/u', $nickname);
}

/**
 * Load nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string|null
 */
function impactshop_identity_profile_load(string $pseudo_id): ?string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return null;
    }
    return isset($value['nickname']) && is_string($value['nickname']) ? $value['nickname'] : null;
}

/**
 * Store nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $nickname Nickname (can be empty to clear).
 * @return void
 */
function impactshop_identity_profile_store(string $pseudo_id, string $nickname): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $recovery = is_array($existing) && isset($existing['recovery_code']) ? (string)$existing['recovery_code'] : null;
    $last_ngo = is_array($existing) && isset($existing['last_ngo']) ? (string)$existing['last_ngo'] : '';
    if ($recovery === null) {
        $recovery = impactshop_identity_profile_generate_recovery_code();
    }
    $payload = [
        'nickname'   => $nickname,
        'recovery_code' => $recovery,
        'last_ngo'   => $last_ngo,
        'updated_at' => current_time('mysql', 1),
    ];

    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/**
 * Get recovery code for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string|null
 */
function impactshop_identity_profile_get_recovery_code(string $pseudo_id): ?string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return null;
    }
    return isset($value['recovery_code']) && is_string($value['recovery_code']) ? $value['recovery_code'] : null;
}

/**
 * Store recovery code for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $recovery_code Recovery code.
 * @return void
 */
function impactshop_identity_profile_store_recovery(string $pseudo_id, string $recovery_code): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $payload = [
        'nickname'      => is_array($existing) && isset($existing['nickname']) ? (string)$existing['nickname'] : '',
        'recovery_code' => $recovery_code,
        'last_ngo'      => is_array($existing) && isset($existing['last_ngo']) ? (string)$existing['last_ngo'] : '',
        'updated_at'    => current_time('mysql', 1),
    ];

    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/**
 * Generate a short recovery code.
 *
 * @return string
 */
function impactshop_identity_profile_generate_recovery_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = 12;
    $raw = '';
    for ($i = 0; $i < $length; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
}

/**
 * Get last supported NGO slug for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string
 */
function impactshop_identity_profile_last_ngo(string $pseudo_id): string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return '';
    }
    return isset($value['last_ngo']) && is_string($value['last_ngo']) ? $value['last_ngo'] : '';
}

/**
 * Store last supported NGO slug for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $slug NGO slug (empty to clear).
 * @return void
 */
function impactshop_identity_profile_store_last_ngo(string $pseudo_id, string $slug): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $existing = get_option($key, null);
    $payload = [
        'nickname'      => is_array($existing) && isset($existing['nickname']) ? (string)$existing['nickname'] : '',
        'recovery_code' => is_array($existing) && isset($existing['recovery_code']) ? (string)$existing['recovery_code'] : impactshop_identity_profile_generate_recovery_code(),
        'last_ngo'      => sanitize_title($slug),
        'updated_at'    => current_time('mysql', 1),
    ];

    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/**
 * Normalize recovery code for comparison (uppercase, strip non-alnum).
 *
 * @param string $recovery_code Raw recovery code.
 * @return string
 */
function impactshop_identity_profile_normalize_recovery(string $recovery_code): string
{
    $recovery_code = strtoupper(trim($recovery_code));
    return preg_replace('/[^A-Z0-9]/', '', $recovery_code) ?? '';
}

/**
 * Build option key for pseudo profile.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string
 */
function impactshop_identity_profile_option_key(string $pseudo_id): string
{
    $hash = hash_hmac('sha256', $pseudo_id, wp_salt('impactshop_profile'));
    return 'impactshop_pseudo_profile_' . $hash;
}

/**
 * Generate pseudo ID (base36).
 *
 * @return string
 */
function impactshop_identity_profile_generate_pseudo_id(): string
{
    $raw = '';
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    for ($i = 0; $i < 12; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $raw;
}

/**
 * Set pseudo ID cookie on client.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return void
 */
function impactshop_identity_profile_set_cookie(string $pseudo_id): void
{
    $secure = is_ssl();
    if (PHP_VERSION_ID >= 70300) {
        setcookie('impactshop_pseudo_id', $pseudo_id, [
            'expires'  => time() + (365 * DAY_IN_SECONDS),
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('impactshop_pseudo_id', $pseudo_id, time() + (365 * DAY_IN_SECONDS), '/; samesite=Lax', '', $secure, false);
    }
}
