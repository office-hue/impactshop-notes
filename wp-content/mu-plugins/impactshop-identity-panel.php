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
add_action('admin_init', 'impactshop_identity_register_broadcast_setting');
add_action('init', 'impactshop_identity_handle_password_manager_save');
add_action('template_redirect', 'impactshop_identity_maybe_complete_profile_return', 1);

function impactshop_identity_register_broadcast_setting(): void
{
    register_setting('reading', 'impactshop_identity_broadcast_message', [
        'type' => 'string',
        'sanitize_callback' => 'impactshop_identity_sanitize_broadcast_message',
        'default' => '',
    ]);

    add_settings_field(
        'impactshop_identity_broadcast_message',
        'ImpactShop – Üzenet (Identity ID)',
        'impactshop_identity_render_broadcast_field',
        'reading'
    );
}

function impactshop_identity_allowed_message_tags(): array
{
    return [
        'a' => [
            'href' => true,
            'target' => true,
            'rel' => true,
        ],
        'br' => true,
        'strong' => true,
        'em' => true,
    ];
}

function impactshop_identity_sanitize_broadcast_message($value): string
{
    $value = is_string($value) ? $value : '';
    $sanitized = wp_kses($value, impactshop_identity_allowed_message_tags());
    $plain = wp_strip_all_tags($sanitized);
    if (mb_strlen($plain) > 300) {
        $plain = mb_substr($plain, 0, 300);
        return esc_html($plain);
    }
    return $sanitized;
}

function impactshop_identity_render_broadcast_field(): void
{
    $value = (string) get_option('impactshop_identity_broadcast_message', '');
    $value = wp_kses($value, impactshop_identity_allowed_message_tags());
    echo '<textarea name="impactshop_identity_broadcast_message" rows="4" cols="60" maxlength="300" style="width:100%;max-width:640px;">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Max 300 karakter. Engedélyezett: <code>&lt;a&gt;</code>, <code>&lt;br&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>.</p>';
}

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
 * Resolve current URL for post-save redirect.
 *
 * @return string
 */
function impactshop_identity_current_url(): string
{
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field((string) wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url('/'), PHP_URL_HOST);
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $uri = wp_kses_bad_protocol($uri, ['http', 'https']);
    $url = $scheme . $host . $uri;
    return esc_url_raw($url);
}

/**
 * Handle password manager save form POST and return user to source page.
 *
 * @return void
 */
function impactshop_identity_handle_password_manager_save(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    if (empty($_POST['impactshop_identity_save_intent'])) {
        return;
    }

    $return_url = '';
    if (!empty($_POST['impactshop_identity_return'])) {
        $return_url = esc_url_raw((string) wp_unslash($_POST['impactshop_identity_return']));
    }
    if ($return_url === '') {
        $return_url = wp_get_referer() ?: home_url('/');
    }
    $redirect = wp_validate_redirect($return_url, home_url('/'));
    wp_safe_redirect($redirect);
    exit;
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
    $current_url = impactshop_identity_current_url();
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $recovery_code = esc_html($profile['recovery_code']);

    $html = '<div class="impactshop-identity-panel" id="' . esc_attr($panel_id) . '" data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div id="impactshop-account-top"></div>';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<div class="impactshop-identity-header">';
    $html .= '<h3>Profilod</h3>';
    $html .= '<p class="impactshop-identity-hint" data-role="greeting"></p>';
    $html .= '<p class="impactshop-identity-hint" data-role="account-message"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-push" data-role="push-section" hidden>';
    $html .= '<h4>Értesítések</h4>';
    $html .= '<p class="impactshop-identity-hint">A profil üzeneteket pushban is elküldjük (ha engedélyezed).</p>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<button type="button" data-role="push-toggle">Értesítések bekapcsolása</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="push-status"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block">';
    $html .= '<h4>Azonosítód</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">' . $pseudo_id . '</code>';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás</button>';
    $html .= '<button type="button" data-role="refresh-profile">Frissítés</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Ezzel kapcsoljuk össze az adományt és a jutalmakat.</p>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value impactshop-identity-value--recovery" data-role="recovery-display">' . $recovery_code . '</code>';
    $html .= '<button type="button" data-role="copy-recovery">Másolás (kód)</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Őrizd meg az azonosítót és a helyreállító kódot.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-save">';
    $html .= '<label class="impactshop-identity-save__label">Mentés jelszókezelőbe (opcionális)</label>';
    $html .= '<form class="impactshop-identity-row" data-role="save-form" method="post" action="' . esc_url(home_url('/')) . '" autocomplete="on">';
    $html .= '<input type="hidden" name="impactshop_identity_save_intent" value="1" />';
    $html .= '<input type="hidden" name="impactshop_identity_return" data-role="save-return" value="' . esc_attr($current_url) . '" />';
    $html .= '<input type="text" name="username" data-role="save-username" autocomplete="username" placeholder="Azonosító" value="' . $pseudo_id . '" />';
    $html .= '<input type="password" name="password" data-role="save-password" autocomplete="current-password" placeholder="Helyreállító kód" value="' . $recovery_code . '" />';
    $html .= '<button type="submit" data-role="save-password-manager">Mentés</button>';
    $html .= '</form>';
    $html .= '<p class="impactshop-identity-hint">A böngésző felajánlhatja a mentést.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block">';
    $html .= '<label>Becenév (opcionális)</label>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" data-role="nickname-input" maxlength="32" placeholder="pl. Anna" />';
    $html .= '<button type="button" data-role="save-nickname">Mentés</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="nickname-status"></p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-points" data-role="points-section" hidden>';
    $html .= '<h4>Szinted és pontjaid</h4>';
    $html .= '<div class="impactshop-identity-row impactshop-identity-points-row">';
    $html .= '<span class="impactshop-identity-badge" data-role="points-badge">🌱</span>';
    $html .= '<div class="impactshop-identity-points-meta">';
    $html .= '<div class="impactshop-identity-level" data-role="points-level">Basic</div>';
    $html .= '<div class="impactshop-identity-total" data-role="points-total">0 pont</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar" data-role="points-progress-bar"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress-text" data-role="points-progress-text"></div>';
    $html .= '<button type="button" class="impactshop-identity-info-trigger" data-role="points-info-trigger">Miért éri meg?</button>';
    $html .= '<div class="impactshop-identity-info" data-role="points-info" hidden>';
    $html .= '<p>A magasabb szint nagyobb adománybónuszt és előnyöket ad. A pontküszöbök tájékoztató jellegűek, a szinteloszlás percentilis alapján is alakul.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-benefits" data-role="points-benefits"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-history">';
    $html .= '<h4>Legutóbbi aktivitás</h4>';
    $html .= '<ul class="impactshop-identity-list" data-role="points-history"></ul>';
    $html .= '<p class="impactshop-identity-hint" data-role="points-history-empty">Még nincs aktivitás.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-lastngo" data-role="last-ngo"></div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-vacation">';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="vacation-status">Szabadság státusz betöltése…</span>';
    $html .= '<button type="button" data-role="vacation-toggle">Szabadság mód</button>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-referral">';
    $html .= '<h4>Ajánlás</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code data-role="referral-code">—</code>';
    $html .= '<button type="button" data-role="referral-copy">Másolás</button>';
    $html .= '<button type="button" data-role="referral-info-trigger">Infó</button>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-hint" data-role="referral-link"></div>';
    $html .= '<div class="impactshop-identity-info" data-role="referral-info" hidden>';
    $html .= '<p>Oszd meg a kódot, hogy jutalmat kapjatok.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-badges" data-role="badges-section">';
    $html .= '<h4>Legacy Wall</h4>';
    $html .= '<div class="impactshop-identity-badge-list" data-role="legacy-badges"></div>';
    $html .= '<p class="impactshop-identity-hint" data-role="badges-empty">Még nincs jelvényed.</p>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-herowall" data-role="herowall-summary">';
    $html .= '<h4>Legacy Pool</h4>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="herowall-tier">—</span>';
    $html .= '<span data-role="herowall-points">—</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-restore">';
    $html .= '<h4 id="impactshop-restore-title">Azonosító helyreállítás</h4>';
    $html .= '<p class="impactshop-identity-hint">Új eszközön add meg az azonosítót és a helyreállító kódot.</p>';
    $html .= '<label class="impactshop-identity-restore__label">Azonosító</label>';
    $html .= '<input type="text" name="impactshop_restore_pseudo" data-role="restore-pseudo" autocomplete="username" placeholder="Azonosító" />';
    $html .= '<label class="impactshop-identity-restore__label">Helyreállító kód</label>';
    $html .= '<input type="password" name="impactshop_restore_recovery" data-role="restore-recovery" autocomplete="current-password" placeholder="Helyreállító kód" />';
    $html .= '<button type="button" data-role="restore-submit">Helyreállítás</button>';
    $html .= '<p class="impactshop-identity-hint" data-role="restore-status"></p>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-status" data-role="status"></p>';
    $html .= '</div></div>';

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
    $current_url = impactshop_identity_current_url();
    $profile = impactshop_identity_profile_resolve();
    $pseudo_id = esc_html($profile['pseudo_id']);
    $recovery_code = esc_html($profile['recovery_code']);
        $broadcast = (string) get_option('impactshop_identity_broadcast_message', '');
    $broadcast = wp_kses($broadcast, impactshop_identity_allowed_message_tags());
    $panel_url = apply_filters('impactshop_identity_panel_url', site_url('/profil'));
    $restore_url = apply_filters('impactshop_identity_restore_url', site_url('/profil') . '#impactshop-restore-title');
    $html = '<div class="impactshop-identity-panel impactshop-identity-panel--compact" id="' . esc_attr($panel_id) . '" data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<div class="impactshop-identity-title">';
    $html .= '<h3>Fiókom</h3>';
    $html .= '<button type="button" class="impactshop-identity-info-trigger" aria-label="Fiókom információ">i</button>';
    $html .= '<div class="impactshop-identity-info-bubble" role="tooltip">';
    $html .= '<p><strong>Fontos:</strong> a fiókodat automatikusan létrehoztuk.</p>';
    $html .= '<ul>';
    $html .= '<li>Ha meg akarod tartani, kattints a <strong>Mentés</strong> gombra, hogy a jelszavaid közé kerüljön.</li>';
    $html .= '<li>Ha máshova mentenéd, használd a <strong>Másolás</strong> gombot.</li>';
    $html .= '<li>Ha később visszajössz és a fiókod nem jelenik meg, kattints a <strong>Helyreállítás</strong> gombra.</li>';
    $html .= '</ul>';
    $html .= '<p>Csak a fiókodban tudod összegyűjteni az előnyöket és a jelvényeket, ezért érdemes elmentened.</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint" data-role="greeting"></p>';
    $html .= '<form class="impactshop-identity-row" data-role="save-form" method="post" action="' . esc_url(home_url('/')) . '" autocomplete="on">';
    $html .= '<input type="hidden" name="impactshop_identity_save_intent" value="1" />';
    $html .= '<input type="hidden" name="impactshop_identity_return" data-role="save-return" value="' . esc_attr($current_url) . '" />';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">' . $pseudo_id . '</code>';
    $html .= '<input type="text" class="impactshop-identity-save-ghost" name="username" data-role="save-username" autocomplete="username" value="' . $pseudo_id . '" />';
    $html .= '<input type="password" class="impactshop-identity-save-ghost" name="password" data-role="save-password" autocomplete="current-password" value="' . $recovery_code . '" />';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás</button>';
    $html .= '<button type="submit" data-role="save-password-manager">Mentés</button>';
    $html .= '</form>';
    $html .= '<span class="impactshop-identity-hidden" data-role="recovery-display">' . $recovery_code . '</span>';
    $html .= '<div class="impactshop-identity-actions">';
    $html .= '<a class="impactshop-identity-link" href="' . esc_url($panel_url . '#impactshop-account-top') . '" target="_blank" rel="noopener">A fiókom kezelése</a>';
    $html .= '<a class="impactshop-identity-link impactshop-identity-link--muted" data-role="identity-restore-link" href="' . esc_url($restore_url) . '" target="_blank" rel="noopener">Ez nem az én fiókom</a>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-compact" data-role="points-compact" hidden>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<span data-role="points-compact-badge">🌱</span>';
    $html .= '<strong data-role="points-compact-level">Basic</strong>';
    $html .= '<span data-role="points-compact-total">0 pont</span>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress">';
    $html .= '<div class="impactshop-identity-progress-bar" data-role="points-compact-bar"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-progress-text" data-role="points-compact-text"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-identity-block impactshop-identity-message" data-role="broadcast-message" hidden>';
    $html .= '<strong>Üzenet</strong>';
    $html .= '<div class="impactshop-identity-message-body">' . $broadcast . '</div>';
    $html .= '</div>';
    $html .= '</div></div>';

    return $html;
}

/**
 * Register assets for the identity panel.
 *
 * @return void
 */
function impactshop_identity_panel_register_assets(): void
{
    $php_asset_version = @filemtime(__FILE__);
    $panel_script_version = max((int) (@filemtime(__DIR__ . '/impactshop-identity-panel.js') ?: 0), (int) ($php_asset_version ?: 0));
    $intl_overlay_version = max((int) (@filemtime(__DIR__ . '/impactshop-identity-panel-intl-overlay.js') ?: 0), (int) ($php_asset_version ?: 0));

    if (!$panel_script_version) {
        $panel_script_version = '1.0.6';
    }
    if (!$intl_overlay_version) {
        $intl_overlay_version = '1.0.2';
    }

    wp_register_style('impactshop-identity-panel', false);
    wp_register_script(
        'impactshop-identity-panel',
        plugins_url('impactshop-identity-panel.js', __FILE__),
        [],
        (string) $panel_script_version,
        true
    );
    wp_register_script(
        'impactshop-identity-panel-intl-overlay',
        plugins_url('impactshop-identity-panel-intl-overlay.js', __FILE__),
        ['impactshop-identity-panel'],
        (string) $intl_overlay_version,
        true
    );

    $css = <<<CSS
.impactshop-identity-panel { max-width: 720px; margin: 24px auto; font-family: inherit; color: #0f172a; }
.impactshop-identity-panel--compact { max-width: 460px; }
.impactshop-identity-card { border-radius: 18px; padding: 22px; background: rgba(255,255,255,0.7); border: 1px solid rgba(148,163,184,0.35); box-shadow: 0 24px 48px rgba(15, 23, 42, 0.12); backdrop-filter: blur(16px); position: relative; overflow: hidden; }
.impactshop-identity-card::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at top left, rgba(59,130,246,0.18), transparent 55%), radial-gradient(circle at bottom right, rgba(14,165,233,0.14), transparent 55%); pointer-events: none; }
.impactshop-identity-card h3, .impactshop-identity-card h4 { margin: 0 0 10px; font-weight: 700; }
.impactshop-identity-title { display: flex; align-items: center; gap: 8px; position: relative; }
.impactshop-identity-info-trigger { width: 22px; height: 22px; border-radius: 999px; border: 0; background: #e2e8f0; color: #0f172a; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.impactshop-identity-info-trigger:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
.impactshop-identity-info-bubble { position: absolute; top: calc(100% + 6px); left: 0; background: #0f172a; color: #fff; padding: 12px 14px; border-radius: 12px; font-size: 13px; width: min(360px, 90vw); box-shadow: 0 14px 30px rgba(15, 23, 42, 0.35); opacity: 0; transform: translateY(6px); transition: opacity .2s ease, transform .2s ease; pointer-events: none; z-index: 5; }
.impactshop-identity-info-bubble p { margin: 0 0 8px; }
.impactshop-identity-info-bubble ul { margin: 0 0 8px; padding-left: 18px; }
.impactshop-identity-info-bubble li { margin: 0 0 6px; }
.impactshop-identity-info-bubble strong { color: #f8fafc; }
.impactshop-identity-title:hover .impactshop-identity-info-bubble,
.impactshop-identity-title:focus-within .impactshop-identity-info-bubble { opacity: 1; transform: translateY(0); pointer-events: auto; }
.impactshop-identity-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.impactshop-identity-row input { flex: 1; }
.impactshop-identity-card label { display: block; margin-top: 14px; font-weight: 600; }
.impactshop-identity-card input, .impactshop-identity-card select { width: 100%; padding: 12px; border: 1px solid rgba(148,163,184,0.6); border-radius: 12px; background: rgba(255,255,255,0.8); }
.impactshop-identity-card button { padding: 11px 16px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.2); background: #0f172a; color: #fff; cursor: pointer; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18); }
.impactshop-identity-card button:hover { background: #1e293b; }
.impactshop-identity-card code { background: rgba(15,23,42,0.06); padding: 10px 12px; border-radius: 10px; font-weight: 700; letter-spacing: 0.02em; }
.impactshop-identity-actions { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
.impactshop-identity-link { display: inline-flex; align-items: center; justify-content: center; padding: 10px 14px; border-radius: 12px; border: 1px solid rgba(15,23,42,0.2); background: #0f172a; color: #fff; text-decoration: none; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18); }
.impactshop-identity-link:hover { background: #1e293b; }
.impactshop-identity-link--muted { background: rgba(15,23,42,0.08); color: #0f172a; box-shadow: none; }
.impactshop-identity-link--muted:hover { background: rgba(15,23,42,0.14); }
.impactshop-identity-hidden { display: none; }
.impactshop-identity-save-ghost { position: absolute !important; left: -9999px !important; top: -9999px !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important; }
.impactshop-identity-value--recovery { background: rgba(14,165,233,0.15); color: #0e7490; }
.impactshop-identity-card hr { margin: 18px 0; border: none; border-top: 1px solid rgba(148,163,184,0.4); }
.impactshop-identity-status { margin-top: 12px; color: #0f172a; min-height: 20px; }
.impactshop-identity-hint { color: #475569; margin-top: 8px; line-height: 1.5; }
.impactshop-identity-save { margin-top: 14px; padding: 12px; border: 1px dashed rgba(148,163,184,0.5); border-radius: 14px; background: rgba(248,250,252,0.75); }
.impactshop-identity-save__label { display: block; font-weight: 600; margin-bottom: 6px; }
.impactshop-identity-restore { margin-top: 18px; padding-top: 12px; border-top: 1px solid rgba(148,163,184,0.35); display: grid; gap: 10px; }
.impactshop-identity-restore h4 { margin: 0; font-size: 16px; font-weight: 700; }
.impactshop-identity-restore__label { display: block; font-weight: 600; }
.impactshop-identity-block { margin-top: 18px; }
.impactshop-identity-header { margin-bottom: 12px; }
.impactshop-identity-progress { width: 100%; height: 8px; background: rgba(148,163,184,0.3); border-radius: 999px; overflow: hidden; margin: 10px 0; }
.impactshop-identity-progress-bar { height: 100%; width: 0; background: linear-gradient(90deg, #0ea5e9, #22c55e); }
.impactshop-identity-benefits span { display: block; margin-top: 6px; font-size: 13px; color: #334155; }
.impactshop-identity-list { list-style: none; padding: 0; margin: 0; }
.impactshop-identity-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(148,163,184,0.2); font-size: 13px; }
.impactshop-identity-badge-list { display: flex; flex-wrap: wrap; gap: 8px; }
.impactshop-identity-badge-list span { background: transparent; padding: 6px 10px; border-radius: 999px; font-size: 12px; }
.impactshop-identity-info { margin-top: 8px; padding: 10px; border-radius: 12px; background: rgba(15,23,42,0.06); font-size: 12px; }
.impactshop-identity-info-trigger { margin-top: 6px; background: #111827; }
.impactshop-identity-compact { margin-top: 12px; }
.impactshop-identity-message { padding: 12px; border-radius: 14px; background: rgba(15,23,42,0.06); }
.impactshop-identity-message strong { display: block; margin-bottom: 6px; font-size: 13px; }
.impactshop-identity-message-body { font-size: 13px; color: #334155; line-height: 1.5; }
.impactshop-identity-push .impactshop-identity-row { margin-top: 6px; }
@media (max-width: 640px) {
  .impactshop-identity-row { flex-direction: column; align-items: stretch; }
  .impactshop-identity-card button { width: 100%; }
}
CSS;
    wp_add_inline_style('impactshop-identity-panel', $css);

    wp_localize_script('impactshop-identity-panel', 'impactshopIdentityPanel', [
        'restBase'  => esc_url_raw(rest_url('impact/v1')),
        'restNonce' => wp_create_nonce('wp_rest'),
    ]);
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
    wp_enqueue_script('impactshop-identity-panel-intl-overlay');
    $enqueued = true;
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
    $pseudo_id = isset($params['pseudo_id']) ? strtolower((string)$params['pseudo_id']) : '';
    $nickname = isset($params['nickname']) ? (string)$params['nickname'] : '';

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $cookie_pseudo = strtolower(impactshop_identity_profile_cookie());
    if ($cookie_pseudo === '' || $cookie_pseudo !== $pseudo_id) {
        return new WP_REST_Response(['message' => 'Azonosító nem egyezik a böngésző cookie-val.'], 403);
    }

    $nickname = sanitize_text_field($nickname);
    $nickname = trim($nickname);
    if ($nickname !== '' && !impactshop_identity_profile_valid_nickname($nickname)) {
        return new WP_REST_Response(['message' => 'Érvénytelen becenév.'], 400);
    }

    impactshop_identity_profile_store($pseudo_id, $nickname);
    if (function_exists('impact_update_herowall')) {
        impact_update_herowall($pseudo_id);
    }
    do_action('impactshop_identity_profile_updated', $pseudo_id, $nickname);

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
    $pseudo_id = isset($params['pseudo_id']) ? strtolower((string)$params['pseudo_id']) : '';
    $recovery_code = isset($params['recovery_code']) ? (string)$params['recovery_code'] : '';

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $stored = impactshop_identity_profile_get_recovery_code($pseudo_id);
    if ($stored === null) {
        return new WP_REST_Response(['message' => 'Nincs tárolt helyreállító kód.'], 404);
    }
    if ($recovery_code === '' || $recovery_code !== $stored) {
        return new WP_REST_Response(['message' => 'Helyreállító kód nem egyezik.'], 403);
    }

    impactshop_identity_profile_set_cookie($pseudo_id);
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
 * Resolve requested bridge target from current request.
 *
 * @return string
 */
function impactshop_identity_requested_bridge_target(): string
{
    $value = isset($_REQUEST['bridge_target']) ? sanitize_text_field((string) wp_unslash($_REQUEST['bridge_target'])) : '';
    if ($value === 'restore') {
        return 'impactshop_restore';
    }
    if ($value === 'account') {
        return 'impactshop_account';
    }
    return '';
}

/**
 * Build native fallback profile URL for the given UI target.
 *
 * @param string $ui_target Bridge UI target.
 * @return string
 */
function impactshop_identity_profile_native_fallback(string $ui_target): string
{
    $base = home_url('/profil/');
    if ($ui_target === 'impactshop_restore') {
        return $base . '#impactshop-restore-title';
    }
    return $base;
}

/**
 * Complete profile-return redirect after a successful client-side action.
 *
 * @return void
 */
function impactshop_identity_maybe_complete_profile_return(): void
{
    if (is_admin()) {
        return;
    }

    if (empty($_GET['impactshop_profile_return_complete'])) {
        return;
    }

    $ui_target = impactshop_identity_requested_bridge_target();
    if ($ui_target === '' || !function_exists('impactshop_factlens_profile_return_target')) {
        return;
    }

    $target = impactshop_factlens_profile_return_target($ui_target, impactshop_identity_profile_native_fallback($ui_target));
    if (!is_string($target) || $target === '') {
        return;
    }

    $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $target_host = (string) wp_parse_url($target, PHP_URL_HOST);
    if ($target_host !== '' && $home_host !== '' && !hash_equals($home_host, $target_host)) {
        wp_redirect($target, 303);
        exit;
    }

    wp_safe_redirect($target, 303);
    exit;
}

/**
 * Validate nickname format.
 *
 * @param string $nickname Nickname to validate.

    $ui_target = impactshop_identity_requested_bridge_target();
    if ($ui_target !== '' && function_exists('impactshop_factlens_profile_return_target')) {
        $resolved = impactshop_factlens_profile_return_target($ui_target, impactshop_identity_profile_native_fallback($ui_target));
        if (is_string($resolved) && $resolved !== '') {
            $target_host = (string) wp_parse_url($resolved, PHP_URL_HOST);
            $home_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
            if ($target_host !== '' && $home_host !== '' && !hash_equals($home_host, $target_host)) {
                wp_redirect($resolved, 303);
                exit;
            }
            wp_safe_redirect($resolved, 303);
            exit;
        }
    }
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
    if ($recovery === null) {
        $recovery = impactshop_identity_profile_generate_recovery_code();
    }
    $payload = [
        'nickname'   => $nickname,
        'recovery_code' => $recovery,
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
 * Build option key for pseudo profile.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string
 */
function impactshop_identity_profile_option_key(string $pseudo_id): string
{
    $pseudo_id = strtolower($pseudo_id);
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
    $pseudo_id = strtolower($pseudo_id);
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
