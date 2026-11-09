<?php
/**
 * Plugin Name: Impact Shop Profile UI
 * Description: Profile-only presentation adapter; identity and return controllers stay untouched.
 */

if (!defined('ABSPATH')) {
    exit;
}

function impactshop_profile_ui_should_render(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    return ($_SERVER['HTTP_HOST'] ?? '') === 'app.sharity.hu'
        && in_array(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), ['/profil', '/profil/'], true);
}

function impactshop_profile_ui_render(): void
{
    if (!impactshop_profile_ui_should_render()) {
        return;
    }
    ?>
    <style id="impactshop-profile-ui-style">
    body.impactshop-profile-ui .impactshop-profile-strip{box-sizing:border-box;display:flex;flex-wrap:wrap;align-items:end;gap:12px;max-width:820px;margin:24px auto 0;font:inherit}
    body.impactshop-profile-ui .impactshop-profile-strip label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;margin:0}
    body.impactshop-profile-ui .impactshop-profile-strip select{box-sizing:border-box;min-height:44px;width:auto;max-width:100%;border:2px solid #171421;border-radius:12px;padding:8px 12px;background:#fffdf7;color:#171421;font:inherit;cursor:pointer}
    body.impactshop-profile-ui #sharity-slc-btn{position:static!important;transform:none!important;display:inline-flex;min-width:44px;min-height:44px;box-sizing:border-box;border-radius:12px;margin:0;flex-shrink:0}
    body.impactshop-profile-ui .sharity-action-bar,body.impactshop-profile-ui .sharity-action-bar > a,body.impactshop-profile-ui .sharity-action-bar > button{box-sizing:border-box}
    body.impactshop-profile-ui .sharity-action-bar [data-bar="messages-upcoming"]{display:none!important}
    body.impactshop-profile-ui .sharity-action-bar [data-bar="my-causes"]{background:#fff0f3}
    body.impactshop-profile-ui .profile-ui-hidden{display:none!important}
    body.impactshop-profile-ui .impactshop-profile-strip :focus-visible{outline:2px solid #171421;outline-offset:3px}
    body.impactshop-profile-ui .profile-ui-country-close{position:absolute;right:8px;top:4px;width:44px;height:44px;border:0;border-radius:12px;color:inherit;background:transparent;font-size:24px;cursor:pointer}
    body.impactshop-profile-ui .sharity-slc__header{position:relative;padding-right:60px}
    body.impactshop-profile-ui #sharity-slc-backdrop,body.impactshop-profile-ui #sharity-slc-sheet{z-index:2147483647}
    body.impactshop-profile-ui .impactshop-profile-strip details{font-size:12px;max-width:48ch}
    body.impactshop-profile-ui .impactshop-profile-strip summary{cursor:pointer;min-height:44px;display:flex;align-items:center}
    body.impactshop-profile-ui .impactshop-profile-strip details p{margin:0 0 8px}
    body.impactshop-profile-ui .sharity-slc__sheet{max-height:85vh;max-height:85dvh;overflow-y:auto}
    body.impactshop-profile-ui .sharity-slc__row{min-height:44px;box-sizing:border-box}
    body.impactshop-profile-ui #impactshop-pwa-install,body.impactshop-profile-ui #impactshop-pwa-reopen{width:48px;height:48px;border-radius:14px}
    body.impactshop-profile-ui .impactshop-mobile-utility__toggle{width:44px;min-height:44px}
    @media(max-width:768px){body.impactshop-profile-ui .impactshop-mobile-utility{bottom:calc(var(--sharity-action-bar-height,126px) + env(safe-area-inset-bottom,0px) + 50px)}body.impactshop-profile-ui .impactshop-profile-strip{gap:10px;margin:20px 12px 0}}
    </style>
    <script id="impactshop-profile-ui">
    (function () {
      'use strict';
      var panel = document.querySelector('.impactshop-identity-panel');
      var bar = document.querySelector('.sharity-action-bar[data-sharity-quick-dock]');
      var btn = document.getElementById('sharity-slc-btn');
      var sheet = document.getElementById('sharity-slc-sheet');
      var backdrop = document.getElementById('sharity-slc-backdrop');
      var messages = bar && bar.querySelector('[data-bar="messages-upcoming"]');
      if (!panel || !panel.parentNode || !bar || !btn || !sheet || !backdrop || !messages || document.getElementById('impactshop-profile-strip')) return;
      var managed = bar.hasAttribute('data-sharity-preferences-managed');
      var rows = sheet.querySelectorAll('.sharity-slc__rows');
      var sections = sheet.querySelectorAll('.sharity-slc__section-title');
      var header = sheet.querySelector('.sharity-slc__header');
      var label = btn.querySelector('.sharity-slc__label');
      var currencySelect = document.getElementById('sharity-slc-currency');
      if (!header || !label || rows.length < 2 || sections.length < 2 || !messages.querySelector('.bar-coming-soon')) return;
      if (managed && (!currencySelect || !sheet.querySelector('[data-slc-close]') ||
          ['hu','en','de','fr'].some(function (value) { return !sheet.querySelector('[data-slc-language="'+value+'"]'); }))) return;

      var languages = ['hu','en','de','fr'], currencies = ['HUF','EUR','USD','GBP'];
      var copy = {
        hu: ['Nyelv','Deviza','Ország','Szívügyeim','Bezárás','Sharity gyorsműveletek', ['Vásárlási Segéd','Feladatok adományokért','NGO Card','Tippjáték','Vállalások','Profil','Közösség']],
        en: ['Language','Display currency','Country','My causes','Close','Quick actions', ['Shopping Assistant','Tasks for donations','NGO Card','Prediction game','Pledges','Profile','Community']],
        de: ['Sprache','Anzeigewährung','Land','Meine Anliegen','Schließen','Schnellaktionen', ['Einkaufsassistent','Aufgaben für Spenden','NGO Card','Tippspiel','Zusagen','Profil','Gemeinschaft']],
        fr: ['Langue','Devise d’affichage','Pays','Mes causes','Fermer','Actions rapides', ['Assistant shopping','Missions pour des dons','NGO Card','Jeu de pronostics','Engagements','Profil','Communauté']]
      };
      var language, currency;
      var strip = document.createElement('div');
      strip.id = 'impactshop-profile-strip'; strip.className = 'impactshop-profile-strip';
      // The legacy ?lang=en translator must not rewrite the independently selected UI language.
      [strip, btn, sheet].forEach(function (el) { el.setAttribute('data-sharity-preferences-managed',''); });
      function makeSelect(values, id) {
        var select = document.createElement('select'); select.id = id;
        values.forEach(function (value) { var option = document.createElement('option'); option.value = value; option.textContent = value.toUpperCase(); select.appendChild(option); });
        return select;
      }
      function wrap(select) {
        var wrapper = document.createElement('label'), text = document.createElement('span');
        wrapper.appendChild(text); wrapper.appendChild(select); strip.appendChild(wrapper); return text;
      }
      var languageSelect = makeSelect(languages, 'impactshop-profile-language');
      var languageLabel = wrap(languageSelect);
      if (!managed) currencySelect = makeSelect(currencies, 'impactshop-profile-currency');
      var currencyLabel = wrap(currencySelect);
      // Move the original trigger, never clone it: its controller and focus target survive.
      strip.appendChild(btn);
      var info = document.createElement('details'), summary = document.createElement('summary'), help = document.createElement('p');
      info.appendChild(summary); info.appendChild(help); strip.appendChild(info);
      var helpCopy = {
        hu: ['Mit állítok be?', 'A nyelv a Sharity felületi szövegeit állítja. A deviza csak tájékoztató megjelenítés: fizetést és elszámolást nem módosít. Az ország külön bolti beállítás.'],
        en: ['About these settings', 'Language changes Sharity interface text. Display currency is indicative only: payments and settlement are unchanged. Country is a separate shop setting.'],
        de: ['Zu diesen Einstellungen', 'Die Sprache ändert Sharitys Oberflächentexte. Die Anzeigewährung dient nur der Information: Zahlungen und Abrechnungen bleiben unverändert. Das Land ist eine separate Shop-Einstellung.'],
        fr: ['À propos des réglages', 'La langue modifie les textes de l’interface Sharity. La devise d’affichage est indicative : les paiements et règlements restent inchangés. Le pays est un réglage distinct pour la boutique.']
      };
      panel.parentNode.insertBefore(strip, panel);
      var causes = document.createElement('a'); causes.href = 'https://sharity.hu/sajat-sharity';
      causes.setAttribute('data-bar','my-causes');
      causes.innerHTML = '<span class="bar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"></path></svg></span><span></span>';
      bar.insertBefore(causes, messages);
      // Keep the complete old message subtree for the managed controller.
      messages.hidden = true;
      rows[0].classList.add('profile-ui-hidden'); sections[0].classList.add('profile-ui-hidden');
      if (managed) {
        rows[1].classList.add('profile-ui-hidden'); sections[1].classList.add('profile-ui-hidden');
        sheet.querySelectorAll('[data-slc-help]').forEach(function (el) { el.classList.add('profile-ui-hidden'); });
      }
      var title = managed ? sheet.querySelector('[data-slc-title]') : header.firstChild;
      var close = sheet.querySelector('[data-slc-close]');
      if (!managed) {
        close = document.createElement('button'); close.type = 'button'; close.className = 'profile-ui-country-close'; close.textContent = '×';
        header.appendChild(close);
        close.addEventListener('click', function () { backdrop.click(); });
        // Existing open/close listeners remain the only owners of sheet visibility.
        btn.addEventListener('click', function () { sheet.inert = false; close.focus(); });
        backdrop.addEventListener('click', function () { sheet.inert = true; btn.focus(); });
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') { sheet.inert = true; if (sheet.contains(document.activeElement)) btn.focus(); }
          if (event.key === 'Tab' && sheet.classList.contains('is-open')) {
            var links = Array.from(rows[1].querySelectorAll('a[href]')), last = links[links.length-1];
            if (event.shiftKey && document.activeElement === close) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); close.focus(); }
          }
        });
        sheet.inert = true;
      }
      // Hidden legacy/managed language anchors must not enter the existing focus trap.
      (managed ? sheet.querySelectorAll('[data-slc-language]') : rows[0].querySelectorAll('a')).forEach(function (el) { el.removeAttribute('href'); });
      function render() {
        var text = copy[language];
        summary.textContent = helpCopy[language][0]; help.textContent = helpCopy[language][1];
        strip.lang = language; languageSelect.value = language; currencySelect.value = currency;
        languageLabel.textContent = text[0]; currencyLabel.textContent = text[1]; label.textContent = text[2];
        btn.setAttribute('aria-label',text[2]); sheet.setAttribute('aria-label',text[2]); close.setAttribute('aria-label',text[4]);
        if (managed) title.textContent = text[2]; else title.nodeValue = text[2];
        sections[sections.length-1].textContent = text[2];
        causes.lastChild.textContent = text[3];
        sheet.querySelectorAll('button[data-slc-language]').forEach(function (el) { el.disabled = true; });
        if (!managed) {
          bar.lang = language; bar.setAttribute('data-sharity-preferences-managed',''); bar.setAttribute('aria-label',text[5]);
          ['shopping-assistant','offerwall','ngo-card','tip-game','commitments','account','community'].forEach(function (key,i) {
            var item = bar.querySelector('[data-bar="'+key+'"]'); if (item && item.children[1]) item.children[1].textContent = text[6][i];
          });
        }
      }
      if (managed) {
        // Read live controller state, including when optional storage is unavailable.
        language = languages.indexOf(bar.lang) >= 0 ? bar.lang : 'hu'; currency = currencySelect.value;
        languageSelect.addEventListener('change', function () {
          if (languages.indexOf(languageSelect.value) < 0) return;
          var event = new MouseEvent('click',{bubbles:true,cancelable:true}); event.preventDefault();
          sheet.querySelector('[data-slc-language="'+languageSelect.value+'"]').dispatchEvent(event);
          language = bar.lang; currency = currencySelect.value; render();
        });
        currencySelect.addEventListener('change', function () { currency = currencySelect.value; render(); });
      } else {
        // Same fieldwise preference contract as the managed bar; never touch identity state.
        function cookie(name) {
          try { var fields = document.cookie.split(';').map(function (s) { return s.trim(); }).filter(function (s) { return s.indexOf(name+'=') === 0; }); return fields.length === 1 ? decodeURIComponent(fields[0].slice(name.length+1)) : null; } catch (_) { return null; }
        }
        var stored = {}; try { var value = JSON.parse(localStorage.getItem('sharity_ui_preferences_v1') || 'null'); if (value && typeof value === 'object' && !Array.isArray(value)) stored = value; } catch (_) {}
        var savedLanguage = cookie('sharity_ui_language'), savedCurrency = cookie('sharity_display_currency');
        language = languages.indexOf(savedLanguage) >= 0 ? savedLanguage : (languages.indexOf(stored.language) >= 0 ? stored.language : 'hu');
        currency = currencies.indexOf(savedCurrency) >= 0 ? savedCurrency : (currencies.indexOf(stored.displayCurrency) >= 0 ? stored.displayCurrency : 'HUF');
        var query = new URLSearchParams(location.search), legacy = query.getAll('lang');
        if (languages.indexOf(savedLanguage) < 0 && languages.indexOf(stored.language) < 0 && legacy.length === 1 && ['hu','en'].indexOf(legacy[0]) >= 0) language = legacy[0];
        var incomingLanguage = query.getAll('ui_lang'), incomingCurrency = query.getAll('ui_currency');
        if (incomingLanguage.length === 1 && languages.indexOf(incomingLanguage[0]) >= 0) language = incomingLanguage[0];
        if (incomingCurrency.length === 1 && currencies.indexOf(incomingCurrency[0]) >= 0) currency = incomingCurrency[0];
        function keepQuery(search) {
          return search.replace(/^\?/, '').split('&').filter(function (field) { var key = new URLSearchParams(field).keys().next().value; return key !== 'ui_lang' && key !== 'ui_currency'; }).join('&');
        }
        var remaining = keepQuery(location.search), search = remaining ? '?'+remaining : '';
        if (search !== location.search) { try { history.replaceState(history.state,'',location.pathname+search+location.hash); } catch (_) {} }
        var destinations = ['https://sharity.hu/vasarlasi-seged','https://sharity.hu/offerwall','https://sharity.hu/ngo-kartyak','https://sharity.hu/vallalasok','https://sharity.hu/hatas-korok','https://factlens.eu/factlens/vb-prod/'];
        function decorate(link, preferences) {
          var href = link.getAttribute('href'), hashAt = href.indexOf('#'), hash = hashAt < 0 ? '' : href.slice(hashAt), head = hashAt < 0 ? href : href.slice(0,hashAt), queryAt = head.indexOf('?'), base = queryAt < 0 ? head : head.slice(0,queryAt);
          if (preferences && destinations.indexOf(base) < 0) return;
          var kept = queryAt < 0 ? '' : keepQuery(head.slice(queryAt+1));
          if (preferences) kept += (kept ? '&' : '')+'ui_lang='+language+'&ui_currency='+currency;
          link.setAttribute('href',base+(kept ? '?'+kept : '')+hash);
        }
        sheet.querySelectorAll('a[href]').forEach(function (link) { decorate(link,false); });
        function persist() {
          try { localStorage.setItem('sharity_ui_preferences_v1',JSON.stringify({language:language,displayCurrency:currency})); } catch (_) {}
          [['sharity_ui_language',language],['sharity_display_currency',currency]].forEach(function (pair) { try { document.cookie = pair[0]+'='+pair[1]+'; Path=/; Max-Age=31536000; SameSite=Lax'; } catch (_) {} });
          bar.querySelectorAll('a[href]').forEach(function (link) { decorate(link,true); }); render();
        }
        languageSelect.addEventListener('change', function () { if (languages.indexOf(languageSelect.value) < 0) return; language = languageSelect.value; persist(); });
        currencySelect.addEventListener('change', function () { if (currencies.indexOf(currencySelect.value) < 0) return; currency = currencySelect.value; persist(); });
        persist();
      }
      render(); document.body.classList.add('impactshop-profile-ui');
    })();
    </script>
    <?php
}
add_action('wp_footer', 'impactshop_profile_ui_render', 30);
