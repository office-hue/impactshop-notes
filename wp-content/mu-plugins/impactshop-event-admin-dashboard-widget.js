(function () {
  "use strict";

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function esc(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function fmtDate(v) {
    if (!v) return "-";
    var d = new Date(v.replace(" ", "T") + "Z");
    if (Number.isNaN(d.getTime())) return v;
    return d.toLocaleString("hu-HU", { hour12: false });
  }

  function downloadBase64Pdf(fileName, b64) {
    var bin = atob(b64 || "");
    var len = bin.length;
    var bytes = new Uint8Array(len);
    var i;
    for (i = 0; i < len; i += 1) bytes[i] = bin.charCodeAt(i);
    var blob = new Blob([bytes], { type: "application/pdf" });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = fileName || "adomanyigazolas.pdf";
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function paymentStatusClass(status) {
    switch (status) {
      case "completed":
      case "paid":
        return "ok";
      case "pending":
      case "payment_pending":
      case "expired":
        return "warn";
      case "failed":
      case "cancelled":
      case "refunded":
      case "outbid":
        return "bad";
      default:
        return "";
    }
  }

  function mount(root) {
    var campaign = root.getAttribute("data-campaign") || "jovonkvize-2026";
    var apiRoot = (root.getAttribute("data-api-root") || "").replace(/\/$/, "");
    var nonce = root.getAttribute("data-wp-nonce") || "";
    var title = root.getAttribute("data-title") || "Privat dashboard";
    var isPublic = root.hasAttribute("data-public");

    if (!apiRoot || !nonce) {
      root.textContent = "Hianyzo API root vagy nonce.";
      return;
    }

    var donationBase = isPublic
      ? apiRoot + "/event-campaigns/" + encodeURIComponent(campaign)
      : apiRoot + "/event-campaigns/admin/" + encodeURIComponent(campaign);
    var donationTxUrl = isPublic
      ? donationBase + "/transactions/public"
      : donationBase + "/transactions";
    var auctionBase = apiRoot + "/event-auctions/admin/" + encodeURIComponent(campaign);

    root.innerHTML =
      '<style>' +
      '.ied{font-family:Manrope,Segoe UI,system-ui,sans-serif;background:#0b1220;color:#e2e8f0;border:1px solid #1e293b;border-radius:16px;padding:14px}' +
      '.ied h3{margin:0 0 10px;font-size:18px}' +
      '.ied .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}' +
      '.ied button,.ied select,.ied input{border-radius:10px;border:1px solid #334155;background:#111827;color:#e2e8f0;padding:8px 10px;font-size:13px}' +
      '.ied button{cursor:pointer;background:#1d4ed8;border-color:#1d4ed8;font-weight:700}' +
      '.ied button.alt{background:#0f172a;border-color:#334155}' +
      '.ied table{width:100%;border-collapse:collapse;font-size:12px}' +
      '.ied th,.ied td{border-bottom:1px solid #243244;padding:8px;text-align:left;vertical-align:top}' +
      '.ied .pill{display:inline-block;border:1px solid #334155;padding:2px 7px;border-radius:999px;font-size:11px}' +
      '.ied .ok{color:#86efac}.ied .warn{color:#fbbf24}.ied .bad{color:#fca5a5}' +
      '.ied .muted{color:#94a3b8;font-size:12px}' +
      '.ied .tabs button{background:#0f172a;border-color:#334155}' +
      '.ied .tabs button.active{background:#1d4ed8;border-color:#1d4ed8}' +
      '.ied .hidden{display:none}' +
      '.ied .err{color:#fca5a5;margin:6px 0}' +
      '@media (max-width:960px){.ied table{font-size:11px}}' +
      '</style>' +
      '<div class="ied">' +
      '<h3>' + esc(title) + '</h3>' +
      '<div class="row tabs">' +
      '<button type="button" data-tab="don" class="active">Jegyek es adomanyok</button>' +
      (isPublic ? '' : '<button type="button" data-tab="auc">Licit es nyertesek</button>') +
      '<span class="muted" data-role="status">Betoltes...</span>' +
      '</div>' +
      '<div data-role="error" class="err"></div>' +
      '<div data-role="auc-error" class="err"></div>' +
      '<div data-panel="don">' +
      '<div class="row">' +
      '<select data-role="don-status"><option value="">Minden statusz</option><option value="completed">completed</option><option value="pending">pending</option><option value="failed">failed</option><option value="cancelled">cancelled</option><option value="expired">expired</option><option value="refunded">refunded</option></select>' +
      '<select data-role="don-cert"><option value="">Minden cert statusz</option><option value="none">none</option><option value="pending">pending</option><option value="sent">sent</option><option value="failed">failed</option></select>' +
      '<label><input type="checkbox" data-role="don-company" /> csak ceges</label>' +
      '<input type="text" data-role="don-search" placeholder="Kereses donation_id / email / nev" />' +
      '<button type="button" data-role="don-refresh">Frissites</button>' +
      '</div>' +
      '<div style="overflow:auto"><table><thead><tr>' +
      '<th>ID</th><th>Datum</th><th>Fizetesi statusz</th><th>Nev / ceg</th><th>Email</th><th>Osszeg</th><th>Csomag / jegyek</th><th>Cert</th><th>Muveletek</th>' +
      '</tr></thead><tbody data-role="don-body"></tbody></table></div>' +
      '</div>' +
      '<div data-panel="auc" class="hidden">' +
      '<div class="row">' +
      '<input type="text" data-role="auc-item" placeholder="item_slug szuro" />' +
      '<select data-role="auc-status"><option value="">Minden statusz</option><option value="winning">winning</option><option value="outbid">outbid</option><option value="closed">closed</option><option value="payment_pending">payment_pending</option><option value="paid">paid</option></select>' +
      '<button type="button" data-role="auc-refresh">Frissites</button>' +
      '</div>' +
      '<div style="overflow:auto"><table><thead><tr>' +
      '<th>Licit UUID</th><th>Targy</th><th>Licit osszeg</th><th>Statusz</th><th>Licitelo</th><th>Email / telefon</th><th>Ido</th>' +
      '</tr></thead><tbody data-role="auc-body"></tbody></table></div>' +
      '</div>' +
      '</div>';

    var els = {
      status: qs(root, '[data-role="status"]'),
      error: qs(root, '[data-role="error"]'),
      aucError: qs(root, '[data-role="auc-error"]'),
      tabs: root.querySelectorAll('[data-tab]'),
      panels: {
        don: qs(root, '[data-panel="don"]'),
        auc: qs(root, '[data-panel="auc"]')
      },
      don: {
        body: qs(root, '[data-role="don-body"]'),
        status: qs(root, '[data-role="don-status"]'),
        cert: qs(root, '[data-role="don-cert"]'),
        company: qs(root, '[data-role="don-company"]'),
        search: qs(root, '[data-role="don-search"]'),
        refresh: qs(root, '[data-role="don-refresh"]')
      },
      auc: {
        body: qs(root, '[data-role="auc-body"]'),
        item: qs(root, '[data-role="auc-item"]'),
        status: qs(root, '[data-role="auc-status"]'),
        refresh: qs(root, '[data-role="auc-refresh"]')
      }
    };

    function setError(msg) {
      els.error.textContent = msg || "";
    }

    function setAucError(msg) {
      els.aucError.textContent = msg || "";
    }

    function setStatus(msg) {
      els.status.textContent = msg || "";
    }

    function api(url, method, bodyObj) {
      return fetch(url, {
        method: method || "GET",
        credentials: "include",
        headers: {
          "X-WP-Nonce": nonce,
          "Content-Type": "application/json"
        },
        body: bodyObj ? JSON.stringify(bodyObj) : undefined
      }).then(function (res) {
        return res.json().then(function (json) {
          if (!res.ok) {
            var msg = (json && (json.error || json.message)) || ("HTTP " + res.status);
            throw new Error(msg);
          }
          return json;
        });
      });
    }

    function renderDonations(items) {
      if (!items || !items.length) {
        els.don.body.innerHTML = '<tr><td colspan="9" class="muted">Nincs talalat.</td></tr>';
        return;
      }

      els.don.body.innerHTML = items.map(function (it) {
        var cert = esc(it.donation_cert_status || "none");
        var paymentStatus = String(it.status || "-");
        var paymentStatusBadge = '<span class="pill ' + paymentStatusClass(paymentStatus) + '">' + esc(paymentStatus) + '</span>';
        var manual = it.cert_manual_confirmed ? '<span class="pill ok">manual: igen</span>' : '<span class="pill warn">manual: nem</span>';
        var certMeta = [
          cert,
          it.donation_cert_sent_at ? ('kuldve: ' + esc(fmtDate(it.donation_cert_sent_at))) : '',
          it.cert_manual_confirmed_by ? ('by: ' + esc(it.cert_manual_confirmed_by)) : ''
        ].filter(Boolean).join(' | ');

        var who = it.is_company ? (it.company_name || it.donor_name || '-') : (it.donor_name || '-');
        var tickets = String(it.ticket_count || 0) + ' db';
        if (it.regular_ticket_count || it.supporter_ticket_count) {
          tickets += ' (A:' + String(it.regular_ticket_count || 0) + ', T:' + String(it.supporter_ticket_count || 0) + ')';
        }

        return '<tr>' +
          '<td><code>' + esc(it.donation_id) + '</code></td>' +
          '<td>' + esc(fmtDate(it.completed_at || it.created_at)) + '</td>' +
          '<td>' + paymentStatusBadge + (it.payment_method === 'bank_transfer' ? ' <span class="pill warn" title="Utalásos fizetés">🏦 utalás</span>' : '') + '</td>' +
          '<td>' + esc(who) + '</td>' +
          '<td>' + esc(it.email || '-') + '</td>' +
          '<td>' + esc(it.amount_formatted || '-') + '</td>' +
          '<td>' + esc((it.selected_package || '-') + ' / ' + tickets) + '</td>' +
          '<td><div>' + manual + '</div><div class="muted">' + esc(certMeta) + '</div></td>' +
          '<td>' +
            '<div class="row">' +
              '<button type="button" class="alt" data-act="download" data-id="' + esc(it.donation_id) + '">PDF</button>' +
              '<button type="button" class="alt" data-act="resend" data-id="' + esc(it.donation_id) + '">Ujrakuldes</button>' +
              '<button type="button" class="alt" data-act="confirm" data-id="' + esc(it.donation_id) + '" data-val="' + (it.cert_manual_confirmed ? '0' : '1') + '">' + (it.cert_manual_confirmed ? 'Visszavon' : 'Megerosit') + '</button>' +
              (it.payment_method === 'bank_transfer' && it.status === 'pending' && !isPublic ?
                '<button type="button" style="background:#0f7240;border-color:#0f7240" data-act="confirm-transfer" data-id="' + esc(it.donation_id) + '">🏦 Utalás megerősítése</button>' : '') +
            '</div>' +
          '</td>' +
        '</tr>';
      }).join('');
    }

    function renderAuctions(items) {
      if (!items || !items.length) {
        els.auc.body.innerHTML = '<tr><td colspan="7" class="muted">Nincs talalat.</td></tr>';
        return;
      }

      els.auc.body.innerHTML = items.map(function (it) {
        return '<tr>' +
          '<td><code>' + esc(it.bid_uuid) + '</code></td>' +
          '<td>' + esc(it.item_slug || '-') + '</td>' +
          '<td>' + esc(it.bid_amount_formatted || '-') + '</td>' +
          '<td><span class="pill">' + esc(it.status || '-') + '</span></td>' +
          '<td>' + esc(it.bidder_name || '-') + '</td>' +
          '<td>' + esc((it.bidder_email || '-') + ' / ' + (it.bidder_phone || '-')) + '</td>' +
          '<td>' + esc(fmtDate(it.created_at)) + '</td>' +
        '</tr>';
      }).join('');
    }

    function loadDonations() {
      setError("");
      setStatus("Adomanyok betoltese...");
      var p = new URLSearchParams();
      if (els.don.status.value) p.set("status", els.don.status.value);
      if (els.don.cert.value) p.set("cert_status", els.don.cert.value);
      if (els.don.company.checked) p.set("only_company", "1");
      if (els.don.search.value.trim()) p.set("search", els.don.search.value.trim());
      p.set("per_page", "100");

      return api(donationTxUrl + "?" + p.toString())
        .then(function (json) {
          renderDonations(json.items || []);
          setStatus("Adomanyok: " + String((json.pagination && json.pagination.total) || 0) + " db");
        })
        .catch(function (err) {
          setError("Adomanyok hiba: " + err.message);
          setStatus("Hiba");
        });
    }

    function loadAuctions() {
      setError("");
      setStatus("Licitek betoltese...");
      var p = new URLSearchParams();
      if (els.auc.item.value.trim()) p.set("item_slug", els.auc.item.value.trim());
      if (els.auc.status.value) p.set("status", els.auc.status.value);
      p.set("per_page", "100");

      return api(auctionBase + "/bids?" + p.toString())
        .then(function (json) {
          renderAuctions(json.items || []);
          setStatus("Licitek: " + String((json.pagination && json.pagination.total) || 0) + " db");
        })
        .catch(function (err) {
          setAucError("Licitek hiba: " + err.message);
          setStatus("Hiba");
        });
    }

    els.tabs.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var tab = btn.getAttribute("data-tab");
        els.tabs.forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        Object.keys(els.panels).forEach(function (k) {
          if (k === tab) {
            els.panels[k].classList.remove("hidden");
          } else {
            els.panels[k].classList.add("hidden");
          }
        });
      });
    });

    els.don.refresh.addEventListener("click", loadDonations);
    els.auc.refresh.addEventListener("click", loadAuctions);

    els.don.body.addEventListener("click", function (ev) {
      var btn = ev.target && ev.target.closest("button[data-act]");
      if (!btn) return;
      var action = btn.getAttribute("data-act");
      var id = btn.getAttribute("data-id");
      if (!id) return;

      setError("");
      setStatus("Muvelet folyamatban...");

      if (action === "download") {
        api(donationBase + "/certificate/download?donation_id=" + encodeURIComponent(id))
          .then(function (json) {
            downloadBase64Pdf(json.filename, json.pdf_base64);
            setStatus("PDF letoltve: " + id);
          })
          .catch(function (err) {
            setError("PDF letoltes hiba: " + err.message);
            setStatus("Hiba");
          });
        return;
      }

      if (action === "resend") {
        api(donationBase + "/certificate/resend", "POST", { donation_id: id, force: true })
          .then(function () {
            setStatus("Igazolas ujrakuldve: " + id);
            return loadDonations();
          })
          .catch(function (err) {
            setError("Ujrakuldes hiba: " + err.message);
            setStatus("Hiba");
          });
        return;
      }

      if (action === "confirm") {
        var val = btn.getAttribute("data-val") === "1";
        api(donationBase + "/certificate/confirm", "POST", { donation_id: id, confirmed: val })
          .then(function () {
            setStatus("Manual cert statusz frissitve: " + id);
            return loadDonations();
          })
          .catch(function (err) {
            setError("Megerosites hiba: " + err.message);
            setStatus("Hiba");
          });
      }

      if (action === "confirm-transfer") {
        if (!window.confirm('Megerősíted az utalást a következő megrendelésnél: ' + id + '?\nEz a megerősítő e-mailt is kiküldeni fogja a vásárlónak.')) {
          setStatus("");
          return;
        }
        api(donationBase + "/confirm-transfer", "POST", { donation_id: id })
          .then(function (json) {
            setStatus('Utalás megerősítve: ' + id + ' (' + (json.confirmed_at || '') + ')');
            return loadDonations();
          })
          .catch(function (err) {
            setError('Utalás megerősítési hiba: ' + err.message);
            setStatus('Hiba');
          });
      }
    });

    var initialLoads = isPublic ? [loadDonations()] : [loadDonations(), loadAuctions()];
    Promise.all(initialLoads).then(function () {
      setStatus("Kesz");
    });
  }

  document.querySelectorAll("[data-impact-event-admin-dashboard]").forEach(mount);
})();
