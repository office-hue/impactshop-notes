<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function impactshop_action_bar_should_render(): bool
{
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

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === 'adomany.sharity.hu') {
        return false;
    }

    $home_host = strtolower((string) parse_url((string) home_url(), PHP_URL_HOST));
    if ($host !== '' && $host !== $home_host) {
        $allowed = ['app.sharity.hu', 'staging.sharity.hu', 'app-staging.sharity.hu'];
        if (!in_array($host, $allowed, true)) {
            return false;
        }
    }

    return true;
}

function impactshop_action_bar_current_path(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($path === '') {
        $path = '/';
    }
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return strtolower($path);
}

function impactshop_action_bar_render(): void
{
    if (!impactshop_action_bar_should_render()) {
        return;
    }

    $path = impactshop_action_bar_current_path();
    $shopping_assistant_url = 'https://sharity.hu/vasarlasi-seged';
    $offerwall_url = 'https://sharity.hu/offerwall';
    $ngo_card_url = 'https://sharity.hu/ngo-kartyak';
    $tip_game_url = 'https://factlens.eu/factlens/vb-prod/';
    $commitments_url = 'https://sharity.hu/vallalasok';
    $account_url = home_url('/profil/#impactshop-account-top');
    $community_url = 'https://sharity.hu/hatas-korok';
    $account_attr = str_starts_with($path, '/profil') ? ' aria-current="page"' : '';

    // Lang + country selector
    $current_lang    = sanitize_key( (string)( $_GET['lang']    ?? '' ) );
    $current_country = sanitize_key( (string)( $_GET['country'] ?? '' ) );
    if ( ! in_array( $current_lang,    ['hu', 'en'], true ) ) { $current_lang   = 'hu'; }
    if ( ! in_array( $current_country, ['hu', 'us'], true ) ) { $current_country = 'hu'; }
    $slc_langs = [
        'hu' => [ 'flag' => '🇭🇺', 'name' => 'Magyar' ],
        'en' => [ 'flag' => '🇬🇧', 'name' => 'English' ],
    ];
    $slc_countries = [
        'hu' => [ 'flag' => '🇭🇺', 'name' => 'Magyarország' ],
        'us' => [ 'flag' => '🇺🇸', 'name' => 'USA' ],
    ];
    $slc_lang_flag    = $slc_langs[$current_lang]['flag'];
    $slc_country_flag = $slc_countries[$current_country]['flag'];
    $slc_pill_label   = $slc_lang_flag . "\u{00A0}" . strtoupper($current_lang);
    if ( $slc_country_flag !== $slc_lang_flag ) {
        $slc_pill_label .= "\u{00A0}·\u{00A0}" . $slc_country_flag;
    }

    ?>
    <style>
        :root {
            --sharity-action-bar-height: 126px;
            --sharity-action-bar-height-tablet: 146px;
            --sharity-action-bar-height-desktop: 146px;
            --sharity-a11y-clearance: 110px;
        }

        @media (max-width: 768px) {
            body {
                padding-bottom: calc(var(--sharity-action-bar-height) + env(safe-area-inset-bottom) + 8px);
            }

            .bottom-nav,
            .bottom-nav-bar,
            .bottom-navigation,
            .mobile-bottom-nav,
            .mobile-nav,
            .mobile-nav-bar,
            .ads-watch-floating-tabs {
                display: none !important;
            }

            .sharity-pwa-install {
                display: block;
                width: 100%;
                margin: 16px 0;
                padding: 14px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: #fff;
                font-weight: 700;
                font-size: 15px;
                border-radius: 12px;
                text-align: center;
                border: none;
                cursor: pointer;
            }

            .sharity-pwa-install.sharity-pwa-install--icon {
                width: 56px !important;
                min-width: 56px;
                height: 56px;
                margin: 12px auto 16px;
                padding: 0 !important;
                border-radius: 999px;
                font-size: 26px !important;
                line-height: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .impactshop-pwa-reopen.sharity-pwa-install--icon {
                width: 56px !important;
                min-width: 56px;
                height: 56px;
                padding: 0 !important;
                border-radius: 999px;
                font-size: 26px !important;
                line-height: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                left: 12px !important;
                bottom: calc(var(--sharity-action-bar-height) + env(safe-area-inset-bottom) + 16px) !important;
                z-index: 10012 !important;
            }

            .sharity-pwa-install--icon::before {
                content: "";
                width: 26px;
                height: 26px;
                display: block;
                background-repeat: no-repeat;
                background-position: center;
                background-size: 26px 26px;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 3v11'/%3E%3Cpath d='m7 11 5 5 5-5'/%3E%3Cpath d='M5 20h14'/%3E%3C/svg%3E");
            }

            #aioa-floating-btn,
            .aioa-addon-floating-icon,
            .aioseo-accessibility-widget,
            #accessibility_settings_toggle {
                bottom: calc(var(--sharity-action-bar-height) + env(safe-area-inset-bottom) + var(--sharity-a11y-clearance)) !important;
                right: 12px !important;
                z-index: 10010;
            }
        }

        @media (max-width: 768px) and (orientation: landscape) and (max-height: 500px) {
            body {
                padding-bottom: 0;
            }
        }

        /* --- Lang/country selector pill --- */
        .sharity-slc {
            position: fixed;
            right: 12px;
            bottom: calc(var(--sharity-action-bar-height) + env(safe-area-inset-bottom) + 8px);
            z-index: 10006;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(26, 26, 46, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            padding: 5px 12px 5px 9px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: opacity 0.15s ease, transform 0.15s ease;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            letter-spacing: 0.02em;
            user-select: none;
            outline: none;
        }
        .sharity-slc:active { opacity: 0.75; transform: scale(0.96); }
        .sharity-slc__globe { font-size: 14px; line-height: 1; }
        .sharity-slc__label { line-height: 1; }

        @media (min-width: 769px) {
            .sharity-slc { bottom: calc(var(--sharity-action-bar-height-tablet) + 14px + 8px); }
        }
        @media (min-width: 1101px) {
            .sharity-slc { bottom: calc(var(--sharity-action-bar-height-desktop) + 14px + 8px); }
        }
        @media (max-width: 768px) and (orientation: landscape) and (max-height: 500px) {
            .sharity-slc { display: none; }
        }

        /* --- Backdrop --- */
        .sharity-slc__backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10007;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            animation: slc-fade-in 0.18s ease;
        }
        .sharity-slc__backdrop.is-open { display: block; }

        /* --- Bottom sheet --- */
        .sharity-slc__sheet {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            z-index: 10008;
            background: #1a1a2e;
            border-top: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px 20px 0 0;
            padding: 0 0 calc(env(safe-area-inset-bottom) + 16px);
            transform: translateY(100%);
            transition: transform 0.28s cubic-bezier(0.32,0.72,0,1);
            will-change: transform;
            max-height: 80vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .sharity-slc__sheet.is-open { transform: translateY(0); }

        .sharity-slc__handle {
            width: 40px; height: 4px;
            background: rgba(255,255,255,0.22);
            border-radius: 99px;
            margin: 12px auto 4px;
        }
        .sharity-slc__header {
            text-align: center;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 10px 16px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sharity-slc__section-title {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            padding: 16px 20px 6px;
        }
        .sharity-slc__rows { padding: 0 12px 4px; }
        .sharity-slc__row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 10px;
            border-radius: 12px;
            color: rgba(255,255,255,0.75);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.12s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .sharity-slc__row:active { background: rgba(255,255,255,0.06); }
        .sharity-slc__row--active { color: #fff; font-weight: 700; }
        .sharity-slc__row--active .sharity-slc__row-name { color: #60a5fa; }
        .sharity-slc__row-flag { font-size: 22px; line-height: 1; flex-shrink: 0; }
        .sharity-slc__row-name { flex: 1; }
        .sharity-slc__row-check { color: #60a5fa; font-size: 16px; margin-left: auto; }

        @keyframes slc-fade-in { from { opacity: 0; } to { opacity: 1; } }

        .sharity-action-bar {
            position: fixed;
            bottom: max(8px, env(safe-area-inset-bottom));
            left: 50%;
            z-index: 10005;
            pointer-events: auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            width: min(720px, calc(100vw - 16px));
            padding: 8px;
            transform: translateX(-50%);
            background: rgba(250, 249, 246, 0.95);
            border: 2px solid #150f26;
            border-radius: 20px;
            box-shadow: 6px 6px 0 #150f26;
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
        }

        .sharity-action-bar a,
        .sharity-action-bar button {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
            min-height: 48px;
            padding: 6px 4px;
            color: #150f26;
            text-decoration: none;
            text-align: center;
            font-size: 10px;
            line-height: 12px;
            font-weight: 700;
            gap: 4px;
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            pointer-events: auto;
            transition: transform 0.15s ease;
            border: 2px solid #150f26;
            border-radius: 12px;
            margin: 0;
            font-family: inherit;
            appearance: none;
        }

        .sharity-action-bar [data-bar="shopping-assistant"],
        .sharity-action-bar [data-bar="tip-game"] {
            background: #fff7d9;
        }

        .sharity-action-bar [data-bar="offerwall"],
        .sharity-action-bar [data-bar="commitments"] {
            background: #f4ffd7;
        }

        .sharity-action-bar [data-bar="messages-upcoming"],
        .sharity-action-bar [data-bar="account"] {
            background: #fff0f3;
        }

        .sharity-action-bar [data-bar="ngo-card"],
        .sharity-action-bar [data-bar="community"] {
            background: #ede5ff;
        }

        .sharity-action-bar [data-bar="messages-upcoming"] {
            cursor: not-allowed;
            opacity: 0.75;
        }

        .sharity-action-bar a:active,
        .sharity-action-bar button:active {
            transform: translateY(1px);
        }

        .sharity-action-bar a:hover {
            transform: translateY(-2px);
        }

        .sharity-action-bar a:focus-visible,
        .sharity-action-bar button:focus-visible {
            outline: 4px solid rgba(109, 59, 245, 0.45);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            .sharity-action-bar a,
            .sharity-action-bar button {
                transition: none;
            }
        }

        .sharity-action-bar a[aria-current="page"],
        .sharity-action-bar button[aria-current="page"] {
            box-shadow: inset 0 0 0 2px #6d3bf5;
        }

        .sharity-action-bar .bar-icon {
            display: inline-flex;
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .sharity-action-bar .bar-icon svg {
            display: block;
            width: 100%;
            height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .sharity-action-bar .bar-coming-soon {
            position: absolute;
            top: 4px;
            right: 4px;
            padding: 0 4px;
            border: 1px solid #150f26;
            border-radius: 999px;
            background: #fff;
            font-size: 8px;
            line-height: 12px;
        }

        .sharity-message-popover {
            position: fixed;
            left: 50%;
            bottom: 140px;
            transform: translateX(-50%);
            width: min(560px, calc(100vw - 24px));
            background: #fffdf7;
            color: #171421;
            border: 2px solid #171421;
            border-radius: 14px;
            box-shadow: 0 18px 42px rgba(2, 6, 23, 0.46);
            padding: 12px 14px;
            z-index: 10020;
        }

        .sharity-message-popover[hidden] {
            display: none !important;
        }

        .sharity-message-popover__title {
            display: block;
            margin: 0 0 6px;
            font-size: 12px;
            font-weight: 700;
            color: #713cff;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .sharity-message-popover__body {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
            color: #171421;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .sharity-status-shortcut {
            padding: 0;
        }

        .sharity-status-shortcut-button {
            width: 100%;
            min-height: 66px;
            border: 1px solid rgba(59, 130, 246, 0.28);
            border-radius: 12px;
            background: #c9ff3d;
            color: #171421;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-weight: 700;
            cursor: pointer;
            padding: 8px 10px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .sharity-status-shortcut-button:hover {
            transform: translateY(-1px);
            box-shadow: 4px 4px 0 #171421;
        }

        .sharity-status-shortcut-button:active {
            opacity: 0.8;
            transform: scale(0.98);
        }

        .sharity-status-shortcut-button .shortcut-icon {
            font-size: 22px;
            line-height: 1;
        }

        .sharity-status-shortcut-button .shortcut-label {
            font-size: 12px;
            line-height: 1.15;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .sharity-status-shortcut--desktop-only {
            display: none !important;
        }

        .sharity-cross-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 10px;
        }

        .sharity-cross-header-title {
            margin-right: auto;
        }

        .sharity-cross-header-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .sharity-cross-nav-btn {
            width: 32px;
            height: 32px;
            border: 2px solid #171421;
            border-radius: 999px;
            background: #f6c4d8;
            color: #171421;
            font-size: 16px;
            line-height: 1;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }

        .sharity-cross-nav-btn:hover {
            transform: translateY(-1px);
            box-shadow: 3px 3px 0 #171421;
        }

        .sharity-cross-nav-btn:active {
            opacity: 0.8;
            transform: scale(0.98);
        }

        @media (min-width: 769px) {
            body {
                padding-bottom: calc(var(--sharity-action-bar-height-tablet) + 18px);
            }

            .ads-watch-floating-tabs {
                display: none !important;
            }

            .sharity-action-bar {
                bottom: max(16px, env(safe-area-inset-bottom));
                width: min(720px, calc(100vw - 32px));
                gap: 8px;
                padding: 12px;
            }

            .sharity-action-bar a,
            .sharity-action-bar button {
                min-height: 56px;
                flex-direction: row;
                font-size: 12px;
                line-height: 16px;
                padding: 8px;
                gap: 8px;
            }

            .sharity-action-bar .bar-icon {
                width: 20px;
                height: 20px;
            }

            .sharity-message-popover {
                bottom: 154px;
                z-index: 10025;
            }

            #impactshop-pwa-install,
            .impactshop-pwa-install,
            #impactshop-pwa-reopen,
            .impactshop-pwa-reopen {
                z-index: 10016 !important;
            }

            .sharity-status-shortcut--desktop-only {
                display: block !important;
            }
        }

        @media (min-width: 1101px) {
            body {
                padding-bottom: calc(var(--sharity-action-bar-height-desktop) + 18px);
            }

            .sharity-message-popover {
                bottom: 154px;
            }
        }

    </style>

    <nav class="sharity-action-bar" aria-label="Sharity gyorsműveletek" data-sharity-quick-dock="true">
        <a href="<?php echo esc_url($shopping_assistant_url); ?>" data-bar="shopping-assistant">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg></span>
            <span>Vásárlási Segéd</span>
        </a>
        <a href="<?php echo esc_url($offerwall_url); ?>" data-bar="offerwall">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"></rect><path d="M3 9h18"></path><path d="M9 21V9"></path></svg></span>
            <span>Feladatok adományokért</span>
        </a>
        <button type="button" disabled aria-disabled="true" aria-label="Üzenetek (hamarosan)" title="A profil üzenetei hamarosan elérhetők." data-bar="messages-upcoming">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg></span>
            <span>Üzenetek</span>
            <span class="bar-coming-soon">Hamarosan</span>
        </button>
        <a href="<?php echo esc_url($ngo_card_url); ?>" data-bar="ngo-card">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path><path d="M12 5 9.04 7.96a2.17 2.17 0 0 0 0 3.08c.82.82 2.13.85 3 .07l2.07-1.9a2.82 2.82 0 0 1 3.79 0l2.96 2.66"></path><path d="m18 15-2-2"></path><path d="m15 18-2-2"></path></svg></span>
            <span>NGO Card</span>
        </a>
        <a href="<?php echo esc_url($tip_game_url); ?>" data-bar="tip-game">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg></span>
            <span>Tippjáték</span>
        </a>
        <a href="<?php echo esc_url($commitments_url); ?>" data-bar="commitments">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M11 14h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"></path><path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"></path><path d="m2 15 6 6"></path><path d="M19.5 8.5c.7-.7 1.5-1.6 1.5-2.7A2.73 2.73 0 0 0 16 4a2.78 2.78 0 0 0-5 1.8c0 1.2.8 2 1.5 2.8L16 12Z"></path></svg></span>
            <span>Vállalások</span>
        </a>
        <a href="<?php echo esc_url($account_url); ?>" data-bar="account"<?php echo $account_attr; ?>>
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 20a6 6 0 0 0-12 0"></path><circle cx="12" cy="10" r="4"></circle><circle cx="12" cy="12" r="10"></circle></svg></span>
            <span>Profil</span>
        </a>
        <a href="<?php echo esc_url($community_url); ?>" data-bar="community">
            <span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18 21a8 8 0 0 0-16 0"></path><circle cx="10" cy="8" r="5"></circle><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"></path></svg></span>
            <span>Közösség</span>
        </a>
    </nav>

    <button class="sharity-slc" id="sharity-slc-btn" type="button" aria-label="Nyelv és ország választó" aria-haspopup="dialog">
        <span class="sharity-slc__globe">🌐</span>
        <span class="sharity-slc__label"><?php echo wp_kses_post( $slc_pill_label ); ?></span>
    </button>

    <div class="sharity-slc__backdrop" id="sharity-slc-backdrop" aria-hidden="true"></div>

    <div class="sharity-slc__sheet" id="sharity-slc-sheet" role="dialog" aria-modal="true" aria-label="Nyelv és ország választó">
        <div class="sharity-slc__handle"></div>
        <div class="sharity-slc__header">🌍 Nyelv és ország</div>

        <div class="sharity-slc__section-title"><?php echo $current_lang === 'hu' ? 'Nyelv' : 'Language'; ?></div>
        <div class="sharity-slc__rows">
            <?php foreach ( $slc_langs as $lcode => $linfo ) :
                $lurl = $lcode === 'hu'
                    ? remove_query_arg('lang')
                    : add_query_arg( 'lang', $lcode, remove_query_arg('lang') );
                $lactive = $lcode === $current_lang;
            ?>
            <a href="<?php echo esc_url( $lurl ); ?>" class="sharity-slc__row<?php echo $lactive ? ' sharity-slc__row--active' : ''; ?>">
                <span class="sharity-slc__row-flag"><?php echo esc_html( $linfo['flag'] ); ?></span>
                <span class="sharity-slc__row-name"><?php echo esc_html( $linfo['name'] ); ?></span>
                <?php if ( $lactive ) : ?><span class="sharity-slc__row-check" aria-label="aktív">✓</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sharity-slc__section-title"><?php echo $current_lang === 'hu' ? 'Ország' : 'Country'; ?></div>
        <div class="sharity-slc__rows">
            <?php foreach ( $slc_countries as $ccode => $cinfo ) :
                $curl = $ccode === 'hu'
                    ? remove_query_arg('country')
                    : add_query_arg( 'country', $ccode, remove_query_arg('country') );
                $cactive = $ccode === $current_country;
            ?>
            <a href="<?php echo esc_url( $curl ); ?>" class="sharity-slc__row<?php echo $cactive ? ' sharity-slc__row--active' : ''; ?>">
                <span class="sharity-slc__row-flag"><?php echo esc_html( $cinfo['flag'] ); ?></span>
                <span class="sharity-slc__row-name"><?php echo esc_html( $cinfo['name'] ); ?></span>
                <?php if ( $cactive ) : ?><span class="sharity-slc__row-check" aria-label="aktív">✓</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    (function(){
        var btn      = document.getElementById('sharity-slc-btn');
        var sheet    = document.getElementById('sharity-slc-sheet');
        var backdrop = document.getElementById('sharity-slc-backdrop');
        if (!btn || !sheet || !backdrop) return;

        function openSheet() {
            sheet.classList.add('is-open');
            backdrop.classList.add('is-open');
        }
        function closeSheet() {
            sheet.classList.remove('is-open');
            backdrop.classList.remove('is-open');
        }

        btn.addEventListener('click', function(e) { e.stopPropagation(); openSheet(); });
        backdrop.addEventListener('click', closeSheet);
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeSheet(); });
    })();
    </script>

    <script>
        (function() {
            var isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
            var bar = document.querySelector('.sharity-action-bar');
            if (!bar) return;

            function normalizePath(pathname) {
                var value = String(pathname || '/').toLowerCase();
                if (value.length > 1 && value.charAt(value.length - 1) === '/') {
                    value = value.slice(0, -1);
                }
                return value || '/';
            }

            function isChallengePath(pathname) {
                var path = normalizePath(pathname);
                return path.indexOf('/impact-challenge') === 0 || path.indexOf('/impactad-2') === 0;
            }

            function adminBarOffset() {
                var wpAdminBar = document.getElementById('wpadminbar');
                return wpAdminBar ? wpAdminBar.offsetHeight + 10 : 10;
            }

            function scrollToElement(element, smooth) {
                if (!element || typeof element.getBoundingClientRect !== 'function') {
                    return false;
                }
                var top = Math.max(0, Math.round(element.getBoundingClientRect().top + window.pageYOffset - adminBarOffset()));
                window.scrollTo({
                    top: top,
                    behavior: smooth ? 'smooth' : 'auto'
                });
                return true;
            }

            function resolveHashTarget(hash) {
                if (!hash) return null;
                if (hash === '#impactshop-account') {
                    return document.querySelector('.impactshop-identity-panel, [id^="impactshop-identity-id-"], [data-role="pseudo-display"]');
                }
                if (hash === '#impactshop-ads-watch') {
                    return document.querySelector('#impactshop-ads-watch, .impactshop-ads-watch-container');
                }
                if (hash === '#ads-watch-message') {
                    return document.querySelector('#ads-watch-message, [data-role="ads-watch-message"]');
                }
                if (hash === '#ads-watch-ngo') {
                    return document.querySelector('#ads-watch-ngo, #btn-change-ngo');
                }
                if (hash === '#impactshop-offerwall') {
                    return document.querySelector('#impactshop-offerwall');
                }
                if (hash === '#ads-watch-video') {
                    return document.querySelector('#ads-watch-video');
                }
                if (hash === '#ads-watch-purchase') {
                    return document.querySelector('#ads-watch-purchase');
                }
                if (hash === '#impactshop-legacy-pool') {
                    return document.querySelector('#impactshop-legacy-pool, [data-role="herowall"], .impactshop-herowall');
                }
                if (hash === '#impactshop-challenges') {
                    return document.querySelector('#impactshop-challenges, .impactshop-challenges');
                }
                try {
                    return document.querySelector(hash);
                } catch (err) {
                    return null;
                }
            }

            function scrollToHash(hash, smooth) {
                var target = resolveHashTarget(hash);
                if (!target) return false;
                if (target.matches && target.matches('[data-role="pseudo-display"]')) {
                    target = target.closest('.impactshop-identity-panel') || target;
                }
                if (hash === '#impactshop-legacy-pool') {
                    expandLegacySection(target);
                } else if (hash === '#impactshop-challenges') {
                    expandChallengesSection(target);
                }
                return scrollToElement(target, smooth);
            }

            function getLegacySection() {
                return document.querySelector('#impactshop-legacy-pool, [data-role="herowall"], .impactshop-herowall');
            }

            function getChallengesSection() {
                return document.querySelector('#impactshop-challenges, .impactshop-challenges');
            }

            function ensureLegacyChallengeAnchors() {
                var legacy = getLegacySection();
                if (legacy && !legacy.id) {
                    legacy.id = 'impactshop-legacy-pool';
                }

                var challenges = getChallengesSection();
                if (challenges && !challenges.id) {
                    challenges.id = 'impactshop-challenges';
                }
            }

            function setHash(hash) {
                if (!hash) return;
                if (window.location.hash === hash) {
                    window.dispatchEvent(new Event('hashchange'));
                    return;
                }
                history.replaceState(null, '', hash);
                window.dispatchEvent(new Event('hashchange'));
            }

            function expandLegacySection(root) {
                if (!root) return;
                var list = root.querySelector('[data-role="herowall-list"]');
                var icon = root.querySelector('[data-role="herowall-icon"]');
                if (!list) return;
                var isCollapsed = list.style.display === 'none';
                if (!isCollapsed && list.style.display === '' && window.getComputedStyle) {
                    isCollapsed = window.getComputedStyle(list).display === 'none';
                }
                if (isCollapsed) {
                    list.style.display = 'grid';
                    if (icon) {
                        icon.style.transform = 'rotate(180deg)';
                    }
                }
            }

            function expandChallengesSection(root) {
                if (!root) return;
                var content = root.querySelector('[data-role="challenges-content"]');
                var icon = root.querySelector('[data-role="collapse-icon"]');
                if (!content) return;
                var isCollapsed = content.style.display === 'none';
                if (!isCollapsed && content.style.display === '' && window.getComputedStyle) {
                    isCollapsed = window.getComputedStyle(content).display === 'none';
                }
                if (isCollapsed) {
                    content.style.display = 'grid';
                    if (icon) {
                        icon.style.transform = 'rotate(180deg)';
                    }
                }
            }

            function openLegacySection() {
                ensureLegacyChallengeAnchors();
                var legacy = getLegacySection();
                if (!legacy) return false;
                expandLegacySection(legacy);
                setHash('#impactshop-legacy-pool');
                return scrollToElement(legacy, true);
            }

            function openChallengesSection() {
                ensureLegacyChallengeAnchors();
                var challenges = getChallengesSection();
                if (!challenges) return false;
                expandChallengesSection(challenges);
                setHash('#impactshop-challenges');
                return scrollToElement(challenges, true);
            }

            function buildStatusShortcut(key, icon, label, onClick, desktopOnly) {
                var item = document.createElement('div');
                item.className = 'status-item sharity-status-shortcut';
                if (desktopOnly) {
                    item.className += ' sharity-status-shortcut--desktop-only';
                }
                item.setAttribute('data-sharity-shortcut', key);

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'sharity-status-shortcut-button';
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);

                var iconNode = document.createElement('span');
                iconNode.className = 'shortcut-icon';
                iconNode.textContent = icon;

                var labelNode = document.createElement('span');
                labelNode.className = 'shortcut-label';
                labelNode.textContent = label;

                button.appendChild(iconNode);
                button.appendChild(labelNode);
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    onClick();
                });

                item.appendChild(button);
                return item;
            }

            function findHeaderTitleNode(header) {
                if (!header || !header.children) return null;
                for (var i = 0; i < header.children.length; i += 1) {
                    var child = header.children[i];
                    if (
                        child.classList.contains('sharity-cross-header-actions') ||
                        child.getAttribute('data-role') === 'collapse-icon' ||
                        child.getAttribute('data-role') === 'herowall-icon'
                    ) {
                        continue;
                    }
                    return child;
                }
                return null;
            }

            function ensureHeaderActionButton(header, key, icon, label, onClick, collapseSelector) {
                if (!header) return;
                if (header.querySelector('[data-sharity-header-link="' + key + '"]')) return;

                header.classList.add('sharity-cross-header');
                var titleNode = findHeaderTitleNode(header);
                if (titleNode) {
                    titleNode.classList.add('sharity-cross-header-title');
                }

                var collapseIcon = collapseSelector ? header.querySelector(collapseSelector) : null;
                var actions = header.querySelector('.sharity-cross-header-actions');
                if (!actions) {
                    actions = document.createElement('span');
                    actions.className = 'sharity-cross-header-actions';
                    if (collapseIcon && collapseIcon.parentNode === header) {
                        header.insertBefore(actions, collapseIcon);
                    } else {
                        header.appendChild(actions);
                    }
                }

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'sharity-cross-nav-btn';
                button.setAttribute('data-sharity-header-link', key);
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
                button.textContent = icon;
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    onClick();
                });

                actions.appendChild(button);

                if (collapseIcon && collapseIcon.parentNode === header) {
                    actions.appendChild(collapseIcon);
                }
            }

            function enhanceLegacyChallengeNavigation() {
                if (!isChallengePath(window.location.pathname)) {
                    return;
                }

                ensureLegacyChallengeAnchors();

                var statusBar = document.querySelector('#ads-watch-status-bar, .ads-watch-status-bar');
                if (statusBar) {
                    if (!statusBar.querySelector('[data-sharity-shortcut="legacy"]')) {
                        statusBar.appendChild(buildStatusShortcut('legacy', '🏆', 'Legacy Wall', openLegacySection, false));
                    }
                    if (!statusBar.querySelector('[data-sharity-shortcut="challenges"]')) {
                        statusBar.appendChild(buildStatusShortcut('challenges', '🎯', 'Kihívások', openChallengesSection, true));
                    }
                }

                var herowallHeader = document.querySelector('[data-role="herowall-toggle"]');
                ensureHeaderActionButton(
                    herowallHeader,
                    'challenges',
                    '🎯',
                    'Ugrás a Kihívások szekcióhoz',
                    openChallengesSection,
                    '[data-role="herowall-icon"]'
                );

                var challengesHeader = document.querySelector('[data-role="challenges-toggle"]');
                ensureHeaderActionButton(
                    challengesHeader,
                    'legacy',
                    '🏆',
                    'Ugrás a Legacy Pool szekcióhoz',
                    openLegacySection,
                    '[data-role="collapse-icon"]'
                );
            }

            document.querySelectorAll('.ads-watch-floating-tabs').forEach(function(el) {
                el.style.setProperty('display', 'none', 'important');
                el.setAttribute('data-sharity-hidden', '1');
            });

            if (isMobile) {
                var targets = ['.bottom-nav', '.bottom-nav-bar', '.bottom-navigation', '.mobile-bottom-nav', '.mobile-nav', '.mobile-nav-bar'];
                targets.forEach(function(selector) {
                    var nodes = document.querySelectorAll(selector);
                    nodes.forEach(function(el) {
                        el.style.display = 'none';
                        el.setAttribute('data-sharity-hidden', '1');
                    });
                });
            }

            var messageToggle = bar.querySelector('[data-bar="message"]');
            var messagePopover = document.createElement('div');
            messagePopover.className = 'sharity-message-popover';
            messagePopover.hidden = true;
            messagePopover.innerHTML = '' +
                '<strong class="sharity-message-popover__title">Aktuális üzenet</strong>' +
                '<p class="sharity-message-popover__body" data-role="sharity-message-popover-body"></p>';
            document.body.appendChild(messagePopover);
            var messageBody = messagePopover.querySelector('[data-role="sharity-message-popover-body"]');
            var messageOpen = false;
            var MESSAGE_STORAGE_KEY = 'sharity_action_bar_seen_message_v1';

            function normalizeMessage(text) {
                var value = String(text || '').trim().replace(/\s+/g, ' ').toLowerCase();
                if (value.normalize) {
                    value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                return value;
            }

            function isPlaceholderMessage(text) {
                var normalized = normalizeMessage(text);
                return normalized === '' || normalized.indexOf('uzenet hamarosan') === 0;
            }

            function getMessageText() {
                var selectors = [
                    '[data-role="ads-watch-message-text"]',
                    '#ads-watch-message [data-role="ads-watch-message-text"]',
                    '.impactshop-identity-message-body',
                    '[data-role="broadcast-message"] .impactshop-identity-message-body'
                ];
                var fallback = '';
                for (var i = 0; i < selectors.length; i += 1) {
                    var node = document.querySelector(selectors[i]);
                    var text = node ? String(node.textContent || '').trim() : '';
                    if (text === '') {
                        continue;
                    }
                    if (fallback === '') {
                        fallback = text;
                    }
                    if (!isPlaceholderMessage(text)) {
                        return text;
                    }
                }
                return fallback;
            }

            function getSeenMessage() {
                try {
                    return localStorage.getItem(MESSAGE_STORAGE_KEY) || '';
                } catch (err) {
                    return '';
                }
            }

            function setSeenMessage(value) {
                try {
                    localStorage.setItem(MESSAGE_STORAGE_KEY, String(value || ''));
                } catch (err) {}
            }

            function updateMessagePopoverPosition() {
                var barRect = bar.getBoundingClientRect();
                var bottom = Math.max(Math.round(window.innerHeight - barRect.top + 12), 92);
                var install = document.getElementById('impactshop-pwa-install') || document.querySelector('.impactshop-pwa-install');
                var reopen = document.getElementById('impactshop-pwa-reopen') || document.querySelector('.impactshop-pwa-reopen');

                [install, reopen].forEach(function(node) {
                    if (!node || node.hidden) return;
                    if (window.getComputedStyle && window.getComputedStyle(node).display === 'none') return;
                    var rect = node.getBoundingClientRect();
                    var candidate = Math.round(window.innerHeight - rect.top + 14);
                    if (candidate > bottom) {
                        bottom = candidate;
                    }
                });

                messagePopover.style.bottom = bottom + 'px';
            }

            function updateMessageUnreadState() {
                if (!messageToggle) return;
                var text = getMessageText();
                var hasUnread = !isPlaceholderMessage(text) && normalizeMessage(text) !== normalizeMessage(getSeenMessage());
                messageToggle.classList.toggle('has-unread', hasUnread);
            }

            function closeMessagePopover() {
                messageOpen = false;
                messagePopover.hidden = true;
            }

            function openMessagePopover() {
                var currentText = getMessageText();
                var safeText = currentText === '' ? 'Jelenleg nincs új üzenet.' : currentText;
                if (messageBody) {
                    messageBody.textContent = safeText;
                }
                messagePopover.hidden = false;
                messageOpen = true;
                updateMessagePopoverPosition();
                if (!isPlaceholderMessage(currentText)) {
                    setSeenMessage(currentText);
                }
                updateMessageUnreadState();
            }

            function toggleMessagePopover() {
                if (messageOpen) {
                    closeMessagePopover();
                    return;
                }
                openMessagePopover();
            }

            window.sharityOpenMessagePopover = function() {
                openMessagePopover();
                if (window.location.hash !== '#ads-watch-message') {
                    history.replaceState(null, '', '#ads-watch-message');
                }
                updateCurrent();
            };

            window.sharityToggleMessagePopover = function() {
                toggleMessagePopover();
                if (window.location.hash !== '#ads-watch-message') {
                    history.replaceState(null, '', '#ads-watch-message');
                }
                updateCurrent();
            };

            function navigate(link) {
                var href = link.getAttribute('href');
                if (!href) return;
                var barType = link.getAttribute('data-bar') || '';
                if (link.target === '_blank') {
                    window.open(href, '_blank', 'noopener,noreferrer');
                    return;
                }
                try {
                    var url = new URL(href, window.location.href);
                    if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.hash) {
                        if (barType === 'message' && isChallengePath(window.location.pathname)) {
                            toggleMessagePopover();
                            if (window.location.hash !== url.hash) {
                                history.replaceState(null, '', url.hash);
                                window.dispatchEvent(new Event('hashchange'));
                            }
                            return;
                        }
                        if (window.location.hash !== url.hash) {
                            window.location.hash = url.hash;
                            setTimeout(function() {
                                scrollToHash(url.hash, true);
                            }, 40);
                        } else {
                            scrollToHash(url.hash, true);
                            window.dispatchEvent(new Event('hashchange'));
                        }
                        return;
                    }
                    window.location.href = url.toString();
                } catch (err) {
                    window.location.href = href;
                }
            }

            var lastNav = 0;
            var DEBOUNCE = 150;

            bar.addEventListener('click', function(e) {
                var target = e.target;
                if (target && target.nodeType === 3) {
                    target = target.parentNode;
                }
                var link = target && target.closest ? target.closest('a, button') : null;
                if (!link || !bar.contains(link)) return;
                e.preventDefault();
                var barType = link.getAttribute('data-bar') || '';

                link.style.opacity = '0.5';
                link.style.transform = 'scale(0.95)';
                setTimeout(function() {
                    link.style.opacity = '';
                    link.style.transform = '';
                }, 350);

                if (barType === 'message' && isChallengePath(window.location.pathname)) {
                    if (messageOpen) {
                        closeMessagePopover();
                    } else {
                        openMessagePopover();
                    }
                    if (window.location.hash !== '#ads-watch-message') {
                        history.replaceState(null, '', '#ads-watch-message');
                    }
                    updateCurrent();
                    return;
                }

                var now = (window.performance && typeof window.performance.now === 'function') ? window.performance.now() : Date.now();
                if (now - lastNav < DEBOUNCE) {
                    return;
                }
                lastNav = now;
                navigate(link);
            }, false);

            if (isMobile) {
                var candidates = document.querySelectorAll('nav, [role="navigation"]');
                function normalizeLabel(text) {
                    var value = (text || '').replace(/\s+/g, ' ').toLowerCase();
                    if (value.normalize) {
                        value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                    return value;
                }
                var labels = ['kezd', 'aktiv', 'shop'];
                candidates.forEach(function(el) {
                    var text = normalizeLabel(el.textContent || '');
                    if (!text) return;
                    var hits = labels.filter(function(label) { return text.indexOf(label) !== -1; }).length;
                    if (hits >= 2) {
                        el.style.display = 'none';
                        el.setAttribute('data-sharity-hidden', '1');
                    }
                });
            }

            function iconifyInstallButton(btn) {
                if (!btn || btn.getAttribute('data-sharity-iconified') === '1') {
                    return;
                }
                btn.classList.add('sharity-pwa-install--icon');
                if (!btn.getAttribute('aria-label')) {
                    btn.setAttribute('aria-label', 'Telepítés');
                }
                btn.setAttribute('title', 'Telepítés');
                btn.textContent = '';
                btn.setAttribute('data-sharity-iconified', '1');
            }

            if (isMobile) {
                var btn = document.querySelector('[data-role="pwa-install"], .pwa-install-button, #pwa-install-button');
                if (!btn) {
                    var nodes = Array.prototype.slice.call(document.querySelectorAll('button, a'));
                    btn = nodes.find(function(el) {
                        return (el.textContent || '').trim().toLowerCase().indexOf('telep') !== -1;
                    }) || null;
                }
                var statsPanel = document.querySelector('.ads-watch-status-bar');
                if (!statsPanel) {
                    statsPanel = document.querySelector('.stats-grid, .impactshop-stats-grid, .stats-grid-container');
                }
                if (btn && statsPanel && !btn.getAttribute('data-sharity-moved')) {
                    statsPanel.parentNode.insertBefore(btn, statsPanel.nextSibling);
                    btn.setAttribute('data-sharity-moved', '1');
                    btn.classList.add('sharity-pwa-install');
                    iconifyInstallButton(btn);
                }

                iconifyInstallButton(document.querySelector('.impactshop-pwa-reopen'));
                var installPolls = 0;
                var installTimer = setInterval(function() {
                    iconifyInstallButton(document.querySelector('.impactshop-pwa-reopen'));
                    installPolls += 1;
                    if (installPolls > 20) {
                        clearInterval(installTimer);
                    }
                }, 500);
            }

            function repositionDesktopPwaInstall() {
                if (isMobile) return;
                var install = document.getElementById('impactshop-pwa-install') || document.querySelector('.impactshop-pwa-install');
                var reopen = document.getElementById('impactshop-pwa-reopen') || document.querySelector('.impactshop-pwa-reopen');
                var barRect = bar.getBoundingClientRect();
                var barBottomOffset = Math.max(Math.round(window.innerHeight - barRect.top), 80);
                var installBottom = barBottomOffset + 12;

                if (install) {
                    install.style.setProperty('bottom', installBottom + 'px', 'important');
                    install.style.setProperty('z-index', '10016', 'important');
                }
                if (reopen) {
                    reopen.style.setProperty('bottom', (installBottom + 14) + 'px', 'important');
                    reopen.style.setProperty('z-index', '10016', 'important');
                }
            }

            function updateCurrent() {
                var currentPath = normalizePath(window.location.pathname);
                var account = bar.querySelector('[data-bar="account"]');
                if (!account) return;
                if (currentPath.indexOf('/profil') === 0) {
                    account.setAttribute('aria-current', 'page');
                } else {
                    account.removeAttribute('aria-current');
                }
            }

            updateCurrent();
            window.addEventListener('hashchange', updateCurrent);
            updateMessageUnreadState();
            enhanceLegacyChallengeNavigation();

            var legacyChallengePolls = 0;
            var legacyChallengeTimer = setInterval(function() {
                enhanceLegacyChallengeNavigation();
                legacyChallengePolls += 1;
                if (legacyChallengePolls > 30) {
                    clearInterval(legacyChallengeTimer);
                }
            }, 400);

            if (window.MutationObserver && isChallengePath(window.location.pathname)) {
                var legacyChallengeObserver = new MutationObserver(function() {
                    enhanceLegacyChallengeNavigation();
                });
                legacyChallengeObserver.observe(document.body, { childList: true, subtree: true });
                setTimeout(function() {
                    legacyChallengeObserver.disconnect();
                }, 20000);
            }

            document.addEventListener('click', function(e) {
                if (!messageOpen) return;
                var node = e.target;
                if (!node) return;
                if (messagePopover.contains(node)) return;
                if (messageToggle && messageToggle.contains(node)) return;
                closeMessagePopover();
            }, true);

            var messageNode = document.querySelector('[data-role="ads-watch-message-text"], .impactshop-identity-message-body');
            if (messageNode && window.MutationObserver) {
                var messageObserver = new MutationObserver(function() {
                    updateMessageUnreadState();
                    if (messageOpen) {
                        openMessagePopover();
                    }
                });
                messageObserver.observe(messageNode, { childList: true, characterData: true, subtree: true });
            }

            if (window.MutationObserver) {
                var pwaObserver = new MutationObserver(function() {
                    repositionDesktopPwaInstall();
                    if (messageOpen) {
                        updateMessagePopoverPosition();
                    }
                });
                pwaObserver.observe(document.body, { childList: true, subtree: true });
                setTimeout(function() { pwaObserver.disconnect(); }, 20000);
            }

            if (window.location.hash === '#ads-watch-message' && isChallengePath(window.location.pathname)) {
                setTimeout(function() {
                    openMessagePopover();
                    updateCurrent();
                }, 120);
            } else if (window.location.hash) {
                setTimeout(function() {
                    enhanceLegacyChallengeNavigation();
                    scrollToHash(window.location.hash, false);
                }, 120);
            }

            function repositionA11y() {
                if (window.getComputedStyle && window.getComputedStyle(bar).display === 'none') {
                    return false;
                }
                var icons = document.querySelectorAll('#aioa-floating-btn, .aioa-addon-floating-icon, .aioseo-accessibility-widget, #accessibility_settings_toggle');
                if (!icons.length) return false;
                var barRect = bar.getBoundingClientRect();
                var barTopFromBottom = window.innerHeight - barRect.top;
                var bottom = Math.max(Math.round(barTopFromBottom + 96), 120);
                icons.forEach(function(icon) {
                    icon.style.setProperty('bottom', bottom + 'px', 'important');
                });
                return true;
            }

            if (!repositionA11y() && window.MutationObserver) {
                var observer = new MutationObserver(function(mutations, obs) {
                    if (isMobile) {
                        iconifyInstallButton(document.querySelector('.impactshop-pwa-reopen'));
                    }
                    if (repositionA11y()) {
                        obs.disconnect();
                    }
                });
                observer.observe(document.body, { childList: true, subtree: true });
                setTimeout(function() { observer.disconnect(); }, 15000);
            }

            window.addEventListener('resize', function() {
                repositionDesktopPwaInstall();
                repositionA11y();
                updateMessagePopoverPosition();
                enhanceLegacyChallengeNavigation();
            });

            repositionDesktopPwaInstall();
            updateMessagePopoverPosition();
        })();
    </script>
    <?php
}

add_action('wp_footer', 'impactshop_action_bar_render', 20);
