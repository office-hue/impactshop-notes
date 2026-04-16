(function ($) {
    'use strict';

    const DEFER_STATE = {
        armed: false,
        releasing: false,
        baseline: null,
        pendingReward: false,
    };

    const SELECTORS = {
        root: '#impactshop-ads-watch',
        topPoints: '#user-points-display',
        topVotes: '#available-votes-display',
        videoPoints: '#video-balance-points',
        videoVotes: '#video-balance-votes',
        videoPointsDelta: '#video-balance-points-delta',
        videoVotesDelta: '#video-balance-votes-delta',
        pointsItem: '.live-balance-item[data-type="points"]',
        votesItem: '.live-balance-item[data-type="votes"]',
        ctaLinks: '#ads-watch-cta-link, [data-role="auto-banner-link"]',
    };

    function formatHuNumber(value) {
        return Math.max(0, Math.round(Number(value || 0))).toLocaleString('hu-HU');
    }

    function parseDisplayedNumber(selector) {
        const raw = String($(selector).first().text() || '');
        const normalized = raw.replace(/[^\d-]+/g, '');
        if (!normalized) {
            return 0;
        }
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function readBaseline() {
        return {
            topPoints: parseDisplayedNumber(SELECTORS.topPoints),
            topVotes: parseDisplayedNumber(SELECTORS.topVotes),
            videoPoints: parseDisplayedNumber(SELECTORS.videoPoints),
            videoVotes: parseDisplayedNumber(SELECTORS.videoVotes),
        };
    }

    function applyBaseline(snapshot) {
        if (!snapshot) {
            return;
        }

        // Guard: prevent re-entrant calls from MutationObserver
        if (DEFER_STATE._applying) {
            return;
        }
        DEFER_STATE._applying = true;
        try {
            $(SELECTORS.topPoints).text(formatHuNumber(snapshot.topPoints));
            $(SELECTORS.topVotes).text(formatHuNumber(snapshot.topVotes));
            $(SELECTORS.videoPoints).text(formatHuNumber(snapshot.videoPoints));
            $(SELECTORS.videoVotes).text(formatHuNumber(snapshot.videoVotes));
            $(SELECTORS.videoPointsDelta).text('').removeClass('is-visible');
            $(SELECTORS.videoVotesDelta).text('').removeClass('is-visible');
            $(SELECTORS.pointsItem).removeClass('is-updated');
            $(SELECTORS.votesItem).removeClass('is-updated');
        } finally {
            DEFER_STATE._applying = false;
        }
    }

    function keepDeferredUiIfNeeded() {
        if (!DEFER_STATE.armed || DEFER_STATE.releasing || !DEFER_STATE.pendingReward) {
            return;
        }
        if (DEFER_STATE._applying) {
            return;
        }
        applyBaseline(DEFER_STATE.baseline);
    }

    function armDeferredUi() {
        if (!$(SELECTORS.root).length) {
            return;
        }
        DEFER_STATE.armed = true;
        DEFER_STATE.releasing = false;
        DEFER_STATE.pendingReward = false;
        DEFER_STATE.baseline = readBaseline();
    }

    function clearDeferredUi() {
        DEFER_STATE.armed = false;
        DEFER_STATE.releasing = false;
        DEFER_STATE.pendingReward = false;
        DEFER_STATE.baseline = null;
    }

    function isUrlMatch(settings, needle) {
        const url = String((settings && settings.url) || '');
        return url.indexOf(needle) !== -1;
    }

    function initMutationGuard() {
        const root = document.querySelector(SELECTORS.root);
        if (!root || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(function () {
            keepDeferredUiIfNeeded();
        });

        observer.observe(root, {
            subtree: true,
            childList: true,
            characterData: true,
        });
    }

    $(document).ready(function () {
        if (!$(SELECTORS.root).length) {
            return;
        }

        document.addEventListener('click', function (event) {
            const target = event.target instanceof Element
                ? event.target.closest(SELECTORS.ctaLinks)
                : null;

            if (!target) {
                return;
            }

            armDeferredUi();
        }, true);

        $(document).ajaxSend(function (_event, _xhr, settings) {
            if (!DEFER_STATE.armed) {
                return;
            }

            if (isUrlMatch(settings, '/wp-json/impact/v1/ads-watch/view')) {
                DEFER_STATE.releasing = true;
            }
        });

        $(document).ajaxSuccess(function (_event, _xhr, settings, response) {
            if (!DEFER_STATE.armed) {
                return;
            }

            if (isUrlMatch(settings, '/wp-json/impact/v1/tracking/cta-click')) {
                const awardedPoints = Number(response && response.awarded_points ? response.awarded_points : 0);
                const awardedVotes = Number(response && response.awarded_votes ? response.awarded_votes : 0);

                if (awardedPoints <= 0 && awardedVotes <= 0) {
                    return;
                }

                DEFER_STATE.pendingReward = true;
                window.setTimeout(function () {
                    keepDeferredUiIfNeeded();
                }, 0);
                return;
            }

            if (isUrlMatch(settings, '/wp-json/impact/v1/ads-watch/view')) {
                DEFER_STATE.releasing = false;
                DEFER_STATE.pendingReward = false;
                DEFER_STATE.baseline = null;
                window.setTimeout(function () {
                    clearDeferredUi();
                }, 0);
            }
        });

        $(document).ajaxError(function (_event, _xhr, settings) {
            if (isUrlMatch(settings, '/wp-json/impact/v1/ads-watch/view')) {
                clearDeferredUi();
            }
        });

        initMutationGuard();
    });
})(jQuery);
