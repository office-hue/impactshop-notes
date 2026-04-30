(function () {
  "use strict";

  var WIDGET_VERSION = "1.0.6";
  var SCRIPT_ATTR = "data-impact-auction-widget";
  var STYLE_ID = "impact-event-auction-widget-style-jvk";
  var DEFAULT_API_BASE = "https://app.sharity.hu/wp-json/impact/v1/event-auctions";
  var DEFAULT_FALLBACK_BASE = "https://app.sharity.hu/";

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) {
      return;
    }

    var style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent =
      "@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap');" +
      ".impact-auction-widget{--iaw-bg1:#060d2a;--iaw-bg2:#0d2f77;--iaw-accent:#c69a5f;--iaw-accent2:#f4ddae;--iaw-text:#f8f4ea;--iaw-muted:rgba(248,244,234,.74);--iaw-panel:rgba(5,15,47,.68);font-family:'Manrope','Segoe UI',system-ui,sans-serif;position:relative;overflow:hidden;border-radius:30px;padding:22px;border:1px solid rgba(255,255,255,.12);background:linear-gradient(145deg,var(--iaw-bg1),var(--iaw-bg2));box-shadow:0 26px 44px rgba(3,8,24,.34);color:var(--iaw-text);max-width:980px;min-width:280px}" +
      ".impact-auction-widget:before{content:'';position:absolute;inset:-34% -12% auto auto;width:68%;height:88%;background:radial-gradient(circle at center,rgba(244,221,174,.22),transparent 70%);pointer-events:none}" +
      ".impact-auction-widget__inner{position:relative;z-index:1;display:grid;gap:16px}" +
      ".impact-auction-widget__eyebrow{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:6px 11px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);font-size:11px;letter-spacing:.08em;text-transform:uppercase}" +
      ".impact-auction-widget__title{margin:0;font-family:'Cormorant Garamond',serif;font-size:40px;line-height:1.02;color:var(--iaw-accent2)}" +
      ".impact-auction-widget__subtitle,.impact-auction-widget__desc{margin:0;color:var(--iaw-muted);font-size:14px}" +
      ".impact-auction-widget__stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}" +
      ".impact-auction-widget__stat{padding:12px 14px;border-radius:16px;background:var(--iaw-panel);border:1px solid rgba(255,255,255,.1)}" +
      ".impact-auction-widget__stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--iaw-muted);margin-bottom:6px}" +
      ".impact-auction-widget__stat-value{font-size:18px;font-weight:800;line-height:1.15}" +
      ".impact-auction-widget__gallery{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(240px,280px);gap:12px;overflow-x:auto;overflow-y:hidden;padding-bottom:8px;scroll-snap-type:x proximity;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}" +
      ".impact-auction-widget__gallery::-webkit-scrollbar{height:10px}" +
      ".impact-auction-widget__gallery::-webkit-scrollbar-thumb{background:rgba(244,221,174,.28);border-radius:999px}" +
      ".impact-auction-widget__card{display:grid;gap:10px;padding:12px;border-radius:18px;border:1px solid rgba(255,255,255,.11);background:rgba(9,19,55,.62);cursor:pointer;transition:transform .18s ease,border-color .18s ease,background .18s ease;color:var(--iaw-text);scroll-snap-align:start;text-align:left;min-height:100%}" +
      ".impact-auction-widget__card:hover,.impact-auction-widget__card:focus-visible{transform:translateY(-2px);border-color:rgba(244,221,174,.42);background:rgba(10,21,59,.84);outline:none}" +
      ".impact-auction-widget__image{aspect-ratio:4/5;border-radius:14px;overflow:hidden;background:linear-gradient(160deg,rgba(255,255,255,.08),rgba(244,221,174,.12));position:relative;display:flex;align-items:stretch;justify-content:stretch}" +
      ".impact-auction-widget__image img{display:block;width:100%;height:100%;object-fit:cover}" +
      ".impact-auction-widget__image.is-fallback img{display:none}" +
      ".impact-auction-widget__image-fallback{display:none;position:absolute;inset:0;padding:16px;background:linear-gradient(180deg,rgba(6,13,42,.78),rgba(13,47,119,.88));align-content:end}" +
      ".impact-auction-widget__image.is-fallback .impact-auction-widget__image-fallback{display:grid}" +
      ".impact-auction-widget__image-fallback-badge{display:inline-flex;width:max-content;padding:5px 8px;border-radius:999px;background:rgba(244,221,174,.18);border:1px solid rgba(244,221,174,.28);font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--iaw-accent2);margin-bottom:8px}" +
      ".impact-auction-widget__image-fallback-title{font-family:'Cormorant Garamond',serif;font-size:26px;line-height:1.05;color:var(--iaw-accent2);margin:0 0 4px}" +
      ".impact-auction-widget__image-fallback-text{font-size:13px;line-height:1.4;color:var(--iaw-text);margin:0}" +
      ".impact-auction-widget__meta{display:grid;gap:4px}" +
      ".impact-auction-widget__artist{font-size:13px;color:var(--iaw-muted)}" +
      ".impact-auction-widget__lot-title{font-family:'Cormorant Garamond',serif;font-size:24px;line-height:1.04;margin:0;color:var(--iaw-text)}" +
      ".impact-auction-widget__price-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--iaw-muted)}" +
      ".impact-auction-widget__price{font-size:18px;font-weight:800;color:var(--iaw-accent2)}" +
      ".impact-auction-widget__badge{display:inline-flex;align-items:center;width:max-content;padding:5px 9px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--iaw-muted)}" +
      ".impact-auction-widget__status{min-height:18px;font-size:12px;color:var(--iaw-muted)}" +
      ".impact-auction-widget__status.is-error{color:#ffb4b4}" +
      ".impact-auction-widget__status.is-success{color:#9cffd6}" +
      ".impact-auction-widget__status.is-info{color:#93d5ff}"  +
      ".impact-auction-widget__drawer{position:fixed;inset:0;display:none;z-index:999999;background:rgba(4,9,22,.56);padding:18px}" +
      ".impact-auction-widget__drawer.is-open{display:flex;justify-content:flex-end}" +
      ".impact-auction-widget__panel{width:min(520px,100%);height:100%;overflow:auto;border-radius:24px;padding:20px;background:linear-gradient(180deg,rgba(6,13,42,.98),rgba(13,47,119,.96));border:1px solid rgba(255,255,255,.12);box-shadow:0 28px 50px rgba(0,0,0,.36)}" +
      ".impact-auction-widget__panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}" +
      ".impact-auction-widget__close{appearance:none;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.08);color:var(--iaw-text);border-radius:12px;padding:10px 12px;cursor:pointer}" +
      ".impact-auction-widget__detail-image{aspect-ratio:4/5;border-radius:16px;overflow:hidden;background:rgba(255,255,255,.08);margin-bottom:14px}" +
      ".impact-auction-widget__detail-image img{display:block;width:100%;height:100%;object-fit:cover}" +
      ".impact-auction-widget__detail-block{display:grid;gap:6px;margin-bottom:16px}" +
      ".impact-auction-widget__detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}" +
      ".impact-auction-widget__detail-item{padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);color:var(--iaw-text)}" +
      ".impact-auction-widget__detail-item small{display:block;margin-bottom:4px;color:var(--iaw-muted);font-size:11px;text-transform:uppercase;letter-spacing:.06em}" +
      ".impact-auction-widget__detail-item span{color:var(--iaw-accent2);font-weight:800}" +
      ".impact-auction-widget__bid-form{display:grid;gap:10px;padding:14px;border-radius:18px;background:rgba(5,15,47,.62);border:1px solid rgba(255,255,255,.1)}" +
      ".impact-auction-widget__input{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.16);background:rgba(5,15,47,.58);color:var(--iaw-text);font-size:14px;padding:11px 12px;box-sizing:border-box}" +
      ".impact-auction-widget__input:focus{outline:none;border-color:rgba(244,221,174,.72);box-shadow:0 0 0 3px rgba(244,221,174,.18)}" +
      ".impact-auction-widget__row{display:grid;grid-template-columns:1fr 1fr;gap:8px}" +
      ".impact-auction-widget__presets{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}" +
      ".impact-auction-widget__preset{appearance:none;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:var(--iaw-accent2);border-radius:12px;padding:10px 12px;font-weight:700;cursor:pointer}" +
      ".impact-auction-widget__submit{appearance:none;border:none;border-radius:14px;padding:12px 14px;background:linear-gradient(110deg,var(--iaw-accent),#b88142 62%,var(--iaw-accent2));color:#111a2f;font-weight:800;letter-spacing:.03em;cursor:pointer;text-transform:uppercase}" +
      ".impact-auction-widget__note{font-size:12px;color:var(--iaw-muted)}" +
      "@media (max-width:900px){.impact-auction-widget__stats{grid-template-columns:repeat(2,minmax(0,1fr))}.impact-auction-widget__gallery{grid-auto-columns:minmax(220px,260px)}}" +
      "@media (max-width:640px){.impact-auction-widget{padding:18px;border-radius:24px}.impact-auction-widget__title{font-size:32px}.impact-auction-widget__stats{grid-template-columns:1fr}.impact-auction-widget__gallery{grid-auto-columns:minmax(78vw,78vw)}.impact-auction-widget__row,.impact-auction-widget__detail-grid,.impact-auction-widget__presets{grid-template-columns:1fr}.impact-auction-widget__drawer{padding:0}.impact-auction-widget__panel{width:100%;height:100%;border-radius:0}}" +
      ".impact-auction-widget__countdown{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:999px;background:rgba(198,154,95,.13);border:1px solid rgba(198,154,95,.28);font-size:12px;font-weight:700;letter-spacing:.04em;color:var(--iaw-accent2);white-space:nowrap;margin-top:4px}" +
      ".impact-auction-widget__countdown.is-urgent{background:rgba(255,80,80,.15);border-color:rgba(255,100,100,.45);color:#ff9898;animation:iaw-pulse 1s ease-in-out infinite}" +
      ".impact-auction-widget__countdown.is-expired{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1);color:var(--iaw-muted)}" +
      ".impact-auction-widget__detail-countdown{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:14px;background:rgba(198,154,95,.1);border:1px solid rgba(198,154,95,.28);margin-bottom:14px;font-size:14px;font-weight:700;color:var(--iaw-accent2)}" +
      ".impact-auction-widget__detail-countdown.is-urgent{background:rgba(255,60,60,.13);border-color:rgba(255,80,80,.45);color:#ffaaaa;animation:iaw-pulse 1s ease-in-out infinite}" +
      ".impact-auction-widget__detail-countdown.is-expired,.impact-auction-widget__countdown.is-expired{display:none}" +
      "@keyframes iaw-pulse{0%,100%{opacity:1}50%{opacity:.6}}";
    document.head.appendChild(style);
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function toNumber(value) {
    var n = Number(value);
    return Number.isFinite(n) ? n : 0;
  }

  function formatAmount(amount, currency) {
    var code = String(currency || "HUF").toUpperCase();
    if (code === "HUF") {
      return Math.round(toNumber(amount)).toLocaleString("hu-HU") + " Ft";
    }
    return toNumber(amount).toLocaleString("hu-HU", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " " + code;
  }

  function requestJson(url, options) {
    return fetch(url, options || { credentials: "omit" }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          var error = new Error((data && data.error) || "request_failed");
          error.payload = data || {};
          throw error;
        }
        return data;
      });
    });
  }

  function createIdempotencyKey() {
    return "bid_" + Date.now() + "_" + Math.random().toString(36).slice(2, 10);
  }

  function shouldAutoScrollGallery(gallery, lots) {
    return !!gallery && Array.isArray(lots) && lots.length > 1 && gallery.scrollWidth > gallery.clientWidth + 24 && !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }

  function renderImageMarkup(lot, className) {
    var hasImage = !!lot.image_url;
    return (
      '<div class="' + className + (hasImage ? '' : ' is-fallback') + '">' +
      (hasImage ? '<img src="' + escapeHtml(lot.image_url) + '" alt="' + escapeHtml((lot.artist_name || '') + ' - ' + (lot.item_title || '')) + '" loading="lazy">' : '') +
      '<div class="impact-auction-widget__image-fallback">' +
      '<span class="impact-auction-widget__image-fallback-badge">Kép hamarosan</span>' +
      '<h5 class="impact-auction-widget__image-fallback-title">' + escapeHtml(lot.item_title || 'Aukciós tétel') + '</h5>' +
      '<p class="impact-auction-widget__image-fallback-text">' + escapeHtml(lot.artist_name || 'A műtárgy képe feltöltés alatt.') + '</p>' +
      '</div>' +
      '</div>'
    );
  }

  function attachImageFallbacks(scope) {
    Array.prototype.forEach.call(scope.querySelectorAll('.impact-auction-widget__image img, .impact-auction-widget__detail-image img'), function (img) {
      img.addEventListener('error', function () {
        if (img.parentNode) {
          img.parentNode.classList.add('is-fallback');
        }
      });
    });
  }

  function createMarkup() {
    return (
      '<section class="impact-auction-widget" role="region" aria-label="Jövőnk Vize aukció widget">' +
      '<div class="impact-auction-widget__inner">' +
      '<span class="impact-auction-widget__eyebrow">Gála aukció widget</span>' +
      '<h3 class="impact-auction-widget__title" data-role="title">Jövőnk Vize gála aukció</h3>' +
      '<p class="impact-auction-widget__subtitle" data-role="subtitle">Miele műtárgyak és felajánlások</p>' +
      '<p class="impact-auction-widget__desc" data-role="description">Jótékonysági aukció a Jövőnk Vize gálán.</p>' +
      '<div class="impact-auction-widget__stats">' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Kampány teljes összeg</div><div class="impact-auction-widget__stat-value" data-role="stat-combined">0 Ft</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Aukció befolyt összeg</div><div class="impact-auction-widget__stat-value" data-role="stat-auction-paid">0 Ft</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Lezárt lotok</div><div class="impact-auction-widget__stat-value" data-role="stat-closed-lots">0</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Tételszám</div><div class="impact-auction-widget__stat-value" data-role="stat-lots">0</div></div>' +
      '</div>' +
      '<div class="impact-auction-widget__gallery" data-role="gallery"></div>' +
      '<div class="impact-auction-widget__status" data-role="status" aria-live="polite"></div>' +
      '</div>' +
      '<div class="impact-auction-widget__drawer" data-role="drawer">' +
      '<div class="impact-auction-widget__panel">' +
      '<div class="impact-auction-widget__panel-head">' +
      '<div><span class="impact-auction-widget__badge" data-role="detail-badge">Tétel</span></div>' +
      '<button type="button" class="impact-auction-widget__close" data-role="close">Bezárás</button>' +
      '</div>' +
      '<div class="impact-auction-widget__detail-countdown" data-role="detail-countdown" style="display:none"></div>' +
      '<div class="impact-auction-widget__detail-image" data-role="detail-image"></div>' +
      '<div class="impact-auction-widget__detail-block">' +
      '<div class="impact-auction-widget__artist" data-role="detail-artist"></div>' +
      '<h4 class="impact-auction-widget__lot-title" data-role="detail-title"></h4>' +
      '<p class="impact-auction-widget__desc" data-role="detail-desc"></p>' +
      '</div>' +
      '<div class="impact-auction-widget__detail-grid">' +
      '<div class="impact-auction-widget__detail-item"><small>Megjelenített ár</small><span data-role="detail-price"></span></div>' +
      '<div class="impact-auction-widget__detail-item"><small>Minimum következő licit</small><span data-role="detail-next-bid"></span></div>' +
      '<div class="impact-auction-widget__detail-item"><small>Technika</small><span data-role="detail-medium"></span></div>' +
      '<div class="impact-auction-widget__detail-item"><small>Méret</small><span data-role="detail-dimensions"></span></div>' +
      '</div>' +
      '<form class="impact-auction-widget__bid-form" data-role="bid-form">' +
      '<div class="impact-auction-widget__presets" data-role="presets"></div>' +
      '<input class="impact-auction-widget__input" data-role="bid-amount" type="text" inputmode="numeric" placeholder="Licitár (Ft)">' +
      '<div class="impact-auction-widget__row">' +
      '<input class="impact-auction-widget__input" data-role="bid-email" type="email" placeholder="E-mail cím" required>' +
      '<input class="impact-auction-widget__input" data-role="bid-phone" type="text" placeholder="Telefonszám (opcionális)">' +
      '</div>' +
      '<input class="impact-auction-widget__input" data-role="bid-name" type="text" placeholder="Név (opcionális)">' +
      '<div class="impact-auction-widget__note">Az e-mail cím megadása kötelező. A telefonszám megadása nem kötelező.</div>' +
      '<button class="impact-auction-widget__submit" type="submit">Licit megerősítése</button>' +
      '<div class="impact-auction-widget__status" data-role="detail-status" aria-live="polite"></div>' +
      '</form>' +
      '</div>' +
      '</div>' +
      '</section>'
    );
  }

  function setStatus(el, message, type) {
    if (!el) {
      return;
    }
    el.textContent = message || "";
    el.classList.remove("is-error", "is-success", "is-info");
    if (type === "error") {
      el.classList.add("is-error");
    } else if (type === "success") {
      el.classList.add("is-success");
    } else if (type === "info") {
      el.classList.add("is-info");
    }
  }

  function formatCountdown(endTimeIso) {
    if (!endTimeIso) { return null; }
    var diff = new Date(endTimeIso).getTime() - Date.now();
    if (diff <= 0) { return { text: "Lejárt", urgent: false, expired: true }; }
    var totalSec = Math.floor(diff / 1000);
    var h = Math.floor(totalSec / 3600);
    var m = Math.floor((totalSec % 3600) / 60);
    var s = totalSec % 60;
    var text = (h > 0 ? h + " ó " : "") + (h > 0 || m > 0 ? m + " p " : "") + s + " mp";
    return { text: text, urgent: totalSec <= 120, expired: false };
  }

  function mountWidget(mountEl, config) {
    var apiBase = (config.apiBase || DEFAULT_API_BASE).replace(/\/$/, "");
    var campaign = config.campaign || "jovonkvize-2026";
    var pollMs = Math.max(15000, Number(config.pollMs) || 30000);
    var state = { payload: null, activeLot: null, sessionToken: "", bidderToken: "", autoScrollFrame: 0, autoScrollPausedByUser: false, autoScrollLastTs: 0, autoScrollStartAt: 0, autoScrollLoopPauseUntil: 0 };

    mountEl.innerHTML = createMarkup();
    var root = mountEl.querySelector(".impact-auction-widget");
    var els = {
      title: root.querySelector('[data-role="title"]'),
      subtitle: root.querySelector('[data-role="subtitle"]'),
      description: root.querySelector('[data-role="description"]'),
      statCombined: root.querySelector('[data-role="stat-combined"]'),
      statAuctionPaid: root.querySelector('[data-role="stat-auction-paid"]'),
      statClosedLots: root.querySelector('[data-role="stat-closed-lots"]'),
      statLots: root.querySelector('[data-role="stat-lots"]'),
      gallery: root.querySelector('[data-role="gallery"]'),
      status: root.querySelector('[data-role="status"]'),
      drawer: root.querySelector('[data-role="drawer"]'),
      close: root.querySelector('[data-role="close"]'),
      detailBadge: root.querySelector('[data-role="detail-badge"]'),
      detailImage: root.querySelector('[data-role="detail-image"]'),
      detailArtist: root.querySelector('[data-role="detail-artist"]'),
      detailTitle: root.querySelector('[data-role="detail-title"]'),
      detailDesc: root.querySelector('[data-role="detail-desc"]'),
      detailPrice: root.querySelector('[data-role="detail-price"]'),
      detailNextBid: root.querySelector('[data-role="detail-next-bid"]'),
      detailMedium: root.querySelector('[data-role="detail-medium"]'),
      detailDimensions: root.querySelector('[data-role="detail-dimensions"]'),
      bidForm: root.querySelector('[data-role="bid-form"]'),
      bidAmount: root.querySelector('[data-role="bid-amount"]'),
      bidEmail: root.querySelector('[data-role="bid-email"]'),
      bidPhone: root.querySelector('[data-role="bid-phone"]'),
      bidName: root.querySelector('[data-role="bid-name"]'),
      presets: root.querySelector('[data-role="presets"]'),
      detailStatus: root.querySelector('[data-role="detail-status"]'),
      detailCountdown: root.querySelector('[data-role="detail-countdown"]')
    };

    function stopAutoScroll(permanent) {
      if (state.autoScrollFrame) {
        window.cancelAnimationFrame(state.autoScrollFrame);
        state.autoScrollFrame = 0;
      }
      state.autoScrollLastTs = 0;
      state.autoScrollStartAt = 0;
      state.autoScrollLoopPauseUntil = 0;
      if (permanent) {
        state.autoScrollPausedByUser = true;
      }
    }

    function startAutoScroll() {
      if (!shouldAutoScrollGallery(els.gallery, state.payload && state.payload.lots) || state.autoScrollPausedByUser) {
        stopAutoScroll(false);
        return;
      }

      stopAutoScroll(false);

      var maxScroll = Math.max(0, els.gallery.scrollWidth - els.gallery.clientWidth);
      if (!maxScroll) {
        return;
      }

      state.autoScrollStartAt = window.performance.now() + 1800;

      function step(ts) {
        if (!shouldAutoScrollGallery(els.gallery, state.payload && state.payload.lots) || state.autoScrollPausedByUser) {
          stopAutoScroll(false);
          return;
        }

        if (state.autoScrollStartAt && ts < state.autoScrollStartAt) {
          state.autoScrollFrame = window.requestAnimationFrame(step);
          return;
        }

        if (state.autoScrollLoopPauseUntil && ts < state.autoScrollLoopPauseUntil) {
          state.autoScrollFrame = window.requestAnimationFrame(step);
          return;
        }

        if (!state.autoScrollLastTs) {
          state.autoScrollLastTs = ts;
        }

        var delta = ts - state.autoScrollLastTs;
        state.autoScrollLastTs = ts;
        var nextLeft = els.gallery.scrollLeft + delta * 0.022;

        if (nextLeft >= maxScroll - 1) {
          els.gallery.scrollLeft = 0;
          state.autoScrollLastTs = 0;
          state.autoScrollLoopPauseUntil = ts + 1200;
        } else {
          els.gallery.scrollLeft = nextLeft;
          state.autoScrollLoopPauseUntil = 0;
        }

        state.autoScrollFrame = window.requestAnimationFrame(step);
      }

      state.autoScrollFrame = window.requestAnimationFrame(step);
    }

    function bindAutoScrollGuards() {
      ["pointerdown", "wheel", "touchstart", "keydown", "focusin"].forEach(function (eventName) {
        els.gallery.addEventListener(eventName, function () {
          stopAutoScroll(true);
        }, { passive: true });
      });
    }

    bindAutoScrollGuards();

    function detailOpen(lot) {
      stopAutoScroll(true);
      state.activeLot = lot;
      els.detailBadge.textContent = (lot.status || "draft").toUpperCase();
      els.detailArtist.textContent = lot.artist_name || "";
      els.detailTitle.textContent = lot.item_title || "";
      els.detailDesc.textContent = lot.description_long || lot.description_short || "";
      els.detailPrice.textContent = (lot.display_label || "Ár") + ": " + (lot.display_amount_formatted || "");
      els.detailMedium.textContent = lot.medium || "-";
      els.detailDimensions.textContent = lot.dimensions || "-";
      els.detailNextBid.textContent = formatAmount((lot.display_amount || lot.starting_bid || 0) + (lot.min_increment || 0), "HUF");
      els.detailImage.innerHTML = renderImageMarkup(lot, 'impact-auction-widget__detail-image');
      renderPresets(lot);
      els.bidAmount.value = String((lot.display_amount || lot.starting_bid || 0) + (lot.min_increment || 0));
      try {
        var savedBidder = JSON.parse(localStorage.getItem("iaw_bidder") || "null");
        if (savedBidder) {
          if (savedBidder.email) els.bidEmail.value = savedBidder.email;
          if (savedBidder.phone) els.bidPhone.value = savedBidder.phone;
          if (savedBidder.name) els.bidName.value = savedBidder.name;
        }
      } catch (e) {}
      attachImageFallbacks(els.detailImage);
      setStatus(els.detailStatus, "", "");
      if (els.detailCountdown) {
        if (lot.end_time && lot.status === "live") {
          els.detailCountdown.setAttribute("data-end-time", lot.end_time);
          els.detailCountdown.style.display = "";
          var cd = formatCountdown(lot.end_time);
          if (cd) {
            els.detailCountdown.textContent = "⏱ " + cd.text;
            els.detailCountdown.className = "impact-auction-widget__detail-countdown" + (cd.urgent ? " is-urgent" : "") + (cd.expired ? " is-expired" : "");
          }
        } else {
          els.detailCountdown.style.display = "none";
          els.detailCountdown.removeAttribute("data-end-time");
        }
      }
      els.drawer.classList.add("is-open");
    }

    function detailClose() {
      els.drawer.classList.remove("is-open");
      state.activeLot = null;
    }

    function renderPresets(lot) {
      var base = toNumber(lot.display_amount || lot.starting_bid || 0);
      var increments = [1, 2, 5, 10].map(function (multiplier) {
        return base + toNumber(lot.min_increment || 0) * multiplier;
      });
      els.presets.innerHTML = increments.map(function (amount) {
        return '<button type="button" class="impact-auction-widget__preset" data-amount="' + amount + '">' + escapeHtml(formatAmount(amount, "HUF")) + '</button>';
      }).join("");
      Array.prototype.forEach.call(els.presets.querySelectorAll("button"), function (button) {
        button.addEventListener("click", function () {
          els.bidAmount.value = button.getAttribute("data-amount") || "";
        });
      });
    }

    function renderLots(payload) {
      var lots = Array.isArray(payload.lots) ? payload.lots : [];
      els.gallery.innerHTML = lots.map(function (lot) {
        return (
          '<button type="button" class="impact-auction-widget__card" data-lot="' + escapeHtml(lot.item_slug || "") + '">' +
          renderImageMarkup(lot, 'impact-auction-widget__image') +
          '<div class="impact-auction-widget__meta">' +
          '<span class="impact-auction-widget__artist">' + escapeHtml(lot.artist_name || "") + '</span>' +
          '<h4 class="impact-auction-widget__lot-title">' + escapeHtml(lot.item_title || "") + '</h4>' +
          '<span class="impact-auction-widget__badge">' + escapeHtml((lot.status || "draft").toUpperCase()) + '</span>' +
          '<span class="impact-auction-widget__price-label">' + escapeHtml(lot.display_label || "Ár") + '</span>' +
          '<span class="impact-auction-widget__price">' + escapeHtml(lot.display_amount_formatted || formatAmount(lot.starting_bid || 0, "HUF")) + '</span>' +
          (lot.end_time && lot.status === "live" ? '<span class="impact-auction-widget__countdown" data-role="card-countdown" data-end-time="' + escapeHtml(lot.end_time) + '">...</span>' : '') +
          '</div>' +
          '</button>'
        );
      }).join("");

      Array.prototype.forEach.call(els.gallery.querySelectorAll("[data-lot]"), function (button) {
        button.addEventListener("click", function () {
          var slug = button.getAttribute("data-lot");
          var lot = (state.payload.lots || []).find(function (item) {
            return item.item_slug === slug;
          });
          if (lot) {
            detailOpen(lot);
          }
        });
      });
      attachImageFallbacks(els.gallery);
      startAutoScroll();
    }

    function renderPayload(payload) {
      state.payload = payload;
      state.sessionToken = (payload.security && payload.security.session_token) || "";
      els.title.textContent = payload.title || "Jövőnk Vize gála aukció";
      els.subtitle.textContent = payload.subtitle || "";
      els.description.textContent = payload.description || "";
      els.statCombined.textContent = (payload.stats && payload.stats.combined_paid_total_amount_formatted) || "0 Ft";
      els.statAuctionPaid.textContent = (payload.stats && payload.stats.auction_paid_total_amount_formatted) || "0 Ft";
      els.statClosedLots.textContent = String((payload.stats && payload.stats.closed_lots_count) || 0);
      els.statLots.textContent = String((payload.stats && payload.stats.auction_lots_count) || (payload.lots || []).length || 0);
      renderLots(payload);
      setStatus(els.status, payload.security && payload.security.write_enabled
        ? ""
        : "", (payload.security && payload.security.write_enabled) ? "success" : "");
    }

    function loadPublic() {
      return requestJson(apiBase + "/" + encodeURIComponent(campaign) + "/public").then(renderPayload).catch(function (error) {
        setStatus(els.status, "Az aukció adatai nem tölthetők be: " + error.message, "error");
      });
    }

    els.close.addEventListener("click", detailClose);
    els.drawer.addEventListener("click", function (event) {
      if (event.target === els.drawer) {
        detailClose();
      }
    });

    els.bidForm.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!state.activeLot) {
        setStatus(els.detailStatus, "Nincs aktív tétel kiválasztva.", "error");
        return;
      }
      if (!els.bidEmail.value.trim()) {
        setStatus(els.detailStatus, "Az e-mail cím megadása kötelező.", "error");
        return;
      }

      if (!state.sessionToken) {
        setStatus(els.detailStatus, "A licitküldés jelenleg nem engedélyezett.", "error");
        return;
      }

      var registerUrl = apiBase + "/" + encodeURIComponent(campaign) + "/register-bidder";
      var bidUrl = apiBase + "/" + encodeURIComponent(campaign) + "/lots/" + encodeURIComponent(state.activeLot.item_slug) + "/bid";
      var payload = {
        session_token: state.sessionToken,
        email: els.bidEmail.value.trim(),
        phone: els.bidPhone.value.trim(),
        display_name: els.bidName.value.trim()
      };

      setStatus(els.detailStatus, "Regisztráció folyamatban...", "");

      var setupUrl = apiBase + "/" + encodeURIComponent(campaign) + "/setup-payment";

      requestJson(registerUrl, {
        method: "POST",
        credentials: "omit",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (registerResponse) {
        state.bidderToken = registerResponse.bidder_token || "";
        setStatus(els.detailStatus, "Bankkártya ellenőrzése...", "");

        return requestJson(setupUrl, {
          method: "POST",
          credentials: "omit",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            session_token: state.sessionToken,
            bidder_token: state.bidderToken,
            return_url: window.location.href
          })
        });
      }).then(function (setupResponse) {
        if (setupResponse.status === "setup_required" && setupResponse.setup_url) {
          window.open(setupResponse.setup_url, "_blank", "noopener,noreferrer");
          setStatus(els.detailStatus, "A bankkártya beállításához egy új lap nyílt meg. Miután elvégezte, kattintson újra a Licitálás gombra.", "info");
          return Promise.reject({ _redirect: true });
        }
        setStatus(els.detailStatus, "Licit rögzítése folyamatban...", "");

        return requestJson(bidUrl, {
          method: "POST",
          credentials: "omit",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            session_token: state.sessionToken,
            bidder_token: state.bidderToken,
            bid_amount: els.bidAmount.value,
            idempotency_key: createIdempotencyKey()
          })
        });
      }).then(function (bidResponse) {
        try {
          localStorage.setItem("iaw_bidder", JSON.stringify({
            email: els.bidEmail.value.trim(),
            phone: els.bidPhone.value.trim(),
            name: els.bidName.value.trim()
          }));
        } catch (e) {}
        setStatus(els.detailStatus, "Licit rögzítve: " + (bidResponse.bid_amount_formatted || ""), "success");
        return loadPublic().then(function () {
          if (!state.payload || !Array.isArray(state.payload.lots)) {
            return;
          }
          var refreshedLot = state.payload.lots.find(function (item) {
            return item.item_slug === state.activeLot.item_slug;
          });
          if (refreshedLot) {
            detailOpen(refreshedLot);
          }
        });
      }).catch(function (error) {
        if (error && error._redirect) {
          return; // redirect in progress, suppress error display
        }
        var payload = error && error.payload ? error.payload : {};
        if (payload.minimum_required_formatted) {
          setStatus(els.detailStatus, "A licit túl alacsony. Minimum: " + payload.minimum_required_formatted, "error");
          els.bidAmount.value = String(payload.minimum_required || "");
          return;
        }
        setStatus(els.detailStatus, "Licitküldés sikertelen: " + (payload.error || error.message || "ismeretlen hiba"), "error");
      });
    });

    // ── Countdown tick: minden másodpercben frissíti a visszaszámlálókat ─────────────────
    window.setInterval(function () {
      // Kártyák: gallery-ban lévő countdown spanek
      Array.prototype.forEach.call(
        root.querySelectorAll('[data-role="card-countdown"][data-end-time]'),
        function (el) {
          var cd = formatCountdown(el.getAttribute("data-end-time"));
          if (!cd) { return; }
          el.textContent = cd.text;
          el.className = "impact-auction-widget__countdown" +
            (cd.urgent ? " is-urgent" : "") +
            (cd.expired ? " is-expired" : "");
        }
      );
      // Detail panel countdown
      if (els.detailCountdown && els.detailCountdown.getAttribute("data-end-time")) {
        var cd2 = formatCountdown(els.detailCountdown.getAttribute("data-end-time"));
        if (cd2) {
          els.detailCountdown.textContent = "⏱ " + cd2.text;
          els.detailCountdown.className = "impact-auction-widget__detail-countdown" +
            (cd2.urgent ? " is-urgent" : "") +
            (cd2.expired ? " is-expired" : "");
        }
      }
    }, 1000);
    // ────────────────────────────────────────────────────────────────────────

    loadPublic().then(function () {
      var urlParams      = new URLSearchParams(window.location.search);
      var cardSetupParam = urlParams.get("ea_card_setup");
      var urlSessionId   = urlParams.get("session_id");
      var urlBidderUuid  = urlParams.get("bidder_uuid");
      var urlCampaignSlug = urlParams.get("campaign_slug");

      function cleanCardSetupUrl() {
        urlParams.delete("ea_card_setup");
        urlParams.delete("session_id");
        urlParams.delete("bidder_uuid");
        urlParams.delete("campaign_slug");
        var qs = urlParams.toString();
        history.replaceState(null, "", window.location.pathname + (qs ? "?" + qs : "") + window.location.hash);
      }

      if (cardSetupParam === "success" && urlSessionId && urlBidderUuid && urlCampaignSlug === campaign) {
        setStatus(els.status, "Bankkártya megerősítése folyamatban...", "info");
        var confirmUrl = apiBase + "/" + encodeURIComponent(campaign) + "/confirm-card-setup";
        requestJson(confirmUrl, {
          method: "POST",
          credentials: "omit",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ session_id: urlSessionId, bidder_uuid: urlBidderUuid })
        }).then(function () {
          cleanCardSetupUrl();
          setStatus(els.status, "Bankkártya sikeresen rögzítve! Adja meg az adatait és licitáljon.", "success");
        }).catch(function () {
          cleanCardSetupUrl();
          setStatus(els.status, "Bankkártya megerősítés sikertelen. Kérjük, töltse ki újra az adatait.", "error");
        });
      } else if (cardSetupParam === "cancelled") {
        cleanCardSetupUrl();
        setStatus(els.status, "Bankkártya rögzítés megszakítva.", "error");
      }

      // Deep link: ?lot=<item_slug> — auto-opens the given lot's detail view
      var lotParam = urlParams.get("lot");
      if (lotParam && state.payload && Array.isArray(state.payload.lots)) {
        var deepLinkedLot = state.payload.lots.find(function (item) {
          return item.item_slug === lotParam;
        });
        if (deepLinkedLot) {
          detailOpen(deepLinkedLot);
        }
      }
    });
    window.setInterval(loadPublic, pollMs);
  }

  function bootstrap() {
    ensureStyles();
    var scripts = document.querySelectorAll("script[" + SCRIPT_ATTR + "]");
    Array.prototype.forEach.call(scripts, function (script, index) {
      if (script.__impactAuctionMounted) {
        return;
      }
      script.__impactAuctionMounted = true;

      var targetSelector = script.getAttribute("data-target") || "";
      var mountEl = targetSelector ? document.querySelector(targetSelector) : null;
      if (!mountEl) {
        mountEl = document.createElement("div");
        mountEl.id = "impact-auction-widget-root-" + index;
        script.parentNode.insertBefore(mountEl, script.nextSibling);
      }

      mountWidget(mountEl, {
        campaign: script.getAttribute("data-campaign") || "jovonkvize-2026",
        apiBase: script.getAttribute("data-api-base") || DEFAULT_API_BASE,
        fallbackApiBase: script.getAttribute("data-fallback-api-base") || DEFAULT_FALLBACK_BASE,
        pollMs: script.getAttribute("data-poll-ms") || 30000
      });
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootstrap);
  } else {
    bootstrap();
  }
})();