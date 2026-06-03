/* ImpactShop Vote Purchase (Stripe) */
(function () {
  "use strict";

  const config = window.impactshopVotePurchase || {};
  if (!config.enabled) {
    const root = document.querySelector("[data-role=vote-purchase]");
    if (root) root.hidden = true;
    return;
  }

  const root = document.querySelector("[data-role=vote-purchase]");
  if (!root) return;

  const packages = config.packages || {};
  const packageList = Object.keys(packages);
  const restBase = config.restBase || "/wp-json/impact/v1";
  const restNonce = config.restNonce || "";
  const defaultCurrency = (config.currency || "huf").toLowerCase();
  const preferredCurrency = (config.preferredCurrency || defaultCurrency).toLowerCase();
  const pseudoId = config.pseudoId || "";

  const pkgWrap = root.querySelector("[data-role=purchase-packages]");
  const purchaseBody = root.querySelector("[data-role=purchase-body]");
  const purchaseToggle = root.querySelector("[data-role=purchase-toggle]");
  const purchaseInfo = root.querySelector("[data-role=purchase-info]");
  const purchaseInfoPanel = root.querySelector("[data-role=purchase-info-panel]");
  const ngoSelect = root.querySelector("[data-role=purchase-ngo]");
  const currencySelect = root.querySelector("[data-role=purchase-currency]");
  const companyToggle = root.querySelector("[data-role=purchase-company]");
  const companyFields = root.querySelector("[data-role=purchase-company-fields]");
  const submitBtn = root.querySelector("[data-role=purchase-submit]");
  const statusEl = root.querySelector("[data-role=purchase-status]");

  let selectedPackage = packageList[0] || "";
  
  // Compute effective currency: fallback to preferredCurrency if currencySelect is missing or has no value
  function getEffectiveCurrency() {
    if (currencySelect && currencySelect.value) {
      return currencySelect.value.toLowerCase();
    }
    // Fallback: prefer server-side per-user preferredCurrency (e.g. "usd" for US users)
    // then check it exists in packages, else fall back to defaultCurrency, then first available
    const availableCurrencies = new Set();
    Object.values(packages).forEach((pkg) => {
      Object.keys(pkg.prices || {}).forEach((cur) => availableCurrencies.add(cur.toLowerCase()));
    });
    if (availableCurrencies.has(preferredCurrency)) {
      return preferredCurrency;
    }
    if (availableCurrencies.has(defaultCurrency)) {
      return defaultCurrency;
    }
    // If neither available, pick first available currency from packages
    return Array.from(availableCurrencies)[0] || "huf";
  }

  function setStatus(message, isError) {
    if (!statusEl) return;
    statusEl.textContent = message || "";
    statusEl.style.color = isError ? "#b91c1c" : "#0f766e";
  }

  function setCollapsed(isCollapsed) {
    if (!purchaseBody || !purchaseToggle) return;
    purchaseBody.hidden = isCollapsed;
    purchaseToggle.setAttribute("aria-expanded", String(!isCollapsed));
  }

  function expandForHash() {
    if (window.location.hash !== "#ads-watch-purchase") return;
    setCollapsed(false);
    if (purchaseToggle && purchaseToggle.scrollIntoView) {
      purchaseToggle.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  function toggleInfoPanel() {
    if (!purchaseInfoPanel || !purchaseInfo) return;
    const nextState = purchaseInfoPanel.hidden;
    purchaseInfoPanel.hidden = !nextState;
    purchaseInfo.setAttribute("aria-expanded", String(nextState));
  }

  function formatAmount(amount, currency) {
    const number = Number(amount);
    const code = String(currency || "").toUpperCase();
    if (Number.isNaN(number)) return "";
    return `${number.toLocaleString("hu-HU")} ${code}`;
  }

  function renderPackages() {
    if (!pkgWrap) return;
    const effectiveCurrency = getEffectiveCurrency();
    pkgWrap.innerHTML = "";
    packageList.forEach((id) => {
      const pkg = packages[id];
      const totalVotes = (pkg.votes || 0) + (pkg.bonus_votes || 0);
      const price = pkg.prices ? pkg.prices[effectiveCurrency] : null;
      const card = document.createElement("button");
      card.type = "button";
      card.className = "purchase-card" + (id === selectedPackage ? " is-active" : "");
      card.dataset.packageId = id;
      card.innerHTML = `
        <div class="purchase-card__title">${pkg.emoji || ""} ${pkg.label || id}</div>
        <div class="purchase-card__price">${formatAmount(price, effectiveCurrency)}</div>
        <div class="purchase-card__votes">${totalVotes.toLocaleString("hu-HU")} szavazat</div>
        ${id === selectedPackage ? '<div class="purchase-card__selected">Kiválasztva</div>' : ''}
        ${pkg.badge ? `<span class="purchase-card__badge">${pkg.badge}</span>` : ""}
      `;
      card.addEventListener("click", function () {
        selectedPackage = id;
        renderPackages();
      });
      pkgWrap.appendChild(card);
    });
  }

  function renderCurrencies() {
    if (!currencySelect) return;
    const currencies = new Set();
    Object.values(packages).forEach((pkg) => {
      Object.keys(pkg.prices || {}).forEach((cur) => currencies.add(cur));
    });
    currencySelect.innerHTML = "";
    Array.from(currencies).forEach((cur) => {
      const opt = document.createElement("option");
      opt.value = cur;
      opt.textContent = cur.toUpperCase();
      if (cur === preferredCurrency) {
        opt.selected = true;
      }
      currencySelect.appendChild(opt);
    });
    currencySelect.addEventListener("change", renderPackages);
  }

  async function loadNgos() {
    if (!ngoSelect) return;
  }

  async function prefillCompanyData() {
    const companyName = root.querySelector("[data-role=company-name]");
    const companyTax = root.querySelector("[data-role=company-tax]");
    const companyAddress = root.querySelector("[data-role=company-address]");
    const companyEmail = root.querySelector("[data-role=company-email]");
    if (!companyName || !companyTax || !companyAddress || !companyEmail) return;
    try {
      const res = await fetch(restBase + "/vote-purchase/saved-company-data?ts=" + Date.now(), {
        credentials: "include"
      });
      const data = await res.json();
      const company = data && data.company ? data.company : null;
      if (!company) return;
      companyName.value = company.company_name || "";
      companyTax.value = company.company_tax_id || "";
      companyAddress.value = company.company_address || "";
      companyEmail.value = company.email || "";
    } catch (e) {
      return;
    }
  }

  function toggleCompanyFields() {
    if (!companyFields || !companyToggle) return;
    companyFields.hidden = !companyToggle.checked;
  }

  async function submitPurchase() {
    if (!selectedPackage) {
      setStatus("Válassz csomagot.", true);
      return;
    }
    const consent = root.querySelector("[data-role=purchase-consent]");
    if (!consent || !consent.checked) {
      setStatus("Fogadd el az ÁSZF-et és az adatvédelmi tájékoztatót.", true);
      return;
    }

    const isCompany = companyToggle ? companyToggle.checked : false;
    const companyName = root.querySelector("[data-role=company-name]");
    const companyTax = root.querySelector("[data-role=company-tax]");
    const companyAddress = root.querySelector("[data-role=company-address]");
    const companyEmail = root.querySelector("[data-role=company-email]");
    const companySave = root.querySelector("[data-role=company-save]");
    const gdprConsent = root.querySelector("[data-role=company-gdpr]");

    const payload = {
      pseudo_id: pseudoId,
      package_id: selectedPackage,
      currency: getEffectiveCurrency(),
      is_company: isCompany,
      company_name: companyName ? companyName.value.trim() : "",
      company_tax_id: companyTax ? companyTax.value.trim() : "",
      company_address: companyAddress ? companyAddress.value.trim() : "",
      email: companyEmail ? companyEmail.value.trim() : "",
      save_company_data: companySave ? companySave.checked : false,
      gdpr_email_consent: gdprConsent ? gdprConsent.checked : false,
      consent: true,
      return_url: window.location.origin + window.location.pathname + "#ads-watch-status-bar"
    };

    setStatus("Fizetési oldal előkészítése...");
    if (submitBtn) submitBtn.disabled = true;
    try {
      const res = await fetch(restBase + "/vote-purchase/start", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce
        },
        body: JSON.stringify(payload),
        credentials: "include"
      });
      const data = await res.json();
      if (!res.ok || !data.stripe_checkout_url) {
        throw new Error(data.error || "Stripe hiba");
      }
      const opened = window.open(data.stripe_checkout_url, "_blank", "noopener");
      if (!opened) {
        window.location.href = data.stripe_checkout_url;
        return;
      }
      setCollapsed(false);
      setStatus("Fizetési oldal új lapon megnyitva. A fizetés után térj vissza ide.");
    } catch (e) {
      setStatus("Nem sikerült elindítani a fizetést.", true);
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  async function checkStatusFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const status = params.get("vp_status");
    const orderId = params.get("order_id");
    if (!status || !orderId) return;
    setCollapsed(false);
    const statusBar = document.getElementById("ads-watch-status-bar");
    if (statusBar && status === "success") {
      statusBar.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    setStatus(status === "success" ? "Sikeres fizetés! A jóváírás feldolgozás alatt..." : "Fizetés megszakítva.", status !== "success");
    if (status !== "success") return;
    try {
      const res = await fetch(restBase + "/vote-purchase/status?order_id=" + encodeURIComponent(orderId));
      const data = await res.json();
      if (data && data.status === "completed") {
        const votesAdded = Number(data.votes || 0);
        animateAvailableVotes(votesAdded);
        setStatus(votesAdded > 0 ? `Köszönjük! +${votesAdded.toLocaleString("hu-HU")} szavazat jóváírva.` : "Köszönjük! A szavazatokat jóváírtuk.");
      } else {
        setStatus("A jóváírás folyamatban van. Próbáld újra később.");
      }
    } catch (e) {
      setStatus("A jóváírás folyamatban van. Próbáld újra később.");
    }
  }

  function animateAvailableVotes(votesAdded) {
    if (!votesAdded || votesAdded <= 0) return;
    const targets = [
      document.getElementById("available-votes-display"),
      document.getElementById("available-votes-inline")
    ].filter(Boolean);
    if (!targets.length) return;
    targets.forEach((el) => {
      const currentText = (el.textContent || "").replace(/[^\d]/g, "");
      const current = currentText ? parseInt(currentText, 10) : 0;
      const next = current + votesAdded;
      const start = performance.now();
      const duration = 900;
      function tick(now) {
        const progress = Math.min(1, (now - start) / duration);
        const value = Math.round(current + (next - current) * progress);
        el.textContent = value.toLocaleString("hu-HU");
        if (progress < 1) {
          requestAnimationFrame(tick);
        }
      }
      requestAnimationFrame(tick);
    });
  }

  setCollapsed(true);
  renderCurrencies();
  renderPackages();
  loadNgos();
  prefillCompanyData();
  toggleCompanyFields();
  checkStatusFromUrl();
  expandForHash();

  if (companyToggle) {
    companyToggle.addEventListener("change", toggleCompanyFields);
  }
  if (purchaseToggle) {
    purchaseToggle.addEventListener("click", function () {
      const isCollapsed = purchaseBody ? purchaseBody.hidden : true;
      setCollapsed(!isCollapsed);
    });
  }
  if (purchaseInfo) {
    purchaseInfo.addEventListener("click", toggleInfoPanel);
  }
  if (submitBtn) {
    submitBtn.addEventListener("click", submitPurchase);
  }
})();
