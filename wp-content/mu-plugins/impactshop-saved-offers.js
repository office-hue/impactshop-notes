(function () {
  "use strict";

  const config = window.impactshopSavedOffers || {};
  const strings = config.strings || {};
  const restBase = String(config.restBase || "/wp-json/impact/v1");
  const restNonce = String(config.nonce || "");
  const state = {
    currentOffer: null,
    lastFingerprint: "",
    identityReady: false,
  };

  function text(value) {
    return String(value || "").trim();
  }

  function isVisible(el) {
    if (!el) return false;
    const style = window.getComputedStyle(el);
    if (style.display === "none" || style.visibility === "hidden" || Number(style.opacity || 1) === 0) {
      return false;
    }
    if (el.hidden) return false;
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function isOfferContainerActive(el) {
    if (!el) return false;
    if (el.hidden) return false;
    const style = window.getComputedStyle(el);
    if (style.display === "none" || style.visibility === "hidden") {
      return false;
    }
    return true;
  }

  function safeUrl(value) {
    const raw = text(value);
    if (!raw || raw === "#") return "";
    try {
      return new URL(raw, window.location.origin).toString();
    } catch (e) {
      return "";
    }
  }

  function simpleHash(input) {
    const value = String(input || "");
    let hash = 0;
    for (let i = 0; i < value.length; i += 1) {
      hash = ((hash << 5) - hash) + value.charCodeAt(i);
      hash |= 0;
    }
    return "so_" + Math.abs(hash);
  }

  function extractShopSlug(url) {
    try {
      const parsed = new URL(url);
      const match = parsed.pathname.match(/\/go(?:-deal)?\/([^/?#]+)/i);
      if (match) {
        return match[1];
      }
      const host = String(parsed.hostname || "").toLowerCase();
      if (host && !/^(app\.sharity\.hu|sharity\.hu|www\.sharity\.hu)$/i.test(host)) {
        return host.replace(/^www\./, "").replace(/\.[a-z.]+$/i, "");
      }
    } catch (e) {
      return "";
    }
    return "";
  }

  function readAutoBannerPayload(el) {
    if (!el || !window.jQuery) return null;
    try {
      const payload = window.jQuery(el).data("cta-payload");
      return payload && typeof payload === "object" ? payload : null;
    } catch (e) {
      return null;
    }
  }

  function detectCurrentOffer() {
    const autoBanner = document.querySelector("[data-role='auto-banner']");
    const autoLink = autoBanner ? autoBanner.querySelector("[data-role='auto-banner-link']") : null;
    const autoPayload = readAutoBannerPayload(autoBanner);
    if (autoBanner && isOfferContainerActive(autoBanner)) {
      const href = safeUrl((autoLink && autoLink.getAttribute("href")) || (autoPayload && autoPayload.cta_url) || "");
      const originalUrl = safeUrl((autoPayload && autoPayload.raw_url) || href);
      if (href) {
        const titleEl = autoBanner.querySelector("[data-role='auto-banner-title']");
        const imgEl = autoBanner.querySelector("[data-role='auto-banner-image']");
        const priceEl = autoBanner.querySelector("[data-role='auto-banner-prices']");
        const title = text(titleEl && titleEl.textContent) || "Mentett ajánlat";
        const shopSlug = text(autoPayload && autoPayload.shop_slug) || extractShopSlug(href) || extractShopSlug(originalUrl);
        const contentId = text(autoPayload && autoPayload.content_id) || simpleHash("auto:" + title + ":" + href);
        return {
          source: "auto_banner",
          contentType: "auto_banner",
          contentId: contentId,
          offerTitle: title,
          affiliateUrl: href,
          originalUrl: originalUrl || href,
          imageUrl: safeUrl(imgEl && imgEl.getAttribute("src")),
          priceLabel: text(priceEl && priceEl.textContent),
          shopSlug: shopSlug,
          fingerprint: "auto|" + contentId + "|" + href + "|" + title,
        };
      }
    }

    const sponsorWrap = document.getElementById("ads-watch-cta");
    const sponsorLink = document.getElementById("ads-watch-cta-link");
    if (sponsorWrap && sponsorLink && isOfferContainerActive(sponsorWrap)) {
      const href = safeUrl(sponsorLink.getAttribute("href"));
      if (href) {
        const title = text(document.getElementById("video-info-title-text") && document.getElementById("video-info-title-text").textContent) || "Mentett ajánlat";
        const shopSlug = extractShopSlug(href);
        return {
          source: "sponsor",
          contentType: "sponsor",
          contentId: simpleHash("sponsor:" + title + ":" + href),
          offerTitle: title,
          affiliateUrl: href,
          originalUrl: href,
          imageUrl: "",
          priceLabel: "",
          shopSlug: shopSlug,
          fingerprint: "sponsor|" + href + "|" + title,
        };
      }
    }

    return null;
  }

  function ensureSaveUi() {
    const root = document.getElementById("impactshop-ads-watch");
    if (!root) return null;

    let wrap = root.querySelector("[data-role='saved-offers-actions']");
    if (!wrap) {
      const anchor = root.querySelector("#ads-watch-live-balance") || root.querySelector("#video-info-panel") || root.querySelector(".ads-watch-player-area");
      if (!anchor || !anchor.parentNode) return null;
      wrap = document.createElement("div");
      wrap.className = "impactshop-saved-offers-actions";
      wrap.setAttribute("data-role", "saved-offers-actions");

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "impactshop-saved-offers-btn";
      btn.setAttribute("data-role", "save-offer-btn");
      btn.textContent = strings.saveLabel || "Ajánlat mentése";

      const status = document.createElement("div");
      status.className = "impactshop-saved-offers-status";
      status.setAttribute("data-role", "save-offer-status");
      status.textContent = strings.ctaNoReward || "";

      wrap.appendChild(btn);
      wrap.appendChild(status);

      anchor.parentNode.insertBefore(wrap, anchor.nextSibling);

      btn.addEventListener("click", function () {
        handleSaveClick(btn, status);
      });
    }

    return wrap;
  }

  function renderSaveState() {
    const wrap = ensureSaveUi();
    if (!wrap) return;

    const btn = wrap.querySelector("[data-role='save-offer-btn']");
    const status = wrap.querySelector("[data-role='save-offer-status']");
    const offer = detectCurrentOffer();
    state.currentOffer = offer;

    if (!btn || !status) return;

    if (!offer) {
      btn.disabled = true;
      status.className = "impactshop-saved-offers-status";
      status.textContent = "A mentés akkor aktív, ha már betöltött egy ajánlat.";
      return;
    }

    btn.disabled = false;
    status.className = "impactshop-saved-offers-status";
    status.textContent = strings.ctaNoReward || "";
  }

  async function handleSaveClick(button, status) {
    if (!state.currentOffer) {
      renderSaveState();
      return;
    }
    button.disabled = true;
    status.className = "impactshop-saved-offers-status";
    status.textContent = "Mentés folyamatban…";

    try {
      const response = await fetch(restBase + "/saved-offers/save", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce,
        },
        body: JSON.stringify({
          content_type: state.currentOffer.contentType,
          content_id: state.currentOffer.contentId,
          offer_title: state.currentOffer.offerTitle,
          affiliate_url: state.currentOffer.affiliateUrl,
          original_url: state.currentOffer.originalUrl,
          image_url: state.currentOffer.imageUrl,
          price_label: state.currentOffer.priceLabel,
          shop_slug: state.currentOffer.shopSlug,
          source_page: "ads_watch",
        }),
      });

      const data = await response.json().catch(function () { return {}; });
      if (!response.ok || !data || (data.status !== "saved" && data.status !== "updated")) {
        throw new Error("save_failed");
      }

      status.className = "impactshop-saved-offers-status is-success";
      status.textContent = data.status === "updated"
        ? (strings.saveExists || "Már mentve volt, frissítettem.")
        : (strings.saveSuccess || "Ajánlat elmentve.");
      refreshSavedOffersSection();
    } catch (e) {
      status.className = "impactshop-saved-offers-status is-error";
      status.textContent = strings.saveError || "Nem sikerült elmenteni.";
      button.disabled = false;
      return;
    }

    button.disabled = false;
  }

  function formatDate(value) {
    const raw = text(value);
    if (!raw) return "";
    const dt = new Date(raw.replace(" ", "T"));
    if (Number.isNaN(dt.getTime())) return raw;
    return dt.toLocaleString("hu-HU", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function buildChip(label, extraClass) {
    const span = document.createElement("span");
    span.className = "impactshop-saved-offers-chip" + (extraClass ? (" " + extraClass) : "");
    span.textContent = label;
    return span;
  }

  function ensureProfileSection() {
    const panel = document.querySelector(".impactshop-identity-panel:not(.impactshop-identity-panel--compact) .impactshop-identity-card");
    if (!panel) return null;

    let section = panel.querySelector("[data-role='saved-offers-section']");
    if (!section) {
      const restore = panel.querySelector(".impactshop-identity-restore");
      section = document.createElement("div");
      section.className = "impactshop-identity-block impactshop-saved-offers-block";
      section.setAttribute("data-role", "saved-offers-section");
      section.innerHTML = [
        "<h4>" + (strings.sectionTitle || "Mentett ajánlataim") + "</h4>",
        "<ul class=\"impactshop-saved-offers-list\" data-role=\"saved-offers-list\"></ul>",
        "<p class=\"impactshop-identity-hint\" data-role=\"saved-offers-empty\">" + (strings.empty || "Nincs mentett ajánlat.") + "</p>"
      ].join("");
      if (restore && restore.parentNode) {
        restore.parentNode.insertBefore(section, restore);
      } else {
        panel.appendChild(section);
      }
    }

    return section;
  }

  function renderSavedOffers(items) {
    const section = ensureProfileSection();
    if (!section) return;

    const list = section.querySelector("[data-role='saved-offers-list']");
    const empty = section.querySelector("[data-role='saved-offers-empty']");
    if (!list || !empty) return;

    list.innerHTML = "";
    if (!items || !items.length) {
      empty.hidden = false;
      return;
    }

    empty.hidden = true;
    items.forEach(function (item) {
      const li = document.createElement("li");
      li.className = "impactshop-saved-offers-card";

      const media = document.createElement(item.image_url ? "img" : "div");
      if (item.image_url) {
        media.src = item.image_url;
        media.alt = item.offer_title || "";
      } else {
        media.style.width = "72px";
        media.style.height = "72px";
        media.style.borderRadius = "12px";
        media.style.background = "linear-gradient(135deg,#e2e8f0,#cbd5e1)";
      }

      const body = document.createElement("div");
      const title = document.createElement("h5");
      title.textContent = item.offer_title || "Mentett ajánlat";
      body.appendChild(title);

      const meta = document.createElement("div");
      meta.className = "impactshop-saved-offers-meta";
      if (item.network) meta.appendChild(buildChip(String(item.network).toUpperCase()));
      if (item.ngo_slug) meta.appendChild(buildChip("NGO: " + item.ngo_slug));
      if (item.reopen_click_count > 0) meta.appendChild(buildChip("Újranyitva: " + item.reopen_click_count, "is-clicked"));
      if (item.purchase_detected_at) meta.appendChild(buildChip("Vásárlás rögzítve", "is-purchased"));
      body.appendChild(meta);

      const sub = document.createElement("div");
      sub.className = "impactshop-saved-offers-sub";
      const pieces = [];
      if (item.price_label) pieces.push(item.price_label);
      if (item.category) pieces.push("Kategória: " + item.category);
      pieces.push("Mentve: " + formatDate(item.saved_at));
      if (item.last_reopened_at) pieces.push("Utolsó megnyitás: " + formatDate(item.last_reopened_at));
      if (item.purchase_detected_at) pieces.push("Vásárlás: " + formatDate(item.purchase_detected_at));
      sub.textContent = pieces.join(" • ");
      body.appendChild(sub);

      const link = document.createElement("a");
      link.className = "impactshop-saved-offers-link";
      link.href = item.open_url || item.affiliate_url || "#";
      link.target = "_blank";
      link.rel = "noopener";
      link.textContent = strings.openLabel || "Megnyitom újra";
      body.appendChild(link);

      li.appendChild(media);
      li.appendChild(body);
      list.appendChild(li);
    });
  }

  async function refreshSavedOffersSection() {
    const section = ensureProfileSection();
    if (!section) return;
    try {
      const response = await fetch(restBase + "/saved-offers?ts=" + Date.now(), {
        credentials: "include",
        cache: "no-store",
        headers: { "Cache-Control": "no-cache" },
      });
      if (!response.ok) {
        return;
      }
      const data = await response.json();
      renderSavedOffers(Array.isArray(data.items) ? data.items : []);
    } catch (e) {
      // noop
    }
  }

  function startOfferWatcher() {
    renderSaveState();

    const observer = new MutationObserver(function () {
      const next = detectCurrentOffer();
      const fingerprint = next ? next.fingerprint : "";
      if (fingerprint === state.lastFingerprint) return;
      state.lastFingerprint = fingerprint;
      state.currentOffer = next;
      renderSaveState();
    });

    observer.observe(document.body, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ["href", "style", "hidden", "src"],
    });

    window.setInterval(function () {
      const next = detectCurrentOffer();
      const fingerprint = next ? next.fingerprint : "";
      if (fingerprint === state.lastFingerprint) return;
      state.lastFingerprint = fingerprint;
      state.currentOffer = next;
      renderSaveState();
    }, 1500);
  }

  function init() {
    startOfferWatcher();
    refreshSavedOffersSection();
    window.addEventListener("impactshop_identity_ready", function () {
      state.identityReady = true;
      refreshSavedOffersSection();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
