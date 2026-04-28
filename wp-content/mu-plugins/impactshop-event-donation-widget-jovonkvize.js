(function () {
  "use strict";

  var WIDGET_VERSION = "1.7.2";
  var SCRIPT_ATTR = "data-impact-campaign-widget";
  var STYLE_ID = "impact-event-donation-widget-style-jvk";
  var DEFAULT_API_BASE = "https://app.sharity.hu/wp-json/impact/v1/event-campaigns";
  var DEFAULT_FALLBACK_BASE = "https://app.sharity.hu/";
  var REGULAR_TICKET_UNIT_PRICE = 50000;
  var SUPPORTER_TICKET_UNIT_PRICE = 150000;
  var STANDALONE_TICKET_MAX = 10;

  function ensureStyles() {
    if (document.getElementById(STYLE_ID)) {
      return;
    }

    var style = document.createElement("style");
    style.id = STYLE_ID;
    style.textContent =
      "@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap');" +
      ".impact-event-widget{--iew-bg1:#060d2a;--iew-bg2:#0d2f77;--iew-accent:#c69a5f;--iew-accent2:#f4ddae;--iew-text:#f8f4ea;--iew-muted:rgba(248,244,234,.72);--iew-panel:rgba(5,15,47,.62);font-family:'Manrope','Segoe UI',system-ui,sans-serif;position:relative;overflow:hidden;border-radius:28px;padding:22px;border:1px solid rgba(255,255,255,.13);background:linear-gradient(150deg,var(--iew-bg1),var(--iew-bg2));box-shadow:0 24px 42px rgba(4,8,24,.35);color:var(--iew-text);max-width:560px;min-width:280px}" +
      ".impact-event-widget:before{content:'';position:absolute;inset:-35% -12% auto auto;width:68%;height:86%;background:radial-gradient(circle at center,rgba(244,221,174,.24),transparent 68%);pointer-events:none}" +
      ".impact-event-widget:after{content:'';position:absolute;inset:auto auto -42% -12%;width:56%;height:84%;background:radial-gradient(circle at center,rgba(198,154,95,.18),transparent 72%);pointer-events:none}" +
      ".impact-event-widget__inner{position:relative;z-index:1;display:grid;gap:14px}" +
      ".impact-event-widget__eyebrow{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:5px 10px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);font-size:11px;letter-spacing:.08em;text-transform:uppercase}" +
      ".impact-event-widget__title{margin:0;font-family:'Cormorant Garamond',serif;font-size:36px;line-height:1.03;letter-spacing:.01em;color:var(--iew-accent2)}" +
      ".impact-event-widget__subtitle{margin:0;font-size:14px;color:var(--iew-muted)}" +
      ".impact-event-widget__disclaimer{margin:0;padding:8px 10px;border-radius:12px;background:rgba(7,19,54,.55);border:1px solid rgba(255,255,255,.11);font-size:12px;color:var(--iew-muted)}" +
      ".impact-event-widget__disclaimer:empty{display:none}" +
      ".impact-event-widget__stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}" +
      ".impact-event-widget__stat{padding:10px 12px;border-radius:14px;background:var(--iew-panel);border:1px solid rgba(255,255,255,.08)}" +
      ".impact-event-widget__stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--iew-muted);margin-bottom:5px}" +
      ".impact-event-widget__stat-value{font-size:18px;font-weight:700;line-height:1.15}" +
      ".impact-event-widget__progress{height:8px;border-radius:999px;background:rgba(255,255,255,.16);overflow:hidden}" +
      ".impact-event-widget__progress > span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--iew-accent),var(--iew-accent2));transition:width .45s ease}" +
      ".impact-event-widget__amounts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}" +
      ".impact-event-widget__amount-btn{appearance:none;border:1px solid rgba(255,255,255,.15);border-radius:12px;background:rgba(255,255,255,.06);color:var(--iew-text);font-weight:700;font-size:13px;padding:9px 8px;cursor:pointer;transition:all .2s ease}" +
      ".impact-event-widget__amount-btn:hover,.impact-event-widget__amount-btn:focus-visible{background:rgba(244,221,174,.18);border-color:rgba(244,221,174,.55);outline:none}" +
      ".impact-event-widget__amount-btn.is-active{background:linear-gradient(125deg,rgba(198,154,95,.32),rgba(244,221,174,.2));border-color:rgba(244,221,174,.68)}" +
      ".impact-event-widget__custom{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}" +
      ".impact-event-widget__input,.impact-event-widget__select{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,.16);background:rgba(5,15,47,.58);color:var(--iew-text);font-size:14px;padding:10px 12px}" +
      ".impact-event-widget__input:focus,.impact-event-widget__select:focus{outline:none;border-color:rgba(244,221,174,.72);box-shadow:0 0 0 3px rgba(244,221,174,.18)}" +
      ".impact-event-widget__row{display:grid;grid-template-columns:1fr 1fr;gap:8px}" +
      ".impact-event-widget__checkbox{display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--iew-muted)}" +
      ".impact-event-widget__checkbox input{margin-top:2px}" +
      ".impact-event-widget__company{display:none;gap:8px}" +
      ".impact-event-widget__company.is-open{display:grid}" +
      ".impact-event-widget__actions{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}" +
      ".impact-event-widget__donate{appearance:none;border:none;border-radius:14px;padding:12px 14px;background:linear-gradient(110deg,var(--iew-accent),#b88142 62%,var(--iew-accent2));color:#111a2f;font-weight:800;letter-spacing:.03em;cursor:pointer;font-size:14px;text-transform:uppercase}" +
      ".impact-event-widget__donate:hover,.impact-event-widget__donate:focus-visible{filter:brightness(1.06);outline:none}" +
      ".impact-event-widget__share{appearance:none;border:1px solid rgba(255,255,255,.16);border-radius:12px;background:rgba(255,255,255,.08);color:var(--iew-text);padding:11px 12px;font-weight:700;cursor:pointer}" +
      ".impact-event-widget__status{min-height:18px;font-size:12px;color:var(--iew-muted)}" +
      ".impact-event-widget__status.is-error{color:#ffb4b4}" +
      ".impact-event-widget__status.is-success{color:#9cffd6}" +
      ".impact-event-widget__share-menu{display:none;position:absolute;right:18px;bottom:72px;z-index:4;background:rgba(5,13,38,.96);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:8px;box-shadow:0 18px 30px rgba(3,8,22,.45)}" +
      ".impact-event-widget__share-menu.is-open{display:grid;gap:4px}" +
      ".impact-event-widget__share-link{border:none;background:transparent;color:var(--iew-text);padding:8px 10px;text-align:left;cursor:pointer;border-radius:10px;font-size:13px}" +
      ".impact-event-widget__share-link:hover{background:rgba(255,255,255,.12)}" +
      /* ── Package tier cards ───────────────────────────── */
      ".impact-event-widget__packages{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}" +
      ".impact-event-widget__pkg{position:relative;padding:14px 12px;border-radius:16px;border:2px solid transparent;cursor:pointer;transition:all .25s ease;text-align:center;overflow:hidden}" +
      ".impact-event-widget__pkg:before{content:'';position:absolute;inset:0;border-radius:14px;opacity:.13;pointer-events:none;transition:opacity .25s ease}" +
      ".impact-event-widget__pkg:hover:before{opacity:.22}" +
      ".impact-event-widget__pkg.is-active{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3)}" +
      /* Silver */
      ".impact-event-widget__pkg--silver{border-color:rgba(192,192,208,.35);background:linear-gradient(165deg,rgba(160,170,190,.14),rgba(220,225,235,.08))}" +
      ".impact-event-widget__pkg--silver:before{background:linear-gradient(165deg,#a0aabe,#dce1eb)}" +
      ".impact-event-widget__pkg--silver.is-active{border-color:rgba(192,200,220,.7);box-shadow:0 8px 24px rgba(160,170,200,.25)}" +
      ".impact-event-widget__pkg--silver .impact-event-widget__pkg-icon{color:#c0c8dc}" +
      /* Gold */
      ".impact-event-widget__pkg--gold{border-color:rgba(198,154,95,.4);background:linear-gradient(165deg,rgba(198,154,95,.16),rgba(244,221,174,.08))}" +
      ".impact-event-widget__pkg--gold:before{background:linear-gradient(165deg,#c69a5f,#f4ddae)}" +
      ".impact-event-widget__pkg--gold.is-active{border-color:rgba(244,221,174,.72);box-shadow:0 8px 24px rgba(198,154,95,.28)}" +
      ".impact-event-widget__pkg--gold .impact-event-widget__pkg-icon{color:#f4ddae}" +
      /* Platinum */
      ".impact-event-widget__pkg--platinum{border-color:rgba(180,160,220,.4);background:linear-gradient(165deg,rgba(120,100,180,.16),rgba(200,185,240,.08))}" +
      ".impact-event-widget__pkg--platinum:before{background:linear-gradient(165deg,#8878b8,#c8b9f0)}" +
      ".impact-event-widget__pkg--platinum.is-active{border-color:rgba(200,185,240,.72);box-shadow:0 8px 24px rgba(120,100,180,.28)}" +
      ".impact-event-widget__pkg--platinum .impact-event-widget__pkg-icon{color:#c8b9f0}" +
      /* Package inner elements */
      ".impact-event-widget__pkg-icon{font-size:28px;line-height:1;margin-bottom:6px}" +
      ".impact-event-widget__pkg-name{font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:700;letter-spacing:.02em;margin-bottom:4px}" +
      ".impact-event-widget__pkg-amount{font-size:15px;font-weight:800;margin-bottom:2px}" +
      ".impact-event-widget__pkg-label{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--iew-muted)}" +
      /* Ticket selector */
      ".impact-event-widget__tickets{display:none;gap:10px;align-items:center;padding:12px 14px;border-radius:14px;background:var(--iew-panel);border:1px solid rgba(255,255,255,.1)}" +
      ".impact-event-widget__tickets.is-open{display:flex}" +
      ".impact-event-widget__tickets-label{font-size:13px;color:var(--iew-muted);flex:1}" +
      ".impact-event-widget__tickets-select{appearance:none;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(5,15,47,.7);color:var(--iew-text);font-size:15px;font-weight:700;padding:8px 28px 8px 12px;cursor:pointer;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23f8f4ea'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 10px center}" +
      ".impact-event-widget__tickets-select:focus{outline:none;border-color:rgba(244,221,174,.72);box-shadow:0 0 0 3px rgba(244,221,174,.18)}" +
      /* Mixed ticket selector */
      ".impact-event-widget__or-sep{text-align:center;font-size:12px;color:var(--iew-muted);letter-spacing:.05em;margin:4px 0}" +
      ".impact-event-widget__ticket-mix{display:grid;gap:10px;padding:14px;border-radius:16px;background:rgba(20,30,70,.8);border:1px solid rgba(255,255,255,.15)}" +
      ".impact-event-widget__ticket-mix-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}" +
      ".impact-event-widget__ticket-mix-title{font-size:13px;color:rgba(248,244,234,.88);line-height:1.35}" +
      ".impact-event-widget__ticket-mix-note{font-size:11px;color:var(--iew-muted);text-align:right}" +
      ".impact-event-widget__ticket-option{display:grid;grid-template-columns:1fr 84px;gap:10px;align-items:center;padding:10px 12px;border-radius:12px;background:rgba(5,15,47,.52);border:1px solid rgba(255,255,255,.08)}" +
      ".impact-event-widget__ticket-option-label{font-size:13px;color:var(--iew-text);font-weight:700}" +
      ".impact-event-widget__ticket-option-sub{display:block;font-size:11px;color:var(--iew-muted);font-weight:500;margin-top:2px}" +
      ".impact-event-widget__ticket-count{width:84px;flex-shrink:0;text-align:center;border:1px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(5,15,47,.95);color:#f8f4ea;font-size:18px;font-weight:700;padding:8px 10px;-moz-appearance:textfield}" +
      ".impact-event-widget__ticket-count:focus{outline:none;border-color:rgba(244,221,174,.72);box-shadow:0 0 0 3px rgba(244,221,174,.18)}" +
      ".impact-event-widget__ticket-count::-webkit-inner-spin-button,.impact-event-widget__ticket-count::-webkit-outer-spin-button{opacity:1;cursor:pointer}" +
      ".impact-event-widget__ticket-mix-summary{font-size:12px;color:rgba(248,244,234,.86);padding-top:4px;border-top:1px solid rgba(255,255,255,.08)}" +
      "@media (max-width:640px){.impact-event-widget{padding:18px;border-radius:22px}.impact-event-widget__title{font-size:30px}.impact-event-widget__stats{grid-template-columns:1fr}.impact-event-widget__packages{grid-template-columns:1fr;gap:8px}.impact-event-widget__amounts{grid-template-columns:repeat(2,minmax(0,1fr))}.impact-event-widget__row{grid-template-columns:1fr}.impact-event-widget__actions{grid-template-columns:1fr}}";

    document.head.appendChild(style);
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

  function parseStatusFromUrl() {
    var params = new URLSearchParams(window.location.search);
    return {
      status: params.get("ed_status") || "",
      campaign: params.get("ed_campaign") || "",
      donationId: params.get("ed_donation_id") || "",
    };
  }

  function createMarkup() {
    return (
      '<section class="impact-event-widget" role="region" aria-label="Sharity adománygyűjtés">' +
      '<div class="impact-event-widget__inner">' +
      '<span class="impact-event-widget__eyebrow">Jótékonysági kampány</span>' +
      '<h3 class="impact-event-widget__title" data-role="title">Jövőnk Vize</h3>' +
      '<p class="impact-event-widget__subtitle" data-role="subtitle">Sharity Adományszervező Alapítvány</p>' +
      '<p class="impact-event-widget__disclaimer" data-role="disclaimer"></p>' +
      '<div class="impact-event-widget__stats">' +
      '<div class="impact-event-widget__stat"><div class="impact-event-widget__stat-label">Összes adomány</div><div class="impact-event-widget__stat-value" data-role="stat-total">0 Ft</div></div>' +
      '<div class="impact-event-widget__stat"><div class="impact-event-widget__stat-label">Támogatók</div><div class="impact-event-widget__stat-value" data-role="stat-supporters">0</div></div>' +
      '<div class="impact-event-widget__stat"><div class="impact-event-widget__stat-label">Átlag / támogató</div><div class="impact-event-widget__stat-value" data-role="stat-average">0 Ft</div></div>' +
      '</div>' +
      '<div class="impact-event-widget__progress" aria-hidden="true"><span data-role="progress"></span></div>' +
      /* ── Package tier cards ── */
      '<div class="impact-event-widget__packages" data-role="packages">' +
      '<button type="button" class="impact-event-widget__pkg impact-event-widget__pkg--silver" data-pkg="silver" data-pkg-amount="1000000">' +
      '<div class="impact-event-widget__pkg-icon">🥈</div>' +
      '<div class="impact-event-widget__pkg-name">Ezüst</div>' +
      '<div class="impact-event-widget__pkg-amount" data-role="pkg-amount-silver">1 000 000 Ft</div>' +
      '<div class="impact-event-widget__pkg-label">csomag</div>' +
      '</button>' +
      '<button type="button" class="impact-event-widget__pkg impact-event-widget__pkg--gold" data-pkg="gold" data-pkg-amount="2000000">' +
      '<div class="impact-event-widget__pkg-icon">🥇</div>' +
      '<div class="impact-event-widget__pkg-name">Arany</div>' +
      '<div class="impact-event-widget__pkg-amount" data-role="pkg-amount-gold">2 000 000 Ft</div>' +
      '<div class="impact-event-widget__pkg-label">csomag</div>' +
      '</button>' +
      '<button type="button" class="impact-event-widget__pkg impact-event-widget__pkg--platinum" data-pkg="platinum" data-pkg-amount="3000000">' +
      '<div class="impact-event-widget__pkg-icon">💎</div>' +
      '<div class="impact-event-widget__pkg-name">Platina</div>' +
      '<div class="impact-event-widget__pkg-amount" data-role="pkg-amount-platinum">3 000 000 Ft</div>' +
      '<div class="impact-event-widget__pkg-label">csomag</div>' +
      '</button>' +
      '</div>' +
      '<div class="impact-event-widget__tickets" data-role="ticket-row">' +
      '<span class="impact-event-widget__tickets-label">🎟️ Gálajegyek száma:</span>' +
      '<select class="impact-event-widget__tickets-select" data-role="ticket-count"><option value="0">–</option></select>' +
      '</div>' +
      '<div class="impact-event-widget__or-sep">— vagy jegyvásárlás —</div>' +
      '<div class="impact-event-widget__ticket-mix">' +
      '<div class="impact-event-widget__ticket-mix-head">' +
      '<span class="impact-event-widget__ticket-mix-title">🎫 Jegytípus választó<br><small style="opacity:.7;font-size:11px">Vegyesen is kombinálható: pl. 1 támogatói + 2 alapjegy</small></span>' +
      '<span class="impact-event-widget__ticket-mix-note">Max. ' + STANDALONE_TICKET_MAX + ' jegy összesen</span>' +
      '</div>' +
      '<div class="impact-event-widget__ticket-option">' +
      '<span class="impact-event-widget__ticket-option-label">Alapjegy<span class="impact-event-widget__ticket-option-sub">1 db = 50 000 Ft</span></span>' +
      '<input type="number" class="impact-event-widget__ticket-count" data-role="regular-ticket-count" min="0" max="10" step="1" value="0">' +
      '</div>' +
      '<div class="impact-event-widget__ticket-option">' +
      '<span class="impact-event-widget__ticket-option-label">Támogatói jegy<span class="impact-event-widget__ticket-option-sub">1 db = 150 000 Ft</span></span>' +
      '<input type="number" class="impact-event-widget__ticket-count" data-role="supporter-ticket-count" min="0" max="10" step="1" value="0">' +
      '</div>' +
      '<div class="impact-event-widget__ticket-mix-summary" data-role="ticket-mix-summary">Jegyek összesen: 0 db<br>Fizetendő végösszeg: 0 Ft</div>' +
      '</div>' +
      '<div class="impact-event-widget__or-sep">— vagy egyszerű adományozás —</div>' +
      '<div class="impact-event-widget__amounts" data-role="preset-amounts"></div>' +
      '<div class="impact-event-widget__custom">' +
      '<input class="impact-event-widget__input" data-role="custom-amount" type="text" inputmode="numeric" placeholder="Egyedi összeg (Ft)">' +
      '<span data-role="currency-label">HUF</span>' +
      '</div>' +
      '<div class="impact-event-widget__row">' +
      '<input class="impact-event-widget__input" data-role="donor-name" type="text" maxlength="150" placeholder="Név (opcionális)" autocomplete="name">' +
      '<input class="impact-event-widget__input" data-role="donor-email" type="email" maxlength="150" placeholder="E-mail cím" autocomplete="email">' +
      '</div>' +
      '<label class="impact-event-widget__checkbox"><input data-role="is-company" type="checkbox"> <span>Céges adományozó vagyok (igazolással)</span></label>' +
      '<div class="impact-event-widget__company" data-role="company-fields">' +
      '<input class="impact-event-widget__input" data-role="company-name" type="text" maxlength="180" placeholder="Cégnév">' +
      '<div class="impact-event-widget__row">' +
      '<input class="impact-event-widget__input" data-role="company-tax" type="text" maxlength="40" placeholder="Adószám">' +
      '<input class="impact-event-widget__input" data-role="company-address" type="text" maxlength="250" placeholder="Székhely">' +
      '</div>' +
      '<label class="impact-event-widget__checkbox"><input data-role="cert-consent" type="checkbox"> <span>Kérek adományigazolást, és hozzájárulok az e-mailes kiállításhoz.</span></label>' +
      '</div>' +
      '<label class="impact-event-widget__checkbox"><input data-role="consent" type="checkbox"> <span>Elfogadom az <a href="https://app.sharity.hu/ngo-guides/jogi-dokumentumok/" target="_blank" rel="noopener" style="color:#7ec8e3;text-decoration:underline">ÁSZF-et és az adatkezelési tájékoztatót</a>.</span></label>' +
      '<div class="impact-event-widget__actions">' +
      '<button class="impact-event-widget__donate" data-role="donate" type="button">Támogatom az ügyet</button>' +
      '<button class="impact-event-widget__share" data-role="share" type="button">Megosztás</button>' +
      '</div>' +
      '<div class="impact-event-widget__share-menu" data-role="share-menu">' +
      '<button class="impact-event-widget__share-link" data-share="copy" type="button">Link másolása</button>' +
      '<button class="impact-event-widget__share-link" data-share="facebook" type="button">Facebook</button>' +
      '<button class="impact-event-widget__share-link" data-share="linkedin" type="button">LinkedIn</button>' +
      '<button class="impact-event-widget__share-link" data-share="email" type="button">E-mail</button>' +
      '</div>' +
      '<div class="impact-event-widget__status" data-role="status" aria-live="polite"></div>' +
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
    var fallbackApiBase = (config.fallbackApiBase || DEFAULT_FALLBACK_BASE);
    var campaign = config.campaign || "jovonkvize-2026";
    var pollMs = Math.max(15000, Number(config.pollMs) || 30000);

    mountEl.innerHTML = createMarkup();
    var root = mountEl.querySelector(".impact-event-widget");

    var els = {
      title: root.querySelector('[data-role="title"]'),
      subtitle: root.querySelector('[data-role="subtitle"]'),
      disclaimer: root.querySelector('[data-role="disclaimer"]'),
      statTotal: root.querySelector('[data-role="stat-total"]'),
      statSupporters: root.querySelector('[data-role="stat-supporters"]'),
      statAverage: root.querySelector('[data-role="stat-average"]'),
      progress: root.querySelector('[data-role="progress"]'),
      presets: root.querySelector('[data-role="preset-amounts"]'),
      ticketRow: root.querySelector('[data-role="ticket-row"]'),
      ticketCount: root.querySelector('[data-role="ticket-count"]'),
      regularTicketCount: root.querySelector('[data-role="regular-ticket-count"]'),
      supporterTicketCount: root.querySelector('[data-role="supporter-ticket-count"]'),
      ticketMixSummary: root.querySelector('[data-role="ticket-mix-summary"]'),
      customAmount: root.querySelector('[data-role="custom-amount"]'),
      currencyLabel: root.querySelector('[data-role="currency-label"]'),
      donorName: root.querySelector('[data-role="donor-name"]'),
      donorEmail: root.querySelector('[data-role="donor-email"]'),
      isCompany: root.querySelector('[data-role="is-company"]'),
      companyFields: root.querySelector('[data-role="company-fields"]'),
      companyName: root.querySelector('[data-role="company-name"]'),
      companyTax: root.querySelector('[data-role="company-tax"]'),
      companyAddress: root.querySelector('[data-role="company-address"]'),
      certConsent: root.querySelector('[data-role="cert-consent"]'),
      consent: root.querySelector('[data-role="consent"]'),
      donate: root.querySelector('[data-role="donate"]'),
      share: root.querySelector('[data-role="share"]'),
      shareMenu: root.querySelector('[data-role="share-menu"]'),
      status: root.querySelector('[data-role="status"]'),
    };

    var state = {
      info: null,
      stats: null,
      selectedAmount: 0,
      baseAmount: 0,
      packageAmount: 0,
      selectedPkg: null,
      ticketCount: 0,
      packageTicketCount: 0,
      regularTicketCount: 0,
      supporterTicketCount: 0,
      shareUrl: "https://jovonkvize.hu",
      pending: false,
      useFallbackApi: false,
    };

    function buildRestUrl(endpoint, query) {
      var suffix = query ? "?" + query : "";
      return apiBase + "/" + encodeURIComponent(campaign) + "/" + endpoint + suffix;
    }

    function buildFallbackUrl(endpoint, query) {
      var glue = fallbackApiBase.indexOf("?") === -1 ? "?" : "&";
      var q = "impact_event_api=" + encodeURIComponent(endpoint) + "&campaign=" + encodeURIComponent(campaign);
      if (query) {
        q += "&" + query;
      }
      return fallbackApiBase + glue + q;
    }

    function isChallengeResponse(response, bodyText) {
      var mitigated = response.headers && response.headers.get ? response.headers.get("cf-mitigated") : "";
      if (String(mitigated).toLowerCase() === "challenge") {
        return true;
      }
      var ct = String((response.headers && response.headers.get ? response.headers.get("content-type") : "") || "").toLowerCase();
      if (ct.indexOf("text/html") !== -1) {
        var snippet = String(bodyText || "").slice(0, 220).toLowerCase();
        if (snippet.indexOf("just a moment") !== -1 || snippet.indexOf("cf_chl_opt") !== -1) {
          return true;
        }
      }
      return false;
    }

    async function fetchApiResult(endpoint, options) {
      var opts = options || {};
      var method = opts.method || "GET";
      var query = opts.query || "";
      var payload = opts.payload || null;

      async function doRequest(url) {
        var init = {
          method: method,
          mode: "cors",
          cache: "no-store",
          headers: {},
        };
        if (payload) {
          init.body = JSON.stringify(payload);
          init.headers["Content-Type"] = "text/plain;charset=UTF-8";
        }
        return fetch(url, init);
      }

      var primaryUrl = state.useFallbackApi ? buildFallbackUrl(endpoint, query) : buildRestUrl(endpoint, query);
      var secondaryUrl = state.useFallbackApi ? buildRestUrl(endpoint, query) : buildFallbackUrl(endpoint, query);

      async function parseResponse(response, text) {
        var data = {};
        try {
          data = JSON.parse(text || "{}");
        } catch (err) {
          data = {};
        }
        return { ok: !!response.ok, status: Number(response.status || 0), data: data };
      }

      try {
        var primary = await doRequest(primaryUrl);
        var primaryText = await primary.text();
        if (!isChallengeResponse(primary, primaryText)) {
          return parseResponse(primary, primaryText);
        }
      } catch (err) {
        // try fallback path
      }

      try {
        var secondary = await doRequest(secondaryUrl);
        var secondaryText = await secondary.text();
        if (isChallengeResponse(secondary, secondaryText)) {
          throw new Error("api_fetch_failed");
        }
        state.useFallbackApi = secondaryUrl.indexOf("impact_event_api=") !== -1;
        return parseResponse(secondary, secondaryText);
      } catch (err3) {
        throw err3;
      }
    }

    function applyTheme(theme) {
      if (!theme || typeof theme !== "object") {
        return;
      }
      if (theme.bg_start) root.style.setProperty("--iew-bg1", theme.bg_start);
      if (theme.bg_end) root.style.setProperty("--iew-bg2", theme.bg_end);
      if (theme.accent) root.style.setProperty("--iew-accent", theme.accent);
      if (theme.accent_2) root.style.setProperty("--iew-accent2", theme.accent_2);
      if (theme.text) root.style.setProperty("--iew-text", theme.text);
    }

    var PKG_MAX_TICKETS = { silver: 2, gold: 4, platinum: 6 };

    function formatInputAmount(val) {
      var n = String(val).replace(/\D/g, "");
      return n.replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }

    function parseInputAmount(str) {
      return Math.round(toNumber(String(str).replace(/\s/g, "")));
    }

    function updateTicketSelector(pkg) {
      state.selectedPkg = pkg;
      if (!pkg) {
        els.ticketRow.classList.remove("is-open");
        state.packageTicketCount = 0;
        els.ticketCount.innerHTML = '<option value="0">–</option>';
        els.ticketCount.value = "0";
        return;
      }
      var max = PKG_MAX_TICKETS[pkg] || 2;
      var html = '';
      for (var i = 0; i <= max; i++) {
        html += '<option value="' + i + '">' + (i === 0 ? '–' : i + ' fő') + '</option>';
      }
      els.ticketCount.innerHTML = html;
      els.ticketCount.value = String(max);
      state.packageTicketCount = max;
      els.ticketRow.classList.add("is-open");
    }

    function clampTicketCount(value) {
      return Math.max(0, Math.min(STANDALONE_TICKET_MAX, Number(value) || 0));
    }

    function renderTicketMixSummary() {
      if (!els.ticketMixSummary) {
        return;
      }
      var extraCount = state.regularTicketCount + state.supporterTicketCount;
      var extraAmount = (state.regularTicketCount * REGULAR_TICKET_UNIT_PRICE) + (state.supporterTicketCount * SUPPORTER_TICKET_UNIT_PRICE);
      if (state.ticketCount <= 0) {
        els.ticketMixSummary.innerHTML = "Jegyek összesen: 0 db<br>Fizetendő végösszeg: 0 Ft";
        return;
      }
      var details = "";
      if (state.selectedPkg) {
        details = "<br>Csomag jegyek: " + state.packageTicketCount + " db";
        details += "<br>Extra jegyek: " + extraCount + " db";
      }
      var parts = [];
      if (state.regularTicketCount > 0) {
        parts.push(state.regularTicketCount + " alapjegy");
      }
      if (state.supporterTicketCount > 0) {
        parts.push(state.supporterTicketCount + " támogatói jegy");
      }
      if (parts.length > 0) {
        details += "<br>Jegybontás: " + parts.join(" + ");
      }
      if (!state.selectedPkg && extraCount > 0) {
        details += "<br>Extra jegyek ára: " + formatAmount(extraAmount, "huf");
      }
      els.ticketMixSummary.innerHTML = "Jegyek összesen: " + state.ticketCount + " db" +
        details +
        "<br>Fizetendő végösszeg: " + formatAmount(state.selectedAmount, "huf");
    }

    function refreshComputedTotals(options) {
      var extraCount = state.regularTicketCount + state.supporterTicketCount;
      var extraAmount = (state.regularTicketCount * REGULAR_TICKET_UNIT_PRICE) + (state.supporterTicketCount * SUPPORTER_TICKET_UNIT_PRICE);
      var packageTickets = state.selectedPkg ? (Number(els.ticketCount.value) || 0) : 0;
      var baseAmount = state.selectedPkg ? state.packageAmount : state.baseAmount;

      state.packageTicketCount = packageTickets;
      state.ticketCount = packageTickets + extraCount;
      state.selectedAmount = Math.max(0, Math.round(baseAmount + extraAmount));

      if (!(options && options.preserveCustomField) && els.customAmount) {
        els.customAmount.value = state.selectedAmount > 0 ? formatInputAmount(state.selectedAmount) : "";
      }

      renderTicketMixSummary();
    }

    function resetTicketMix() {
      state.regularTicketCount = 0;
      state.supporterTicketCount = 0;
      if (els.regularTicketCount) {
        els.regularTicketCount.value = "0";
      }
      if (els.supporterTicketCount) {
        els.supporterTicketCount.value = "0";
      }
      renderTicketMixSummary();
    }

    function recalculateTicketMix(changedType) {
      var regularCount = clampTicketCount(els.regularTicketCount ? els.regularTicketCount.value : 0);
      var supporterCount = clampTicketCount(els.supporterTicketCount ? els.supporterTicketCount.value : 0);

      if (regularCount + supporterCount > STANDALONE_TICKET_MAX) {
        if (changedType === "supporter") {
          supporterCount = Math.max(0, STANDALONE_TICKET_MAX - regularCount);
        } else {
          regularCount = Math.max(0, STANDALONE_TICKET_MAX - supporterCount);
        }
      }

      if (els.regularTicketCount) {
        els.regularTicketCount.value = String(regularCount);
      }
      if (els.supporterTicketCount) {
        els.supporterTicketCount.value = String(supporterCount);
      }

      var totalCount = regularCount + supporterCount;

      state.regularTicketCount = regularCount;
      state.supporterTicketCount = supporterCount;
      if (!state.selectedPkg && totalCount > 0) {
        state.baseAmount = 0;
        els.presets.querySelectorAll(".impact-event-widget__amount-btn").forEach(function (b) { b.classList.remove("is-active"); });
      }

      refreshComputedTotals();
    }

    function setSelectedAmount(amount, source) {
      state.baseAmount = Math.max(0, Math.round(toNumber(amount)));
      var buttons = els.presets.querySelectorAll(".impact-event-widget__amount-btn");
      buttons.forEach(function (button) {
        var value = Number(button.getAttribute("data-amount") || 0);
        if (value === state.baseAmount && source !== "package") {
          button.classList.add("is-active");
        } else {
          button.classList.remove("is-active");
        }
      });
      root.querySelectorAll(".impact-event-widget__pkg").forEach(function (btn) { btn.classList.remove("is-active"); });
      state.selectedPkg = null;
      state.packageAmount = 0;
      updateTicketSelector(null);
      resetTicketMix();
      refreshComputedTotals({ preserveCustomField: source === "custom-input" });
    }

    function renderPresets(amounts, currency) {
      els.presets.innerHTML = "";
      (amounts || []).forEach(function (amount, index) {
        var value = Math.max(0, Math.round(toNumber(amount)));
        if (!value) return;
        var button = document.createElement("button");
        button.type = "button";
        button.className = "impact-event-widget__amount-btn";
        button.setAttribute("data-amount", String(value));
        button.textContent = formatAmount(value, currency);
        button.addEventListener("click", function () {
          setSelectedAmount(value, "preset");
        });
        els.presets.appendChild(button);

        if (index === 0 && state.selectedAmount === 0) {
          setSelectedAmount(value, "preset-default");
        }
      });
    }

    function renderStats(stats) {
      if (!stats) return;
      state.stats = stats;
      els.statTotal.textContent = stats.total_amount_formatted || formatAmount(stats.total_amount || 0, stats.currency || "huf");
      els.statSupporters.textContent = Number(stats.supporters_count || 0).toLocaleString("hu-HU");
      els.statAverage.textContent = stats.average_amount_formatted || formatAmount(stats.average_amount || 0, stats.currency || "huf");
      var progress = Math.max(0, Math.min(100, Number(stats.goal_progress_percent || 0)));
      els.progress.style.width = progress + "%";
    }

    function updateCompanyVisibility() {
      var open = !!els.isCompany.checked;
      els.companyFields.classList.toggle("is-open", open);
      els.certConsent.checked = open ? els.certConsent.checked : false;
    }

    function buildPayload() {
      var amount = state.selectedAmount;
      if (els.customAmount.value) {
        amount = parseInputAmount(els.customAmount.value);
      }

      return {
        amount: amount,
        ticket_count: state.ticketCount || 0,
        regular_ticket_count: state.regularTicketCount || 0,
        supporter_ticket_count: state.supporterTicketCount || 0,
        selected_package: state.selectedPkg || null,
        donor_name: (els.donorName.value || "").trim(),
        email: (els.donorEmail.value || "").trim(),
        is_company: !!els.isCompany.checked,
        company_name: (els.companyName.value || "").trim(),
        company_tax_id: (els.companyTax.value || "").trim(),
        company_address: (els.companyAddress.value || "").trim(),
        request_certificate: !!els.isCompany.checked && !!els.certConsent.checked,
        gdpr_email_consent: !!els.isCompany.checked && !!els.certConsent.checked,
        consent: !!els.consent.checked,
        return_url: window.location.href.split("?")[0],
      };
    }

    function validatePayload(payload) {
      if (!payload.consent) {
        return "Fogadd el az ÁSZF és adatkezelési feltételeket.";
      }
      if (!payload.email || !/.+@.+\..+/.test(payload.email)) {
        return "Adj meg érvényes e-mail címet.";
      }
      if (!payload.amount || payload.amount < Number((state.info && state.info.minimum_amount) || 500)) {
        return "A minimális adományösszeg nincs elérve.";
      }
      if (payload.is_company) {
        if (!payload.company_name || !payload.company_tax_id || !payload.company_address) {
          return "Céges adományhoz töltsd ki a cégadatokat.";
        }
        if (!payload.request_certificate) {
          return "Céges adomány esetén pipáld be az adományigazolás kérését.";
        }
      }
      return "";
    }

    function shareLinks(url, title) {
      return {
        copy: url,
        facebook: "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url),
        linkedin: "https://www.linkedin.com/sharing/share-offsite/?url=" + encodeURIComponent(url),
        email: "mailto:?subject=" + encodeURIComponent(title) + "&body=" + encodeURIComponent(url),
      };
    }

    function closeShareMenu() {
      els.shareMenu.classList.remove("is-open");
    }

    async function copyText(value) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(value);
        return;
      }
      var temp = document.createElement("textarea");
      temp.value = value;
      temp.setAttribute("readonly", "readonly");
      temp.style.position = "absolute";
      temp.style.left = "-9999px";
      document.body.appendChild(temp);
      temp.select();
      document.execCommand("copy");
      document.body.removeChild(temp);
    }

    async function onShare() {
      var url = state.shareUrl || (state.info && state.info.share_url) || window.location.href;
      var title = (state.info && state.info.title) || "Jövőnk Vize adománykampány";

      if (navigator.share) {
        try {
          await navigator.share({ title: title, text: title, url: url });
          return;
        } catch (err) {
          // fall through to inline menu
        }
      }

      els.shareMenu.classList.toggle("is-open");
    }

    async function onShareAction(method) {
      var url = state.shareUrl || (state.info && state.info.share_url) || window.location.href;
      var title = (state.info && state.info.title) || "Jövőnk Vize adománykampány";
      var links = shareLinks(url, title);

      if (method === "copy") {
        try {
          await copyText(links.copy);
          setStatus(els.status, "Megosztási link kimásolva.", "success");
        } catch (err) {
          setStatus(els.status, "A link másolása nem sikerült.", "error");
        }
        closeShareMenu();
        return;
      }

      var target = links[method];
      if (target) {
        window.open(target, "_blank", "noopener,noreferrer");
      }
      closeShareMenu();
    }

    async function loadPublic() {
      var result = await fetchApiResult("public");
      if (!result.ok) {
        throw new Error("public_fetch_failed");
      }
      return result.data || {};
    }

    async function loadStats() {
      var result = await fetchApiResult("stats", { query: "ts=" + Date.now() });
      if (!result.ok) {
        throw new Error("stats_fetch_failed");
      }
      return result.data || {};
    }

    async function submitDonation() {
      if (state.pending) {
        return;
      }

      var payload = buildPayload();
      var error = validatePayload(payload);
      if (error) {
        setStatus(els.status, error, "error");
        return;
      }

      state.pending = true;
      els.donate.disabled = true;
      setStatus(els.status, "Fizetési oldal előkészítése...", "");

      try {
        var result = await fetchApiResult("checkout", { method: "POST", payload: payload });
        var data = result && result.data ? result.data : {};
        if (!result.ok || !data || !data.stripe_checkout_url) {
          var msg = "A fizetés indítása sikertelen.";
          if (data && data.error === "invalid_origin") {
            msg = "Ez a domain jelenleg nincs engedélyezve a kampányhoz.";
          } else if (data && data.error === "rate_limited") {
            msg = "Túl sok próbálkozás rövid időn belül. Kérjük próbáld később.";
          } else if (data && data.error === "missing_consent") {
            msg = "Fogadd el az ÁSZF és adatkezelési feltételeket.";
          } else if (data && data.error === "missing_email") {
            msg = "Adj meg érvényes e-mail címet.";
          } else if (data && data.error === "missing_company_fields") {
            msg = "Céges adományhoz töltsd ki a cégadatokat és az igazolás jelölőt.";
          } else if (data && data.error === "invalid_amount") {
            msg = "Az adományösszeg a megengedett tartományon kívül van.";
          } else if (data && data.error === "api_fetch_failed") {
            msg = "Külső védelmi blokk miatt a kérés nem futott le. Frissíts és próbáld újra.";
          }
          throw new Error(msg);
        }

        window.location.href = data.stripe_checkout_url;
      } catch (err) {
        setStatus(els.status, err && err.message ? err.message : "A fizetés indítása sikertelen.", "error");
        state.pending = false;
        els.donate.disabled = false;
      }
    }

    async function resolveReturnStatus() {
      var parsed = parseStatusFromUrl();
      if (!parsed.status || parsed.campaign !== campaign || !parsed.donationId) {
        return;
      }

      if (parsed.status === "cancel") {
        setStatus(els.status, "A fizetés megszakadt. Bármikor újraindíthatod.", "error");
        return;
      }

      try {
        var result = await fetchApiResult("status", {
          query: "donation_id=" + encodeURIComponent(parsed.donationId) + "&ts=" + Date.now(),
        });
        if (!result.ok) {
          throw new Error("status_error");
        }
        var data = result.data || {};
        if (data && data.status === "completed") {
          var certInfo = data.donation_certificate_status === "pending" || data.donation_certificate_status === "sent"
            ? " Cégként az igazolást e-mailben küldjük."
            : "";
          setStatus(els.status, "Köszönjük az adományt! A befizetés rögzítve." + certInfo, "success");
          loadStats().then(renderStats).catch(function () {});
        } else {
          setStatus(els.status, "A fizetés státusza még feldolgozás alatt áll.", "");
        }
      } catch (err) {
        setStatus(els.status, "A státusz lekérése nem sikerült, frissíts később.", "error");
      }
    }

    function bindEvents() {
      els.customAmount.addEventListener("input", function () {
        var raw = els.customAmount.value;
        var caret = els.customAmount.selectionStart;
        var beforeLen = raw.length;
        var v = parseInputAmount(raw);
        var formatted = v > 0 ? formatInputAmount(v) : "";
        els.customAmount.value = formatted;
        var diff = formatted.length - beforeLen;
        els.customAmount.setSelectionRange(caret + diff, caret + diff);
        if (v > 0) {
          state.baseAmount = v;
          var buttons = els.presets.querySelectorAll(".impact-event-widget__amount-btn");
          buttons.forEach(function (button) { button.classList.remove("is-active"); });
          var pkgs = root.querySelectorAll(".impact-event-widget__pkg");
          pkgs.forEach(function (p) { p.classList.remove("is-active"); });
          state.selectedPkg = null;
          state.packageAmount = 0;
          updateTicketSelector(null);
          resetTicketMix();
          refreshComputedTotals({ preserveCustomField: true });
        }
      });

      els.ticketCount.addEventListener("change", function () {
        state.packageTicketCount = Number(els.ticketCount.value) || 0;
        refreshComputedTotals();
      });

      els.regularTicketCount.addEventListener("input", function () {
        recalculateTicketMix("regular");
      });

      els.supporterTicketCount.addEventListener("input", function () {
        recalculateTicketMix("supporter");
      });

      var pkgButtons = root.querySelectorAll(".impact-event-widget__pkg");
      pkgButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
          var amount = Number(btn.getAttribute("data-pkg-amount") || 0);
          if (amount > 0) {
            state.packageAmount = amount;
            state.selectedPkg = btn.getAttribute("data-pkg") || null;
            state.baseAmount = 0;
            els.presets.querySelectorAll(".impact-event-widget__amount-btn").forEach(function (b) { b.classList.remove("is-active"); });
            root.querySelectorAll(".impact-event-widget__pkg").forEach(function (b) { b.classList.remove("is-active"); });
            btn.classList.add("is-active");
            updateTicketSelector(state.selectedPkg);
            refreshComputedTotals();
          }
        });
      });

      els.isCompany.addEventListener("change", updateCompanyVisibility);
      els.donate.addEventListener("click", submitDonation);
      els.share.addEventListener("click", onShare);

      // Prevent checkbox toggle when clicking the ÁSZF link inside the label
      root.querySelectorAll('.impact-event-widget__checkbox a').forEach(function (a) {
        a.addEventListener("click", function (e) { e.stopPropagation(); });
      });

      root.addEventListener("click", function (evt) {
        var target = evt.target;
        if (!(target instanceof HTMLElement)) return;

        if (!target.closest('[data-role="share-menu"]') && !target.closest('[data-role="share"]')) {
          closeShareMenu();
        }

        if (target.matches("[data-share]")) {
          onShareAction(target.getAttribute("data-share"));
        }
      });
    }

    async function init() {
      bindEvents();
      els.regularTicketCount.max = String(STANDALONE_TICKET_MAX);
      els.regularTicketCount.value = "0";
      els.supporterTicketCount.max = String(STANDALONE_TICKET_MAX);
      els.supporterTicketCount.value = "0";
      renderTicketMixSummary();
      setStatus(els.status, "Kampány adatok betöltése...", "");

      try {
        var info = await loadPublic();
        state.info = info;
        state.shareUrl = info.share_url || state.shareUrl;

        if (info && info.theme) {
          applyTheme(info.theme);
        }
        if (info && info.title) {
          els.title.textContent = info.title;
        }
        if (info && info.subtitle) {
          els.subtitle.textContent = info.subtitle;
        }
        if (info && info.disclaimer) {
          els.disclaimer.textContent = info.disclaimer;
        } else if (els.disclaimer) {
          els.disclaimer.textContent = "";
        }
        if (info && info.currency) {
          els.currencyLabel.textContent = String(info.currency).toUpperCase();
        }

        renderPresets(info.preset_amounts || [], info.currency || "huf");
        renderStats(info.stats || {});
        updateCompanyVisibility();
        setStatus(els.status, "", "");
      } catch (err) {
        setStatus(els.status, "A kampány nem érhető el. Kérjük próbáld újra később.", "error");
      }

      resolveReturnStatus();

      setInterval(function () {
        loadStats().then(renderStats).catch(function () {
          // silent background polling
        });
      }, pollMs);
    }

    init();
  }

  function readConfigFromNode(node) {
    return {
      campaign: node.getAttribute("data-campaign") || "jovonkvize-2026",
      apiBase: node.getAttribute("data-api-base") || DEFAULT_API_BASE,
      fallbackApiBase: node.getAttribute("data-fallback-api-base") || DEFAULT_FALLBACK_BASE,
      mode: node.getAttribute("data-mode") || "compact",
      pollMs: node.getAttribute("data-poll-ms") || "30000",
    };
  }

  function initFromScript(scriptEl) {
    var config = readConfigFromNode(scriptEl);
    var targetSelector = scriptEl.getAttribute("data-target") || "";
    var mount = null;

    if (targetSelector) {
      mount = document.querySelector(targetSelector);
    }

    if (!mount) {
      mount = document.createElement("div");
      mount.setAttribute(SCRIPT_ATTR, "1");
      scriptEl.parentNode.insertBefore(mount, scriptEl);
    }

    if (mount.getAttribute("data-impact-event-mounted") === "1") {
      return;
    }

    mount.setAttribute("data-impact-event-mounted", "1");
    mountWidget(mount, config);
  }

  function initFromExistingMounts() {
    var nodes = document.querySelectorAll("[data-impact-campaign-widget]");
    nodes.forEach(function (node) {
      if (node.getAttribute("data-impact-event-mounted") === "1") {
        return;
      }
      mountWidget(node, readConfigFromNode(node));
      node.setAttribute("data-impact-event-mounted", "1");
    });
  }

  function init() {
    ensureStyles();

    var scripts = document.querySelectorAll("script[" + SCRIPT_ATTR + "]");
    scripts.forEach(initFromScript);

    initFromExistingMounts();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
