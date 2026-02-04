(function () {
  const CONFIG = {
    selector: "[data-ngo-card]",
    scriptId: "impact-ngo-card-runtime",
    styleId: "impact-ngo-card-style",
    apiBase: (window.impactNgoCardConfig && window.impactNgoCardConfig.apiBase) || "/wp-json/impact/v1/ngo-card/",
    defaultVariant: (window.impactNgoCardConfig && window.impactNgoCardConfig.defaultVariant) || "full",
    enableAppDownload:
      window.impactNgoCardConfig && typeof window.impactNgoCardConfig.enableAppDownload !== "undefined"
        ? !!window.impactNgoCardConfig.enableAppDownload
        : false,
  };
  const FALLBACK_LOGO = "https://app.sharity.hu/wp-content/uploads/impactshop/ngo-card-default.jpg";

  const STYLES = `
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');

    :root {
      --impact-card-text: #e7efff;
      --impact-card-muted: rgba(226, 232, 240, 0.78);
      --impact-card-outline: rgba(255, 255, 255, 0.18);
      --impact-card-highlight: rgba(125, 211, 252, 0.65);
      --impact-card-accent: #8dd0ff;
      --impact-card-cta: linear-gradient(135deg, #7de2ff, #60a5fa 45%, #3b82f6 80%);
      --impact-card-cta-text: #051932;
    }

    .impact-ngo-card {
      font-family: "Plus Jakarta Sans", "Space Grotesk", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
      position: relative;
      display: flex;
      gap: 1.75rem;
      padding: 1.9rem 2.1rem;
      border-radius: 30px;
      border: 1px solid var(--impact-card-outline);
      background: linear-gradient(135deg, rgba(9, 18, 40, 0.75), rgba(21, 38, 70, 0.55)) padding-box,
                  radial-gradient(circle at top right, rgba(99, 102, 241, 0.4), rgba(14, 116, 144, 0.15)) border-box;
      color: var(--impact-card-text);
      backdrop-filter: blur(22px);
      -webkit-backdrop-filter: blur(22px);
      box-shadow: 0 25px 55px rgba(3, 8, 20, 0.5);
      align-items: center;
      overflow: hidden;
    }
    .impact-ngo-card::before {
      content: "";
      position: absolute;
      inset: 8px;
      border-radius: 24px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      pointer-events: none;
    }
    .impact-ngo-card::after {
      content: "";
      position: absolute;
      top: -40%;
      right: -20%;
      width: 60%;
      height: 140%;
      background: radial-gradient(circle, rgba(125, 211, 252, 0.18), transparent 70%);
      transform: rotate(18deg);
      pointer-events: none;
    }
    .impact-ngo-card__media {
      position: relative;
      flex: 0 0 112px;
      width: 112px;
      height: 112px;
      border-radius: 28px;
      overflow: hidden;
      background: linear-gradient(145deg, rgba(148, 163, 184, 0.25), rgba(30, 64, 175, 0.2));
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08), 0 18px 35px rgba(3, 7, 18, 0.45);
    }
    .impact-ngo-card__media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 24px;
      filter: drop-shadow(0 14px 28px rgba(3, 7, 18, 0.45));
    }
    .impact-ngo-card__content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      position: relative;
      z-index: 1;
    }
    .impact-ngo-card__header {
      display: flex;
      justify-content: space-between;
      gap: 1.5rem;
      align-items: flex-start;
    }
    .impact-ngo-card__header-aside {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.45rem;
    }
    .impact-ngo-card__title {
      margin: 0;
      font-size: 1.4rem;
      font-weight: 700;
      letter-spacing: -0.01em;
      color: #f8fbff;
    }
    .impact-ngo-card__meta {
      margin: 0;
      font-size: 0.85rem;
      color: var(--impact-card-muted);
    }
    .impact-ngo-card__badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.28rem 0.9rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background: rgba(15, 23, 42, 0.35);
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #f8fbff;
      box-shadow: 0 8px 18px rgba(3, 7, 18, 0.35);
    }
    .impact-ngo-card__qr {
      width: 88px;
      height: 88px;
      border-radius: 20px;
      padding: 6px;
      border: 1px solid rgba(255, 255, 255, 0.16);
      background: rgba(2, 6, 23, 0.55);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04), 0 12px 24px rgba(3, 7, 18, 0.4);
    }
    .impact-ngo-card__qr img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 14px;
    }
    .impact-ngo-card__tagline {
      margin: 0;
      font-size: 1rem;
      color: rgba(226, 238, 255, 0.9);
    }
    .impact-ngo-card__announcement {
      margin: 0.4rem 0 0.2rem;
      padding: 0.8rem 1rem;
      border-radius: 16px;
      border: 1px solid rgba(148,163,184,0.2);
      background: rgba(15,23,42,0.55);
    }
    .impact-ngo-card__announcement-label {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.72rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #7dd3fc;
      margin-bottom: 0.25rem;
    }
    .impact-ngo-card__announcement p {
      margin: 0;
      font-size: 0.9rem;
      color: #e2e8f0;
    }
    .impact-ngo-card__announcement a {
      margin-top: 0.5rem;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      color: #93c5fd;
      font-weight: 600;
      text-decoration: none;
    }
    .impact-ngo-card__announcement a:hover {
      text-decoration: underline;
    }
    .impact-ngo-card__stats {
      display: flex;
      gap: 1.75rem;
      flex-wrap: wrap;
    }
    .impact-ngo-card__stats-block {
      min-width: 140px;
      padding-right: 1rem;
      border-right: 1px solid rgba(255, 255, 255, 0.08);
    }
    .impact-ngo-card__stats-block:last-child {
      border-right: none;
    }
    .impact-ngo-card__stat-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--impact-card-muted);
    }
    .impact-ngo-card__stats-block strong {
      display: block;
      margin-top: 0.25rem;
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--impact-card-text);
    }
    .impact-ngo-card__actions {
      display: flex;
      gap: 0.9rem;
      flex-wrap: wrap;
      margin-top: 0.4rem;
    }
    .impact-ngo-card__cta,
    .impact-ngo-card__secondary,
    .impact-ngo-card__download {
      border-radius: 999px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.92rem;
      padding: 0.75rem 1.6rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .impact-ngo-card__cta {
      background: var(--impact-card-cta);
      color: var(--impact-card-cta-text);
      box-shadow: 0 18px 38px rgba(96, 165, 250, 0.35);
    }
    .impact-ngo-card__cta:hover {
      transform: translateY(-1px);
      box-shadow: 0 20px 40px rgba(96, 165, 250, 0.4);
    }
    .impact-ngo-card__secondary {
      border: 1px solid rgba(147, 197, 253, 0.5);
      color: var(--impact-card-text);
      background: rgba(148, 163, 184, 0.1);
    }
    .impact-ngo-card__secondary--share {
      border-style: dashed;
    }
    .impact-ngo-card__secondary--tombola {
      border-color: rgba(251, 191, 36, 0.55);
      background: rgba(251, 191, 36, 0.12);
      color: #fef9c3;
    }
    .impact-ngo-card__secondary--video {
      border-color: rgba(16, 185, 129, 0.55);
      background: rgba(16, 185, 129, 0.12);
      color: #d1fae5;
    }
    .impact-ngo-card__download {
      border: 1px solid rgba(125, 211, 252, 0.35);
      color: #93c5fd;
      background: rgba(37, 99, 235, 0.15);
      font-size: 0.85rem;
      gap: 0.35rem;
    }
    .impact-ngo-card__download::before {
      content: "↓";
      font-size: 0.95rem;
    }
    .impact-ngo-card__action--disabled {
      opacity: 0.88;
      pointer-events: none;
      cursor: default;
    }
    .impact-ngo-card__download:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 30px rgba(37, 99, 235, 0.35);
    }
    .impact-ngo-card__rank {
      width: 60px;
      height: 60px;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 1.05rem;
      background: rgba(125, 211, 252, 0.18);
      color: var(--impact-card-text);
      border: 1px solid rgba(255, 255, 255, 0.12);
    }
    .impact-ngo-card--compact .impact-ngo-card__tagline,
    .impact-ngo-card--compact .impact-ngo-card__stats-block:nth-child(2) {
      display: none;
    }
    .impact-ngo-card--loading {
      position: relative;
      overflow: hidden;
    }
    .impact-ngo-card--loading::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.5), rgba(255,255,255,0));
      animation: impactNgoCardShimmer 1.6s infinite;
    }
    @keyframes impactNgoCardShimmer {
      0%   { transform: translateX(-100%); }
      50%  { transform: translateX(100%); }
      100% { transform: translateX(100%); }
    }
    .impact-ngo-card--error {
      border-color: rgba(248, 113, 113, 0.4);
      background: rgba(248, 113, 113, 0.08);
      box-shadow: inset 0 0 0 1px rgba(248, 113, 113, 0.25);
    }
    .impact-ngo-card--widget {
      flex-direction: column;
      padding: 1.4rem 1.6rem;
      gap: 1rem;
    }
    .impact-ngo-card__widget-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.85rem;
    }
    .impact-ngo-card__widget-amount {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
      font-size: 0.85rem;
      color: var(--impact-card-muted);
    }
    .impact-ngo-card__widget-amount strong {
      font-size: 1.65rem;
      font-weight: 700;
      color: var(--impact-card-accent);
    }
    .impact-ngo-card__widget-cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      padding: 0.65rem 1.2rem;
      border-radius: 999px;
      text-decoration: none;
      background: var(--impact-card-cta);
      color: var(--impact-card-cta-text);
      font-weight: 600;
      font-size: 0.9rem;
      box-shadow: 0 16px 34px rgba(37, 99, 235, 0.3);
    }
    .impact-ngo-card__widget-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.74rem;
      color: var(--impact-card-muted);
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .impact-ngo-card__widget-link {
      color: var(--impact-card-accent);
      text-decoration: none;
      font-weight: 500;
    }
    @media (max-width: 700px) {
      .impact-ngo-card {
        flex-direction: column;
        padding: 1.5rem;
      }
      .impact-ngo-card__media {
        width: 100%;
        height: 180px;
      }
      .impact-ngo-card__header {
        flex-direction: column;
        align-items: flex-start;
      }
      .impact-ngo-card__stats-block {
        border-right: none;
        min-width: calc(50% - 1rem);
      }
      .impact-ngo-card__actions {
        width: 100%;
      }
      .impact-ngo-card__cta,
      .impact-ngo-card__secondary {
        flex: 1;
        text-align: center;
      }
    }
  `;
  const STYLES_ACCESSIBILITY = `
    .impact-ngo-card a:focus-visible,
    .impact-ngo-card button:focus-visible {
      outline: 2px solid #2563eb;
      outline-offset: 2px;
    }
    @media (prefers-reduced-motion: reduce) {
      .impact-ngo-card__progress-bar-fill {
        transition: none !important;
      }
    }
  `;

  const DEFAULT_LOCALE =
    document.documentElement.getAttribute("lang") ||
    navigator.language ||
    "hu-HU";

  function getLocale() {
    return DEFAULT_LOCALE;
  }

  function escapeHtml(value = "") {
    return String(value).replace(/[&<>"']/g, (char) => {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      };
      return map[char] || char;
    });
  }

  function openSafe(url) {
    window.open(url, "_blank", "noopener");
  }


  function injectStyles() {
    if (document.getElementById(CONFIG.styleId)) return;
    const style = document.createElement("style");
    style.id = CONFIG.styleId;
    style.textContent = STYLES + STYLES_ACCESSIBILITY;
    document.head.appendChild(style);
  }

  function buildApiUrl(slug, variant) {
    const base = CONFIG.apiBase;
    const normalized = base.endsWith("/") ? base : base + "/";
    const params = new URLSearchParams({ variant });
    return normalized + encodeURIComponent(slug) + "?" + params.toString();
  }

  function resolveLogoUrl(slug, data = {}) {
    if (data.logo_url) {
      return data.logo_url;
    }
    if (
      window.impactNgoCardConfig &&
      typeof window.impactNgoCardConfig.logoResolver === "function"
    ) {
      const custom = window.impactNgoCardConfig.logoResolver(slug, data);
      if (custom) {
        return custom;
      }
    }
    return `https://app.sharity.hu/wp-content/uploads/impactshop/ngo-logos/${slug}.png`;
  }

  function resolveTagline(name, data = {}) {
    if (data.summary) {
      return data.summary;
    }
    return `Segítsd a(z) ${name} ügyét az Impact Shopban.`;
  }

  function mediumFromContext(context) {
    const lower = context.toLowerCase();
    if (lower.includes("qr")) return "qr";
    if (lower.includes("wallet")) return "wallet";
    if (lower.includes("share")) return "share";
    return "embed";
  }

  function buildLegacyCtaUrl(slug, context) {
    const url = new URL(window.location.origin + "/go");
    const campaign = `${slug}-${new Date().toISOString().slice(0, 7).replace("-", "")}`;
    url.searchParams.set("d1", slug);
    url.searchParams.set("src", context);
    url.searchParams.set("utm_source", "ngo-card");
    url.searchParams.set("utm_medium", mediumFromContext(context));
    url.searchParams.set("utm_campaign", campaign);
    url.searchParams.set("utm_content", context);
    return url.toString();
  }

  function decorateUrlForContext(url, context) {
    if (!url) return "";
    try {
      const parsed = new URL(url, window.location.origin);
      parsed.searchParams.set("src", context);
      parsed.searchParams.set("utm_source", "ngo-card");
      parsed.searchParams.set("utm_content", context);
      parsed.searchParams.set("utm_medium", mediumFromContext(context));
      return parsed.toString();
    } catch (err) {
      return url;
    }
  }

  function ensureSlugParam(url, slug) {
    if (!url) return "";
    try {
      const parsed = new URL(url, window.location.origin);
      if (slug && !parsed.searchParams.get("d1")) {
        parsed.searchParams.set("d1", slug);
      }
      return parsed.toString();
    } catch (err) {
      return url;
    }
  }

  function resolveAppDownloadUrl(slug) {
    const config = window.impactNgoCardConfig || {};
    const rawUrl = config.appDownloadUrl || "https://app.sharity.hu/impactshop/";
    if (!rawUrl) {
      return "";
    }
    const substituted = slug ? rawUrl.replace("{slug}", slug) : rawUrl;
    try {
      const parsed = new URL(substituted, window.location.origin);
      if (slug && !substituted.includes("{slug}") && !parsed.searchParams.get("ngo")) {
        parsed.searchParams.set("ngo", slug);
      }
      return parsed.toString();
    } catch (err) {
      return substituted;
    }
  }

  function resolveCtaUrl(data, slug, variant) {
    let context = "ngo-card-embed-full";
    if (variant === "compact") {
      context = "ngo-card-embed-compact";
    } else if (variant === "widget") {
      context = "ngo-card-embed-widget";
    }
    const decorate = (value) =>
      ensureSlugParam(decorateUrlForContext(value, context), slug);

    const ctaUrl = decorate(data.cta_url);
    if (ctaUrl) return ctaUrl;

    const filloutUrl = decorate(data.fillout_url);
    if (filloutUrl) return filloutUrl;

    const goUrl = decorate(data.go_url);
    if (goUrl) return goUrl;

    return buildLegacyCtaUrl(slug, context);
  }

  function createSkeleton(el, variant) {
    if (variant === "widget") {
      el.innerHTML = `<div class="impact-ngo-card impact-ngo-card--loading impact-ngo-card--widget">
        <div class="impact-ngo-card__widget-header">
          <div class="impact-ngo-card__rank">--</div>
          <div>
            <p class="impact-ngo-card__title">&nbsp;</p>
            <p class="impact-ngo-card__meta">&nbsp;</p>
          </div>
        </div>
        <div class="impact-ngo-card__widget-amount">
          <span>&nbsp;</span>
          <strong>…</strong>
        </div>
      </div>`;
      return;
    }

    el.innerHTML = `<div class="impact-ngo-card impact-ngo-card--loading impact-ngo-card--${variant}">
      <div class="impact-ngo-card__media"></div>
      <div class="impact-ngo-card__content">
        <div class="impact-ngo-card__header">
          <div>
            <p class="impact-ngo-card__title">&nbsp;</p>
            <p class="impact-ngo-card__meta">&nbsp;</p>
          </div>
        </div>
        <p class="impact-ngo-card__tagline">&nbsp;</p>
        <div class="impact-ngo-card__stats">
          <div class="impact-ngo-card__stats-block">
            <span class="impact-ngo-card__stat-label">&nbsp;</span>
            <strong>…</strong>
          </div>
        </div>
        <div class="impact-ngo-card__actions">
          <span class="impact-ngo-card__cta">…</span>
        </div>
      </div>
    </div>`;
  }

  // GA4 Tracking Helper
  function trackEvent(eventName, params) {
    const consentDenied =
      window.impactConsent === "denied" ||
      window.doNotTrack === "1" ||
      navigator.doNotTrack === "1";

    if (consentDenied) {
      return;
    }

    if (typeof gtag === "function") {
      gtag("event", eventName, params);
    }
    if (typeof window.dataLayer !== "undefined") {
      window.dataLayer.push({ event: eventName, ...params });
    }
  }

  function renderCard(el, data, variant, cacheStatus) {
    const amount = data.amount || {};
    const slug = data.slug || "";
    const shareUrl =
      data.share_url ||
      (slug ? `${window.location.origin}/ngo/${slug}/share/` : "");
    const name = data.name || slug || "";
    const rank = data.rank || 0;
    const formattedAmount = amount.formatted || "";
    const ctaUrl = resolveCtaUrl(data, slug, variant);
    const badge = data.badge_status || {};
    const badgeLabel = badge.label || "";
    const safeBadgeLabel = escapeHtml(badgeLabel);
    const badgeKey = (badge.key || "").replace(/[^a-z0-9_-]/gi, "");
    const safeName = escapeHtml(name);
    const safeFormattedAmount = escapeHtml(formattedAmount);
    const tagline = escapeHtml(resolveTagline(name, data));
    const logoUrl = resolveLogoUrl(slug, data);
    const supporters = data.supporters || null;
    const locale = getLocale();
    const updatedDisplay = formatTimestamp(data.last_updated);
    const qrUrl = el.dataset.qr || el.getAttribute("data-qr") || "";
    const hideAnnouncement = el.dataset.hideAnnouncement === "true";
    const announcement = data.announcement || null;
    const announcementText =
      announcement && announcement.text ? escapeHtml(String(announcement.text)) : "";
    const announcementUrl =
      announcement && announcement.url ? escapeHtml(String(announcement.url)) : "";
    const showAnnouncement = !!(announcementText && !hideAnnouncement);
    const hideAppDownload = el.dataset.hideAppDownload === "true";
    const appDownloadUrl = resolveAppDownloadUrl(slug);
    const showAppDownloadAttr = el.dataset.showAppDownload;
    const allowAppDownload =
      showAppDownloadAttr === "true" ||
      (showAppDownloadAttr !== "false" && CONFIG.enableAppDownload);
    const showAppDownload = !!(allowAppDownload && appDownloadUrl && !hideAppDownload);
    const safeAppDownloadUrl = showAppDownload ? escapeHtml(appDownloadUrl) : "";
    const tombolaUrl = data.tombola_url ? String(data.tombola_url).trim() : "";
    const videoSupportUrl = data.video_support_url ? String(data.video_support_url).trim() : "";
    const showTombola = !!tombolaUrl;
    const showVideoSupport = !!videoSupportUrl;
    const safeTombolaUrl = showTombola ? escapeHtml(tombolaUrl) : "";
    const safeVideoSupportUrl = showVideoSupport ? escapeHtml(videoSupportUrl) : "";
    const safeCtaUrl = escapeHtml(ctaUrl);
    const shareLinkTarget = shareUrl || ctaUrl;
    const safeShareLink = escapeHtml(shareLinkTarget);
    const previewOnly = el.dataset.previewOnly === "true";
    const cardClasses = ["impact-ngo-card", `impact-ngo-card--${variant}`];
    if (previewOnly) {
      cardClasses.push("impact-ngo-card--preview");
    }
    const buildAction = (className, label, url) => {
      if (previewOnly || !url) {
        return `<span class="${className} impact-ngo-card__action--disabled" role="button" aria-disabled="true" tabindex="-1">${label}</span>`;
      }
      return `<a class="${className}" href="${url}" target="_blank" rel="noopener">${label}</a>`;
    };
    let secondaryStatLabel = "Rangsor";
    let secondaryStatValue = `#${escapeHtml(String(rank || "-"))}`;
    if (supporters) {
      secondaryStatLabel = "Támogatók";
      secondaryStatValue = escapeHtml(Number(supporters).toLocaleString(locale));
    }

    if (variant === "widget") {
      renderWidgetCard(el, {
        slug,
        name,
        formattedAmount,
        rank,
        badgeLabel,
        badgeKey,
        shareUrl,
        ctaUrl,
        lastUpdated: data.last_updated,
        cacheStatus,
      });
      return;
    }

    el.innerHTML = `
      <div class="${cardClasses.join(" ")}">
        <div class="impact-ngo-card__media">
          <img src="${logoUrl}" alt="${safeName} logója" loading="lazy" crossorigin="anonymous" onerror="this.onerror=null;this.src='${FALLBACK_LOGO}'" />
        </div>
        <div class="impact-ngo-card__content">
          <div class="impact-ngo-card__header">
            <div>
              <p class="impact-ngo-card__title">${safeName}</p>
              <p class="impact-ngo-card__meta">
                Frissítve: ${updatedDisplay}${rank ? ` • #${escapeHtml(String(rank))}` : ""}
              </p>
            </div>
            ${
              badgeLabel || qrUrl
                ? `<div class="impact-ngo-card__header-aside">
              ${
                badgeLabel
                  ? `<span class="impact-ngo-card__badge" data-mode="${badgeKey}">${safeBadgeLabel}</span>`
                  : ""
              }
              ${
                qrUrl
                  ? `<div class="impact-ngo-card__qr"><img src="${qrUrl}" alt="QR" loading="lazy" crossorigin="anonymous"></div>`
                  : ""
              }
            </div>`
                : ""
            }
          </div>
          <p class="impact-ngo-card__tagline">${tagline}</p>
          ${
            showAnnouncement
              ? `<div class="impact-ngo-card__announcement">
            <div class="impact-ngo-card__announcement-label">Sharity hírek</div>
            <p>${announcementText}</p>
            ${
              announcementUrl
                ? `<a href="${announcementUrl}" target="_blank" rel="noopener">Részletek →</a>`
                : ""
            }
          </div>`
              : ""
          }
          <div class="impact-ngo-card__stats">
            <div class="impact-ngo-card__stats-block">
              <span class="impact-ngo-card__stat-label">Összeg</span>
              <strong>${safeFormattedAmount}</strong>
            </div>
            <div class="impact-ngo-card__stats-block">
              <span class="impact-ngo-card__stat-label">${secondaryStatLabel}</span>
              <strong>${secondaryStatValue}</strong>
            </div>
          </div>
          <div class="impact-ngo-card__actions">
            ${buildAction("impact-ngo-card__cta", "Tovább az Impact Shopba", safeCtaUrl)}
            ${
              showTombola
                ? buildAction(
                    "impact-ngo-card__secondary impact-ngo-card__secondary--tombola",
                    "WIN4Good tombola",
                    safeTombolaUrl
                  )
                : ""
            }
            ${
              showVideoSupport
                ? buildAction(
                    "impact-ngo-card__secondary impact-ngo-card__secondary--video",
                    "Támogatom videónézéssel",
                    safeVideoSupportUrl
                  )
                : ""
            }
            ${buildAction(
              "impact-ngo-card__secondary impact-ngo-card__secondary--share",
              "Megosztási oldal",
              safeShareLink
            )}
            ${
              showAppDownload
                ? buildAction(
                    "impact-ngo-card__download",
                    "ImpactShop app letöltése",
                    safeAppDownloadUrl
                  )
                : ""
            }
          </div>
        </div>
      </div>
    `;

    const ctaButton = el.querySelector(".impact-ngo-card__cta");
    const shareLink = el.querySelector(".impact-ngo-card__secondary--share");
    const tombolaLink = el.querySelector(".impact-ngo-card__secondary--tombola");
    const videoLink = el.querySelector(".impact-ngo-card__secondary--video");
    const downloadLink = el.querySelector(".impact-ngo-card__download");

    // Card view tracking
    trackEvent('card_view', {
      ngo: slug,
      variant: variant,
      cache_hit: cacheStatus === 'HIT',
      rank: rank
    });

    // CTA click tracking
    if (!previewOnly && ctaButton && ctaButton.tagName === "A") {
      ctaButton.addEventListener('click', function() {
        trackEvent('cta_click', {
          ngo: slug,
          destination: this.href,
          utm_source: 'ngo-card'
        });
      });
    }

    if (!previewOnly && shareLink && shareLink.tagName === "A") {
      shareLink.addEventListener("click", function () {
        trackEvent("share_open", {
          ngo: slug,
          method: "link",
          variant: variant,
        });
      });
    }

    if (!previewOnly && tombolaLink && tombolaLink.tagName === "A") {
      tombolaLink.addEventListener("click", function () {
        trackEvent("tombola_click", {
          ngo: slug,
          variant: variant,
          destination: this.href,
        });
      });
    }

    if (!previewOnly && videoLink && videoLink.tagName === "A") {
      videoLink.addEventListener("click", function () {
        trackEvent("video_support_click", {
          ngo: slug,
          variant: variant,
          destination: this.href,
        });
      });
    }

    if (!previewOnly && downloadLink && downloadLink.tagName === "A") {
      downloadLink.addEventListener('click', function() {
        trackEvent('app_download_click', {
          ngo: slug,
          variant: variant,
          destination: this.href
        });
      });
    }
  }

  function renderWidgetCard(el, info) {
    const {
      slug,
      name,
      formattedAmount,
      rank,
      shareUrl,
      ctaUrl,
      lastUpdated,
      cacheStatus,
      badgeLabel = '',
      badgeKey = '',
    } = info;

    const displayUpdated = lastUpdated ? `Frissítve: ${formatTimestamp(lastUpdated)}` : '';
    const saveLink = shareUrl || ctaUrl;
    const safeName = escapeHtml(name);
    const safeFormattedAmount = escapeHtml(formattedAmount);
    const safeBadgeLabel = escapeHtml(badgeLabel);
    const sanitizedBadgeKey = (badgeKey || '').replace(/[^a-z0-9_-]/gi, '');
    const rankDisplay = rank ? `#${rank}` : '#-';

    el.innerHTML = `
      <div class="impact-ngo-card impact-ngo-card--widget">
        <div class="impact-ngo-card__widget-header">
          <div class="impact-ngo-card__rank" data-rank="${rank}">${rankDisplay}</div>
          <div>
            <p class="impact-ngo-card__title">${safeName}</p>
            ${badgeLabel ? `<span class="impact-ngo-card__badge impact-ngo-card__badge--${sanitizedBadgeKey}">${safeBadgeLabel}</span>` : ''}
            <div class="impact-ngo-card__widget-amount">
              <span>Összegyűjtve</span>
              <strong>${safeFormattedAmount}</strong>
            </div>
          </div>
        </div>
        <a class="impact-ngo-card__widget-cta" href="${ctaUrl}" target="_blank" rel="noopener">
          Támogasd az Impact Shopban
        </a>
        <div class="impact-ngo-card__widget-footer">
          <span>${displayUpdated}</span>
          ${saveLink ? `<a class="impact-ngo-card__widget-link" href="${saveLink}" target="_blank" rel="noopener">Megnyitás új ablakban</a>` : ''}
        </div>
      </div>
    `;

    trackEvent('card_view', {
      ngo: slug,
      variant: 'widget',
      cache_hit: cacheStatus === 'HIT',
      rank: rank
    });

    const cta = el.querySelector('.impact-ngo-card__widget-cta');
    if (cta) {
      cta.addEventListener('click', function () {
        trackEvent('cta_click', {
          ngo: slug,
          destination: this.href,
          utm_source: 'ngo-card-widget'
        });
      });
    }

    const link = el.querySelector('.impact-ngo-card__widget-link');
    if (link) {
      link.addEventListener('click', function () {
        trackEvent('share_open', { ngo: slug, method: 'widget-link' });
      });
    }
  }

  function formatTimestamp(value) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return date.toLocaleString(getLocale(), {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  function hydrateElement(el) {
    const slug =
      el.getAttribute("data-ngo-card") ||
      el.getAttribute("data-ngo") ||
      el.dataset.ngo ||
      el.dataset.slug ||
      el.getAttribute("slug");
    if (!slug) {
      el.textContent = "NGO slug hiányzik.";
      return;
    }
    const variant = el.getAttribute("variant") || el.dataset.variant || CONFIG.defaultVariant;
    createSkeleton(el, variant);
    const url = buildApiUrl(slug, variant);
    
    fetch(url, { credentials: "same-origin" })
      .then((resp) => {
        const cacheStatus = resp.headers.get('x-cache-status') || 'UNKNOWN';
        if (resp.ok) {
          return resp.json().then(data => ({ data, cacheStatus }));
        }
        return resp.json()
          .catch(() => ({}))
          .then(body => {
            const message = body?.message || body?.data?.message || null;
            const error = new Error(message || `HTTP ${resp.status}`);
            error.status = resp.status;
            error.cacheStatus = cacheStatus;
            throw error;
          });
      })
      .then(({ data, cacheStatus }) => {
        renderCard(el, data, variant, cacheStatus);
      })
      .catch((error) => {
        const status = error && error.status;
        if (status === 429) {
          return renderErrorCard(el, variant, {
            title: "Túl sok kérés",
            message: "Rövid időn belül túl sok kérés érkezett erről az eszközről.",
            hint: "Várj fél percet, majd frissítsd az oldalt.",
          });
        }
        if (status === 404) {
          return renderErrorCard(el, variant, {
            title: "Jelenleg nem elérhető",
            message: "Próbáld újra később. (Ellenőrizd, hogy helyes slugot adtál meg az embed elemben.)",
          });
        }

        const fallbackMessage = (error && error.message) || "Próbáld újra később.";
        return renderErrorCard(el, variant, {
          title: "Jelenleg nem érhető el",
          message: fallbackMessage,
        });
      });
  }

  function renderErrorCard(el, variant, options = {}) {
    const { title = "Jelenleg nem érhető el", message = "Próbáld újra később.", hint = "" } = options;
    if (variant === "widget") {
      el.innerHTML = `
        <div class="impact-ngo-card impact-ngo-card--widget impact-ngo-card--error">
          <div class="impact-ngo-card__widget-header">
            <div class="impact-ngo-card__rank">!</div>
            <div>
              <p class="impact-ngo-card__title impact-ngo-card__error-title">${title}</p>
              <p class="impact-ngo-card__meta impact-ngo-card__error-message">${message}</p>
              ${hint ? `<p class="impact-ngo-card__meta impact-ngo-card__error-hint">${hint}</p>` : ""}
            </div>
          </div>
        </div>`;
      return;
    }

    el.innerHTML = `
      <div class="impact-ngo-card impact-ngo-card--${variant} impact-ngo-card--error">
        <div class="impact-ngo-card__rank">!</div>
        <div>
          <p class="impact-ngo-card__title impact-ngo-card__error-title">${title}</p>
          <p class="impact-ngo-card__meta impact-ngo-card__error-message">${message}</p>
          ${hint ? `<p class="impact-ngo-card__meta impact-ngo-card__error-hint">${hint}</p>` : ""}
        </div>
      </div>`;
  }

  function init() {
    injectStyles();
    const nodes = document.querySelectorAll(CONFIG.selector);
    nodes.forEach((el) => hydrateElement(el));
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
