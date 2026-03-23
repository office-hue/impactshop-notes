(function(){
  "use strict";

  // =========================================================================
  // AyeT Offerwall – Enriched Card UI with filters, CPE stepper, toast, stats
  // See: AYET-OFFERWALL-UX-REWARD-PLAN.md
  // =========================================================================

  var POLL_INTERVAL = 60000;     // history polling: 60s
  var TOAST_DURATION = 4000;     // toast visible: 4s
  var MAX_TOASTS = 3;
  var SCROLL_KEY = 'impactshop_offerwall_scroll_v1';
  var VISIBLE_STEP = 12;

  function initOfferwall(root){
    if (!root) return;
    var restBase = (window.impactshopOfferwall && window.impactshopOfferwall.restUrl) || '';
    if (!restBase) return;

    var cardsEl = root.querySelector('[data-role="offerwall-cards"]');
    var activeEl = root.querySelector('[data-role="offerwall-active"]');
    var historyEl = root.querySelector('[data-role="offerwall-history"]');
    var modal = root.querySelector('[data-role="offerwall-modal"]');
    var frame = root.querySelector('[data-role="offerwall-frame"]');
    var closeBtn = root.querySelector('[data-role="offerwall-close"]');
    var mobileModal = root.querySelector('[data-role="offerwall-mobile-modal"]');
    var mobileClose = root.querySelector('[data-role="offerwall-mobile-close"]');
    var mobileQr = root.querySelector('[data-role="offerwall-mobile-qr"]');
    var mobileLink = root.querySelector('[data-role="offerwall-mobile-link"]');
    var mobileCopy = root.querySelector('[data-role="offerwall-mobile-copy"]');
    var mobileText = root.querySelector('[data-role="offerwall-mobile-text"]');
    var mobilePlatforms = root.querySelector('[data-role="offerwall-mobile-platforms"]');
    var faqTrigger = root.querySelector('[data-role="offerwall-faq-trigger"]');
    var faqBox = root.querySelector('[data-role="offerwall-faq"]');
    var tabsEl = root.querySelector('[data-role="offerwall-tabs"]');
    var tabButtons = root.querySelectorAll('[data-role="offerwall-tab"]');
    var providerTabs = root.querySelectorAll('[data-role="offerwall-provider-tabs"]');

    var consentKey = 'impactshop_offerwall_consent_v1';
    var providersCache = [];
    var lastHistoryIds = [];
    var offersCache = [];
    var activeOffersCache = [];
    var ayetSurveyCache = [];
    var ayetSurveyLoaded = false;
    var activeFilter = 0;       // 0 = all, 1-4 = tier
    var activeSort = 'payout';  // payout | time | status
    var currentProvider = null;
    var visibleCount = VISIBLE_STEP;
    var lastFilteredCache = [];
    var providerTabsReady = false;

    // --- Cookie helper ---
    function getCookie(name){
      var match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
      return match ? decodeURIComponent(match[2]) : '';
    }

    // --- Consent ---
    function ensureConsent(){
      if (localStorage.getItem(consentKey) === '1') return true;
      var ok = window.confirm('Az offerwall külső szolgáltató. Elfogadod a feltételeket?');
      if (ok) localStorage.setItem(consentKey, '1');
      return ok;
    }

    // --- Modal (iframe fallback for non-offers providers) ---
    function openModal(url){
      if (!modal || !frame) return;
      frame.src = url;
      modal.classList.add('active');
    }
    function closeModal(){
      if (!modal || !frame) return;
      modal.classList.remove('active');
      frame.src = 'about:blank';
      setTimeout(fetchHistory, 2500);
    }
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    if (closeBtn) closeBtn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); closeModal(); });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal && modal.classList.contains('active')) closeModal();
    });
    function isMobileUA(){
      var ua = (navigator.userAgent || '').toLowerCase();
      return ua.indexOf('android') !== -1 || ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1;
    }
    function isAndroidUA(){
      return (navigator.userAgent || '').toLowerCase().indexOf('android') !== -1;
    }
    function isIOSUA(){
      var ua = (navigator.userAgent || '').toLowerCase();
      return ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1;
    }
    function formatMaxDays(maxDays){
      if (maxDays == null || maxDays === 0) return '?';
      var days = maxDays;
      if (days > 1000) days = Math.ceil(days / 86400);
      return days + ' nap';
    }
    function formatStepRemaining(value){
      if (value == null) return '';
      var days = value;
      if (days > 1000) days = Math.ceil(days / 86400);
      return days + ' nap';
    }
    function formatStepName(stepName, idx){
      var name = String(stepName || '').trim();
      if (!name) return 'Feladat ' + (idx + 1);
      if (name === 'INSTALLATION_TRACKED') return 'Telepítés';
      var expMatch = name.match(/EXP[_-]?(\d+)/i);
      if (expMatch) return 'Szint ' + expMatch[1] + ' elérése';
      var lvlMatch = name.match(/LEVEL[_-]?(\d+)/i);
      if (lvlMatch) return 'Szint ' + lvlMatch[1] + ' elérése';
      var msMatch = name.match(/MILESTONE[_-]?(\d+)/i);
      if (msMatch) return 'Mérföldkő ' + msMatch[1];
      if (/^[a-z0-9]{5,8}$/i.test(name)) return 'Feladat ' + (idx + 1);
      return name.replace(/_/g, ' ');
    }
    function initCpxSurvey(){
      var container = root.querySelector('#cpx-survey-container');
      if (!container) return;
      if (container.getAttribute('data-cpx-enabled') !== '1') return;
      var appIdRaw = container.getAttribute('data-cpx-app-id') || '';
      var userId = container.getAttribute('data-cpx-user') || '';
      var secureHash = container.getAttribute('data-cpx-hash') || '';
      var subid1 = container.getAttribute('data-cpx-subid1') || '';
      if (!appIdRaw || !userId || !secureHash) {
        container.innerHTML = '<div class="offerwall-empty">A kérdőív modul jelenleg nem elérhető.</div>';
        return;
      }
      if (window.__impactshopCpxInit) return;
      window.__impactshopCpxInit = true;
      var appId = parseInt(appIdRaw, 10);
      if (!isFinite(appId)) appId = appIdRaw;
      window.config = {
        general_config: {
          app_id: appId,
          ext_user_id: userId,
          secure_hash: secureHash,
          subid_1: subid1,
          subid_2: ''
        },
        style_config: {
          text_color: '#f8fafc',
          survey_box: {
            topbar_background_color: '#2563eb',
            box_background_color: '#111827',
            rounded_borders: true,
            stars_filled: '#f59e0b'
          }
        },
        script_config: [{
          div_id: 'cpx-survey-container',
          theme_style: 1,
          order_by: 2,
          limit_surveys: 10
        }],
        debug: false,
        useIFrame: true,
        iFramePosition: 1,
        functions: {
          no_surveys_available: function(){
            container.innerHTML = '<div class="offerwall-empty">Jelenleg nincs elérhető kérdőív. Nézz vissza később!</div>';
          },
          count_new_surveys: function(count){
            var surveyTab = root.querySelector('[data-role="offerwall-tab"][data-target="survey"]');
            if (surveyTab && count > 0) {
              surveyTab.textContent = '📊 Kérdőív (' + count + ')';
            }
          }
        }
      };
      if (!document.querySelector('script[data-cpx-script]')) {
        var script = document.createElement('script');
        script.src = 'https://cdn.cpx-research.com/assets/js/script_tag_v2.0.js';
        script.async = true;
        script.setAttribute('data-cpx-script', '1');
        document.body.appendChild(script);
      }
    }
    function closeMobileModal(){
      if (!mobileModal) return;
      mobileModal.hidden = true;
      if (mobileQr) mobileQr.src = '';
      if (mobileLink) mobileLink.href = '#';
      if (mobilePlatforms) mobilePlatforms.hidden = true;
    }
    function openMobileModal(link, platformLabel, platformLinks){
      if (!mobileModal || !link) return;
      var currentLink = link;
      if (platformLinks && mobilePlatforms) {
        mobilePlatforms.innerHTML = '';
        mobilePlatforms.hidden = false;
        var keys = Object.keys(platformLinks);
        keys.forEach(function(key, idx){
          var btn = document.createElement('button');
          btn.type = 'button';
          var label = key === 'ios' ? 'iOS' : (key === 'android' ? 'Android' : 'Web');
          btn.textContent = label;
          if (idx === 0) btn.classList.add('is-active');
          btn.addEventListener('click', function(){
            mobilePlatforms.querySelectorAll('button').forEach(function(b){ b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            currentLink = platformLinks[key];
            if (mobileQr) mobileQr.src = 'https://quickchart.io/qr?text=' + encodeURIComponent(currentLink) + '&size=220';
            if (mobileLink) mobileLink.href = currentLink;
          });
          mobilePlatforms.appendChild(btn);
        });
        if (platformLinks[keys[0]]) currentLink = platformLinks[keys[0]];
      }
      var qrUrl = 'https://quickchart.io/qr?text=' + encodeURIComponent(currentLink) + '&size=220';
      if (mobileQr) mobileQr.src = qrUrl;
      if (mobileLink) mobileLink.href = currentLink;
      if (mobileText) {
        var label = platformLabel ? ('Csak ' + platformLabel + ' rendszeren végezhető. ') : '';
        mobileText.textContent = label + 'Olvasd be a QR kódot a telefonoddal, és folytasd ott.';
      }
      if (mobileCopy) mobileCopy.textContent = 'Link másolása';
      mobileModal.hidden = false;
    }
    if (mobileClose) mobileClose.addEventListener('click', function(){ closeMobileModal(); });
    if (mobileModal) mobileModal.addEventListener('click', function(e){ if (e.target === mobileModal) closeMobileModal(); });
    if (mobileCopy) {
      mobileCopy.addEventListener('click', function(){
        var link = mobileLink ? mobileLink.href : '';
        if (!link) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(function(){ mobileCopy.textContent = 'Link másolva'; });
        } else {
          var input = document.createElement('input');
          input.value = link;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          document.body.removeChild(input);
          mobileCopy.textContent = 'Link másolva';
        }
      });
    }
    if (faqTrigger && faqBox) {
      faqTrigger.addEventListener('click', function(){ faqBox.hidden = !faqBox.hidden; });
    }

    function setActiveProvider(scope, providerKey){
      if (!providerTabs || !providerTabs.length) return;
      Array.prototype.forEach.call(providerTabs, function(group){
        if ((group.getAttribute('data-scope') || '') !== scope) return;
        var buttons = group.querySelectorAll('[data-role="offerwall-provider"]');
        Array.prototype.forEach.call(buttons, function(btn){
          if (btn.getAttribute('data-provider') === providerKey) {
            btn.classList.add('is-active');
          } else {
            btn.classList.remove('is-active');
          }
        });
      });

      if (scope === 'survey') {
        showSurveyProvider(providerKey);
      }
    }

    function showSurveyProvider(providerKey){
      var sections = root.querySelectorAll('.offerwall-survey-section');
      Array.prototype.forEach.call(sections, function(section){
        var key = section.getAttribute('data-provider');
        section.style.display = (key === providerKey) ? '' : 'none';
      });
      if (providerKey === 'ayet') {
        fetchAyetSurveys();
      }
    }

    function initProviderTabs(){
      if (providerTabsReady) return;
      providerTabsReady = true;
      Array.prototype.forEach.call(providerTabs, function(group){
        var scope = group.getAttribute('data-scope') || '';
        var buttons = group.querySelectorAll('[data-role="offerwall-provider"]');
        Array.prototype.forEach.call(buttons, function(btn){
          btn.addEventListener('click', function(){
            if (btn.classList.contains('is-disabled') || btn.disabled) return;
            setActiveProvider(scope, btn.getAttribute('data-provider'));
          });
        });
      });

      var surveyDefault = root.querySelector('[data-scope="survey"] [data-role="offerwall-provider"]:not(.is-disabled)');
      if (surveyDefault) {
        setActiveProvider('survey', surveyDefault.getAttribute('data-provider'));
      }
    }

    // ===== TOAST SYSTEM =====
    var toastContainer = document.querySelector('.offerwall-toast-container');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'offerwall-toast-container';
      document.body.appendChild(toastContainer);
    }

    function showToast(text, type){
      type = type || 'success';
      var existing = toastContainer.querySelectorAll('.offerwall-toast');
      if (existing.length >= MAX_TOASTS) {
        toastContainer.removeChild(existing[0]);
      }
      var toast = document.createElement('div');
      toast.className = 'offerwall-toast offerwall-toast--' + type;
      toast.textContent = text;
      toastContainer.appendChild(toast);
      setTimeout(function(){
        toast.classList.add('offerwall-toast--out');
        setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
      }, TOAST_DURATION);
    }

    // ===== SKELETON LOADING =====
    function renderSkeleton(){
      if (!cardsEl) return;
      cardsEl.innerHTML = '';
      for (var i = 0; i < 3; i++) {
        var sk = document.createElement('div');
        sk.className = 'offerwall-card offerwall-card--skeleton';
        sk.innerHTML = '<div class="sk-icon"></div><div class="sk-line sk-line--w60"></div>' +
          '<div class="sk-line sk-line--w80"></div><div class="sk-line sk-line--w40"></div>';
        cardsEl.appendChild(sk);
      }
    }

    // ===== STATS PANEL =====
    function renderStats(){
      var existing = root.querySelector('.offerwall-stats');
      if (existing) existing.remove();
      var panel = document.createElement('div');
      panel.className = 'offerwall-stats';
      panel.innerHTML =
        '<div class="offerwall-stat"><span class="offerwall-stat-value" data-role="stat-today-points">—</span><span class="offerwall-stat-label">pont ma</span></div>' +
        '<div class="offerwall-stat"><span class="offerwall-stat-value" data-role="stat-today-votes">—</span><span class="offerwall-stat-label">szavazat ma</span></div>' +
        '<div class="offerwall-stat"><span class="offerwall-stat-value" data-role="stat-total-points">—</span><span class="offerwall-stat-label">összes offerwall pont</span></div>' +
        '<div class="offerwall-stat"><span class="offerwall-stat-value" data-role="stat-total-votes">—</span><span class="offerwall-stat-label">összes offerwall szavazat</span></div>';
      if (cardsEl && cardsEl.parentNode) {
        cardsEl.parentNode.insertBefore(panel, cardsEl);
      }
      fetchStats(panel);
    }

    function fetchStats(panel){
      fetchWithRetry(restBase + '/stats', { credentials: 'include' }, 2)
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
          if (!data) return;
          var pe = panel.querySelector('[data-role="stat-today-points"]');
          var ve = panel.querySelector('[data-role="stat-today-votes"]');
          var tpe = panel.querySelector('[data-role="stat-total-points"]');
          var tve = panel.querySelector('[data-role="stat-total-votes"]');
          if (pe) pe.textContent = data.points_today != null ? data.points_today : '—';
          if (ve) ve.textContent = data.votes_today != null ? data.votes_today : '—';
          if (tpe) tpe.textContent = data.total_points != null ? data.total_points : '—';
          if (tve) tve.textContent = data.total_votes != null ? data.total_votes : '—';
        });
    }

    function fetchAyetSurveys(force){
      if (ayetSurveyLoaded && !force) return;
      var surveyUrl = (window.impactshopOfferwall && window.impactshopOfferwall.ayetSurveyUrl) || '';
      var container = root.querySelector('[data-role="offerwall-ayet-surveys"]');
      if (!surveyUrl || !container) {
        renderOfferCardsInto(container, [], 'Az AyeT kérdőívek jelenleg nem elérhetők.');
        return;
      }
      var shouldRefresh = force || !ayetSurveyLoaded;
      ayetSurveyLoaded = true;
      renderOfferCardsInto(container, [], 'Kérdőívek betöltése...');
      var refreshParam = shouldRefresh ? '&refresh=1' : '';
      fetchWithRetry(surveyUrl + '?_ts=' + Date.now() + refreshParam, { credentials: 'include' }, 2)
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
          if (data && data.status === 'missing_pseudo') {
            return { __error: 'Azonosító szükséges a kérdőívek megjelenítéséhez.' };
          }
          if (data && data.status === 'missing_adslot') {
            return { __error: 'Az AyeT kérdőívek még nincsenek beállítva.' };
          }
          return (data && data.status === 'ok' && data.offers) ? data.offers : [];
        })
        .then(function(offers){
          if (offers && offers.__error) {
            renderOfferCardsInto(container, [], offers.__error);
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'offerwall-cta';
            retry.textContent = 'Frissítés';
            retry.addEventListener('click', function(){ fetchAyetSurveys(true); });
            container.appendChild(retry);
            return;
          }
          ayetSurveyCache = offers.slice();
          renderOfferCardsInto(container, ayetSurveyCache, 'Jelenleg nincs elérhető AyeT kérdőív.');
          if (!ayetSurveyCache.length) {
            var retryEmpty = document.createElement('button');
            retryEmpty.type = 'button';
            retryEmpty.className = 'offerwall-cta';
            retryEmpty.textContent = 'Frissítés';
            retryEmpty.addEventListener('click', function(){ fetchAyetSurveys(true); });
            container.appendChild(retryEmpty);
          }
        })
        .catch(function(){
          renderOfferCardsInto(container, [], 'A kérdőívek betöltése nem sikerült. Próbáld újra!');
          var retry = document.createElement('button');
          retry.type = 'button';
          retry.className = 'offerwall-cta';
          retry.textContent = 'Frissítés';
          retry.addEventListener('click', function(){ fetchAyetSurveys(true); });
          container.appendChild(retry);
        });
    }

    // ===== FILTER BAR =====
    function renderFilterBar(offers){
      var existing = root.querySelector('.offerwall-filters');
      if (existing) existing.remove();
      if (!offers || !offers.length) return;

      var bar = document.createElement('div');
      bar.className = 'offerwall-filters';

      var filters = [
        { tier: 0, label: 'Összes' },
        { tier: 1, label: '⭐ Könnyű' },
        { tier: 2, label: '⭐⭐ Közepes' },
        { tier: 3, label: '⭐⭐⭐ Kihívás' },
        { tier: 4, label: '🏆 Nagy' }
      ];

      filters.forEach(function(f){
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'offerwall-filter-btn' + (f.tier === activeFilter ? ' active' : '');
        btn.textContent = f.label;
        btn.addEventListener('click', function(){
          activeFilter = f.tier;
          visibleCount = VISIBLE_STEP;
          renderFilteredOffers(offers);
          bar.querySelectorAll('.offerwall-filter-btn').forEach(function(b){ b.classList.remove('active'); });
          btn.classList.add('active');
        });
        bar.appendChild(btn);
      });

      // Sort dropdown
      var sortSel = document.createElement('select');
      sortSel.className = 'offerwall-sort';
      [
        { value: 'payout', label: 'Legtöbb pont' },
        { value: 'time', label: 'Leggyorsabb' },
        { value: 'status', label: 'Aktívak elöl' }
      ].forEach(function(opt){
        var o = document.createElement('option');
        o.value = opt.value;
        o.textContent = opt.label;
        if (opt.value === activeSort) o.selected = true;
        sortSel.appendChild(o);
      });
      sortSel.addEventListener('change', function(){
        activeSort = sortSel.value;
        visibleCount = VISIBLE_STEP;
        renderFilteredOffers(offers);
      });
      bar.appendChild(sortSel);

      var refresh = document.createElement('button');
      refresh.type = 'button';
      refresh.className = 'offerwall-refresh';
      refresh.textContent = 'Frissítés';
      refresh.addEventListener('click', function(){
        if (!currentProvider) return;
        refresh.classList.add('is-loading');
        fetchOffers(currentProvider, { refresh: true })
          .catch(function(){})
          .finally(function(){ refresh.classList.remove('is-loading'); });
      });
      bar.appendChild(refresh);

      cardsEl.parentNode.insertBefore(bar, cardsEl);
    }

    function normalizeOfferKey(offer){
      var name = (offer.name || '').toLowerCase().replace(/\s+/g, ' ').trim();
      return name;
    }

    function mergePlatformVariants(offers){
      var map = {};
      offers.forEach(function(offer){
        var key = normalizeOfferKey(offer);
        if (!key) return;
        if (!map[key]) {
          var clone = Object.assign({}, offer);
          clone.platform_links = {};
          if (offer.platform && offer.tracking_link) {
            clone.platform_links[offer.platform] = offer.tracking_link;
          }
          map[key] = clone;
          return;
        }
        var base = map[key];
        if (offer.platform && offer.tracking_link) {
          base.platform_links[offer.platform] = offer.tracking_link;
        }
        if ((offer.rating || 0) > (base.rating || 0)) base.rating = offer.rating;
        if ((offer.total_points || 0) > (base.total_points || 0)) base.total_points = offer.total_points;
        if ((offer.total_votes || 0) > (base.total_votes || 0)) base.total_votes = offer.total_votes;
      });
      return Object.values(map).map(function(offer){
        var platforms = Object.keys(offer.platform_links || {});
        if (platforms.length >= 2) {
          offer.platform = platforms.indexOf('web') >= 0 ? 'web' : 'mobile';
          offer.mobile_only = platforms.indexOf('web') === -1;
        } else if (platforms.length === 1) {
          offer.platform = platforms[0];
          offer.mobile_only = offer.platform === 'ios' || offer.platform === 'android';
        }
        return offer;
      });
    }

    function renderFilteredOffers(offers){
      var filtered = offers;
      if (activeFilter > 0) {
        filtered = offers.filter(function(o){ return o.difficulty && o.difficulty.tier === activeFilter; });
      }
      filtered = mergePlatformVariants(filtered);
      filtered = sortOffers(filtered, activeSort);
      lastFilteredCache = filtered.slice();
      var visible = filtered.slice(0, visibleCount);
      renderOfferCards(visible);
      renderLoadMore();
    }

    function renderLoadMore(){
      if (!cardsEl || !cardsEl.parentNode) return;
      var existing = root.querySelector('.offerwall-load-more');
      if (existing) existing.remove();
      if (!lastFilteredCache.length || lastFilteredCache.length <= visibleCount) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'offerwall-load-more';
      var remaining = lastFilteredCache.length - visibleCount;
      btn.textContent = 'Mutass még ' + Math.min(VISIBLE_STEP, remaining) + ' ajánlatot';
      btn.addEventListener('click', function(){
        visibleCount += VISIBLE_STEP;
        renderFilteredOffers(lastFilteredCache);
      });
      cardsEl.parentNode.insertBefore(btn, cardsEl.nextSibling);
    }

    function sortOffers(offers, sort){
      var arr = offers.slice();
      if (sort === 'payout') {
        arr.sort(function(a, b){ return (b.total_points || 0) - (a.total_points || 0); });
      } else if (sort === 'time') {
        arr.sort(function(a, b){ return (a.max_days || 999) - (b.max_days || 999); });
      } else if (sort === 'status') {
        arr.sort(function(a, b){
          var sa = (a.offer_status === 'started' || a.offer_status === 'in_progress') ? 0 : 1;
          var sb = (b.offer_status === 'started' || b.offer_status === 'in_progress') ? 0 : 1;
          return sa - sb || (b.total_points || 0) - (a.total_points || 0);
        });
      }
      return arr;
    }

    // ===== OFFER CARD RENDERING =====
    function renderOfferCards(offers){
      if (!cardsEl) return;
      cardsEl.innerHTML = '';
      if (!offers || !offers.length) {
        cardsEl.appendChild(createMessageCard('Jelenleg nincs elérhető feladat a régiódban. Nézz vissza később! Ha AdBlock fut, próbáld meg kikapcsolni.'));
        return;
      }
      offers.forEach(function(offer){
        cardsEl.appendChild(createOfferCard(offer));
      });
    }

    function createOfferCard(offer){
      var card = document.createElement('div');
      card.className = 'offerwall-card offerwall-card--offer';
      var isActive = offer.offer_status === 'started' || offer.offer_status === 'in_progress';
      if (isActive) card.classList.add('offerwall-card--active');

      // Active badge
      var activeBadge = '';
      if (isActive) {
        var daysLeft = offer.days_left != null ? offer.days_left + ' nap van hátra' : 'Folyamatban';
        activeBadge = '<div class="offerwall-active-badge">▶ ' + escHtml(daysLeft) + '</div>';
      }

      // Difficulty badge
      var diff = offer.difficulty || {};
      var diffHtml = '<span class="offerwall-diff" style="background:' + (diff.color || '#94a3b8') + '">' +
        escHtml(diff.label || '') + '</span>';
      var platformLabel = '';
      if (offer.platform === 'ios') platformLabel = 'iOS';
      if (offer.platform === 'android') platformLabel = 'Android';
      if (offer.platform === 'web') platformLabel = 'Web';
      if (offer.platform === 'mobile') platformLabel = 'iOS+Android';
      var platformHtml = platformLabel ? '<span class="offerwall-platform">' + escHtml(platformLabel) + '</span>' : '';

      // Icon
      var iconHtml = offer.icon
        ? '<img class="offerwall-card-icon" src="' + escAttr(offer.icon) + '" alt="" loading="lazy" />'
        : '<div class="offerwall-card-icon offerwall-card-icon--placeholder">🎯</div>';

      // Reward box
      var pointsDisplay = (offer.points_display != null) ? offer.points_display : (offer.total_points || 0);
      var votesDisplay = (offer.votes_display != null) ? offer.votes_display : (offer.total_votes || 0);
      var rewardHtml = '<div class="offerwall-reward">' +
        '<span>🎯 ' + pointsDisplay + ' pont + ' + votesDisplay + ' szavazat</span>' +
        '<span>⏱ ~' + escHtml(diff.est || '?') + '  ·  📅 max ' + formatMaxDays(offer.max_days) + '</span>' +
        '</div>';

      // Intro (max 2 lines via CSS)
      var introHtml = offer.introduction
        ? '<p class="offerwall-intro">' + escHtml(offer.introduction) + '</p>'
        : '';

      // Categories & rating
      var metaItems = [];
      if (offer.rating > 0) metaItems.push('⭐ ' + offer.rating.toFixed(1));
      if (offer.categories && offer.categories.length) {
        metaItems.push(offer.categories.slice(0, 2).map(escHtml).join(' · '));
      }
      var metaHtml = metaItems.length
        ? '<div class="offerwall-meta">' + metaItems.join(' · ') + '</div>'
        : '';

      // CPE steps
      var cpeHtml = '';
      if (offer.has_cpe && offer.cpe_steps && offer.cpe_steps.length) {
        cpeHtml = '<div class="offerwall-cpe">';
        cpeHtml += '<div class="offerwall-cpe-title">Lépések:</div>';
        offer.cpe_steps.forEach(function(step, idx){
          var statusIcon = '⬜';
          var statusClass = '';
          if (step.status === 'completed') { statusIcon = '✅'; statusClass = 'cpe--done'; }
          else if (step.status === 'available' && isActive) { statusIcon = '🔵'; statusClass = 'cpe--active'; }
          else if (step.status === 'unavailable') { statusIcon = '🔒'; statusClass = 'cpe--locked'; }
          if (step.type === 'bonus_task') { statusIcon = '⭐'; statusClass = 'cpe--bonus'; }

          cpeHtml += '<div class="offerwall-cpe-step ' + statusClass + '">';
          cpeHtml += '<span class="cpe-icon">' + statusIcon + '</span>';
          cpeHtml += '<span class="cpe-name">' + (idx + 1) + '. ' + escHtml(formatStepName(step.task_name, idx)) + '</span>';
          var stepPoints = (step.points_display != null) ? step.points_display : (step.points || 0);
          var stepVotes = (step.votes_display != null) ? step.votes_display : (step.votes || 0);
          cpeHtml += '<span class="cpe-reward">+' + stepPoints + ' pont +' + stepVotes + ' szav.</span>';
          if (step.remaining_time != null) {
            cpeHtml += '<span class="cpe-time">⏳ ' + formatStepRemaining(step.remaining_time) + '</span>';
          }
          cpeHtml += '</div>';
        });
        cpeHtml += '</div>';
      }

      // Rules accordion
      var rulesHtml = '';
      if (offer.rules) {
        var rulesId = 'rules-' + (offer.id || Math.random().toString(36).slice(2));
        rulesHtml = '<details class="offerwall-rules"><summary>ⓘ Feltételek</summary>' +
          '<div class="offerwall-rules-body">' + escHtml(offer.rules) + '</div></details>';
      }

      // CTA button
      var ctaLabel = isActive ? '▶️ Folytatás' : '🚀 Feladat indítása';
      var ctaHtml = '<button type="button" class="offerwall-cta">' + ctaLabel + '</button>';

      // Impression pixel
      var impressionHtml = offer.impression_url
        ? '<img src="' + escAttr(offer.impression_url) + '" style="width:1px;height:1px;border:0;position:absolute;" loading="lazy" alt="" />'
        : '';

      card.innerHTML = activeBadge +
        '<div class="offerwall-card-header">' + iconHtml +
        '<div class="offerwall-card-title"><strong>' + escHtml(offer.name) + '</strong>' + diffHtml + platformHtml + '</div></div>' +
        introHtml + rewardHtml + cpeHtml + rulesHtml + metaHtml + ctaHtml + impressionHtml;

      // CTA click handler
      var cta = card.querySelector('.offerwall-cta');
      if (cta) {
        cta.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          if (!offer.tracking_link) return;
          if (!ensureConsent()) return;
          saveScrollPosition();
          if (offer.mobile_only) {
            if (!isMobileUA()) {
              openMobileModal(offer.tracking_link, platformLabel || 'mobil', offer.platform_links || null);
              return;
            }
            if ((offer.platform === 'android' && isIOSUA()) || (offer.platform === 'ios' && isAndroidUA())) {
              openMobileModal(offer.tracking_link, platformLabel || 'mobil', offer.platform_links || null);
              return;
            }
          }
          var target = offer.tracking_link;
          if (offer.platform_links) {
            if (isIOSUA() && offer.platform_links.ios) target = offer.platform_links.ios;
            else if (isAndroidUA() && offer.platform_links.android) target = offer.platform_links.android;
          }
          openTrackingLink(target);
        });
      }

      return card;
    }

    // ===== PROVIDER CARDS (non-offers mode fallback) =====
    function renderProviders(providers){
      if (!cardsEl) return;
      providersCache = providers || [];
      offersCache = [];
      activeOffersCache = [];
      cardsEl.innerHTML = '';
      if (!providersCache.length) {
        cardsEl.innerHTML = '<div class="offerwall-card">Most nincs elérhető offerwall.</div>';
        return;
      }
      var externalProviders = providersCache.filter(function(provider){
        return provider.mode === 'offers' || provider.mode === 'iframe';
      });
      externalProviders.forEach(function(provider){
        var card = document.createElement('div');
        card.className = 'offerwall-card';
        card.innerHTML = '<strong>' + escHtml(provider.name) + '</strong>' +
          '<span>Pont szorzó: ' + provider.points_multiplier + '×</span>' +
          '<span>Szavazat szorzó: ' + provider.votes_multiplier + '×</span>';
        card.addEventListener('click', function(){
          if (!ensureConsent()) return;
          var key = provider.key || '';
          if (!key) return;
          if (provider.mode === 'offers') {
            fetchOffers(provider);
            return;
          }
          fetch(restBase + '/iframe/' + encodeURIComponent(key), { credentials: 'include' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(data){
              if (!data || data.status !== 'ok' || !data.url) return;
              openModal(data.url);
            });
        });
        cardsEl.appendChild(card);
      });
    }

    // ===== FETCH OFFERS (enriched) =====
    function fetchOffers(provider, opts){
      if (!cardsEl) return;
      currentProvider = provider || currentProvider;
      opts = opts || {};
      visibleCount = VISIBLE_STEP;
      renderSkeleton();
      var url = restBase + '/offers/' + encodeURIComponent(provider.key || '');
      var params = [];
      if (!isMobileUA()) {
        params.push('include_mobile=1');
      }
      if (opts.refresh) {
        params.push('refresh=1');
      }
      params.push('_ts=' + Date.now());
      if (params.length) url += '?' + params.join('&');
      fetchWithRetry(url, { credentials: 'include' }, 2)
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
          if (data && data.status === 'missing_pseudo') {
            return { __error: 'Azonosító szükséges a feladatok megjelenítéséhez. Kérlek jelentkezz be vagy frissítsd az oldalt.' };
          }
          if (data && data.status === 'rate_limited') {
            showToast('Kérlek várj pár másodpercet a frissítéssel.', 'info');
          }
          if (data && data.status === 'disabled') {
            return { __error: 'Az offerwall jelenleg nem elérhető.' };
          }
          var offers = (data && data.status === 'ok' && data.offers) ? data.offers : [];
          return fetchRewardStatus().then(function(campaigns){
            offers = mergeRewardStatus(offers, campaigns);
            return offers;
          }).catch(function(){ return offers; });
        })
        .then(function(offers){
          if (offers && offers.__error) {
            cardsEl.innerHTML = '';
            cardsEl.appendChild(createMessageCard(offers.__error));
            return;
          }
          offersCache = offers.slice();
          activeOffersCache = offers.filter(function(o){ return o.offer_status === 'started' || o.offer_status === 'in_progress'; });

          cardsEl.innerHTML = '';
          var existingBack = root.querySelector('.offerwall-back');
          if (existingBack) existingBack.remove();
          var back = document.createElement('button');
          back.type = 'button';
          back.className = 'offerwall-back';
          back.textContent = '← Vissza a szolgáltatókhoz';
          back.addEventListener('click', function(){ renderProviders(providersCache); });
          if (cardsEl.parentNode) {
            cardsEl.parentNode.insertBefore(back, cardsEl);
          }

          renderStats();
          renderFilterBar(offers);
          renderFilteredOffers(offers);
          renderActiveOffers(activeOffersCache);
          restoreScrollPosition();

          startHistoryPolling();
        })
        .catch(function(){
          cardsEl.innerHTML = '';
          cardsEl.appendChild(createMessageCard('A feladatok betöltése nem sikerült. Próbáld újra!'));
          var retry = document.createElement('button');
          retry.type = 'button';
          retry.className = 'offerwall-cta';
          retry.textContent = '🔄 Újra';
          retry.addEventListener('click', function(){ fetchOffers(provider); });
          cardsEl.appendChild(retry);
        });
    }

    function createMessageCard(message){
      var card = document.createElement('div');
      card.className = 'offerwall-card';
      card.textContent = message;
      return card;
    }

    function renderOfferCardsInto(container, offers, emptyMessage){
      if (!container) return;
      container.innerHTML = '';
      if (!offers || !offers.length) {
        container.appendChild(createMessageCard(emptyMessage || 'Jelenleg nincs elérhető feladat.'));
        return;
      }
      offers.forEach(function(offer){
        container.appendChild(createOfferCard(offer));
      });
    }

    function renderActiveOffers(offers){
      if (!activeEl) return;
      activeEl.innerHTML = '';
      if (!offers || !offers.length) {
        activeEl.appendChild(createMessageCard('Nincs folyamatban lévő feladatod.'));
        return;
      }
      offers.forEach(function(offer){
        activeEl.appendChild(createOfferCard(offer));
      });
    }

    // ===== HISTORY + TOAST POLLING =====
    var pollTimer = null;

    function startHistoryPolling(){
      if (pollTimer) clearInterval(pollTimer);
      fetchHistory();
      pollTimer = setInterval(fetchHistory, POLL_INTERVAL);
    }

    function renderHistory(items){
      if (!historyEl) return;
      historyEl.innerHTML = '';
      if (!items || !items.length) {
        historyEl.innerHTML = '<li>Nincs még teljesítés.</li>';
        return;
      }
      items.forEach(function(item){
        var li = document.createElement('li');
        var left = document.createElement('span');
        var right = document.createElement('span');
        left.textContent = (item.offer_name || 'Offer') + ' · ' + (item.provider || '');
        right.textContent = '+' + (item.points_awarded || 0) + ' pont, +' + (item.votes_awarded || 0) + ' szavazat';
        li.appendChild(left);
        li.appendChild(right);
        historyEl.appendChild(li);
      });

      // Toast detection: new items since last poll
      var currentIds = items.map(function(item){
        return (item.transaction_id || '') + '|' + (item.created_at || '');
      });
      if (lastHistoryIds.length > 0) {
        currentIds.forEach(function(id, i){
          if (lastHistoryIds.indexOf(id) === -1 && i < 3) {
            var item = items[i];
            if (item.status === 'reversed') {
              showToast('⚠️ Jóváírás visszavonva – ' + (item.offer_name || 'Feladat'), 'warning');
            } else if (item.status === 'capped') {
              showToast('ℹ️ Napi limit elérve – ' + (item.offer_name || 'Feladat'), 'info');
            } else {
              showToast('🎉 +' + (item.points_awarded || 0) + ' pont +' + (item.votes_awarded || 0) + ' szavazat – ' + (item.offer_name || 'Feladat'));
            }
          }
        });
      }
      lastHistoryIds = currentIds;
    }

    function fetchConfig(){
      return fetch(restBase + '/config', { credentials: 'include' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
          var providers = data ? data.providers : [];
          var ayetProvider = providers.find(function(p){ return p.key === 'ayet'; });
          if (ayetProvider) {
            fetchOffers(ayetProvider);
            return;
          }
          renderProviders(providers);
        });
    }

    function fetchHistory(){
      return fetch(restBase + '/history', { credentials: 'include' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){ renderHistory(data ? data.items : []); });
    }

    function fetchRewardStatus(){
      return fetchWithRetry(restBase + '/reward-status', { credentials: 'include' }, 1)
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){ return (data && data.campaigns) ? data.campaigns : []; });
    }

    function mergeRewardStatus(offers, campaigns){
      if (!offers || !offers.length || !campaigns || !campaigns.length) return offers;
      var map = {};
      campaigns.forEach(function(c){
        var offerId = c.offer_id || c.offerId || c.id || c.campaign_id || c.campaignId || '';
        if (offerId !== '') map[String(offerId)] = c;
      });

      offers.forEach(function(offer){
        var key = offer.offer_id != null ? String(offer.offer_id) : (offer.id != null ? String(offer.id) : '');
        var campaign = key && map[key] ? map[key] : null;
        if (!campaign || !offer.cpe_steps || !offer.cpe_steps.length) return;

        var tasks = campaign.tasks || campaign.cpe_instructions || campaign.steps || [];
        if (!Array.isArray(tasks) || !tasks.length) return;

        var taskMap = {};
        tasks.forEach(function(task){
          var name = task.task_name || task.taskName || task.event_name || task.eventName || '';
          if (name) taskMap[name] = task;
        });

        offer.cpe_steps.forEach(function(step, idx){
          var task = taskMap[step.task_name] || tasks[idx];
          if (!task) return;
          var status = task.status || task.task_status || task.state || '';
          if (status) step.status = status;
          if (task.remaining_time != null) step.remaining_time = task.remaining_time;
        });
      });

      return offers;
    }

    function fetchWithRetry(url, options, retries){
      retries = retries || 0;
      var baseDelay = 500;
      var attempt = function(count){
        return fetch(url, options).then(function(resp){
          if ((resp.status === 429 || resp.status >= 500) && count < retries) {
            return new Promise(function(resolve){
              setTimeout(function(){ resolve(attempt(count + 1)); }, baseDelay * Math.pow(2, count));
            });
          }
          return resp;
        });
      };
      return attempt(0);
    }

    function initTabsUI(){
      if (!tabsEl || !tabButtons.length) return;
      var panels = root.querySelectorAll('[data-panel]');
      tabButtons.forEach(function(btn){
        btn.addEventListener('click', function(){
          var target = btn.getAttribute('data-target');
          tabButtons.forEach(function(b){ b.classList.remove('is-active'); });
          btn.classList.add('is-active');
          panels.forEach(function(panel){
            panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
          });
        });
      });
    }

    function isMobileView(){
      return window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
    }

    function openTrackingLink(url){
      if (isMobileView()) {
        window.location.href = url;
        return;
      }
      window.open(url, '_blank', 'noopener,noreferrer');
    }

    function saveScrollPosition(){
      try {
        sessionStorage.setItem(SCROLL_KEY, String(window.scrollY || 0));
      } catch (e) {}
    }

    function restoreScrollPosition(){
      try {
        var saved = sessionStorage.getItem(SCROLL_KEY);
        if (saved != null) {
          sessionStorage.removeItem(SCROLL_KEY);
          window.scrollTo(0, parseInt(saved, 10) || 0);
        }
      } catch (e) {}
    }

    // ===== HELPERS =====
    function escHtml(str){
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str || ''));
      return div.innerHTML;
    }
    function escAttr(str){
      return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ===== INIT =====
    initTabsUI();
    initProviderTabs();
    initCpxSurvey();
    fetchConfig();
    fetchHistory();
  }

  document.addEventListener('DOMContentLoaded', function(){
    var root = document.getElementById('impactshop-offerwall');
    if (root) initOfferwall(root);
  });
})();
