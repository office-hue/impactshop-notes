(() => {
  'use strict';
  if (location.origin !== 'https://app.sharity.hu' || document.getElementById('impactshop-measurement-choice')) return;
  const routes = {
    '/impactshop': 'sharity-app.impactshop', '/rolunk': 'sharity-app.about', '/cegeknek': 'sharity-app.companies',
    '/befektetoknek': 'sharity-app.investors', '/ngo-guides': 'sharity-app.ngo-guides',
    '/ngo-guides/impact-shop': 'sharity-app.ngo-guides', '/ngo-guides/impact-challenge': 'sharity-app.ngo-guides', '/ngo-guides/ngo-card': 'sharity-app.ngo-guides',
  };
  const cookieName = '__Host-impactshop-measurement-consent';
  const consent = () => document.cookie.split('; ').find(value => value.startsWith(`${cookieName}=`))?.slice(cookieName.length + 1);
  const path = () => location.pathname.replace(/\/$/, '');
  let observedPath = path(), counted = false;
  const pending = new Set();
  const panel = document.createElement('aside');
  panel.id = 'impactshop-measurement-choice';
  panel.setAttribute('aria-label', 'Napi mérési hozzájárulás');
  panel.style.cssText = 'position:fixed;bottom:12px;left:12px;right:12px;max-width:640px;z-index:10000;background:white;color:#172033;border:1px solid #cbd5e1;border-radius:12px;padding:16px;box-shadow:0 4px 16px #0002;font:14px/1.5 sans-serif';
  document.body.appendChild(panel);
  const writeConsent = value => { document.cookie = `${cookieName}=${value}; Path=/; Secure; SameSite=Lax; Max-Age=31536000`; };
  function send() {
    const routeId = routes[path()];
    if (!routeId || counted || consent() !== 'granted-v2') return;
    counted = true;
    const controller = new AbortController(); pending.add(controller);
    fetch('/wp-json/impactshop-marketing/v1/events', {
      method: 'POST', credentials: 'same-origin', referrerPolicy: 'no-referrer', headers: { 'content-type': 'application/json' },
      signal: controller.signal, body: JSON.stringify({ schemaVersion: 2, routeId }),
    }).catch(() => {}).finally(() => pending.delete(controller));
  }
  function button(label, callback) {
    const element = document.createElement('button'); element.type = 'button'; element.textContent = label;
    element.style.cssText = 'margin:8px 8px 0 0;padding:6px 10px;cursor:pointer';
    element.addEventListener('click', callback); panel.appendChild(element);
  }
  function render(showChoice = false) {
    panel.hidden = !routes[path()]; panel.replaceChildren();
    if (panel.hidden) return;
    if (consent() === 'granted-v2' && !showChoice) {
      button('Napi mérés kikapcsolása', () => { writeConsent('denied-v2'); for (const request of pending) request.abort(); pending.clear(); render(); });
    } else if (consent() === 'denied-v2' && !showChoice) {
      button('Mérési beállítások', () => render(true));
    } else {
      const text = document.createElement('p');
      text.textContent = 'Segíted az oldal fejlesztését? Hozzájárulás után csak az oldal napi megtekintésszámát növeljük. Nem tárolunk látogató- vagy munkamenet-azonosítót. Az anonim összesítéseket 25 hónapig őrizzük. Kikapcsolás után nincs új mérés; a korábbi anonim megtekintések egyenként nem törölhetők.';
      panel.appendChild(text);
      button('Elfogadom', () => { writeConsent('granted-v2'); render(); send(); });
      button('Elutasítom', () => { writeConsent('denied-v2'); render(); });
    }
  }
  function navigate() {
    if (observedPath === path()) return;
    observedPath = path(); counted = false; render(); send();
  }
  for (const method of ['pushState', 'replaceState']) {
    const original = history[method];
    history[method] = function (...args) { const result = original.apply(this, args); navigate(); return result; };
  }
  addEventListener('popstate', navigate);
  render(); send();
})();
