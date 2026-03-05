(() => {
  const root = document.querySelector("[data-ngo-selector]");
  if (!root || !window.fetch) return;

  const listUrl = root.getAttribute("data-list-url");
  const context = root.getAttribute("data-context") || "jysk-komarom";
  const selectEl = root.querySelector("[data-role=ngo-select]");
  const statusEl = root.querySelector("[data-role=ngo-status]");
  const cardEl = root.querySelector("[data-role=ngo-card]");
  const restBase = (window.impactshopNgoSelector && window.impactshopNgoSelector.restBase) || "/wp-json/impact/v1/ngo-selector";

  let ngoList = [];

  function setStatus(message) {
    if (statusEl) statusEl.textContent = message || "";
  }

  function buildCard(ngo) {
    if (!cardEl) return;
    if (!ngo) {
      cardEl.innerHTML = '<div class="impactshop-ngo-card__empty">Még nem választottál szervezetet.</div>';
      return;
    }
    const summary = ngo.summary || "A civil szervezet célja a közösségi összefogás és a rászorulók támogatása.";
    cardEl.innerHTML = `
      <div class="impactshop-ngo-card__title">${escapeHtml(ngo.name || "")}</div>
      <div class="impactshop-ngo-card__meta">Adószám: ${escapeHtml(ngo.tax_number || "—")}</div>
      <div class="impactshop-ngo-card__summary">${escapeHtml(summary)}</div>
    `;
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function populateSelect() {
    if (!selectEl) return;
    ngoList.forEach((ngo) => {
      const option = document.createElement("option");
      option.value = ngo.slug;
      option.textContent = ngo.name || ngo.slug;
      selectEl.appendChild(option);
    });
  }

  function getSelection() {
    return fetch(`${restBase}/get?context=${encodeURIComponent(context)}`, {
      credentials: "same-origin",
    })
      .then((res) => res.json())
      .then((data) => {
        if (data && data.ngo_slug) {
          const selected = ngoList.find((ngo) => ngo.slug === data.ngo_slug);
          if (selected && selectEl) {
            selectEl.value = selected.slug;
            buildCard(selected);
            window.impactshopNgoSelectorSelection = { context, slug: selected.slug };
            document.dispatchEvent(new CustomEvent("impactshopNgoSelected", { detail: { context, slug: selected.slug } }));
          }
        } else {
          buildCard(null);
        }
      })
      .catch(() => {
        buildCard(null);
      });
  }

  function saveSelection(slug) {
    return fetch(`${restBase}/set`, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ context, ngo_slug: slug }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data && data.status === "ok") {
          setStatus("Mentve.");
          window.impactshopNgoSelectorSelection = { context, slug };
          document.dispatchEvent(new CustomEvent("impactshopNgoSelected", { detail: { context, slug } }));
          return true;
        }
        if (data && data.status === "missing_pseudo") {
          setStatus("Azonosító (ID) szükséges a mentéshez.");
          return false;
        }
        setStatus("Nem sikerült menteni.");
        return false;
      })
      .catch(() => {
        setStatus("Nem sikerült menteni.");
        return false;
      });
  }

  function onChange() {
    const slug = selectEl ? selectEl.value : "";
    const selected = ngoList.find((ngo) => ngo.slug === slug);
    buildCard(selected || null);
    if (!slug) {
      setStatus("");
      return;
    }
    saveSelection(slug);
  }

  function loadList() {
    if (!listUrl) {
      setStatus("NGO lista nem elérhető.");
      return;
    }
    fetch(listUrl, { credentials: "same-origin" })
      .then((res) => res.json())
      .then((data) => {
        ngoList = Array.isArray(data.items) ? data.items : [];
        populateSelect();
        return getSelection();
      })
      .catch(() => {
        setStatus("NGO lista nem elérhető.");
      });
  }

  if (selectEl) {
    selectEl.addEventListener("change", onChange);
  }

  buildCard(null);
  loadList();
})();
