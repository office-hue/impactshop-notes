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
    window.ImpactCommunity = { init: handleHash };

})();
</script>
</body>
</html>
