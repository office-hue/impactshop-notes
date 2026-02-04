;(function(){
  function initPanel(root){
    if (!root) return;
    const restBase = root.getAttribute("data-rest-base") || "";
    const statusEl = root.querySelector("[data-role=status]");
    const greetingEl = root.querySelector("[data-role=greeting]");
    const totalEl = root.querySelector("[data-role=total-display]");
    const messageEl = root.querySelector("[data-role=account-message]");
    const pseudoDisplay = root.querySelector("[data-role=pseudo-display]");
    const recoveryDisplay = root.querySelector("[data-role=recovery-display]");
    const nicknameInput = root.querySelector("[data-role=nickname-input]");
    const nicknameStatus = root.querySelector("[data-role=nickname-status]");
    const saveUsername = root.querySelector("[data-role=save-username]");
    const savePassword = root.querySelector("[data-role=save-password]");
    const pointsSection = root.querySelector("[data-role=points-section]");
    const pointsBadge = root.querySelector("[data-role=points-badge]");
    const pointsLevel = root.querySelector("[data-role=points-level]");
    const pointsTotal = root.querySelector("[data-role=points-total]");
    const pointsProgressBar = root.querySelector("[data-role=points-progress-bar]");
    const pointsProgressText = root.querySelector("[data-role=points-progress-text]");
    const pointsBenefits = root.querySelector("[data-role=points-benefits]");
    const pointsHistory = root.querySelector("[data-role=points-history]");
    const pointsHistoryEmpty = root.querySelector("[data-role=points-history-empty]");
    const lastNgoBox = root.querySelector("[data-role=last-ngo]");
    const pointsCompact = root.querySelector("[data-role=points-compact]");
    const pointsCompactBadge = root.querySelector("[data-role=points-compact-badge]");
    const pointsCompactLevel = root.querySelector("[data-role=points-compact-level]");
    const pointsCompactTotal = root.querySelector("[data-role=points-compact-total]");
    const pointsCompactBar = root.querySelector("[data-role=points-compact-bar]");
    const pointsCompactText = root.querySelector("[data-role=points-compact-text]");
    const pointsInfoTrigger = root.querySelector("[data-role=points-info-trigger]");
    const pointsInfo = root.querySelector("[data-role=points-info]");
    const vacationStatus = root.querySelector("[data-role=vacation-status]");
    const vacationToggle = root.querySelector("[data-role=vacation-toggle]");
    const referralCode = root.querySelector("[data-role=referral-code]");
    const referralCopy = root.querySelector("[data-role=referral-copy]");
    const referralLink = root.querySelector("[data-role=referral-link]");
    const referralInfoTrigger = root.querySelector("[data-role=referral-info-trigger]");
    const referralInfo = root.querySelector("[data-role=referral-info]");
    const badgesSection = root.querySelector("[data-role=badges-section]");
    const legacyBadges = root.querySelector("[data-role=legacy-badges]");
    const badgesEmpty = root.querySelector("[data-role=badges-empty]");
    const herowallSummary = root.querySelector("[data-role=herowall-summary]");
    const herowallTier = root.querySelector("[data-role=herowall-tier]");
    const herowallPoints = root.querySelector("[data-role=herowall-points]");
    let restNonce = window.impactshopIdentityPanel ? window.impactshopIdentityPanel.restNonce : "";
    const sharedState = window.__impactshopIdentityState || (window.__impactshopIdentityState = {});
    let lastPointsTotal = 0;
    let legacyPosition = null;
    const pointsBase = restBase
      ? restBase.replace(/\/impact\/v1\/?$/, "") + "/sharity/v1"
      : "/wp-json/sharity/v1";
    const badgesBase = restBase || "/wp-json/impact/v1";
    const levelMap = [
      { key: "basic", label: "Basic", badge: "🌱", min: 0, max: 500 },
      { key: "bronze", label: "Bronze", badge: "🥉", min: 500, max: 1500 },
      { key: "silver", label: "Silver", badge: "🥈", min: 1500, max: 4000 },
      { key: "gold", label: "Gold", badge: "🥇", min: 4000, max: 8000 },
      { key: "platinum", label: "Platinum", badge: "💎", min: 8000, max: 15000 },
      { key: "legend", label: "Legend", badge: "👑", min: 15000, max: null }
    ];

    function setStatus(msg, isError) {
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.style.color = isError ? "#b91c1c" : "#0f766e";
    }

    function emitIdentityReady(pseudo) {
      if (!pseudo) return;
      const event = new CustomEvent("impactshop_identity_ready", { detail: { pseudo_id: pseudo } });
      window.dispatchEvent(event);
    }

    function getCookie(name) {
      const match = document.cookie.match(new RegExp("(^|; )" + name + "=([^;]*)"));
      return match ? decodeURIComponent(match[2]) : "";
    }

    function refreshPseudo() {
      const pseudo = getCookie("impactshop_pseudo_id");
      if (pseudoDisplay) {
        pseudoDisplay.textContent = pseudo || "—";
      }
      if (saveUsername) {
        saveUsername.value = pseudo || "";
      }
      emitIdentityReady(pseudo || "");
    }

    function formatPoints(value) {
      const num = typeof value === "number" ? value : Number(value || 0);
      return num.toLocaleString("hu-HU") + " pont";
    }

    function formatShortDate(value) {
      if (!value) return "";
      const date = new Date(value.replace(" ", "T") + "Z");
      if (Number.isNaN(date.getTime())) return "";
      return date.toLocaleString("hu-HU", { year: "numeric", month: "short", day: "2-digit" });
    }

    function resolveLevel(pointsValue, currentKey) {
      const points = Math.max(0, Number(pointsValue || 0));
      const match = levelMap.find(function(level){ return level.key === currentKey; }) ||
        levelMap.slice().reverse().find(function(level){ return points >= level.min; }) ||
        levelMap[0];
      const nextIndex = levelMap.findIndex(function(level){ return level.key === match.key; }) + 1;
      const nextLevel = nextIndex < levelMap.length ? levelMap[nextIndex] : null;
      return { current: match, next: nextLevel, points: points };
    }

    function resolveHerowallTier(pointsValue) {
      const points = Math.max(0, Number(pointsValue || 0));
      if (points >= 100) return "Legend";
      if (points >= 50) return "Platinum";
      if (points >= 25) return "Gold";
      if (points >= 10) return "Silver";
      return points >= 1 ? "Bronze" : "Basic";
    }

    const ngoNameCache = {};

    async function resolveNgoName(slug) {
      if (!slug) return "";
      if (Object.prototype.hasOwnProperty.call(ngoNameCache, slug)) {
        return ngoNameCache[slug];
      }
      try {
        const res = await fetch(restBase + "/ngo-card/" + encodeURIComponent(slug) + "?variant=full", {
          credentials: "omit",
          cache: "no-store"
        });
        if (!res.ok) return "";
        const data = await res.json();
        const name = data && data.name ? String(data.name) : "";
        ngoNameCache[slug] = name || "";
        return ngoNameCache[slug];
      } catch (e) {
        return "";
      }
    }

    function setupPopover(trigger, popover) {
      if (!trigger || !popover) return;
      trigger.addEventListener("click", function(ev){
        ev.preventDefault();
        popover.hidden = !popover.hidden;
      });
      trigger.addEventListener("mouseenter", function(){
        popover.hidden = false;
      });
      trigger.addEventListener("mouseleave", function(){
        if (!popover.matches(":hover")) {
          popover.hidden = true;
        }
      });
      popover.addEventListener("mouseleave", function(){
        popover.hidden = true;
      });
      document.addEventListener("click", function(ev){
        if (popover.hidden) return;
        if (popover.contains(ev.target) || trigger.contains(ev.target)) return;
        popover.hidden = true;
      });
    }

    function updatePointsSummary(data) {
      if (!pointsSection && !pointsCompact) return;
      if (pointsSection) pointsSection.hidden = false;
      const pointsValue = data && data.points && typeof data.points.total === "number"
        ? data.points.total
        : Number(data && data.points_total ? data.points_total : 0);
      lastPointsTotal = Number(pointsValue || 0);
      const currentKey = data && data.level ? data.level.current : "basic";
      const resolved = resolveLevel(pointsValue, currentKey);
      if (pointsBadge) pointsBadge.textContent = resolved.current.badge;
      if (pointsLevel) pointsLevel.textContent = resolved.current.label;
      if (pointsTotal) pointsTotal.textContent = formatPoints(pointsValue);
      if (pointsCompact) pointsCompact.hidden = false;
      if (pointsCompactBadge) pointsCompactBadge.textContent = resolved.current.badge;
      if (pointsCompactLevel) pointsCompactLevel.textContent = resolved.current.label;
      if (pointsCompactTotal) pointsCompactTotal.textContent = formatPoints(pointsValue);

      if (pointsProgressBar || pointsProgressText || pointsCompactBar || pointsCompactText) {
        if (!resolved.next) {
          if (pointsProgressBar) pointsProgressBar.style.width = "100%";
          if (pointsProgressText) pointsProgressText.textContent = "Legmagasabb szint elérve.";
          if (pointsCompactBar) pointsCompactBar.style.width = "100%";
          if (pointsCompactText) pointsCompactText.textContent = "Legmagasabb szint elérve.";
        } else {
          const span = resolved.next.min - resolved.current.min;
          const progress = span > 0 ? Math.min(100, Math.max(0, ((resolved.points - resolved.current.min) / span) * 100)) : 0;
          const remaining = Math.max(0, resolved.next.min - resolved.points);
          const progressText = remaining.toLocaleString("hu-HU") + " pont a " + resolved.next.label + " szinthez";
          if (pointsProgressBar) pointsProgressBar.style.width = progress.toFixed(0) + "%";
          if (pointsProgressText) pointsProgressText.textContent = progressText;
          if (pointsCompactBar) pointsCompactBar.style.width = progress.toFixed(0) + "%";
          if (pointsCompactText) pointsCompactText.textContent = progressText;
        }
      }

      if (pointsBenefits) {
        const benefits = data && data.benefits ? data.benefits : {};
        const multiplier = benefits.donation_multiplier ? benefits.donation_multiplier.toFixed(2) : "1.00";
        const discount = benefits.discount_percent || 0;
        const voteAd = benefits.vote_weight_ad || 0;
        const voteSponsor = benefits.vote_weight_sponsor || 0;
        pointsBenefits.innerHTML = [
          "<span>🎯 Adomány szorzó: <strong>" + multiplier + "×</strong></span>",
          "<span>🗳️ Szavazati súly: <strong>" + voteAd + " / " + voteSponsor + "</strong></span>",
          "<span>🏷️ Kedvezmény: <strong>" + discount + "%</strong></span>"
        ].join("");
      }
    }

    function updateHistoryList(items) {
      if (!pointsHistory || !pointsHistoryEmpty) return;
      pointsHistory.innerHTML = "";
      if (!items || !items.length) {
        pointsHistoryEmpty.hidden = false;
        return;
      }
      pointsHistoryEmpty.hidden = true;
      const labels = {
        purchase: "Impact Shop vásárlás",
        first_purchase: "Első vásárlás",
        shop_discovery: "Shop felfedezés",
        video_ad: "Ads videó",
        video_sponsor: "Szponzori videó",
        share: "Megosztás",
        feedback: "Visszajelzés",
        referral: "Ajánlás",
        referral_bonus: "Ajánlói bónusz",
        profile_complete: "Profil kitöltése",
        nickname: "Becenév mentés",
        login_daily: "Napi belépés",
        streak_bonus: "Sorozat bónusz",
        wallet_download: "Wallet letöltés",
        tombola: "Digitális tombola",
        bonus: "Bónusz",
        admin_adjustment: "Pontkorrekció",
        vacation_start: "Vakáció mód",
        vacation_end: "Vakáció mód",
        decay: "Inaktivitás"
      };
      items.forEach(function(row){
        const li = document.createElement("li");
        const left = document.createElement("span");
        const right = document.createElement("span");
        const pts = Number(row.points || 0);
        let label = labels[row.type] || "";
        const meta = row.metadata || {};

        if (row.type === "video_ad" && pts >= 5) {
          label = "Szponzori videó";
        }
        if (!label && meta && typeof meta.source_type === "string") {
          const source = meta.source_type.toLowerCase();
          if (source.includes("purchase") || source.includes("order") || source.includes("impactshop")) {
            label = "Impact Shop vásárlás";
          }
        }
        if (!label) {
          label = "Pontmozgás";
        }
        const when = formatShortDate(row.created_at);
        left.textContent = label + (when ? " · " + when : "");
        right.textContent = (pts > 0 ? "+" : "") + pts.toLocaleString("hu-HU") + " pont";
        li.appendChild(left);
        li.appendChild(right);
        pointsHistory.appendChild(li);
      });
    }

    function getCachedPoints() {
      const cached = sharedState.pointsCache || window.__sharityPointsCache;
      if (!cached || !cached.data || !cached.at) return null;
      if ((Date.now() - cached.at) > 2 * 60 * 1000) return null;
      return cached.data;
    }

    function setPointsCache(data) {
      const payload = { data: data, at: Date.now() };
      sharedState.pointsCache = payload;
      window.__sharityPointsCache = payload;
    }

    function clearPointsCache() {
      sharedState.pointsCache = null;
      sharedState.pointsPromise = null;
      window.__sharityPointsCache = null;
    }

    function getPointsData() {
      const cached = getCachedPoints();
      if (cached) {
        return Promise.resolve(cached);
      }
      if (sharedState.pointsPromise) {
        return sharedState.pointsPromise;
      }
      sharedState.pointsPromise = fetch(pointsBase + "/pseudo/points?ts=" + Date.now(), {
        credentials: "include",
        cache: "no-store"
      })
        .then(function(res){
          if (!res.ok) return null;
          return res.json();
        })
        .then(function(data){
          if (data) {
            setPointsCache(data);
          }
          return data;
        })
        .catch(function(){
          return null;
        })
        .finally(function(){
          sharedState.pointsPromise = null;
        });
      return sharedState.pointsPromise;
    }

    async function fetchPointsSummary() {
      if (!pointsSection && !pointsCompact) return;
      try {
        const data = await getPointsData();
        if (data) {
          updatePointsSummary(data);
        }
      } catch (e) {
        // ignore
      }
    }

    async function fetchPointsHistory() {
      if (!pointsHistory || !pointsHistoryEmpty) return;
      try {
        const res = await fetch(pointsBase + "/pseudo/points/history?per_page=5&ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) return;
        const data = await res.json();
        updateHistoryList(data && data.transactions ? data.transactions : []);
      } catch (e) {
        // ignore
      }
    }

    async function fetchLastNgo() {
      if (!lastNgoBox) return;
      try {
        const res = await fetch(pointsBase + "/pseudo/last-ngo?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) return;
        const data = await res.json();
        const slug = data && data.slug ? String(data.slug) : "";
        if (!slug) {
          lastNgoBox.textContent = "Jelenleg nincs eltárolt NGO választásod.";
          return;
        }
        const ngoName = await resolveNgoName(slug);
        const displayName = ngoName || "Ismeretlen NGO";
        lastNgoBox.innerHTML = "";
        const text = document.createElement("span");
        text.textContent = "Legutóbb ezt a szervezetet támogattad: " + displayName;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "Másikat választok";
        btn.addEventListener("click", async function(){
          await fetch(pointsBase + "/pseudo/last-ngo", {
            method: "POST",
            credentials: "include",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ slug: "" })
          });
          fetchLastNgo();
        });
        lastNgoBox.appendChild(text);
        lastNgoBox.appendChild(btn);
      } catch (e) {
        lastNgoBox.textContent = "Nem sikerült betölteni a legutóbbi NGO-t.";
      }
    }

    async function fetchVacationStatus() {
      if (!vacationStatus || !vacationToggle) return;
      try {
        const res = await fetch(pointsBase + "/pseudo/vacation?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) {
          vacationToggle.disabled = false;
          return;
        }
        const data = await res.json();
        if (data.active) {
          vacationStatus.textContent = "Vakáció aktív: " + (data.days_remaining || 0) + " nap hátra.";
          vacationToggle.textContent = "Kikapcsolás";
          vacationToggle.dataset.mode = "end";
        } else {
          vacationStatus.textContent = "Nincs aktív vakáció. Éves alkalmak: " + (data.remaining_activations || 0);
          vacationToggle.textContent = "Bekapcsolás";
          vacationToggle.dataset.mode = "start";
        }
        vacationToggle.disabled = false;
      } catch (e) {
        vacationToggle.disabled = false;
      }
    }

    async function fetchReferral() {
      if (!referralCode || !referralLink) return;
      try {
        const res = await fetch(pointsBase + "/pseudo/referral?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) return;
        const data = await res.json();
        if (data && data.referral_code) {
          referralCode.textContent = data.referral_code;
          referralLink.textContent = data.url || "";
        }
      } catch (e) {
        // ignore
      }
    }

    function badgeGroupKey(key) {
      const match = String(key || "").match(/^(.*)_\d+$/);
      return match ? match[1] : String(key || "");
    }

    function badgeTierRank(tier) {
      switch (String(tier || "").toLowerCase()) {
        case "silver":
          return 2;
        case "gold":
          return 3;
        case "platinum":
          return 4;
        case "diamond":
          return 5;
        case "legend":
          return 6;
        case "bronze":
        default:
          return 1;
      }
    }

    function badgeIconFor(key, category) {
      const value = String(key || "");
      if (value.startsWith("views") || value.startsWith("video")) return "🎬";
      if (value.startsWith("votes") || value.includes("szavazat") || value.includes("vote")) return "🗳️";
      if (value.includes("offer") || value.includes("task")) return "🎁";
      if (value.startsWith("edu") || value.includes("tanulas")) return "📚";
      if (value.startsWith("streak")) return "🔥";
      if (value.includes("ngo") || value.includes("szervezet") || value.includes("multi")) return "🏛️";
      if (value.startsWith("referral")) return "🤝";
      if (value.startsWith("seasonal")) return "❄️";
      if (value.startsWith("anniversary")) return "🎉";
      if (value.startsWith("early")) return "✨";
      return category === "kozosseg" ? "🤝" : "🏆";
    }

    async function fetchBadges() {
      if (!badgesSection || !legacyBadges || !badgesEmpty) return;
      try {
        const [userRes, availableRes] = await Promise.all([
          fetch(badgesBase + "/badges/user?ts=" + Date.now(), {
            credentials: "include",
            cache: "no-store"
          }),
          fetch(badgesBase + "/badges/available?ts=" + Date.now(), {
            credentials: "omit",
            cache: "no-store"
          })
        ]);
        if (!userRes.ok) return;
        const userData = await userRes.json();
        const availableData = availableRes.ok ? await availableRes.json() : { badges: [] };
        const available = Array.isArray(availableData.badges) ? availableData.badges : [];
        const availableMap = {};
        available.forEach(function(row){
          if (row && row.badge_key) {
            availableMap[row.badge_key] = row;
          }
        });
        const badges = Array.isArray(userData.badges) ? userData.badges : [];
        legacyBadges.innerHTML = "";
        if (!badges.length) {
          badgesSection.hidden = false;
          badgesEmpty.hidden = false;
          if (herowallSummary) {
            herowallSummary.hidden = true;
          }
          return;
        }
        badgesSection.hidden = false;
        badgesEmpty.hidden = true;
        const grouped = {};
        badges.forEach(function(row){
          const key = row.badge_key || "";
          const group = badgeGroupKey(key);
          const meta = availableMap[key] || {};
          const tier = row.tier || meta.default_tier || "bronze";
          const rank = badgeTierRank(tier);
          const existing = grouped[group];
          if (!existing || rank > existing.rank) {
            grouped[group] = { row: row, meta: meta, rank: rank };
          } else if (rank === existing.rank) {
            const currNum = parseInt((String(key).match(/_(\d+)$/) || [])[1] || "0", 10);
            const existNum = parseInt((String(existing.row.badge_key).match(/_(\d+)$/) || [])[1] || "0", 10);
            if (currNum > existNum) {
              grouped[group] = { row: row, meta: meta, rank: rank };
            }
          }
        });
        const selected = Object.keys(grouped).map(function(k){ return grouped[k]; });
        selected.sort(function(a, b){
          const aTime = Date.parse(a.row.awarded_at || "") || 0;
          const bTime = Date.parse(b.row.awarded_at || "") || 0;
          return bTime - aTime;
        });
        selected.forEach(function(item){
          const meta = item.meta || {};
          const name = meta.name_hu || item.row.badge_key || "Jelvény";
          const icon = badgeIconFor(item.row.badge_key, meta.category || "");
          const badge = document.createElement("span");
          badge.className = "impactshop-legacy-badge";
          badge.title = (meta.description_hu || name || "").toString();
          badge.innerHTML = '<span class="impactshop-legacy-badge-icon">' + icon + '</span><span>' + name + '</span>';
          legacyBadges.appendChild(badge);
        });
        if (herowallSummary && herowallTier && herowallPoints) {
          if (badges.length) {
            herowallSummary.hidden = false;
            herowallTier.textContent = legacyPosition
              ? "Legacy Pool helyezés: #" + legacyPosition
              : "Legacy Pool helyezés: —";
            herowallPoints.textContent = "Pontszám: " + lastPointsTotal.toLocaleString("hu-HU") + " pont";
          } else {
            herowallSummary.hidden = true;
          }
        }
      } catch (e) {
        // ignore
      }
    }

    async function fetchLegacySummary() {
      if (!herowallSummary) return;
      try {
        const res = await fetch(badgesBase + "/herowall?limit=1&ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) return;
        const data = await res.json();
        legacyPosition = (data && typeof data.user_position === "number") ? data.user_position : null;
      } catch (e) {
        legacyPosition = null;
      }
    }

    async function refreshPointsSection() {
      await Promise.all([
        fetchPointsSummary(),
        fetchPointsHistory(),
        fetchLastNgo(),
        fetchVacationStatus(),
        fetchReferral(),
        fetchLegacySummary(),
        fetchBadges()
      ]);
    }

    async function refreshNonce() {
      try {
        const res = await fetch(restBase + "/identity/refresh-nonce?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store"
        });
        if (!res.ok) return "";
        const data = await res.json();
        if (data && data.nonce) {
          restNonce = data.nonce;
          if (window.impactshopIdentityPanel) {
            window.impactshopIdentityPanel.restNonce = data.nonce;
          }
          return data.nonce;
        }
      } catch (e) {
        return "";
      }
      return "";
    }

    async function postWithNonce(url, payload, credentialsMode) {
      const headers = { "Content-Type": "application/json" };
      if (restNonce) {
        headers["X-WP-Nonce"] = restNonce;
      }
      let res = await fetch(url, {
        method: "POST",
        headers,
        credentials: credentialsMode || "include",
        body: JSON.stringify(payload)
      });
      if (res.status === 403) {
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        const message = data && data.message ? String(data.message) : "";
        if (/cookie check failed|süti/i.test(message)) {
          const newNonce = await refreshNonce();
          if (newNonce) {
            headers["X-WP-Nonce"] = newNonce;
            res = await fetch(url, {
              method: "POST",
              headers,
              credentials: credentialsMode || "include",
              body: JSON.stringify(payload)
            });
          }
        } else {
          res._data = data;
        }
      }
      return res;
    }

    function awardCredentialsSave(pseudo) {
      if (!pseudo || pseudo === "—") return;
      const payload = {
        points: 10,
        type: "profile_complete",
        source_id: "credentials",
        metadata: { source_type: "credentials_saved" },
        dedupe_key: "profile_complete:credentials:" + pseudo
      };
      try {
        fetch(pointsBase + "/pseudo/points/earn", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "include",
          keepalive: true,
          body: JSON.stringify(payload)
        }).catch(() => {});
      } catch (e) {
        // ignore
      }
    }

    function getCachedProfile() {
      if (!sharedState.profileCache || !sharedState.profileAt) return null;
      if ((Date.now() - sharedState.profileAt) > 2 * 60 * 1000) return null;
      return sharedState.profileCache;
    }

    function applyProfileData(data) {
      if (!data || !data.pseudo_id || !data.recovery_code) {
        setStatus("Nem sikerült azonosítót kérni. Próbáld újra.", true);
        return;
      }
      const pseudo = String(data.pseudo_id || "");
      if (pseudo && sharedState.lastPseudo && sharedState.lastPseudo !== pseudo) {
        sharedState.pointsCache = null;
        window.__sharityPointsCache = null;
      }
      if (pseudo && !sharedState.lastPseudo) {
        sharedState.pointsCache = null;
        window.__sharityPointsCache = null;
      }
      if (pseudo) {
        sharedState.lastPseudo = pseudo;
      }
      if (greetingEl) {
        if (data.nickname) {
          greetingEl.textContent = "Szia " + data.nickname + "! Üdvözöllek a Sharity oldalán.";
        } else {
          greetingEl.textContent = "Szia, üdvözöllek a Sharity oldalán.";
        }
      }
      if (recoveryDisplay) {
        recoveryDisplay.textContent = data.recovery_code ? data.recovery_code : "—";
      }
      if (savePassword) {
        savePassword.value = data.recovery_code ? data.recovery_code : "";
      }
      if (nicknameInput && data.nickname && !nicknameInput.value) {
        nicknameInput.value = data.nickname;
      }
      if (pseudoDisplay && pseudo) {
        pseudoDisplay.textContent = pseudo;
      }
      if (saveUsername && pseudo) {
        saveUsername.value = pseudo;
      }
      emitIdentityReady(pseudo);
      setStatus("Azonosító betöltve.");
    }

    async function fetchProfile() {
      const cached = getCachedProfile();
      if (cached) {
        applyProfileData(cached);
        return cached;
      }
      if (sharedState.profilePromise) {
        const data = await sharedState.profilePromise;
        if (data) {
          applyProfileData(data);
        }
        return data;
      }
      sharedState.profilePromise = fetch(restBase + "/identity/profile?ts=" + Date.now(), {
        credentials: "include",
        cache: "no-store",
        headers: { "Cache-Control": "no-cache" }
      })
        .then(function(res){
          if (!res.ok) return null;
          return res.json();
        })
        .catch(function(){
          return null;
        })
        .finally(function(){
          sharedState.profilePromise = null;
        });
      const data = await sharedState.profilePromise;
      if (data) {
        sharedState.profileCache = data;
        sharedState.profileAt = Date.now();
        applyProfileData(data);
      }
      return data;
    }

    async function fetchTotal() {
      if (!totalEl) return;
      try {
        const res = await fetch(restBase + "/identity/total?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store",
          headers: { "Cache-Control": "no-cache" }
        });
        if (!res.ok) {
          return;
        }
        const data = await res.json();
        const total = typeof data.total_huf === "number" ? data.total_huf : 0;
        totalEl.textContent = "Támogatásaim összege: " + total.toLocaleString("hu-HU") + " Ft";
      } catch (e) {
        // silent fail
      }
    }

    async function fetchMessages() {
      if (!messageEl) return;
      try {
        const res = await fetch(restBase + "/identity/messages?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store",
          headers: { "Cache-Control": "no-cache" }
        });
        if (!res.ok) return;
        const data = await res.json();
        const messages = data && Array.isArray(data.messages) ? data.messages : [];
        if (!messages.length) {
          messageEl.hidden = true;
          return;
        }
        const msg = messages[0];
        messageEl.hidden = false;
        messageEl.innerHTML = "";
        const text = document.createElement("span");
        text.textContent = msg.content || "";
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "Rendben";
        btn.addEventListener("click", async function(){
          messageEl.hidden = true;
          if (msg.type === "targeted" && msg.id) {
            try {
              await fetch(restBase + "/identity/message-read", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": restNonce
                },
                credentials: "include",
                body: JSON.stringify({ message_id: msg.id })
              });
            } catch (e) {
              // ignore
            }
          }
        });
        messageEl.appendChild(text);
        messageEl.appendChild(btn);
      } catch (e) {
        // ignore
      }
    }

    function isPseudoValid(value) {
      return /^[a-z0-9]{10,12}$/i.test(value);
    }

    function normalizeRecovery(value) {
      return (value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
    }

    const refreshBtn = root.querySelector("[data-role=refresh-profile]");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", function(){
        setStatus("Frissítés folyamatban…");
        fetchProfile().then(refreshPointsSection);
      });
    }

    const scrollBtn = root.querySelector("[data-role=scroll-account]");
    if (scrollBtn) {
      scrollBtn.addEventListener("click", function(){
        const target = root.querySelector("#impactshop-account-top") || document.getElementById("impactshop-account-top") ||
          root.querySelector("#impactshop-account") || document.getElementById("impactshop-account");
        if (target && target.scrollIntoView) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    }

    const scrollRestoreBtn = root.querySelector("[data-role=scroll-restore]");
    if (scrollRestoreBtn) {
      scrollRestoreBtn.addEventListener("click", function(){
        const target = root.querySelector("#impactshop-restore-title") || document.getElementById("impactshop-restore-title");
        if (target && target.scrollIntoView) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    }

    const copyBtn = root.querySelector("[data-role=copy-pseudo]");
    if (copyBtn) {
      copyBtn.addEventListener("click", async function(){
        const value = pseudoDisplay ? pseudoDisplay.textContent.trim() : "";
        if (!value || value === "—") {
          setStatus("Nincs aktív azonosító.", true);
          return;
        }
        try {
          await navigator.clipboard.writeText(value);
          setStatus("Azonosító másolva.");
        } catch (e) {
          setStatus("Másolás sikertelen.", true);
        }
      });
    }

    const copyRecoveryBtn = root.querySelector("[data-role=copy-recovery]");
    if (copyRecoveryBtn) {
      copyRecoveryBtn.addEventListener("click", async function(){
        const recovery = recoveryDisplay ? recoveryDisplay.textContent.trim() : "";
        if (!recovery || recovery === "—") {
          setStatus("Nincs aktív kód.", true);
          return;
        }
        try {
          await navigator.clipboard.writeText(recovery);
          setStatus("Helyreállító kód másolva.");
        } catch (e) {
          setStatus("Másolás sikertelen.", true);
        }
      });
    }

    const shareBtn = root.querySelector("[data-role=share-both]");
    if (shareBtn) {
      shareBtn.addEventListener("click", async function(){
        setStatus("Megosztás indítása…");
        const pseudo = pseudoDisplay ? pseudoDisplay.textContent.trim() : "";
        const recovery = recoveryDisplay ? recoveryDisplay.textContent.trim() : "";
        if (!pseudo || pseudo === "—" || !recovery || recovery === "—") {
          setStatus("Nincs aktív azonosító vagy kód.", true);
          return;
        }
        const payload = "Azonosító: " + pseudo + "\nHelyreállító kód: " + recovery;
        if (navigator.share) {
          try {
            await navigator.share({ title: "Impact Shop azonosító", text: payload });
            setStatus("Megosztva.");
            return;
          } catch (e) {
            if (e && e.name !== "AbortError") {
              setStatus("Megosztás megszakítva.", false);
            }
          }
        }
        if (navigator.clipboard && window.isSecureContext) {
          try {
            await navigator.clipboard.writeText(payload);
            setStatus("Megosztás helyett másolva.");
            return;
          } catch (e) {
            // fall back to prompt
          }
        }
        window.prompt("Másold ki az adatokat:", payload);
        setStatus("Másold ki az adatokat a felugró ablakból.");
      });
    }

    const saveForm = root.querySelector("[data-role=save-form]");
    const savePasswordBtn = root.querySelector("[data-role=save-password-manager]");
    async function handleSavePassword(e) {
      const pseudo = pseudoDisplay ? pseudoDisplay.textContent.trim() : "";
      const recovery = recoveryDisplay ? recoveryDisplay.textContent.trim() : "";
      if (!pseudo || pseudo === "—" || !recovery || recovery === "—") {
        if (e) e.preventDefault();
        setStatus("Nincs aktív azonosító vagy kód.", true);
        return;
      }
      if (saveUsername) {
        saveUsername.value = pseudo;
      }
      if (savePassword) {
        savePassword.value = recovery;
      }

      if (window.PasswordCredential && navigator.credentials && navigator.credentials.store) {
        if (e) e.preventDefault();
        try {
          const cred = new PasswordCredential({ id: pseudo, password: recovery, name: "Impact Shop ID" });
          await navigator.credentials.store(cred);
          setStatus("Mentve a jelszókezelőbe.");
          awardCredentialsSave(pseudo);
          return;
        } catch (err) {
          setStatus("A jelszómentés nem sikerült, próbáld újra.", true);
          return;
        }
      }

      if (e) e.preventDefault();
      setStatus("A böngésző felajánlhatja a mentést. Ha nem kérdez rá, valószínűleg már mentve van.");
      awardCredentialsSave(pseudo);
    }
    if (saveForm) {
      saveForm.addEventListener("submit", handleSavePassword);
    }
    if (savePasswordBtn) {
      savePasswordBtn.addEventListener("click", handleSavePassword);
    }

    const restorePseudo = root.querySelector("[data-role=restore-pseudo]");
    const restoreRecovery = root.querySelector("[data-role=restore-recovery]");
    const restoreSubmit = root.querySelector("[data-role=restore-submit]");
    const restoreStatus = root.querySelector("[data-role=restore-status]");
    if (restoreSubmit) {
      restoreSubmit.addEventListener("click", async function(){
        const pseudo = (restorePseudo && restorePseudo.value || "").trim().toLowerCase();
        const recoveryRaw = (restoreRecovery && restoreRecovery.value || "").trim();
        const recovery = normalizeRecovery(recoveryRaw);
        if (restoreStatus) restoreStatus.textContent = "";
        if (!isPseudoValid(pseudo)) {
          setStatus("Adj meg érvényes azonosítót.", true);
          if (restoreStatus) restoreStatus.textContent = "Adj meg érvényes azonosítót.";
          return;
        }
        if (!/^[A-Z0-9]{12}$/.test(recovery)) {
          setStatus("Érvénytelen helyreállító kód formátum.", true);
          if (restoreStatus) restoreStatus.textContent = "Érvénytelen helyreállító kód formátum.";
          return;
        }
        setStatus("Helyreállítás folyamatban…");
        if (restoreStatus) restoreStatus.textContent = "Helyreállítás folyamatban…";
        restoreSubmit.disabled = true;
        const originalLabel = restoreSubmit.textContent;
        restoreSubmit.textContent = "Helyreállítás…";
        try {
          const res = await postWithNonce(
            restBase + "/identity/restore",
            { pseudo_id: pseudo, recovery_code: recoveryRaw },
            "include"
          );
          const data = (res._data !== undefined) ? res._data : await res.json();
          if (!res.ok) {
            const message = (data && data.message) ? data.message : "Helyreállítás sikertelen.";
            setStatus(message, true);
            if (restoreStatus) restoreStatus.textContent = message;
            return;
          }
          document.cookie = "impactshop_pseudo_id=" + encodeURIComponent(pseudo) + "; path=/; samesite=Lax";
          if (pseudoDisplay) {
            pseudoDisplay.textContent = pseudo;
          }
          if (saveUsername) {
            saveUsername.value = pseudo;
          }
          setStatus("Azonosító helyreállítva.");
          if (restoreStatus) restoreStatus.textContent = "Azonosító helyreállítva.";
          emitIdentityReady(pseudo);
          fetchProfile().then(refreshPointsSection);
          setTimeout(function(){
            window.location.reload();
          }, 800);
        } catch (e) {
          setStatus("Helyreállítás hiba.", true);
          if (restoreStatus) restoreStatus.textContent = "Helyreállítás hiba.";
        } finally {
          restoreSubmit.disabled = false;
          restoreSubmit.textContent = originalLabel;
        }
      });
    }

    const saveNicknameBtn = root.querySelector("[data-role=save-nickname]");
    if (saveNicknameBtn) {
      saveNicknameBtn.addEventListener("click", async function(){
        const pseudo = pseudoDisplay ? pseudoDisplay.textContent.trim().toLowerCase() : "";
        const nickname = (nicknameInput.value || "").trim();
        if (!isPseudoValid(pseudo)) {
          setStatus("Adj meg érvényes azonosítót.", true);
          if (nicknameStatus) nicknameStatus.textContent = "";
          return;
        }
        setStatus("Becenév mentése…");
        if (nicknameStatus) nicknameStatus.textContent = "";
        saveNicknameBtn.disabled = true;
        const originalLabel = saveNicknameBtn.textContent;
        saveNicknameBtn.textContent = "Mentés…";
        try {
          const res = await postWithNonce(
            restBase + "/identity/profile",
            { pseudo_id: pseudo, nickname: nickname },
            "same-origin"
          );
          const data = (res._data !== undefined) ? res._data : await res.json();
          if (!res.ok) {
            const message = (data && data.message) ? data.message : "Becenév mentése sikertelen.";
            setStatus(message, true);
            if (nicknameStatus) nicknameStatus.textContent = "Sikertelen mentés.";
            return;
          }
          setStatus("Becenév mentve.");
          if (nicknameStatus) nicknameStatus.textContent = "Becenév mentve.";
          saveNicknameBtn.textContent = "Mentve";
          setTimeout(function(){
            saveNicknameBtn.textContent = originalLabel;
          }, 1600);
        } catch (e) {
          setStatus("Becenév mentése hiba.", true);
          if (nicknameStatus) nicknameStatus.textContent = "Hiba történt.";
        } finally {
          saveNicknameBtn.disabled = false;
          if (saveNicknameBtn.textContent === "Mentés…") {
            saveNicknameBtn.textContent = originalLabel;
          }
        }
      });
    }

    if (vacationToggle) {
      vacationToggle.disabled = false;
      vacationToggle.addEventListener("click", async function(){
        const mode = vacationToggle.dataset.mode || "start";
        vacationToggle.disabled = true;
        try {
          if (mode === "end") {
            const res = await fetch(pointsBase + "/pseudo/vacation/end", {
              method: "POST",
              credentials: "include",
              headers: { "X-WP-Nonce": restNonce }
            });
            if (!res.ok) {
              const data = await res.json().catch(function(){ return {}; });
              setStatus((data && data.message) ? data.message : "Vakáció kikapcsolása sikertelen.", true);
              return;
            }
          } else {
            const res = await fetch(pointsBase + "/pseudo/vacation", {
              method: "POST",
              credentials: "include",
              headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": restNonce
              },
              body: JSON.stringify({ days: 14 })
            });
            if (!res.ok) {
              const data = await res.json().catch(function(){ return {}; });
              setStatus((data && data.message) ? data.message : "Vakáció indítása sikertelen.", true);
              return;
            }
          }
          fetchVacationStatus();
        } catch (e) {
          setStatus("Vakáció mód hiba. Próbáld újra.", true);
        } finally {
          vacationToggle.disabled = false;
        }
      });
    }

    if (referralCopy) {
      referralCopy.addEventListener("click", async function(){
        const code = referralCode ? referralCode.textContent.trim() : "";
        const link = referralLink ? referralLink.textContent.trim() : "";
        const value = link || code;
        if (!value || value === "—") {
          setStatus("Nincs ajánlói kód.", true);
          return;
        }
        try {
          await navigator.clipboard.writeText(value);
          setStatus(link ? "Ajánlói link másolva." : "Ajánlói kód másolva.");
        } catch (e) {
          setStatus("Másolás sikertelen.", true);
        }
      });
    }

    setupPopover(pointsInfoTrigger, pointsInfo);
    setupPopover(referralInfoTrigger, referralInfo);

    window.addEventListener("sharity_points_updated", function(event){
      const detail = event && event.detail ? event.detail : {};
      const incoming = String(detail.pseudo_id || "").toLowerCase();
      const current = (pseudoDisplay ? String(pseudoDisplay.textContent || "").trim().toLowerCase() : "") ||
        String(getCookie("impactshop_pseudo_id") || "").toLowerCase();
      if (!incoming || !current || incoming !== current) {
        return;
      }
      clearPointsCache();
      fetchPointsSummary();
      fetchPointsHistory();
    });

    refreshPseudo();
    fetchProfile()
      .then(function(){
        return Promise.all([fetchTotal(), fetchMessages(), refreshPointsSection()]);
      })
      .catch(function(){
        return Promise.all([fetchTotal(), fetchMessages(), refreshPointsSection()]);
      });
  }

  document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll(".impactshop-identity-panel").forEach(initPanel);
  });
})();
