(function(){
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
    const restNonce = window.impactshopIdentityPanel ? window.impactshopIdentityPanel.restNonce : "";

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

    async function fetchProfile() {
      try {
        const res = await fetch(restBase + "/identity/profile?ts=" + Date.now(), {
          credentials: "include",
          cache: "no-store",
          headers: { "Cache-Control": "no-cache" }
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.pseudo_id || !data.recovery_code) {
          setStatus("Nem sikerült azonosítót kérni. Próbáld újra.", true);
          return;
        }
        if (greetingEl) {
          if (data.nickname) {
            greetingEl.textContent = "Szia " + data.nickname + "! Üdvözöllek a Sharity oldalán.";
          } else {
            greetingEl.textContent = "Szia, üdvözöllek az Impact Shop oldalán.";
          }
        }
        if (recoveryDisplay) {
          recoveryDisplay.textContent = (data && data.recovery_code) ? data.recovery_code : "—";
        }
        if (savePassword) {
          savePassword.value = (data && data.recovery_code) ? data.recovery_code : "";
        }
        if (nicknameInput && data && data.nickname && !nicknameInput.value) {
          nicknameInput.value = data.nickname;
        }
        if (pseudoDisplay && data && data.pseudo_id) {
          pseudoDisplay.textContent = data.pseudo_id;
        }
        if (saveUsername && data && data.pseudo_id) {
          saveUsername.value = data.pseudo_id;
        }
        emitIdentityReady(data && data.pseudo_id ? data.pseudo_id : "");
        setStatus("Azonosító betöltve.");
      } catch (e) {
        setStatus("Nem sikerült azonosítót kérni. Próbáld újra.", true);
      }
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
        fetchProfile();
      });
    }

    const scrollBtn = root.querySelector("[data-role=scroll-account]");
    if (scrollBtn) {
      scrollBtn.addEventListener("click", function(){
        const target = root.querySelector("#impactshop-account") || document.getElementById("impactshop-account");
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
    if (saveForm && savePasswordBtn) {
      saveForm.addEventListener("submit", async function(e){
        const pseudo = pseudoDisplay ? pseudoDisplay.textContent.trim() : "";
        const recovery = recoveryDisplay ? recoveryDisplay.textContent.trim() : "";
        if (!pseudo || pseudo === "—" || !recovery || recovery === "—") {
          e.preventDefault();
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
          e.preventDefault();
          try {
            const cred = new PasswordCredential({ id: pseudo, password: recovery, name: "Impact Shop ID" });
            await navigator.credentials.store(cred);
            setStatus("Mentve a jelszókezelőbe.");
            return;
          } catch (err) {
            setStatus("A jelszómentés nem sikerült, próbáld újra.", true);
            return;
          }
        }

        setStatus("A böngésző felajánlja a mentést a beküldés után. Ha nem kérdez rá, valószínűleg már mentve van.");
      });
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
          const res = await fetch(restBase + "/identity/restore", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": restNonce
            },
            credentials: "include",
            body: JSON.stringify({ pseudo_id: pseudo, recovery_code: recoveryRaw })
          });
          const data = await res.json();
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
          fetchProfile();
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
          const res = await fetch(restBase + "/identity/profile", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": restNonce
            },
            credentials: "same-origin",
            body: JSON.stringify({ pseudo_id: pseudo, nickname: nickname })
          });
          const data = await res.json();
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

    refreshPseudo();
    fetchProfile();
    fetchTotal();
    fetchMessages();
    setTimeout(fetchProfile, 800);
  }

  document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll(".impactshop-identity-panel").forEach(initPanel);
  });
})();
