(function(){
  'use strict';

  function ready(callback){
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }
    callback();
  }

  function setText(root, selector, value){
    var el = root.querySelector(selector);
    if (el && typeof value === 'string' && value !== '') {
      if (el.textContent !== value) {
        el.textContent = value;
      }
    }
  }

  function singularOrPlural(numberText, singular, plural) {
    var normalized = String(numberText).replace(/[^0-9]/g, '').trim();
    return normalized === '1' ? singular : plural;
  }

  function translateCatalogText(text) {
    if (typeof text !== 'string' || text === '') return text;
    var translated = text;
    var tokenMap = [
      [/\bKabát\b/g, 'Coat'],
      [/\bFekete\b/g, 'Black'],
      [/\bFehér\b/g, 'White'],
      [/\bPóló\b/g, 'T-Shirt'],
      [/\bPulóver\b/g, 'Sweater'],
      [/\bDzseki\b/g, 'Jacket'],
      [/\bFarmer\b/g, 'Jeans'],
      [/\bRuha\b/g, 'Dress'],
      [/\bTáska\b/g, 'Bag'],
      [/\bCipő\b/g, 'Shoes'],
      [/\bNői\b/g, "Women's"],
      [/\bFérfi\b/g, "Men's"]
    ];

    for (var i = 0; i < tokenMap.length; i += 1) {
      translated = translated.replace(tokenMap[i][0], tokenMap[i][1]);
    }

    return translated;
  }

  function translateDynamicText(text) {
    if (typeof text !== 'string' || text === '') return text;

    var translated = translateCatalogText(text);

    translated = translated.replace(/\bFt\b/g, 'HUF');
    translated = translated.replace(/\sés\s/g, ' and ');
    translated = translated.replace(/A mentés akkor aktív, ha már betöltött egy ajánlat\./g, 'Save becomes available after an offer has loaded.');
    translated = translated.replace(/Minden\s+(\d+)\s+mp-ért:/g, 'For each $1 sec:');
    translated = translated.replace(/Végignézésért:/g, 'Completion bonus:');
    translated = translated.replace(/bónusz pont/g, 'bonus points');
    translated = translated.replace(/bónusz szavazat/g, 'bonus votes');
    translated = translated.replace(/Eddig:/g, 'So far:');
    translated = translated.replace(/pont jóváírva/g, 'points credited');
    translated = translated.replace(/Videó kihagyása/g, 'Skip video');
    translated = translated.replace(/Lezárásig:/g, 'Until closing:');
    translated = translated.replace(/Kihívások/g, 'Challenges');
    translated = translated.replace(/Ugrás a Kihívások szekcióhoz/g, 'Go to Challenges section');
    translated = translated.replace(/Ugrás a Legacy Pool szekcióhoz/g, 'Go to Legacy Pool section');
    translated = translated.replace(/a hirdetésre kattintás után/g, 'after clicking the ad');
    translated = translated.replace(/a szavazat‑egyenlegedhez/g, 'to your vote balance');
    translated = translated.replace(/a szavazat-egyenlegedhez/g, 'to your vote balance');
    translated = translated.replace(/minden\s+(\d+)\s+mp\s+videó\s+után/g, 'after every $1 sec of video');

    translated = translated.replace(/(\+?\d[\d\s]*)\s+pont\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num.replace(/\s+/g, ''), 'point', 'points');
    });

    translated = translated.replace(/(\+?\d[\d\s]*)\s+szavazat\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num.replace(/\s+/g, ''), 'vote', 'votes');
    });

    translated = translated.replace(/(\d[\d\s]*)\s+nap\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num.replace(/\s+/g, ''), 'day', 'days');
    });

    translated = translated.replace(/(\d[\d\s]*)\s+óra\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num.replace(/\s+/g, ''), 'hour', 'hours');
    });

    translated = translated.replace(/(\d[\d\s]*)\s+perc\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num.replace(/\s+/g, ''), 'min', 'min');
    });

    return translated;
  }

  function setTranslatedText(root, selector) {
    var el = root.querySelector(selector);
    if (!el) return;
    var translated = translateDynamicText(String(el.textContent || ''));
    if (translated && translated !== el.textContent) {
      el.textContent = translated;
    }
  }

  function setTranslatedHtml(root, selector) {
    var el = root.querySelector(selector);
    if (!el) return;
    var translated = translateDynamicText(String(el.innerHTML || ''));
    if (translated && translated !== el.innerHTML) {
      el.innerHTML = translated;
    }
  }

  function setTranslatedAttr(root, selector, name) {
    var el = root.querySelector(selector);
    if (!el) return;
    var current = String(el.getAttribute(name) || '');
    if (!current) return;
    var translated = translateDynamicText(current);
    if (translated && translated !== current) {
      el.setAttribute(name, translated);
    }
  }

  function setHtml(root, selector, value){
    var el = root.querySelector(selector);
    if (el && typeof value === 'string' && value !== '') {
      if (el.innerHTML !== value) {
        el.innerHTML = value;
      }
    }
  }

  function setAttr(root, selector, name, value){
    var el = root.querySelector(selector);
    if (el && typeof value === 'string' && value !== '') {
      if (el.getAttribute(name) !== value) {
        el.setAttribute(name, value);
      }
    }
  }

  function setLabelForInput(root, inputSelector, value) {
    if (!root || typeof value !== 'string' || value === '') return;
    var input = root.querySelector(inputSelector);
    if (!input) return;
    var label = input.closest('label');
    if (!label) return;

    var textTarget = label.querySelector('[data-role="label-text"]');
    if (textTarget) {
      textTarget.textContent = value;
      return;
    }

    var nodes = Array.prototype.slice.call(label.childNodes || []);
    var trailingText = '';
    for (var j = 0; j < nodes.length; j += 1) {
      if (nodes[j] !== input && nodes[j].nodeType === 3) {
        trailingText += String(nodes[j].textContent || '');
      }
    }
    if (trailingText.replace(/\s+/g, ' ').trim() === value.trim()) {
      return;
    }

    for (var i = 0; i < nodes.length; i += 1) {
      var node = nodes[i];
      if (node !== input && node.nodeType === 3) {
        label.removeChild(node);
      }
    }

    label.appendChild(document.createTextNode(' ' + value));
  }

  function setTextPreserveSuffix(root, selector, value) {
    if (!root || typeof value !== 'string' || value === '') return;
    var el = root.querySelector(selector);
    if (!el) return;

    var suffix = '';
    var iconNode = el.querySelector('i, svg, .icon, .caret, [data-role="chevron"]');
    if (iconNode) {
      suffix = ' ' + String(iconNode.textContent || '').trim();
    }

    var desired = value + (suffix ? (' ' + suffix) : '');
    if (String(el.textContent || '').trim() !== desired.trim()) {
      el.textContent = desired;
    }
  }

  function resolveAvailableVotes(root) {
    var selectors = [
      '#available-votes-inline',
      '#available-votes-display',
      '.status-item.vote-balance .value'
    ];

    for (var i = 0; i < selectors.length; i += 1) {
      var el = root.querySelector(selectors[i]);
      if (!el) continue;
      var raw = String(el.textContent || '').trim();
      if (!raw) continue;

      var normalized = raw.replace(/[^0-9\s]/g, '').trim();
      if (normalized) return normalized;
      if (raw === '0') return '0';
    }

    return '0';
  }

  function setAvailableVotesLabel(root, prefix, suffix) {
    var container = root.querySelector('.vote-available');
    if (!container) return;
    var value = resolveAvailableVotes(root);

    var safePrefix = (typeof prefix === 'string' && prefix !== '') ? prefix : 'Available:';
    var safeSuffix = (typeof suffix === 'string' && suffix !== '') ? suffix : 'votes';
    var html = safePrefix + ' <strong id="available-votes-inline">' + value + '</strong> ' + safeSuffix;

    if (container.innerHTML !== html) {
      container.innerHTML = html;
    }
  }

  function applyActionBar(i18n) {
    var bar = document.querySelector('.sharity-action-bar');
    if (!bar) return;

    if (typeof i18n.actionBarLabel === 'string' && i18n.actionBarLabel !== '') {
      bar.setAttribute('aria-label', i18n.actionBarLabel);
    }

    var labels = {
      video: i18n.actionBarVideo,
      tasks: i18n.actionBarTasks,
      donate: i18n.actionBarDonate,
      account: i18n.actionBarProfile,
      ngo: i18n.actionBarNgo,
      message: i18n.actionBarMessages,
      stats: i18n.actionBarPoints
    };

    Object.keys(labels).forEach(function(key) {
      var value = labels[key];
      if (typeof value !== 'string' || value === '') return;
      var el = bar.querySelector('[data-bar="' + key + '"] span:last-child');
      if (el && el.textContent !== value) {
        el.textContent = value;
      }
    });
  }

  function apply(root){
    var config = window.impactshopAdsWatch || {};
    var toggles = config.toggles || {};
    var i18n = config.i18n || {};
    if (!root || config.lang !== 'en' || !toggles.currentFeatureEnabled) return;

    applyActionBar(i18n);
    setText(root, '.ads-watch-header h2', i18n.headerTitle);
    setText(root, '#ads-watch-subtitle-text', i18n.subtitle);
    setAttr(root, '[data-info-trigger]', 'aria-label', 'Information');
    setHtml(root, '[data-info-popover]', i18n.infoPopoverHtml);
    setText(root, '.pool-label', i18n.donationPoolLabel);
    setText(root, '[data-role="ads-watch-tab"][data-target="video"]', i18n.tabVideo);
    setText(root, '[data-role="ads-watch-tab"][data-target="offerwall"]', i18n.tabTasks);
    setText(root, '#ads-watch-donate-btn', i18n.tabDonate);
    setText(root, '#ima-cta-overlay .ima-cta-text', i18n.ctaOverlayText);
    setText(root, '#ads-watch-cta-link .ima-cta-text', i18n.ctaLinkText);
    setText(root, '#presence-check-overlay .presence-check-title', i18n.presenceTitle);
    setText(root, '#presence-check-overlay .presence-check-subtitle', i18n.presenceSubtitle);
    setText(root, '#presence-confirm', i18n.presenceConfirm);
    setText(root, '#btn-watch-ad .btn-text', i18n.watchAd);
    setText(root, '#player-loading span', i18n.loadingAd);
    setText(root, '#ad-progress-text', i18n.progressRequired);
    setText(root, '.ad-progress-help-label', i18n.progressWhyLabel);
    setText(root, '.ad-progress-help-bubble', i18n.progressWhyBubble);
    setText(root, '#btn-skip-video', i18n.skipVideo);
    setText(root, '#btn-resume-ad', i18n.resume);
    setText(root, '.video-info-watch .video-info-label', i18n.watchReward);
    setText(root, '.video-info-click .video-info-label', i18n.clickReward);
    setTranslatedText(root, '#video-info-watch-reward');
    setTranslatedText(root, '#video-info-click-reward');
    setText(root, '#ads-watch-live-balance .ads-watch-live-balance-title', i18n.liveBalanceTitle);
    setText(root, '.live-balance-item[data-type="points"] .live-balance-label', i18n.pointsLabel);
    setText(root, '.live-balance-item[data-type="votes"] .live-balance-label', i18n.votesLabel);
    setText(root, '[data-role="auto-banner-link"]', i18n.autoBannerCta);
    setText(root, '.auto-banner-hint', i18n.autoBannerHint);
    setTranslatedText(root, '[data-role="auto-banner-title"]');
    setTranslatedText(root, '[data-role="auto-banner-prices"]');
    setText(root, '.status-item.user-points .label', i18n.statusPoints);
    setText(root, '.status-item.user-level .label', i18n.statusLevel);
    setText(root, '.status-item.vote-weight .label', i18n.statusVoteWeight);
    setText(root, '.status-item.donation-multiplier .label', i18n.statusDonationBonus);
    setText(root, '.status-item.vote-balance .label', i18n.statusVotes);
    setText(root, '.status-item.streak .label', i18n.statusStreak);
    setText(root, '.status-item.countdown .label', i18n.statusCountdown);
    setTranslatedText(root, '#streak-display');
    setTranslatedText(root, '#impact-challenge-countdown-display');
    setText(root, '#ads-watch-steps .step-pill:nth-child(1)', i18n.stepVideo);
    setText(root, '#ads-watch-steps .step-pill:nth-child(2)', i18n.stepNgo);
    setText(root, '#ads-watch-steps .step-pill:nth-child(3)', i18n.stepVote);
    setText(root, '.ads-watch-insights-title', i18n.quickInfo);
    setText(root, '#ads-watch-live .insight-title', i18n.liveActivity);
    setText(root, '#ads-watch-live .insight-hint', i18n.lastFiveVotes);
    setText(root, '#ads-watch-message .insight-title', i18n.campaignMessage);
    setText(root, '[data-role="ads-watch-message-text"]', i18n.messageSoon);
    setText(root, '#ads-watch-message .insight-hint', i18n.newsHint);
    setText(root, '#ads-watch-chance .insight-title', i18n.chanceTitle);
    setText(root, '#ads-watch-chance .insight-hint', i18n.chanceHint);
    setText(root, '#ads-watch-ngo h3', i18n.selectNgoTitle);
    setText(root, '#selected-ngo-display .no-ngo-text', i18n.noNgoSelectedText);
    setText(root, '#btn-change-ngo', i18n.changeNgo);
    setTextPreserveSuffix(root, '[data-role="purchase-toggle"]', '🎁 ' + (i18n.purchaseToggle || 'Donate and vote'));
    setText(root, 'label[for="purchase-currency"]', i18n.purchaseCurrency || 'Currency');
    setLabelForInput(root, '[data-role="purchase-company"]', i18n.purchaseCompany || 'I am donating as a company');
    setLabelForInput(root, '[data-role="purchase-consent"]', i18n.purchaseConsent || 'I accept the Terms and Conditions and the Privacy Notice');
    setText(root, '[data-role="purchase-submit"]', i18n.purchaseSubmit || 'Donate now');
    setText(root, 'label[for="vote-amount-input"]', i18n.voteAmountLabel);
    setAvailableVotesLabel(root, i18n.availableVotes, i18n.votesSuffix);
    setText(root, '[data-vote-quick="all"]', i18n.voteQuickAll);
    setText(root, '[data-vote-quick="half"]', i18n.voteQuickHalf);
    setText(root, '#btn-allocate-votes', i18n.voteNow);
    setText(root, '.vote-alloc-hint', i18n.voteHint);
    setLabelForInput(root, '#auto-vote-enabled', i18n.autoVote);
    var autoVoteInput = root.querySelector('#auto-vote-enabled');
    if (autoVoteInput) {
      autoVoteInput.disabled = false;
      autoVoteInput.removeAttribute('disabled');
    }
    setText(root, '.ads-watch-tally h3', i18n.topNgo);
    setText(root, '.tally-info span', i18n.totalVotes);
    setText(root, '.tally-loading', i18n.loading);
    setTranslatedHtml(root, '#reward-info-text');
    setTranslatedText(root, '.edu-info-rewards');
    setTranslatedText(root, '.edu-info-bonus');
    setTranslatedText(root, '.edu-info-progress');
    setTranslatedText(root, '#btn-skip-education');
    setTranslatedText(root, '[data-role="save-offer-status"]');
    setTranslatedText(root, '.reward-points');
    setTranslatedText(root, '.reward-votes');
    setTranslatedText(root, '[data-sharity-shortcut="challenges"] .shortcut-label');
    setTranslatedAttr(root, '[data-sharity-shortcut="challenges"] .sharity-status-shortcut-button', 'aria-label');
    setTranslatedAttr(root, '[data-sharity-shortcut="challenges"] .sharity-status-shortcut-button', 'title');
    setTranslatedAttr(root, '[data-sharity-header-link="challenges"]', 'aria-label');
    setTranslatedAttr(root, '[data-sharity-header-link="challenges"]', 'title');
    setTranslatedAttr(root, '[data-sharity-header-link="legacy"]', 'aria-label');
    setTranslatedAttr(root, '[data-sharity-header-link="legacy"]', 'title');
    setText(root, '#btn-show-more-ngos', i18n.showMore);
    setText(root, '#btn-show-all-ngos', i18n.showFullList);
    setText(root, '#ngo-selection-modal .modal-header h3', i18n.ngoModalTitle);
    setAttr(root, '#ngo-search-input', 'placeholder', i18n.searchPlaceholder);
    setText(root, '#full-tally-modal .modal-header h3', i18n.fullListTitle);
    setAttr(root, '#full-tally-search', 'placeholder', i18n.fullListSearchPlaceholder);
  }

  ready(function(){
    var root = document.getElementById('impactshop-ads-watch');
    if (!root) return;
    apply(root);
    var scheduled = false;
    var observer = new MutationObserver(function(){
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(function(){
        scheduled = false;
        apply(root);
      });
    });
    observer.observe(root, { childList: true, subtree: true });
  });
})();