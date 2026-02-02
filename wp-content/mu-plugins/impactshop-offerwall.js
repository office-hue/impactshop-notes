(function(){
  "use strict";

  function initOfferwall(root){
    if (!root) return;
    var restBase = (window.impactshopOfferwall && window.impactshopOfferwall.restUrl) || '';
    if (!restBase) return;

    var cardsEl = root.querySelector('[data-role="offerwall-cards"]');
    var historyEl = root.querySelector('[data-role="offerwall-history"]');
    var modal = root.querySelector('[data-role="offerwall-modal"]');
    var frame = root.querySelector('[data-role="offerwall-frame"]');
    var faqTrigger = root.querySelector('[data-role="offerwall-faq-trigger"]');
    var faqBox = root.querySelector('[data-role="offerwall-faq"]');

    var consentKey = 'impactshop_offerwall_consent_v1';

    function getCookie(name){
      var match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
      return match ? decodeURIComponent(match[2]) : '';
    }

    function ensureConsent(){
      if (localStorage.getItem(consentKey) === '1') {
        return true;
      }
      var ok = window.confirm('Az offerwall külső szolgáltató. Elfogadod a feltételeket?');
      if (ok) {
        localStorage.setItem(consentKey, '1');
      }
      return ok;
    }

    function openModal(url){
      if (!modal || !frame) return;
      frame.src = url;
      modal.classList.add('active');
    }

    function closeModal(){
      if (!modal || !frame) return;
      modal.classList.remove('active');
      frame.src = 'about:blank';
    }

    if (modal) {
      modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
      });
    }

    if (faqTrigger && faqBox) {
      faqTrigger.addEventListener('click', function(){
        faqBox.hidden = !faqBox.hidden;
      });
    }

    function renderCards(providers){
      if (!cardsEl) return;
      cardsEl.innerHTML = '';
      if (!providers || !providers.length) {
        cardsEl.innerHTML = '<div class="offerwall-card">Most nincs elérhető offerwall.</div>';
        return;
      }
      providers.forEach(function(provider){
        var card = document.createElement('div');
        card.className = 'offerwall-card';
        card.innerHTML = '<strong>' + provider.name + '</strong>' +
          '<span>Pont szorzó: ' + provider.points_multiplier + '×</span>' +
          '<span>Szavazat szorzó: ' + provider.votes_multiplier + '×</span>';
        card.addEventListener('click', function(){
          if (!ensureConsent()) return;
          var key = provider.key || '';
          if (!key) return;
          fetch(restBase + '/iframe/' + encodeURIComponent(key), { credentials: 'include' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(data){
              if (!data || data.status !== 'ok' || !data.url) return;
              openModal(data.url);
            });
        });
        cardsEl.appendChild(card);
      });
    }

    function renderHistory(items){
      if (!historyEl) return;
      historyEl.innerHTML = '';
      if (!items || !items.length) {
        historyEl.innerHTML = '<li>Nincs még teljesítés.</li>';
        return;
      }
      items.forEach(function(item){
        var li = document.createElement('li');
        var left = document.createElement('span');
        var right = document.createElement('span');
        left.textContent = (item.offer_name || 'Offer') + ' · ' + (item.provider || '');
        right.textContent = '+' + (item.points_awarded || 0) + ' pont';
        li.appendChild(left);
        li.appendChild(right);
        historyEl.appendChild(li);
      });
    }

    function fetchConfig(){
      return fetch(restBase + '/config', { credentials: 'include' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){ renderCards(data ? data.providers : []); });
    }

    function fetchHistory(){
      return fetch(restBase + '/history', { credentials: 'include' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){ renderHistory(data ? data.items : []); });
    }

    fetchConfig();
    fetchHistory();
  }

  document.addEventListener('DOMContentLoaded', function(){
    var root = document.getElementById('impactshop-offerwall');
    if (root) initOfferwall(root);
  });
})();
