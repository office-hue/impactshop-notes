<?php
/**
 * Plugin Name: ImpactShop JYSK Vote
 * Description: JYSK video vote campaign (shortcode + REST + data model).
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_VOTE_JYSK_SCHEMA = 3;
const IMPACTSHOP_VOTE_JYSK_NONCE = 'impact_vote_action';
const IMPACTSHOP_VOTE_JYSK_TOKEN_TTL = 600;
const IMPACTSHOP_VOTE_JYSK_TALLY_TTL = 15;

add_action('muplugins_loaded', 'impactshop_vote_jysk_maybe_migrate');
add_action('init', 'impactshop_vote_jysk_register_shortcodes');
add_action('rest_api_init', 'impactshop_vote_jysk_register_routes');
add_action('wp_enqueue_scripts', 'impactshop_vote_jysk_register_assets');

add_action('impactshop_vote_cron', 'impactshop_vote_jysk_cron');

function impactshop_vote_jysk_register_shortcodes(): void
{
    add_shortcode('impactshop_vote_page', 'impactshop_vote_jysk_shortcode');
}

function impactshop_vote_jysk_register_assets(): void
{
    wp_register_style('impactshop-vote-jysk', false);

    $script_path = __DIR__ . '/impactshop-vote-jysk.js';
    $script_url = plugins_url('impactshop-vote-jysk.js', __FILE__);
    $script_version = file_exists($script_path) ? (string) filemtime($script_path) : null;
    wp_register_script('impactshop-vote-jysk', $script_url, [], $script_version, true);

    $css = <<<CSS
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&display=swap');

.impactshop-vote { max-width: 1080px; margin: 0 auto; font-family: inherit; color: #0f172a; }
.impactshop-vote__panel { background: rgba(255,255,255,0.92); border-radius: 20px; padding: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); backdrop-filter: blur(14px); border: 1px solid rgba(148, 163, 184, 0.2); font-family: "Inter", "Segoe UI", Arial, sans-serif; }
.impactshop-vote__intro { background: #0f172a; color: #fff; border-radius: 16px; padding: 14px 16px; }
.impactshop-vote__intro h3 { margin: 0 0 4px; font-size: 22px; font-weight: 700; letter-spacing: -0.01em; }
.impactshop-vote__intro p { margin: 0; font-size: 14px; color: #e2e8f0; }
.impactshop-vote__steps { margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap; }
.impactshop-vote__step { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: rgba(148, 163, 184, 0.2); color: #e2e8f0; text-decoration: none; cursor: pointer; }
.impactshop-vote__step.is-active { background: rgba(59, 130, 246, 0.35); color: #fff; }
.impactshop-vote__step.is-done { background: rgba(16, 185, 129, 0.35); color: #fff; }
.impactshop-vote__step-dot { width: 8px; height: 8px; border-radius: 999px; background: currentColor; }
.impactshop-vote__step-check { font-size: 12px; }
.impactshop-vote__countdown { margin-top: 12px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; padding: 10px 14px; border-radius: 12px; font-size: 13px; border: 1px solid #fbbf24; text-align: center; }
.impactshop-vote__video { margin: 16px 0; }
.impactshop-vote__video.is-playing { box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3), 0 12px 30px rgba(37, 99, 235, 0.2); border-radius: 18px; padding: 4px; }
.impactshop-vote__video video { width: 100%; border-radius: 16px; display: block; background: #0f172a; }
.impactshop-vote__progress { margin-top: 10px; font-size: 14px; color: #475569; }
.impactshop-vote__help { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 13px; color: #475569; }
.impactshop-vote__help-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 999px; background: #e2e8f0; color: #0f172a; font-size: 12px; cursor: pointer; position: relative; }
.impactshop-vote__help-icon:hover .impactshop-vote__help-bubble,
.impactshop-vote__help-icon:focus .impactshop-vote__help-bubble { opacity: 1; transform: translateY(0); pointer-events: auto; }
.impactshop-vote__help-bubble { position: absolute; bottom: 150%; left: 50%; transform: translateX(-50%) translateY(6px); background: #0f172a; color: #fff; padding: 8px 10px; border-radius: 10px; font-size: 12px; white-space: nowrap; opacity: 0; transition: opacity .2s ease, transform .2s ease; pointer-events: none; z-index: 30; }
.impactshop-vote__help-bubble::after { content: ""; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border-width: 6px; border-style: solid; border-color: #0f172a transparent transparent transparent; }
.impactshop-vote__progress-bar { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
.impactshop-vote__progress-bar span { display: block; height: 100%; background: linear-gradient(90deg, #ef4444 0%, #f59e0b 25%, #10b981 50%, #0ea5e9 75%, #1d4ed8 100%); background-size: 200% 100%; animation: impactshop-gradient-shift 3s ease infinite; width: 0%; transition: width .3s ease; }
.impactshop-vote__status { margin-top: 12px; font-size: 14px; display: inline-block; padding: 6px 10px; border-radius: 10px; background: #f1f5f9; color: #0f172a; }
.impactshop-vote__ngos { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0; }
.impactshop-vote__ngo { border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); cursor: pointer; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
.impactshop-vote__ngo:focus-visible,
.impactshop-vote__cta button:focus-visible,
.impactshop-vote__ngo-more:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
.impactshop-vote__ngo--active { border-color: #1d4ed8; box-shadow: 0 14px 34px rgba(30, 64, 175, 0.18); transform: translateY(-2px); animation: impactshop-pulse-glow 2s ease-in-out infinite; }
.impactshop-vote__ngo h4 { margin: 8px 0 4px; font-size: 16px; font-weight: 600; }
.impactshop-vote__ngo p { margin: 0; font-size: 14px; color: #475569; }
.impactshop-vote__ngo img { width: 72px; height: 72px; object-fit: cover; border-radius: 14px; background: #fff; }
.impactshop-vote__ngo--kids { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
.impactshop-vote__ngo--animals { background: linear-gradient(135deg, #dcfce7 0%, #86efac 100%); }
.impactshop-vote__ngo--environment { background: linear-gradient(135deg, #cffafe 0%, #7dd3fc 100%); }
.impactshop-vote__ngo--culture { background: linear-gradient(135deg, #ede9fe 0%, #c4b5fd 100%); }
.impactshop-vote__ngo--other { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
.impactshop-vote__ngo-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #0f172a; background: #e0e7ff; border-radius: 999px; padding: 2px 8px; margin-top: 8px; }
.impactshop-vote__ngo-badge--gold { background: #fef3c7; color: #92400e; }
.impactshop-vote__ngo-badge--silver { background: #e2e8f0; color: #475569; }
.impactshop-vote__ngo-badge--bronze { background: #fcd9c2; color: #9a3412; }
.impactshop-vote__ngo-more { margin-top: 8px; background: transparent; border: 0; color: #1d4ed8; font-weight: 600; font-size: 12px; cursor: pointer; padding: 0; }
.impactshop-vote__ngo-progress { margin-top: 10px; }
.impactshop-vote__ngo-progress-bar { height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-bottom: 4px; }
.impactshop-vote__ngo-progress-bar span { display: block; height: 100%; background: linear-gradient(90deg, #0ea5e9, #1d4ed8); border-radius: 999px; transition: width .4s ease; }
.impactshop-vote__ngo-progress small { font-size: 11px; color: #64748b; }
.impactshop-vote__cta { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-top: 16px; }
.impactshop-vote__cta button { background: #0f172a; color: #fff; border: 0; border-radius: 14px; padding: 12px 20px; font-size: 15px; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
.impactshop-vote__cta button:not([disabled]):hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15, 23, 42, 0.18); }
.impactshop-vote__cta button:not([disabled]):active { transform: scale(0.97); }
.impactshop-vote__cta button[disabled] { background: #94a3b8; cursor: not-allowed; }
.impactshop-vote__selected-bar { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.92); color: #fff; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); border-radius: 0; font-size: 12px; box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.28); z-index: 1200; text-align: center; }
.impactshop-vote.has-selected-bar { padding-bottom: 120px; }
.impactshop-vote__selector { margin-top: 16px; background: #f8fafc; border-radius: 16px; padding: 14px; border: 1px solid #e2e8f0; }
.impactshop-vote__selector-row { display: flex; flex-direction: column; gap: 8px; }
.impactshop-vote__selector-label { font-size: 14px; font-weight: 600; color: #0f172a; }
.impactshop-vote__ngo-panel { display: flex; flex-direction: column; gap: 8px; }
.impactshop-vote__ngo-toggle { align-self: flex-start; background: #fff; border: 1px solid #cbd5f5; border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 600; color: #1e3a8a; cursor: pointer; }
.impactshop-vote__selector.is-collapsed .impactshop-vote__ngo-panel { display: none; }
.impactshop-vote__selector select { padding: 10px 12px; border-radius: 12px; border: 1px solid #cbd5f5; background: #fff; font-size: 14px; }
.impactshop-vote__selector input[type="search"] { padding: 10px 12px; border-radius: 12px; border: 1px solid #cbd5f5; background: #fff; font-size: 14px; width: 100%; }
.impactshop-vote__ngo-list { margin-top: 8px; border: 0; border-radius: 0; background: transparent; max-height: 240px; overflow: auto; }
.impactshop-vote__ngo-item { display: block; width: 100%; text-align: left; background: transparent !important; border: 0; padding: 8px 0; font-size: 14px; cursor: pointer; color: #0f172a !important; border-radius: 0 !important; box-shadow: none !important; }
.impactshop-vote__ngo-item:hover,
.impactshop-vote__ngo-item:focus-visible,
.impactshop-vote__ngo-item:active { background: transparent !important; }
.impactshop-vote__ngo-item.is-active { font-weight: 600; }
.impactshop-vote__ngo-empty { padding: 10px 12px; font-size: 13px; color: #64748b; }
.impactshop-vote__ngo-card { margin-top: 12px; background: #fff; border-radius: 14px; padding: 12px; border: 1px solid #e2e8f0; }
.impactshop-vote__ngo-card.is-empty { background: #f1f5f9; color: #64748b; }
.impactshop-vote__ngo-card h4 { margin: 0 0 6px; font-size: 16px; font-weight: 700; }
.impactshop-vote__ngo-card .impactshop-vote__ngo-meta { font-size: 12px; color: #64748b; margin-bottom: 6px; }
.impactshop-vote__ngo-card p { margin: 0; font-size: 14px; color: #1f2937; line-height: 1.5; }
.impactshop-vote__tally { margin-top: 16px; font-size: 14px; color: #475569; }
.impactshop-vote__tally ul { list-style: none; padding: 0; margin: 8px 0 0; }
.impactshop-vote__tally li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; }
.impactshop-vote__count { font-family: "JetBrains Mono", "Courier New", monospace; font-variant-numeric: tabular-nums; }
.impactshop-vote__ngo-progress small { font-family: "JetBrains Mono", "Courier New", monospace; }
.impactshop-vote__skeleton { background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: impactshop-skeleton 1.4s infinite; border-radius: 12px; }
.impactshop-vote__skeleton--video { height: 240px; }
.impactshop-vote__skeleton--card { height: 90px; }
.impactshop-vote__toast { position: fixed; left: 50%; transform: translateX(-50%); bottom: 20px; background: rgba(15, 23, 42, 0.92); color: #fff; padding: 10px 16px; border-radius: 999px; font-size: 13px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.3); opacity: 0; transition: opacity .2s ease, transform .2s ease; z-index: 1000; pointer-events: none; }
.impactshop-vote__toast.is-visible { opacity: 1; transform: translateX(-50%) translateY(-6px); }
.impactshop-vote__social { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; margin-top: 14px; }
.impactshop-vote__social-box { background: rgba(14, 165, 233, 0.08); border-left: 3px solid #0ea5e9; padding: 10px 12px; border-radius: 10px; font-size: 13px; color: #075985; }
.impactshop-vote__ticker { background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; padding: 10px 12px; border-radius: 10px; font-size: 13px; color: #065f46; }
.impactshop-vote__micro { background: rgba(244, 63, 94, 0.08); border-left: 3px solid #f43f5e; padding: 10px 12px; border-radius: 10px; font-size: 13px; color: #9f1239; }
.impactshop-vote__odds { background: rgba(99, 102, 241, 0.08); border-left: 3px solid #6366f1; padding: 10px 12px; border-radius: 10px; font-size: 13px; color: #3730a3; }
.impactshop-vote__sheet { position: fixed; inset: 0; display: none; align-items: flex-end; z-index: 999; }
.impactshop-vote__sheet.is-open { display: flex; }
.impactshop-vote__sheet-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.35); }
.impactshop-vote__sheet-panel { position: relative; background: #fff; border-radius: 20px 20px 0 0; padding: 18px; width: 100%; max-height: 70vh; overflow: auto; box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.2); }
.impactshop-vote__sheet-close { background: #0f172a; color: #fff; border: 0; border-radius: 999px; padding: 6px 12px; font-size: 12px; cursor: pointer; }
@keyframes impactshop-pulse-glow {
  0%, 100% { box-shadow: 0 14px 34px rgba(30, 64, 175, 0.18); }
  50% { box-shadow: 0 16px 46px rgba(30, 64, 175, 0.32); }
}
@keyframes impactshop-skeleton {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
@keyframes impactshop-gradient-shift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
@media (max-width: 720px) {
  .impactshop-vote__cta--sticky {
    position: fixed;
    bottom: 72px;
    left: 0;
    right: 0;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(10px);
    border-radius: 14px 14px 0 0;
    box-shadow: 0 -4px 18px rgba(15, 23, 42, 0.08);
    z-index: 1200;
  }
  .impactshop-vote__cta--sticky {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .impactshop-vote__cta--sticky button {
    width: 100%;
    display: inline-flex;
    justify-content: center;
  }
  .impactshop-vote__status {
    width: 100%;
    text-align: center;
  }
  .impactshop-vote__selected-bar {
    bottom: 0;
  }
  .impactshop-vote.has-selected-bar { padding-bottom: 140px; }
}
@media (max-width: 720px) {
  .impactshop-vote__panel { padding: 16px; }
}
CSS;

    wp_add_inline_style('impactshop-vote-jysk', $css);
}

function impactshop_vote_jysk_enqueue_assets(): void
{
    wp_enqueue_style('impactshop-vote-jysk');
    wp_enqueue_script('impactshop-vote-jysk');
    wp_localize_script('impactshop-vote-jysk', 'impactshopVoteJysk', [
        'restBase' => esc_url_raw(rest_url('impact/v1')),
        'restNonce' => wp_create_nonce('wp_rest'),
        'siteOrigin' => esc_url_raw(home_url('/')),
        'nonceIssuedAt' => time(),
    ]);
}

function impactshop_vote_jysk_shortcode($atts = []): string
{
    $atts = shortcode_atts([
        'campaign_slug' => '',
    ], $atts, 'impactshop_vote_page');
    $campaign_slug = sanitize_title((string) $atts['campaign_slug']);
    $slug_attr = $campaign_slug !== '' ? ' data-campaign-slug="' . esc_attr($campaign_slug) . '"' : '';

    if (class_exists('Elementor\Plugin')) {
        $plugin = Elementor\Plugin::instance();
        if (!empty($plugin->editor) && $plugin->editor->is_edit_mode()) {
            return '<div class="impactshop-vote__panel">Vote modul (szerkesztő előnézet)</div>';
        }
    }

    impactshop_vote_jysk_enqueue_assets();

    $html  = '<div class="impactshop-vote" data-role="vote-root"' . $slug_attr . '>';
    $html .= '<div class="impactshop-vote__panel">';
    $html .= '<div class="impactshop-vote__intro">';
    $html .= '<h3>JYSK szavazás – segítsd a kedvencedet!</h3>';
    $html .= '<p>A szavazás a videó végignézése után aktiválódik.</p>';
    $html .= '<div class="impactshop-vote__steps" data-role="steps">';
    $html .= '<a class="impactshop-vote__step is-active" data-step="1" href="#impactshop-vote-video"><span class="impactshop-vote__step-dot"></span>Videó</a>';
    $html .= '<a class="impactshop-vote__step" data-step="2" href="#impactshop-vote-selector"><span class="impactshop-vote__step-dot"></span>Szervezet</a>';
    $html .= '<a class="impactshop-vote__step" data-step="3" href="#impactshop-vote-submit"><span class="impactshop-vote__step-dot"></span>Szavazás</a>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__countdown" data-role="countdown"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__social">';
    $html .= '<div class="impactshop-vote__social-box" data-role="social-count">Az elmúlt 5 percben: — szavazat (becslés)</div>';
    $html .= '<div class="impactshop-vote__ticker" data-role="live-ticker">🔥 Élő aktivitás: várakozás…</div>';
    $html .= '<div class="impactshop-vote__micro" data-role="micro-message">💬 Üzenet hamarosan…</div>';
    $html .= '<div class="impactshop-vote__odds" data-role="odds">🎁 Nyeremény esélyed: 1 / —</div>';
    $html .= '</div>';
    $html .= do_shortcode('[impactshop_identity_id]');
    $html .= '<div class="impactshop-vote__video" data-role="video-wrap" id="impactshop-vote-video"></div>';
    $html .= '<div class="impactshop-vote__progress">';
    $html .= '<div class="impactshop-vote__progress-bar"><span data-role="progress-bar"></span></div>';
    $html .= '<div data-role="progress-text">Videó megtekintése szükséges a szavazathoz. Szavazás előtt válassz szervezetet.</div>';
    $html .= '<div class="impactshop-vote__help">';
    $html .= '<span>Miért kell végignézni?</span>';
    $html .= '<span class="impactshop-vote__help-icon" tabindex="0">ⓘ';
    $html .= '<span class="impactshop-vote__help-bubble">A szavazás hitelesítése miatt csak a végignézett videó után aktiválódik.</span>';
    $html .= '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__selector" data-role="ngo-selector" id="impactshop-vote-selector">';
    $html .= '<div class="impactshop-vote__selector-row">';
    $html .= '<label class="impactshop-vote__selector-label" for="impactshop-vote-select">Válaszd ki, melyik szervezetre szavazol</label>';
    $html .= '<button type="button" class="impactshop-vote__ngo-toggle" data-role="ngo-toggle" hidden>Másik szervezet választása</button>';
    $html .= '<div class="impactshop-vote__ngo-panel" data-role="ngo-panel">';
    $html .= '<input type="search" id="impactshop-vote-search" data-role="ngo-filter" placeholder="Kezdj el gépelni az NGO nevére…">';
    $html .= '<div class="impactshop-vote__ngo-list" data-role="ngo-list"></div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__ngo-card" data-role="ngo-card"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__cta">';
    $html .= '<button type="button" data-role="vote-submit" id="impactshop-vote-submit" disabled>Szavazok most</button>';
    $html .= '<span data-role="vote-status" class="impactshop-vote__status"></span>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__tally" data-role="tally"></div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__selected-bar" data-role="selected-bar" style="display:none;"></div>';
    $html .= '<div class="impactshop-vote__sheet" data-role="ngo-sheet">';
    $html .= '<div class="impactshop-vote__sheet-backdrop" data-role="ngo-sheet-close"></div>';
    $html .= '<div class="impactshop-vote__sheet-panel">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">';
    $html .= '<h4 data-role="ngo-sheet-title" style="margin:0;font-size:18px;">Részletek</h4>';
    $html .= '<button type="button" class="impactshop-vote__sheet-close" data-role="ngo-sheet-close">Bezárás</button>';
    $html .= '</div>';
    $html .= '<p data-role="ngo-sheet-body" style="margin-top:10px;color:#475569;"></p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="impactshop-vote__toast" data-role="toast"></div>';
    $html .= '</div>';

    return $html;
}

function impactshop_vote_jysk_register_routes(): void
{
    register_rest_route('impact/v1', '/vote/init', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_init',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote/campaign', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_campaign',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote/status', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_status',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote/tally', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_tally',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/vote/view', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vote_jysk_view',
        'permission_callback' => 'impactshop_vote_jysk_require_nonce',
    ]);

    register_rest_route('impact/v1', '/vote/cast', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vote_jysk_cast',
        'permission_callback' => 'impactshop_vote_jysk_require_nonce',
    ]);

    register_rest_route('impact/v1', '/vote/refresh-nonce', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_refresh_nonce',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/identity/messages', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'impactshop_vote_jysk_identity_messages',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('impact/v1', '/identity/message-read', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'impactshop_vote_jysk_identity_message_read',
        'permission_callback' => 'impactshop_vote_jysk_require_any_nonce',
    ]);
}

function impactshop_vote_jysk_refresh_nonce(WP_REST_Request $request): WP_REST_Response
{
    if (!impactshop_vote_jysk_check_origin()) {
        return new WP_REST_Response(['message' => 'Hibas origin.'], 403);
    }
    return new WP_REST_Response(['nonce' => wp_create_nonce('wp_rest')], 200);
}

function impactshop_vote_jysk_require_nonce(WP_REST_Request $request)
{
    if (impactshop_vote_jysk_kill_switch()) {
        return new WP_Error('KILL_SWITCH_ACTIVE', 'A szavazas ideiglenesen szunet.', ['status' => 503]);
    }
    if (!impactshop_vote_jysk_https_ok()) {
        return new WP_Error('HTTPS_REQUIRED', 'HTTPS szukseges.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if (!wp_verify_nonce($nonce, IMPACTSHOP_VOTE_JYSK_NONCE) && !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('INVALID_NONCE', 'Hibas nonce.', ['status' => 403]);
    }

    if (!impactshop_vote_jysk_check_origin()) {
        return new WP_Error('INVALID_ORIGIN', 'Hibas origin.', ['status' => 403]);
    }

    return true;
}

function impactshop_vote_jysk_require_any_nonce(WP_REST_Request $request)
{
    if (impactshop_vote_jysk_kill_switch()) {
        return new WP_Error('KILL_SWITCH_ACTIVE', 'A szavazas ideiglenesen szunet.', ['status' => 503]);
    }
    if (!impactshop_vote_jysk_https_ok()) {
        return new WP_Error('HTTPS_REQUIRED', 'HTTPS szukseges.', ['status' => 403]);
    }

    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        $nonce = (string) $request->get_param('_wpnonce');
    }

    if (!wp_verify_nonce($nonce, IMPACTSHOP_VOTE_JYSK_NONCE) && !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('INVALID_NONCE', 'Hibas nonce.', ['status' => 403]);
    }

    if (!impactshop_vote_jysk_check_origin()) {
        return new WP_Error('INVALID_ORIGIN', 'Hibas origin.', ['status' => 403]);
    }

    return true;
}

function impactshop_vote_jysk_https_ok(): bool
{
    $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
    if ($scheme === 'https' && !is_ssl()) {
        return false;
    }
    return true;
}

function impactshop_vote_jysk_check_origin(): bool
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if (!$host) {
        return true;
    }
    foreach ([$origin, $referer] as $value) {
        if ($value === '') {
            continue;
        }
        $value_host = wp_parse_url($value, PHP_URL_HOST);
        if ($value_host && $value_host === $host) {
            return true;
        }
    }
    return false;
}

function impactshop_vote_jysk_kill_switch(): bool
{
    return (bool) get_option('impact_vote_kill_switch', false);
}

function impactshop_vote_jysk_init(WP_REST_Request $request): WP_REST_Response
{
    $campaign_slug = impactshop_vote_jysk_request_campaign_slug($request);
    $campaign = impactshop_vote_jysk_get_campaign($campaign_slug);
    $status = impactshop_vote_jysk_status_data($campaign_slug);

    return new WP_REST_Response([
        'campaign' => $campaign,
        'status' => $status,
    ], 200);
}

function impactshop_vote_jysk_campaign(WP_REST_Request $request): WP_REST_Response
{
    $campaign_slug = impactshop_vote_jysk_request_campaign_slug($request);
    return new WP_REST_Response(impactshop_vote_jysk_get_campaign($campaign_slug), 200);
}

function impactshop_vote_jysk_status(WP_REST_Request $request): WP_REST_Response
{
    $campaign_slug = impactshop_vote_jysk_request_campaign_slug($request);
    return new WP_REST_Response(impactshop_vote_jysk_status_data($campaign_slug), 200);
}

function impactshop_vote_jysk_tally(WP_REST_Request $request): WP_REST_Response
{
    $campaign_id = (int) $request->get_param('campaign_id');
    if ($campaign_id <= 0) {
        return new WP_REST_Response(['message' => 'campaign_id hianyzik.'], 400);
    }

    $cache_key = impactshop_vote_jysk_tally_cache_key($campaign_id);
    $cached = get_transient($cache_key);
    if (is_array($cached) && isset($cached['etag'], $cached['payload'])) {
        $etag = (string) $cached['etag'];
        $if_none = (string) $request->get_header('If-None-Match');
        if ($if_none !== '' && $if_none === $etag) {
            return new WP_REST_Response(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'max-age=' . IMPACTSHOP_VOTE_JYSK_TALLY_TTL . ', stale-while-revalidate=30',
            ]);
        }
    }

    $lock_key = $cache_key . '_lock';
    if ($cached === false && !get_transient($lock_key)) {
        set_transient($lock_key, 1, 10);
        $payload = impactshop_vote_jysk_build_tally($campaign_id);
        $etag = '"' . md5(wp_json_encode($payload)) . '"';
        set_transient($cache_key, ['etag' => $etag, 'payload' => $payload], IMPACTSHOP_VOTE_JYSK_TALLY_TTL);
        delete_transient($lock_key);
        $cached = ['etag' => $etag, 'payload' => $payload];
    }

    if (!is_array($cached)) {
        $cached = ['etag' => '"' . md5('empty') . '"', 'payload' => ['items' => []]];
    }

    return new WP_REST_Response($cached['payload'], 200, [
        'ETag' => $cached['etag'],
        'Cache-Control' => 'max-age=' . IMPACTSHOP_VOTE_JYSK_TALLY_TTL . ', stale-while-revalidate=30',
    ]);
}

function impactshop_vote_jysk_view(WP_REST_Request $request): WP_REST_Response
{
    $params = (array) $request->get_json_params();
    $campaign_id = isset($params['campaign_id']) ? (int) $params['campaign_id'] : 0;
    $completed = !empty($params['completed']);

    if (!$completed || $campaign_id <= 0) {
        return new WP_REST_Response(['message' => 'Ervenytelen kereses.'], 400);
    }

    $campaign = impactshop_vote_jysk_get_campaign_by_id($campaign_id);
    if (!$campaign || empty($campaign['id']) || (int) $campaign['id'] !== $campaign_id) {
        return new WP_REST_Response(['message' => 'Nincs aktiv kampany.'], 400);
    }

    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['message' => 'Hianyzo azonosito.'], 403);
    }

    $rate_result = impactshop_vote_jysk_rate_limits($pseudo_id, 'view');
    if (!$rate_result['allowed']) {
        impactshop_vote_jysk_log_event('view_rate_limited', [
            'campaign_id' => $campaign_id,
            'pseudo_id' => $pseudo_id,
        ]);
        return impactshop_vote_jysk_error('RATE_LIMIT_EXCEEDED', 'Tul sok probalkozas.', 429, [
            'retry_after' => $rate_result['retry_after'],
        ], [
            'X-RateLimit-Limit' => $rate_result['limit'],
            'X-RateLimit-Remaining' => $rate_result['remaining'],
            'X-RateLimit-Reset' => $rate_result['reset'],
            'Retry-After' => $rate_result['retry_after'],
        ]);
    }

    $token = impactshop_vote_jysk_issue_view_token($campaign_id, $pseudo_id);

    impactshop_vote_jysk_log_event('video_complete', [
        'campaign_id' => $campaign_id,
        'pseudo_id' => $pseudo_id,
    ]);

    if (class_exists('Sharity_Points_Manager')) {
        $day = gmdate('Y-m-d');
        $manager = new Sharity_Points_Manager();
        $manager->award_points_for_pseudo(
            $pseudo_id,
            5,
            'video_sponsor',
            (string) $campaign_id,
            ['source_type' => 'impact_vote', 'campaign_id' => (string) $campaign_id],
            'video_sponsor:' . $pseudo_id . ':' . $day
        );
    }

    return new WP_REST_Response(['view_token' => $token, 'expires_in' => IMPACTSHOP_VOTE_JYSK_TOKEN_TTL], 200, [
        'X-RateLimit-Limit' => $rate_result['limit'],
        'X-RateLimit-Remaining' => $rate_result['remaining'],
        'X-RateLimit-Reset' => $rate_result['reset'],
    ]);
}

function impactshop_vote_jysk_cast(WP_REST_Request $request): WP_REST_Response
{
    $params = (array) $request->get_json_params();
    $campaign_id = isset($params['campaign_id']) ? (int) $params['campaign_id'] : 0;
    $ngo_id = isset($params['ngo_id']) ? (int) $params['ngo_id'] : 0;
    $view_token = isset($params['view_token']) ? (string) $params['view_token'] : '';

    if ($campaign_id <= 0 || $ngo_id <= 0 || $view_token === '') {
        return impactshop_vote_jysk_error('INVALID_REQUEST', 'Ervenytelen kereses.', 400);
    }

    $campaign = impactshop_vote_jysk_get_campaign_by_id($campaign_id);
    if (!$campaign || (int) $campaign['id'] !== $campaign_id) {
        return impactshop_vote_jysk_error('CAMPAIGN_NOT_ACTIVE', 'Nincs aktiv kampany.', 400);
    }

    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    if ($pseudo_id === '') {
        return impactshop_vote_jysk_error('MISSING_PSEUDO', 'Hianyzo azonosito.', 403);
    }

    if (!impactshop_vote_jysk_validate_view_token($view_token, $campaign_id, $pseudo_id)) {
        return impactshop_vote_jysk_error('INVALID_VIEW_TOKEN', 'Lejart vagy ervenytelen token.', 403);
    }

    if (!impactshop_vote_jysk_ngo_active($campaign_id, $ngo_id)) {
        return impactshop_vote_jysk_error('NGO_NOT_FOUND', 'Ervenytelen civil szervezet.', 404);
    }

    $rate_result = impactshop_vote_jysk_rate_limits($pseudo_id, 'cast');
    if (!$rate_result['allowed']) {
        impactshop_vote_jysk_log_event('vote_rate_limited', [
            'campaign_id' => $campaign_id,
            'pseudo_id' => $pseudo_id,
            'ngo_id' => $ngo_id,
        ]);
        return impactshop_vote_jysk_error('RATE_LIMIT_EXCEEDED', 'Tul sok probalkozas.', 429, [
            'retry_after' => $rate_result['retry_after'],
        ], [
            'X-RateLimit-Limit' => $rate_result['limit'],
            'X-RateLimit-Remaining' => $rate_result['remaining'],
            'X-RateLimit-Reset' => $rate_result['reset'],
            'Retry-After' => $rate_result['retry_after'],
        ]);
    }

    $status = impactshop_vote_jysk_status_data_by_campaign_id($campaign_id);
    if (!empty($status['voted_today'])) {
        return impactshop_vote_jysk_error('DAILY_LIMIT_EXCEEDED', 'Ma már szavaztál.', 409, [
            'next_vote_available_at' => $status['next_vote_available_at'],
        ]);
    }

    global $wpdb;
    $log_table = impactshop_vote_jysk_table('impact_vote_log');
    $daily_table = impactshop_vote_jysk_table('impact_vote_daily');

    $day_key = impactshop_vote_jysk_day_key();
    $ip_hash = impactshop_vote_jysk_hash_ip();
    $ua_hash = impactshop_vote_jysk_hash_ua();
    $now = impactshop_vote_jysk_now_utc();

    $wpdb->query('START TRANSACTION');
    $inserted = $wpdb->insert($log_table, [
        'campaign_id' => $campaign_id,
        'ngo_id' => $ngo_id,
        'pseudo_id' => $pseudo_id,
        'voted_at' => $now,
        'day_key' => $day_key,
        'ip_hash' => $ip_hash,
        'ua_hash' => $ua_hash,
    ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s']);

    if ($inserted === false) {
        $wpdb->query('ROLLBACK');
        impactshop_vote_jysk_log_event('vote_duplicate', [
            'campaign_id' => $campaign_id,
            'pseudo_id' => $pseudo_id,
            'ngo_id' => $ngo_id,
        ]);
        return impactshop_vote_jysk_error('DAILY_LIMIT_EXCEEDED', 'Ma már szavaztál.', 409);
    }

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$daily_table} (day_key, campaign_id, ngo_id, votes) VALUES (%s, %d, %d, 1)
            ON DUPLICATE KEY UPDATE votes = votes + 1",
            $day_key,
            $campaign_id,
            $ngo_id
        )
    );

    $wpdb->query('COMMIT');

    impactshop_vote_jysk_refresh_tally_cache($campaign_id);
    impactshop_vote_jysk_log_event('vote_success', [
        'campaign_id' => $campaign_id,
        'pseudo_id' => $pseudo_id,
        'ngo_id' => $ngo_id,
    ]);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Szavazat rogzitve.',
    ], 200, [
        'X-RateLimit-Limit' => $rate_result['limit'],
        'X-RateLimit-Remaining' => $rate_result['remaining'],
        'X-RateLimit-Reset' => $rate_result['reset'],
    ]);
}

function impactshop_vote_jysk_identity_messages(WP_REST_Request $request): WP_REST_Response
{
    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['messages' => []], 200);
    }

    global $wpdb;
    $messages_table = impactshop_vote_jysk_table('impact_vote_messages');
    $targets_table = impactshop_vote_jysk_table('impact_vote_message_targets');

    $now = impactshop_vote_jysk_now_utc();

    $global_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, type, content, priority, start_at, end_at
            FROM {$messages_table}
            WHERE type = 'global' AND start_at <= %s AND end_at >= %s
            ORDER BY priority DESC, start_at DESC",
            $now,
            $now
        ),
        ARRAY_A
    );

    $targeted_messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT m.id, m.type, m.content, m.priority, m.start_at, m.end_at, t.is_read
            FROM {$messages_table} m
            INNER JOIN {$targets_table} t ON m.id = t.message_id
            WHERE m.type = 'targeted' AND t.pseudo_id = %s AND t.is_read = 0
              AND m.start_at <= %s AND m.end_at >= %s
            ORDER BY m.priority DESC, m.start_at DESC",
            $pseudo_id,
            $now,
            $now
        ),
        ARRAY_A
    );

    $messages = array_merge($targeted_messages, $global_messages);
    usort($messages, function ($a, $b) {
        $pa = (int) ($a['priority'] ?? 0);
        $pb = (int) ($b['priority'] ?? 0);
        if ($pa === $pb) {
            return strcmp((string) ($b['start_at'] ?? ''), (string) ($a['start_at'] ?? ''));
        }
        return $pb <=> $pa;
    });

    return new WP_REST_Response(['messages' => $messages], 200);
}

function impactshop_vote_jysk_identity_message_read(WP_REST_Request $request): WP_REST_Response
{
    $params = (array) $request->get_json_params();
    $message_id = isset($params['message_id']) ? (int) $params['message_id'] : 0;
    if ($message_id <= 0) {
        return new WP_REST_Response(['status' => 'error'], 400);
    }

    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'error'], 403);
    }

    global $wpdb;
    $targets_table = impactshop_vote_jysk_table('impact_vote_message_targets');
    $wpdb->update($targets_table, [
        'is_read' => 1,
        'read_at' => impactshop_vote_jysk_now_utc(),
    ], [
        'message_id' => $message_id,
        'pseudo_id' => $pseudo_id,
    ], ['%d', '%s'], ['%d', '%s']);

    return new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_vote_jysk_status_data(string $campaign_slug = ''): array
{
    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    $day_key = impactshop_vote_jysk_day_key();

    if ($pseudo_id === '') {
        return [
            'voted_today' => false,
            'next_vote_available_at' => impactshop_vote_jysk_next_vote_time(),
            'user_votes_total' => 0,
        ];
    }

    global $wpdb;
    $log_table = impactshop_vote_jysk_table('impact_vote_log');
    $campaign = impactshop_vote_jysk_get_campaign($campaign_slug);
    $campaign_id = $campaign ? (int) $campaign['id'] : 0;

    $already = false;
    $user_votes_total = 0;
    if ($campaign_id > 0) {
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$log_table} WHERE campaign_id = %d AND pseudo_id = %s AND day_key = %s",
                $campaign_id,
                $pseudo_id,
                $day_key
            )
        );
        $already = $count > 0;
        $user_votes_total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$log_table} WHERE campaign_id = %d AND pseudo_id = %s",
                $campaign_id,
                $pseudo_id
            )
        );
    }

    return [
        'voted_today' => $already,
        'next_vote_available_at' => impactshop_vote_jysk_next_vote_time(),
        'user_votes_total' => $user_votes_total,
    ];
}

function impactshop_vote_jysk_status_data_by_campaign_id(int $campaign_id): array
{
    if ($campaign_id <= 0) {
        return [
            'voted_today' => false,
            'next_vote_available_at' => impactshop_vote_jysk_next_vote_time(),
            'user_votes_total' => 0,
        ];
    }
    $pseudo_id = impactshop_vote_jysk_get_pseudo_id();
    $day_key = impactshop_vote_jysk_day_key();

    if ($pseudo_id === '') {
        return [
            'voted_today' => false,
            'next_vote_available_at' => impactshop_vote_jysk_next_vote_time(),
            'user_votes_total' => 0,
        ];
    }

    global $wpdb;
    $log_table = impactshop_vote_jysk_table('impact_vote_log');
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1) FROM {$log_table} WHERE campaign_id = %d AND pseudo_id = %s AND day_key = %s",
            $campaign_id,
            $pseudo_id,
            $day_key
        )
    );
    $already = $count > 0;
    $user_votes_total = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1) FROM {$log_table} WHERE campaign_id = %d AND pseudo_id = %s",
            $campaign_id,
            $pseudo_id
        )
    );

    return [
        'voted_today' => $already,
        'next_vote_available_at' => impactshop_vote_jysk_next_vote_time(),
        'user_votes_total' => $user_votes_total,
    ];
}

function impactshop_vote_jysk_next_vote_time(): string
{
    $tz = new DateTimeZone('Europe/Budapest');
    $next = new DateTime('tomorrow', $tz);
    return $next->format('c');
}

function impactshop_vote_jysk_get_campaign(string $campaign_slug = ''): array
{
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');

    $now = impactshop_vote_jysk_now_utc();
    if ($campaign_slug !== '') {
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$campaigns_table}
                 WHERE status = 'active' AND start_at <= %s AND end_at >= %s AND campaign_slug = %s
                 ORDER BY id ASC LIMIT 1",
                $now,
                $now,
                $campaign_slug
            ),
            ARRAY_A
        );
    } else {
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$campaigns_table} WHERE status = 'active' AND start_at <= %s AND end_at >= %s ORDER BY id ASC LIMIT 1",
                $now,
                $now
            ),
            ARRAY_A
        );
    }

    if (!$campaign) {
        return ['status' => 'none'];
    }

    $selector_list = impactshop_vote_jysk_selector_list_for_campaign((string) ($campaign['campaign_slug'] ?? ''));
    if ($selector_list !== '') {
        impactshop_vote_jysk_sync_ngos_from_selector((int) $campaign['id'], (string) ($campaign['campaign_slug'] ?? ''));
    }
    $ngos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, ngo_slug, ngo_name, description, logo_url, sort_order
            FROM {$ngos_table}
            WHERE campaign_id = %d AND is_active = 1
            ORDER BY sort_order ASC, ngo_name ASC",
            (int) $campaign['id']
        ),
        ARRAY_A
    );

    return [
        'status' => 'active',
        'id' => (int) $campaign['id'],
        'name' => (string) $campaign['name'],
        'campaign_slug' => (string) ($campaign['campaign_slug'] ?? ''),
        'start_at' => impactshop_vote_jysk_iso8601($campaign['start_at']),
        'end_at' => impactshop_vote_jysk_iso8601($campaign['end_at']),
        'video_url' => (string) ($campaign['video_url'] ?? ''),
        'poster_url' => (string) ($campaign['poster_url'] ?? ''),
        'ngos' => $ngos,
    ];
}

function impactshop_vote_jysk_get_campaign_by_id(int $campaign_id): array
{
    if ($campaign_id <= 0) {
        return [];
    }
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $now = impactshop_vote_jysk_now_utc();

    $campaign = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$campaigns_table}
             WHERE id = %d AND status = 'active' AND start_at <= %s AND end_at >= %s
             LIMIT 1",
            $campaign_id,
            $now,
            $now
        ),
        ARRAY_A
    );

    if (!$campaign) {
        return [];
    }

    $selector_list = impactshop_vote_jysk_selector_list_for_campaign((string) ($campaign['campaign_slug'] ?? ''));
    if ($selector_list !== '') {
        impactshop_vote_jysk_sync_ngos_from_selector((int) $campaign['id'], (string) ($campaign['campaign_slug'] ?? ''));
    }
    $ngos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, ngo_slug, ngo_name, description, logo_url, sort_order
            FROM {$ngos_table}
            WHERE campaign_id = %d AND is_active = 1
            ORDER BY sort_order ASC, ngo_name ASC",
            (int) $campaign['id']
        ),
        ARRAY_A
    );

    return [
        'status' => 'active',
        'id' => (int) $campaign['id'],
        'name' => (string) $campaign['name'],
        'campaign_slug' => (string) ($campaign['campaign_slug'] ?? ''),
        'start_at' => impactshop_vote_jysk_iso8601($campaign['start_at']),
        'end_at' => impactshop_vote_jysk_iso8601($campaign['end_at']),
        'video_url' => (string) ($campaign['video_url'] ?? ''),
        'poster_url' => (string) ($campaign['poster_url'] ?? ''),
        'ngos' => $ngos,
    ];
}

function impactshop_vote_jysk_selector_list_for_campaign(string $campaign_slug): string
{
    $slug = sanitize_title($campaign_slug);
    if ($slug === '') {
        return '';
    }
    $map = [
        'jysk-komarom-szavazas' => 'komarom-esztergom',
        'jysk-komarom' => 'komarom-esztergom',
        'jysk-2' => 'komarom-esztergom',
        'jysk-mezokovesd-szavazas' => 'borsod-abauj-zemplen',
        'jysk-mezokovesd' => 'borsod-abauj-zemplen',
    ];
    if (isset($map[$slug])) {
        return $map[$slug];
    }
    if (strpos($slug, 'komarom') !== false) {
        return 'komarom-esztergom';
    }
    if (strpos($slug, 'mezokovesd') !== false) {
        return 'borsod-abauj-zemplen';
    }
    return '';
}

function impactshop_vote_jysk_sync_ngos_from_selector(int $campaign_id, string $campaign_slug): void
{
    if ($campaign_id <= 0) {
        return;
    }
    $list = impactshop_vote_jysk_selector_list_for_campaign($campaign_slug);
    if ($list === '') {
        return;
    }
    $path = __DIR__ . '/impactshop-ngo-selector-data/' . $list . '.json';
    if (!file_exists($path)) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
        return;
    }
    global $wpdb;
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $existing_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, ngo_slug FROM {$ngos_table} WHERE campaign_id = %d",
        $campaign_id
    ), ARRAY_A);
    $existing_map = [];
    foreach ($existing_rows as $row) {
        $existing_map[(string) $row['ngo_slug']] = (int) $row['id'];
    }

    $list_slugs = [];
    $order = 1;
    foreach ($payload['items'] as $item) {
        $slug = sanitize_title((string) ($item['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $list_slugs[] = $slug;
        $name = sanitize_text_field((string) ($item['name'] ?? $item['short_name'] ?? $slug));
        $summary = trim((string) ($item['summary'] ?? ''));
        if ($summary === '') {
            $activity = trim((string) ($item['activity'] ?? ''));
            $address = trim((string) ($item['address'] ?? ''));
            $summary = $name;
            if ($activity !== '') {
                $summary .= ' fő tevékenysége: ' . $activity . '.';
            }
            if ($address !== '') {
                $summary .= ' Székhely: ' . $address . '.';
            }
        }

        if (isset($existing_map[$slug])) {
            $wpdb->update(
                $ngos_table,
                [
                    'ngo_name' => $name,
                    'description' => $summary,
                    'sort_order' => $order,
                    'is_active' => 1,
                ],
                ['id' => $existing_map[$slug]],
                ['%s', '%s', '%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $ngos_table,
                [
                    'campaign_id' => $campaign_id,
                    'ngo_slug' => $slug,
                    'ngo_name' => $name,
                    'description' => $summary,
                    'logo_url' => '',
                    'sort_order' => $order,
                    'is_active' => 1,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d']
            );
        }
        $order += 1;
    }

    if ($list_slugs) {
        $placeholders = implode(',', array_fill(0, count($list_slugs), '%s'));
        $params = array_merge([$campaign_id], $list_slugs);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$ngos_table} SET is_active = 0 WHERE campaign_id = %d AND ngo_slug NOT IN ({$placeholders})",
            $params
        ));
    }
}

function impactshop_vote_jysk_request_campaign_slug(WP_REST_Request $request): string
{
    $slug = (string) $request->get_param('campaign_slug');
    $slug = sanitize_title($slug);
    return $slug;
}

function impactshop_vote_jysk_iso8601($datetime): string
{
    try {
        $dt = new DateTime((string) $datetime, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return '';
    }
    $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
    return $dt->format('c');
}

function impactshop_vote_jysk_issue_view_token(int $campaign_id, string $pseudo_id): string
{
    $payload = [
        'cmp' => $campaign_id,
        'pid' => $pseudo_id,
        'exp' => time() + IMPACTSHOP_VOTE_JYSK_TOKEN_TTL,
        'jti' => bin2hex(random_bytes(8)),
    ];
    $json = wp_json_encode($payload);
    $body = impactshop_vote_jysk_base64url($json);
    $sig = hash_hmac('sha256', $body, wp_salt('impact_vote_view_token'));
    return $body . '.' . $sig;
}

function impactshop_vote_jysk_validate_view_token(string $token, int $campaign_id, string $pseudo_id): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    [$body, $sig] = $parts;
    $expected = hash_hmac('sha256', $body, wp_salt('impact_vote_view_token'));
    if (!hash_equals($expected, $sig)) {
        return false;
    }
    $json = impactshop_vote_jysk_base64url_decode($body);
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return false;
    }
    if ((int) ($payload['cmp'] ?? 0) !== $campaign_id) {
        return false;
    }
    if ((string) ($payload['pid'] ?? '') !== $pseudo_id) {
        return false;
    }
    if ((int) ($payload['exp'] ?? 0) < time()) {
        return false;
    }
    return true;
}

function impactshop_vote_jysk_base64url(string $input): string
{
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function impactshop_vote_jysk_base64url_decode(string $input): string
{
    $input = strtr($input, '-_', '+/');
    $pad = strlen($input) % 4;
    if ($pad) {
        $input .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($input) ?: '';
}

function impactshop_vote_jysk_rate_limits(string $pseudo_id, string $action): array
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    $suffix = $action === 'view' ? 'view' : 'cast';
    $pseudo_key = 'impact_vote_rl_p_' . $suffix . '_' . hash_hmac('sha256', $pseudo_id, wp_salt('impact_vote_rl'));
    $ip_key = 'impact_vote_rl_ip_' . $suffix . '_' . hash_hmac('sha256', $ip, wp_salt('impact_vote_rl'));

    $pseudo_limit = $action === 'view' ? 30 : 10;
    $ip_limit = $action === 'view' ? 100 : 50;

    $pseudo_result = impactshop_vote_jysk_rate_limit_check($pseudo_key, $pseudo_limit, HOUR_IN_SECONDS);
    if (!$pseudo_result['allowed']) {
        return $pseudo_result;
    }

    $ip_result = impactshop_vote_jysk_rate_limit_check($ip_key, $ip_limit, HOUR_IN_SECONDS);
    if (!$ip_result['allowed']) {
        return $ip_result;
    }

    return [
        'allowed' => true,
        'retry_after' => 0,
        'limit' => min($pseudo_result['limit'], $ip_result['limit']),
        'remaining' => min($pseudo_result['remaining'], $ip_result['remaining']),
        'reset' => min($pseudo_result['reset'], $ip_result['reset']),
    ];
}

function impactshop_vote_jysk_rate_limit_check(string $key, int $limit, int $window): array
{
    $now = time();
    $bucket = get_transient($key);
    if (!is_array($bucket)) {
        $bucket = [];
    }
    $bucket = array_values(array_filter($bucket, function ($timestamp) use ($now, $window) {
        return is_int($timestamp) && $timestamp >= ($now - $window);
    }));

    if (count($bucket) >= $limit) {
        $oldest = min($bucket);
        return [
            'allowed' => false,
            'retry_after' => max(1, ($oldest + $window) - $now),
            'limit' => $limit,
            'remaining' => 0,
            'reset' => $oldest + $window,
        ];
    }

    $bucket[] = $now;
    set_transient($key, $bucket, $window);

    return [
        'allowed' => true,
        'retry_after' => 0,
        'limit' => $limit,
        'remaining' => max(0, $limit - count($bucket)),
        'reset' => $bucket ? (min($bucket) + $window) : ($now + $window),
    ];
}

function impactshop_vote_jysk_ngo_active(int $campaign_id, int $ngo_id): bool
{
    global $wpdb;
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $found = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(1) FROM {$ngos_table} WHERE id = %d AND campaign_id = %d AND is_active = 1",
            $ngo_id,
            $campaign_id
        )
    );
    return (int) $found > 0;
}

function impactshop_vote_jysk_build_tally(int $campaign_id): array
{
    global $wpdb;
    $daily_table = impactshop_vote_jysk_table('impact_vote_daily');
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT d.ngo_id,
                    SUM(d.votes) as total_votes,
                    n.ngo_name,
                    n.ngo_slug
             FROM {$daily_table} d
             LEFT JOIN {$ngos_table} n ON n.id = d.ngo_id
             WHERE d.campaign_id = %d
             GROUP BY d.ngo_id, n.ngo_name, n.ngo_slug",
            $campaign_id
        ),
        ARRAY_A
    );

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'ngo_id' => (int) $row['ngo_id'],
            'votes' => (int) $row['total_votes'],
            'ngo_name' => isset($row['ngo_name']) ? (string) $row['ngo_name'] : '',
            'ngo_slug' => isset($row['ngo_slug']) ? (string) $row['ngo_slug'] : '',
        ];
    }

    return ['items' => $items];
}

function impactshop_vote_jysk_refresh_tally_cache(int $campaign_id): void
{
    $cache_key = impactshop_vote_jysk_tally_cache_key($campaign_id);
    $payload = impactshop_vote_jysk_build_tally($campaign_id);
    $etag = '"' . md5(wp_json_encode($payload)) . '"';
    set_transient($cache_key, ['etag' => $etag, 'payload' => $payload], IMPACTSHOP_VOTE_JYSK_TALLY_TTL);
}

function impactshop_vote_jysk_tally_cache_key(int $campaign_id): string
{
    return 'impact_vote_tally_' . $campaign_id;
}

function impactshop_vote_jysk_error(string $code, string $message, int $status, array $data = [], array $headers = []): WP_REST_Response
{
    return new WP_REST_Response([
        'success' => false,
        'error_code' => $code,
        'message' => $message,
        'data' => $data,
    ], $status, $headers);
}

function impactshop_vote_jysk_hash_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return hash_hmac('sha256', $ip, wp_salt('impact_vote_ip'));
}

function impactshop_vote_jysk_hash_ua(): string
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return hash_hmac('sha256', $ua, wp_salt('impact_vote_ua'));
}

function impactshop_vote_jysk_get_pseudo_id(): string
{
    if (empty($_COOKIE['impactshop_pseudo_id'])) {
        return '';
    }
    $pseudo = strtolower(sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])));
    if (!preg_match('/^[a-z0-9]{10,12}$/', $pseudo)) {
        return '';
    }
    return $pseudo;
}

function impactshop_vote_jysk_day_key(): string
{
    $tz = new DateTimeZone('Europe/Budapest');
    $dt = new DateTime('now', $tz);
    return $dt->format('Y-m-d');
}

function impactshop_vote_jysk_now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function impactshop_vote_jysk_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . $name;
}

function impactshop_vote_jysk_maybe_migrate(): void
{
    $version = (int) get_option('impactshop_vote_jysk_schema', 0);
    if ($version >= IMPACTSHOP_VOTE_JYSK_SCHEMA) {
        return;
    }
    $lock_key = 'impactshop_vote_jysk_schema_lock';
    if (get_transient($lock_key)) {
        return;
    }
    set_transient($lock_key, 1, 60);

    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $campaigns = impactshop_vote_jysk_table('impact_vote_campaigns');
    $ngos = impactshop_vote_jysk_table('impact_vote_ngos');
    $log = impactshop_vote_jysk_table('impact_vote_log');
    $daily = impactshop_vote_jysk_table('impact_vote_daily');
    $winner = impactshop_vote_jysk_table('impact_vote_winner');
    $messages = impactshop_vote_jysk_table('impact_vote_messages');
    $targets = impactshop_vote_jysk_table('impact_vote_message_targets');
    $lottery = impactshop_vote_jysk_table('impact_vote_lottery');
    $analytics = impactshop_vote_jysk_table('impact_vote_analytics');

    $sql_campaigns = "CREATE TABLE {$campaigns} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        campaign_slug VARCHAR(190) DEFAULT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL,
        video_url TEXT,
        poster_url TEXT,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uk_campaign_slug (campaign_slug),
        KEY status (status),
        KEY start_at (start_at),
        KEY end_at (end_at)
    ) {$charset};";

    $sql_ngos = "CREATE TABLE {$ngos} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        ngo_slug VARCHAR(190) NOT NULL,
        ngo_name VARCHAR(190) NOT NULL,
        description TEXT,
        logo_url TEXT,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY idx_campaign_active (campaign_id, is_active)
    ) {$charset};";

    $sql_log = "CREATE TABLE {$log} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        ngo_id BIGINT UNSIGNED NOT NULL,
        pseudo_id VARCHAR(32) NOT NULL,
        voted_at DATETIME NOT NULL,
        day_key DATE NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        ua_hash CHAR(64) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_campaign_pseudo_day (campaign_id, pseudo_id, day_key),
        KEY idx_pseudo_day (pseudo_id, day_key, campaign_id),
        KEY idx_campaign_ngo (campaign_id, ngo_id),
        KEY idx_campaign_day (campaign_id, day_key)
    ) {$charset};";

    $sql_daily = "CREATE TABLE {$daily} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        day_key DATE NOT NULL,
        campaign_id BIGINT UNSIGNED NOT NULL,
        ngo_id BIGINT UNSIGNED NOT NULL,
        votes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uk_day_campaign_ngo (day_key, campaign_id, ngo_id),
        KEY idx_campaign_votes (campaign_id, votes)
    ) {$charset};";

    $sql_winner = "CREATE TABLE {$winner} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        ngo_id BIGINT UNSIGNED NOT NULL,
        decided_at DATETIME NOT NULL,
        votes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uk_campaign (campaign_id)
    ) {$charset};";

    $sql_messages = "CREATE TABLE {$messages} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type ENUM('global', 'targeted') NOT NULL,
        content TEXT NOT NULL,
        start_at DATETIME NOT NULL,
        end_at DATETIME NOT NULL,
        priority INT DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_active_messages (type, start_at, end_at)
    ) {$charset};";

    $sql_targets = "CREATE TABLE {$targets} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id BIGINT UNSIGNED NOT NULL,
        pseudo_id VARCHAR(32) NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        read_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_pseudo_unread (pseudo_id, is_read),
        KEY message_id (message_id)
    ) {$charset};";

    $sql_lottery = "CREATE TABLE {$lottery} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        pseudo_id VARCHAR(32) NOT NULL,
        rank INT NOT NULL,
        drawn_at DATETIME NOT NULL,
        notified_at DATETIME NULL,
        claimed_at DATETIME NULL,
        status ENUM('pending', 'notified', 'claimed', 'expired') DEFAULT 'pending',
        PRIMARY KEY (id),
        UNIQUE KEY uk_campaign_rank (campaign_id, rank),
        KEY idx_campaign_status (campaign_id, status)
    ) {$charset};";

    $sql_analytics = "CREATE TABLE {$analytics} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        happened_at DATETIME NOT NULL,
        event_type VARCHAR(64) NOT NULL,
        campaign_id BIGINT UNSIGNED DEFAULT 0,
        ngo_id BIGINT UNSIGNED DEFAULT 0,
        pseudo_id_hash CHAR(64) DEFAULT '',
        meta LONGTEXT,
        PRIMARY KEY (id),
        KEY event_type (event_type),
        KEY campaign_id (campaign_id),
        KEY happened_at (happened_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_campaigns);
    dbDelta($sql_ngos);
    dbDelta($sql_log);
    dbDelta($sql_daily);
    dbDelta($sql_winner);
    dbDelta($sql_messages);
    dbDelta($sql_targets);
    dbDelta($sql_lottery);
    dbDelta($sql_analytics);

    update_option('impactshop_vote_jysk_schema', IMPACTSHOP_VOTE_JYSK_SCHEMA, false);
    delete_transient($lock_key);

    if (!wp_next_scheduled('impactshop_vote_cron')) {
        wp_schedule_event(time() + 60, 'five_minutes', 'impactshop_vote_cron');
    }
}

add_filter('cron_schedules', function (array $schedules): array {
    if (!isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 Minutes',
        ];
    }
    return $schedules;
});

function impactshop_vote_jysk_cron(): void
{
    impactshop_vote_jysk_update_campaign_statuses();
    impactshop_vote_jysk_run_lottery_if_due();
}

function impactshop_vote_jysk_update_campaign_statuses(): void
{
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $now = impactshop_vote_jysk_now_utc();

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$campaigns_table} SET status = 'active' WHERE status = 'scheduled' AND start_at <= %s",
            $now
        )
    );

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$campaigns_table} SET status = 'closed' WHERE status = 'active' AND end_at <= %s",
            $now
        )
    );
}

function impactshop_vote_jysk_run_lottery_if_due(): void
{
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $lottery_table = impactshop_vote_jysk_table('impact_vote_lottery');
    $now = impactshop_vote_jysk_now_utc();

    $campaigns = $wpdb->get_results("SELECT * FROM {$campaigns_table} WHERE status IN ('active','closed')", ARRAY_A);
    if (!$campaigns) {
        return;
    }

    foreach ($campaigns as $campaign) {
        $campaign_id = (int) $campaign['id'];
        if ($campaign_id <= 0) {
            continue;
        }
        $already = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$lottery_table} WHERE campaign_id = %d", $campaign_id)
        );
        if ($already > 0) {
            continue;
        }

        $end_at = (string) $campaign['end_at'];
        if ($end_at === '' || strtotime($end_at) > strtotime($now)) {
            continue;
        }
        $draw_time = impactshop_vote_jysk_lottery_time($end_at);
        if ($draw_time === '') {
            continue;
        }
        if (time() < strtotime($draw_time)) {
            continue;
        }

        impactshop_vote_jysk_draw_lottery($campaign_id);
    }
}

function impactshop_vote_jysk_lottery_time(string $end_at): string
{
    try {
        $dt = new DateTime($end_at, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
        $dt->modify('+1 day');
        $dt->setTime(12, 0, 0);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function impactshop_vote_jysk_draw_lottery(int $campaign_id): void
{
    global $wpdb;
    $log_table = impactshop_vote_jysk_table('impact_vote_log');
    $lottery_table = impactshop_vote_jysk_table('impact_vote_lottery');

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pseudo_id, COUNT(*) as tickets FROM {$log_table} WHERE campaign_id = %d GROUP BY pseudo_id",
            $campaign_id
        ),
        ARRAY_A
    );

    if (!$rows) {
        return;
    }

    $pool = [];
    $total = 0;
    foreach ($rows as $row) {
        $tickets = (int) $row['tickets'];
        if ($tickets <= 0) {
            continue;
        }
        $pool[] = [
            'pseudo_id' => (string) $row['pseudo_id'],
            'tickets' => $tickets,
        ];
        $total += $tickets;
    }

    if ($total <= 0) {
        return;
    }

    $winners = [];
    $rank = 1;
    while ($rank <= 3 && $pool) {
        $pick = random_int(1, $total);
        $acc = 0;
        foreach ($pool as $idx => $entry) {
            $acc += $entry['tickets'];
            if ($pick <= $acc) {
                $winners[] = [
                    'pseudo_id' => $entry['pseudo_id'],
                    'rank' => $rank,
                ];
                $total -= $entry['tickets'];
                unset($pool[$idx]);
                $pool = array_values($pool);
                $rank++;
                break;
            }
        }
        if ($total <= 0) {
            break;
        }
    }

    if (!$winners) {
        return;
    }

    $now = impactshop_vote_jysk_now_utc();
    foreach ($winners as $winner) {
        $wpdb->insert($lottery_table, [
            'campaign_id' => $campaign_id,
            'pseudo_id' => $winner['pseudo_id'],
            'rank' => $winner['rank'],
            'drawn_at' => $now,
            'status' => 'pending',
        ], ['%d', '%s', '%d', '%s', '%s']);
    }

    impactshop_vote_jysk_create_winner_messages($campaign_id, $winners);
}

function impactshop_vote_jysk_create_winner_messages(int $campaign_id, array $winners): void
{
    global $wpdb;
    $messages_table = impactshop_vote_jysk_table('impact_vote_messages');
    $targets_table = impactshop_vote_jysk_table('impact_vote_message_targets');

    $primary = array_filter($winners, function ($winner) {
        return isset($winner['rank']) && (int) $winner['rank'] <= 3;
    });

    if (!$primary) {
        return;
    }

    $content = 'Gratulalunk, nyertel egy 10 000 Ft-os JYSK utalvanyt! A nyeremenyt postan tudjuk megkuldeni, ehhez irj nekunk az office@sharity.hu cimre, es add meg a postazasi adataidat. Ha 10 napon belul nem jelentkezel, elveszited a nyeremenyt.';
    $start = impactshop_vote_jysk_now_utc();
    $end = gmdate('Y-m-d H:i:s', time() + (10 * DAY_IN_SECONDS));

    $wpdb->insert($messages_table, [
        'type' => 'targeted',
        'content' => $content,
        'start_at' => $start,
        'end_at' => $end,
        'priority' => 100,
        'created_at' => $start,
    ], ['%s', '%s', '%s', '%s', '%d', '%s']);

    $message_id = (int) $wpdb->insert_id;
    if ($message_id <= 0) {
        return;
    }

    foreach ($primary as $winner) {
        $wpdb->insert($targets_table, [
            'message_id' => $message_id,
            'pseudo_id' => (string) $winner['pseudo_id'],
            'is_read' => 0,
        ], ['%d', '%s', '%d']);
    }
}

add_action('admin_menu', 'impactshop_vote_jysk_admin_menu');
add_action('admin_post_impactshop_vote_export_log', 'impactshop_vote_jysk_export_log');
add_action('admin_post_impactshop_vote_export_daily', 'impactshop_vote_jysk_export_daily');
add_action('admin_post_impactshop_vote_draw_lottery', 'impactshop_vote_jysk_admin_draw_lottery');
add_action('admin_post_impactshop_vote_toggle_ngo', 'impactshop_vote_jysk_admin_toggle_ngo');
add_action('admin_post_impactshop_vote_import_ngos', 'impactshop_vote_jysk_admin_import_ngos');

function impactshop_vote_jysk_admin_menu(): void
{
    add_menu_page(
        'JYSK Vote',
        'JYSK Vote',
        'manage_options',
        'impactshop-vote-jysk',
        'impactshop_vote_jysk_admin_page',
        'dashicons-megaphone',
        58
    );
}

function impactshop_vote_jysk_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    impactshop_vote_jysk_admin_handle_post();

    $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'campaigns';
    $tabs = [
        'campaigns' => 'Kampanyok',
        'ngos' => 'Civil szervezetek',
        'messages' => 'Uzenetek',
        'exports' => 'Export',
    ];

    echo '<div class="wrap">';
    echo '<h1>JYSK Vote admin</h1>';
    echo '<nav class="nav-tab-wrapper">';
    foreach ($tabs as $key => $label) {
        $class = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
        $url = admin_url('admin.php?page=impactshop-vote-jysk&tab=' . $key);
        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';

    switch ($tab) {
        case 'ngos':
            impactshop_vote_jysk_admin_tab_ngos();
            break;
        case 'messages':
            impactshop_vote_jysk_admin_tab_messages();
            break;
        case 'exports':
            impactshop_vote_jysk_admin_tab_exports();
            break;
        default:
            impactshop_vote_jysk_admin_tab_campaigns();
            break;
    }

    echo '</div>';
}

function impactshop_vote_jysk_admin_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    check_admin_referer('impactshop_vote_admin');

    $action = isset($_POST['impactshop_vote_action']) ? sanitize_key((string) $_POST['impactshop_vote_action']) : '';
    if ($action === 'create_campaign') {
        impactshop_vote_jysk_admin_create_campaign();
    }
    if ($action === 'create_ngo') {
        impactshop_vote_jysk_admin_create_ngo();
    }
    if ($action === 'create_message') {
        impactshop_vote_jysk_admin_create_message();
    }
}

function impactshop_vote_jysk_admin_tab_campaigns(): void
{
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $campaigns = $wpdb->get_results("SELECT * FROM {$campaigns_table} ORDER BY id DESC", ARRAY_A);

    echo '<h2>Uj kampany</h2>';
    echo '<form method="post">';
    wp_nonce_field('impactshop_vote_admin');
    echo '<input type="hidden" name="impactshop_vote_action" value="create_campaign" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Nev</th><td><input type="text" name="name" class="regular-text" required></td></tr>';
    echo '<tr><th>Slug</th><td><input type="text" name="campaign_slug" class="regular-text" required><p class="description">Példa: jysk-komarom-szavazas</p></td></tr>';
    echo '<tr><th>Start (HU idozona)</th><td><input type="datetime-local" name="start_at" required><p class="description">Idozona: Europe/Budapest</p></td></tr>';
    echo '<tr><th>Vege (HU idozona)</th><td><input type="datetime-local" name="end_at" required><p class="description">Idozona: Europe/Budapest</p></td></tr>';
    echo '<tr><th>Video URL</th><td><input type="url" name="video_url" class="regular-text"></td></tr>';
    echo '<tr><th>Poster URL</th><td><input type="url" name="poster_url" class="regular-text"></td></tr>';
    echo '</tbody></table>';
    submit_button('Kampany letrehozasa');
    echo '</form>';

    echo '<h2>Meglevo kampanyok</h2>';
    if (!$campaigns) {
        echo '<p>Nincs kampany.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Nev</th><th>Slug</th><th>Status</th><th>Start</th><th>Vege</th><th>Akcio</th>';
    echo '</tr></thead><tbody>';
    foreach ($campaigns as $campaign) {
        $draw_url = wp_nonce_url(
            admin_url('admin-post.php?action=impactshop_vote_draw_lottery&campaign_id=' . (int) $campaign['id']),
            'impactshop_vote_admin'
        );
        echo '<tr>';
        echo '<td>' . esc_html($campaign['id']) . '</td>';
        echo '<td>' . esc_html($campaign['name']) . '</td>';
        echo '<td>' . esc_html((string) ($campaign['campaign_slug'] ?? '')) . '</td>';
        $status = (string) $campaign['status'];
        $status_color = $status === 'active' ? '#16a34a' : ($status === 'closed' ? '#64748b' : '#f97316');
        echo '<td><span style="font-weight:700;color:' . esc_attr($status_color) . '">' . esc_html($status) . '</span></td>';
        echo '<td>' . esc_html($campaign['start_at']) . '</td>';
        echo '<td>' . esc_html($campaign['end_at']) . '</td>';
        echo '<td><a class="button" href="' . esc_url($draw_url) . '">Sorsolas futtatasa</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function impactshop_vote_jysk_admin_tab_ngos(): void
{
    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');

    $campaigns = $wpdb->get_results("SELECT id, name FROM {$campaigns_table} ORDER BY id DESC", ARRAY_A);
    $ngos = $wpdb->get_results("SELECT * FROM {$ngos_table} ORDER BY id DESC", ARRAY_A);

    echo '<h2>Uj civil szervezet</h2>';
    echo '<form method="post">';
    wp_nonce_field('impactshop_vote_admin');
    echo '<input type="hidden" name="impactshop_vote_action" value="create_ngo" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Kampany</th><td><select name="campaign_id" required>';
    foreach ($campaigns as $campaign) {
        echo '<option value="' . esc_attr($campaign['id']) . '">' . esc_html($campaign['name']) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>Nev</th><td><input type="text" name="ngo_name" class="regular-text" required></td></tr>';
    echo '<tr><th>Slug</th><td><input type="text" name="ngo_slug" class="regular-text" required></td></tr>';
    echo '<tr><th>Leiras</th><td><textarea name="description" class="large-text" rows="3"></textarea></td></tr>';
    echo '<tr><th>Logo URL</th><td><input type="url" name="logo_url" class="regular-text"></td></tr>';
    echo '<tr><th>Sorrend</th><td><input type="number" name="sort_order" value="0"></td></tr>';
    echo '</tbody></table>';
    submit_button('Civil szervezet letrehozasa');
    echo '</form>';

    echo '<h2>Bulk import (CSV)</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
    wp_nonce_field('impactshop_vote_admin');
    echo '<input type="hidden" name="action" value="impactshop_vote_import_ngos" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Kampany</th><td><select name="campaign_id" required>';
    foreach ($campaigns as $campaign) {
        echo '<option value="' . esc_attr($campaign['id']) . '">' . esc_html($campaign['name']) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th>CSV fajl</th><td><input type="file" name="ngo_csv" accept=".csv" required></td></tr>';
    echo '<tr><th>Csere</th><td><label><input type="checkbox" name="replace_all" value="1"> Meglevo NGO-k inaktivalasa import elott</label></td></tr>';
    echo '<tr><th>Formatum</th><td><code>ngo_name,ngo_slug,description,logo_url,sort_order,is_active</code> (fejlec opcionális)</td></tr>';
    echo '</tbody></table>';
    submit_button('CSV import');
    echo '</form>';

    echo '<h2>Meglevo civil szervezetek</h2>';
    if (!$ngos) {
        echo '<p>Nincs civil szervezet.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Kampany</th><th>Nev</th><th>Slug</th><th>Aktiv</th><th>Sorrend</th><th>Akcio</th>';
    echo '</tr></thead><tbody>';
    foreach ($ngos as $ngo) {
        $toggle_url = wp_nonce_url(
            admin_url('admin-post.php?action=impactshop_vote_toggle_ngo&ngo_id=' . (int) $ngo['id']),
            'impactshop_vote_admin'
        );
        $active_label = (int) $ngo['is_active'] === 1 ? 'Aktiv' : 'Inaktiv';
        echo '<tr>';
        echo '<td>' . esc_html($ngo['id']) . '</td>';
        echo '<td>' . esc_html($ngo['campaign_id']) . '</td>';
        echo '<td>' . esc_html($ngo['ngo_name']) . '</td>';
        echo '<td>' . esc_html($ngo['ngo_slug']) . '</td>';
        echo '<td>' . esc_html($active_label) . '</td>';
        echo '<td>' . esc_html($ngo['sort_order']) . '</td>';
        echo '<td><a class="button" href="' . esc_url($toggle_url) . '">Valt</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function impactshop_vote_jysk_admin_tab_messages(): void
{
    global $wpdb;
    $messages_table = impactshop_vote_jysk_table('impact_vote_messages');
    $messages = $wpdb->get_results("SELECT * FROM {$messages_table} ORDER BY id DESC", ARRAY_A);

    echo '<h2>Uj uzenet</h2>';
    echo '<form method="post">';
    wp_nonce_field('impactshop_vote_admin');
    echo '<input type="hidden" name="impactshop_vote_action" value="create_message" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Tipus</th><td><select name="type"><option value="global">Global</option><option value="targeted">Cimzett</option></select></td></tr>';
    echo '<tr><th>Tartalom</th><td><textarea name="content" class="large-text" rows="4" required></textarea></td></tr>';
    echo '<tr><th>Ervenyesseg kezdete (HU)</th><td><input type="datetime-local" name="start_at" required></td></tr>';
    echo '<tr><th>Ervenyesseg vege (HU)</th><td><input type="datetime-local" name="end_at" required></td></tr>';
    echo '<tr><th>Cimzettek (pseudo_id, vesszovel)</th><td><input type="text" name="targets" class="regular-text"></td></tr>';
    echo '</tbody></table>';
    submit_button('Uzenet letrehozasa');
    echo '</form>';

    echo '<h2>Meglevo uzenetek</h2>';
    if (!$messages) {
        echo '<p>Nincs uzenet.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Tipus</th><th>Prioritas</th><th>Kezdete</th><th>Vege</th>';
    echo '</tr></thead><tbody>';
    foreach ($messages as $msg) {
        echo '<tr>';
        echo '<td>' . esc_html($msg['id']) . '</td>';
        echo '<td>' . esc_html($msg['type']) . '</td>';
        echo '<td>' . esc_html($msg['priority']) . '</td>';
        echo '<td>' . esc_html($msg['start_at']) . '</td>';
        echo '<td>' . esc_html($msg['end_at']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function impactshop_vote_jysk_admin_tab_exports(): void
{
    $log_url = wp_nonce_url(admin_url('admin-post.php?action=impactshop_vote_export_log'), 'impactshop_vote_admin');
    $daily_url = wp_nonce_url(admin_url('admin-post.php?action=impactshop_vote_export_daily'), 'impactshop_vote_admin');

    echo '<h2>CSV export</h2>';
    echo '<p><a class="button" href="' . esc_url($log_url) . '">Szavazatok export</a> '; 
    echo '<a class="button" href="' . esc_url($daily_url) . '">Daily osszesites export</a></p>';
}

function impactshop_vote_jysk_admin_create_campaign(): void
{
    $name = isset($_POST['name']) ? sanitize_text_field((string) $_POST['name']) : '';
    $campaign_slug = isset($_POST['campaign_slug']) ? sanitize_title((string) $_POST['campaign_slug']) : '';
    $start = isset($_POST['start_at']) ? (string) $_POST['start_at'] : '';
    $end = isset($_POST['end_at']) ? (string) $_POST['end_at'] : '';
    $video_url = isset($_POST['video_url']) ? esc_url_raw((string) $_POST['video_url']) : '';
    $poster_url = isset($_POST['poster_url']) ? esc_url_raw((string) $_POST['poster_url']) : '';

    $start_utc = impactshop_vote_jysk_admin_to_utc($start);
    $end_utc = impactshop_vote_jysk_admin_to_utc($end);
    if ($campaign_slug === '') {
        $campaign_slug = sanitize_title($name);
    }
    if ($name === '' || $start_utc === '' || $end_utc === '' || $campaign_slug === '') {
        return;
    }

    global $wpdb;
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $wpdb->insert($campaigns_table, [
        'name' => $name,
        'campaign_slug' => $campaign_slug,
        'start_at' => $start_utc,
        'end_at' => $end_utc,
        'status' => 'scheduled',
        'video_url' => $video_url,
        'poster_url' => $poster_url,
        'created_at' => impactshop_vote_jysk_now_utc(),
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
}

function impactshop_vote_jysk_admin_create_ngo(): void
{
    $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
    $ngo_name = isset($_POST['ngo_name']) ? sanitize_text_field((string) $_POST['ngo_name']) : '';
    $ngo_slug = isset($_POST['ngo_slug']) ? sanitize_title((string) $_POST['ngo_slug']) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field((string) $_POST['description']) : '';
    $logo_url = isset($_POST['logo_url']) ? esc_url_raw((string) $_POST['logo_url']) : '';
    $sort_order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    if ($campaign_id <= 0 || $ngo_name === '' || $ngo_slug === '') {
        return;
    }

    global $wpdb;
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $wpdb->insert($ngos_table, [
        'campaign_id' => $campaign_id,
        'ngo_slug' => $ngo_slug,
        'ngo_name' => $ngo_name,
        'description' => $description,
        'logo_url' => $logo_url,
        'sort_order' => $sort_order,
        'is_active' => 1,
    ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d']);
}

function impactshop_vote_jysk_admin_create_message(): void
{
    $type = isset($_POST['type']) ? sanitize_key((string) $_POST['type']) : 'global';
    $content = isset($_POST['content']) ? sanitize_textarea_field((string) $_POST['content']) : '';
    $start = isset($_POST['start_at']) ? (string) $_POST['start_at'] : '';
    $end = isset($_POST['end_at']) ? (string) $_POST['end_at'] : '';
    $targets = isset($_POST['targets']) ? (string) $_POST['targets'] : '';

    $start_utc = impactshop_vote_jysk_admin_to_utc($start);
    $end_utc = impactshop_vote_jysk_admin_to_utc($end);
    if ($content === '' || $start_utc === '' || $end_utc === '') {
        return;
    }

    global $wpdb;
    $messages_table = impactshop_vote_jysk_table('impact_vote_messages');
    $targets_table = impactshop_vote_jysk_table('impact_vote_message_targets');

    $wpdb->insert($messages_table, [
        'type' => $type,
        'content' => $content,
        'start_at' => $start_utc,
        'end_at' => $end_utc,
        'priority' => 0,
        'created_at' => impactshop_vote_jysk_now_utc(),
    ], ['%s', '%s', '%s', '%s', '%d', '%s']);

    $message_id = (int) $wpdb->insert_id;
    if ($message_id <= 0 || $type !== 'targeted') {
        return;
    }

    $pseudo_ids = array_filter(array_map('trim', explode(',', $targets)));
    foreach ($pseudo_ids as $pseudo_id) {
        $pseudo_id = strtolower(sanitize_text_field($pseudo_id));
        if ($pseudo_id === '') {
            continue;
        }
        $wpdb->insert($targets_table, [
            'message_id' => $message_id,
            'pseudo_id' => $pseudo_id,
            'is_read' => 0,
        ], ['%d', '%s', '%d']);
    }
}

function impactshop_vote_jysk_admin_to_utc(string $value): string
{
    try {
        $tz = new DateTimeZone('Europe/Budapest');
        $dt = new DateTime($value, $tz);
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function impactshop_vote_jysk_admin_draw_lottery(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultsag.');
    }
    check_admin_referer('impactshop_vote_admin');
    $campaign_id = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
    if ($campaign_id > 0) {
        impactshop_vote_jysk_draw_lottery($campaign_id);
    }
    wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=campaigns'));
    exit;
}

function impactshop_vote_jysk_export_log(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultsag.');
    }
    check_admin_referer('impactshop_vote_admin');

    global $wpdb;
    $log_table = impactshop_vote_jysk_table('impact_vote_log');
    $campaigns_table = impactshop_vote_jysk_table('impact_vote_campaigns');
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $rows = $wpdb->get_results(
        "SELECT l.*, c.name as campaign_name, n.ngo_name as ngo_name
        FROM {$log_table} l
        LEFT JOIN {$campaigns_table} c ON l.campaign_id = c.id
        LEFT JOIN {$ngos_table} n ON l.ngo_id = n.id
        ORDER BY l.voted_at DESC",
        ARRAY_A
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=impact_vote_log.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['campaign_id', 'campaign_name', 'ngo_id', 'ngo_name', 'pseudo_id_hash', 'voted_at', 'day_key', 'ip_hash_prefix']);
    foreach ($rows as $row) {
        $hash = hash_hmac('sha256', (string) $row['pseudo_id'], wp_salt('impact_vote_export'));
        fputcsv($out, [
            $row['campaign_id'],
            $row['campaign_name'],
            $row['ngo_id'],
            $row['ngo_name'],
            $hash,
            $row['voted_at'],
            $row['day_key'],
            substr((string) $row['ip_hash'], 0, 8),
        ]);
    }
    fclose($out);
    exit;
}

function impactshop_vote_jysk_export_daily(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultsag.');
    }
    check_admin_referer('impactshop_vote_admin');

    global $wpdb;
    $daily_table = impactshop_vote_jysk_table('impact_vote_daily');
    $rows = $wpdb->get_results("SELECT * FROM {$daily_table} ORDER BY day_key DESC", ARRAY_A);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=impact_vote_daily.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['campaign_id', 'day_key', 'ngo_id', 'votes']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['campaign_id'],
            $row['day_key'],
            $row['ngo_id'],
            $row['votes'],
        ]);
    }
    fclose($out);
    exit;
}

function impactshop_vote_jysk_admin_toggle_ngo(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultsag.');
    }
    check_admin_referer('impactshop_vote_admin');
    $ngo_id = isset($_GET['ngo_id']) ? (int) $_GET['ngo_id'] : 0;
    if ($ngo_id <= 0) {
        wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
        exit;
    }
    global $wpdb;
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    $current = (int) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM {$ngos_table} WHERE id = %d", $ngo_id));
    $next = $current === 1 ? 0 : 1;
    $wpdb->update($ngos_table, ['is_active' => $next], ['id' => $ngo_id], ['%d'], ['%d']);
    wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
    exit;
}

function impactshop_vote_jysk_admin_import_ngos(): void
{
    if (!current_user_can('manage_options')) {
        wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
        return;
    }
    check_admin_referer('impactshop_vote_admin');

    $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
    if ($campaign_id <= 0 || empty($_FILES['ngo_csv']['tmp_name'])) {
        wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
        return;
    }

    $replace_all = !empty($_POST['replace_all']);
    $file = $_FILES['ngo_csv']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) {
        wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
        return;
    }

    global $wpdb;
    $ngos_table = impactshop_vote_jysk_table('impact_vote_ngos');
    if ($replace_all) {
        $wpdb->update($ngos_table, ['is_active' => 0], ['campaign_id' => $campaign_id], ['%d'], ['%d']);
    }

    $header = fgetcsv($handle);
    $has_header = is_array($header) && in_array('ngo_name', $header, true);
    if (!$has_header) {
        rewind($handle);
    }

    $existing_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, ngo_slug FROM {$ngos_table} WHERE campaign_id = %d",
        $campaign_id
    ), ARRAY_A);
    $existing_map = [];
    foreach ($existing_rows as $row) {
        $existing_map[(string) $row['ngo_slug']] = (int) $row['id'];
    }

    $order = 1;
    while (($row = fgetcsv($handle)) !== false) {
        if (!$row || !is_array($row)) {
            continue;
        }
        $row = array_map('trim', $row);
        $name = (string) ($row[0] ?? '');
        if ($name === '' || strtolower($name) === 'ngo_name') {
            continue;
        }
        $slug = (string) ($row[1] ?? '');
        if ($slug === '') {
            $slug = sanitize_title($name);
        } else {
            $slug = sanitize_title($slug);
        }
        $desc = (string) ($row[2] ?? '');
        $logo = (string) ($row[3] ?? '');
        $sort = isset($row[4]) && $row[4] !== '' ? (int) $row[4] : $order;
        $active = isset($row[5]) && $row[5] !== '' ? (int) $row[5] : 1;

        if (isset($existing_map[$slug])) {
            $wpdb->update(
                $ngos_table,
                [
                    'ngo_name' => $name,
                    'description' => $desc,
                    'logo_url' => $logo,
                    'sort_order' => $sort,
                    'is_active' => $active,
                ],
                ['id' => $existing_map[$slug]],
                ['%s', '%s', '%s', '%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $ngos_table,
                [
                    'campaign_id' => $campaign_id,
                    'ngo_slug' => $slug,
                    'ngo_name' => $name,
                    'description' => $desc,
                    'logo_url' => $logo,
                    'sort_order' => $sort,
                    'is_active' => $active,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d']
            );
        }
        $order += 1;
    }
    fclose($handle);

    wp_safe_redirect(admin_url('admin.php?page=impactshop-vote-jysk&tab=ngos'));
    return;
}

function impactshop_vote_jysk_log_event(string $event_type, array $data = []): void
{
    global $wpdb;
    $table = impactshop_vote_jysk_table('impact_vote_analytics');
    $pseudo = isset($data['pseudo_id']) ? (string) $data['pseudo_id'] : '';
    $hash = $pseudo !== '' ? hash_hmac('sha256', $pseudo, wp_salt('impact_vote_analytics')) : '';
    $wpdb->insert($table, [
        'happened_at' => impactshop_vote_jysk_now_utc(),
        'event_type' => sanitize_key($event_type),
        'campaign_id' => isset($data['campaign_id']) ? (int) $data['campaign_id'] : 0,
        'ngo_id' => isset($data['ngo_id']) ? (int) $data['ngo_id'] : 0,
        'pseudo_id_hash' => $hash,
        'meta' => wp_json_encode($data['meta'] ?? []),
    ], ['%s', '%s', '%d', '%d', '%s', '%s']);
}
