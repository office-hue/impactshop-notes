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
    $video_current = str_starts_with($path, '/impact-challenge') || str_starts_with($path, '/impactad-2');
    $shop_current = str_starts_with($path, '/impactshop');

    $video_url = home_url('/impact-challenge/') . '#ads-watch-video';
    $tasks_url = home_url('/impact-challenge/') . '#impactshop-offerwall';
    $shop_url = home_url('/impactshop/');
    $donate_url = home_url('/impact-challenge/') . '#ads-watch-purchase';
    $account_url = home_url('/impact-challenge/') . '#impactshop-account';
    $ngo_url = home_url('/impact-challenge/') . '#ads-watch-ngo';
    $message_url = home_url('/impact-challenge/') . '#ads-watch-message';
    $stats_url = home_url('/impact-challenge/') . '#impactshop-ads-watch';

    $video_attr = $video_current ? ' aria-current="page" data-default-current="1"' : '';
    $shop_attr = $shop_current ? ' aria-current="page"' : '';
    $donate_attr = '';

    ?>
    <style>
        :root {
            --sharity-action-bar-height: 116px;
            --sharity-action-bar-height-tablet: 126px;
            --sharity-action-bar-height-desktop: 70px;
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

        .sharity-action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 10005;
            pointer-events: auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            background: #1a1a2e;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: calc(4px + env(safe-area-inset-bottom));
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
        }

        .sharity-action-bar a,
        .sharity-action-bar button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 4px;
            color: #fff;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            gap: 4px;
            white-space: nowrap;
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            pointer-events: auto;
            transition: opacity 0.15s ease, transform 0.15s ease;
            border: 0;
            background: transparent;
            margin: 0;
            font-family: inherit;
            appearance: none;
        }

        .sharity-action-bar a:active,
        .sharity-action-bar button:active {
            opacity: 0.7;
        }

        @media (prefers-reduced-motion: reduce) {
            .sharity-action-bar a,
            .sharity-action-bar button {
                transition: none;
            }
        }

        .sharity-action-bar a[aria-current="page"],
        .sharity-action-bar button[aria-current="page"] {
            color: #60a5fa;
            border-top: 2px solid currentColor;
        }

        .sharity-action-bar .bar-icon {
            position: relative;
            display: inline-flex;
            font-size: 20px;
        }

        .sharity-action-bar [data-bar="message"].has-unread {
            color: #fbbf24;
        }

        .sharity-action-bar [data-bar="message"].has-unread .bar-icon::after {
            content: "";
            position: absolute;
            top: -2px;
            right: -7px;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #fb7185;
            box-shadow: 0 0 0 2px rgba(26, 26, 46, 0.95);
        }

        .sharity-message-popover {
            position: fixed;
            left: 50%;
            bottom: 140px;
            transform: translateX(-50%);
            width: min(560px, calc(100vw - 24px));
            background: rgba(15, 23, 42, 0.97);
            color: #fff;
            border: 1px solid rgba(148, 163, 184, 0.35);
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
            color: #93c5fd;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .sharity-message-popover__body {
            margin: 0;
            font-size: 14px;
            line-height: 1.45;
            color: #f8fafc;
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
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.16));
            color: #1e3a8a;
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
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.18);
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
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 999px;
            background: radial-gradient(circle at 30% 30%, rgba(224, 242, 254, 0.98), rgba(191, 219, 254, 0.98));
            color: #1d4ed8;
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
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.22);
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
                left: 50%;
                right: auto;
                bottom: 14px;
                transform: translateX(-50%);
                width: min(760px, calc(100vw - 28px));
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 4px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 16px;
                padding: 6px;
                box-shadow: 0 8px 28px rgba(0, 0, 0, 0.32);
                background: rgba(26, 26, 46, 0.96);
                backdrop-filter: blur(8px);
            }

            .sharity-action-bar a,
            .sharity-action-bar button {
                font-size: 13px;
                padding: 10px 8px;
                gap: 6px;
                border-radius: 10px;
            }

            .sharity-action-bar .bar-icon {
                font-size: 22px;
            }

            .sharity-action-bar a[aria-current="page"],
            .sharity-action-bar button[aria-current="page"] {
                border-top: 0;
                background: rgba(96, 165, 250, 0.16);
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

            .sharity-action-bar {
                width: min(1220px, calc(100vw - 32px));
                grid-template-columns: repeat(8, minmax(0, 1fr));
            }

            .sharity-message-popover {
                bottom: 96px;
            }
        }

        @media (max-width: 768px) and (orientation: landscape) and (max-height: 500px) {
            .sharity-action-bar {
                display: none;
            }
        }
    </style>

    <nav class="sharity-action-bar" aria-label="Gyors műveletek">
        <a href="<?php echo esc_url($video_url); ?>" data-bar="video"<?php echo $video_attr; ?>>
            <span class="bar-icon">🎬</span>
            <span>Videó</span>
        </a>
        <a href="<?php echo esc_url($tasks_url); ?>" data-bar="tasks">
            <span class="bar-icon">🎁</span>
            <span>Feladatok</span>
        </a>
        <a href="<?php echo esc_url($shop_url); ?>" data-bar="shop"<?php echo $shop_attr; ?>>
            <span class="bar-icon">🛍️</span>
            <span>Impact Shop</span>
        </a>
        <a href="<?php echo esc_url($donate_url); ?>" data-bar="donate"<?php echo $donate_attr; ?>>
            <span class="bar-icon">❤️</span>
            <span>Adományozok</span>
        </a>
        <a href="<?php echo esc_url($account_url); ?>" data-bar="account">
            <span class="bar-icon">👤</span>
            <span>Profil</span>
        </a>
        <a href="<?php echo esc_url($ngo_url); ?>" data-bar="ngo">
            <span class="bar-icon">🏛️</span>
            <span>NGO</span>
        </a>
        <a href="<?php echo esc_url($message_url); ?>" data-bar="message">
            <span class="bar-icon">💬</span>
            <span>Üzenetek</span>
        </a>
        <a href="<?php echo esc_url($stats_url); ?>" data-bar="stats">
            <span class="bar-icon">📊</span>
            <span>Pontok</span>
        </a>
    </nav>

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
                var hash = window.location.hash || '';
                var currentPath = normalizePath(window.location.pathname);
                var isShopPage = currentPath.indexOf('/impactshop') === 0;
                var shop = bar.querySelector('[data-bar="shop"]');
                var video = bar.querySelector('[data-bar="video"]');
                var tasks = bar.querySelector('[data-bar="tasks"]');
                var donate = bar.querySelector('[data-bar="donate"]');
                var account = bar.querySelector('[data-bar="account"]');
                var ngo = bar.querySelector('[data-bar="ngo"]');
                var message = bar.querySelector('[data-bar="message"]');
                var stats = bar.querySelector('[data-bar="stats"]');

                [video, tasks, donate, account, ngo, message, stats].forEach(function(node) {
                    if (node) node.removeAttribute('aria-current');
                });

                if (shop) {
                    if (isShopPage) {
                        shop.setAttribute('aria-current', 'page');
                    } else {
                        shop.removeAttribute('aria-current');
                    }
                }

                if (!video || !tasks || !donate) return;
                if (hash === '#impactshop-offerwall') {
                    tasks.setAttribute('aria-current', 'page');
                } else if (hash === '#ads-watch-purchase') {
                    donate.setAttribute('aria-current', 'page');
                } else if (hash === '#impactshop-account') {
                    if (account) account.setAttribute('aria-current', 'page');
                } else if (hash === '#ads-watch-ngo') {
                    if (ngo) ngo.setAttribute('aria-current', 'page');
                } else if (hash === '#ads-watch-message') {
                    if (message) message.setAttribute('aria-current', 'page');
                } else if (hash === '#impactshop-ads-watch') {
                    if (stats) stats.setAttribute('aria-current', 'page');
                } else if (video.getAttribute('data-default-current') === '1') {
                    video.setAttribute('aria-current', 'page');
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
