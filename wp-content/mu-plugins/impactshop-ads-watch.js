/**
 * ImpactShop Ads Watch - Frontend JavaScript
 *
 * Google IMA SDK integration + NGO selection + tally display (pseudo_id based)
 *
 * @package ImpactShop
 * @since   2.1.0
 */

(function ($) {
    'use strict';

    const config = window.impactshopAdsWatch || {};
    const restUrl = config.restUrl || '/wp-json/impact/v1/ads-watch';
    const impactShopBaseUrl = config.impactShopBaseUrl || 'https://app.sharity.hu/impactshop/';
    const restNonce = config.restNonce || '';
    // No fallback test/sample ad tags - production tags come from config
    const fallbackAdTagBase = '';
    const i18n = config.i18n || {};
    const ngoCacheKey = 'impactshop_ads_watch_ngos_v1';
    const ngoCacheTtl = 24 * 60 * 60 * 1000;
    const autoBannerUrl = config.autoBannerUrl || restUrl.replace(/\/ads-watch\/?$/, '/auto-banner');

    let $messageCard = $();
    let $messageText = $();

    const state = {
        pseudoId: '',
        points: 0,
        level: 'basic',
        voteWeightRegular: 1,
        voteWeightSponsor: 5,
        donationMultiplier: 1,
        selectedNgo: null,
        todayViews: 0,
        availableVotes: 0,
        stats: {},
        achievements: [],
        isPlaying: false,
        adDisplayContainer: null,
        adsLoader: null,
        adsManager: null,
        currentAdType: 'regular',
        currentSponsorId: 0,
        pendingAdTagUrl: '',
        defaultAdTagUrl: config.adTagUrl || fallbackAdTagBase,
        fullTallyItems: [],
        adProgress: 0,
        progressTarget: 0,
        progressDisplay: 0,
        progressAnimating: false,
        imaReady: false,
        imaLoading: null,
        autoVote: false,
        unifiedDisplay: config.unifiedDisplay !== false,
        currentMode: 'regular',
        educationSessionToken: '',
        educationMaxIntervals: 0,
        educationIntervalsSent: 0,
        educationIntervalSeconds: 30,
        educationPointsPerInterval: 1,
        educationVotesPerInterval: 1,
        educationBonusPoints: 0,
        educationBonusVotes: 0,
        educationPresenceInterval: 0,
        educationPresenceTimeout: 0,
        educationSkipEnabled: false,
        educationDurationSeconds: 0,
        educationTimer: null,
        educationLastTick: 0,
        educationPresenceTimer: null,
        educationPresenceTimeoutTimer: null,
        educationPresenceActive: false,
        educationLastPresenceCheck: 0,
        educationPlayStartedAt: 0,
        educationAccumulatedSeconds: 0,
        educationProgress: 0,
        educationSkipTimeout: null,
        educationVisibilityPaused: false,
        educationBonusAwarded: false,
        educationPlaying: false,
        educationContent: null,
        educationWatchedSeconds: 0,
        educationInFlight: false,
        educationLastPlayerTime: 0,  // Track last player position for seek detection
        sponsorYoutubeTimer: null,
        youtubePlayer: null,
        youtubeReady: null,
        ctaLabel: '',
        ctaUrl: '',
        ctaMeta: null,
        currentCtaPoints: 0,
        ctaClicked: false,
        ctaClickedKeys: {},
        ctaBonusPoints: 0,
        ctaBonusVotes: 0,
        ctaUiDeferred: false,
        imaProgressFrameId: null,
        imaAdDuration: 0,
        imaClickThroughUrl: '',
        adLoadTimeout: null,
        adRequestPending: false,
        adRequestStartTime: 0,
        lastNgoSlugForBanner: '',
        currentAutoBanner: null,
        externalNavigationPending: false,
        externalNavigationVisibilityLost: false,
        externalNavigationStartedAt: 0,
        externalNavigationReloaded: false,
        externalNavigationSource: '',
        videoBalanceReady: false,
        videoBalancePointsDisplay: 0,
        videoBalanceVotesDisplay: 0,
        videoBalanceRafPoints: null,
        videoBalanceRafVotes: null,
        videoBalanceDeltaTimerPoints: null,
        videoBalanceDeltaTimerVotes: null
    };

    let countdownTimer = null;

    $(document).ready(function () {
        if ($('#impactshop-ads-watch').length === 0) {
            return;
        }

        $messageCard = $('[data-role=ads-watch-message]');
        $messageText = $('[data-role=ads-watch-message-text]');
        initEventListeners();
        initIdentityBridge();
        initTabs();
        fetchCampaignMessage();
        loadConfig();
        initChallengeCountdown();
        loadUserStatus();
        scheduleTallyLoad();
    });

    function initIdentityBridge() {
        state.pseudoId = getPseudoId();
        window.addEventListener('impactshop_identity_ready', function (event) {
            if (event && event.detail && event.detail.pseudo_id) {
                state.pseudoId = String(event.detail.pseudo_id || '').toLowerCase();
                loadUserStatus();
            }
        });
    }

    function scheduleTallyLoad() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(function () {
                loadTally();
            }, { timeout: 1500 });
            return;
        }
        setTimeout(function () {
            loadTally();
        }, 800);
    }

    function openCampaignMessagePanel() {
        if (typeof window.sharityOpenMessagePopover === 'function') {
            window.sharityOpenMessagePopover();
            return;
        }

        const actionBarMessage = document.querySelector('.sharity-action-bar [data-bar="message"]');
        if (actionBarMessage) {
            actionBarMessage.dispatchEvent(new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                view: window
            }));
            return;
        }

        if (window.location.hash !== '#ads-watch-message') {
            history.replaceState(null, '', '#ads-watch-message');
        }
        window.dispatchEvent(new Event('hashchange'));

        const messageSection = document.querySelector('#ads-watch-message, [data-role="ads-watch-message"]');
        if (messageSection && messageSection.scrollIntoView) {
            messageSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function initEventListeners() {
        $('#btn-watch-ad').on('click', startAdPlayback);
        $('#presence-confirm').on('click', confirmPresenceCheck);
        $('#btn-skip-education').on('click', skipEducationVideo);
        $('#btn-skip-video').on('click', skipCurrentVideo);
        $('#btn-resume-ad').on('click', resumeImaAd);
        // Note: IMA CTA overlay has pointer-events: none in CSS
        // Clicks pass through to ad-container, IMA SDK opens URL
        // Points are awarded via onAdClick callback (CLICK event listener)
        document.addEventListener('visibilitychange', handleVisibilityChange);

        // Safari bfcache fix: reload page when restored from back-forward cache
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                window.location.reload();
                return;
            }
            maybeRecoverFromExternalNavigation('pageshow');
        });
        window.addEventListener('focus', function () {
            window.setTimeout(function () {
                maybeRecoverFromExternalNavigation('focus');
            }, 120);
        });

        $('#btn-change-ngo').on('click', openNgoModal);
        $('#modal-close').on('click', closeNgoModal);
        $('#ngo-selection-modal').on('click', function (e) {
            if (e.target === this) closeNgoModal();
        });

        $('#btn-allocate-votes').on('click', allocateVotes);
        $('#vote-amount-input').on('input', updateVoteControls);
        $(document).on('click', '.btn-quick-vote', function () {
            const action = String($(this).data('vote-quick') || '');
            const available = Math.max(0, Number(state.availableVotes || 0));
            let nextValue = 0;
            if (action === 'all') {
                nextValue = available;
            } else if (action === 'half') {
                nextValue = Math.max(1, Math.floor(available / 2));
            } else {
                const add = Number(action || 0);
                nextValue = Math.max(0, Math.min(available, Number($('#vote-amount-input').val() || 0) + add));
            }
            $('#vote-amount-input').val(nextValue > 0 ? nextValue : '');
            updateVoteControls();
        });

        if ($messageCard.length) {
            $messageCard
                .attr('tabindex', '0')
                .attr('role', 'button')
                .attr('aria-label', 'Kampány üzenet megnyitása')
                .on('click', function (e) {
                    e.preventDefault();
                    openCampaignMessagePanel();
                })
                .on('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openCampaignMessagePanel();
                    }
                });
        }

        $('#ngo-search-input').on('input', debounce(searchNgos, 300));

        $('#btn-show-all-ngos').on('click', openFullTallyModal);
        $('#btn-show-more-ngos').on('click', function () {
            const $list = $('#tally-list');
            $list.attr('data-collapsed', 'false');
            $list.find('.tally-item-hidden').removeClass('tally-item-hidden').slideDown(200);
            $(this).slideUp(200);
        });
        $('#full-tally-close').on('click', closeFullTallyModal);
        $('#full-tally-modal').on('click', function (e) {
            if (e.target === this) closeFullTallyModal();
        });

        $('#full-tally-search').on('input', function () {
            renderFullTallyList($(this).val().trim());
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                closeNgoModal();
                closeFullTallyModal();
            }
        });

        $('.step-pill').on('click', function () {
            const target = $(this).data('scroll-target');
            if (!target) return;
            const el = document.querySelector(target);
            if (el && el.scrollIntoView) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        const $infoTrigger = $('[data-info-trigger]');
        const $infoPopover = $('[data-info-popover]');
        if ($infoTrigger.length && $infoPopover.length) {
            $infoTrigger.on('click', function () {
                const isHidden = $infoPopover.prop('hidden');
                $infoPopover.prop('hidden', !isHidden);
            });
            $infoTrigger.on('mouseenter', function () {
                $infoPopover.prop('hidden', false);
            });
            $infoTrigger.on('mouseleave', function () {
                if (!$infoPopover.is(':hover')) {
                    $infoPopover.prop('hidden', true);
                }
            });
            $infoPopover.on('mouseleave', function () {
                $infoPopover.prop('hidden', true);
            });
        }

        const $autoVote = $('#auto-vote-enabled');
        if ($autoVote.length) {
            try {
                state.autoVote = localStorage.getItem('impactshop_ads_watch_autovote') === '1';
            } catch (e) {
                state.autoVote = false;
            }
            $autoVote.prop('checked', state.autoVote);
            $autoVote.on('change', function () {
                state.autoVote = Boolean($(this).is(':checked'));
                try {
                    localStorage.setItem('impactshop_ads_watch_autovote', state.autoVote ? '1' : '0');
                } catch (e) {
                    // ignore
                }
            });
        }

        $('#ads-watch-cta-link').on('click', function (event) {
            if (!state.ctaMeta) return;
            // Only award bonus once per video
            if (state.ctaClicked) {
                console.log('[Sponsor CTA] Already clicked - skipping bonus');
                event.preventDefault();
                return;
            }
            state.ctaClicked = true;
            const fallbackReward = getCtaFallbackReward(state.ctaMeta.points || 0);
            // Track CTA click
            sendCtaTracking({
                content_type: state.ctaMeta.content_type,
                content_id: state.ctaMeta.content_id,
                cta_url: state.ctaUrl || '',
                shop_slug: '',
                category: '',
                price_range: '',
                points: state.ctaMeta.points || 0,
                dedupe_key: state.ctaMeta.dedupe_key || ''
            }, {
                fallbackReward: fallbackReward
            });

            // Let native <a target="_blank"> handle navigation instead of window.open
            // to avoid Safari blank-page bug
            const href = String($(this).attr('href') || '').trim();
            if (!href || href === '#') {
                event.preventDefault();
                clearExternalNavigationState();
            } else {
                markExternalNavigation('sponsor_cta');
            }
        });

        $(document).on('click', '[data-role=auto-banner-link]', function (event) {
            const $link = $(this);
            const payload = $link.closest('[data-role=auto-banner]').data('cta-payload');
            console.log('[AutoBanner][DEBUG] CLICK handler fired', {
                href: $link.attr('href'),
                target: $link.attr('target'),
                tagName: this.tagName,
                dataRole: $link.attr('data-role'),
                hasPayload: !!payload,
                payloadShopSlug: payload && payload.shop_slug,
                payloadRawUrl: payload && payload.raw_url,
                payloadCtaUrl: payload && payload.cta_url,
                selectedNgo: state.selectedNgo,
                ctaClicked: state.ctaClicked,
                filloutFormId: config.filloutFormId
            });

            // ── Update href with latest NGO slug and let native <a target="_blank"> handle navigation ──
            // Using native link behaviour instead of window.open to avoid Safari blank-page bug
            const rawUrl = String($link.attr('href') || (payload && payload.cta_url) || '').trim();
            if (rawUrl && rawUrl !== '#') {
                const ngoSlug = state.selectedNgo ? state.selectedNgo.slug : '';
                const shopSlug = (payload && payload.shop_slug) || '';
                const rawTarget = (payload && payload.raw_url) || rawUrl;
                const clickUrl = transformBannerUrl(rawTarget, shopSlug, ngoSlug);
                console.log('[AutoBanner][DEBUG] CLICK resolved URLs', {
                    rawUrl: rawUrl,
                    rawTarget: rawTarget,
                    shopSlug: shopSlug,
                    ngoSlug: ngoSlug,
                    clickUrl: clickUrl,
                    isFillout: clickUrl && clickUrl.includes('fillout.com')
                });
                // Set the correct URL on the link — browser will open it in new tab via target="_blank"
                $link.attr('href', clickUrl);
                markExternalNavigation('auto_banner');
            } else {
                // No valid URL — prevent scroll to #
                event.preventDefault();
                clearExternalNavigationState();
            }

            // ── Award bonus (once per banner rotation) ──
            if (payload && !state.ctaClicked) {
                state.ctaClicked = true;
                sendCtaTracking(payload, {
                    fallbackReward: getCtaFallbackReward(payload.points || 0)
                });
            }
        });
    }

    function initTabs() {
    const $tabs = $('[data-role=ads-watch-tabs]');
        const $tabButtons = $('[data-role=ads-watch-tab]');
        const $main = $('[data-role=ads-watch-main]');
        const $offerwall = $('#impactshop-offerwall');
        const $subtitle = $('#ads-watch-subtitle-text');
        const $infoPrimary = $('#ads-watch-info-primary');

        if (!$tabs.length || !$tabButtons.length || !$main.length) {
            return;
        }

        if (!$offerwall.length) {
            $tabs.prop('hidden', true);
            return;
        }

        $tabs.prop('hidden', false);

        const scrollToTarget = function (target) {
            const el = document.querySelector(target);
            if (el && el.scrollIntoView) {
                setTimeout(function () {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 60);
            }
        };
        const setActiveTab = function (target) {
            $tabButtons.removeClass('is-active');
            const $targetBtn = $tabButtons.filter('[data-target="' + target + '"]');
            if ($targetBtn.length) {
                $targetBtn.addClass('is-active');
            }
        };

        const headerCopy = {
            video: {
                subtitle: '🎬 Nézz videókat – minden megtekintés után pontot és szavazatot kapsz.',
                info: '🎬 <strong>Nézz videókat</strong> – minden megtekintés után pontot és szavazatot kapsz'
            },
            offerwall: {
                subtitle: '🧩 Végezz feladatokat – minden teljesítés után pontot és szavazatot kapsz.',
                info: '🧩 <strong>Végezz feladatokat</strong> – minden teljesítés után pontot és szavazatot kapsz'
            }
        };

        const applyHeaderCopy = function (mode) {
            const copy = headerCopy[mode] || headerCopy.video;
            if ($subtitle.length) {
                $subtitle.text(copy.subtitle);
            }
            if ($infoPrimary.length) {
                $infoPrimary.html(copy.info);
            }
        };

        const showVideo = function (skipScroll) {
            $main.prop('hidden', false);
            $offerwall.prop('hidden', true);
            $offerwall.css('display', 'none');
            applyHeaderCopy('video');
            setActiveTab('video');
            if (!skipScroll) {
                scrollToTarget('#ads-watch-video');
            }
        };

        const showOfferwall = function (skipScroll) {
            $main.prop('hidden', true);
            $offerwall.prop('hidden', false);
            $offerwall.css('display', '');
            applyHeaderCopy('offerwall');
            setActiveTab('offerwall');
            if (!skipScroll) {
                scrollToTarget('#impactshop-offerwall');
            }
        };

        const handleHash = function () {
            const hash = String(window.location.hash || '');
            if (hash === '#impactshop-offerwall') {
                showOfferwall(true);
                scrollToTarget('#impactshop-offerwall');
                return;
            }
            if (hash === '#ads-watch-purchase') {
                showVideo(true);
                scrollToTarget('#ads-watch-purchase');
                return;
            }
            showVideo(true);
            if (hash === '#ads-watch-video') {
                scrollToTarget('#ads-watch-video');
            }
        };

        handleHash();
        window.addEventListener('hashchange', handleHash);
        $tabButtons.on('click', function () {
            const target = String($(this).data('target') || '');
            $tabButtons.removeClass('is-active');
            $(this).addClass('is-active');
            if (target === 'offerwall') {
                showOfferwall();
            } else {
                showVideo();
            }
        });
    }

    function apiRequest(endpoint, method = 'GET', data = null, opts = {}) {
        const deferred = $.Deferred();
        const maxRetries = Number.isInteger(opts.retries) ? opts.retries : 2;
        const baseDelay = Number.isInteger(opts.retryDelay) ? opts.retryDelay : 400;
        const notifyOnFail = opts.notifyOnFail !== false;

        const attempt = function (count) {
            const options = {
                url: `${restUrl}/${endpoint}`,
                method: method,
                dataType: 'json',
                timeout: 12000,
                headers: {},
                xhrFields: { withCredentials: true },
            };

            if (data && method !== 'GET') {
                options.data = JSON.stringify(data);
                options.contentType = 'application/json';
                if (restNonce) {
                    options.headers['X-WP-Nonce'] = restNonce;
                }
            }

            $.ajax(options)
                .done(function (response) {
                    deferred.resolve(response);
                })
                .fail(function (xhr) {
                    const isRetryable = (xhr && (xhr.status === 0 || xhr.status >= 500));
                    if (count < maxRetries && isRetryable) {
                        const delay = baseDelay * Math.pow(2, count);
                        setTimeout(function () {
                            attempt(count + 1);
                        }, delay);
                        return;
                    }
                    if (notifyOnFail && xhr && xhr.status === 0) {
                        showNotification('Hálózati hiba. Ellenőrizd a kapcsolatot.', 'error');
                    }
                    deferred.reject(xhr);
                });
        };

        attempt(0);
        return deferred.promise();
    }

    function loadConfig() {
        apiRequest('config?ts=' + Date.now())
            .done(function (response) {
                if (response && response.ad_tag_url) {
                    state.defaultAdTagUrl = response.ad_tag_url;
                }
                if (response && response.education_interval_seconds) {
                    state.educationIntervalSeconds = Number(response.education_interval_seconds || 30);
                }
                if (response && response.education_points_per_interval) {
                    state.educationPointsPerInterval = Number(response.education_points_per_interval || 1);
                }
                if (response && response.education_votes_per_interval) {
                    state.educationVotesPerInterval = Number(response.education_votes_per_interval || 1);
                }
            })
            .fail(function () {
                // ignore
            });
    }

    function loadUserStatus() {
        console.log('[AdsWatch] loadUserStatus called, pseudoId:', state.pseudoId);
        apiRequest('status?ts=' + Date.now())
            .done(function (response) {
                console.log('[AdsWatch] status response:', response);
                if (!response || response.has_identity === false) {
                    console.log('[AdsWatch] No identity found');
                    state.pseudoId = '';
                    state.points = 0;
                    state.level = 'basic';
                    state.selectedNgo = null;
                    state.availableVotes = 0;
                    updateStatusDisplay();
                    updateNgoDisplay();
                    updateWatchButton();
                    updateVoteControls();
                    return;
                }

                state.pseudoId = response.pseudo_id || state.pseudoId;
                state.points = response.points || 0;
                state.level = normalizeLevelValue(response.level || response.sharity_level || response.user_level) || 'basic';
                state.voteWeightRegular = response.vote_weight_regular || 1;
                state.voteWeightSponsor = response.vote_weight_sponsor || 5;
                state.donationMultiplier = Number(response.donation_multiplier || 1);
                state.selectedNgo = response.selected_ngo || null;
                state.todayViews = response.today_views || 0;
                state.availableVotes = response.available_votes || 0;
                state.stats = response.stats || {};
                state.achievements = response.achievements || [];

                updateStatusDisplay();
                updateNgoDisplay();
                updateWatchButton();
                updateVoteControls();
                refreshAutoBannerLink('status-load');
            })
            .fail(function (xhr) {
                console.error('Failed to load user status:', xhr);
                loadUserStatusFallback(xhr);
            });
    }

    function loadUserStatusFallback(xhr) {
        const fallbackUrl = getFallbackStatusUrl();
        if (!fallbackUrl) {
            return;
        }

        const queryParts = [`ts=${Date.now()}`];
        if (state.pseudoId) {
            queryParts.push(`pseudo_id=${encodeURIComponent(state.pseudoId)}`);
        }

        $.ajax({
            url: `${fallbackUrl}?${queryParts.join('&')}`,
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            xhrFields: { withCredentials: true },
        })
            .done(function (response) {
                const fallback = normalizeFallbackStatus(response);
                if (!fallback) {
                    return;
                }
                state.points = fallback.points;
                state.level = fallback.level;
                if (Number.isFinite(fallback.availableVotes)) {
                    state.availableVotes = fallback.availableVotes;
                }
                if (fallback.streakDays !== null) {
                    state.stats = Object.assign({}, state.stats, { streak_days: fallback.streakDays });
                }
                updateStatusDisplay();
                updateWatchButton();
                updateVoteControls();
                showNotification('A státusz frissítése sikertelen, részleges adatok láthatók.', 'warning');
            })
            .fail(function (fallbackXhr) {
                console.error('Failed to load fallback status:', fallbackXhr);
                if (xhr && xhr.status === 0) {
                    showNotification('Nem sikerült frissíteni az adatokat. Próbáld később.', 'error');
                }
            });
    }

    function normalizeFallbackStatus(response) {
        if (!response || typeof response !== 'object') {
            return null;
        }
        const points = Number(response.points || response.points_total || response.total_points || 0);
        const level = normalizeLevelValue(response.level || response.sharity_level || response.user_level) || 'basic';
        const availableVotes = response.available_votes !== undefined
            ? Number(response.available_votes || 0)
            : (response.votes !== undefined ? Number(response.votes || 0) : null);
        const streakDays = response.streak_days !== undefined
            ? Number(response.streak_days || 0)
            : (response.stats && response.stats.streak_days !== undefined
                ? Number(response.stats.streak_days || 0)
                : null);

        return {
            points: Number.isFinite(points) ? points : 0,
            level: level || 'basic',
            availableVotes: Number.isFinite(availableVotes) ? availableVotes : null,
            streakDays: Number.isFinite(streakDays) ? streakDays : null,
        };
    }

    function normalizeLevelValue(value) {
        if (!value) {
            return '';
        }
        if (Array.isArray(value)) {
            if (value.length === 0) {
                return '';
            }
            return normalizeLevelValue(value[0]);
        }
        if (typeof value === 'object') {
            const candidate = value.key || value.slug || value.code || value.name || value.label || value.level || value.value || '';
            if (!candidate) {
                return '';
            }
            return normalizeLevelValue(candidate);
        }
        const text = String(value);
        if (text.toLowerCase() === '[object object]') {
            return '';
        }
        return text.toLowerCase();
    }

    function getFallbackStatusUrl() {
        const base = String(restUrl || '').replace(/\/impact\/v1\/ads-watch\/?$/, '');
        if (base) {
            return `${base}/impact/v1/ads-watch/status`;
        }
        return '/wp-json/impact/v1/ads-watch/status';
    }

    function loadTally(limit = 10) {
        apiRequest(`tally?limit=${limit}`)
            .done(function (response) {
                renderTally(response);
            })
            .fail(function (xhr) {
                console.error('Failed to load tally:', xhr);
                $('#tally-list').html('<div class="tally-error">Nem sikerült betölteni az eredményeket.</div>');
            });
    }

    function loadNgos(search = '') {
        const cached = getNgoCache();
        if (cached && cached.length) {
            if (search) {
                const q = search.toLowerCase();
                renderNgoList(cached.filter(function (ngo) {
                    return String(ngo.name || '').toLowerCase().includes(q);
                }));
                return;
            }
            renderNgoList(cached);
            return;
        }

        const endpoint = search ? `ngos?search=${encodeURIComponent(search)}&limit=5000` : 'ngos?limit=5000';

        apiRequest(endpoint)
            .done(function (response) {
                const list = response.ngos || [];
                if (!search) {
                    setNgoCache(list);
                }
                renderNgoList(list);
            })
            .fail(function (xhr) {
                console.error('Failed to load NGOs:', xhr);
                $('#ngo-list').html('<div class="ngo-list-error">Nem sikerült betölteni az NGO-kat.</div>');
            });
    }

    function setUserNgo(ngoSlug) {
        apiRequest('set-ngo', 'POST', { ngo_slug: ngoSlug, pseudo_id: state.pseudoId })
            .done(function (response) {
                if (response.success) {
                    state.selectedNgo = response.ngo;
                    updateNgoDisplay();
                    updateWatchButton();
                    refreshAutoBannerLink('ngo-set');
                    closeNgoModal();
                    showNotification('NGO sikeresen kiválasztva!', 'success');
                    trackEvent('ads_watch_ngo_select', {
                        ngo_slug: response.ngo && response.ngo.slug ? response.ngo.slug : ngoSlug,
                        ngo_name: response.ngo && response.ngo.name ? response.ngo.name : ''
                    });
                } else {
                    showNotification(response.message || 'Hiba történt', 'error');
                }
            })
            .fail(function (xhr) {
                console.error('Failed to set NGO:', xhr);
                showNotification('Nem sikerült menteni a választást.', 'error');
            });
    }

    function updateStatusDisplay() {
        const safePoints = Math.max(0, Math.round(Number(state.points || 0)));
        const safeVotes = Math.max(0, Math.round(Number(state.availableVotes || 0)));

        $('#user-points-display').text(formatNumber(safePoints));
        const safeLevel = sanitizeLevelLabel(state.level);
        $('#user-level-display').text(capitalizeFirst(safeLevel));
        $('#vote-weight-display').text(`×${state.voteWeightRegular}`);
        const multiplier = Number(state.donationMultiplier || 1);
        const bonusPct = Math.max(0, Math.round((multiplier - 1) * 100));
        $('#donation-multiplier-display').text(`+${bonusPct}%`);
        $('#available-votes-display').text(formatNumber(safeVotes));
        $('#available-votes-inline').text(formatNumber(safeVotes));
        const streakDays = Number(state.stats && state.stats.streak_days ? state.stats.streak_days : 0);
        let streakMultiplier = 1.0;
        if (streakDays >= 30) {
          streakMultiplier = 1.30;
        } else if (streakDays >= 14) {
          streakMultiplier = 1.20;
        } else if (streakDays >= 7) {
          streakMultiplier = 1.10;
        }
        const streakLabel = streakDays > 0 ? `${streakDays} nap` : '0 nap';
        $('#streak-display').text(`${streakLabel} (x${streakMultiplier.toFixed(2)})`);
        syncVideoBalanceCounters(safePoints, safeVotes);
    }

    function syncVideoBalanceCounters(points, votes) {
        const $points = $('#video-balance-points');
        const $votes = $('#video-balance-votes');
        if (!$points.length || !$votes.length) {
            return;
        }

        const nextPoints = Math.max(0, Math.round(Number(points || 0)));
        const nextVotes = Math.max(0, Math.round(Number(votes || 0)));

        if (!state.videoBalanceReady) {
            state.videoBalanceReady = true;
            state.videoBalancePointsDisplay = 0;
            state.videoBalanceVotesDisplay = 0;
            $points.text(formatNumber(0));
            $votes.text(formatNumber(0));
            animateVideoBalanceValue('points', 0, nextPoints, { silentDelta: true });
            animateVideoBalanceValue('votes', 0, nextVotes, { silentDelta: true });
            return;
        }

        animateVideoBalanceValue('points', state.videoBalancePointsDisplay, nextPoints);
        animateVideoBalanceValue('votes', state.videoBalanceVotesDisplay, nextVotes);
    }

    function stopVideoBalanceAnimation(kind) {
        const key = kind === 'points' ? 'videoBalanceRafPoints' : 'videoBalanceRafVotes';
        if (state[key]) {
            cancelAnimationFrame(state[key]);
            state[key] = null;
        }
    }

    function animateVideoBalanceValue(kind, fromValue, toValue, opts) {
        const $target = kind === 'points' ? $('#video-balance-points') : $('#video-balance-votes');
        const $item = kind === 'points'
            ? $('.live-balance-item[data-type="points"]')
            : $('.live-balance-item[data-type="votes"]');

        if (!$target.length) {
            return;
        }

        const start = Math.max(0, Math.round(Number(fromValue || 0)));
        const end = Math.max(0, Math.round(Number(toValue || 0)));
        const delta = end - start;
        const silentDelta = !!(opts && opts.silentDelta);

        if (kind === 'points') {
            state.videoBalancePointsDisplay = end;
        } else {
            state.videoBalanceVotesDisplay = end;
        }

        if (delta > 0 && !silentDelta) {
            showVideoBalanceDelta(kind, delta);
            if ($item.length) {
                $item.addClass('is-updated');
                setTimeout(function () {
                    $item.removeClass('is-updated');
                }, 700);
            }
        }

        if (start === end) {
            $target.text(formatNumber(end));
            return;
        }

        stopVideoBalanceAnimation(kind);

        const duration = Math.min(1200, Math.max(340, Math.abs(delta) * 28));
        const startTs = performance.now();
        const easeOut = function (t) {
            return 1 - Math.pow(1 - t, 3);
        };

        const step = function (now) {
            const progress = Math.min(1, (now - startTs) / duration);
            const eased = easeOut(progress);
            const value = Math.round(start + (delta * eased));
            $target.text(formatNumber(value));
            if (progress < 1) {
                const raf = requestAnimationFrame(step);
                if (kind === 'points') {
                    state.videoBalanceRafPoints = raf;
                } else {
                    state.videoBalanceRafVotes = raf;
                }
            } else {
                if (kind === 'points') {
                    state.videoBalanceRafPoints = null;
                } else {
                    state.videoBalanceRafVotes = null;
                }
                $target.text(formatNumber(end));
            }
        };

        const raf = requestAnimationFrame(step);
        if (kind === 'points') {
            state.videoBalanceRafPoints = raf;
        } else {
            state.videoBalanceRafVotes = raf;
        }
    }

    function showVideoBalanceDelta(kind, amount) {
        const $delta = kind === 'points'
            ? $('#video-balance-points-delta')
            : $('#video-balance-votes-delta');

        if (!$delta.length || amount <= 0) {
            return;
        }

        const timerKey = kind === 'points'
            ? 'videoBalanceDeltaTimerPoints'
            : 'videoBalanceDeltaTimerVotes';

        if (state[timerKey]) {
            clearTimeout(state[timerKey]);
            state[timerKey] = null;
        }

        $delta.text('+' + formatNumber(amount));
        $delta.addClass('is-visible');

        state[timerKey] = setTimeout(function () {
            $delta.removeClass('is-visible');
            state[timerKey] = null;
        }, 950);
    }

    function sanitizeLevelLabel(value) {
        const normalized = normalizeLevelValue(value);
        if (!normalized) {
            return 'basic';
        }
        if (normalized.toLowerCase() === '[object object]') {
            return 'basic';
        }
        return normalized;
    }

    function initChallengeCountdown() {
        const $display = $('#impact-challenge-countdown-display');
        if (!$display.length) {
            return;
        }

        const quarter = config.quarter || {};
        const startTs = Number(quarter.startTs || 0);
        const endTs = Number(quarter.endTs || 0);
        const baseServerTs = Number(quarter.nowTs || 0);
        const baseClientTs = Math.floor(Date.now() / 1000);

        if (!startTs || !endTs) {
            $display.text('-');
            return;
        }

        function getNowTs() {
            if (baseServerTs > 0) {
                return baseServerTs + Math.floor(Date.now() / 1000 - baseClientTs);
            }
            return Math.floor(Date.now() / 1000);
        }

        function render() {
            const nowTs = getNowTs();
            if (nowTs < startTs) {
                $display.text(`Indulásig: ${formatCountdown(startTs - nowTs)}`);
                return;
            }
            if (nowTs <= endTs) {
                $display.text(`Lezárásig: ${formatCountdown(endTs - nowTs)}`);
                return;
            }
            $display.text('Lezárult');
        }

        render();
        if (countdownTimer) {
            clearInterval(countdownTimer);
        }
        countdownTimer = setInterval(render, 30000);
    }

    function notifyPointsUpdated() {
        if (!state.pseudoId) {
            return;
        }
        window.dispatchEvent(new CustomEvent('sharity_points_updated', {
            detail: {
                pseudo_id: state.pseudoId,
                points: state.points,
                available_votes: state.availableVotes
            }
        }));
    }

    function updateNgoDisplay() {
        const $display = $('#selected-ngo-display');
        // Adományozok button always points to fixed URL - no dynamic changes

        if (!state.pseudoId) {
            $display.removeClass('has-ngo').html(`<span class="no-ngo-text">${escapeHtml(i18n.noIdentity || 'Azonosító szükséges a pontgyűjtéshez.')}</span>`);
            updateImpactShopLink();
            return;
        }

        if (state.selectedNgo) {
            $display.addClass('has-ngo').html(`
                ${state.selectedNgo.logo ? `<img src="${state.selectedNgo.logo}" alt="" class="ngo-logo">` : '<div class="ngo-logo"></div>'}
                <span class="ngo-name">${escapeHtml(state.selectedNgo.name || state.selectedNgo.slug || '')}</span>
            `);
        } else {
            $display.removeClass('has-ngo').html('<span class="no-ngo-text">Még nem választottál NGO-t</span>');
        }
        const slug = state.selectedNgo && state.selectedNgo.slug ? String(state.selectedNgo.slug || '') : '';
        if (slug !== state.lastNgoSlugForBanner) {
            state.lastNgoSlugForBanner = slug;
            updateAutoBannerLink();
        }
        updateImpactShopLink();
    }

    function updateImpactShopLink() {
        const $btn = $('#ads-watch-impactshop-btn');
        if (!$btn.length) {
            return;
        }
        const base = String(impactShopBaseUrl || '').trim() || 'https://app.sharity.hu/impactshop/';
        if (state.selectedNgo && state.selectedNgo.slug) {
            const slug = encodeURIComponent(String(state.selectedNgo.slug));
            $btn.attr('href', `${base}?d1=${slug}&ngo=${slug}&src=ngo-card`);
        } else {
            $btn.attr('href', base);
        }
    }

    function updateWatchButton() {
        const $btn = $('#btn-watch-ad');
        const hasNgo = state.selectedNgo !== null;
        const hasIdentity = Boolean(state.pseudoId);

        $btn.prop('disabled', state.isPlaying);

        if (!hasIdentity) {
            $btn.find('.btn-text').text(i18n.noIdentity || 'Azonosító szükséges');
        } else {
            $btn.find('.btn-text').text('Reklám megtekintése');
        }
    }

    function updateVoteControls() {
        const hasIdentity = Boolean(state.pseudoId);
        const hasNgo = Boolean(state.selectedNgo);
        const available = Number(state.availableVotes || 0);
        const requested = Number($('#vote-amount-input').val() || 0);

        const canVote = hasIdentity && hasNgo && available > 0;
        $('#btn-allocate-votes').prop('disabled', !canVote || requested <= 0 || requested > available);
    }

    function allocateVotes() {
        const requested = Number($('#vote-amount-input').val() || 0);
        if (!state.pseudoId || !state.selectedNgo || requested <= 0) {
            showNotification('Add meg a szavazatszámot és válassz NGO-t.', 'error');
            return;
        }
        if (requested > state.availableVotes) {
            showNotification('Nincs ennyi szavazatod.', 'error');
            return;
        }

        apiRequest('allocate', 'POST', {
            pseudo_id: state.pseudoId,
            ngo_slug: state.selectedNgo.slug,
            votes: requested
        }).done(function (response) {
            if (!response || response.success === false) {
                showNotification(response && response.message ? response.message : 'Nem sikerült a szavazás.', 'error');
                return;
            }
            state.availableVotes = response.remaining_votes || 0;
            $('#vote-amount-input').val('');
            updateStatusDisplay();
            updateVoteControls();
            loadTally();
            showNotification('Szavazat rögzítve!', 'success');
            trackEvent('ads_watch_vote_cast', {
                ngo_slug: state.selectedNgo ? state.selectedNgo.slug : '',
                votes: requested,
                weighted_votes: response.weighted_votes || 0
            });
        }).fail(function () {
            showNotification('Nem sikerült a szavazás.', 'error');
        });
    }

    function autoAllocateVotes(votes) {
        const requested = Math.max(0, Number(votes || 0));
        if (!state.pseudoId || !state.selectedNgo || requested <= 0) {
            return;
        }

        apiRequest('allocate', 'POST', {
            pseudo_id: state.pseudoId,
            ngo_slug: state.selectedNgo.slug,
            votes: requested
        }, { notifyOnFail: false })
            .done(function (response) {
                if (!response || response.success === false) {
                    return;
                }
                state.availableVotes = response.remaining_votes || 0;
                updateStatusDisplay();
                updateVoteControls();
                loadTally();
            });
    }

    function renderTally(data) {
        const $list = $('#tally-list');
        const $showMoreBtn = $('#btn-show-more-ngos');
        const tally = data.tally || [];
        const isCollapsed = $list.attr('data-collapsed') === 'true';
        const VISIBLE_COUNT = 3;

        const totalVotes = data.total_votes || 0;
        
        $('#total-votes-display').text(formatNumber(totalVotes));
        
        // Élő aktivitás: max total_votes * 3%, random variáció ±20%
        const maxLiveActivity = Math.max(0, Math.floor(totalVotes * 0.03));
        const liveActivityBase = maxLiveActivity > 0
            ? Math.min(maxLiveActivity, Math.floor(Math.random() * maxLiveActivity * 0.4) + Math.floor(maxLiveActivity * 0.6))
            : 0;
        $('#live-activity-value').text(`${formatNumber(liveActivityBase)} szavazat`);
        
        // Nyeremény esély: egyelőre statikus üzenet
        $('#chance-value').text('hamarosan');

        if (tally.length === 0) {
            $list.html('<div class="tally-empty">Még nincs szavazat.</div>');
            $showMoreBtn.hide();
            return;
        }

        let html = '';
        tally.forEach(function (item, index) {
            const hiddenClass = isCollapsed && index >= VISIBLE_COUNT ? 'tally-item-hidden' : '';
            html += `
                <div class="tally-item rank-${item.rank} ${hiddenClass}" style="${hiddenClass ? 'display:none' : ''}">
                    <div class="tally-rank">#${item.rank}</div>
                    <img src="${item.ngo_logo || '/wp-content/uploads/impactshop/ngo-card-default.jpg'}" alt="" class="tally-logo">
                    <div class="tally-name">${escapeHtml(item.ngo_name)}</div>
                    <div class="tally-votes">${formatNumber(item.votes)} szavazat<br><small>${item.percentage}%</small></div>
                    <div class="tally-amount">${formatNumber(item.amount)} Ft</div>
                </div>
            `;
        });

        $list.html(html);

        // Show/hide "Show more" button
        if (tally.length > VISIBLE_COUNT && isCollapsed) {
            $showMoreBtn.text(`Mutass még ${tally.length - VISIBLE_COUNT} NGO-t ▼`).show();
        } else {
            $showMoreBtn.hide();
        }
    }

    function renderNgoList(ngos) {
        const $list = $('#ngo-list');

        if (ngos.length === 0) {
            $list.html('<div class="ngo-list-empty">Nincs találat.</div>');
            return;
        }

        let html = '';
        ngos.forEach(function (ngo) {
            const isSelected = state.selectedNgo && state.selectedNgo.slug === ngo.slug;
            html += `
                <div class="ngo-list-item ${isSelected ? 'selected' : ''}" data-ngo-slug="${ngo.slug}">
                    <img src="${ngo.logo || '/wp-content/uploads/impactshop/ngo-card-default.jpg'}" alt="" class="ngo-logo">
                    <span class="ngo-name">${escapeHtml(ngo.name)}</span>
                    ${isSelected ? '<span class="selected-badge">✓ Kiválasztva</span>' : ''}
                </div>
            `;
        });

        $list.html(html);

        $list.find('.ngo-list-item').on('click', function () {
            const ngoSlug = $(this).data('ngo-slug');
            if (ngoSlug) {
                setUserNgo(ngoSlug);
            }
        });
    }

    function openNgoModal() {
        $('#ngo-selection-modal').fadeIn(200);
        $('#ngo-search-input').val('').focus();
        loadNgos();
    }

    function closeNgoModal() {
        $('#ngo-selection-modal').fadeOut(200);
    }

    function openFullTallyModal() {
        $('#full-tally-modal').fadeIn(200);

        apiRequest('tally?limit=1000')
            .done(function (response) {
                state.fullTallyItems = response.tally || [];
                $('#full-tally-search').val('');
                renderFullTallyList('');
            })
            .fail(function () {
                $('#full-tally-list').html('<div class="tally-error">Nem sikerült betölteni.</div>');
            });
    }

    function closeFullTallyModal() {
        $('#full-tally-modal').fadeOut(200);
    }

    function renderFullTallyList(query) {
        const list = Array.isArray(state.fullTallyItems) ? state.fullTallyItems : [];
        const q = (query || '').toLowerCase();
        const filtered = q
            ? list.filter(function (item) {
                return String(item.ngo_name || '').toLowerCase().includes(q);
            })
            : list;

        if (!filtered.length) {
            $('#full-tally-list').html('<div class="tally-empty">Nincs találat.</div>');
            return;
        }

        let html = '';
        filtered.forEach(function (item) {
            html += `
                <div class="tally-item rank-${item.rank}">
                    <div class="tally-rank">#${item.rank}</div>
                    <img src="${item.ngo_logo || '/wp-content/uploads/impactshop/ngo-card-default.jpg'}" alt="" class="tally-logo">
                    <div class="tally-name">${escapeHtml(item.ngo_name)}</div>
                    <div class="tally-votes">${formatNumber(item.votes)}<br><small>${item.percentage}%</small></div>
                    <div class="tally-amount">${formatNumber(item.amount)} Ft</div>
                </div>
            `;
        });

        $('#full-tally-list').html(html);
    }

    function searchNgos() {
        const query = $('#ngo-search-input').val().trim();
        loadNgos(query);
    }

    function loadImaSdk() {
        if (window.google && window.google.ima) {
            return $.Deferred().resolve().promise();
        }
        if (state.imaLoading) {
            return state.imaLoading;
        }
        const existing = document.querySelector('script[src*="ima3.js"]');
        if (existing) {
            const deferred = $.Deferred();
            const checkReady = setInterval(function () {
                if (window.google && window.google.ima) {
                    clearInterval(checkReady);
                    deferred.resolve();
                }
            }, 100);
            state.imaLoading = deferred.promise();
            return state.imaLoading;
        }
        const deferred = $.Deferred();
        const script = document.createElement('script');
        script.src = 'https://imasdk.googleapis.com/js/sdkloader/ima3.js';
        script.async = true;
        script.onload = function () { deferred.resolve(); };
        script.onerror = function () { deferred.reject(); };
        document.head.appendChild(script);
        state.imaLoading = deferred.promise();
        return state.imaLoading;
    }

    function initGoogleIMA() {
        const deferred = $.Deferred();
        loadImaSdk()
            .done(function () {
                if (!window.google || !window.google.ima) {
                    state.imaReady = false;
                    deferred.resolve(false);
                    return;
                }

                const adContainer = document.getElementById('ad-container');
                const videoElement = document.getElementById('content-video');

                if (!adContainer || !videoElement) {
                    console.error('Required elements not found');
                    state.imaReady = false;
                    deferred.resolve(false);
                    return;
                }

                state.adDisplayContainer = new google.ima.AdDisplayContainer(adContainer, videoElement);
                state.adsLoader = new google.ima.AdsLoader(state.adDisplayContainer);

                state.adsLoader.addEventListener(
                    google.ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED,
                    onAdsManagerLoaded,
                    false
                );

                state.adsLoader.addEventListener(
                    google.ima.AdErrorEvent.Type.AD_ERROR,
                    onAdError,
                    false
                );
                state.imaReady = true;
                deferred.resolve(true);
            })
            .fail(function () {
                state.imaReady = false;
                deferred.resolve(false);
            });

        return deferred.promise();
    }

    function startAdPlayback() {
        if (!state.pseudoId) {
            showNotification(i18n.noIdentity || 'Azonosító szükséges.', 'warning');
            return;
        }

        if (state.isPlaying) {
            return;
        }

        const proceed = function () {
            state.isPlaying = true;
            state.adProgress = 0;
            state.ctaClicked = false; // Reset CTA clicked flag for new ad
            updateWatchButton();
            showLoading(true);

            apiRequest('next?ts=' + Date.now())
            .done(function (response) {
                console.log('[Rotation] /next response:', {
                    content_type: response?.content_type,
                    mode: response?.mode,
                    has_sponsor: !!response?.sponsor,
                    has_education: !!response?.education,
                    has_auto_banner: !!response?.auto_banner,
                    content_id: response?.content_id
                });
                const rewardRules = response && response.reward_rules ? response.reward_rules : {};
                const contentType = response && response.content_type ? String(response.content_type) : String(response && response.mode ? response.mode : 'regular');
                const contentId = response && response.content_id ? String(response.content_id) : '';
                const cta = response && response.cta ? response.cta : null;
                const ctaPointsHintRaw = cta && cta.points !== undefined
                    ? Number(cta.points)
                    : Number(rewardRules.cta_points || 0);
                state.currentCtaPoints = Number.isFinite(ctaPointsHintRaw)
                    ? Math.max(0, Math.round(ctaPointsHintRaw))
                    : 0;

                if (contentType === 'education' && response.education) {
                    state.currentMode = 'education';
                    state.currentAdType = 'education';
                    state.currentSponsorId = 0;
                    updateRewardInfoText(rewardRules, 'education');
                    
                    // Education uses its own dedicated info bar (education-info-bar), NOT the generic video-info-panel
                    // So we don't call showVideoInfoPanel here
                    
                    if (cta && cta.url && cta.url.trim() !== '' && cta.url !== '#') {
                        updateCta(cta.label, cta.url, {
                            content_type: 'education',
                            content_id: contentId || '',
                            sponsor_id: 0,
                            points: cta.points || 0,
                            dedupe_key: cta.dedupe_key || ''
                        });
                    } else if (response.education.cta_url && response.education.cta_url.trim() !== '' && response.education.cta_url !== '#') {
                        updateCta(response.education.cta_label, response.education.cta_url, {
                            content_type: 'education',
                            content_id: contentId || response.education.content_id || '',
                            sponsor_id: 0
                        });
                    } else {
                        // No CTA - hide the CTA button
                        updateCta('', '', null);
                    }
                    trackEvent('ads_watch_view_start', {
                        ad_type: 'education',
                        sponsor_id: 0,
                        content_id: contentId || response.education.content_id || ''
                    });
                    playEducationContent(response.education);
                    return;
                }

                if (contentType === 'sponsor' && response.sponsor) {
                    state.currentMode = 'sponsor';
                    state.currentAdType = 'sponsor';
                    state.currentSponsorId = Number(response.sponsor.id || 0);
                    updateRewardInfoText(rewardRules, 'sponsor');
                    
                    // Show unified video info panel
                    const sponsorPoints = Number(rewardRules.points_sponsor || 5);
                    const sponsorVotes = Number(rewardRules.votes_sponsor || 5);
                    // CTA click always gives 5 pts + 5 votes (hardcoded in PHP)
                    showVideoInfoPanel('sponsor', {
                        title: response.sponsor.title || 'Szponzori videó',
                        watchReward: '+' + sponsorPoints + ' pont, +' + sponsorVotes + ' szavazat',
                        clickReward: '+5 pont, +5 szavazat'
                    });
                    
                    if (cta) {
                        updateCta(cta.label, cta.url, {
                            content_type: 'sponsor',
                            content_id: contentId || String(response.sponsor.id || ''),
                            sponsor_id: state.currentSponsorId,
                            points: cta.points || 0,
                            dedupe_key: cta.dedupe_key || ''
                        });
                    } else {
                        updateCta(response.sponsor.cta_label, response.sponsor.cta_url, {
                            content_type: 'sponsor',
                            content_id: String(response.sponsor.id || ''),
                            sponsor_id: state.currentSponsorId
                        });
                    }
                    trackEvent('ads_watch_view_start', {
                        ad_type: 'sponsor',
                        sponsor_id: state.currentSponsorId,
                        content_id: contentId || String(response.sponsor.id || '')
                    });

                    if (response.sponsor.media_type === 'youtube' && (response.sponsor.youtube_id || response.sponsor.youtube_url)) {
                        playSponsorYoutube(response.sponsor.youtube_id || response.sponsor.youtube_url);
                        return;
                    }

                    if (response.sponsor.media_type === 'mp4' && response.sponsor.media_url) {
                        playSponsorMp4(response.sponsor.media_url);
                        return;
                    }

                    // VAST/IMA sponsor - only if we have a valid tag URL
                    const vastUrl = response.sponsor.vast_tag || state.defaultAdTagUrl;
                    if (!vastUrl || vastUrl === '') {
                        console.error('[Sponsor] No valid media source - skipping', response.sponsor);
                        showLoading(false);
                        state.isPlaying = false;
                        updateWatchButton();
                        showNotification('Nincs több videó a rendszerben, térj vissza később.', 'warning');
                        return;
                    }
                    
                    state.pendingAdTagUrl = vastUrl;
                    if (state.adDisplayContainer) {
                        state.adDisplayContainer.initialize();
                    }
                    requestAds();
                    return;
                }

                if (contentType === 'auto_banner' && response.auto_banner) {
                    state.currentMode = 'auto_banner';
                    state.currentAdType = 'auto_banner';
                    state.currentSponsorId = 0;
                    updateRewardInfoText(rewardRules, 'auto_banner');
                    
                    // Show unified video info panel for auto banner
                    // CTA click always gives 5 pts + 5 votes (hardcoded in PHP)
                    showVideoInfoPanel('auto_banner', {
                        title: response.auto_banner.title || 'Akciós ajánlat',
                        watchReward: '+1 pont, +1 szavazat',
                        clickReward: '+5 pont, +5 szavazat'
                    });
                    
                    updateCta('', '', null);
                    showAutoBannerContent(response.auto_banner, cta, contentId || '', response.ttl_seconds || 5);
                    trackEvent('ads_watch_view_start', {
                        ad_type: 'auto_banner',
                        sponsor_id: 0,
                        content_id: contentId || ''
                    });
                    return;
                }

                state.currentMode = 'regular';
                state.currentAdType = 'regular';
                state.currentSponsorId = 0;
                updateRewardInfoText(rewardRules, 'regular');
                
                // Show unified video info panel for regular ads
                const regularPoints = Number(rewardRules.points_regular || 1);
                const regularVotes = Number(rewardRules.votes_regular || 1);
                // CTA click always gives 5 pts + 5 votes (hardcoded in PHP)
                showVideoInfoPanel('regular', {
                    title: 'Reklám',
                    watchReward: '+' + regularPoints + ' pont, +' + regularVotes + ' szavazat',
                    clickReward: '+5 pont, +5 szavazat'
                });
                
                if (cta) {
                    updateCta(cta.label, cta.url, {
                        content_type: 'ad',
                        content_id: contentId || '',
                        sponsor_id: 0,
                        points: cta.points || 0,
                        dedupe_key: cta.dedupe_key || ''
                    });
                } else {
                    updateCta('', '', null);
                }
                trackEvent('ads_watch_view_start', {
                    ad_type: 'regular',
                    sponsor_id: 0,
                    content_id: contentId || ''
                });
                state.pendingAdTagUrl = response && response.ad_tag_url ? response.ad_tag_url : state.defaultAdTagUrl;
                if (state.adDisplayContainer) {
                    state.adDisplayContainer.initialize();
                }
                requestAds();
            })
            .fail(function () {
                showLoading(false);
                state.isPlaying = false;
                updateWatchButton();
                showNotification(i18n.adError || 'Nem sikerült betölteni a reklámot', 'error');
            });
        };

        if (!state.imaReady) {
            showLoading(true);
            initGoogleIMA().done(function (ready) {
                showLoading(false);
                if (!ready) {
                    showNotification('Az Ad blokkereket kapcsold ki a videókhoz.', 'warning');
                    return;
                }
                proceed();
            });
            return;
        }

        proceed();
    }

    function playSponsorMp4(url) {
        const videoElement = document.getElementById('content-video');
        const adContainer = document.getElementById('ad-container');
        const iframeContainer = document.getElementById('education-iframe');
        if (!videoElement) {
            showLoading(false);
            state.isPlaying = false;
            updateWatchButton();
            showNotification(i18n.adError || 'Nem sikerült betölteni a videót', 'error');
            return;
        }

        if (adContainer) {
            adContainer.innerHTML = '';
            adContainer.style.display = '';
        }
        if (iframeContainer) {
            iframeContainer.innerHTML = '';
            iframeContainer.style.display = 'none';
        }

        videoElement.pause();
        videoElement.removeAttribute('src');
        videoElement.load();
        videoElement.src = url;
        videoElement.controls = false;
        videoElement.muted = false;

        $('#player-overlay').hide();
        showLoading(false);

        const onTimeUpdate = function () {
            if (!videoElement.duration || Number.isNaN(videoElement.duration)) {
                return;
            }
            state.adProgress = Math.min(1, Math.max(0, videoElement.currentTime / videoElement.duration));
            updateAdProgressBar();
        };

        const onEnded = function () {
            videoElement.removeEventListener('ended', onEnded);
            videoElement.removeEventListener('timeupdate', onTimeUpdate);
            handleAdCompletion(true, state.adProgress || 1);
            resetPlayer();
        };

        videoElement.addEventListener('timeupdate', onTimeUpdate);
        videoElement.addEventListener('ended', onEnded);
        videoElement.play().catch(function () {
            showNotification(i18n.adError || 'Nem sikerült lejátszani a videót', 'error');
            resetPlayer();
        });
    }

    function extractYouTubeId(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        if (/^[a-zA-Z0-9_-]{6,20}$/.test(raw)) return raw;
        const match = raw.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([a-zA-Z0-9_-]{6,20})/i);
        return match ? match[1] : '';
    }

    function playSponsorYoutube(value) {
        const videoId = extractYouTubeId(value);
        const videoElement = document.getElementById('content-video');
        const adContainer = document.getElementById('ad-container');
        const iframeContainer = document.getElementById('education-iframe');
        if (!videoId || !iframeContainer) {
            showLoading(false);
            state.isPlaying = false;
            updateWatchButton();
            showNotification(i18n.adError || 'Nem sikerült betölteni a YouTube videót', 'error');
            return;
        }

        resetEducationState();
        if (adContainer) {
            adContainer.innerHTML = '';
            adContainer.style.display = 'none';
        }
        if (videoElement) {
            videoElement.pause();
            videoElement.removeAttribute('src');
            videoElement.load();
        }

        iframeContainer.style.display = 'block';
        iframeContainer.innerHTML = '<div id="sponsor-player"></div>';

        $('#player-overlay').hide();
        showLoading(false);

        console.log('[YouTube] Starting sponsor video load for videoId:', videoId);
        const ytLoadStart = Date.now();
        let ytPlayerReady = false;

        // Timeout for YouTube player - 8 seconds max
        const ytTimeout = setTimeout(function () {
            if (!ytPlayerReady) {
                const elapsed = Date.now() - ytLoadStart;
                console.error('[YouTube] Sponsor player timeout after ' + elapsed + 'ms', { videoId: videoId });
                showNotification('A YouTube videó betöltése túl sokáig tartott. Próbáld újra!', 'warning');
                resetPlayer();
            }
        }, 8000);

        loadYouTubeApi().then(function () {
            if (!window.YT || !window.YT.Player) {
                clearTimeout(ytTimeout);
                console.error('[YouTube] YT API not available after load');
                showNotification(i18n.adError || 'Nem sikerült betölteni a YouTube videót', 'error');
                resetPlayer();
                return;
            }
            console.log('[YouTube] YT API loaded, creating player...');
            if (state.youtubePlayer && typeof state.youtubePlayer.destroy === 'function') {
                state.youtubePlayer.destroy();
            }
            state.youtubePlayer = new window.YT.Player('sponsor-player', {
                videoId: videoId,
                playerVars: {
                    autoplay: 1,
                    playsinline: 1,
                    rel: 0,
                    modestbranding: 1
                },
                events: {
                    onReady: function (event) {
                        ytPlayerReady = true;
                        clearTimeout(ytTimeout);
                        const loadTime = Date.now() - ytLoadStart;
                        console.log('[YouTube] Sponsor player ready in ' + loadTime + 'ms');
                        try { event.target.playVideo(); } catch (e) {
                            console.error('[YouTube] playVideo() failed:', e);
                        }
                    },
                    onStateChange: function (event) {
                        console.log('[YouTube] Sponsor player state change:', event.data);
                        if (event.data === window.YT.PlayerState.ENDED) {
                            if (state.sponsorYoutubeTimer) {
                                clearInterval(state.sponsorYoutubeTimer);
                                state.sponsorYoutubeTimer = null;
                            }
                            state.adProgress = 1;
                            updateAdProgressBar();
                            handleAdCompletion(true, 1);
                            resetPlayer(true); // Keep progress bar visible
                        }
                    },
                    onError: function (event) {
                        ytPlayerReady = true; // Stop timeout
                        clearTimeout(ytTimeout);
                        // YouTube error codes: 2=invalid param, 5=HTML5 error, 100=not found, 101/150=not embeddable
                        console.error('[YouTube] Sponsor player error:', event.data, {
                            videoId: videoId,
                            errorMeaning: {
                                2: 'Invalid video ID',
                                5: 'HTML5 player error',
                                100: 'Video not found or private',
                                101: 'Video not embeddable',
                                150: 'Video not embeddable'
                            }[event.data] || 'Unknown error'
                        });
                        showNotification('A YouTube videó nem játszható le (hiba: ' + event.data + ')', 'error');
                        resetPlayer();
                    }
                }
            });

            if (state.sponsorYoutubeTimer) {
                clearInterval(state.sponsorYoutubeTimer);
            }
            state.sponsorYoutubeTimer = setInterval(function () {
                if (!state.youtubePlayer || typeof state.youtubePlayer.getDuration !== 'function') {
                    return;
                }
                const duration = Number(state.youtubePlayer.getDuration() || 0);
                const current = Number(state.youtubePlayer.getCurrentTime() || 0);
                if (duration > 0) {
                    state.adProgress = Math.min(1, Math.max(0, current / duration));
                    updateAdProgressBar();
                }
            }, 500);
        });
    }

    function resetEducationState() {
        if (state.educationTimer) {
            clearInterval(state.educationTimer);
            state.educationTimer = null;
        }
        if (state.educationSkipTimeout) {
            clearTimeout(state.educationSkipTimeout);
            state.educationSkipTimeout = null;
        }
        if (state.educationPresenceTimer) {
            clearInterval(state.educationPresenceTimer);
            state.educationPresenceTimer = null;
        }
        if (state.educationPresenceTimeoutTimer) {
            clearInterval(state.educationPresenceTimeoutTimer);
            state.educationPresenceTimeoutTimer = null;
        }
        if (state.sponsorYoutubeTimer) {
            clearInterval(state.sponsorYoutubeTimer);
            state.sponsorYoutubeTimer = null;
        }
        state.educationContent = null;
        state.educationSessionToken = '';
        state.educationMaxIntervals = 0;
        state.educationIntervalsSent = 0;
        state.educationWatchedSeconds = 0;
        state.educationIntervalSeconds = 30;
        state.educationPointsPerInterval = 1;
        state.educationVotesPerInterval = 1;
        state.educationBonusPoints = 0;
        state.educationBonusVotes = 0;
        state.educationBonusAwarded = false;
        state.educationPresenceInterval = 0;
        state.educationPresenceTimeout = 0;
        state.educationSkipEnabled = false;
        state.educationDurationSeconds = 0;
        state.educationLastTick = 0;
        state.educationPresenceActive = false;
        state.educationLastPresenceCheck = 0;
        state.educationPlayStartedAt = 0;
        state.educationAccumulatedSeconds = 0;
        state.educationProgress = 0;
        state.educationVisibilityPaused = false;
        state.educationInFlight = false;
        state.educationPlaying = false;
        state.educationLastPlayerTime = 0;
        const iframeContainer = document.getElementById('education-iframe');
        if (iframeContainer) {
            iframeContainer.innerHTML = '';
            iframeContainer.style.display = 'none';
        }
        if (state.youtubePlayer && typeof state.youtubePlayer.destroy === 'function') {
            state.youtubePlayer.destroy();
            state.youtubePlayer = null;
        }
        $('#education-info-bar').hide();
        $('#presence-check-overlay').hide();
        $('#presence-timeout-fill').css('width', '0%');
        $('#btn-skip-education').hide().prop('disabled', true);
    }

    function getEducationVideoElement() {
        return document.getElementById('content-video');
    }

    function getEducationCurrentSeconds() {
        if (state.youtubePlayer && typeof state.youtubePlayer.getCurrentTime === 'function') {
            return Number(state.youtubePlayer.getCurrentTime() || 0);
        }
        const videoElement = getEducationVideoElement();
        if (videoElement && typeof videoElement.currentTime === 'number') {
            return Number(videoElement.currentTime || 0);
        }
        return null;
    }

    function getEducationDurationSeconds() {
        if (state.youtubePlayer && typeof state.youtubePlayer.getDuration === 'function') {
            const duration = Number(state.youtubePlayer.getDuration() || 0);
            if (duration > 0) {
                return duration;
            }
        }
        const videoElement = getEducationVideoElement();
        if (videoElement && typeof videoElement.duration === 'number' && !Number.isNaN(videoElement.duration)) {
            return Number(videoElement.duration || 0);
        }
        return Number(state.educationDurationSeconds || 0);
    }

    function formatDuration(seconds) {
        const total = Math.max(0, Math.floor(Number(seconds || 0)));
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function showEducationInfoBar(education) {
        const $bar = $('#education-info-bar');
        if (!$bar.length) {
            return;
        }
        // Hide the generic video-info-panel - education has its own bar
        hideVideoInfoPanel();
        
        $('#edu-video-title').text(education.title || education.name || 'Edukációs videó');
        $('#edu-interval-sec').text(state.educationIntervalSeconds);
        $('#edu-pts-interval').text(state.educationPointsPerInterval);
        $('#edu-votes-interval').text(state.educationVotesPerInterval);
        $('#edu-bonus-pts').text(state.educationBonusPoints);
        $('#edu-bonus-votes').text(state.educationBonusVotes);
        updateEducationInfoProgress();
        $bar.show();
    }

    function updateEducationInfoProgress() {
        const $watched = $('#edu-watched-time');
        const $earned = $('#edu-earned-pts');
        if ($watched.length) {
            $watched.text(formatDuration(state.educationWatchedSeconds));
        }
        if ($earned.length) {
            let earned = Math.max(0, Number(state.educationIntervalsSent || 0) * Number(state.educationPointsPerInterval || 0));
            if (state.educationBonusAwarded) {
                earned += Number(state.educationBonusPoints || 0);
            }
            $earned.text(earned.toLocaleString('hu-HU'));
        }
    }

    function updateEducationProgress() {
        const duration = getEducationDurationSeconds();
        if (duration > 0) {
            state.educationProgress = Math.min(1, Math.max(0, state.educationWatchedSeconds / duration));
        } else if (state.educationMaxIntervals > 0) {
            const totalSeconds = state.educationMaxIntervals * state.educationIntervalSeconds;
            state.educationProgress = Math.min(1, Math.max(0, state.educationWatchedSeconds / Math.max(1, totalSeconds)));
        } else {
            state.educationProgress = 0;
        }
        updateAdProgressBar();
        updateEducationInfoProgress();
        updateVideoInfoProgress();
    }

    function pauseEducationPlayback() {
        const videoElement = getEducationVideoElement();
        if (videoElement && !videoElement.paused) {
            videoElement.pause();
        }
        if (state.youtubePlayer && typeof state.youtubePlayer.pauseVideo === 'function') {
            state.youtubePlayer.pauseVideo();
        }
        state.educationPlaying = false;
    }

    function resumeEducationPlayback() {
        if (state.educationVisibilityPaused) {
            return;
        }
        const videoElement = getEducationVideoElement();
        if (videoElement && videoElement.paused) {
            videoElement.play().catch(function () {});
        }
        if (state.youtubePlayer && typeof state.youtubePlayer.playVideo === 'function') {
            state.youtubePlayer.playVideo();
        }
        state.educationPlaying = true;
    }

    function showPresenceOverlay() {
        const $overlay = $('#presence-check-overlay');
        if (!$overlay.length) {
            return;
        }
        if (state.educationPresenceActive) {
            return;
        }
        state.educationPresenceActive = true;
        pauseEducationPlayback();
        $overlay.show();
        const timeoutSeconds = Math.max(5, Number(state.educationPresenceTimeout || 15));
        const $fill = $('#presence-timeout-fill');
        $fill.css('width', '100%');
        const start = Date.now();
        if (state.educationPresenceTimeoutTimer) {
            clearInterval(state.educationPresenceTimeoutTimer);
        }
        state.educationPresenceTimeoutTimer = setInterval(function () {
            const elapsed = (Date.now() - start) / 1000;
            const remaining = Math.max(0, timeoutSeconds - elapsed);
            const percent = Math.max(0, (remaining / timeoutSeconds) * 100);
            $fill.css('width', percent.toFixed(1) + '%');
            if (remaining <= 0) {
                clearInterval(state.educationPresenceTimeoutTimer);
                state.educationPresenceTimeoutTimer = null;
                showNotification('A videó szünetelt, mert nem volt aktivitás.', 'warning');
                resetPlayer();
            }
        }, 200);
    }

    function confirmPresenceCheck() {
        if (!state.educationPresenceActive) {
            return;
        }
        state.educationPresenceActive = false;
        state.educationLastPresenceCheck = state.educationAccumulatedSeconds;
        $('#presence-check-overlay').hide();
        if (state.educationPresenceTimeoutTimer) {
            clearInterval(state.educationPresenceTimeoutTimer);
            state.educationPresenceTimeoutTimer = null;
        }
        $('#presence-timeout-fill').css('width', '0%');
        resumeEducationPlayback();
    }

    function startPresenceTimer() {
        if (state.educationPresenceTimer) {
            clearInterval(state.educationPresenceTimer);
        }
        if (state.educationPresenceInterval <= 0) {
            return;
        }
        state.educationPresenceTimer = setInterval(function () {
            if (!state.educationPlaying || state.educationPresenceActive || state.educationVisibilityPaused) {
                return;
            }
            if ((state.educationAccumulatedSeconds - state.educationLastPresenceCheck) >= state.educationPresenceInterval) {
                showPresenceOverlay();
            }
        }, 1000);
    }

    function scheduleSkipButton() {
        const $btn = $('#btn-skip-education');
        if (!$btn.length) {
            return;
        }
        $btn.hide().prop('disabled', true);
        if (!state.educationSkipEnabled) {
            return;
        }
        if (state.educationSkipTimeout) {
            clearTimeout(state.educationSkipTimeout);
        }
        state.educationSkipTimeout = setTimeout(function () {
            $btn.show().prop('disabled', false);
        }, 8000);
    }

    function skipEducationVideo() {
        if (!state.educationContent) {
            return;
        }
        showNotification('A videót kihagytad, ezért nem jár jutalom.', 'warning');
        resetPlayer();
    }

    function skipCurrentVideo() {
        // Unified skip handler for all video types
        if (state.currentMode === 'education') {
            skipEducationVideo();
            return;
        }
        showNotification('A videót kihagytad, ezért nem jár jutalom.', 'warning');
        resetPlayer();
    }

    function showVideoInfoPanel(mode, options) {
        const $panel = $('#video-info-panel');
        const $skipBtn = $('#btn-skip-video');
        if (!$panel.length) {
            return;
        }

        options = options || {};
        const title = options.title || '';
        const watchReward = options.watchReward || '';
        const clickReward = options.clickReward || '';
        const showProgress = options.showProgress || false;
        const showClick = !!clickReward;

        // Set icon based on mode
        const iconMap = {
            education: '📚',
            sponsor: '🎬',
            auto_banner: '🛒',
            regular: '📺'
        };
        $('#video-info-icon').text(iconMap[mode] || '📺');
        $('#video-info-title-text').text(title);
        $('#video-info-watch-reward').text(watchReward);

        if (showClick) {
            $('#video-info-click-reward').text(clickReward);
            $('#video-info-click-section').show();
        } else {
            $('#video-info-click-section').hide();
        }

        if (showProgress) {
            $('#video-info-progress-section').show();
        } else {
            $('#video-info-progress-section').hide();
        }

        $panel.show();

        // Show skip button after delay
        if ($skipBtn.length) {
            $skipBtn.hide();
            setTimeout(function () {
                $skipBtn.show();
            }, 5000);
        }
    }

    function hideVideoInfoPanel() {
        $('#video-info-panel').hide();
        $('#btn-skip-video').hide();
    }

    function updateVideoInfoProgress() {
        const $watched = $('#video-info-watched-time');
        const $earned = $('#video-info-earned-pts');
        if ($watched.length) {
            $watched.text(formatDuration(state.educationWatchedSeconds));
        }
        if ($earned.length) {
            let earned = Math.max(0, Number(state.educationIntervalsSent || 0) * Number(state.educationPointsPerInterval || 0));
            if (state.educationBonusAwarded) {
                earned += Number(state.educationBonusPoints || 0);
            }
            $earned.text(earned.toLocaleString('hu-HU'));
        }
    }

    function handleVisibilityChange() {
        if (state.externalNavigationPending) {
            if (document.hidden) {
                state.externalNavigationVisibilityLost = true;
            } else {
                maybeRecoverFromExternalNavigation('visibilitychange');
            }
        }
        if (!state.educationContent) {
            return;
        }
        if (document.hidden) {
            state.educationVisibilityPaused = true;
            pauseEducationPlayback();
            return;
        }
        state.educationVisibilityPaused = false;
        if (!state.educationPresenceActive) {
            resumeEducationPlayback();
        }
    }

    function loadYouTubeApi() {
        if (window.YT && window.YT.Player) {
            return Promise.resolve();
        }
        if (state.youtubeReady) {
            return state.youtubeReady;
        }
        state.youtubeReady = new Promise(function (resolve) {
            const existing = document.querySelector('script[src*="youtube.com/iframe_api"]');
            if (existing) {
                const check = setInterval(function () {
                    if (window.YT && window.YT.Player) {
                        clearInterval(check);
                        resolve();
                    }
                }, 100);
                return;
            }
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(tag);
            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof previous === 'function') {
                    previous();
                }
                resolve();
            };
        });
        return state.youtubeReady;
    }

    function markExternalNavigation(source) {
        state.externalNavigationPending = true;
        state.externalNavigationVisibilityLost = false;
        state.externalNavigationStartedAt = Date.now();
        state.externalNavigationReloaded = false;
        state.externalNavigationSource = String(source || '');
    }

    function clearExternalNavigationState() {
        state.externalNavigationPending = false;
        state.externalNavigationVisibilityLost = false;
        state.externalNavigationStartedAt = 0;
        state.externalNavigationReloaded = false;
        state.externalNavigationSource = '';
    }

    function maybeRecoverFromExternalNavigation(reason) {
        if (!state.externalNavigationPending || state.externalNavigationReloaded) {
            return;
        }
        if (document.hidden) {
            return;
        }
        const elapsed = Date.now() - Number(state.externalNavigationStartedAt || 0);
        const lostVisibility = !!state.externalNavigationVisibilityLost;
        if (!lostVisibility && elapsed < 1500) {
            return;
        }
        state.externalNavigationReloaded = true;
        console.log('[AutoBanner][DEBUG] Recovering from external navigation return', {
            reason: reason || '',
            source: state.externalNavigationSource || '',
            elapsed: elapsed,
            lostVisibility: lostVisibility
        });
        window.location.reload();
    }

    function initYouTubePlayer(videoId) {
        const iframeContainer = document.getElementById('education-iframe');
        if (!iframeContainer) {
            console.error('[YouTube] Education iframe container not found');
            showNotification('Nem sikerült betölteni az oktató videót.', 'error');
            resetPlayer();
            return;
        }
        if (!videoId) {
            console.error('[YouTube] No YouTube video ID provided');
            showNotification('Nincs megadva YouTube videó azonosító.', 'error');
            resetPlayer();
            return;
        }

        console.log('[YouTube] Starting education video load for videoId:', videoId);
        const ytLoadStart = Date.now();
        let ytPlayerReady = false;

        // Timeout for YouTube player - 8 seconds max
        const ytTimeout = setTimeout(function () {
            if (!ytPlayerReady) {
                const elapsed = Date.now() - ytLoadStart;
                console.error('[YouTube] Education player timeout after ' + elapsed + 'ms', { videoId: videoId });
                showNotification('A YouTube videó betöltése túl sokáig tartott. Próbáld újra!', 'warning');
                resetPlayer();
            }
        }, 8000);

        iframeContainer.innerHTML = '<div id="education-player"></div>';
        loadYouTubeApi().then(function () {
            console.log('[YouTube] YouTube API loaded, creating education player...');
            if (!window.YT || !window.YT.Player) {
                clearTimeout(ytTimeout);
                console.error('[YouTube] YouTube API not available');
                showNotification('A YouTube API nem elérhető.', 'error');
                resetPlayer();
                return;
            }
            if (state.youtubePlayer && typeof state.youtubePlayer.destroy === 'function') {
                state.youtubePlayer.destroy();
            }
            state.youtubePlayer = new window.YT.Player('education-player', {
                videoId: videoId,
                playerVars: {
                    autoplay: 1,
                    playsinline: 1,
                    rel: 0,
                    modestbranding: 1
                },
                events: {
                    onReady: function (event) {
                        ytPlayerReady = true;
                        clearTimeout(ytTimeout);
                        const loadTime = Date.now() - ytLoadStart;
                        console.log('[YouTube] Education player ready in ' + loadTime + 'ms');
                        state.educationPlaying = true;
                        try { event.target.playVideo(); } catch (e) {
                            console.error('[YouTube] playVideo() failed:', e);
                        }
                    },
                    onStateChange: function (event) {
                        console.log('[YouTube] Education player state change:', event.data);
                        if (event.data === window.YT.PlayerState.PLAYING) {
                            state.educationPlaying = true;
                        } else if (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.BUFFERING) {
                            state.educationPlaying = false;
                        } else if (event.data === window.YT.PlayerState.ENDED) {
                            state.educationPlaying = false;
                            maybeAwardEducationIntervals(true);
                            resetPlayer(true); // Keep progress bar visible
                        }
                    },
                    onError: function (event) {
                        ytPlayerReady = true;
                        clearTimeout(ytTimeout);
                        // YouTube error codes: 2=invalid param, 5=HTML5 error, 100=not found, 101/150=not embeddable
                        console.error('[YouTube] Education player error:', event.data, {
                            videoId: videoId,
                            errorMeaning: {
                                2: 'Invalid video ID',
                                5: 'HTML5 player error',
                                100: 'Video not found or private',
                                101: 'Video not embeddable',
                                150: 'Video not embeddable'
                            }[event.data] || 'Unknown error'
                        });
                        showNotification('A YouTube videó nem játszható le (hiba: ' + event.data + ')', 'error');
                        resetPlayer();
                    }
                }
            });
        });
    }

    function playEducationContent(education) {
        const videoElement = document.getElementById('content-video');
        const adContainer = document.getElementById('ad-container');
        const iframeContainer = document.getElementById('education-iframe');
        if (!videoElement || !iframeContainer) {
            showLoading(false);
            state.isPlaying = false;
            updateWatchButton();
            showNotification('Nem sikerült betölteni az oktató videót.', 'error');
            return;
        }

        resetEducationState();
        state.educationContent = education || null;
        state.educationSessionToken = education.session_token || '';
        state.educationMaxIntervals = Number(education.max_intervals || 0);
        state.educationIntervalSeconds = Math.max(5, Number(education.interval_seconds || state.educationIntervalSeconds || 30));
        state.educationPointsPerInterval = Math.max(0, Number(education.points_per_interval || state.educationPointsPerInterval || 1));
        state.educationVotesPerInterval = Math.max(0, Number(education.votes_per_interval || state.educationVotesPerInterval || 1));
        state.educationBonusPoints = Math.max(0, Number(education.bonus_points || 0));
        state.educationBonusVotes = Math.max(0, Number(education.bonus_votes || 0));
        state.educationPresenceInterval = Math.max(0, Number(education.presence_interval || 0));
        state.educationPresenceTimeout = Math.max(0, Number(education.presence_timeout || 0));
        state.educationSkipEnabled = Boolean(education.skip_enabled);
        state.educationDurationSeconds = Math.max(0, Number(education.duration_seconds || 0));
        state.educationIntervalsSent = 0;
        state.educationWatchedSeconds = 0;
        state.educationPlaying = false;
        state.educationBonusAwarded = false;
        state.educationAccumulatedSeconds = 0;
        state.educationLastPresenceCheck = 0;
        state.educationProgress = 0;
        state.educationLastTick = Date.now();

        if (adContainer) {
            adContainer.innerHTML = '';
            adContainer.style.display = 'none';
        }

        $('#player-overlay').hide();
        showLoading(false);
        showEducationInfoBar(education);
        scheduleSkipButton();
        startPresenceTimer();

        if (education.youtube_id) {
            videoElement.pause();
            videoElement.removeAttribute('src');
            videoElement.load();
            iframeContainer.style.display = 'block';
            initYouTubePlayer(education.youtube_id);
            startEducationTimer();
            return;
        }

        if (education.video_url) {
            iframeContainer.innerHTML = '';
            iframeContainer.style.display = 'none';
            videoElement.pause();
            videoElement.removeAttribute('src');
            videoElement.load();
            videoElement.src = education.video_url;
            videoElement.controls = false;
            videoElement.muted = false;

            const onTimeUpdate = function () {
                if (!videoElement.duration || Number.isNaN(videoElement.duration)) {
                    return;
                }
                state.educationWatchedSeconds = Math.max(0, Number(videoElement.currentTime || 0));
                updateEducationProgress();
            };

            const onEnded = function () {
                videoElement.removeEventListener('ended', onEnded);
                videoElement.removeEventListener('timeupdate', onTimeUpdate);
                maybeAwardEducationIntervals(true);
                resetPlayer();
            };

            videoElement.addEventListener('timeupdate', onTimeUpdate);
            videoElement.addEventListener('ended', onEnded);
            videoElement.addEventListener('play', function () {
                state.educationPlaying = true;
            });
            videoElement.addEventListener('pause', function () {
                state.educationPlaying = false;
            });
            videoElement.addEventListener('ended', function () {
                state.educationPlaying = false;
            });
            videoElement.play().catch(function () {
                showNotification(i18n.adError || 'Nem sikerült lejátszani a videót', 'error');
                resetPlayer();
            });
            startEducationTimer();
            return;
        }

        showNotification('Nincs több videó a rendszerben, térj vissza később.', 'warning');
        resetPlayer();
    }

    function startEducationTimer() {
        if (state.educationTimer) {
            clearInterval(state.educationTimer);
        }
        state.educationLastTick = Date.now();
        state.educationLastPlayerTime = 0;
        state.educationTimer = setInterval(function () {
            const now = Date.now();
            const delta = Math.max(0, (now - state.educationLastTick) / 1000);
            state.educationLastTick = now;
            if (!state.educationPlaying || state.educationPresenceActive || state.educationVisibilityPaused) {
                return;
            }
            const currentSeconds = getEducationCurrentSeconds();
            if (typeof currentSeconds === 'number' && !Number.isNaN(currentSeconds)) {
                // Seek detection: if user jumped forward more than 2 seconds, revert to last position
                const maxAllowedJump = 2;
                if (state.educationLastPlayerTime > 0 && currentSeconds > state.educationLastPlayerTime + maxAllowedJump) {
                    console.log('[Education] Seek forward detected: ' + state.educationLastPlayerTime.toFixed(1) + 's → ' + currentSeconds.toFixed(1) + 's, reverting');
                    // Revert player to last valid position
                    if (state.youtubePlayer && typeof state.youtubePlayer.seekTo === 'function') {
                        state.youtubePlayer.seekTo(state.educationLastPlayerTime, true);
                    }
                    return; // Don't update watched time
                }
                // Only count time actually watched (delta-based, not player position)
                state.educationWatchedSeconds += delta;
                state.educationAccumulatedSeconds += delta;
                state.educationLastPlayerTime = currentSeconds;
            } else {
                state.educationWatchedSeconds += delta;
                state.educationAccumulatedSeconds += delta;
            }
            updateEducationProgress();
            maybeAwardEducationIntervals();
        }, 500);
    }

    function maybeAwardEducationIntervals(force) {
        if (!state.educationSessionToken) {
            return;
        }
        if (state.educationMaxIntervals > 0 && state.educationIntervalsSent >= state.educationMaxIntervals) {
            if (state.educationTimer) {
                clearInterval(state.educationTimer);
                state.educationTimer = null;
            }
            return;
        }
        const intervalSeconds = Math.max(1, Number(state.educationIntervalSeconds || 30));
        const maxIntervals = Number(state.educationMaxIntervals || 0);
        const totalIntervals = Math.floor(state.educationWatchedSeconds / intervalSeconds);
        let availableIntervals = totalIntervals - state.educationIntervalsSent;

        if (maxIntervals > 0) {
            availableIntervals = Math.min(availableIntervals, maxIntervals - state.educationIntervalsSent);
        }

        if (availableIntervals <= 0 && !force) {
            return;
        }

        if (availableIntervals <= 0 && force) {
            return;
        }

        recordEducationIntervals(availableIntervals);
    }

    function recordEducationIntervals(intervals) {
        if (!intervals || intervals <= 0 || state.educationInFlight) {
            return;
        }
        state.educationInFlight = true;
        apiRequest('education', 'POST', {
            session_token: state.educationSessionToken,
            intervals: intervals,
            watched_seconds: state.educationWatchedSeconds
        })
            .done(function (response) {
                const prevAvailable = Number(state.availableVotes || 0);
                const points = response.points || 0;
                const votes = response.votes || 0;
                if (response.bonus_awarded) {
                    state.educationBonusAwarded = true;
                }
                if (typeof response.new_total === 'number') {
                    state.points = response.new_total;
                } else {
                    state.points = state.points + points;
                }
                if (typeof response.available_votes === 'number') {
                    state.availableVotes = response.available_votes;
                } else {
                    state.availableVotes = state.availableVotes + votes;
                }
                state.educationIntervalsSent = Number(response.total_intervals || (state.educationIntervalsSent + intervals));

                updateStatusDisplay();
                updateVoteControls();
                if (points > 0 || votes > 0) {
                    showRewardAnimation(points, votes);
                }
                updateEducationInfoProgress();
                trackEvent('ads_watch_view_complete', {
                    ad_type: 'education',
                    sponsor_id: 0,
                    points: points,
                    votes: votes
                });
                notifyPointsUpdated();

                const addedVotes = Math.max(0, Number(state.availableVotes) - prevAvailable);
                if (state.autoVote && addedVotes > 0 && state.selectedNgo) {
                    autoAllocateVotes(addedVotes);
                }

                if (state.educationMaxIntervals > 0 && state.educationIntervalsSent >= state.educationMaxIntervals) {
                    showNotification('Videó teljesítve. Új videóval folytathatod.', 'success');
                    resetPlayer();
                }
            })
            .fail(function () {
                showNotification('Nem sikerült rögzíteni az edukációs videót.', 'error');
            })
            .always(function () {
                state.educationInFlight = false;
            });
    }

    function requestAds() {
        const diagnostics = {
            timestamp: new Date().toISOString(),
            adsLoader: !!state.adsLoader,
            adDisplayContainer: !!state.adDisplayContainer,
            imaReady: state.imaReady,
            googleImaAvailable: typeof google !== 'undefined' && google.ima,
            alreadyPending: state.adRequestPending,
            vastTagUrl: null,
            containerSize: null
        };

        // Prevent duplicate requests
        if (state.adRequestPending) {
            console.warn('[IMA] requestAds called while another request is pending - ignoring');
            return;
        }

        if (!state.adsLoader) {
            console.error('[IMA] requestAds failed - adsLoader not initialized', diagnostics);
            onAdError({ getError: () => ({ getMessage: () => 'SDK not ready - adsLoader is null' }) });
            return;
        }

        if (!state.adDisplayContainer) {
            console.error('[IMA] requestAds failed - adDisplayContainer not initialized', diagnostics);
            onAdError({ getError: () => ({ getMessage: () => 'SDK not ready - adDisplayContainer is null' }) });
            return;
        }

        // Clear any existing timeout
        if (state.adLoadTimeout) {
            clearTimeout(state.adLoadTimeout);
            state.adLoadTimeout = null;
        }

        const adTagUrl = getAdTagUrl();
        diagnostics.vastTagUrl = adTagUrl;

        if (!adTagUrl) {
            console.error('[IMA] requestAds failed - no VAST tag URL', diagnostics);
            showLoading(false);
            state.isPlaying = false;
            updateWatchButton();
            showNotification('Nincs több videó a rendszerben, térj vissza később.', 'warning');
            return;
        }

        const adsRequest = new google.ima.AdsRequest();
        adsRequest.adTagUrl = adTagUrl;

        const container = document.getElementById('video-container');
        if (!container) {
            console.error('[IMA] requestAds failed - video-container not found', diagnostics);
            onAdError({ getError: () => ({ getMessage: () => 'video-container element not found' }) });
            return;
        }

        adsRequest.linearAdSlotWidth = container.clientWidth;
        adsRequest.linearAdSlotHeight = container.clientHeight;
        diagnostics.containerSize = { width: container.clientWidth, height: container.clientHeight };

        // Validate container has reasonable size
        if (container.clientWidth < 100 || container.clientHeight < 50) {
            console.warn('[IMA] video-container has small size, may cause issues', diagnostics);
        }

        console.log('[IMA] Requesting ad with VAST tag:', adTagUrl.substring(0, 100) + '...');
        console.log('[IMA] Request diagnostics:', diagnostics);

        // Mark request as pending
        state.adRequestPending = true;
        state.adRequestStartTime = Date.now();

        // Set timeout for ad loading - 6 seconds max
        state.adLoadTimeout = setTimeout(function () {
            const elapsed = Date.now() - state.adRequestStartTime;
            console.error('[IMA] Ad load timeout after ' + elapsed + 'ms', {
                vastTagUrl: adTagUrl,
                elapsed: elapsed,
                hint: 'Possible causes: 1) VAST tag server not responding, 2) CORS blocked, 3) Ad blocker, 4) Network issue'
            });
            state.adRequestPending = false;

            // Full IMA SDK reset - destroy and reinitialize everything
            console.log('[IMA] Performing full SDK reset due to timeout');
            if (state.adsManager) {
                try { state.adsManager.destroy(); } catch (e) {}
                state.adsManager = null;
            }
            if (state.adsLoader) {
                try { state.adsLoader.contentComplete(); } catch (e) {}
                // Destroy adsLoader to force fresh initialization on next request
                state.adsLoader = null;
            }
            state.adDisplayContainer = null;
            state.imaReady = false;

            showNotification('A reklám betöltése túl sokáig tartott. Próbáld újra!', 'warning');
            resetPlayer();
        }, 6000);

        try {
            state.adsLoader.requestAds(adsRequest);
            console.log('[IMA] requestAds() called successfully, waiting for callback...');
        } catch (e) {
            console.error('[IMA] requestAds threw exception:', e);
            state.adRequestPending = false;
            clearTimeout(state.adLoadTimeout);
            state.adLoadTimeout = null;
            onAdError({ getError: () => ({ getMessage: () => 'requestAds exception: ' + e.message }) });
        }
    }

    function getAdTagUrl() {
        let url = state.pendingAdTagUrl || state.defaultAdTagUrl || '';
        // Add dynamic correlator for fresh ad each request (prevents caching same ad)
        if (url && url.indexOf('correlator=') === -1) {
            const separator = url.indexOf('?') === -1 ? '?' : '&';
            url += separator + 'correlator=' + Date.now() + Math.random().toString(36).substring(2, 8);
        }
        return url;
    }

    function updateRewardInfoText(rules, mode) {
        const $text = $('#reward-info-text');
        if (!$text.length) return;
        const safeMode = mode || 'regular';
        const safeRules = rules || {};
        if (safeMode === 'education') {
            const points = Number(safeRules.education_points_per_interval || state.educationPointsPerInterval || 1);
            const votes = Number(safeRules.education_votes_per_interval || state.educationVotesPerInterval || 1);
            const seconds = Number(safeRules.education_interval_seconds || state.educationIntervalSeconds || 30);
            $text.html(`<strong>+${points} pont</strong> és <strong>+${votes} szavazat</strong> minden ${seconds} mp videó után`);
            return;
        }
        if (safeMode === 'auto_banner') {
            const ctaPoints = Number(safeRules.cta_points || 5);
            const ctaVotes = ctaPoints > 0 ? 5 : 0;
            $text.html(`<strong>+${ctaPoints} pont</strong> és <strong>+${ctaVotes} szavazat</strong> a hirdetésre kattintás után`);
            return;
        }
        if (safeMode === 'sponsor') {
            const points = Number(safeRules.points_sponsor || 5);
            const votes = Number(safeRules.votes_sponsor || 5);
            $text.html(`<strong>+${points} pont</strong> és <strong>+${votes} szavazat</strong> a szavazat‑egyenlegedhez`);
            return;
        }
        const points = Number(safeRules.points_regular || 1);
        const votes = Number(safeRules.votes_regular || 1);
        $text.html(`<strong>+${points} pont</strong> és <strong>+${votes} szavazat</strong> a szavazat‑egyenlegedhez`);
    }

    function updateCta(label, url, meta) {
        const $cta = $('#ads-watch-cta');
        const $link = $('#ads-watch-cta-link');
        if (!$cta.length || !$link.length) return;
        state.ctaMeta = null;
        state.ctaLabel = label || '';
        state.ctaUrl = url || '';

        // Check if URL is a valid external CTA (not empty, not just site root, not default fallback)
        const isValidCtaUrl = (function () {
            if (!url) return false;
            const trimmed = url.trim();
            if (trimmed === '' || trimmed === '#') return false;
            // Reject default/fallback URLs (site root, /impactshop/, app.sharity.hu root)
            if (/^https?:\/\/[^/]+\/?$/.test(trimmed)) return false; // just domain
            if (/^https?:\/\/[^/]+\/impactshop\/?$/i.test(trimmed)) return false; // /impactshop/
            if (/^\/impactshop\/?$/i.test(trimmed)) return false;
            if (/^\/\/?$/i.test(trimmed)) return false; // just /
            return true;
        })();

        if (!isValidCtaUrl) {
            $cta.hide();
            $link.attr('href', '#');
            return;
        }

        // Sponsor CTA with icon and "Kattints ide!" text (same style as IMA CTA)
        $link.html('<span class="ima-cta-icon">👆</span><span class="ima-cta-text">Kattints ide!</span>');
        $link.attr('href', url);
        $link.attr('target', '_blank');
        $link.attr('rel', 'noopener');
        $link.attr('title', 'Kattints a bónusz pontokért!');
        $cta.show();
        if (meta && typeof meta === 'object') {
            const ctaPoints = Number(meta.points);
            state.ctaMeta = {
                content_type: String(meta.content_type || ''),
                content_id: String(meta.content_id || ''),
                sponsor_id: Number(meta.sponsor_id || 0),
                points: Number.isFinite(ctaPoints) ? Math.max(0, Math.round(ctaPoints)) : 0,
                dedupe_key: String(meta.dedupe_key || '')
            };
        }
    }

    function buildCtaDedupe(contentType, contentId, ctaUrl) {
        const pseudo = state.pseudoId || '';
        const safeType = String(contentType || 'cta');
        const safeId = String(contentId || '') || (ctaUrl ? String(ctaUrl).slice(-48) : 'unknown');
        if (safeType === 'auto_banner') {
            return `cta:${safeType}:${safeId}:${pseudo}:${Date.now()}:${Math.random().toString(36).slice(2, 8)}`;
        }
        return `cta:${safeType}:${safeId}:${pseudo}`;
    }

    function safeBtoa(value) {
        try {
            return btoa(unescape(encodeURIComponent(String(value))));
        } catch (e) {
            try {
                return btoa(String(value));
            } catch (err) {
                return '';
            }
        }
    }

    function normalizeEncodedUrl(value) {
        return String(value || '')
            .replace(/#038;/g, '&')
            .replace(/&#38;/g, '&')
            .replace(/&amp;/g, '&');
    }

    function extractFilloutTarget(bannerUrl) {
        if (!bannerUrl) {
            return bannerUrl;
        }
        try {
            const url = new URL(normalizeEncodedUrl(bannerUrl));
            if (url.hostname.includes('fillout.com')) {
                let uParam = url.searchParams.get('u');
                if (!uParam && url.hash) {
                    const hashParams = new URLSearchParams(normalizeEncodedUrl(url.hash.replace(/^#/, '')));
                    uParam = hashParams.get('u');
                }
                if (uParam) {
                    return decodeURIComponent(escape(atob(uParam)));
                }
            }
        } catch (e) {
            return bannerUrl;
        }
        return bannerUrl;
    }

    function isFilloutUrl(url) {
        try {
            const parsed = new URL(url);
            return parsed.hostname.includes('fillout.com');
        } catch (e) {
            return false;
        }
    }

    function normalizeTargetUrl(rawUrl) {
        if (!rawUrl) {
            return rawUrl;
        }
        let candidate = normalizeEncodedUrl(rawUrl);
        if (isFilloutUrl(candidate)) {
            return extractFilloutTarget(candidate) || candidate;
        }
        try {
            const parsed = new URL(candidate);
            if (parsed.hostname.includes('dognet.com') && parsed.searchParams.has('url')) {
                const inner = normalizeEncodedUrl(decodeURIComponent(parsed.searchParams.get('url') || ''));
                if (inner) {
                    candidate = inner;
                }
                if (isFilloutUrl(candidate)) {
                    return extractFilloutTarget(candidate) || candidate;
                }
                return candidate;
            }
        } catch (e) {
            return candidate;
        }
        return candidate;
    }

    function getAffiliatePseudoId() {
        const rawPseudo = String(state.pseudoId || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        return rawPseudo.slice(0, 12);
    }

    function buildAffiliateSid(ngoSlug) {
        const parts = [];
        const cleanNgo = String(ngoSlug || '').trim();
        const pseudo = getAffiliatePseudoId();
        if (cleanNgo) {
            parts.push(cleanNgo);
        }
        if (pseudo) {
            parts.push(pseudo);
        }
        return parts.join('~');
    }

    function parseSafeUrl(rawUrl) {
        try {
            return new URL(rawUrl);
        } catch (e) {
            return null;
        }
    }

    function isImpactshopInternalGoUrl(rawUrl) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return false;
        }
        const host = String(parsed.hostname || '').toLowerCase();
        const currentHost = String(window.location.hostname || '').toLowerCase();
        if (host !== currentHost && host !== 'app.sharity.hu' && host !== 'sharity.hu' && host !== 'www.sharity.hu') {
            return false;
        }
        return /^\/go(?:-deal)?(?:\/|$)/i.test(parsed.pathname || '');
    }

    function ensureInternalGoParams(rawUrl, ngoSlug) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return rawUrl;
        }
        const cleanNgo = String(ngoSlug || '').trim();
        if (cleanNgo && !parsed.searchParams.get('d1')) {
            parsed.searchParams.set('d1', cleanNgo);
        }
        return parsed.toString();
    }

    function decorateCjAffiliateUrl(rawUrl, ngoSlug) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return rawUrl;
        }
        const sid = buildAffiliateSid(ngoSlug);
        if (sid) {
            parsed.searchParams.set('sid', sid);
        }
        return parsed.toString();
    }

    function isDognetAffiliateUrl(rawUrl) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return false;
        }
        return /(^|\.)dognet\.(com|sk|hu)$/i.test(String(parsed.hostname || ''));
    }

    function decorateDognetAffiliateUrl(rawUrl, ngoSlug) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return rawUrl;
        }
        const cleanNgo = String(ngoSlug || '').trim();
        const pseudo = getAffiliatePseudoId();
        if (cleanNgo && !parsed.searchParams.get('d1')) {
            parsed.searchParams.set('d1', cleanNgo);
        }
        if (pseudo && !parsed.searchParams.get('data5')) {
            parsed.searchParams.set('data5', pseudo);
        }
        return parsed.toString();
    }

    function isGenericMerchantLandingUrl(rawUrl) {
        const parsed = parseSafeUrl(rawUrl);
        if (!parsed) {
            return false;
        }
        const pathname = String(parsed.pathname || '/').replace(/\/+$/, '') || '/';
        const segments = pathname.split('/').filter(Boolean);
        const hasQuery = String(parsed.search || '') !== '';
        const hasHash = String(parsed.hash || '') !== '';

        if (hasQuery || hasHash) {
            return false;
        }

        if (segments.length === 0) {
            return true;
        }

        // Locale-style homepages such as /hu or /hu/hun should use the safe base affiliate route.
        if (segments.length <= 2 && segments.every(function (segment) {
            return /^[a-z]{2,3}$/i.test(segment);
        })) {
            return true;
        }

        return false;
    }

    function buildFilloutUrl(targetUrl, shopSlug, ngoSlug) {
        const fallbackBase = 'https://form.fillout.com/t/eM61RLkz6jus';
        let rawBase = String(config.filloutFormId || '').trim();
        if (!rawBase) {
            rawBase = fallbackBase;
        }
        let base = rawBase;
        if (!/^https?:\/\//i.test(base)) {
            base = `https://form.fillout.com/t/${base}`;
        }
        const params = new URLSearchParams();
        if (shopSlug) {
            params.set('shop', shopSlug);
        }
        if (ngoSlug) {
            params.set('d1', ngoSlug);
        }
        if (targetUrl) {
            params.set('u', safeBtoa(targetUrl));
        }
        const buildWithBase = (baseUrl) => {
            if (!baseUrl) {
                return '';
            }
            const query = params.toString();
            if (!query) {
                return baseUrl;
            }
            const joiner = baseUrl.includes('?') ? '&' : '?';
            return `${baseUrl}${joiner}${query}`;
        };
        try {
            const url = new URL(base);
            params.forEach((value, key) => {
                url.searchParams.set(key, value);
            });
            return url.toString();
        } catch (e) {
            if (base !== fallbackBase) {
                try {
                    const url = new URL(fallbackBase);
                    params.forEach((value, key) => {
                        url.searchParams.set(key, value);
                    });
                    return url.toString();
                } catch (err) {
                    return buildWithBase(base || fallbackBase);
                }
            }
            return buildWithBase(base || fallbackBase);
        }
    }

    function shouldUseFillout(ngoSlug) {
        return !ngoSlug || String(ngoSlug).trim() === '';
    }

    function resolveFilloutUrl(targetUrl, shopSlug, ngoSlug) {
        if (!targetUrl) {
            return targetUrl;
        }
        let cleanSlug = String(shopSlug || '');
        if (cleanSlug.startsWith('sync:')) {
            cleanSlug = cleanSlug.substring(5);
        }
        const normalizedSlug = cleanSlug.toLowerCase();
        const resolvedTarget = normalizeTargetUrl(targetUrl);
        const trackedTarget = normalizedSlug.includes('arukereso')
            ? buildArukeresoTrackedUrl(resolvedTarget, config.arukeresoDognetBase || '', ngoSlug)
            : resolvedTarget;
        if (!shouldUseFillout(ngoSlug)) {
            return trackedTarget;
        }
        return buildFilloutUrl(trackedTarget, cleanSlug, ngoSlug) || trackedTarget;
    }

    function parseQueryFromUrl(url) {
        try {
            const parsed = new URL(url);
            const params = {};
            parsed.searchParams.forEach((value, key) => {
                params[key] = value;
            });
            return params;
        } catch (e) {
            return {};
        }
    }

    function arukeresoIsHost(hostname) {
        if (!hostname) {
            return false;
        }
        return /(^|\.)arukereso\.[a-z.]+$/i.test(String(hostname).toLowerCase());
    }

    function buildArukeresoTrackedUrl(productUrl, dognetBase, ngoSlug) {
        if (!productUrl) {
            return productUrl;
        }
        let target;
        try {
            target = new URL(productUrl);
        } catch (e) {
            return productUrl;
        }
        if (!arukeresoIsHost(target.hostname)) {
            return productUrl;
        }

        const baseParams = parseQueryFromUrl(dognetBase || '');
        const affKeys = ['a_aid', 'a_cid', 'a_bid', 'chan', 'chid', 'refid'];
        const merged = parseQueryFromUrl(productUrl);

        if (!merged.utm_source) merged.utm_source = 'dognet';
        if (!merged.utm_medium) merged.utm_medium = 'cpc';
        if (!merged.utm_campaign) {
            const tld = (target.hostname.split('.').pop() || '').toUpperCase();
            merged.utm_campaign = tld || 'HU';
        }

        affKeys.forEach((key) => {
            if (baseParams[key]) {
                merged[key] = baseParams[key];
                if (key === 'chid' && !merged.chan) {
                    merged.chan = baseParams[key];
                }
            }
        });

        if (ngoSlug) {
            merged.data1 = ngoSlug;
        }

        const query = new URLSearchParams(merged);
        return `${target.origin}${target.pathname}?${query.toString()}${target.hash || ''}`;
    }

    function buildAutoBannerFallbackImage() {
        return `${window.location.origin}/wp-content/uploads/impactshop/ngo-card-default.jpg`;
    }

    function applyAutoBannerImage($img, banner) {
        const imgEl = $img && $img.get(0);
        if (!imgEl) {
            return;
        }
        const fallback = buildAutoBannerFallbackImage();
        let rawUrl = banner.image_url || '';
        if (/^http:\/\//i.test(rawUrl)) {
            rawUrl = rawUrl.replace(/^http:\/\//i, 'https://');
        }
        const safeUrl = /^https:\/\//i.test(rawUrl) ? rawUrl : fallback;
        imgEl.onerror = function () {
            if (imgEl.dataset.fallbackApplied === '1') {
                return;
            }
            imgEl.dataset.fallbackApplied = '1';
            imgEl.src = fallback;
        };
        imgEl.dataset.fallbackApplied = '0';
        imgEl.src = safeUrl;
    }

    function transformBannerUrl(bannerUrl, shopSlug, ngoSlug) {
        console.log('[AutoBanner][DEBUG] transformBannerUrl called', {
            bannerUrl: bannerUrl,
            shopSlug: shopSlug,
            ngoSlug: ngoSlug,
            filloutFormId: config.filloutFormId
        });
        if (!bannerUrl) {
            console.log('[AutoBanner][DEBUG] transformBannerUrl: no bannerUrl, returning as-is');
            return bannerUrl;
        }
        const targetUrl = normalizeTargetUrl(bannerUrl) || bannerUrl;
        if (!shopSlug) {
            if (shouldUseFillout(ngoSlug)) {
                const filloutFallback = buildFilloutUrl(targetUrl, '', ngoSlug);
                if (filloutFallback) {
                    console.log('[AutoBanner][DEBUG] transformBannerUrl: no shopSlug, using fillout fallback:', filloutFallback);
                    return filloutFallback;
                }
            }
            if (targetUrl && targetUrl !== bannerUrl) {
                console.log('[AutoBanner][DEBUG] transformBannerUrl: no shopSlug, using normalized targetUrl:', targetUrl);
                return targetUrl;
            }
            console.log('[AutoBanner][DEBUG] transformBannerUrl: no shopSlug, returning bannerUrl as-is:', bannerUrl);
            return bannerUrl;
        }
        // Strip sync: prefix from shop slug (harvester-synced banners)
        let cleanSlug = shopSlug;
        if (cleanSlug.startsWith('sync:')) {
            cleanSlug = cleanSlug.substring(5);
        }
        const normalizedSlug = String(cleanSlug || '').toLowerCase();
        const isArukereso = normalizedSlug.includes('arukereso');
        const trackedTarget = isArukereso
            ? buildArukeresoTrackedUrl(targetUrl, config.arukeresoDognetBase || '', ngoSlug)
            : targetUrl;
        const filloutUrl = shouldUseFillout(ngoSlug)
            ? buildFilloutUrl(trackedTarget, cleanSlug, ngoSlug)
            : '';
        console.log('[AutoBanner][DEBUG] transformBannerUrl fillout result', {
            targetUrl: targetUrl,
            trackedTarget: trackedTarget,
            filloutUrl: filloutUrl,
            isDifferent: filloutUrl !== trackedTarget,
            willReturnFillout: filloutUrl && filloutUrl !== trackedTarget
        });
        if (filloutUrl && filloutUrl !== trackedTarget) {
            return filloutUrl;
        }
        if (isArukereso) {
            return trackedTarget;
        }
        if (normalizedSlug.startsWith('cj-')) {
            return decorateCjAffiliateUrl(trackedTarget, ngoSlug);
        }
        if (isDognetAffiliateUrl(trackedTarget)) {
            return decorateDognetAffiliateUrl(trackedTarget, ngoSlug);
        }
        if (isImpactshopInternalGoUrl(trackedTarget)) {
            return ensureInternalGoParams(trackedTarget, ngoSlug);
        }
        if (ngoSlug && isGenericMerchantLandingUrl(trackedTarget)) {
            const params = new URLSearchParams({
                d1: ngoSlug || '',
            });
            return `${window.location.origin}/go/${encodeURIComponent(cleanSlug)}?${params.toString()}`;
        }
        const base = `${window.location.origin}/go-deal/${encodeURIComponent(cleanSlug)}`;
        const params = new URLSearchParams({
            d1: ngoSlug || '',
            u: safeBtoa(targetUrl),
        });
        return `${base}?${params.toString()}`;
    }

    function refreshAutoBannerLink(reason) {
        const banner = state.currentAutoBanner;
        if (!banner) {
            return;
        }
        const $banner = $('[data-role=auto-banner]');
        if (!$banner.length) {
            return;
        }
        const ngoSlug = state.selectedNgo ? state.selectedNgo.slug : '';
        const finalUrl = transformBannerUrl(
            banner.banner_url || '',
            banner.shop_slug || '',
            ngoSlug
        );
        const $link = $banner.find('[data-role=auto-banner-link]');
        $link.attr('href', finalUrl || '#').attr('target', '_blank').attr('rel', 'noopener');
        const payload = $banner.data('cta-payload') || {};
        if (finalUrl) {
            payload.cta_url = finalUrl;
            payload.dedupe_key = buildCtaDedupe(
                payload.content_type || 'auto_banner',
                payload.content_id || banner.id || '',
                finalUrl
            );
        }
        $banner.data('cta-payload', payload);
        console.log('[AutoBanner][DEBUG] refreshAutoBannerLink', {
            reason: reason || '',
            ngoSlug: ngoSlug,
            finalUrl: finalUrl,
            isFillout: finalUrl && finalUrl.includes('fillout.com')
        });
    }

    function showAutoBannerContent(banner, cta, contentId, ttlSeconds) {
        const $banner = $('[data-role=auto-banner]');
        if (!$banner.length) {
            state.isPlaying = false;
            updateWatchButton();
            showLoading(false);
            return;
        }

        if (!banner || !banner.banner_url) {
            $banner.prop('hidden', true);
            state.isPlaying = false;
            updateWatchButton();
            showLoading(false);
            return;
        }

        const bannerId = contentId || banner.id || '';
        const ctaPoints = Number((cta && cta.points) || 1);
        state.currentAutoBanner = banner || null;
        // Reset CTA tracking for new banner so bonus can be earned again
        state.ctaClicked = false;
        const finalUrl = transformBannerUrl(
            banner.banner_url || '',
            banner.shop_slug || '',
            state.selectedNgo ? state.selectedNgo.slug : ''
        );
        const ctaDedupe = (cta && cta.dedupe_key) ? cta.dedupe_key : buildCtaDedupe('auto_banner', bannerId, finalUrl);

        $banner.prop('hidden', false);
        $banner.find('[data-role=auto-banner-title]').text(banner.title || '');
        const $bannerImg = $banner.find('[data-role=auto-banner-image]');
        $bannerImg.attr('alt', banner.title || '');
        applyAutoBannerImage($bannerImg, banner);
        $banner.find('[data-role=auto-banner-prices]').text(formatPriceLabel(banner));
        $banner.find('[data-role=auto-banner-link]')
            .attr('href', finalUrl || '#')
            .attr('target', '_blank')
            .attr('rel', 'noopener');
        console.log('[AutoBanner][DEBUG] showAutoBannerContent set link', {
            finalUrl: finalUrl,
            isFillout: finalUrl && finalUrl.includes('fillout.com'),
            shopSlug: banner.shop_slug,
            bannerUrl: banner.banner_url,
            ngoSlug: state.selectedNgo ? state.selectedNgo.slug : '(none)',
            hrefAfterSet: $banner.find('[data-role=auto-banner-link]').attr('href'),
            targetAfterSet: $banner.find('[data-role=auto-banner-link]').attr('target')
        });
        $banner.data('cta-payload', {
            content_type: 'auto_banner',
            content_id: bannerId,
            cta_url: finalUrl || '',
            raw_url: banner.banner_url || '',
            shop_slug: banner.shop_slug || '',
            category: banner.category || '',
            price_range: banner.price_range || '',
            points: ctaPoints,
            dedupe_key: ctaDedupe
        });

        showLoading(false);
        showVideoInfoPanel('auto_banner', {
            title: banner.title || 'Akciós ajánlat',
            watchReward: '+1 pont, +1 szavazat',
            clickReward: '+5 pont, +5 szavazat'
        });
        startBannerProgress($banner, {
            duration: Math.max(5, Number(ttlSeconds || 5)) * 1000,
            onComplete: function () {
                $banner.prop('hidden', true);
                // Award points and votes for banner view completion
                handleAdCompletion(true, 1);
                state.isPlaying = false;
                updateWatchButton();
                showLoading(false);
            }
        });
    }

    function loadAutoBanner() {
        const $banner = $('[data-role=auto-banner]');
        if (!$banner.length) {
            return;
        }

        const params = new URLSearchParams();
        params.set('ts', String(Date.now()));
        if (state.pseudoId) {
            params.set('pseudo_id', state.pseudoId);
        }

        $.ajax({
            url: `${autoBannerUrl}/next?${params.toString()}`,
            method: 'GET',
            dataType: 'json',
            timeout: 8000
        }).done(function (response) {
            const banner = response && response.banner ? response.banner : null;
            if (!banner || !banner.banner_url) {
                $banner.prop('hidden', true);
                return;
            }

            state.currentAutoBanner = banner;
            const finalUrl = transformBannerUrl(
                banner.banner_url || '',
                banner.shop_slug || '',
                state.selectedNgo ? state.selectedNgo.slug : ''
            );
            $banner.prop('hidden', false);
            $banner.find('[data-role=auto-banner-title]').text(banner.title || '');
            const $bannerImg = $banner.find('[data-role=auto-banner-image]');
            $bannerImg.attr('alt', banner.title || '');
            applyAutoBannerImage($bannerImg, banner);
            $banner.find('[data-role=auto-banner-prices]').text(formatPriceLabel(banner));
            $banner.find('[data-role=auto-banner-link]')
                .attr('href', finalUrl || '#')
                .attr('target', '_blank')
                .attr('rel', 'noopener');
            $banner.data('cta-payload', {
                content_type: 'auto_banner',
                content_id: banner.id || '',
                cta_url: finalUrl || '',
                raw_url: banner.banner_url || '',
                shop_slug: banner.shop_slug || '',
                category: '',
                price_range: '',
                points: 5,
                dedupe_key: buildCtaDedupe('auto_banner', banner.id || '', finalUrl || '')
            });

            showVideoInfoPanel('auto_banner', {
                title: banner.title || 'Akciós ajánlat',
                watchReward: '+1 pont, +1 szavazat',
                clickReward: '+5 pont, +5 szavazat'
            });
            startBannerProgress($banner, {
                duration: 5000,
                onComplete: function () {
                    state.currentAutoBanner = null;
                    $banner.prop('hidden', true);
                    updateWatchButton();
                    hideVideoInfoPanel();
                }
            });
        }).fail(function () {
            $banner.prop('hidden', true);
        });
    }

    function startBannerProgress($banner, options) {
        const $progress = $banner.find('[data-role=auto-banner-progress]');
        if (!$progress.length) {
            return;
        }
        $progress.css('width', '0%');
        const safeOptions = options && typeof options === 'object' ? options : {};
        const duration = Math.max(1000, Number(safeOptions.duration || 15000));
        const onComplete = typeof safeOptions.onComplete === 'function' ? safeOptions.onComplete : null;
        const start = Date.now();
        const step = function () {
            const elapsed = Date.now() - start;
            const ratio = Math.min(1, elapsed / duration);
            $progress.css('width', `${(ratio * 100).toFixed(1)}%`);
            if (ratio < 1) {
                requestAnimationFrame(step);
            } else if (onComplete) {
                onComplete();
            }
        };
        requestAnimationFrame(step);
    }

    function updateAutoBannerLink() {
        const $banner = $('[data-role=auto-banner]');
        if (!$banner.length || !$banner.is(':visible')) {
            return;
        }
        if (!state.currentAutoBanner) {
            return;
        }
        const finalUrl = transformBannerUrl(
            state.currentAutoBanner.banner_url || '',
            state.currentAutoBanner.shop_slug || '',
            state.selectedNgo ? state.selectedNgo.slug : ''
        );
        if (!finalUrl) {
            return;
        }
        const bannerId = state.currentAutoBanner.id || '';
        $banner.find('[data-role=auto-banner-link]')
            .attr('href', finalUrl)
            .attr('target', '_blank')
            .attr('rel', 'noopener');
        $banner.data('cta-payload', {
            content_type: 'auto_banner',
            content_id: bannerId,
            cta_url: finalUrl,
            raw_url: state.currentAutoBanner.banner_url || '',
            shop_slug: state.currentAutoBanner.shop_slug || '',
            category: state.currentAutoBanner.category || '',
            price_range: state.currentAutoBanner.price_range || '',
            points: 5,
            dedupe_key: buildCtaDedupe('auto_banner', bannerId, finalUrl || '')
        });
    }

    function formatPriceLabel(banner) {
        const priceNew = Number(banner.price_new || 0);
        let priceOld = Number(banner.price_old || 0);
        const discount = Number(banner.discount_percent || 0);
        
        // If we have discount but no old price, calculate it
        if (priceOld === 0 && priceNew > 0 && discount > 0) {
            // new_price = old_price * (1 - discount/100)
            // old_price = new_price / (1 - discount/100)
            priceOld = Math.round(priceNew / (1 - discount / 100));
        }
        
        if (priceNew > 0 && priceOld > 0 && priceOld !== priceNew) {
            return `${priceOld.toLocaleString('hu-HU')} Ft → ${priceNew.toLocaleString('hu-HU')} Ft`;
        }
        if (priceNew > 0) {
            return `${priceNew.toLocaleString('hu-HU')} Ft`;
        }
        if (discount > 0) {
            return `-${discount}% kedvezmény`;
        }
        return 'Ajánlat';
    }

    function getCtaFallbackReward(pointsHint) {
        const enabled = Number(pointsHint || 0) > 0;
        return {
            points: enabled ? 5 : 0,
            votes: enabled ? 5 : 0
        };
    }

    function applyCtaTrackingReward(response, fallbackReward) {
        const safeResponse = response && typeof response === 'object' ? response : null;
        const hasAwardedPoints = !!(safeResponse && Object.prototype.hasOwnProperty.call(safeResponse, 'awarded_points'));
        const hasAwardedVotes = !!(safeResponse && Object.prototype.hasOwnProperty.call(safeResponse, 'awarded_votes'));

        let awardedPoints = hasAwardedPoints
            ? Number(safeResponse.awarded_points)
            : Number((fallbackReward && fallbackReward.points) || 0);
        let awardedVotes = hasAwardedVotes
            ? Number(safeResponse.awarded_votes)
            : Number((fallbackReward && fallbackReward.votes) || 0);

        if (!Number.isFinite(awardedPoints) || awardedPoints < 0) {
            awardedPoints = 0;
        }
        if (!Number.isFinite(awardedVotes) || awardedVotes < 0) {
            awardedVotes = 0;
        }
        awardedPoints = Math.round(awardedPoints);
        awardedVotes = Math.round(awardedVotes);

        const hasServerPointsTotal = !!(safeResponse && typeof safeResponse.new_total === 'number' && Number.isFinite(safeResponse.new_total));
        const hasServerVotes = !!(safeResponse && typeof safeResponse.available_votes === 'number' && Number.isFinite(safeResponse.available_votes));

        if (hasServerPointsTotal) {
            state.points = Math.max(0, Math.round(Number(safeResponse.new_total)));
        } else if (awardedPoints > 0) {
            state.points = Math.max(0, Math.round(Number(state.points || 0))) + awardedPoints;
        }

        if (hasServerVotes) {
            state.availableVotes = Math.max(0, Math.round(Number(safeResponse.available_votes)));
        } else if (awardedVotes > 0) {
            state.availableVotes = Math.max(0, Math.round(Number(state.availableVotes || 0))) + awardedVotes;
        }

        if (awardedPoints > 0 || awardedVotes > 0) {
            state.ctaBonusPoints = Number(state.ctaBonusPoints || 0) + awardedPoints;
            state.ctaBonusVotes = Number(state.ctaBonusVotes || 0) + awardedVotes;
        }

        const shouldDeferVisibleCtaReward = state.isPlaying && (
            awardedPoints > 0
            || awardedVotes > 0
            || hasServerPointsTotal
            || hasServerVotes
        );

        if (awardedPoints > 0 || awardedVotes > 0 || hasServerPointsTotal || hasServerVotes) {
            if (shouldDeferVisibleCtaReward) {
                state.ctaUiDeferred = true;
            } else {
                updateStatusDisplay();
                updateVoteControls();
                notifyPointsUpdated();
            }
        }
    }

    function sendCtaTracking(payload, options) {
        if (!payload || !payload.content_type) {
            return $.Deferred().reject({ status: 'invalid_payload' }).promise();
        }
        const safeOptions = options && typeof options === 'object' ? options : {};
        const fallbackReward = safeOptions.fallbackReward || { points: 0, votes: 0 };
        const body = {
            content_type: payload.content_type,
            content_id: payload.content_id || '',
            cta_url: payload.cta_url || '',
            shop_slug: payload.shop_slug || '',
            category: payload.category || '',
            price_range: payload.price_range || '',
            points: Number(payload.points || 0),
            dedupe_key: payload.dedupe_key || ''
        };
        const headers = {};
        if (restNonce) {
            headers['X-WP-Nonce'] = restNonce;
        }
        return $.ajax({
            url: '/wp-json/impact/v1/tracking/cta-click',
            method: 'POST',
            data: JSON.stringify(body),
            contentType: 'application/json',
            dataType: 'json',
            timeout: 8000,
            headers: headers,
            xhrFields: { withCredentials: true }
        })
            .done(function (response) {
                applyCtaTrackingReward(response, fallbackReward);
            })
            .fail(function (xhr) {
                console.error('[CTA] tracking failed', xhr);
            });
    }

    function onAdsManagerLoaded(adsManagerLoadedEvent) {
        // Clear ad load timeout - ad loaded successfully
        if (state.adLoadTimeout) {
            clearTimeout(state.adLoadTimeout);
            state.adLoadTimeout = null;
        }

        const loadTime = state.adRequestStartTime ? Date.now() - state.adRequestStartTime : 0;
        console.log('[IMA] AdsManager loaded successfully in ' + loadTime + 'ms');
        state.adRequestPending = false;

        const videoElement = document.getElementById('content-video');
        if (!videoElement) {
            console.error('[IMA] content-video element not found during AdsManager init');
        }

        const adsRenderingSettings = new google.ima.AdsRenderingSettings();
        adsRenderingSettings.restoreCustomPlaybackStateOnAdBreakComplete = true;

        state.adsManager = adsManagerLoadedEvent.getAdsManager(videoElement, adsRenderingSettings);

        state.adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_PAUSE_REQUESTED, onContentPauseRequested);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.CONTENT_RESUME_REQUESTED, onContentResumeRequested);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.ALL_ADS_COMPLETED, onAllAdsCompleted);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.STARTED, onAdStarted);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.FIRST_QUARTILE, onAdProgress);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.MIDPOINT, onAdProgress);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.THIRD_QUARTILE, onAdProgress);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.COMPLETE, onAdComplete);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.SKIPPED, onAdSkipped);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.CLICK, onAdClick);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.PAUSED, onAdPaused);
        state.adsManager.addEventListener(google.ima.AdEvent.Type.RESUMED, onAdResumed);
        state.adsManager.addEventListener(google.ima.AdErrorEvent.Type.AD_ERROR, onAdError);

        try {
            const container = document.getElementById('video-container');
            state.adsManager.init(container.clientWidth, container.clientHeight, google.ima.ViewMode.NORMAL);
            state.adsManager.start();
            showLoading(false);
            $('#player-overlay').hide();
        } catch (adError) {
            onAdError({ getError: () => ({ getMessage: () => adError.message }) });
        }
    }

    function onContentPauseRequested() {
        const videoElement = document.getElementById('content-video');
        if (videoElement) {
            videoElement.pause();
        }
    }

    function onContentResumeRequested() {
        // no-op
    }

    function stopImaProgressLoop() {
        if (state.imaProgressFrameId) {
            cancelAnimationFrame(state.imaProgressFrameId);
            state.imaProgressFrameId = null;
        }
    }

    function startImaProgressLoop() {
        stopImaProgressLoop();
        function tick() {
            if (!state.adsManager || !state.isPlaying) {
                state.imaProgressFrameId = null;
                return;
            }
            try {
                const remaining = state.adsManager.getRemainingTime();
                if (state.imaAdDuration > 0 && remaining >= 0) {
                    const elapsed = state.imaAdDuration - remaining;
                    state.adProgress = Math.min(1, Math.max(0, elapsed / state.imaAdDuration));
                    updateAdProgressBar();
                }
            } catch (e) {
                // adsManager may throw if destroyed
            }
            state.imaProgressFrameId = requestAnimationFrame(tick);
        }
        state.imaProgressFrameId = requestAnimationFrame(tick);
    }

    function onAdStarted(adEvent) {
        if (!state.currentAdType) {
            state.currentAdType = 'regular';
        }
        let clickUrl = '';
        try {
            const ad = adEvent.getAd();
            if (ad && typeof ad.getClickThroughUrl === 'function') {
                clickUrl = String(ad.getClickThroughUrl() || '');
            }
        } catch (e) {
            clickUrl = '';
        }
        showImaCtaOverlay(clickUrl);
        state.adProgress = 0;
        state.imaAdDuration = 0;
        try {
            const ad = adEvent.getAd();
            if (ad && typeof ad.getDuration === 'function') {
                state.imaAdDuration = ad.getDuration() || 0;
            }
        } catch (e) {}
        updateAdProgressBar();
        startImaProgressLoop();
    }

    function onAdProgress(adEvent) {
        // Quartile events are now only a backup; primary progress via startImaProgressLoop
        const type = adEvent && adEvent.type ? adEvent.type : '';
        if (type === google.ima.AdEvent.Type.FIRST_QUARTILE) {
            state.adProgress = Math.max(state.adProgress, 0.25);
        } else if (type === google.ima.AdEvent.Type.MIDPOINT) {
            state.adProgress = Math.max(state.adProgress, 0.5);
        } else if (type === google.ima.AdEvent.Type.THIRD_QUARTILE) {
            state.adProgress = Math.max(state.adProgress, 0.75);
        }
        updateAdProgressBar();
    }

    function onAdComplete() {
        stopImaProgressLoop();
        hideImaCtaOverlay();
        hideResumeButton();
        state.adProgress = 1;
        updateAdProgressBar();
        handleAdCompletion(true, state.adProgress);
    }

    function onAdSkipped() {
        stopImaProgressLoop();
        hideImaCtaOverlay();
        hideResumeButton();
        showNotification('A reklámot át kell nézni a pontokért!', 'warning');
    }

    function onAdPaused() {
        console.log('[IMA] Ad paused - showing resume button');
        showResumeButton();
    }

    function onAdResumed() {
        console.log('[IMA] Ad resumed - hiding resume button');
        hideResumeButton();
    }

    function showResumeButton() {
        $('#btn-resume-ad').show();
    }

    function hideResumeButton() {
        $('#btn-resume-ad').hide();
    }

    function resumeImaAd() {
        if (state.adsManager) {
            try {
                state.adsManager.resume();
                console.log('[IMA] Manually resumed ad');
                hideResumeButton();
            } catch (e) {
                console.log('[IMA] Could not resume ad:', e);
            }
        }
    }

    function onAdClick() {
        // Only award bonus once per ad
        if (state.ctaClicked) {
            console.log('[IMA] Ad already clicked - skipping bonus');
            return;
        }
        state.ctaClicked = true;
        
        // User clicked on IMA ad - award bonus points (5 pts + 5 votes)
        console.log('[IMA] Ad clicked - awarding CTA bonus');
        const ctaPointsHint = Math.max(0, Math.round(Number(state.currentCtaPoints || 0)));
        sendCtaTracking({
            content_type: state.currentMode || 'ad',
            content_id: '',
            cta_url: state.imaClickThroughUrl || '',
            shop_slug: '',
            category: '',
            price_range: '',
            points: ctaPointsHint,
            dedupe_key: 'ima_click:' + state.pseudoId + ':' + Date.now()
        }, {
            fallbackReward: getCtaFallbackReward(ctaPointsHint)
        });
        
        // Resume ad playback after click (IMA pauses on click)
        setTimeout(function() {
            if (state.adsManager) {
                try {
                    state.adsManager.resume();
                    console.log('[IMA] Resumed ad after click');
                } catch (e) {
                    console.log('[IMA] Could not resume ad:', e);
                }
            }
        }, 500);
    }

    function onAllAdsCompleted() {
        stopImaProgressLoop();
        hideImaCtaOverlay();
        hideResumeButton();
        resetPlayer(true); // Keep progress bar visible after successful completion
    }

    function onAdError(adErrorEvent) {
        // Clear ad load timeout
        if (state.adLoadTimeout) {
            clearTimeout(state.adLoadTimeout);
            state.adLoadTimeout = null;
        }
        state.adRequestPending = false;
        stopImaProgressLoop();
        hideImaCtaOverlay();
        hideResumeButton();

        // Extract detailed error info from IMA SDK
        let errorMessage = 'Unknown error';
        let errorCode = 0;
        let errorType = 'unknown';
        let vastErrorCode = 0;
        let innerError = null;

        try {
            if (adErrorEvent && typeof adErrorEvent.getError === 'function') {
                const imaError = adErrorEvent.getError();
                errorMessage = imaError.getMessage ? imaError.getMessage() : errorMessage;
                errorCode = imaError.getErrorCode ? imaError.getErrorCode() : 0;
                errorType = imaError.getType ? imaError.getType() : 'unknown';
                vastErrorCode = imaError.getVastErrorCode ? imaError.getVastErrorCode() : 0;
                innerError = imaError.getInnerError ? imaError.getInnerError() : null;
            }
        } catch (e) {
            console.warn('[IMA] Could not extract error details:', e);
        }

        console.error('[IMA] Ad error details:', {
            message: errorMessage,
            code: errorCode,
            type: errorType,
            vastErrorCode: vastErrorCode,
            innerError: innerError,
            vastTagUrl: state.pendingAdTagUrl || state.defaultAdTagUrl
        });

        // Common IMA error codes:
        // 400 = VAST malformed response
        // 900 = Undefined error
        // 1009 = VAST response empty
        // 301 = VAST redirect timeout
        // 302 = VAST wrapper limit reached
        if (vastErrorCode === 303 || vastErrorCode === 1009) {
            console.warn('[IMA] No ads available (VAST empty/no ads) - this is normal if no inventory');
        }

        showLoading(false);
        showNotification(i18n.adError || 'Nem sikerült betölteni a reklámot', 'error');
        hideAdProgressBar();
        resetPlayer();
    }

    function handleAdCompletion(success, completionRatio = 1) {
        if (!success) {
            return;
        }

        const adType = state.currentAdType || 'regular';
        const sponsorId = state.currentSponsorId || 0;

        recordAdView(adType, sponsorId, completionRatio)
            .done(function (viewResponse) {
                const prevAvailable = Number(state.availableVotes || 0);
                let points = viewResponse.points || 0;
                let votes = viewResponse.votes || 0;
                
                // Add CTA bonus if clicked during this video
                const ctaBonusPoints = Math.max(0, Number(state.ctaBonusPoints || 0));
                const ctaBonusVotes = Math.max(0, Number(state.ctaBonusVotes || 0));
                
                if (typeof viewResponse.new_total === 'number') {
                    state.points = Math.max(
                        Math.max(0, Math.round(Number(state.points || 0))),
                        Math.max(0, Math.round(Number(viewResponse.new_total)))
                    );
                } else {
                    state.points = state.points + points;
                }
                if (typeof viewResponse.available_votes === 'number') {
                    state.availableVotes = Math.max(
                        Math.max(0, Math.round(Number(state.availableVotes || 0))),
                        Math.max(0, Math.round(Number(viewResponse.available_votes)))
                    );
                } else {
                    state.availableVotes = state.availableVotes + votes;
                }

                updateStatusDisplay();
                updateVoteControls();
                
                // Store base video reward for combined display at CTA click
                state.lastVideoRewardPoints = points;
                state.lastVideoRewardVotes = votes;
                
                const displayPoints = points + ctaBonusPoints;
                const displayVotes = votes + ctaBonusVotes;
                state.ctaUiDeferred = false;

                if (displayPoints > 0 || displayVotes > 0) {
                    showRewardAnimation(displayPoints, displayVotes);
                } else {
                    showNotification('A megtekintést már rögzítettük.', 'warning');
                }
                
                hideCtaStickyNotice();
                trackEvent('ads_watch_view_complete', {
                    ad_type: adType,
                    sponsor_id: sponsorId,
                    points: points,
                    votes: votes,
                    cta_bonus_points: ctaBonusPoints,
                    cta_bonus_votes: ctaBonusVotes
                });
                notifyPointsUpdated();

                const addedVotes = Math.max(0, Number(state.availableVotes) - prevAvailable);
                if (state.autoVote && addedVotes > 0 && state.selectedNgo) {
                    autoAllocateVotes(addedVotes);
                }

                setTimeout(function () {
                    loadTally();
                }, 1000);
            })
            .fail(function (xhr) {
                console.error('View recording failed:', xhr);
                showNotification('Nem sikerült rögzíteni a megtekintést. Próbáld újra.', 'error');
            });
    }

    function resetPlayer(keepProgressBar) {
        // Clear ad load timeout
        if (state.adLoadTimeout) {
            clearTimeout(state.adLoadTimeout);
            state.adLoadTimeout = null;
        }
        state.adRequestPending = false;
        stopImaProgressLoop();
        state.isPlaying = false;
        state.currentAdType = 'regular';
        state.currentSponsorId = 0;
        state.pendingAdTagUrl = '';
        state.currentMode = 'regular';
        state.currentCtaPoints = 0;
        state.imaAdDuration = 0;
        state.ctaBonusPoints = 0;
        state.ctaBonusVotes = 0;
        state.ctaUiDeferred = false;
        updateWatchButton();
        $('#player-overlay').fadeIn(200);
        showLoading(false);
        if (!keepProgressBar) {
            hideAdProgressBar();
        }
        updateCta('', '', null);
        resetEducationState();
        hideVideoInfoPanel();
        hideImaCtaOverlay();
        hideCtaStickyNotice();
        const adContainer = document.getElementById('ad-container');
        if (adContainer) {
            adContainer.style.display = '';
        }

        if (state.adsManager) {
            state.adsManager.destroy();
            state.adsManager = null;
        }

        // Reset adsLoader for next ad request
        if (state.adsLoader) {
            try {
                state.adsLoader.contentComplete();
            } catch (e) {
                // Ignore if already completed
            }
        }
    }

    function showImaCtaOverlay(clickUrl) {
        const $overlay = $('#ima-cta-overlay');
        if (!$overlay.length) {
            return;
        }
        state.imaClickThroughUrl = clickUrl || '';
        // Always show CTA overlay for IMA ads (even without clickUrl)
        // The whole video is clickable anyway, but we want to show the button
        $overlay.show();
    }

    function hideImaCtaOverlay() {
        state.imaClickThroughUrl = '';
        $('#ima-cta-overlay').hide();
    }

    function updateAdProgressBar() {
        const $bar = $('#ad-progress-bar');
        const $fill = $('#ad-progress-fill');
        const $meta = $('#ad-progress-meta');
        const $text = $('#ad-progress-text');
        if (!$bar.length || !$fill.length) return;
        const progressSource = state.currentMode === 'education'
            ? Number(state.educationProgress || 0)
            : Number(state.adProgress || 0);
        const rawPercent = Math.min(100, Math.max(0, progressSource * 100));
        const percent = Math.round(rawPercent);
        state.progressTarget = rawPercent;
        if (!state.progressAnimating) {
            state.progressAnimating = true;
            const animate = function () {
                const diff = state.progressTarget - state.progressDisplay;
                if (Math.abs(diff) < 0.2) {
                    state.progressDisplay = state.progressTarget;
                } else {
                    state.progressDisplay += diff * 0.18;
                }
                $fill.css('width', state.progressDisplay.toFixed(1) + '%');
                if (state.progressDisplay !== state.progressTarget) {
                    requestAnimationFrame(animate);
                } else {
                    state.progressAnimating = false;
                }
            };
            requestAnimationFrame(animate);
        }
        $bar.show();
        if ($meta.length) {
            $meta.show();
        }
        if ($text.length) {
            if (percent >= 100) {
                $text.text('Videó teljesítve.');
            } else {
                $text.text('Eddig hitelesen: ' + percent + '%');
            }
        }
        // Keep progress bar visible after completion - don't hide it
    }

    function hideAdProgressBar() {
        $('#ad-progress-bar').hide();
        $('#ad-progress-fill').css('width', '0%');
        $('#ad-progress-meta').hide();
        state.progressTarget = 0;
        state.progressDisplay = 0;
        state.progressAnimating = false;
    }

    function showLoading(show) {
        if (show) {
            $('#player-loading').fadeIn(200);
        } else {
            $('#player-loading').fadeOut(200);
        }
    }

    function showRewardAnimation(points, votes) {
        // Reward popups are intentionally disabled.
        // Real-time feedback is shown by the animated in-player balance counters.
        return;
    }

    function showNotification(message, type = 'info') {
        const colors = {
            success: '#4caf50',
            error: '#f44336',
            warning: '#ff9800',
            info: '#2196f3',
        };

        const $notif = $(
            `<div class="impactshop-notification" style="
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${colors[type] || colors.info};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 100000;
                animation: slideIn 0.3s ease-out;
            ">
                ${escapeHtml(message)}
            </div>`
        );

        $('body').append($notif);

        setTimeout(function () {
            $notif.fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

    function showCtaStickyNotice(message) {
        let $notif = $('#impactshop-cta-sticky');
        if (!$notif.length) {
            $notif = $(
                `<div id="impactshop-cta-sticky" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 25px;
                    background: #4caf50;
                    color: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 100000;
                "></div>`
            );
            $('body').append($notif);
        }
        $notif.text(message).show();
    }

    function hideCtaStickyNotice() {
        const $notif = $('#impactshop-cta-sticky');
        if ($notif.length) {
            $notif.fadeOut(200, function () {
                $(this).remove();
            });
        }
    }

    function updateCampaignMessage(text) {
        if (!$messageText.length) return;
        const placeholder = 'Üzenet hamarosan...';
        const safeText = (text && String(text).trim()) ? String(text) : placeholder;
        $messageText.text(safeText);
        if ($messageCard.length) {
            if (!safeText || safeText === placeholder) {
                $messageCard.hide();
            } else {
                $messageCard.show();
            }
        }
    }

    function fetchCampaignMessage() {
        if (!$messageText.length) return;
        $.ajax({
            url: '/wp-json/impact/v1/identity/messages?ts=' + Date.now(),
            method: 'GET',
            cache: false
        }).done(function (data) {
            const messages = data && Array.isArray(data.messages) ? data.messages : [];
            if (!messages.length || !messages[0] || !messages[0].content) {
                updateCampaignMessage('');
                return;
            }
            updateCampaignMessage(messages[0].content);
        }).fail(function () {
            updateCampaignMessage('');
        });
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function formatCountdown(seconds) {
        const total = Math.max(0, Math.floor(seconds));
        const days = Math.floor(total / 86400);
        const hours = Math.floor((total % 86400) / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const parts = [];
        if (days > 0) {
            parts.push(`${days} nap`);
        }
        if (days > 0 || hours > 0) {
            parts.push(`${hours} óra`);
        }
        parts.push(`${minutes} perc`);
        return parts.join(' ');
    }

    function trackEvent(name, data) {
        if (typeof window.gtag === 'function') {
            window.gtag('event', name, data || {});
        }
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function getPseudoId() {
        const match = document.cookie.match(new RegExp('(^|; )' + 'impactshop_pseudo_id' + '=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    function getNgoCache() {
        try {
            const raw = localStorage.getItem(ngoCacheKey);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.items)) return null;
            if (!parsed.at || (Date.now() - parsed.at) > ngoCacheTtl) return null;
            return parsed.items;
        } catch (e) {
            return null;
        }
    }

    function setNgoCache(items) {
        try {
            localStorage.setItem(ngoCacheKey, JSON.stringify({
                at: Date.now(),
                items: items || []
            }));
        } catch (e) {
            // ignore
        }
    }

    function recordAdView(adType = 'regular', sponsorId = 0, completionRatio = 1) {
        return apiRequest('view', 'POST', {
            ad_type: adType,
            sponsor_id: sponsorId,
            pseudo_id: state.pseudoId,
            completion_ratio: completionRatio
        }, { retries: 3, retryDelay: 500 });
    }

})(jQuery);
