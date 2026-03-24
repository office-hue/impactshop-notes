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
    padding: 7px 14px;
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--radius-sm);
    color: var(--muted);
    transition: all var(--transition);
}

.ic-nav-btn:hover {
    background: var(--teal-bg);
    color: var(--teal);
}

.ic-nav-btn.active {
    background: var(--teal);
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
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    margin-bottom: 16px;
}

.ic-composer-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 8px;
}

.ic-composer textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;
    resize: vertical;
    outline: none;
    transition: border-color var(--transition);
}

.ic-composer textarea:focus { border-color: var(--teal); }

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

.ic-post {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    margin-bottom: 12px;
    transition: box-shadow var(--transition);
}

.ic-post:hover {
    box-shadow: var(--shadow-sm);
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

.ic-post-author.impi { color: var(--sun); }

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

.ic-reactions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.ic-reaction-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--border);
    color: var(--muted);
    background: var(--surface);
    transition: all var(--transition);
    cursor: pointer;
}

.ic-reaction-btn:hover:not(:disabled) {
    border-color: var(--teal);
    color: var(--teal);
    background: var(--teal-bg);
}

.ic-reaction-btn.reacted {
    background: var(--teal-bg);
    border-color: var(--teal);
    color: var(--teal-dark);
    font-weight: 600;
}

.ic-reaction-btn:disabled { opacity: .5; cursor: not-allowed; }

.ic-post-delete {
    font-size: 12px;
    color: var(--muted);
    margin-left: auto;
}

.ic-post-delete:hover { color: var(--coral); }

/* Post Intent Selector */
.ic-intent-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 6px;
}
.ic-intent-pills {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.ic-intent-pill {
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 20px;
    border: 1px solid var(--border);
    color: var(--muted);
    background: var(--surface);
    cursor: pointer;
    transition: all var(--transition);
}
.ic-intent-pill:hover { border-color: var(--teal); color: var(--teal); }
.ic-intent-pill.selected { background: var(--teal); color: #fff; border-color: var(--teal); }

/* Leaderboard Panel */
.ic-leaderboard {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-top: 24px;
}
.ic-leaderboard-title {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ic-lb-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.ic-lb-row:last-child { border-bottom: none; }
.ic-lb-rank { font-weight: 700; color: var(--muted); width: 28px; text-align: center; flex-shrink: 0; }
.ic-lb-rank.top1 { color: #F59E0B; }
.ic-lb-rank.top2 { color: #9CA3AF; }
.ic-lb-rank.top3 { color: #B45309; }
.ic-lb-alias { flex: 1; color: var(--teal-dark); font-weight: 500; }
.ic-lb-score { color: var(--muted); font-size: 13px; }

/* Impi Bot Post */
.ic-post.impi-post {
    border-left: 3px solid var(--sun);
    background: #FFFBEB;
}
.ic-impi-avatar {
    display: inline-block;
    background: #FEF3C7;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    text-align: center;
    line-height: 28px;
    font-size: 16px;
    margin-right: 6px;
    flex-shrink: 0;
}
.ic-post-author.impi {
    color: #B45309;
    display: flex;
    align-items: center;
}

/* Impi Boost — heti kiemelt poszt */
.ic-post.impi-boost-post {
    border: 2px solid #F59E0B;
    background: linear-gradient(135deg, #FFFBEB 0%, #fff 100%);
    box-shadow: 0 2px 10px rgba(245,158,11,.15);
}
.ic-impi-pick-label {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    color: #D97706;
    background: #FEF3C7;
    border-radius: 4px;
    padding: 2px 7px;
    margin-left: 8px;
    white-space: nowrap;
}

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
   Invite landing overlay
   ================================================================ */
.ic-invite-landing {
    max-width: 440px;
    margin: 48px auto;
    text-align: center;
    padding: 32px 24px;
    background: var(--card-bg, #fff);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
}
.ic-invite-icon   { font-size: 48px; margin-bottom: 12px; }
.ic-invite-title  { font-size: 20px; font-weight: 700; margin: 0 0 6px; color: var(--text); }
.ic-invite-circle { font-size: 16px; font-weight: 600; color: var(--teal); margin: 0 0 8px; }
.ic-invite-meta   { font-size: 13px; color: var(--muted); margin: 0 0 16px; }
.ic-invite-desc   { font-size: 14px; color: var(--text); margin: 0 0 24px; }
.ic-invite-join-btn { width: 100%; font-size: 16px; padding: 14px; }

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
}

/* === Tombola & Aukció === */
.ic-tombola-section, .ic-auction-section {
    margin: 18px 0;
}
.ic-tombola-section h3, .ic-auction-section h3 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ic-campaign-card {
    background: #fff;
    border: 1.5px solid var(--border);
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.ic-campaign-card.drawn, .ic-campaign-card.closed {
    opacity: .75;
}
.ic-campaign-title {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 4px;
    color: var(--text);
}
.ic-campaign-desc {
    font-size: 13px;
    color: var(--muted);
    margin: 0 0 10px;
    line-height: 1.5;
}
.ic-campaign-meta {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 12px;
}
.ic-campaign-meta strong {
    color: var(--text);
}
.ic-ticket-row, .ic-bid-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ic-ticket-input, .ic-bid-input {
    width: 70px;
    padding: 6px 10px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    text-align: center;
}
.ic-campaign-winner {
    background: linear-gradient(135deg, #fffbea 0%, #fff3c4 100%);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--text);
    margin-top: 8px;
}
.ic-campaign-leader {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 8px;
}
.ic-campaign-leader strong {
    color: var(--teal);
}
.ic-bid-history {
    margin-top: 10px;
    font-size: 12px;
    color: var(--muted);
}
.ic-bid-history-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
    border-bottom: 1px solid var(--border);
}
/* §10 Sprint panel */
.ic-sprint-panel { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 16px 0; }
.ic-sprint-title { font-weight: 700; font-size: 16px; color: #15803d; margin-bottom: 6px; }
.ic-sprint-meta  { font-size: 13px; color: #166534; }
.ic-sprint-lb    { margin-top: 12px; }
.ic-sprint-lb-title { font-weight: 600; font-size: 14px; margin-bottom: 6px; }
/* §12 Settlement / health */
.ic-settlement-panel { background: #fefce8; border: 1px solid #fde68a; border-radius: 12px; padding: 16px; margin: 16px 0; }
.ic-settlement-title { font-weight: 700; font-size: 16px; color: #92400e; margin-bottom: 8px; }
.ic-health-label { font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 4px; }
.ic-health-bar-wrap { background: #e5e7eb; border-radius: 999px; height: 10px; overflow: hidden; }
.ic-health-bar-fill { height: 100%; border-radius: 999px; transition: width 0.4s; }
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
            <button class="ic-nav-btn active" data-nav="circles">Körök</button>
            <button class="ic-nav-btn" data-nav="mine">Saját köreim</button>
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
    let NONCE = <?php echo wp_json_encode($nonce); ?>;
    const HAS_PSEUDO = <?php echo $pseudo ? 'true' : 'false'; ?>;
    const MAX_BODY = <?php echo IC_MAX_BODY_LENGTH; ?>;

    const $content = document.getElementById('ic-content');
    const $status  = document.getElementById('ic-status');

    /* --- State ------------------------------------------------------ */
    let state = {
        view: 'circles',        // 'circles' | 'mine' | 'circle'
        circleId: null,
        circles: [],
        myCircles: [],
        posts: [],
        circle: null,
        filter: '',             // '' | 'ngo' | 'settlement'
        search: '',
        page: 1,
        totalCircles: 0,
        perPage: 30,
        postPage: 1,
        totalPosts: 0,
        votedPosts: new Set(JSON.parse(localStorage.getItem('ic_voted') || '[]')), // legacy compat
        myReactions: JSON.parse(localStorage.getItem('ic_reactions') || '{}'),
    };

    /* --- API helper ------------------------------------------------- */
    async function api(path, opts = {}) {
        const url = API.replace(/\/$/, '') + path;
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE,
        };
        try {
            const resp = await fetch(url, {
                credentials: 'same-origin',
                headers,
                ...opts,
            });

            // Nonce may expire — refresh and retry once on 403
            if (resp.status === 403 && !opts._retried) {
                const authResp = await fetch(API.replace(/\/$/, '') + '/auth/status', {
                    credentials: 'same-origin',
                });
                if (authResp.ok) {
                    const auth = await authResp.json();
                    if (auth.nonce) {
                        NONCE = auth.nonce;
                        headers['X-WP-Nonce'] = NONCE;
                        return api(path, { ...opts, _retried: true });
                    }
                }
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

    /* --- Render engine ---------------------------------------------- */
    function render() {
        switch (state.view) {
            case 'circles': renderCircles(state.circles, false); break;
            case 'mine':    renderCircles(state.myCircles, true); break;
            case 'circle':  renderCircleDetail(); break;
        }
        updateNav();
    }

    function updateNav() {
        document.querySelectorAll('.ic-nav-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.nav === state.view);
        });
    }

    function html(tag, attrs = {}, ...children) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'className') el.className = v;
            else if (k === 'innerHTML') el.innerHTML = v;
            else if (k.startsWith('on')) el.addEventListener(k.slice(2).toLowerCase(), v);
            else el.setAttribute(k, v);
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

        const header = html('div', {});
        header.appendChild(html('h1', {className: 'ic-section-title'},
            isMine ? 'Saját köreim' : 'Hatás Körök'));
        header.appendChild(html('p', {className: 'ic-section-sub'},
            isMine
                ? 'A körök ahol aktívan jelen vagy'
                : 'Csatlakozz NGO vagy települési közösségekhez, posztolj és szavazz'));
        $content.appendChild(header);

        if (!isMine) {
            const bar = html('div', {className: 'ic-filter-bar'});
            ['', 'ngo', 'settlement'].forEach(f => {
                const label = f === '' ? 'Mind' : f === 'ngo' ? '🏢 NGO' : '🏘️ Település';
                const btn = html('button', {
                    className: 'ic-filter-btn' + (state.filter === f ? ' active' : ''),
                    onClick: () => { state.filter = f; state.page = 1; loadCircles(); },
                }, label);
                bar.appendChild(btn);
            });
            const search = html('input', {
                className: 'ic-search',
                type: 'text',
                placeholder: 'Keresés...',
                value: state.search,
            });
            search.addEventListener('input', debounce(e => {
                state.search = e.target.value;
                filterAndRender();
            }, 250));
            bar.appendChild(search);
            $content.appendChild(bar);
        }

        const filtered = state.search
            ? circles.filter(c => c.name.toLowerCase().includes(state.search.toLowerCase()))
            : circles;

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

            grid.appendChild(card);
        });
        $content.appendChild(grid);

        // Pagination
        if (!isMine && state.totalCircles > state.perPage) {
            const pages = Math.ceil(state.totalCircles / state.perPage);
            const pag = html('div', {className: 'ic-pagination'});
            for (let i = 1; i <= Math.min(pages, 10); i++) {
                pag.appendChild(html('button', {
                    className: 'ic-btn ' + (state.page === i ? 'ic-btn-primary' : 'ic-btn-outline'),
                    onClick: () => { state.page = i; loadCircles(); },
                }, String(i)));
            }
            $content.appendChild(pag);
        }
    }

    function filterAndRender() {
        const wasFocused = document.activeElement &&
            document.activeElement.classList.contains('ic-search');
        const list = state.view === 'mine' ? state.myCircles : state.circles;
        renderCircles(list, state.view === 'mine');
        if (wasFocused) {
            const inp = $content.querySelector('.ic-search');
            if (inp) { inp.focus(); const l = inp.value.length; inp.setSelectionRange(l, l); }
        }
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
        const hdr = html('div', {className: 'ic-circle-header'});

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

            const inviteBtn = html('button', {
                className: 'ic-btn ic-btn-outline',
                onClick: () => shareInvite(c.id),
            }, '📨 Meghívó');
            actions.appendChild(inviteBtn);
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

            // Intent selector pills
            const intentLabel = html('div', {className: 'ic-intent-label'}, 'Mit szeretnél megosztani?');
            const intentPills = html('div', {className: 'ic-intent-pills'});
            const intents = [
                {value: 'help',  label: '🙋 Segítséget kérek'},
                {value: 'info',  label: '📢 Megosztok valamit'},
                {value: 'proof', label: '✅ Hatást igazolok'},
                {value: 'ask',   label: '❓ Kérdést teszek fel'},
            ];
            intents.forEach(({value, label}) => {
                const pill = html('button', {className: 'ic-intent-pill'});
                pill.dataset.intent = value;
                pill.textContent = label;
                pill.addEventListener('click', () => {
                    intentPills.querySelectorAll('.ic-intent-pill').forEach(p => p.classList.remove('selected'));
                    pill.classList.add('selected');
                });
                intentPills.appendChild(pill);
            });
            composer.appendChild(intentLabel);
            composer.appendChild(intentPills);

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
                onClick: () => createPost(c.id),
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

        // Tombola & Auction panels (before leaderboard)
        const tombolaContainer = html('div', {id: 'ic-tombola-panel'});
        $content.appendChild(tombolaContainer);
        loadTombolas(c.id);

        const auctionContainer = html('div', {id: 'ic-auction-panel'});
        $content.appendChild(auctionContainer);
        loadAuctions(c.id);

        // §10 Sprint panel (NGO circles only)
        if (c.type === 'ngo') {
            const sprintContainer = html('div', {id: 'ic-sprint-panel'});
            $content.appendChild(sprintContainer);
            loadSprintPanel(c.ref_slug);
        }

        // §12 Settlement rivalry panel
        if (c.type === 'settlement') {
            const rivalContainer = html('div', {id: 'ic-settlement-panel'});
            $content.appendChild(rivalContainer);
            loadSettlementPanel(c.id);
        }

        // Leaderboard panel
        const lbContainer = html('div', {id: 'ic-leaderboard-panel'});
        $content.appendChild(lbContainer);
        loadLeaderboard(c.id);
    }

    async function loadLeaderboard(circleId) {
        const panel = document.getElementById('ic-leaderboard-panel');
        if (!panel) return;
        try {
            const data = await api(`/circles/${circleId}/leaderboard`);
            if (!data.leaderboard || data.leaderboard.length === 0) return;
            panel.innerHTML = '';
            const lb = html('div', {className: 'ic-leaderboard'});
            lb.appendChild(html('div', {className: 'ic-leaderboard-title'}, '🏆 Körünk legjobb tagjai'));
            const rankEmojis = ['🥇', '🥈', '🥉'];
            data.leaderboard.forEach(entry => {
                const row = html('div', {className: 'ic-lb-row'});
                const rankClass = entry.rank <= 3 ? `top${entry.rank}` : '';
                const rankLabel = entry.rank <= 3 ? rankEmojis[entry.rank - 1] : String(entry.rank);
                row.appendChild(html('span', {className: `ic-lb-rank ${rankClass}`}, rankLabel));
                row.appendChild(html('span', {className: 'ic-lb-alias'}, entry.alias));
                if (entry.badge_count > 0) {
                    row.appendChild(html('span', {}, '🏅'.repeat(Math.min(entry.badge_count, 3))));
                }
                row.appendChild(html('span', {className: 'ic-lb-score'}, `${entry.score} pont`));
                lb.appendChild(row);
            });
            panel.appendChild(lb);
        } catch (_) {
            // Leaderboard is optional — fail silently
        }
    }

    async function loadSprintPanel(ngoSlug) {
        const panel = document.getElementById('ic-sprint-panel');
        if (!panel) return;
        try {
            const data = await api(`/sprints/current?ngo_slug=${encodeURIComponent(ngoSlug)}`);
            panel.innerHTML = '';
            if (!data.active) return;

            const box = html('div', {className: 'ic-sprint-panel'});
            box.appendChild(html('div', {className: 'ic-sprint-title'}, '🚀 NGO Sprint'));
            const daysLeft = data.days_left ?? 0;
            box.appendChild(html('div', {className: 'ic-sprint-meta'},
                `${daysLeft} nap maradt · Körünk: ${data.ngo_credits ?? 0} kredit (${data.ngo_rank ? data.ngo_rank + '. helyezés' : 'nincs még élet'})`
            ));

            // Leaderboard button
            const lbBtn = html('button', {
                className: 'ic-btn ic-btn-outline',
                style: 'margin-top:8px;font-size:13px',
                onClick: () => loadSprintLeaderboard(box),
            }, '📊 Rangsor megtekintése');
            box.appendChild(lbBtn);
            panel.appendChild(box);
        } catch (_) { /* optional */ }
    }

    async function loadSprintLeaderboard(container) {
        try {
            const data = await api('/sprints/current/leaderboard');
            if (!data.active || !data.leaderboard?.length) return;
            const existing = container.querySelector('.ic-sprint-lb');
            if (existing) { existing.remove(); return; } // toggle off
            const lb = html('div', {className: 'ic-sprint-lb'});
            lb.appendChild(html('div', {className: 'ic-sprint-lb-title'}, '🏅 Sprint top 10'));
            const medals = ['🥇','🥈','🥉'];
            data.leaderboard.forEach((row, i) => {
                const r = html('div', {className: 'ic-lb-row'});
                r.appendChild(html('span', {className: 'ic-lb-rank'}, medals[i] || String(i + 1)));
                r.appendChild(html('span', {className: 'ic-lb-alias'}, row.ngo_name));
                r.appendChild(html('span', {className: 'ic-lb-score'}, `${row.credits} kredit`));
                lb.appendChild(r);
            });
            container.appendChild(lb);
        } catch (_) { /* optional */ }
    }

    async function loadSettlementPanel(circleId) {
        const panel = document.getElementById('ic-settlement-panel');
        if (!panel) return;
        try {
            const data = await api(`/circles/${circleId}/health`);
            if (data.error) return;
            const box = html('div', {className: 'ic-settlement-panel'});
            box.appendChild(html('div', {className: 'ic-settlement-title'}, '⚔️ Körünk havi hangulatjelző'));
            const bar = html('div', {className: 'ic-health-bar-wrap'});
            const fill = html('div', {className: 'ic-health-bar-fill'});
            const score = Math.max(0, Math.min(100, data.health_score ?? 0));
            fill.style.width = `${score}%`;
            fill.style.background = score >= 70 ? '#22c55e' : score >= 40 ? '#f59e0b' : '#ef4444';
            bar.appendChild(fill);
            box.appendChild(html('div', {className: 'ic-health-label'},
                `Körégészség: ${score}/100`
            ));
            box.appendChild(bar);
            if (data.community_bonus && data.community_bonus > 1.0) {
                box.appendChild(html('div', {
                    className: 'ic-card-badge settlement',
                    style: 'margin-top:8px',
                }, `🏆 +${Math.round((data.community_bonus - 1) * 100)}% pontbónusz aktív!`));
            }
            panel.appendChild(box);
        } catch (_) { /* optional */ }
    }

    function renderPost(p) {
        const isImpi  = p.author_type === 'impi';
        const isBoost = !isImpi && !!p.impi_boost;
        const post = html('div', {className:
            'ic-post' +
            (p.is_pinned  ? ' pinned'          : '') +
            (isImpi       ? ' impi-post'        : '') +
            (isBoost      ? ' impi-boost-post'  : '')
        });

        const head = html('div', {className: 'ic-post-head'});
        if (isImpi) {
            const authorEl = html('span', {className: 'ic-post-author impi'});
            authorEl.innerHTML = `<span class="ic-impi-avatar">🦡</span>${p.author_alias}`;
            head.appendChild(authorEl);
        } else {
            const authorPrefix = p.author_type === 'ngo' ? '🏢 ' : '';
            head.appendChild(html('span', {className: 'ic-post-author'}, authorPrefix + p.author_alias));
            if (isBoost) {
                head.appendChild(html('span', {className: 'ic-impi-pick-label'}, '🦡 Heti Impi-pick!'));
            }
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

        const myReaction = state.myReactions[p.id] || p.my_reaction || null;

        if (!p.is_own && !isImpi) {
            const reactionDefs = [
                {type: 'thanks',  emoji: '🙏', label: 'Köszi'},
                {type: 'useful',  emoji: '💡', label: 'Hasznos'},
                {type: 'support', emoji: '🤝', label: 'Támogatlak'},
                {type: 'done',    emoji: '✅', label: 'Megcsináltam'},
            ];
            const reactionsWrap = html('div', {className: 'ic-reactions'});
            reactionDefs.forEach(({type, emoji, label}) => {
                const count = (p.reactions && p.reactions[type]) || 0;
                const isReacted = myReaction === type;
                const alreadyReacted = myReaction !== null;
                const btn = html('button', {
                    className: 'ic-reaction-btn' + (isReacted ? ' reacted' : ''),
                    disabled: !HAS_PSEUDO || alreadyReacted,
                    title: label,
                });
                btn.innerHTML = `${emoji} <span class="ic-reaction-count-${type}">${count > 0 ? count : ''}</span>`;
                btn.addEventListener('click', () => reactPost(p.circle_id, p.id, type, btn));
                reactionsWrap.appendChild(btn);
            });
            footer.appendChild(reactionsWrap);
        } else if (p.vote_count > 0) {
            const totalEl = html('span', {style: 'font-size:13px;color:var(--muted)'});
            totalEl.textContent = `${p.vote_count} reakció`;
            footer.appendChild(totalEl);
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

    async function reactPost(circleId, postId, reactionType, btn) {
        if (state.myReactions[postId]) return;
        try {
            const data = await api(`/circles/${circleId}/posts/${postId}/react`, {
                method: 'POST',
                body: JSON.stringify({reaction_type: reactionType}),
            });
            state.myReactions[postId] = reactionType;
            localStorage.setItem('ic_reactions', JSON.stringify(state.myReactions));

            // Update UI: disable all reaction buttons for this post, mark the pressed one
            const postEl = btn.closest('.ic-post');
            if (postEl) {
                postEl.querySelectorAll('.ic-reaction-btn').forEach(b => {
                    b.disabled = true;
                    if (b === btn) {
                        b.classList.add('reacted');
                        const countEl = b.querySelector(`.ic-reaction-count-${reactionType}`);
                        if (countEl && data.reactions) {
                            countEl.textContent = data.reactions[reactionType] || '';
                        }
                    }
                });
            }
        } catch (err) {
            showStatus(err.message, 'error');
        }
    }

    /* --- Navigation ------------------------------------------------- */
    function navigate(view) {
        state.view = view;
        state.circleId = null;
        state.circle = null;
        state.posts = [];
        state.search = '';
        window.location.hash = view === 'mine' ? '#mine' : '#circles';
        if (view === 'circles') loadCircles();
        else if (view === 'mine') loadMyCircles();
    }

    function navigateCircle(id) {
        state.view = 'circle';
        state.circleId = id;
        state.postPage = 1;
        window.location.hash = '#circle/' + id;
        loadCircleDetail(id);
    }

    /* --- API Loaders ------------------------------------------------ */
    async function loadCircles() {
        $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        try {
            let path = `/circles?page=${state.page}&per_page=300`;
            if (state.filter) path += `&type=${state.filter}`;
            if (state.search) path += `&search=${encodeURIComponent(state.search)}`;
            const data = await api(path);
            state.circles = data.circles;
            state.totalCircles = data.total;
            state.perPage = data.per_page;
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
            render();
        } catch (err) {
            showStatus('Hiba: ' + err.message, 'error');
        }
    }

    async function loadCircleDetail(id) {
        $content.innerHTML = '<div class="ic-loading"><div class="ic-spinner"></div></div>';
        try {
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
    async function shareInvite(circleId) {
        try {
            const data = await api(`/circles/${circleId}/invite`, {method: 'POST'});
            const url = data.share_url || '';
            if (!url) {
                showStatus('Nem sikerült meghívót létrehozni.', 'error');
                return;
            }
            if (navigator.share) {
                await navigator.share({ title: 'Hatás Körök meghívó', url });
            } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(url);
                showStatus('Meghívó link másolva! 📋', 'success');
            } else {
                prompt('Másold ki a meghívó linket:', url);
            }
        } catch (err) {
            showStatus(err.message || 'Hiba a meghívó létrehozásakor.', 'error');
        }
    }

    async function openInviteLanding(refCode) {
        try {
            const data = await api(`/invite/${refCode}`);
            const circle = data.circle || {};
            const inviter = data.inviter_alias || 'egy tag';

            // Render invite landing overlay
            $content.innerHTML = '';
            const wrap = html('div', {className: 'ic-invite-landing'});
            wrap.appendChild(html('div', {className: 'ic-invite-icon'}, '🌱'));
            wrap.appendChild(html('h1', {className: 'ic-invite-title'}, `${inviter} meghívott a körbe`));
            wrap.appendChild(html('h2', {className: 'ic-invite-circle'}, circle.name || ''));
            wrap.appendChild(html('p', {className: 'ic-invite-meta'}, `${circle.member_count || 0} tag · ${circle.type === 'ngo' ? 'NGO kör' : 'Települési kör'}`));
            wrap.appendChild(html('p', {className: 'ic-invite-desc'},
                'Csatlakozz ehhez a közösséghez, és +30 pontot kapsz induláshoz!'));

            if (!HAS_PSEUDO) {
                wrap.appendChild(html('p', {className: 'ic-auth-prompt'},
                    'Böngéssz még egy kicsit az oldalon, hogy aktívan részt vehess a körben! 🌱'));
            } else {
                const joinBtn = html('button', {
                    className: 'ic-btn ic-btn-primary ic-invite-join-btn',
                    onClick: async () => {
                        try {
                            const res = await api(`/circles/${circle.id}/join`, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ref_code: refCode}),
                            });
                            showStatus(`Csatlakoztál! Az álneved: ${res.alias || ''}`, 'success');
                            navigate('circle');
                            navigateCircle(circle.id);
                        } catch (err) {
                            showStatus(err.message, 'error');
                        }
                    },
                }, '✋ Csatlakozom a körhöz');
                wrap.appendChild(joinBtn);
            }

            const backLink = html('a', {
                className: 'ic-back',
                href: '#circles',
                onClick: e => { e.preventDefault(); navigate('circles'); },
            }, '← Vagy nézd meg az összes kört');
            wrap.appendChild(backLink);
            $content.appendChild(wrap);
        } catch (err) {
            showStatus('Érvénytelen meghívó link.', 'error');
            navigate('circles');
        }
    }

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

    async function createPost(circleId) {
        const ta = document.getElementById('ic-post-body');
        const body = ta.value.trim();
        if (!body) {
            showStatus('Írd be a poszt szövegét!', 'error');
            return;
        }
        if (body.length > MAX_BODY) {
            showStatus(`Maximum ${MAX_BODY} karakter engedélyezett.`, 'error');
            return;
        }

        const btn = document.getElementById('ic-post-send');
        btn.disabled = true;
        btn.textContent = '⏳ Küldés...';

        // Collect selected intent from pills
        const selectedPill = document.querySelector('.ic-intent-pill.selected');
        const intent = selectedPill ? selectedPill.dataset.intent : null;

        try {
            await api(`/circles/${circleId}/posts`, {
                method: 'POST',
                body: JSON.stringify({
                    body: body,
                    meta: intent ? {intent: intent} : null,
                }),
            });
            ta.value = '';
            // Reset intent pills
            document.querySelectorAll('.ic-intent-pill').forEach(p => p.classList.remove('selected'));
            showStatus('Poszt elküldve! 🎉', 'success');
            loadCircleDetail(circleId);
        } catch (err) {
            showStatus(err.message, 'error');
            btn.disabled = false;
            btn.textContent = '📝 Küldés';
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
        const hash = window.location.hash || '#circles';
        if (hash === '#mine') {
            state.view = 'mine';
            loadMyCircles();
        } else if (hash.startsWith('#circle/')) {
            const id = parseInt(hash.split('/')[1], 10);
            if (id) {
                state.view = 'circle';
                navigateCircle(id);
            } else {
                navigate('circles');
            }
        } else {
            state.view = 'circles';
            loadCircles();
        }
    }

    /* --- Nav click handlers ----------------------------------------- */
    document.querySelectorAll('.ic-nav-btn').forEach(btn => {
        btn.addEventListener('click', () => navigate(btn.dataset.nav));
    });

    /* --- Init ------------------------------------------------------- */
    window.addEventListener('hashchange', handleHash);
    handleHash();

    /* --- Public API for shortcode ----------------------------------- */
    window.ImpactCommunity = { init: handleHash, openInviteLanding };

    /* ================================================================
       §9 Tombola & Aukció — panel loaders & actions
       ================================================================ */

    async function loadTombolas(circleId) {
        const panel = document.getElementById('ic-tombola-panel');
        if (!panel) return;
        try {
            const data = await api(`/circles/${circleId}/tombolas`);
            const tombolas = data.tombolas || [];
            if (!tombolas.length) return;
            panel.innerHTML = '';
            const section = html('div', {className: 'ic-tombola-section'});
            section.appendChild(html('h3', {}, '🎟️ Tombola'));
            tombolas.forEach(t => section.appendChild(renderTombolaCard(t)));
            panel.appendChild(section);
        } catch (_) { /* optional panel */ }
    }

    function renderTombolaCard(t) {
        const card = html('div', {className: 'ic-campaign-card ' + t.status});
        card.appendChild(html('div', {className: 'ic-campaign-title'}, t.title));
        if (t.description) {
            card.appendChild(html('div', {className: 'ic-campaign-desc'}, t.description));
        }
        const prizeName = (t.prize_json && t.prize_json.name) ? t.prize_json.name : '';
        const meta = html('div', {className: 'ic-campaign-meta'});
        if (prizeName) meta.appendChild(html('span', {innerHTML: `🏆 Díj: <strong>${esc(prizeName)}</strong>`}));
        meta.appendChild(html('span', {innerHTML: `🎫 Eladt: <strong>${t.tickets_sold}</strong>${t.max_tickets ? ' / ' + t.max_tickets : ''}`}));
        if (t.status === 'active') {
            const secsLeft = t.ends_at_ts - Math.floor(Date.now() / 1000);
            meta.appendChild(html('span', {innerHTML: `⏰ Hátralévő: <strong>${formatCountdown(secsLeft)}</strong>`}));
        }
        card.appendChild(meta);

        if (t.status === 'drawn') {
            const win = html('div', {className: 'ic-campaign-winner'});
            win.innerHTML = `🎉 Nyertes: <strong>${esc(t.winner_alias || 'Nemér')}</strong>`;
            card.appendChild(win);
        } else if (t.status === 'active' && HAS_PSEUDO) {
            const row = html('div', {className: 'ic-ticket-row'});
            const countInput = html('input', {
                type: 'number', min: '1', max: String(t.max_per_user - t.my_tickets),
                value: '1', className: 'ic-ticket-input',
            });
            const cost = (int) => t.ticket_cost > 0 ? ` (${int * t.ticket_cost} pont)` : ' (ingyenes)';
            const btn = html('button', {
                className: 'ic-btn ic-btn-primary',
                style: 'font-size:13px',
            }, 'Jegy vásárlása');
            btn.textContent = 'Jegy vásárlása' + cost(1);
            countInput.addEventListener('input', () => {
                btn.textContent = 'Jegy vásárlása' + cost(parseInt(countInput.value) || 1);
            });
            btn.addEventListener('click', async () => {
                await buyTicket(t.id, parseInt(countInput.value) || 1, btn);
            });
            if (t.my_tickets > 0) {
                row.appendChild(html('span', {style: 'font-size:13px;color:var(--teal)'}, `✓ ${t.my_tickets} jegyem van`));
            }
            if (t.my_tickets < t.max_per_user) {
                row.appendChild(countInput);
                row.appendChild(btn);
            }
            card.appendChild(row);
        }
        return card;
    }

    async function buyTicket(tombolaId, count, btn) {
        btn.disabled = true;
        try {
            const data = await api(`/tombolas/${tombolaId}/buy`, {
                method: 'POST',
                body: JSON.stringify({count}),
            });
            showStatus(`🎫 ${data.my_tickets} jegy megvásárolva! 🍀`, 'success');
            // Reload tombola panel
            const c = state.circle;
            if (c) loadTombolas(c.id);
        } catch (err) {
            showStatus(err.message, 'error');
            btn.disabled = false;
        }
    }

    async function loadAuctions(circleId) {
        const panel = document.getElementById('ic-auction-panel');
        if (!panel) return;
        try {
            const data = await api(`/circles/${circleId}/auctions`);
            const auctions = data.auctions || [];
            if (!auctions.length) return;
            panel.innerHTML = '';
            const section = html('div', {className: 'ic-auction-section'});
            section.appendChild(html('h3', {}, '🔨 Aukció'));
            auctions.forEach(a => section.appendChild(renderAuctionCard(a)));
            panel.appendChild(section);
        } catch (_) { /* optional */ }
    }

    function renderAuctionCard(a) {
        const card = html('div', {className: 'ic-campaign-card ' + a.status});
        card.appendChild(html('div', {className: 'ic-campaign-title'}, a.title));
        if (a.description) {
            card.appendChild(html('div', {className: 'ic-campaign-desc'}, a.description));
        }
        const meta = html('div', {className: 'ic-campaign-meta'});
        meta.appendChild(html('span', {innerHTML: `💰 Induló licit: <strong>${a.starting_bid} pont</strong>`}));
        meta.appendChild(html('span', {innerHTML: `📊 Liciték száma: <strong>${a.bid_count}</strong>`}));
        if (a.status === 'active') {
            const effectiveEnd = a.extended_to_ts || a.ends_at_ts;
            const secsLeft = effectiveEnd - Math.floor(Date.now() / 1000);
            meta.appendChild(html('span', {innerHTML: `⏰ Hátralévő: <strong>${formatCountdown(secsLeft)}</strong>`}));
        }
        card.appendChild(meta);

        if (a.current_bid > 0 && a.leader_alias) {
            const leader = html('div', {className: 'ic-campaign-leader'});
            if (a.is_my_bid) {
                leader.innerHTML = `🏆 Te vezeted az aukciót: <strong>${a.current_bid} pont</strong>`;
            } else {
                leader.innerHTML = `⬆️ Jelenlegi vezető: <strong>${esc(a.leader_alias)}</strong> (${a.current_bid} pont)`;
            }
            card.appendChild(leader);
        }

        if (a.status === 'closed') {
            const win = html('div', {className: 'ic-campaign-winner'});
            win.innerHTML = `🎉 Nyertes: <strong>${esc(a.winner_alias || '?')}</strong> — ${a.current_bid} pont`;
            card.appendChild(win);
        } else if (a.status === 'active' && HAS_PSEUDO && !a.is_my_bid) {
            const minBid = Math.max(a.starting_bid, a.current_bid + 10);
            const row = html('div', {className: 'ic-bid-row'});
            const bidInput = html('input', {
                type: 'number', min: String(minBid), step: '10',
                value: String(minBid), className: 'ic-bid-input',
                placeholder: `min ${minBid}`,
            });
            const bidBtn = html('button', {
                className: 'ic-btn ic-btn-primary',
                style: 'font-size:13px',
            }, `Licit leadása`);
            bidBtn.addEventListener('click', async () => {
                await placeBid(a.id, parseInt(bidInput.value) || minBid, bidBtn);
            });
            row.appendChild(bidInput);
            row.appendChild(bidBtn);
            card.appendChild(row);
        }

        // Bid history toggle
        if (a.bid_count > 0) {
            const histBtn = html('button', {
                className: 'ic-btn ic-btn-outline',
                style: 'font-size:12px;margin-top:8px',
            }, 'Licit történet');
            const histDiv = html('div', {className: 'ic-bid-history', style: 'display:none'});
            histBtn.addEventListener('click', async () => {
                if (histDiv.style.display === 'none') {
                    histDiv.style.display = 'block';
                    histDiv.innerHTML = '…';
                    try {
                        const hd = await api(`/auctions/${a.id}/bids`);
                        histDiv.innerHTML = '';
                        (hd.bids || []).slice(0, 5).forEach(b => {
                            const row2 = html('div', {className: 'ic-bid-history-row'});
                            row2.appendChild(html('span', {}, esc(b.alias)));
                            row2.appendChild(html('span', {style: 'font-weight:700'}, b.bid_amount + ' pont'));
                            histDiv.appendChild(row2);
                        });
                    } catch (_) { histDiv.textContent = 'Nem érhető el.'; }
                } else {
                    histDiv.style.display = 'none';
                }
            });
            card.appendChild(histBtn);
            card.appendChild(histDiv);
        }
        return card;
    }

    async function placeBid(auctionId, amount, btn) {
        btn.disabled = true;
        try {
            const data = await api(`/auctions/${auctionId}/bid`, {
                method: 'POST',
                body: JSON.stringify({amount}),
            });
            showStatus(`🔨 Licit leadva: ${data.new_bid} pont! ${data.extended_to ? 'Az aukció meghosszabbítva.' : ''}`, 'success');
            const c = state.circle;
            if (c) loadAuctions(c.id);
        } catch (err) {
            showStatus(err.message, 'error');
            btn.disabled = false;
        }
    }

    function formatCountdown(secs) {
        if (secs <= 0) return 'lejárt';
        const d = Math.floor(secs / 86400);
        const h = Math.floor((secs % 86400) / 3600);
        const m = Math.floor((secs % 3600) / 60);
        if (d > 0) return `${d} nap ${h} óra`;
        if (h > 0) return `${h} óra ${m} perc`;
        return `${m} perc`;
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

})();
</script>
</body>
</html>
<?php
/* ===========================================================================
 * §13  NGO Admin Panel — [impact_community_ngo_admin]
 * Standalone SPA shortcode for NGO admins: login, circle stats, advisor quotas
 * =========================================================================*/
add_shortcode( 'impact_community_ngo_admin', 'ic_render_ngo_admin' );
function ic_render_ngo_admin(): string {
    $api = esc_js( trailingslashit( get_rest_url() ) . 'ic/v1' );
    ob_start();
    ?>
<!DOCTYPE html>
<html class="ic-ngo-root" lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>NGO Admin &mdash; ImpactShop</title>
<style>
:root{--ic-primary:#2563eb;--ic-danger:#dc2626;--ic-ok:#16a34a;--ic-bg:#f8fafc;--ic-card:#fff;--ic-border:#e2e8f0;--ic-text:#1e293b;--ic-muted:#64748b}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:system-ui,sans-serif;background:var(--ic-bg);color:var(--ic-text);font-size:15px}
.ica-wrap{max-width:720px;margin:0 auto;padding:24px 16px}
.ica-card{background:var(--ic-card);border:1px solid var(--ic-border);border-radius:12px;padding:24px;margin-bottom:20px}
.ica-card h2{margin:0 0 16px;font-size:18px;font-weight:700}
.ica-form label{display:block;font-size:13px;font-weight:600;margin-bottom:4px;margin-top:12px}
.ica-form input{width:100%;padding:8px 12px;border:1px solid var(--ic-border);border-radius:8px;font-size:14px}
.ica-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:var(--ic-primary);color:#fff;transition:opacity .15s}
.ica-btn:disabled{opacity:.5;cursor:default}
.ica-btn.secondary{background:var(--ic-bg);color:var(--ic-primary);border:1px solid var(--ic-primary)}
.ica-btn.danger{background:var(--ic-danger)}
.ica-error{color:var(--ic-danger);font-size:13px;margin-top:8px}
.ica-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px}
.ica-stat-box{background:var(--ic-bg);border:1px solid var(--ic-border);border-radius:10px;padding:14px;text-align:center}
.ica-stat-box .val{font-size:26px;font-weight:800;color:var(--ic-primary)}
.ica-stat-box .lbl{font-size:11px;color:var(--ic-muted);margin-top:4px}
.ica-quota-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.ica-quota-card{background:var(--ic-bg);border:1px solid var(--ic-border);border-radius:10px;padding:16px}
.ica-quota-card h4{margin:0 0 10px;font-size:14px;font-weight:700;text-transform:capitalize}
.ica-quota-bar-wrap{background:#e2e8f0;border-radius:6px;height:8px;overflow:hidden;margin-bottom:8px}
.ica-quota-bar{height:100%;border-radius:6px;background:var(--ic-primary);transition:width .4s}
.ica-quota-bar.low{background:var(--ic-danger)}
.ica-quota-meta{font-size:12px;color:var(--ic-muted)}
.ica-ask-btn{margin-top:10px;width:100%;padding:7px;font-size:12px;font-weight:600;border:1px solid var(--ic-primary);border-radius:7px;background:#fff;color:var(--ic-primary);cursor:pointer}
.ica-ask-btn:disabled{opacity:.4;cursor:default}
.ica-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.ica-modal-bg.open{display:flex}
.ica-modal{background:#fff;border-radius:14px;padding:28px;max-width:460px;width:100%;margin:16px}
.ica-modal h3{margin:0 0 14px}
.ica-modal textarea{width:100%;padding:10px;border:1px solid var(--ic-border);border-radius:8px;font-size:14px;min-height:100px;resize:vertical}
.ica-modal-actions{display:flex;gap:10px;margin-top:14px;justify-content:flex-end}
.ica-notice{padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:16px}
.ica-notice.ok{background:#dcfce7;color:#14532d}
.ica-notice.err{background:#fee2e2;color:#7f1d1d}
#ica-logout-btn{float:right;margin-top:-4px}
</style>
</head>
<body>
<div class="ica-wrap" id="ica-app">
  <div id="ica-screen-login" style="display:none">
    <div class="ica-card">
      <h2>NGO Admin belépés</h2>
      <div class="ica-form">
        <label for="ica-email">E-mail cím</label>
        <input id="ica-email" type="email" autocomplete="username" placeholder="ngo@example.com">
        <label for="ica-pw">Jelszó</label>
        <input id="ica-pw" type="password" autocomplete="current-password">
        <div id="ica-login-err" class="ica-error" style="display:none"></div>
        <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
          <button class="ica-btn" id="ica-login-btn">Belépés</button>
          <a href="#" id="ica-reset-link" style="font-size:13px;color:var(--ic-primary)">Elfelejtett jelszó</a>
        </div>
      </div>
    </div>
  </div>

  <div id="ica-screen-reset" style="display:none">
    <div class="ica-card">
      <h2>Jelszó visszaállítás</h2>
      <div class="ica-form">
        <label for="ica-reset-email">E-mail cím</label>
        <input id="ica-reset-email" type="email" placeholder="ngo@example.com">
        <div id="ica-reset-msg" class="ica-error" style="display:none"></div>
        <div style="margin-top:16px;display:flex;gap:10px">
          <button class="ica-btn" id="ica-reset-send-btn">Link küldése</button>
          <button class="ica-btn secondary" id="ica-reset-back-btn">Vissza</button>
        </div>
      </div>
    </div>
  </div>

  <div id="ica-screen-dashboard" style="display:none">
    <div class="ica-card">
      <h2>
        Körös áttekintő
        <button class="ica-btn danger" id="ica-logout-btn" style="font-size:12px;padding:5px 12px">Kilépés</button>
      </h2>
      <div id="ica-notice" class="ica-notice" style="display:none"></div>
      <div class="ica-stats-grid" id="ica-stats"></div>
    </div>

    <div class="ica-card">
      <h2>Email blast</h2>
      <div id="ica-blast-locked" style="display:none;color:var(--ic-muted);font-size:14px">
        Ebben a hónapban már küldtél kampánylevelet.
      </div>
      <div id="ica-blast-form">
        <div class="ica-form">
          <label for="ica-blast-subj">Tárgy</label>
          <input id="ica-blast-subj" type="text" placeholder="Havi hír a körtől">
          <label for="ica-blast-body">Üzenet (szöveg)</label>
          <textarea id="ica-blast-body" style="width:100%;padding:8px 12px;border:1px solid var(--ic-border);border-radius:8px;font-size:14px;min-height:90px;resize:vertical;margin-top:4px" placeholder="Kedves tagunk…"></textarea>
          <div id="ica-blast-err" class="ica-error" style="display:none"></div>
          <div style="margin-top:12px">
            <button class="ica-btn" id="ica-blast-btn">Küldés</button>
          </div>
        </div>
      </div>
    </div>

    <div class="ica-card">
      <h2>Impi NGO Copilot — havi keretek</h2>
      <div class="ica-quota-grid" id="ica-quota"></div>
    </div>
  </div>
</div>

<!-- Ask Impi modal -->
<div class="ica-modal-bg" id="ica-ask-modal">
  <div class="ica-modal">
    <h3 id="ica-modal-title">Kérdés küldése</h3>
    <textarea id="ica-modal-q" placeholder="Írd le a kérdésed (min. 10 karakter)…"></textarea>
    <div id="ica-modal-err" class="ica-error" style="display:none"></div>
    <div class="ica-modal-actions">
      <button class="ica-btn secondary" id="ica-modal-cancel">Mégse</button>
      <button class="ica-btn" id="ica-modal-send">Küldés</button>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';
    const API = '<?php echo $api; ?>';
    const SK  = 'ic_ngo_token';
    const SL  = 'ic_ngo_slug';

    let state = { token: sessionStorage.getItem(SK), slug: sessionStorage.getItem(SL), channel: null };

    /* ── helpers ─────────────────────────────────────────────────────────── */
    function show(id)  { document.getElementById(id).style.display = ''; }
    function hide(id)  { document.getElementById(id).style.display = 'none'; }
    function text(id, t){ document.getElementById(id).textContent = t; }
    function esc(s)    { const d=document.createElement('div');d.textContent=String(s);return d.innerHTML; }

    async function api(method, path, body, token) {
        const opts = { method, headers: { 'Content-Type': 'application/json' } };
        if (token) opts.headers['Authorization'] = 'Bearer ' + token;
        if (body)  opts.body = JSON.stringify(body);
        const r = await fetch(API + path, opts);
        const j = await r.json().catch(() => ({}));
        return { ok: r.ok, status: r.status, data: j };
    }

    function notice(msg, type) {
        const el = document.getElementById('ica-notice');
        el.textContent = msg;
        el.className = 'ica-notice ' + type;
        el.style.display = '';
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    /* ── routing ─────────────────────────────────────────────────────────── */
    function route() {
        hide('ica-screen-login');
        hide('ica-screen-reset');
        hide('ica-screen-dashboard');
        if (state.token) {
            show('ica-screen-dashboard');
            loadDashboard();
        } else {
            show('ica-screen-login');
        }
    }

    /* ── login ───────────────────────────────────────────────────────────── */
    document.getElementById('ica-login-btn').addEventListener('click', async () => {
        const email = document.getElementById('ica-email').value.trim();
        const pw    = document.getElementById('ica-pw').value;
        const errEl = document.getElementById('ica-login-err');
        errEl.style.display = 'none';
        if (!email || !pw) { errEl.textContent='Töltsd ki mindkét mezőt.'; errEl.style.display=''; return; }
        const btn = document.getElementById('ica-login-btn');
        btn.disabled = true;
        const r = await api('POST', '/ngo/login', { email, password: pw });
        btn.disabled = false;
        if (r.ok) {
            state.token = r.data.token;
            state.slug  = r.data.ngo_slug;
            sessionStorage.setItem(SK, state.token);
            sessionStorage.setItem(SL, state.slug);
            route();
        } else {
            errEl.textContent = r.data.error === 'invalid_credentials'
                ? 'Hibás e-mail vagy jelszó.' : 'Bejelentkezési hiba.';
            errEl.style.display = '';
        }
    });

    /* ── reset password ──────────────────────────────────────────────────── */
    document.getElementById('ica-reset-link').addEventListener('click', e => {
        e.preventDefault();
        hide('ica-screen-login');
        show('ica-screen-reset');
    });
    document.getElementById('ica-reset-back-btn').addEventListener('click', () => {
        hide('ica-screen-reset');
        show('ica-screen-login');
    });
    document.getElementById('ica-reset-send-btn').addEventListener('click', async () => {
        const email = document.getElementById('ica-reset-email').value.trim();
        const msgEl = document.getElementById('ica-reset-msg');
        if (!email) { msgEl.textContent='Add meg az e-mail cím!'; msgEl.style.display=''; return; }
        const btn = document.getElementById('ica-reset-send-btn');
        btn.disabled = true;
        await api('POST', '/ngo/reset-password', { email });
        btn.disabled = false;
        msgEl.textContent = 'Ha az e-mail regisztrált, hamarosan megérkezik a link.';
        msgEl.style.color = 'var(--ic-ok)';
        msgEl.style.display = '';
    });

    /* ── logout ──────────────────────────────────────────────────────────── */
    document.getElementById('ica-logout-btn').addEventListener('click', () => {
        state.token = null; state.slug = null;
        sessionStorage.removeItem(SK); sessionStorage.removeItem(SL);
        route();
    });

    /* ── dashboard ───────────────────────────────────────────────────────── */
    async function loadDashboard() {
        const r = await api('GET', '/ngo/circle', null, state.token);
        if (!r.ok) {
            if (r.status === 401) { state.token=null; sessionStorage.removeItem(SK); route(); }
            return;
        }
        renderStats(r.data);
        renderQuota(r.data.advisor);
        if (r.data.blast_locked) {
            show('ica-blast-locked');
            hide('ica-blast-form');
        } else {
            hide('ica-blast-locked');
            show('ica-blast-form');
        }
    }

    function renderStats(data) {
        const c = data.circle || {};
        const items = [
            { val: c.active_members ?? 0,  lbl: 'Aktív tag' },
            { val: c.monthly_posts  ?? 0,  lbl: 'Havi hozzászólás' },
            { val: c.total_votes    ?? 0,  lbl: 'Szavazatok' },
            { val: (parseFloat(c.health_score) || 0).toFixed(1), lbl: 'Egészség %' },
            { val: c.community_bonus ? (parseFloat(c.community_bonus)*100-100).toFixed(0)+'%' : '—', lbl: 'Közösségi bónusz' },
        ];
        document.getElementById('ica-stats').innerHTML = items
            .map(i => `<div class="ica-stat-box"><div class="val">${esc(i.val)}</div><div class="lbl">${esc(i.lbl)}</div></div>`)
            .join('');
    }

    function renderQuota(advisor) {
        const labels = { legal: '⚖️ Jogi', finance: '💰 Pénzügyi', marketing: '📣 Marketing' };
        document.getElementById('ica-quota').innerHTML = Object.entries(advisor || {}).map(([ch, q]) => {
            const pct   = q.cap > 0 ? Math.min(100, Math.round(q.used / q.cap * 100)) : 0;
            const low   = q.remaining === 0 ? ' low' : '';
            return `<div class="ica-quota-card">
              <h4>${labels[ch] || ch}</h4>
              <div class="ica-quota-bar-wrap"><div class="ica-quota-bar${low}" style="width:${pct}%"></div></div>
              <div class="ica-quota-meta">${q.used} / ${q.cap} felhasználva · <strong>${q.remaining} maradt</strong></div>
              <button class="ica-ask-btn" data-channel="${ch}" ${q.remaining === 0 ? 'disabled' : ''}>
                Kérdés Impinek →
              </button>
            </div>`;
        }).join('');

        document.querySelectorAll('.ica-ask-btn').forEach(btn => {
            btn.addEventListener('click', () => openAskModal(btn.dataset.channel));
        });
    }

    /* ── blast ───────────────────────────────────────────────────────────── */
    document.getElementById('ica-blast-btn').addEventListener('click', async () => {
        const subj = document.getElementById('ica-blast-subj').value.trim();
        const body = document.getElementById('ica-blast-body').value.trim();
        const errEl = document.getElementById('ica-blast-err');
        errEl.style.display = 'none';
        if (!subj || !body) { errEl.textContent='Tárgy és üzenet kötelező.'; errEl.style.display=''; return; }
        const btn = document.getElementById('ica-blast-btn');
        btn.disabled = true;
        const r = await api('POST', '/ngo/circle/blast', { subject: subj, body }, state.token);
        btn.disabled = false;
        if (r.ok) {
            notice(`Kampánylevél elküldve ${r.data.sent} tagnak!`, 'ok');
            loadDashboard();
        } else {
            const msgs = { already_blasted_this_month: 'Ebben a hónapban már küldtél levelet.', missing_subject_or_body: 'Tárgy és üzenet kötelező.' };
            errEl.textContent = msgs[r.data.error] || 'Hiba a küldés során.';
            errEl.style.display = '';
        }
    });

    /* ── ask modal ───────────────────────────────────────────────────────── */
    function openAskModal(channel) {
        const titles = { legal: '⚖️ Jogi kérdés', finance: '💰 Pénzügyi kérdés', marketing: '📣 Marketing kérdés' };
        state.channel = channel;
        text('ica-modal-title', titles[channel] || 'Kérdés küldése');
        document.getElementById('ica-modal-q').value = '';
        document.getElementById('ica-modal-err').style.display = 'none';
        document.getElementById('ica-ask-modal').classList.add('open');
    }

    document.getElementById('ica-modal-cancel').addEventListener('click', () => {
        document.getElementById('ica-ask-modal').classList.remove('open');
    });

    document.getElementById('ica-modal-send').addEventListener('click', async () => {
        const q     = document.getElementById('ica-modal-q').value.trim();
        const errEl = document.getElementById('ica-modal-err');
        errEl.style.display = 'none';
        if (q.length < 10) { errEl.textContent='Min. 10 karakter szükséges.'; errEl.style.display=''; return; }
        const btn = document.getElementById('ica-modal-send');
        btn.disabled = true;
        const r = await api('POST', '/ngo/advisor/ask', { channel: state.channel, question: q }, state.token);
        btn.disabled = false;
        if (r.ok) {
            document.getElementById('ica-ask-modal').classList.remove('open');
            notice(r.data.message || 'Kérdés elküldve!', 'ok');
            loadDashboard();
        } else {
            const msgs = { quota_exceeded:'Elfogyott a havi keret.', question_too_short:'Min. 10 karakter szükséges.' };
            errEl.textContent = msgs[r.data.error] || 'Hiba a küldéskor.';
            errEl.style.display = '';
        }
    });

    /* ── init ────────────────────────────────────────────────────────────── */
    route();
})();
</script>
</body>
</html>
    <?php
    return ob_get_clean();
}

/* =========================================================================
   §16 — Platform Admin Dashboard — [impact_community_admin_dashboard]
   Requires manage_options. Shows all circles with health, stats and bonus.
   ========================================================================= */

add_shortcode( 'impact_community_admin_dashboard', 'ic_render_admin_dashboard' );
function ic_render_admin_dashboard(): string {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p>Nincs hozzáférésed ehhez az oldalhoz.</p>';
    }

    ob_start();
    $rest_url = esc_url( rest_url( 'ic/v1/admin/circles' ) );
    $nonce    = wp_create_nonce( 'wp_rest' );
    ?>
<div id="ic-admin-dash" style="font-family:system-ui,sans-serif;max-width:1200px;margin:0 auto;padding:20px">
<h2 style="color:#1b5e20;margin-bottom:16px">🌿 Hatás Körök — Admin Dashboard</h2>
<div id="ic-adm-status" style="color:#666;margin-bottom:12px">Betöltés…</div>
<div id="ic-adm-summary" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap"></div>
<div style="overflow-x:auto">
<table id="ic-adm-table" style="width:100%;border-collapse:collapse;font-size:14px">
<thead>
<tr style="background:#1b5e20;color:#fff">
  <th style="padding:8px 10px;text-align:left">Slug</th>
  <th style="padding:8px 10px;text-align:left">Név</th>
  <th style="padding:8px 10px;text-align:left">Típus</th>
  <th style="padding:8px 10px;text-align:right">Tagok</th>
  <th style="padding:8px 10px;text-align:right">Havi posztok</th>
  <th style="padding:8px 10px;text-align:right">Szavazatok</th>
  <th style="padding:8px 10px;text-align:right">Egészség %</th>
  <th style="padding:8px 10px;text-align:right">Bónusz×</th>
  <th style="padding:8px 10px;text-align:left">Utolsó blast</th>
</tr>
</thead>
<tbody id="ic-adm-tbody"></tbody>
</table>
</div>
</div>
<style>
#ic-adm-tbody tr:nth-child(even){background:#f1f8e9}
#ic-adm-tbody tr:hover{background:#dcedc8}
.ic-health-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px}
.ic-health-high{background:#c8e6c9;color:#1b5e20}
.ic-health-mid{background:#fff9c4;color:#f57f17}
.ic-health-low{background:#ffcdd2;color:#b71c1c}
.ic-bonus-active{color:#e65100;font-weight:700}
#ic-adm-summary .ic-stat-box{background:#e8f5e9;border-radius:8px;padding:12px 20px;min-width:130px;text-align:center}
#ic-adm-summary .ic-stat-box strong{display:block;font-size:22px;color:#1b5e20}
#ic-adm-summary .ic-stat-box span{font-size:12px;color:#555}
</style>
<script>
(async () => {
    const res = await fetch(<?php echo wp_json_encode( $rest_url ); ?>, {
        headers: {'X-WP-Nonce': <?php echo wp_json_encode( $nonce ); ?>}
    });
    const statusEl = document.getElementById('ic-adm-status');
    if (!res.ok) { statusEl.textContent = 'API hiba: ' + res.status; return; }
    const rows = await res.json();
    statusEl.textContent = rows.length + ' aktív kör — ' + new Date().toLocaleString('hu-HU');

    // Summary cards
    const summary = document.getElementById('ic-adm-summary');
    const totalMembers  = rows.reduce((s,r) => s + r.member_count, 0);
    const totalPosts    = rows.reduce((s,r) => s + r.monthly_posts, 0);
    const avgHealth     = rows.length ? Math.round(rows.reduce((s,r) => s + r.health_score, 0) / rows.length) : 0;
    const bonusCircles  = rows.filter(r => r.community_bonus > 1).length;
    [
        [totalMembers, 'Összes tag'],
        [totalPosts,   'Havi posztok'],
        [avgHealth + '%', 'Átl. egészség'],
        [bonusCircles, 'Bónuszos kör'],
    ].forEach(([val, lbl]) => {
        summary.insertAdjacentHTML('beforeend',
            `<div class="ic-stat-box"><strong>${val}</strong><span>${lbl}</span></div>`);
    });

    // Table rows
    const tbody = document.getElementById('ic-adm-tbody');
    rows.forEach(r => {
        const hClass = r.health_score >= 70 ? 'ic-health-high'
                     : r.health_score >= 40 ? 'ic-health-mid' : 'ic-health-low';
        const bonusText = r.community_bonus > 1
            ? '<span class="ic-bonus-active">×' + r.community_bonus.toFixed(2) + '</span>'
            : '×1.00';
        const blast = r.last_blast_at
            ? new Date(r.last_blast_at).toLocaleDateString('hu-HU')
            : '—';
        tbody.insertAdjacentHTML('beforeend', `<tr>
            <td style="padding:7px 10px;font-family:monospace">${r.ref_slug}</td>
            <td style="padding:7px 10px">${r.name || '—'}</td>
            <td style="padding:7px 10px">${r.type}</td>
            <td style="padding:7px 10px;text-align:right">${r.member_count}</td>
            <td style="padding:7px 10px;text-align:right">${r.monthly_posts}</td>
            <td style="padding:7px 10px;text-align:right">${r.votes_generated}</td>
            <td style="padding:7px 10px;text-align:right">
                <span class="ic-health-pill ${hClass}">${r.health_score}</span>
            </td>
            <td style="padding:7px 10px;text-align:right">${bonusText}</td>
            <td style="padding:7px 10px">${blast}</td>
        </tr>`);
    });
})();
</script>
    <?php
    return ob_get_clean();
}

