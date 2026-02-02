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
    const restNonce = config.restNonce || '';
    // Base VAST tag URL without correlator - correlator added dynamically per request
    const fallbackAdTagBase = 'https://pubads.g.doubleclick.net/gampad/ads?' +
        'iu=/21775744923/external/single_preroll_skippable&sz=640x480&' +
        'ciu_szs=300x250%2C728x90&gdfp_req=1&output=vast&' +
        'unviewed_position_start=1&env=vp&impl=s';
    const i18n = config.i18n || {};
    const ngoCacheKey = 'impactshop_ads_watch_ngos_v1';
    const ngoCacheTtl = 24 * 60 * 60 * 1000;
    const autoBannerUrl = config.autoBannerUrl || restUrl.replace(/\/ads-watch\/?$/, '/auto-banner');

    const state = {
        pseudoId: '',
        points: 0,
        level: 'basic',
        voteWeightRegular: 1,
        voteWeightSponsor: 5,
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
        ctaClicked: false,
        ctaClickedKeys: {},
        imaProgressFrameId: null,
        imaAdDuration: 0,
        imaClickThroughUrl: '',
        adLoadTimeout: null,
        adRequestPending: false,
        adRequestStartTime: 0
    };

    $(document).ready(function () {
        if ($('#impactshop-ads-watch').length === 0) {
            return;
        }

        initEventListeners();
        initIdentityBridge();
        initTabs();
        loadConfig();
        loadUserStatus();
        loadTally();
        if (!state.unifiedDisplay) {
            loadAutoBanner();
        }
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

    function initEventListeners() {
        $('#btn-watch-ad').on('click', startAdPlayback);
        $('#presence-confirm').on('click', confirmPresenceCheck);
        $('#btn-skip-education').on('click', skipEducationVideo);
        $('#btn-skip-video').on('click', skipCurrentVideo);
        // Note: IMA CTA overlay has pointer-events: none in CSS
        // Clicks pass through to ad-container, IMA SDK opens URL
        // Points are awarded via onAdClick callback (CLICK event listener)
        document.addEventListener('visibilitychange', handleVisibilityChange);

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

        $('#ngo-search-input').on('input', debounce(searchNgos, 300));

        $('#btn-show-all-ngos').on('click', openFullTallyModal);
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

        $('#ads-watch-cta-link').on('click', function () {
            if (!state.ctaMeta) return;
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
            });
            // Update available votes locally (CTA gives +5 votes)
            state.availableVotes = (state.availableVotes || 0) + 5;
            updateStatusDisplay();
            // Show feedback for CTA click
            showNotification('+5 pont és +5 szavazat a kattintásért!', 'success');
            state.ctaClicked = true;
        });

        $(document).on('click', '[data-role=auto-banner-link]', function () {
            const payload = $(this).closest('[data-role=auto-banner]').data('cta-payload');
            if (payload) {
                sendCtaTracking(payload);
                // Update available votes locally (CTA gives +5 votes)
                state.availableVotes = (state.availableVotes || 0) + 5;
                updateStatusDisplay();
                showNotification('+5 pont és +5 szavazat a kattintásért!', 'success');
                state.ctaClicked = true;
            }
        });
    }

    function initTabs() {
        const $tabs = $('[data-role=ads-watch-tabs]');
        const $tabButtons = $('[data-role=ads-watch-tab]');
        const $main = $('[data-role=ads-watch-main]');
        const $offerwall = $('#impactshop-offerwall');

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

        const showVideo = function () {
            $main.prop('hidden', false);
            $offerwall.prop('hidden', true);
            $offerwall.css('display', 'none');
            scrollToTarget('#ads-watch-video');
        };

        const showOfferwall = function () {
            $main.prop('hidden', true);
            $offerwall.prop('hidden', false);
            $offerwall.css('display', '');
            scrollToTarget('#impactshop-offerwall');
        };

        showVideo();
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
        apiRequest('status?ts=' + Date.now())
            .done(function (response) {
                if (!response || response.has_identity === false) {
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
                state.level = response.level || 'basic';
                state.voteWeightRegular = response.vote_weight_regular || 1;
                state.voteWeightSponsor = response.vote_weight_sponsor || 5;
                state.selectedNgo = response.selected_ngo || null;
                state.todayViews = response.today_views || 0;
                state.availableVotes = response.available_votes || 0;
                state.stats = response.stats || {};
                state.achievements = response.achievements || [];

                updateStatusDisplay();
                updateNgoDisplay();
                updateWatchButton();
                updateVoteControls();
            })
            .fail(function (xhr) {
                console.error('Failed to load user status:', xhr);
            });
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
        $('#user-points-display').text(formatNumber(state.points));
        $('#user-level-display').text(capitalizeFirst(state.level));
        $('#vote-weight-display').text(`×${state.voteWeightRegular}`);
        $('#available-votes-display').text(formatNumber(state.availableVotes));
        $('#available-votes-inline').text(formatNumber(state.availableVotes));
        const streakDays = Number(state.stats && state.stats.streak_days ? state.stats.streak_days : 0);
        $('#streak-display').text(streakDays > 0 ? `${streakDays} nap` : '-');
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

        if (!state.pseudoId) {
            $display.removeClass('has-ngo').html(`<span class="no-ngo-text">${escapeHtml(i18n.noIdentity || 'Azonosító szükséges a pontgyűjtéshez.')}</span>`);
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
        const tally = data.tally || [];

        $('#total-votes-display').text(formatNumber(data.total_votes || 0));
        $('#live-activity-value').text(`${formatNumber(data.total_votes || 0)} szavazat`);
        $('#chance-value').text(`${formatNumber(state.availableVotes)} / 10`);

        if (tally.length === 0) {
            $list.html('<div class="tally-empty">Még nincs szavazat.</div>');
            return;
        }

        let html = '';
        tally.forEach(function (item) {
            html += `
                <div class="tally-item rank-${item.rank}">
                    <div class="tally-rank">#${item.rank}</div>
                    <img src="${item.ngo_logo || '/wp-content/uploads/impactshop/ngo-card-default.jpg'}" alt="" class="tally-logo">
                    <div class="tally-name">${escapeHtml(item.ngo_name)}</div>
                    <div class="tally-votes">${formatNumber(item.votes)} szavazat<br><small>${item.percentage}%</small></div>
                    <div class="tally-amount">${formatNumber(item.amount)} Ft</div>
                </div>
            `;
        });

        $list.html(html);
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

                    state.pendingAdTagUrl = response.sponsor.vast_tag || state.defaultAdTagUrl;
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

        showNotification('Nincs elérhető edukációs videó.', 'warning');
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
            onAdError({ getError: () => ({ getMessage: () => 'No VAST tag URL configured' }) });
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
            $text.html(`<strong>+${ctaPoints} pont</strong> a hirdetésre kattintás után`);
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

        // Compact CTA - just icon, tooltip shows reward info
        $link.html('<span class="cta-icon">👆</span>');
        $link.attr('href', url);
        $link.attr('title', 'Kattints a bónusz pontokért!');
        $cta.show();
        if (meta && typeof meta === 'object') {
            state.ctaMeta = {
                content_type: String(meta.content_type || ''),
                content_id: String(meta.content_id || ''),
                sponsor_id: Number(meta.sponsor_id || 0),
                points: 5, // Always 5 points for CTA clicks
                dedupe_key: String(meta.dedupe_key || '')
            };
        }
    }

    function buildCtaDedupe(contentType, contentId, ctaUrl) {
        const pseudo = state.pseudoId || '';
        const safeType = String(contentType || 'cta');
        const safeId = String(contentId || '') || (ctaUrl ? String(ctaUrl).slice(-48) : 'unknown');
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

    function extractFilloutTarget(bannerUrl) {
        if (!bannerUrl) {
            return bannerUrl;
        }
        try {
            const url = new URL(bannerUrl);
            if (url.hostname.includes('fillout.com')) {
                const uParam = url.searchParams.get('u');
                if (uParam) {
                    return decodeURIComponent(escape(atob(uParam)));
                }
            }
        } catch (e) {
            return bannerUrl;
        }
        return bannerUrl;
    }

    function transformBannerUrl(bannerUrl, shopSlug, ngoSlug) {
        if (!bannerUrl) {
            return bannerUrl;
        }
        if (!ngoSlug) {
            return bannerUrl;
        }
        const targetUrl = extractFilloutTarget(bannerUrl) || bannerUrl;
        if (!shopSlug) {
            return bannerUrl;
        }
        // Strip sync: prefix from shop slug (harvester-synced banners)
        let cleanSlug = shopSlug;
        if (cleanSlug.startsWith('sync:')) {
            cleanSlug = cleanSlug.substring(5);
        }
        const normalizedSlug = String(cleanSlug || '').toLowerCase();
        if (normalizedSlug.includes('arukereso')) {
            const base = `${window.location.origin}/go`;
            const params = new URLSearchParams({
                shop: cleanSlug,
                d1: ngoSlug,
                src: 'ads-watch',
            });
            return `${base}?${params.toString()}`;
        }
        const base = `${window.location.origin}/go-deal/${encodeURIComponent(cleanSlug)}`;
        const params = new URLSearchParams({
            d1: ngoSlug,
            u: safeBtoa(targetUrl),
        });
        return `${base}?${params.toString()}`;
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
        const finalUrl = transformBannerUrl(
            banner.banner_url || '',
            banner.shop_slug || '',
            state.selectedNgo ? state.selectedNgo.slug : ''
        );
        const ctaDedupe = (cta && cta.dedupe_key) ? cta.dedupe_key : buildCtaDedupe('auto_banner', bannerId, finalUrl);

        $banner.prop('hidden', false);
        $banner.find('[data-role=auto-banner-title]').text(banner.title || '');
        $banner.find('[data-role=auto-banner-image]').attr('src', banner.image_url || '').attr('alt', banner.title || '');
        $banner.find('[data-role=auto-banner-prices]').text(formatPriceLabel(banner));
        $banner.find('[data-role=auto-banner-link]').attr('href', finalUrl || '#');
        $banner.data('cta-payload', {
            content_type: 'auto_banner',
            content_id: bannerId,
            cta_url: finalUrl || '',
            shop_slug: banner.shop_slug || '',
            category: banner.category || '',
            price_range: banner.price_range || '',
            points: ctaPoints,
            dedupe_key: ctaDedupe
        });

        showLoading(false);
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

        $.ajax({
            url: `${autoBannerUrl}/next?ts=${Date.now()}`,
            method: 'GET',
            dataType: 'json',
            timeout: 8000
        }).done(function (response) {
            const banner = response && response.banner ? response.banner : null;
            if (!banner || !banner.banner_url) {
                $banner.prop('hidden', true);
                return;
            }

            const finalUrl = transformBannerUrl(
                banner.banner_url || '',
                banner.shop_slug || '',
                state.selectedNgo ? state.selectedNgo.slug : ''
            );
            $banner.prop('hidden', false);
            $banner.find('[data-role=auto-banner-title]').text(banner.title || '');
            $banner.find('[data-role=auto-banner-image]').attr('src', banner.image_url || '').attr('alt', banner.title || '');
            $banner.find('[data-role=auto-banner-prices]').text(formatPriceLabel(banner));
            $banner.find('[data-role=auto-banner-link]').attr('href', finalUrl || '#');
            $banner.data('cta-payload', {
                content_type: 'auto_banner',
                content_id: banner.id || '',
                cta_url: finalUrl || '',
                shop_slug: banner.shop_slug || '',
                category: '',
                price_range: '',
                points: 1,
                dedupe_key: buildCtaDedupe('auto_banner', banner.id || '', finalUrl || '')
            });

            startBannerProgress($banner, { duration: 5000, onComplete: loadAutoBanner });
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

    function formatPriceLabel(banner) {
        const priceNew = Number(banner.price_new || 0);
        const priceOld = Number(banner.price_old || 0);
        const discount = Number(banner.discount_percent || 0);
        if (priceNew > 0 && priceOld > 0) {
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

    function sendCtaTracking(payload) {
        if (!payload || !payload.content_type) {
            return;
        }
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
        const url = '/wp-json/impact/v1/tracking/cta-click';
        try {
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(body)], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
                return;
            }
        } catch (e) {
            // ignore
        }
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        }).catch(function () {});
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
        state.adProgress = 1;
        updateAdProgressBar();
        handleAdCompletion(true, state.adProgress);
    }

    function onAdSkipped() {
        stopImaProgressLoop();
        hideImaCtaOverlay();
        showNotification('A reklámot át kell nézni a pontokért!', 'warning');
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
        sendCtaTracking({
            content_type: state.currentMode || 'ad',
            content_id: '',
            cta_url: state.imaClickThroughUrl || '',
            shop_slug: '',
            category: '',
            price_range: '',
            points: 5,
            dedupe_key: 'ima_click:' + state.pseudoId + ':' + Date.now()
        });
        // Update available votes locally (CTA gives +5 votes)
        state.availableVotes = (state.availableVotes || 0) + 5;
        updateStatusDisplay();
        showNotification('+5 pont és +5 szavazat a kattintásért!', 'success');
        
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
                const points = viewResponse.points || 0;
                const votes = viewResponse.votes || 0;
                if (typeof viewResponse.new_total === 'number') {
                    state.points = viewResponse.new_total;
                } else {
                    state.points = state.points + points;
                }
                if (typeof viewResponse.available_votes === 'number') {
                    state.availableVotes = viewResponse.available_votes;
                } else {
                    state.availableVotes = state.availableVotes + votes;
                }

                updateStatusDisplay();
                updateVoteControls();
                if (points > 0 || votes > 0) {
                    showRewardAnimation(points, votes);
                } else {
                    showNotification('A megtekintést már rögzítettük.', 'warning');
                }
                trackEvent('ads_watch_view_complete', {
                    ad_type: adType,
                    sponsor_id: sponsorId,
                    points: points,
                    votes: votes
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
        state.imaAdDuration = 0;
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
        const p = Number(points) || 0;
        const v = Number(votes) || 0;
        if (p <= 0 && v <= 0) {
            return;
        }
        const $anim = $('#reward-animation');
        $('#reward-points-value').text(p);
        $('#reward-votes-value').text(v);
        $('#reward-ngo-name').text('').hide();

        $anim.css('display', 'block');

        setTimeout(function () {
            $anim.fadeOut(500);
        }, 2500);
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

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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
