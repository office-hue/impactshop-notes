(function(){
  function getCookie(name) {
    const match = document.cookie.match(new RegExp("(^|; )" + name + "=([^;]*)"));
    return match ? decodeURIComponent(match[2]) : "";
  }

  function waitForIdentity() {
    return new Promise(function(resolve){
      const existing = getCookie("impactshop_pseudo_id");
      if (existing) {
        resolve(existing);
        return;
      }
      function handler(event){
        if (event && event.detail && event.detail.pseudo_id) {
          window.removeEventListener("impactshop_identity_ready", handler);
          resolve(event.detail.pseudo_id);
        }
      }
      window.addEventListener("impactshop_identity_ready", handler);
      setTimeout(function(){
        const fallback = getCookie("impactshop_pseudo_id");
        if (fallback) {
          resolve(fallback);
        }
      }, 1200);
    });
  }

  function initVote(root){
    if (!root) return;
    const config = window.impactshopVoteJysk || {};
    const restBase = config.restBase || "";
    let restNonce = config.restNonce || "";
    const nonceIssuedAt = config.nonceIssuedAt || 0;
    const videoWrap = root.querySelector("[data-role=video-wrap]");
    const ngoList = root.querySelector("[data-role=ngo-list]");
    const voteBtn = root.querySelector("[data-role=vote-submit]");
    const statusEl = root.querySelector("[data-role=vote-status]");
    const progressText = root.querySelector("[data-role=progress-text]");
    const progressBar = root.querySelector("[data-role=progress-bar]");
    const tallyEl = root.querySelector("[data-role=tally]");
    const socialCountEl = root.querySelector("[data-role=social-count]");
    const liveTickerEl = root.querySelector("[data-role=live-ticker]");
    const microMessageEl = root.querySelector("[data-role=micro-message]");
    const oddsEl = root.querySelector("[data-role=odds]");
    const sheetEl = root.querySelector("[data-role=ngo-sheet]");
    const sheetTitleEl = root.querySelector("[data-role=ngo-sheet-title]");
    const sheetBodyEl = root.querySelector("[data-role=ngo-sheet-body]");
    const toastEl = root.querySelector("[data-role=toast]");
    const stepsEl = root.querySelector("[data-role=steps]");
    const selectedBarEl = root.querySelector("[data-role=selected-bar]");
    const countdownEl = root.querySelector("[data-role=countdown]");

    let campaign = null;
    let statusState = { voted_today: false, next_vote_available_at: "" };
    let selectedNgo = null;
    let viewToken = "";
    let videoCompletedAt = 0;
    let tallyEtag = "";
    let tallyTimer = null;
    const tallyState = { totalVotes: 0, shownNames: 0 };
    const milestones = {25: false, 50: false, 75: false};

    function setStatus(msg, isError){
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.style.color = isError ? "#b91c1c" : "#0f766e";
      showToast(msg);
    }

    function showToast(message){
      if (!toastEl) return;
      toastEl.textContent = message;
      toastEl.classList.add("is-visible");
      clearTimeout(toastEl._timer);
      toastEl._timer = setTimeout(function(){
        toastEl.classList.remove("is-visible");
      }, 3200);
    }

    function setStepState(step, state){
      if (!stepsEl) return;
      const el = stepsEl.querySelector('[data-step="' + step + '"]');
      if (!el) return;
      el.classList.remove("is-active", "is-done");
      if (state === "active") el.classList.add("is-active");
      if (state === "done") el.classList.add("is-done");
    }

    function updateSelectedBar(){
      if (!selectedBarEl) return;
      if (!selectedNgo) {
        selectedBarEl.style.display = "none";
        selectedBarEl.textContent = "";
        return;
      }
      selectedBarEl.style.display = "";
      selectedBarEl.textContent = "Választott: " + selectedNgo.ngo_name + " (Változtatás ↑)";
    }

    function ngoCategory(ngo){
      const slug = (ngo.ngo_slug || "").toLowerCase();
      if (slug.includes("gyerek") || slug.includes("autist") || slug.includes("child")) return "kids";
      if (slug.includes("allat") || slug.includes("animal")) return "animals";
      if (slug.includes("kornyezet") || slug.includes("zold") || slug.includes("green")) return "environment";
      if (slug.includes("szinhaz") || slug.includes("kultura") || slug.includes("muvelodes")) return "culture";
      return "other";
    }

    function showSheet(ngo){
      if (!sheetEl || !sheetTitleEl || !sheetBodyEl) return;
      sheetTitleEl.textContent = ngo.ngo_name || "Részletek";
      sheetBodyEl.textContent = ngo.description || "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus id sapien et libero fermentum dictum. Nulla facilisi.";
      sheetEl.classList.add("is-open");
    }

    function hideSheet(){
      if (!sheetEl) return;
      sheetEl.classList.remove("is-open");
    }

    function renderSkeleton(){
      if (videoWrap) {
        videoWrap.innerHTML = "<div class=\"impactshop-vote__skeleton impactshop-vote__skeleton--video\"></div>";
      }
      if (ngoList) {
        ngoList.innerHTML = "";
        for (let i = 0; i < 5; i++) {
          const card = document.createElement("div");
          card.className = "impactshop-vote__skeleton impactshop-vote__skeleton--card";
          ngoList.appendChild(card);
        }
      }
    }

    if (sheetEl) {
      sheetEl.querySelectorAll("[data-role=ngo-sheet-close]").forEach(function(btn){
        btn.addEventListener("click", hideSheet);
      });
    }

    function renderCountdown(endAt){
      if (!countdownEl) return;
      if (!endAt) {
        countdownEl.textContent = "";
        return;
      }
      const end = new Date(endAt).getTime();
      if (isNaN(end)) {
        countdownEl.textContent = "";
        return;
      }
      function update(){
        const now = Date.now();
        const diff = Math.max(0, end - now);
        if (diff === 0) {
          countdownEl.innerHTML = "⏰ <strong>A szavazás lezárult</strong>";
          return;
        }
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        countdownEl.innerHTML = "⏱️ <strong>" + days + "n " + hours + "ó " + minutes + "p</strong> múlva zárul";
      }
      update();
      if (countdownEl._timer) {
        clearInterval(countdownEl._timer);
      }
      countdownEl._timer = setInterval(update, 30000);
    }

    function formatNextVote(iso){
      if (!iso) return "";
      const date = new Date(iso);
      if (isNaN(date.getTime())) return "";
      return date.toLocaleString("hu-HU", { year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" });
    }

    function trackEvent(name, params){
      if (window.gtag) {
        window.gtag("event", name, params || {});
      }
    }

    async function ensureFreshNonce(){
      if (!nonceIssuedAt) return;
      const ageMs = Date.now() - (nonceIssuedAt * 1000);
      if (ageMs < 12 * 3600 * 1000) return;
      try {
        const res = await fetch(restBase + "/vote/refresh-nonce?ts=" + Date.now(), { credentials: "include" });
        if (!res.ok) return;
        const data = await res.json();
        if (data && data.nonce) {
          restNonce = data.nonce;
        }
      } catch (e) {
        // ignore
      }
    }

    function setProgress(percent){
      if (progressBar) {
        progressBar.style.width = Math.min(100, Math.max(0, percent)) + "%";
      }
      if (progressText) {
        progressText.textContent = percent >= 100 ? "Videó teljesítve." : ("Eddig hitelesen: " + percent.toFixed(0) + "%");
      }
    }

    function renderVideo(){
      if (!videoWrap || !campaign || !campaign.video_url) {
        if (videoWrap) {
          videoWrap.innerHTML = "<div>Videó nem elérhető.</div>";
        }
        return;
      }
      if (statusState.voted_today) {
        const nextText = formatNextVote(statusState.next_vote_available_at);
        videoWrap.innerHTML = "<div>Ma már szavaztál. A következő szavazat legkorábban ekkor adható le: " + (nextText || "holnap") + ".</div>";
        return;
      }
      const video = document.createElement("video");
      const storageKey = "jysk_video_progress";
      video.src = campaign.video_url;
      video.poster = campaign.poster_url || "";
      video.controls = true;
      video.setAttribute("playsinline", "playsinline");
      videoWrap.innerHTML = "";
      videoWrap.appendChild(video);

      let maxWatched = 0;
      let saveTimer = null;
      const savedRaw = localStorage.getItem(storageKey);
      if (savedRaw) {
        try {
          const saved = JSON.parse(savedRaw);
          if (saved && saved.campaign_id === campaign.id && Date.now() - saved.timestamp < 60 * 60 * 1000) {
            const minutes = Math.floor(saved.current_time / 60);
            const seconds = Math.floor(saved.current_time % 60).toString().padStart(2, "0");
            if (confirm("Folytatod innen: " + minutes + ":" + seconds + "?")) {
              video.currentTime = saved.current_time;
            }
          }
        } catch (e) {
          // ignore
        }
      }
      video.addEventListener("timeupdate", function(){
        if (video.currentTime > maxWatched) {
          maxWatched = video.currentTime;
        } else if (video.currentTime > maxWatched + 1) {
          video.currentTime = maxWatched;
        }
        if (video.duration) {
          const percent = Math.min(100, (video.currentTime / video.duration) * 100);
          setProgress(percent);
          [25, 50, 75].forEach(function(step){
            if (!milestones[step] && percent >= step) {
              milestones[step] = true;
              trackEvent("video_progress", { percent: step, campaign_id: campaign.id });
            }
          });
        }
        if (!saveTimer) {
          saveTimer = setTimeout(function(){
            saveTimer = null;
            localStorage.setItem(storageKey, JSON.stringify({
              campaign_id: campaign.id,
              current_time: video.currentTime,
              timestamp: Date.now()
            }));
          }, 5000);
        }
      });

      video.addEventListener("ratechange", function(){
        if (video.playbackRate !== 1) {
          video.playbackRate = 1;
        }
      });

      video.addEventListener("play", function(){
        trackEvent("video_start", { campaign_id: campaign.id });
        videoWrap.classList.add("is-playing");
        setStepState(1, "active");
      });
      video.addEventListener("pause", function(){
        videoWrap.classList.remove("is-playing");
      });

      video.addEventListener("ended", function(){
        const duration = video.duration || 0;
        const percent = duration ? (video.currentTime / duration) * 100 : 100;
        setProgress(100);
        localStorage.removeItem(storageKey);
        if (percent >= 98) {
          videoCompletedAt = Date.now();
          setStepState(1, "done");
          setStepState(2, "active");
          requestViewToken();
        }
        videoWrap.classList.remove("is-playing");
      });
    }

    function renderNgos(){
      if (!ngoList || !campaign) return;
      ngoList.innerHTML = "";
      (campaign.ngos || []).forEach(function(ngo){
        const card = document.createElement("div");
        card.className = "impactshop-vote__ngo";
        card.dataset.id = ngo.id;

        const title = document.createElement("h4");
        title.textContent = ngo.ngo_name;
        const desc = document.createElement("p");
        desc.textContent = ngo.description || "";

      if (ngo.logo_url) {
        const img = document.createElement("img");
        img.src = ngo.logo_url;
        img.alt = ngo.ngo_name;
        card.appendChild(img);
      }

      card.appendChild(title);
      card.appendChild(desc);
      const more = document.createElement("button");
      more.type = "button";
      more.className = "impactshop-vote__ngo-more";
      more.textContent = "Részletek";
      more.addEventListener("click", function(e){
        e.stopPropagation();
        showSheet(ngo);
      });
      card.appendChild(more);

      const category = ngoCategory(ngo);
      card.classList.add("impactshop-vote__ngo--" + category);
        card.addEventListener("click", function(){
          selectedNgo = ngo;
          document.querySelectorAll(".impactshop-vote__ngo").forEach(function(el){
            el.classList.remove("impactshop-vote__ngo--active");
          });
          card.classList.add("impactshop-vote__ngo--active");
          if (viewToken) {
            voteBtn.disabled = false;
          }
          setStepState(2, "done");
          setStepState(3, "active");
          updateSelectedBar();
        });
        ngoList.appendChild(card);
      });
    }

    async function fetchInit(){
      renderSkeleton();
      const res = await fetch(restBase + "/vote/init?ts=" + Date.now(), { credentials: "include" });
      if (!res.ok) {
        setStatus("Nem elérhető a kampány.", true);
        return;
      }
      const data = await res.json();
      campaign = data.campaign && data.campaign.status === "active" ? data.campaign : null;
      statusState = data.status || { voted_today: false, next_vote_available_at: "" };

      if (!campaign) {
        setStatus("Jelenleg nincs aktív kampány.", true);
        return;
      }

      renderCountdown(campaign.end_at);
      renderVideo();
      renderNgos();
      if (statusState.voted_today) {
        voteBtn.disabled = true;
        const nextText = formatNextVote(statusState.next_vote_available_at);
        setStatus("Ma már szavaztál. Következő szavazat: " + (nextText || "holnap") + ".", true);
        setStepState(1, "done");
        setStepState(2, "done");
        setStepState(3, "done");
      }
      startTally();

      if (liveTickerEl && !liveTickerEl._timer) {
        const names = ["Anna", "Péter", "Zsuzsa", "Gábor", "Kata", "Bence", "Réka"];
        liveTickerEl._timer = setInterval(function(){
          if (tallyState.totalVotes === 0 || tallyState.shownNames >= Math.max(0, tallyState.totalVotes - 1)) {
            liveTickerEl.textContent = "🔥 Élő aktivitás: várakozás…";
            return;
          }
          const name = names[Math.floor(Math.random() * names.length)];
          const seconds = Math.floor(Math.random() * 60) + 5;
          liveTickerEl.textContent = "🔥 " + name + " most szavazott • " + seconds + " másodperce";
          tallyState.shownNames += 1;
        }, 15000);
      }
    }

    async function requestViewToken(){
      if (!campaign) return;
      await ensureFreshNonce();
      if (statusState.voted_today) {
        const nextText = formatNextVote(statusState.next_vote_available_at);
        setStatus("Ma már szavaztál. Következő szavazat: " + (nextText || "holnap") + ".", true);
        voteBtn.disabled = true;
        return;
      }
      if (Date.now() - videoCompletedAt > 60 * 1000) {
        setStatus("A videó túl régen fejeződött be, kérlek nézd meg újra.", true);
        viewToken = "";
        voteBtn.disabled = true;
        return;
      }
      try {
        const res = await fetch(restBase + "/vote/view", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": restNonce
          },
          credentials: "include",
          body: JSON.stringify({ campaign_id: campaign.id, completed: true })
        });
        const data = await res.json();
        if (!res.ok) {
          if (res.status === 503) {
            setStatus("Karbantartás folyamatban, kérlek próbáld később.", true);
          } else {
            setStatus(data.message || "Nem sikerült hitelesíteni a videót.", true);
          }
          return;
        }
        viewToken = data.view_token || "";
        if (selectedNgo) {
          voteBtn.disabled = false;
        }
        setStatus("Videó hitelesítve, most szavazhatsz.");
      } catch (e) {
        setStatus("Nem sikerült hitelesíteni a videót.", true);
      }
    }

    async function submitVote(){
      if (!campaign || !selectedNgo || !viewToken) return;
      await ensureFreshNonce();
      voteBtn.disabled = true;
      setStatus("Szavazat küldése…");
      trackEvent("vote_attempt", { campaign_id: campaign.id, ngo_id: selectedNgo.id });
      try {
        const res = await fetch(restBase + "/vote/cast", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": restNonce
          },
          credentials: "include",
          body: JSON.stringify({
            campaign_id: campaign.id,
            ngo_id: selectedNgo.id,
            view_token: viewToken
          })
        });
        const data = await res.json();
        if (!res.ok) {
          if (res.status === 503) {
            setStatus("Karbantartás folyamatban, kérlek próbáld később.", true);
          } else {
            if (data && data.data && data.data.retry_after) {
              setStatus((data.message || "Szavazat sikertelen.") + " (" + data.data.retry_after + "s)", true);
            } else {
              setStatus(data.message || "Szavazat sikertelen.", true);
            }
          }
          trackEvent("vote_fail", { campaign_id: campaign.id, ngo_id: selectedNgo.id });
          return;
        }
        setStatus("Köszönjük! Szavazat rögzítve.");
        if (microMessageEl) {
          microMessageEl.textContent = "Köszönjük, hogy szavaztál.";
        }
        setStepState(3, "done");
        trackEvent("vote_success", { campaign_id: campaign.id, ngo_id: selectedNgo.id });
        voteBtn.textContent = "Köszönjük!";
        voteBtn.disabled = true;
        refreshStatus();
        refreshTally();
      } catch (e) {
        setStatus("Szavazat sikertelen.", true);
      }
    }

    async function refreshStatus(){
      const res = await fetch(restBase + "/vote/status?ts=" + Date.now(), { credentials: "include" });
      if (!res.ok) return;
      const data = await res.json();
      statusState = data || statusState;
      if (statusState.voted_today) {
        voteBtn.disabled = true;
        const nextText = formatNextVote(statusState.next_vote_available_at);
        setStatus("Ma már szavaztál. Következő szavazat: " + (nextText || "holnap") + ".", true);
        renderVideo();
      }
    }

    function renderTally(items){
      if (!tallyEl) return;
      if (!items || !items.length) {
        tallyEl.textContent = "Még nincs összesített adat.";
        return;
      }
      const topIds = items.slice(0, 3).map(function(item){ return Number(item.ngo_id); });
      const totalVotes = items.reduce(function(sum, item){ return sum + (Number(item.votes) || 0); }, 0);
      tallyState.totalVotes = totalVotes;
      if (tallyState.shownNames > totalVotes) {
        tallyState.shownNames = totalVotes;
      }
      if (totalVotes === 0) {
        tallyState.shownNames = 0;
      }
      const list = document.createElement("ul");
      items.forEach(function(item){
        const ngoId = Number(item.ngo_id);
        const ngo = (campaign.ngos || []).find(function(n){ return Number(n.id) === ngoId; });
        const li = document.createElement("li");
        li.textContent = (ngo ? ngo.ngo_name : ("NGO #" + item.ngo_id)) + " ";
        const span = document.createElement("span");
        span.className = "impactshop-vote__count";
        span.textContent = "0 szavazat";
        const targetVotes = Number(item.votes) || 0;
        const start = performance.now();
        const duration = 500;
        function tick(now){
          const progress = Math.min(1, (now - start) / duration);
          const value = Math.floor(targetVotes * progress);
          span.textContent = value.toLocaleString("hu-HU") + " szavazat";
          if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        if (topIds.includes(ngoId)) {
          const badge = document.createElement("span");
          badge.className = "impactshop-vote__ngo-badge";
          badge.textContent = "Top 3";
          li.appendChild(badge);
        }
        li.appendChild(span);
        list.appendChild(li);

        const card = ngoList ? ngoList.querySelector('[data-id="' + ngoId + '"]') : null;
        if (card) {
          const existing = card.querySelector(".impactshop-vote__ngo-progress");
          if (existing) existing.remove();
          const percent = totalVotes > 0 ? (Number(item.votes) / totalVotes) * 100 : 0;
          const progressWrap = document.createElement("div");
          progressWrap.className = "impactshop-vote__ngo-progress";
          progressWrap.innerHTML = "<div class=\"impactshop-vote__ngo-progress-bar\"><span style=\"width: " + percent.toFixed(1) + "%\"></span></div><small>" + percent.toFixed(1) + "% • " + Number(item.votes).toLocaleString("hu-HU") + " szavazat</small>";
          card.appendChild(progressWrap);
        }
      });
      tallyEl.innerHTML = "<strong>Aktuális állás</strong>";
      tallyEl.appendChild(list);

      if (socialCountEl) {
        const estimate = totalVotes === 0 ? 0 : Math.min(totalVotes - 1, Math.max(1, Math.ceil(totalVotes * 0.15)));
        socialCountEl.textContent = "Az elmúlt 5 percben: " + estimate.toLocaleString("hu-HU") + " szavazat (becslés)";
      }
      if (oddsEl) {
        const odds = totalVotes > 1 ? totalVotes - 1 : 1;
        oddsEl.textContent = "🎁 Nyeremény esélyed: 1 / " + (totalVotes ? odds.toLocaleString("hu-HU") : "—");
      }
    }

    async function refreshTally(){
      if (!campaign) return;
      const headers = {};
      if (tallyEtag) headers["If-None-Match"] = tallyEtag;
      const res = await fetch(restBase + "/vote/tally?campaign_id=" + campaign.id + "&ts=" + Date.now(), { headers: headers });
      if (res.status === 304) return;
      if (!res.ok) return;
      const data = await res.json();
      tallyEtag = res.headers.get("ETag") || "";
      renderTally(data.items || []);
    }

    function startTally(){
      refreshTally();
      if (tallyTimer) clearInterval(tallyTimer);
      tallyTimer = setInterval(refreshTally, 15000);
    }

    if (voteBtn) {
      voteBtn.addEventListener("click", submitVote);
    }

    waitForIdentity().then(fetchInit);
  }

  document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll(".impactshop-vote").forEach(initVote);
  });
})();
