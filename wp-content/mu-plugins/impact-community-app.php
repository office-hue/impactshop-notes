<?php
/**
 * Impact Community — Hatás Körök Frontend Application Template
 *
 * Rendered by impact-community.php template_redirect.
 * Variables available: $api_url, $nonce, $pseudo
 */
// Guard: csak akkor futtatjuk, ha a template_redirect explicit include-olja
// ($api_url be van állítva). MU-plugin load közben $api_url nincs → return.
if (!defined('ABSPATH') || !isset($api_url)) {
    return;
}

$ngo_admin_public_url = add_query_arg(['impactshop_ngo_admin' => '1'], home_url('/'));
if (defined('IMPACTSHOP_NGO_ADMIN_PUBLIC_TOKEN') && IMPACTSHOP_NGO_ADMIN_PUBLIC_TOKEN) {
    $ngo_admin_public_url = add_query_arg(
        ['token' => (string) IMPACTSHOP_NGO_ADMIN_PUBLIC_TOKEN],
        $ngo_admin_public_url
    );
}
$identity_panel_url = apply_filters('impactshop_identity_panel_url', site_url('/profil'));
$identity_restore_url = apply_filters('impactshop_identity_restore_url', site_url('/profil') . '#impactshop-restore-title');
?><!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hatás Körök — Impact Community</title>
<meta name="description" content="Fejleszd közösséged erejét az ImpactShop Hatás Körökben. Csatlakozz NGO és települési körökhöz, posztolj, szavazz, gyűjts pontokat.">
<style>
/* ================================================================
   Design Tokens
   ================================================================ */
:root {
    --bg: #FAF8F5;
    --surface: #FFFFFF;
    --ink: #1A1A2E;
    --muted: #6B7280;
    --border: #E5E7EB;
    --teal: #0D9488;
    --teal-dark: #0F766E;
    --teal-bg: #F0FDFA;
    --mint: #A7F3D0;
    --sun: #F59E0B;
    --sun-light: #FEF3C7;
    --coral: #F43F5E;
    --coral-light: #FFF1F2;
    --blue: #3B82F6;
    --blue-light: #EFF6FF;
    --radius: 12px;
    --radius-sm: 8px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
    --shadow: 0 4px 12px rgba(0,0,0,.08);
    --shadow-lg: 0 8px 24px rgba(0,0,0,.12);
    --transition: .18s ease;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-display: 'Space Grotesk', var(--font);
}

/* ================================================================
   Reset & Base
   ================================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--ink);
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

button {
    font-family: inherit;
    cursor: pointer;
    border: none;
    background: none;
}

a { color: var(--teal); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ================================================================
   Layout
   ================================================================ */
.ic-shell {
    max-width: 860px;
    margin: 0 auto;
    padding: 0 16px;
    min-height: 100vh;
}

/* ================================================================
   Header
   ================================================================ */
.ic-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(250, 248, 245, .92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 14px 0;
    margin-bottom: 24px;
}

.ic-header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 860px;
    margin: 0 auto;
    padding: 0 16px;
}

.ic-logo {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 700;
    color: var(--teal);
    display: flex;
    align-items: center;
    gap: 8px;
}

.ic-logo-icon {
    font-size: 24px;
}

.ic-nav {
    display: flex;
    gap: 6px;
}

.ic-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--radius-sm);
    color: var(--muted);
    background: rgba(255, 255, 255, .72);
    border: 1px solid rgba(13, 148, 136, .14);
    box-shadow: 0 2px 8px rgba(14, 116, 110, .06);
    transition: all var(--transition);
}

.ic-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    font-size: 14px;
}

.ic-nav-btn:hover {
    background: rgba(236, 253, 245, .88);
    border-color: rgba(13, 148, 136, .34);
    color: var(--teal);
}

.ic-nav-btn.active {
    background: linear-gradient(140deg, #0d9488, #059669);
    border-color: transparent;
    box-shadow: 0 8px 18px rgba(13, 148, 136, .24);
    color: #fff;
}

/* ================================================================
   Alert / Status Banner
   ================================================================ */
.ic-status {
    padding: 10px 16px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    margin-bottom: 16px;
    display: none;
    align-items: center;
    gap: 8px;
}

.ic-status.show { display: flex; }
.ic-status.info { background: var(--blue-light); color: var(--blue); }
.ic-status.success { background: var(--teal-bg); color: var(--teal-dark); }
.ic-status.error { background: var(--coral-light); color: var(--coral); }

/* ================================================================
   Circles Grid
   ================================================================ */
.ic-section-title {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 6px;
}

.ic-section-sub {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 20px;
}

.ic-filter-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.ic-filter-btn {
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 20px;
    border: 1px solid var(--border);
    color: var(--muted);
    background: var(--surface);
    transition: all var(--transition);
}

.ic-filter-btn:hover { border-color: var(--teal); color: var(--teal); }
.ic-filter-btn.active { background: var(--teal); color: #fff; border-color: var(--teal); }

.ic-search {
    flex: 1;
    min-width: 180px;
    padding: 7px 14px;
    border-radius: 20px;
    border: 1px solid var(--border);
    font-size: 13px;
    background: var(--surface);
    outline: none;
    transition: border-color var(--transition);
}

.ic-search:focus { border-color: var(--teal); }

.ic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.ic-view-hero {
    position: relative;
    overflow: hidden;
    padding: 24px;
    margin-bottom: 20px;
    border-radius: 24px;
    border: 1px solid rgba(13, 148, 136, .12);
    background:
        radial-gradient(circle at top right, rgba(167, 243, 208, .65), transparent 28%),
        linear-gradient(135deg, #ffffff 0%, #f0fdfa 55%, #ecfeff 100%);
    box-shadow: 0 18px 40px rgba(13, 148, 136, .08);
}

.ic-view-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    margin-bottom: 10px;
    border-radius: 999px;
    background: rgba(13, 148, 136, .08);
    color: var(--teal-dark);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.ic-view-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.ic-view-meta-card {
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, .8);
    border: 1px solid rgba(13, 148, 136, .1);
}

.ic-view-meta-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
    margin-bottom: 4px;
}

.ic-view-meta-value {
    display: block;
    font-family: var(--font-display);
    font-size: 22px;
    line-height: 1;
    color: var(--ink);
}

.ic-view-meta-note {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: var(--muted);
}

/* ================================================================
   Circle Card
   ================================================================ */
.ic-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    transition: all var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.ic-card::before {
    content: '';
    position: absolute;
    inset: 0 auto auto 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--teal) 0%, #14b8a6 45%, #f59e0b 100%);
    opacity: .9;
}

.ic-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
    border-color: var(--teal);
}

.ic-card-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 3px 8px;
    border-radius: 4px;
}

.ic-card-badge.ngo { background: var(--teal-bg); color: var(--teal); }
.ic-card-badge.settlement { background: var(--sun-light); color: #B45309; }

.ic-card-name {
    font-family: var(--font-display);
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 6px;
    padding-right: 60px;
}

.ic-card-desc {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.ic-card-stats {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--muted);
}

.ic-card-stat {
    display: flex;
    align-items: center;
    gap: 4px;
}

.ic-card-stat strong {
    color: var(--ink);
    font-weight: 600;
}

.ic-card-member-badge {
    display: inline-block;
    margin-top: 10px;
    padding: 3px 10px;
    background: var(--mint);
    color: var(--teal-dark);
    font-size: 11px;
    font-weight: 600;
    border-radius: 10px;
}

/* ================================================================
   Circle Detail View
   ================================================================ */
.ic-circle-header {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 20px;
}

.ic-circle-header-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    border: 1px solid rgba(13, 148, 136, .12);
    background:
        radial-gradient(circle at top right, rgba(167, 243, 208, .5), transparent 24%),
        linear-gradient(135deg, #ffffff 0%, #f8fffd 55%, #f0fdfa 100%);
    box-shadow: 0 18px 40px rgba(13, 148, 136, .08);
}

.ic-circle-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.ic-circle-name {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 700;
}

.ic-circle-type {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 4px 10px;
    border-radius: 6px;
}

.ic-circle-type.ngo { background: var(--teal-bg); color: var(--teal); }
.ic-circle-type.settlement { background: var(--sun-light); color: #B45309; }

.ic-circle-desc {
    color: var(--muted);
    font-size: 15px;
    margin-bottom: 16px;
}

.ic-circle-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
}

.ic-circle-stat {
    font-size: 14px;
    color: var(--muted);
}

.ic-circle-stat strong {
    font-weight: 700;
    color: var(--ink);
    font-size: 18px;
}

.ic-alias-badge {
    padding: 6px 14px;
    background: var(--teal-bg);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: var(--teal-dark);
    font-weight: 500;
}

/* ================================================================
   Action Buttons
   ================================================================ */
.ic-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: var(--radius-sm);
    transition: all var(--transition);
}

.ic-btn-primary {
    background: var(--teal);
    color: #fff;
}

.ic-btn-primary:hover { background: var(--teal-dark); }

.ic-btn-danger {
    background: var(--coral-light);
    color: var(--coral);
}

.ic-btn-danger:hover { background: var(--coral); color: #fff; }

.ic-btn-outline {
    border: 1px solid var(--border);
    color: var(--muted);
}

.ic-btn-outline:hover { border-color: var(--teal); color: var(--teal); }

.ic-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* ================================================================
   Post Composer
   ================================================================ */
.ic-composer {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at top right, rgba(167, 243, 208, .28), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, .98), rgba(240, 253, 250, .96));
    border: 1px solid rgba(13, 148, 136, .14);
    border-radius: 18px;
    padding: 18px;
    margin-bottom: 16px;
    box-shadow: 0 12px 28px rgba(13, 148, 136, .08);
}

.ic-composer::after {
    content: '';
    position: absolute;
    inset: auto -60px -70px auto;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(45, 212, 191, .12), transparent 70%);
    pointer-events: none;
}

.ic-composer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.ic-composer-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(13, 148, 136, .08);
    color: var(--teal-dark);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.ic-composer-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--teal-dark);
}

.ic-composer-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}

.ic-composer-alias {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 999px;
    background: rgba(13, 148, 136, .08);
    color: var(--teal-dark);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.ic-composer-hint {
    margin-top: 10px;
    font-size: 12px;
    color: var(--muted);
}

.ic-composer-tools {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.ic-composer-tools-label {
    font-size: 12px;
    color: var(--muted);
    font-weight: 600;
}

.ic-emoji-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ic-emoji-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid rgba(13, 148, 136, .14);
    background: rgba(255, 255, 255, .82);
    font-size: 18px;
    transition: transform var(--transition), border-color var(--transition), background var(--transition);
}

.ic-emoji-btn:hover {
    transform: translateY(-1px);
    border-color: rgba(13, 148, 136, .42);
    background: rgba(236, 253, 245, .94);
}

.ic-composer textarea {
    width: 100%;
    min-height: 92px;
    padding: 14px 15px;
    border: 1px solid rgba(13, 148, 136, .14);
    border-radius: 14px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;
    resize: vertical;
    outline: none;
    background: rgba(255, 255, 255, .92);
    transition: border-color var(--transition), box-shadow var(--transition);
}

.ic-composer textarea:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(20, 184, 166, .12);
}

.ic-input {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid rgba(13, 148, 136, .18);
    border-radius: 12px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.4;
    background: rgba(255, 255, 255, .92);
    color: var(--text);
    outline: none;
    transition: border-color var(--transition), box-shadow var(--transition);
}

.ic-input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(20, 184, 166, .12);
}

.ic-composer-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

.ic-char-count {
    font-size: 12px;
    color: var(--muted);
}

.ic-char-count.over { color: var(--coral); font-weight: 600; }

/* ================================================================
   Posts Feed
   ================================================================ */
.ic-feed-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 14px;
}

.ic-feed-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    align-items: center;
}

.ic-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .3px;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 999px;
}

.ic-chip-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
}

.ic-post-circle {
    margin-bottom: 10px;
}

.ic-chip.type-ngo { background: #ecfeff; color: #155e75; }
.ic-chip.type-ngo .ic-chip-dot { background: #0e7490; }
.ic-chip.type-settlement { background: #fffbeb; color: #92400e; }
.ic-chip.type-settlement .ic-chip-dot { background: #d97706; }

.ic-chip.color-lagoon { border: 1px solid #0ea5a3; }
.ic-chip.color-mint { border: 1px solid #10b981; }
.ic-chip.color-cobalt { border: 1px solid #2563eb; }
.ic-chip.color-amber { border: 1px solid #f59e0b; }
.ic-chip.color-coral { border: 1px solid #f43f5e; }
.ic-chip.color-slate { border: 1px solid #64748b; }
.ic-chip.color-moss { border: 1px solid #4d7c0f; }
.ic-chip.color-rose { border: 1px solid #e11d48; }
.ic-chip.color-indigo { border: 1px solid #4f46e5; }
.ic-chip.color-ember { border: 1px solid #ea580c; }

.ic-post {
    background: linear-gradient(150deg, rgba(240, 253, 244, .84), rgba(236, 253, 245, .68));
    border: 1px solid rgba(16, 185, 129, .24);
    backdrop-filter: blur(8px);
    border-radius: var(--radius);
    padding: 18px;
    margin-bottom: 12px;
    box-shadow: 0 8px 20px rgba(15, 118, 110, .08);
    transition: box-shadow var(--transition), transform var(--transition), border-color var(--transition);
}

.ic-post:hover {
    box-shadow: 0 12px 24px rgba(13, 148, 136, .14);
    transform: translateY(-1px);
    border-color: rgba(5, 150, 105, .38);
}

.ic-post.pinned {
    border-left: 3px solid var(--sun);
}

.ic-post-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.ic-post-author {
    font-size: 14px;
    font-weight: 600;
    color: var(--teal-dark);
}

.ic-post-author.impi { color: #0ea5e9; }

    .ic-impi-avatar-wrap {
        position: relative;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
    }
    .ic-impi-avatar-wrap video,
    .ic-impi-avatar-wrap img {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        display: block;
        position: relative;
        z-index: 1;
    }
    .ic-impi-avatar-ring {
        position: absolute;
        inset: -3px;
        border-radius: 13px;
        background: linear-gradient(135deg, #0ea5e9, #2563eb);
        z-index: 0;
        animation: impi-ring-pulse 2s ease-in-out infinite;
        opacity: 0.7;
    }
    @keyframes impi-ring-pulse {
        0%, 100% { opacity: 0.55; transform: scale(1); }
        50%       { opacity: 0.9;  transform: scale(1.06); }
    }
    .ic-impi-author-block {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ic-impi-label {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .ic-impi-name {
        font-size: 13px;
        font-weight: 700;
        color: #0ea5e9;
        line-height: 1.2;
    }
    .ic-impi-tag {
        font-size: 10px;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(125deg, #2563eb, #0ea5e9);
        border-radius: 6px;
        padding: 1px 5px;
        line-height: 1.4;
        letter-spacing: 0.3px;
    }

.ic-post-time {
    font-size: 12px;
    color: var(--muted);
}

.ic-post-body {
    font-size: 15px;
    line-height: 1.65;
    margin-bottom: 12px;
    white-space: pre-line;
    word-break: break-word;
}

.ic-post-footer {
    display: flex;
    align-items: center;
    gap: 16px;
}

.ic-post-reactions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ic-reaction-icon-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
    height: 34px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid rgba(16, 185, 129, .3);
    background: rgba(255, 255, 255, .74);
    color: #115e59;
    font-size: 14px;
    font-weight: 600;
    pointer-events: auto;
    touch-action: manipulation;
    transition: all var(--transition);
}

.ic-reaction-icon-btn:hover:not(:disabled) {
    background: rgba(236, 253, 245, .94);
    border-color: rgba(13, 148, 136, .62);
    transform: translateY(-1px);
}

.ic-reaction-icon-btn.voted {
    background: linear-gradient(145deg, rgba(13, 148, 136, .9), rgba(5, 150, 105, .88));
    color: #fff;
    border-color: transparent;
}

.ic-reaction-icon-btn.blocked {
    border-style: dashed;
    background: rgba(248, 250, 252, .92);
    color: #64748b;
}

.ic-reaction-count {
    min-width: 18px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
}

.ic-reaction-note {
    font-size: 12px;
    color: var(--muted);
    margin-left: 10px;
}

.ic-feed-actions {
    margin-left: auto;
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
}

.ic-vote-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--border);
    color: var(--muted);
    transition: all var(--transition);
}

.ic-vote-btn:hover:not(:disabled) {
    border-color: var(--teal);
    color: var(--teal);
    background: var(--teal-bg);
}

.ic-vote-btn.voted {
    background: var(--teal-bg);
    border-color: var(--teal);
    color: var(--teal);
}

.ic-post-delete {
    font-size: 12px;
    color: var(--muted);
    margin-left: auto;
}

.ic-post-delete:hover { color: var(--coral); }

.ic-pinned-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--sun);
    text-transform: uppercase;
    letter-spacing: .4px;
}

/* ================================================================
   Empty State
   ================================================================ */
.ic-empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--muted);
}

.ic-empty-icon {
    font-size: 48px;
    margin-bottom: 12px;
}

.ic-empty-text {
    font-size: 16px;
    margin-bottom: 6px;
}

.ic-empty-sub {
    font-size: 13px;
}

/* ================================================================
   Loading
   ================================================================ */
.ic-loading {
    display: flex;
    justify-content: center;
    padding: 32px;
}

.ic-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--border);
    border-top-color: var(--teal);
    border-radius: 50%;
    animation: ic-spin .7s linear infinite;
}

@keyframes ic-spin {
    to { transform: rotate(360deg); }
}

/* ================================================================
   Pagination
   ================================================================ */
.ic-pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 24px 0;
}

/* ================================================================
   Back Link
   ================================================================ */
.ic-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 16px;
}

.ic-back:hover { color: var(--teal); text-decoration: none; }

/* ================================================================
   No-auth prompt
   ================================================================ */
.ic-auth-prompt {
    padding: 14px 18px;
    background: var(--sun-light);
    border-radius: var(--radius-sm);
    font-size: 14px;
    color: #92400E;
    margin-bottom: 16px;
}

.ic-feed-hero {
    position: relative;
    overflow: hidden;
    padding: 24px;
    margin-bottom: 18px;
    border: 1px solid rgba(13, 148, 136, .18);
    border-radius: 24px;
    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 48%, #f0fdfa 100%);
    color: #f8fffe;
    box-shadow: 0 16px 40px rgba(15, 118, 110, .18);
}

.ic-feed-hero::after {
    content: '';
    position: absolute;
    inset: auto -40px -50px auto;
    width: 180px;
    height: 180px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .14);
}

.ic-feed-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    margin-bottom: 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .22);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.ic-feed-hero-title {
    font-family: var(--font-display);
    font-size: 30px;
    line-height: 1.05;
    margin-bottom: 10px;
    max-width: 12ch;
}

.ic-feed-hero-sub {
    max-width: 58ch;
    font-size: 15px;
    color: rgba(248, 255, 254, .92);
    margin-bottom: 18px;
}

.ic-feed-glance {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.ic-feed-glance-card {
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .22);
}

.ic-feed-glance-label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: rgba(248, 255, 254, .78);
    margin-bottom: 4px;
}

.ic-feed-glance-value {
    display: block;
    font-family: var(--font-display);
    font-size: 22px;
    line-height: 1;
}

.ic-feed-glance-note {
    display: block;
    font-size: 12px;
    color: rgba(248, 255, 254, .84);
    margin-top: 4px;
}

.ic-feed-empty {
    padding: 28px;
    border-radius: 24px;
    border: 1px solid #c7f9f1;
    background:
        radial-gradient(circle at top left, rgba(20, 184, 166, .18), transparent 34%),
        linear-gradient(180deg, #ffffff 0%, #f4fffd 100%);
    box-shadow: 0 18px 40px rgba(13, 148, 136, .08);
}

.ic-feed-empty .ic-empty-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 20px;
    background: linear-gradient(135deg, #ccfbf1, #fef3c7);
    display: grid;
    place-items: center;
    font-size: 30px;
}

.ic-feed-empty .ic-empty-text {
    font-family: var(--font-display);
    font-size: 24px;
    margin-bottom: 8px;
}

.ic-feed-empty .ic-empty-sub {
    max-width: 48ch;
    margin: 0 auto 18px;
}

/* ================================================================
   Footer
   ================================================================ */
.ic-footer {
    text-align: center;
    padding: 24px 0 32px;
    font-size: 12px;
    color: var(--muted);
    border-top: 1px solid var(--border);
    margin-top: 48px;
}

/* ================================================================
   Responsive
   ================================================================ */
@media (max-width: 600px) {
    .ic-grid {
        grid-template-columns: 1fr;
    }
    .ic-circle-name {
        font-size: 22px;
    }
    .ic-card-name {
        font-size: 15px;
    }
    .ic-header-inner {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
    .ic-feed-hero {
        padding: 20px;
        border-radius: 20px;
    }
    .ic-feed-hero-title {
        font-size: 24px;
    }
    .ic-feed-glance {
        grid-template-columns: 1fr;
    }
    .ic-view-meta {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Header (sticky) -->
<header class="ic-header">
    <div class="ic-header-inner">
        <div class="ic-logo">
            <span class="ic-logo-icon">🌍</span>
            <span>Hatás Körök</span>
        </div>
        <nav class="ic-nav">
            <button class="ic-nav-btn" data-nav="circles"><span class="ic-nav-icon">🧭</span><span>Körök</span></button>
            <button class="ic-nav-btn" data-nav="mine"><span class="ic-nav-icon">🌱</span><span>Saját köreim</span></button>
            <button class="ic-nav-btn active" data-nav="feed"><span class="ic-nav-icon">✨</span><span>Folyam</span></button>
            <button class="ic-nav-btn" data-nav="ngo-admin"><span class="ic-nav-icon">🛠️</span><span>NGO admin</span></button>
        </nav>
    </div>
</header>

<!-- Main shell -->
<main class="ic-shell">
    <div id="ic-status" class="ic-status"></div>
    <div id="ic-content"></div>
</main>

<footer class="ic-footer">
    Hatás Körök — Impact Community &middot; <a href="https://app.sharity.hu">ImpactShop</a>
</footer>

<script>
/* ===================================================================
   Impact Community — Client Application
   =================================================================== */
(function() {
    'use strict';

    /* --- Config ---------------------------------------------------- */
    const API = <?php echo wp_json_encode(esc_url_raw($api_url)); ?>;
    const NGO_ADMIN_PUBLIC_URL = <?php echo wp_json_encode(esc_url_raw($ngo_admin_public_url)); ?>;
    const IDENTITY_PANEL_URL = <?php echo wp_json_encode(esc_url_raw($identity_panel_url)); ?>;
    const IDENTITY_RESTORE_URL = <?php echo wp_json_encode(esc_url_raw($identity_restore_url)); ?>;
    let NONCE = <?php echo wp_json_encode($nonce); ?>;
    let HAS_PSEUDO = <?php echo $pseudo ? 'true' : 'false'; ?>;
    let PSEUDO_HASH_HINT = '';
    const MAX_BODY = <?php echo IC_MAX_BODY_LENGTH; ?>;

    const $content = document.getElementById('ic-content');
    const $status  = document.getElementById('ic-status');

    /* --- State ------------------------------------------------------ */
    function votedStorageKey() {
        return 'ic_voted:' + (PSEUDO_HASH_HINT || 'anon');
    }

    function loadVotedPosts() {
        try {
            const raw = localStorage.getItem(votedStorageKey()) || '[]';
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return new Set();
            }
            return new Set(parsed.map((v) => String(v)));
        } catch (_) {
            return new Set();
        }
    }

    function saveVotedPosts() {
        try {
            localStorage.setItem(votedStorageKey(), JSON.stringify([...state.votedPosts]));
        } catch (_) {
            // Local storage errors should not break voting UX.
        }
    }

    let state = {
        view: 'feed',           // 'circles' | 'mine' | 'feed' | 'circle' | 'ngo-admin'
        circleId: null,
        circles: [],
        myCircles: [],
        feedItems: [],
        posts: [],
        circle: null,
        filter: '',             // '' | 'ngo' | 'settlement'
        feedType: '',           // '' | 'ngo' | 'settlement'
        feedCircleId: 0,
        search: '',
        page: 1,
        circlesPerPage: 30,
        totalCircles: 0,
        feedPage: 1,
        totalFeed: 0,
        feedUnreadCount: 0,
        feedHasMore: false,
        feedError: '',
        feedLoadingMore: false,
        postPage: 1,
        totalPosts: 0,
        ngoAdminAccess: [],
        ngoCompanyResults: [],
        ngoCompanySearchLoading: false,
        ngoSelectedCompany: null,
        ngoLastSearchQuery: '',
        ngoLastSearchTax: '',
        ngoAdminFocusCircleId: null,
        ngoWorkspaceSlug: '',
        votedPosts: loadVotedPosts(),
    };

    function getNgoAccessForCircle(circleId) {
        const id = Number(circleId) || 0;
        if (!id) return null;
        return state.ngoAdminAccess.find((item) => Number(item.circle_id) === id) || null;
    }

    function canPostAsNgo(circleId) {
        const item = getNgoAccessForCircle(circleId);
        return !!(item && item.is_registered && item.can_post_as_ngo);
    }

    async function loadNgoAdminAccess() {
        if (!HAS_PSEUDO) {
            state.ngoAdminAccess = [];
            return;
        }
        try {
            const data = await api('/ngo/admin/mine');
            state.ngoAdminAccess = Array.isArray(data.ngo_admin) ? data.ngo_admin : [];
        } catch (_) {
            state.ngoAdminAccess = [];
        }
    }

    async function searchNgoCompanies(query, taxNumber = '') {
        const cleanQuery = String(query || '').trim();
        const cleanTax = String(taxNumber || '').trim();
        state.ngoLastSearchQuery = cleanQuery;
        state.ngoLastSearchTax = cleanTax;
        if (cleanQuery.length < 3 && cleanTax.length < 8) {
            state.ngoCompanyResults = [];
            state.ngoSelectedCompany = null;
            return;
        }

        state.ngoCompanySearchLoading = true;
        try {
            const data = await api('/ngo/admin/company-search', {
                method: 'POST',
                body: JSON.stringify({
                    query: cleanQuery,
                    tax_number: cleanTax,
                    limit: 8,
                }),
            });
            state.ngoCompanyResults = Array.isArray(data.items) ? data.items : [];
            state.ngoSelectedCompany = null;
        } catch (err) {
            state.ngoCompanyResults = [];
            state.ngoSelectedCompany = null;
            showStatus('A Cegjelzo kereses sikertelen: ' + (err.message || 'Ismeretlen hiba'), 'error');
        } finally {
            state.ngoCompanySearchLoading = false;
        }
    }

    async function registerNgoAdminAccess(circleId, options = {}) {
        const numericId = Number(circleId) || 0;
        const selectedCompany = options && typeof options.selectedCompany === 'object' ? options.selectedCompany : null;

        if (!numericId && !selectedCompany) {
            showStatus('Valassz NGO szervezetet, vagy add meg a korazonositot.', 'error');
            return;
        }

        let contactEmail = String(options.contactEmail || '').trim();
        let displayName = String(options.displayName || '').trim();

        if (!options || Object.keys(options).length === 0) {
            const emailRaw = window.prompt('Kapcsolattartó e-mail (opcionális):', '');
            if (emailRaw === null) {
                return;
            }
            contactEmail = String(emailRaw || '').trim();
        }

        try {
            const payload = {
                contact_email: contactEmail,
                display_name: displayName,
            };
            if (numericId > 0) {
                payload.circle_id = numericId;
            }
            if (selectedCompany) {
                payload.selected_company = selectedCompany;
            }

            await api('/ngo/admin/register', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await loadNgoAdminAccess();
            showStatus('NGO regisztráció kész. Most már posztolhatsz NGO név alatt is.', 'success');
            if (state.view === 'ngo-admin') {
                renderNgoAdmin();
            }
        } catch (err) {
            showStatus('Az NGO admin regisztráció nem sikerült: ' + (err.message || 'Ismeretlen hiba'), 'error');
        }
    }

    async function ensureCircleAlias(circleId) {
        const numericId = Number(circleId) || 0;
        if (!numericId) return '';

        const existing = state.myCircles.find((circle) => Number(circle.id) === numericId);
        if (existing && existing.my_alias) {
            return String(existing.my_alias);
        }

        try {
            const data = await api(`/circles/${numericId}`);
            const alias = String((data && data.circle && data.circle.my_alias) || '');
            if (!alias) {
                return '';
            }
            state.myCircles = state.myCircles.map((circle) => (
                Number(circle.id) === numericId ? {...circle, my_alias: alias} : circle
            ));
            return alias;
        } catch (_) {
            return '';
        }
    }

    async function refreshAuthState() {
        try {
            const authResp = await fetch(API.replace(/\/$/, '') + '/auth/status', {
                credentials: 'same-origin',
            });
            if (!authResp.ok) {
                return;
            }
            const auth = await authResp.json();
            if (auth && auth.nonce) {
                NONCE = auth.nonce;
            }
            const nextHasPseudo = !!(auth && auth.authenticated);
            const nextHint = String((auth && auth.pid_hash) || '').replace(/\.\.\.$/, '') || '';
            const identityChanged = nextHint !== PSEUDO_HASH_HINT;
            HAS_PSEUDO = nextHasPseudo;
            PSEUDO_HASH_HINT = nextHint;
            if (identityChanged) {
                state.votedPosts = loadVotedPosts();
            }
        } catch (_) {
            // Best effort only; initial server-rendered auth state is the fallback.
        }
    }

    /* --- API helper ------------------------------------------------- */
    async function api(path, opts = {}) {
        const url = API.replace(/\/$/, '') + path;
        const method = String(opts.method || 'GET').toUpperCase();
        const sendNonce = method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';
        const headers = {
            'Content-Type': 'application/json',
            ...(sendNonce ? {'X-WP-Nonce': NONCE} : {}),
        };
        try {
            const resp = await fetch(url, {
                credentials: 'same-origin',
                headers,
                ...opts,
            });

            // Nonce may expire — refresh and retry once on 403
            if (resp.status === 401 && !opts._retried) {
                await refreshAuthState();
                const retryHeaders = {
                    'Content-Type': 'application/json',
                    ...(sendNonce ? {'X-WP-Nonce': NONCE} : {}),
                };
                return api(path, { ...opts, _retried: true, headers: retryHeaders });
            }

            if (resp.status === 403 && !opts._retried) {
                await refreshAuthState();
                const retryHeaders = {
                    'Content-Type': 'application/json',
                    ...(sendNonce ? {'X-WP-Nonce': NONCE} : {}),
                };
                return api(path, { ...opts, _retried: true, headers: retryHeaders });
            }

            const data = await resp.json();
            if (!resp.ok) {
                throw new Error(data.message || `HTTP ${resp.status}`);
            }
            return data;
        } catch (err) {
            throw err;
        }
    }

    /* --- Status banner ---------------------------------------------- */
    function showStatus(msg, type = 'info') {
        $status.textContent = msg;
        $status.className = 'ic-status show ' + type;
        if (type !== 'error') {
            setTimeout(() => { $status.classList.remove('show'); }, 4000);
        }
    }

    function trackFeedEvent(name, payload = {}) {
        if (!name) return;
        const detail = {
            event: name,
            ts: new Date().toISOString(),
            view: state.view,
            ...payload,
        };
        try {
            window.dispatchEvent(new CustomEvent('impact_feed_event', {detail}));
        } catch (_) {
            // Telemetry should never break UX.
        }
    }

    /* --- Render engine ---------------------------------------------- */
    function render() {
        switch (state.view) {
            case 'circles': renderCircles(state.circles, false); break;
            case 'mine':    renderCircles(state.myCircles, true); break;
            case 'feed':    renderFeed(); break;
            case 'circle':  renderCircleDetail(); break;
            case 'ngo-admin': renderNgoAdmin(); break;
            case 'ngo-workspace': renderNgoWorkspace(); break;
        }
        updateNav();
    }

    function renderNgoAdmin() {
        $content.innerHTML = '';

        const head = html('section', {className: 'ic-view-hero'});
        head.appendChild(html('div', {className: 'ic-view-kicker'}, 'NGO ADMIN', '•', 'JOGKEZELÉS'));
        head.appendChild(html('h1', {className: 'ic-section-title'}, 'NGO admin felület'));
        head.appendChild(html('p', {className: 'ic-section-sub'}, 'Sima regisztracios urláp Cegjelzo keresessel: nevre szurve listat kapsz, vagy adoszammal gyorsan megtalalod a szervezetet. Kivalasztas utan minden elerheto cegadat mezonkent betoltodik.'));
        head.appendChild(html('div', {className: 'ic-card-actions'},
            html('button', {
                className: 'ic-btn ic-btn-primary',
                onClick: () => navigate('circles'),
            }, 'NGO körök')
        ));
        $content.appendChild(head);

        const pseudoWidget = renderPseudoIdWidget();
        if (pseudoWidget) {
            $content.appendChild(pseudoWidget);
        }

        if (!HAS_PSEUDO) {
            const empty = html('div', {className: 'ic-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, '🔐'));
            empty.appendChild(html('p', {className: 'ic-empty-text'}, 'A használathoz pseudo azonosítás szükséges.'));
            empty.appendChild(html('p', {className: 'ic-empty-sub'}, 'Belepes utan a Cegjelzo-alapu regisztracios urláp jelenik meg.'));
            empty.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: async () => {
                        await refreshAuthState();
                        await loadNgoAdminAccess();
                        renderNgoAdmin();
                    },
                }, 'Állapot frissítése')
            ));
            $content.appendChild(empty);
            return;
        }

        const registerWrap = html('div', {className: 'ic-card'});
        registerWrap.appendChild(html('div', {className: 'ic-card-name'}, 'Szimpla NGO regisztráció'));
        registerWrap.appendChild(html('p', {className: 'ic-card-desc'}, '1) Keress nev vagy adoszam alapjan. 2) Valaszd ki a sajat NGO-t. 3) Ellenorizd az automatikusan letoltott cegadatokat. 4) Aktivald a regisztraciot.'));

        const form = html('form', {className: 'ic-card-actions', style: 'display:grid;gap:10px'});
        const queryInput = html('input', {
            type: 'text',
            className: 'ic-input',
            id: 'ic-ngo-search-name',
            placeholder: 'NGO neve (minimum 3 karakter)',
            maxLength: 180,
        });
        const taxInput = html('input', {
            type: 'text',
            className: 'ic-input',
            id: 'ic-ngo-search-tax',
            placeholder: 'Adoszam (pl. 19329006-1-17)',
            maxLength: 32,
        });

        const searchActions = html('div', {className: 'ic-card-actions'});
        const triggerCompanySearch = async () => {
            await searchNgoCompanies(queryInput.value, taxInput.value);
            renderNgoAdmin();
        };

        const handleSearchInputKeydown = async (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            await triggerCompanySearch();
        };

        queryInput.addEventListener('keydown', handleSearchInputKeydown);
        taxInput.addEventListener('keydown', handleSearchInputKeydown);

        searchActions.appendChild(html('button', {
            type: 'button',
            className: 'ic-btn ic-btn-outline',
            onClick: triggerCompanySearch,
        }, 'Keresés Cégjelzőben'));
        searchActions.appendChild(html('button', {
            type: 'button',
            className: 'ic-btn ic-btn-outline',
            onClick: () => {
                queryInput.value = '';
                taxInput.value = '';
                state.ngoCompanyResults = [];
                state.ngoSelectedCompany = null;
                state.ngoLastSearchQuery = '';
                state.ngoLastSearchTax = '';
                renderNgoAdmin();
            },
        }, 'Találatok törlése'));

        const emailInput = html('input', {
            type: 'email',
            className: 'ic-input',
            id: 'ic-ngo-register-email',
            placeholder: 'Kapcsolattartó e-mail (opcionális)',
        });
        const nameInput = html('input', {
            type: 'text',
            className: 'ic-input',
            id: 'ic-ngo-register-name',
            placeholder: 'Megjelenített név (opcionális)',
            maxLength: 120,
        });

        form.appendChild(queryInput);
        form.appendChild(taxInput);
        form.appendChild(searchActions);

        if (state.ngoCompanySearchLoading) {
            form.appendChild(html('p', {className: 'ic-card-desc'}, 'Cegjelzo kereses folyamatban...'));
        }

        const hasSearchIntent = String(state.ngoLastSearchQuery || '').trim().length >= 3 || String(state.ngoLastSearchTax || '').replace(/\D+/g, '').length >= 8;
        if (!state.ngoCompanySearchLoading && hasSearchIntent && (state.ngoCompanyResults || []).length === 0) {
            form.appendChild(html('p', {className: 'ic-card-desc'}, 'Nincs talalat erre a keresesre. Probald teljesebb nevvel vagy ellenorizd az adoszam formatumat.'));

            const manualWrap = html('div', {className: 'ic-card'});
            manualWrap.appendChild(html('div', {className: 'ic-card-name'}, 'Nem listazta? Rogzitsuk manualisan'));
            manualWrap.appendChild(html('p', {className: 'ic-card-desc'}, 'Ha biztosan letezik a szervezet, folytathatod kezi rogzitessel is.'));
            manualWrap.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    type: 'button',
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => {
                        const manualName = String(queryInput.value || '').trim();
                        const manualTax = String(taxInput.value || '').trim();
                        if (manualName.length < 3) {
                            showStatus('A manualis rogziteshez legalabb 3 karakteres szervezetnevet adj meg.', 'error');
                            return;
                        }
                        state.ngoSelectedCompany = {
                            display_name: manualName,
                            official_name: manualName,
                            short_name: manualName,
                            tax_number: manualTax,
                            registration_number: '',
                            org_type: 'manual',
                            status_label: 'manual_entry',
                            representatives: [],
                            proceedings: [],
                            raw: {
                                source: 'manual_fallback',
                                query: manualName,
                                tax_number: manualTax,
                            },
                        };
                        showStatus('Manualis NGO kivalasztas beallitva. Folytasd a regisztracio aktivalasaval.', 'info');
                        renderNgoAdmin();
                    },
                }, 'Szervezet manualis kivalasztasa')
            ));
            form.appendChild(manualWrap);
        }

        if ((state.ngoCompanyResults || []).length > 0) {
            const resultsWrap = html('div', {className: 'ic-grid', style: 'margin-top:4px'});
            state.ngoCompanyResults.forEach((company) => {
                const item = html('div', {className: 'ic-card'});
                item.appendChild(html('div', {className: 'ic-card-name'}, company.display_name || company.official_name || company.short_name || 'Ismeretlen NGO'));
                const queryNorm = String(state.ngoLastSearchQuery || '').trim().toLowerCase();
                const nameNorm = String(company.display_name || company.official_name || company.short_name || '').trim().toLowerCase();
                const queryExact = queryNorm !== '' && nameNorm === queryNorm;
                const queryPrefix = queryNorm !== '' && !queryExact && nameNorm.startsWith(queryNorm);
                const taxNorm = String(state.ngoLastSearchTax || '').replace(/\D+/g, '');
                const companyTaxNorm = String(company.tax_number || '').replace(/\D+/g, '');
                const taxExact = taxNorm !== '' && companyTaxNorm !== '' && taxNorm === companyTaxNorm;

                const matchBadges = [];
                if (taxExact) matchBadges.push('Pontos adoszam-egyezes');
                if (queryExact) matchBadges.push('Pontos nev-egyezes');
                else if (queryPrefix) matchBadges.push('Nev eleji egyezes');
                if (matchBadges.length > 0) {
                    item.appendChild(html('p', {className: 'ic-card-desc'}, matchBadges.join(' · ')));
                }
                const parts = [
                    company.tax_number ? `Adoszam: ${company.tax_number}` : '',
                    company.registration_number ? `Nyilv. szam: ${company.registration_number}` : '',
                    company.status_label ? `Statusz: ${company.status_label}` : '',
                ].filter(Boolean);
                if (parts.length > 0) {
                    item.appendChild(html('p', {className: 'ic-card-desc'}, parts.join(' · ')));
                }
                const itemActions = html('div', {className: 'ic-card-actions'});
                itemActions.appendChild(html('button', {
                    type: 'button',
                    className: 'ic-btn ' + (state.ngoSelectedCompany === company ? 'ic-btn-primary' : 'ic-btn-outline'),
                    onClick: () => {
                        state.ngoSelectedCompany = company;
                        renderNgoAdmin();
                    },
                }, state.ngoSelectedCompany === company ? 'Kiválasztva' : 'Kiválasztom'));
                item.appendChild(itemActions);
                resultsWrap.appendChild(item);
            });
            form.appendChild(resultsWrap);
        }

        if (state.ngoSelectedCompany) {
            const selectedHead = html('div', {className: 'ic-card', style: 'margin-top:8px'});
            selectedHead.appendChild(html('span', {className: 'ic-card-badge ngo'}, 'Kivalasztott NGO'));
            selectedHead.appendChild(html('div', {className: 'ic-card-name'}, state.ngoSelectedCompany.official_name || state.ngoSelectedCompany.display_name || state.ngoSelectedCompany.short_name || 'Ismeretlen NGO'));
            selectedHead.appendChild(html('p', {className: 'ic-card-desc'}, [
                state.ngoSelectedCompany.status_label ? `Statusz: ${state.ngoSelectedCompany.status_label}` : '',
                state.ngoSelectedCompany.tax_number ? `Adoszam: ${state.ngoSelectedCompany.tax_number}` : '',
                state.ngoSelectedCompany.registration_number ? `Nyilv. szam: ${state.ngoSelectedCompany.registration_number}` : '',
            ].filter(Boolean).join(' · ')));
            form.appendChild(selectedHead);

            const detailWrap = html('div', {className: 'ic-grid', style: 'margin-top:8px'});
            const detailFields = [
                ['Hivatalos név', state.ngoSelectedCompany.official_name || ''],
                ['Rövid név', state.ngoSelectedCompany.short_name || ''],
                ['Adószám', state.ngoSelectedCompany.tax_number || ''],
                ['Nyilvántartási szám', state.ngoSelectedCompany.registration_number || ''],
                ['Szervezet típus', state.ngoSelectedCompany.org_type || ''],
                ['Állapot', state.ngoSelectedCompany.status_label || ''],
                ['Státusz kód', state.ngoSelectedCompany.status_code === null || state.ngoSelectedCompany.status_code === undefined ? '' : String(state.ngoSelectedCompany.status_code)],
                ['Közhasznúsági szint', state.ngoSelectedCompany.level_of_charity || ''],
                ['Székhely cím', state.ngoSelectedCompany.address || ''],
                ['NAV cím', state.ngoSelectedCompany.nav_address || ''],
                ['Tevékenység', state.ngoSelectedCompany.activity || ''],
                ['Leírás', state.ngoSelectedCompany.description || ''],
                ['Képviselők', JSON.stringify(state.ngoSelectedCompany.representatives || [])],
                ['Eljárások', JSON.stringify(state.ngoSelectedCompany.proceedings || [])],
            ];

            detailFields.forEach(([label, value]) => {
                const row = html('div', {className: 'ic-card'});
                row.appendChild(html('div', {className: 'ic-card-badge ngo'}, label));
                row.appendChild(html('div', {className: 'ic-card-desc'}, value || '—'));
                detailWrap.appendChild(row);
            });

            form.appendChild(detailWrap);
        }

        form.appendChild(emailInput);
        form.appendChild(nameInput);
        form.appendChild(html('button', {type: 'submit', className: 'ic-btn ic-btn-primary'}, 'Regisztráció aktiválása'));
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.ngoSelectedCompany) {
                showStatus('Valaszd ki a sajat NGO szervezetedet a Cegjelzo talalatok kozul.', 'error');
                return;
            }
            await registerNgoAdminAccess(0, {
                contactEmail: emailInput.value,
                displayName: nameInput.value,
                selectedCompany: state.ngoSelectedCompany,
            });
        });

        registerWrap.appendChild(form);
        $content.appendChild(registerWrap);

        if (state.ngoAdminAccess.length === 0) {
            const empty = html('div', {className: 'ic-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, '🏢'));
            empty.appendChild(html('p', {className: 'ic-empty-text'}, 'Még nincs aktív NGO admin jogosultságod.'));
            empty.appendChild(html('p', {className: 'ic-empty-sub'}, 'A fenti Cegjelzo urláppal azonnal letrehozhato a regisztracio, nem csak az elore felvitt listabol.'));
            $content.appendChild(empty);
            return;
        }

        const grid = html('div', {className: 'ic-grid'});
        const focusCircleId = Number(state.ngoAdminFocusCircleId) || 0;
        const ngoAdminItems = focusCircleId > 0
            ? state.ngoAdminAccess.filter((entry) => Number(entry.circle_id) === focusCircleId)
            : state.ngoAdminAccess;

        if (focusCircleId > 0) {
            const focusInfo = html('div', {className: 'ic-card', style: 'margin-bottom:10px'});
            if (ngoAdminItems.length > 0) {
                focusInfo.appendChild(html('div', {className: 'ic-card-name'}, 'Kijelolt NGO admin rekord'));
                focusInfo.appendChild(html('p', {className: 'ic-card-desc'}, `Kor ID: ${focusCircleId} • Az adott NGO admin kartyat mutatjuk.`));
            } else {
                focusInfo.appendChild(html('div', {className: 'ic-card-name'}, 'A kijelolt NGO admin rekord nem talalhato'));
                focusInfo.appendChild(html('p', {className: 'ic-card-desc'}, `Kor ID: ${focusCircleId} • Lehet, hogy nincs aktiv regisztracio ehhez a korhoz.`));
            }
            focusInfo.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => {
                        state.ngoAdminFocusCircleId = null;
                        window.location.hash = '#ngo-admin';
                        renderNgoAdmin();
                    },
                }, 'Osszes NGO admin rekord mutatasa')
            ));
            $content.appendChild(focusInfo);
        }

        ngoAdminItems.forEach((item) => {
            const card = html('div', {className: 'ic-card'});
            if (focusCircleId > 0) {
                card.style.borderColor = '#0ea5a3';
                card.style.boxShadow = '0 0 0 2px rgba(14,165,163,.15)';
            }
            card.appendChild(html('span', {className: 'ic-card-badge ngo'}, 'NGO'));
            card.appendChild(html('div', {className: 'ic-card-name'}, item.circle_name || `NGO #${item.circle_id}`));

            const statusText = item.can_post_as_ngo
                ? 'Aktív: posztolhatsz NGO név alatt.'
                : item.is_registered
                    ? 'Regisztrálva, de még nincs aktív NGO-neves posztjog.'
                    : 'Még nincs NGO admin regisztráció ehhez a körhöz.';
            card.appendChild(html('p', {className: 'ic-card-desc'}, statusText));

            const actions = html('div', {className: 'ic-card-actions'});
            if (!item.can_post_as_ngo) {
                actions.appendChild(html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: () => registerNgoAdminAccess(item.circle_id),
                }, 'Jog aktiválása'));
            }

            const ngoWorkspaceUrl = buildNgoWorkspaceUrl(item);
            if (ngoWorkspaceUrl) {
                actions.appendChild(html('a', {
                    className: 'ic-btn ic-btn-outline',
                    href: ngoWorkspaceUrl,
                    target: '_blank',
                    rel: 'noopener',
                }, 'NGO Munkater (kulon oldal)'));
            } else {
                actions.appendChild(html('button', {
                    className: 'ic-btn ic-btn-outline',
                    type: 'button',
                    onClick: () => showStatus('Nem talalhato NGO admin URL ehhez a rekordhoz.', 'error'),
                }, 'NGO Munkater nem elerheto'));
            }

            actions.appendChild(html('button', {
                className: 'ic-btn ic-btn-outline',
                onClick: () => navigateNgoAdmin(item.circle_id),
            }, 'NGO admin nezet (itt)'));

            const companyMeta = [
                item.official_name ? `Hivatalos nev: ${item.official_name}` : '',
                item.tax_number ? `Adoszam: ${item.tax_number}` : '',
                item.registration_number ? `Nyilv. szam: ${item.registration_number}` : '',
            ].filter(Boolean);
            if (companyMeta.length > 0) {
                card.appendChild(html('p', {className: 'ic-card-desc'}, companyMeta.join(' · ')));
            }

            card.appendChild(actions);
            grid.appendChild(card);
        });

        if (focusCircleId > 0) {
            requestAnimationFrame(() => {
                const firstCard = grid.querySelector('.ic-card');
                if (firstCard && firstCard.scrollIntoView) {
                    firstCard.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            });
        }
        $content.appendChild(grid);
    }

    function isNgoCompanyDataSparse(item) {
        if (!item || typeof item !== 'object') {
            return true;
        }

        const coreValues = [
            item.official_name,
            item.short_name,
            item.tax_number,
            item.registration_number,
            item.org_type,
            item.address,
            item.activity,
            item.status_label,
        ];
        return !coreValues.some((value) => String(value || '').trim() !== '');
    }

    function pickNgoCompanySearchQuery(item) {
        const candidates = [
            item.official_name,
            item.display_name,
            item.circle_name,
            item.short_name,
        ];
        for (const candidate of candidates) {
            const clean = String(candidate || '').trim();
            if (clean.length >= 3) {
                return clean;
            }
        }
        return '';
    }

    function mergeNgoCompanyData(baseItem, company) {
        if (!company || typeof company !== 'object') {
            return baseItem;
        }
        return {
            ...baseItem,
            registry_id: company.registry_id || baseItem.registry_id || '',
            official_name: company.official_name || baseItem.official_name || '',
            short_name: company.short_name || baseItem.short_name || '',
            display_name: company.display_name || baseItem.display_name || '',
            tax_number: company.tax_number || baseItem.tax_number || '',
            registration_number: company.registration_number || baseItem.registration_number || '',
            org_type: company.org_type || baseItem.org_type || '',
            address: company.address || baseItem.address || '',
            nav_address: company.nav_address || baseItem.nav_address || '',
            activity: company.activity || baseItem.activity || '',
            description: company.description || baseItem.description || '',
            status_label: company.status_label || baseItem.status_label || '',
            status_code: company.status_code === null || company.status_code === undefined
                ? (baseItem.status_code === null || baseItem.status_code === undefined ? null : baseItem.status_code)
                : company.status_code,
            level_of_charity: company.level_of_charity || baseItem.level_of_charity || '',
            representatives: Array.isArray(company.representatives) ? company.representatives : (baseItem.representatives || []),
            proceedings: Array.isArray(company.proceedings) ? company.proceedings : (baseItem.proceedings || []),
        };
    }

    async function findNgoCompanyBackfill(item) {
        const query = pickNgoCompanySearchQuery(item);
        if (query === '') {
            return null;
        }

        try {
            const data = await api('/ngo/admin/company-search', {
                method: 'POST',
                body: JSON.stringify({
                    query,
                    tax_number: String(item.tax_number || '').trim(),
                    limit: 8,
                }),
            });
            const items = Array.isArray(data.items) ? data.items : [];
            if (items.length === 0) {
                return null;
            }

            const normCircle = String(item.circle_name || '').trim().toLowerCase();
            const normOfficial = String(item.official_name || '').trim().toLowerCase();
            const taxDigits = String(item.tax_number || '').replace(/\D+/g, '');

            const scored = items
                .map((entry) => {
                    const name = String(entry.display_name || entry.official_name || entry.short_name || '').trim().toLowerCase();
                    const official = String(entry.official_name || '').trim().toLowerCase();
                    const entryTaxDigits = String(entry.tax_number || '').replace(/\D+/g, '');
                    let score = 0;
                    if (taxDigits && entryTaxDigits && taxDigits === entryTaxDigits) score += 12;
                    if (normOfficial && official === normOfficial) score += 9;
                    if (normCircle && name === normCircle) score += 7;
                    if (normCircle && name.startsWith(normCircle)) score += 4;
                    if (normOfficial && official.startsWith(normOfficial)) score += 3;
                    if (name && name === query.toLowerCase()) score += 2;
                    return {entry, score};
                })
                .sort((a, b) => b.score - a.score);

            return scored[0]?.entry || items[0] || null;
        } catch (_) {
            return null;
        }
    }

    async function renderNgoWorkspace() {
        $content.innerHTML = '';
        const slug = normalizeNgoSlug(state.ngoWorkspaceSlug || '');
        const item = findNgoWorkspaceItem(slug);
        let resolvedItem = item;
        let recoveredCompany = null;

        const head = html('section', {className: 'ic-view-hero'});
        head.appendChild(html('div', {className: 'ic-view-kicker'}, 'NGO MUNKATER', '•', (slug || 'n/a').toUpperCase()));
        head.appendChild(html('h1', {className: 'ic-section-title'}, item ? (item.circle_name || 'NGO munkater') : 'NGO munkater'));
        head.appendChild(html('p', {className: 'ic-section-sub'}, 'Kulon NGO oldal: Cegjelzo adatok, media feltoltes, Impi Agent, aukcio es tombola inditas.'));
        head.appendChild(html('div', {className: 'ic-card-actions'},
            html('button', {
                className: 'ic-btn ic-btn-outline',
                onClick: () => navigate('ngo-admin'),
            }, 'Vissza NGO adminhoz')
        ));
        $content.appendChild(head);

        if (!item) {
            const empty = html('div', {className: 'ic-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, '⚠️'));
            empty.appendChild(html('p', {className: 'ic-empty-text'}, 'Ehhez a slughoz nem talaltam NGO admin rekordot.'));
            empty.appendChild(html('p', {className: 'ic-empty-sub'}, 'Ellenorizd a jogosultsagot vagy nyisd meg az NGO admin listat.'));
            $content.appendChild(empty);
            return;
        }

        if (isNgoCompanyDataSparse(item)) {
            recoveredCompany = await findNgoCompanyBackfill(item);
            if (recoveredCompany) {
                resolvedItem = mergeNgoCompanyData(item, recoveredCompany);
            }
        }

        const details = html('div', {className: 'ic-card'});
        details.appendChild(html('div', {className: 'ic-card-name'}, 'Cegjelzo es NGO adatok'));
        if (recoveredCompany) {
            details.appendChild(html('p', {className: 'ic-card-desc'}, 'Ideiglenes Cegjelzo visszatoltes aktiv: a hianyzo mezok megjelentek. Tartos menteshez kattints a gombra.'));
            details.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    className: 'ic-btn ic-btn-primary',
                    type: 'button',
                    onClick: async () => {
                        await registerNgoAdminAccess(Number(item.circle_id) || 0, {
                            selectedCompany: recoveredCompany,
                            contactEmail: item.contact_email || '',
                            displayName: item.display_name || '',
                        });
                        state.ngoWorkspaceSlug = slug;
                        await loadNgoAdminAccess();
                        await renderNgoWorkspace();
                    },
                }, 'Cegjelzo adatok mentese ebbe az NGO rekordba')
            ));
        }
        const detailGrid = html('div', {className: 'ic-grid', style: 'margin-top:10px'});
        const detailFields = [
            ['Kor', resolvedItem.circle_name || ''],
            ['Kor slug', resolvedItem.circle_slug || ''],
            ['Kor ID', resolvedItem.circle_id === undefined || resolvedItem.circle_id === null ? '' : String(resolvedItem.circle_id)],
            ['Regisztracio allapot', resolvedItem.is_registered ? 'Igen' : 'Nem'],
            ['Fiok statusz', resolvedItem.account_status || ''],
            ['NGO neven posztolhat', resolvedItem.can_post_as_ngo ? 'Igen' : 'Nem'],
            ['Kapcsolattarto email', resolvedItem.contact_email || ''],
            ['Megjelenitett nev', resolvedItem.display_name || ''],
            ['Regisztracio datuma', resolvedItem.registered_at || ''],
            ['Registry ID', resolvedItem.registry_id || ''],
            ['Hivatalos nev', resolvedItem.official_name || ''],
            ['Rovid nev', resolvedItem.short_name || ''],
            ['Adoszam', resolvedItem.tax_number || ''],
            ['Nyilvantartasi szam', resolvedItem.registration_number || ''],
            ['Szervezet tipusa', resolvedItem.org_type || ''],
            ['Szekhely cim', resolvedItem.address || ''],
            ['NAV cim', resolvedItem.nav_address || ''],
            ['Tevekenyseg', resolvedItem.activity || ''],
            ['Leiras', resolvedItem.description || ''],
            ['Statusz cimke', resolvedItem.status_label || ''],
            ['Statusz kod', resolvedItem.status_code === undefined || resolvedItem.status_code === null ? '' : String(resolvedItem.status_code)],
            ['Kozhasznusagi szint', resolvedItem.level_of_charity || ''],
            ['Utolso Cegjelzo ellenorzes', resolvedItem.company_last_checked_at || ''],
        ];

        detailFields.forEach(([label, value]) => {
            const row = html('div', {className: 'ic-card'});
            row.appendChild(html('div', {className: 'ic-card-badge ngo'}, label));
            row.appendChild(html('div', {className: 'ic-card-desc'}, String(value || '—')));
            detailGrid.appendChild(row);
        });

        const renderStructuredList = (title, values) => {
            const row = html('div', {className: 'ic-card'});
            row.appendChild(html('div', {className: 'ic-card-badge ngo'}, title));

            const list = Array.isArray(values) ? values : [];
            if (list.length === 0) {
                row.appendChild(html('div', {className: 'ic-card-desc'}, 'Nincs adat.'));
                detailGrid.appendChild(row);
                return;
            }

            const ul = html('ul', {style: 'margin:0;padding-left:18px;display:grid;gap:8px;'});
            list.forEach((entry) => {
                if (!entry || typeof entry !== 'object') {
                    ul.appendChild(html('li', {className: 'ic-card-desc'}, String(entry || '—')));
                    return;
                }

                const pairs = Object.entries(entry)
                    .filter(([, value]) => value !== null && value !== undefined && value !== '' && typeof value !== 'object')
                    .map(([key, value]) => `${key}: ${String(value)}`);

                const li = html('li', {className: 'ic-card-desc'});
                if (pairs.length > 0) {
                    li.textContent = pairs.join(' · ');
                } else {
                    li.textContent = JSON.stringify(entry);
                }
                ul.appendChild(li);
            });

            row.appendChild(ul);
            detailGrid.appendChild(row);
        };

        renderStructuredList('Kepviselok', resolvedItem.representatives || []);
        renderStructuredList('Eljarasok', resolvedItem.proceedings || []);

        details.appendChild(detailGrid);
        $content.appendChild(details);

        const mediaCard = html('div', {className: 'ic-card'});
        mediaCard.appendChild(html('div', {className: 'ic-card-name'}, 'Logo es kepek feltoltese'));
        mediaCard.appendChild(html('p', {className: 'ic-card-desc'}, 'WP Media tarba tolti fel a kepfajlokat.'));
        const mediaActions = html('div', {className: 'ic-card-actions'});
        const fileInput = html('input', {
            type: 'file',
            accept: 'image/*',
            multiple: true,
            className: 'ic-input',
            style: 'max-width:360px;'
        });
        mediaActions.appendChild(fileInput);
        mediaActions.appendChild(html('button', {
            className: 'ic-btn ic-btn-primary',
            onClick: async () => {
                try {
                    await uploadNgoWorkspaceImages(fileInput.files);
                    showStatus('Kepfeltoltes sikeres.', 'success');
                } catch (err) {
                    showStatus('Kepfeltoltes hiba: ' + (err.message || 'ismeretlen hiba'), 'error');
                }
            },
        }, 'Kepek feltoltese'));
        mediaCard.appendChild(mediaActions);
        $content.appendChild(mediaCard);

        const impiCard = html('div', {className: 'ic-card'});
        impiCard.appendChild(html('div', {className: 'ic-card-name'}, 'Impi Agent'));
        impiCard.appendChild(html('p', {className: 'ic-card-desc'}, 'Kulon Impi chat ablak visszakotese folyamatban. Addig ez a modul hamarosan erheto el.'));
        impiCard.appendChild(html('div', {className: 'ic-card-actions'},
            html('button', {
                className: 'ic-btn ic-btn-outline',
                disabled: true,
                title: 'Hamarosan',
            }, 'Impi chat - hamarosan'),
            html('button', {
                className: 'ic-btn ic-btn-outline',
                disabled: true,
                title: 'Hamarosan',
            }, 'Kep + marketing - hamarosan')
        ));
        $content.appendChild(impiCard);

        const launchCard = html('div', {className: 'ic-card'});
        launchCard.appendChild(html('div', {className: 'ic-card-name'}, 'Aukcio es tombola inditas'));
        launchCard.appendChild(html('p', {className: 'ic-card-desc'}, 'Aukcio es tombola modul hamarosan.'));
        launchCard.appendChild(html('div', {className: 'ic-card-actions'},
            html('button', {
                className: 'ic-btn ic-btn-outline',
                disabled: true,
                title: 'Hamarosan',
            }, 'Aukcio - hamarosan'),
            html('button', {
                className: 'ic-btn ic-btn-outline',
                disabled: true,
                title: 'Hamarosan',
            }, 'Tombola - hamarosan')
        ));
        $content.appendChild(launchCard);
    }

    function getPseudoIdFromCookie() {
        const match = document.cookie.match(/(?:^|;\s*)impactshop_pseudo_id=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getPseudoIdFromQuery() {
        try {
            const qp = new URLSearchParams(window.location.search || '');
            return String(qp.get('impact_pseudo_id') || '').trim();
        } catch (_) {
            return '';
        }
    }

    function appendReturnParamToUrl(rawUrl) {
        const base = String(rawUrl || '').trim();
        if (!base) {
            return '';
        }

        try {
            const currentUrl = new URL(window.location.href);
            if (!/sharity\.hu$/i.test(currentUrl.hostname)) {
                return base;
            }

            const nextUrl = new URL(base, window.location.origin);
            nextUrl.searchParams.set('return', currentUrl.toString());
            return nextUrl.toString();
        } catch (_) {
            return base;
        }
    }

    function normalizeNgoSlug(raw) {
        const value = String(raw || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s_-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
        return value;
    }

    function getNgoSlugFromAccessItem(item) {
        if (!item || typeof item !== 'object') {
            return '';
        }
        const direct = [
            item.ngo_slug,
            item.slug,
            item.circle_slug,
            item.organization_slug,
            item.card_slug,
        ];
        for (const value of direct) {
            const slug = normalizeNgoSlug(value);
            if (slug) {
                return slug;
            }
        }
        return normalizeNgoSlug(item.circle_name);
    }

    function buildNgoWorkspaceUrl(item) {
        const slug = getNgoSlugFromAccessItem(item);
        if (!slug) {
            return '';
        }
        try {
            const current = new URL(window.location.href);
            current.hash = '#ngo-workspace/' + slug;
            return current.toString();
        } catch (_) {
            return '#ngo-workspace/' + slug;
        }
    }

    function findNgoWorkspaceItem(slug) {
        const wanted = normalizeNgoSlug(slug);
        if (!wanted) {
            return null;
        }
        return state.ngoAdminAccess.find((item) => getNgoSlugFromAccessItem(item) === wanted) || null;
    }

    async function uploadNgoWorkspaceImages(files) {
        const list = Array.from(files || []).filter((file) => file && /^image\//i.test(file.type));
        if (list.length === 0) {
            showStatus('Valassz legalabb 1 kepfajlt.', 'error');
            return;
        }
        for (const file of list) {
            const fd = new FormData();
            fd.append('file', file, file.name || 'ngo-image.jpg');
            const resp = await fetch(window.location.origin + '/wp-json/wp/v2/media', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': NONCE,
                    'Content-Disposition': `attachment; filename="${encodeURIComponent(file.name || 'ngo-image.jpg')}"`,
                },
                body: fd,
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok) {
                throw new Error(data && data.message ? String(data.message) : 'Media upload hiba');
            }
        }
    }

    async function copyPseudoIdValue(value) {
        const text = String(value || '').trim();
        if (!text) {
            throw new Error('Nincs pseudo azonosito');
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    function renderPseudoIdWidget() {
        const pseudoId = getPseudoIdFromCookie() || getPseudoIdFromQuery();
        if (!pseudoId) {
            return null;
        }

        const wrap = html('section', {className: 'ic-card', style: 'margin-bottom:16px'});
        wrap.appendChild(html('div', {className: 'ic-card-name'}, 'Fiokom pseudo azonosito'));
        wrap.appendChild(html('p', {className: 'ic-card-desc'}, 'Ha uj bongeszoben vagy uj eszkozrol nyitod meg az oldalt, ezzel az azonositoval tudod visszakotni a fiokodat.'));

        const row = html('div', {className: 'ic-card-actions'});
        row.appendChild(html('code', {
            style: 'display:inline-flex;align-items:center;padding:10px 12px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;font-weight:700;letter-spacing:.02em;min-height:42px;'
        }, pseudoId));

        row.appendChild(html('button', {
            type: 'button',
            className: 'ic-btn ic-btn-primary',
            onClick: async () => {
                try {
                    await copyPseudoIdValue(pseudoId);
                    showStatus('Pseudo azonosito masolva.', 'success');
                } catch (err) {
                    showStatus('A masolas nem sikerult: ' + (err.message || 'ismeretlen hiba'), 'error');
                }
            },
        }, 'Masolas'));

        const manageUrl = String(IDENTITY_PANEL_URL || '').trim();
        const restoreUrl = String(IDENTITY_RESTORE_URL || '').trim();
        if (manageUrl) {
            row.appendChild(html('a', {
                className: 'ic-btn ic-btn-outline',
                href: manageUrl.indexOf('#') === -1 ? (manageUrl + '#impactshop-account-top') : manageUrl,
                target: '_blank',
                rel: 'noopener',
            }, 'A fiokom kezelese'));
        }
        if (restoreUrl) {
            row.appendChild(html('a', {
                className: 'ic-btn ic-btn-outline',
                href: appendReturnParamToUrl(restoreUrl),
                target: '_blank',
                rel: 'noopener',
            }, 'Ez nem az en fiokom'));
        }

        wrap.appendChild(row);
        return wrap;
    }

    function updateNav() {
        document.querySelectorAll('.ic-nav-btn').forEach(btn => {
            if (!btn.dataset.nav) {
                btn.classList.remove('active');
                return;
            }
            btn.classList.toggle('active', btn.dataset.nav === state.view);
        });
    }

    function html(tag, attrs = {}, ...children) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'className') el.className = v;
            else if (k === 'innerHTML') el.innerHTML = v;
            else if (k.startsWith('on')) el.addEventListener(k.slice(2).toLowerCase(), v);
            else if (typeof v === 'boolean') {
                if (v) {
                    el.setAttribute(k, '');
                    try { el[k] = true; } catch (_) {}
                } else {
                    el.removeAttribute(k);
                    try { el[k] = false; } catch (_) {}
                }
            }
            else if (v !== null && v !== undefined) el.setAttribute(k, v);
        }
        for (const child of children) {
            if (typeof child === 'string') el.appendChild(document.createTextNode(child));
            else if (child) el.appendChild(child);
        }
        return el;
    }

    /* --- Circles list view ------------------------------------------ */
    function renderCircles(circles, isMine) {
        $content.innerHTML = '';

        let filtered = Array.isArray(circles) ? [...circles] : [];
        if (isMine && state.search) {
            const needle = String(state.search || '').toLowerCase();
            filtered = filtered.filter((circle) => String(circle.name || '').toLowerCase().includes(needle));
        }
        const totalPosts = circles.reduce((sum, circle) => sum + Number(circle.post_count || 0), 0);

        const header = html('section', {className: 'ic-view-hero'});
        header.appendChild(html('div', {className: 'ic-view-kicker'}, isMine ? 'SAJÁT TÉRKÉP' : 'KÖZÖSSÉGI TÉRKÉP'));
        header.appendChild(html('h1', {className: 'ic-section-title'},
            isMine ? 'Saját köreid gyorsnézete' : 'Fedezd fel a Hatás Körök hálózatát'));
        header.appendChild(html('p', {className: 'ic-section-sub'},
            isMine
                ? 'Itt látod egyben azokat a köröket, ahol már jelen vagy, és innen léphetsz tovább a közös folyamra vagy a részletekre.'
                : 'Civil és települési közösségek egy közös vizuális rendszerben. Válassz kört, csatlakozz, majd kövesd a közös folyamot.'));

        const meta = html('div', {className: 'ic-view-meta'});
        meta.appendChild(html('div', {className: 'ic-view-meta-card'},
            html('span', {className: 'ic-view-meta-label'}, isMine ? 'Aktív tagság' : 'Látható körök'),
            html('span', {className: 'ic-view-meta-value'}, String(filtered.length || 0)),
            html('span', {className: 'ic-view-meta-note'}, isMine ? 'A személyes közösségi hálód.' : 'NGO és települési közösségek vegyesen.')
        ));
        meta.appendChild(html('div', {className: 'ic-view-meta-card'},
            html('span', {className: 'ic-view-meta-label'}, 'Összes poszt'),
            html('span', {className: 'ic-view-meta-value'}, String(totalPosts || 0)),
            html('span', {className: 'ic-view-meta-note'}, 'A körkártyák élő aktivitási képpel indulnak.')
        ));
        meta.appendChild(html('div', {className: 'ic-view-meta-card'},
            html('span', {className: 'ic-view-meta-label'}, 'Következő lépés'),
            html('span', {className: 'ic-view-meta-value'}, isMine ? 'Folyam' : 'Csatlakozás'),
            html('span', {className: 'ic-view-meta-note'}, isMine ? 'Nyisd meg a közös folyamot egyetlen kattintással.' : 'Lépj be a részletekbe, és csatlakozz a kiválasztott körhöz.')
        ));
        header.appendChild(meta);
        $content.appendChild(header);

        if (!isMine) {
            const bar = html('div', {className: 'ic-filter-bar'});
            ['', 'ngo', 'settlement'].forEach((f) => {
                const label = f === '' ? 'Mind' : f === 'ngo' ? '🏢 NGO' : '🏘️ Település';
                const btn = html('button', {
                    className: 'ic-filter-btn' + (state.filter === f ? ' active' : ''),
                    onClick: () => {
                        state.filter = f;
                        state.page = 1;
                        loadCircles();
                    },
                }, label);
                bar.appendChild(btn);
            });
            const search = html('input', {
                className: 'ic-search',
                type: 'text',
                placeholder: 'Körök keresese...',
                value: state.search,
            });
            search.addEventListener('input', debounce((e) => {
                state.search = e.target.value;
                state.page = 1;
                loadCircles();
            }, 250));
            bar.appendChild(search);
            $content.appendChild(bar);
        }

        if (isMine) {
            const joinCta = html('div', {className: 'ic-card', style: 'margin-bottom:12px;'});
            joinCta.appendChild(html('div', {className: 'ic-card-name'}, 'Uj körökhöz csatlakozas'));
            joinCta.appendChild(html('p', {className: 'ic-card-desc'}, 'Itt a sajat köreidet latod. Uj NGO vagy telepules körhöz a Körök listaban tudsz csatlakozni.'));
            joinCta.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: () => navigate('circles'),
                }, 'Uj körök böngeszese es csatlakozas')
            ));
            $content.appendChild(joinCta);
        }

        if (filtered.length === 0) {
            const empty = html('div', {className: 'ic-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, isMine ? '🌱' : '🔍'));
            empty.appendChild(html('p', {className: 'ic-empty-text'},
                isMine ? 'Még nem csatlakoztál egyetlen körhöz sem.' : 'Nincs találat.'));
            if (isMine) {
                const goBtn = html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: () => navigate('circles'),
                    style: 'margin-top:12px',
                }, 'Böngészd a köröket →');
                empty.appendChild(goBtn);
            }
            $content.appendChild(empty);
            return;
        }

        const grid = html('div', {className: 'ic-grid'});
        filtered.forEach(c => {
            const card = html('div', {
                className: 'ic-card',
                onClick: () => navigateCircle(c.id),
            });

            card.appendChild(html('span', {
                className: 'ic-card-badge ' + c.type,
            }, c.type === 'ngo' ? 'NGO' : 'Település'));

            card.appendChild(html('div', {className: 'ic-card-name'}, c.name));

            if (c.description) {
                card.appendChild(html('p', {className: 'ic-card-desc'}, c.description));
            }

            const stats = html('div', {className: 'ic-card-stats'});
            stats.appendChild(html('span', {className: 'ic-card-stat', innerHTML: `<strong>${c.member_count}</strong> tag`}));
            stats.appendChild(html('span', {className: 'ic-card-stat', innerHTML: `<strong>${c.post_count}</strong> poszt`}));
            card.appendChild(stats);

            if (c.is_member) {
                card.appendChild(html('span', {className: 'ic-card-member-badge'}, '✓ Tag'));
            }

            if (!isMine && HAS_PSEUDO && !c.is_member) {
                const quickJoin = html('div', {className: 'ic-card-actions', style: 'margin-top:10px;'});
                quickJoin.appendChild(html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: async (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        await joinCircle(c.id);
                    },
                }, '✋ Csatlakozom'));
                card.appendChild(quickJoin);
            }

            grid.appendChild(card);
        });
        $content.appendChild(grid);

        if (!isMine && state.totalCircles > state.circlesPerPage) {
            const pages = Math.max(1, Math.ceil(state.totalCircles / state.circlesPerPage));
            const pag = html('div', {className: 'ic-pagination'});
            const start = Math.max(1, state.page - 2);
            const end = Math.min(pages, state.page + 2);

            if (state.page > 1) {
                pag.appendChild(html('button', {
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => { state.page = state.page - 1; loadCircles(); },
                }, '← Előző'));
            }

            for (let i = start; i <= end; i++) {
                pag.appendChild(html('button', {
                    className: 'ic-btn ' + (state.page === i ? 'ic-btn-primary' : 'ic-btn-outline'),
                    onClick: () => { state.page = i; loadCircles(); },
                }, String(i)));
            }

            if (state.page < pages) {
                pag.appendChild(html('button', {
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => { state.page = state.page + 1; loadCircles(); },
                }, 'Következő →'));
            }

            $content.appendChild(pag);
        }

    }

    function filterAndRender() {
        const list = state.view === 'mine' ? state.myCircles : state.circles;
        renderCircles(list, state.view === 'mine');
    }

    function renderFeed() {
        $content.innerHTML = '';

        const header = html('section', {className: 'ic-feed-hero'});
        header.appendChild(html('div', {className: 'ic-feed-kicker'}, 'ÚJ KEZDŐFELÜLET', '•', 'KÖZÖS FOLYAM'));
        header.appendChild(html('h1', {className: 'ic-feed-hero-title'}, 'Minden köröd egyetlen, áttekinthető folyamban.'));
        header.appendChild(html('p', {className: 'ic-feed-hero-sub'},
            'Innen indul a Hatás Körök. A friss posztok, a közösségi színek és a kör-címkék mostantól a folyamon kapnak elsőbbséget.'));

        const glance = html('div', {className: 'ic-feed-glance'});
        glance.appendChild(html('div', {className: 'ic-feed-glance-card'},
            html('span', {className: 'ic-feed-glance-label'}, 'Aktív körök'),
            html('span', {className: 'ic-feed-glance-value'}, String(state.myCircles.length || 0)),
            html('span', {className: 'ic-feed-glance-note'}, 'Innen szűrheted őket egy kattintással.')
        ));
        glance.appendChild(html('div', {className: 'ic-feed-glance-card'},
            html('span', {className: 'ic-feed-glance-label'}, 'Látható posztok'),
            html('span', {className: 'ic-feed-glance-value'}, String(state.feedItems.length || 0)),
            html('span', {className: 'ic-feed-glance-note'}, 'A közös folyam azonnal kör-címkével renderel.')
        ));
        glance.appendChild(html('div', {className: 'ic-feed-glance-card'},
            html('span', {className: 'ic-feed-glance-label'}, 'Új elemek'),
            html('span', {className: 'ic-feed-glance-value'}, String(state.feedUnreadCount || 0)),
            html('span', {className: 'ic-feed-glance-note'}, 'Az unread contract már aktív a feed válaszban.')
        ));
        header.appendChild(glance);
        $content.appendChild(header);

        const bar = html('div', {className: 'ic-feed-toolbar'});
        [
            {value: '', label: 'Mind'},
            {value: 'ngo', label: 'NGO'},
            {value: 'settlement', label: 'Település'},
        ].forEach(item => {
            bar.appendChild(html('button', {
                className: 'ic-filter-btn' + (state.feedType === item.value ? ' active' : ''),
                onClick: () => {
                    state.feedType = item.value;
                    state.feedPage = 1;
                    trackFeedEvent('feed_filter_changed', {
                        filter_type: state.feedType || 'all',
                        circle_id: state.feedCircleId || 0,
                    });
                    loadFeedMine(true);
                },
            }, item.label));
        });
        $content.appendChild(bar);

        if (state.myCircles.length > 0) {
            const circleBar = html('div', {className: 'ic-feed-toolbar'});
            circleBar.appendChild(html('button', {
                className: 'ic-chip' + (state.feedCircleId === 0 ? ' active' : ''),
                onClick: () => {
                    state.feedCircleId = 0;
                    state.feedPage = 1;
                    trackFeedEvent('feed_filter_changed', {
                        filter_type: state.feedType || 'all',
                        circle_id: 0,
                    });
                    loadFeedMine(true);
                },
            }, 'Minden kör'));

            state.myCircles.forEach(c => {
                circleBar.appendChild(html('button', {
                    className: 'ic-chip' + (state.feedCircleId === Number(c.id) ? ' active' : ''),
                    onClick: () => {
                        state.feedCircleId = Number(c.id) || 0;
                        state.feedPage = 1;
                        trackFeedEvent('feed_filter_changed', {
                            filter_type: state.feedType || 'all',
                            circle_id: state.feedCircleId || 0,
                        });
                        loadFeedMine(true);
                    },
                }, c.name));
            });

            $content.appendChild(circleBar);
        }

        const composerBox = renderFeedComposer();
        if (composerBox) {
            $content.appendChild(composerBox);
        }

        if (state.feedError) {
            const errBox = html('div', {className: 'ic-empty'});
            errBox.appendChild(html('div', {className: 'ic-empty-icon'}, '⚠️'));
            errBox.appendChild(html('p', {className: 'ic-empty-text'}, 'Nem sikerült betölteni a folyamot.'));
            errBox.appendChild(html('p', {className: 'ic-empty-sub'}, state.feedError));
            errBox.appendChild(html('button', {
                className: 'ic-btn ic-btn-primary',
                onClick: () => loadFeedMine(true),
            }, 'Újrapróbálom'));
            $content.appendChild(errBox);
            return;
        }

        if (state.feedItems.length === 0) {
            const empty = html('div', {className: 'ic-empty ic-feed-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, '🧭'));
            empty.appendChild(html('p', {className: 'ic-empty-text'}, 'A közös folyam készen áll az induláshoz.'));
            empty.appendChild(html('p', {className: 'ic-empty-sub'}, 'Most már nem a Körök lista a kezdőnézet, hanem ez a közös nyitófelület. Amint érkezik új poszt vagy csatlakozol több körhöz, itt jelenik meg minden egy helyen.'));
            empty.appendChild(html('div', {className: 'ic-card-actions'},
                html('button', {
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => navigate('mine'),
                }, 'Saját köreim'),
                html('button', {
                    className: 'ic-btn ic-btn-primary',
                    onClick: () => navigate('circles'),
                }, 'Körök felfedezése')
            ));
            $content.appendChild(empty);
            return;
        }

        $content.appendChild(html('p', {className: 'ic-section-sub'},
            `Betöltve: ${state.feedItems.length} / ${state.totalFeed || state.feedItems.length}` +
            (state.feedUnreadCount > 0 ? ` • Új: ${state.feedUnreadCount}` : '')));

        state.feedItems.forEach(p => {
            $content.appendChild(renderPost(p, {showCircleMeta: true}));
        });

        if (state.feedHasMore) {
            const more = html('div', {style: 'text-align:center;margin:20px 0'});
            more.appendChild(html('button', {
                className: 'ic-btn ic-btn-outline',
                disabled: state.feedLoadingMore,
                onClick: () => {
                    trackFeedEvent('feed_load_more', {
                        page: state.feedPage + 1,
                        filter_type: state.feedType || 'all',
                        circle_id: state.feedCircleId || 0,
                    });
                    loadFeedMine(false);
                },
            }, state.feedLoadingMore ? 'Betöltés...' : 'További folyambejegyzések...'));
            $content.appendChild(more);
        } else if (state.totalFeed > 0) {
            $content.appendChild(html('p', {
                className: 'ic-section-sub',
                style: 'text-align:center;margin:14px 0 4px',
            }, 'Nincs több folyambejegyzés.'));
        }
    }

    function renderFeedComposer() {
        if (!HAS_PSEUDO) {
            const box = html('div', {className: 'ic-composer'});
            box.appendChild(html('div', {className: 'ic-composer-label'}, 'A posztoláshoz szükség van pseudo azonosítóra.'));
            box.appendChild(html('p', {className: 'ic-empty-sub'}, 'Frissítsd az oldalt egyszer, vagy nyiss meg egy másik ImpactShop oldalt, majd térj vissza ide.'));
            return box;
        }

        if (state.myCircles.length === 0) {
            return null;
        }

        const wrap = html('div', {className: 'ic-composer'});
        wrap.appendChild(html('div', {className: 'ic-composer-eyebrow'}, 'Folyam', '•', 'Gyors posztolas'));
        const head = html('div', {className: 'ic-composer-head'});
        const titleWrap = html('div');
        titleWrap.appendChild(html('div', {className: 'ic-composer-label'}, 'Gyors poszt a folyambol'));
        titleWrap.appendChild(html('div', {className: 'ic-composer-sub'}, 'A kiválasztott körben saját álnéven vagy NGO név alatt is megjelenhet a poszt.'));
        head.appendChild(titleWrap);
        const aliasBadge = html('div', {className: 'ic-composer-alias', id: 'ic-feed-alias-badge'}, '🌿 Alnev betoltese...');
        head.appendChild(aliasBadge);
        wrap.appendChild(head);

        const selector = html('select', {id: 'ic-feed-post-circle', className: 'ic-search', style: 'margin-bottom:10px'});
        state.myCircles.forEach((circle) => {
            const opt = document.createElement('option');
            opt.value = String(circle.id);
            opt.textContent = circle.name;
            selector.appendChild(opt);
        });
        wrap.appendChild(selector);

        const ngoModeWrap = html('div', {className: 'ic-composer-hint', style: 'display:none;margin-top:0;margin-bottom:10px;'});
        const ngoMode = html('input', {type: 'checkbox', id: 'ic-feed-post-as-ngo'});
        const ngoModeLabel = html('label', {for: 'ic-feed-post-as-ngo', style: 'margin-left:8px;cursor:pointer;'}, '🏢 NGO nevében posztolok');
        ngoModeWrap.appendChild(ngoMode);
        ngoModeWrap.appendChild(ngoModeLabel);
        wrap.appendChild(ngoModeWrap);

        const syncAlias = async () => {
            const selectedCircle = state.myCircles.find((circle) => String(circle.id) === String(selector.value));
            const selectedId = Number(selector.value || 0);
            const hasNgoAccess = !!(selectedCircle && selectedCircle.type === 'ngo' && canPostAsNgo(selectedId));
            ngoModeWrap.style.display = hasNgoAccess ? 'block' : 'none';
            if (!hasNgoAccess) {
                ngoMode.checked = false;
            }

            if (ngoMode.checked && selectedCircle) {
                aliasBadge.textContent = `🏢 ${selectedCircle.name}`;
                return;
            }

            if (selectedCircle && selectedCircle.my_alias) {
                aliasBadge.textContent = `🌿 ${selectedCircle.my_alias}`;
                return;
            }
            aliasBadge.textContent = '🌿 Alnev betoltese...';
            const resolvedAlias = await ensureCircleAlias(selector.value);
            aliasBadge.textContent = resolvedAlias ? `🌿 ${resolvedAlias}` : '🌿 Alnev jelenleg nem elerheto';
        };
        selector.addEventListener('change', syncAlias);
        ngoMode.addEventListener('change', syncAlias);

        const ta = html('textarea', {
            placeholder: 'Mi ujsag a korodben? Irhatsz emojikat is, peldaul: 🌿💚🙌',
            maxlength: MAX_BODY,
            id: 'ic-feed-post-body',
        });
        wrap.appendChild(ta);
        wrap.appendChild(html('div', {className: 'ic-composer-hint'}, 'Az uzeneted barmilyen emojit elbir. Billentyuzettel is beirhatod, vagy hasznald a gyors gombokat.'));

        const tools = html('div', {className: 'ic-composer-tools'});
        tools.appendChild(html('span', {className: 'ic-composer-tools-label'}, 'Gyors emoji:'));
        const emojiRow = html('div', {className: 'ic-emoji-row'});
        ['🌿', '💚', '🙌', '👏', '🔥', '🙏', '✨', '💧'].forEach((emoji) => {
            emojiRow.appendChild(html('button', {
                type: 'button',
                className: 'ic-emoji-btn',
                title: `Emoji beszurasa: ${emoji}`,
                onClick: () => insertEmoji(ta, emoji),
            }, emoji));
        });
        tools.appendChild(emojiRow);
        wrap.appendChild(tools);

        const footer = html('div', {className: 'ic-composer-footer'});
        const count = html('span', {className: 'ic-char-count', id: 'ic-feed-post-count'}, `0 / ${MAX_BODY}`);
        ta.addEventListener('input', () => {
            count.textContent = `${ta.value.length} / ${MAX_BODY}`;
        });
        footer.appendChild(count);
        footer.appendChild(html('button', {
            type: 'button',
            className: 'ic-btn ic-btn-primary',
            onClick: async () => {
                const selectedCircle = Number(selector.value || 0);
                await createPost(selectedCircle, {
                    bodyEl: ta,
                    buttonEl: null,
                    asNgo: ngoMode.checked,
                    onSuccess: async () => {
                        ta.value = '';
                        count.textContent = `0 / ${MAX_BODY}`;
                        await primeMyCirclesForFeed();
                        await loadFeedMine(true);
                    },
                });
            },
        }, '📝 Poszt küldése'));
        wrap.appendChild(footer);

        void syncAlias();

        return wrap;
    }

    /* --- Circle detail view ----------------------------------------- */
    function renderCircleDetail() {
        const c = state.circle;
        if (!c) {
            $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
            return;
        }

        $content.innerHTML = '';

        // Back link
        const back = html('a', {
            className: 'ic-back',
            href: '#',
            onClick: e => { e.preventDefault(); navigate('circles'); },
        }, '← Vissza a körökhöz');
        $content.appendChild(back);

        // Circle header
        const hdr = html('div', {className: 'ic-circle-header ic-circle-header-hero'});
        hdr.appendChild(html('div', {className: 'ic-view-kicker'}, c.type === 'ngo' ? 'NGO KÖR' : 'TELEPÜLÉSI KÖR'));

        const top = html('div', {className: 'ic-circle-top'});
        top.appendChild(html('h1', {className: 'ic-circle-name'}, c.name));
        top.appendChild(html('span', {
            className: 'ic-circle-type ' + c.type,
        }, c.type === 'ngo' ? '🏢 NGO kör' : '🏘️ Települési kör'));
        hdr.appendChild(top);

        if (c.description) {
            hdr.appendChild(html('p', {className: 'ic-circle-desc'}, c.description));
        }

        const meta = html('div', {className: 'ic-circle-meta'});
        meta.appendChild(html('span', {className: 'ic-circle-stat', innerHTML: `<strong>${c.member_count}</strong> tag`}));
        meta.appendChild(html('span', {className: 'ic-circle-stat', innerHTML: `<strong>${c.post_count}</strong> poszt`}));
        if (c.is_member && c.my_alias) {
            meta.appendChild(html('span', {className: 'ic-alias-badge'}, c.my_alias));
        }
        hdr.appendChild(meta);

        // Action buttons
        const actions = html('div', {style: 'display:flex;gap:8px;flex-wrap:wrap'});
        if (!HAS_PSEUDO) {
            actions.appendChild(html('span', {className: 'ic-auth-prompt'},
                'Böngéssz még az oldalon, hogy csatlakozhass! 🌱'));
        } else if (c.is_member) {
            const leaveBtn = html('button', {
                className: 'ic-btn ic-btn-danger',
                onClick: () => leaveCircle(c.id),
            }, '🚪 Kilépés');
            actions.appendChild(leaveBtn);
        } else {
            const joinBtn = html('button', {
                className: 'ic-btn ic-btn-primary',
                onClick: () => joinCircle(c.id),
            }, '✋ Csatlakozom');
            actions.appendChild(joinBtn);
        }
        hdr.appendChild(actions);
        $content.appendChild(hdr);

        // Composer (only if member)
        if (c.is_member && HAS_PSEUDO) {
            const composer = html('div', {className: 'ic-composer'});
            composer.appendChild(html('div', {className: 'ic-composer-label'}, `Írj posztot ${c.my_alias} néven:`));

            let ngoCheckbox = null;
            if (c.type === 'ngo') {
                if (canPostAsNgo(c.id)) {
                    const ngoLine = html('div', {className: 'ic-composer-hint', style: 'margin-top:0;margin-bottom:10px;'});
                    ngoCheckbox = html('input', {type: 'checkbox', id: 'ic-circle-post-as-ngo'});
                    ngoLine.appendChild(ngoCheckbox);
                    ngoLine.appendChild(html('label', {for: 'ic-circle-post-as-ngo', style: 'margin-left:8px;cursor:pointer;'}, `🏢 Posztolás NGO név alatt (${c.name})`));
                    composer.appendChild(ngoLine);
                } else {
                    const ngoLine = html('div', {className: 'ic-composer-hint'});
                    ngoLine.appendChild(html('span', {}, 'Ehhez a körhöz még nincs NGO-neves posztjogod.'));
                    ngoLine.appendChild(html('button', {
                        className: 'ic-btn ic-btn-outline',
                        style: 'margin-left:10px',
                        onClick: async () => {
                            await registerNgoAdminAccess(c.id);
                            await loadNgoAdminAccess();
                            await loadCircleDetail(c.id);
                        },
                    }, 'NGO jog aktiválása'));
                    composer.appendChild(ngoLine);
                }
            }

            const ta = html('textarea', {
                placeholder: 'Oszd meg gondolataidat a körrel... (max ' + MAX_BODY + ' karakter)',
                maxlength: MAX_BODY,
                id: 'ic-post-body',
            });

            const counter = html('span', {className: 'ic-char-count', id: 'ic-char-count'}, `0 / ${MAX_BODY}`);
            ta.addEventListener('input', () => {
                const len = ta.value.length;
                counter.textContent = `${len} / ${MAX_BODY}`;
                counter.classList.toggle('over', len > MAX_BODY);
            });

            composer.appendChild(ta);

            const footer = html('div', {className: 'ic-composer-footer'});
            footer.appendChild(counter);
            footer.appendChild(html('button', {
                className: 'ic-btn ic-btn-primary',
                id: 'ic-post-send',
                onClick: () => createPost(c.id, {asNgo: !!(ngoCheckbox && ngoCheckbox.checked)}),
            }, '📝 Küldés'));
            composer.appendChild(footer);
            $content.appendChild(composer);
        }

        // Feed
        $content.appendChild(html('h2', {className: 'ic-feed-title'}, 'Közösségi fal'));

        if (state.posts.length === 0) {
            const empty = html('div', {className: 'ic-empty'});
            empty.appendChild(html('div', {className: 'ic-empty-icon'}, '💬'));
            empty.appendChild(html('p', {className: 'ic-empty-text'}, 'Még nincsenek posztok ebben a körben.'));
            empty.appendChild(html('p', {className: 'ic-empty-sub'}, 'Légy te az első, aki megosztja gondolatait!'));
            $content.appendChild(empty);
        } else {
            state.posts.forEach(p => {
                $content.appendChild(renderPost(p));
            });

            // Load more
            if (state.totalPosts > state.posts.length) {
                const more = html('div', {style: 'text-align:center;margin:20px 0'});
                more.appendChild(html('button', {
                    className: 'ic-btn ic-btn-outline',
                    onClick: () => loadMorePosts(c.id),
                }, 'Több poszt betöltése...'));
                $content.appendChild(more);
            }
        }
    }

    function renderPost(p, opts = {}) {
        const post = html('div', {className: 'ic-post' + (p.is_pinned ? ' pinned' : '')});

        if (opts.showCircleMeta && p.circle_name) {
            const type = p.circle_type === 'settlement' ? 'settlement' : 'ngo';
            const token = sanitizeColorToken(p.circle_color_token);
            post.style.borderLeft = '4px solid ' + circleTokenColor(token);
            const row = html('div', {className: 'ic-post-circle'});
            const chip = html('span', {className: `ic-chip type-${type} color-${token}`});
            chip.appendChild(html('span', {className: 'ic-chip-dot'}));
            const label = `${circleTypeLabel(type)} · ${p.circle_name}`;
            chip.appendChild(document.createTextNode(label));
            row.appendChild(chip);
            post.appendChild(row);
        }

        const head = html('div', {className: 'ic-post-head'});
        if (p.author_type === 'impi') {
            const block = html('div', {className: 'ic-impi-author-block'});
            const wrap = html('div', {className: 'ic-impi-avatar-wrap'});
            const ring = html('div', {className: 'ic-impi-avatar-ring'});
            wrap.appendChild(ring);
            const vid = document.createElement('video');
            vid.autoplay = true; vid.loop = true; vid.muted = true; vid.playsInline = true;
            const src = document.createElement('source');
            src.src = 'https://app.sharity.hu/wp-content/uploads/2025/12/Impi-Loop_Animation_Request.mp4';
            src.type = 'video/mp4';
            vid.appendChild(src);
            const img = document.createElement('img');
            img.src = 'https://app.sharity.hu/wp-content/uploads/2025/12/20251125_0859_Coupon-Hunter-Meerkat_simple_compose_01kax0a6g0f24va5gd51a68gra.png';
            img.alt = 'Impi';
            vid.appendChild(img);
            wrap.appendChild(vid);
            block.appendChild(wrap);
            const lbl = html('div', {className: 'ic-impi-label'});
            lbl.appendChild(html('span', {className: 'ic-impi-name'}, p.author_alias || 'Impi'));
            lbl.appendChild(html('span', {className: 'ic-impi-tag'}, 'AI'));
            block.appendChild(lbl);
            head.appendChild(block);
        } else {
            const authorClass = p.author_type === 'ngo' ? 'ic-post-author' : 'ic-post-author';
            const authorPrefix = p.author_type === 'ngo' ? '🏢 ' : '';
            head.appendChild(html('span', {className: authorClass}, authorPrefix + p.author_alias));
        }

        const timeRow = html('span', {className: 'ic-post-time'});
        if (p.is_pinned) {
            timeRow.appendChild(html('span', {className: 'ic-pinned-label'}, '📌 Kitűzve · '));
        }
        timeRow.appendChild(document.createTextNode(p.time_ago));
        head.appendChild(timeRow);
        post.appendChild(head);

        post.appendChild(html('div', {className: 'ic-post-body'}, p.body));

        const footer = html('div', {className: 'ic-post-footer'});

        const voted = state.votedPosts.has(String(p.id));
        const blockedReason = !HAS_PSEUDO
            ? 'A reakciohoz azonositas szukseges.'
            : p.is_own
                ? 'A sajat posztodra nem tudsz reagalni.'
                : voted
                    ? 'Erre a posztra mar reagaltal.'
                    : '';
        const reactions = html('div', {className: 'ic-post-reactions'});
        const reactionSet = [
            {emoji: '🙏', label: 'Köszi'},
            {emoji: '💚', label: 'Veled vagyok'},
            {emoji: '👏', label: 'Szép munka'},
            {emoji: '🔥', label: 'Erős poszt'},
        ];
        reactionSet.forEach((reaction, index) => {
            const btn = html('button', {
                type: 'button',
                className: 'ic-reaction-icon-btn' + (voted && index === 0 ? ' voted' : '') + (blockedReason ? ' blocked' : ''),
                disabled: !HAS_PSEUDO,
                title: blockedReason || reaction.label,
                onClick: (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    votePost(p, btn, reactions);
                },
            });
            btn.innerHTML = `${reaction.emoji}${index === 0 ? ` <span class="ic-reaction-count">${p.vote_count}</span>` : ''}`;
            reactions.appendChild(btn);
        });
        footer.appendChild(reactions);
        if (blockedReason) {
            footer.appendChild(html('span', {className: 'ic-reaction-note'}, blockedReason));
        }

        if (opts.showCircleMeta) {
            const actions = html('div', {className: 'ic-feed-actions'});
            actions.appendChild(html('button', {
                className: 'ic-btn ic-btn-outline',
                onClick: () => {
                    trackFeedEvent('feed_post_open_circle', {
                        post_id: Number(p.id) || 0,
                        circle_id: Number(p.circle_id) || 0,
                    });
                    navigateCircle(p.circle_id);
                },
            }, 'Megnyitás körben'));
            actions.appendChild(html('button', {
                className: 'ic-btn ic-btn-outline',
                onClick: () => {
                    trackFeedEvent('feed_report_click', {
                        post_id: Number(p.id) || 0,
                        circle_id: Number(p.circle_id) || 0,
                    });
                    reportPost(p.circle_id, p.id);
                },
            }, '🚩 Jelentés'));
            footer.appendChild(actions);
        }

        if (p.is_own) {
            const del = html('button', {
                className: 'ic-post-delete',
                onClick: () => deletePost(p.circle_id, p.id),
            }, '🗑 Törlés');
            footer.appendChild(del);
        }

        post.appendChild(footer);
        return post;
    }

    /* --- Navigation ------------------------------------------------- */
    function navigate(view) {
        state.view = view;
        state.circleId = null;
        state.circle = null;
        state.posts = [];
        state.search = '';
        if (view === 'circles') {
            state.filter = '';
            state.page = 1;
        }
        state.feedPage = 1;
        state.ngoWorkspaceSlug = '';
        window.location.hash = view === 'mine' ? '#mine' : view === 'feed' ? '#feed' : view === 'ngo-admin' ? '#ngo-admin' : '#circles';
        if (view === 'circles') loadCircles();
        else if (view === 'mine') loadMyCircles();
        else if (view === 'ngo-admin') {
            loadNgoAdminAccess().then(renderNgoAdmin);
        }
        else if (view === 'feed') {
            trackFeedEvent('feed_opened', {source: 'navigate'});
            if (state.myCircles.length === 0) {
                primeMyCirclesForFeed();
            }
            loadFeedMine(true);
        }
    }

    async function primeMyCirclesForFeed() {
        try {
            const data = await api('/circles/mine');
            state.myCircles = Array.isArray(data.circles) ? data.circles : [];
            await loadNgoAdminAccess();
            if (state.view === 'feed') {
                render();
            }
        } catch {
            // Feed remains usable even if member-circle prefetch fails.
        }
    }

    function navigateCircle(id) {
        state.view = 'circle';
        state.circleId = id;
        state.postPage = 1;
        window.location.hash = '#circle/' + id;
        loadCircleDetail(id);
    }

    function navigateNgoAdmin(circleId = 0) {
        const id = Number(circleId) || 0;
        state.view = 'ngo-admin';
        state.circleId = null;
        state.circle = null;
        state.posts = [];
        state.search = '';
        state.ngoAdminFocusCircleId = id > 0 ? id : null;
        window.location.hash = id > 0 ? ('#ngo-admin/' + id) : '#ngo-admin';
        loadNgoAdminAccess().then(renderNgoAdmin);
    }

    /* --- API Loaders ------------------------------------------------ */
    async function loadCircles() {
        $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        try {
            let path = `/circles?page=${state.page}&per_page=${state.circlesPerPage}`;
            if (state.filter) path += `&type=${state.filter}`;
            if (state.search) path += `&search=${encodeURIComponent(state.search)}`;
            const data = await api(path);
            state.circles = data.circles;
            state.totalCircles = data.total;
            state.circlesPerPage = Number(data.per_page || state.circlesPerPage || 30);
            render();
        } catch (err) {
            showStatus('Hiba a körök betöltésekor: ' + err.message, 'error');
        }
    }

    async function loadMyCircles() {
        $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        try {
            const data = await api('/circles/mine');
            state.myCircles = data.circles;
            await loadNgoAdminAccess();
            render();
        } catch (err) {
            showStatus('Hiba: ' + err.message, 'error');
        }
    }

    async function loadFeedMine(reset = true) {
        if (reset) {
            state.feedError = '';
            state.feedHasMore = false;
            $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        } else {
            state.feedLoadingMore = true;
            render();
        }
        try {
            const page = reset ? 1 : (state.feedPage + 1);
            let path = `/feed/mine?page=${page}&per_page=20`;
            if (state.feedType) {
                path += `&type=${state.feedType}`;
            }
            if (state.feedCircleId > 0) {
                path += `&circle_id=${state.feedCircleId}`;
            }
            const data = await api(path);
            const items = Array.isArray(data.items) ? data.items : [];
            state.feedPage = data.page || page;
            state.totalFeed = data.total || 0;
            state.feedUnreadCount = Number(data.unread_count || 0);
            if (reset) {
                state.feedItems = items;
            } else {
                state.feedItems = state.feedItems.concat(items);
            }
            state.feedHasMore = Boolean(data.has_more) || state.feedItems.length < state.totalFeed;
            state.feedError = '';
            render();
        } catch (err) {
            state.feedError = err.message || 'Ismeretlen hiba';
            showStatus('Hiba a feed betoltese kozben: ' + err.message, 'error');
            if (state.view === 'feed') {
                render();
            }
        } finally {
            state.feedLoadingMore = false;
        }
    }

    async function loadCircleDetail(id) {
        $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        try {
            await loadNgoAdminAccess();
            const data = await api(`/circles/${id}`);
            state.circle = data.circle;

            const postsData = await api(`/circles/${id}/posts?page=1`);
            state.posts = postsData.posts;
            state.totalPosts = postsData.total;
            state.postPage = 1;
            render();
        } catch (err) {
            showStatus('Hiba: ' + err.message, 'error');
        }
    }

    async function loadMorePosts(circleId) {
        state.postPage++;
        try {
            const data = await api(`/circles/${circleId}/posts?page=${state.postPage}`);
            state.posts = state.posts.concat(data.posts);
            render();
        } catch (err) {
            showStatus('Hiba: ' + err.message, 'error');
        }
    }

    /* --- Actions ---------------------------------------------------- */
    async function joinCircle(id) {
        try {
            const data = await api(`/circles/${id}/join`, {method: 'POST'});
            if (data.already_member) {
                showStatus('Már tagja vagy ennek a körnek!', 'info');
            } else {
                showStatus(`Csatlakoztál! Az álneved: ${data.alias}`, 'success');
            }
            loadCircleDetail(id);
        } catch (err) {
            showStatus(err.message, 'error');
        }
    }

    async function leaveCircle(id) {
        if (!confirm('Biztosan kilépsz ebből a körből?')) return;
        try {
            await api(`/circles/${id}/join`, {method: 'DELETE'});
            showStatus('Kiléptél a körből.', 'info');
            loadCircleDetail(id);
        } catch (err) {
            showStatus(err.message, 'error');
        }
    }

    async function createPost(circleId, opts = {}) {
        const ta = opts.bodyEl || document.getElementById('ic-post-body');
        if (!ta) {
            showStatus('A poszt mező nem található.', 'error');
            return;
        }
        const body = ta.value.trim();
        if (!body) {
            showStatus('Írd be a poszt szövegét!', 'error');
            return;
        }
        if (body.length > MAX_BODY) {
            showStatus(`Maximum ${MAX_BODY} karakter engedélyezett.`, 'error');
            return;
        }

        const btn = opts.buttonEl || document.getElementById('ic-post-send');
        const originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = '⏳ Küldés...';
        }

        try {
            await api(`/circles/${circleId}/posts`, {
                method: 'POST',
                body: JSON.stringify({
                    body: body,
                    as_ngo: !!opts.asNgo,
                }),
            });
            ta.value = '';
            showStatus('Poszt elküldve! 🎉', 'success');
            if (typeof opts.onSuccess === 'function') {
                await opts.onSuccess();
            } else {
                loadCircleDetail(circleId);
            }
        } catch (err) {
            showStatus(err.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText || '📝 Küldés';
            }
            return;
        }

        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText || '📝 Küldés';
        }
    }

    function insertEmoji(textarea, emoji) {
        const el = textarea;
        if (!el) return;
        const start = Number(el.selectionStart || 0);
        const end = Number(el.selectionEnd || 0);
        const value = el.value || '';
        const spacer = start > 0 && !/\s$/.test(value.slice(0, start)) ? ' ' : '';
        const nextValue = value.slice(0, start) + spacer + emoji + value.slice(end);
        el.value = nextValue;
        const caret = start + spacer.length + emoji.length;
        el.focus();
        el.setSelectionRange(caret, caret);
        el.dispatchEvent(new Event('input', {bubbles: true}));
    }

    async function votePost(post, btn, reactionsWrap = null) {
        const circleId = Number(post.circle_id) || 0;
        const postId = Number(post.id) || 0;
        if (!HAS_PSEUDO) {
            showStatus('A reakciohoz azonositas szukseges.', 'error');
            return;
        }
        if (post.is_own) {
            showStatus('A sajat posztodra nem tudsz reakciot kuldeni.', 'info');
            return;
        }
        if (state.votedPosts.has(String(postId))) {
            showStatus('Erre a posztra mar reagaltal.', 'info');
            return;
        }

        const previousText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⏳';
        }
        try {
            const data = await api(`/circles/${circleId}/posts/${postId}/vote`, {method: 'POST'});
            state.votedPosts.add(String(postId));
            saveVotedPosts();
            if (reactionsWrap) {
                reactionsWrap.querySelectorAll('.ic-reaction-icon-btn').forEach((reactionBtn) => {
                    reactionBtn.disabled = true;
                    reactionBtn.classList.remove('voted');
                });
            }
            btn.classList.add('voted');
            const countEl = (reactionsWrap || document).querySelector('.ic-reaction-count');
            if (countEl) {
                countEl.textContent = String(data.vote_count || 0);
            }
            btn.disabled = true;
            showStatus('Reakcio rogzitve. Koszonjuk! 💚', 'success');
            if (state.view === 'feed') {
                trackFeedEvent('feed_vote', {
                    post_id: Number(postId) || 0,
                    circle_id: Number(circleId) || 0,
                });
            }
        } catch (err) {
            showStatus(err.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = previousText;
            }
        }
    }

    async function reportPost(circleId, postId) {
        if (!HAS_PSEUDO) {
            showStatus('A jelentéshez azonosítás szükséges.', 'error');
            return;
        }

        const reasonRaw = window.prompt('Miért szeretnéd jelenteni ezt a posztot? (pl. sértő, félrevezető, spam)', 'sértő tartalom');
        if (reasonRaw === null) {
            return;
        }

        const reason = String(reasonRaw).trim();
        if (!reason) {
            showStatus('Kérlek, adj meg rövid indokot a jelentéshez.', 'error');
            return;
        }

        try {
            await api(`/circles/${circleId}/posts/${postId}/report`, {
                method: 'POST',
                body: JSON.stringify({reason}),
            });
            showStatus('Köszönjük, a jelentést rögzítettük. 🛡️', 'success');
        } catch (err) {
            const msg = (err && err.message) ? String(err.message) : 'Ismeretlen hiba';
            const missingRoute = /404|No route|nem található/i.test(msg);
            if (missingRoute) {
                const subject = encodeURIComponent(`Hatás Körök jelentés (kör: ${circleId}, poszt: ${postId})`);
                const body = encodeURIComponent(`Poszt azonosító: ${postId}\nKör azonosító: ${circleId}\nIndok: ${reason}\n\nKérem az ellenőrzést.`);
                window.location.href = `mailto:office@sharity.hu?subject=${subject}&body=${body}`;
                showStatus('A jelentés e-mail fallbackkel indult el.', 'info');
                return;
            }
            showStatus('A jelentés nem sikerült: ' + msg, 'error');
        }
    }

    async function deletePost(circleId, postId) {
        if (!confirm('Biztosan törlöd ezt a posztot?')) return;
        try {
            await api(`/circles/${circleId}/posts/${postId}`, {method: 'DELETE'});
            showStatus('Poszt törölve.', 'info');
            loadCircleDetail(circleId);
        } catch (err) {
            showStatus(err.message, 'error');
        }
    }

    /* --- Utils ------------------------------------------------------ */
    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    /* --- Router (hash-based) ---------------------------------------- */
    function handleHash() {
        const hash = window.location.hash || '#feed';
        if (hash === '#mine') {
            state.view = 'mine';
            loadMyCircles();
        } else if (hash.startsWith('#ngo-workspace/')) {
            const slug = normalizeNgoSlug(hash.split('/')[1] || '');
            state.view = 'ngo-workspace';
            state.ngoWorkspaceSlug = slug;
            loadNgoAdminAccess().then(renderNgoWorkspace);
        } else if (hash === '#ngo-admin' || hash.startsWith('#ngo-admin/')) {
            const focusedId = hash.startsWith('#ngo-admin/') ? parseInt(hash.split('/')[1], 10) : 0;
            state.view = 'ngo-admin';
            state.ngoAdminFocusCircleId = Number.isFinite(focusedId) && focusedId > 0 ? focusedId : null;
            loadNgoAdminAccess().then(renderNgoAdmin);
        } else if (hash === '#feed') {
            state.view = 'feed';
            trackFeedEvent('feed_opened', {source: 'hash'});
            loadFeedMine(true);
        } else if (hash.startsWith('#circle/')) {
            const id = parseInt(hash.split('/')[1], 10);
            if (id) {
                state.view = 'circle';
                navigateCircle(id);
            } else {
                navigate('circles');
            }
        } else {
            state.view = 'feed';
            trackFeedEvent('feed_opened', {source: 'default'});
            if (state.myCircles.length === 0) {
                primeMyCirclesForFeed();
            }
            loadFeedMine(true);
        }
    }

    /* --- Nav click handlers ----------------------------------------- */
    document.querySelectorAll('.ic-nav-btn').forEach(btn => {
        if (!btn.dataset.nav) return;
        btn.addEventListener('click', () => navigate(btn.dataset.nav));
    });

    function sanitizeColorToken(token) {
        const allowed = ['lagoon', 'mint', 'cobalt', 'amber', 'coral', 'slate', 'moss', 'rose', 'indigo', 'ember'];
        return allowed.includes(token) ? token : 'slate';
    }

    function circleTokenColor(token) {
        const palette = {
            lagoon: '#0ea5a3',
            mint: '#10b981',
            cobalt: '#2563eb',
            amber: '#f59e0b',
            coral: '#f43f5e',
            slate: '#64748b',
            moss: '#4d7c0f',
            rose: '#e11d48',
            indigo: '#4f46e5',
            ember: '#ea580c',
        };
        const safe = sanitizeColorToken(token);
        return palette[safe] || palette.slate;
    }

    function circleTypeLabel(type) {
        return type === 'settlement' ? 'Telepules' : 'NGO';
    }

    /* --- Init ------------------------------------------------------- */
    window.addEventListener('hashchange', handleHash);
    refreshAuthState().finally(handleHash);

    /* --- Public API for shortcode ----------------------------------- */
    window.ImpactCommunity = { init: handleHash };

})();
</script>
</body>
</html>
