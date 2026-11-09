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
    /* Keep the original controller nodes/listeners, without exposing the retired country UI. */
    body.impactshop-profile-ui #sharity-slc-btn,body.impactshop-profile-ui #sharity-slc-sheet,body.impactshop-profile-ui #sharity-slc-backdrop{display:none!important}
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
    body.impactshop-profile-install-ui{padding-bottom:calc(var(--profile-ui-dock-clearance,148px) + 88px)}
    body.impactshop-profile-install-ui #impactshop-pwa-install,body.impactshop-profile-install-ui #impactshop-pwa-reopen,body.impactshop-profile-install-ui #impactshop-mobile-utility{display:none!important}
    body.impactshop-profile-install-ui .impactshop-profile-shell{box-sizing:border-box;width:calc(100% - 32px);max-width:820px}
    body.impactshop-profile-install-ui .impactshop-profile-intro{box-sizing:border-box}
    body.impactshop-profile-install-ui .impactshop-profile-intro::after{z-index:0}
    body.impactshop-profile-install-ui .impactshop-profile-intro > *{position:relative;z-index:1}
    body.impactshop-profile-install-ui .impactshop-identity-card input,body.impactshop-profile-install-ui .impactshop-identity-card select{box-sizing:border-box;min-width:0;max-width:100%}
    body.impactshop-profile-install-ui .impactshop-identity-panel button,body.impactshop-profile-install-ui .sharity-action-bar > a{min-width:0;overflow-wrap:anywhere;white-space:normal}
    body.impactshop-profile-install-ui .sharity-action-bar > a > span{min-width:0;max-width:100%}
    body.impactshop-profile-install-ui .impactshop-identity-panel :is(button,input,select,a){scroll-margin-bottom:calc(var(--profile-ui-dock-clearance,148px) + 88px)}
    #impactshop-profile-install button{box-sizing:border-box;font:inherit;cursor:pointer;color:#150f26;border:2px solid #150f26}
    #impactshop-profile-install button:focus-visible{outline:3px solid #6d3bf5;outline-offset:3px}
    #impactshop-profile-install [hidden],#impactshop-profile-install[hidden]{display:none!important}
    #impactshop-profile-install-launcher{position:fixed;right:16px;bottom:calc(var(--profile-ui-dock-clearance,148px) + 16px);z-index:10020;width:44px;height:44px;padding:10px;border-radius:50%;background:#c6ff3d;box-shadow:4px 4px 0 #150f26}
    #impactshop-profile-install-launcher svg{display:block;width:20px;height:20px}
    #impactshop-profile-install-dialog{box-sizing:border-box;position:fixed;inset:auto 12px calc(var(--profile-ui-dock-clearance,148px) + 16px);width:calc(100% - 24px);max-width:448px;max-height:calc(100dvh - var(--profile-ui-dock-clearance,148px) - 40px);overflow:auto;margin:0 auto;padding:20px;border:2px solid #150f26;border-radius:18px;background:#fff;color:#150f26;box-shadow:6px 6px 0 #6d3bf5;text-align:left;font:inherit}
    #impactshop-profile-install-dialog::backdrop{background:rgba(21,15,38,.25)}
    #impactshop-profile-install-dialog header{display:flex;align-items:flex-start;gap:12px}
    #impactshop-profile-install-dialog header > div{min-width:0;flex:1}
    #impactshop-profile-install-dialog h2{font-size:24px;line-height:1.2;color:inherit;margin:8px 0 0;overflow-wrap:anywhere}
    #impactshop-profile-install-dialog p{font-size:14px;line-height:1.6;margin:12px 0}
    #impactshop-profile-install-dialog [data-install-copy=eyebrow]{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6d3bf5;margin:0}
    #impactshop-profile-install-close{flex:0 0 44px;min-height:44px;border-radius:10px;background:#faf9f6;font-size:24px!important}
    #impactshop-profile-install-now{min-height:44px;padding:10px 16px;border-radius:12px;background:#c6ff3d;font-weight:700!important;box-shadow:3px 3px 0 #150f26}
    #impactshop-profile-install-now:disabled{opacity:.6;cursor:wait}
    #impactshop-profile-install-dialog footer{border-top:2px solid #150f261a;margin-top:20px;padding-top:12px}
    body.sharity-consent-blocking #impactshop-profile-install{display:none!important}
    @media(max-width:768px){body.impactshop-profile-ui .impactshop-profile-strip{gap:10px;margin:20px 16px 0}body.impactshop-profile-install-ui .sharity-action-bar > a{padding-left:4px;padding-right:4px}}
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
        hu: ['Mit állítok be?', 'A nyelv a Sharity felületi szövegeit állítja. A deviza csak tájékoztató megjelenítés: fizetést és elszámolást nem módosít.'],
        en: ['About these settings', 'Language changes Sharity interface text. Display currency is indicative only: payments and settlement are unchanged.'],
        de: ['Zu diesen Einstellungen', 'Die Sprache ändert Sharitys Oberflächentexte. Die Anzeigewährung dient nur der Information: Zahlungen und Abrechnungen bleiben unverändert.'],
        fr: ['À propos des réglages', 'La langue modifie les textes de l’interface Sharity. La devise d’affichage est indicative : les paiements et règlements restent inchangés.']
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
    <script id="impactshop-profile-install-ui">
    (function () {
      'use strict';
      // Presentation only: the existing PWA delegate remains the sole prompt owner.
      if (!document.querySelector('.impactshop-identity-panel') || !document.getElementById('impactshop-pwa-install') || document.getElementById('impactshop-profile-install')) return;
      var root = document.createElement('div');
      root.id = 'impactshop-profile-install';
      root.innerHTML = '<button id="impactshop-profile-install-launcher" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg></button><dialog id="impactshop-profile-install-dialog" aria-labelledby="impactshop-profile-install-title"><header><div><p data-install-copy="eyebrow"></p><h2 id="impactshop-profile-install-title" data-install-copy="title"></h2></div><button id="impactshop-profile-install-close" type="button">×</button></header><p data-install-copy="text"></p><button id="impactshop-profile-install-now" type="button" data-role="pwa-install" hidden></button><footer><strong data-install-copy="guideTitle"></strong><p data-install-copy="guide"></p></footer></dialog>';
      document.body.appendChild(root);
      var launcher = document.getElementById('impactshop-profile-install-launcher');
      var dialog = document.getElementById('impactshop-profile-install-dialog');
      var close = document.getElementById('impactshop-profile-install-close');
      var install = document.getElementById('impactshop-profile-install-now');
      // A browser without modal support keeps its working legacy controls.
      if (typeof dialog.showModal !== 'function') { root.remove(); return; }
      document.body.classList.add('impactshop-profile-install-ui');
      var promptEvent = null, pending = false, installed = false;
      var dock = document.querySelector('.sharity-action-bar[data-sharity-quick-dock]');
      function measureDock() {
        var clearance = dock && dock.getClientRects().length ? Math.max(0,Math.ceil(innerHeight - dock.getBoundingClientRect().top)) : 0;
        var value = clearance+'px';
        if (document.body.style.getPropertyValue('--profile-ui-dock-clearance') !== value) document.body.style.setProperty('--profile-ui-dock-clearance',value);
      }
      if (dock && typeof ResizeObserver === 'function') new ResizeObserver(measureDock).observe(dock);
      var copy = {
        hu: ['Sharity telepítése','Sharity a készülékeden','Telepítsd, hogy mindig kéznél legyen.','A Sharity egy ikonról megnyitható webalkalmazásként is használható. A telepítés ingyenes, és nem változtatja meg a böngészőben használt fiókodat.','Telepítési súgó bezárása','Telepítés most','iPhone vagy iPad','Nyomd meg a Megosztás gombot, válaszd a Hozzáadás a Főképernyőhöz lehetőséget, majd a Hozzáadás gombot.','Android','A böngésző menüjében válaszd az Alkalmazás telepítése vagy a Hozzáadás a kezdőképernyőhöz lehetőséget.','Safari Macen','A Megosztás menüben vagy a Fájl menüben válaszd a Hozzáadás a Dockhoz lehetőséget.','Firefox','A Sharityt könyvjelzőként vagy parancsikonként mentheted el; a böngészőben nem minden esetben jelenik meg alkalmazás-telepítés.','Számítógép','A címsorban keresd a telepítés ikonját, vagy a böngésző menüjében válaszd az Alkalmazás telepítése lehetőséget.'],
        en: ['Install Sharity','Sharity on your device','Install it to keep it close at hand.','You can use Sharity as a web app launched from an icon. Installation is free and does not change the account you use in your browser.','Close installation help','Install now','iPhone or iPad','Tap Share, choose Add to Home Screen, then tap Add.','Android','In your browser menu, choose Install app or Add to Home Screen.','Safari on Mac','In the Share or File menu, choose Add to Dock.','Firefox','You can save Sharity as a bookmark or shortcut; app installation is not always available in this browser.','Computer','Look for the install icon in the address bar, or choose Install app in the browser menu.'],
        de: ['Sharity installieren','Sharity auf deinem Gerät','Installiere Sharity und habe es immer griffbereit.','Du kannst Sharity als Web-App über ein Symbol öffnen. Die Installation ist kostenlos und ändert dein Browserkonto nicht.','Installationshilfe schließen','Jetzt installieren','iPhone oder iPad','Tippe auf Teilen, dann auf Zum Home-Bildschirm und anschließend auf Hinzufügen.','Android','Wähle im Browsermenü App installieren oder Zum Startbildschirm hinzufügen.','Safari auf dem Mac','Wähle im Menü Teilen oder Ablage die Option Zum Dock hinzufügen.','Firefox','Du kannst Sharity als Lesezeichen oder Verknüpfung speichern; eine App-Installation wird in diesem Browser nicht immer angeboten.','Computer','Suche in der Adressleiste nach dem Installationssymbol oder wähle im Browsermenü App installieren.'],
        fr: ['Installer Sharity','Sharity sur votre appareil','Installez Sharity pour l’avoir à portée de main.','Sharity peut être utilisé comme une application web accessible depuis une icône. L’installation est gratuite et ne change pas votre compte dans le navigateur.','Fermer l’aide à l’installation','Installer maintenant','iPhone ou iPad','Touchez Partager, puis Sur l’écran d’accueil, puis Ajouter.','Android','Dans le menu du navigateur, choisissez Installer l’application ou Ajouter à l’écran d’accueil.','Safari sur Mac','Dans le menu Partager ou Fichier, choisissez Ajouter au Dock.','Firefox','Vous pouvez enregistrer Sharity comme marque-page ou raccourci ; l’installation d’une application n’est pas toujours disponible dans ce navigateur.','Ordinateur','Cherchez l’icône d’installation dans la barre d’adresse ou choisissez Installer l’application dans le menu du navigateur.']
      };
      function isStandalone() { return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true; }
      function render() {
        measureDock();
        var select = document.getElementById('impactshop-profile-language');
        var language = select && copy[select.value] ? select.value : 'hu';
        var text = copy[language], ua = navigator.userAgent;
        var ios = /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
        var guide = ios ? 6 : /android/i.test(ua) ? 8 : /macintosh/i.test(ua) && /safari/i.test(ua) && !/chrome|crios|edg/i.test(ua) ? 10 : /firefox/i.test(ua) ? 12 : 14;
        root.lang = language;
        launcher.setAttribute('aria-label',text[0]); close.setAttribute('aria-label',text[4]); install.textContent = text[5];
        [['eyebrow',1],['title',2],['text',3],['guideTitle',guide],['guide',guide+1]].forEach(function (entry) { root.querySelector('[data-install-copy="'+entry[0]+'"]').textContent = text[entry[1]]; });
        install.hidden = !promptEvent; install.disabled = pending;
        root.hidden = installed || isStandalone();
        if ((root.hidden || document.body.classList.contains('sharity-consent-blocking')) && dialog.open) dialog.close();
      }
      launcher.addEventListener('click', function () { render(); if (!root.hidden && !dialog.open && !document.body.classList.contains('sharity-consent-blocking')) { dialog.showModal(); close.focus(); } });
      close.addEventListener('click', function () { dialog.close(); });
      dialog.addEventListener('close', function () { if (!root.hidden) launcher.focus(); });
      // Do not consume or prompt this event; the old PWA listener owns that operation.
      window.addEventListener('beforeinstallprompt', function (event) { promptEvent = event; pending = false; render(); });
      install.addEventListener('click', function (event) {
        if (!promptEvent || pending) { event.preventDefault(); event.stopPropagation(); return; }
        pending = true; install.disabled = true;
        var choice = promptEvent.userChoice;
        // Let this single click bubble to the existing PWA delegate.
        Promise.resolve(choice).then(function () { promptEvent = null; pending = false; render(); }, function () { promptEvent = null; pending = false; render(); });
      });
      window.addEventListener('appinstalled', function () { installed = true; promptEvent = null; render(); });
      window.addEventListener('resize', render);
      document.addEventListener('change', function (event) { if (event.target.id === 'impactshop-profile-language') render(); });
      new MutationObserver(render).observe(document.body, {attributes:true,attributeFilter:['class']});
      render();
    })();
    </script>
    <?php
}
add_action('wp_footer', 'impactshop_profile_ui_render', 30);
