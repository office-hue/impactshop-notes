(function () {
  "use strict";

  var WIDGET_VERSION = "1.0.1";
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
      "@media (max-width:640px){.impact-auction-widget{padding:18px;border-radius:24px}.impact-auction-widget__title{font-size:32px}.impact-auction-widget__stats{grid-template-columns:1fr}.impact-auction-widget__gallery{grid-auto-columns:minmax(78vw,78vw)}.impact-auction-widget__row,.impact-auction-widget__detail-grid,.impact-auction-widget__presets{grid-template-columns:1fr}.impact-auction-widget__drawer{padding:0}.impact-auction-widget__panel{width:100%;height:100%;border-radius:0}}";
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
      '<p class="impact-auction-widget__desc" data-role="description">Additív frontend lane a Jövőnk Vize aukciós flow-hoz.</p>' +
      '<div class="impact-auction-widget__stats">' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Kampány teljes összeg</div><div class="impact-auction-widget__stat-value" data-role="stat-combined">0 Ft</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Aukcio paid total</div><div class="impact-auction-widget__stat-value" data-role="stat-auction-paid">0 Ft</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Lezárt lotok</div><div class="impact-auction-widget__stat-value" data-role="stat-closed-lots">0</div></div>' +
      '<div class="impact-auction-widget__stat"><div class="impact-auction-widget__stat-label">Tételszám</div><div class="impact-auction-widget__stat-value" data-role="stat-lots">0</div></div>' +
      '</div>' +
      '<div class="impact-auction-widget__gallery" data-role="gallery"></div>' +
      '<div class="impact-auction-widget__status" data-role="status" aria-live="polite"></div>' +
      '</div>' +
      '<div class="impact-auction-widget__drawer" data-role="drawer">' +
      '<div class="impact-auction-widget__panel">' +
      '<div class="impact-auction-widget__panel-head">' +
      '<div><span class="impact-auction-widget__badge" data-role="detail-badge">Scaffold</span></div>' +
      '<button type="button" class="impact-auction-widget__close" data-role="close">Bezárás</button>' +
      '</div>' +
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
      '<div class="impact-auction-widget__note">Az e-mail kötelező. Az SMS lane külön disclosure és későbbi backend-bekötés után aktiválható.</div>' +
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
    el.classList.remove("is-error", "is-success");
    if (type === "error") {
      el.classList.add("is-error");
    } else if (type === "success") {
      el.classList.add("is-success");
    }
  }

  function mountWidget(mountEl, config) {
    var apiBase = (config.apiBase || DEFAULT_API_BASE).replace(/\/$/, "");
    var campaign = config.campaign || "jovonkvize-2026";
    var pollMs = Math.max(15000, Number(config.pollMs) || 30000);
    var state = { payload: null, activeLot: null, sessionToken: "", bidderToken: "" };

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
      detailStatus: root.querySelector('[data-role="detail-status"]')
    };

    function detailOpen(lot) {
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
      attachImageFallbacks(els.detailImage);
      setStatus(els.detailStatus, "A bidder regisztráció és a licit submit lane aktív. A winner-payment backend kész, de a triggerelő admin UI külön fázisban jön.", "");
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
          '<span class="impact-auction-widget__price-label">' + escapeHtml(lot.display_label || "Ar") + '</span>' +
          '<span class="impact-auction-widget__price">' + escapeHtml(lot.display_amount_formatted || formatAmount(lot.starting_bid || 0, "HUF")) + '</span>' +
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
        ? "A read API és az alap bidder/bid write lane aktív. A winner-payment backend kész, de frontend/admin trigger UI még nincs."
        : "A read API aktív, de ezen az originen a write lane nincs engedélyezve.", (payload.security && payload.security.write_enabled) ? "success" : "");
    }

    function loadPublic() {
      return requestJson(apiBase + "/" + encodeURIComponent(campaign) + "/public").then(renderPayload).catch(function (error) {
        setStatus(els.status, "A public scaffold payload nem tölthető be: " + error.message, "error");
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
        setStatus(els.detailStatus, "Az e-mail cím kötelező a scaffold szerinti bidder flowban.", "error");
        return;
      }

      if (!state.sessionToken) {
        setStatus(els.detailStatus, "Ezen az originen nincs write session token. A licitküldés nem engedélyezett.", "error");
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

      setStatus(els.detailStatus, "Bidder regisztráció folyamatban...", "");

      requestJson(registerUrl, {
        method: "POST",
        credentials: "omit",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (registerResponse) {
        state.bidderToken = registerResponse.bidder_token || "";
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
        var payload = error && error.payload ? error.payload : {};
        if (payload.minimum_required_formatted) {
          setStatus(els.detailStatus, "A licit tul alacsony. Minimum: " + payload.minimum_required_formatted, "error");
          els.bidAmount.value = String(payload.minimum_required || "");
          return;
        }
        setStatus(els.detailStatus, "Licitküldés sikertelen: " + (payload.error || error.message || "ismeretlen hiba"), "error");
      });
    });

    loadPublic();
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